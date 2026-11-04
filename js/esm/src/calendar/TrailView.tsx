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
 * The Trilhas view: the trails of the months it holds, one row per trail and its steps under it.
 *
 * - On a desktop, a timeline of contiguous months (trails.ts Loaded): the month in view and the next on a first paint,
 *   another month asked for when the scroll nears the end of what is held or the arrows go back past its start, at most
 *   trails.ts capOf() held. Each step a bar from its start to its end (trails.ts barOf()), a diamond on an expected
 *   completion date, one that began earlier marked at the left edge, one that runs on past what is held cut there, an
 *   open one fading out; the trail's own row carries its span (trails.ts trailBarOf()). Above the days a band names each
 *   month held, its name a button that scrolls to its 1st; a month on its way is a quiet skeleton at the end. Weekends
 *   are hatched, a month's first day is a line, and today is a line down the rows. Consecutive steps are joined by
 *   connectors in the trail's order (trails.ts linksOf()), drawn by the same day arithmetic, aria-hidden. The days have
 *   a fixed width and only they scroll, in TrailScroll.tsx, the trail column held still; a trail's chevron (core's
 *   course-section toggle, its icons rendered by the server) folds its steps away, the view's own state, never saved;
 *   the legend follows the grid.
 * - A course's row in the trail column is a button: it scrolls the timeline to the course's bar (asking for its month
 *   first when it is not held), then opens the bar's popover once the scroll has ended; a course with no date opens it
 *   on the row. Under its name a quieter line gives its dates (trails.ts datesOf()), which its accessible name carries
 *   too. A mouse over a row lights its bar and over a bar its row (a touch never does).
 * - The desktop timeline has a zoom (trails.ts Zoom, the page's: `zoom`), days, weeks or months, which sets a day's width
 *   (the stylesheet's --dim-cal-trail-day under the section's --week or --month modifier, trails.ts dayWidth() for the
 *   arithmetic). By days the header is the weekdays and the days, today a pill, the weekends hatched; by weeks it is the
 *   weeks from the viewer's first weekday (trails.ts weekCells()), each a short month over its days, the week holding
 *   today tinted; by months the band alone names the months and the header draws the weeks' lines. Off the days no
 *   weekend is shaded; at every zoom a small "Today" tag tops today's line. A bar too narrow for its name
 *   (trails.ts namedBar()) draws none, and is still named by its step for a screen reader.
 * - On a phone, an agenda: each trail in a card, a learner's progress as one segment per step, the steps in order with
 *   their dates, a "Today" line between the steps behind and the steps ahead (trails.ts todayIndex()).
 * - For a learner of the trail each step carries its state (done, current, late, locked, upcoming) as a class and
 *   data-state from that fixed vocabulary, and the trail's row counts the steps done; for anyone else the bars are the
 *   trail colour, the schedule alone. The event types' colours never reach this view.
 * - A step opens its popover (TrailPopover.tsx) on a press, Enter or Space: the bars and the agenda's rows are buttons
 *   named by the step. The popover is drawn beside the scroller, never inside it, so its overflow cannot cut it.
 * - The toolbar's arrows and "Today" ask the view (`nav`): a month back or ahead of the first day in view, animated,
 *   asking for it when needed; the toolbar's title is the months in view (`onRange`), and the address's month is the
 *   month of the first day in view once a scroll settles (`onMonth`): the page's history writes it, never this view.
 *
 * Its ONE ResizeObserver watches the frame it is drawn in and reports the frame's width alone, the grids' rule
 * (MonthGrid.tsx PHONE): the frame's width does not depend on what it holds, so the phone decision cannot feed back
 * into the measure. Nothing is measured here, and no layout is read to place a bar or a connector: the bars, the
 * weekends and today are grid columns or multiples of the day's width the stylesheet declares; the scroller's reads are
 * TrailScroll's.
 *
 * @module     theme_dimensions/calendar/TrailView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Fragment, useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState} from 'react';
import type {CSSProperties, MutableRefObject, PointerEvent, ReactNode} from 'react';
import Pending from '@moodle/lms/core/pending';
import {say} from '../daterange/calendar';
import {isWeekend, monthAfter} from './days';
import type {Look} from './look';
import {safeHref} from './model';
import {fill} from './strings';
import type {Strings} from './strings';
import TrailPopover, {TrailIcon, stateLabel, stateLine, stepWhen} from './TrailPopover';
import TrailScroll from './TrailScroll';
import type {Near, ScrollControl, Shift} from './TrailScroll';
import {
    ANSWER_MONTHS,
    capOf,
    SKELETON_DAYS,
    ZOOMS,
    barOf,
    datesOf,
    emptyTrails,
    extendLoaded,
    linksOf,
    loadedOf,
    mergedOf,
    monthDays,
    monthStep,
    namedBar,
    todayIndex,
    trailBarOf,
    weekCells,
    weekLabel,
    weekLabelled,
    windowDays,
} from './trails';
import type {FoldIcons, Loaded, StepState, Trail, TrailAnswer, TrailFilter, TrailStep, Zoom} from './trails';

/** What the toolbar asks of the view: a month back or ahead, and today; false when the view draws no timeline. */
export type TrailNav = {step: (direction: number) => boolean, today: () => boolean};

/** What the view is handed. */
type TrailViewProps = {
    look: Look,
    month: string,
    answer: TrailAnswer | null,
    today: string,
    now: number,
    phone: boolean,
    captionid: string,
    chosen?: number,
    zoom?: Zoom,
    firstday?: number,
    icons?: FoldIcons,
    fetch?: (month: string, months: number) => Promise<TrailAnswer>,
    scope?: string,
    nav?: MutableRefObject<TrailNav | null>,
    onRange?: (range: {first: string, last: string} | null) => void,
    onMonth?: (month: string) => void,
    onDrawn?: (answer: TrailAnswer) => void,
    onWidth: (width: number) => void,
};

/** The open popover: the trail, the step and the element that opened it. */
type Popped = {trail: number, cmid: number, opener: HTMLElement};

/**
 * The months held, the set they belong to (a new answer is a new set), the filters and scope they were asked with, the
 * last shift of the first day, and a month on its way.
 */
type Win = {loaded: Loaded, generation: number, scope: string, shift: Shift, loading: boolean};

