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
 * Tests for the filter line's suggestions: the order, and the filters a page offers.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {MATCH_COUNT, START_COUNT, matchOf, offers, rankOf, remember, suggest} from '../../src/filterline/suggest';
import type {Rest} from '../../src/filterline/suggest';
import {drawPage} from './page';

afterEach(() => {
    document.body.replaceChildren();
});

/**
 * A filter for the pure rules.
 *
 * @param {string} label Its words.
 * @param {string} group Its group's words.
 * @param {number|null} count Its count.
 * @param {number} order Its place on the page.
 * @returns {object}
 */
const item = (label: string, group: string, count: number | null, order: number) => ({label, group, count, order});

describe('theme_dimensions/filterline/suggest', () => {
    it('tells a word start from a match inside a word', () => {
        expect(matchOf('in person', 'pers')).toBe(0);
        expect(matchOf('in-person', 'on')).toBe(1);
        expect(matchOf('online', 'x')).toBe(-1);
        expect(matchOf('person on', 'on')).toBe(0);
    });

    it('ranks the label\'s word start, then the group\'s, then inside the label, then inside the group', () => {
        expect(rankOf({label: 'Online', group: 'Modality'}, 'on')).toBe(0);
        expect(rankOf({label: 'Remote', group: 'Online modes'}, 'on')).toBe(1);
        expect(rankOf({label: 'In-person', group: 'Mode'}, 'on')).toBe(2);
        expect(rankOf({label: 'Hybrid', group: 'Mention'}, 'on')).toBe(3);
        expect(rankOf({label: 'Hybrid', group: 'Mode'}, 'on')).toBe(-1);
    });

    it('matches accent- and case-insensitively on both sides', () => {
        const items = [item('Educação', 'Área', 4, 0), item('Saúde', 'Área', 2, 1)];
        expect(suggest(items, 'EDUCA').map((found) => found.label)).toEqual(['Educação']);
        expect(suggest(items, 'saude').map((found) => found.label)).toEqual(['Saúde']);
        expect(suggest(items, 'área')).toHaveLength(2);
    });

    it('offers the highest counts to start with, under the floor, an uncounted filter last', () => {
        const items = [
            item('A', '', null, 0), item('B', '', 2, 1), item('C', '', 9, 2), item('D', '', 2, 3),
            item('E', '', 1, 4), item('F', '', 7, 5), item('G', '', 3, 6),
        ];
        expect(suggest(items, '').map((found) => found.label)).toEqual(['C', 'F', 'G', 'B', 'D']);
        expect(suggest(items, 'c')).toHaveLength(START_COUNT);
    });

    it('orders a typed text by rank, then count, then the page\'s order, and caps it', () => {
        const items = [item('Rescue at sea', '', 1, 0), item('Sea rescue', '', 5, 1), item('Prescue', '', 9, 2)];
        expect(suggest(items, 'rescue').map((found) => found.label)).toEqual(['Sea rescue', 'Rescue at sea', 'Prescue']);
        const many = Array.from({length: 12}, (_, index) => item(`Fire ${index}`, '', index, index));
        expect(suggest(many, 'fire')).toHaveLength(MATCH_COUNT);
    });

    it('offers what the page may still narrow by, and nothing in force, hidden, empty or "All"', () => {
        const {form} = drawPage();
        const found = offers(form, new WeakMap());
        expect(found.map((offer) => [offer.label, offer.group, offer.count])).toEqual([
            ['My courses', 'Status', 3],
            ['In-person', 'Modality', 5],
            ['Favourites', '', 2],
            ['Today', 'Start date', null],
        ]);
        expect(found[2].control.dataset.region).toBe('dim-line-star');
    });

    it('offers the counts of the last answer given while the field held no term', () => {
        const {form} = drawPage();
        const field = form.querySelector<HTMLInputElement>('input[type="search"]') as HTMLInputElement;
        const rest = new WeakMap<Element, Rest>();
        // A term in the field: nothing is remembered.
        remember(form, field.value, rest);
        expect(rest.get(form.querySelector('#dim-chip-7-enrolled') as Element)).toBeUndefined();
        field.value = '';
        remember(form, field.value, rest);
        // A term typed since zeroes the cell's count on the page.
        field.value = 'zz';
        const count = form.querySelector('label[for="dim-chip-7-enrolled"] [data-region="dim-pill-count"]') as HTMLElement;
        count.textContent = '0';
        remember(form, field.value, rest);
        const mine = offers(form, rest).find((offer) => offer.label === 'My courses');
        expect(mine?.count).toBe(3);
        // Without the memory the zero would hide it.
        expect(offers(form, new WeakMap()).find((offer) => offer.label === 'My courses')).toBeUndefined();
    });
});
