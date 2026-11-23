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
 * Tests for the week view on both shells, by what a reader and a screen reader meet: the grid and its labels, the
 * strip, overlaps side by side, the "now" line, the view switch and its address, one range call per week, the
 * keyboard contract, and the phone's agenda.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, fireEvent, render, waitFor} from '@testing-library/react';
import type {ReactElement} from 'react';
import {fetchOne} from '@moodle/lms/core/ajax';
import Calendar from '../../src/calendar/Calendar';
import {forget} from '../../src/calendar/description';
import {forget as forgetEditing} from '../../src/calendar/editing';
import type {Item, Props} from '../../src/calendar/model';
import PublicCalendar from '../../src/calendar/PublicCalendar';
import type {PublicProps} from '../../src/calendar/PublicCalendar';
import {TICK} from '../../src/calendar/WeekGrid';
import {STRINGS, answer, at, item, props} from './fixture';

jest.mock('@moodle/lms/core/ajax', () => ({fetchOne: jest.fn()}));
jest.mock('@moodle/lms/core/location', () => ({redirect: jest.fn()}));

const fetched = fetchOne as jest.Mock;

/** The global fetch, which the visitor's halves are asked through. */
const fetchedPublic = jest.fn();

/** The visitor's halves' address prefixes. */
const EVENTS = 'http://localhost/r.php/theme_dimensions/calendar/public/events/t1/en';
const COURSES = 'http://localhost/r.php/theme_dimensions/calendar/public/courses/t2/en';

const HOUR = 3600;

beforeEach(() => {
    document.documentElement.lang = 'en';
    Object.entries(STRINGS).forEach(([key, value]) => mockString(key, 'theme_dimensions', value));
    fetched.mockReset();
    fetched.mockImplementation(({methodname, args}: {methodname: string, args: {from: string, to: string}}) =>
        Promise.resolve(methodname === 'theme_dimensions_get_calendar_range' ? answer([], args.from, args.to) : {}));
    fetchedPublic.mockReset();
    fetchedPublic.mockImplementation((url: string) => {
        const [from, to] = url.split('/').slice(-2);
        return Promise.resolve({ok: true, status: 200, json: () => Promise.resolve({...answer([], from, to), descriptions: []})});
    });
    (global as unknown as {fetch: unknown}).fetch = fetchedPublic;
    window.history.replaceState({}, '', '/r.php/theme_dimensions/calendar');
    forget();
});

afterEach(() => {
    document.body.replaceChildren();
    delete (global as unknown as {fetch?: unknown}).fetch;
    jest.useRealTimers();
});

/**
 * The week of 15 November 2026 (Sunday first), with today the 17th.
 *
 * @param {Array} events The answer's items.
 * @param {object} more Props that differ.
 * @returns {object} The props.
 */
const week = (events: Item[] = [], more: Partial<Props> = {}): Props => props(events, {
    view: 'week',
    week: '2026-11-15',
    answer: answer(events, '2026-11-15', '2026-11-21'),
    ...more,
});

/**
 * Render a shell inside a page like calendar_page.mustache's and wait for its grid.
 *
 * @param {object} element The shell.
 * @returns {Promise} The page's root.
 */
const mount = async(element: ReactElement): Promise<HTMLElement> => {
    const page = document.createElement('div');
    page.dataset.region = 'dim-cal';
    const server = document.createElement('div');
    server.dataset.region = 'dim-cal-server';
    const node = document.createElement('div');
    page.append(server, node);
    document.body.append(page);
    await act(async() => {
        render(element, {container: node});
    });
    await waitFor(() => expect(page.querySelector('[role="grid"]')).not.toBeNull());
    return page;
};

/**
 * A day's header, or its button in the month grid.
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
 * An item of the week's grid by its name.
 *
 * @param {string} name The item's name.
 * @returns {HTMLElement}
 */
const named = (name: string): HTMLElement => {
    const found = Array.from(document.querySelectorAll<HTMLElement>('[data-region="dim-cal-weekgrid"] [data-cal-pop]'))
        .find((element) => element.textContent?.includes(name) || element.getAttribute('aria-label')?.startsWith(name));
    if (!found) {
        throw new Error(`No item ${name}`);
    }
    return found;
};

/**
 * The range requests made.
 *
 * @returns {Array} Their arguments.
 */
