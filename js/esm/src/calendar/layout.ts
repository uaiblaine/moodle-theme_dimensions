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
 * The month grid's layout, pure: the spanning bars' slots, the density of each day, the day counts.
 *
 * The rules:
 *
 * - An item that touches more than one day, and every event course, is a BAR; any other item sits in its day's
 *   content. A bar is clipped to each week row it crosses; the segments of a row are sorted by start, then longer
 *   first, then name, and each takes the lowest slot free on all its columns, so a bar keeps one slot across its
 *   row and never zigzags. Slots past the row's capacity ({@see barSlots()}) are hidden and counted per day.
 * - The density of a day comes from its row's height (measured by the grid, one ResizeObserver per grid) and from
 *   what the bars left: one item on a day with room is a CARD, a few that fit are LINES, more than fit is a SUMMARY
 *   (the lines that fit, then "+N" for the rest and the hidden bars, with a dot in its key for each of the first
 *   {@see DOTS} lines left out).
 * - The count of a day is every item touching it, bars included: the day button's label says it.
 * - On a phone the month draws a dot row per day instead ({@see dayDots()}): a dot per key with items, from the
 *   answer's per-day counts, at most {@see DOTS}, then a "+".
 *
 * @module     theme_dimensions/calendar/layout
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {KEYS} from './model';
import type {Item, Key} from './model';
import type {Zone} from './days';

/** The height of a day's number row, in px. */
export const HEADER = 30;

/** The height of one bar slot, in px. */
export const SLOT = 24;

/** The height of one line, in px. */
export const LINE = 22;

/** The padding under a day's content, in px. */
export const PAD = 8;

/** The least room a card needs, in px. */
export const CARD_MIN = 78;

/** The most bar slots a row draws, however tall it is. */
export const MAX_BARS = 3;

/** The row height the grid assumes until its first measure: the stylesheet's 9.25rem at 16px. */
export const ROW_DEFAULT = 148;

/** The most dots a summary or a phone's day draws. */
export const DOTS = 4;

/** An item with the days it touches. */
export type Placed = {item: Item, first: string, last: string, bar: boolean};

/** One bar's segment in a week row, its columns 0 to 6. */
export type Segment = {placed: Placed, start: number, end: number, clipstart: boolean, clipend: boolean, slot: number};

/** A week row's layout. */
export type WeekLayout = {
    bars: Segment[],
    hidden: number[],
    used: number,
    timed: Placed[][],
};

/** The densities of a day, 'none' for a day with nothing to draw. */
export type Density = 'card' | 'lines' | 'summary' | 'none';

/** What a day draws in its content. */
export type CellPlan = {density: Density, shown: Placed[], more: number, dots: Key[]};

/**
 * Every item with the first and last day it touches in the viewer's timezone, and whether it is a bar.
 *
 * An item of no duration touches its start day alone; otherwise its last second decides its last day, as
 * range::days() counts it.
 *
 * @param {Array} items The answer's items.
 * @param {object} zone The viewer's timezone reader.
 * @returns {Array} The placed items, in the answer's order.
 */
export const place = (items: Item[], zone: Zone): Placed[] => items.map((item) => {
    const first = zone.day(item.timestart);
    const last = zone.day(item.timestart + Math.max(0, item.timeduration - 1));
    return {item: item, first: first, last: last, bar: first !== last || item.source === 'course'};
});

/**
 * How many bar slots a row of this height draws: room for the number row, one line and the padding stays.
 *
 * @param {number} height The row's height, in px.
 * @returns {number} 1 to MAX_BARS.
 */
export const barSlots = (height: number): number =>
    Math.max(1, Math.min(MAX_BARS, Math.floor((height - HEADER - PAD - LINE) / SLOT)));

/**
 * The order of a row's segments: start, then longer first, then name, then id.
 *
 * @param {object} a A segment.
 * @param {object} b Another.
 * @returns {number}
 */
const bySpan = (a: Segment, b: Segment): number => (a.start - b.start)
    || ((b.end - b.start) - (a.end - a.start))
    || a.placed.item.name.localeCompare(b.placed.item.name)
    || (a.placed.item.id - b.placed.item.id);

/**
 * One week row: its bars in their slots, the hidden ones counted per day, and the other items per day.
 *
 * @param {Array} week The row's seven days, YYYY-MM-DD.
 * @param {Array} placed Every placed item.
 * @param {number} slots How many bar slots the row draws.
 * @returns {object} The row's layout.
 */
