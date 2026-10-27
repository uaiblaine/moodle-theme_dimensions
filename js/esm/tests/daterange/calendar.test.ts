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
 * Tests for the period control's calendar arithmetic and its reading of a period.
 *
 * The suite runs west of UTC with daylight saving (./zone.ts), so a local-time accessor anywhere
 * in the arithmetic reads the day before the one meant, and a day added across a transition that is
 * 23 or 25 hours long shows: the control in the first test proves the zone is really in force.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    bringIntoView, format, monthAt, monthLength, onScreen, parse, period, say, shift, shiftMonth, weekdays, weeks,
} from '../../src/daterange/calendar';
import {westOfUtc} from './zone';

let restore: () => void = () => undefined;

beforeAll(() => {
    restore = westOfUtc();
});

afterAll(() => {
    restore();
});

describe('theme_dimensions/daterange/calendar', () => {
    it('runs where a local-time reading of midnight UTC is the day before', () => {
        // The control: without it every test below would pass in a UTC process whatever the code read.
        expect(new Date(Date.UTC(2026, 2, 8)).getDate()).toBe(7);
        expect(new Intl.DateTimeFormat('en', {day: 'numeric'}).format(new Date(Date.UTC(2026, 2, 8)))).toBe('7');
    });

    it('reads a date the way the server does, refusing a day its month does not have', () => {
        expect(parse('2026-02-28')).toEqual({y: 2026, m: 2, d: 28});
        expect(parse('2028-02-29')).toEqual({y: 2028, m: 2, d: 29});
        expect(parse('2026-02-29')).toBeNull();
        expect(parse('2026-09-31')).toBeNull();
        expect(parse('2026-13-01')).toBeNull();
        expect(parse('2026-00-10')).toBeNull();
        expect(parse('2026-1-01')).toBeNull();
        expect(parse('')).toBeNull();
        expect(parse(undefined)).toBeNull();
        expect(format({y: 2026, m: 3, d: 8})).toBe('2026-03-08');
        expect(monthLength(2026, 2)).toBe(28);
        expect(monthLength(2026, 12)).toBe(31);
    });

    it('reads a period as criteria does: a bound that is no date dropped, a pair given backwards put in order', () => {
        expect(period('2026-03-10', '2026-03-01')).toEqual({from: '2026-03-01', to: '2026-03-10'});
        expect(period('2026-03-01', '2026-03-10')).toEqual({from: '2026-03-01', to: '2026-03-10'});
        expect(period('2026-09-31', '2026-03-10')).toEqual({from: '', to: '2026-03-10'});
        expect(period('2026-03-10', 'tomorrow')).toEqual({from: '2026-03-10', to: ''});
        expect(period(null, undefined)).toEqual({from: '', to: ''});
    });

    it('adds whole days across a daylight saving transition and a year boundary', () => {
        // The United States move their clocks on 2026-03-08 and 2026-11-01: those days are 23 and 25 hours long.
        expect(shift('2026-03-07', 1)).toBe('2026-03-08');
        expect(shift('2026-03-08', 1)).toBe('2026-03-09');
        expect(shift('2026-03-05', 7)).toBe('2026-03-12');
        expect(shift('2026-11-01', 1)).toBe('2026-11-02');
        expect(shift('2026-11-02', -1)).toBe('2026-11-01');
        expect(shift('2026-12-31', 1)).toBe('2027-01-01');
        expect(shift('2027-01-01', -1)).toBe('2026-12-31');
        expect(shift('nothing', 1)).toBe('');
    });

    it('adds whole months clamped to the month\'s length', () => {
        expect(shiftMonth('2026-01-31', 1)).toBe('2026-02-28');
        expect(shiftMonth('2026-03-31', -1)).toBe('2026-02-28');
        expect(shiftMonth('2026-12-15', 1)).toBe('2027-01-15');
        expect(shiftMonth('2027-01-15', -1)).toBe('2026-12-15');
        expect(shiftMonth('2026-09-31', 1)).toBe('');
        expect(monthAt({y: 2026, m: 12}, 1)).toEqual({y: 2027, m: 1});
    });

    it('names a day in UTC, the zone its Date was built in', () => {
        expect(say('2026-03-08', {dateStyle: 'long'}, 'en')).toBe('March 8, 2026');
        expect(say('2026-01-04', {weekday: 'long'}, 'en')).toBe('Sunday');
        expect(say('nothing', {dateStyle: 'long'}, 'en')).toBe('');
    });

    it('begins the week on the site\'s weekday and lines the first day up under its name', () => {
        expect(weekdays(0).map((day) => say(day, {weekday: 'short'}, 'en'))).toEqual(
            ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
        );
        expect(say(weekdays(1)[0], {weekday: 'long'}, 'en')).toBe('Monday');
        expect(say(weekdays(6)[0], {weekday: 'long'}, 'en')).toBe('Saturday');
        // March 2026 begins on a Sunday: no blank on a Sunday week, six on a Monday one.
        const sunday = weeks({y: 2026, m: 3}, 0);
        expect(sunday[0][0]).toBe('2026-03-01');
        const monday = weeks({y: 2026, m: 3}, 1);
        expect(monday[0].slice(0, 6)).toEqual(['', '', '', '', '', '']);
        expect(monday[0][6]).toBe('2026-03-01');
        // Every row is a week, the last one filled with blanks.
        monday.forEach((row) => expect(row).toHaveLength(7));
        expect(monday.flat().filter((day) => day !== '')).toHaveLength(31);
        expect(monday[monday.length - 1].slice(-3)).toEqual(['', '', '']);
    });

    it('moves the months on screen as little as a day needs', () => {
        const march = {y: 2026, m: 3};
        expect(onScreen(march, 2, '2026-04-30')).toBe(true);
        expect(onScreen(march, 1, '2026-04-01')).toBe(false);
        expect(bringIntoView(march, 2, '2026-04-15')).toBe(march);
        // A day before the months opens them on its month; one after them ends them on it.
        expect(bringIntoView(march, 2, '2026-02-10')).toEqual({y: 2026, m: 2});
        expect(bringIntoView(march, 2, '2026-05-10')).toEqual({y: 2026, m: 4});
        expect(bringIntoView(march, 1, '2026-05-10')).toEqual({y: 2026, m: 5});
        expect(bringIntoView(march, 1, 'nothing')).toBe(march);
    });
});