/** What the view is on its way to, once the months it needs are held. */
type Want = {kind: 'month', month: string, tries: number} | {kind: 'today', tries: number}
    | {kind: 'step', trail: number, cmid: number, tries: number};

/** The most months asked for on the way to one target before it is given up. */
const TRIES = 3;

/** No icons: a page whose props carry none draws an empty toggle box. */
const NO_ICONS: FoldIcons = {expanded: '', collapsed: '', collapsedrtl: ''};

/** A month and its year, in the band. */
const MONTH_YEAR: Intl.DateTimeFormatOptions = {month: 'long', year: 'numeric'};

/** A month alone, in the band of the month zoom, where a month is a few rem wide (the approved mockup's). */
const MONTH_ONLY: Intl.DateTimeFormatOptions = {month: 'long'};

/** A day's letter in the header. */
const WEEKDAY: Intl.DateTimeFormatOptions = {weekday: 'narrow'};

/** A short day: "Dec 10". */
const SHORT_DAY: Intl.DateTimeFormatOptions = {day: 'numeric', month: 'short'};

/** The states a learner's legend names, in the order the legend lists them. */
const LEGEND: StepState[] = ['done', 'current', 'upcoming', 'locked', 'late'];

/**
 * A new set of months from one answer, or an empty month.
 *
 * @param {object|null} answer The answer.
 * @param {string} month The month an absent answer stands for.
 * @param {number} generation The set's number.
 * @param {string} scope The filters and scope it was asked with.
 * @returns {object} See Win.
 */
const freshWin = (answer: TrailAnswer | null, month: string, generation: number, scope: string): Win => ({
    loaded: loadedOf(answer ?? emptyTrails(month)),
    generation: generation,
    scope: scope,
    shift: {key: 0, days: 0},
    loading: false,
});

/**
 * Whether a step has a date at all: one with none has no bar anywhere, and breaks its trail's chain of connectors.
 *
 * @param {object} step The step.
 * @returns {boolean}
 */
const dated = (step: TrailStep): boolean => step.start > 0 || step.end > 0;

/**
 * A step's accessible name: its name, its dates and its state.
 *
 * @param {object} step The step.
 * @param {object} look The page's look.
 * @param {number} now Now, in seconds.
 * @returns {string}
 */
const stepLabel = (step: TrailStep, look: Look, now: number): string =>
    [step.name, stepWhen(step, look), stateLine(step, look, now)].filter((part) => part !== '').join(', ');

/**
 * A course's dates for its row in the trail column: the range, "From" a start alone, "Until" an end alone, "No dates".
 *
 * @param {object} step The step.
 * @param {object} look The page's look.
 * @returns {string}
 */
const datesLine = (step: TrailStep, look: Look): string => {
    const dates = datesOf(step.start, step.end, look.zone, look.language);
    if (dates.kind === 'from') {
        return fill(look.strings.calendar_trailfrom, dates.text);
    }
    if (dates.kind === 'until') {
        return fill(look.strings.calendar_until, dates.text);
    }
    return dates.kind === 'none' ? look.strings.calendar_trailnodates : dates.text;
};

/**
 * The zoom's name on its button.
 *
 * @param {object} strings The page's strings.
 * @param {string} zoom The zoom.
 * @returns {string}
 */
const zoomLabel = (strings: Strings, zoom: Zoom): string => {
    if (zoom === 'week') {
        return strings.calendar_trailzoomweek;
    }
    return zoom === 'month' ? strings.calendar_trailzoommonth : strings.calendar_trailzoomday;
};

/**
 * The trail's progress for a learner ("2 of 5 done"), else ''.
 *
 * @param {object} trail The trail.
 * @param {object} look The page's look.
 * @returns {string}
 */
const progressOf = (trail: Trail, look: Look): string => (trail.enrolled
    ? fill(look.strings.calendar_trailprogress, {done: trail.done, count: trail.steps.length})
    : '');

/**
 * A count of courses: "1 course", "5 courses".
 *
 * @param {object} strings The page's strings.
 * @param {number} count The count.
 * @returns {string}
 */
const coursesOf = (strings: Strings, count: number): string => (count === 1
    ? strings.landing_trail_onecourse
    : fill(strings.landing_trail_coursecount, count));

/**
 * The trail's line under its name: "Enrolled · 2 of 5 done" for a learner, "Not enrolled · 2 courses" otherwise.
 *
 * @param {object} trail The trail.
 * @param {object} look The page's look.
 * @returns {string}
 */
const metaOf = (trail: Trail, look: Look): string => fill(look.strings.landing_trail_whenjoin, trail.enrolled
    ? {a: look.strings.calendar_trailenrolled, b: progressOf(trail, look)}
    : {a: look.strings.calendar_trailnotenrolled, b: coursesOf(look.strings, trail.steps.length)});

/**
 * The toolbar's count: "1 trail · 5 courses".
 *
 * @param {object} strings The page's strings.
 * @param {object} answer The trails answer.
 * @returns {string}
 */
export const countOf = (strings: Strings, answer: TrailAnswer): string => fill(strings.landing_trail_whenjoin, {
    a: answer.trails.length === 1 ? strings.calendar_trailcountone : fill(strings.calendar_trailcountmany, answer.trails.length),
    b: coursesOf(strings, answer.trails.reduce((sum, trail) => sum + trail.steps.length, 0)),
});

/**
 * A date's parts as one compact line: a literal holding a word ("4 de dez." in Portuguese) gives way to a space, so the
 * agenda's date column holds it ("4 dez."); the order and the month's spelling stay the language's.
 *
 * @param {Array} parts The formatter's parts.
 * @returns {string}
 */
const compact = (parts: Intl.DateTimeFormatPart[]): string => parts
    .map((part) => (part.type === 'literal' && /\p{L}/u.test(part.value) ? ' ' : part.value))
    .join('').replace(/\s+/g, ' ').trim();

/**
 * A day as a short compact date ("Dec 10", "10 dez.").
 *
 * @param {string} iso The day, YYYY-MM-DD.
 * @param {string|undefined} language The language tag.
 * @returns {string}
 */
