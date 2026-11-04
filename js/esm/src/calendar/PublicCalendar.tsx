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
 * The public calendar's client: the visitor's shell of the calendar page.
 *
 * The same grid, event types' dropdown, drawer and popover as the logged-in page, over the shared modules of
 * calendar/, with what a visitor has: two keys (the public categories' events, and the public event courses), nothing
 * saved, no category and no "More" filters. "Clear filters" is drawn while a key is off and turns both back on.
 * Mounted by core's react_autoinit on the node calendar_page.mustache draws in its visitor mode; the props carry the
 * first answer, so the first paint asks for nothing.
 *
 * - One service, {@see METHOD}, answered without a session: one call site ({@see ask()}), posted with login not
 *   required and no cachekey (a cachekey would turn it into a GET answered with ninety days of public caching), with
 *   the page's language.
 * - The latest request wins: a ticket is taken per request and checked after the await. A Pending token
 *   (theme_dimensions/calendar:range) spans the request to the painted grid.
 * - The month view and the week view as on the logged-in page: what is shown is a span with its answer,
 *   one call of the service either way (calendar/weeks.ts); on a phone the Day view too (DayView.tsx), a day span on a desktop
 *   asked for and drawn as its month with the day's drawer open, as on the logged-in page.
 * - The address has one writer (calendar/history.ts): a month or a week moved and a view switched push an entry, a chip
 *   and the open day replace it; Back and Forward read the address again.
 * - A failure loads the address of what was asked for, which the server draws: a visitor is never shown the server's
 *   words.
 * - The description is the answer's own text, drawn as text; nothing is asked of core.
 *
 * What it imports is the point: nothing that reads the session or asks a logged-in service
 * (categorypublic_source_test walks its whole graph).
 *
 * @module     theme_dimensions/calendar/PublicCalendar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState} from 'react';
import type {KeyboardEvent, MouseEvent, ReactNode} from 'react';
import {fetchOne} from '@moodle/lms/core/ajax';
import Config from '@moodle/lms/core/config';
import {redirect} from '@moodle/lms/core/location';
import Pending from '@moodle/lms/core/pending';
import {parse} from '../daterange/calendar';
import DayDrawer from './DayDrawer';
import DayView from './DayView';
import {frameStyle, gridWeeks, zone as zoneOf} from './days';
import EventPopover from './EventPopover';
import EventTypes, {ClearFilters} from './EventTypes';
import {writeAddress} from './history';
import {dayDots, place} from './layout';
import type {Look} from './look';
import {DAY_PARAM, emptyAnswer, isMonth, withDay} from './model';
import type {Answer, Key, Option} from './model';
import MonthGrid, {PHONE, guessPhone} from './MonthGrid';
import {itemKey, overlapsOf} from './overlap';
import {load as loadStrings} from './strings';
import type {Strings} from './strings';
import ViewSwitch from './ViewSwitch';
import WeekGrid from './WeekGrid';
import {
    deskOf,
    drawerOf,
    gridOf,
    inSpan,
    monthOfSpan,
    openOf,
    openingOf,
    readSpan,
    selectedIn,
    spanDays,
    spanKey,
    spanLabels,
    spanOf,
    stepSpan,
} from './weeks';
import type {Span, View} from './weeks';

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
    view: string,
    week: string,
    day: string,
    today: string,
    now: number,
    firstday: number,
    timezone: string,
    answer: PublicAnswer,
    keys: Key[],
    modalities: Option[],
    models?: Option[],
    urls: {page: string, home: string},
};

/** What the page shows: a month or a week and its answer, always together. */
type Shown = {span: Span, answer: PublicAnswer};

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
 * The span the server's props name: the week or the day (a phone's) when they say so and name
 * one, else the month.
 *
 * @param {object} props The server's props.
 * @returns {object} The span.
 */
