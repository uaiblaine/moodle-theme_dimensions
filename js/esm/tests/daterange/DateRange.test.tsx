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
 * Tests for the logged-in showcase's calendar component, by what a reader and the listing see.
 *
 * The component is rendered inside a region like the toolbar's, with a control outside it, and is
 * driven through the hooks the template category_daterange writes (the ones the Behat steps and the
 * stylesheet read). The listing is the region's listeners: what it is told (pick) and what it tells
 * (set). The suite runs west of UTC with daylight saving (./zone.ts).
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, fireEvent, render, waitFor} from '@testing-library/react';
import * as stringUtils from '@moodle/lms/core/stringUtils';
import DateRange, {EVENTS} from '../../src/daterange/DateRange';
import {westOfUtc} from './zone';

/** The day the site calls today in every test: the eve of the United States' spring transition. */
const TODAY = '2026-03-07';

/** The component's strings. */
const STRINGS: Record<string, string> = {
    'category_daterange': 'Period',
    'category_datefrom': 'From',
    'category_dateto': 'To',
    'category_dateclearfrom': 'Clear the first day',
    'category_dateclearto': 'Clear the last day',
    'category_dateprevmonth': 'Previous month',
    'category_datenextmonth': 'Next month',
    'category_dateclear': 'Clear the period',
    'category_datedone': 'Done',
    'category_datechoosefrom': 'Choose the first day',
    'category_datechooseto': 'Choose the last day',
};

let restore: () => void = () => undefined;
let wide = true;
const widths: Array<() => void> = [];

beforeAll(() => {
    restore = westOfUtc();
});

afterAll(() => {
    restore();
});

beforeEach(() => {
    wide = true;
    widths.length = 0;
    document.documentElement.lang = 'en';
    Object.entries(STRINGS).forEach(([key, value]) => mockString(key, 'theme_dimensions', value));
    window.matchMedia = jest.fn().mockImplementation((query: string) => ({
        media: query,
        get matches() {
            return wide;
        },
        addEventListener: (type: string, listener: () => void) => widths.push(listener),
        removeEventListener: jest.fn(),
    }));
});

afterEach(() => {
    document.body.replaceChildren();
});

/** The server's values, any of which a test may change. */
type Props = Partial<{id: number | string, firstday: number | string, today: string, from: string, to: string}>;

type Mounted = {
    region: HTMLElement,
    outside: HTMLButtonElement,
    picks: Array<{from: string, to: string}>,
    mounted: jest.Mock,
};

/**
 * Render the component in a region like the toolbar's and wait for it to say it is drawn.
 *
 * @param {object} props The server's values.
 * @param {boolean} expectMount Whether to wait for the mounted event.
 * @returns {Promise} The region, a control outside it, the picks it was told and the mounted listener.
 */
const mount = async(props: Props, expectMount = true): Promise<Mounted> => {
    const region = document.createElement('div');
    region.dataset.region = 'dim-daterange';
    const container = document.createElement('div');
    region.append(container);
    const outside = document.createElement('button');
    outside.type = 'button';
    document.body.append(region, outside);
    const picks: Array<{from: string, to: string}> = [];
    region.addEventListener(EVENTS.pick, (event) => picks.push((event as CustomEvent).detail));
    const mounted = jest.fn();
    region.addEventListener(EVENTS.mounted, mounted);
    await act(async() => {
        render(<DateRange id={7} firstday={0} today={TODAY} {...props} />, {container});
    });
    if (expectMount) {
        await waitFor(() => expect(mounted).toHaveBeenCalledTimes(1));
    }
    return {region, outside, picks, mounted};
};

/**
 * One element by selector, which must exist.
 *
 * @param {string} selector The selector.
 * @returns {HTMLElement} The element.
 */
const one = (selector: string): HTMLElement => {
    const element = document.querySelector<HTMLElement>(selector);
    if (!element) {
        throw new Error(`Nothing matches ${selector}`);
    }
    return element;
};

/**
 * A box, by its bound.
 *
 * @param {string} bound from or to.
 * @returns {HTMLElement} The box.
 */
const box = (bound: string): HTMLElement => one(`[data-action="dim-daterange-open"][data-bound="${bound}"]`);

/**
 * A box's clearing control.
 *
 * @param {string} bound from or to.
 * @returns {HTMLElement} The control.
 */
const clear = (bound: string): HTMLElement => one(`[data-action="dim-daterange-clear"][data-bound="${bound}"]`);

