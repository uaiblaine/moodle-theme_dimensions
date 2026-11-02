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
 * Tests for an account's shell: the remembered state, the view preference, and the progress.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import Fetch from '@moodle/lms/core/fetch';
import * as location from '@moodle/lms/core/location';
import {start} from '../../src/listing/account';

jest.mock('@moodle/lms/core/ajax', () => ({fetchOne: jest.fn()}));
jest.mock('@moodle/lms/core/fetch', () => ({__esModule: true, default: {performPost: jest.fn()}}));

const fetched = fetchOne as jest.Mock;
const posted = Fetch.performPost as jest.Mock;

/** A stand-in IntersectionObserver: what is watched, and a way to say all of it came into view. */
class Watcher {
    static last: Watcher;
    observed = new Set<Element>();
    callback: (entries: Array<{target: Element, isIntersecting: boolean}>) => void;
    constructor(callback: (entries: Array<{target: Element, isIntersecting: boolean}>) => void) {
        this.callback = callback;
        Watcher.last = this;
    }
    observe(element: Element): void {
        this.observed.add(element);
    }
    unobserve(element: Element): void {
        this.observed.delete(element);
    }
    seeAll(): void {
        this.callback(Array.from(this.observed).map((target) => ({target: target, isIntersecting: true})));
    }
}

/** The answers the details service hands out, one per batch, in order. */
let batches: Array<{ids: number[], resolve: (answer: object) => void}>;

/** The behaviour of the remembering service: answered at once, or never. */
let rememberAnswers: boolean;

/**
 * A card as the server draws it, with its disclosure and its progress slot.
 *
 * @param {number} id The course id.
 * @param {string} state The classifier's state.
 * @returns {string}
 */
const cardOf = (id: number, state = 'enrolled'): string => `<li><article data-region="dim-card" data-courseid="${id}" `
    + `data-state="${state}"><details data-region="dim-card-details"><summary>More</summary>`
    + `<div data-region="dim-card-panel">About ${id}</div></details>`
    + '<span data-region="dim-card-progress" hidden></span></article></li>';

/**
 * An account's flat page: a toolbar with the organisation, a chip and the view, a tree, a grid and the pager.
 *
 * @param {object} payload What the listing's payload says beside the defaults.
 * @param {string} cards The grid's cards.
 * @returns {void}
 */
const page = (payload: object = {}, cards = cardOf(1) + cardOf(2)): void => {
    const data = Object.assign({categoryid: 7, page: 0, total: 2, hasmore: false, sort: 'name', defaultsort: 'name',
        view: 'cards', canpersist: true}, payload);
    document.body.className = '';
    document.body.innerHTML = `<section data-region="dim-vitrine" data-vitrine='${JSON.stringify(data)}'>`
        + '<form data-region="dim-vitrine-toolbar" id="t"><input type="search" name="q">'
        + '<fieldset data-region="dim-sort"><input type="radio" name="sort" value="name">'
        + '<input type="radio" name="sort" value="category"></fieldset>'
        + '<input type="radio" name="chip" value="open"><button data-action="dim-view" value="list">List</button></form>'
        + '<nav data-region="dim-vitrine-tree"><a data-region="dim-tree-link" data-categoryid="9" href="#">Area</a></nav>'
        + '<div data-region="dim-area"></div>'
        + `<ul data-region="dim-vitrine-grid">${cards}</ul>`
        + '<button data-action="dim-loadmore">Load more</button></section>';
};

/**
 * Choose a radio of the toolbar.
 *
 * @param {string} name The radio's name.
 * @param {string} value Its value.
 * @returns {void}
 */
const choose = (name: string, value: string): void => {
    const input = document.querySelector<HTMLInputElement>(`input[name="${name}"][value="${value}"]`) as HTMLInputElement;
    input.checked = true;
    input.dispatchEvent(new Event('change', {bubbles: true}));
};

/**
 * The requests of one service so far.
 *
 * @param {string} method The service's name.
 * @returns {Array} The requests' arguments.
 */
const asked = (method: string): Array<Record<string, unknown>> => fetched.mock.calls
    .filter(([request]) => request.methodname === method).map(([request]) => request.args);

/**
 * The address on screen's parameters.
 *
 * @returns {URLSearchParams}
 */
const address = (): URLSearchParams => new URL(window.location.href).searchParams;

/** A calendar that cannot mount: a region the component cannot be appended to. Returns the spy to restore. */
const failingCalendar = (): jest.SpyInstance => {
    document.querySelector('[data-region="dim-vitrine-toolbar"]')?.insertAdjacentHTML('beforeend',
        '<fieldset data-region="dim-date"><input type="date" name="from"><div data-region="dim-daterange"></div></fieldset>');
    const append = Element.prototype.append;
    return jest.spyOn(Element.prototype, 'append').mockImplementation(function(this: Element, ...nodes) {
        if (this.getAttribute('data-region') === 'dim-daterange') {
            throw new Error('no calendar');
        }
        append.apply(this, nodes);
    });
};