const propsSpan = (props: PublicProps): Span => {
    if (props.view === 'day' && parse(props.day)) {
        return {view: 'day', day: String(props.day)};
    }
    if (props.view === 'week' && parse(props.week)) {
        return {view: 'week', week: String(props.week)};
    }
    return {view: 'month', month: props.month};
};

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
    const [shown, setShown] = useState<Shown>(() => ({span: propsSpan(props), answer: props.answer}));
    const [on, setOn] = useState<Key[]>(() => visitorKeys(props.keys));
    const [openday, setOpenday] = useState<string | null>(
        () => openOf(propsSpan(props), props.day, spanDays(propsSpan(props), firstday))
    );
    const [selected, setSelected] = useState<string>(() => openday || openingOf(propsSpan(props), props.today));
    const [focusing, setFocusing] = useState<number>(0);
    const [dayfocus, setDayfocus] = useState<number>(0);
    const [popped, setPopped] = useState<Popped | null>(null);
    const [phone, setPhone] = useState<boolean>(guessPhone);
    const [gridheight, setGridHeight] = useState<number>(0);

    const root = useRef<HTMLDivElement | null>(null);
    const ticket = useRef<number>(0);
    const painting = useRef<Pending | null>(null);
    const mounting = useRef<Pending | null>(null);
    const opened = useRef<string | null>(openday);
    opened.current = openday;
    const showing = useRef<Span>(shown.span);
    showing.current = shown.span;
    // The phone decision as the last measure gave it (until the first, the media query's guess), read by the request path
    // before the render that follows it.
    const phoned = useRef<boolean>(phone);

    const zone = useMemo(() => zoneOf(props.timezone, language), [props.timezone, language]);
    const month = monthOfSpan(shown.span, selected);
    const weeks = useMemo(() => gridWeeks(month, firstday), [month, firstday]);
    const days = useMemo(() => spanDays(shown.span, firstday), [shown.span, firstday]);
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
            const span = readSpan(window.location.href, firstday, read.month || props.month, props.today);
            const day = openOf(span, new URL(window.location.href).searchParams.get(DAY_PARAM), spanDays(span, firstday));
            setOn(read.on);
            request(span, read.on, 'none', day || openingOf(span, props.today), false);
            setOpenday(day);
        };
        window.addEventListener('popstate', onPop);
        return () => {
            window.removeEventListener('popstate', onPop);
            mounting.current?.resolve();
            painting.current?.resolve();
        };
    }, []);

    // The server's layer goes in the frame this layer is first painted in, and the mount is settled. The page's
    // root is marked mounted in the same frame: that class retires the placeholder and cancels the stylesheet's
    // fail-safe, which brings the server's layer back when this module never draws.
    useLayoutEffect(() => {
        if (!strings) {
            return;
        }
        const page = root.current?.closest('[data-region="dim-cal"]');
        page?.classList.add('dim-cal--mounted');
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
     * Ask for a span and the keys on, and show the answer if it is still the latest request's. A day span on a frame
     * that is not a phone's is asked as its month, the day opened (weeks.ts deskOf()).
     *
     * @param {object} want The month, the week or the day.
     * @param {Array} keys The keys on.
     * @param {string} write How the address is written: push, replace or none.
     * @param {string} select The day to select once the span is shown.
     * @param {boolean} focus Whether the focus follows the selected day.
     * @returns {Promise} Settles once the answer is taken or dropped.
     */
    const request = async(want: Span, keys: Key[], write: Write, select: string, focus: boolean): Promise<void> => {
        const seq = ++ticket.current;
        const desk = deskOf(want, phoned.current, firstday);
        const span = desk.span;
        if (desk.open !== null) {
            opened.current = desk.open;
            setOpenday(desk.open);
        }
        const days = spanDays(span, firstday);
        // A move closes the drawer and the popover; a view switch keeps the open day its span holds.
        const moved = spanKey(span) !== spanKey(showing.current);
        const switched = span.view !== showing.current.view;
        const kept = opened.current !== null && (!moved || (switched && days.includes(opened.current))) ? opened.current : '';
        if (moved) {
            setPopped(null);
            if (kept === '') {
                setOpenday(null);
            }
        }
        const target = inSpan(publicAddress(props.urls.page, monthOfSpan(span, select), keys, kept), span);
        if (write !== 'none') {
            writeAddress(target, write === 'push');
        }
        const pending = new Pending('theme_dimensions/calendar:range');
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
            setShown({span: span, answer: answer});
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
     * Show the keys on, and replace the address; the same keys ask nothing.
     *
     * @param {Array} keys The keys on.
     * @returns {void}
     */
    const keep = (keys: readonly Key[]): void => {
        const next = visitorKeys(keys);
        if (next.length === on.length && next.every((key) => on.includes(key))) {
            return;
        }
        setOn(next);
        request(shown.span, next, 'replace', selected, false);
    };

    /**
     * A press on a day's ground opens the day, as its button does; the second click of a double-click opens nothing.
     *
     * @param {string} iso The day.
     * @param {number|null} hour The hour row pressed, unused: a visitor creates nothing.
     * @param {number} clicks The press's click count.
     * @returns {void}
     */
    const pressGround = (iso: string, hour: number | null, clicks: number): void => {
        if (clicks <= 1) {
            openDay(iso);
        }
    };

    /**
     * Turn every key back on, the focus on the event types' button.
     *
     * @returns {void}
     */
    const clear = (): void => {
        root.current?.querySelector<HTMLElement>('[data-action="dim-cal-types"]')?.focus();
        keep(VISITOR_KEYS);
    };

    /**
     * Go to the month or the week a day is in, in the view shown, selecting the day.
     *
     * @param {string} iso The day.
     * @param {boolean} focus Whether the focus follows.
     * @returns {void}
     */
    const toDay = (iso: string, focus: boolean): void => {
        request(spanOf(shown.span.view, iso, firstday), on, 'push', iso, focus);
    };

    /**
     * Switch the view, keeping the selected day: the week holding it, or its month.
     *
     * @param {string} view The view.
     * @returns {void}
     */
    const toView = (view: View): void => {
        if (view !== shown.span.view) {
            request(spanOf(view, selected, firstday), on, 'push', selected, false);
        }
    };

    /**
     * The frame's width, from the shown grid's or the Day view's one observer: the phone decision, and a day span on a
     * frame that is no longer a phone's asked again, which asks for its month.
     *
     * @param {number} width The frame's width, in px.
     * @returns {void}
     */
    const onWidth = (width: number): void => {
        const narrow = width < PHONE;
        phoned.current = narrow;
        setPhone(narrow);
        const span = showing.current;
        if (!narrow && span.view === 'day') {
            request(span, on, 'replace', span.day, false);
        }
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
    const labels = spanLabels(shown.span, days, strings, language);
    const dots = phone ? new Map(shown.answer.days.map((entry) => [entry.date, dayDots(entry.counts)])) : null;
    const poppedentry = popped ? placed.find((entry) => itemKey(entry.item) === popped.key) || null : null;
    const grid = gridOf(shown.span, phone);
    const drawn = drawerOf(shown.span, openday, days, phone);
    /**
     * Move a month or a week from the selected day, the focus left where it is.
     *
     * @param {number} offset Whole months or weeks.
     * @returns {void}
     */
    const step = (offset: number): void => toDay(stepSpan(shown.span, parse(selected) ? selected : days[0], offset), false);

    /**
     * What the frame draws: the phone's day, the week or the month (weeks.ts gridOf()).
     *
     * @returns {object} The grid.
     */
    const drawGrid = (): ReactNode => {
        if (grid === 'day') {
            return (
                <DayView look={look} iso={days[0]} placed={placed} captionid={CAPTION} onWidth={onWidth} />
            );
        }
        if (grid === 'week') {
            return (
                <WeekGrid
                    look={look}
                    days={days}
                    placed={placed}
                    today={props.today}
                    now={props.now}
                    selected={selectedIn(selected, shown.span, days, props.today)}
                    focusing={focusing}
                    captionid={CAPTION}
                    phone={phone}
                    onSelect={selectDay}
                    onWeek={(iso) => toDay(iso, true)}
                    onOpen={openDay}
                    onGround={pressGround}
                    onWidth={onWidth}
                    onHeight={setGridHeight}
                />
            );
        }
        return (
            <MonthGrid
                look={look}
                month={month}
                weeks={weeks}
                placed={placed}
                today={props.today}
                selected={weeks.flat().includes(selected) ? selected : openingDay(month, props.today)}
                focusing={focusing}
                captionid={CAPTION}
                dots={dots}
                onSelect={selectDay}
                onMonth={(iso) => toDay(iso, true)}
                onOpen={openDay}
                onGround={pressGround}
                onWidth={onWidth}
                onHeight={setGridHeight}
            />
        );
    };

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
                    aria-label={labels.previous}
                    onClick={() => step(-1)}
                >
                    <i className="fa fa-chevron-left" aria-hidden="true"></i>
                </button>
                <button
                    type="button"
                    className="btn btn-secondary dim-cal__step"
                    data-action="dim-cal-next"
                    aria-label={labels.next}
                    onClick={() => step(1)}
                >
                    <i className="fa fa-chevron-right" aria-hidden="true"></i>
                </button>
                <p className="dim-cal__caption" id={CAPTION} aria-live="polite" data-region="dim-cal-caption">{labels.caption}</p>
                <span className="dim-cal__spacer"></span>
                <ViewSwitch strings={strings} view={shown.span.view} phone={phone} onView={toView} />
            </div>
            <div className="dim-cal__toolbar">
                <EventTypes
                    strings={strings}
                    keys={VISITOR_KEYS}
                    off={VISITOR_KEYS.filter((key) => !on.includes(key))}
                    counts={counts}
                    onChange={(off: Key[]) => keep(VISITOR_KEYS.filter((key) => !off.includes(key)))}
                />
                <span className="dim-cal__spacer"></span>
                <ClearFilters shown={on.length !== VISITOR_KEYS.length} label={strings.calendar_clearfilters} onClear={clear} />
            </div>
            <div
                className={`dim-cal__body${drawn !== null && !phone ? ' dim-cal__body--drawer' : ''}`}
                data-region="dim-cal-body"
                style={frameStyle(gridheight)}
            >
                {drawGrid()}
                {drawn !== null ? (
                    <DayDrawer
                        look={look}
                        iso={drawn}
                        gridheight={gridheight}
                        placed={placed}
                        variant={phone ? 'sheet' : 'drawer'}
                        focusing={dayfocus}
                        onClose={closeDay}
                    />
                ) : null}
            </div>
            {shown.answer.truncated ? <p className="dim-cal__status">{strings.calendar_truncatedpublic}</p> : null}
            {popped && poppedentry ? (
                <EventPopover
                    look={look}
                    entry={poppedentry}
                    overlaps={overlapsOf(poppedentry, placed, zone)}
                    opener={popped.opener}
                    sheet={phone}
                    modalities={props.modalities}
                    models={props.models || []}
                    describe={null}
                    plain={poppedentry.item.source === 'core' ? texts.get(poppedentry.item.id) || '' : ''}
                    onClose={closePopover}
                />
            ) : null}
        </div>
    );
};

export default PublicCalendar;
