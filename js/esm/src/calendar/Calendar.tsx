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
 * The calendar page's client: the toolbar, the key chips, the category picker, "More filters", the month grid or the
 * week (WeekGrid.tsx, stage C4), the day drawer (the agenda sheet on a phone) and the event popover.
 *
 * Mounted by core's react_autoinit on the node calendar_page.mustache draws, never moved (a node that leaves the
 * document is unmounted). The props carry the range's first answer, so the first paint asks for nothing; once the
 * strings are read (from core's cache, where the page put them) the component draws and hides the server's layer
 * in the same frame.
 *
 * - The latest request wins: a ticket is taken per request and checked after the await; a superseded answer is
 *   dropped. A Pending token (theme_dimensions/calendar:range) spans the request to the painted grid.
 * - What is shown is a span, a month or a week (calendar/weeks.ts), with its answer: one range call either way. The
 *   view switch keeps the selected day (the week holding it, or its month), and the open day when the new span holds
 *   it. The view and the week are navigation, in the address and never in the remembered state.
 * - The address has one writer (calendar/history.ts): a month or a week moved and a view switched push an entry, a
 *   change of state replaces it; Back and Forward read the address again (popstate) and ask for what it names, writing
 *   nothing.
 * - A change of keys or filters is remembered after a quiet ({@see QUIET}), never on every press, and an unchanged
 *   state is not sent again; a Pending token (theme_dimensions/calendar:remember) spans the quiet and the request.
 * - Every key off asks the range nothing: the answer is the window's days at zero.
 * - A day opens the drawer, or on a phone the sheet (DayDrawer.tsx); the open day is `day=` in the address, written
 *   with replaceState (Back leaves the month rather than walking the days) and taken out when it closes. Moving the
 *   selected day while it is open moves it; a month or week change closes it and the popover.
 * - An item opens the popover (EventPopover.tsx) on a press, Enter or Space, through one delegated listener on the
 *   elements marked data-cal-pop; a modified press and a middle click follow the item's href.
 * - The phone layout is the frame's width, measured by the shown grid's one ResizeObserver (MonthGrid.tsx PHONE).
 *
 * @module     theme_dimensions/calendar/Calendar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState} from 'react';
