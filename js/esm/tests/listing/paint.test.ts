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
 * Tests for the listing's painters: an answer put onto the page the server drew, word for word.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {LINE, LINE_DRAWN, LISTING} from '../../src/listing/events';
import {
    markTree, paintChips, paintControls, paintCount, paintFavToggle, paintFiltersControl, paintGrid, paintGroups, paintPager,
    paintView, renderCards,
} from '../../src/listing/paint';
import type {ListingState} from '../../src/listing/address';

/**
 * A state at rest on category 7, with the given fields changed.
 *
 * @param {object} changes The fields to change.
 * @returns {object}
 */
const stateOf = (changes: Partial<ListingState> = {}): ListingState => ({
    categoryid: 7, page: 0, query: '', chip: '', fav: false, filters: {}, sort: 'name', defaultsort: '', remembered: false,
    datefrom: '', dateto: '', cat: 0, view: 'cards', viewchosen: false, pagesize: 0, ...changes,
});


/**
 * The listing section with the given inner markup.
 *
 * @param {string} html The markup inside the section.
 * @returns {HTMLElement}
 */
const page = (html: string): HTMLElement => {
    document.body.innerHTML = `<section data-region="dim-vitrine">${html}</section>`;
    return document.querySelector<HTMLElement>('[data-region="dim-vitrine"]') as HTMLElement;
};

/**
 * A pill of a group, as the toolbar draws it.
 *
 * @param {string} name The group's name.
 * @param {string} value The pill's value.
 * @returns {string}
 */
const pill = (name: string, value: string): string => `<input type="radio" name="${name}" id="p-${name}-${value}" value="${value}">`
    + `<label for="p-${name}-${value}">${value} <span data-region="dim-pill-count"></span></label>`;

/**
 * The value order of a group's visible pills.
 *
 * @param {Element} group The group.
 * @returns {string[]}
 */
const order = (group: Element): string[] => Array.from(group.querySelectorAll<HTMLInputElement>('input'))
    .filter((input) => !input.hidden).map((input) => input.value);

/**
 * The response's option of a status group or a facet.
 *
 * @param {string} value The option's value.
 * @param {number} count Its count.
 * @returns {object}
 */
const option = (value: string, count = 1): {id: string, value: string, label: string, count: number, hascount: boolean} =>
    ({id: 'p-chip-' + value, value: value, label: value, count: count, hascount: true});

beforeEach(() => {
    window.history.replaceState(null, '', '/course/index.php?categoryid=7');
});

