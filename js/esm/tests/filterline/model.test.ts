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
 * Tests for what the filter line reads off the server's line and how what it is told moves it.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {answered, read, scopeAfter, stated} from '../../src/filterline/model';
import type {Line} from '../../src/filterline/model';
import {drawPage} from './page';

afterEach(() => {
    document.body.replaceChildren();
});

/**
 * The example's line, read.
 *
 * @returns {object}
 */
const example = (): Line => read(drawPage().line) as Line;

describe('theme_dimensions/filterline/model', () => {
    it('reads every word, name, count and state the server drew', () => {
        const model = example();
        expect(model.scope?.name).toBe('chip');
        expect(model.scope?.legend).toBe('Status');
        expect(model.scope?.cells.map((cell) => [cell.value, cell.label, cell.count, cell.checked, cell.absent])).toEqual([
            ['', 'All', '14', false, false],
            ['enrolled', 'My courses', '3', false, false],
            ['open', 'Open', '0', false, true],
        ]);
        expect(model.search).toEqual({
            id: 'dim-vitrine-q-7',
            name: 'q',
            label: 'Search courses',
            placeholder: 'Search by name',
            value: 'rescue',
            clearlabel: 'Clear text',
        });
        expect(model.star).toEqual({name: 'fav', pressed: false, count: '2', label: 'Favourites'});
        expect(model.filters).toEqual({label: 'Filters', count: '3', countlabel: 'Applied filters: 3', hascount: true});
        expect(model.views).toEqual([
            {value: 'list', label: 'List', icon: 'fa-list', pressed: false},
            {value: 'cards', label: 'Cards', icon: 'fa-border-all', pressed: true},
        ]);
        expect([model.viewname, model.viewlabel, model.organiselabel, model.openlabel, model.closelabel, model.manage])
            .toEqual(['view', 'View', 'Organise', 'Search courses', 'Close search', true]);
        expect(model.suggest).toEqual({id: 'dim-vitrine-suggest-7', filters: 'Filters', start: 'Start with a filter'});
    });

    it('reads what the reader did on the server\'s line before the mount', () => {
        const {line} = drawPage();
        (line.querySelector('input[type="search"]') as HTMLInputElement).value = 'fire';
        (line.querySelector('#dim-chip-7-enrolled') as HTMLInputElement).checked = true;
        const model = read(line) as Line;
        expect(model.search.value).toBe('fire');
        expect(model.scope?.cells.find((cell) => cell.checked)?.value).toBe('enrolled');
    });

    it('reads no line without its field or its listbox, as categoryline arms none', () => {
        const {line} = drawPage();
        line.querySelector('[data-region="dim-line-suggest"]')?.remove();
        expect(read(line)).toBeNull();
        expect(read(null)).toBeNull();
    });

    it('takes an answer\'s scope: counts and states, a cell it does not offer hidden, the order kept', () => {
        const model = example();
        const cells = scopeAfter(model.scope?.cells || [], [
            {id: 'dim-chip-7-all', value: '', label: 'All', count: 20, hascount: true, checked: false},
            {id: 'dim-chip-7-open', value: 'open', label: 'Open', count: 4, hascount: true, checked: true},
        ]);
        expect(cells.map((cell) => [cell.value, cell.count, cell.checked, cell.absent])).toEqual([
            ['', '20', false, false],
            ['enrolled', '3', false, true],
            ['open', '4', true, false],
        ]);
    });

    it('leaves the scope as drawn for an answer with no option, and a star the answer does not offer', () => {
        const model = example();
        const next = answered(model, {scope: null, favourites: {available: false, pressed: true, count: 9}});
        expect(next.scope).toBe(model.scope);
        expect(next.star).toBe(model.star);
        const pressed = answered(model, {favourites: {available: true, pressed: true, count: 5}, filtercount: 0});
        expect(pressed.star).toEqual({name: 'fav', pressed: true, count: '5', label: 'Favourites'});
        expect(pressed.filters).toEqual({label: 'Filters', count: '0', countlabel: '', hascount: false});
    });

    it('takes the listing\'s state: the term, the scope\'s choice, the star, the view', () => {
        const model = stated(example(), {query: '', chip: 'enrolled', fav: true, view: 'list'});
        expect(model.search.value).toBe('');
        expect(model.scope?.cells.filter((cell) => cell.checked).map((cell) => cell.value)).toEqual(['enrolled']);
        expect(model.star?.pressed).toBe(true);
        expect(model.views.filter((view) => view.pressed).map((view) => view.value)).toEqual(['list']);
        // A choice of "More statuses" checks no cell of the scope.
        expect(stated(model, {chip: null}).scope?.cells.some((cell) => cell.checked)).toBe(false);
        expect(stated(model, {}).scope).toBe(model.scope);
    });
});