const ranges = (): Array<Record<string, unknown>> => fetched.mock.calls
    .filter(([request]) => request.methodname === 'theme_dimensions_get_calendar_range').map(([request]) => request.args);

/**
 * The view switch's button.
 *
 * @param {string} view The view.
 * @returns {HTMLElement}
 */
const view = (view: string): HTMLElement => document.querySelector(`[data-action="dim-cal-view-${view}"]`) as HTMLElement;

/** Two overlapping meetings on the 17th, a point item and a breakfast on the 18th, and a three-day congress. */
const BUSY = [
    item({id: 1, name: 'Mentoring', timestart: at('2026-11-17', 14), timeduration: HOUR}),
    item({id: 2, name: 'Area meeting', timestart: at('2026-11-17', 14) + 1800, timeduration: HOUR}),
    item({id: 3, name: 'Report due', timestart: at('2026-11-18', 16), timeduration: 0}),
    item({id: 4, name: 'Congress', timestart: at('2026-11-16'), timeduration: 3 * 86400}),
    item({id: 5, name: 'Breakfast', timestart: at('2026-11-18', 9), timeduration: HOUR}),
];

describe('the week\'s first paint', () => {
    it('draws seven columns from the first weekday, labelled by the week\'s caption, and asks nothing', async() => {
        const page = await mount(<Calendar {...week()} />);
        expect(fetched).not.toHaveBeenCalled();
        const grid = page.querySelector('[data-region="dim-cal-weekgrid"]') as HTMLElement;
        expect(grid.getAttribute('role')).toBe('grid');
        expect(document.getElementById(grid.getAttribute('aria-labelledby') || '')?.textContent)
            .toBe('November 15 to November 21, 2026');
        const heads = Array.from(grid.querySelectorAll('[role="columnheader"] [data-role="day"]'))
            .map((button) => (button as HTMLElement).dataset.day);
        expect(heads).toEqual(['2026-11-15', '2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-21']);
        expect(view('week').getAttribute('aria-pressed')).toBe('true');
        expect(view('month').getAttribute('aria-pressed')).toBe('false');
        expect(document.querySelector('[data-action="dim-cal-prev"]')?.getAttribute('aria-label')).toBe('Previous week');
    });

    it('starts on Monday for a reader whose week does', async() => {
        await mount(<Calendar {...week([], {firstday: 1, week: '2026-11-16'})} />);
        const heads = Array.from(document.querySelectorAll('[role="columnheader"] [data-role="day"]'));
        expect((heads[0] as HTMLElement).dataset.day).toBe('2026-11-16');
        expect((heads[6] as HTMLElement).dataset.day).toBe('2026-11-22');
    });

    it('draws the month when the props name no week', async() => {
        await mount(<Calendar {...props([], {view: 'week', week: ''})} />);
        expect(document.querySelector('[data-region="dim-cal-grid"]')).not.toBeNull();
        expect(document.querySelector('[data-region="dim-cal-weekgrid"]')).toBeNull();
    });

    it('has a row header per row and the day\'s label on each header, today marked', async() => {
        await mount(<Calendar {...week(BUSY)} />);
        const rowheaders = Array.from(document.querySelectorAll('[role="rowheader"]')).map((cell) => cell.textContent);
        expect(rowheaders[0]).toBe('All day');
        expect(rowheaders[1]).toContain('At set times');
        expect(day('2026-11-17').getAttribute('aria-label')).toBe('Tuesday, November 17, 2026, 3 items, today');
        expect(day('2026-11-17').getAttribute('aria-current')).toBe('date');
        expect(document.querySelectorAll('[data-region="dim-cal-weekcol"][role="gridcell"]')).toHaveLength(7);
    });
});

