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
 * One day's items, pure: the all-day strip, the hour column in columns, and who overlaps whom.
 *
 * - The ALL-DAY strip holds every item touching the day that is a bar of the month grid (it spans more than one day,
 *   or it is an event course) or that fills whole days ({@see isAllDay()}).
 * - The HOUR column holds the day's other items. Those with a duration are laid out in columns: sorted by start,
 *   then longer first, an item takes the first column whose last item ended at or before its start, and a cluster
 *   of items that overlap one another (directly or through each other) shares one column count. A point item (a
 *   deadline, duration 0) sits in the column at its time, alone in its own cluster.
 * - Two items OVERLAP when both have a duration and each starts before the other ends: touching ends do not, a
 *   point item never does, and an all-day or multi-day item never counts.
 * - The hour column draws the whole day in a scroll box:
 *   the busy band (hourWindow()) or the working hours (EMPTY_WINDOW) is where the box starts ({@see boxTopOf()}),
 *   and a fade marks each end it can still scroll to ({@see edgesOf()}).
 *
 * @module     theme_dimensions/calendar/overlap
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Placed} from './layout';
import type {Item} from './model';
import type {Zone} from './days';

/** One item of the hour column, in its column, with the items it overlaps. */
export type Laid = {entry: Placed, column: number, columns: number, overlaps: Placed[]};

/** A day's content. */
export type DayPlan = {allday: Placed[], hours: Laid[]};

/**
 * An item's identity across the answer's two sources.
 *
 * @param {object} item The item.
 * @returns {string}
 */
export const itemKey = (item: Item): string => `${item.source}-${item.id}`;

/**
 * Whether an item fills whole days in the zone: it starts and ends on a midnight, a day or more apart.
 *
 * @param {object} item The item.
 * @param {object} zone The viewer's timezone reader.
 * @returns {boolean}
 */
export const isAllDay = (item: Item, zone: Zone): boolean => {
    if (item.timeduration <= 0) {
        return false;
    }
    const end = item.timestart + item.timeduration;
    return zone.minutes(item.timestart) === 0 && zone.minutes(end) === 0 && zone.day(item.timestart) !== zone.day(end);
};

/**
 * Whether two items overlap: both have a duration and each starts before the other ends.
 *
 * @param {object} a An item.
 * @param {object} b Another.
 * @returns {boolean}
 */
export const intersects = (a: Item, b: Item): boolean => a.timeduration > 0 && b.timeduration > 0
    && a.timestart < b.timestart + b.timeduration && b.timestart < a.timestart + a.timeduration;

/**
 * The order of the hour column: start, then longer first, then name, then id.
 *
 * @param {object} a A placed item.
 * @param {object} b Another.
 * @returns {number}
 */
const byStart = (a: Placed, b: Placed): number => (a.item.timestart - b.item.timestart)
    || (b.item.timeduration - a.item.timeduration)
    || a.item.name.localeCompare(b.item.name)
    || (a.item.id - b.item.id);

/**
 * The hour column's items in columns, each with the others it overlaps.
 *
 * The items with a duration are clustered and given columns; a point item takes the first column, alone, and never
 * joins a cluster, so a deadline inside a meeting neither widens it nor splits it from what it overlaps.
 *
 * @param {Array} entries The day's hour items (none all-day, none a bar).
 * @returns {Array} The laid items, in the column's order.
 */
export const columns = (entries: Placed[]): Laid[] => {
    const sorted = [...entries].sort(byStart);
    const laid: Laid[] = [];
    let cluster: Laid[] = [];
    let ends: number[] = [];
    let clusterend = -Infinity;
    /**
     * Give every item of the cluster just closed the cluster's column count.
     *
     * @returns {void}
     */
    const close = (): void => {
        cluster.forEach((entry) => {
            entry.columns = ends.length;
        });
        cluster = [];
        ends = [];
    };
    sorted.forEach((entry) => {
        if (entry.item.timeduration <= 0) {
            laid.push({entry: entry, column: 0, columns: 1, overlaps: []});
            return;
        }
        const start = entry.item.timestart;
        const end = start + entry.item.timeduration;
        if (start >= clusterend) {
            close();
        }
        let column = ends.findIndex((last) => last <= start);
        if (column < 0) {
            column = ends.length;
            ends.push(end);
        } else {
            ends[column] = end;
        }
        const placed: Laid = {
            entry: entry,
            column: column,
            columns: 1,
            overlaps: sorted.filter((other) => other !== entry && intersects(entry.item, other.item)),
        };
        cluster.push(placed);
        laid.push(placed);
        clusterend = Math.max(clusterend, end);
    });
    close();
    return laid;
};