import type {KeyboardEvent, MouseEvent} from 'react';
import {fetchOne} from '@moodle/lms/core/ajax';
import Pending from '@moodle/lms/core/pending';
import {load} from '../amd';
import {parse} from '../daterange/calendar';
import {RANGE_METHOD, REMEMBER_METHOD} from './account';
import CategoryPicker from './CategoryPicker';
import DayDrawer from './DayDrawer';
import {gridWeeks, zone as zoneOf} from './days';
import {description} from './description';
import EventPopover from './EventPopover';
import {writeAddress} from './history';
import KeyChips from './KeyChips';
import {dayDots, place} from './layout';
import type {Look} from './look';
import {
    KEYS,
    DAY_PARAM,
    address,
    emptyAnswer,
    keysOn,
    monthHref,
    normalise,
    rangeArgs,
    readAddress,
    sameState,
    withDay,
} from './model';
import type {Answer, Key, Props, State} from './model';
import MonthGrid, {PHONE} from './MonthGrid';
import {itemKey, overlapsOf} from './overlap';
import MoreFilters from './MoreFilters';
import {load as loadStrings} from './strings';
import type {Strings} from './strings';
import ViewSwitch from './ViewSwitch';
import WeekGrid from './WeekGrid';
import {
    dayIn,
    inSpan,
    monthOfSpan,
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

/** How long a state must hold still before it is remembered, in ms. */
export const QUIET = 800;

/** The id of the month's caption, which labels the grid. */
const CAPTION = 'dim-cal-caption';

/** What the page shows: a month or a week and its answer, always together. */
type Shown = {span: Span, answer: Answer};

/** How a request writes the address. */
type Write = 'push' | 'replace' | 'none';

/** The open popover: the item's key and the element that opened it. */
type Popped = {key: string, opener: HTMLElement};

/**
 * Show core's message for a failure.
 *
 * @param {Error} failure What failed.
 * @returns {Promise} Settles once the message is shown.
 */
const report = (failure: unknown): Promise<void> =>
    load('core/notification').then((notification) => notification.exception(failure));

/**
 * The day a month opens on: today when it is in the month, else the 1st.
 *
 * @param {string} month The month, YYYY-MM.
 * @param {string} today Today, YYYY-MM-DD.
 * @returns {string}
 */
export const openingDay = (month: string, today: string): string => (today.startsWith(month) ? today : `${month}-01`);

/**
 * The open day an address names, kept only when the span's days hold it (local\calendar\page::day_from_request()).
 *
 * @param {string|null} day The day the address names.
 * @param {Array} days The days shown.
 * @returns {string|null} The day, or null.
 */
export const openableDay = (day: string | null, days: string[]): string | null => (day && days.includes(day) ? day : null);

/**
 * The span the server's props name: the week when they say so and name one, else the month.
 *
 * @param {object} props The server's props.
 * @returns {object} The span.
 */
export const propsSpan = (props: {month: string, view?: string, week?: string}): Span =>
    (props.view === 'week' && parse(props.week) ? {view: 'week', week: String(props.week)} : {view: 'month', month: props.month});

/**
 * The page.
 *
 * @param {object} props The server's props; see Props.
 * @returns {object|null} The page, or nothing until its strings are read.
 */
const Calendar = (props: Props) => {
    const language = document.documentElement.lang || undefined;
    const firstday = Number(props.firstday) || 0;
    const [strings, setStrings] = useState<Strings | null>(null);
    const [shown, setShown] = useState<Shown>(() => ({span: propsSpan(props), answer: props.answer}));
    const [state, setState] = useState<State>(() => normalise(props.state));
    const [openday, setOpenday] = useState<string | null>(() => openableDay(props.day, spanDays(propsSpan(props), firstday)));
    const [selected, setSelected] = useState<string>(() => openday || openingOf(propsSpan(props), props.today));
    const [focusing, setFocusing] = useState<number>(0);
    const [dayfocus, setDayfocus] = useState<number>(0);
    const [popped, setPopped] = useState<Popped | null>(null);
    const [phone, setPhone] = useState<boolean>(false);

    const root = useRef<HTMLDivElement | null>(null);
    const ticket = useRef<number>(0);
    const painting = useRef<Pending | null>(null);
    const mounting = useRef<Pending | null>(null);
    const remembered = useRef<State>(normalise(props.state));
    const quiet = useRef<ReturnType<typeof setTimeout> | null>(null);
    const holding = useRef<Pending | null>(null);
    const latest = useRef<State>(normalise(props.state));
    const opened = useRef<string | null>(openday);
    opened.current = openday;
    const showing = useRef<Span>(shown.span);
    showing.current = shown.span;

    const zone = useMemo(() => zoneOf(props.timezone, language), [props.timezone, language]);
    const month = monthOfSpan(shown.span, selected);
    const weeks = useMemo(() => gridWeeks(month, firstday), [month, firstday]);
    const days = useMemo(() => spanDays(shown.span, firstday), [shown.span, firstday]);
    const placed = useMemo(() => place(shown.answer.events, zone), [shown.answer, zone]);

    useEffect(() => {
        mounting.current = new Pending('theme_dimensions/calendar:mount');
        loadStrings()
            .then((values) => {
                setStrings(values);
                return values;
            })
            .catch((failure) => {
                mounting.current?.resolve();
                mounting.current = null;
                report(failure);
            });
        /**
         * Ask again for what the address names, writing nothing: Back and Forward.
         *
         * @returns {void}
         */
        const onPop = (): void => {
            const read = readAddress(window.location.href);
            const span = readSpan(window.location.href, firstday, read.month || props.month);
            const day = openableDay(new URL(window.location.href).searchParams.get(DAY_PARAM), spanDays(span, firstday));
            request(span, read.state || normalise(props.state), 'none', day || openingOf(span, props.today), false);
            setOpenday(day);
        };
        window.addEventListener('popstate', onPop);
        return () => {
            window.removeEventListener('popstate', onPop);
            mounting.current?.resolve();
            painting.current?.resolve();
            holding.current?.resolve();
            if (quiet.current !== null) {
                clearTimeout(quiet.current);
            }
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
     * Ask the range for a span and a state, and show the answer if it is still the latest request's.
     *
     * @param {object} span The month or the week.
     * @param {object} next The state.
     * @param {string} write How the address is written: push, replace or none.
     * @param {string} select The day to select once the span is shown.
     * @param {boolean} focus Whether the focus follows the selected day.
     * @returns {Promise} Settles once the answer is taken or dropped.
     */
    const request = async(span: Span, next: State, write: Write, select: string, focus: boolean): Promise<void> => {
        const seq = ++ticket.current;
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
        if (write !== 'none') {
            writeAddress(inSpan(address(props.urls.page, monthOfSpan(span, select), next, kept), span), write === 'push');
        }
        const pending = new Pending('theme_dimensions/calendar:range');
        try {
            const answer = keysOn(next).length
                ? await fetchOne<Answer>({methodname: RANGE_METHOD, args: rangeArgs(days[0], days[days.length - 1], next)})
                : emptyAnswer(days);
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
        } catch (failure) {
            pending.resolve();
            if (seq === ticket.current) {
                report(failure);
            }
        }
    };

    /**
     * Remember a state after a quiet, unless it is the one remembered last.
     *
     * @param {object} next The state.
     * @returns {void}
     */
    const remember = (next: State): void => {
        latest.current = next;
        if (quiet.current !== null) {
            clearTimeout(quiet.current);
        }
        if (!holding.current) {
            holding.current = new Pending('theme_dimensions/calendar:remember');
        }
        quiet.current = setTimeout(() => {
            quiet.current = null;
            const hold = holding.current;
            holding.current = null;
            const sent = latest.current;
            if (sameState(sent, remembered.current)) {
                hold?.resolve();
                return;
            }
            fetchOne({methodname: REMEMBER_METHOD, args: {...sent}})
                .then((stored) => {
                    remembered.current = sent;
                    return stored;
                })
                .catch(report)
                .finally(() => hold?.resolve());
        }, QUIET);
    };

    /**
     * Take a change of state: show it, write it, remember it.
     *
     * @param {object} fields The fields that change.
     * @returns {void}
     */
    const change = (fields: Partial<State>): void => {
        const next = normalise({...state, ...fields});
        if (sameState(next, state)) {
            return;
        }
        setState(next);
        request(shown.span, next, 'replace', selected, false);
        remember(next);
    };

    /**
     * Go to the month or the week a day is in, in the view shown, selecting the day.
     *
     * @param {string} iso The day.
     * @param {boolean} focus Whether the focus follows.
     * @returns {void}
     */
    const toDay = (iso: string, focus: boolean): void => {
        request(spanOf(shown.span.view, iso, firstday), state, 'push', iso, focus);
    };

    /**
     * Switch the view, keeping the selected day: the week holding it, or its month.
     *
     * @param {string} view The view.
     * @returns {void}
     */
    const toView = (view: View): void => {
        if (view !== shown.span.view) {
            request(spanOf(view, selected, firstday), state, 'push', selected, false);
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
        colour: state.colour,
        courses: new Map(shown.answer.courses.map((course) => [course.id, course])),
        categories: new Map(shown.answer.categories.map((category) => [category.id, category])),
        home: props.urls.home,
    };
    const counts: Record<string, number> = Object.fromEntries(KEYS.map((key) => [key, 0]));
    shown.answer.events.forEach((item) => {
        counts[item.key] = (counts[item.key] || 0) + 1;
    });
    const cities = [...new Set([...props.options.city, ...shown.answer.courses.map((course) => course.city)])]
        .filter((city) => city !== '').sort((a, b) => a.localeCompare(b, language));
    const week = shown.span.view === 'week';
    const labels = spanLabels(shown.span, days, strings, language);
    const dots = phone ? new Map(shown.answer.days.map((entry) => [entry.date, dayDots(entry.counts)])) : null;
    const poppedentry = popped ? placed.find((entry) => itemKey(entry.item) === popped.key) || null : null;
    const drawn = dayIn(openday, days);
    /**
     * Move a month or a week from the selected day, the focus left where it is.
     *
     * @param {number} offset Whole months or weeks.
     * @returns {void}
     */
    const step = (offset: number): void => toDay(stepSpan(shown.span, parse(selected) ? selected : days[0], offset), false);

    return (
        <div
            ref={root}
            className={`dim-cal__app${phone ? ' dim-cal__app--phone' : ''}`}
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
                <ViewSwitch strings={strings} view={shown.span.view} onView={toView} />
                <a className="dim-cal__classic" href={monthHref(props.urls.calendar, month)}>{strings.calendar_classic}</a>
            </div>
            <div className="dim-cal__toolbar">
                <KeyChips
                    strings={strings}
                    keys={KEYS}
                    off={state.off}
                    counts={counts}
                    onToggle={(key: Key) => change({off: state.off.includes(key)
                        ? state.off.filter((other) => other !== key) : [...state.off, key]})}
                />
                <span className="dim-cal__spacer"></span>
                <CategoryPicker
                    strings={strings}
                    tree={props.tree}
                    categoryid={state.categoryid}
                    recursive={state.recursive}
                    onChange={change}
                />
                <MoreFilters
                    strings={strings}
                    state={state}
                    options={{model: props.options.model, modality: props.options.modality, city: cities}}
                    onChange={change}
                />
            </div>
            <div
                className={`dim-cal__body${drawn !== null && !phone ? ' dim-cal__body--drawer' : ''}`}
                data-region="dim-cal-body"
            >
                {week ? (
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
                        onWidth={(width) => setPhone(width < PHONE)}
                    />
                ) : (
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
                        onWidth={(width) => setPhone(width < PHONE)}
                    />
                )}
                {drawn !== null ? (
                    <DayDrawer
                        look={look}
                        iso={drawn}
                        placed={placed}
                        variant={phone ? 'sheet' : 'drawer'}
                        calendar={props.urls.calendar}
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
                    modalities={props.options.modality}
                    describe={description}
                    plain=""
                    onClose={closePopover}
                />
            ) : null}
        </div>
    );
};

export default Calendar;
