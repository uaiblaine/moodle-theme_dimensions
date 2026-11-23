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
 * Tests for the public calendar's halves: the merge, which must give what publicrange::merge() gives, the halves asked
 * for the keys on, and a half's address.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Category, Course} from '../../src/calendar/model';
import {halfUrl, halvesOn, merge} from '../../src/calendar/publichalves';
import type {PublicAnswer} from '../../src/calendar/publichalves';
import {at, item} from './fixture';

/** Every key of the answer's day counts, at zero (keys::ALL). */
const ZERO = {site: 0, category: 0, course: 0, group: 0, user: 0, activity: 0, other: 0, open: 0};

/**
 * A category of the dictionary.
 *
 * @param {number} id The id.
 * @param {string} name The name.
 * @returns {object}
 */
const category = (id: number, name = `Category ${id}`): Category => ({id: id, name: name, bg: '#000000', ink: '#ffffff'});

/**
 * A course of the dictionary.
 *
 * @param {number} id The id.
 * @returns {object}
 */
const course = (id: number): Course => ({
    id: id, fullname: `Course ${id}`, shortname: `C${id}`, categoryid: 0, url: '', image: '', model: 2, modality: 0,
    city: '', venue: '', landingurl: '', enrolled: false, participants: null, join: 'open',
});

/**
 * A half over three days, one key counted.
 *
 * @param {object} fields What differs.
 * @param {string} key The key its days count.
 * @param {Array} counts That key's count on each day.
 * @returns {object}
 */
const half = (fields: Partial<PublicAnswer>, key: string, counts: number[]): PublicAnswer => ({
    from: '2026-11-01',
    to: '2026-11-03',
    truncated: false,
    events: [],
    courses: [],
    categories: [],
    days: ['2026-11-01', '2026-11-02', '2026-11-03'].map((date, index) => ({
        date: date,
        counts: {...ZERO, [key]: counts[index]},
    })),
    editing: [],
    descriptions: [],
    ...fields,
});

describe('merge, as publicrange::merge()', () => {
    const start = at('2026-11-02', 9);
    const events = half({
        events: [
            item({id: 7, source: 'core', key: 'category', timestart: start}),
            item({id: 3, source: 'core', key: 'category', timestart: start}),
            item({id: 1, source: 'core', key: 'category', timestart: at('2026-11-03', 9)}),
        ],
        categories: [category(5, 'Old name'), category(2)],
        descriptions: [{id: 7, text: 'Seven'}],
        truncated: true,
    }, 'category', [0, 2, 1]);
    const courses = half({
        events: [
            item({id: 4, source: 'course', key: 'open', timestart: start, courseid: 4}),
            item({id: 9, source: 'course', key: 'open', timestart: at('2026-11-01', 9), courseid: 9}),
        ],
        courses: [course(4), course(9)],
        categories: [category(5, 'New name'), category(1)],
    }, 'open', [1, 1, 0]);

    it('sorts the items by start, then source byte by byte, then id', () => {
        const merged = merge([events, courses]);
        expect(merged.events.map((entry) => `${entry.source}-${entry.id}`)).toEqual([
            'course-9', 'core-3', 'core-7', 'course-4', 'core-1',
        ]);
    });

    it('sorts a source before another byte by byte, not by locale', () => {
        const upper = half({events: [item({id: 1, source: 'b', timestart: start})]}, 'category', [0, 0, 0]);
        const lower = half({events: [item({id: 1, source: 'B', timestart: start})]}, 'open', [0, 0, 0]);
        expect(merge([upper, lower]).events.map((entry) => entry.source)).toEqual(['B', 'b']);
    });

    it('keeps each category once, the later half\'s entry, in id order', () => {
        expect(merge([events, courses]).categories).toEqual([category(1), category(2), category(5, 'New name')]);
    });

    it('sums each day\'s counts key by key, and nothing else moves', () => {
        const merged = merge([events, courses]);
        expect(merged.days).toEqual([
            {date: '2026-11-01', counts: {...ZERO, category: 0, open: 1}},
            {date: '2026-11-02', counts: {...ZERO, category: 2, open: 1}},
            {date: '2026-11-03', counts: {...ZERO, category: 1, open: 0}},
        ]);
    });

    it('is truncated when either half is, concatenates the courses and the descriptions, and edits nothing', () => {
        const merged = merge([events, courses]);
        expect(merged.truncated).toBe(true);
        expect(merge([{...events, truncated: false}, courses]).truncated).toBe(false);
        expect(merge([{...events, truncated: false}, {...courses, truncated: true}]).truncated).toBe(true);
        expect(merged.courses.map((entry) => entry.id)).toEqual([4, 9]);
        expect(merged.descriptions).toEqual([{id: 7, text: 'Seven'}]);
        expect(merged.editing).toEqual([]);
        expect([merged.from, merged.to]).toEqual(['2026-11-01', '2026-11-03']);
    });

    it('returns one half as it is', () => {
        expect(merge([events])).toBe(events);
        expect(merge([courses])).toBe(courses);
    });
});

describe('the halves asked', () => {
    it('asks only the halves whose key is on, the events\' first', () => {
        expect(halvesOn(['category', 'open'])).toEqual(['events', 'courses']);
        expect(halvesOn(['open', 'category'])).toEqual(['events', 'courses']);
        expect(halvesOn(['category'])).toEqual(['events']);
        expect(halvesOn(['open'])).toEqual(['courses']);
        expect(halvesOn([])).toEqual([]);
        expect(halvesOn(['site', 'course'])).toEqual([]);
    });

    it('completes the prefix with the span\'s first and last day', () => {
        const prefix = 'https://example.com/r.php/theme_dimensions/calendar/public/events/a1b2/pt_br';
        expect(halfUrl(prefix, '2026-11-01', '2026-12-05')).toBe(`${prefix}/2026-11-01/2026-12-05`);
    });
});