const shortOf = (iso: string, language: string | undefined): string => {
    try {
        return compact(new Intl.DateTimeFormat(language, {...SHORT_DAY, timeZone: 'UTC'})
            .formatToParts(new Date(`${iso}T00:00:00Z`)));
    } catch (error) {
        return say(iso, SHORT_DAY, language);
    }
};

/**
 * What the view's filters are handed: the trail choice and "Só as minhas", then the page's own controls; the zoom and its
 * change on a desktop (none on a phone, whose agenda has no timeline).
 */
type TrailFiltersProps = {
    strings: Strings,
    filter: TrailFilter,
    answer: TrailAnswer | null,
    onChange: (fields: Partial<TrailFilter>) => void,
    zoom?: Zoom,
    onZoom?: (zoom: Zoom) => void,
    phone?: boolean,
    children?: ReactNode,
};

/**
 * The Trilhas view's toolbar row: "Trilha" (every trail the answer offers, "Todas as trilhas" first) drawn as a button
 * with the trail's icon over a real select, "Só as minhas" as a toggle button, the page's category picker and "Clear
 * filters", then on a desktop the timeline's zoom ("Dias", "Semanas", "Meses": a group of real buttons, the zoom shown
 * pressed), and the count of what is drawn at the end. The event types are not part of it.
 *
 * @param {object} props See TrailFiltersProps.
 * @returns {object} The row.
 */
export const TrailFilters = ({strings, filter, answer, onChange, zoom = 'day', onZoom, phone = false, children}:
    TrailFiltersProps) => (
    <div className="dim-cal__toolbar dim-cal__trailtools" data-region="dim-cal-trailfilters">
        <label className={`dim-cal__trailpick${filter.trail > 0 ? ' dim-cal__trailpick--chosen' : ''}`}>
            <TrailIcon />
            <span className="visually-hidden">{strings.calendar_trail}</span>
            <select
                className="dim-cal__trailselect"
                data-action="dim-cal-trail"
                value={String(filter.trail)}
                onChange={(event) => onChange({trail: Number(event.currentTarget.value)})}
            >
                <option value="0">{strings.calendar_trailall}</option>
                {(answer ? answer.options : []).map((option) => (
                    <option key={option.id} value={String(option.id)}>{option.name}</option>
                ))}
            </select>
            <i className="fa fa-chevron-down dim-cal__trailcaret" aria-hidden="true"></i>
        </label>
        <button
            type="button"
            className="btn btn-secondary dim-cal__trailmine"
            data-action="dim-cal-trailmine"
            aria-pressed={filter.mine}
            onClick={() => onChange({mine: !filter.mine})}
        >
            <i className="fa fa-user" aria-hidden="true"></i>
            {strings.calendar_trailmine}
        </button>
        {children}
        <span className="dim-cal__spacer"></span>
        {onZoom && !phone ? (
            <span
                className="dim-cal__trailzoom"
                role="group"
                aria-label={strings.calendar_trailzoom}
                data-region="dim-cal-trailzoom"
            >
                {ZOOMS.map((one) => (
                    <button
                        key={one}
                        type="button"
                        className="dim-cal__trailzoombtn"
                        data-action="dim-cal-trailzoom"
                        data-zoom={one}
                        aria-pressed={one === zoom}
                        onClick={() => onZoom(one)}
                    >
                        {zoomLabel(strings, one)}
                    </button>
                ))}
            </span>
        ) : null}
        {answer && answer.trails.length > 0 ? (
            <span className="dim-cal__trailcount" data-region="dim-cal-trailcount">{countOf(strings, answer)}</span>
        ) : null}
    </div>
);

/**
 * The view.
 *
 * @param {object} props See TrailViewProps.
 * @returns {object} The region.
 */