const REMEMBER = 'theme_dimensions_remember_category_state';
const DETAILS = 'theme_dimensions_get_card_details';
const ROWS = 'theme_dimensions_get_category_rows';

beforeAll(() => {
    (window as unknown as {IntersectionObserver: unknown}).IntersectionObserver = Watcher;
    window.matchMedia = jest.fn().mockReturnValue({matches: true}) as never;
});

beforeEach(() => {
    jest.useFakeTimers();
    batches = [];
    rememberAnswers = true;
    window.history.replaceState(null, '', '/course/index.php?categoryid=7');
    fetched.mockReset();
    fetched.mockImplementation((request: {methodname: string, args: {courseids: number[]}}) => {
        if (request.methodname === DETAILS) {
            return new Promise((resolve) => batches.push({ids: request.args.courseids, resolve: resolve}));
        }
        if (request.methodname === REMEMBER) {
            return rememberAnswers ? Promise.resolve({}) : new Promise(() => null);
        }
        return new Promise(() => null);
    });
    posted.mockReset();
    posted.mockReturnValue(Promise.resolve({}));
    mockAmdModule('core/notification', {exception: jest.fn(() => Promise.resolve())});
    mockAmdModule('core/templates', {
        renderForPromise: jest.fn(async(name: string, context: {id: number}) => ({html: cardOf(context.id), js: ''})),
        appendNodeContents: jest.fn((element: Element, html: string) => element.insertAdjacentHTML('beforeend', html)),
    });
});

afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
});

