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
 * Tests for the calendar page's client, by what a reader and a screen reader meet (PROPOSAL.md section 11.3).
 *
 * The component is rendered inside a page like calendar_page.mustache's (its server layer beside the mount); the
 * range, the state and core's event services are core/ajax's fetchOne, mocked. C3's day drawer, phone sheet and event
 * popover (PROPOSAL.md sections 13.2 and 13.3) are read here by what they draw and where the focus goes.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, fireEvent, render, waitFor} from '@testing-library/react';
import {fetchOne} from '@moodle/lms/core/ajax';
import Calendar, {QUIET} from '../../src/calendar/Calendar';
import {forget} from '../../src/calendar/description';
import {forget as forgetEditing} from '../../src/calendar/editing';
import type {Answer, Edit, Props} from '../../src/calendar/model';
import {MOVE_MINUTE, instantOf} from '../../src/calendar/moves';
import {STRINGS, answer, at, item, props} from './fixture';

jest.mock('@moodle/lms/core/ajax', () => ({fetchOne: jest.fn()}));

const fetched = fetchOne as jest.Mock;

beforeEach(() => {
    document.documentElement.lang = 'en';
    Object.entries(STRINGS).forEach(([key, value]) => mockString(key, 'theme_dimensions', value));
    fetched.mockReset();
    fetched.mockImplementation(({methodname, args}: {methodname: string, args: {from: string, to: string}}) =>
        Promise.resolve(methodname === 'theme_dimensions_get_calendar_range' ? answer([], args.from, args.to) : {}));
    window.history.replaceState({}, '', '/r.php/theme_dimensions/calendar');
    forget();
});

afterEach(() => {
    document.body.replaceChildren();
    jest.useRealTimers();
});

/**
 * Render the page and wait for its grid.
 *
 * @param {object} given The props.
 * @returns {Promise} The page's root.
 */
const mount = async(given: Props): Promise<HTMLElement> => {
    const page = document.createElement('div');
    page.dataset.region = 'dim-cal';
    const server = document.createElement('div');
    server.dataset.region = 'dim-cal-server';
    server.textContent = 'The month, as a list';
    const node = document.createElement('div');
    page.append(server, node);
    document.body.append(page);
    await act(async() => {
        render(<Calendar {...given} />, {container: node});
    });
    await waitFor(() => expect(page.querySelector('[role="grid"]')).not.toBeNull());
    return page;
};

/**
 * A day's button.
 *
 * @param {string} iso The day.
 * @returns {HTMLElement}
 */
const day = (iso: string): HTMLElement => {
    const button = document.querySelector<HTMLElement>(`[data-role="day"][data-day="${iso}"]`);
    if (!button) {
        throw new Error(`No day ${iso}`);
    }
    return button;
};

/**
 * The days in the Tab order.
 *
 * @returns {Array} Their dates.
 */
const tabbable = (): string[] => Array.from(document.querySelectorAll<HTMLElement>('[data-role="day"]'))
    .filter((button) => button.tabIndex === 0).map((button) => button.dataset.day || '');

/**
 * The open day's region.
 *
 * @returns {HTMLElement}
 */
const region = (): HTMLElement => {
    const found = document.querySelector<HTMLElement>('[data-region="dim-cal-day"]');
    if (!found) {
        throw new Error('No day is open');
    }
    return found;
};

/**
 * The open popover, or null.
 *
 * @returns {HTMLElement|null}
 */
const popover = (): HTMLElement | null => document.querySelector<HTMLElement>('[data-region="dim-cal-popover"]');

/**
 * The requests made for core's events.
 *
 * @returns {Array} Their event ids.
 */
const described = (): number[] => fetched.mock.calls
    .filter(([request]) => request.methodname === 'core_calendar_get_calendar_event_by_id')
    .map(([request]) => request.args.eventid);

/**
 * The range requests made.
 *
 * @returns {Array} Their arguments.
 */
const ranges = (): Array<Record<string, unknown>> => fetched.mock.calls
    .filter(([request]) => request.methodname === 'theme_dimensions_get_calendar_range').map(([request]) => request.args);

describe('the first paint', () => {
    it('draws from the embedded answer, asks for nothing, and hides the server\'s layer', async() => {
        const page = await mount(props([item({id: 1, name: 'Seminar', timestart: at('2026-11-10'), timeduration: 3 * 86400})]));
        expect(fetched).not.toHaveBeenCalled();
        expect(page.querySelector<HTMLElement>('[data-region="dim-cal-server"]')?.hidden).toBe(true);
        // The root is marked mounted: the stylesheet retires its placeholder and cancels the server layer's fail-safe.
        expect(page.classList.contains('dim-cal--mounted')).toBe(true);
        expect(page.querySelectorAll('.dim-cal__bar')).toHaveLength(1);
    });

    it('labels the grid by the live month caption, with a header row of weekdays', async() => {
        const page = await mount(props());
        const grid = page.querySelector('[role="grid"]') as HTMLElement;
        const caption = document.getElementById(grid.getAttribute('aria-labelledby') || '') as HTMLElement;
        expect(caption.textContent).toBe('November 2026');
        expect(caption.getAttribute('aria-live')).toBe('polite');
        expect(grid.querySelectorAll('[role="columnheader"]')).toHaveLength(7);
        expect(grid.querySelectorAll('[role="gridcell"]')).toHaveLength(35);
    });

    it('draws a ten-day item once per week it crosses, labelled with its whole span', async() => {
        const page = await mount(props([item({id: 1, name: 'Enrolment', timestart: at('2026-11-06'), timeduration: 10 * 86400})]));
        const bars = page.querySelectorAll('.dim-cal__bar');
        expect(bars).toHaveLength(3);
        bars.forEach((bar) => expect(bar.getAttribute('aria-label')).toBe('Enrolment, November 6 to November 15'));
        expect(bars[0].classList.contains('dim-cal__bar--clipend')).toBe(true);
        expect(bars[1].classList.contains('dim-cal__bar--clipstart')).toBe(true);
    });
});

