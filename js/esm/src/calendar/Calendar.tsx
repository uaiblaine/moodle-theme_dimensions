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
 * The calendar page's client: the toolbar, the event types' dropdown, the category picker, "More filters" and "Clear
 * filters", the month grid or the week (WeekGrid.tsx, stage C4), the day drawer (the agenda sheet on a phone) and the
 * event popover.
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
 * - The phone layout is the frame's width, measured by the shown grid's one ResizeObserver (MonthGrid.tsx PHONE), or
 *   the Day view's, which watches the same frame.
 * - The Day view is a phone's (DayView.tsx, PROPOSAL.md section 31): the switch offers it only on a phone, a request
 *   asks for its one day, and a day span on a desktop is asked for and drawn as its month with the day's drawer open
 *   (weeks.ts deskOf(), gridOf()), in the one request path, so a first measure, Back and Forward and a phone turned
 *   wide all land there.
 * - "Clear filters" is drawn while any filter is applied (model.ts filtered()) and puts the keys, "More filters" and
 *   the category scope back in one change (cleared()); the focus goes to the event types' button, which stays.
 * - Editing is core's (stage C5, PROPOSAL.md section 23): "New event" in the toolbar and in the drawer only when the
 *   server says core would show it (props.edits), and so only then a double-click on a day's ground or an hour row
 *   (R1, section 27.2: {@see creator()}), the popover's Edit and Delete (EditActions.tsx) and the drag only for what
 *   core's answer lists (answer.editing). Core's form, confirmation and move service do the work (editing.ts);
 *   when core says something changed, or a move is done, the span shown is asked again with the state shown, through
 *   the same ticket and Pending token as navigation, writing nothing.
 * - A drag is delegated on the root: the items let the pointer through while it is on (a class on the root), so the
 *   day under the pointer is the cell it finds (data-cal-drop), and no layout is read. A day outside core's limits is
 *   refused with core's message before anything is asked (moves.ts dropRefusal()).
 *
 * @module     theme_dimensions/calendar/Calendar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState} from 'react';
import type {DragEvent, KeyboardEvent, MouseEvent, ReactNode} from 'react';
import {fetchOne} from '@moodle/lms/core/ajax';
import Pending from '@moodle/lms/core/pending';
import {load} from '../amd';
import {parse, say} from '../daterange/calendar';
import {RANGE_METHOD, REMEMBER_METHOD, TRAILS_METHOD} from './account';
import CategoryPicker from './CategoryPicker';
import DayDrawer from './DayDrawer';
import DayView from './DayView';
import {frameStyle, gridWeeks, zone as zoneOf} from './days';
import {description} from './description';
import EditActions from './EditActions';
import EventTypes, {ClearFilters} from './EventTypes';
import {moveStart, onChange, openForm, refuse} from './editing';
import type {Change} from './editing';
import EventPopover from './EventPopover';
import {writeAddress} from './history';
import {dayDots, place} from './layout';
import type {Placed} from './layout';
import type {Look} from './look';
import {
    KEYS,
    DAY_PARAM,
    address,
    cleared,
    emptyAnswer,
    filtered,
    keysOn,
    normalise,
    rangeArgs,
    readAddress,
    sameState,
    withDay,
} from './model';
import type {Answer, Edit, Edits, Item, Key, Props, State} from './model';
import MonthGrid, {PHONE, guessPhone} from './MonthGrid';
import {itemKey, overlapsOf} from './overlap';
import MoreFilters from './MoreFilters';
import {MOVE_MINUTE, createStart, dayStart, dropRefusal, editOf, editsOf, instantOf, toolbarStart} from './moves';
import {fill, load as loadStrings} from './strings';
import type {Strings} from './strings';
import TrailView, {TrailFilters} from './TrailView';
import {emptyTrails, filterOf, readTrail, trailArgs, trailFiltered, withTrail} from './trails';
import type {TrailAnswer, TrailFilter} from './trails';
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

/** How long a state must hold still before it is remembered, in ms. */
export const QUIET = 800;

