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
 * Tests for the listing's mounts of the three components and the history writer.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {AREAS, CALENDAR, LINE, LINE_DRAWN} from '../../src/listing/events';
import {replaceKeeping, writeUrl} from '../../src/listing/history';
import {drawnByLine, focusTarget, mountAreas, mountCalendar, mountLine, tellAnswer} from '../../src/listing/mounts';
import type {ListingState} from '../../src/listing/address';

/**
 * A state at rest on category 7, with the given fields changed.
 *
 * @param {object} changes The fields to change.
 * @returns {object}
 */
const stateOf = (changes: Partial<ListingState> = {}): ListingState => ({
    categoryid: 7, page: 0, query: 'fire', chip: '', fav: false, filters: {}, sort: '', defaultsort: '', remembered: false,
    datefrom: '2026-01-01', dateto: '', cat: 0, view: 'cards', viewchosen: false, pagesize: 0, ...changes,
});


/**
 * The toolbar's markup with a line the component can draw, or a part of it left out.
 *
 * @param {string} formid The form's id attribute, or the empty string for none.
 * @param {boolean} listbox Whether the line carries its listbox.
 * @returns {HTMLElement} The listing section.
 */
const toolbar = (formid: string, listbox = true): HTMLElement => {
    document.body.innerHTML = '<section data-region="dim-vitrine"><form data-region="dim-vitrine-toolbar"'
        + (formid ? ` id="${formid}"` : '') + '><input type="hidden" name="id" value="7">'
        + '<div data-region="dim-line"><span data-region="dim-line-search"><input type="search" name="q"></span>'
        + (listbox ? '<ul data-region="dim-line-suggest"></ul>' : '') + '</div>'
        + '<fieldset data-region="dim-date" data-firstday="1" data-today="2026-10-29"><div data-region="dim-daterange"></div>'
        + '<div data-region="dim-dateinputs"><input type="date" name="from"></div></fieldset></form>'
        + '<div data-region="dim-areas"><ol><li data-region="dim-panel"></li></ol></div></section>';
    return document.querySelector<HTMLElement>('[data-region="dim-vitrine"]') as HTMLElement;
};

beforeEach(() => {
    window.history.replaceState(null, '', '/course/index.php?categoryid=7');
});