describe('the week\'s layout', () => {
    it('lays overlapping items side by side, each saying so in words', async() => {
        await mount(<Calendar {...week(BUSY)} />);
        const mentoring = named('Mentoring');
        const meeting = named('Area meeting');
        expect([mentoring.dataset.column, mentoring.dataset.columns]).toEqual(['0', '2']);
        expect([meeting.dataset.column, meeting.dataset.columns]).toEqual(['1', '2']);
        expect(mentoring.textContent).toContain('Overlaps');
        expect(named('Breakfast').textContent).not.toContain('Overlaps');
        expect(mentoring.closest('[data-region="dim-cal-weekcol"]')?.getAttribute('data-day')).toBe('2026-11-17');
    });

    it('draws a multi-day item once, as a bar across its columns in the strip', async() => {
        await mount(<Calendar {...week(BUSY)} />);
        const bars = document.querySelectorAll('[data-region="dim-cal-weekstrip"] .dim-cal__bar');
        expect(bars).toHaveLength(1);
        const bar = bars[0] as HTMLElement;
        expect(bar.getAttribute('aria-label')).toBe('Congress, November 16 to November 18');
        expect([bar.dataset.first, bar.dataset.last]).toEqual(['2026-11-16', '2026-11-18']);
        expect(document.querySelectorAll('[data-region="dim-cal-weekhours"] [data-cal-pop]')).toHaveLength(4);
    });

    it('draws the whole day in a scroll box of its own, a labelled rowgroup of the grid (R1c)', async() => {
        await mount(<Calendar {...week(BUSY)} />);
        const hours = document.querySelector('[data-region="dim-cal-weekhours"]') as HTMLElement;
        expect(hours.style.getPropertyValue('--dim-cal-hourcount')).toBe('24');
        const hour = new Intl.DateTimeFormat('en', {hour: '2-digit', minute: '2-digit', timeZone: 'UTC'});
        const labels = Array.from(hours.querySelectorAll('.dim-cal__hour')).map((label) => label.textContent);
        expect(labels).toHaveLength(24);
        expect([labels[0], labels[23]]).toEqual([hour.format(0), hour.format(at('1970-01-01', 23) * 1000)]);
        const box = hours.closest('[data-region="dim-cal-hourscroll"]') as HTMLElement;
        expect(box.getAttribute('role')).toBe('rowgroup');
        expect(box.getAttribute('aria-label')).toBe('Hours of the day');
        expect(box.tabIndex).toBe(0);
        expect(box.closest('[role="grid"]')).not.toBeNull();
        const breakfast = hours.querySelector<HTMLElement>('[data-cal-pop][data-id="5"]') as HTMLElement;
        expect(breakfast.style.getPropertyValue('--dim-cal-top')).toBe(`${(9 / 24) * 100}%`);
        expect(breakfast.style.getPropertyValue('--dim-cal-height')).toBe(`${(1 / 24) * 100}%`);
    });

    it('says the week is empty and draws no hour body when nothing is on', async() => {
        await mount(<Calendar {...week()} />);
        expect(document.querySelector('[data-region="dim-cal-weekhours"]')).toBeNull();
        expect(document.querySelector('[data-region="dim-cal-weekempty"]')?.textContent).toBe('Nothing this week.');
    });
});

describe('the "now" line', () => {
    it('is drawn on today\'s column at the time, hidden from the screen reader', async() => {
        await mount(<Calendar {...week(BUSY, {now: at('2026-11-17', 12)})} />);
        const line = document.querySelector('[data-region="dim-cal-now"]') as HTMLElement;
        expect(line.closest('[data-region="dim-cal-weekcol"]')?.getAttribute('data-day')).toBe('2026-11-17');
        expect(line.getAttribute('aria-hidden')).toBe('true');
        expect(line.style.getPropertyValue('--dim-cal-top')).toBe(`${(12 / 24) * 100}%`);
    });

    it('is not drawn on a week that does not hold today', async() => {
        const later = BUSY.map((one) => ({...one, timestart: one.timestart + 7 * 86400}));
        await mount(<Calendar {...week(later, {week: '2026-11-22', now: at('2026-11-17', 12)})} />);
        expect(document.querySelectorAll('[data-region="dim-cal-weekcol"]')).toHaveLength(7);
        expect(document.querySelector('[data-region="dim-cal-now"]')).toBeNull();
    });

    it('moves with the minutes since the paint', async() => {
        jest.useFakeTimers();
        await mount(<Calendar {...week(BUSY, {now: at('2026-11-17', 12)})} />);
        await act(async() => {
            jest.advanceTimersByTime(60 * TICK);
        });
        const line = document.querySelector('[data-region="dim-cal-now"]') as HTMLElement;
        expect(line.style.getPropertyValue('--dim-cal-top')).toBe(`${(13 / 24) * 100}%`);
    });
});

