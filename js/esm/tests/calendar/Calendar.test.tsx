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
 * range and the state services are core/ajax's fetchOne, mocked; a day's opening is core/location's redirect.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, fireEvent, render, waitFor} from '@testing-library/react';
import {fetchOne} from '@moodle/lms/core/ajax';
import Calendar, {QUIET} from '../../src/calendar/Calendar';
import type {Answer, Props} from '../../src/calendar/model';
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

    it('Enter opens the day in core\'s day view', async() => {
        expectRedirect({urlContains: 'view=day&cal_d=17&cal_m=11&cal_y=2026'});
        await mount(props());
        fireEvent.click(day('2026-11-17'));
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

    it('"+N" names the day it opens', async() => {
        expectRedirect({urlContains: 'cal_d=18'});
        await mount(props(Array.from({length: 9}, (unused, index) => item({id: index + 1, timestart: at('2026-11-18', 8 + index)}))));
        const more = document.querySelector<HTMLElement>('.dim-cal__overflow') as HTMLElement;
        expect(more.getAttribute('aria-label')).toBe('5 more on November 18');
        fireEvent.click(more);
    });
});

describe('the keys and the filters', () => {
    it('a chip is a toggle button; off, its key is not asked for and the address is replaced', async() => {
        const replace = jest.spyOn(window.history, 'replaceState');
        await mount(props([item({id: 1, key: 'group'})]));
        const chip = document.querySelector<HTMLElement>('[data-region="dim-cal-chips"] [data-key="group"]') as HTMLElement;
        expect(chip.getAttribute('aria-pressed')).toBe('true');
        await act(async() => {
            fireEvent.click(chip);
        });
        expect(chip.getAttribute('aria-pressed')).toBe('false');
        expect(ranges()[0].keys).toEqual(['site', 'category', 'course', 'user', 'activity', 'other', 'open']);
        expect(replace.mock.calls.some(([, , url]) => String(url).includes('keys%5B%5D=site'))).toBe(true);
        replace.mockRestore();
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

    it('"More filters" counts what is on and asks the range with it', async() => {
        await mount(props());
        const button = document.querySelector<HTMLElement>('[data-region="dim-cal-more"] button') as HTMLElement;
        fireEvent.click(button);
        await act(async() => {
            fireEvent.click(document.querySelector('[data-region="dim-cal-more"] input[name="model"][value="2"]') as HTMLElement);
        });
        expect(ranges()[0]).toEqual(expect.objectContaining({model: [2]}));
        expect(button.textContent).toContain('1 filters on');
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
