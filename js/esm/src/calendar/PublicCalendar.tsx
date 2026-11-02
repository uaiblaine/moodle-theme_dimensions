// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The public calendar's client: the visitor's shell of the calendar page (docs/calendar/PROPOSAL.md section 19).
 *
 * The same grid, chips, drawer and popover as the logged-in page, over the shared modules of calendar/, with what a
 * visitor has: two keys (the public categories' events, and the public event courses), nothing saved, no category,
 * no "More" filters and no link to core's calendar, which asks a visitor to log in. Mounted by core's react_autoinit
 * on the node calendar_page.mustache draws in its visitor mode; the props carry the first answer, so the first paint
 * asks for nothing.
 *
 * - One service, {@see METHOD}, answered without a session: one call site ({@see ask()}), posted with login not
 *   required and no cachekey (a cachekey would turn it into a GET answered with ninety days of public caching), with
 *   the page's language.
 * - The latest request wins: a ticket is taken per request and checked after the await. A Pending token
 *   (theme_dimensions/calendar:range) spans the request to the painted grid.
 * - The address has one writer (calendar/history.ts): a month moved pushes an entry, a chip and the open day replace
 *   it; Back and Forward read the address again.
 * - A failure loads the address of what was asked for, which the server draws: a visitor is never shown the server's
 *   words.
 * - The description is the answer's own text (decision V2), drawn as text; nothing is asked of core.
 *
 * What it imports is the point: nothing that reads the session or asks a logged-in service
 * (categorypublic_source_test walks its whole graph).
 *
 * @module     theme_dimensions/calendar/PublicCalendar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState} from 'react';
import type {KeyboardEvent, MouseEvent} from 'react';
import {fetchOne} from '@moodle/lms/core/ajax';
import Config from '@moodle/lms/core/config';
import {redirect} from '@moodle/lms/core/location';
import Pending from '@moodle/lms/core/pending';
import {parse, say, shiftMonth} from '../daterange/calendar';
import DayDrawer from './DayDrawer';
import {gridWeeks, zone as zoneOf} from './days';
import EventPopover from './EventPopover';
import {writeAddress} from './history';
import KeyChips from './KeyChips';
import {dayDots, place} from './layout';
import type {Look} from './look';
import {DAY_PARAM, emptyAnswer, isMonth, withDay} from './model';
import type {Answer, Key, Option} from './model';
import MonthGrid, {PHONE} from './MonthGrid';
import {itemKey, overlapsOf} from './overlap';
import {load as loadStrings} from './strings';
import type {Strings} from './strings';

/** The one web service the visitor's calendar calls, answered without a session. */
export const METHOD = 'theme_dimensions_get_public_calendar_range';

/** The keys a visitor's calendar carries, in the order the chips are drawn (local\calendar\visitor::KEYS). */
export const VISITOR_KEYS: readonly Key[] = ['category', 'open'];

/** The address parameter of the keys that are on. */
const KEYS_PARAM = 'keys[]';

/** The id of the month's caption, which labels the grid. */
const CAPTION = 'dim-cal-caption';

/** The visitor's answer: the logged-in answer's shape, and the category events' descriptions as text. */
export type PublicAnswer = Answer & {descriptions: Array<{id: number, text: string}>};

/** The page's props, as calendar_page::props() builds them in its visitor mode. */
export type PublicProps = {
    month: string,
    day: string,
    today: string,
    firstday: number,
    timezone: string,
    answer: PublicAnswer,
    keys: Key[],
    modalities: Option[],
    urls: {page: string, home: string},
};

/** What the grid shows: a month and its answer, always together. */
type Shown = {month: string, answer: PublicAnswer};

/** How a request writes the address. */
type Write = 'push' | 'replace' | 'none';

/** The open popover: the item's key and the element that opened it. */
type Popped = {key: string, opener: HTMLElement};

/**
 * The visitor's keys among these, in their order; anything else dropped.
 *
 * @param {Array} keys The keys.
 * @returns {Array} The visitor's keys on.
 */
export const visitorKeys = (keys: readonly string[]): Key[] => VISITOR_KEYS.filter((key) => keys.includes(key));

