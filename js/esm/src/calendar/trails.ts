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
 *   month and the next (local\calendar\trails::MONTHS), each further month is one call, appended or prepended, and the
 *   answers are merged by trail id ({@see mergedOf()}), a trail kept while its period meets what is held.
 * - A bar runs from its step's start to its end, clipped at the edges of what is held: a step that began earlier is
 *   marked `before`, one that ends later `after`, and one with no end `open` (it fades at the edge). A step with an end
 *   and no start is a milestone on its end; an expected date drawn as the bar's end is a milestone too.
 * - On a desktop the days have a fixed width and only the timeline scrolls, the trail column held still beside it. The
 *   scroller's geometry is read from the scroller alone ({@see trackOf()}: its content width is the trail column and the
 *   days, TRACK, in rem, which scss/calendar.scss declares with the same numbers), so the position of today, a month, a
 *   course, a page of days and the map's thumb are pure arithmetic over three numbers the scroll handlers read.
 * - Consecutive steps are joined in the trail's order ({@see linksOf()}), never by what their enrolment needs: the
 *   path is chosen from the dates alone, and a step with no date breaks the chain.
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
export type TrailProps = {offered: boolean, answer: TrailAnswer | null, trail: number, mine: boolean, icons?: FoldIcons};

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
    spanOf(trail.first, trail.last, false, days, zone);

/**
 * Where a span between two instants goes on the days held, or null when it does not touch them.
 *
 * @param {number} begins The start, in seconds, 0 for none.
 * @param {number} ends The end, in seconds, 0 for none.
 * @param {boolean} expected Whether the end is an expected completion date (a milestone when inside the days held).
 * @param {Array} days The days held, in order.
 * @param {object} zone The viewer's timezone reader.
 * @returns {object|null} See TrailBar.
 */