describe('the keyboard', () => {
    it('has one Tab stop, today, whose label says the date, the count and today', async() => {
        await mount(props([item({id: 1, timestart: at('2026-11-17', 9)}), item({id: 2, timestart: at('2026-11-17', 10)})]));
        expect(tabbable()).toEqual(['2026-11-17']);
        expect(day('2026-11-17').getAttribute('aria-label')).toBe('Tuesday, November 17, 2026, 2 items, today');
        expect(day('2026-11-17').getAttribute('aria-current')).toBe('date');
        expect(day('2026-11-18').getAttribute('aria-label')).toBe('Wednesday, November 18, 2026, no items');
    });

    it('moves by day and by week with the arrows, and to the week\'s ends with Home and End', async() => {
        await mount(props());
        day('2026-11-17').focus();
        fireEvent.keyDown(day('2026-11-17'), {key: 'ArrowRight'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-18')));
        fireEvent.keyDown(day('2026-11-18'), {key: 'ArrowDown'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-25')));
        fireEvent.keyDown(day('2026-11-25'), {key: 'Home'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-22')));
        fireEvent.keyDown(day('2026-11-22'), {key: 'End'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-28')));
        expect(tabbable()).toEqual(['2026-11-28']);
        expect(fetched).not.toHaveBeenCalled();
    });

    it('PageDown asks for the next month and lands on the same day number, pushing the address', async() => {
        const push = jest.spyOn(window.history, 'pushState');
        await mount(props());
        day('2026-11-17').focus();
        fireEvent.keyDown(day('2026-11-17'), {key: 'PageDown'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-12-17')));
        expect(ranges()).toEqual([expect.objectContaining({from: '2026-11-29', to: '2027-01-02'})]);
        expect(push).toHaveBeenCalledTimes(1);
        expect(String(push.mock.calls[0][2])).toContain('month=2026-12');
        expect(document.querySelector('[data-region="dim-cal-caption"]')?.textContent).toBe('December 2026');
        push.mockRestore();
    });

    it('PageUp from the 31st lands on the last day of a shorter month', async() => {
        await mount(props([], {month: '2026-12', today: '2026-12-31'}));
        day('2026-12-31').focus();
        fireEvent.keyDown(day('2026-12-31'), {key: 'PageUp'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-30')));
    });

    it('an arrow past the grid goes to that day\'s month', async() => {
        await mount(props([], {today: '2026-11-03'}));
        day('2026-11-03').focus();
        fireEvent.keyDown(day('2026-11-03'), {key: 'ArrowUp'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-10-27')));
        expect(ranges()).toHaveLength(1);
    });

    it('Enter opens the day\'s drawer, which links no view of core\'s calendar', async() => {
        const page = await mount(props());
        fireEvent.click(day('2026-11-17'));
        const drawer = await waitFor(() => region());
        expect(drawer.dataset.day).toBe('2026-11-17');
        expect(page.querySelector('a[href*="calendar/view.php"]')).toBeNull();
    });

    it('opens a day on a click anywhere on it but its items, which open their popover alone', async() => {
        await mount(props([item({id: 3, name: 'Mentoring', timestart: at('2026-11-12', 14)})]));
        const ground = (iso: string): HTMLElement => day(iso).parentElement?.querySelector('.dim-cal__ground') as HTMLElement;
        fireEvent.click(ground('2026-11-19'), {detail: 1});
        await waitFor(() => expect(region().dataset.day).toBe('2026-11-19'));
        expect(new URL(window.location.href).searchParams.get('day')).toBe('2026-11-19');
        await act(async() => {
            fireEvent.click(gridItem(3), {detail: 1});
        });
        // The item's click does not open its day.
        expect(region().dataset.day).toBe('2026-11-19');
        expect(popover()?.dataset.id).toBe('3');
    });

    it('marks the day the mouse is over, and not one a finger touches', async() => {
        await mount(props());
        const cell = day('2026-11-19').parentElement as HTMLElement;
        const ground = cell.querySelector('.dim-cal__ground') as HTMLElement;
        /**
         * Move a pointer of a type over the ground, or out of it to the page (jsdom has no PointerEvent of its own).
         *
         * @param {string} type pointerover or pointerout.
         * @param {string} pointer The pointer's type.
         * @returns {void}
         */
        const move = (type: string, pointer: string): void => {
            const event = new MouseEvent(type, {bubbles: true, relatedTarget: type === 'pointerout' ? document.body : null});
            Object.defineProperty(event, 'pointerType', {value: pointer});
            act(() => {
                ground.dispatchEvent(event);
            });
        };
        move('pointerover', 'touch');
        expect(cell.hasAttribute('data-cal-hover')).toBe(false);
        move('pointerout', 'touch');
        move('pointerover', 'mouse');
        expect(cell.hasAttribute('data-cal-hover')).toBe(true);
        move('pointerout', 'mouse');
        expect(cell.hasAttribute('data-cal-hover')).toBe(false);
    });

    it('marks Saturday and Sunday by their date, whatever day the week begins on', async() => {
        await mount(props([], {firstday: 1}));
        const weekend = (iso: string): boolean => (day(iso).parentElement?.querySelector('.dim-cal__ground') as HTMLElement)
            .classList.contains('dim-cal__ground--weekend');
        expect([weekend('2026-11-14'), weekend('2026-11-15')]).toEqual([true, true]);
        expect([weekend('2026-11-13'), weekend('2026-11-16')]).toEqual([false, false]);
        const headers = Array.from(document.querySelectorAll<HTMLElement>('.dim-cal__weekday'));
        expect(headers.map((header) => header.classList.contains('dim-cal__weekday--weekend')))
            .toEqual([false, false, false, false, false, true, true]);
    });

    it('F2 moves into the day\'s items, Tab cycles them, Escape goes back to the day', async() => {
        await mount(props([
            item({id: 1, name: 'Seminar', timestart: at('2026-11-16'), timeduration: 3 * 86400}),
            item({id: 2, name: 'Mentoring', timestart: at('2026-11-17', 14)}),
            item({id: 3, name: 'Office hours', timestart: at('2026-11-17', 16)}),
        ]));
        day('2026-11-17').focus();
        fireEvent.keyDown(day('2026-11-17'), {key: 'F2'});
        const first = document.activeElement as HTMLElement;
        expect(first.classList.contains('dim-cal__bar')).toBe(true);
        fireEvent.keyDown(first, {key: 'Tab'});
        expect(document.activeElement?.textContent).toContain('Mentoring');
        fireEvent.keyDown(document.activeElement as HTMLElement, {key: 'Tab'});
        expect(document.activeElement?.textContent).toContain('Office hours');
        fireEvent.keyDown(document.activeElement as HTMLElement, {key: 'Tab'});
        expect(document.activeElement).toBe(first);
        fireEvent.keyDown(first, {key: 'Tab', shiftKey: true});
        expect(document.activeElement?.textContent).toContain('Office hours');
        fireEvent.keyDown(document.activeElement as HTMLElement, {key: 'Escape'});
        expect(document.activeElement).toBe(day('2026-11-17'));
    });

    it('F2 on an empty day stays on the day', async() => {
        await mount(props());
        day('2026-11-17').focus();
        fireEvent.keyDown(day('2026-11-17'), {key: 'F2'});
        expect(document.activeElement).toBe(day('2026-11-17'));
    });

    it('"+N" names the day it opens, and opens it', async() => {
        await mount(props(Array.from({length: 9}, (unused, index) => item({id: index + 1, timestart: at('2026-11-18', 8 + index)}))));
        const more = document.querySelector<HTMLElement>('.dim-cal__overflow') as HTMLElement;
        expect(more.getAttribute('aria-label')).toBe('5 more on November 18');
        fireEvent.click(more);
        await waitFor(() => expect(region().dataset.day).toBe('2026-11-18'));
        expect(region().querySelectorAll('[data-cal-pop]')).toHaveLength(9);
    });
});

describe('the keys and the filters', () => {
    it('the event types are one button and a dropdown of checkboxes; unchecked, a key is not asked for', async() => {
        const replace = jest.spyOn(window.history, 'replaceState');
        await mount(props([item({id: 1, key: 'group'})]));
        const button = document.querySelector<HTMLElement>('[data-action="dim-cal-types"]') as HTMLElement;
        const panel = document.getElementById(button.getAttribute('aria-controls') || '') as HTMLElement;
        expect(button.textContent).toBe('Event typesAll');
        expect(button.getAttribute('aria-expanded')).toBe('false');
        expect(panel.hidden).toBe(true);
        fireEvent.click(button);
        expect(button.getAttribute('aria-expanded')).toBe('true');
        expect(panel.hidden).toBe(false);
        const boxes = Array.from(panel.querySelectorAll<HTMLInputElement>('input[type="checkbox"]'));
        expect(boxes.map((box) => box.dataset.key)).toEqual(['site', 'category', 'course', 'group', 'user', 'activity', 'other',
            'open']);
        expect(boxes.every((box) => box.checked && box.closest('label') !== null)).toBe(true);
        const group = boxes[3];
        expect(group.closest('label')?.textContent).toBe('Group1');
        await act(async() => {
            fireEvent.click(group);
        });
        expect(group.checked).toBe(false);
        expect(group.closest('label')?.textContent).toBe('Group');
        expect(button.textContent).toBe('Event types7 of 8');
        expect(ranges()[0].keys).toEqual(['site', 'category', 'course', 'user', 'activity', 'other', 'open']);
        expect(replace.mock.calls.some(([, , url]) => String(url).includes('keys%5B%5D=site'))).toBe(true);
        replace.mockRestore();
    });

    it('the dropdown closes on Escape with the focus back on its button, and on a press outside', async() => {
        await mount(props());
        const button = document.querySelector<HTMLElement>('[data-action="dim-cal-types"]') as HTMLElement;
        const panel = document.getElementById(button.getAttribute('aria-controls') || '') as HTMLElement;
        fireEvent.click(button);
        const box = panel.querySelector<HTMLInputElement>('input[data-key="site"]') as HTMLInputElement;
        box.focus();
        fireEvent.keyDown(box, {key: 'Escape'});
        expect(panel.hidden).toBe(true);
        expect(document.activeElement).toBe(button);
        fireEvent.click(button);
        expect(panel.hidden).toBe(false);
        fireEvent.mouseDown(document.body);
        expect(panel.hidden).toBe(true);
    });

    it('Escape closes the dropdown after "Select all" and after a checkbox, from wherever the focus is', async() => {
        await mount(props([], {state: {
            off: ['site', 'group'], categoryid: 0, recursive: true, model: [], modality: [], city: '', actiononly: false,
            colour: 'key',
        }}));
        const button = document.querySelector<HTMLElement>('[data-action="dim-cal-types"]') as HTMLElement;
        const panel = document.getElementById(button.getAttribute('aria-controls') || '') as HTMLElement;
        fireEvent.click(button);
        const all = panel.querySelector<HTMLButtonElement>('[data-action="dim-cal-types-all"]') as HTMLButtonElement;
        await act(async() => {
            fireEvent.click(all);
        });
        // The pressed button is disabled now: the focus is outside the dropdown, on the body.
        document.body.focus();
        fireEvent.keyDown(document.body, {key: 'Escape'});
        expect(panel.hidden).toBe(true);
        expect(button.getAttribute('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(button);
        fireEvent.click(button);
        expect(panel.hidden).toBe(false);
        await act(async() => {
            fireEvent.click(panel.querySelector('input[data-key="user"]') as HTMLElement);
        });
        (document.activeElement as HTMLElement | null)?.blur();
        fireEvent.keyDown(document.body, {key: 'Escape'});
        expect(panel.hidden).toBe(true);
        expect(document.activeElement).toBe(button);
        // The listener is gone with the dropdown: a later Escape moves nothing.
        const elsewhere = day('2026-11-17');
        elsewhere.focus();
        fireEvent.keyDown(elsewhere, {key: 'Escape'});
        expect(document.activeElement).toBe(elsewhere);
    });

    it('"Select all" turns every key back on in one change', async() => {
        await mount(props([], {state: {
            off: ['site', 'group'], categoryid: 0, recursive: true, model: [], modality: [], city: '', actiononly: false,
            colour: 'key',
        }}));
        const all = document.querySelector<HTMLButtonElement>('[data-action="dim-cal-types-all"]') as HTMLButtonElement;
        expect(all.disabled).toBe(false);
        expect(document.querySelector('[data-action="dim-cal-types"]')?.textContent).toBe('Event types6 of 8');
        await act(async() => {
            fireEvent.click(all);
        });
        expect(ranges()).toHaveLength(1);
        expect(ranges()[0].keys).toHaveLength(8);
        expect(all.disabled).toBe(true);
    });

    it('"Clear filters" is drawn only while a filter is applied, and puts keys, filters and scope back at once', async() => {
        await mount(props());
        expect(document.querySelector('[data-action="dim-cal-clear"]')).toBeNull();
        document.body.replaceChildren();
        await mount(props([], {state: {
            off: ['site'], categoryid: 3, recursive: false, model: [1], modality: [1], city: 'Recife', actiononly: true,
            colour: 'category',
        }}));
        const clear = document.querySelector<HTMLElement>('[data-action="dim-cal-clear"]') as HTMLElement;
        expect(clear.closest('[data-region="dim-cal-more"]')).toBeNull();
        expect(document.querySelector('[data-region="dim-cal-more"] .dim-cal__clear')).toBeNull();
        await act(async() => {
            fireEvent.click(clear);
        });
        expect(ranges()).toHaveLength(1);
        expect(ranges()[0]).toEqual(expect.objectContaining({
            keys: ['site', 'category', 'course', 'group', 'user', 'activity', 'other', 'open'],
            categoryid: 0, recursive: true, model: [], modality: [], city: '', actiononly: false,
        }));
        expect(document.querySelector('[data-action="dim-cal-clear"]')).toBeNull();
        expect(document.activeElement).toBe(document.querySelector('[data-action="dim-cal-types"]'));
        expect(window.location.search).toContain('colour=category');
    });

    it('every key off asks the range nothing', async() => {
        await mount(props([], {state: {
            off: ['site', 'category', 'course', 'group', 'user', 'activity', 'other'],
            categoryid: 0, recursive: true, model: [], modality: [], city: '', actiononly: false, colour: 'key',
        }}));
        await act(async() => {
            fireEvent.click(document.querySelector('[data-key="open"]') as HTMLElement);
        });
        expect(ranges()).toEqual([]);
    });

    it('remembers a state once, after a quiet, and not again when nothing changed', async() => {
        jest.useFakeTimers();
        await mount(props());
        await act(async() => {
            fireEvent.click(document.querySelector('[data-key="site"]') as HTMLElement);
        });
        await act(async() => {
            fireEvent.click(document.querySelector('[data-key="user"]') as HTMLElement);
        });
        const remembers = () => fetched.mock.calls.filter(([request]) => request.methodname === 'theme_dimensions_remember_calendar_state');
        expect(remembers()).toHaveLength(0);
        await act(async() => {
            jest.advanceTimersByTime(QUIET);
        });
        expect(remembers()).toHaveLength(1);
        expect(remembers()[0][0].args.off).toEqual(['site', 'user']);
        await act(async() => {
            fireEvent.click(document.querySelector('[data-key="user"]') as HTMLElement);
        });
        await act(async() => {
            fireEvent.click(document.querySelector('[data-key="user"]') as HTMLElement);
        });
        await act(async() => {
            jest.advanceTimersByTime(QUIET);
        });
        expect(remembers()).toHaveLength(1);
    });

    it('the latest request wins', async() => {
        let release: (value: Answer) => void = () => undefined;
        fetched.mockImplementationOnce(() => new Promise((resolve) => {
            release = resolve;
        }));
        await mount(props());
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-caption"]')?.textContent).toBe('December 2026'));
        await act(async() => {
            release(answer([item({id: 9, name: 'Stale', timestart: at('2026-12-10', 9)})], '2026-11-29', '2027-01-02'));
        });
        expect(document.body.textContent).not.toContain('Stale');
    });

    it('the category tree is a tree, and choosing a category narrows the range', async() => {
        await mount(props());
        const button = document.querySelector<HTMLElement>('[data-region="dim-cal-category"] button') as HTMLElement;
        expect(button.getAttribute('aria-expanded')).toBe('false');
        fireEvent.click(button);
        expect(button.getAttribute('aria-expanded')).toBe('true');
        const tree = document.querySelector('[role="tree"]') as HTMLElement;
        const items = Array.from(tree.querySelectorAll<HTMLElement>('[role="treeitem"]'));
        expect(items.map((node) => node.dataset.id)).toEqual(['0', '3', '5']);
        expect(items[0].getAttribute('aria-selected')).toBe('true');
        expect(items[1].getAttribute('aria-expanded')).toBe('false');
        items[0].focus();
        fireEvent.keyDown(items[0], {key: 'ArrowDown'});
        await waitFor(() => expect(document.activeElement).toBe(items[1]));
        fireEvent.keyDown(items[1], {key: 'ArrowRight'});
        await waitFor(() => expect(tree.querySelector('[data-id="4"]')).not.toBeNull());
        expect(items[1].getAttribute('aria-expanded')).toBe('true');
        await act(async() => {
            fireEvent.keyDown(items[1], {key: 'Enter'});
        });
        expect(ranges()[0]).toEqual(expect.objectContaining({categoryid: 3, recursive: true}));
        expect(button.textContent).toContain('School of Government');
        fireEvent.keyDown(tree, {key: 'Escape'});
        expect(button.getAttribute('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(button);
    });

    it('a search shows the matches with their ancestors', async() => {
        await mount(props());
        fireEvent.click(document.querySelector('[data-region="dim-cal-category"] button') as HTMLElement);
        fireEvent.change(document.querySelector('[data-region="dim-cal-category"] input[type="search"]') as HTMLElement,
            {target: {value: 'gestao'}});
        expect(Array.from(document.querySelectorAll<HTMLElement>('[role="treeitem"]')).map((node) => node.dataset.id))
            .toEqual(['0']);
        fireEvent.change(document.querySelector('[data-region="dim-cal-category"] input[type="search"]') as HTMLElement,
            {target: {value: 'management'}});
        expect(Array.from(document.querySelectorAll<HTMLElement>('[role="treeitem"]')).map((node) => node.dataset.id))
            .toEqual(['0', '3', '4']);
    });

    it('"More filters" starts with every box on, and unchecking one asks the range for the others (R1c)', async() => {
        await mount(props());
        const button = document.querySelector<HTMLElement>('[data-region="dim-cal-more"] button') as HTMLElement;
        const box = (name: string, value: number) => document.querySelector<HTMLInputElement>(
            `[data-region="dim-cal-more"] input[name="${name}"][value="${value}"]`
        ) as HTMLInputElement;
        fireEvent.click(button);
        expect([box('model', 1).checked, box('model', 2).checked, box('modality', 1).checked]).toEqual([true, true, true]);
        expect(box('model', 1).dataset.on).toBe('1');
        expect(button.textContent).not.toContain('filters on');
        expect(box('modality', 1).disabled).toBe(true);
        await act(async() => {
            fireEvent.click(box('model', 2));
        });
        expect(ranges()[0]).toEqual(expect.objectContaining({model: [1]}));
        expect([box('model', 1).checked, box('model', 2).checked]).toEqual([true, false]);
        expect(box('model', 2).dataset.on).toBe('0');
        expect(box('model', 1).disabled).toBe(true);
        expect(button.textContent).toContain('1 filters on');
        expect(document.querySelector('[data-action="dim-cal-clear"]')).not.toBeNull();
        await act(async() => {
            fireEvent.click(box('model', 2));
        });
        expect(ranges()[1]).toEqual(expect.objectContaining({model: []}));
        expect(button.textContent).not.toContain('filters on');
        expect(document.querySelector('[data-action="dim-cal-clear"]')).toBeNull();
    });

    it('"More filters" reads a saved list as its boxes, and a list of every option narrows nothing', async() => {
        await mount(props([], {state: {
            off: [], categoryid: 0, recursive: true, model: [1, 2], modality: [], city: '', actiononly: false, colour: 'key',
        }}));
        const button = document.querySelector<HTMLElement>('[data-region="dim-cal-more"] button') as HTMLElement;
        expect(button.textContent).not.toContain('filters on');
        expect(document.querySelector('[data-action="dim-cal-clear"]')).toBeNull();
    });

    it('colours by category with the branding pair, whole', async() => {
        const given = props([item({id: 1, key: 'category', categoryid: 3, timestart: at('2026-11-10'), timeduration: 3 * 86400})]);
        given.answer.categories = [{id: 3, name: 'School', bg: '#0a4a85', ink: '#ffffff'}];
        given.state.colour = 'category';
        await mount(given);
        const bar = document.querySelector<HTMLElement>('.dim-cal__bar') as HTMLElement;
        expect(bar.style.getPropertyValue('--dim-cal-tint')).toBe('#0a4a85');
        expect(bar.style.getPropertyValue('--dim-cal-ink')).toBe('#ffffff');
    });
});

/**
 * A request whose answer the test releases.
 *
 * @returns {object} {promise, release, refuse}.
 */
const deferred = <T, >() => {
    let release: (value: T) => void = () => undefined;
    let refuse: (error: Error) => void = () => undefined;
    const promise = new Promise<T>((resolve, reject) => {
        release = resolve;
        refuse = reject;
    });
    return {promise: promise, release: release, refuse: refuse};
};

/**
 * Answer core's event service from a map of descriptions by id; anything else as the default mock does.
 *
 * @param {Function} answerFor What the service answers for an event id.
 * @returns {void}
 */
const describeWith = (answerFor: (id: number) => Promise<unknown>): void => {
    fetched.mockImplementation(({methodname, args}: {methodname: string, args: Record<string, unknown>}) => {
        if (methodname === 'core_calendar_get_calendar_event_by_id') {
            return answerFor(Number(args.eventid));
        }
        return Promise.resolve(methodname === 'theme_dimensions_get_calendar_range'
            ? answer([], String(args.from), String(args.to)) : {});
    });
};

/**
 * A grid item's element by its id.
 *
 * @param {number} id The item's id.
 * @param {string} source Its source.
 * @returns {HTMLElement}
 */
const gridItem = (id: number, source = 'core'): HTMLElement => {
    const selector = `[data-region="dim-cal-grid"] [data-cal-pop][data-source="${source}"][data-id="${id}"]`;
    return document.querySelector<HTMLElement>(selector) as HTMLElement;
};

describe('the day drawer', () => {
    it('opens on a day with the focus on its heading, the long date, and the address keeps day=', async() => {
        const replace = jest.spyOn(window.history, 'replaceState');
        const push = jest.spyOn(window.history, 'pushState');
        await mount(props([item({id: 1, name: 'Mentoring', timestart: at('2026-11-17', 14)})]));
        fireEvent.click(day('2026-11-17'));
        const drawer = await waitFor(() => region());
        const heading = document.getElementById(drawer.getAttribute('aria-labelledby') || '') as HTMLElement;
        expect(drawer.getAttribute('role')).toBe('region');
        expect(heading.textContent).toBe('Tuesday, November 17, 2026');
        expect(heading.getAttribute('aria-live')).toBe('polite');
        await waitFor(() => expect(document.activeElement).toBe(heading));
        expect(drawer.textContent).toContain('Mentoring');
        expect(new URL(window.location.href).searchParams.get('day')).toBe('2026-11-17');
        expect(replace).toHaveBeenCalled();
        expect(push).not.toHaveBeenCalled();
        replace.mockRestore();
        push.mockRestore();
    });

    it('closes on Escape, the focus back on the day, and the address loses day=', async() => {
        await mount(props());
        fireEvent.click(day('2026-11-17'));
        const drawer = await waitFor(() => region());
        fireEvent.keyDown(drawer.querySelector('h2') as HTMLElement, {key: 'Escape'});
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-day"]')).toBeNull());
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-17')));
        expect(new URL(window.location.href).searchParams.has('day')).toBe(false);
    });

    it('closes with its button too', async() => {
        await mount(props());
        fireEvent.click(day('2026-11-17'));
        fireEvent.click(await waitFor(() => region().querySelector('[data-action="dim-cal-day-close"]') as HTMLElement));
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-day"]')).toBeNull());
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-17')));
    });

    it('follows the selected day while it is open, the grid kept usable beside it', async() => {
        await mount(props());
        fireEvent.click(day('2026-11-17'));
        await waitFor(() => region());
        day('2026-11-17').focus();
        fireEvent.keyDown(day('2026-11-17'), {key: 'ArrowRight'});
        await waitFor(() => expect(region().dataset.day).toBe('2026-11-18'));
        expect(document.activeElement).toBe(day('2026-11-18'));
        expect(new URL(window.location.href).searchParams.get('day')).toBe('2026-11-18');
    });

    it('opens from the address the server kept, and a month change closes it', async() => {
        await mount(props([], {day: '2026-11-20'}));
        expect(region().dataset.day).toBe('2026-11-20');
        expect(tabbable()).toEqual(['2026-11-20']);
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-caption"]')?.textContent).toBe('December 2026'));
        expect(document.querySelector('[data-region="dim-cal-day"]')).toBeNull();
        expect(new URL(window.location.href).searchParams.has('day')).toBe(false);
    });

    it('ignores an open day its grid does not hold', async() => {
        await mount(props([], {day: '2027-01-20'}));
        expect(document.querySelector('[data-region="dim-cal-day"]')).toBeNull();
    });

    it('lays out the day: the all-day strip, the hour column, and a visible mark on what overlaps', async() => {
        await mount(props([
            item({id: 1, name: 'Week', timestart: at('2026-11-16'), timeduration: 3 * 86400}),
            item({id: 2, name: 'Mentoring', timestart: at('2026-11-17', 9), timeduration: 7200}),
            item({id: 3, name: 'Office hours', timestart: at('2026-11-17', 10), timeduration: 3600}),
            item({id: 4, name: 'Lunch', timestart: at('2026-11-17', 11), timeduration: 3600}),
        ]));
        fireEvent.click(day('2026-11-17'));
        const drawer = await waitFor(() => region());
        expect(drawer.querySelector('[data-region="dim-cal-allday"]')?.textContent).toContain('Week');
        const hours = drawer.querySelector('[data-region="dim-cal-hours"]') as HTMLElement;
        const marked = Array.from(hours.querySelectorAll<HTMLElement>('.dim-cal__dayitem--overlap'))
            .map((element) => element.querySelector('.dim-cal__dayname')?.textContent);
        expect(marked).toEqual(['Mentoring', 'Office hours']);
        expect(hours.querySelector('.dim-cal__dayitem--overlap')?.textContent).toContain('Overlaps');
        const office = hours.querySelector<HTMLElement>('[data-id="3"]') as HTMLElement;
        expect(office.style.getPropertyValue('--dim-cal-share-left')).toBe('0.5');
        expect(office.style.getPropertyValue('--dim-cal-share-width')).toBe('0.5');
    });

    it('opens the whole day in its scroll box on the busy band, with a fade on each end it can scroll to (R1c)', async() => {
        const tops: number[] = [];
        const box = (element: Element) => (element as HTMLElement).dataset?.region === 'dim-cal-hourscroll';
        const saved = ['scrollHeight', 'clientHeight', 'scrollTop']
            .map((name) => [name, Object.getOwnPropertyDescriptor(HTMLElement.prototype, name)]);
        Object.defineProperty(HTMLElement.prototype, 'scrollHeight', {configurable: true,
            get() {
                return box(this) ? 960 : 0;
            }});
        Object.defineProperty(HTMLElement.prototype, 'clientHeight', {configurable: true,
            get() {
                return box(this) ? 400 : 0;
            }});
        Object.defineProperty(HTMLElement.prototype, 'scrollTop', {configurable: true,
            get() {
                return tops.length ? tops[tops.length - 1] : 0;
            },
            set(value: number) {
                tops.push(value);
            }});
        try {
            await mount(props([item({id: 2, name: 'Mentoring', timestart: at('2026-11-17', 10), timeduration: 3600})]));
            fireEvent.click(day('2026-11-17'));
            const drawer = await waitFor(() => region());
            const scroll = drawer.querySelector('[data-region="dim-cal-hourscroll"]') as HTMLElement;
            expect(scroll.querySelectorAll('.dim-cal__hour')).toHaveLength(24);
            expect(tops).toEqual([9 * 40]);
            expect(scroll.tabIndex).toBe(-1);
            expect(drawer.querySelectorAll('.dim-cal__hourfade--up, .dim-cal__hourfade--down')).toHaveLength(2);
            expect(drawer.querySelector('.dim-cal__hourfade')?.getAttribute('aria-hidden')).toBe('true');
            await act(async() => {
                tops.push(0);
                fireEvent.scroll(scroll);
            });
            expect(drawer.querySelector('.dim-cal__hourfade--up')).toBeNull();
            expect(drawer.querySelector('.dim-cal__hourfade--down')).not.toBeNull();
        } finally {
            saved.forEach(([name, descriptor]) => {
                if (descriptor) {
                    Object.defineProperty(HTMLElement.prototype, name as string, descriptor as PropertyDescriptor);
                } else {
                    delete (HTMLElement.prototype as unknown as Record<string, unknown>)[name as string];
                }
            });
        }
    });

    it('says so when the day is empty', async() => {
        await mount(props());
        fireEvent.click(day('2026-11-17'));
        expect((await waitFor(() => region())).textContent).toContain('Nothing on this day.');
    });
});

describe('the event popover', () => {
    it('opens on a press with the focus on its name, and Escape gives the focus back to the item', async() => {
        describeWith(() => Promise.resolve({event: {description: '<p>Bring <strong>notes</strong></p>'}}));
        await mount(props([item({id: 7, name: 'Seminar', timestart: at('2026-11-10'), timeduration: 3 * 86400})]));
        const bar = gridItem(7);
        await act(async() => {
            fireEvent.click(bar);
        });
        const dialog = popover() as HTMLElement;
        expect(dialog.getAttribute('role')).toBe('dialog');
        expect(dialog.getAttribute('aria-modal')).toBeNull();
        const title = document.getElementById(dialog.getAttribute('aria-labelledby') || '') as HTMLElement;
        expect(title.textContent).toBe('Seminar');
        expect(document.activeElement).toBe(title);
        await waitFor(() => expect(dialog.querySelector('[data-region="dim-cal-description"]')?.innerHTML)
            .toBe('<p>Bring <strong>notes</strong></p>'));
        expect(dialog.textContent).toContain('November 10 to November 12');
        fireEvent.keyDown(title, {key: 'Escape'});
        expect(popover()).toBeNull();
        expect(document.activeElement).toBe(bar);
    });

    it('takes the focus only once it is shown, never while it is still measuring', async() => {
        describeWith(() => Promise.resolve({event: {description: ''}}));
        await mount(props([item({id: 8, name: 'Review', timestart: at('2026-11-17', 14)})]));
        const calls: boolean[] = [];
        const spy = jest.spyOn(HTMLElement.prototype, 'focus').mockImplementation(function(this: HTMLElement) {
            const dialog = this.closest('[data-region="dim-cal-popover"]');
            if (dialog) {
                calls.push(dialog.classList.contains('dim-cal__pop--measuring'));
            }
        });
        try {
            await act(async() => {
                fireEvent.click(gridItem(8));
            });
        } finally {
            spy.mockRestore();
        }
        expect(calls.length).toBeGreaterThan(0);
        expect(calls.every((measuring) => !measuring)).toBe(true);
    });

    it('opens on Enter and on Space from an item, and a modified press follows the link', async() => {
        describeWith(() => Promise.resolve({event: {description: ''}}));
        await mount(props([item({id: 2, name: 'Mentoring', timestart: at('2026-11-17', 14)})]));
        const line = gridItem(2);
        await act(async() => {
            fireEvent.keyDown(line, {key: 'Enter'});
        });
        expect(popover()?.dataset.id).toBe('2');
        fireEvent.keyDown(popover() as HTMLElement, {key: 'Escape'});
        await act(async() => {
            fireEvent.keyDown(line, {key: ' '});
        });
        expect(popover()?.dataset.id).toBe('2');
        fireEvent.keyDown(popover() as HTMLElement, {key: 'Escape'});
        // The page's own listener answers first; this one, on the window, sees whether the link was left to follow.
        const followed = jest.fn();
        window.addEventListener('click', (event) => {
            followed(event.defaultPrevented);
            event.preventDefault();
        }, {once: true});
        fireEvent.click(line, {ctrlKey: true});
        expect(followed).toHaveBeenCalledWith(false);
        expect(popover()).toBeNull();
    });

    it('reads the description once per event, and draws a placeholder until it comes', async() => {
        const wait = deferred<unknown>();
        describeWith(() => wait.promise);
        await mount(props([item({id: 2, name: 'Mentoring', timestart: at('2026-11-17', 14)})]));
        await act(async() => {
            fireEvent.click(gridItem(2));
        });
        expect(popover()?.textContent).toContain('Loading the description…');
        await act(async() => {
            wait.release({event: {description: '<p>Room 4</p>'}});
        });
        expect(popover()?.querySelector('[data-region="dim-cal-description"]')?.textContent).toBe('Room 4');
        fireEvent.keyDown(popover() as HTMLElement, {key: 'Escape'});
        await act(async() => {
            fireEvent.click(gridItem(2));
        });
        expect(popover()?.querySelector('[data-region="dim-cal-description"]')?.textContent).toBe('Room 4');
        expect(described()).toEqual([2]);
    });

    it('shows the latest popover\'s description: an earlier answer arriving late is dropped', async() => {
        const first = deferred<unknown>();
        const second = deferred<unknown>();
        describeWith((id) => (id === 1 ? first.promise : second.promise));
        await mount(props([
            item({id: 1, name: 'Mentoring', timestart: at('2026-11-17', 9)}),
            item({id: 2, name: 'Office hours', timestart: at('2026-11-17', 14)}),
        ]));
        await act(async() => {
            fireEvent.click(gridItem(1));
        });
        await act(async() => {
            fireEvent.click(gridItem(2));
        });
        expect(popover()?.dataset.id).toBe('2');
        await act(async() => {
            first.release({event: {description: '<p>For the first</p>'}});
        });
        expect(popover()?.textContent).not.toContain('For the first');
        expect(popover()?.textContent).toContain('Loading the description…');
        await act(async() => {
            second.release({event: {description: '<p>For the second</p>'}});
        });
        expect(popover()?.textContent).toContain('For the second');
    });

    it('draws nothing of a description core refuses, and asks nothing for an event course', async() => {
        describeWith(() => Promise.reject(new Error('nopermissiontoviewcalendar')));
        const given = props([
            item({id: 2, name: 'Mentoring', timestart: at('2026-11-17', 14)}),
            item({id: 4, name: 'Public seminar', source: 'course', key: 'open', courseid: 4, timestart: at('2026-11-10', 9),
                timeduration: 2 * 86400, url: 'https://example.com/landing/4'}),
        ]);
        given.answer.courses = [{id: 4, fullname: 'Public seminar', shortname: 'PS', categoryid: 3,
            url: 'https://example.com/course/view.php?id=4', image: '', model: 2, modality: 1, city: 'Recife', venue: 'Hall',
            landingurl: 'https://example.com/landing/4', enrolled: false, participants: 8, join: 'open'}];
        await mount(given);
        await act(async() => {
            fireEvent.click(gridItem(2));
        });
        expect(popover()?.querySelector('[data-region="dim-cal-description"]')).toBeNull();
        expect(popover()?.textContent).not.toContain('Loading');
        fireEvent.keyDown(popover() as HTMLElement, {key: 'Escape'});
        await act(async() => {
            fireEvent.click(gridItem(4, 'course'));
        });
        const dialog = popover() as HTMLElement;
        expect(described()).toEqual([2]);
        expect(dialog.textContent).toContain('8 participants');
        expect(dialog.textContent).toContain('Recife · Hall');
        expect(dialog.textContent).toContain('Online');
        const primary = dialog.querySelector<HTMLAnchorElement>('.dim-cal__popactions .btn-primary') as HTMLAnchorElement;
        expect(primary.textContent).toBe('Enrol');
        expect(primary.href).toBe('https://example.com/landing/4');
        expect(dialog.querySelector<HTMLAnchorElement>('[data-action="dim-cal-open"]')?.href).toBe('https://example.com/landing/4');
        const course = dialog.querySelector<HTMLAnchorElement>('[data-action="dim-cal-course"]') as HTMLAnchorElement;
        expect(course.href).toBe('https://example.com/course/view.php?id=4');
        expect(course.textContent).toBe('Event page');
        expect(course.className).toContain('btn-outline-secondary');
    });

    it('names the items an event overlaps, in words', async() => {
        describeWith(() => Promise.resolve({event: {description: ''}}));
        await mount(props([
            item({id: 1, name: 'Mentoring', timestart: at('2026-11-17', 9), timeduration: 7200}),
            item({id: 2, name: 'Office hours', timestart: at('2026-11-17', 10), timeduration: 3600}),
            item({id: 3, name: 'Lunch', timestart: at('2026-11-17', 11), timeduration: 3600}),
        ]));
        await act(async() => {
            fireEvent.click(gridItem(1));
        });
        expect(popover()?.querySelector('[data-region="dim-cal-overlap"]')?.textContent).toBe('Overlaps with Office hours');
        fireEvent.keyDown(popover() as HTMLElement, {key: 'Escape'});
        await act(async() => {
            fireEvent.click(gridItem(3));
        });
        expect(popover()?.querySelector('[data-region="dim-cal-overlap"]')).toBeNull();
    });

    it('offers core\'s action first, "Open" beside it', async() => {
        describeWith(() => Promise.resolve({event: {description: ''}}));
        await mount(props([item({id: 5, name: 'Essay due', timestart: at('2026-11-17', 23), timeduration: 0, actionevent: true,
            url: 'https://example.com/mod/assign/view.php?id=5',
            action: {name: 'Add submission', url: 'https://example.com/submit', itemcount: 1, actionable: true}})]));
        await act(async() => {
            fireEvent.click(gridItem(5));
        });
        const actions = Array.from(popover()?.querySelectorAll<HTMLAnchorElement>('.dim-cal__popactions a') || []);
        expect(actions.map((link) => [link.textContent, link.href])).toEqual([
            ['Add submission', 'https://example.com/submit'],
            ['Open', 'https://example.com/mod/assign/view.php?id=5'],
        ]);
    });

    it('offers no "Open" for a personal event, never the site\'s home, and keeps its description', async() => {
        describeWith(() => Promise.resolve({event: {description: '<p>Dentist</p>'}}));
        await mount(props([item({id: 6, key: 'user', eventtype: 'user', name: 'Appointment', url: '', courseid: 0})]));
        await act(async() => {
            fireEvent.click(gridItem(6));
        });
        const dialog = popover() as HTMLElement;
        await waitFor(() => expect(dialog.querySelector('[data-region="dim-cal-description"]')?.textContent).toBe('Dentist'));
        expect(dialog.querySelectorAll('.dim-cal__popactions a')).toHaveLength(0);
        expect(dialog.querySelector('a[href="http://localhost/"]')).toBeNull();
    });

    it('draws an event course\'s cover: its image, else its category\'s colours, with the date tile', async() => {
        const course = {
            id: 4, fullname: 'Fair', shortname: 'F', categoryid: 3, url: 'https://example.com/course/view.php?id=4',
            image: 'https://example.com/pluginfile.php/9/course/overviewfiles/fair.jpg', model: 2, modality: 1, city: '',
            venue: '', landingurl: 'https://example.com/landing/4', enrolled: false, participants: 3, join: '',
        };
        const entry = item({id: 4, source: 'course', key: 'open', courseid: 4, name: 'Fair', timestart: at('2026-11-19', 9),
            url: 'https://example.com/landing/4'});
        const given = props([entry]);
        await mount({...given, answer: {...given.answer, courses: [course],
            categories: [{id: 3, name: 'School', bg: '#0a4a85', ink: '#ffffff'}]}});
        await act(async() => {
            fireEvent.click(gridItem(4, 'course'));
        });
        const band = popover()?.querySelector<HTMLElement>('[data-region="dim-cal-popcover"]') as HTMLElement;
        const image = band.querySelector('img') as HTMLImageElement;
        expect(image.getAttribute('src')).toBe(course.image);
        expect(image.getAttribute('alt')).toBe('');
        expect(band.querySelector('.dim-cal__poptile')?.textContent).toBe('Nov19');
        expect(band.querySelector('.dim-cal__poptile')?.getAttribute('aria-hidden')).toBe('true');
        expect(band.getAttribute('style') || '').not.toContain('url(');
        fireEvent.keyDown(popover() as HTMLElement, {key: 'Escape'});

        document.body.replaceChildren();
        await mount({...given, answer: {...given.answer, courses: [{...course, image: ''}],
            categories: [{id: 3, name: 'School', bg: '#0a4a85', ink: '#ffffff'}]}});
        await act(async() => {
            fireEvent.click(gridItem(4, 'course'));
        });
        const plain = popover()?.querySelector<HTMLElement>('[data-region="dim-cal-popcover"]') as HTMLElement;
        expect(plain.querySelector('img')).toBeNull();
        expect(plain.style.getPropertyValue('--dim-cal-cover-bg')).toBe('#0a4a85');
        fireEvent.keyDown(popover() as HTMLElement, {key: 'Escape'});

        document.body.replaceChildren();
        await mount(props([item({id: 7, timestart: at('2026-11-19', 9)})]));
        await act(async() => {
            fireEvent.click(gridItem(7));
        });
        expect(popover()?.querySelector('[data-region="dim-cal-popcover"]')).toBeNull();
    });

    it('closes on Tab past its last control, and on a press outside, the focus back on the item', async() => {
        describeWith(() => Promise.resolve({event: {description: ''}}));
        await mount(props([item({id: 2, name: 'Mentoring', timestart: at('2026-11-17', 14)})]));
        await act(async() => {
            fireEvent.click(gridItem(2));
        });
        const controls = Array.from(popover()?.querySelectorAll<HTMLElement>('a[href], button') || []);
        const last = controls[controls.length - 1];
        last.focus();
        fireEvent.keyDown(last, {key: 'Tab'});
        expect(popover()).toBeNull();
        expect(document.activeElement).toBe(gridItem(2));
        await act(async() => {
            fireEvent.click(gridItem(2));
        });
        expect(popover()).not.toBeNull();
        fireEvent.mouseDown(document.querySelector('.dim-cal__toolbar') as HTMLElement);
        expect(popover()).toBeNull();
    });

    it('opens from an item of the drawer too', async() => {
        describeWith(() => Promise.resolve({event: {description: ''}}));
        await mount(props([item({id: 2, name: 'Mentoring', timestart: at('2026-11-17', 14)})], {day: '2026-11-17'}));
        await act(async() => {
            fireEvent.click(region().querySelector('[data-cal-pop]') as HTMLElement);
        });
        expect(popover()?.dataset.id).toBe('2');
        expect(region()).not.toBeNull();
    });

    it('closes on a month change', async() => {
        describeWith(() => Promise.resolve({event: {description: ''}}));
        await mount(props([item({id: 2, name: 'Mentoring', timestart: at('2026-11-17', 14)})]));
        await act(async() => {
            fireEvent.click(gridItem(2));
        });
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        expect(popover()).toBeNull();
    });
});

describe('the phone', () => {
    /** The callbacks the page's ResizeObservers registered. */
    let observers: Array<(entries: unknown[]) => void> = [];
    /** What each observer watches. */
    let watched: Element[] = [];

    beforeEach(() => {
        observers = [];
        watched = [];
        (window as unknown as {ResizeObserver: unknown}).ResizeObserver = class {
            /**
             * Keep the callback.
             *
             * @param {Function} callback The observer's callback.
             */
            constructor(callback: (entries: unknown[]) => void) {
                observers.push(callback);
            }

            /**
             * Note what is watched.
             *
             * @param {Element} element The element.
             * @returns {void}
             */
            observe(element: Element): void {
                watched.push(element);
            }

            /**
             * Nothing to release.
             *
             * @returns {void}
             */
            disconnect(): void {
                return;
            }
        };
    });

    afterEach(() => {
        delete (window as unknown as {ResizeObserver?: unknown}).ResizeObserver;
    });

    /**
     * Mount the page and give its frame a width.
     *
     * @param {object} given The props.
     * @param {number} width The frame's width, in px.
     * @returns {Promise}
     */
    const mountAt = async(given: Props, width: number): Promise<void> => {
        await mount(given);
        const frame = document.querySelector('[data-region="dim-cal-body"]') as Element;
        expect(observers).toHaveLength(1);
        expect(watched).toContain(frame);
        await act(async() => {
            observers[0]([{target: frame, contentRect: {width: width, height: 600}}]);
        });
    };

    it('hands the month grid\'s height to the frame, for the drawer beside it', async() => {
        await mount(props([]));
        const frame = document.querySelector('[data-region="dim-cal-body"]') as HTMLElement;
        const grid = document.querySelector('[role="grid"].dim-cal__grid') as Element;
        expect(watched).toContain(grid);
        await act(async() => {
            observers[0]([{target: grid, contentRect: {width: 900, height: 701.6}}]);
        });
        expect(frame.style.getPropertyValue('--dim-cal-gridheight')).toBe('702px');
        await act(async() => {
            observers[0]([{target: frame, contentRect: {width: 900, height: 999}}]);
        });
        expect(frame.style.getPropertyValue('--dim-cal-gridheight')).toBe('702px');
        // The regression: the report re-renders the calendar, and a custom property written by hand did not survive it.
        // React owns it now, so opening the drawer (the frame's class changes) leaves the same frame carrying it.
        await act(async() => {
            fireEvent.click(day('2026-11-17'));
        });
        const after = document.querySelector('[data-region="dim-cal-body"]') as HTMLElement;
        expect(after).toBe(frame);
        expect(after.className).toContain('dim-cal__body--drawer');
        expect(after.style.getPropertyValue('--dim-cal-gridheight')).toBe('702px');
        expect(after.getAttribute('style')).toContain('--dim-cal-gridheight: 702px');
    });

    it('draws the month as dots from the answer\'s counts, at most four and then "+"', async() => {
        const given = props([item({id: 1, name: 'Mentoring', timestart: at('2026-11-17', 14)})]);
        given.answer.days = [
            {date: '2026-11-17', counts: {site: 1, category: 0, course: 2, group: 0, user: 0, activity: 0, other: 0, open: 0}},
            {date: '2026-11-18', counts: {site: 1, category: 1, course: 1, group: 1, user: 1, activity: 0, other: 0, open: 0}},
        ];
        await mountAt(given, 400);
        const dots = (iso: string) => Array.from(document.querySelectorAll(`[data-day="${iso}"] .dim-cal__daydot`))
            .map((dot) => dot.className.replace('dim-cal__daydot dim-cal-key--', ''));
        expect(dots('2026-11-17')).toEqual(['site', 'course']);
        expect(dots('2026-11-18')).toEqual(['site', 'category', 'course', 'group']);
        expect(document.querySelector('[data-day="2026-11-18"] .dim-cal__daydotmore')?.textContent).toBe('+');
        expect(document.querySelectorAll('.dim-cal__bar, .dim-cal__line, .dim-cal__card')).toHaveLength(0);
        expect(day('2026-11-17').getAttribute('aria-label')).toBe('Tuesday, November 17, 2026, 1 item, today');
    });

    it('opens the day as a sheet with no hour column, and the popover as a bottom sheet', async() => {
        describeWith(() => Promise.resolve({event: {description: ''}}));
        await mountAt(props([item({id: 2, name: 'Mentoring', timestart: at('2026-11-17', 14), timeduration: 3600})]), 400);
        fireEvent.click(day('2026-11-17'));
        const sheet = await waitFor(() => region());
        expect(sheet.classList.contains('dim-cal__drawer--sheet')).toBe(true);
        expect(sheet.querySelector('.dim-cal__hours')).toBeNull();
        await act(async() => {
            fireEvent.click(sheet.querySelector('[data-cal-pop]') as HTMLElement);
        });
        expect(popover()?.classList.contains('dim-cal__pop--sheet')).toBe(true);
    });

    it('stays a desktop above the breakpoint, the drawer beside the grid', async() => {
        await mountAt(props(), 900);
        fireEvent.click(day('2026-11-17'));
        expect((await waitFor(() => region())).classList.contains('dim-cal__drawer--drawer')).toBe(true);
        expect(document.querySelector('[data-region="dim-cal-body"]')?.classList.contains('dim-cal__body--drawer')).toBe(true);
    });
});

describe('the Day view, a phone\'s (R1d)', () => {
    /** The callbacks the page's ResizeObservers registered, the latest last. */
    let observers: Array<(entries: unknown[]) => void> = [];
    /** Core's event form modal. */
    let modal: Record<string, jest.Mock>;
    /** The box's height the hour column is scrolled in. */
    let viewport = 600;
    let top = 0;
    const saved = ['scrollHeight', 'clientHeight', 'scrollTop']
        .map((name) => [name, Object.getOwnPropertyDescriptor(HTMLElement.prototype, name)]);

    /** What the address carries of the default state after the span. */
    const STATE = '&recursive=1&colour=key';

    /** Core's answers for creating: "New event" offered. */
    const EDITS = {create: true, context: 2, course: 1};

    beforeEach(() => {
        observers = [];
        viewport = 600;
        top = 0;
        (window as unknown as {ResizeObserver: unknown}).ResizeObserver = class {
            /**
             * Keep the callback.
             *
             * @param {Function} callback The observer's callback.
             */
            constructor(callback: (entries: unknown[]) => void) {
                observers.push(callback);
            }

            /**
             * Nothing to note.
             *
             * @returns {void}
             */
            observe(): void {
                return;
            }

            /**
             * Nothing to release.
             *
             * @returns {void}
             */
            disconnect(): void {
                return;
            }
        };
        // Jsdom has no PointerEvent: a mouse event carrying the pointer's type stands in for it.
        (window as unknown as {PointerEvent: unknown}).PointerEvent = class extends MouseEvent {
            pointerType: string;

            /**
             * A mouse event with a pointer type.
             *
             * @param {string} type The event's type.
             * @param {object} init Its fields.
             */
            constructor(type: string, init: MouseEventInit & {pointerType?: string} = {}) {
                super(type, init);
                this.pointerType = init.pointerType || '';
            }
        };
        Object.defineProperty(HTMLElement.prototype, 'scrollHeight', {configurable: true, get: () => 960});
        Object.defineProperty(HTMLElement.prototype, 'clientHeight', {configurable: true, get: () => viewport});
        Object.defineProperty(HTMLElement.prototype, 'scrollTop', {configurable: true,
            get: () => top,
            set(value: number) {
                top = Math.max(0, Math.min(value, 960 - viewport));
            }});
        modal = {
            setContextId: jest.fn(),
            setCourseId: jest.fn(),
            setEventId: jest.fn(),
            setStartTime: jest.fn(),
            show: jest.fn(),
        };
        mockAmdModule('jquery', () => ({on: () => undefined}));
        mockAmdModule('core_calendar/events', {created: 'c', updated: 'u', deleted: 'd'});
        mockAmdModule('core_calendar/modal_event_form', {create: jest.fn(() => Promise.resolve(modal))});
        mockAmdModule('core/notification', {exception: jest.fn(() => Promise.resolve())});
        forgetEditing();
    });

    afterEach(() => {
        jest.restoreAllMocks();
        delete (window as unknown as {ResizeObserver?: unknown}).ResizeObserver;
        delete (window as unknown as {PointerEvent?: unknown}).PointerEvent;
        saved.forEach(([name, descriptor]) => {
            if (descriptor) {
                Object.defineProperty(HTMLElement.prototype, name as string, descriptor as PropertyDescriptor);
            } else {
                delete (HTMLElement.prototype as unknown as Record<string, unknown>)[name as string];
            }
        });
    });

    /**
     * Report a width for the frame, through the latest observer (the grid's, or the Day view's).
     *
     * @param {number} width The frame's width, in px.
     * @returns {Promise}
     */
    const measure = async(width: number): Promise<void> => {
        const frame = document.querySelector('[data-region="dim-cal-body"]') as Element;
        await act(async() => {
            observers[observers.length - 1]([{target: frame, contentRect: {width: width, height: 600}}]);
        });
    };

    /**
     * The Day view's region, or null.
     *
     * @returns {HTMLElement|null}
     */
    const dayview = (): HTMLElement | null => document.querySelector<HTMLElement>('[data-region="dim-cal-dayview"]');

    /**
     * The range requests made, as [from, to].
     *
     * @returns {Array}
     */
    const ranges = (): string[][] => fetched.mock.calls
        .filter(([request]) => request.methodname === 'theme_dimensions_get_calendar_range')
        .map(([request]) => [request.args.from, request.args.to]);

    /**
     * The view switch's choices.
     *
     * @returns {Array} Their names.
     */
    const choices = (): string[] => Array.from(document.querySelectorAll('[data-region="dim-cal-views"] button'))
        .map((button) => button.textContent || '');

    /**
     * Wait for a hold to run out.
     *
     * @returns {Promise}
     */
    const held = async(): Promise<void> => {
        await act(async() => {
            await new Promise((resolve) => setTimeout(resolve, 550));
        });
    };

    it('offers Day in the switch on a phone only', async() => {
        await mount(props());
        await measure(900);
        expect(choices()).toEqual(['Month', 'Week']);
        await measure(400);
        expect(choices()).toEqual(['Month', 'Week', 'Day']);
        expect(document.querySelector('[data-action="dim-cal-view-day"]')?.getAttribute('aria-pressed')).toBe('false');
    });

    it('shows one day at the frame\'s width, asking for it alone, and the arrows and "Today" move it and the address', async() => {
        const events = [
            item({id: 1, name: 'Mentoring', timestart: at('2026-11-17', 14), timeduration: 3600}),
            item({id: 2, name: 'Seminar', timestart: at('2026-11-17', 0), timeduration: 3 * 86400}),
            item({id: 3, name: 'Review', timestart: at('2026-11-18', 9), timeduration: 3600}),
        ];
        fetched.mockImplementation(({methodname, args}: {methodname: string, args: {from: string, to: string}}) =>
            Promise.resolve(methodname === 'theme_dimensions_get_calendar_range' ? answer(events, args.from, args.to) : {}));
        const push = jest.spyOn(window.history, 'pushState');
        await mount(props(events));
        await measure(400);
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-view-day"]') as HTMLElement);
        });
        expect(ranges()).toEqual([['2026-11-17', '2026-11-17']]);
        expect(window.location.search).toBe('?view=day&day=2026-11-17' + STATE);
        const view = await waitFor(() => dayview() as HTMLElement);
        expect(document.querySelector('[role="grid"]')).toBeNull();
        expect(document.querySelector('[data-region="dim-cal-day"]')).toBeNull();
        expect(view.getAttribute('aria-labelledby')).toBe('dim-cal-caption');
        expect(document.querySelector('[data-region="dim-cal-caption"]')?.textContent).toBe('Tuesday, November 17, 2026');
        expect(view.querySelector('[data-region="dim-cal-allday"]')?.textContent).toContain('Seminar');
        expect(view.querySelector('[data-region="dim-cal-hours"] .dim-cal__dayitem--timed')?.textContent).toContain('Mentoring');
        expect(view.querySelectorAll('.dim-cal__hour')).toHaveLength(24);
        expect(view.textContent).not.toContain('Review');
        expect(document.querySelector('[data-action="dim-cal-view-day"]')?.getAttribute('aria-pressed')).toBe('true');
        const next = document.querySelector('[data-action="dim-cal-next"]') as HTMLElement;
        expect(next.getAttribute('aria-label')).toBe('Next day');
        expect(document.querySelector('[data-action="dim-cal-prev"]')?.getAttribute('aria-label')).toBe('Previous day');

        push.mockClear();
        await act(async() => {
            fireEvent.click(next);
        });
        expect(ranges()[ranges().length - 1]).toEqual(['2026-11-18', '2026-11-18']);
        expect(window.location.search).toBe('?view=day&day=2026-11-18' + STATE);
        expect(push).toHaveBeenCalledTimes(1);
        expect((dayview() as HTMLElement).dataset.day).toBe('2026-11-18');
        expect((dayview() as HTMLElement).textContent).toContain('Review');
        expect((dayview() as HTMLElement).textContent).not.toContain('Mentoring');
        for (let press = 0; press < 2; press++) {
            await act(async() => {
                fireEvent.click(document.querySelector('[data-action="dim-cal-prev"]') as HTMLElement);
            });
        }
        expect(window.location.search).toBe('?view=day&day=2026-11-16' + STATE);
        await act(async() => {
            fireEvent.click(document.querySelector('.dim-cal__today') as HTMLElement);
        });
        expect(window.location.search).toBe('?view=day&day=2026-11-17' + STATE);
        expect((dayview() as HTMLElement).dataset.day).toBe('2026-11-17');
        push.mockRestore();
    });

    it('opens an item of the day as the phone\'s bottom sheet', async() => {
        describeWith(() => Promise.resolve({event: {description: ''}}));
        await mount(props([item({id: 4, name: 'Mentoring', timestart: at('2026-11-18', 14)})], {view: 'day', day: '2026-11-18'}));
        await measure(400);
        await act(async() => {
            fireEvent.click((dayview() as HTMLElement).querySelector('[data-cal-pop]') as HTMLElement);
        });
        expect(popover()?.classList.contains('dim-cal__pop--sheet')).toBe(true);
    });

    it('draws a view=day address on a phone from the first answer, an empty day centred on the working hours', async() => {
        await mount(props([], {view: 'day', day: '2026-11-20', edits: EDITS}));
        await measure(400);
        const view = dayview() as HTMLElement;
        expect(view.dataset.day).toBe('2026-11-20');
        expect(ranges()).toEqual([]);
        expect(view.textContent).toContain('Nothing on this day.');
        const box = view.querySelector('[data-region="dim-cal-hourscroll"]') as HTMLElement;
        // 08:00-18:00 centred in a 600px box over 40px rows: (13 x 40) - 300.
        expect(box.scrollTop).toBe(220);
        expect(document.querySelector('[data-region="dim-cal-day"]')).toBeNull();
        expect(window.location.search).toBe('');
    });

    it('creates at an hour by a double-tap or a held touch, and never by a moved touch or a mouse held down', async() => {
        await mount(props([], {view: 'day', day: '2026-11-20', edits: EDITS}));
        await measure(400);
        const row = (hour: number): HTMLElement => (dayview() as HTMLElement)
            .querySelector(`.dim-cal__hour[data-hour="${hour}"]`) as HTMLElement;
        await act(async() => {
            fireEvent.doubleClick(row(9));
        });
        expect(modal.setStartTime).toHaveBeenLastCalledWith(at('2026-11-20', 9));
        expect(modal.show).toHaveBeenCalledTimes(1);

        fireEvent.pointerDown(row(14), {pointerType: 'touch', clientX: 10, clientY: 10});
        await held();
        expect(modal.setStartTime).toHaveBeenLastCalledWith(at('2026-11-20', 14));
        expect(modal.show).toHaveBeenCalledTimes(2);

        fireEvent.pointerDown(row(15), {pointerType: 'touch', clientX: 10, clientY: 10});
        fireEvent.pointerMove(row(15), {pointerType: 'touch', clientX: 10, clientY: 40});
        await held();
        fireEvent.pointerDown(row(16), {pointerType: 'touch', clientX: 10, clientY: 10});
        fireEvent.pointerCancel(row(16), {pointerType: 'touch'});
        await held();
        fireEvent.pointerDown(row(17), {pointerType: 'mouse', clientX: 10, clientY: 10});
        await held();
        expect(modal.show).toHaveBeenCalledTimes(2);

        fireEvent.pointerDown(row(18), {pointerType: 'touch', clientX: 10, clientY: 10});
        fireEvent.pointerMove(row(18), {pointerType: 'touch', clientX: 14, clientY: 16});
        await held();
        expect(modal.setStartTime).toHaveBeenLastCalledWith(at('2026-11-20', 18));
        expect(modal.show).toHaveBeenCalledTimes(3);
    });

    it('draws the "+" of "New event" only while it is offered, opening the form on the day', async() => {
        await mount(props([], {view: 'day', day: '2026-11-20', edits: EDITS}));
        await measure(400);
        const plus = (dayview() as HTMLElement).querySelector('[data-action="dim-cal-new"]') as HTMLElement;
        expect(plus.getAttribute('aria-label')).toBe('New event on Friday, November 20, 2026');
        await act(async() => {
            fireEvent.click(plus);
        });
        expect(modal.setStartTime).toHaveBeenLastCalledWith(at('2026-11-20', 10));
        document.body.replaceChildren();
        await mount(props([], {view: 'day', day: '2026-11-20'}));
        await measure(400);
        expect((dayview() as HTMLElement).querySelector('[data-action="dim-cal-new"]')).toBeNull();
        expect((dayview() as HTMLElement).querySelector('.dim-cal__hour')).toBeNull();
    });

    it('shows a view=day address on a desktop as the month with that day\'s drawer, never a day view', async() => {
        const replace = jest.spyOn(window.history, 'replaceState');
        window.history.pushState({}, '', '/r.php/theme_dimensions/calendar?view=day&day=2026-11-18');
        replace.mockClear();
        await mount(props([item({id: 5, name: 'Review', timestart: at('2026-11-18', 9)})], {view: 'day', day: '2026-11-18'}));
        // The first frame, before any measure, is already the month with the drawer.
        expect(document.querySelector('[role="grid"].dim-cal__grid')).not.toBeNull();
        expect(region().dataset.day).toBe('2026-11-18');
        await measure(900);
        expect(dayview()).toBeNull();
        expect(region().classList.contains('dim-cal__drawer--drawer')).toBe(true);
        expect(region().dataset.day).toBe('2026-11-18');
        expect(ranges()).toEqual([['2026-11-01', '2026-12-05']]);
        expect(window.location.search).toBe('?month=2026-11&day=2026-11-18' + STATE);
        expect(replace).toHaveBeenCalled();
        expect(choices()).toEqual(['Month', 'Week']);
        expect(document.querySelector('[data-action="dim-cal-view-month"]')?.getAttribute('aria-pressed')).toBe('true');
        replace.mockRestore();
    });

    it('leaves the Day view for its month, the drawer on the day, when the phone turns wide', async() => {
        await mount(props([], {view: 'day', day: '2026-11-18'}));
        await measure(400);
        expect(dayview()).not.toBeNull();
        await measure(900);
        expect(dayview()).toBeNull();
        expect(region().dataset.day).toBe('2026-11-18');
        expect(region().classList.contains('dim-cal__drawer--drawer')).toBe(true);
        expect(ranges()).toEqual([['2026-11-01', '2026-12-05']]);
        expect(window.location.search).toBe('?month=2026-11&day=2026-11-18' + STATE);
    });

    it('reads the frame\'s entry alone for the phone decision, never another box\'s', async() => {
        await mount(props([], {view: 'day', day: '2026-11-18'}));
        await measure(400);
        const view = dayview() as HTMLElement;
        await act(async() => {
            observers[observers.length - 1]([{target: view, contentRect: {width: 900, height: 600}}]);
        });
        expect(dayview()).not.toBeNull();
        expect(ranges()).toEqual([]);
    });

    it('goes back to the month from the Day view with the day selected and no sheet', async() => {
        await mount(props([], {view: 'day', day: '2026-11-18'}));
        await measure(400);
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-view-month"]') as HTMLElement);
        });
        expect(dayview()).toBeNull();
        expect(document.querySelector('[data-region="dim-cal-day"]')).toBeNull();
        expect(window.location.search).toBe('?month=2026-11' + STATE);
        expect(day('2026-11-18').tabIndex).toBe(0);
    });
});

describe('editing, core\'s (C5)', () => {
    /** The handlers core's events were given on the body, by event name. */
    let handlers: Map<string, Array<() => void>>;
    /** Core's event form modal. */
    let modal: Record<string, jest.Mock>;
    /** Core's crud, repository and notification. */
    let crud: {registerRemove: jest.Mock};
    let repository: {updateEventStartDay: jest.Mock};
    let notification: {exception: jest.Mock, addNotification: jest.Mock};
    /** What core's jQuery was handed. */
    let wrapped: unknown[];

    /** The edits core's answer allows a creator: the form's context and the site's course. */
    const EDITS = {create: true, context: 2, course: 1};

    beforeEach(() => {
        handlers = new Map();
        wrapped = [];
        modal = {
            setContextId: jest.fn(),
            setCourseId: jest.fn(),
            setEventId: jest.fn(),
            setStartTime: jest.fn(),
            show: jest.fn(),
        };
        crud = {registerRemove: jest.fn()};
        repository = {updateEventStartDay: jest.fn(() => Promise.resolve({event: {}}))};
        notification = {exception: jest.fn(() => Promise.resolve()), addNotification: jest.fn()};
        mockAmdModule('jquery', (target: unknown) => {
            wrapped.push(target);
            return {
                on: (name: string, handler: () => void) => {
                    handlers.set(name, [...(handlers.get(name) || []), handler]);
                },
            };
        });
        mockAmdModule('core_calendar/events', {
            created: 'calendar-events:created',
            updated: 'calendar-events:updated',
            deleted: 'calendar-events:deleted',
        });
        mockAmdModule('core_calendar/modal_event_form', {create: jest.fn(() => Promise.resolve(modal))});
        mockAmdModule('core_calendar/crud', crud);
        mockAmdModule('core_calendar/repository', repository);
        mockAmdModule('core/notification', notification);
        mockString('errorinvaliddate', 'calendar', 'Invalid date');
        forgetEditing();
    });

    /**
     * Say what core says when it changed an event: its jQuery event on the body.
     *
     * @param {string} name The event's name.
     * @returns {Promise} Settles once the page has answered.
     */
    const trigger = async(name: string): Promise<void> => {
        await act(async() => {
            (handlers.get(name) || []).forEach((handler) => handler());
        });
    };

    /**
     * An entry of core's answer.
     *
     * @param {object} fields What differs from an editable, deletable, draggable event with no limits.
     * @returns {object}
     */
    const entry = (fields: Partial<Edit> & {id: number}): Edit => ({
        edit: true,
        delete: true,
        drag: true,
        count: 1,
        minday: '',
        maxday: '',
        minerror: '',
        maxerror: '',
        ...fields,
    });

    /**
     * Props whose answer lists these items and core's entries.
     *
     * @param {Array} events The items.
     * @param {Array} editing Core's entries.
     * @param {object} more Props that differ.
     * @returns {object}
     */
    const editable = (events: ReturnType<typeof item>[], editing: Edit[], more: Partial<Props> = {}): Props => {
        const given = props(events, {edits: EDITS, ...more});
        return {...given, answer: {...given.answer, editing: editing}};
    };

    /**
     * A drag's data, as the browser hands it.
     *
     * @returns {object}
     */
    const transfer = (): Record<string, unknown> => ({setData: jest.fn(), effectAllowed: '', dropEffect: ''});

    it('draws "New event" only when the server says core would', async() => {
        await mount(props([]));
        expect(document.querySelector('[data-action="dim-cal-new"]')).toBeNull();
        document.body.replaceChildren();
        await mount(props([], {edits: {...EDITS, create: false}}));
        expect(document.querySelector('[data-action="dim-cal-new"]')).toBeNull();
        document.body.replaceChildren();
        await mount(props([], {edits: EDITS}));
        const button = document.querySelector<HTMLButtonElement>('[data-action="dim-cal-new"]') as HTMLButtonElement;
        expect(button.tagName).toBe('BUTTON');
        expect(button.textContent).toBe('New event');
        expect(button.tabIndex).toBe(0);
    });

    it('opens core\'s form with core\'s context and course, and no start while today is shown', async() => {
        await mount(props([], {edits: EDITS}));
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-new"]') as HTMLElement);
        });
        expect(modal.setContextId).toHaveBeenCalledWith(2);
        expect(modal.setCourseId).toHaveBeenCalledWith(1);
        expect(modal.setStartTime).not.toHaveBeenCalled();
        expect(modal.setEventId).not.toHaveBeenCalled();
        expect(modal.show).toHaveBeenCalledTimes(1);
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-new"]') as HTMLElement);
        });
        expect(modal.show).toHaveBeenCalledTimes(2);
        expect(handlers.get('calendar-events:created')).toHaveLength(1);
    });

    it('starts a new event on the month\'s first day at the time of day when today is elsewhere', async() => {
        await mount(props([], {edits: EDITS, month: '2026-12'}));
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-new"]') as HTMLElement);
        });
        expect(modal.setStartTime).toHaveBeenCalledWith(at('2026-12-01', 10));
    });

    it('offers "New event" in the drawer for its day, the day prefilled', async() => {
        await mount(props([], {edits: EDITS, day: '2026-11-10'}));
        const button = region().querySelector<HTMLElement>('[data-action="dim-cal-new"]') as HTMLElement;
        expect(button.getAttribute('aria-label')).toBe('New event on Tuesday, November 10, 2026');
        expect(button.getAttribute('title')).toBe('New event on Tuesday, November 10, 2026');
        expect(button.textContent).toBe('');
        expect(button.querySelector('.fa-plus')).not.toBeNull();
        await act(async() => {
            fireEvent.click(button);
        });
        expect(modal.setStartTime).toHaveBeenCalledWith(at('2026-11-10', 10));
        document.body.replaceChildren();
        await mount(props([], {day: '2026-11-10'}));
        expect(region().querySelector('[data-action="dim-cal-new"]')).toBeNull();
    });

    it('draws the whole day on an empty day while creation is offered, under the "nothing" line', async() => {
        await mount(props([], {edits: EDITS, day: '2026-11-10'}));
        expect(region().textContent).toContain('Nothing on this day.');
        const rows = Array.from(region().querySelectorAll<HTMLElement>('.dim-cal__hour')).map((row) => row.dataset.hour);
        expect(rows).toEqual(Array.from({length: 24}, (unused, hour) => String(hour)));
        const box = region().querySelector('[data-region="dim-cal-hourscroll"]') as HTMLElement;
        expect([box.tabIndex, box.getAttribute('role'), box.getAttribute('aria-label')]).toEqual([0, 'group', 'Hours of the day']);
        await act(async() => {
            fireEvent.doubleClick(region().querySelector('.dim-cal__hour[data-hour="9"]') as HTMLElement);
        });
        expect(modal.setStartTime).toHaveBeenCalledWith(at('2026-11-10', 9));
        document.body.replaceChildren();
        await mount(props([], {day: '2026-11-10'}));
        expect(region().textContent).toContain('Nothing on this day.');
        expect(region().querySelector('.dim-cal__hour')).toBeNull();
    });

    it('opens core\'s form on a day\'s ground at a double-click, and not on its controls', async() => {
        await mount(props([item({id: 3, name: 'Mentoring', timestart: at('2026-11-12', 14)})], {edits: EDITS}));
        const ground = day('2026-11-10').parentElement?.querySelector('.dim-cal__ground') as HTMLElement;
        await act(async() => {
            fireEvent.doubleClick(ground);
        });
        expect(modal.setStartTime).toHaveBeenCalledWith(at('2026-11-10', 10));
        expect(modal.show).toHaveBeenCalledTimes(1);
        await act(async() => {
            fireEvent.doubleClick(day('2026-11-11'));
            fireEvent.doubleClick(gridItem(3));
        });
        expect(modal.show).toHaveBeenCalledTimes(1);
    });

    it('opens the day on the first click of a double-click and creates on that day, whatever the second lands on', async() => {
        await mount(props([], {edits: EDITS}));
        const ground = (iso: string): HTMLElement => day(iso).parentElement?.querySelector('.dim-cal__ground') as HTMLElement;
        await act(async() => {
            fireEvent.click(ground('2026-11-19'), {detail: 1});
        });
        expect(region().dataset.day).toBe('2026-11-19');
        // The drawer narrowed the grid: the second click and the double-click land on the next day's ground.
        await act(async() => {
            fireEvent.click(ground('2026-11-20'), {detail: 2});
            fireEvent.doubleClick(ground('2026-11-20'), {detail: 2});
        });
        // The second click opens nothing.
        expect(region().dataset.day).toBe('2026-11-19');
        expect(modal.setStartTime).toHaveBeenCalledWith(at('2026-11-19', 10));
        expect(modal.show).toHaveBeenCalledTimes(1);
    });

    it('opens core\'s form at the hour of a double-clicked hour row of the drawer', async() => {
        await mount(props([item({id: 3, name: 'Mentoring', timestart: at('2026-11-10', 14)})], {edits: EDITS, day: '2026-11-10'}));
        const row = region().querySelector<HTMLElement>('.dim-cal__hour[data-hour="14"]') as HTMLElement;
        await act(async() => {
            fireEvent.doubleClick(row);
        });
        expect(modal.setStartTime).toHaveBeenCalledWith(at('2026-11-10', 14));
    });

    it('listens to no double-click while core would not show "New event"', async() => {
        await mount(props([item({id: 3, timestart: at('2026-11-10', 14)})], {day: '2026-11-10'}));
        await act(async() => {
            fireEvent.doubleClick(day('2026-11-12').parentElement?.querySelector('.dim-cal__ground') as HTMLElement);
            fireEvent.doubleClick(region().querySelector('.dim-cal__hour') as HTMLElement);
        });
        expect(modal.show).not.toHaveBeenCalled();
    });

    it('asks for the window shown again when core says an event was saved, writing nothing', async() => {
        const push = jest.spyOn(window.history, 'pushState');
        const replace = jest.spyOn(window.history, 'replaceState');
        await mount(props([], {edits: EDITS}));
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-new"]') as HTMLElement);
        });
        await trigger('calendar-events:created');
        expect(ranges()).toEqual([expect.objectContaining({from: '2026-11-01', to: '2026-12-05'})]);
        await trigger('calendar-events:updated');
        expect(ranges()).toHaveLength(2);
        expect(push).not.toHaveBeenCalled();
        expect(replace).not.toHaveBeenCalled();
        push.mockRestore();
        replace.mockRestore();
    });

    it('draws Edit and Delete in the popover only for what core allows, with core\'s markers', async() => {
        describeWith(() => Promise.resolve({event: {description: ''}}));
        await mount(editable([
            item({id: 1, name: 'Mine', timestart: at('2026-11-17', 9)}),
            item({id: 2, name: 'Not mine', timestart: at('2026-11-18', 9)}),
            item({id: 3, name: 'Due', timestart: at('2026-11-19', 9), modulename: 'assign'}),
        ], [entry({id: 1, count: 3}), entry({id: 3, edit: false, delete: false})]));
        await act(async() => {
            fireEvent.click(gridItem(2));
        });
        expect(popover()?.querySelector('[data-region="dim-cal-edit"]')).toBeNull();
        await act(async() => {
            fireEvent.click(gridItem(3));
        });
        expect(popover()?.querySelector('[data-region="dim-cal-edit"]')).toBeNull();
        await act(async() => {
            fireEvent.click(gridItem(1));
        });
        const actions = popover()?.querySelector<HTMLElement>('[data-region="dim-cal-edit"]') as HTMLElement;
        expect(actions.dataset.type).toBe('event');
        expect(actions.dataset.eventId).toBe('1');
        expect(actions.dataset.eventTitle).toBe('Mine');
        expect(actions.dataset.eventCount).toBe('3');
        expect(Array.from(actions.querySelectorAll('button')).map((button) => [button.textContent, button.dataset.action]))
            .toEqual([['Edit', 'dim-cal-edit'], ['Delete', 'delete']]);
        await waitFor(() => expect(crud.registerRemove).toHaveBeenCalledTimes(1));
        expect(wrapped).toContain(actions);
    });

    it('hands core\'s delete the title in the escaped spelling core\'s templates carry, a plain one unchanged', async() => {
        describeWith(() => Promise.resolve({event: {description: ''}}));
        const name = 'Q&A <b>"quoted"</b> it\'s';
        await mount(editable([item({id: 1, name: name, timestart: at('2026-11-17', 9)})], [entry({id: 1})]));
        await act(async() => {
            fireEvent.click(gridItem(1));
        });
        const actions = popover()?.querySelector<HTMLElement>('[data-region="dim-cal-edit"]') as HTMLElement;
        // Core reads the decoded value and shows it as HTML: it must be the text a double stash would have written.
        expect(actions.getAttribute('data-event-title')).toBe(
            'Q&amp;A &lt;b&gt;&quot;quoted&quot;&lt;/b&gt; it&#039;s'
        );
        expect(actions.getAttribute('data-event-title')).not.toBe(name);
    });

    it('edits through core\'s form, and closes the popover once core deleted the event', async() => {
        describeWith(() => Promise.resolve({event: {description: ''}}));
        await mount(editable([item({id: 1, name: 'Mine', courseid: 7, timestart: at('2026-11-17', 9)})], [entry({id: 1})]));
        await act(async() => {
            fireEvent.click(gridItem(1));
        });
        await act(async() => {
            fireEvent.click(popover()?.querySelector('[data-action="dim-cal-edit"]') as HTMLElement);
        });
        expect(modal.setEventId).toHaveBeenCalledWith(1);
        expect(modal.setContextId).toHaveBeenCalledWith(2);
        // Core's edit hands the event's own course, not the page's (EDITS.course is 1).
        expect(modal.setCourseId).toHaveBeenCalledWith(7);
        expect(modal.setStartTime).not.toHaveBeenCalled();
        expect(modal.show).toHaveBeenCalledTimes(1);
        const dialogue = document.createElement('div');
        dialogue.setAttribute('role', 'dialog');
        // A press in core's dialogue, over the popover, leaves the popover open.
        fireEvent.mouseDown(document.body.appendChild(dialogue));
        expect(popover()).not.toBeNull();
        await trigger('calendar-events:deleted');
        expect(popover()).toBeNull();
        expect(ranges()).toHaveLength(1);
        expect(document.activeElement).toBe(day('2026-11-17'));
    });

    it('closes on Tab past Delete, its last control', async() => {
        describeWith(() => Promise.resolve({event: {description: ''}}));
        await mount(editable([item({id: 1, name: 'Mine', timestart: at('2026-11-17', 9)})], [entry({id: 1})]));
        await act(async() => {
            fireEvent.click(gridItem(1));
        });
        const remove = popover()?.querySelector<HTMLElement>('[data-action="delete"]') as HTMLElement;
        remove.focus();
        fireEvent.keyDown(remove, {key: 'Tab'});
        expect(popover()).toBeNull();
    });

    it('lets only what core marks draggable be dragged, and marks the days only then', async() => {
        await mount(editable([
            item({id: 1, timestart: at('2026-11-17', 9)}),
            item({id: 2, timestart: at('2026-11-18', 9)}),
            item({id: 3, timestart: at('2026-11-19', 9)}),
        ], [entry({id: 1}), entry({id: 3, drag: false})]));
        expect(gridItem(1).getAttribute('draggable')).toBe('true');
        expect(gridItem(2).hasAttribute('draggable')).toBe(false);
        expect(gridItem(3).hasAttribute('draggable')).toBe(false);
        expect(document.querySelectorAll('[data-cal-drop]').length).toBe(35);
    });

    it('moves a start day through core\'s service and asks for the window again', async() => {
        await mount(editable([item({id: 1, timestart: at('2026-11-17', 9)})], [entry({id: 1})]));
        const data = transfer();
        fireEvent.dragStart(gridItem(1), {dataTransfer: data});
        expect(document.querySelector('.dim-cal__app--moving')).not.toBeNull();
        fireEvent.dragOver(day('2026-11-20'), {dataTransfer: data});
        expect(day('2026-11-20').closest('[data-cal-drop]')?.getAttribute('data-cal-over')).toBe('valid');
        await act(async() => {
            fireEvent.drop(day('2026-11-20'), {dataTransfer: data});
        });
        expect(repository.updateEventStartDay).toHaveBeenCalledWith(1, instantOf('2026-11-20', MOVE_MINUTE, 'UTC'));
        await waitFor(() => expect(ranges()).toHaveLength(1));
        expect(document.querySelector('.dim-cal__app--moving')).toBeNull();
        expect(document.querySelector('[data-cal-over]')).toBeNull();
    });

    it('refuses a day outside core\'s limits with core\'s message, asking nothing', async() => {
        await mount(editable([item({id: 1, timestart: at('2026-11-17', 9)})],
            [entry({id: 1, minday: '2026-11-15', minerror: 'Not before the course starts'})]));
        const data = transfer();
        fireEvent.dragStart(gridItem(1), {dataTransfer: data});
        fireEvent.dragOver(day('2026-11-12'), {dataTransfer: data});
        expect(day('2026-11-12').closest('[data-cal-drop]')?.getAttribute('data-cal-over')).toBe('invalid');
        await act(async() => {
            fireEvent.drop(day('2026-11-12'), {dataTransfer: data});
        });
        await waitFor(() => expect(notification.exception).toHaveBeenCalledWith({
            name: 'Invalid date',
            message: 'Not before the course starts',
        }));
        expect(repository.updateEventStartDay).not.toHaveBeenCalled();
        expect(ranges()).toHaveLength(0);
    });

    it('asks nothing for a drop on the day it starts on, nor for a drag core does not allow', async() => {
        await mount(editable([
            item({id: 1, timestart: at('2026-11-17', 9)}),
            item({id: 2, timestart: at('2026-11-18', 9)}),
        ], [entry({id: 1}), entry({id: 2, drag: false})]));
        const data = transfer();
        fireEvent.dragStart(gridItem(1), {dataTransfer: data});
        await act(async() => {
            fireEvent.drop(day('2026-11-17'), {dataTransfer: data});
        });
        fireEvent.dragStart(gridItem(2), {dataTransfer: data});
        expect(document.querySelector('.dim-cal__app--moving')).toBeNull();
        await act(async() => {
            fireEvent.drop(day('2026-11-21'), {dataTransfer: data});
        });
        expect(repository.updateEventStartDay).not.toHaveBeenCalled();
    });

    it('shows core\'s exception when core refuses the move', async() => {
        repository.updateEventStartDay.mockImplementation(() => Promise.reject(new Error('The start day cannot be modified')));
        await mount(editable([item({id: 1, timestart: at('2026-11-17', 9)})], [entry({id: 1})]));
        const data = transfer();
        fireEvent.dragStart(gridItem(1), {dataTransfer: data});
        await act(async() => {
            fireEvent.drop(day('2026-11-20'), {dataTransfer: data});
        });
        await waitFor(() => expect(notification.exception).toHaveBeenCalledTimes(1));
        expect(ranges()).toHaveLength(0);
    });
});

describe('the first paint, guessed by a media query (R1d)', () => {
    /** The callbacks the page's ResizeObservers registered, the latest last. */
    let observers: Array<(entries: unknown[]) => void> = [];
    /** The queries asked of matchMedia. */
    let asked: string[] = [];

    /**
     * Stand in for matchMedia, answering every query with one verdict.
     *
     * @param {boolean} matches The verdict.
     * @returns {void}
     */
    const media = (matches: boolean): void => {
        asked = [];
        (window as unknown as {matchMedia: unknown}).matchMedia = (query: string) => {
            asked.push(query);
            return {matches: matches, media: query, addEventListener: () => undefined, removeEventListener: () => undefined};
        };
    };

    beforeEach(() => {
        observers = [];
        (window as unknown as {ResizeObserver: unknown}).ResizeObserver = class {
            /**
             * Keep the callback.
             *
             * @param {Function} callback The observer's callback.
             */
            constructor(callback: (entries: unknown[]) => void) {
                observers.push(callback);
            }

            /**
             * Nothing to note.
             *
             * @returns {void}
             */
            observe(): void {
                return;
            }

            /**
             * Nothing to release.
             *
             * @returns {void}
             */
            disconnect(): void {
                return;
            }
        };
    });

    afterEach(() => {
        delete (window as unknown as {ResizeObserver?: unknown}).ResizeObserver;
        delete (window as unknown as {matchMedia?: unknown}).matchMedia;
    });

    /**
     * Render the page without waiting for a grid, which a phone's Day view does not draw.
     *
     * @param {object} given The props.
     * @returns {Promise}
     */
    const draw = async(given: Props): Promise<void> => {
        const page = document.createElement('div');
        page.dataset.region = 'dim-cal';
        const node = document.createElement('div');
        page.append(node);
        document.body.append(page);
        await act(async() => {
            render(<Calendar {...given} />, {container: node});
        });
        await waitFor(() => expect(page.querySelector('[data-region="dim-cal-body"]')).not.toBeNull());
    };

    /**
     * Report a width for the frame, through the latest observer.
     *
     * @param {number} width The frame's width, in px.
     * @returns {Promise}
     */
    const measure = async(width: number): Promise<void> => {
        const frame = document.querySelector('[data-region="dim-cal-body"]') as Element;
        await act(async() => {
            observers[observers.length - 1]([{target: frame, contentRect: {width: width, height: 600}}]);
        });
    };

    /**
     * The view switch's choices.
     *
     * @returns {Array} Their names.
     */
    const choices = (): string[] => Array.from(document.querySelectorAll('[data-region="dim-cal-views"] button'))
        .map((button) => button.textContent || '');

    /**
     * The range requests made.
     *
     * @returns {Array} Their [from, to].
     */
    const asks = (): string[][] => ranges().map((args) => [String(args.from), String(args.to)]);

    it('draws the phone layout and offers Day before any observer entry when the query matches', async() => {
        media(true);
        await draw(props([]));
        expect(asked).toEqual(['(max-width: 591px)']);
        expect(choices()).toEqual(['Month', 'Week', 'Day']);
        expect(document.querySelector('.dim-cal__grid--phone')).not.toBeNull();
    });

    it('draws a view=day address as the Day view at once, with no request before the first measure', async() => {
        media(true);
        await draw(props([], {view: 'day', day: '2026-11-18'}));
        expect(document.querySelector('[data-region="dim-cal-dayview"]')).not.toBeNull();
        expect(document.querySelector('[data-region="dim-cal-day"]')).toBeNull();
        expect(asks()).toEqual([]);
        await measure(400);
        expect(document.querySelector('[data-region="dim-cal-dayview"]')).not.toBeNull();
        expect(asks()).toEqual([]);
    });

    it('lets a desktop-width first entry replace the guess', async() => {
        media(true);
        await draw(props([], {view: 'day', day: '2026-11-18'}));
        await measure(900);
        expect(document.querySelector('[data-region="dim-cal-dayview"]')).toBeNull();
        expect(choices()).toEqual(['Month', 'Week']);
        expect((document.querySelector('[data-region="dim-cal-day"]') as HTMLElement).dataset.day).toBe('2026-11-18');
    });

    it('keeps today\'s behaviour when the query does not match', async() => {
        media(false);
        await draw(props([], {view: 'day', day: '2026-11-18'}));
        expect(document.querySelector('[data-region="dim-cal-dayview"]')).toBeNull();
        expect((document.querySelector('[data-region="dim-cal-day"]') as HTMLElement).dataset.day).toBe('2026-11-18');
        expect(choices()).toEqual(['Month', 'Week']);
        await measure(400);
        expect(document.querySelector('[data-region="dim-cal-dayview"]')).not.toBeNull();
    });
});