/**
 * A day's content: its all-day strip and its hour column.
 *
 * @param {string} iso The day, YYYY-MM-DD.
 * @param {Array} placed Every placed item of the answer.
 * @param {object} zone The viewer's timezone reader.
 * @returns {object} The day's plan.
 */
export const dayPlan = (iso: string, placed: Placed[], zone: Zone): DayPlan => {
    const allday: Placed[] = [];
    const hours: Placed[] = [];
    placed.forEach((entry) => {
        if (entry.first > iso || entry.last < iso) {
            return;
        }
        if (entry.bar || isAllDay(entry.item, zone)) {
            allday.push(entry);
        } else {
            hours.push(entry);
        }
    });
    allday.sort(byStart);
    return {allday: allday, hours: columns(hours)};
};

/** The least hours of a busy band. */
export const MIN_WINDOW = 4;

/** The hours of the column: [from, to) in whole hours of the day. */
export type HourWindow = {from: number, to: number};

/** The hours an hour column draws, the drawer's and the week's: the whole day. */
export const FULL_DAY: HourWindow = {from: 0, to: 24};

/**
 * The band shown first on a day (or a week) with nothing timed on it: the working day, 08:00 to 18:00, where a
 * double-click is most useful.
 */
export const EMPTY_WINDOW: HourWindow = {from: 8, to: 18};

/**
 * A day's busy band, shown first when the hour column opens (its scroll position, boxTopOf()): from an hour before
 * the earliest start to an hour after the latest end, at least MIN_WINDOW hours, inside 0 to 24. A point item counts
 * with its start; an item running past midnight ends the day. Pure.
 *
 * @param {Array} laid The day's laid hour items.
 * @param {object} zone The viewer's timezone reader.
 * @returns {object} {from, to} in whole hours.
 */
export const hourWindow = (laid: Laid[], zone: Zone): HourWindow => {
    if (!laid.length) {
        return {from: 0, to: 24};
    }
    let earliest = 1440;
    let latest = 0;
    laid.forEach((one) => {
        const start = zone.minutes(one.entry.item.timestart);
        earliest = Math.min(earliest, start);
        latest = Math.max(latest, Math.min(1440, start + Math.max(one.entry.item.timeduration / 60, 0)));
    });
    let from = Math.max(0, Math.floor(earliest / 60) - 1);
    let to = Math.min(24, Math.ceil(latest / 60) + 1);
    if (to - from < MIN_WINDOW) {
        to = Math.min(24, from + MIN_WINDOW);
        from = Math.max(0, to - MIN_WINDOW);
    }
    return {from: from, to: to};
};

/**
 * The items an item overlaps on its day: none for an all-day, multi-day or point item.
 *
 * @param {object} entry The placed item.
 * @param {Array} placed Every placed item of the answer.
 * @param {object} zone The viewer's timezone reader.
 * @returns {Array} The placed items it overlaps.
 */
export const overlapsOf = (entry: Placed, placed: Placed[], zone: Zone): Placed[] => {
    const key = itemKey(entry.item);
    const laid = dayPlan(entry.first, placed, zone).hours.find((other) => itemKey(other.entry.item) === key);
    return laid ? laid.overlaps : [];
};

/**
 * Where an hour column's scroll box starts. A busy band's first hour goes to the box's top,
 * so the earliest item, an hour below it, is never cut; the empty day's band is centred in the box, never with its
 * first hour above the box's top when the box is shorter than the band. The browser clamps what is past the end. Pure:
 * the row's height and the box's are read once by the caller, when the day opens.
 *
 * @param {object} band The band to show.
 * @param {boolean} busy Whether it is the band of the day's items (else the working hours of an empty day).
 * @param {number} row One hour's height, in px.
 * @param {number} viewport The box's visible height, in px.
 * @returns {number} The scrollTop, in px.
 */
export const boxTopOf = (band: HourWindow, busy: boolean, row: number, viewport: number): number => {
    if (!(row > 0)) {
        return 0;
    }
    const first = band.from * row;
    if (busy) {
        return first;
    }
    const centred = ((band.from + band.to) / 2) * row - viewport / 2;
    return Math.max(0, Math.min(centred, first));
};

/** Which ends of a scroll box can still be scrolled to: a fade is drawn over each. */
export type Edges = {up: boolean, down: boolean};

/**
 * Which ways a scroll box can still scroll, with a pixel of slack for a fractional scroll position. Pure.
 *
 * @param {number} top Its scrollTop.
 * @param {number} total Its scrollHeight.
 * @param {number} viewport Its clientHeight.
 * @returns {object} {up, down}.
 */
export const edgesOf = (top: number, total: number, viewport: number): Edges => ({
    up: top > 1,
    down: top + viewport < total - 1,
});
