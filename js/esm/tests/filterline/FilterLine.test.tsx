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
 * Tests for the logged-in showcase's filter line component, mounted on the server's own line.
 *
 * The page is the template's example (./toolbar, held equal to the template by
 * filterline_component_source_test), and the component is mounted the way core's react_autoinit mounts
 * it: a root over the line node itself, whose server markup the first commit replaces. The listing is
 * played by the events it sends and hears on the line. jsdom lays nothing out, so the steps are given
 * through a stubbed measure where a test is about them.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, fireEvent, render, waitFor} from '@testing-library/react';
import {createRoot} from 'react-dom/client';
import FilterLine, {DRAWN, EVENTS} from '../../src/filterline/FilterLine';
import * as Measure from '../../src/filterline/measure';
import {FORM, drawPage, pointer, shape} from './page';
import type {Card, Page} from './page';

// The browser imports the preview on first use; Jest's runtime cannot run a native import(), so the
// suite hands the component the very module it would have imported.
jest.mock('../../src/filterline/lazy', () => ({
    importPreview: () => Promise.resolve(jest.requireActual('../../src/filterline/preview')),
}));

jest.mock('../../src/filterline/measure', () => {
    const actual = jest.requireActual('../../src/filterline/measure');
    return Object.assign({}, actual, {
        stepWidths: jest.fn(actual.stepWidths),
        contentWidth: jest.fn(actual.contentWidth),
        placeList: jest.fn(actual.placeList),
    });
});

/** The attributes the component adds to the server's markup: the combobox and its listbox. */
const ADDED = ['input:role', 'input:aria-autocomplete', 'input:aria-expanded', 'input:aria-controls', 'div:role', 'div:aria-label'];

/** The ResizeObserver callbacks the page has registered. */
let observers: Array<(entries: Array<{contentRect: {width: number}}>) => void> = [];

beforeEach(() => {
    observers = [];
    // A value queued for a call a failed test never made must not reach the next test.
    const actual = jest.requireActual('../../src/filterline/measure');
    (Measure.stepWidths as jest.Mock).mockReset().mockImplementation(actual.stepWidths);
    (Measure.contentWidth as jest.Mock).mockReset().mockImplementation(actual.contentWidth);
    (Measure.placeList as jest.Mock).mockReset().mockImplementation(actual.placeList);
    window.matchMedia = jest.fn().mockImplementation((query: string) => ({
        media: query,
        matches: false,
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
    }));
    (window as unknown as {ResizeObserver: unknown}).ResizeObserver = class {
        /**
         * Keep the callback for the test to call.
         *
         * @param {Function} callback The observer's callback.
         */
        constructor(callback: (entries: Array<{contentRect: {width: number}}>) => void) {
            observers.push(callback);
        }

        /**
         * Nothing to observe in jsdom.
         *
         * @returns {void}
         */
        observe(): void {
            return undefined;
        }

        /**
         * Nothing to stop.
         *
         * @returns {void}
         */
        disconnect(): void {
            return undefined;
        }
    };
});

afterEach(async() => {
    // The preview arrives a task later and gives the focus event that loaded it again: inside act, then gone.
    await act(async() => {
        await new Promise((resolve) => window.setTimeout(resolve, 0));
    });
    document.body.replaceChildren();
});

/** The page, the line and what the listing heard. */
type Mounted = Page & {mounted: jest.Mock, dropped: jest.Mock, field: HTMLInputElement};

/**
 * Draw the page and mount the component on its line, as react_autoinit does.
 *
 * @param {Array} cards The cards of the grid.
 * @param {Function} before Called with the page before the mount: what the reader did on the server's line.
 * @returns {Promise} The page with the listing's listeners.
 */