/**
 * A day cell.
 *
 * @param {string} iso The day.
 * @returns {HTMLElement} The cell.
 */
const cell = (iso: string): HTMLElement => one(`[data-region="dim-daterange-day"][data-date="${iso}"]`);

/**
 * The popover.
 *
 * @returns {HTMLElement} The popover.
 */
const pop = (): HTMLElement => one('[data-region="dim-daterange-pop"]');

/**
 * What a box shows.
 *
 * @param {string} bound from or to.
 * @returns {string} The box's value.
 */
const shown = (bound: string): string => box(bound).querySelector('[data-region="dim-daterange-value"]')?.textContent || '';

/**
 * The months' captions on screen.
 *
 * @returns {Array} Each visible month's caption.
 */
const captions = (): string[] => Array.from(document.querySelectorAll<HTMLElement>('[data-region="dim-daterange-monthblock"]'))
    .filter((block) => !block.hidden)
    .map((block) => block.querySelector('[data-region="dim-daterange-month"]')?.textContent || '');

/**
 * Press an element.
 *
 * @param {HTMLElement} element What is pressed.
 * @returns {void}
 */
const press = (element: HTMLElement): void => {
    act(() => {
        fireEvent.click(element);
    });
};

/**
 * Press a key on an element.
 *
 * @param {HTMLElement} element Where the key is pressed.
 * @param {string} name The key.
 * @returns {void}
 */
const key = (element: HTMLElement, name: string): void => {
    act(() => {
        fireEvent.keyDown(element, {key: name});
    });
};

