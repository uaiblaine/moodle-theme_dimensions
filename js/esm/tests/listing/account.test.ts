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
 * Tests for an account's shell: the remembered state, the view preference, the progress and the completed state.
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
 * A card as the server draws it: its state pill, its progress row, its call to action and its "More information" link;
 * an enrolled one carries the completed state's words.
 *
 * @param {number} id The course id.
 * @param {string} state The classifier's state.
 * @returns {string}
 */
const cardOf = (id: number, state = 'enrolled'): string => `<li><article data-region="dim-card" data-courseid="${id}" `
    + `data-state="${state}"${state === 'enrolled' ? ' data-done="Completed" data-review="Review"' : ''}>`
    + '<span class="dim-card__badge dim-badge dim-badge--enrolled" data-region="dim-card-pill">'
    + '<i class="fa fa-circle-check" aria-hidden="true"></i> Enrolled</span>'
    + '<div data-region="dim-card-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" hidden>'
    + '<span aria-hidden="true"><span></span></span><span data-region="dim-card-pct" aria-hidden="true"></span></div>'
    + `<a class="btn btn-sm dim-card__cta btn-primary" data-region="dim-card-cta" href="#view-${id}">Access</a>`
    + `<a data-region="dim-card-more" href="#course-${id}">More</a></article></li>`;

/**
 * One card's parts, by course id.
 *
 * @param {number} id The course id.
 * @returns {object} The card, its progress row, its percentage, its pill and its call to action.
 */
