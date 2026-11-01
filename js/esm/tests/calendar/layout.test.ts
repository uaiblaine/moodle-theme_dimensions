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
 * Tests for the month grid's layout: the bars' slots, the clipping across weeks, "+N", the density per row height.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {gridWeeks, zone} from '../../src/calendar/days';
import {CARD_MIN, HEADER, PAD, ROW_DEFAULT, SLOT, barSlots, cellPlan, dayCounts, place, weekLayout} from '../../src/calendar/layout';
import {at, item} from './fixture';

const UTC = zone('UTC', 'en');
const WEEKS = gridWeeks('2026-11', 0);

describe('place', () => {
    it('makes a bar of what touches several days and of every event course, a timed item of the rest', () => {
        const placed = place([
            item({id: 1, timestart: at('2026-11-10', 9), timeduration: 3600}),
            item({id: 2, timestart: at('2026-11-10'), timeduration: 3 * 86400}),
            item({id: 3, source: 'course', key: 'course', timestart: at('2026-11-12', 9), timeduration: 3600}),
            item({id: 4, timestart: at('2026-11-10', 23), timeduration: 0}),
            item({id: 5, timestart: at('2026-11-10'), timeduration: 86400}),
        ], UTC);
        expect(placed.map((entry) => [entry.item.id, entry.first, entry.last, entry.bar])).toEqual([
            [1, '2026-11-10', '2026-11-10', false],
            [2, '2026-11-10', '2026-11-12', true],
            [3, '2026-11-12', '2026-11-12', true],
            [4, '2026-11-10', '2026-11-10', false],
            // A whole day ends on its last second: one day, not two.
            [5, '2026-11-10', '2026-11-10', false],
        ]);
    });

    it('reads the day in the viewer\'s timezone, not the browser\'s', () => {
        const late = item({id: 1, timestart: at('2026-11-10', 1), timeduration: 0});
        expect(place([late], UTC)[0].first).toBe('2026-11-10');
        expect(place([late], zone('America/Sao_Paulo', 'en'))[0].first).toBe('2026-11-09');
    });
});

describe('weekLayout', () => {
    it('gives each bar the lowest free slot, longer first at the same start, and keeps a bar in one slot', () => {
        const week = WEEKS[1];
        const placed = place([
            item({id: 1, name: 'Short', timestart: at('2026-11-09'), timeduration: 2 * 86400}),
            item({id: 2, name: 'Long', timestart: at('2026-11-09'), timeduration: 4 * 86400}),
            item({id: 3, name: 'After', timestart: at('2026-11-11'), timeduration: 2 * 86400}),
        ], UTC);
        const layout = weekLayout(week, placed, 3);
        const slots = Object.fromEntries(layout.bars.map((segment) => [segment.placed.item.name, segment.slot]));
        expect(slots).toEqual({Long: 0, Short: 1, After: 1});
        expect(layout.used).toBe(2);
    });

    it('clips a bar at each week it crosses and marks the open ends', () => {
        const placed = place([item({id: 1, timestart: at('2026-11-06'), timeduration: 10 * 86400})], UTC);
        const first = weekLayout(WEEKS[0], placed, 3).bars[0];
        const second = weekLayout(WEEKS[1], placed, 3).bars[0];
        const third = weekLayout(WEEKS[2], placed, 3).bars[0];
        expect([first.start, first.end, first.clipstart, first.clipend]).toEqual([5, 6, false, true]);
        expect([second.start, second.end, second.clipstart, second.clipend]).toEqual([0, 6, true, true]);
        expect([third.start, third.end, third.clipstart, third.clipend]).toEqual([0, 0, true, false]);
    });

    it('hides the bars past the row\'s slots and counts them on every day they cover', () => {
        const placed = place([1, 2, 3].map((id) => item({id: id, timestart: at('2026-11-09'), timeduration: 2 * 86400})), UTC);
        const layout = weekLayout(WEEKS[1], placed, 2);
        expect(layout.bars).toHaveLength(2);
        expect(layout.hidden).toEqual([0, 1, 1, 0, 0, 0, 0]);
    });

    it('puts the other items under their day, in time order', () => {
        const placed = place([
            item({id: 1, name: 'Late', timestart: at('2026-11-10', 15)}),
            item({id: 2, name: 'Early', timestart: at('2026-11-10', 8)}),
        ], UTC);
        const layout = weekLayout(WEEKS[1], placed, 3);
        expect(layout.timed[2].map((entry) => entry.item.name)).toEqual(['Early', 'Late']);
        expect(layout.bars).toEqual([]);
    });
});

describe('density', () => {
    const entries = (count: number) => place(Array.from({length: count}, (unused, index) => item({id: index + 1})), UTC);

    it('draws a card for one item in a row with room, lines for a few, a summary past what fits', () => {
        expect(cellPlan(entries(1), 0, 0, ROW_DEFAULT).density).toBe('card');
        expect(cellPlan(entries(3), 0, 0, ROW_DEFAULT).density).toBe('lines');
        const summary = cellPlan(entries(9), 0, 0, ROW_DEFAULT);
        expect(summary.density).toBe('summary');
        const fits = Math.floor((ROW_DEFAULT - HEADER - PAD) / 22);
        expect(summary.shown).toHaveLength(fits - 1);
        expect(summary.more).toBe(9 - (fits - 1));
        expect(summary.dots.length).toBeLessThanOrEqual(4);
    });

    it('turns a card into a line when the bars leave too little room', () => {
        const used = Math.ceil((ROW_DEFAULT - HEADER - PAD - CARD_MIN + 1) / SLOT);
        expect(cellPlan(entries(1), 0, used, ROW_DEFAULT).density).toBe('lines');
    });

    it('a taller row holds more lines before it summarises', () => {
        expect(cellPlan(entries(6), 0, 0, ROW_DEFAULT).density).toBe('summary');
        expect(cellPlan(entries(6), 0, 0, 260).density).toBe('lines');
    });

    it('counts a hidden bar in "+N" even under one item', () => {
        const plan = cellPlan(entries(1), 2, 3, ROW_DEFAULT);
        expect(plan.density).toBe('summary');
        expect(plan.more).toBeGreaterThanOrEqual(2);
    });

    it('draws nothing for an empty day', () => {
        expect(cellPlan([], 0, 0, ROW_DEFAULT)).toEqual({density: 'none', shown: [], more: 0, dots: []});
    });

    it('a row draws between one and three bar slots', () => {
        expect(barSlots(ROW_DEFAULT)).toBe(3);
        expect(barSlots(400)).toBe(3);
        expect(barSlots(90)).toBe(1);
    });
});

describe('dayCounts', () => {
    it('counts every item on every day it touches, bars included', () => {
        const placed = place([
            item({id: 1, timestart: at('2026-11-09'), timeduration: 3 * 86400}),
            item({id: 2, timestart: at('2026-11-10', 9)}),
        ], UTC);
        const counts = dayCounts(WEEKS.flat(), placed);
        expect([counts.get('2026-11-08'), counts.get('2026-11-09'), counts.get('2026-11-10'), counts.get('2026-11-11'),
            counts.get('2026-11-12')]).toEqual([0, 1, 2, 1, 0]);
    });
});