const mount = async(cards: Card[] = [], before: (page: Page) => void = () => undefined): Promise<Mounted> => {
    const page = drawPage(cards);
    before(page);
    const mounted = jest.fn();
    const dropped = jest.fn();
    page.line.addEventListener(EVENTS.mounted, mounted);
    page.line.addEventListener(EVENTS.dropterm, dropped);
    await act(async() => {
        render(<FilterLine form={FORM} />, {container: page.line});
    });
    const field = page.line.querySelector<HTMLInputElement>('input[type="search"]') as HTMLInputElement;
    return Object.assign(page, {mounted, dropped, field});
};

/**
 * Empty the server's field before the mount, so the suggestions start from no term.
 *
 * @param {Page} page The page.
 * @returns {void}
 */
const empty = (page: Page): void => {
    (page.line.querySelector('input[type="search"]') as HTMLInputElement).value = '';
};

/**
 * The listbox's options' words.
 *
 * @param {HTMLElement} line The line.
 * @returns {Array}
 */
const options = (line: HTMLElement): string[] => Array.from(line.querySelectorAll('[role="option"] .dim-line__optionlabel'))
    .map((label) => label.textContent || '');

/**
 * The listbox.
 *
 * @param {HTMLElement} line The line.
 * @returns {HTMLElement}
 */
const listbox = (line: HTMLElement): HTMLElement => line.querySelector('[data-region="dim-line-suggest"]') as HTMLElement;

/**
 * Deliver an entry of the line's width to every observer, as the browser does after its layout.
 *
 * @param {number} width The line's content box width.
 * @returns {Promise}
 */
const resize = async(width: number): Promise<void> => {
    await act(async() => {
        observers.forEach((callback) => callback([{contentRect: {width: width}}]));
    });
};

/**
 * Spy on every layout read the line's measure and its layers' placement make.
 *
 * @returns {Array} The spies, to count and restore.
 */
const layoutReads = (): jest.SpyInstance[] => [
    jest.spyOn(Element.prototype, 'getBoundingClientRect'),
    jest.spyOn(Element.prototype, 'getClientRects'),
    jest.spyOn(Element.prototype, 'clientWidth', 'get'),
    jest.spyOn(Element.prototype, 'scrollWidth', 'get'),
    jest.spyOn(HTMLElement.prototype, 'offsetWidth', 'get'),
    jest.spyOn(HTMLElement.prototype, 'offsetParent', 'get'),
    jest.spyOn(window, 'getComputedStyle'),
];

/**
 * Tell the line something, as the listing does.
 *
 * @param {HTMLElement} line The line.
 * @param {string} type The event.
 * @param {object} detail What it is told.
 * @returns {Promise}
 */
const tell = async(line: HTMLElement, type: string, detail: object): Promise<void> => {
    await act(async() => {
        line.dispatchEvent(new CustomEvent(type, {detail: detail}));
    });
};