/** The long date the drawer's "New event" names. */
const LONG_DAY: Intl.DateTimeFormatOptions = {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'};

/** The id of the month's caption, which labels the grid. */
const CAPTION = 'dim-cal-caption';

/** What the page shows: a month, a week, a day or the Trilhas view's month, and its answer, always together. */
type Shown = {span: Span, answer: Answer, trails: TrailAnswer | null};

/** How a request writes the address. */
type Write = 'push' | 'replace' | 'none';

/** The open popover: the item's key and the element that opened it. */
type Popped = {key: string, opener: HTMLElement};

/** A drag under way: the item, core's answer for it and the day it starts on. */
type Moving = {item: Item, edit: Edit, from: string};

/** Where a day's ground was pressed: the day, and the hour row of the week's body when it was one. */
type Pressed = {iso: string, hour: number | null};

/** What creates an event on the grid: a day, and an hour or null. */
type Create = (iso: string, hour: number | null) => void;

/**
 * What creates an event on the grid, only while core would show "New event" (props.edits): undefined otherwise, so
 * nothing listens to a double-click.
 *
 * @param {object|undefined} edits Core's answers for creating.
 * @param {Function} create What opens core's form.
 * @returns {Function|undefined}
 */
export const creator = (edits: Edits | undefined, create: Create): Create | undefined => (edits?.create ? create : undefined);

/**
 * The drawer's creation at an hour of its day, or undefined when nothing creates.
 *
 * @param {Function|undefined} create What creates an event on the grid.
 * @param {string} iso The drawer's day.
 * @returns {Function|undefined}
 */
const atHourOf = (create: Create | undefined, iso: string): ((hour: number) => void) | undefined =>
    (create ? (hour: number) => create(iso, hour) : undefined);

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
 * The span the server's props name: the week or the day (a phone's, PROPOSAL.md section 31) when they say so and name
 * one, else the month.
 *
 * @param {object} props The server's props.
 * @returns {object} The span.
 */
export const propsSpan = (props: {
    month: string,
    view?: string,
    week?: string,
    day?: string,
    trails?: {answer: TrailAnswer | null},
}): Span => {
    if (props.view === 'trail' && props.trails?.answer) {
        return {view: 'trail', month: props.month};
    }
    if (props.view === 'day' && parse(props.day)) {
        return {view: 'day', day: String(props.day)};
    }
    if (props.view === 'week' && parse(props.week)) {
        return {view: 'week', week: String(props.week)};
    }
    return {view: 'month', month: props.month};
};

/**
 * What the page first shows: the props' span, the range's answer and, in the Trilhas view, the month's trails.
 *
 * @param {object} props The server's props.
 * @returns {object}
 */
const shownOf = (props: Props): Shown => ({span: propsSpan(props), answer: props.answer, trails: props.trails?.answer ?? null});

/**
 * The categories the shown answer names: the month's trails' in the Trilhas view, the range's otherwise.
 *
 * @param {object} shown What the page shows.
 * @returns {Array}
 */
const categoriesOf = (shown: Shown) => (shown.trails ? shown.trails.categories : shown.answer.categories);

/**
 * The day cell a drag is over, or null: the element marked data-cal-drop that holds the target.
 *
 * @param {EventTarget} target What the pointer is over.
 * @returns {HTMLElement|null}
 */
const dropCell = (target: EventTarget | null): HTMLElement | null =>
    (target instanceof Element ? target.closest<HTMLElement>('[data-cal-drop]') : null);

/**
 * "New event", only when core would show it: a button that opens core's form.
 *
 * @param {object|undefined} edits Core's answers for creating.
 * @param {string} label The button's text.
 * @param {string} name The button's accessible name.
 * @param {Function} onOpen What it does.
 * @param {boolean} icon Whether it is the "+" icon button (the day drawer's) rather than the text button.
 * @returns {object|null} The button, or nothing.
 */
const newEventButton = (
    edits: Edits | undefined,
    label: string,
    name: string,
    onOpen: () => void,
    icon: boolean = false
) => (edits?.create ? (
    <button
        type="button"
        className={icon ? 'btn btn-primary dim-cal__newevent dim-cal__newevent--icon' : 'btn btn-primary dim-cal__newevent'}
        aria-label={name}
        title={icon ? name : undefined}
        data-action="dim-cal-new"
        onClick={onOpen}
    >
        {icon ? <i className="fa fa-plus" aria-hidden="true"></i> : label}
    </button>
) : null);