describe('the view switch', () => {
    it('shows the week holding the selected day: one range call of seven days, the address pushed', async() => {
        const push = jest.spyOn(window.history, 'pushState');
        await mount(<Calendar {...props()} />);
        await act(async() => {
            fireEvent.click(view('week'));
        });
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-weekgrid"]')).not.toBeNull());
        expect(ranges()).toEqual([expect.objectContaining({from: '2026-11-15', to: '2026-11-21'})]);
        expect(push).toHaveBeenCalledTimes(1);
        const url = new URL(String(push.mock.calls[0][2]));
        expect(url.searchParams.get('view')).toBe('week');
        expect(url.searchParams.get('week')).toBe('2026-11-15');
        expect(url.searchParams.has('month')).toBe(false);
        push.mockRestore();
    });

    it('goes back to the month of the selected day, the address naming the month alone', async() => {
        const push = jest.spyOn(window.history, 'pushState');
        await mount(<Calendar {...week([], {week: '2026-11-29', today: '2026-11-17'})} />);
        fireEvent.keyDown(day('2026-11-29'), {key: 'ArrowRight'});
        fireEvent.keyDown(day('2026-11-30'), {key: 'ArrowRight'});
        await act(async() => {
            fireEvent.click(view('month'));
        });
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-grid"]')).not.toBeNull());
        expect(ranges()).toEqual([expect.objectContaining({from: '2026-11-29', to: '2027-01-02'})]);
        const url = new URL(String(push.mock.calls[0][2]));
        expect(url.searchParams.get('month')).toBe('2026-12');
        expect(url.searchParams.has('view')).toBe(false);
        expect(url.searchParams.has('week')).toBe(false);
        push.mockRestore();
    });

    it('keeps the open day when the week holds it', async() => {
        await mount(<Calendar {...props([], {day: '2026-11-18'})} />);
        expect(document.querySelector('[data-region="dim-cal-day"]')).not.toBeNull();
        await act(async() => {
            fireEvent.click(view('week'));
        });
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-weekgrid"]')).not.toBeNull());
        expect(document.querySelector('[data-region="dim-cal-day"]')?.getAttribute('data-day')).toBe('2026-11-18');
        expect(new URL(window.location.href).searchParams.get('day')).toBe('2026-11-18');
    });

    it('never sends the view to the remembered state', async() => {
        jest.useFakeTimers();
        await mount(<Calendar {...week()} />);
        await act(async() => {
            fireEvent.click(document.querySelector('[data-region="dim-cal-types"] [data-key="site"]') as HTMLElement);
        });
        await act(async() => {
            jest.advanceTimersByTime(1000);
        });
        const remembered = fetched.mock.calls
            .filter(([request]) => request.methodname === 'theme_dimensions_remember_calendar_state');
        expect(remembered).toHaveLength(1);
        expect(Object.keys(remembered[0][0].args)).not.toContain('view');
        expect(Object.keys(remembered[0][0].args)).not.toContain('week');
        expect(new URL(window.location.href).searchParams.get('view')).toBe('week');
    });

    it('reads the view and the week again on Back and Forward', async() => {
        await mount(<Calendar {...props()} />);
        window.history.replaceState({}, '', '/r.php/theme_dimensions/calendar?view=week&week=2026-11-04');
        await act(async() => {
            window.dispatchEvent(new PopStateEvent('popstate'));
        });
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-weekgrid"]')).not.toBeNull());
        expect(ranges()).toEqual([expect.objectContaining({from: '2026-11-01', to: '2026-11-07'})]);
    });
});

