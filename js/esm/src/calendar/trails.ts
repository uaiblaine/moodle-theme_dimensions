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
 * The Trilhas view's model: the answer's shape, its address, the months it holds and where each step's bar and each
 * connector goes on them. Pure: no layout is read, the bars are grid columns and the connectors rem.
 *
 * - The view's trail and "Só as minhas" are navigation, like the view: in the address (`trail=`, `mine=1`), never in
 *   the remembered state (local\calendar\page::trail_from_request(), mine_from_request()).
 * - A step's days are read in the viewer's timezone (days.ts zone()), as every item of the calendar.
 * - The view holds contiguous whole months ({@see Loaded}), at most {@see LOADED_CAP}: the first paint's answer is the
 *   month and the next (local\calendar\trails::OPENING), each further load is one call of one month, or by months of up to
 *   {@see BATCH_MONTHS} (local\calendar\trails::MONTHS, {@see batchOf()}), appended or prepended, and the
 *   answers are merged by trail id ({@see mergedOf()}), a trail kept while its period meets what is held.
 * - A bar runs from its step's start to its end, clipped at the edges of what is held: a step that began earlier is
 *   marked `before`, one that ends later `after`. A step with a start and no end is `open`: it is drawn as its first
 *   {@see OPEN_DAYS} days, followed by a decorative chip ({@see chipLeft()}) saying it has no end;
 *   an expected completion date is an end, so such a step is not open. A step with an end and no start is a milestone on
 *   its end; an expected date drawn as the bar's end is a milestone too.
 * - On a desktop the days have a fixed width and only the timeline scrolls, the trail column held still beside it. The
 *   scroller's geometry is read from the scroller alone ({@see trackOf()}: its content width is the trail column and the
 *   days, TRACK, in rem, which scss/calendar.scss declares with the same numbers), so the position of today, a month, a
 *   course, a page of days and the map's thumb are pure arithmetic over three numbers the scroll handlers read.
 * - Consecutive steps are joined in the trail's order ({@see linksOf()}), never by what their enrolment needs: the
 *   path is chosen from the dates alone, and a step with no date breaks the chain.
 * - The desktop timeline has a zoom ({@see Zoom}): a day's width is TRACK's for it ({@see dayWidth()}), and every
 *   position (the bars' columns, the connectors, today, the scroll arithmetic) follows it from there. The zoom is
 *   navigation, like the trail (`zoom=week|month`, absent for days: local\calendar\page::zoom_from_request()), and a
 *   switch keeps the day at the start of the view ({@see zoomedLeft()}).
 *
 * @module     theme_dimensions/calendar/trails
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {monthLength, say} from '../daterange/calendar';
import {monthAfter} from './days';
import type {Zone} from './days';
import type {Category} from './model';

/** The address parameter of the one trail kept (local\calendar\page::PARAM_TRAIL). */
export const TRAIL_PARAM = 'trail';

/** The address parameter of "Só as minhas", exactly 1 when on (local\calendar\page::PARAM_MINE). */
export const MINE_PARAM = 'mine';

/** The address parameter of the timeline's zoom, week or month, absent for days (local\calendar\page::PARAM_ZOOM). */
export const ZOOM_PARAM = 'zoom';

/** The desktop timeline's zoom: a day's width is TRACK's of the same name. */
export type Zoom = 'day' | 'week' | 'month';

/** The zooms, in the order the control offers them. */
export const ZOOMS: Zoom[] = ['day', 'week', 'month'];

/** A step's state for a learner of the trail (local\trail\progress), or '' for anyone else. */
export type StepState = 'done' | 'current' | 'late' | 'locked' | 'upcoming' | '';

/** One step of a trail as the answer carries it. */
export type TrailStep = {
    cmid: number,
    position: number,
    name: string,
    courseid: number,
    coursename: string,
    categoryid: number,
    start: number,
    end: number,
    expected: boolean,
    state: StepState,
    reason: string,
    completed: number,
    url: string,
    next: boolean,
};

/** One trail of the answer. */
export type Trail = {
    id: number,
    name: string,
    url: string,
    categoryid: number,
    enrolled: boolean,
    done: number,
    first: number,
    last: number,
    steps: TrailStep[],
};

/** The trails service's answer (local\calendar\trails::answer()). */
export type TrailAnswer = {
    from: string,
    to: string,
    truncated: boolean,
    options: Array<{id: number, name: string}>,
    trails: Trail[],
    categories: Category[],
};

/** Core's course-section chevrons, rendered by the server (calendar_page::props()'s `trails.icons`). */
export type FoldIcons = {expanded: string, collapsed: string, collapsedrtl: string};

/** What the page hands the view (calendar_page::props()'s `trails`). */
export type TrailProps = {
    offered: boolean,
    answer: TrailAnswer | null,
    trail: number,
    mine: boolean,
    zoom?: string,
    icons?: FoldIcons,
};

/** The view's two filters: one trail (0 for every trail) and "Só as minhas". */
export type TrailFilter = {trail: number, mine: boolean};

/** Where a step's bar goes on the days held: day indexes, 0 for the first day held. */
export type TrailBar = {
    from: number,
    to: number,
    before: boolean,
    after: boolean,
    open: boolean,
    milestone: boolean,
};

/** The days a course with a start and no end is drawn for, its start day included. */
export const OPEN_DAYS = 30;

/**
 * An ISO day some days later (or earlier, negative).
 *
 * @param {string} day The day, YYYY-MM-DD.
 * @param {number} count The days to add.
 * @returns {string} The day, YYYY-MM-DD.
 */
const daysAfter = (day: string, count: number): string =>
    new Date(Date.parse(`${day}T00:00:00Z`) + count * 86400000).toISOString().slice(0, 10);

/**
 * The last day of a span with a start and no end: its {@see OPEN_DAYS}th day, or the day given when it is later.
 *
 * @param {string} start The start day.
 * @param {string|null} until A later last day, null for none.
 * @returns {string} The day, YYYY-MM-DD.
 */
const openEnd = (start: string, until: string | null): string =>
    until !== null && until > start ? until : daysAfter(start, OPEN_DAYS - 1);

/**
 * Where an open trail's period ends, as its row's line is drawn: the latest of its steps' ends and of each open
 * step's {@see OPEN_DAYS} (the trail's own last end is 0 while any step is open, and says nothing).
 *
 * @param {object} trail The trail.
 * @param {object} zone The viewer's timezone reader.
 * @returns {string|null} The day, null when no step has a date.
 */
const openTrailEnd = (trail: Trail, zone: Zone): string | null => {
    let latest: string | null = null;
    trail.steps.forEach((step) => {
        let day: string | null = null;
        if (step.end > 0) {
            day = zone.day(step.end);
        } else if (step.start > 0) {
            day = daysAfter(zone.day(step.start), OPEN_DAYS - 1);
        }
        if (day !== null && (latest === null || day > latest)) {
            latest = day;
        }
    });
    return latest;
};

/**
 * A month's own days, its 1st to its last (the Trilhas view's columns).
 *
 * @param {string} month The month, YYYY-MM.
 * @returns {Array} The days, YYYY-MM-DD.
 */
export const monthDays = (month: string): string[] => {
    const length = monthLength(Number(month.slice(0, 4)), Number(month.slice(5, 7)));
    return Array.from({length: length}, (unused, index) => `${month}-${String(index + 1).padStart(2, '0')}`);
};

/**
 * Where a step's bar goes on the days held, or null when the step does not touch them.
 *
 * @param {object} step The step.
 * @param {Array} days The days held, in order.
 * @param {object} zone The viewer's timezone reader.
 * @returns {object|null} See TrailBar.
 */
export const barOf = (step: TrailStep, days: string[], zone: Zone): TrailBar | null =>
    spanOf(step.start, step.end, step.expected, days, zone);

/**
 * Where a trail's own span goes on the days held (the line on its row), from its period's first start to its last
 * end: {@see barOf()}'s rule over the trail's period, which is never a milestone.
 *
 * @param {object} trail The trail.
 * @param {Array} days The days held, in order.
 * @param {object} zone The viewer's timezone reader.
 * @returns {object|null} See TrailBar.
 */
export const trailBarOf = (trail: Trail, days: string[], zone: Zone): TrailBar | null =>
    spanOf(trail.first, trail.last, false, days, zone, trail.first > 0 && trail.last <= 0 ? openTrailEnd(trail, zone) : null);

/**
 * Where a span between two instants goes on the days held, or null when it does not touch them.
 *
 * @param {number} begins The start, in seconds, 0 for none.
 * @param {number} ends The end, in seconds, 0 for none.
 * @param {boolean} expected Whether the end is an expected completion date (a milestone when inside the days held).
 * @param {Array} days The days held, in order.
 * @param {object} zone The viewer's timezone reader.
 * @param {string|null} until The last day of a span with no end, null for its start's {@see OPEN_DAYS}th day.
 * @returns {object|null} See TrailBar.
 */
const spanOf = (
    begins: number,
    ends: number,
    expected: boolean,
    days: string[],
    zone: Zone,
    until: string | null = null,
): TrailBar | null => {
    const first = days[0];
    const last = days[days.length - 1];
    const start = begins > 0 ? zone.day(begins) : null;
    let end = ends > 0 ? zone.day(ends) : null;
    if (start === null && end === null) {
        return null;
    }
    // An end before the start (a course's dates out of order) is the start day alone, before the edges are read: the
    // bar and the connectors (reachOf()) read the same day.
    if (start !== null && end !== null && end < start) {
        end = start;
    }
    // A start with no end is open: its first OPEN_DAYS days, the bar and the connectors (reachOf()) reading the same ones.
    const open = start !== null && end === null;
    if (open) {
        end = openEnd(String(start), until);
    }
    if ((start !== null && start > last) || (end !== null && end < first)) {
        return null;
    }
    if (start === null) {
        // An end alone: the milestone on its day, none when the day is not held.
        const at = days.indexOf(String(end));
        if (at < 0) {
            return null;
        }
        return {from: at, to: at, before: false, after: false, open: false, milestone: true};
    }
    return placedOf(start, end, open, expected, days);
};

/**
 * Where a span with a start goes on the days held, which it touches: clipped at their edges.
 *
 * @param {string} start The start day.
 * @param {string|null} end The end day, null for none.
 * @param {boolean} open Whether the end is the start's {@see OPEN_DAYS}th day, not a stated one.
 * @param {boolean} expected Whether the end is an expected completion date.
 * @param {Array} days The days held, in order.
 * @returns {object} See TrailBar.
 */
const placedOf = (start: string, end: string | null, open: boolean, expected: boolean, days: string[]): TrailBar => {
    const first = days[0];
    const last = days[days.length - 1];
    const before = start < first;
    const after = end === null || end > last;
    const from = before ? 0 : days.indexOf(start);
    return {
        from: from,
        to: Math.max(from, after ? days.length - 1 : days.indexOf(String(end))),
        before: before,
        after: after,
        open: open,
        milestone: !open && expected && end !== null && end <= last,
    };
};

/**
 * The index of the first step still ahead, where the phone's agenda draws its "Today" line: the first step not done
 * whose end has not passed (or that has none); the steps' count when every step is behind.
 *
 * @param {Array} steps The trail's steps, in order.
 * @param {number} now Now, in seconds.
 * @returns {number}
 */
export const todayIndex = (steps: TrailStep[], now: number): number => {
    const index = steps.findIndex((step) => step.state !== 'done' && (step.end === 0 || step.end >= now));
    return index < 0 ? steps.length : index;
};

/**
 * The service's arguments for a month, the filters, the category scope and how many months from it.
 *
 * @param {string} month The month, YYYY-MM.
 * @param {object} filter The view's filters.
 * @param {number} categoryid The category narrowing, 0 for none.
 * @param {boolean} recursive Whether the category's whole subtree counts.
 * @param {number} months 1 to {@see BATCH_MONTHS}: the month alone, or that many from it.
 * @returns {object}
 */
export const trailArgs = (month: string, filter: TrailFilter, categoryid: number, recursive: boolean,
    months: number = 1): Record<string, unknown> => ({
    month: month,
    trail: filter.trail,
    mine: filter.mine,
    categoryid: categoryid,
    recursive: recursive,
    months: months,
});

/**
 * An address with the view's filters and its zoom written, or taken out (any other view). Days, the zoom a bare address
 * reads as, is never written.
 *
 * @param {string} href The address.
 * @param {object|null} filter The filters, or null to take them and the zoom out.
 * @param {string} zoom The timeline's zoom.
 * @returns {string}
 */
export const withTrail = (href: string, filter: TrailFilter | null, zoom: Zoom = 'day'): string => {
    const url = new URL(href, window.location.href);
    url.searchParams.delete(TRAIL_PARAM);
    url.searchParams.delete(MINE_PARAM);
    url.searchParams.delete(ZOOM_PARAM);
    if (filter && filter.trail > 0) {
        url.searchParams.set(TRAIL_PARAM, String(filter.trail));
    }
    if (filter && filter.mine) {
        url.searchParams.set(MINE_PARAM, '1');
    }
    if (filter && zoom !== 'day') {
        url.searchParams.set(ZOOM_PARAM, zoom);
    }
    return url.toString();
};

/**
 * A zoom as the server reads it: week or month exactly, days for anything else.
 *
 * @param {*} value The value.
 * @returns {string}
 */
export const zoomOf = (value: unknown): Zoom => (value === 'week' || value === 'month' ? value : 'day');

/**
 * The zoom an address names.
 *
 * @param {string} href The address.
 * @returns {string}
 */
export const readZoom = (href: string): Zoom => zoomOf(new URL(href, window.location.href).searchParams.get(ZOOM_PARAM));

/**
 * The filters an address names, as the server reads them: a positive integer trail, mine exactly 1.
 *
 * @param {string} href The address.
 * @returns {object}
 */
export const readTrail = (href: string): TrailFilter => {
    const params = new URL(href, window.location.href).searchParams;
    const trail = String(params.get(TRAIL_PARAM) || '');
    return {trail: /^\d{1,10}$/.test(trail) ? Number(trail) : 0, mine: params.get(MINE_PARAM) === '1'};
};

/**
 * An empty answer for a month: no trail, nothing offered.
 *
 * @param {string} month The month, YYYY-MM.
 * @returns {object}
 */
export const emptyTrails = (month: string): TrailAnswer => {
    const days = monthDays(month);
    return {from: days[0], to: days[days.length - 1], truncated: false, options: [], trails: [], categories: []};
};

/**
 * The filters the page's props name: none for a page without the view.
 *
 * @param {object|undefined} props The page's `trails` props.
 * @returns {object}
 */
export const filterOf = (props: TrailProps | undefined): TrailFilter => ({trail: props?.trail || 0, mine: props?.mine === true});

/**
 * Whether any of the Trilhas view's filters is applied: a trail, "Só as minhas" or a category ("Clear filters" shows).
 *
 * @param {object} filter The view's filters.
 * @param {number} categoryid The category narrowing, 0 for none.
 * @returns {boolean}
 */
export const trailFiltered = (filter: TrailFilter, categoryid: number): boolean =>
    filter.trail > 0 || filter.mine || categoryid !== 0;


/** The months of the first paint and of a far target's new set (local\calendar\trails::OPENING): the month and the next. */
export const OPENING_MONTHS = 2;

/** The most months one request of the service covers, the batch by months (local\calendar\trails::MONTHS). */
export const BATCH_MONTHS = 3;

/** The most months the view holds by days and by weeks; one more drops the month on the far side. */
export const LOADED_CAP = 6;

/** The most months the view holds by months, where a month is a few rem wide and six fill about two thirds of a wide screen. */
export const MONTH_CAP = 12;

/**
 * The most months the view holds at a zoom. A zoom switched to a smaller cap with more months held keeps them until the
 * next month loads, which drops the far side down to the cap with the shift told ({@see extendLoaded()}).
 *
 * @param {string} zoom The timeline's zoom.
 * @returns {number}
 */
export const capOf = (zoom: Zoom): number => (zoom === 'month' ? MONTH_CAP : LOADED_CAP);

/**
 * How many months one load asks for beside what is held: by months, where a month is a few rem wide, a batch of
 * BATCH_MONTHS so a wide screen is filled in few requests, never more than the cap leaves room for; one month at any other
 * zoom, and at the cap (the scroll's month drops the far side).
 *
 * @param {string} zoom The timeline's zoom.
 * @param {number} held The months held.
 * @returns {number} 1 to BATCH_MONTHS.
 */
export const batchOf = (zoom: Zoom, held: number): number =>
    (zoom === 'month' ? Math.max(1, Math.min(BATCH_MONTHS, capOf(zoom) - held)) : 1);

/** How near the end of what is held, in days, a scroll asks for the next month. */
export const LOAD_AHEAD = 7;

/** How many days the skeleton of a month on its way spans at the end. */
export const SKELETON_DAYS = 3;

/** How far inside the days a course's bar starts once its row has scrolled the timeline to it, in days. */
export const STEP_INSET = 2;

/** The months the view holds, in order and contiguous, each beside the answer that covered it (one may cover two). */
export type Loaded = {months: string[], answers: TrailAnswer[]};

/**
 * The months from one day's to another's, both included.
 *
 * @param {string} from The first day, YYYY-MM-DD.
 * @param {string} to The last day, YYYY-MM-DD.
 * @returns {Array} The months, YYYY-MM.
 */
export const monthsOf = (from: string, to: string): string[] => {
    const months = [from.slice(0, 7)];
    const last = to.slice(0, 7);
    while (months[months.length - 1] < last && months.length < 24) {
        months.push(monthAfter(months[months.length - 1], 1));
    }
    return months;
};

/**
 * The days of whole months, in order: the timeline's columns.
 *
 * @param {Array} months The months, YYYY-MM, in order.
 * @returns {Array} The days, YYYY-MM-DD.
 */
export const windowDays = (months: string[]): string[] => months.flatMap((month) => monthDays(month));

/**
 * What one answer holds: its months, each beside it.
 *
 * @param {object} answer The answer.
 * @returns {object} See Loaded.
 */
export const loadedOf = (answer: TrailAnswer): Loaded => {
    const months = monthsOf(answer.from, answer.to);
    return {months: months, answers: months.map(() => answer)};
};

/**
 * Whether a trail's period meets the days from one to another: local\calendar\trails::meets() on the client, a period
 * with no last end being open.
 *
 * @param {object} trail The trail.
 * @param {string} from The first day held.
 * @param {string} to The last day held.
 * @param {object} zone The viewer's timezone reader.
 * @returns {boolean}
 */
export const meets = (trail: Trail, from: string, to: string, zone: Zone): boolean =>
    (trail.first <= 0 || zone.day(trail.first) <= to) && (trail.last <= 0 || zone.day(trail.last) >= from);

/**
 * Two names in the server's order (core_text::strtolower(), then the bytes).
 *
 * @param {string} a One name.
 * @param {string} b The other.
 * @returns {number}
 */
const byName = (a: string, b: string): number => {
    const one = a.toLowerCase();
    const other = b.toLowerCase();
    return one < other ? -1 : Number(one > other);
};

/**
 * The answers the view holds as one: every trail once, by id (a trail's steps are the same in every answer), kept while
 * its period meets the days held and sorted as local\calendar\trails::answer() sorts (first start, name, id); the
 * options and the categories once each; truncated when any answer was.
 *
 * @param {object} loaded The months held.
 * @param {object} zone The viewer's timezone reader.
 * @returns {object} The answer over the days held.
 */
export const mergedOf = (loaded: Loaded, zone: Zone): TrailAnswer => {
    const days = windowDays(loaded.months);
    const from = days[0];
    const to = days[days.length - 1];
    const trails = new Map<number, Trail>();
    const options = new Map<number, {id: number, name: string}>();
    const categories = new Map<number, Category>();
    let truncated = false;
    Array.from(new Set(loaded.answers)).forEach((answer) => {
        truncated = truncated || answer.truncated;
        answer.trails.forEach((trail) => trails.set(trail.id, trails.get(trail.id) ?? trail));
        answer.options.forEach((option) => options.set(option.id, options.get(option.id) ?? option));
        answer.categories.forEach((category) => categories.set(category.id, categories.get(category.id) ?? category));
    });
    return {
        from: from,
        to: to,
        truncated: truncated,
        options: Array.from(options.values()).sort((a, b) => byName(a.name, b.name) || a.id - b.id),
        trails: Array.from(trails.values())
            .filter((trail) => meets(trail, from, to, zone))
            .sort((a, b) => a.first - b.first || byName(a.name, b.name) || a.id - b.id),
        categories: Array.from(categories.values()),
    };
};

/**
 * The months held with an answer's months beside them (one month, or up to {@see BATCH_MONTHS}): appended after the
 * last, or prepended before the first, and the months on the far side dropped past the cap. The shift is how many days
 * the first day held moved back (the prepended months' days) or forward (less the dropped months'), which the scroller
 * adds to its position so the view stays still. An answer that is held already, or that is not beside what is held, is
 * refused: the months stay contiguous.
 *
 * @param {object} loaded The months held.
 * @param {object} answer The answer of one or more months in a row.
 * @param {number} cap The most months held ({@see capOf()}).
 * @returns {object|null} The months held and the shift, in days; null when refused.
 */
export const extendLoaded = (loaded: Loaded, answer: TrailAnswer, cap: number = capOf('day')):
    {loaded: Loaded, shift: number} | null => {
    const added = monthsOf(answer.from, answer.to);
    const months = [...loaded.months];
    const answers = [...loaded.answers];
    let shift = 0;
    if (added[0] === monthAfter(months[months.length - 1], 1)) {
        months.push(...added);
        answers.push(...added.map(() => answer));
        while (months.length > cap) {
            shift -= monthDays(String(months.shift())).length;
            answers.shift();
        }
    } else if (added[added.length - 1] === monthAfter(months[0], -1)) {
        months.unshift(...added);
        answers.unshift(...added.map(() => answer));
        shift = added.reduce((days, month) => days + monthDays(month).length, 0);
        while (months.length > cap) {
            months.pop();
            answers.pop();
        }
    } else {
        return null;
    }
    return {loaded: {months: months, answers: answers}, shift: shift};
};

/**
 * The scroll position that keeps the view still once the first day held moved by a shift.
 *
 * @param {number} left The scroll position before.
 * @param {number} shift The days the first day held moved back (negative: forward).
 * @param {number} pitch A day's width, in pixels.
 * @returns {number}
 */
export const keptLeft = (left: number, shift: number, pitch: number): number => Math.max(0, left + shift * pitch);

/**
 * Where the toolbar's arrows go from the first day in view: back to its month's 1st, or to the month before when it is
 * the 1st already; ahead to the next month's 1st.
 *
 * @param {string} first The first day in view, YYYY-MM-DD.
 * @param {number} direction -1 back, 1 ahead.
 * @returns {string} The month, YYYY-MM.
 */
export const monthStep = (first: string, direction: number): string => {
    if (direction > 0) {
        return monthAfter(first.slice(0, 7), 1);
    }
    return first.endsWith('-01') ? monthAfter(first.slice(0, 7), -1) : first.slice(0, 7);
};

/** A month and its year. */
const MONTH_YEAR: Intl.DateTimeFormatOptions = {month: 'long', year: 'numeric'};

/**
 * The months in view as the toolbar names them, in the viewer's language: "novembro de 2026", "novembro – dezembro de
 * 2026", "dezembro de 2026 – janeiro de 2027", Intl's own range where it has one.
 *
 * @param {string} first The first day in view, YYYY-MM-DD.
 * @param {string} last The last day in view, YYYY-MM-DD.
 * @param {string|undefined} language The language tag.
 * @returns {string}
 */
export const rangeLabel = (first: string, last: string, language: string | undefined): string => {
    const from = `${first.slice(0, 7)}-01`;
    const to = `${last.slice(0, 7)}-01`;
    if (from >= to) {
        return say(from, MONTH_YEAR, language);
    }
    try {
        return new Intl.DateTimeFormat(language, {...MONTH_YEAR, timeZone: 'UTC'})
            .formatRange(new Date(`${from}T00:00:00Z`), new Date(`${to}T00:00:00Z`));
    } catch (error) {
        return `${say(from, MONTH_YEAR, language)} – ${say(to, MONTH_YEAR, language)}`;
    }
};

/** A week's month, in the weeks row. */
const WEEK_MONTH: Intl.DateTimeFormatOptions = {month: 'short'};

/** One week of the timeline's weeks row: where it starts among the days held, how many days it holds, its first and last. */
export type WeekCell = {at: number, length: number, first: string, last: string};

/**
 * The weekday of a day, 0 for Sunday.
 *
 * @param {string} iso The day, YYYY-MM-DD.
 * @returns {number}
 */
const weekdayOf = (iso: string): number =>
    new Date(Date.UTC(Number(iso.slice(0, 4)), Number(iso.slice(5, 7)) - 1, Number(iso.slice(8, 10)))).getUTCDay();

/**
 * The weeks of the days held, each beginning on the viewer's first weekday (core's calendar setting, the month grid's):
 * the first starts on the first day held, whatever its weekday, and the last ends on the last day held, so a week cut
 * by an edge of what is held is the part of it that is held.
 *
 * @param {Array} days The days held, in order.
 * @param {number} firstday The weekday a week begins on, 0 for Sunday.
 * @returns {Array} See WeekCell.
 */
export const weekCells = (days: string[], firstday: number): WeekCell[] => {
    const cells: WeekCell[] = [];
    days.forEach((iso, index) => {
        if (index === 0 || weekdayOf(iso) === firstday) {
            cells.push({at: index, length: 1, first: iso, last: iso});
        } else {
            const cell = cells[cells.length - 1];
            cell.length++;
            cell.last = iso;
        }
    });
    return cells;
};

/**
 * A week's cell of the weeks row: a short month over its days ("nov." over "2–8"); a week that crosses a month names the
 * end's month beside its last day ("out." over "26–1 nov."), and a week of one day held is that day.
 *
 * @param {object} cell The week.
 * @param {string|undefined} language The language tag.
 * @returns {object} {kicker, days}.
 */
export const weekLabel = (cell: WeekCell, language: string | undefined): {kicker: string, days: string} => {
    const first = Number(cell.first.slice(8, 10));
    const last = Number(cell.last.slice(8, 10));
    const kicker = say(cell.first, WEEK_MONTH, language);
    if (cell.length <= 1) {
        return {kicker: kicker, days: String(first)};
    }
    const crosses = cell.first.slice(0, 7) !== cell.last.slice(0, 7);
    return {kicker: kicker, days: crosses ? `${first}–${last} ${say(cell.last, WEEK_MONTH, language)}` : `${first}–${last}`};
};

/** The narrowest week cell that draws its label, in rem: "nov." over "26–1 nov." needs about this much. */
export const WEEK_LABEL_LEAST = 2.5;

/**
 * Whether a week cell is wide enough at a zoom for its label. A week cut by the start of what is held may be a day or two
 * (one and two days are narrower than {@see WEEK_LABEL_LEAST} by weeks): it draws no text and keeps its line, and the
 * next week's label names the days after it. Off the weeks there is no label at all.
 *
 * @param {object} cell The week.
 * @param {string} zoom The zoom.
 * @returns {boolean}
 */
export const weekLabelled = (cell: WeekCell, zoom: Zoom): boolean =>
    zoom === 'week' && cell.length * dayWidth(zoom) >= WEEK_LABEL_LEAST;

/** A course's dates as the trail column says them: a range, a start alone, an end alone, or none. */
export type Dates = {kind: 'range' | 'from' | 'until' | 'none', text: string};

/** A short date with its year: "2 nov. 2026", "Nov 2, 2026". */
const SHORT_DATE: Intl.DateTimeFormatOptions = {day: 'numeric', month: 'short', year: 'numeric'};

/** A formatter's part, of one date or of a range (whose parts say which side they come from). */
type DatePart = {type: string, value: string, source?: string};

/**
 * Whether a range's part is its separator: the literal between the start's parts and the end's.
 *
 * @param {Array} parts The parts.
 * @param {number} index The part's index.
 * @returns {boolean}
 */
const separates = (parts: DatePart[], index: number): boolean =>
    parts[index - 1]?.source === 'startRange' && parts[index + 1]?.source === 'endRange';

/**
 * A formatter's parts as one compact line: a literal holding a word inside one date ("2 de nov. de 2026" in Portuguese)
 * gives way to a space, never the range's separator, which may be a word ("a", "bis", "au"); a dash separating two days
 * of one month loses its spaces ("2–20 nov. 2026"). The order and the month's spelling stay the language's.
 *
 * @param {Array} parts The parts.
 * @returns {string}
 */
const compactParts = (parts: DatePart[]): string => parts
    .map((part, index) => {
        if (part.type !== 'literal') {
            return part.value;
        }
        if (separates(parts, index)) {
            const tight = parts[index - 1].type === 'day' && parts[index + 1].type === 'day' && part.value.includes('–');
            return tight ? '–' : part.value;
        }
        return /\p{L}/u.test(part.value) ? ' ' : part.value;
    })
    .join('').replace(/\s+/g, ' ').trim();

/**
 * A day as a short date with its year, compact.
 *
 * @param {string} iso The day, YYYY-MM-DD.
 * @param {string|undefined} language The language tag.
 * @returns {string}
 */
const shortDate = (iso: string, language: string | undefined): string => {
    try {
        return compactParts(new Intl.DateTimeFormat(language, {...SHORT_DATE, timeZone: 'UTC'})
            .formatToParts(new Date(`${iso}T00:00:00Z`)));
    } catch (error) {
        return say(iso, SHORT_DATE, language);
    }
};

/**
 * A course's dates for its row in the trail column, in the viewer's timezone and language: both ends as one range
 * (Intl's, so one month name when the month is the same and one year when the year is: "2–20 nov. 2026",
 * "16 nov. – 12 dez. 2026", "15 dez. 2026 – 20 jan. 2027"), a start alone, an end alone (an expected completion date is
 * the end), or none. The view words the last three ("A partir de", "Até", "Sem datas").
 *
 * @param {number} start The start, in seconds, 0 for none.
 * @param {number} end The end, in seconds, 0 for none.
 * @param {object} zone The viewer's timezone reader.
 * @param {string|undefined} language The language tag.
 * @returns {object} See Dates.
 */
export const datesOf = (start: number, end: number, zone: Zone, language: string | undefined): Dates => {
    const from = start > 0 ? zone.day(start) : '';
    const to = end > 0 ? zone.day(end) : '';
    if (from !== '' && to !== '') {
        // Dates out of order are the start day alone, as the bar draws them.
        const last = to < from ? from : to;
        try {
            return {kind: 'range', text: compactParts(new Intl.DateTimeFormat(language, {...SHORT_DATE, timeZone: 'UTC'})
                .formatRangeToParts(new Date(`${from}T00:00:00Z`), new Date(`${last}T00:00:00Z`)))};
        } catch (error) {
            return {kind: 'range', text: from === last ? shortDate(from, language)
                : `${shortDate(from, language)} – ${shortDate(last, language)}`};
        }
    }
    if (from !== '') {
        return {kind: 'from', text: shortDate(from, language)};
    }
    return to !== '' ? {kind: 'until', text: shortDate(to, language)} : {kind: 'none', text: ''};
};

/**
 * The desktop grid's measures, in rem: the trail column, one day at each zoom (days, weeks, months), one row and a
 * bar's height. scss/calendar.scss declares the same (--dim-cal-trail-label, --dim-cal-trail-day on .dim-cal__trails and
 * its --week and --month modifiers, -row, -bar); calendar_component_source_test compares them.
 */
export const TRACK = {label: 21.25, day: 3, week: 1, month: 0.25, row: 3.5, bar: 1.5};

/**
 * The narrowest bar that draws its step's name off the day zoom, in rem; a narrower one is named for a screen reader
 * alone. By days the least is one day (TRACK.day), so a one-day bar keeps its name there.
 */
export const NAMED_BAR = 3.5;

/**
 * A day's width at a zoom, in rem.
 *
 * @param {string} zoom The zoom.
 * @returns {number}
 */
export const dayWidth = (zoom: Zoom): number => TRACK[zoom];

/**
 * Whether a bar is wide enough to draw its step's name at a zoom: one day by days, {@see NAMED_BAR} off them.
 *
 * @param {object} bar The bar.
 * @param {string} zoom The zoom.
 * @returns {boolean}
 */
export const namedBar = (bar: TrailBar, zoom: Zoom): boolean => {
    const least = zoom === 'day' ? TRACK.day : NAMED_BAR;
    return (bar.to - bar.from + 1) * dayWidth(zoom) >= least;
};

/** The gap between an open bar's last day and its "no end date" chip, in rem: fixed at every zoom (decorative). */
export const CHIP_GAP = 0.375;

/**
 * Where the chip after an open bar starts, in rem from the first day held, by the bars' day arithmetic: its gap after
 * the end of the bar's last day, at every zoom. Null for a bar that is not open, and for one whose 30 days run to the
 * last day held or past it: the chip is drawn once the next month is held, never at the edge.
 *
 * @param {object} bar The bar ({@see barOf()}).
 * @param {number} count The days held.
 * @param {string} zoom The zoom.
 * @returns {number|null}
 */
export const chipLeft = (bar: TrailBar, count: number, zoom: Zoom): number | null =>
    bar.open && bar.to + 1 < count ? Math.round(((bar.to + 1) * dayWidth(zoom) + CHIP_GAP) * 10000) / 10000 : null;

/**
 * The scroll position that keeps the day at the start of the view where it was once the zoom changes: the same days
 * from the first day held, at the new width (the browser keeps it inside the new furthest scroll).
 *
 * @param {number} left The scroll position at the old zoom.
 * @param {string} from The old zoom.
 * @param {string} to The new zoom.
 * @returns {number}
 */
export const zoomedLeft = (left: number, from: Zoom, to: Zoom): number =>
    Math.max(0, Math.round(left * dayWidth(to) / dayWidth(from)));

/** The scroller's geometry, from its scroll position and its two widths. */
export type Track = {
    pitch: number,
    view: number,
    max: number,
    left: number,
    start: number,
    span: number,
    back: boolean,
    ahead: boolean,
};

/**
 * The scroller's geometry: a day's width in pixels (the content is the trail column and the days, TRACK, at the zoom's
 * day width), the width the days are seen through (the scroller's less the column held still), the furthest scroll, the
 * position kept inside it, the map's thumb (where the days in view start and how many they are, as fractions of the days
 * held) and whether there are days to scroll back or ahead to. A scroller with nothing to scroll has a whole thumb and
 * no way to go.
 *
 * @param {number} left The scroll position.
 * @param {number} total The scroller's content width.
 * @param {number} width The scroller's own width, the part of it in view.
 * @param {number} days The days' columns.
 * @param {number} day A day's width in rem ({@see dayWidth()}).
 * @returns {object} See Track.
 */
export const trackOf = (left: number, total: number, width: number, days: number, day: number = TRACK.day): Track => {
    const pitch = days > 0 ? total / (days + TRACK.label / day) : 0;
    const max = Math.max(0, total - width);
    if (pitch <= 0 || max < 1) {
        return {pitch: pitch, view: Math.max(0, width), max: 0, left: 0, start: 0, span: 1, back: false, ahead: false};
    }
    const view = Math.max(0, width - pitch * TRACK.label / day);
    const span = Math.min(1, view / (days * pitch));
    const at = Math.max(0, Math.min(left, max));
    return {
        pitch: pitch,
        view: view,
        max: max,
        left: at,
        start: Math.min(1 - span, at / (days * pitch)),
        span: span,
        back: at > 1,
        ahead: at < max - 1,
    };
};

/**
 * The scroll position that puts a day at the start of the days in view, kept between the ends.
 *
 * @param {number} index The day's index among the days held.
 * @param {object} track The scroller's geometry.
 * @returns {number}
 */
export const dayLeft = (index: number, track: Track): number => (track.max <= 0 || track.pitch <= 0
    ? 0
    : Math.max(0, Math.min(track.max, Math.round(index * track.pitch))));

/**
 * The scroll position that brings today into view from its month's 1st: the 1st at the start when today then shows
 * whole with a day to spare on its far side, else the least scroll that shows it so.
 *
 * @param {number} todayat Today's index among the days held, -1 when not held.
 * @param {number} monthat The index of today's month's 1st among the days held.
 * @param {object} track The scroller's geometry.
 * @returns {number}
 */
export const todayLeft = (todayat: number, monthat: number, track: Track): number => {
    if (todayat < 0 || track.max <= 0) {
        return dayLeft(Math.max(0, monthat), track);
    }
    const far = (todayat + 2) * track.pitch;
    const base = Math.max(0, monthat) * track.pitch;
    return far - base <= track.view ? dayLeft(monthat, track) : Math.min(track.max, Math.round(far - track.view));
};

/**
 * Where the scroller opens: today in view ({@see todayLeft()} from the first day held), the first day held when today
 * is not held.
 *
 * @param {number} todayat Today's index among the days held, -1 when not held.
 * @param {object} track The scroller's geometry at position 0.
 * @returns {number} The scroll position.
 */
export const openingLeft = (todayat: number, track: Track): number => (todayat < 0 ? 0 : todayLeft(todayat, 0, track));

/** Where the timeline is asked to go: a month's 1st, a course's bar, or today from its month's 1st. */
export type ScrollTarget = {kind: 'day', index: number} | {kind: 'step', index: number}
    | {kind: 'today', index: number, month: number};

/**
 * A target after the first day held moved back by some days (a month prepended) or forward (one dropped): the same
 * day, at its new index.
 *
 * @param {object} target The target.
 * @param {number} shift The days the first day held moved back (negative: forward).
 * @returns {object} The target.
 */
export const shiftedTarget = (target: ScrollTarget, shift: number): ScrollTarget => (target.kind === 'today'
    ? {kind: 'today', index: target.index < 0 ? target.index : target.index + shift, month: target.month + shift}
    : {kind: target.kind, index: target.index + shift});

/**
 * The scroll position of a target: a day at the start of the days in view, a course's bar {@see STEP_INSET} days inside
 * them, today by {@see todayLeft()}.
 *
 * @param {object} target The target.
 * @param {object} track The scroller's geometry.
 * @returns {number}
 */
export const targetLeft = (target: ScrollTarget, track: Track): number => {
    if (target.kind === 'today') {
        return todayLeft(target.index, target.month, track);
    }
    return dayLeft(target.kind === 'step' ? target.index - STEP_INSET : target.index, track);
};

/**
 * The days in view: the first whose start is in view and the last any part of which is.
 *
 * @param {object} track The scroller's geometry.
 * @param {number} count The days held.
 * @returns {object} {first, last}, indexes among the days held.
 */
export const shownDays = (track: Track, count: number): {first: number, last: number} => {
    if (track.pitch <= 0 || count <= 0) {
        return {first: 0, last: Math.max(0, count - 1)};
    }
    const first = Math.max(0, Math.min(count - 1, Math.floor(track.left / track.pitch + 0.001)));
    const last = Math.min(count - 1, Math.ceil((track.left + track.view) / track.pitch - 0.001) - 1);
    return {first: first, last: Math.max(first, last)};
};

/**
 * Whether the days in view reach within {@see LOAD_AHEAD} days of the end of what is held: time to ask for the next
 * month. A scroller that has not measured a day yet is never near.
 *
 * @param {object} track The scroller's geometry.
 * @param {number} count The days held.
 * @returns {boolean}
 */
export const nearEnd = (track: Track, count: number): boolean =>
    track.pitch > 0 && track.left + track.view >= (count - LOAD_AHEAD) * track.pitch;

/**
 * Where an edge arrow takes the scroller: a page of whole days back or ahead (the days in view less one, at least
 * one), landing on a day's start, never past either end.
 *
 * @param {number} left The scroll position.
 * @param {number} direction -1 back, 1 ahead.
 * @param {object} track The scroller's geometry.
 * @returns {number} The scroll position.
 */
export const pageLeft = (left: number, direction: number, track: Track): number => {
    if (track.max <= 0 || track.pitch <= 0) {
        return 0;
    }
    const page = Math.max(1, Math.floor(track.view / track.pitch) - 1) * track.pitch;
    const landed = Math.round((left + direction * page) / track.pitch) * track.pitch;
    return Math.max(0, Math.min(track.max, landed));
};

/**
 * Where the map puts the scroller: the thumb's start at the pointer less where it was grabbed, kept on the line.
 *
 * @param {number} at Where the pointer is along the map, 0 to 1.
 * @param {number} grab Where the thumb was grabbed, from its start, as a fraction of the map.
 * @param {object} track The scroller's geometry.
 * @param {number} days The days' columns.
 * @returns {number} The scroll position.
 */
export const scrubLeft = (at: number, grab: number, track: Track, days: number): number => {
    const start = Math.max(0, Math.min(1 - track.span, at - grab));
    return Math.max(0, Math.min(track.max, start * days * track.pitch));
};

/** One piece of a connector, in rem from the first day held's start and the first step row's top. */
export type LinkPiece = {
    kind: 'horizontal' | 'vertical' | 'dot' | 'right' | 'down',
    left: number,
    top: number,
    width: number,
    height: number,
};

/**
 * A connector's measures, in rem: the line, the stub out of a bar's end (the elbow's offset and the drop's, at the day
 * zoom), the least an elbow's offset narrows to over a narrow gap and the least a drop's does on a narrow day, the
 * detour's room before the next bar, an arrowhead's length and half its width, and the dot.
 */
const LINK = {line: 0.125, stub: 0.625, tight: 0.1875, least: 0.125, detour: 0.5625, head: 0.375, half: 0.25, dot: 0.375};

/**
 * The days from one ISO day to another, negative when the second is earlier.
 *
 * @param {string} from The first day.
 * @param {string} to The second day.
 * @returns {number}
 */
const daysBetween = (from: string, to: string): number =>
    Math.round((Date.parse(`${to}T00:00:00Z`) - Date.parse(`${from}T00:00:00Z`)) / 86400000);

/**
 * A step's days counted from the first day held, unclipped (before it, negative; an open step runs its {@see OPEN_DAYS}), as
 * {@see barOf()} reads them: an end alone is its day, an end before the start the start day. Null for a step with no
 * date, which has no bar anywhere.
 *
 * @param {object} step The step.
 * @param {string} first The first day held.
 * @param {object} zone The viewer's timezone reader.
 * @returns {object|null} {from, to}.
 */
export const reachOf = (step: TrailStep, first: string, zone: Zone): {from: number, to: number} | null => {
    if (step.start <= 0 && step.end <= 0) {
        return null;
    }
    if (step.start <= 0) {
        const at = daysBetween(first, zone.day(step.end));
        return {from: at, to: at};
    }
    const from = daysBetween(first, zone.day(step.start));
    return {from: from, to: step.end <= 0 ? from + OPEN_DAYS - 1 : Math.max(from, daysBetween(first, zone.day(step.end)))};
};

/**
 * A piece kept to the days held: a horizontal line cut at both edges, anything else whole or not at all.
 *
 * @param {object} piece The piece.
 * @param {number} width The days held's width, in rem.
 * @returns {object|null}
 */
const clipped = (piece: LinkPiece, width: number): LinkPiece | null => {
    if (piece.kind !== 'horizontal') {
        return piece.left >= 0 && piece.left + piece.width <= width ? piece : null;
    }
    const left = Math.max(0, piece.left);
    const right = Math.min(width, piece.left + piece.width);
    return right > left ? {...piece, left: left, width: right - left} : null;
};

/**
 * The pieces joining one step's bar to the next one's, by the dates (the Conectores board of the approved mockup):
 *
 * - the next starts after the previous ends: an elbow out of the previous bar's right end, down, and into the next
 *   bar's left end with an arrowhead; over a narrow gap (a small zoom) the elbow comes half way, never under
 *   LINK.tight, and the line into the next bar shrinks to nothing rather than run backwards, the arrowhead's tip kept
 *   past the elbow;
 * - the next starts while the previous runs: a drop on the next's start day, a dot on the previous bar's base and a
 *   short arrow down into the next bar; the drop's offset into the day is half the day's width on a narrow day, never
 *   under LINK.least;
 * - the next starts before the previous starts: a detour on the left, from the previous bar's start into the next's;
 *   its turn keeps LINK.detour before the next bar, half the gap on a narrow one and no more than the room the days
 *   held leave before it, never under LINK.tight, its line into the next bar shrinking to nothing as the elbow's does.
 *
 * @param {object} a The previous step's days ({@see reachOf()}).
 * @param {object} b The next step's days.
 * @param {number} row The previous step's row, from 0.
 * @param {number} day A day's width, in rem.
 * @returns {Array} The pieces, unclipped.
 */
const pathOf = (a: {from: number, to: number}, b: {from: number, to: number}, row: number, day: number): LinkPiece[] => {
    const amid = row * TRACK.row + TRACK.row / 2;
    const bmid = amid + TRACK.row;
    /**
     * A horizontal line between two points of a row's middle.
     *
     * @param {number} y The line's middle.
     * @param {number} x1 One end.
     * @param {number} x2 The other.
     * @returns {object}
     */
    const across = (y: number, x1: number, x2: number): LinkPiece => ({
        kind: 'horizontal', left: Math.min(x1, x2), top: y - LINK.line / 2, width: Math.abs(x2 - x1), height: LINK.line,
    });
    /**
     * A vertical line from one height to a lower one.
     *
     * @param {number} x The line's middle.
     * @param {number} y1 Its top.
     * @param {number} y2 Its bottom.
     * @returns {object}
     */
    const down = (x: number, y1: number, y2: number): LinkPiece => ({
        kind: 'vertical', left: x - LINK.line / 2, top: y1, width: LINK.line, height: y2 - y1,
    });
    /**
     * An arrowhead pointing right, its tip on a point.
     *
     * @param {number} x The tip.
     * @param {number} y Its height.
     * @returns {object}
     */
    const right = (x: number, y: number): LinkPiece => ({
        kind: 'right', left: x - LINK.head, top: y - LINK.half, width: LINK.head, height: 2 * LINK.half,
    });
    if (b.from > a.to) {
        const start = (a.to + 1) * day;
        const end = b.from * day;
        const elbow = start + Math.max(LINK.tight, Math.min(LINK.stub, (end - start) / 2));
        return [
            across(amid, start, elbow),
            down(elbow, amid - LINK.line / 2, bmid + LINK.line / 2),
            across(bmid, elbow, Math.max(elbow, end - LINK.head)),
            right(Math.max(end, elbow + LINK.head), bmid),
        ];
    }
    if (b.from >= a.from) {
        const x = b.from * day + Math.min(LINK.stub, Math.max(LINK.least, day / 2));
        const base = amid + TRACK.bar / 2;
        const head = bmid - TRACK.bar / 2;
        return [
            {kind: 'dot', left: x - LINK.dot / 2, top: base - LINK.dot / 2, width: LINK.dot, height: LINK.dot},
            down(x, base, head - LINK.head),
            {kind: 'down', left: x - LINK.half, top: head - LINK.head, width: 2 * LINK.half, height: LINK.head},
        ];
    }
    const start = a.from * day;
    const end = b.from * day;
    const turn = end - Math.max(LINK.tight, Math.min(LINK.detour, (start - end) / 2, end - LINK.line / 2));
    return [
        across(amid, turn, start),
        down(turn, amid - LINK.line / 2, bmid + LINK.line / 2),
        across(bmid, turn, Math.max(turn, end - LINK.head)),
        right(Math.max(end, turn + LINK.head), bmid),
    ];
};

/**
 * A number of rem kept to four places, so the pieces' styles carry no binary noise.
 *
 * @param {number} value The number.
 * @returns {number}
 */
const tidy = (value: number): number => Math.round(value * 10000) / 10000;

/**
 * The connectors of a trail's steps over the days held, in the trail's order: each step joined to the next by
 * {@see pathOf()}'s rule at the zoom's day width, cut at the edges of what is held (a line whose other end is in a month
 * not held runs to that edge, and goes on once the month is held). A step with no date breaks the chain: nothing goes
 * into it or out of it, and the steps around it are not joined. A folded trail draws none.
 *
 * @param {Array} steps The trail's steps, in order.
 * @param {Array} days The days held, in order.
 * @param {object} zone The viewer's timezone reader.
 * @param {boolean} shown Whether the trail's steps are shown (not folded).
 * @param {string} zoom The timeline's zoom.
 * @returns {Array} The pieces, in rem.
 */
export const linksOf = (steps: TrailStep[], days: string[], zone: Zone, shown: boolean, zoom: Zoom = 'day'): LinkPiece[] => {
    if (!shown || days.length === 0) {
        return [];
    }
    const day = dayWidth(zoom);
    const width = days.length * day;
    const reaches = steps.map((step) => reachOf(step, days[0], zone));
    const pieces: LinkPiece[] = [];
    reaches.forEach((reach, index) => {
        const next = index + 1 < reaches.length ? reaches[index + 1] : null;
        if (reach === null || next === null) {
            return;
        }
        pathOf(reach, next, index, day).forEach((piece) => {
            const kept = clipped(piece, width);
            if (kept) {
                pieces.push({
                    kind: kept.kind,
                    left: tidy(kept.left),
                    top: tidy(kept.top),
                    width: tidy(kept.width),
                    height: tidy(kept.height),
                });
            }
        });
    });
    return pieces;
};