const TrailView = ({look, month, answer, today, now, phone, captionid, chosen = 0, zoom = 'day', firstday = 0, icons = NO_ICONS,
    fetch, scope = '', nav, onRange, onMonth, onDrawn, onWidth}: TrailViewProps) => {
    const root = useRef<HTMLElement | null>(null);
    const width = useRef<(width: number) => void>(onWidth);
    width.current = onWidth;
    const [seen, setSeen] = useState<TrailAnswer | null>(answer);
    const [win, setWin] = useState<Win>(() => freshWin(answer, month, 0, scope));
    const [popped, setPopped] = useState<Popped | null>(null);
    const [folded, setFolded] = useState<number[]>([]);
    const [inview, setInview] = useState<string>('');
    const control = useRef<ScrollControl | null>(null);
    const viewed = useRef<{first: number, last: number}>({first: 0, last: 0});
    const ranged = useRef<string>('');
    const busy = useRef<boolean>(false);
    const generation = useRef<number>(win.generation);
    const wanted = useRef<Want | null>(null);
    const finding = useRef<Pending | null>(null);
    // The reader's last action's number: a course's popover opens only for the action that asked for it.
    const asked = useRef<number>(0);
    // A month whose request failed is not asked for again by the scroll, only by the reader's next action.
    const failed = useRef<string>('');
    // The filters and the scope the months held were asked with: a load asked with others is dropped when it comes.
    const scoped = useRef<string>(scope);
    scoped.current = scope;
    // The zoom a month's answer is added at: a load on its way when the zoom switches meets the new cap.
    const zooming = useRef<Zoom>(zoom);
    zooming.current = zoom;

    // A new answer from the page (a first paint, a filter, Back or Forward) is a new set of months.
    if (seen !== answer) {
        setSeen(answer);
        setWin((was) => freshWin(answer, month, was.generation + 1, scope));
    }
    generation.current = win.generation;

    const merged = useMemo(() => mergedOf(win.loaded, look.zone), [win.loaded, look.zone]);
    const days = useMemo(() => windowDays(win.loaded.months), [win.loaded]);
    const columns = days.length + (win.loading ? SKELETON_DAYS : 0);
    const timeline = !phone && merged.trails.length > 0;

    useEffect(() => {
        const frame = root.current?.parentElement || null;
        if (!frame || typeof ResizeObserver === 'undefined') {
            return undefined;
        }
        const observer = new ResizeObserver((entries) => {
            entries.forEach((entry) => {
                // The phone decision reads the frame's width only, as the grids' do.
                if (entry.target === frame && entry.contentRect.width > 0) {
                    width.current(entry.contentRect.width);
                }
            });
        });
        observer.observe(frame);
        return () => observer.disconnect();
    }, []);

    // A new answer closes the popover: its step may be gone.
    useEffect(() => setPopped(null), [answer]);

    // With no timeline drawn the toolbar's title is the page's own again.
    useEffect(() => {
        if (!timeline) {
            ranged.current = '';
            onRange?.(null);
        }
    }, [timeline]);

    // A row and its bar lit together: a month dropped or a trail folded may take one of them away under the pointer, so
    // the pair is put out rather than left half lit.
    useLayoutEffect(() => {
        root.current?.querySelectorAll<HTMLElement>('[data-lit]').forEach((element) => element.removeAttribute('data-lit'));
    }, [win.loaded, folded]);

    // The page's count and choice follow what is drawn, in the frame it is drawn in.
    useLayoutEffect(() => onDrawn?.(merged), [merged]);

    // A search for a course that is given up, or done, lets its Pending token go.
    useEffect(() => () => finding.current?.resolve(), []);

    const close = useCallback((refocus: boolean): void => {
        setPopped((was) => {
            if (was && refocus) {
                was.opener.focus();
            }
            return null;
        });
    }, []);

    /**
     * Let a search for a course go: its target is reached or given up.
     *
     * @returns {void}
     */
    const found = (): void => {
        finding.current?.resolve();
        finding.current = null;
    };

    /**
     * Ask for one month beside what is held and add it when it comes, unless a new set of months came first; one
     * request at a time.
     *
     * @param {string} target The month, YYYY-MM.
     * @returns {void}
     */
    const extend = (target: string): void => {
        // A filter changed and the page's answer for it is on its way: no month is added to the months asked before.
        if (!fetch || busy.current || scoped.current !== win.scope) {
            return;
        }
        busy.current = true;
        const set = generation.current;
        const askedwith = win.scope;
        const ahead = target > win.loaded.months[win.loaded.months.length - 1];
        setWin((was) => ({...was, loading: ahead}));
        const pending = new Pending('theme_dimensions/calendar:trailmonth');
        fetch(target, 1)
            .then((got) => {
                setWin((was) => {
                    if (was.generation !== set || scoped.current !== askedwith) {
                        return was;
                    }
                    const grown = extendLoaded(was.loaded, got, capOf(zooming.current));
                    if (!grown) {
                        return {...was, loading: false};
                    }
                    const shift = grown.shift === 0 ? was.shift : {key: was.shift.key + 1, days: grown.shift};
                    return {...was, loaded: grown.loaded, shift: shift, loading: false};
                });
                return got;
            })
            .catch(() => {
                // The scroll asks for this month no more; a target that needs it tries again, a few times at most.
                failed.current = target;
            })
            .finally(() => {
                busy.current = false;
                pending.resolve();
                // Always a new state, so what is wanted is pursued again: a load dropped, refused or turned away while
                // this one was on its way leaves no target stranded.
                setWin((was) => ({...was, loading: false}));
            });
    };

    /**
     * Ask for a month that is not beside what is held, with the next: a new set of months in place of the held ones.
     *
     * @param {string} target The month, YYYY-MM.
     * @returns {void}
     */
    const jump = (target: string): void => {
        if (!fetch || busy.current || scoped.current !== win.scope) {
            return;
        }
        busy.current = true;
        const set = generation.current;
        const askedwith = win.scope;
        const pending = new Pending('theme_dimensions/calendar:trailmonth');
        fetch(target, ANSWER_MONTHS)
            .then((got) => {
                setWin((was) => (was.generation === set && scoped.current === askedwith
                    ? freshWin(got, target, was.generation + 1, askedwith)
                    : was));
                return got;
            })
            .catch(() => {
                // The scroll asks for this month no more; a target that needs it tries again, a few times at most.
                failed.current = target;
            })
            .finally(() => {
                busy.current = false;
                pending.resolve();
                setWin((was) => ({...was, loading: false}));
            });
    };

    /**
     * Bring a month into what is held: nothing when it is, the month beside what is held, or a new set of months.
     *
     * @param {string} target The month, YYYY-MM.
     * @returns {void}
     */
    const reach = (target: string): void => {
        const months = win.loaded.months;
        if (months.includes(target)) {
            return;
        }
        if (target === monthAfter(months[months.length - 1], 1) || target === monthAfter(months[0], -1)) {
            extend(target);
        } else {
            jump(target);
        }
    };

    /**
     * Open a step's popover on its bar, the scroll ended.
     *
     * @param {number} trailid The trail.
     * @param {number} cmid The step.
     * @returns {void}
     */
    const openOn = (trailid: number, cmid: number): void => {
        const bar = root.current?.querySelector<HTMLElement>(
            `[data-trail="${trailid}"] [data-region="dim-cal-trailstep"][data-cmid="${cmid}"]`
        );
        if (bar) {
            setPopped({trail: trailid, cmid: cmid, opener: bar});
        }
        found();
    };

    /**
     * The scroll to a course's bar ended for the action that asked for it: open the popover when the bar was reached,
     * else let the search go.
     *
     * @param {number} trailid The trail.
     * @param {number} cmid The step.
     * @param {boolean} reached Whether the bar was reached.
     * @returns {void}
     */
    const arrived = (trailid: number, cmid: number, reached: boolean): void => {
        if (reached) {
            openOn(trailid, cmid);
        } else {
            found();
        }
    };

    /**
     * Go on to what is wanted: scroll to it when its month is held, else ask for the month (the next render goes on);
     * given up after {@see TRIES} months asked for.
     *
     * @returns {void}
     */
    const pursue = (): void => {
        const want = wanted.current;
        const scroller = control.current;
        if (!want || !scroller) {
            return;
        }
        if (want.tries > TRIES) {
            wanted.current = null;
            found();
            return;
        }
        if (want.kind === 'month') {
            const at = days.indexOf(`${want.month}-01`);
            if (at < 0) {
                want.tries++;
                reach(want.month);
                return;
            }
            wanted.current = null;
            scroller.go({kind: 'day', index: at});
            return;
        }
        if (want.kind === 'today') {
            const at = days.indexOf(today);
            if (at < 0) {
                want.tries++;
                reach(today.slice(0, 7));
                return;
            }
            wanted.current = null;
            scroller.go({kind: 'today', index: at, month: days.indexOf(`${today.slice(0, 7)}-01`)});
            return;
        }
        const step = merged.trails.find((trail) => trail.id === want.trail)?.steps.find((one) => one.cmid === want.cmid);
        if (!step) {
            wanted.current = null;
            found();
            return;
        }
        const bar = barOf(step, days, look.zone);
        if (bar) {
            wanted.current = null;
            const ticket = asked.current;
            scroller.go({kind: 'step', index: bar.from})
                .then((reached) => {
                    // A newer action owns the search; a target not reached for this one gives it up.
                    if (ticket === asked.current) {
                        arrived(want.trail, want.cmid, reached);
                    }
                    return reached;
                })
                .catch(found);
            return;
        }
        want.tries++;
        reach(look.zone.day(step.start > 0 ? step.start : step.end).slice(0, 7));
    };

    // Once a month asked for is held, go on to what was wanted.
    useEffect(() => pursue(), [win]);

    /**
     * Go on to a new target: a search for a course on its way is given up first, so its Pending token is let go.
     *
     * @param {object} next The target.
     * @returns {void}
     */
    const aim = (next: Want): void => {
        asked.current++;
        failed.current = '';
        found();
        wanted.current = next;
        pursue();
    };

    /**
     * A course's row: scroll the timeline to its bar, then open the bar's popover; a course with no date opens it on
     * the row.
     *
     * @param {object} trail The trail.
     * @param {object} step The step.
     * @param {object} row The row's button.
     * @returns {void}
     */
    const find = (trail: Trail, step: TrailStep, row: HTMLElement): void => {
        asked.current++;
        failed.current = '';
        found();
        if (!dated(step)) {
            wanted.current = null;
            setPopped({trail: trail.id, cmid: step.cmid, opener: row});
            return;
        }
        finding.current = new Pending('theme_dimensions/calendar:trailfind');
        wanted.current = {kind: 'step', trail: trail.id, cmid: step.cmid, tries: 0};
        pursue();
    };

    if (nav) {
        nav.current = {
            /**
             * A month back or ahead of the first day in view.
             *
             * @param {number} direction -1 back, 1 ahead.
             * @returns {boolean} Whether the timeline took it.
             */
            step: (direction: number): boolean => {
                if (!timeline || days.length === 0) {
                    return false;
                }
                const first = days[viewed.current.first] ?? days[0];
                aim({kind: 'month', month: monthStep(first, direction), tries: 0});
                return true;
            },
            /**
             * Today, from its month's 1st.
             *
             * @returns {boolean} Whether the timeline took it.
             */
            today: (): boolean => {
                if (!timeline) {
                    return false;
                }
                aim({kind: 'today', tries: 0});
                return true;
            },
        };
    }

    /**
     * The scroller's report of the days in view: the toolbar's title, the band's month in view, and a month asked for
     * at an end of what is held.
     *
     * @param {number} first The first day in view.
     * @param {number} last The last day in view.
     * @param {string} near The end reached, if any.
     * @returns {void}
     */
    const viewing = (first: number, last: number, near: Near): void => {
        if (days.length === 0) {
            return;
        }
        const from = days[Math.min(first, days.length - 1)];
        const to = days[Math.min(last, days.length - 1)];
        viewed.current = {first: Math.min(first, days.length - 1), last: Math.min(last, days.length - 1)};
        const range = `${from.slice(0, 7)}:${to.slice(0, 7)}`;
        if (range !== ranged.current) {
            ranged.current = range;
            onRange?.({first: from, last: to});
        }
        setInview((was) => (was === from.slice(0, 7) ? was : from.slice(0, 7)));
        const months = win.loaded.months;
        let target = '';
        if (near === 'end') {
            target = monthAfter(months[months.length - 1], 1);
        } else if (near === 'start') {
            target = monthAfter(months[0], -1);
        }
        // At the cap with the first day held in view (every day held fits the scroller), the next month would only drop
        // days in view: the months held stay.
        const full = near === 'end' && months.length >= capOf(zoom) && first === 0;
        if (target !== '' && target !== failed.current && !full) {
            extend(target);
        }
    };

    /**
     * The scroll settled: the address's month is the first day in view's.
     *
     * @param {number} first The first day in view.
     * @returns {void}
     */
    const settled = (first: number): void => {
        const day = days[Math.min(first, days.length - 1)];
        if (day) {
            onMonth?.(day.slice(0, 7));
        }
    };

    /**
     * Light a step's bar and row together, for a mouse only: a touch has no hover, and a tap must not leave one lit.
     *
     * @param {number} cmid The step.
     * @param {boolean} on Whether the pointer came in or went out.
     * @returns {Function} The handler.
     */
    const lit = (cmid: number, on: boolean) => (event: PointerEvent<HTMLElement>): void => {
        if (event.pointerType !== 'mouse') {
            return;
        }
        root.current?.querySelectorAll<HTMLElement>(`[data-pair="${cmid}"]`).forEach((element) => {
            element.toggleAttribute('data-lit', on);
        });
    };

    /**
     * Fold a trail's steps away, or unfold them: the view's own state, never saved.
     *
     * @param {number} id The trail.
     * @returns {void}
     */
    const fold = (id: number): void => {
        setFolded((was) => (was.includes(id) ? was.filter((one) => one !== id) : [...was, id]));
    };

    const todayat = days.indexOf(today);
    // The opening aims at today only when the month opened on (the one the page or the reader asked for) holds it.
    const openat = today.startsWith(win.loaded.months[0]) ? todayat : -1;
    const grid = {'--dim-cal-trail-days': String(columns)} as CSSProperties;
    const poppedtrail = popped ? merged.trails.find((trail) => trail.id === popped.trail) : undefined;
    const poppedstep = poppedtrail?.steps.find((step) => step.cmid === popped?.cmid);
    const learner = merged.trails.some((trail) => trail.enrolled);
    const starts = win.loaded.months.map((one) => ({month: one, at: days.indexOf(`${one}-01`), length: monthDays(one).length}));
    // By days the header draws the days; off them the weeks (their lines only by months), and no weekend is shaded.
    const bydays = zoom === 'day';
    const cells = bydays ? [] : weekCells(days, firstday);

    /**
     * A short day of an instant, in the viewer's timezone.
     *
     * @param {number} timestamp The instant, in seconds.
     * @returns {string}
     */
    const shortDay = (timestamp: number): string => shortOf(look.zone.day(timestamp), look.language);

    /**
     * The agenda's date of a step: its end, else its start, else nothing.
     *
     * @param {object} step The step.
     * @returns {string}
     */
    const stepDay = (step: TrailStep): string => {
        const at = step.end > 0 ? step.end : step.start;
        return at > 0 ? shortDay(at) : '';
    };

    /**
     * The button that opens a step's popover: a bar of the timeline or a row of the phone's agenda, named by the step.
     *
     * @param {object} trail The trail.
     * @param {object} step The step.
     * @param {string} className Its classes.
     * @param {object} style Its grid placement.
     * @param {object} content What it draws.
     * @returns {object}
     */
    const opener = (trail: Trail, step: TrailStep, className: string, style: CSSProperties | undefined, content: ReactNode) => (
        <button
            type="button"
            className={className}
            style={style}
            aria-label={stepLabel(step, look, now)}
            aria-expanded={popped?.cmid === step.cmid}
            data-region="dim-cal-trailstep"
            data-cmid={step.cmid}
            data-pair={step.cmid}
            data-state={step.state || undefined}
            onClick={(event) => setPopped({trail: trail.id, cmid: step.cmid, opener: event.currentTarget})}
            onPointerEnter={lit(step.cmid, true)}
            onPointerLeave={lit(step.cmid, false)}
        >{content}</button>
    );

    /**
     * One step's row of the desktop grid: its button (number and name) in the trail column, its bar and its diamond.
     *
     * @param {object} trail The trail.
     * @param {object} step The step.
     * @returns {object}
     */
    const stepRow = (trail: Trail, step: TrailStep) => {
        const bar = barOf(step, days, look.zone);
        const tone = step.state ? `--${step.state}` : '';
        let drawn = null;
        let diamond = null;
        if (bar) {
            const continued = bar.after && !bar.open;
            const classes = 'dim-cal__trailbar' + (tone ? ` dim-cal__trailbar${tone}` : '')
                + (bar.before ? ' dim-cal__trailbar--before' : '')
                + (continued ? ' dim-cal__trailbar--after' : '')
                + (bar.open ? ' dim-cal__trailbar--open' : '')
                + (bar.milestone ? ' dim-cal__trailbar--milestone' : '')
                + (step.next ? ' dim-cal__trailbar--next' : '');
            // A bar too narrow for its name draws none; its accessible name is the step's all the same.
            drawn = opener(trail, step, classes, {gridColumn: `${bar.from + 2} / ${bar.to + 3}`, gridRow: '1'},
                namedBar(bar, zoom) ? step.name : null);
            if (bar.milestone) {
                diamond = (
                    <span
                        className={'dim-cal__traildiamond' + (tone ? ` dim-cal__traildiamond${tone}` : '')}
                        style={{gridColumn: String(bar.to + 2), gridRow: '1'}}
                        aria-hidden="true"
                    ></span>
                );
            }
        }
        const dates = datesLine(step, look);
        return (
            <div className="dim-cal__trailrow dim-cal__trailrow--step" key={step.cmid} data-cmid={step.cmid}>
                <span className="dim-cal__traillabel dim-cal__trailsteplabel">
                    <button
                        type="button"
                        className="dim-cal__trailfind"
                        data-region="dim-cal-trailrow"
                        data-cmid={step.cmid}
                        data-pair={step.cmid}
                        aria-label={dated(step) ? fill(look.strings.calendar_trailshow, `${step.name}, ${dates}`) : undefined}
                        aria-haspopup="dialog"
                        aria-expanded={popped?.cmid === step.cmid}
                        onClick={(event) => find(trail, step, event.currentTarget)}
                        onPointerEnter={lit(step.cmid, true)}
                        onPointerLeave={lit(step.cmid, false)}
                    >
                        <span className={'dim-cal__trailnum' + (tone ? ` dim-cal__trailnum${tone}` : '')} aria-hidden="true">
                            {step.position}
                        </span>
                        <span className="dim-cal__trailnames">
                            <span className="dim-cal__trailname">{step.name}</span>
                            <small
                                className={`dim-cal__traildates${dated(step) ? '' : ' dim-cal__traildates--none'}`}
                                data-region="dim-cal-traildates"
                            >{dates}</small>
                        </span>
                        {dated(step) ? (
                            <i className="fa fa-crosshairs dim-cal__traillocate" aria-hidden="true"></i>
                        ) : null}
                    </button>
                </span>
                {drawn}
                {diamond}
            </div>
        );
    };

    /**
     * One trail of the desktop grid: its row (the fold, the name, the line under it, its span), its steps and the
     * connectors between them.
     *
     * @param {object} trail The trail.
     * @returns {object}
     */
    const trailRows = (trail: Trail) => {
        const open = !folded.includes(trail.id);
        const span = trailBarOf(trail, days, look.zone);
        const steps = `dim-cal-trailsteps-${trail.id}`;
        const links = linksOf(trail.steps, days, look.zone, open, zoom);
        return (
            <div className="dim-cal__trail" key={trail.id} role="group" aria-label={trail.name} data-trail={trail.id}>
                <div
                    className={'dim-cal__trailrow dim-cal__trailrow--trail'
                        + (chosen === trail.id ? ' dim-cal__trailrow--chosen' : '')}
                >
                    <span className="dim-cal__traillabel dim-cal__trailcaption">
                        <button
                            type="button"
                            className={`btn btn-icon icons-collapse-expand dim-cal__trailfold${open ? '' : ' collapsed'}`}
                            data-action="dim-cal-trailfold"
                            aria-expanded={open}
                            aria-controls={steps}
                            aria-label={fill(look.strings.calendar_trailtoggle, trail.name)}
                            onClick={() => fold(trail.id)}
                        >
                            <span
                                className="expanded-icon icon-no-margin"
                                dangerouslySetInnerHTML={{__html: icons.expanded}}
                            ></span>
                            <span className="collapsed-icon icon-no-margin">
                                <span className="dir-rtl-hide" dangerouslySetInnerHTML={{__html: icons.collapsed}}></span>
                                <span className="dir-ltr-hide" dangerouslySetInnerHTML={{__html: icons.collapsedrtl}}></span>
                            </span>
                        </button>
                        <span className="dim-cal__trailnames">
                            <a className="dim-cal__trailname dim-cal__trailname--trail" href={safeHref(trail.url, look.home)}>
                                {trail.name}
                            </a>
                            <small className="dim-cal__trailmeta" data-region="dim-cal-trailmeta">{metaOf(trail, look)}</small>
                        </span>
                    </span>
                    {span ? (
                        <span
                            className={'dim-cal__trailspan' + (span.before ? ' dim-cal__trailspan--before' : '')
                                + (span.after ? ' dim-cal__trailspan--after' : '')}
                            style={{gridColumn: `${span.from + 2} / ${span.to + 3}`, gridRow: '1'}}
                            aria-hidden="true"
                        ></span>
                    ) : null}
                    {span && !span.after ? (
                        <span
                            className="dim-cal__traildiamond dim-cal__traildiamond--trail"
                            style={{gridColumn: String(span.to + 2), gridRow: '1'}}
                            aria-hidden="true"
                        ></span>
                    ) : null}
                </div>
                <div className="dim-cal__trailsteprows" id={steps} hidden={!open}>
                    {trail.steps.map((step) => stepRow(trail, step))}
                    {links.length > 0 ? (
                        <span className="dim-cal__traillinks" data-region="dim-cal-traillinks" aria-hidden="true">
                            {links.map((piece, index) => (
                                <span
                                    key={`${index}-${piece.kind}`}
                                    className={`dim-cal__traillink dim-cal__traillink--${piece.kind}`}
                                    style={{
                                        '--dim-cal-link-start': `${piece.left}rem`,
                                        top: `${piece.top}rem`,
                                        width: `${piece.width}rem`,
                                        height: `${piece.height}rem`,
                                    } as CSSProperties}
                                ></span>
                            ))}
                        </span>
                    ) : null}
                </div>
            </div>
        );
    };

    /**
     * The band above the days: one segment per month held, its name a button that scrolls to its 1st and stays at the
     * start of the days while the segment is in view; a month on its way is a skeleton at the end.
     *
     * @returns {object}
     */
    const band = () => (
        <div
            className="dim-cal__trailrow dim-cal__trailrow--band"
            role="group"
            aria-label={look.strings.calendar_trailmonths}
            data-region="dim-cal-trailmonths"
        >
            <span className="dim-cal__traillabel" aria-hidden="true"></span>
            {starts.map((entry) => (
                <span
                    key={entry.month}
                    className={`dim-cal__trailmonth${entry.month === inview ? ' dim-cal__trailmonth--inview' : ''}`}
                    style={{gridColumn: `${entry.at + 2} / ${entry.at + entry.length + 2}`, gridRow: '1'}}
                >
                    <button
                        type="button"
                        className="dim-cal__trailmonthname"
                        data-action="dim-cal-trailmonth"
                        data-month={entry.month}
                        aria-current={entry.month === inview ? 'true' : undefined}
                        onClick={() => aim({kind: 'month', month: entry.month, tries: 0})}
                    >
                        {say(`${entry.month}-01`, zoom === 'month' ? MONTH_ONLY : MONTH_YEAR, look.language)}
                    </button>
                </span>
            ))}
            {win.loading ? (
                <span
                    className="dim-cal__trailmonth dim-cal__trailmonth--loading"
                    style={{gridColumn: `${days.length + 2} / ${columns + 2}`, gridRow: '1'}}
                    data-region="dim-cal-trailloading"
                    aria-hidden="true"
                >
                    <span className="dim-cal__trailskeleton"></span>
                </span>
            ) : null}
        </div>
    );

    /**
     * The desktop grid, in its scroller, and its legend.
     *
     * @returns {object}
     */
    const desk = () => (
        <>
            <TrailScroll
                days={columns}
                todayat={todayat}
                openat={openat}
                opening={String(win.generation)}
                shift={win.shift}
                zoom={zoom}
                control={control}
                onView={viewing}
                onSettle={settled}
                strings={look.strings}
            >
                <div className="dim-cal__trailgrid" data-region="dim-cal-trailgrid" style={grid}>
                    {band()}
                    <div className="dim-cal__trailrow dim-cal__trailrow--head" aria-hidden="true">
                        <span className="dim-cal__traillabel dim-cal__trailheading">{look.strings.calendar_trailheading}</span>
                        {bydays ? days.map((iso, index) => (
                            <span
                                key={iso}
                                className={`dim-cal__trailday${isWeekend(iso) ? ' dim-cal__trailday--weekend' : ''}`
                                    + `${index === todayat ? ' dim-cal__trailday--today' : ''}`
                                    + `${iso.endsWith('-01') && index > 0 ? ' dim-cal__trailday--first' : ''}`}
                                data-day={iso}
                            >
                                <span className="dim-cal__trailweekday">{say(iso, WEEKDAY, look.language)}</span>
                                <span className="dim-cal__traildaynum">{Number(iso.slice(8, 10))}</span>
                            </span>
                        )) : cells.map((cell) => (
                            <span
                                key={cell.first}
                                className={'dim-cal__trailweek'
                                    + (zoom === 'week' && today >= cell.first && today <= cell.last
                                        ? ' dim-cal__trailweek--today' : '')
                                    + (cell.first.endsWith('-01') && cell.at > 0 ? ' dim-cal__trailweek--first' : '')}
                                style={{gridColumn: `${cell.at + 2} / ${cell.at + cell.length + 2}`, gridRow: '1'}}
                                data-region="dim-cal-trailweek"
                                data-week={cell.first}
                            >
                                {weekLabelled(cell, zoom) ? (
                                    <>
                                        <span className="dim-cal__trailweekmonth">{weekLabel(cell, look.language).kicker}</span>
                                        <span className="dim-cal__trailweekdays">{weekLabel(cell, look.language).days}</span>
                                    </>
                                ) : null}
                            </span>
                        ))}
                    </div>
                    <div className="dim-cal__trailground" aria-hidden="true">
                        {bydays ? days.map((iso, index) => (isWeekend(iso) ? (
                            <span
                                key={iso}
                                className="dim-cal__trailweekend"
                                style={{'--dim-cal-trail-at': String(index)} as CSSProperties}
                            ></span>
                        ) : null)) : null}
                        {cells.filter((cell) => cell.at > 0 && !cell.first.endsWith('-01')).map((cell) => (
                            <span
                                key={cell.first}
                                className="dim-cal__trailweekline"
                                style={{'--dim-cal-trail-at': String(cell.at)} as CSSProperties}
                            ></span>
                        ))}
                        {starts.filter((entry) => entry.at > 0).map((entry) => (
                            <span
                                key={entry.month}
                                className="dim-cal__trailmonthedge"
                                style={{'--dim-cal-trail-at': String(entry.at)} as CSSProperties}
                            ></span>
                        ))}
                        {todayat >= 0 ? (
                            <span
                                className="dim-cal__trailnow"
                                style={{'--dim-cal-trail-at': String(todayat)} as CSSProperties}
                            ></span>
                        ) : null}
                        {todayat >= 0 ? (
                            <span
                                className="dim-cal__trailnowtag"
                                data-region="dim-cal-trailnowtag"
                                style={{'--dim-cal-trail-at': String(todayat)} as CSSProperties}
                            >{look.strings.calendar_today}</span>
                        ) : null}
                    </div>
                    {merged.trails.map((trail) => trailRows(trail))}
                </div>
            </TrailScroll>
            <ul className="dim-cal__traillegend" data-region="dim-cal-traillegend">
                {learner ? LEGEND.map((state) => (
                    <li key={state}>
                        <span className={`dim-cal__trailswatch dim-cal__trailswatch--${state}`} aria-hidden="true"></span>
                        {stateLabel(look.strings, state)}
                    </li>
                )) : null}
                <li>
                    <span className="dim-cal__trailswatch" aria-hidden="true"></span>
                    {look.strings.calendar_trailnotenrolled}
                </li>
                <li>
                    <span className="dim-cal__traildiamond dim-cal__traildiamond--legend" aria-hidden="true"></span>
                    {look.strings.calendar_trailexpected}
                </li>
                <li>
                    <span className="dim-cal__traillegendnow" aria-hidden="true"></span>
                    {look.strings.calendar_today}
                </li>
            </ul>
        </>
    );

    /**
     * The phone's "Today" line.
     *
     * @returns {object}
     */
    const todayLine = () => (
        <li className="dim-cal__trailtoday" data-region="dim-cal-trailtoday">
            <span className="dim-cal__trailtodayword">{look.strings.calendar_today}</span>
            <span className="dim-cal__trailtodayline" aria-hidden="true"></span>
            <span className="dim-cal__trailtodaydate">{shortOf(today, look.language)}</span>
        </li>
    );

    /**
     * The phone's agenda.
     *
     * @returns {object}
     */
    const agenda = () => (
        <div className="dim-cal__trailagenda" data-region="dim-cal-trailagenda">
            {merged.trails.map((trail) => {
                const at = todayIndex(trail.steps, now);
                return (
                    <section
                        className="dim-cal__trail dim-cal__trailsheet"
                        key={trail.id}
                        aria-label={trail.name}
                        data-trail={trail.id}
                    >
                        <div className="dim-cal__trailsheethead">
                            <h3 className="dim-cal__trailtitle">
                                <a href={safeHref(trail.url, look.home)}>{trail.name}</a>
                            </h3>
                            <small className="dim-cal__trailprogress">
                                {trail.enrolled ? progressOf(trail, look) : coursesOf(look.strings, trail.steps.length)}
                            </small>
                        </div>
                        {trail.enrolled ? (
                            <span className="dim-cal__trailsegments" aria-hidden="true">
                                {trail.steps.map((step) => (
                                    <span
                                        key={step.cmid}
                                        className={'dim-cal__trailsegment'
                                            + (step.state ? ` dim-cal__trailsegment--${step.state}` : '')}
                                    ></span>
                                ))}
                            </span>
                        ) : null}
                        <ol className="dim-cal__trailsteps">
                            {trail.steps.map((step, index) => (
                                <Fragment key={step.cmid}>
                                    {index === at ? todayLine() : null}
                                    <li className={`dim-cal__trailstep${step.state ? ` dim-cal__trailstep--${step.state}` : ''}`}>
                                        {opener(trail, step, 'dim-cal__trailstepname', undefined, (
                                            <>
                                                <span className="dim-cal__trailstepdate">{stepDay(step)}</span>
                                                <span className="dim-cal__trailstepbar" aria-hidden="true"></span>
                                                <span className="dim-cal__trailsteptext">
                                                    <span className="dim-cal__trailstepline">
                                                        {`${step.position}. ${step.name}`}
                                                    </span>
                                                    <span className="dim-cal__trailwhen">{stepWhen(step, look)}</span>
                                                    {step.state ? (
                                                        <span className={`dim-cal__trailstate dim-cal__trailstate--${step.state}`}>
                                                            {stateLine(step, look, now)}
                                                        </span>
                                                    ) : null}
                                                </span>
                                            </>
                                        ))}
                                    </li>
                                </Fragment>
                            ))}
                            {at === trail.steps.length ? todayLine() : null}
                        </ol>
                    </section>
                );
            })}
        </div>
    );

    return (
        <section
            ref={root}
            className={'dim-cal__trails' + (phone ? ' dim-cal__trails--phone' : '')
                + (!phone && zoom !== 'day' ? ` dim-cal__trails--${zoom}` : '')}
            role="region"
            aria-labelledby={captionid}
            data-region="dim-cal-trails"
        >
            {merged.truncated ? <p className="dim-cal__status">{look.strings.calendar_trailstruncated}</p> : null}
            {merged.trails.length === 0 ? (
                <p className="dim-cal__empty" data-region="dim-cal-trailsempty">{look.strings.calendar_trailsempty}</p>
            ) : null}
            {timeline ? desk() : null}
            {merged.trails.length > 0 && phone ? agenda() : null}
            {popped && poppedtrail && poppedstep ? (
                <TrailPopover
                    look={look}
                    trail={poppedtrail}
                    step={poppedstep}
                    opener={popped.opener}
                    sheet={phone}
                    now={now}
                    onClose={close}
                />
            ) : null}
        </section>
    );
};

export default TrailView;