describe('theme_dimensions/daterange/DateRange', () => {
    it('draws the template\'s control, says so on the region and holds the pending counter until then', async() => {
        const {mounted} = await mount({});
        expect(mounted).toHaveBeenCalledTimes(1);
        expect(mounted.mock.calls[0][0].bubbles).toBe(true);
        expect(one('[data-region="dim-daterange-field"]').classList.contains('dim-daterange')).toBe(true);
        expect(one('.dim-daterange__boxes').getAttribute('aria-label')).toBe('Period');
        expect(box('from').id).toBe('dim-daterange-7-from');
        expect(box('to').id).toBe('dim-daterange-7-to');
        expect(pop().id).toBe('dim-daterange-pop-7');
        expect(pop().hidden).toBe(true);
        ['from', 'to'].forEach((bound) => {
            expect(box(bound).getAttribute('aria-controls')).toBe('dim-daterange-pop-7');
            expect(box(bound).getAttribute('aria-expanded')).toBe('false');
            expect(clear(bound).hidden).toBe(true);
        });
        expect(one('[data-action="dim-daterange-done"]').textContent).toBe('Done');
        expect(one('[data-action="dim-daterange-clear"]:not([data-bound])').textContent).toBe('Clear the period');
        expect(pendingStack).toContain('theme_dimensions/daterange:mount');
        expect(completeStack).toContain('theme_dimensions/daterange:mount');
    });

    it('draws nothing, asks nothing and says nothing without the site\'s today', async() => {
        const strings = jest.spyOn(stringUtils, 'getRequestedStrings');
        strings.mockClear();
        for (const today of ['', '2026-02-30', 'today']) {
            const {mounted, region} = await mount({today: today}, false);
            expect(region.querySelector('[data-region="dim-daterange-field"]')).toBeNull();
            expect(mounted).not.toHaveBeenCalled();
            document.body.replaceChildren();
        }
        expect(strings).not.toHaveBeenCalled();
    });

    it('marks the site\'s today, never the browser\'s, and opens on its month with no period', async() => {
        await mount({today: '2031-06-15'});
        press(box('from'));
        expect(captions()).toEqual(['June 2031', 'July 2031']);
        const current = document.querySelectorAll('[aria-current="date"]');
        expect(current).toHaveLength(1);
        expect((current[0] as HTMLElement).dataset.date).toBe('2031-06-15');
        expect(document.activeElement).toBe(cell('2031-06-15'));
    });

    it('opens on the months of the period in force, wherever the reader last left the months', async() => {
        await mount({from: '2026-06-10'});
        press(box('to'));
        expect(captions()).toEqual(['June 2026', 'July 2026']);
        expect(document.activeElement).toBe(cell('2026-06-10'));
        press(one('[data-action="dim-daterange-next"]'));
        press(one('[data-action="dim-daterange-next"]'));
        expect(captions()).toEqual(['August 2026', 'September 2026']);
        key(cell('2026-08-01'), 'Escape');
        press(box('from'));
        expect(captions()).toEqual(['June 2026', 'July 2026']);
    });

    it('shows the period it was handed, put in order, a bound that is no date dropped', async() => {
        await mount({from: '2026-03-20', to: '2026-03-10'});
        expect(shown('from')).toBe('Mar 10, 2026');
        expect(shown('to')).toBe('Mar 20, 2026');
        expect(one('.dim-daterange').classList.contains('dim-daterange--set')).toBe(true);
        expect(box('from').querySelector('[data-region="dim-daterange-label"]')?.classList.contains('visually-hidden')).toBe(true);
        expect(clear('from').hidden).toBe(false);
        document.body.replaceChildren();

        await mount({from: '2026-09-31', to: '2026-03-10'});
        expect(shown('from')).toBe('');
        expect(shown('to')).toBe('Mar 10, 2026');
        expect(box('from').querySelector('[data-region="dim-daterange-label"]')?.classList.contains('visually-hidden')).toBe(false);
    });

    it('opens one popover from either box, in that box\'s mode', async() => {
        await mount({});
        press(box('to'));
        expect(pop().hidden).toBe(false);
        expect(box('from').getAttribute('aria-expanded')).toBe('true');
        expect(box('to').getAttribute('aria-expanded')).toBe('true');
        expect(box('to').classList.contains('dim-daterange__field--active')).toBe(true);
        expect(box('from').classList.contains('dim-daterange__field--active')).toBe(false);
        expect(one('[data-region="dim-daterange-prompt"]').textContent).toBe('Choose the last day');
        expect(one('[data-region="dim-daterange-prompt"]').getAttribute('aria-live')).toBe('polite');
    });

    it('takes a press in the start\'s mode as the start, moves to the end and applies nothing', async() => {
        const {picks} = await mount({});
        press(box('from'));
        press(cell('2026-03-10'));
        expect(shown('from')).toBe('Mar 10, 2026');
        expect(one('[data-region="dim-daterange-prompt"]').textContent).toBe('Choose the last day');
        expect(box('to').classList.contains('dim-daterange__field--active')).toBe(true);
        expect(pop().hidden).toBe(false);
        expect(document.activeElement).toBe(cell('2026-03-10'));
        expect(picks).toEqual([]);

        // The second press is the end, and the two describe a range.
        press(cell('2026-03-14'));
        expect(shown('to')).toBe('Mar 14, 2026');
        expect(cell('2026-03-10').classList.contains('dim-daterange__day--start')).toBe(true);
        expect(cell('2026-03-14').classList.contains('dim-daterange__day--end')).toBe(true);
        expect(cell('2026-03-12').classList.contains('dim-daterange__day--in')).toBe(true);
        expect(cell('2026-03-12').getAttribute('aria-selected')).toBe('true');
        expect(cell('2026-03-15').getAttribute('aria-selected')).toBe('false');
        expect(cell('2026-03-09').classList.contains('dim-daterange__day--in')).toBe(false);
        expect(picks).toEqual([]);
    });

    it('drops an end a later start would run backwards from', async() => {
        await mount({from: '2026-03-02', to: '2026-03-05'});
        press(box('from'));
        press(cell('2026-03-09'));
        expect(shown('from')).toBe('Mar 9, 2026');
        expect(shown('to')).toBe('');
        expect(one('[data-region="dim-daterange-prompt"]').textContent).toBe('Choose the last day');
    });

    it('takes a day before the start, in the end\'s mode, as a new start and still asks for the end', async() => {
        await mount({});
        press(box('from'));
        press(cell('2026-03-10'));
        press(cell('2026-03-04'));
        expect(shown('from')).toBe('Mar 4, 2026');
        expect(shown('to')).toBe('');
        expect(one('[data-region="dim-daterange-prompt"]').textContent).toBe('Choose the last day');
        press(cell('2026-03-06'));
        expect(shown('to')).toBe('Mar 6, 2026');
    });

    it('marks a bound alone on its one day, and tints nothing', async() => {
        await mount({from: '2026-03-10'});
        press(box('from'));
        expect(cell('2026-03-10').classList.contains('dim-daterange__day--edge')).toBe(true);
        expect(cell('2026-03-10').classList.contains('dim-daterange__day--start')).toBe(false);
        expect(document.querySelectorAll('.dim-daterange__day--in')).toHaveLength(0);
        expect(document.querySelectorAll('[aria-selected="true"]')).toHaveLength(1);
        document.body.replaceChildren();

        await mount({to: '2026-03-10'});
        press(box('to'));
        expect(cell('2026-03-10').classList.contains('dim-daterange__day--edge')).toBe(true);
        expect(cell('2026-03-10').classList.contains('dim-daterange__day--end')).toBe(false);
        expect(document.querySelectorAll('.dim-daterange__day--in')).toHaveLength(0);
        expect(document.querySelectorAll('[aria-selected="true"]')).toHaveLength(1);
    });

    it('moves an open popover to the other box\'s mode and keeps the draft', async() => {
        await mount({});
        press(box('from'));
        press(cell('2026-03-10'));
        press(box('from'));
        expect(pop().hidden).toBe(false);
        expect(shown('from')).toBe('Mar 10, 2026');
        expect(one('[data-region="dim-daterange-prompt"]').textContent).toBe('Choose the first day');
        expect(document.activeElement).toBe(cell('2026-03-10'));
    });

    it('applies the draft on Done, tells the listing once, and gives the box of the mode its focus', async() => {
        const {picks} = await mount({});
        press(box('from'));
        press(cell('2026-03-10'));
        press(cell('2026-03-14'));
        press(one('[data-action="dim-daterange-done"]'));
        expect(picks).toEqual([{from: '2026-03-10', to: '2026-03-14'}]);
        expect(pop().hidden).toBe(true);
        expect(box('from').getAttribute('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(box('to'));
        expect(shown('from')).toBe('Mar 10, 2026');

        // A period equal to the one in force asks for nothing.
        press(box('from'));
        press(one('[data-action="dim-daterange-done"]'));
        expect(picks).toHaveLength(1);
    });

    it('applies the draft on a press outside the open control, and only then', async() => {
        const {picks, outside} = await mount({});
        act(() => {
            fireEvent.pointerDown(outside);
        });
        expect(picks).toEqual([]);
        press(box('to'));
        press(cell('2026-03-12'));
        act(() => {
            fireEvent.pointerDown(cell('2026-03-13'));
        });
        expect(picks).toEqual([]);
        expect(pop().hidden).toBe(false);
        act(() => {
            fireEvent.pointerDown(outside);
        });
        expect(picks).toEqual([{from: '', to: '2026-03-12'}]);
        expect(pop().hidden).toBe(true);
    });

    it('discards the draft on Escape and gives the box its focus back', async() => {
        const {picks} = await mount({from: '2026-03-02'});
        press(box('from'));
        press(cell('2026-03-10'));
        key(cell('2026-03-10'), 'Escape');
        expect(pop().hidden).toBe(true);
        expect(shown('from')).toBe('Mar 2, 2026');
        expect(document.activeElement).toBe(box('to'));
        expect(picks).toEqual([]);
    });

    it('discards the draft when the keyboard walks out of it', async() => {
        const {picks, outside} = await mount({});
        press(box('from'));
        press(cell('2026-03-10'));
        await act(async() => {
            outside.focus();
            fireEvent.blur(cell('2026-03-10'), {relatedTarget: outside});
            await new Promise((resolve) => window.setTimeout(resolve, 0));
        });
        expect(pop().hidden).toBe(true);
        expect(shown('from')).toBe('');
        expect(picks).toEqual([]);
    });

    it('stays open while the focus moves inside it', async() => {
        await mount({});
        press(box('from'));
        await act(async() => {
            fireEvent.blur(cell(TODAY));
            box('to').focus();
            await new Promise((resolve) => window.setTimeout(resolve, 0));
        });
        expect(pop().hidden).toBe(false);
    });

    it('clears one bound at once with its box\'s control, keeping the other, and focuses the box', async() => {
        const {picks} = await mount({from: '2026-03-02', to: '2026-03-09'});
        press(clear('from'));
        expect(picks).toEqual([{from: '', to: '2026-03-09'}]);
        expect(shown('from')).toBe('');
        expect(clear('from').hidden).toBe(true);
        expect(clear('to').hidden).toBe(false);
        expect(document.activeElement).toBe(box('from'));

        press(clear('to'));
        expect(picks[1]).toEqual({from: '', to: ''});
        expect(document.activeElement).toBe(box('to'));
        expect(one('.dim-daterange').classList.contains('dim-daterange--set')).toBe(false);
    });

    it('clears the whole period from the footer, at once, with the start asked for again', async() => {
        const {picks} = await mount({from: '2026-03-02', to: '2026-03-09'});
        press(box('to'));
        press(one('[data-action="dim-daterange-clear"]:not([data-bound])'));
        expect(picks).toEqual([{from: '', to: ''}]);
        expect(pop().hidden).toBe(false);
        expect(one('[data-region="dim-daterange-prompt"]').textContent).toBe('Choose the first day');

        // Clearing a period that is not set fetches nothing.
        press(one('[data-action="dim-daterange-clear"]:not([data-bound])'));
        expect(picks).toHaveLength(1);
    });

    it('applies the draft as it stands when a box\'s clearing control is pressed mid-choice', async() => {
        const {picks} = await mount({to: '2026-03-20'});
        press(box('from'));
        press(cell('2026-03-10'));
        press(clear('to'));
        expect(picks).toEqual([{from: '2026-03-10', to: ''}]);
    });

    it('walks the days with the arrows and the months with the pages, across a daylight saving change', async() => {
        await mount({});
        press(box('from'));
        expect(document.activeElement).toBe(cell('2026-03-07'));
        key(cell('2026-03-07'), 'ArrowRight');
        expect(document.activeElement).toBe(cell('2026-03-08'));
        expect(cell('2026-03-08').getAttribute('aria-label')).toBe('March 8, 2026');
        key(cell('2026-03-08'), 'ArrowRight');
        expect(document.activeElement).toBe(cell('2026-03-09'));
        key(cell('2026-03-09'), 'ArrowUp');
        expect(document.activeElement).toBe(cell('2026-03-02'));
        key(cell('2026-03-02'), 'ArrowDown');
        expect(document.activeElement).toBe(cell('2026-03-09'));
        key(cell('2026-03-09'), 'ArrowLeft');
        expect(document.activeElement).toBe(cell('2026-03-08'));
        // One tab stop: only the day stood on is in the tab order.
        expect(document.querySelectorAll('[data-region="dim-daterange-day"][tabindex="0"]')).toHaveLength(1);

        // The pages move a month; a day past the months on screen brings its month on.
        key(cell('2026-03-08'), 'PageDown');
        expect(document.activeElement).toBe(cell('2026-04-08'));
        expect(captions()).toEqual(['March 2026', 'April 2026']);
        key(cell('2026-04-08'), 'PageDown');
        expect(document.activeElement).toBe(cell('2026-05-08'));
        expect(captions()).toEqual(['April 2026', 'May 2026']);
        key(cell('2026-05-08'), 'PageUp');
        key(cell('2026-04-08'), 'PageUp');
        key(cell('2026-03-08'), 'PageUp');
        expect(document.activeElement).toBe(cell('2026-02-08'));
        expect(captions()).toEqual(['February 2026', 'March 2026']);
    });

    it('crosses the year on the arrows, the pages and the month steps', async() => {
        wide = false;
        await mount({today: '2026-12-31'});
        press(box('from'));
        expect(captions()).toEqual(['December 2026']);
        key(cell('2026-12-31'), 'ArrowRight');
        expect(document.activeElement).toBe(cell('2027-01-01'));
        expect(captions()).toEqual(['January 2027']);
        key(cell('2027-01-01'), 'ArrowLeft');
        expect(captions()).toEqual(['December 2026']);
        press(one('[data-action="dim-daterange-next"]'));
        expect(captions()).toEqual(['January 2027']);
        expect(document.activeElement).toBe(cell('2027-01-01'));
        press(one('[data-action="dim-daterange-prev"]'));
        press(one('[data-action="dim-daterange-prev"]'));
        expect(captions()).toEqual(['November 2026']);
        expect(document.activeElement).toBe(cell('2026-11-01'));
    });

    it('leaves a box its own keys', async() => {
        await mount({});
        press(box('from'));
        box('from').focus();
        key(box('from'), 'ArrowRight');
        expect(document.activeElement).toBe(box('from'));
        expect(document.querySelector('[data-date="2026-03-07"]')?.getAttribute('tabindex')).toBe('0');
    });

    it('begins its weeks on the site\'s weekday, and on Sunday when that is no weekday', async() => {
        await mount({firstday: 1});
        press(box('from'));
        const heads = document.querySelectorAll<HTMLElement>('[data-region="dim-daterange-monthblock"] [role="columnheader"]');
        expect(heads[0].textContent).toBe('Mon');
        expect(heads[0].getAttribute('aria-label')).toBe('Monday');
        // March 2026 begins on a Sunday: six blanks before it in a Monday week.
        const first = document.querySelector('[data-region="dim-daterange-grid"] [role="row"]:nth-child(2)') as HTMLElement;
        expect(first.querySelectorAll('.dim-daterange__day--blank')).toHaveLength(6);
        document.body.replaceChildren();

        await mount({firstday: '9'});
        press(box('from'));
        expect(document.querySelector('[role="columnheader"]')?.textContent).toBe('Sun');
    });

    it('draws each month as a grid named by its caption, its days buttons named by their date', async() => {
        await mount({});
        press(box('from'));
        const grid = one('[data-region="dim-daterange-grid"]');
        expect(grid.getAttribute('role')).toBe('grid');
        const caption = one(`#${grid.getAttribute('aria-labelledby')}`);
        expect(caption.textContent).toBe('March 2026');
        expect(caption.id).toBe('dim-daterange-pop-7-month-0');
        expect(cell('2026-03-18').tagName).toBe('BUTTON');
        expect(cell('2026-03-18').getAttribute('type')).toBe('button');
        expect(cell('2026-03-18').getAttribute('role')).toBe('gridcell');
        expect(cell('2026-03-18').textContent).toBe('18');
        expect(cell('2026-03-18').getAttribute('aria-label')).toBe('March 18, 2026');
    });

    it('shows two months where they fit, one below, and keeps the focus when the width changes', async() => {
        await mount({});
        press(box('from'));
        const blocks = document.querySelectorAll<HTMLElement>('[data-region="dim-daterange-monthblock"]');
        expect(blocks[1].hidden).toBe(false);
        key(cell('2026-03-07'), 'PageDown');
        expect(document.activeElement).toBe(cell('2026-04-07'));
        act(() => {
            wide = false;
            widths.forEach((listener) => listener());
        });
        expect(blocks[1].hidden).toBe(true);
        expect(blocks[1].querySelectorAll('[data-region="dim-daterange-day"]')).toHaveLength(0);
        expect(captions()).toEqual(['April 2026']);
        expect(document.activeElement).toBe(cell('2026-04-07'));
    });

    it('takes the period the address says from the region, silently, put in order', async() => {
        const {region, picks} = await mount({});
        act(() => {
            region.dispatchEvent(new CustomEvent(EVENTS.set, {detail: {from: '2026-03-20', to: '2026-03-10'}}));
        });
        expect(shown('from')).toBe('Mar 10, 2026');
        expect(shown('to')).toBe('Mar 20, 2026');
        expect(picks).toEqual([]);

        // On an open popover it is what the boxes and the grid show, and Done applies nothing new.
        press(box('from'));
        act(() => {
            region.dispatchEvent(new CustomEvent(EVENTS.set, {detail: {from: '2026-03-05', to: ''}}));
        });
        expect(pop().hidden).toBe(false);
        expect(shown('from')).toBe('Mar 5, 2026');
        expect(cell('2026-03-05').classList.contains('dim-daterange__day--edge')).toBe(true);
        press(one('[data-action="dim-daterange-done"]'));
        expect(picks).toEqual([]);
    });

    it('tells the listing through an event that bubbles to the region', async() => {
        expect(EVENTS).toEqual({pick: 'dim-daterange:pick', set: 'dim-daterange:set', mounted: 'dim-daterange:mounted'});
        const {region} = await mount({});
        const heard: string[] = [];
        const onDocument = () => heard.push('document');
        document.addEventListener(EVENTS.pick, onDocument);
        region.addEventListener(EVENTS.pick, () => heard.push('region'));
        press(box('from'));
        press(cell('2026-03-10'));
        press(one('[data-action="dim-daterange-done"]'));
        document.removeEventListener(EVENTS.pick, onDocument);
        expect(heard).toEqual(['region', 'document']);
    });

    it('draws nothing and shows core\'s message when its strings cannot be had', async() => {
        const notification = {addNotification: jest.fn(), exception: jest.fn(() => Promise.resolve())};
        mockAmdModule('core/notification', notification);
        const failure = new Error('No strings');
        jest.spyOn(stringUtils, 'getRequestedStrings').mockImplementationOnce(
            (requests) => requests.map(() => Promise.reject(failure))
        );
        const {mounted, region} = await mount({}, false);
        await waitFor(() => expect(notification.exception).toHaveBeenCalledWith(failure));
        expect(region.querySelector('[data-region="dim-daterange-field"]')).toBeNull();
        expect(mounted).not.toHaveBeenCalled();
        expect(completeStack).toContain('theme_dimensions/daterange:mount');
    });
});