/**
 * The page's address for a month, the keys on and the open day: the keys named only when one is off, an empty member
 * when both are (local\calendar\visitor::query()).
 *
 * @param {string} base The page's address without a query (urls.page).
 * @param {string} month The month, YYYY-MM.
 * @param {Array} on The keys on.
 * @param {string} day The open day, YYYY-MM-DD, or '' for none.
 * @returns {string} The address.
 */
export const publicAddress = (base: string, month: string, on: readonly Key[], day = ''): string => {
    const url = new URL(base, window.location.href);
    url.search = '';
    url.searchParams.append('month', month);
    if (day !== '') {
        url.searchParams.append(DAY_PARAM, day);
    }
    const kept = visitorKeys(on);
    if (kept.length !== VISITOR_KEYS.length) {
        (kept.length ? kept : ['']).forEach((key) => url.searchParams.append(KEYS_PARAM, key));
    }
    return url.toString();
};

/**
 * The month and the keys an address names, as the server reads them: both keys when it names none.
 *
 * @param {string} href The address.
 * @returns {object} {month: string|null, on: Array}.
 */
export const readPublicAddress = (href: string): {month: string | null, on: Key[]} => {
    const params = new URL(href, window.location.href).searchParams;
    const month = params.get('month');
    return {
        month: isMonth(month) ? month : null,
        on: params.has(KEYS_PARAM) ? visitorKeys(params.getAll(KEYS_PARAM)) : [...VISITOR_KEYS],
    };
};

/**
 * The service's arguments for a window and the keys on; called only with a key on (the service reads none as both).
 *
 * @param {string} from The first day.
 * @param {string} to The last day.
 * @param {Array} on The keys on.
 * @returns {object} The arguments, with the page's language.
 */
export const publicArgs = (from: string, to: string, on: readonly Key[]): Record<string, unknown> => ({
    from: from,
    to: to,
    keys: visitorKeys(on),
    lang: Config.language,
});

/**
 * Ask the visitor's web service one question: the one call site of this shell. Login not required, which posts to
 * service-nologin.php; no cachekey.
 *
 * @param {object} args The service's arguments.
 * @returns {Promise} The answer.
 */
export const ask = (args: Record<string, unknown>): Promise<PublicAnswer> => {
    return fetchOne<PublicAnswer>({
        methodname: METHOD,
        args: args,
    }, {loginrequired: false});
};

/**
 * The answer with every key off: no item, every day at zero, no description. Nothing is asked of the server.
 *
 * @param {Array} days The window's days.
 * @returns {object} The answer.
 */
export const emptyPublicAnswer = (days: string[]): PublicAnswer => ({...emptyAnswer(days), descriptions: []});

/**
 * The day a month opens on: today when it is in the month, else the 1st.
 *
 * @param {string} month The month, YYYY-MM.
 * @param {string} today Today, YYYY-MM-DD.
 * @returns {string}
 */
const openingDay = (month: string, today: string): string => (today.startsWith(month) ? today : `${month}-01`);

/**
 * The open day an address names, kept only when the month's grid holds it (local\calendar\page::day_from_request()).
 *
 * @param {string|null} day The day the address names.
 * @param {string} month The month shown.
 * @param {number} firstday The weekday a week begins on.
 * @returns {string|null} The day, or null.
 */
const openableDay = (day: string | null, month: string, firstday: number): string | null =>
    (day && gridWeeks(month, firstday).flat().includes(day) ? day : null);

/**
 * The public calendar.
 *
 * @param {object} props The server's props; see PublicProps.
 * @returns {object|null} The page, or nothing until its strings are read.
 */