describe('theme_dimensions/listing/paint', () => {
    it('splits the status group by SCOPE and puts a returning cell back in its place without moving the focus', () => {
        const root = page('<fieldset data-region="dim-vitrine-group" data-name="chip" data-role="scope">'
            + pill('chip', '') + pill('chip', 'open') + '</fieldset>'
            + '<fieldset data-region="dim-vitrine-group" data-name="chip" data-role="more">' + pill('chip', 'review') + '</fieldset>');
        const [scope, more] = Array.from(root.querySelectorAll<HTMLElement>('fieldset'));
        const open = scope.querySelector<HTMLInputElement>('input[value="open"]') as HTMLInputElement;
        open.focus();
        paintGroups(root, {statusgroup: {options: [option('open'), option('enrolled', 2), option(''), option('free')]}});
        expect(order(scope)).toEqual(['', 'enrolled', 'open']);
        expect(document.activeElement).toBe(open);
        expect(order(more)).toEqual(['free']);
        expect(more.hidden).toBe(false);
        paintGroups(root, {statusgroup: {options: [option(''), option('open')]}});
        expect(order(more)).toEqual([]);
        expect(more.hidden).toBe(true);
        // No option at all: the state sort was switched off under the page, which keeps its pills.
        paintGroups(root, {statusgroup: {options: []}});
        expect(order(scope)).toEqual(['', 'open']);
    });

    it('hides the Filters control while its panel holds no group, shuts it open, and shows it when a group returns', () => {
        // The scope sits outside the panel: it is a group, but not one the control opens.
        const root = page('<fieldset data-region="dim-vitrine-group" data-name="chip" data-role="scope">'
            + pill('chip', '') + '</fieldset>'
            + '<details data-region="dim-filters" open><summary>Filters</summary><div data-region="dim-filters-panel">'
            + '<fieldset data-region="dim-vitrine-group" data-name="chip" data-role="more" hidden>' + pill('chip', 'review')
            + '</fieldset></div></details>');
        const control = root.querySelector<HTMLDetailsElement>('[data-region="dim-filters"]') as HTMLDetailsElement;
        paintGroups(root, {statusgroup: {options: [option('')]}});
        paintFiltersControl(root);
        expect(control.hidden).toBe(true);
        expect(control.open).toBe(false);
        paintGroups(root, {statusgroup: {options: [option(''), option('free')]}});
        paintFiltersControl(root);
        expect(control.hidden).toBe(false);
        // A field's group alone keeps the control, whatever "More statuses" holds.
        paintGroups(root, {statusgroup: {options: [option('')]}});
        (root.querySelector('[data-region="dim-filters-panel"]') as HTMLElement).insertAdjacentHTML('beforeend',
            '<fieldset data-region="dim-vitrine-group" data-name="cf_3">' + pill('cf_3', '0') + '</fieldset>');
        paintFiltersControl(root);
        expect(control.hidden).toBe(false);
    });

    it('leaves the Filters control to the component where it draws the line', () => {
        const root = page('<div data-region="dim-line"><details data-region="dim-filters"><summary>Filters</summary>'
            + '<div data-region="dim-filters-panel"></div></details></div>');
        (root.querySelector('[data-region="dim-line"]') as HTMLElement).setAttribute(LINE_DRAWN, '');
        paintFiltersControl(root);
        expect(root.querySelector<HTMLElement>('[data-region="dim-filters"]')?.hidden).toBe(false);
    });

    it('puts a facet\'s "any" pill back at the head of its group', () => {
        const root = page('<fieldset data-region="dim-vitrine-group" data-name="cf_3">' + pill('cf_3', '0') + pill('cf_3', '2') + '</fieldset>');
        paintGroups(root, {facets: [{name: 'cf_3', anyid: 'p-cf_3-0', anylabel: 'Any', anychecked: true, options: [option('2', 4)]}]});
        const group = root.querySelector('fieldset') as HTMLElement;
        expect(order(group)).toEqual(['0', '2']);
        expect(group.querySelector<HTMLInputElement>('input[value="0"]')?.checked).toBe(true);
        expect(group.querySelector('label[for="p-cf_3-2"] [data-region="dim-pill-count"]')?.textContent).toBe('4');
    });

    it('paints the chips where the server draws them, with the open panel carried, and "Clear all" from the answer', () => {
        window.history.replaceState(null, '', '/course/index.php?categoryid=7&open=9');
        const root = page('<div data-region="dim-line-chips" class="dim-line__chips--rest"><p data-region="dim-vitrine-count" class="visually-hidden"></p>'
            + '<span data-region="dim-daterange-pill"></span><span data-region="dim-line-chiplist"></span>'
            + '<a data-region="dim-vitrine-clear" hidden></a></div><a data-region="dim-filters-clear" hidden></a>');
        paintChips(root, {hasfilters: true, clearall: true, chips: [
            {param: 'period', label: 'From 1 May', url: '/course/index.php?categoryid=7', removelabel: 'Remove'},
            {param: 'cf_3', label: 'Online', url: '/course/index.php?categoryid=7&from=2026-05-01'},
        ]});
        const row = root.querySelector('[data-region="dim-line-chips"]') as HTMLElement;
        expect(row.classList.contains('dim-line__chips--rest')).toBe(false);
        // The sentence's visibility is the count's painter's, from the answer's "See all as a list".
        expect(row.querySelector('[data-region="dim-vitrine-count"]')?.classList.contains('visually-hidden')).toBe(true);
        expect(root.querySelector<HTMLElement>('[data-region="dim-filters-clear"]')?.hidden).toBe(false);
        expect(row.querySelector('[data-region="dim-daterange-pill"] a')?.getAttribute('data-param')).toBe('period');
        const field = row.querySelector<HTMLAnchorElement>('[data-region="dim-line-chiplist"] a') as HTMLAnchorElement;
        expect(field.dataset.param).toBe('cf_3');
        expect(field.href).toBe('http://localhost/course/index.php?categoryid=7&from=2026-05-01&open=9');
        expect(row.querySelector<HTMLElement>('[data-region="dim-vitrine-clear"]')?.hidden).toBe(false);

        // At rest the panel's "Clear filters" has nothing to clear.
        paintChips(root, {hasfilters: false, clearall: false, chips: []});
        expect(row.classList.contains('dim-line__chips--rest')).toBe(true);
        expect(root.querySelector<HTMLElement>('[data-region="dim-filters-clear"]')?.hidden).toBe(true);
    });

    it('shows the count sentence only where it says where the results lie, the bar\'s count and "Show" with its number', () => {
        const root = page('<div data-region="dim-line"><span data-region="dim-line-total">16</span></div>'
            + '<button data-action="dim-filters-show" data-dim-template="Mostrar {n}">Mostrar 16</button>'
            + '<button data-action="dim-period-show" data-dim-template="Mostrar {n}">Mostrar 16</button>'
            + '<p data-region="dim-vitrine-count"><b data-region="dim-vitrine-countnumber"></b>'
            + '<span data-region="dim-vitrine-counttext"></span></p><span data-region="dim-vitrine-seeall" hidden><a href="#"></a></span>');
        const sentence = root.querySelector('[data-region="dim-vitrine-count"]') as HTMLElement;
        paintCount(root, {countnumber: '1.234', countline: 'cursos com estes filtros', seeallurl: ''});
        expect(sentence.classList.contains('visually-hidden')).toBe(true);
        expect(root.querySelector('[data-region="dim-line-total"]')?.textContent).toBe('1.234');
        expect(root.querySelector('[data-action="dim-filters-show"]')?.textContent).toBe('Mostrar 1.234');
        // The date layer's "Show" is the same button with the same sentence.
        expect(root.querySelector('[data-action="dim-period-show"]')?.textContent).toBe('Mostrar 1.234');
        // Changes that must make it fail: the sentence's visibility read from anything but the answer's seeallurl.
        paintCount(root, {countnumber: '16', countline: 'cursos: 2 nesta categoria e 14 em 4 áreas', seeallurl: '/all'});
        expect(sentence.classList.contains('visually-hidden')).toBe(false);
        expect(root.querySelector('[data-region="dim-line-total"]')?.textContent).toBe('16');

        // A line the component draws is told its count instead.
        const line = root.querySelector('[data-region="dim-line"]') as HTMLElement;
        line.setAttribute('data-line-drawn', 'true');
        paintCount(root, {countnumber: '7', countline: 'cursos', seeallurl: ''});
        expect(root.querySelector('[data-region="dim-line-total"]')?.textContent).toBe('16');
        expect(root.querySelector('[data-action="dim-filters-show"]')?.textContent).toBe('Mostrar 7');
        expect(root.querySelector('[data-action="dim-period-show"]')?.textContent).toBe('Mostrar 7');
    });

    it('names the date button with the answer\'s period and dots it, on a line the server drew only', () => {
        window.history.replaceState(null, '', '/course/index.php?categoryid=7&open=9');
        const root = page('<div data-region="dim-line"><details data-region="dim-period">'
            + '<summary data-region="dim-period-button" aria-label="Data de início" data-dim-tip="Data de início"'
            + ' data-dim-template="Data de início: {period}"><span data-region="dim-period-dot" hidden></span></summary>'
            + '<div data-region="dim-period-layer"><a data-action="dim-period-clear" href="/old"></a></div></details></div>'
            + '<div data-region="dim-line-chips"><span data-region="dim-daterange-pill"></span></div>');
        const button = root.querySelector('[data-region="dim-period-button"]') as HTMLElement;
        const dot = root.querySelector('[data-region="dim-period-dot"]') as HTMLElement;
        const clear = root.querySelector('[data-action="dim-period-clear"]') as HTMLAnchorElement;
        paintChips(root, {hasfilters: true, chips: [
            {param: 'period', label: '1 de maio até 9 de maio', url: '/course/index.php?categoryid=7&q=fire'},
        ]});
        expect(button.getAttribute('aria-label')).toBe('Data de início: 1 de maio até 9 de maio');
        expect(dot.hidden).toBe(false);
        // "Clear period" goes where the period chip goes, the open panel carried.
        expect(clear.href).toBe('http://localhost/course/index.php?categoryid=7&q=fire&open=9');

        // No period chip is no period: the filter's name alone, and no dot.
        paintChips(root, {hasfilters: true, chips: [{param: 'cf_3', label: 'Online', url: '/x'}]});
        expect(button.getAttribute('aria-label')).toBe('Data de início');
        expect(dot.hidden).toBe(true);

        // A line the component draws is told the period instead (tellAnswer()), and the layer is still painted.
        (root.querySelector('[data-region="dim-line"]') as HTMLElement).setAttribute('data-line-drawn', 'true');
        paintChips(root, {hasfilters: true, chips: [{param: 'period', label: 'Até 9 de maio', url: '/y'}]});
        expect(button.getAttribute('aria-label')).toBe('Data de início');
        expect(dot.hidden).toBe(true);
        expect(clear.href).toBe('http://localhost/y?open=9');
    });

    it('rewrites the count so that a repeated sentence still differs, and takes the opening\'s note away', () => {
        const root = page('<p data-region="dim-vitrine-count"><b data-region="dim-vitrine-countnumber"></b>'
            + '<span data-region="dim-vitrine-counttext"></span></p><span data-region="dim-vitrine-seeall" hidden><a href="#"></a></span>'
            + '<span data-region="dim-vitrine-countnote"></span>');
        const text = root.querySelector('[data-region="dim-vitrine-counttext"]') as HTMLElement;
        paintCount(root, {countnumber: '3', countline: 'courses', seeallurl: '/all'});
        const first = text.textContent;
        paintCount(root, {countnumber: '3', countline: 'courses', seeallurl: '/all'});
        expect(text.textContent).not.toBe(first);
        expect(text.firstChild?.textContent).toBe('courses');
        expect(root.querySelector('[data-region="dim-vitrine-countnumber"]')?.textContent).toBe('3');
        expect(root.querySelector<HTMLElement>('[data-region="dim-vitrine-seeall"]')?.hidden).toBe(false);
        expect(root.querySelector<HTMLElement>('[data-region="dim-vitrine-countnote"]')?.hidden).toBe(true);
    });

    it('closes paging after the tenth append, and restores the button before it gives it the focus', () => {
        const root = page('<ul data-region="dim-vitrine-grid"></ul><nav data-region="dim-vitrine-pagerbar"></nav>'
            + '<button data-action="dim-loadmore" data-label="Load more" disabled>Loading</button>'
            + '<a data-region="dim-vitrine-nextpage" hidden></a>');
        const grid = root.querySelector<HTMLElement>('[data-region="dim-vitrine-grid"]') as HTMLElement;
        const more = root.querySelector<HTMLButtonElement>('[data-action="dim-loadmore"]') as HTMLButtonElement;
        const next = root.querySelector<HTMLAnchorElement>('[data-region="dim-vitrine-nextpage"]') as HTMLAnchorElement;
        paintGrid(root, stateOf(), grid, [], false, null);
        for (let count = 1; count <= 9; count++) {
            paintGrid(root, stateOf(), grid, [document.createElement('li')], true, null);
            paintPager(root, {hasmore: true, nexturl: '/next'}, true, null);
        }
        expect(more.hidden).toBe(false);
        expect(more.disabled).toBe(false);
        expect(more.textContent).toBe('Load more');
        expect(document.activeElement).toBe(more);
        paintGrid(root, stateOf(), grid, [document.createElement('li')], true, null);
        paintPager(root, {hasmore: true, nexturl: '/next'}, true, null);
        expect(more.hidden).toBe(true);
        expect(next.hidden).toBe(false);
        expect(document.activeElement).toBe(next);
        expect(root.querySelector<HTMLElement>('[data-region="dim-vitrine-pagerbar"]')?.hidden).toBe(true);
        // A replaced grid opens the window again.
        paintGrid(root, stateOf(), grid, [], false, null);
        paintPager(root, {hasmore: true}, false, null);
        expect(more.hidden).toBe(false);
    });

    it('hands every card a replace discards to the caller, marks the term and shows the empty box', () => {
        const root = page('<ul data-region="dim-vitrine-grid"><li><article data-region="dim-card"></article></li></ul>'
            + '<div data-region="dim-vitrine-empty" hidden></div>');
        const grid = root.querySelector<HTMLElement>('[data-region="dim-vitrine-grid"]') as HTMLElement;
        const discard = jest.fn();
        const item = document.createElement('li');
        item.innerHTML = '<h3 data-region="dim-card-title">Fire rescue</h3>';
        paintGrid(root, stateOf({query: 'rescue'}), grid, [item], false, discard);
        expect(discard).toHaveBeenCalledTimes(1);
        expect(item.querySelector('mark')?.textContent).toBe('rescue');
        paintGrid(root, stateOf(), grid, [], false, discard);
        expect(grid.hidden).toBe(true);
        expect(root.querySelector<HTMLElement>('[data-region="dim-vitrine-empty"]')?.hidden).toBe(false);
    });

    it('tells a drawn line its state instead of writing into it, and paints the server\'s line otherwise', () => {
        const root = page('<div data-region="dim-line"><button data-action="dim-view" value="list" aria-pressed="false"></button>'
            + '<button data-region="dim-line-star" value="1"><i class="fa-star fa-regular"></i></button></div>'
            + '<input type="hidden" data-region="dim-fav-field" disabled>');
        const line = root.querySelector<HTMLElement>('[data-region="dim-line"]') as HTMLElement;
        const button = line.querySelector('button') as HTMLButtonElement;
        const views: string[] = [];
        document.addEventListener(LISTING.view, (event) => views.push((event as CustomEvent).detail.view));
        paintView(root, 'list');
        expect(button.getAttribute('aria-pressed')).toBe('true');
        expect(root.classList.contains('dim-vitrine--list')).toBe(true);
        expect(views).toEqual(['list']);
        paintFavToggle(root, true);
        expect(line.querySelector('[data-region="dim-line-star"]')?.getAttribute('aria-pressed')).toBe('true');
        expect(root.querySelector<HTMLInputElement>('[data-region="dim-fav-field"]')?.disabled).toBe(false);

        line.setAttribute(LINE_DRAWN, '');
        const told: Array<Record<string, unknown>> = [];
        line.addEventListener(LINE.state, (event) => told.push((event as CustomEvent).detail));
        paintView(root, 'cards');
        paintFavToggle(root, false);
        expect(button.getAttribute('aria-pressed')).toBe('true');
        expect(told).toEqual([{view: 'cards'}, {fav: false}]);
        // The hidden field is the form's, outside the line: painted on both shells.
        expect(root.querySelector<HTMLInputElement>('[data-region="dim-fav-field"]')?.disabled).toBe(true);
    });

    it('puts a state back onto the controls and marks exactly one tree entry', () => {
        const root = page('<input type="search" value="old"><button data-action="dim-search-clear"></button>'
            + '<fieldset data-region="dim-sort">' + pill('sort', 'category') + pill('sort', 'name') + '</fieldset>'
            + '<nav data-region="dim-vitrine-tree"><a data-region="dim-tree-link" data-categoryid="7"></a>'
            + '<a data-region="dim-tree-link" data-categoryid="9" class="dim-tree__link--on"></a></nav>');
        paintControls(root, stateOf({query: '', sort: ''}));
        expect(root.querySelector<HTMLInputElement>('input[type="search"]')?.value).toBe('');
        expect(root.querySelector<HTMLElement>('[data-action="dim-search-clear"]')?.hidden).toBe(true);
        expect(root.querySelector<HTMLInputElement>('input[value="category"]')?.checked).toBe(true);
        const marked = (): string[] => Array.from(root.querySelectorAll<HTMLElement>('.dim-tree__link--on'))
            .map((link) => link.dataset.categoryid || '');
        expect(marked()).toEqual(['7']);
        markTree(root, stateOf({cat: 9}));
        expect(marked()).toEqual(['9']);
        expect(root.querySelector('[data-categoryid="9"]')?.getAttribute('aria-current')).toBe('true');
    });

    it('renders every card through the server\'s card template, in order, entering hidden', async() => {
        const rendered: Array<Record<string, unknown>> = [];
        mockAmdModule('core/templates', {
            renderForPromise: jest.fn(async(name: string, context: Record<string, unknown>) => {
                rendered.push({name: name, ...context});
                return {html: `<article>${context.id}</article>`, js: ''};
            }),
            appendNodeContents: jest.fn((element: Element, html: string) => element.insertAdjacentHTML('beforeend', html)),
        });
        const items = await renderCards([{id: 1}, {id: 2}]);
        expect(rendered.map((one) => one.name)).toEqual(['theme_dimensions/category_card', 'theme_dimensions/category_card']);
        expect(items.map((item) => item.textContent)).toEqual(['1', '2']);
        expect(items[0].className).toBe('dim-vitrine__item dim-vitrine__item--enter');
        expect(items.map((item) => item.tagName)).toEqual(['LI', 'LI']);
    });

    it('announces a whole page of cards to core once, with every card\'s JavaScript', async() => {
        // Each insertion is one filterContentUpdated, and Boost answers each with a document listener it keeps.
        const append = jest.fn((element: Element, html: string) => element.insertAdjacentHTML('beforeend', html));
        mockAmdModule('core/templates', {
            renderForPromise: jest.fn(async(name: string, context: Record<string, unknown>) =>
                ({html: `<article>${context.id}</article>`, js: context.id === 2 ? '' : `run(${context.id});`})),
            appendNodeContents: append,
        });
        const items = await renderCards([{id: 1}, {id: 2}, {id: 3}]);
        expect(append).toHaveBeenCalledTimes(1);
        expect(append.mock.calls[0][2]).toBe('run(1);;\nrun(3);');
        expect(items.map((item) => item.textContent)).toEqual(['1', '2', '3']);
        expect(items.every((item) => item.parentElement === items[0].parentElement)).toBe(true);
    });

    it('announces nothing for an answer without cards', async() => {
        const append = jest.fn();
        mockAmdModule('core/templates', {renderForPromise: jest.fn(), appendNodeContents: append});
        expect(await renderCards([])).toEqual([]);
        expect(append).not.toHaveBeenCalled();
    });
});