describe('the week\'s navigation', () => {
    it('moves a week with the arrows, pushing the address', async() => {
        const push = jest.spyOn(window.history, 'pushState');
        await mount(<Calendar {...week()} />);
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        expect(ranges()).toEqual([expect.objectContaining({from: '2026-11-22', to: '2026-11-28'})]);
        expect(new URL(String(push.mock.calls[0][2])).searchParams.get('week')).toBe('2026-11-22');
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-caption"]')?.textContent)
            .toBe('November 22 to November 28, 2026'));
        push.mockRestore();
    });

    it('the latest request wins', async() => {
        const answers: Array<(value: unknown) => void> = [];
        fetched.mockImplementation(() => new Promise((resolve) => answers.push(resolve)));
        await mount(<Calendar {...week()} />);
        const next = document.querySelector('[data-action="dim-cal-next"]') as HTMLElement;
        const prev = document.querySelector('[data-action="dim-cal-prev"]') as HTMLElement;
        fireEvent.click(next);
        fireEvent.click(prev);
        await act(async() => {
            answers[1](answer([item({id: 9, name: 'Earlier', timestart: at('2026-11-10', 9)})], '2026-11-08', '2026-11-14'));
        });
        await act(async() => {
            answers[0](answer([item({id: 8, name: 'Later', timestart: at('2026-11-24', 9)})], '2026-11-22', '2026-11-28'));
        });
        expect(document.querySelector('[data-region="dim-cal-weekgrid"]')?.textContent).toContain('Earlier');
        expect(document.querySelector('[data-region="dim-cal-weekgrid"]')?.textContent).not.toContain('Later');
    });
});