describe('theme_dimensions/filterline/FilterLine', () => {
    it('draws the boxes the server drew, every name in the same form, the three frames adopted as they were', async() => {
        const page = drawPage();
        const server = Array.from(page.line.children).map((child) => shape(child, ADDED)).join('');
        const posted = Array.from(new FormData(page.form).entries());
        const panel = page.line.querySelector('[data-region="dim-filters-panel"] [data-name="cf_12"]');
        const menu = page.line.querySelector('[data-region="dim-sort"]');
        const manage = page.line.querySelector('[data-region="dim-line-manage"] .action-menu');

        await act(async() => {
            render(<FilterLine form={FORM} />, {container: page.line});
        });

        expect(Array.from(page.line.children).map((child) => shape(child, ADDED)).join('')).toBe(server);
        expect(Array.from(new FormData(page.form).entries())).toEqual(posted);
        // The very server nodes, moved: what the listing paints there and what the period control is mounted in.
        expect(page.line.querySelector('[data-region="dim-filters-panel"] [data-name="cf_12"]')).toBe(panel);
        expect(page.line.querySelector('[data-region="dim-sort"]')).toBe(menu);
        expect(page.line.querySelector('[data-region="dim-line-manage"] .action-menu')).toBe(manage);
        const field = page.line.querySelector('input[type="search"]') as HTMLInputElement;
        expect(field.getAttribute('role')).toBe('combobox');
        expect(field.getAttribute('aria-controls')).toBe('dim-vitrine-suggest-7');
        expect(listbox(page.line).getAttribute('role')).toBe('listbox');
    });

    it('marks the line drawn and says so once, after the first commit', async() => {
        const page = await mount();
        expect(page.line.hasAttribute(DRAWN)).toBe(true);
        expect(page.mounted).toHaveBeenCalledTimes(1);
    });

    it('keeps what the reader did on the server\'s line before the mount, the focus included', async() => {
        const page = await mount([], (server) => {
            const field = server.line.querySelector('input[type="search"]') as HTMLInputElement;
            field.value = 'fire';
            (server.line.querySelector('#dim-chip-7-enrolled') as HTMLInputElement).checked = true;
            field.focus();
        });
        expect(page.field.value).toBe('fire');
        expect((page.line.querySelector('#dim-chip-7-enrolled') as HTMLInputElement).checked).toBe(true);
        expect(document.activeElement).toBe(page.field);
        // A focus the component gave back is not the reader's: the search did not open.
        expect(listbox(page.line).hidden).toBe(true);
        // A line holding the focus is never hidden until its first fold: a hidden ancestor would take the focus away.
        expect(page.line.style.visibility).toBe('');
    });

    it('draws an answer: the scope\'s counts and absent cells, the star, the badge', async() => {
        const page = await mount();
        await tell(page.line, EVENTS.answer, {
            scope: [
                {id: 'dim-chip-7-all', value: '', label: 'All', count: 8, hascount: true, checked: true},
                {id: 'dim-chip-7-open', value: 'open', label: 'Open', count: 2, hascount: true, checked: false},
            ],
            favourites: {available: true, pressed: true, count: 4},
            filtercount: 0,
            filtercountlabel: '',
        });
        const label = (value: string) => page.line.querySelector(`label[for="dim-chip-7-${value}"]`) as HTMLElement;
        expect(label('all').textContent).toBe('All 8');
        expect((page.line.querySelector('#dim-chip-7-all') as HTMLInputElement).checked).toBe(true);
        expect(label('enrolled').hidden).toBe(true);
        expect(label('open').hidden).toBe(false);
        const star = page.line.querySelector('[data-region="dim-line-star"]') as HTMLButtonElement;
        expect(star.getAttribute('aria-pressed')).toBe('true');
        expect(star.value).toBe('0');
        expect(star.querySelector('.fa-star')?.classList.contains('fa-solid')).toBe(true);
        expect(star.querySelector('[data-region="dim-fav-count"]')?.textContent).toBe('4');
        expect((page.line.querySelector('[data-region="dim-filtercount"]') as HTMLElement).hidden).toBe(true);
    });

    it('draws the listing\'s state: the term, the scope\'s choice, the star, the view', async() => {
        const page = await mount();
        await tell(page.line, EVENTS.state, {query: '', chip: 'enrolled', fav: true, view: 'list'});
        expect(page.field.value).toBe('');
        expect((page.line.querySelector('[data-action="dim-search-clear"]') as HTMLElement).hidden).toBe(true);
        expect((page.line.querySelector('#dim-chip-7-enrolled') as HTMLInputElement).checked).toBe(true);
        expect(page.line.querySelector('[data-region="dim-line-star"]')?.getAttribute('aria-pressed')).toBe('true');
        const views = Array.from(page.line.querySelectorAll('[data-region="dim-view-row"] button'));
        expect(views.map((button) => button.getAttribute('aria-pressed'))).toEqual(['true', 'false']);
    });

    it('opens the search on the reader\'s own focus only: a press or a Tab, never a client\'s focus', async() => {
        const page = await mount([], empty);
        await act(async() => page.field.focus());
        expect(listbox(page.line).hidden).toBe(true);
        await act(async() => page.field.blur());

        fireEvent.pointerDown(page.field);
        await act(async() => page.field.focus());
        expect(listbox(page.line).hidden).toBe(false);
        expect(page.field.getAttribute('aria-expanded')).toBe('true');
        expect(listbox(page.line).querySelector('.dim-line__suggesthead')?.textContent).toBe('Start with a filter');
        expect(options(page.line)).toEqual(['In-person', 'My courses', 'Favourites', 'Today']);

        fireEvent.keyDown(page.field, {key: 'Escape'});
        expect(listbox(page.line).hidden).toBe(true);
        await act(async() => page.field.blur());
        fireEvent.keyDown(document, {key: 'Tab'});
        await act(async() => page.field.focus());
        expect(listbox(page.line).hidden).toBe(false);
    });

    it('matches what is typed, accent- and case-insensitively, and marks it in the option', async() => {
        const page = await mount([], empty);
        fireEvent.change(page.field, {target: {value: 'PERS'}});
        expect(listbox(page.line).hidden).toBe(false);
        expect(listbox(page.line).querySelector('.dim-line__suggesthead')?.textContent).toBe('Filters');
        expect(options(page.line)).toEqual(['In-person']);
        expect(listbox(page.line).querySelector('mark.dim-mark')?.textContent).toBe('pers');
    });

    it('places the listbox when it opens, not on every key typed into it, and again once it reopens', async() => {
        const page = await mount([], empty);
        ['P', 'PE', 'PER', 'PERS'].forEach((value) => fireEvent.change(page.field, {target: {value: value}}));
        expect(listbox(page.line).hidden).toBe(false);
        expect(Measure.placeList).toHaveBeenCalledTimes(1);

        fireEvent.keyDown(page.field, {key: 'Escape'});
        expect(listbox(page.line).hidden).toBe(true);
        fireEvent.change(page.field, {target: {value: 'PERSO'}});
        expect(listbox(page.line).hidden).toBe(false);
        expect(Measure.placeList).toHaveBeenCalledTimes(2);
    });

    it('applies a suggestion: the term dropped first, then its very control pressed, and offers what is left', async() => {
        const page = await mount([], empty);
        const control = page.line.querySelector('#dim-cf-12-2') as HTMLInputElement;
        const order: string[] = [];
        page.dropped.mockImplementation(() => order.push('dropterm'));
        control.addEventListener('click', () => order.push(control.checked ? 'press' : 'press-unchecked'));
        fireEvent.change(page.field, {target: {value: 'in'}});
        fireEvent.keyDown(page.field, {key: 'ArrowDown'});
        expect(page.field.getAttribute('aria-activedescendant')).toBe('dim-vitrine-suggest-7-0');
        expect(listbox(page.line).querySelector('[aria-selected="true"]')?.textContent).toContain('In-person');
        await act(async() => {
            fireEvent.keyDown(page.field, {key: 'Enter'});
        });
        expect(order).toEqual(['dropterm', 'press']);
        expect(control.checked).toBe(true);
        expect(page.field.value).toBe('');
        expect(options(page.line)).not.toContain('In-person');
        expect(listbox(page.line).hidden).toBe(false);
    });

    it('closes a panel of the line on Escape and gives its summary the focus, but leaves the calendar its key', async() => {
        const page = await mount();
        const filters = page.line.querySelector('[data-region="dim-filters"]') as HTMLDetailsElement;
        filters.open = true;
        const radio = page.line.querySelector('#dim-cf-12-2') as HTMLInputElement;
        fireEvent.keyDown(radio, {key: 'Escape'});
        expect(filters.open).toBe(false);
        expect(document.activeElement).toBe(filters.querySelector('summary'));

        filters.open = true;
        const calendar = document.createElement('button');
        page.line.querySelector('[data-region="dim-daterange"]')?.append(calendar);
        fireEvent.keyDown(calendar, {key: 'Escape'});
        expect(filters.open).toBe(true);
    });

    it('folds at the observer\'s first entry, before its first paint, from the widths measured once', async() => {
        (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900, 700, 300]);
        const page = await mount();
        await resize(800);
        expect(page.line.classList.contains('dim-line--s1')).toBe(true);
        expect(page.line.classList.contains('dim-line--over')).toBe(true);
        expect(Measure.stepWidths).toHaveBeenCalledTimes(1);
        expect(Measure.stepWidths).toHaveBeenCalledWith(page.line, null, 800);
        // The entry's content box is the width: nothing is read off the line for it.
        expect(Measure.contentWidth).not.toHaveBeenCalled();
    });

    it('reads no layout in the mount\'s commit: the line is drawn and said so, and measured at the first entry', async() => {
        const reads = layoutReads();
        const page = await mount();
        try {
            expect(page.line.hasAttribute(DRAWN)).toBe(true);
            expect(page.mounted).toHaveBeenCalledTimes(1);
            expect(Measure.stepWidths).not.toHaveBeenCalled();
            expect(Measure.contentWidth).not.toHaveBeenCalled();
            reads.forEach((spy) => expect(spy).not.toHaveBeenCalled());
            expect(page.line.className).not.toMatch(/dim-line--s\d/);
        } finally {
            reads.forEach((spy) => spy.mockRestore());
        }

        (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900]);
        await resize(1200);
        expect(Measure.stepWidths).toHaveBeenCalledTimes(1);
        expect(Measure.stepWidths).toHaveBeenCalledWith(page.line, null, 1200);
        expect(page.line.classList.contains('dim-line--s1')).toBe(true);
    });

    it('folds a line its first entry finds narrow straight to the two-line step, the scope on its own line', async() => {
        (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900, 700, 300]);
        const page = await mount();
        expect(page.line.style.visibility).toBe('hidden');
        await resize(500);
        expect(page.line.classList.contains('dim-line--s2')).toBe(true);
        expect(page.line.classList.contains('dim-line--over')).toBe(true);
        expect(page.line.style.visibility).toBe('');
        expect(Measure.stepWidths).toHaveBeenCalledTimes(1);
        const order = Array.from(page.line.children)
            .map((child) => child.getAttribute('data-region') || child.getAttribute('data-action'));
        expect(order.indexOf('dim-vitrine-group')).toBe(order.indexOf('dim-search-close') + 1);
    });

    it('leaves an answer and the fonts drawn before the first entry to that entry\'s measure', async() => {
        Object.defineProperty(document, 'fonts', {value: {ready: Promise.resolve()}, configurable: true});
        try {
            const page = await mount();
            const answer = (count: number) => tell(page.line, EVENTS.answer, {
                scope: [{id: 'dim-chip-7-all', value: '', label: 'All', count: count, hascount: true, checked: true}],
                favourites: {available: true, pressed: false, count: 0},
                filtercount: 0,
                filtercountlabel: '',
            });
            await answer(8);
            expect(page.line.querySelector('label[for="dim-chip-7-all"]')?.textContent).toBe('All 8');
            expect(Measure.stepWidths).not.toHaveBeenCalled();

            (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900]);
            await resize(1200);
            expect(Measure.stepWidths).toHaveBeenCalledTimes(1);
            // Once measured, a count with as many digits keeps every width (the counts are tabular figures)...
            await answer(9);
            expect(page.line.querySelector('label[for="dim-chip-7-all"]')?.textContent).toBe('All 9');
            expect(Measure.stepWidths).toHaveBeenCalledTimes(1);
            // ...and one that gains a digit is measured again at once.
            (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900]);
            (Measure.contentWidth as jest.Mock).mockReturnValueOnce(1200);
            await answer(10);
            expect(Measure.stepWidths).toHaveBeenCalledTimes(2);
        } finally {
            delete (document as unknown as {fonts?: unknown}).fonts;
        }
    });

    it('measures in the mount\'s commit where there is no ResizeObserver', async() => {
        delete (window as unknown as {ResizeObserver?: unknown}).ResizeObserver;
        (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900, 700, 300]);
        (Measure.contentWidth as jest.Mock).mockReturnValueOnce(800);
        const page = await mount();
        expect(Measure.contentWidth).toHaveBeenCalledTimes(1);
        expect(Measure.stepWidths).toHaveBeenCalledWith(page.line, null, 800);
        expect(page.line.classList.contains('dim-line--s1')).toBe(true);
        expect(page.line.classList.contains('dim-line--over')).toBe(true);
        // The fallback measures a line already drawn, whose search the stylesheet shows as its button.
        expect(page.line.hasAttribute(DRAWN)).toBe(true);
        expect(page.mounted).toHaveBeenCalledTimes(1);
        // Measured in the commit, folded before the paint: nothing to hide.
        expect(page.line.style.visibility).toBe('');
    });

    it('stays the line at the full step where its first entry cannot measure it', async() => {
        (Measure.stepWidths as jest.Mock).mockImplementationOnce(() => {
            throw new Error('No layout.');
        });
        const page = await mount();
        expect(page.line.style.visibility).toBe('hidden');
        await resize(700);
        expect(page.line.hasAttribute(DRAWN)).toBe(true);
        expect(page.line.className).not.toMatch(/dim-line--s\d/);
        expect(page.line.querySelector('input[type="search"]')).not.toBeNull();
        expect(page.line.style.visibility).toBe('');
    });

    it('hides the line from its commit to its first fold, then gives it back its own inline visibility', async() => {
        const page = await mount([], (server) => {
            server.line.style.visibility = 'visible';
        });
        expect(page.line.style.visibility).toBe('hidden');
        expect(page.mounted).toHaveBeenCalledTimes(1);

        (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900]);
        await resize(1200);
        expect(page.line.classList.contains('dim-line--s1')).toBe(true);
        expect(page.line.style.visibility).toBe('visible');
        // A later entry leaves it as it is.
        await resize(1100);
        expect(page.line.style.visibility).toBe('visible');
    });

    it('gives the line back its visibility when it is unmounted before its first fold', async() => {
        const page = drawPage([]);
        const root = createRoot(page.line);
        await act(async() => {
            root.render(<FilterLine form={FORM} />);
        });
        expect(page.line.style.visibility).toBe('hidden');
        await act(async() => {
            root.unmount();
        });
        expect(page.line.style.visibility).toBe('');
    });

    it('takes a step from a new width alone, the scope on a line of its own at the second, its focus kept', async() => {
        (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900, 700, 300]);
        const page = await mount();
        await resize(1000);
        expect(page.line.classList.contains('dim-line--s1')).toBe(true);
        expect(page.line.classList.contains('dim-line--over')).toBe(false);
        const cell = page.line.querySelector('#dim-chip-7-enrolled') as HTMLInputElement;
        await act(async() => cell.focus());

        await resize(500);

        expect(page.line.classList.contains('dim-line--s2')).toBe(true);
        expect(page.line.classList.contains('dim-line--over')).toBe(true);
        expect(Measure.stepWidths).toHaveBeenCalledTimes(1);
        const order = Array.from(page.line.children).map((child) => child.getAttribute('data-region') || child.getAttribute('data-action'));
        expect(order.indexOf('dim-vitrine-group')).toBe(order.indexOf('dim-search-close') + 1);
        expect(document.activeElement).toBe(page.line.querySelector('#dim-chip-7-enrolled'));
    });

    it('grows the search over the line where the field does not fit and closes back to the control that opened it', async() => {
        (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900, 700, 300]);
        const page = await mount([], empty);
        await resize(800);
        const opener = page.line.querySelector('[data-action="dim-search-open"]') as HTMLButtonElement;
        await act(async() => opener.focus());
        fireEvent.click(opener);
        expect(page.line.classList.contains('dim-line--searching')).toBe(true);
        expect(page.line.classList.contains('dim-line--open')).toBe(false);
        expect(opener.getAttribute('aria-expanded')).toBe('true');
        expect(document.activeElement).toBe(page.field);
        expect(listbox(page.line).hidden).toBe(false);

        // Only the opener takes up room, as in a browser where the field is folded away: jsdom lays nothing out.
        const layout = jest.spyOn(Element.prototype, 'getClientRects').mockImplementation(function(this: Element) {
            return (this === opener ? [{}] : []) as unknown as DOMRectList;
        });
        try {
            // Changes that must make it fail: closing without handing the focus back, or not at all.
            fireEvent.keyDown(page.field, {key: 'Escape'});
            expect(page.line.classList.contains('dim-line--searching')).toBe(false);
            expect(listbox(page.line).hidden).toBe(true);
            expect(document.activeElement).toBe(opener);
        } finally {
            layout.mockRestore();
        }
    });

    it('folds the search to its button at rest at every step, and opens it in place from the button', async() => {
        (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900, 700, 300]);
        const page = await mount([], empty);
        await resize(1000);
        const opener = page.line.querySelector('[data-action="dim-search-open"]') as HTMLButtonElement;
        // Changes that must make it fail: the field left open at rest on a line with room for it.
        expect(page.line.classList.contains('dim-line--open')).toBe(false);
        expect(page.line.classList.contains('dim-line--searching')).toBe(false);
        expect(opener.getAttribute('aria-expanded')).toBe('false');

        await act(async() => opener.focus());
        fireEvent.click(opener);
        expect(page.line.classList.contains('dim-line--open')).toBe(true);
        expect(page.line.classList.contains('dim-line--searching')).toBe(false);
        expect(opener.getAttribute('aria-expanded')).toBe('true');
        expect(document.activeElement).toBe(page.field);

        // The focus leaving an empty field folds it.
        const star = page.line.querySelector('[data-region="dim-line-star"]') as HTMLButtonElement;
        await act(async() => {
            fireEvent.focusOut(page.field, {relatedTarget: star});
        });
        expect(page.line.classList.contains('dim-line--open')).toBe(false);
        expect(opener.getAttribute('aria-expanded')).toBe('false');
    });

    it('keeps the search open in place while it holds a text, and the eraser empties it and keeps it open', async() => {
        (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900, 700, 300]);
        // The example's field holds a term: the first fold opens it in place.
        const page = await mount();
        expect(page.field.value).toBe('rescue');
        await resize(1000);
        expect(page.line.classList.contains('dim-line--open')).toBe(true);

        const star = page.line.querySelector('[data-region="dim-line-star"]') as HTMLButtonElement;
        await act(async() => page.field.focus());
        await act(async() => {
            fireEvent.focusOut(page.field, {relatedTarget: star});
        });
        expect(page.line.classList.contains('dim-line--open')).toBe(true);

        const eraser = page.line.querySelector('[data-action="dim-search-clear"]') as HTMLButtonElement;
        await act(async() => page.field.focus());
        await act(async() => {
            fireEvent.focusOut(page.field, {relatedTarget: eraser});
            fireEvent.click(eraser);
        });
        expect(page.field.value).toBe('');
        expect(document.activeElement).toBe(page.field);
        expect(page.line.classList.contains('dim-line--open')).toBe(true);

        // Escape folds it back to its button, whatever it holds.
        fireEvent.change(page.field, {target: {value: 'fire'}});
        fireEvent.keyDown(page.field, {key: 'Escape'});
        expect(page.line.classList.contains('dim-line--open')).toBe(false);

        // A term the listing takes away while nothing holds the search folds it too.
        fireEvent.click(page.line.querySelector('[data-action="dim-search-open"]') as HTMLButtonElement);
        expect(page.line.classList.contains('dim-line--open')).toBe(true);
        await act(async() => (document.activeElement as HTMLElement).blur());
        await tell(page.line, EVENTS.state, {query: ''});
        expect(page.line.classList.contains('dim-line--open')).toBe(false);
    });

    it('folds a search that holds a text where it would cover the line, and opens it in place again with room', async() => {
        (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900, 700, 300]);
        const page = await mount();
        await resize(800);
        expect(page.line.classList.contains('dim-line--over')).toBe(true);
        expect(page.line.classList.contains('dim-line--searching')).toBe(false);
        expect(page.line.classList.contains('dim-line--open')).toBe(false);
        await resize(1000);
        expect(page.line.classList.contains('dim-line--open')).toBe(true);
    });

    it('names every icon-only control and repeats its name in the bar\'s tooltip, with no title', async() => {
        const page = await mount();
        expect(page.line.querySelectorAll('[title]')).toHaveLength(0);
        const tips = Array.from(page.line.querySelectorAll<HTMLElement>('[data-dim-tip]'));
        expect(tips.map((control) => control.getAttribute('data-dim-tip'))).toEqual(
            ['Favourites', 'Filters', 'Organise', 'List', 'Cards', 'Search courses', 'Clear text', 'Close search']
        );
        tips.forEach((control) => {
            const word = control.querySelector(':scope > .visually-hidden');
            const name = control.getAttribute('aria-label') || (word ? (word.textContent || '').trim() : '');
            expect(name.startsWith(control.getAttribute('data-dim-tip') || '-')).toBe(true);
        });
    });

    it('puts the tooltips off on Escape and back on another control', async() => {
        const page = await mount();
        const star = page.line.querySelector('[data-region="dim-line-star"]') as HTMLButtonElement;
        const organise = page.line.querySelector('[data-region="dim-organise"] > summary') as HTMLElement;
        await act(async() => star.focus());
        fireEvent.keyDown(star, {key: 'Escape'});
        expect(page.line.classList.contains('dim-line--tipsoff')).toBe(true);
        // The same control again leaves them off; another one brings them back.
        star.dispatchEvent(pointer('pointerover', 'mouse'));
        expect(page.line.classList.contains('dim-line--tipsoff')).toBe(true);
        organise.dispatchEvent(pointer('pointerover', 'mouse'));
        expect(page.line.classList.contains('dim-line--tipsoff')).toBe(false);
    });

    it('draws the answer\'s result count on the bar', async() => {
        const page = await mount();
        const total = page.line.querySelector('[data-region="dim-line-total"]') as HTMLElement;
        expect(total.textContent).toBe('16');
        expect(total.getAttribute('aria-hidden')).toBe('true');
        await tell(page.line, EVENTS.answer, {total: '1.234', filtercount: 0, filtercountlabel: ''});
        expect(total.textContent).toBe('1.234');
    });

    it('closes the Filters panel from its "Show" and gives its summary the focus', async() => {
        const page = await mount();
        const filters = page.line.querySelector('[data-region="dim-filters"]') as HTMLDetailsElement;
        filters.open = true;
        const show = page.line.querySelector('[data-action="dim-filters-show"]') as HTMLButtonElement;
        expect(show.textContent).toBe('Show 16');
        fireEvent.click(show);
        expect(filters.open).toBe(false);
        expect(document.activeElement).toBe(filters.querySelector('summary'));
    });

    it('imports the preview on the first pointer on the toolbar, and previews the active suggestion', async() => {
        const page = await mount([{chip: 'pending', fields: '12:2'}, {chip: 'pending', fields: '12:1'}], empty);
        const label = page.line.querySelector('label[for="dim-cf-12-2"]') as HTMLElement;
        label.dispatchEvent(pointer('pointerover', 'mouse'));
        await waitFor(() => expect(globalThis.pendingStack).toContain('theme_dimensions/filterline:preview'));
        await waitFor(() => expect(globalThis.completeStack).toContain('theme_dimensions/filterline:preview'));
        label.dispatchEvent(pointer('pointerover', 'mouse'));
        expect(page.cards.map((card) => card.classList.contains('dim-card--lit'))).toEqual([true, false]);
    });
});
