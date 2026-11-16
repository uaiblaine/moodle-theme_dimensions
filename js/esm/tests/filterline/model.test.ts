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
        expect(model.filters).toEqual({label: 'Filters', count: '2', countlabel: 'Applied filters: 2', hascount: true, available: true});
        expect(model.date).toEqual({
            label: 'Start date',
            name: 'Start date: 17 September 2026 to 24 September 2026',
            template: 'Start date: {period}',
            set: true,
        });
        expect(model.views).toEqual([
            {value: 'list', label: 'List', icon: 'fa-list', checked: false},
            {value: 'cards', label: 'Cards', icon: 'fa-border-all', checked: true},
        ]);
        expect([model.viewname, model.viewlabel, model.organiselabel, model.openlabel, model.closelabel, model.manage])
            .toEqual(['view', 'View', 'Organise', 'Search courses', 'Close search', true]);
        expect([model.viewheading, model.viewheadid]).toEqual(['Show as', 'dim-view-7-head']);
        expect(model.suggest).toEqual({id: 'dim-vitrine-suggest-7', filters: 'Filters', start: 'Start with a filter'});
        // The result count on the bar, the count sentence's number.
        expect(model.total).toBe('16');
    });

    it('takes the labels of the icon-only controls from their tooltip, and the result count from an answer', () => {
        const {line} = drawPage();
        // A title is not where a label is read from: the controls carry none.
        (line.querySelector('[data-region="dim-line-star"]') as HTMLElement).setAttribute('title', 'Wrong');
        const model = read(line) as Line;
        expect(model.star?.label).toBe('Favourites');
        expect(answered(model, {total: '1.234'}).total).toBe('1.234');
        expect(answered(model, {filtercount: 0}).total).toBe('16');
    });

    it('reads what the reader did on the server\'s line before the mount', () => {
        const {line} = drawPage();
        (line.querySelector('input[type="search"]') as HTMLInputElement).value = 'fire';
        (line.querySelector('#dim-chip-7-enrolled') as HTMLInputElement).checked = true;
        const model = read(line) as Line;
        expect(model.search.value).toBe('fire');
        expect(model.scope?.cells.find((cell) => cell.checked)?.value).toBe('enrolled');
    });

    it('reads no line without its field or its listbox, and arms none', () => {
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
        expect(pressed.filters).toEqual({label: 'Filters', count: '0', countlabel: '', hascount: false, available: true});
    });

    it('takes an answer\'s period into the date button\'s name and dot, and leaves it to an answer silent on it', () => {
        const model = example();
        const none = answered(model, {period: ''});
        expect(none.date).toEqual(Object.assign({}, model.date, {name: 'Start date', set: false}));
        const from = answered(none, {period: 'From 1 October 2026'});
        expect(from.date?.name).toBe('Start date: From 1 October 2026');
        expect(from.date?.set).toBe(true);
        expect(answered(from, {total: '4'}).date).toEqual(from.date);
        // A line the server drew without the button has none to name.
        expect(answered(Object.assign({}, model, {date: null}), {period: 'From 1 October 2026'}).date).toBeNull();
    });

    it('takes the listing\'s state: the term, the scope\'s choice, the star, the view', () => {
        const model = stated(example(), {query: '', chip: 'enrolled', fav: true, view: 'list'});
        expect(model.search.value).toBe('');
        expect(model.scope?.cells.filter((cell) => cell.checked).map((cell) => cell.value)).toEqual(['enrolled']);
        expect(model.star?.pressed).toBe(true);
        expect(model.views.filter((view) => view.checked).map((view) => view.value)).toEqual(['list']);
        // A choice of "More statuses" checks no cell of the scope.
        expect(stated(model, {chip: null}).scope?.cells.some((cell) => cell.checked)).toBe(false);
        expect(stated(model, {}).scope).toBe(model.scope);
    });
});