/**
 * The popover's item with core's answer for it, when core lets the viewer edit it.
 *
 * @param {Map} edits Core's answers by event id.
 * @param {object|null} entry The popover's placed item.
 * @returns {object|null} The item and its entry, or null.
 */
const editedOf = (edits: Map<number, Edit>, entry: Placed | null): {item: Item, edit: Edit} | null => {
    const edit = entry ? editOf(edits, entry.item) : null;
    return entry && edit ? {item: entry.item, edit: edit} : null;
};

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
    const [shown, setShown] = useState<Shown>(() => shownOf(props));
    const offered = Boolean(props.trails?.offered);
    const [filter, setFilter] = useState<TrailFilter>(() => filterOf(props.trails));
    const [state, setState] = useState<State>(() => normalise(props.state));
    const [openday, setOpenday] = useState<string | null>(
        () => openOf(propsSpan(props), props.day, spanDays(propsSpan(props), firstday))
    );
    const [selected, setSelected] = useState<string>(() => openday || openingOf(propsSpan(props), props.today));
    const [focusing, setFocusing] = useState<number>(0);
    const [dayfocus, setDayfocus] = useState<number>(0);
    const [popped, setPopped] = useState<Popped | null>(null);
    const [phone, setPhone] = useState<boolean>(guessPhone);
    const [gridheight, setGridHeight] = useState<number>(0);
    const [dragging, setDragging] = useState<boolean>(false);

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
    const moving = useRef<Moving | null>(null);
    const pressed = useRef<Pressed | null>(null);
    const over = useRef<HTMLElement | null>(null);
    const changes = useRef<(change: Change) => void>(() => undefined);
    // The phone decision as the last measure gave it (until the first, the media query's guess), read by the request path
    // before the render that follows it.
    const phoned = useRef<boolean>(phone);
    // The Trilhas view's filters as the last change gave them, read by the request path before the render that follows.
    const filtering = useRef<TrailFilter>(filter);

    const zone = useMemo(() => zoneOf(props.timezone, language), [props.timezone, language]);
    const month = monthOfSpan(shown.span, selected);
    const weeks = useMemo(() => gridWeeks(month, firstday), [month, firstday]);
    const days = useMemo(() => spanDays(shown.span, firstday), [shown.span, firstday]);
    const placed = useMemo(() => place(shown.answer.events, zone), [shown.answer, zone]);
    const edits = useMemo(() => editsOf(shown.answer), [shown.answer]);

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
            const span = readSpan(window.location.href, firstday, read.month || props.month, props.today, offered);
            filtering.current = readTrail(window.location.href);
            setFilter(filtering.current);
            const day = openOf(span, new URL(window.location.href).searchParams.get(DAY_PARAM), spanDays(span, firstday));
            request(span, read.state || normalise(props.state), 'none', day || openingOf(span, props.today), false);
            setOpenday(day);
        };
        window.addEventListener('popstate', onPop);
        onChange((change) => changes.current(change));
        return () => {
            window.removeEventListener('popstate', onPop);
            onChange(() => undefined);
            mounting.current?.resolve();
            painting.current?.resolve();
            holding.current?.resolve();
            if (quiet.current !== null) {
                clearTimeout(quiet.current);
            }
        };
    }, []);

    // The server's layer goes in the frame this layer is first painted in, and the mount is settled. The page's
    // root is marked mounted in the same frame: that class retires the placeholder and cancels the stylesheet's
    // fail-safe, which brings the server's layer back when this module never draws (PROPOSAL section 29).
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
     * Ask the range for a span and a state, and show the answer if it is still the latest request's. A day span on a
     * frame that is not a phone's is asked as its month, the day opened (weeks.ts deskOf()).
     *
     * @param {object} want The month, the week or the day.
     * @param {object} next The state.
     * @param {string} write How the address is written: push, replace or none.
     * @param {string} select The day to select once the span is shown.
     * @param {boolean} focus Whether the focus follows the selected day.
     * @returns {Promise} Settles once the answer is taken or dropped.
     */
    const request = async(want: Span, next: State, write: Write, select: string, focus: boolean): Promise<void> => {
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
        if (write !== 'none' && span.view === 'trail') {
            // The Trilhas view opens no day; its trail and "Só as minhas" are navigation, like the view.
            writeAddress(withTrail(inSpan(address(props.urls.page, span.month, next), span), filtering.current), write === 'push');
        } else if (write !== 'none') {
            writeAddress(inSpan(address(props.urls.page, monthOfSpan(span, select), next, kept), span), write === 'push');
        }
        const pending = new Pending('theme_dimensions/calendar:range');
        try {
            let answer: Answer;
            let trails: TrailAnswer | null = null;
            if (span.view === 'trail') {
                // The Trilhas view draws no event: its answer is the month's trails (docs/trail/PLAN.md section 7).
                trails = await fetchOne<TrailAnswer>({
                    methodname: TRAILS_METHOD,
                    args: trailArgs(span.month, filtering.current, next.categoryid, next.recursive),
                });
                answer = emptyAnswer(days);
            } else {
                answer = keysOn(next).length
                    ? await fetchOne<Answer>({methodname: RANGE_METHOD, args: rangeArgs(days[0], days[days.length - 1], next)})
                    : emptyAnswer(days);
            }
            if (seq !== ticket.current) {
                pending.resolve();
                return;
            }
            painting.current?.resolve();
            painting.current = pending;
            setShown({span: span, answer: answer, trails: trails});
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
     * Take a change of the Trilhas view's filters: show it and write it; they are navigation, never remembered.
     *
     * @param {object} fields The filters that change.
     * @returns {void}
     */
    const changeTrail = (fields: Partial<TrailFilter>): void => {
        const next = {...filtering.current, ...fields};
        if (next.trail === filtering.current.trail && next.mine === filtering.current.mine) {
            return;
        }
        filtering.current = next;
        setFilter(next);
        request(shown.span, state, 'replace', selected, false);
    };

    /**
     * Put the Trilhas view's filters back: every trail, not only mine, and no category; the focus on the trail choice.
     *
     * @returns {void}
     */
    const clearTrails = (): void => {
        root.current?.querySelector<HTMLElement>('[data-action="dim-cal-trail"]')?.focus();
        filtering.current = {trail: 0, mine: false};
        setFilter(filtering.current);
        const next = normalise({...state, categoryid: 0, recursive: true});
        if (sameState(next, state)) {
            request(shown.span, state, 'replace', selected, false);
            return;
        }
        change({categoryid: 0, recursive: true});
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
     * The frame's width, from the shown grid's or the Day view's one observer: the phone decision, and a day span on a
     * frame that is no longer a phone's asked again, which asks for its month (PROPOSAL.md section 31.2).
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
            request(span, state, 'replace', span.day, false);
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

    /**
     * Ask again for the span shown with the state shown, writing nothing: after core created, changed, deleted or moved
     * an event. A delete takes the item away, so its popover closes and the focus goes to the selected day.
     *
     * @param {string} change What core said changed.
     * @returns {Promise} Settles once the answer is taken or dropped.
     */
    const refresh = (change: Change): Promise<void> => {
        if (change === 'deleted') {
            setPopped(null);
            setFocusing((tick) => tick + 1);
        }
        return request(showing.current, state, 'none', selected, false);
    };
    changes.current = refresh;

    /**
     * Open core's event form, a Pending token spanning its opening.
     *
     * @param {number|null} event The event to edit, or null for a new one.
     * @param {number|null} start A new event's start, or null for core's default.
     * @param {number|null} course An edited event's course, as core reads it off the event; null for the page's.
     * @returns {void}
     */
    const form = (event: number | null, start: number | null, course: number | null = null): void => {
        const pending = new Pending('theme_dimensions/calendar:form');
        openForm({
            context: props.edits?.context || 0,
            course: course ?? (props.edits?.course || 0),
            event: event,
            start: start,
        })
            .catch(report)
            .finally(() => pending.resolve());
    };

    /**
     * Clear the drop mark and the drag.
     *
     * @returns {void}
     */
    const endDrag = (): void => {
        over.current?.removeAttribute('data-cal-over');
        over.current = null;
        moving.current = null;
        setDragging(false);
    };

    /**
     * Start a drag on an item core lets the viewer drag; any other drag inside the page is not the page's.
     *
     * @param {object} event The drag's start.
     * @returns {void}
     */
    const onDragStart = (event: DragEvent): void => {
        const element = popTarget(event.target);
        const key = element ? `${element.dataset.source}-${element.dataset.id}` : '';
        const entry = placed.find((one) => itemKey(one.item) === key) || null;
        const edit = entry ? editOf(edits, entry.item) : null;
        if (!entry || !edit || !edit.drag) {
            return;
        }
        moving.current = {item: entry.item, edit: edit, from: zone.day(entry.item.timestart)};
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(entry.item.id));
        setDragging(true);
    };

    /**
     * Over a day: take the drop, and mark the day as core's limits would judge it.
     *
     * @param {object} event The drag over.
     * @returns {void}
     */
    const onDragOver = (event: DragEvent): void => {
        const cell = moving.current ? dropCell(event.target) : null;
        if (!moving.current || !cell) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        if (over.current !== cell) {
            over.current?.removeAttribute('data-cal-over');
            over.current = cell;
        }
        cell.setAttribute('data-cal-over', dropRefusal(moving.current.edit, cell.dataset.calDrop || '') ? 'invalid' : 'valid');
    };

    /**
     * A drop on a day: refused with core's message outside core's limits, nothing on the day it starts on, else core's
     * move service and the span asked again.
     *
     * @param {object} event The drop.
     * @returns {void}
     */
    const onDrop = (event: DragEvent): void => {
        const move = moving.current;
        const cell = dropCell(event.target);
        endDrag();
        if (!move || !cell) {
            return;
        }
        event.preventDefault();
        const day = cell.dataset.calDrop || '';
        const refusal = dropRefusal(move.edit, day);
        if (refusal) {
            refuse(refusal === 'min' ? move.edit.minerror : move.edit.maxerror).catch(report);
            return;
        }
        if (day === move.from) {
            return;
        }
        const pending = new Pending('theme_dimensions/calendar:move');
        moveStart(move.item.id, instantOf(day, MOVE_MINUTE, props.timezone))
            .then(() => refresh('updated'))
            .catch(report)
            .finally(() => pending.resolve());
    };

    /**
     * Whether core lets the viewer drag an item: its draggable, read from the answer.
     *
     * @param {object} one The item.
     * @returns {boolean}
     */
    const movable = (one: Item): boolean => editOf(edits, one)?.drag === true;

    if (!strings) {
        return null;
    }

    const look: Look = {
        strings: strings,
        zone: zone,
        language: language,
        colour: state.colour,
        courses: new Map(shown.answer.courses.map((course) => [course.id, course])),
        categories: new Map(categoriesOf(shown).map((category) => [category.id, category])),
        home: props.urls.home,
        movable: movable,
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
    const poppededit = editedOf(edits, poppedentry);
    const grid = gridOf(shown.span, phone);
    const drawn = drawerOf(shown.span, openday, days, phone);
    const trailview = shown.span.view === 'trail';
    const own = week ? days : days.filter((iso) => iso.startsWith(month));
    /**
     * Move a month or a week from the selected day, the focus left where it is.
     *
     * @param {number} offset Whole months or weeks.
     * @returns {void}
     */
    const step = (offset: number): void => toDay(stepSpan(shown.span, parse(selected) ? selected : days[0], offset), false);
    // Core's form for a new event on a day, at its hour when one is named (the drawer's hour rows).
    const createAt = creator(props.edits, (iso, hour) => form(null, createStart(iso, hour, props.now, zone, props.timezone)));
    /**
     * A press on a day's ground (anywhere in the day but its controls) opens the day, as its button does. The first
     * click of a double-click is one too: it opens the drawer, which narrows the grid, so the second click may land on
     * another day; the double-click therefore creates on the day the first click named ({@see createOnGround}).
     *
     * @param {string} iso The day.
     * @param {number|null} hour The hour row pressed in the week's body, or null.
     * @param {number} clicks The press's click count (event.detail): the second of a double-click opens nothing.
     * @returns {void}
     */
    const pressGround = (iso: string, hour: number | null, clicks: number): void => {
        if (clicks > 1) {
            return;
        }
        pressed.current = {iso: iso, hour: hour};
        openDay(iso);
    };
    // A double-click on a day's ground creates on the day (and hour) its first click pressed.
    const createOnGround = creator(props.edits, (iso, hour) => {
        const at = pressed.current || {iso: iso, hour: hour};
        pressed.current = null;
        form(null, createStart(at.iso, at.hour, props.now, zone, props.timezone));
    });
    /**
     * Put every filter back: the keys, "More filters" and the category scope, the focus on the event types' button.
     *
     * @returns {void}
     */
    const clear = (): void => {
        root.current?.querySelector<HTMLElement>('[data-action="dim-cal-types"]')?.focus();
        change(cleared());
    };

    /**
     * What the frame draws: the phone's day, the week or the month (weeks.ts gridOf()).
     *
     * @returns {object} The grid.
     */
    const drawGrid = (): ReactNode => {
        if (grid === 'trail') {
            return (
                <TrailView
                    look={look}
                    month={month}
                    answer={shown.trails ?? emptyTrails(month)}
                    today={props.today}
                    now={props.now}
                    phone={phone}
                    captionid={CAPTION}
                    onWidth={onWidth}
                />
            );
        }
        if (grid === 'day') {
            return (
                <DayView
                    look={look}
                    iso={days[0]}
                    placed={placed}
                    captionid={CAPTION}
                    action={newEventButton(props.edits, strings.calendar_newevent,
                        fill(strings.calendar_neweventon, say(days[0], LONG_DAY, language)),
                        () => form(null, dayStart(days[0], props.now, zone, props.timezone)), true)}
                    onCreate={atHourOf(createAt, days[0])}
                    onWidth={onWidth}
                />
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
                    onCreate={createOnGround}
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
                onCreate={createOnGround}
                onWidth={onWidth}
                onHeight={setGridHeight}
            />
        );
    };

    return (
        <div
            ref={root}
            className={`dim-cal__app${phone ? ' dim-cal__app--phone' : ''}${dragging ? ' dim-cal__app--moving' : ''}`}
            data-region="dim-cal-app"
            onClick={onPress}
            onKeyDown={onItemKey}
            onDragStart={onDragStart}
            onDragOver={onDragOver}
            onDrop={onDrop}
            onDragEnd={endDrag}
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
                <ViewSwitch strings={strings} view={shown.span.view} phone={phone} onView={toView} trails={offered} />
                {trailview ? null : newEventButton(props.edits, strings.calendar_newevent, strings.calendar_newevent,
                    () => form(null, toolbarStart(own, props.today, props.now, zone, props.timezone)))}
            </div>
            {trailview ? (
                <TrailFilters strings={strings} filter={filter} answer={shown.trails} onChange={changeTrail}>
                    <CategoryPicker
                        strings={strings}
                        tree={props.tree}
                        categoryid={state.categoryid}
                        recursive={state.recursive}
                        onChange={change}
                    />
                    <ClearFilters
                        shown={trailFiltered(filter, state.categoryid)}
                        label={strings.calendar_clearfilters}
                        onClear={clearTrails}
                    />
                </TrailFilters>
            ) : null}
            <div className="dim-cal__toolbar" hidden={trailview}>
                <EventTypes
                    strings={strings}
                    keys={KEYS}
                    off={state.off}
                    counts={counts}
                    onChange={(off: Key[]) => change({off: off})}
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
                <ClearFilters shown={filtered(state, props.options)} label={strings.calendar_clearfilters} onClear={clear} />
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
                        action={newEventButton(props.edits, strings.calendar_newevent,
                            fill(strings.calendar_neweventon, say(drawn, LONG_DAY, language)),
                            () => form(null, dayStart(drawn, props.now, zone, props.timezone)), true)}
                        onCreate={atHourOf(createAt, drawn)}
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
                    models={props.options.model}
                    describe={description}
                    plain=""
                    actions={poppededit ? (
                        <EditActions
                            key={itemKey(poppededit.item)}
                            item={poppededit.item}
                            edit={poppededit.edit}
                            strings={strings}
                            onEdit={() => form(poppededit.item.id, null, poppededit.item.courseid)}
                            onFailure={report}
                        />
                    ) : null}
                    onClose={closePopover}
                />
            ) : null}
        </div>
    );
};

export default Calendar;
