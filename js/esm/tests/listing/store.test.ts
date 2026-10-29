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
 * Tests for the listing's store over a stand-in shell: the actions, the history, the requests and their tickets.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {current, init as start} from '../../src/listing/store';
import type {Rows, Shell} from '../../src/listing/store';

/** What the stand-in shell was told, in order. */
let told: string[];

/** The answers the stand-in service hands out, one per request, in order. */
let answers: Array<{resolve: (rows: Rows) => void, reject: (error: unknown) => void, page: number}>;

/**
 * A stand-in shell that records every call.
 *
 * @returns {object}
 */
const shellOf = (): Shell => ({
    mark: 'dim-test-js',
    normalise: jest.fn(() => told.push('normalise')),
    rows: jest.fn((page: number) => new Promise<Rows>((resolve, reject) => {
        told.push('rows ' + page);
        answers.push({resolve: resolve, reject: reject, page: page});
    })),
    failed: jest.fn((page: number, append: boolean) => told.push(`failed ${page} ${append}`)),
    written: jest.fn(() => told.push('written ' + window.location.search)),
    leave: jest.fn((url: URL) => told.push('leave ' + url.search)),
    answered: jest.fn(() => told.push('answered')),
    areas: {props: {links: true}, client: {ask: jest.fn()}},
});

/**
 * A listing section as the server draws it: a toolbar, a flat tree, a grid of two cards and the pager.
 *
 * @param {boolean} toolbar Whether the page draws its toolbar.
 * @returns {void}
 */
const page = (toolbar = true): void => {
    document.body.innerHTML = `<section data-region="dim-vitrine" data-vitrine='{"page":0,"total":3,"hasmore":true}'>`
        + (toolbar ? '<form data-region="dim-vitrine-toolbar" id="t"><input type="search" name="q">'
            + '<fieldset data-region="dim-sort"><input type="radio" name="sort" value="name">'
            + '<input type="radio" name="sort" value="recent"><input type="radio" name="sort" value="category"></fieldset>'
            + '</form>' : '')
        + '<nav data-region="dim-vitrine-tree"><a data-region="dim-tree-link" data-categoryid="9" href="#">Area</a></nav>'
        + '<p data-region="dim-toolbar-notice" hidden></p>'
        + '<div data-region="dim-areas"><ol><li data-region="dim-panel"></li></ol></div>'
        + '<ul data-region="dim-vitrine-grid"><li><article>Old</article></li></ul>'
        + '<button data-action="dim-loadmore" data-label="Load more">Load more</button></section>';
};

/**
 * An answer with the given cards.
 *
 * @param {number} count How many cards.
 * @param {number} at The page answered.
 * @returns {object}
 */
const rowsOf = (count: number, at = 0): Rows => ({
    cards: Array.from({length: count}, (_, i) => ({id: i})), page: at, total: count, hasmore: false,
});

/**
 * Let the request chain settle, the bridge's loading of core/templates included.
 *
 * @returns {Promise}
 */
const settle = async(): Promise<void> => {
    for (let round = 0; round < 3; round++) {
        await new Promise((resolve) => setTimeout(resolve, 0));
    }
};

/**
 * The text of the grid's articles.
 *
 * @returns {string[]}
 */
const grid = (): string[] => Array.from(document.querySelectorAll('[data-region="dim-vitrine-grid"] article'))
    .map((article) => article.textContent || '');

const push = jest.spyOn(window.history, 'pushState');

beforeEach(() => {
    told = [];
    answers = [];
    push.mockClear();
    window.history.replaceState(null, '', '/course/index.php?categoryid=7&sort=name');
    mockAmdModule('core/templates', {
        renderForPromise: jest.fn(async(name: string, context: {id: number}) =>
            ({html: `<article>${context.id}</article>`, js: ''})),
        appendNodeContents: jest.fn((element: Element, html: string) => element.insertAdjacentHTML('beforeend', html)),
    });
});