const spanOf = (begins: number, ends: number, expected: boolean, days: string[], zone: Zone): TrailBar | null => {
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
    const before = start < first;
    const after = end === null || end > last;
    const from = before ? 0 : days.indexOf(start);
    return {
        from: from,
        to: Math.max(from, after ? days.length - 1 : days.indexOf(String(end))),
        before: before,
        after: after,
        open: end === null,
        milestone: expected && end !== null && end <= last,
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
 * @param {number} months 1 for the month alone, {@see ANSWER_MONTHS} for the month and the next.
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
 * An address with the view's filters written, or taken out (any other view).
 *
 * @param {string} href The address.
 * @param {object|null} filter The filters, or null to take them out.
 * @returns {string}
 */
export const withTrail = (href: string, filter: TrailFilter | null): string => {
    const url = new URL(href, window.location.href);
    url.searchParams.delete(TRAIL_PARAM);
    url.searchParams.delete(MINE_PARAM);
    if (filter && filter.trail > 0) {
        url.searchParams.set(TRAIL_PARAM, String(filter.trail));
    }
    if (filter && filter.mine) {
        url.searchParams.set(MINE_PARAM, '1');
    }
    return url.toString();
};

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


/** The most months one answer of the service covers (local\calendar\trails::MONTHS): the month and the next. */
export const ANSWER_MONTHS = 2;

/** The most months the view holds; one more drops the month on the far side. */
export const LOADED_CAP = 6;

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
 * The months held with one more beside them: appended after the last, or prepended before the first, and the month on
 * the far side dropped past the cap. The shift is how many days the first day held moved back (a prepended month's
 * days) or forward (less a dropped month's), which the scroller adds to its position so the view stays still. A month
 * that is held already, or that is not beside what is held, is refused: the months stay contiguous.
 *
 * @param {object} loaded The months held.
 * @param {object} answer The answer of one month.
 * @param {number} cap The most months held.
 * @returns {object|null} The months held and the shift, in days; null when refused.
 */
export const extendLoaded = (loaded: Loaded, answer: TrailAnswer, cap: number = LOADED_CAP):
    {loaded: Loaded, shift: number} | null => {
    const month = answer.from.slice(0, 7);
    const months = [...loaded.months];
    const answers = [...loaded.answers];
    let shift = 0;
    if (month === monthAfter(months[months.length - 1], 1)) {
        months.push(month);
        answers.push(answer);
        while (months.length > cap) {
            shift -= monthDays(String(months.shift())).length;
            answers.shift();
        }
    } else if (month === monthAfter(months[0], -1)) {
        months.unshift(month);
        answers.unshift(answer);
        shift = monthDays(month).length;
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

/**
 * The desktop grid's measures, in rem: the trail column, one day, one row and a bar's height. scss/calendar.scss
 * declares the same four (--dim-cal-trail-label, -day, -row, -bar); calendar_component_source_test compares them.
 */
export const TRACK = {label: 21.25, day: 3, row: 3.25, bar: 1.5};

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
 * The scroller's geometry: a day's width in pixels (the content is the trail column and the days, TRACK), the width
 * the days are seen through (the scroller's less the column held still), the furthest scroll, the position kept inside
 * it, the map's thumb (where the days in view start and how many they are, as fractions of the days held) and whether
 * there are days to scroll back or ahead to. A scroller with nothing to scroll has a whole thumb and no way to go.
 *
 * @param {number} left The scroll position.
 * @param {number} total The scroller's content width.
 * @param {number} width The scroller's own width, the part of it in view.
 * @param {number} days The days' columns.
 * @returns {object} See Track.
 */
export const trackOf = (left: number, total: number, width: number, days: number): Track => {
    const pitch = days > 0 ? total / (days + TRACK.label / TRACK.day) : 0;
    const max = Math.max(0, total - width);
    if (pitch <= 0 || max < 1) {
        return {pitch: pitch, view: Math.max(0, width), max: 0, left: 0, start: 0, span: 1, back: false, ahead: false};
    }
    const view = Math.max(0, width - pitch * TRACK.label / TRACK.day);
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
 * A connector's measures, in rem: the line, the stub out of a bar's end, the room an elbow keeps before the next bar, the
 * detour's room before it, an arrowhead's length and half its width, and the dot.
 */
const LINK = {line: 0.125, stub: 0.625, room: 0.75, detour: 0.5625, head: 0.375, half: 0.25, dot: 0.375};

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
 * A step's days counted from the first day held, unclipped (before it, negative; an open step runs on for ever), as
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
    return {from: from, to: step.end <= 0 ? Infinity : Math.max(from, daysBetween(first, zone.day(step.end)))};
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
 *   bar's left end with an arrowhead;
 * - the next starts while the previous runs: a drop on the next's start day, a dot on the previous bar's base and a
 *   short arrow down into the next bar;
 * - the next starts before the previous starts: a detour on the left, from the previous bar's start into the next's.
 *
 * @param {object} a The previous step's days ({@see reachOf()}).
 * @param {object} b The next step's days.
 * @param {number} row The previous step's row, from 0.
 * @returns {Array} The pieces, unclipped.
 */
const pathOf = (a: {from: number, to: number}, b: {from: number, to: number}, row: number): LinkPiece[] => {
    const day = TRACK.day;
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
        const elbow = Math.min(start + LINK.stub, end - LINK.room);
        return [
            across(amid, start, elbow),
            down(elbow, amid - LINK.line / 2, bmid + LINK.line / 2),
            across(bmid, elbow, end - LINK.head),
            right(end, bmid),
        ];
    }
    if (b.from >= a.from) {
        const x = b.from * day + LINK.stub;
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
    const turn = end - LINK.detour;
    return [
        across(amid, turn, start),
        down(turn, amid - LINK.line / 2, bmid + LINK.line / 2),
        across(bmid, turn, end - LINK.head),
        right(end, bmid),
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
 * {@see pathOf()}'s rule, cut at the edges of what is held (a line whose other end is in a month not held runs to that
 * edge, and goes on once the month is held). A step with no date breaks the chain: nothing goes into it or out of it,
 * and the steps around it are not joined. A folded trail draws none.
 *
 * @param {Array} steps The trail's steps, in order.
 * @param {Array} days The days held, in order.
 * @param {object} zone The viewer's timezone reader.
 * @param {boolean} shown Whether the trail's steps are shown (not folded).
 * @returns {Array} The pieces, in rem.
 */
export const linksOf = (steps: TrailStep[], days: string[], zone: Zone, shown: boolean): LinkPiece[] => {
    if (!shown || days.length === 0) {
        return [];
    }
    const width = days.length * TRACK.day;
    const reaches = steps.map((step) => reachOf(step, days[0], zone));
    const pieces: LinkPiece[] = [];
    reaches.forEach((reach, index) => {
        const next = index + 1 < reaches.length ? reaches[index + 1] : null;
        if (reach === null || next === null) {
            return;
        }
        pathOf(reach, next, index).forEach((piece) => {
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
