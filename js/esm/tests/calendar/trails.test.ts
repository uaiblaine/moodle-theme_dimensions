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
 * Tests for the Trilhas view's model: where a step's bar goes on a month, the phone's "Today" line, and the address.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {zone} from '../../src/calendar/days';
import {barOf, monthDays, readTrail, todayIndex, withTrail} from '../../src/calendar/trails';
import type {TrailStep} from '../../src/calendar/trails';
import {at} from './fixture';

const utc = zone('UTC', 'en');
const march = monthDays('2027-03');

/**
 * A step of the answer's shape.
 *
 * @param {object} fields The fields that differ from a step running through March 2027.
 * @returns {object}
 */
const step = (fields: Partial<TrailStep>): TrailStep => ({
    cmid: 1,
    position: 1,
    name: 'Alfa',
    courseid: 2,
    coursename: 'Alfa',
    categoryid: 0,
    start: at('2027-03-02', 12),
    end: at('2027-03-20', 12),
    expected: false,
    state: '',
    reason: '',
    completed: 0,
    url: 'https://example.com/course/view.php?id=2',
    next: false,
    ...fields,
});

describe('monthDays', () => {
    it('lists the month\'s own days, a leap February included', () => {
        expect(march).toHaveLength(31);
        expect([march[0], march[30]]).toEqual(['2027-03-01', '2027-03-31']);
        expect(monthDays('2028-02')).toHaveLength(29);
    });
});

describe('barOf', () => {
    it('runs from the start to the end inside the month', () => {
        expect(barOf(step({}), march, utc)).toEqual({from: 1, to: 19, before: false, after: false, open: false, milestone: false});
    });

    it('clips a step that began earlier and one that ends in a later month', () => {
        const bar = barOf(step({start: at('2027-02-20', 12), end: at('2027-04-10', 12)}), march, utc);
        expect(bar).toEqual({from: 0, to: 30, before: true, after: true, open: false, milestone: false});
    });

    it('leaves a step with no end open to the month\'s edge', () => {
        expect(barOf(step({end: 0}), march, utc)).toEqual({from: 1, to: 30, before: false, after: true, open: true, milestone: false});
    });

    it('draws an expected date inside the month as a milestone, and not one past it', () => {
        expect(barOf(step({expected: true}), march, utc)?.milestone).toBe(true);
        expect(barOf(step({expected: true, end: at('2027-04-02', 12)}), march, utc)?.milestone).toBe(false);
    });

    it('draws an end alone as a milestone on its day', () => {
        expect(barOf(step({start: 0}), march, utc)).toEqual({from: 19, to: 19, before: false, after: false, open: false, milestone: true});
    });

    it('draws nothing for a step outside the month or without a date', () => {
        expect(barOf(step({start: at('2027-04-02', 12), end: at('2027-04-20', 12)}), march, utc)).toBeNull();
        expect(barOf(step({start: at('2027-01-02', 12), end: at('2027-02-20', 12)}), march, utc)).toBeNull();
        expect(barOf(step({start: 0, end: 0}), march, utc)).toBeNull();
    });

    it('reads an end before the start as the start day alone', () => {
        expect(barOf(step({start: at('2027-03-10', 12), end: at('2027-03-05', 12)}), march, utc))
            .toEqual({from: 9, to: 9, before: false, after: false, open: false, milestone: false});
    });

    it('reads the days in the viewer\'s timezone', () => {
        // 01:00 UTC on the 2nd is still the 1st in São Paulo, three hours behind.
        const late = step({start: at('2027-03-02', 1), end: at('2027-03-20', 12)});
        expect(barOf(late, march, utc)?.from).toBe(1);
        expect(barOf(late, march, zone('America/Sao_Paulo', 'en'))?.from).toBe(0);
    });
});

describe('todayIndex', () => {
    const now = at('2027-03-15', 12);

    it('is the first step not done whose end has not passed', () => {
        const steps = [
            step({cmid: 1, state: 'done', end: at('2027-03-10', 12)}),
            step({cmid: 2, end: at('2027-03-12', 12), state: 'late'}),
            step({cmid: 3, end: at('2027-03-20', 12)}),
        ];
        expect(todayIndex(steps, now)).toBe(2);
    });

    it('counts a step with no end as ahead, and is the count when every step is behind', () => {
        expect(todayIndex([step({end: 0})], now)).toBe(0);
        expect(todayIndex([step({end: at('2027-03-01', 12)})], now)).toBe(1);
        expect(todayIndex([step({state: 'done'})], now)).toBe(1);
    });
});

describe('the address', () => {
    it('writes the trail and "only mine" when set and takes them out otherwise', () => {
        const base = 'https://example.com/r.php/theme_dimensions/calendar?view=trail&month=2027-03';
        const written = withTrail(base, {trail: 12, mine: true});
        expect(new URL(written).searchParams.get('trail')).toBe('12');
        expect(new URL(written).searchParams.get('mine')).toBe('1');
        expect(new URL(withTrail(written, {trail: 0, mine: false})).search).toBe('?view=trail&month=2027-03');
        expect(new URL(withTrail(written, null)).searchParams.has('trail')).toBe(false);
    });

    it('reads them as the server does, anything else dropped', () => {
        expect(readTrail('https://example.com/?trail=12&mine=1')).toEqual({trail: 12, mine: true});
        expect(readTrail('https://example.com/?trail=-3&mine=yes')).toEqual({trail: 0, mine: false});
        expect(readTrail('https://example.com/?trail=12345678901')).toEqual({trail: 0, mine: false});
        expect(readTrail('https://example.com/')).toEqual({trail: 0, mine: false});
    });
});
