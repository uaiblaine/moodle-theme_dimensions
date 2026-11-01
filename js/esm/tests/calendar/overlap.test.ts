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
 * Tests for a day's layout (PROPOSAL.md section 13.2): the columns, the clusters, the boundaries of an overlap.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {zone} from '../../src/calendar/days';
import {place} from '../../src/calendar/layout';
import {columns, dayPlan, hourWindow, intersects, isAllDay, overlapsOf} from '../../src/calendar/overlap';
import {at, item} from './fixture';

const UTC = zone('UTC', 'en');
const DAY = '2026-11-17';
const HOUR = 3600;

/**
 * An hour item on the day.
 *
 * @param {number} id The id.
 * @param {number} hour The start hour, UTC.
 * @param {number} hours The duration in hours (0 for a point).
 * @returns {object} The placed item.
 */
const at17 = (id: number, hour: number, hours: number) =>
    place([item({id: id, name: `Item ${id}`, timestart: at(DAY, 0) + hour * HOUR, timeduration: hours * HOUR})], UTC)[0];

/**
 * The laid items as [id, column, columns, overlapped ids].
 *
 * @param {Array} entries The placed items.
 * @returns {Array}
 */
const shape = (entries: ReturnType<typeof at17>[]) => columns(entries).map((laid) => [
    laid.entry.item.id, laid.column, laid.columns, laid.overlaps.map((other) => other.item.id),
]);

describe('the overlap rule', () => {
    it('two items overlap when each starts before the other ends', () => {
        expect(intersects(at17(1, 9, 2).item, at17(2, 10, 2).item)).toBe(true);
        expect(intersects(at17(1, 9, 3).item, at17(2, 10, 1).item)).toBe(true);
    });

    it('touching ends do not overlap', () => {
        expect(intersects(at17(1, 9, 1).item, at17(2, 10, 1).item)).toBe(false);
        expect(intersects(at17(2, 10, 1).item, at17(1, 9, 1).item)).toBe(false);
    });

    it('a point item never overlaps, not even inside another item', () => {
        expect(intersects(at17(1, 9, 2).item, at17(2, 10, 0).item)).toBe(false);
        expect(intersects(at17(2, 10, 0).item, at17(3, 10, 0).item)).toBe(false);
    });
});

describe('the columns', () => {
    it('lays apart items alone, each in one full-width column', () => {
        expect(shape([at17(1, 9, 1), at17(2, 14, 1)])).toEqual([[1, 0, 1, []], [2, 0, 1, []]]);
    });

    it('gives a cluster of overlapping items one column count, the longer first', () => {
        expect(shape([at17(2, 9, 1), at17(1, 9, 3), at17(3, 9.5, 1)])).toEqual([
            [1, 0, 3, [2, 3]],
            [2, 1, 3, [1, 3]],
            [3, 2, 3, [1, 2]],
        ]);
    });

    it('reuses a column whose last item ended at or before the start: touching items share it', () => {
        expect(shape([at17(1, 9, 3), at17(2, 9, 1), at17(3, 10, 1)])).toEqual([
            [1, 0, 2, [2, 3]],
            [2, 1, 2, [1]],
            [3, 1, 2, [1]],
        ]);
    });

    it('starts a new cluster at a touching end, so an item alone keeps its whole width', () => {
        expect(shape([at17(1, 9, 1), at17(2, 10, 1), at17(3, 10, 1)])).toEqual([
            [1, 0, 1, []],
            [2, 0, 2, [3]],
            [3, 1, 2, [2]],
        ]);
    });

    it('keeps a point item out of the clusters: it neither widens nor splits what it sits in', () => {
        expect(shape([at17(1, 9, 2), at17(2, 10, 0), at17(3, 10.5, 1)])).toEqual([
            [1, 0, 2, [3]],
            [2, 0, 1, []],
            [3, 1, 2, [1]],
        ]);
    });
});

describe('a day', () => {
    it('puts whole-day, multi-day and event-course items in the all-day strip, never among the overlaps', () => {
        const placed = place([
            item({id: 1, name: 'Holiday', timestart: at(DAY), timeduration: 86400}),
            item({id: 2, name: 'Week', timestart: at('2026-11-16', 9), timeduration: 3 * 86400}),
            item({id: 3, name: 'Seminar', source: 'course', key: 'open', timestart: at(DAY, 9), timeduration: HOUR}),
            item({id: 4, name: 'Meeting', timestart: at(DAY, 9), timeduration: 2 * HOUR}),
            item({id: 5, name: 'Deadline', timestart: at(DAY, 23), timeduration: 0}),
        ], UTC);
        const plan = dayPlan(DAY, placed, UTC);
        expect(plan.allday.map((entry) => entry.item.id).sort()).toEqual([1, 2, 3]);
        expect(plan.hours.map((laid) => [laid.entry.item.id, laid.overlaps.length])).toEqual([[4, 0], [5, 0]]);
        expect(overlapsOf(placed[3], placed, UTC)).toEqual([]);
        expect(overlapsOf(placed[0], placed, UTC)).toEqual([]);
    });

    it('answers per item the others it overlaps', () => {
        const placed = place([
            item({id: 1, name: 'Mentoring', timestart: at(DAY, 9), timeduration: 2 * HOUR}),
            item({id: 2, name: 'Office hours', timestart: at(DAY, 10), timeduration: HOUR}),
            item({id: 3, name: 'Lunch', timestart: at(DAY, 11), timeduration: HOUR}),
        ], UTC);
        expect(overlapsOf(placed[0], placed, UTC).map((entry) => entry.item.name)).toEqual(['Office hours']);
        expect(overlapsOf(placed[2], placed, UTC)).toEqual([]);
    });

    it('reads a whole day in the viewer\'s timezone, not in UTC', () => {
        const recife = zone('America/Recife', 'en');
        const midnight = at(DAY, 3);
        expect(isAllDay(item({id: 1, timestart: midnight, timeduration: 86400}), recife)).toBe(true);
        expect(isAllDay(item({id: 1, timestart: midnight, timeduration: 86400}), UTC)).toBe(false);
        expect(isAllDay(item({id: 1, timestart: midnight, timeduration: 0}), recife)).toBe(false);
    });

    it('lists nothing on an empty day', () => {
        expect(dayPlan(DAY, [], UTC)).toEqual({allday: [], hours: []});
    });
});

describe('the hour window', () => {
    const windowOf = (...items: ReturnType<typeof at17>[]) => hourWindow(columns(items), UTC);

    it('pads an hour either side of the busy hours', () => {
        expect(windowOf(at17(1, 10, 2), at17(2, 11, 3))).toEqual({from: 9, to: 15});
    });

    it('spans at least four hours, the end extended first and then the start', () => {
        expect(windowOf(at17(1, 10, 1))).toEqual({from: 9, to: 13});
        expect(windowOf(at17(1, 22, 1))).toEqual({from: 20, to: 24});
    });

    it('stops at the edges of the day', () => {
        expect(windowOf(at17(1, 0, 1))).toEqual({from: 0, to: 4});
        expect(windowOf(at17(1, 23, 1))).toEqual({from: 20, to: 24});
    });

    it('counts a point item with its start', () => {
        expect(windowOf(at17(1, 14, 0))).toEqual({from: 13, to: 17});
    });

    it('ends an item crossing midnight at the day\'s end', () => {
        expect(windowOf(at17(1, 20, 8))).toEqual({from: 19, to: 24});
    });

    it('is the whole day for no item', () => {
        expect(hourWindow([], UTC)).toEqual({from: 0, to: 24});
    });
});