const partsOf = (id: number): {card: HTMLElement, row: HTMLElement, pct: HTMLElement, pill: HTMLElement, cta: HTMLElement} => {
    const card = document.querySelector<HTMLElement>(`[data-region="dim-card"][data-courseid="${id}"]`) as HTMLElement;
    const part = (region: string): HTMLElement => card.querySelector<HTMLElement>(`[data-region="${region}"]`) as HTMLElement;
    return {card: card, row: part('dim-card-progress'), pct: part('dim-card-pct'), pill: part('dim-card-pill'),
        cta: part('dim-card-cta')};
};

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

        // A typed search and a quick period are no more part of the remembered state than the tree is: the
        // address stays bare of the organisation. Changes that must make it fail: counting the search or the
        // period as a change to remember (every address would then name the organisation).
        const search = document.querySelector<HTMLInputElement>('input[name="q"]') as HTMLInputElement;
        search.value = 'ab';
        search.dispatchEvent(new Event('input', {bubbles: true}));
        jest.advanceTimersByTime(1000);
        expect(address().get('q')).toBe('ab');
        expect(asked(REMEMBER)).toEqual([]);
        expect(address().has('sort')).toBe(false);

        document.querySelector('[data-region="dim-vitrine-toolbar"]')?.insertAdjacentHTML('beforeend',
            '<button type="button" data-action="dim-quick" data-from="2026-02-01" data-to="2026-02-28">February</button>');
        (document.querySelector('[data-action="dim-quick"]') as HTMLElement).click();
        jest.advanceTimersByTime(1000);
        expect(address().get('from')).toBe('2026-02-01');
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

    it('leaves for the grouped organisation as soon as the state is remembered, without waiting the second out', async() => {
        const redirected = location.redirect as unknown as jest.Mock;
        redirected.mockImplementation(() => null);
        rememberAnswers = true;
        // A category no other test remembers: the shell sends a state only when it differs from the last one sent.
        page({categoryid: 8});
        start();
        choose('sort', 'category');
        expect(asked(REMEMBER)).toHaveLength(1);
        // Changes that must make it fail: waiting for the timer alone, so the page leaves a second late.
        await jest.advanceTimersByTimeAsync(0);
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
        batches[0].resolve({details: [{id: 1, completed: false, progress: 40, progresslabel: '40% complete',
            progresspct: '40%'}]});
        await jest.advanceTimersByTimeAsync(0);
        const slot = partsOf(1).row;
        expect(slot.getAttribute('aria-label')).toBe('40% complete');
        expect(slot.hidden).toBe(false);
        expect(watcher.observed.size).toBe(6);
        // Seen again, a settled course is not asked again; the six left are the next batch.
        watcher.seeAll();
        await jest.advanceTimersByTimeAsync(100);
        expect(batches.map((batch) => batch.ids)).toEqual([batches[0].ids, [25, 26, 27, 28, 29, 30]]);
        // The module's state outlives the test: the batch in flight is answered, so the next test's batch can go.
        batches[1].resolve({details: []});
        await jest.advanceTimersByTimeAsync(0);
    });

    it('runs the drain only while something is pending', async() => {
        page({}, cardOf(81) + cardOf(82));
        start();
        expect(jest.getTimerCount()).toBe(0);
        Watcher.last.seeAll();
        expect(jest.getTimerCount()).toBe(1);
        jest.advanceTimersByTime(100);
        batches[0].resolve({details: []});
        await jest.advanceTimersByTimeAsync(0);
        // The next tick finds nothing pending and stops the interval.
        await jest.advanceTimersByTimeAsync(100);
        expect(jest.getTimerCount()).toBe(0);
    });

    it('paints the progress row: the bar\'s width, the value, the label and the percentage; no number, no row', async() => {
        // Courses no other test settled.
        page({}, cardOf(61) + cardOf(62) + cardOf(63));
        start();
        Watcher.last.seeAll();
        jest.advanceTimersByTime(100);
        expect(batches[0].ids).toEqual([61, 62, 63]);
        batches[0].resolve({details: [
            {id: 61, completed: false, progress: 40, progresslabel: '40% complete', progresspct: '40%'},
            // No completion tracking: no number, and the row stays hidden rather than reading 0 %.
            {id: 62, completed: false, progress: null},
            // Boost Union's progress setting off: the same, the server sends no number.
            {id: 63, completed: false},
        ]});
        await jest.advanceTimersByTimeAsync(0);

        const tracked = partsOf(61);
        expect(tracked.row.hidden).toBe(false);
        expect(tracked.row.getAttribute('role')).toBe('progressbar');
        expect(tracked.row.getAttribute('aria-valuenow')).toBe('40');
        expect(tracked.row.getAttribute('aria-label')).toBe('40% complete');
        expect(tracked.row.style.getPropertyValue('--dim-progress')).toBe('40%');
        expect(tracked.pct.textContent).toBe('40%');
        expect(tracked.card.classList.contains('dim-card--done')).toBe(false);
        expect(tracked.pill.textContent).toBe(' Enrolled');
        expect(tracked.cta.textContent).toBe('Access');

        [62, 63].forEach((id) => {
            const parts = partsOf(id);
            expect(parts.row.hidden).toBe(true);
            expect(parts.row.hasAttribute('aria-valuenow')).toBe(false);
            expect(parts.pct.textContent).toBe('');
        });
    });

    it('turns a completed course\'s card into the seal and "Review", with no bar, whatever its progress', async() => {
        page({}, cardOf(71) + cardOf(72));
        start();
        Watcher.last.seeAll();
        jest.advanceTimersByTime(100);
        batches[0].resolve({details: [
            {id: 71, completed: true, progress: 100, progresslabel: '100% complete', progresspct: '100%'},
            // Completed with Boost Union's progress row off: the seal stays.
            {id: 72, completed: true, progress: null},
        ]});
        await jest.advanceTimersByTimeAsync(0);

        [71, 72].forEach((id) => {
            const {card, row, pill, cta} = partsOf(id);
            expect(card.classList.contains('dim-card--done')).toBe(true);
            expect(row.hidden).toBe(true);
            expect(row.hasAttribute('aria-valuenow')).toBe(false);
            expect(pill.textContent).toBe(' Completed');
            expect(pill.classList.contains('dim-badge--done')).toBe(true);
            expect(pill.classList.contains('dim-badge--enrolled')).toBe(false);
            expect(pill.classList.contains('dim-card__badge')).toBe(true);
            expect((pill.querySelector('i') as HTMLElement).className).toBe('fa fa-check');
            expect(cta.textContent).toBe('Review');
            expect(cta.classList.contains('btn-outline-primary')).toBe(true);
            expect(cta.classList.contains('btn-primary')).toBe(false);
            expect(cta.getAttribute('href')).toBe(`#view-${id}`);
        });
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

    it('leaves the card\'s "More information" link to the course dialogue\'s module', () => {
        page();
        start();
        const link = document.querySelector('[data-region="dim-card-more"]') as HTMLAnchorElement;
        const press = new MouseEvent('click', {bubbles: true, cancelable: true});
        link.dispatchEvent(press);
        // The shell intercepts nothing of it: the dialogue's AMD module does, for a real account, or the link is followed.
        expect(press.defaultPrevented).toBe(false);
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