const PublicCalendar = (props: PublicProps) => {
    const language = document.documentElement.lang || undefined;
    const firstday = Number(props.firstday) || 0;
    const [strings, setStrings] = useState<Strings | null>(null);
    const [shown, setShown] = useState<Shown>({month: props.month, answer: props.answer});
    const [on, setOn] = useState<Key[]>(() => visitorKeys(props.keys));
    const [openday, setOpenday] = useState<string | null>(() => openableDay(props.day, props.month, firstday));
    const [selected, setSelected] = useState<string>(() => openday || openingDay(props.month, props.today));
    const [focusing, setFocusing] = useState<number>(0);
    const [dayfocus, setDayfocus] = useState<number>(0);
    const [popped, setPopped] = useState<Popped | null>(null);
    const [phone, setPhone] = useState<boolean>(false);

    const root = useRef<HTMLDivElement | null>(null);
    const ticket = useRef<number>(0);
    const painting = useRef<Pending | null>(null);
    const mounting = useRef<Pending | null>(null);
    const opened = useRef<string | null>(openday);
    opened.current = openday;
    const showing = useRef<string>(shown.month);
    showing.current = shown.month;

    const zone = useMemo(() => zoneOf(props.timezone, language), [props.timezone, language]);
    const weeks = useMemo(() => gridWeeks(shown.month, firstday), [shown.month, firstday]);
    const placed = useMemo(() => place(shown.answer.events, zone), [shown.answer, zone]);
    const texts = useMemo(
        () => new Map((shown.answer.descriptions || []).map((entry) => [entry.id, entry.text])),
        [shown.answer]
    );

    useEffect(() => {
        mounting.current = new Pending('theme_dimensions/calendar:mount');
        loadStrings()
            .then((values) => {
                setStrings(values);
                return values;
            })
            .catch(() => {
                mounting.current?.resolve();
                mounting.current = null;
            });
        /**
         * Ask again for what the address names, writing nothing: Back and Forward.
         *
         * @returns {void}
         */
        const onPop = (): void => {
            const read = readPublicAddress(window.location.href);
            const month = read.month || props.month;
            const day = openableDay(new URL(window.location.href).searchParams.get(DAY_PARAM), month, firstday);
            setOn(read.on);
            request(month, read.on, 'none', day || openingDay(month, props.today), false);
            setOpenday(day);
        };
        window.addEventListener('popstate', onPop);
        return () => {
            window.removeEventListener('popstate', onPop);
            mounting.current?.resolve();
            painting.current?.resolve();
        };
    }, []);

    // The server's layer goes in the frame this layer is first painted in, and the mount is settled.
    useLayoutEffect(() => {
        if (!strings) {
            return;
        }
        const page = root.current?.closest('[data-region="dim-cal"]');
        const server = page?.querySelector<HTMLElement>('[data-region="dim-cal-server"]');
        if (server) {
            server.hidden = true;
        }
        mounting.current?.resolve();
        mounting.current = null;
    }, [strings]);

    // An answer is settled once it is painted.
    useEffect(() => {
        painting.current?.resolve();
        painting.current = null;
    }, [shown]);

    /**
     * Ask for a month and the keys on, and show the answer if it is still the latest request's.
     *
     * @param {string} month The month, YYYY-MM.
     * @param {Array} keys The keys on.
     * @param {string} write How the address is written: push, replace or none.
     * @param {string} select The day to select once the month is shown.
     * @param {boolean} focus Whether the focus follows the selected day.
     * @returns {Promise} Settles once the answer is taken or dropped.
     */
    const request = async(month: string, keys: Key[], write: Write, select: string, focus: boolean): Promise<void> => {
        const seq = ++ticket.current;
        const moved = month !== showing.current;
        if (moved) {
            setOpenday(null);
            setPopped(null);
        }
        const target = publicAddress(props.urls.page, month, keys, moved ? '' : opened.current || '');
        if (write !== 'none') {
            writeAddress(target, write === 'push');
        }
        const pending = new Pending('theme_dimensions/calendar:range');
        const days = gridWeeks(month, firstday).flat();
        try {
            const answer = keys.length
                ? await ask(publicArgs(days[0], days[days.length - 1], keys))
                : emptyPublicAnswer(days);
            if (seq !== ticket.current) {
                pending.resolve();
                return;
            }
            painting.current?.resolve();
            painting.current = pending;
            setShown({month: month, answer: answer});
            setSelected(select);
            if (focus) {
                setFocusing((tick) => tick + 1);
            }
        } catch {
            pending.resolve();
            if (seq === ticket.current) {
                // The server draws the same address without a client.
                redirect(target);
            }
        }
    };

    /**
     * Switch a key on or off: show it, and replace the address.
     *
     * @param {string} key The key.
     * @returns {void}
     */
    const toggle = (key: Key): void => {
        const next = on.includes(key) ? on.filter((other) => other !== key) : visitorKeys([...on, key]);
        setOn(next);
        request(shown.month, next, 'replace', selected, false);
    };

    /**
     * Go to the month a day is in, selecting the day.
     *
     * @param {string} iso The day.
     * @param {boolean} focus Whether the focus follows.
     * @returns {void}
     */
    const toDay = (iso: string, focus: boolean): void => {
        request(iso.slice(0, 7), on, 'push', iso, focus);
    };

    /**
     * Open a day: the drawer, or the phone's sheet, with the focus on its heading; the address gains the day.
     *
     * @param {string} iso The day.
     * @returns {void}
     */
    const openDay = (iso: string): void => {
        setSelected(iso);
        setOpenday(iso);
        setDayfocus((tick) => tick + 1);
        writeAddress(withDay(window.location.href, iso), false);
    };

    /**
     * Close the day, the focus back on its button; the address loses the day.
     *
     * @returns {void}
     */
    const closeDay = (): void => {
        setOpenday(null);
        setFocusing((tick) => tick + 1);
        writeAddress(withDay(window.location.href, ''), false);
    };

    /**
     * Select a day; while one is open, the drawer follows it.
     *
     * @param {string} iso The day.
     * @returns {void}
     */
    const selectDay = (iso: string): void => {
        setSelected(iso);
        setFocusing((tick) => tick + 1);
        if (opened.current !== null) {
            setOpenday(iso);
            writeAddress(withDay(window.location.href, iso), false);
        }
    };

    /**
     * Close the popover, the focus back on the item that opened it when asked.
     *
     * @param {boolean} refocus Whether the focus goes back to the item.
     * @returns {void}
     */
    const closePopover = useCallback((refocus: boolean): void => {
        setPopped((was) => {
            if (was && refocus) {
                was.opener.focus();
            }
            return null;
        });
    }, []);

    /**
     * The element of an item press, or null: an element marked data-cal-pop inside the page.
     *
     * @param {EventTarget} target What was pressed.
     * @returns {HTMLElement|null}
     */
    const popTarget = (target: EventTarget | null): HTMLElement | null => {
        const element = target instanceof Element ? target.closest<HTMLElement>('[data-cal-pop]') : null;
        return element && root.current?.contains(element) ? element : null;
    };

    /**
     * Open the popover of the item an element stands for.
     *
     * @param {HTMLElement} element The bar, line, card or drawer item.
     * @returns {void}
     */
    const popFrom = (element: HTMLElement): void => {
        setPopped({key: `${element.dataset.source || ''}-${element.dataset.id || ''}`, opener: element});
    };

    /**
     * A plain press on an item opens its popover; a modified one follows its href.
     *
     * @param {object} event The press.
     * @returns {void}
     */
    const onPress = (event: MouseEvent): void => {
        const element = popTarget(event.target);
        if (!element || event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        event.preventDefault();
        popFrom(element);
    };

    /**
     * Enter or Space on an item opens its popover.
     *
     * @param {object} event The key press.
     * @returns {void}
     */
    const onItemKey = (event: KeyboardEvent): void => {
        const element = popTarget(event.target);
        if (!element || event.defaultPrevented || (event.key !== 'Enter' && event.key !== ' ')) {
            return;
        }
        event.preventDefault();
        popFrom(element);
    };

    if (!strings) {
        return null;
    }

    const look: Look = {
        strings: strings,
        zone: zone,
        language: language,
        colour: 'key',
        courses: new Map(shown.answer.courses.map((course) => [course.id, course])),
        categories: new Map(shown.answer.categories.map((category) => [category.id, category])),
        home: props.urls.home,
    };
    const counts: Record<string, number> = Object.fromEntries(VISITOR_KEYS.map((key) => [key, 0]));
    shown.answer.events.forEach((item) => {
        counts[item.key] = (counts[item.key] || 0) + 1;
    });
    const caption = say(`${shown.month}-01`, {month: 'long', year: 'numeric'}, language);
    const dots = phone ? new Map(shown.answer.days.map((entry) => [entry.date, dayDots(entry.counts)])) : null;
    const poppedentry = popped ? placed.find((entry) => itemKey(entry.item) === popped.key) || null : null;
    const drawn = openday !== null && weeks.flat().includes(openday) ? openday : null;
    /**
     * Move a month from the selected day, the focus left where it is.
     *
     * @param {number} offset Whole months.
     * @returns {void}
     */
    const step = (offset: number): void => toDay(shiftMonth(parse(selected) ? selected : `${shown.month}-01`, offset), false);

    return (
        <div
            ref={root}
            className={`dim-cal__app dim-cal__app--public${phone ? ' dim-cal__app--phone' : ''}`}
            data-region="dim-cal-app"
            onClick={onPress}
            onKeyDown={onItemKey}
        >
            <div className="dim-cal__toolbar">
                <h1 className="dim-cal__title">{strings.calendar_title}</h1>
                <button type="button" className="btn btn-secondary dim-cal__today" onClick={() => toDay(props.today, false)}>
                    {strings.calendar_today}
                </button>
                <button
                    type="button"
                    className="btn btn-secondary dim-cal__step"
                    data-action="dim-cal-prev"
                    aria-label={strings.calendar_prevmonth}
                    onClick={() => step(-1)}
                >
                    <i className="fa fa-chevron-left" aria-hidden="true"></i>
                </button>
                <button
                    type="button"
                    className="btn btn-secondary dim-cal__step"
                    data-action="dim-cal-next"
                    aria-label={strings.calendar_nextmonth}
                    onClick={() => step(1)}
                >
                    <i className="fa fa-chevron-right" aria-hidden="true"></i>
                </button>
                <p className="dim-cal__caption" id={CAPTION} aria-live="polite" data-region="dim-cal-caption">{caption}</p>
                <span className="dim-cal__spacer"></span>
                <div className="dim-cal__views" role="group" aria-label={strings.calendar_view}>
                    <span className="dim-cal__view dim-cal__view--active" aria-current="true">{strings.calendar_viewmonth}</span>
                </div>
            </div>
            <div className="dim-cal__toolbar">
                <KeyChips
                    strings={strings}
                    keys={VISITOR_KEYS}
                    off={VISITOR_KEYS.filter((key) => !on.includes(key))}
                    counts={counts}
                    onToggle={toggle}
                />
            </div>
            <div
                className={`dim-cal__body${drawn !== null && !phone ? ' dim-cal__body--drawer' : ''}`}
                data-region="dim-cal-body"
            >
                <MonthGrid
                    look={look}
                    month={shown.month}
                    weeks={weeks}
                    placed={placed}
                    today={props.today}
                    selected={weeks.flat().includes(selected) ? selected : openingDay(shown.month, props.today)}
                    focusing={focusing}
                    captionid={CAPTION}
                    dots={dots}
                    onSelect={selectDay}
                    onMonth={(iso) => toDay(iso, true)}
                    onOpen={openDay}
                    onWidth={(width) => setPhone(width < PHONE)}
                />
                {drawn !== null ? (
                    <DayDrawer
                        look={look}
                        iso={drawn}
                        placed={placed}
                        variant={phone ? 'sheet' : 'drawer'}
                        calendar={null}
                        focusing={dayfocus}
                        onClose={closeDay}
                    />
                ) : null}
            </div>
            {shown.answer.truncated ? <p className="dim-cal__status">{strings.calendar_truncated}</p> : null}
            {popped && poppedentry ? (
                <EventPopover
                    look={look}
                    entry={poppedentry}
                    overlaps={overlapsOf(poppedentry, placed, zone)}
                    opener={popped.opener}
                    sheet={phone}
                    modalities={props.modalities}
                    describe={null}
                    plain={poppedentry.item.source === 'core' ? texts.get(poppedentry.item.id) || '' : ''}
                    onClose={closePopover}
                />
            ) : null}
        </div>
    );
};

export default PublicCalendar;
