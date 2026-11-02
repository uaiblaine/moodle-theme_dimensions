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

    it('Enter opens the day\'s drawer, never core\'s day view, which the drawer links', async() => {
        await mount(props());
        fireEvent.click(day('2026-11-17'));
        const drawer = await waitFor(() => region());
        expect(drawer.querySelector<HTMLAnchorElement>('.dim-cal__dayclassic')?.href)
            .toContain('view=day&cal_d=17&cal_m=11&cal_y=2026');
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
        expect(dialog.querySelector<HTMLAnchorElement>('[data-action="dim-cal-course"]')?.href)
            .toBe('https://example.com/course/view.php?id=4');
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
        await act(async() => {
            fireEvent.click(button);
        });
        expect(modal.setStartTime).toHaveBeenCalledWith(at('2026-11-10', 10));
        document.body.replaceChildren();
        await mount(props([], {day: '2026-11-10'}));
        expect(region().querySelector('[data-action="dim-cal-new"]')).toBeNull();
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