describe('theme_dimensions/listing/store', () => {
    it('arms the page with the history\'s listener before the posters are handed over, and fetches nothing', () => {
        page();
        const order: string[] = [];
        const listen = jest.spyOn(window, 'addEventListener').mockImplementation(((type: string) => {
            order.push('listen ' + type);
        }) as never);
        const append = jest.spyOn(Element.prototype, 'append').mockImplementation(function(this: Element, ...nodes) {
            order.push('append to ' + this.getAttribute('data-region'));
            nodes.forEach((node) => this.appendChild(node as Node));
        });
        const shell = shellOf();
        expect(start(shell, 7)).toBe(true);
        listen.mockRestore();
        append.mockRestore();
        expect(order.slice(0, 2)).toEqual(['listen popstate', 'append to dim-areas']);
        expect(document.body.classList.contains('dim-test-js')).toBe(true);
        expect(told).toEqual(['normalise', 'written ?categoryid=7&sort=name']);
        expect(shell.rows).not.toHaveBeenCalled();
    });

    it('arms a root without a search line for its posters alone, with no listener of the history', () => {
        document.body.classList.remove('dim-test-js');
        page(false);
        const listen = jest.spyOn(window, 'addEventListener');
        start(shellOf(), 0);
        expect(listen).not.toHaveBeenCalledWith('popstate', expect.anything());
        listen.mockRestore();
        expect(document.querySelector('[data-region="dim-areas"] [data-react-component]')).not.toBeNull();
        expect(document.body.classList.contains('dim-test-js')).toBe(false);
    });

    it('pushes one entry for a tree press, then asks page zero, and paints the answer busy until then', async() => {
        page();
        start(shellOf(), 7);
        (document.querySelector('[data-region="dim-tree-link"]') as HTMLElement).click();
        expect(push).toHaveBeenCalledTimes(1);
        expect(current().cat).toBe(9);
        expect(told.slice(2)).toEqual(['written ?categoryid=7&sort=name&cat=9', 'rows 0']);
        const root = document.querySelector('[data-region="dim-vitrine"]') as HTMLElement;
        expect(root.getAttribute('aria-busy')).toBe('true');
        answers[0].resolve(rowsOf(2));
        await settle();
        expect(grid()).toEqual(['0', '1']);
        expect(root.hasAttribute('aria-busy')).toBe(false);
        expect(told).toContain('answered');
    });

    it('paints nothing of an answer a newer request overtook, and lets only the latest failure fall back', async() => {
        page();
        start(shellOf(), 7);
        const radio = (value: string): void => {
            const input = document.querySelector<HTMLInputElement>(`input[value="${value}"]`) as HTMLInputElement;
            input.checked = true;
            input.dispatchEvent(new Event('change', {bubbles: true}));
        };
        radio('recent');
        (document.querySelector('[data-region="dim-tree-link"]') as HTMLElement).click();
        answers[1].resolve(rowsOf(1));
        await settle();
        answers[0].resolve(rowsOf(3));
        await settle();
        expect(grid()).toEqual(['0']);
        (document.querySelector('[data-region="dim-tree-link"]') as HTMLElement).dataset.categoryid = '8';
        (document.querySelector('[data-region="dim-tree-link"]') as HTMLElement).click();
        radio('name');
        answers[2].reject(new Error('gone'));
        answers[3].reject(new Error('gone'));
        await settle();
        expect(told.filter((line) => line.startsWith('failed'))).toEqual(['failed 0 false']);
    });

    it('leaves the page for the grouped organisation, which a flat shell cannot hold', () => {
        page();
        const shell = shellOf();
        start(shell, 7);
        const input = document.querySelector<HTMLInputElement>('input[value="category"]') as HTMLInputElement;
        input.dispatchEvent(new Event('change', {bubbles: true}));
        expect(shell.leave).toHaveBeenCalledTimes(1);
        expect(shell.rows).not.toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
    });

    it('appends the next page with no address of its own', async() => {
        page();
        start(shellOf(), 7);
        (document.querySelector('[data-action="dim-loadmore"]') as HTMLElement).click();
        expect(told.slice(2)).toEqual(['rows 1']);
        answers[0].resolve(rowsOf(1, 1));
        await settle();
        expect(grid()).toEqual(['Old', '0']);
        expect(push).not.toHaveBeenCalled();
        expect(window.location.search).toBe('?categoryid=7&sort=name');
    });

    it('reads Back into the state, asks page zero and writes no entry', () => {
        page();
        start(shellOf(), 7);
        window.history.replaceState(null, '', '/course/index.php?categoryid=7&sort=name&q=fire');
        window.dispatchEvent(new PopStateEvent('popstate'));
        expect(current().query).toBe('fire');
        expect(told.slice(2)).toEqual(['normalise', 'written ?categoryid=7&sort=name&q=fire', 'rows 0']);
        expect(push).not.toHaveBeenCalled();
    });

    it('holds one pending from the request until the rows are painted, through a late template load', async() => {
        const ROWS = 'theme_dimensions/listing:rows';
        const opened = jest.spyOn(M.util, 'js_pending');
        const closed = jest.spyOn(M.util, 'js_complete');
        const open = (): number => opened.mock.calls.filter(([key]) => key === ROWS).length
            - closed.mock.calls.filter(([key]) => key === ROWS).length;
        let render: () => void = () => null;
        mockAmdModule('core/templates', {
            renderForPromise: jest.fn(() => new Promise((resolve) => {
                render = () => resolve({html: '<article>New</article>', js: ''});
            })),
            appendNodeContents: jest.fn((element: Element, html: string) => element.insertAdjacentHTML('beforeend', html)),
        });
        page();
        start(shellOf(), 7);
        const tree = document.querySelector('[data-region="dim-tree-link"]') as HTMLElement;
        tree.click();
        expect(open()).toBe(1);
        answers[0].resolve(rowsOf(1));
        await settle();
        // The answer is in, the cards are not: the page is not settled yet.
        expect(grid()).toEqual(['Old']);
        expect(open()).toBe(1);
        render();
        await settle();
        expect(grid()).toEqual(['New']);
        expect(open()).toBe(0);

        // A failure releases its token, and so does a request a newer one overtook, each once.
        tree.dataset.categoryid = '8';
        tree.click();
        answers[1].reject(new Error('gone'));
        await settle();
        expect(open()).toBe(0);
        tree.dataset.categoryid = '6';
        tree.click();
        tree.dataset.categoryid = '5';
        tree.click();
        expect(open()).toBe(2);
        answers[2].resolve(rowsOf(1));
        await settle();
        expect(open()).toBe(1);
        answers[3].reject(new Error('gone'));
        await settle();
        expect(open()).toBe(0);
        expect(closed.mock.calls.filter(([key]) => key === ROWS)).toHaveLength(4);
        opened.mockRestore();
        closed.mockRestore();
    });

    it('sends a term after the quiet, holding a pending, and never one under the floor', () => {
        jest.useFakeTimers();
        page();
        start(shellOf(), 7);
        const search = document.querySelector<HTMLInputElement>('input[type="search"]') as HTMLInputElement;
        search.value = 'f';
        search.dispatchEvent(new Event('input', {bubbles: true}));
        jest.advanceTimersByTime(300);
        expect(document.querySelector<HTMLElement>('[data-region="dim-toolbar-notice"]')?.hidden).toBe(false);
        search.value = 'fire';
        search.dispatchEvent(new Event('input', {bubbles: true}));
        jest.advanceTimersByTime(299);
        expect(told.filter((line) => line.startsWith('rows'))).toEqual([]);
        jest.advanceTimersByTime(1);
        expect(told.filter((line) => line.startsWith('rows'))).toEqual(['rows 0']);
        expect(current().query).toBe('fire');
        jest.useRealTimers();
    });
});