describe('the week\'s keyboard', () => {
    it('has one Tab stop, the selected day\'s header; the items are out of the Tab order', async() => {
        await mount(<Calendar {...week(BUSY)} />);
        const stops = Array.from(document.querySelectorAll<HTMLElement>('[data-region="dim-cal-weekgrid"] [data-role="day"]'))
            .filter((button) => button.tabIndex === 0).map((button) => button.dataset.day);
        expect(stops).toEqual(['2026-11-17']);
        document.querySelectorAll<HTMLElement>('[data-region="dim-cal-weekgrid"] [data-cal-pop]')
            .forEach((element) => expect(element.tabIndex).toBe(-1));
    });

    it('moves a day with the arrows and to the week\'s ends with Home and End', async() => {
        await mount(<Calendar {...week()} />);
        fireEvent.keyDown(day('2026-11-17'), {key: 'ArrowRight'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-18')));
        fireEvent.keyDown(day('2026-11-18'), {key: 'End'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-21')));
        fireEvent.keyDown(day('2026-11-21'), {key: 'Home'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-15')));
        expect(fetched).not.toHaveBeenCalled();
    });

    it('fetches the adjacent week past its ends, and a week away with PageDown, the focus on the day', async() => {
        await mount(<Calendar {...week()} />);
        fireEvent.keyDown(day('2026-11-17'), {key: 'Home'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-15')));
        await act(async() => {
            fireEvent.keyDown(day('2026-11-15'), {key: 'ArrowLeft'});
        });
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-14')));
        expect(ranges()).toEqual([expect.objectContaining({from: '2026-11-08', to: '2026-11-14'})]);
        await act(async() => {
            fireEvent.keyDown(day('2026-11-14'), {key: 'PageDown'});
        });
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-21')));
        expect(ranges()[1]).toEqual(expect.objectContaining({from: '2026-11-15', to: '2026-11-21'}));
    });

    it('enters the day\'s items with ArrowDown, walks them, and leaves them with ArrowUp and Escape', async() => {
        await mount(<Calendar {...week(BUSY)} />);
        fireEvent.keyDown(day('2026-11-17'), {key: 'ArrowDown'});
        expect(document.activeElement).toBe(named('Congress'));
        fireEvent.keyDown(named('Congress'), {key: 'ArrowDown'});
        expect(document.activeElement).toBe(named('Mentoring'));
        fireEvent.keyDown(named('Mentoring'), {key: 'ArrowDown'});
        expect(document.activeElement).toBe(named('Area meeting'));
        fireEvent.keyDown(named('Area meeting'), {key: 'Escape'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-17')));
        fireEvent.keyDown(day('2026-11-17'), {key: 'F2'});
        expect(document.activeElement).toBe(named('Congress'));
        fireEvent.keyDown(named('Congress'), {key: 'ArrowUp'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-17')));
    });

    it('moves across days to the item nearest in time, or the day\'s header', async() => {
        await mount(<Calendar {...week(BUSY)} />);
        fireEvent.keyDown(day('2026-11-17'), {key: 'ArrowDown'});
        fireEvent.keyDown(named('Congress'), {key: 'ArrowDown'});
        fireEvent.keyDown(named('Mentoring'), {key: 'ArrowRight'});
        expect(document.activeElement).toBe(named('Report due'));
        fireEvent.keyDown(named('Report due'), {key: 'ArrowRight'});
        await waitFor(() => expect(document.activeElement).toBe(day('2026-11-19')));
    });

    it('opens the popover on Enter from an item, the day\'s drawer on Enter from a header', async() => {
        // The address the server wrote for the week.
        window.history.replaceState({}, '', '/r.php/theme_dimensions/calendar?view=week&week=2026-11-15');
        await mount(<Calendar {...week(BUSY)} />);
        fireEvent.keyDown(day('2026-11-17'), {key: 'ArrowDown'});
        fireEvent.keyDown(named('Congress'), {key: 'ArrowDown'});
        await act(async() => {
            fireEvent.keyDown(named('Mentoring'), {key: 'Enter'});
        });
        expect(document.querySelector('[data-region="dim-cal-popover"]')?.textContent).toContain('Mentoring');
        expect(document.querySelector('[data-region="dim-cal-overlap"]')?.textContent).toContain('Area meeting');
        await act(async() => {
            fireEvent.click(day('2026-11-18'));
        });
        const drawer = document.querySelector('[data-region="dim-cal-day"]') as HTMLElement;
        expect(drawer.getAttribute('data-day')).toBe('2026-11-18');
        expect(new URL(window.location.href).searchParams.get('day')).toBe('2026-11-18');
        expect(new URL(window.location.href).searchParams.get('view')).toBe('week');
    });
});

describe('the phone\'s week', () => {
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

    it('is a seven-day agenda below the breakpoint, a day header opening its sheet', async() => {
        await mount(<Calendar {...week(BUSY)} />);
        expect(observers).toHaveLength(1);
        expect(watched).toEqual([
            document.querySelector('[data-region="dim-cal-body"]'),
            document.querySelector('[data-region="dim-cal-week"]'),
        ]);
        await act(async() => {
            observers[0]([{target: watched[0], contentRect: {width: 400, height: 600}}]);
        });
        const agenda = document.querySelector('[data-region="dim-cal-weekagenda"]') as HTMLElement;
        expect(document.querySelector('[data-region="dim-cal-weekgrid"]')).toBeNull();
        expect(agenda.querySelectorAll('section')).toHaveLength(7);
        const tuesday = agenda.querySelector('section[data-day="2026-11-17"]') as HTMLElement;
        expect(Array.from(tuesday.querySelectorAll('[data-cal-pop] .dim-cal__dayname')).map((name) => name.textContent))
            .toEqual(['Congress', 'Mentoring', 'Area meeting']);
        expect(tuesday.querySelector('[data-cal-pop] .dim-cal__daywhen')?.textContent).toBe('Nov 16–Nov 18');
        expect(tuesday.querySelectorAll('.dim-cal__overlapmark')).toHaveLength(2);
        expect(agenda.querySelector('section[data-day="2026-11-15"]')?.textContent).toContain('Nothing on this day.');
        await act(async() => {
            fireEvent.click(day('2026-11-17'));
        });
        expect(document.querySelector('.dim-cal__drawer--sheet[data-region="dim-cal-day"]')?.getAttribute('data-day'))
            .toBe('2026-11-17');
    });

    it('hands the week\'s height to the frame, for the drawer beside it', async() => {
        await mount(<Calendar {...week(BUSY)} />);
        const frame = document.querySelector('[data-region="dim-cal-body"]') as HTMLElement;
        const view = document.querySelector('[data-region="dim-cal-week"]') as Element;
        await act(async() => {
            observers[0]([{target: view, contentRect: {width: 900, height: 612.4}}]);
        });
        expect(frame.style.getPropertyValue('--dim-cal-gridheight')).toBe('612px');
        // The frame's own entry carries the width alone: it never writes its (drawer-inclusive) height.
        await act(async() => {
            observers[0]([{target: frame, contentRect: {width: 900, height: 999}}]);
        });
        expect(frame.style.getPropertyValue('--dim-cal-gridheight')).toBe('612px');
        // A re-render (the drawer opening changes the frame's class) leaves the same frame carrying it.
        await act(async() => {
            fireEvent.click(day('2026-11-17'));
        });
        const after = document.querySelector('[data-region="dim-cal-body"]') as HTMLElement;
        expect(after).toBe(frame);
        expect(after.style.getPropertyValue('--dim-cal-gridheight')).toBe('612px');
    });

    it('stays a time grid above the breakpoint', async() => {
        await mount(<Calendar {...week(BUSY)} />);
        await act(async() => {
            observers[0]([{target: watched[0], contentRect: {width: 900, height: 600}}]);
        });
        expect(document.querySelector('[data-region="dim-cal-weekgrid"]')).not.toBeNull();
        expect(document.querySelector('[data-region="dim-cal-weekagenda"]')).toBeNull();
    });

    it('settles: the week\'s own narrowed width never decides the phone layout, an equal height reports nothing', async() => {
        await mount(<Calendar {...week(BUSY)} />);
        const frame = document.querySelector('[data-region="dim-cal-body"]') as HTMLElement;
        const view = document.querySelector('[data-region="dim-cal-week"]') as Element;
        await act(async() => {
            observers[0]([{target: frame, contentRect: {width: 900, height: 700}}]);
        });
        await act(async() => {
            fireEvent.click(day('2026-11-17'));
        });
        const changes: string[] = [];
        const watcher = new MutationObserver(() => changes.push(frame.style.getPropertyValue('--dim-cal-gridheight')));
        watcher.observe(frame, {attributes: true, attributeFilter: ['style']});
        // The loop's entries: the drawer narrows the week's box under the breakpoint while the frame stays wide.
        for (let pass = 0; pass < 6; pass++) {
            await act(async() => {
                observers[0]([{target: view, contentRect: {width: 490, height: 835}}]);
            });
            expect(document.querySelector('[data-region="dim-cal-weekagenda"]')).toBeNull();
            await act(async() => {
                observers[0]([{target: frame, contentRect: {width: 900, height: 700}}]);
            });
        }
        await act(async() => {
            changes.push(...watcher.takeRecords().map(() => frame.style.getPropertyValue('--dim-cal-gridheight')));
        });
        watcher.disconnect();
        expect(document.querySelector('[data-region="dim-cal-weekgrid"]')).not.toBeNull();
        expect(document.querySelector('[data-region="dim-cal-weekagenda"]')).toBeNull();
        expect(document.querySelector('.dim-cal__body--drawer')).not.toBeNull();
        // Six equal reports wrote the property once (835), not six times.
        expect(frame.style.getPropertyValue('--dim-cal-gridheight')).toBe('835px');
        expect(changes.length).toBe(1);
    });
});

describe('the visitor\'s week', () => {
    /**
     * The visitor's props for the week of 15 November 2026.
     *
     * @param {object} more Props that differ.
     * @returns {object}
     */
    const visitor = (more: Partial<PublicProps> = {}): PublicProps => ({
        month: '2026-11',
        view: 'week',
        week: '2026-11-15',
        day: '',
        today: '2026-11-17',
        now: at('2026-11-17', 10),
        firstday: 0,
        timezone: 'UTC',
        answer: {...answer(BUSY.slice(0, 2), '2026-11-15', '2026-11-21'), descriptions: []},
        keys: ['category', 'open'],
        modalities: [],
        urls: {
            page: 'http://localhost/r.php/theme_dimensions/calendar',
            home: 'http://localhost/',
            events: EVENTS,
            courses: COURSES,
        },
        ...more,
    });

    it('draws the week from its props, the overlaps side by side, and asks nothing', async() => {
        await mount(<PublicCalendar {...visitor()} />);
        expect(fetched).not.toHaveBeenCalled();
        expect(fetchedPublic).not.toHaveBeenCalled();
        expect([named('Mentoring').dataset.column, named('Area meeting').dataset.column]).toEqual(['0', '1']);
    });

    it('asks its own halves for a week with no cookie, and writes the week into the address', async() => {
        await mount(<PublicCalendar {...visitor()} />);
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        expect(fetched).not.toHaveBeenCalled();
        const options = {credentials: 'omit', headers: {Accept: 'application/json'}};
        expect(fetchedPublic.mock.calls).toEqual([
            [`${EVENTS}/2026-11-22/2026-11-28`, options],
            [`${COURSES}/2026-11-22/2026-11-28`, options],
        ]);
        const url = new URL(window.location.href);
        expect([url.searchParams.get('view'), url.searchParams.get('week')]).toEqual(['week', '2026-11-22']);
    });

    it('switches back to the month', async() => {
        await mount(<PublicCalendar {...visitor()} />);
        await act(async() => {
            fireEvent.click(view('month'));
        });
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-grid"]')).not.toBeNull());
        expect(fetchedPublic.mock.calls[0][0]).toBe(`${EVENTS}/2026-11-01/2026-12-05`);
        expect(new URL(window.location.href).searchParams.get('month')).toBe('2026-11');
    });
});

describe('the weekend and a double-click (R1)', () => {
    it('marks Saturday and Sunday in the header, the strip and the hour body', async() => {
        await mount(<Calendar {...week([item({id: 1, name: 'Mentoring', timestart: at('2026-11-17', 14)})])} />);
        const marked = (selector: string, weekend: string): string[] => Array.from(document.querySelectorAll<HTMLElement>(selector))
            .map((element) => (element.classList.contains(weekend) ? 'w' : '-'));
        expect(marked('.dim-cal__weekheadcell', 'dim-cal__weekheadcell--weekend')).toEqual(['w', '-', '-', '-', '-', '-', 'w']);
        expect(marked('.dim-cal__weekstrip .dim-cal__ground', 'dim-cal__ground--weekend')).toEqual(['w', '-', '-', '-', '-', '-', 'w']);
        expect(marked('[data-region="dim-cal-weekcol"]', 'dim-cal__weekcol--weekend')).toEqual(['w', '-', '-', '-', '-', '-', 'w']);
    });

    it('opens core\'s form at a double-clicked hour of the body, and on a day of the strip', async() => {
        const modal = {setContextId: jest.fn(), setCourseId: jest.fn(), setEventId: jest.fn(), setStartTime: jest.fn(), show: jest.fn()};
        mockAmdModule('jquery', () => ({on: () => undefined}));
        mockAmdModule('core_calendar/events', {created: 'c', updated: 'u', deleted: 'd'});
        mockAmdModule('core_calendar/modal_event_form', {create: jest.fn(() => Promise.resolve(modal))});
        forgetEditing();
        await mount(<Calendar {...week([item({id: 1, name: 'Mentoring', timestart: at('2026-11-17', 14)})],
            {edits: {create: true, context: 2, course: 1}})} />);
        const column = document.querySelector<HTMLElement>('[data-region="dim-cal-weekcol"][data-day="2026-11-18"]') as HTMLElement;
        const slot = column.querySelector<HTMLElement>('.dim-cal__slot[data-hour="14"]') as HTMLElement;
        await act(async() => {
            fireEvent.doubleClick(slot);
        });
        expect(modal.setStartTime).toHaveBeenLastCalledWith(at('2026-11-18', 14));
        const strip = document.querySelector<HTMLElement>('.dim-cal__weekstrip .dim-cal__day[data-day="2026-11-19"] .dim-cal__ground');
        await act(async() => {
            fireEvent.doubleClick(strip as HTMLElement);
        });
        expect(modal.setStartTime).toHaveBeenLastCalledWith(at('2026-11-19', 10));
        await act(async() => {
            fireEvent.doubleClick(named('Mentoring'));
        });
        expect(modal.show).toHaveBeenCalledTimes(2);
    });

    it('opens a day on a click in its column or its strip, and not on an item', async() => {
        await mount(<Calendar {...week([item({id: 1, name: 'Mentoring', timestart: at('2026-11-17', 14)})])} />);
        fireEvent.click(document.querySelector('[data-region="dim-cal-weekcol"][data-day="2026-11-18"]') as HTMLElement, {detail: 1});
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-day"]')?.getAttribute('data-day')).toBe('2026-11-18'));
        await act(async() => {
            fireEvent.click(named('Mentoring'), {detail: 1});
        });
        expect(document.querySelector('[data-region="dim-cal-day"]')?.getAttribute('data-day')).toBe('2026-11-18');
        fireEvent.click(document.querySelector('.dim-cal__weekstrip .dim-cal__day[data-day="2026-11-20"] .dim-cal__ground') as HTMLElement,
            {detail: 1});
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-day"]')?.getAttribute('data-day')).toBe('2026-11-20'));
    });

    it('draws no hour row to double-click while core would not show "New event"', async() => {
        await mount(<Calendar {...week([item({id: 1, name: 'Mentoring', timestart: at('2026-11-17', 14)})])} />);
        expect(document.querySelector('.dim-cal__slot')).toBeNull();
    });
});
