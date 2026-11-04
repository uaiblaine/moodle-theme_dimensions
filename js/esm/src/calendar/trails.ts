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
 * The Trilhas view's model (docs/trail/PLAN.md section 7): the answer's shape, its address, and where each step's bar
 * goes on the month. Pure: no layout is read, the bars are grid columns.
 *
 * - The view's trail and "Só as minhas" are navigation, like the view: in the address (`trail=`, `mine=1`), never in
 *   the remembered state (local\calendar\page::trail_from_request(), mine_from_request()).
 * - A step's days are read in the viewer's timezone (days.ts zone()), as every item of the calendar.
 * - A bar runs from its step's start to its end, clipped at the month's edges: a step that began earlier is marked
 *   `before`, one that ends in a later month `after` (drawn into the "next month" column), and one with no end `open`
 *   (it fades at the month's edge). A step with an end and no start is a milestone on its end; an expected date drawn as
 *   the bar's end is a milestone too.
 * - R3 (docs/calendar/PROPOSAL.md section 33): on a desktop the days have a fixed width and only the timeline scrolls,
 *   the trail column and the "next month" column held still beside it. The scroller's geometry is read from the
 *   scroller alone ({@see trackOf()}: its content width is the three columns' widths, TRACK, in rem, which
 *   scss/calendar.scss declares with the same numbers), so the position of today, a page of days and the map's thumb are
 *   pure arithmetic over three numbers the scroll handlers read.
 *
 * @module     theme_dimensions/calendar/trails
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {monthLength} from '../daterange/calendar';
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

/** What the page hands the view (calendar_page::props()'s `trails`). */
export type TrailProps = {offered: boolean, answer: TrailAnswer | null, trail: number, mine: boolean};

/** The view's two filters: one trail (0 for every trail) and "Só as minhas". */
export type TrailFilter = {trail: number, mine: boolean};

/** Where a step's bar goes on the month: day indexes, 0 for the 1st. */
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
 * Where a step's bar goes on a month, or null when the step does not touch it.
 *
 * @param {object} step The step.
 * @param {Array} days The month's days, in order.
 * @param {object} zone The viewer's timezone reader.
 * @returns {object|null} See TrailBar.
 */
export const barOf = (step: TrailStep, days: string[], zone: Zone): TrailBar | null =>
    spanOf(step.start, step.end, step.expected, days, zone);

/**
 * Where a trail's own span goes on a month (the line under its row), from its period's first start to its last end:
 * {@see barOf()}'s rule over the trail's period, which is never a milestone.
 *
 * @param {object} trail The trail.
 * @param {Array} days The month's days, in order.
 * @param {object} zone The viewer's timezone reader.
 * @returns {object|null} See TrailBar.
 */
export const trailBarOf = (trail: Trail, days: string[], zone: Zone): TrailBar | null =>
    spanOf(trail.first, trail.last, false, days, zone);

/**
 * Where a span between two instants goes on a month, or null when it does not touch it.
 *
 * @param {number} begins The start, in seconds, 0 for none.
 * @param {number} ends The end, in seconds, 0 for none.
 * @param {boolean} expected Whether the end is an expected completion date (a milestone when inside the month).
 * @param {Array} days The month's days, in order.
 * @param {object} zone The viewer's timezone reader.
 * @returns {object|null} See TrailBar.
 */
const spanOf = (begins: number, ends: number, expected: boolean, days: string[], zone: Zone): TrailBar | null => {
    const first = days[0];
    const last = days[days.length - 1];
    const start = begins > 0 ? zone.day(begins) : null;
    const end = ends > 0 ? zone.day(ends) : null;
    if (start === null && end === null) {
        return null;
    }
    if ((start !== null && start > last) || (end !== null && end < first)) {
        return null;
    }
    if (start === null) {
        // An end alone: the milestone on its day.
        const at = days.indexOf(String(end));
        return {from: at, to: at, before: false, after: false, open: false, milestone: true};
    }
    const before = start < first;
    const after = end === null || end > last;
    const from = before ? 0 : days.indexOf(start);
    return {
        from: from,
        // An end before the start (a course's dates out of order) is the start day alone.
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
 * The service's arguments for a month, the filters and the category scope.
 *
 * @param {string} month The month, YYYY-MM.
 * @param {object} filter The view's filters.
 * @param {number} categoryid The category narrowing, 0 for none.
 * @param {boolean} recursive Whether the category's whole subtree counts.
 * @returns {object}
 */
export const trailArgs = (month: string, filter: TrailFilter, categoryid: number, recursive: boolean): Record<string, unknown> => ({
    month: month,
    trail: filter.trail,
    mine: filter.mine,
    categoryid: categoryid,
    recursive: recursive,
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

/**
 * What of a step lies past the month, drawn in the "next month" column: `continues` for a bar that runs on past the
 * month's end, `after` for a step that starts after it, `none` for anything else (an open step fades out instead).
 *
 * @param {object} step The step.
 * @param {Array} days The month's days, in order.
 * @param {object} zone The viewer's timezone reader.
 * @returns {string}
 */
export const beyondOf = (step: TrailStep, days: string[], zone: Zone): 'none' | 'continues' | 'after' => {
    const last = days[days.length - 1];
    if (step.end <= 0) {
        return 'none';
    }
    const end = zone.day(step.end);
    if (end <= last) {
        return 'none';
    }
    return step.start > 0 && zone.day(step.start) > last ? 'after' : 'continues';
};

/**
 * The desktop grid's three columns, in rem: the trail column, one day, the "next month" column. scss/calendar.scss
 * declares the same three (--dim-cal-trail-label, -day, -spill); calendar_component_source_test compares them.
 */
export const TRACK = {label: 21.25, day: 3, spill: 4.75};

/** The scroller's geometry, from its scroll position and its two widths. */
export type Track = {
    pitch: number,
    view: number,
    max: number,
    start: number,
    span: number,
    back: boolean,
    ahead: boolean,
};

/**
 * The scroller's geometry: a day's width in pixels (the content is the trail column, the days and the "next month"
 * column, TRACK), the width the days are seen through (the scroller's less the two columns held still), the furthest
 * scroll, the map's thumb (where the days in view start and how many they are, as fractions of the month) and whether
 * there are days to scroll back or ahead to. A scroller with nothing to scroll has a whole thumb and no way to go.
 *
 * @param {number} left The scroll position.
 * @param {number} total The scroller's content width.
 * @param {number} width The scroller's own width, the part of it in view.
 * @param {number} days The month's days.
 * @returns {object} See Track.
 */
export const trackOf = (left: number, total: number, width: number, days: number): Track => {
    const pitch = days > 0 ? total / (days + (TRACK.label + TRACK.spill) / TRACK.day) : 0;
    const max = Math.max(0, total - width);
    if (pitch <= 0 || max < 1) {
        return {pitch: pitch, view: Math.max(0, width), max: 0, start: 0, span: 1, back: false, ahead: false};
    }
    const view = Math.max(0, width - pitch * (TRACK.label + TRACK.spill) / TRACK.day);
    const span = Math.min(1, view / (days * pitch));
    const at = Math.max(0, Math.min(left, max));
    return {
        pitch: pitch,
        view: view,
        max: max,
        start: Math.min(1 - span, at / (days * pitch)),
        span: span,
        back: at > 1,
        ahead: at < max - 1,
    };
};

/**
 * Where the scroller opens: today in view, by the least scroll that shows it whole with a day to spare on its far side
 * (so a month whose today is already in view opens at its 1st); its 1st for a month without today.
 *
 * @param {number} todayat Today's index in the month, -1 when the month is not today's.
 * @param {object} track The scroller's geometry at position 0.
 * @returns {number} The scroll position.
 */
export const openingLeft = (todayat: number, track: Track): number => {
    if (todayat < 0 || track.max <= 0) {
        return 0;
    }
    const far = (todayat + 2) * track.pitch;
    return far <= track.view ? 0 : Math.min(track.max, Math.round(far - track.view));
};

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
 * @param {number} days The month's days.
 * @returns {number} The scroll position.
 */
export const scrubLeft = (at: number, grab: number, track: Track, days: number): number => {
    const start = Math.max(0, Math.min(1 - track.span, at - grab));
    return Math.max(0, Math.min(track.max, start * days * track.pitch));
};
