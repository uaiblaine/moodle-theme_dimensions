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
 * The public calendar's two halves: the halves asked for the keys on, their addresses and their merge.
 *
 * The visitor's answer is two halves that share no item, each a complete answer of one key under an address of its own
 * that a CDN may keep: "events" answers the key "category" (and carries the category events' descriptions), "courses"
 * the key "open" (and carries the course dictionary). {@see merge()} is the answer of both, and must stay in step with
 * classes/local/calendar/publicrange.php merge(), which the server's first paint is: one half is returned as is; two
 * are concatenated, the items sorted by start, source and id.
 *
 * Pure: nothing here fetches, reads the page or keeps state.
 *
 * @module     theme_dimensions/calendar/publichalves
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Answer, Category, Item, Key} from './model';

/** The visitor's answer: the logged-in answer's shape, and the category events' descriptions as text. */
export type PublicAnswer = Answer & {descriptions: Array<{id: number, text: string}>};

/** A half of the answer (publiccache::HALVES). */
export type Half = 'events' | 'courses';

/** The halves, in the order the server merges them: the events' first (publiccache::HALVES). */
export const HALVES: readonly Half[] = ['events', 'courses'];

/** The key each half answers (publiccache::KEY_OF). */
export const KEY_OF: Readonly<Record<Half, Key>> = {events: 'category', courses: 'open'};

/**
 * The halves to ask for the keys on, in their order: a half whose key is off is never asked.
 *
 * @param {Array} on The keys on.
 * @returns {Array} The halves.
 */
export const halvesOn = (on: readonly Key[]): Half[] => HALVES.filter((half) => on.includes(KEY_OF[half]));

/**
 * The address of a half for a span: the prefix the page's props carry, then the span's first and last day.
 *
 * @param {string} prefix The half's address without its window (urls.events or urls.courses).
 * @param {string} from The first day, YYYY-MM-DD.
 * @param {string} to The last day, YYYY-MM-DD.
 * @returns {string} The address.
 */
export const halfUrl = (prefix: string, from: string, to: string): string => `${prefix}/${from}/${to}`;

/**
 * Two strings in byte order, as PHP's <=> compares two strings that are not numeric (the sources are ASCII names).
 *
 * @param {string} a One.
 * @param {string} b The other.
 * @returns {number} Negative, zero or positive.
 */
const byteOrder = (a: string, b: string): number => {
    if (a === b) {
        return 0;
    }
    return a < b ? -1 : 1;
};

/**
 * The merged items' order: [timestart, source, id], as publicrange::merge() sorts them.
 *
 * @param {object} a One item.
 * @param {object} b The other.
 * @returns {number} Negative, zero or positive.
 */
export const itemOrder = (a: Item, b: Item): number =>
    (a.timestart - b.timestart) || byteOrder(a.source, b.source) || (a.id - b.id);

/**
 * The answer of the halves given, as publicrange::merge() gives it: one half is its own answer; two have their items
 * concatenated and sorted ({@see itemOrder()}), their courses and descriptions concatenated in the halves' order,
 * `truncated` when either is, the categories once each by id (the later half's entry kept) in id order, nothing to
 * edit, and each day's counts summed key by key (each key comes from the one half that answers it).
 *
 * @param {Array} halves One or two halves of one window, in {@see HALVES} order.
 * @returns {object} The answer.
 */
export const merge = (halves: readonly PublicAnswer[]): PublicAnswer => {
    if (halves.length === 1) {
        return halves[0];
    }
    const categories = new Map<number, Category>();
    halves.forEach((half) => half.categories.forEach((category) => categories.set(category.id, category)));
    const first = halves[0];
    return {
        from: first.from,
        to: first.to,
        truncated: halves.some((half) => half.truncated),
        events: halves.flatMap((half) => half.events).sort(itemOrder),
        courses: halves.flatMap((half) => half.courses),
        categories: [...categories.values()].sort((a, b) => a.id - b.id),
        days: first.days.map((day, index) => {
            const counts: Record<string, number> = {};
            halves.forEach((half) => Object.entries(half.days[index]?.counts || {}).forEach(([key, count]) => {
                counts[key] = (counts[key] || 0) + count;
            }));
            return {date: day.date, counts: counts};
        }),
        editing: [],
        descriptions: halves.flatMap((half) => half.descriptions),
    };
};