export const weekLayout = (week: string[], placed: Placed[], slots: number): WeekLayout => {
    const first = week[0];
    const last = week[week.length - 1];
    const segments: Segment[] = [];
    const timed: Placed[][] = week.map(() => []);
    placed.forEach((entry) => {
        if (entry.last < first || entry.first > last) {
            return;
        }
        if (!entry.bar) {
            const column = week.indexOf(entry.first);
            if (column >= 0) {
                timed[column].push(entry);
            }
            return;
        }
        const start = entry.first < first ? 0 : week.indexOf(entry.first);
        const end = entry.last > last ? week.length - 1 : week.indexOf(entry.last);
        segments.push({
            placed: entry,
            start: start,
            end: end,
            clipstart: entry.first < first,
            clipend: entry.last > last,
            slot: 0,
        });
    });
    segments.sort(bySpan);

    const taken: boolean[][] = [];
    const hidden = week.map(() => 0);
    const bars: Segment[] = [];
    let used = 0;
    segments.forEach((segment) => {
        let slot = 0;
        for (;;) {
            taken[slot] = taken[slot] || week.map(() => false);
            let free = true;
            for (let column = segment.start; column <= segment.end; column++) {
                free = free && !taken[slot][column];
            }
            if (free) {
                break;
            }
            slot++;
        }
        for (let column = segment.start; column <= segment.end; column++) {
            taken[slot][column] = true;
        }
        if (slot >= slots) {
            for (let column = segment.start; column <= segment.end; column++) {
                hidden[column]++;
            }
            return;
        }
        used = Math.max(used, slot + 1);
        bars.push({...segment, slot: slot});
    });
    timed.forEach((entries) => entries.sort((a, b) => (a.item.timestart - b.item.timestart)
        || a.item.name.localeCompare(b.item.name) || (a.item.id - b.item.id)));
    return {bars: bars, hidden: hidden, used: used, timed: timed};
};

/**
 * What one day draws, from its row's height and what the bars left of it.
 *
 * @param {Array} timed The day's own items, in order.
 * @param {number} hidden The bars hidden on the day.
 * @param {number} used The bar slots the row draws.
 * @param {number} height The row's height, in px.
 * @returns {object} The day's plan.
 */
export const cellPlan = (timed: Placed[], hidden: number, used: number, height: number): CellPlan => {
    if (!timed.length && !hidden) {
        return {density: 'none', shown: [], more: 0, dots: []};
    }
    const room = height - HEADER - used * SLOT - PAD;
    const fits = Math.max(1, Math.floor(room / LINE));
    if (timed.length === 1 && !hidden && room >= CARD_MIN) {
        return {density: 'card', shown: timed, more: 0, dots: []};
    }
    if (timed.length <= fits && !hidden) {
        return {density: 'lines', shown: timed, more: 0, dots: []};
    }
    const shown = timed.slice(0, Math.max(0, fits - 1));
    const rest = timed.slice(shown.length);
    return {
        density: 'summary',
        shown: shown,
        more: rest.length + hidden,
        dots: rest.slice(0, DOTS).map((entry) => entry.item.key),
    };
};

/**
 * How many items touch each day of the grid, bars included.
 *
 * @param {Array} days Every day of the grid, in order.
 * @param {Array} placed Every placed item.
 * @returns {Map} day => count.
 */
export const dayCounts = (days: string[], placed: Placed[]): Map<string, number> => {
    const counts = new Map<string, number>(days.map((day) => [day, 0]));
    placed.forEach((entry) => {
        days.forEach((day) => {
            if (day >= entry.first && day <= entry.last) {
                counts.set(day, (counts.get(day) || 0) + 1);
            }
        });
    });
    return counts;
};

/** A phone day's dots: one per key with items, and whether keys were left out. */
export type Dots = {keys: Key[], more: boolean};

/**
 * The dots of a day on a phone, from the answer's per-day counts by key: the keys with items in the chips' order,
 * at most DOTS of them, and "+" when more keys have items.
 *
 * @param {object|undefined} counts The day's counts by key (answer.days[].counts), or nothing for a day it lacks.
 * @returns {object} The dots.
 */
export const dayDots = (counts: Record<string, number> | undefined): Dots => {
    const keys = KEYS.filter((key) => (counts?.[key] || 0) > 0);
    return {keys: keys.slice(0, DOTS), more: keys.length > DOTS};
};