describe('theme_dimensions/listing/mounts', () => {
    it('hands the line over by its form\'s id attribute, never by the control a form names "id"', () => {
        const root = toolbar('dim-toolbar-7');
        const line = root.querySelector<HTMLElement>('[data-region="dim-line"]') as HTMLElement;
        const dropTerm = jest.fn();
        expect(mountLine(root, dropTerm)).toBe(true);
        expect(line.dataset.reactComponent).toBe('@moodle/lms/theme_dimensions/filterline/FilterLine');
        expect(JSON.parse(line.dataset.reactProps || '')).toEqual({form: 'dim-toolbar-7'});
        line.dispatchEvent(new CustomEvent(LINE.dropterm));
        expect(dropTerm).toHaveBeenCalledTimes(1);
    });

    it('leaves a line it cannot draw to the server', () => {
        expect(mountLine(toolbar(''), jest.fn())).toBe(false);
        const root = toolbar('dim-toolbar-7', false);
        expect(mountLine(root, jest.fn())).toBe(false);
        expect(root.querySelector<HTMLElement>('[data-region="dim-line"]')?.dataset.reactComponent).toBeUndefined();
    });

    it('appends the calendar after its listeners, hides the fields once drawn and tells the period again', () => {
        const root = toolbar('dim-toolbar-7');
        const state = stateOf();
        const region = root.querySelector<HTMLElement>('[data-region="dim-daterange"]') as HTMLElement;
        const told: Array<{from: string, to: string}> = [];
        region.addEventListener(CALENDAR.set, (event) => told.push((event as CustomEvent).detail));
        const picked = jest.fn();
        mountCalendar(root, state, picked);
        const node = region.firstElementChild as HTMLElement;
        expect(node.dataset.reactComponent).toBe('@moodle/lms/theme_dimensions/daterange/DateRange');
        expect(JSON.parse(node.dataset.reactProps || '')).toEqual({id: 7, firstday: '1', today: '2026-10-29', from: '2026-01-01', to: ''});
        const fields = root.querySelector<HTMLElement>('[data-region="dim-dateinputs"]') as HTMLElement;
        expect(fields.hidden).toBe(false);
        state.datefrom = '2026-02-02';
        region.dispatchEvent(new CustomEvent(CALENDAR.mounted));
        expect(fields.hidden).toBe(true);
        expect(told).toEqual([{from: '2026-02-02', to: ''}]);
        region.dispatchEvent(new CustomEvent(CALENDAR.pick, {detail: {from: '2026-03-01', to: '2026-03-02'}}));
        expect(picked).toHaveBeenCalledWith('2026-03-01', '2026-03-02');
    });

    it('appends a hidden node carrying the posters\' props object, with the client\'s answers heard first', () => {
        const root = toolbar('dim-toolbar-7');
        const areas = root.querySelector<HTMLElement>('[data-region="dim-areas"]') as HTMLElement;
        const answer = Promise.resolve({});
        const client = {ask: jest.fn(() => answer), question: jest.fn(() => 'q'), unanswered: jest.fn(() => true), painted: jest.fn()};
        expect(mountAreas(areas, {links: true}, client)).toBe(true);
        const node = areas.lastElementChild as HTMLElement;
        expect(node.hidden).toBe(true);
        expect(node.dataset.reactComponent).toBe('@moodle/lms/theme_dimensions/areas/Areas');
        expect(JSON.parse(node.dataset.reactProps || '')).toEqual({links: true});
        const poster = document.createElement('article');
        const detail: Record<string, unknown> = {poster: poster, psort: 'name'};
        areas.dispatchEvent(new CustomEvent(AREAS.ask, {detail: detail}));
        areas.dispatchEvent(new CustomEvent(AREAS.question, {detail: detail}));
        areas.dispatchEvent(new CustomEvent(AREAS.unanswered, {detail: detail}));
        areas.dispatchEvent(new CustomEvent(AREAS.painted, {detail: {panel: poster}}));
        expect(detail).toMatchObject({answer: answer, question: 'q', taken: true});
        expect(client.ask).toHaveBeenCalledWith(poster, 'name');
        expect(client.painted).toHaveBeenCalledWith(poster);
    });

    it('mounts no posters where the areas carry no panel', () => {
        document.body.innerHTML = '<div data-region="dim-areas"></div>';
        expect(mountAreas(document.querySelector<HTMLElement>('[data-region="dim-areas"]'), {}, {ask: jest.fn()})).toBe(false);
        expect(mountAreas(null, {}, {ask: jest.fn()})).toBe(false);
    });

    it('tells a drawn line the scope in SCOPE\'s order, and an undrawn one nothing', () => {
        const root = toolbar('dim-toolbar-7');
        const line = root.querySelector<HTMLElement>('[data-region="dim-line"]') as HTMLElement;
        const heard: Array<Record<string, unknown>> = [];
        line.addEventListener(LINE.answer, (event) => heard.push((event as CustomEvent).detail));
        const response = {statusgroup: {options: [
            {id: 'b', value: 'scheduled', label: 'Scheduled'}, {id: 'c', value: 'review', label: 'Review'},
            {id: 'a', value: '', label: 'All'}, {id: 'd', value: 'enrolled', label: 'Mine'},
        ]}, filtercount: '2', filtercountlabel: 'Two filters'};
        tellAnswer(root, response);
        expect(heard).toHaveLength(0);
        line.setAttribute(LINE_DRAWN, '');
        tellAnswer(root, response);
        expect((heard[0].scope as Array<{value: string}>).map((option) => option.value)).toEqual(['', 'enrolled', 'scheduled']);
        expect(heard[0]).toMatchObject({favourites: null, filtercount: 2, filtercountlabel: 'Two filters', period: ''});

        // The date button is told the period chip's words, which an answer without the chip has none of.
        tellAnswer(root, Object.assign({}, response, {chips: [
            {param: 'cf_3', label: 'Online'}, {param: 'period', label: '1 May to 9 May'},
        ]}));
        expect(heard[1].period).toBe('1 May to 9 May');
    });

    it('reads a drawn line\'s own controls as the component\'s, and its four adopted frames as the listing\'s', () => {
        document.body.innerHTML = `<div data-region="dim-line" ${LINE_DRAWN}><button id="star"></button>`
            + '<div data-region="dim-filters-panel"><input id="pill"></div>'
            + '<details data-region="dim-period"><summary id="date"></summary>'
            + '<div data-region="dim-period-layer"><a id="quick"></a></div></details></div><input id="out">';
        expect(drawnByLine(document.getElementById('star'))).toBe(true);
        expect(drawnByLine(document.getElementById('date'))).toBe(true);
        expect(drawnByLine(document.getElementById('quick'))).toBe(false);
        expect(drawnByLine(document.getElementById('pill'))).toBe(false);
        expect(drawnByLine(document.getElementById('out'))).toBe(false);
        expect(drawnByLine(null)).toBe(false);
    });

    it('gives the focus to the search, or to its icon where the line folded the field away', () => {
        document.body.innerHTML = '<section><input type="search"><button data-action="dim-search-open"></button></section>';
        const root = document.querySelector('section') as HTMLElement;
        const search = root.querySelector('input') as HTMLElement;
        const opener = root.querySelector('button') as HTMLElement;
        const rects = (element: Element, count: number): void => {
            jest.spyOn(element, 'getClientRects').mockReturnValue({length: count} as DOMRectList);
        };
        rects(search, 0);
        rects(opener, 1);
        expect(focusTarget(root)).toBe(opener);
        rects(search, 1);
        expect(focusTarget(root)).toBe(search);
    });
});

describe('theme_dimensions/listing/history', () => {
    it('pushes a change and replaces a correction, with an empty state, and brings the fold form in line', () => {
        document.body.innerHTML = '<form data-region="dim-hero-fold"><button>Fold</button></form>';
        const push = jest.spyOn(window.history, 'pushState');
        const replace = jest.spyOn(window.history, 'replaceState');
        const state = stateOf();
        writeUrl(state, true);
        expect(push).toHaveBeenLastCalledWith({}, '', 'http://localhost/course/index.php?categoryid=7&q=fire&from=2026-01-01');
        expect(document.querySelector<HTMLInputElement>('input[name="q"]')?.value).toBe('fire');
        replace.mockClear();
        writeUrl(state, false);
        expect(replace).toHaveBeenCalledTimes(1);
        expect(replace.mock.calls[0][0]).toEqual({});
    });

    it('replaces an address and keeps the entry\'s state, adding no entry', () => {
        window.history.replaceState({kept: 1}, '', '/course/index.php?categoryid=7');
        const before = window.history.length;
        replaceKeeping('/course/index.php?categoryid=7&open=9');
        expect(window.history.state).toEqual({kept: 1});
        expect(window.location.search).toBe('?categoryid=7&open=9');
        expect(window.history.length).toBe(before);
    });
});