describe('theme_dimensions/listing/account', () => {
    it('names the organisation in the address before it is written, and remembers the state once, after the quiet', async() => {
        page();
        start();
        expect(document.body.classList.contains('dim-js')).toBe(true);
        expect(address().has('sort')).toBe(false);
        choose('chip', 'open');
        // The default organisation is named from the first change on: the bare address means the remembered state now.
        expect(address().get('sort')).toBe('name');
        expect(address().get('chip')).toBe('open');
        expect(asked(ROWS)).toHaveLength(1);
        jest.advanceTimersByTime(499);
        expect(asked(REMEMBER)).toEqual([]);
        jest.advanceTimersByTime(1);
        expect(asked(REMEMBER)).toEqual([{categoryid: 7, sort: 'name', view: 'cards', chip: 'open', fav: false, filters: []}]);
        // Back to the same state: nothing new to remember.
        window.dispatchEvent(new PopStateEvent('popstate'));
        jest.advanceTimersByTime(1000);
        expect(asked(REMEMBER)).toHaveLength(1);
    });

    it('remembers neither the tree, nor a search, nor a period', () => {
        page();
        start();
        (document.querySelector('[data-region="dim-tree-link"]') as HTMLElement).click();
        jest.advanceTimersByTime(1000);
        expect(asked(ROWS)).toHaveLength(1);
        expect(asked(REMEMBER)).toEqual([]);
        expect(address().has('sort')).toBe(false);
    });

    it('leaves for the grouped organisation once the state is remembered at once, or a second has passed', async() => {
        const redirected = location.redirect as unknown as jest.Mock;
        redirected.mockImplementation(() => null);
        rememberAnswers = false;
        page();
        start();
        choose('sort', 'category');
        expect(asked(REMEMBER)).toEqual([{categoryid: 7, sort: 'category', view: 'cards', chip: '', fav: false, filters: []}]);
        expect(asked(ROWS)).toEqual([]);
        await jest.advanceTimersByTimeAsync(999);
        expect(redirected).not.toHaveBeenCalled();
        await jest.advanceTimersByTimeAsync(1);
        expect(redirected).toHaveBeenCalledTimes(1);
        expect(new URL(redirected.mock.calls[0][0]).searchParams.get('sort')).toBe('category');
        redirected.mockClear();
    });

    it('writes the view as the person\'s preference after a quiet, for a real account only', () => {
        page();
        start();
        (document.querySelector('[data-action="dim-view"]') as HTMLElement).click();
        jest.advanceTimersByTime(499);
        expect(posted).not.toHaveBeenCalled();
        jest.advanceTimersByTime(1);
        expect(posted).toHaveBeenCalledWith('core_user', 'current/preferences/theme_dimensions_categoryview',
            {body: {value: 'list'}});
        expect(asked(REMEMBER)).toHaveLength(1);

        posted.mockClear();
        fetched.mockClear();
        page({canpersist: false});
        start();
        (document.querySelector('[data-action="dim-view"]') as HTMLElement).click();
        jest.advanceTimersByTime(1000);
        expect(posted).not.toHaveBeenCalled();
        expect(asked(REMEMBER)).toEqual([]);
        expect(address().has('sort')).toBe(false);
    });

    it('writes a remembered bare address out in full in place, and sends nothing it was drawn from', () => {
        const push = jest.spyOn(window.history, 'pushState');
        page({remembered: true, sort: 'recent'});
        start();
        expect(address().get('sort')).toBe('recent');
        expect(push).not.toHaveBeenCalled();
        window.dispatchEvent(new PopStateEvent('popstate'));
        jest.advanceTimersByTime(1000);
        expect(asked(REMEMBER)).toEqual([]);
        push.mockRestore();
    });

    it('asks progress in batches of 24 on a fixed interval, once per course, and stops watching what settled', async() => {
        page({}, Array.from({length: 30}, (_, i) => cardOf(i + 1)).join('') + cardOf(99, 'open'));
        start();
        const watcher = Watcher.last;
        expect(watcher.observed.size).toBe(30);
        watcher.seeAll();
        jest.advanceTimersByTime(100);
        expect(batches.map((batch) => batch.ids.length)).toEqual([24]);
        // One request at a time: the next drain waits for the first batch.
        jest.advanceTimersByTime(100);
        expect(batches).toHaveLength(1);
        batches[0].resolve({details: [{id: 1, progresslabel: '40% complete'}]});
        await jest.advanceTimersByTimeAsync(0);
        const slot = document.querySelector<HTMLElement>('[data-courseid="1"] [data-region="dim-card-progress"]') as HTMLElement;
        expect(slot.textContent).toBe('40% complete');
        expect(slot.hidden).toBe(false);
        expect(watcher.observed.size).toBe(6);
        // Seen again, a settled course is not asked again; the six left are the next batch.
        watcher.seeAll();
        await jest.advanceTimersByTimeAsync(100);
        expect(batches.map((batch) => batch.ids)).toEqual([batches[0].ids, [25, 26, 27, 28, 29, 30]]);
    });

    it('gives a replaced grid\'s cards back to the observer and watches the cards an answer drew', async() => {
        // Courses no other test settled: a settled course is never watched again, in this module's lifetime.
        page({}, cardOf(41) + cardOf(42));
        start();
        const watcher = Watcher.last;
        expect(watcher.observed.size).toBe(2);
        fetched.mockImplementation(() => Promise.resolve({cards: [{id: 51}], page: 0, total: 1, hasmore: false}));
        (document.querySelector('[data-region="dim-tree-link"]') as HTMLElement).click();
        await jest.advanceTimersByTimeAsync(0);
        await jest.advanceTimersByTimeAsync(0);
        const watched = Array.from(watcher.observed).map((card) => (card as HTMLElement).dataset.courseid);
        expect(watched).toEqual(['51']);
    });

    it('opens one panel at a time and closes it on Escape', () => {
        page();
        start();
        const [first, second] = Array.from(document.querySelectorAll<HTMLElement>('[data-region="dim-card"]'));
        const opened = first.querySelector('details') as HTMLDetailsElement;
        opened.open = true;
        opened.dispatchEvent(new Event('toggle'));

        // A disclosure's toggle does not bubble: the listing hears it in the capture phase, and closes the others.
        const disclosure = second.querySelector('details') as HTMLDetailsElement;
        disclosure.open = true;
        disclosure.dispatchEvent(new Event('toggle'));
        expect(opened.open).toBe(false);
        expect(disclosure.open).toBe(true);

        document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape'}));
        expect(disclosure.open).toBe(false);
    });

    it('shows core\'s dialogue for a calendar that could not mount, over the native fields', async() => {
        const Notification = {exception: jest.fn(() => Promise.resolve())};
        mockAmdModule('core/notification', Notification);
        page();
        const append = failingCalendar();
        start();
        append.mockRestore();
        await jest.advanceTimersByTimeAsync(0);
        await jest.advanceTimersByTimeAsync(0);
        expect(Notification.exception).toHaveBeenCalledWith(expect.objectContaining({message: 'no calendar'}));
        expect(document.querySelector('input[name="from"]')).not.toBeNull();
    });

    it('shows a failed page in core\'s dialogue and tells the posters their answer is not coming', async() => {
        const Notification = {exception: jest.fn(() => Promise.resolve())};
        mockAmdModule('core/notification', Notification);
        const told = jest.fn();
        document.addEventListener('dim-vitrine:failed', told);
        page();
        start();
        const error = new Error('gone');
        fetched.mockImplementation(() => Promise.reject(error));
        (document.querySelector('[data-region="dim-tree-link"]') as HTMLElement).click();
        await jest.advanceTimersByTimeAsync(0);
        await jest.advanceTimersByTimeAsync(0);
        expect(Notification.exception).toHaveBeenCalledWith(error);
        expect(told).toHaveBeenCalledTimes(1);
        document.removeEventListener('dim-vitrine:failed', told);
    });
});
