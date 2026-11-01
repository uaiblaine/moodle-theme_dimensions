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
 * Tests for the calendar's data: the address round trip, the two seams, the grid's days.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {gridWeeks, monthAfter} from '../../src/calendar/days';
import {
    DEFAULT_STATE, address, dayHref, emptyAnswer, itemHref, monthHref, normalise, query, rangeArgs, readAddress,
} from '../../src/calendar/model';
import type {State} from '../../src/calendar/model';
import {fill} from '../../src/calendar/strings';
import {item} from './fixture';

const PAGE = 'http://localhost/r.php/theme_dimensions/calendar';

describe('the address', () => {
    it('a bare address names no state', () => {
        expect(readAddress(`${PAGE}?month=2026-11`)).toEqual({month: '2026-11', state: null});
    });

    it('reads back the state it was written from', () => {
        const state: State = {
            off: ['group', 'open'],
            categoryid: 7,
            recursive: false,
            model: [2],
            modality: [1, 3],
            city: 'Recife',
            actiononly: true,
            colour: 'category',
        };
        const read = readAddress(address(PAGE, '2026-12', state));
        expect(read).toEqual({month: '2026-12', state: normalise(state)});
    });

    it('names every key off with one empty member, as the server reads it', () => {
        const state = {...DEFAULT_STATE, off: [...['site', 'category', 'course', 'group', 'user', 'activity', 'other', 'open']]} as State;
        expect(query(state).filter(([name]) => name === 'keys[]')).toEqual([['keys[]', '']]);
        expect(readAddress(address(PAGE, '2026-11', state)).state?.off).toHaveLength(8);
    });

    it('lists no key while every key is on, and the default state still names itself', () => {
        expect(query(DEFAULT_STATE)).toEqual([['recursive', '1'], ['colour', 'key']]);
    });

    it('drops a malformed month', () => {
        expect(readAddress(`${PAGE}?month=2026-13`).month).toBeNull();
    });
});

describe('the seams', () => {
    it('an item opens its own page, else its course\'s, else the home', () => {
        const courses = new Map([[4, {url: 'https://example.com/course/view.php?id=4'} as never]]);
        expect(itemHref(item({id: 1, url: 'https://example.com/mod'}), courses, 'https://example.com/')).toBe('https://example.com/mod');
        expect(itemHref(item({id: 1, url: '', courseid: 4}), courses, 'https://example.com/'))
            .toBe('https://example.com/course/view.php?id=4');
        expect(itemHref(item({id: 1, url: '', courseid: 9}), courses, 'https://example.com/')).toBe('https://example.com/');
    });

    it('a day opens core\'s day view by its day, month and year, with no timestamp', () => {
        const url = new URL(dayHref('http://localhost/calendar/view.php', '2026-11-07'));
        expect(Object.fromEntries(url.searchParams)).toEqual({view: 'day', 'cal_d': '7', 'cal_m': '11', 'cal_y': '2026'});
        expect(new URL(monthHref('http://localhost/calendar/view.php', '2026-02')).searchParams.get('cal_m')).toBe('2');
    });
});

describe('the range\'s arguments', () => {
    it('ask for the keys that are on and the window', () => {
        const args = rangeArgs('2026-11-01', '2026-12-05', {...DEFAULT_STATE, off: ['site']});
        expect(args.keys).toEqual(['category', 'course', 'group', 'user', 'activity', 'other', 'open']);
        expect([args.from, args.to]).toEqual(['2026-11-01', '2026-12-05']);
    });

    it('every key off answers the window\'s days at zero', () => {
        const empty = emptyAnswer(['2026-11-01', '2026-11-02']);
        expect(empty.events).toEqual([]);
        expect(empty.days.map((day) => day.counts.site)).toEqual([0, 0]);
    });
});

describe('the grid', () => {
    it('fills whole weeks with the neighbouring months\' days', () => {
        const weeks = gridWeeks('2026-11', 0);
        expect(weeks).toHaveLength(5);
        expect(weeks[0][0]).toBe('2026-11-01');
        expect(weeks[4][6]).toBe('2026-12-05');
        const monday = gridWeeks('2026-11', 1);
        expect(monday[0][0]).toBe('2026-10-26');
        expect(monday[monday.length - 1][6]).toBe('2026-12-06');
    });

    it('moves months across a year', () => {
        expect(monthAfter('2026-12', 1)).toBe('2027-01');
        expect(monthAfter('2026-01', -1)).toBe('2025-12');
    });
});

describe('fill', () => {
    it('fills core\'s placeholders', () => {
        expect(fill('{$a} items', 3)).toBe('3 items');
        expect(fill('{$a->name}, {$a->span}', {name: 'A', span: 'B'})).toBe('A, B');
    });
});
