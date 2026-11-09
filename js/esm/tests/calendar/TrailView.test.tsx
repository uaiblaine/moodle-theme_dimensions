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
 * Tests for the calendar's Trilhas view (docs/trail/PLAN.md section 7), through the page's client: the switch, the
 * request, the filters, the bars, the months held, the courses' rows, the connectors and the step's popover.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, fireEvent, render, waitFor} from '@testing-library/react';
import {fetchOne} from '@moodle/lms/core/ajax';
import {createRef} from 'react';
import type {CSSProperties} from 'react';
import Calendar from '../../src/calendar/Calendar';
import TrailScroll from '../../src/calendar/TrailScroll';
import type {ScrollControl} from '../../src/calendar/TrailScroll';
import type {Strings} from '../../src/calendar/strings';
import {forget} from '../../src/calendar/description';
import type {Props} from '../../src/calendar/model';
import type {Trail, TrailAnswer} from '../../src/calendar/trails';
import {STRINGS, answer, at, props} from './fixture';

jest.mock('@moodle/lms/core/ajax', () => ({fetchOne: jest.fn()}));

const fetched = fetchOne as jest.Mock;

/** The Trilhas service. */
const TRAILS = 'theme_dimensions_get_calendar_trails';

/**
 * A trail of two steps in November 2026: the first ends on the 10th, the second runs into December.
 *
 * @param {object} fields The fields that differ.
 * @returns {object}
 */
const trail = (fields: Partial<Trail> = {}): Trail => ({
    id: 7,
    name: 'Trilha de Gestão',
    url: 'https://example.com/r.php/theme_dimensions/landing/7',
    categoryid: 3,
    enrolled: false,
    done: 0,
    first: at('2026-11-02', 12),
    last: at('2026-12-10', 12),
    steps: [
        {
            cmid: 70, position: 1, name: 'Fundamentos', courseid: 71, coursename: 'Fundamentos', categoryid: 3,
            start: at('2026-11-02', 12), end: at('2026-11-10', 12), expected: true, state: '', reason: '', completed: 0,
            url: 'https://example.com/course/view.php?id=71', image: '', next: false,
        },
        {
            cmid: 72, position: 2, name: 'Prática', courseid: 73, coursename: 'Prática supervisionada', categoryid: 3,
            start: at('2026-11-11', 12), end: at('2026-12-10', 12), expected: false, state: '', reason: '', completed: 0,
            url: 'https://example.com/course/view.php?id=73', image: '', next: false,
        },
    ],
    ...fields,
});

/**
 * A trails answer for November 2026, or from November to another month's last day.
 *
 * @param {Array} trails The trails.
 * @param {string} to The last day.
 * @param {string} from The first day.
 * @returns {object}
 */
const trails = (trails: Trail[], to: string = '2026-11-30', from: string = '2026-11-01'): TrailAnswer => ({
    from: from,
    to: to,
    truncated: false,
    options: [{id: 8, name: 'Outra'}, {id: 7, name: 'Trilha de Gestão'}],
    trails: trails,
    categories: [{id: 3, name: 'Gestão', bg: '#ffffff', ink: '#000000'}],
});

/**
 * The last day of the month a number of months after another.
 *
 * @param {string} month The month, YYYY-MM.
 * @param {number} months How many months it covers.
 * @returns {string} The last day, YYYY-MM-DD.
 */
const lastOf = (month: string, months: number): string => new Date(Date.UTC(Number(month.slice(0, 4)),
    Number(month.slice(5, 7)) - 1 + months, 0)).toISOString().slice(0, 10);

/** The service's arguments, as the tests read them. */
type Args = {from: string, to: string, month: string, months: number};

/**
 * The service's answer for the months asked for: the one trail, kept where its period meets them.
 *
 * @param {object} args The arguments.
 * @returns {object}
 */
const answerFor = (args: Args): TrailAnswer => {
    const to = lastOf(args.month, args.months || 1);
    const from = `${args.month}-01`;
    return trails(from <= '2026-12-10' && to >= '2026-11-02' ? [trail()] : [], to, from);
};

beforeEach(() => {
    document.documentElement.lang = 'en';
    Object.entries(STRINGS).forEach(([key, value]) => mockString(key, 'theme_dimensions', value));
    fetched.mockReset();
    fetched.mockImplementation(({methodname, args}: {methodname: string, args: Args}) => {
        if (methodname === TRAILS) {
            return Promise.resolve(answerFor(args));
        }
        return Promise.resolve(methodname === 'theme_dimensions_get_calendar_range' ? answer([], args.from, args.to) : {});
    });
    window.history.replaceState({}, '', '/r.php/theme_dimensions/calendar');
    forget();
});

afterEach(() => {
    document.body.replaceChildren();
});

/**
 * Render the page and wait for its client to draw.
 *
 * @param {object} given The props.
 * @returns {Promise} The page's root.
 */
const mount = async(given: Props): Promise<HTMLElement> => {
    const page = document.createElement('div');
    page.dataset.region = 'dim-cal';
    const node = document.createElement('div');
    page.append(node);
    document.body.append(page);
    await act(async() => {
        render(<Calendar {...given} />, {container: node});
    });
    await waitFor(() => expect(page.classList.contains('dim-cal--mounted')).toBe(true));
    return page;
};

/**
 * The props of a page that offers the Trilhas view, showing it or the month.
 *
 * @param {object|null} shown The trails answer when the Trilhas view is shown, null for the month.
 * @param {object} more Further trails props.
 * @returns {object}
 */
const offered = (
    shown: TrailAnswer | null,
    more: {trail?: number, mine?: boolean, today?: string, zoom?: string, covers?: boolean} = {}
): Props =>
    props([], {
        view: shown ? 'trail' : 'month',
        today: more.today || '2026-11-17',
        trails: {
            offered: true,
            answer: shown,
            trail: more.trail || 0,
            mine: more.mine || false,
            zoom: more.zoom || '',
            icons: ICONS,
            ...(more.covers === undefined ? {} : {covers: more.covers}),
        },
    });

/** Core's chevrons as the server renders them. */
const ICONS = {
    expanded: '<i class="icon fa fa-chevron-down" aria-hidden="true"></i>',
    collapsed: '<i class="icon fa fa-chevron-right" aria-hidden="true"></i>',
    collapsedrtl: '<i class="icon fa fa-chevron-left" aria-hidden="true"></i>',
};

/**
 * A pointer coming over an element or leaving it, of a kind (jsdom has no PointerEvent: a mouse event carrying it).
 *
 * @param {object} element The element.
 * @param {string} type pointerover or pointerout.
 * @param {string} kind The pointer's type.
 * @returns {void}
 */
const point = (element: Element, type: 'pointerover' | 'pointerout', kind: string): void => {
    const event = new MouseEvent(type, {bubbles: true, relatedTarget: null});
    Object.defineProperty(event, 'pointerType', {value: kind});
    fireEvent(element, event);
};

/**
 * The requests made of the Trilhas service.
 *
 * @returns {Array} Their arguments.
 */
const trailRequests = () => fetched.mock.calls.filter(([call]) => call.methodname === TRAILS).map(([call]) => call.args);

describe('the switch', () => {
    it('offers Trilhas only where the page does', async() => {
        await mount(props());
        expect(document.querySelector('[data-action="dim-cal-view-trail"]')).toBeNull();
    });

    it('asks for the month\'s trails, draws them in place of the event types, and writes the view', async() => {
        const page = await mount(offered(null));
        expect(page.querySelector('[data-action="dim-cal-types"]')?.closest('[hidden]')).toBeNull();

        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-view-trail"]') as HTMLElement);
        });

        await waitFor(() => expect(page.querySelector('[data-region="dim-cal-trails"]')).not.toBeNull());
        expect(trailRequests()).toEqual([{month: '2026-11', trail: 0, mine: false, categoryid: 0, recursive: true, months: 2}]);
        expect(page.querySelector('[data-action="dim-cal-view-trail"]')?.getAttribute('aria-pressed')).toBe('true');
        expect(page.querySelector('[data-action="dim-cal-types"]')?.closest('[hidden]')).not.toBeNull();
        expect(page.querySelector('[data-region="dim-cal-trailfilters"]')).not.toBeNull();
        expect(page.querySelector('[data-action="dim-cal-new"]')).toBeNull();
        const url = new URL(window.location.href);
        expect([url.searchParams.get('view'), url.searchParams.get('month')]).toEqual(['trail', '2026-11']);
    });
});

describe('the Trilhas view', () => {
    it('draws a row per step, each bar on its days, and asks nothing on the first paint', async() => {
        const page = await mount(offered(trails([trail()])));
        expect(trailRequests()).toEqual([]);
        const bars = Array.from(page.querySelectorAll<HTMLElement>('.dim-cal__trailbar'));
        expect(bars.map((bar) => bar.textContent)).toEqual(['Fundamentos', 'Prática']);
        expect(bars.map((bar) => bar.style.gridColumn)).toEqual(['3 / 12', '12 / 32']);
        expect(bars[0].classList.contains('dim-cal__trailbar--milestone')).toBe(true);
        expect(bars[1].classList.contains('dim-cal__trailbar--after')).toBe(true);
        expect(page.querySelector('.dim-cal__trailday--today .dim-cal__traildaynum')?.textContent).toBe('17');
        // No "next month" column: the months held are named in the band above the days.
        expect(page.querySelector('.dim-cal__trailday--next')).toBeNull();
        expect(page.querySelector('.dim-cal__trailspill')).toBeNull();
        expect(page.querySelector('[data-region="dim-cal-trailchip"]')).toBeNull();
        const band = page.querySelector<HTMLElement>('[data-region="dim-cal-trailmonths"]') as HTMLElement;
        expect([band.getAttribute('role'), band.getAttribute('aria-label')]).toEqual(['group', 'Months']);
        expect(Array.from(band.querySelectorAll('[data-action="dim-cal-trailmonth"]')).map((button) => button.textContent))
            .toEqual(['November 2026']);
        // The expected date is a diamond on its day.
        const diamonds = Array.from(page.querySelectorAll<HTMLElement>('.dim-cal__trailrow--step .dim-cal__traildiamond'));
        expect(diamonds.map((diamond) => diamond.style.gridColumn)).toEqual(['11']);
        // The trail's own span runs to the end of what is held, its period going on into December.
        const span = page.querySelector<HTMLElement>('.dim-cal__trailspan') as HTMLElement;
        expect([span.style.gridColumn, span.classList.contains('dim-cal__trailspan--after')]).toEqual(['3 / 32', true]);
        expect(page.querySelector('[data-region="dim-cal-trailmeta"]')?.textContent).toBe('Not enrolled · 2 courses');
        expect(page.querySelector('[data-region="dim-cal-trailcount"]')?.textContent).toBe('1 trail · 2 courses');
        // The weekends and today are columns of the ground, never a layout read.
        const weekends = Array.from(page.querySelectorAll<HTMLElement>('.dim-cal__trailweekend'));
        expect(weekends.map((day) => day.style.getPropertyValue('--dim-cal-trail-at')).slice(0, 3)).toEqual(['0', '6', '7']);
        expect(page.querySelector<HTMLElement>('.dim-cal__trailnow')?.style.getPropertyValue('--dim-cal-trail-at')).toBe('16');
    });

    it('draws the month and the next of a first paint as one timeline', async() => {
        const page = await mount(offered(trails([trail()], '2026-12-31')));
        expect(trailRequests()).toEqual([]);
        const bars = Array.from(page.querySelectorAll<HTMLElement>('.dim-cal__trailbar'));
        expect(bars.map((bar) => bar.style.gridColumn)).toEqual(['3 / 12', '12 / 42']);
        expect(bars[1].classList.contains('dim-cal__trailbar--after')).toBe(false);
        expect(page.querySelector<HTMLElement>('[data-region="dim-cal-trailgrid"]')?.style.getPropertyValue('--dim-cal-trail-days'))
            .toBe('61');
        expect(Array.from(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).map((button) => button.textContent))
            .toEqual(['November 2026', 'December 2026']);
        // December's 1st is a line down the rows, and the trail's period ends inside what is held.
        expect(Array.from(page.querySelectorAll<HTMLElement>('.dim-cal__trailmonthedge'))
            .map((edge) => edge.style.getPropertyValue('--dim-cal-trail-at'))).toEqual(['30']);
        expect(page.querySelector('.dim-cal__traildiamond--trail')).not.toBeNull();
    });

    it('joins the courses in the trail\'s order by connectors, aria-hidden, and none for a folded trail', async() => {
        const page = await mount(offered(trails([trail()])));
        const links = page.querySelector<HTMLElement>('[data-region="dim-cal-traillinks"]') as HTMLElement;
        expect(links.getAttribute('aria-hidden')).toBe('true');
        expect(links.closest('#dim-cal-trailsteps-7')).not.toBeNull();
        // Prática starts the day after Fundamentos ends: an elbow at its least into its start, the arrowhead straight
        // off the elbow (no room is left for a line into the bar).
        expect(Array.from(links.children).map((piece) => piece.className)).toEqual([
            'dim-cal__traillink dim-cal__traillink--horizontal',
            'dim-cal__traillink dim-cal__traillink--vertical',
            'dim-cal__traillink dim-cal__traillink--right',
        ]);
        // Placed from the start of the days (the stylesheet's inset-inline-start), never from the left: a right-to-left
        // page mirrors them.
        const first = links.children[0] as HTMLElement;
        expect([first.style.getPropertyValue('--dim-cal-link-start'), first.style.left, first.style.top])
            .toEqual(['30rem', '', '1.6875rem']);
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-trailfold"]') as HTMLElement);
        });
        expect(page.querySelector('[data-region="dim-cal-traillinks"]')).toBeNull();
    });

    it('breaks the chain at a course with no date, whose row says so', async() => {
        const broken = trail();
        broken.steps[0].start = 0;
        broken.steps[0].end = 0;
        const page = await mount(offered(trails([broken])));
        expect(page.querySelector('[data-region="dim-cal-traillinks"]')).toBeNull();
        const rows = Array.from(page.querySelectorAll<HTMLElement>('[data-region="dim-cal-trailrow"]'));
        expect(rows[0].textContent).toContain('No dates');
        expect(rows[0].getAttribute('aria-label')).toBeNull();
        expect(rows[1].getAttribute('aria-label')).toBe('Prática, Nov 11 – Dec 10, 2026: show on the timeline');
    });

    it('carries a learner\'s states and progress', async() => {
        const learner = trail({enrolled: true, done: 1});
        learner.steps[0].state = 'done';
        learner.steps[1].state = 'current';
        learner.steps[1].next = true;
        const page = await mount(offered(trails([learner])));
        const bars = Array.from(page.querySelectorAll<HTMLElement>('.dim-cal__trailbar'));
        expect(bars.map((bar) => bar.dataset.state)).toEqual(['done', 'current']);
        expect(bars[1].classList.contains('dim-cal__trailbar--next')).toBe(true);
        expect(page.querySelector('[data-region="dim-cal-trailmeta"]')?.textContent).toBe('Enrolled · 1 of 2 done');
        expect(Array.from(page.querySelectorAll('.dim-cal__trailnum')).map((number) => number.className)).toEqual([
            'dim-cal__trailnum dim-cal__trailnum--done',
            'dim-cal__trailnum dim-cal__trailnum--current',
        ]);
        // A learner's legend names the states.
        expect(page.querySelector('[data-region="dim-cal-traillegend"]')?.textContent).toContain('Completed');
    });

    it('folds a trail\'s steps away and back with core\'s section toggle, as the view\'s own state', async() => {
        const page = await mount(offered(trails([trail()])));
        const fold = page.querySelector<HTMLElement>('[data-action="dim-cal-trailfold"]') as HTMLElement;
        const steps = page.querySelector<HTMLElement>('#dim-cal-trailsteps-7') as HTMLElement;
        expect([fold.getAttribute('aria-expanded'), fold.getAttribute('aria-controls'), fold.getAttribute('aria-label')])
            .toEqual(['true', 'dim-cal-trailsteps-7', 'Courses of Trilha de Gestão']);
        expect(fold.className).toBe('btn btn-icon icons-collapse-expand dim-cal__trailfold');
        expect(fold.querySelector('.expanded-icon')?.innerHTML).toBe(ICONS.expanded);
        expect(fold.querySelector('.collapsed-icon .dir-rtl-hide')?.innerHTML).toBe(ICONS.collapsed);
        expect(fold.querySelector('.collapsed-icon .dir-ltr-hide')?.innerHTML).toBe(ICONS.collapsedrtl);
        expect(steps.hidden).toBe(false);
        const before = window.location.href;
        const calls = fetched.mock.calls.length;

        await act(async() => {
            fireEvent.click(fold);
        });
        expect(fold.getAttribute('aria-expanded')).toBe('false');
        expect(fold.className).toBe('btn btn-icon icons-collapse-expand dim-cal__trailfold collapsed');
        expect(steps.hidden).toBe(true);
        await act(async() => {
            fireEvent.click(fold);
        });
        expect(fold.getAttribute('aria-expanded')).toBe('true');
        expect(steps.hidden).toBe(false);
        expect([window.location.href, fetched.mock.calls.length]).toEqual([before, calls]);
    });

    it('lights a course\'s bar from its row and its row from the bar, for a mouse only', async() => {
        const page = await mount(offered(trails([trail()])));
        const row = page.querySelector<HTMLElement>('[data-region="dim-cal-trailrow"][data-cmid="72"]') as HTMLElement;
        const bar = page.querySelector<HTMLElement>('[data-region="dim-cal-trailstep"][data-cmid="72"]') as HTMLElement;
        point(row, 'pointerover', 'mouse');
        expect([row.hasAttribute('data-lit'), bar.hasAttribute('data-lit')]).toEqual([true, true]);
        point(row, 'pointerout', 'mouse');
        expect([row.hasAttribute('data-lit'), bar.hasAttribute('data-lit')]).toEqual([false, false]);
        point(bar, 'pointerover', 'mouse');
        expect(row.hasAttribute('data-lit')).toBe(true);
        point(bar, 'pointerout', 'mouse');
        // A touch or a pen has no hover here: it lights nothing.
        point(bar, 'pointerover', 'touch');
        point(row, 'pointerover', 'pen');
        expect([row.hasAttribute('data-lit'), bar.hasAttribute('data-lit')]).toEqual([false, false]);
    });

    it('puts a lit pair out when its trail is folded under the pointer', async() => {
        const page = await mount(offered(trails([trail()])));
        const row = page.querySelector<HTMLElement>('[data-region="dim-cal-trailrow"][data-cmid="72"]') as HTMLElement;
        const bar = page.querySelector<HTMLElement>('[data-region="dim-cal-trailstep"][data-cmid="72"]') as HTMLElement;
        point(row, 'pointerover', 'mouse');
        expect(bar.hasAttribute('data-lit')).toBe(true);
        const fold = page.querySelector<HTMLElement>('[data-action="dim-cal-trailfold"]') as HTMLElement;
        await act(async() => {
            fireEvent.click(fold);
        });
        await act(async() => {
            fireEvent.click(fold);
        });
        expect([row.hasAttribute('data-lit'), bar.hasAttribute('data-lit')]).toEqual([false, false]);
    });

    it('says when no trail runs in the months held', async() => {
        const page = await mount(offered(trails([])));
        expect(page.querySelector('[data-region="dim-cal-trailsempty"]')?.textContent).toBe('No trail runs in these months.');
    });

    it('asks for the next month from the toolbar\'s arrow and appends it, the trail and the history kept', async() => {
        const page = await mount(offered(trails([trail()]), {trail: 7}));
        const before = [window.location.href, window.history.length];
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        await waitFor(() => expect(trailRequests()).toHaveLength(1));
        expect(trailRequests()[0]).toEqual({month: '2026-12', trail: 7, mine: false, categoryid: 0, recursive: true, months: 1});
        await waitFor(() => expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(2));
        expect(page.querySelectorAll<HTMLElement>('.dim-cal__trailbar')[1].style.gridColumn).toBe('12 / 42');
        // Moving is a scroll, never a request of the page nor a new entry: the address is written when a scroll settles.
        expect([window.location.href, window.history.length]).toEqual(before);
    });

    it('goes back a month by prepending it, and to today without a request once its month is held', async() => {
        const page = await mount(offered(trails([trail()], '2027-01-31', '2026-12-01')));
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-prev"]') as HTMLElement);
        });
        await waitFor(() => expect(trailRequests()).toHaveLength(1));
        expect(trailRequests()[0]).toMatchObject({month: '2026-11', months: 1});
        await waitFor(() => expect(Array.from(page.querySelectorAll('[data-action="dim-cal-trailmonth"]'))
            .map((button) => button.textContent)).toEqual(['November 2026', 'December 2026', 'January 2027']));
        await act(async() => {
            fireEvent.click(page.querySelector('.dim-cal__today') as HTMLElement);
        });
        expect(trailRequests()).toHaveLength(1);
    });

    it('asks for today\'s month and the next when today is far from what is held', async() => {
        const page = await mount(offered(trails([trail()], '2027-04-30', '2027-03-01')));
        await act(async() => {
            fireEvent.click(page.querySelector('.dim-cal__today') as HTMLElement);
        });
        await waitFor(() => expect(trailRequests()).toHaveLength(1));
        expect(trailRequests()[0]).toMatchObject({month: '2026-11', months: 2});
        await waitFor(() => expect(Array.from(page.querySelectorAll('[data-action="dim-cal-trailmonth"]'))
            .map((button) => button.textContent)).toEqual(['November 2026', 'December 2026']));
    });
});

describe('the zoom and the courses\' dates (T3c)', () => {
    /**
     * The zoom's buttons, as their names and whether each is pressed.
     *
     * @param {object} page The page.
     * @returns {Array}
     */
    const zooms = (page: HTMLElement): string[] => Array.from(page.querySelectorAll<HTMLElement>('[data-action="dim-cal-trailzoom"]'))
        .map((button) => `${button.textContent}:${button.getAttribute('aria-pressed')}`);

    /**
     * Press one of the zoom's buttons.
     *
     * @param {object} page The page.
     * @param {string} zoom The zoom.
     * @returns {Promise}
     */
    const press = async(page: HTMLElement, zoom: string): Promise<void> => {
        await act(async() => {
            fireEvent.click(page.querySelector(`[data-action="dim-cal-trailzoom"][data-zoom="${zoom}"]`) as HTMLElement);
        });
    };

    it('offers the three zooms as real buttons, writes the one pressed to the address, and asks nothing', async() => {
        const page = await mount(offered(trails([trail()])));
        const group = page.querySelector<HTMLElement>('[data-region="dim-cal-trailzoom"]') as HTMLElement;
        expect([group.getAttribute('role'), group.getAttribute('aria-label')]).toEqual(['group', 'Timeline scale']);
        expect(group.closest('[data-region="dim-cal-trailfilters"]')).not.toBeNull();
        expect(Array.from(group.querySelectorAll('button')).every((button) => button.type === 'button')).toBe(true);
        expect(zooms(page)).toEqual(['Days:true', 'Weeks:false', 'Months:false']);
        const section = page.querySelector<HTMLElement>('[data-region="dim-cal-trails"]') as HTMLElement;
        expect(section.className).toBe('dim-cal__trails');
        const entries = window.history.length;

        await press(page, 'week');
        expect(zooms(page)).toEqual(['Days:false', 'Weeks:true', 'Months:false']);
        expect(section.classList.contains('dim-cal__trails--week')).toBe(true);
        expect(new URL(window.location.href).searchParams.get('zoom')).toBe('week');
        await press(page, 'month');
        expect(section.classList.contains('dim-cal__trails--month')).toBe(true);
        expect(new URL(window.location.href).searchParams.get('zoom')).toBe('month');
        await press(page, 'day');
        expect(section.className).toBe('dim-cal__trails');
        expect(new URL(window.location.href).searchParams.has('zoom')).toBe(false);
        // Navigation: replaced, never an entry, and the months held are not asked again.
        expect([window.history.length, trailRequests()]).toEqual([entries, []]);
    });

    it('opens on the zoom the page names, and keeps it through a filter\'s request', async() => {
        const page = await mount(offered(trails([trail()]), {zoom: 'week'}));
        expect(zooms(page)).toEqual(['Days:false', 'Weeks:true', 'Months:false']);
        await act(async() => {
            fireEvent.change(page.querySelector('[data-action="dim-cal-trail"]') as HTMLSelectElement, {target: {value: '7'}});
        });
        await waitFor(() => expect(trailRequests()).toHaveLength(1));
        const url = new URL(window.location.href);
        expect([url.searchParams.get('trail'), url.searchParams.get('zoom')]).toEqual(['7', 'week']);
        expect(trailRequests()[0]).not.toHaveProperty('zoom');
    });

    it('reads a zoom the page does not know as days', async() => {
        const page = await mount(offered(trails([trail()]), {zoom: 'year'}));
        expect(zooms(page)).toEqual(['Days:true', 'Weeks:false', 'Months:false']);
    });

    it('draws the days by days, the weeks by weeks and the weeks\' lines by months, weekends shaded by days only', async() => {
        const page = await mount(offered(trails([trail()])));
        expect(page.querySelectorAll('.dim-cal__trailday')).toHaveLength(30);
        expect(page.querySelector('.dim-cal__trailday--today')).not.toBeNull();
        expect(page.querySelectorAll('.dim-cal__trailweekend').length).toBeGreaterThan(0);
        expect(page.querySelector('[data-region="dim-cal-trailweek"]')).toBeNull();

        await press(page, 'week');
        expect(page.querySelectorAll('.dim-cal__trailday')).toHaveLength(0);
        expect(page.querySelectorAll('.dim-cal__trailweekend')).toHaveLength(0);
        const weeks = Array.from(page.querySelectorAll<HTMLElement>('[data-region="dim-cal-trailweek"]'));
        // From the viewer's first weekday, Sunday here: November 2026 opens on one.
        expect(weeks.map((week) => week.textContent)).toEqual(['Nov1–7', 'Nov8–14', 'Nov15–21', 'Nov22–28', '']);
        expect(weeks.map((week) => week.style.gridColumn)).toEqual(['2 / 9', '9 / 16', '16 / 23', '23 / 30', '30 / 32']);
        // The week of two days at the end is narrower than its label: no text, its place and line kept.
        // The week holding today, the 17th, is tinted; no day's pill.
        expect(weeks.map((week) => week.classList.contains('dim-cal__trailweek--today'))).toEqual([false, false, true, false, false]);
        expect(page.querySelector('.dim-cal__trailday--today')).toBeNull();
        expect(Array.from(page.querySelectorAll<HTMLElement>('.dim-cal__trailweekline'))
            .map((line) => line.style.getPropertyValue('--dim-cal-trail-at'))).toEqual(['7', '14', '21', '28']);
        expect(Array.from(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).map((button) => button.textContent))
            .toEqual(['November 2026']);

        await press(page, 'month');
        expect(page.querySelectorAll('.dim-cal__trailweekend')).toHaveLength(0);
        const lines = Array.from(page.querySelectorAll<HTMLElement>('[data-region="dim-cal-trailweek"]'));
        expect([lines.length, lines.map((line) => line.textContent).join('')]).toEqual([5, '']);
        expect(lines.some((line) => line.classList.contains('dim-cal__trailweek--today'))).toBe(false);
        // The band alone names the months.
        expect(Array.from(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).map((button) => button.textContent))
            .toEqual(['November']);
    });

    it('starts the weeks on the viewer\'s first weekday', async() => {
        const page = await mount({...offered(trails([trail()])), firstday: 1});
        await press(page, 'week');
        expect(Array.from(page.querySelectorAll<HTMLElement>('[data-region="dim-cal-trailweek"]'))
            .map((week) => week.dataset.week).slice(0, 3)).toEqual(['2026-11-01', '2026-11-02', '2026-11-09']);
    });

    it('tops today\'s line with a Today tag at every zoom', async() => {
        const page = await mount(offered(trails([trail()])));
        for (const zoom of ['day', 'week', 'month']) {
            await press(page, zoom);
            const tag = page.querySelector<HTMLElement>('[data-region="dim-cal-trailnowtag"]') as HTMLElement;
            expect([tag.textContent, tag.style.getPropertyValue('--dim-cal-trail-at')]).toEqual(['Today', '16']);
            expect(tag.closest('[aria-hidden="true"]')).not.toBeNull();
        }
    });

    it('draws no name on a bar too narrow for it, its accessible name unchanged', async() => {
        const page = await mount(offered(trails([trail()])));
        const named = (): Array<string | null> => Array.from(page.querySelectorAll<HTMLElement>('.dim-cal__trailbar'))
            .map((bar) => bar.textContent);
        const labels = Array.from(page.querySelectorAll<HTMLElement>('.dim-cal__trailbar')).map((bar) => bar.getAttribute('aria-label'));
        expect(named()).toEqual(['Fundamentos', 'Prática']);
        await press(page, 'week');
        expect(named()).toEqual(['Fundamentos', 'Prática']);
        // Fundamentos's nine days by months are 2.25rem: no name; Prática's twenty, 5rem, keep it.
        await press(page, 'month');
        expect(named()).toEqual(['', 'Prática']);
        expect(Array.from(page.querySelectorAll<HTMLElement>('.dim-cal__trailbar')).map((bar) => bar.getAttribute('aria-label')))
            .toEqual(labels);
        expect(String(labels[0])).toContain('Fundamentos');
    });

    it('says each course\'s dates under its name, and names its row with them', async() => {
        const dated = trail();
        dated.steps = [
            ...dated.steps,
            {...dated.steps[1], cmid: 74, position: 3, name: 'Início', start: at('2026-12-01', 12), end: 0},
            {...dated.steps[1], cmid: 75, position: 4, name: 'Prazo', start: 0, end: at('2026-11-20', 12)},
            {...dated.steps[1], cmid: 76, position: 5, name: 'Sem data', start: 0, end: 0},
        ];
        const page = await mount(offered(trails([dated])));
        const lines = Array.from(page.querySelectorAll<HTMLElement>('[data-region="dim-cal-traildates"]'));
        expect(lines.map((line) => String(line.textContent).replace(/\s/g, ' '))).toEqual([
            'Nov 2–10, 2026',
            'Nov 11 – Dec 10, 2026',
            'From Dec 1, 2026',
            'Until Nov 20, 2026',
            'No dates',
        ]);
        expect(lines.every((line) => line.closest('[data-region="dim-cal-trailrow"]') !== null)).toBe(true);
        expect(lines[4].classList.contains('dim-cal__traildates--none')).toBe(true);
        const rows = Array.from(page.querySelectorAll<HTMLElement>('[data-region="dim-cal-trailrow"]'));
        expect(rows.map((row) => String(row.getAttribute('aria-label')).replace(/\s/g, ' '))).toEqual([
            'Fundamentos, Nov 2–10, 2026: show on the timeline',
            'Prática, Nov 11 – Dec 10, 2026: show on the timeline',
            'Início, From Dec 1, 2026: show on the timeline',
            'Prazo, Until Nov 20, 2026: show on the timeline',
            'null',
        ]);
        // The course with no dates is named by what it draws: its name and "No dates".
        expect(rows[4].textContent).toContain('Sem dataNo dates');
    });

    it('says the dates in the viewer\'s language', async() => {
        document.documentElement.lang = 'pt-BR';
        const page = await mount(offered(trails([trail()])));
        expect(Array.from(page.querySelectorAll<HTMLElement>('[data-region="dim-cal-traildates"]'))
            .map((line) => String(line.textContent).replace(/\s/g, ' '))).toEqual(['2–10 nov. 2026', '11 nov. – 10 dez. 2026']);
    });
});

describe('the filters', () => {
    it('keep one trail, then only mine, then clear', async() => {
        const page = await mount(offered(trails([trail()])));
        const select = page.querySelector<HTMLSelectElement>('[data-action="dim-cal-trail"]') as HTMLSelectElement;
        expect(Array.from(select.options).map((option) => option.textContent)).toEqual(['All trails', 'Outra', 'Trilha de Gestão']);
        expect(page.querySelector('[data-action="dim-cal-clear"]')).toBeNull();

        await act(async() => {
            fireEvent.change(select, {target: {value: '8'}});
        });
        await waitFor(() => expect(trailRequests()).toHaveLength(1));
        expect(trailRequests()[0].trail).toBe(8);
        expect(new URL(window.location.href).searchParams.get('trail')).toBe('8');

        const mine = page.querySelector('[data-action="dim-cal-trailmine"]') as HTMLElement;
        expect([mine.tagName, mine.getAttribute('aria-pressed')]).toEqual(['BUTTON', 'false']);
        await act(async() => {
            fireEvent.click(mine);
        });
        await waitFor(() => expect(trailRequests()).toHaveLength(2));
        expect(page.querySelector('[data-action="dim-cal-trailmine"]')?.getAttribute('aria-pressed')).toBe('true');
        expect(trailRequests()[1]).toEqual({month: '2026-11', trail: 8, mine: true, categoryid: 0, recursive: true, months: 2});
        expect(new URL(window.location.href).searchParams.get('mine')).toBe('1');

        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-clear"]') as HTMLElement);
        });
        await waitFor(() => expect(trailRequests()).toHaveLength(3));
        expect(trailRequests()[2]).toEqual({month: '2026-11', trail: 0, mine: false, categoryid: 0, recursive: true, months: 2});
        const url = new URL(window.location.href);
        expect([url.searchParams.has('trail'), url.searchParams.has('mine')]).toEqual([false, false]);
    });
});

describe('the step\'s popover', () => {
    it('says the step\'s place, its dates and their source, and leads to the course and the trail', async() => {
        const page = await mount(offered(trails([trail()])));
        const bar = page.querySelectorAll<HTMLElement>('.dim-cal__trailbar')[1];
        await act(async() => {
            fireEvent.click(bar);
        });
        const pop = page.querySelector<HTMLElement>('[data-region="dim-cal-trailpopover"]') as HTMLElement;
        expect(pop).not.toBeNull();
        expect(pop.getAttribute('role')).toBe('dialog');
        expect(pop.querySelector('h2')?.textContent).toBe('Prática');
        expect(pop.querySelector('[data-region="dim-cal-trailposition"]')?.textContent).toBe('Course 2 of 2 · Trilha de Gestão');
        expect(pop.textContent).toContain('The course\'s dates');
        expect(pop.textContent).toContain('Prática supervisionada');
        expect(pop.querySelector('.dim-cal__trailcategory')?.textContent).toBe('Gestão');
        const links = Array.from(pop.querySelectorAll<HTMLAnchorElement>('a')).map((link) => [link.textContent, link.href]);
        expect(links).toEqual([
            ['View trail', 'https://example.com/r.php/theme_dimensions/landing/7'],
            ['Open course', 'https://example.com/course/view.php?id=73'],
        ]);
        expect(pop.querySelector('[data-region="dim-cal-trailstate"]')).toBeNull();

        // Drawn beside the scroller, never inside it: its overflow cannot cut the popover.
        expect(page.querySelector('[data-region="dim-cal-trailscroll"]')?.contains(pop)).toBe(false);

        fireEvent.keyDown(pop, {key: 'Escape'});
        expect(page.querySelector('[data-region="dim-cal-trailpopover"]')).toBeNull();
        expect(document.activeElement).toBe(bar);
    });

    it('opens on a course\'s row when it has no date', async() => {
        const undated = trail();
        undated.steps[0].start = 0;
        undated.steps[0].end = 0;
        const page = await mount(offered(trails([undated])));
        const row = page.querySelector<HTMLElement>('[data-region="dim-cal-trailrow"][data-cmid="70"]') as HTMLElement;
        await act(async() => {
            fireEvent.click(row);
        });
        expect(page.querySelector('[data-region="dim-cal-trailpopover"] h2')?.textContent).toBe('Fundamentos');
        expect(row.getAttribute('aria-expanded')).toBe('true');
        fireEvent.keyDown(page.querySelector('[data-region="dim-cal-trailpopover"]') as HTMLElement, {key: 'Escape'});
        expect(document.activeElement).toBe(row);
    });

    it('carries a locked step\'s state and core\'s reason', async() => {
        const learner = trail({enrolled: true});
        learner.steps[1].state = 'locked';
        learner.steps[1].reason = 'Not available unless: Fundamentos is complete';
        const page = await mount(offered(trails([learner])));
        await act(async() => {
            fireEvent.click(page.querySelectorAll<HTMLElement>('.dim-cal__trailbar')[1]);
        });
        const pop = page.querySelector<HTMLElement>('[data-region="dim-cal-trailpopover"]') as HTMLElement;
        expect(pop.querySelector('[data-region="dim-cal-trailstate"]')?.textContent).toBe('Locked');
        expect(pop.querySelector('[data-region="dim-cal-trailreason"]')?.textContent)
            .toBe('Not available unless: Fundamentos is complete');
    });

    /**
     * Open the second step's popover.
     *
     * @param {object} page The page.
     * @returns {Promise} The popover and the bar that opened it.
     */
    const popSecond = async(page: HTMLElement): Promise<{pop: HTMLElement, bar: HTMLElement}> => {
        const bar = page.querySelectorAll<HTMLElement>('.dim-cal__trailbar')[1];
        await act(async() => {
            fireEvent.click(bar);
        });
        return {pop: page.querySelector<HTMLElement>('[data-region="dim-cal-trailpopover"]') as HTMLElement, bar: bar};
    };

    it('draws the course\'s image as its cover, the band under it, and the close disc on it', async() => {
        const pictured = trail();
        pictured.steps[1].image = 'https://example.com/pluginfile.php/5/course/overviewfiles/capa.png';
        const page = await mount(offered(trails([pictured])));
        const {pop, bar} = await popSecond(page);
        const cover = pop.querySelector<HTMLElement>('[data-region="dim-cal-trailcover"]') as HTMLElement;
        expect(pop.firstElementChild).toBe(cover);
        const image = cover.querySelector('img') as HTMLImageElement;
        expect(image.getAttribute('src')).toBe('https://example.com/pluginfile.php/5/course/overviewfiles/capa.png');
        expect(image.getAttribute('alt')).toBe('');
        expect(cover.style.getPropertyValue('--dim-cal-cover-bg')).toBe('#ffffff');
        expect(pop.classList.contains('dim-cal__trailpop--nocover')).toBe(false);
        expect(pop.querySelector('[role="progressbar"]')).toBeNull();
        // The meta line, the plugin's slot and the category over the title.
        expect(pop.querySelector('[data-region="dim-cal-trailwhen"]')?.classList.contains('dim-cal__trailmeta')).toBe(true);
        expect(pop.querySelector('[data-region="dim-cal-trailposition"]')?.classList.contains('dim-cal__trailextra')).toBe(true);
        expect(pop.querySelector('.dim-cal__trailpopnames')?.textContent).toBe('GestãoPrática');

        // A failed image leaves the band.
        fireEvent.error(image);
        expect(cover.querySelector('img')).toBeNull();
        expect(page.querySelector('[data-region="dim-cal-trailcover"]')).toBe(cover);

        // The disc is the cover's, named, and closes the popover with the focus back on the bar.
        const close = cover.querySelector<HTMLButtonElement>('.dim-cal__coverclose') as HTMLButtonElement;
        expect(close.getAttribute('aria-label')).toBe('Close');
        expect(close.querySelector('.btn-close')?.getAttribute('aria-hidden')).toBe('true');
        expect(pop.querySelectorAll('[data-action="dim-cal-pop-close"]')).toHaveLength(1);
        await act(async() => {
            fireEvent.click(close);
        });
        expect(page.querySelector('[data-region="dim-cal-trailpopover"]')).toBeNull();
        expect(document.activeElement).toBe(bar);
    });

    it('takes the server\'s SVG cover and refuses any other data address', async() => {
        const cover = 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=';
        const pictured = trail();
        pictured.steps[0].image = cover;
        pictured.steps[1].image = 'data:text/html;base64,PHN2Zz48L3N2Zz4=';
        const page = await mount(offered(trails([pictured])));
        const {pop} = await popSecond(page);
        expect(pop.querySelector('img')).toBeNull();
        fireEvent.keyDown(pop, {key: 'Escape'});
        await act(async() => {
            fireEvent.click(page.querySelectorAll<HTMLElement>('.dim-cal__trailbar')[0]);
        });
        expect(page.querySelector('[data-region="dim-cal-trailpopover"] img')?.getAttribute('src')).toBe(cover);
    });

    it('marks a completed step with the green seal and base, and its main action reads Review', async() => {
        const learner = trail({enrolled: true});
        learner.steps[0].state = 'current';
        learner.steps[1].state = 'done';
        const page = await mount(offered(trails([learner])));
        const {pop} = await popSecond(page);
        const pill = pop.querySelector('[data-region="dim-cal-trailstate"]') as HTMLElement;
        expect(pill.textContent).toBe('Completed');
        expect(pill.classList.contains('dim-cal__trailseal')).toBe(true);
        expect(pill.querySelector('.fa-check')?.getAttribute('aria-hidden')).toBe('true');
        expect(pop.querySelector('[data-region="dim-cal-trailcover"]')?.classList.contains('dim-cal__trailcover--done')).toBe(true);
        expect(pop.classList.contains('dim-cal__trailpop--done')).toBe(true);
        const actions = Array.from(pop.querySelectorAll<HTMLAnchorElement>('.dim-cal__trailpopactions a'));
        expect(actions.map((link) => [link.className, link.textContent])).toEqual([
            ['btn btn-secondary', 'View trail'],
            ['btn btn-primary', 'Review'],
        ]);

        // The control: a step in progress keeps its pill and "Open course".
        fireEvent.keyDown(pop, {key: 'Escape'});
        await act(async() => {
            fireEvent.click(page.querySelectorAll<HTMLElement>('.dim-cal__trailbar')[0]);
        });
        const current = page.querySelector<HTMLElement>('[data-region="dim-cal-trailpopover"]') as HTMLElement;
        expect(current.querySelector('.dim-cal__trailseal')).toBeNull();
        expect(current.querySelector('.dim-cal__trailcover--done')).toBeNull();
        expect(current.querySelector('.dim-cal__trailpopactions .btn-primary')?.textContent).toBe('Open course');
    });

    it('draws no cover while Boost Union\'s card view is off, the category and the icon button in a row', async() => {
        const pictured = trail({enrolled: true});
        pictured.steps[1].image = 'https://example.com/pluginfile.php/5/course/overviewfiles/capa.png';
        pictured.steps[1].state = 'done';
        const page = await mount(offered(trails([pictured]), {covers: false}));
        const {pop, bar} = await popSecond(page);
        expect(pop.querySelector('[data-region="dim-cal-trailcover"]')).toBeNull();
        expect(pop.querySelector('img')).toBeNull();
        expect(pop.querySelector('.dim-cal__coverclose')).toBeNull();
        expect(pop.classList.contains('dim-cal__trailpop--nocover')).toBe(true);
        expect(pop.style.getPropertyValue('--dim-cal-cover-bg')).toBe('#ffffff');
        const top = pop.querySelector('.dim-cal__traildialogtop') as HTMLElement;
        expect(top).toBe(pop.firstElementChild);
        expect(top.querySelector('.dim-cal__trailcategory')?.textContent).toBe('Gestão');
        const close = top.querySelector<HTMLButtonElement>('button.btn.btn-icon.dim-cal__close') as HTMLButtonElement;
        expect(close.getAttribute('aria-label')).toBe('Close');
        expect(pop.querySelectorAll('.dim-cal__trailcategory')).toHaveLength(1);
        // The seal stays without the cover; the keyboard is unchanged: Tab past the last control closes.
        expect(pop.querySelector('.dim-cal__trailseal')).not.toBeNull();
        const last = pop.querySelector<HTMLElement>('.dim-cal__trailpopactions .btn-primary') as HTMLElement;
        fireEvent.keyDown(last, {key: 'Tab'});
        expect(page.querySelector('[data-region="dim-cal-trailpopover"]')).toBeNull();
        expect(document.activeElement).toBe(bar);
    });
});

describe('the days\' scroller', () => {
    /** The scroll positions jsdom does not keep, per element. */
    const lefts = new WeakMap<Element, number>();
    /** The scroller's own width. */
    let viewport = 1000;

    beforeEach(() => {
        // A 1000px scroller at 16px a rem: the 340px trail column and 48px a column of the grid it holds.
        Object.defineProperty(HTMLElement.prototype, 'scrollLeft', {
            configurable: true,
            get() {
                return lefts.get(this) || 0;
            },
            set(value: number) {
                lefts.set(this, value);
            },
        });
        Object.defineProperty(HTMLElement.prototype, 'scrollWidth', {
            configurable: true,
            get() {
                if (this.dataset.region !== 'dim-cal-trailscroll') {
                    return 0;
                }
                const grid = this.querySelector('[data-region="dim-cal-trailgrid"]') as HTMLElement;
                const section = this.closest('[data-region="dim-cal-trails"]');
                // A day is 48px by days, 16px by weeks and 4px by months (trails.ts TRACK at 16px a rem).
                let pitch = section?.classList.contains('dim-cal__trails--week') ? 16 : 48;
                if (section?.classList.contains('dim-cal__trails--month')) {
                    pitch = 4;
                }
                return Math.max(viewport, 340 + Number(grid.style.getPropertyValue('--dim-cal-trail-days')) * pitch);
            },
        });
        Object.defineProperty(HTMLElement.prototype, 'clientWidth', {
            configurable: true,
            get() {
                return this.dataset.region === 'dim-cal-trailscroll' ? viewport : 0;
            },
        });
        HTMLElement.prototype.scrollTo = jest.fn(function(this: HTMLElement, options?: ScrollToOptions | number) {
            // A browser keeps the position between the ends.
            const left = typeof options === 'object' ? Number(options.left) : 0;
            this.scrollLeft = Math.max(0, Math.min(left, this.scrollWidth - this.clientWidth));
        }) as unknown as typeof HTMLElement.prototype.scrollTo;
    });

    afterEach(() => {
        viewport = 1000;
        ['scrollLeft', 'scrollWidth', 'clientWidth', 'scrollTo'].forEach((name) => {
            delete (HTMLElement.prototype as unknown as Record<string, unknown>)[name];
        });
        delete (window as unknown as Record<string, unknown>).matchMedia;
    });

    /**
     * The days' scroller of a page.
     *
     * @param {object} page The page.
     * @returns {object}
     */
    const scrollerOf = (page: HTMLElement): HTMLElement =>
        page.querySelector<HTMLElement>('[data-region="dim-cal-trailscroll"]') as HTMLElement;

    /**
     * The toolbar's title, Intl's thin spaces read as plain ones.
     *
     * @param {object} page The page.
     * @returns {string}
     */
    const title = (page: HTMLElement): string => String(page.querySelector('[data-region="dim-cal-caption"]')?.textContent)
        .replace(/\s/g, ' ');

    it('opens on today, paints the map, pages by the arrows and asks for the next month near the end', async() => {
        let deliver: (answer: TrailAnswer) => void = () => undefined;
        fetched.mockImplementation(({methodname, args}: {methodname: string, args: Args}) => (methodname === TRAILS
            ? new Promise((resolve) => {
                deliver = () => resolve(answerFor(args));
            })
            : Promise.resolve({})));
        const page = await mount(offered(trails([trail()])));
        const scroller = scrollerOf(page);
        const thumb = page.querySelector<HTMLElement>('[data-region="dim-cal-trailthumb"]') as HTMLElement;
        const map = page.querySelector<HTMLElement>('[data-region="dim-cal-trailmap"]') as HTMLElement;
        // November's 30 days: 1780px of content, 660px of days in view. Today, the 17th, is past the days in view at the
        // 1st: the least scroll that shows it with a day to spare.
        expect(scroller.scrollLeft).toBe(204);
        expect([thumb.style.left, thumb.style.width]).toEqual(['14.167%', '45.833%']);
        expect(map.getAttribute('aria-hidden')).toBe('true');
        expect(page.querySelector<HTMLElement>('.dim-cal__trailmaptoday')?.style.left).toBe('55%');
        expect(title(page)).toBe('November 2026');
        const back = page.querySelector<HTMLElement>('[data-action="dim-cal-trail-back"]') as HTMLElement;
        const ahead = page.querySelector<HTMLElement>('[data-action="dim-cal-trail-ahead"]') as HTMLElement;
        expect([back.getAttribute('aria-label'), back.getAttribute('aria-disabled')]).toEqual(['Earlier days', null]);
        expect([ahead.getAttribute('aria-label'), ahead.getAttribute('aria-disabled')]).toEqual(['Later days', null]);
        expect(trailRequests()).toEqual([]);

        // A page is the days in view less one, landing on a day's start; there the end of what is held is near.
        await act(async() => {
            fireEvent.click(ahead);
            fireEvent.scroll(scroller);
        });
        expect(HTMLElement.prototype.scrollTo).toHaveBeenLastCalledWith({left: 768, behavior: 'smooth'});
        // The thumb over the days held and the skeleton of December, on its way: 33 columns.
        expect(thumb.style.left).toBe('48.485%');
        expect(trailRequests()).toEqual([{month: '2026-12', trail: 0, mine: false, categoryid: 0, recursive: true, months: 1}]);
        // While December is on its way, a quiet skeleton closes the band, and nothing else is asked.
        expect(page.querySelector('[data-region="dim-cal-trailloading"]')).not.toBeNull();
        await act(async() => {
            fireEvent.scroll(scroller);
        });
        expect(trailRequests()).toHaveLength(1);
        await act(async() => {
            deliver(trails([]));
        });
        await waitFor(() => expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(2));
        expect(page.querySelector('[data-region="dim-cal-trailloading"]')).toBeNull();
        // Appended at the end: the view did not move.
        expect(scroller.scrollLeft).toBe(768);

        // A press on the map's line puts the thumb's middle under the pointer, kept on the line.
        jest.spyOn(map, 'getBoundingClientRect').mockReturnValue({
            left: 0, top: 0, right: 1000, bottom: 24, width: 1000, height: 24, x: 0, y: 0, toJSON: () => ({}),
        });
        await act(async() => {
            fireEvent.pointerDown(map, {clientX: 100, pointerType: 'mouse', button: 0, pointerId: 1});
            fireEvent.pointerUp(map, {pointerId: 1});
        });
        expect(scroller.scrollLeft).toBe(0);
    });

    it('keeps the view still when a month is prepended, then moves to its 1st', async() => {
        const page = await mount(offered(trails([trail()], '2027-01-31', '2026-12-01'), {today: '2027-03-05'}));
        const scroller = scrollerOf(page);
        expect(scroller.scrollLeft).toBe(0);
        (HTMLElement.prototype.scrollTo as jest.Mock).mockClear();
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-prev"]') as HTMLElement);
        });
        await waitFor(() => expect(Array.from(page.querySelectorAll('[data-action="dim-cal-trailmonth"]'))
            .map((button) => button.textContent)).toEqual(['November 2026', 'December 2026', 'January 2027']));
        // November's 30 days moved December's 1st by 1440px: the position followed it, then the arrow animated back.
        expect((HTMLElement.prototype.scrollTo as jest.Mock).mock.calls.map(([options]) => options)).toEqual([
            {left: 1440, behavior: 'auto'},
            {left: 0, behavior: 'smooth'},
        ]);
    });

    it('scrolls a course\'s row to its bar, then opens the bar\'s popover once the scroll has ended', async() => {
        const page = await mount(offered(trails([trail()], '2026-12-31')));
        const scroller = scrollerOf(page);
        expect(scroller.scrollLeft).toBe(204);
        const row = page.querySelector<HTMLElement>('[data-region="dim-cal-trailrow"][data-cmid="72"]') as HTMLElement;
        expect([row.tagName, row.getAttribute('aria-haspopup')]).toEqual(['BUTTON', 'dialog']);
        await act(async() => {
            fireEvent.click(row);
        });
        // Prática starts on the 11th: two days inside the days in view.
        expect(HTMLElement.prototype.scrollTo).toHaveBeenLastCalledWith({left: 384, behavior: 'smooth'});
        expect(page.querySelector('[data-region="dim-cal-trailpopover"]')).toBeNull();
        await act(async() => {
            fireEvent(scroller, new Event('scrollend'));
        });
        const pop = page.querySelector<HTMLElement>('[data-region="dim-cal-trailpopover"]') as HTMLElement;
        expect(pop.querySelector('h2')?.textContent).toBe('Prática');
        expect(row.getAttribute('aria-expanded')).toBe('true');
        expect(scroller.contains(pop)).toBe(false);
        fireEvent.keyDown(pop, {key: 'Escape'});
        expect(document.activeElement).toBe(page.querySelector('[data-region="dim-cal-trailstep"][data-cmid="72"]'));
    });

    it('opens no popover when the course\'s trail is folded before the scroll ends', async() => {
        const second = trail({id: 8, name: 'Outra', steps: [{...trail().steps[0], cmid: 80, courseid: 81}]});
        for (const control of ['dim-cal-trailfold', 'dim-cal-trailfoldall']) {
            window.history.replaceState({}, '', '/r.php/theme_dimensions/calendar');
            const page = await mount(offered(trails([trail(), second], '2026-12-31')));
            const fold = page.querySelector<HTMLElement>('[data-trail="7"] [data-action="dim-cal-trailfold"]') as HTMLElement;
            const all = page.querySelector<HTMLElement>('[data-action="dim-cal-trailfoldall"]') as HTMLElement;
            const toggle = control === 'dim-cal-trailfold' ? fold : all;
            // Two trails arrive folded: the reader opens them to press a course.
            await act(async() => {
                fireEvent.click(toggle);
            });
            expect(fold.getAttribute('aria-expanded')).toBe('true');
            const row = page.querySelector<HTMLElement>('[data-region="dim-cal-trailrow"][data-cmid="72"]') as HTMLElement;
            await act(async() => {
                fireEvent.click(row);
            });
            await act(async() => {
                fireEvent.click(toggle);
            });
            expect(fold.getAttribute('aria-expanded')).toBe('false');
            await act(async() => {
                fireEvent(scrollerOf(page), new Event('scrollend'));
            });
            expect(page.querySelector('[data-region="dim-cal-trailpopover"]')).toBeNull();
            // The control: opened again, the same press opens the popover once its scroll ends.
            await act(async() => {
                fireEvent.click(toggle);
            });
            await act(async() => {
                fireEvent.click(row);
            });
            await act(async() => {
                fireEvent(scrollerOf(page), new Event('scrollend'));
            });
            expect(page.querySelector('[data-region="dim-cal-trailpopover"] h2')?.textContent).toBe('Prática');
            document.body.replaceChildren();
        }
    });

    it('moves at once and opens at once for a reader who asked for less motion', async() => {
        const motion = jest.fn((query: string) => ({matches: query === '(prefers-reduced-motion: reduce)'}));
        window.matchMedia = motion as unknown as typeof window.matchMedia;
        const page = await mount(offered(trails([trail()], '2026-12-31')));
        await act(async() => {
            fireEvent.click(page.querySelector('[data-region="dim-cal-trailrow"][data-cmid="72"]') as HTMLElement);
        });
        expect(HTMLElement.prototype.scrollTo).toHaveBeenLastCalledWith({left: 384, behavior: 'auto'});
        await waitFor(() => expect(page.querySelector('[data-region="dim-cal-trailpopover"] h2')?.textContent).toBe('Prática'));
    });

    it('goes to a month from the band, names the months in view and writes the month a scroll settles on', async() => {
        const page = await mount(offered(trails([trail()], '2026-12-31')));
        const scroller = scrollerOf(page);
        const before = window.history.length;
        const december = page.querySelectorAll<HTMLElement>('[data-action="dim-cal-trailmonth"]')[1];
        expect(december.tagName).toBe('BUTTON');
        expect(page.querySelector('[data-action="dim-cal-trailmonth"][aria-current="true"]')?.textContent).toBe('November 2026');
        await act(async() => {
            fireEvent.click(december);
        });
        expect(HTMLElement.prototype.scrollTo).toHaveBeenLastCalledWith({left: 1440, behavior: 'smooth'});

        scroller.scrollLeft = 1000;
        await act(async() => {
            fireEvent.scroll(scroller);
        });
        expect(title(page)).toBe('November – December 2026');
        scroller.scrollLeft = 1440;
        await act(async() => {
            fireEvent.scroll(scroller);
            fireEvent(scroller, new Event('scrollend'));
        });
        expect(title(page)).toBe('December 2026');
        expect(page.querySelector('[data-action="dim-cal-trailmonth"][aria-current="true"]')?.textContent).toBe('December 2026');
        const url = new URL(window.location.href);
        expect([url.searchParams.get('view'), url.searchParams.get('month'), window.history.length])
            .toEqual(['trail', '2026-12', before]);
        expect(trailRequests()).toEqual([]);
    });

    /** Calls of the trails service held until the test lets them go, in order. */
    const held: Array<{args: Args, resolve: (answer: TrailAnswer) => void, reject: (error: Error) => void}> = [];

    /**
     * Hold every call of the trails service until the test answers it.
     *
     * @returns {void}
     */
    const hold = (): void => {
        held.length = 0;
        fetched.mockImplementation(({methodname, args}: {methodname: string, args: Args}) => (methodname === TRAILS
            ? new Promise((resolve, reject) => {
                held.push({args: args, resolve: resolve, reject: reject});
            })
            : Promise.resolve({})));
    };

    /**
     * Scroll the days to a position, as the reader would.
     *
     * @param {object} scroller The scroller.
     * @param {number} left The position.
     * @returns {Promise} Settles once the scroll is painted.
     */
    const scrollTo = async(scroller: HTMLElement, left: number): Promise<void> => {
        scroller.scrollLeft = left;
        await act(async() => {
            fireEvent.scroll(scroller);
        });
    };

    /**
     * The popover's heading, or null.
     *
     * @param {object} page The page.
     * @returns {string|null}
     */
    const popped = (page: HTMLElement): string | null =>
        page.querySelector('[data-region="dim-cal-trailpopover"] h2')?.textContent ?? null;

    it('opens no popover for a course whose scroll another action superseded', async() => {
        const page = await mount(offered(trails([trail()], '2026-12-31')));
        await act(async() => {
            fireEvent.click(page.querySelector('[data-region="dim-cal-trailrow"][data-cmid="72"]') as HTMLElement);
        });
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        expect(HTMLElement.prototype.scrollTo).toHaveBeenLastCalledWith({left: 1440, behavior: 'smooth'});
        expect(popped(page)).toBeNull();
        await act(async() => {
            fireEvent(scrollerOf(page), new Event('scrollend'));
        });
        expect(popped(page)).toBeNull();
    });

    it('opens no popover for a course when the reader moved on before its scroll ended', async() => {
        hold();
        const page = await mount(offered(trails([trail()])));
        await act(async() => {
            fireEvent.click(page.querySelector('[data-region="dim-cal-trailrow"][data-cmid="72"]') as HTMLElement);
        });
        // December is asked for, not held yet: nothing has superseded the course's scroll.
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        expect(held.map((call) => call.args.month)).toEqual(['2026-12']);
        await act(async() => {
            fireEvent(scrollerOf(page), new Event('scrollend'));
        });
        expect(popped(page)).toBeNull();
    });

    it('settles a superseded target as not reached, and the last one once the scroll ends', async() => {
        const control = createRef<ScrollControl | null>() as {current: ScrollControl | null};
        render(
            <TrailScroll
                days={30}
                todayat={-1}
                openat={-1}
                opening="0"
                shift={{key: 0, days: 0}}
                zoom="day"
                control={control}
                onView={() => undefined}
                onSettle={() => undefined}
                strings={STRINGS as unknown as Strings}
            >
                <div data-region="dim-cal-trailgrid" style={{'--dim-cal-trail-days': '30'} as CSSProperties}></div>
            </TrailScroll>
        );
        const scroller = document.querySelector<HTMLElement>('[data-region="dim-cal-trailscroll"]') as HTMLElement;
        const first = (control.current as ScrollControl).go({kind: 'day', index: 10});
        const second = (control.current as ScrollControl).go({kind: 'day', index: 5});
        await expect(first).resolves.toBe(false);
        fireEvent(scroller, new Event('scrollend'));
        await expect(second).resolves.toBe(true);
    });

    it('keeps the popover of a course without dates opened after another course\'s scroll', async() => {
        const undated = trail();
        undated.steps[0].start = 0;
        undated.steps[0].end = 0;
        const page = await mount(offered(trails([undated], '2026-12-31')));
        await act(async() => {
            fireEvent.click(page.querySelector('[data-region="dim-cal-trailrow"][data-cmid="72"]') as HTMLElement);
        });
        await act(async() => {
            fireEvent.click(page.querySelector('[data-region="dim-cal-trailrow"][data-cmid="70"]') as HTMLElement);
        });
        expect(popped(page)).toBe('Fundamentos');
        await act(async() => {
            fireEvent(scrollerOf(page), new Event('scrollend'));
        });
        expect(popped(page)).toBe('Fundamentos');
    });

    it('goes on to a target asked for while a month was on its way, when that month comes back stale', async() => {
        hold();
        const page = await mount(offered(trails([trail()]), {today: '2027-03-05'}));
        const scroller = scrollerOf(page);
        await scrollTo(scroller, 780);
        expect(held.map((call) => call.args.month)).toEqual(['2026-12']);
        // Today is far from what is held: a new set is wanted while December is still on its way.
        await act(async() => {
            fireEvent.click(page.querySelector('.dim-cal__today') as HTMLElement);
        });
        await act(async() => {
            fireEvent.change(page.querySelector('[data-action="dim-cal-trail"]') as HTMLElement, {target: {value: '8'}});
        });
        expect(held.map((call) => [call.args.month, call.args.months])).toEqual([['2026-12', 1], ['2026-11', 2]]);
        await act(async() => {
            held[1].resolve(trails([trail()]));
        });
        await act(async() => {
            held[0].resolve(trails([], '2026-12-31', '2026-12-01'));
        });
        await waitFor(() => expect(held.map((call) => call.args.month)).toContain('2027-03'));
    });

    it('names the page\'s month in the toolbar once no timeline is drawn', async() => {
        fetched.mockImplementation(({methodname, args}: {methodname: string, args: Args & {trail: number}}) => {
            if (methodname !== TRAILS) {
                return Promise.resolve({});
            }
            return Promise.resolve(args.trail === 8 ? trails([], lastOf(args.month, args.months)) : answerFor(args));
        });
        const page = await mount(offered(trails([trail()], '2026-12-31')));
        await scrollTo(scrollerOf(page), 1000);
        expect(title(page)).toBe('November – December 2026');
        await act(async() => {
            fireEvent.change(page.querySelector('[data-action="dim-cal-trail"]') as HTMLElement, {target: {value: '8'}});
        });
        await waitFor(() => expect(page.querySelector('[data-region="dim-cal-trailsempty"]')).not.toBeNull());
        expect(title(page)).toBe('November 2026');
    });

    it('does not ask again for a month whose request failed while the reader only scrolls', async() => {
        hold();
        const page = await mount(offered(trails([trail()])));
        const scroller = scrollerOf(page);
        await scrollTo(scroller, 780);
        expect(held).toHaveLength(1);
        await act(async() => {
            held[0].reject(new Error('offline'));
        });
        await scrollTo(scroller, 700);
        await scrollTo(scroller, 780);
        expect(held).toHaveLength(1);
        // The reader's next action may ask for it again.
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        expect(held.map((call) => call.args.month)).toEqual(['2026-12', '2026-12']);
    });

    it('aims a target on its way again when a month is prepended under it', async() => {
        hold();
        const page = await mount(offered(trails([trail()], '2027-01-31', '2026-12-01'), {today: '2027-03-05'}));
        const scroller = scrollerOf(page);
        expect(scroller.scrollLeft).toBe(0);
        await scrollTo(scroller, 100);
        await scrollTo(scroller, 0);
        expect(held.map((call) => call.args.month)).toEqual(['2026-11']);
        await act(async() => {
            fireEvent.click(page.querySelectorAll<HTMLElement>('[data-action="dim-cal-trailmonth"]')[1]);
        });
        expect(HTMLElement.prototype.scrollTo).toHaveBeenLastCalledWith({left: 1488, behavior: 'smooth'});
        await act(async() => {
            held[0].resolve(trails([], '2026-11-30', '2026-11-01'));
        });
        await waitFor(() => expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(3));
        // November's 30 days came before: January's 1st is now day 61, and the animation goes on to it.
        expect(HTMLElement.prototype.scrollTo).toHaveBeenLastCalledWith({left: 61 * 48, behavior: 'smooth'});
    });

    it('waits for a long animation while it still scrolls, past the fallback\'s time', async() => {
        const page = await mount(offered(trails([trail()], '2026-12-31')));
        const scroller = scrollerOf(page);
        await act(async() => {
            fireEvent.click(page.querySelector('[data-region="dim-cal-trailrow"][data-cmid="72"]') as HTMLElement);
        });
        for (let tick = 0; tick < 9; tick++) {
            await act(async() => {
                await new Promise((resolve) => {
                    setTimeout(resolve, 100);
                });
                fireEvent.scroll(scroller);
            });
        }
        expect(popped(page)).toBeNull();
        await waitFor(() => expect(popped(page)).toBe('Prática'));
    });

    it('writes no month while the page\'s own request is on its way', async() => {
        hold();
        const page = await mount(offered(trails([trail()], '2026-12-31')));
        const scroller = scrollerOf(page);
        await act(async() => {
            fireEvent.change(page.querySelector('[data-action="dim-cal-trail"]') as HTMLElement, {target: {value: '8'}});
        });
        expect(held).toHaveLength(1);
        await scrollTo(scroller, 1440);
        await act(async() => {
            fireEvent(scroller, new Event('scrollend'));
        });
        expect(new URL(window.location.href).searchParams.get('month')).toBe('2026-11');
        await act(async() => {
            held[0].resolve(trails([], '2026-12-31'));
        });
    });

    it('opens on the month asked for, not on today in the month after it', async() => {
        const page = await mount(offered(trails([trail()], '2026-11-30', '2026-10-01')));
        expect(scrollerOf(page).scrollLeft).toBe(0);
    });

    it('drops a month asked for with filters the reader has since changed', async() => {
        hold();
        const page = await mount(offered(trails([trail()])));
        await scrollTo(scrollerOf(page), 780);
        expect(held).toHaveLength(1);
        await act(async() => {
            fireEvent.change(page.querySelector('[data-action="dim-cal-trail"]') as HTMLElement, {target: {value: '8'}});
        });
        await act(async() => {
            held[0].resolve(trails([trail()], '2026-12-31', '2026-12-01'));
        });
        expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(1);
        await act(async() => {
            held[1].resolve(trails([], '2026-12-31'));
        });
    });

    it('opens at the 1st when today is in view there', async() => {
        const page = await mount(offered(trails([trail()]), {today: '2026-11-05'}));
        expect(scrollerOf(page).scrollLeft).toBe(0);
        expect(page.querySelector('[data-action="dim-cal-trail-back"]')?.getAttribute('aria-disabled')).toBe('true');
        expect(page.querySelector('.dim-cal__trailfade--back')).toBeNull();
    });

    it('keeps the day at the start of the view across a zoom, by arithmetic', async() => {
        const page = await mount(offered(trails([trail()], '2026-12-31')));
        const scroller = scrollerOf(page);
        // Today, the 17th, in view: the 5th at the start, 204px in at 48px a day.
        expect(scroller.scrollLeft).toBe(204);
        const thumb = page.querySelector<HTMLElement>('[data-region="dim-cal-trailthumb"]') as HTMLElement;
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-trailzoom"][data-zoom="week"]') as HTMLElement);
        });
        // The same 4.25 days in at 16px a day, at once: no animation.
        expect(HTMLElement.prototype.scrollTo).toHaveBeenLastCalledWith({left: 68, behavior: 'auto'});
        expect(scroller.scrollLeft).toBe(68);
        // The thumb is painted where that left the days: 61 days of 16px seen through 660px.
        expect(thumb.style.width).toBe(`${(660 / (61 * 16) * 100).toFixed(3)}%`);
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-trailzoom"][data-zoom="month"]') as HTMLElement);
        });
        expect(HTMLElement.prototype.scrollTo).toHaveBeenLastCalledWith({left: 17, behavior: 'auto'});
        // By months the 61 days fit: nothing scrolls, so the browser keeps the first day at the start, and the next months
        // are asked for, three at a time, until the days fill the scroller (five months are 944px: eight, 1312px).
        expect(scroller.scrollLeft).toBe(0);
        await waitFor(() => expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(8));
        expect(trailRequests().map((args) => args.months)).toEqual([3, 3]);
        expect(page.querySelector('[data-action="dim-cal-trail-ahead"]')).not.toBeNull();
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-trailzoom"][data-zoom="day"]') as HTMLElement);
        });
        // The first day held was at the start: it stays there.
        expect(HTMLElement.prototype.scrollTo).toHaveBeenLastCalledWith({left: 0, behavior: 'auto'});
        expect(new URL(window.location.href).searchParams.has('zoom')).toBe(false);
    });

    it('opens a new set of months at the new zoom without rescaling it, when both come in one commit', async() => {
        // Back or Forward across a zoom: a new opening and a new zoom in the same render.
        const control = createRef<ScrollControl | null>() as {current: ScrollControl | null};
        const scroller = (zoom: 'day' | 'week', opening: string) => (
            <section
                data-region="dim-cal-trails"
                className={zoom === 'week' ? 'dim-cal__trails dim-cal__trails--week' : 'dim-cal__trails'}
            >
                <TrailScroll
                    days={61}
                    todayat={16}
                    openat={16}
                    opening={opening}
                    shift={{key: 0, days: 0}}
                    zoom={zoom}
                    control={control}
                    onView={() => undefined}
                    onSettle={() => undefined}
                    strings={STRINGS as unknown as Strings}
                >
                    <div data-region="dim-cal-trailgrid" style={{'--dim-cal-trail-days': '61'} as CSSProperties}></div>
                </TrailScroll>
            </section>
        );
        const {rerender} = render(scroller('week', '0'));
        const box = document.querySelector<HTMLElement>('[data-region="dim-cal-trailscroll"]') as HTMLElement;
        // By weeks the 17th shows from the 1st.
        expect(box.scrollLeft).toBe(0);
        await act(async() => {
            rerender(scroller('day', '1'));
        });
        // By days the opening shows the 17th with a day to spare: 204px, not that position rescaled by three.
        expect(box.scrollLeft).toBe(204);
        expect(HTMLElement.prototype.scrollTo).toHaveBeenLastCalledWith({left: 204, behavior: 'auto'});
    });

    it('keeps asking for the next three months while the days held fit a wide scroller, up to the month zoom\'s cap', async() => {
        viewport = 2400;
        const page = await mount(offered(trails([trail()], '2026-12-31'), {zoom: 'month'}));
        // By months 61 days are 244px: each batch that comes is measured again, and the next is asked for, past six months
        // (1064px) up to the twelve the month zoom holds, which still fit 2400px (1800px): 2, 5, 8, 11 and 12 months held,
        // the last batch only the one month the cap leaves room for.
        await waitFor(() => expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(12));
        expect(trailRequests().map((args) => [args.month, args.months])).toEqual([
            ['2027-01', 3], ['2027-04', 3], ['2027-07', 3], ['2027-10', 1],
        ]);
        // At the cap nothing more is asked, or the first month, in view, would go.
        await act(async() => {
            fireEvent.scroll(scrollerOf(page));
        });
        expect(trailRequests()).toHaveLength(4);
        expect(page.querySelector('[data-action="dim-cal-trailmonth"]')?.getAttribute('data-month')).toBe('2026-11');
    });

    it('holds six months by weeks, however wide the scroller', async() => {
        viewport = 6000;
        const page = await mount(offered(trails([trail()], '2026-12-31'), {zoom: 'week'}));
        await waitFor(() => expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(6));
        await act(async() => {
            fireEvent.scroll(scrollerOf(page));
        });
        expect(trailRequests()).toHaveLength(4);
        expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(6);
        // A month a request by weeks, whatever the scroller's width.
        expect(trailRequests().map((args) => args.months)).toEqual([1, 1, 1, 1]);
    });

    it('prepends a batch of three months by months, the view kept still by the days of all three', async() => {
        viewport = 500;
        const page = await mount(offered(trails([trail()], '2027-01-31', '2026-12-01'), {today: '2027-03-05', zoom: 'month'}));
        expect(trailRequests()).toEqual([]);
        (HTMLElement.prototype.scrollTo as jest.Mock).mockClear();
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-prev"]') as HTMLElement);
        });
        await waitFor(() => expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(5));
        // September to November before December: the three months start at the month the batch ends before, one request.
        expect(trailRequests().map((args) => [args.month, args.months])).toEqual([['2026-09', 3]]);
        expect(Array.from(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).map((button) => button.textContent))
            .toEqual(['September', 'October', 'November', 'December', 'January']);
        // 30 + 31 + 30 days at 4px moved December's 1st by 364px: the position followed it before the arrow animated.
        expect((HTMLElement.prototype.scrollTo as jest.Mock).mock.calls[0][0]).toEqual({left: 364, behavior: 'auto'});
    });

    it('asks in batches from the next load on once the zoom is switched to months', async() => {
        viewport = 2400;
        const page = await mount(offered(trails([trail()], '2026-12-31')));
        expect(trailRequests()).toEqual([]);
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-trailzoom"][data-zoom="month"]') as HTMLElement);
        });
        await waitFor(() => expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(12));
        expect(trailRequests().map((args) => args.months)).toEqual([3, 3, 3, 1]);
    });

    it('keeps the start day in view when the zoom goes from months to days with twelve held', async() => {
        viewport = 2400;
        const page = await mount(offered(trails([trail()], '2026-12-31'), {zoom: 'month'}));
        await waitFor(() => expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(12));
        const scroller = scrollerOf(page);
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-trailzoom"][data-zoom="day"]') as HTMLElement);
        });
        // The first day held was at the start: it stays there, and the months held wait for the next load to drop.
        expect(scroller.scrollLeft).toBe(0);
        expect(page.querySelector('[data-action="dim-cal-trailmonth"]')?.getAttribute('data-month')).toBe('2026-11');
    });

    /**
     * Ten trails of ten courses each, the steps twelve days apart from the 2nd of November 2026 and ten days long: a hundred
     * subcourse steps over four months, every trail meeting the first of them.
     *
     * @returns {Array} The trails.
     */
    const tenOfTen = (): Trail[] => Array.from({length: 10}, (_, i) => trail({
        id: 100 + i,
        name: `Trilha ${i}`,
        first: at('2026-11-02', 12),
        last: at('2027-03-02', 12),
        steps: Array.from({length: 10}, (__, j) => {
            const start = new Date(Date.UTC(2026, 10, 2 + j * 12)).toISOString().slice(0, 10);
            const end = new Date(Date.UTC(2026, 10, 12 + j * 12)).toISOString().slice(0, 10);
            return {
                cmid: 1000 + i * 10 + j, position: j + 1, name: `Curso ${i}.${j}`, courseid: 5000 + i * 10 + j,
                coursename: `Curso ${i}.${j}`, categoryid: 3, start: at(start, 12), end: at(end, 12), expected: false,
                state: j % 4 === 0 ? 'complete' : '', reason: '', completed: j % 4 === 0 ? at(end, 12) : 0,
                url: `https://example.com/course/view.php?id=${5000 + i * 10 + j}`, image: '', next: j === 1,
            };
        }),
    }));

    /**
     * Answer the trails service with the ten trails, a task later, counting the calls on their way.
     *
     * @returns {object} The counts: now in flight, and the most there were at once.
     */
    const countFlight = (): {now: number, peak: number} => {
        const flight = {now: 0, peak: 0};
        fetched.mockImplementation(({methodname, args}: {methodname: string, args: Args}) => {
            if (methodname !== TRAILS) {
                return Promise.resolve({});
            }
            flight.now++;
            flight.peak = Math.max(flight.peak, flight.now);
            return new Promise((resolve) => {
                setTimeout(() => {
                    flight.now--;
                    const to = lastOf(args.month, args.months || 1);
                    resolve(trails(tenOfTen(), to, `${args.month}-01`));
                }, 0);
            });
        });
        return flight;
    };

    it('asks the same batches of ten trails of ten courses as of one, never two at once, up to the month zoom\'s cap', async() => {
        viewport = 2400;
        const flight = countFlight();
        const page = await mount(offered(trails(tenOfTen(), '2026-12-31'), {zoom: 'month'}));
        await waitFor(() => expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(12));
        expect(page.querySelectorAll('[data-region="dim-cal-trailrow"]').length).toBeGreaterThanOrEqual(100);
        expect(trailRequests().map((args) => [args.month, args.months])).toEqual([
            ['2027-01', 3], ['2027-04', 3], ['2027-07', 3], ['2027-10', 1],
        ]);
        expect([flight.peak, flight.now]).toEqual([1, 0]);
        // At the cap nothing more is asked, whatever the scroll.
        await act(async() => {
            fireEvent.scroll(scrollerOf(page));
            await new Promise((resolve) => setTimeout(resolve, 20));
        });
        expect(trailRequests()).toHaveLength(4);
        expect([flight.peak, flight.now]).toEqual([1, 0]);
    });

    it('asks nothing of ten trails of ten courses on a first paint by days until the reader scrolls, then one request', async() => {
        const flight = countFlight();
        const page = await mount(offered(trails(tenOfTen())));
        await act(async() => {
            await new Promise((resolve) => setTimeout(resolve, 20));
        });
        expect([trailRequests(), flight.peak]).toEqual([[], 0]);
        await scrollTo(scrollerOf(page), 780);
        await waitFor(() => expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(2));
        expect(trailRequests().map((args) => [args.month, args.months])).toEqual([['2026-12', 1]]);
        expect([flight.peak, flight.now]).toEqual([1, 0]);
    });

    it('drops the stale answer of ten trails when a filter changed under it, and waits on the new request alone', async() => {
        hold();
        const page = await mount(offered(trails(tenOfTen())));
        await scrollTo(scrollerOf(page), 780);
        expect(held).toHaveLength(1);
        await act(async() => {
            fireEvent.change(page.querySelector('[data-action="dim-cal-trail"]') as HTMLElement, {target: {value: '8'}});
        });
        // The call of the old filters cannot be called back: it is on its way beside the new one, whose filter it is not.
        expect(held.map((call) => call.args.trail)).toEqual([0, 8]);
        await act(async() => {
            held[0].resolve(trails(tenOfTen(), '2026-12-31', '2026-12-01'));
        });
        expect(page.querySelectorAll('[data-action="dim-cal-trailmonth"]')).toHaveLength(1);
        // The new request is still the only one the view waits on: scrolling asks nothing more.
        await scrollTo(scrollerOf(page), 790);
        expect(held).toHaveLength(2);
        await act(async() => {
            held[1].resolve(trails([], '2026-12-31'));
        });
        // The new filter's answer is what is drawn: none of the stale answer's hundred rows is.
        expect(page.querySelectorAll('[data-region="dim-cal-trailrow"]')).toHaveLength(0);
        expect(held).toHaveLength(2);
    });

    it('draws no label in a first week of one day or two, and its line stays', async() => {
        const labels = async(firstday: number): Promise<string[]> => {
            const page = await mount({...offered(trails([trail()]), {zoom: 'week'}), firstday: firstday});
            return Array.from(page.querySelectorAll<HTMLElement>('[data-region="dim-cal-trailweek"]'))
                .slice(0, 3).map((week) => week.textContent as string);
        };
        // November 2026 opens on a Sunday: from Monday its first week is the 1st alone, from Tuesday the 1st and 2nd.
        expect(await labels(1)).toEqual(['', 'Nov2–8', 'Nov9–15']);
        document.body.innerHTML = '';
        expect(await labels(2)).toEqual(['', 'Nov3–9', 'Nov10–16']);
        document.body.innerHTML = '';
        // From Sunday the first week is whole.
        expect(await labels(0)).toEqual(['Nov1–7', 'Nov8–14', 'Nov15–21']);
    });

    it('names the end\'s month in a week that crosses one', async() => {
        const page = await mount(offered(trails([trail()], '2026-12-31'), {zoom: 'week'}));
        const weeks = Array.from(page.querySelectorAll<HTMLElement>('[data-region="dim-cal-trailweek"]'));
        expect(weeks.map((week) => week.textContent).slice(3, 6)).toEqual(['Nov22–28', 'Nov29–5 Dec', 'Dec6–12']);
    });
});

describe('an open course (a start and no end)', () => {
    /**
     * A trail whose one course starts on a day and never ends, so the trail's own period is open.
     *
     * @param {string} from The course's first day.
     * @param {object} fields What differs in the course.
     * @param {number} last The trail's last end, 0 while open.
     * @returns {object}
     */
    const opened = (from: string, fields: Partial<Trail['steps'][number]> = {}, last = 0): Trail => {
        const source = trail();
        return trail({
            first: at(from, 12),
            last: last,
            steps: [{...source.steps[1], cmid: 80, position: 1, name: 'Aberto', start: at(from, 12), end: 0, ...fields}],
        });
    };

    it('draws its first 30 days and an aria-hidden "no end date" chip after them, and no spill chip', async() => {
        const page = await mount(offered(trails([opened('2026-11-01')], '2026-12-31')));
        const bar = page.querySelector<HTMLElement>('.dim-cal__trailbar') as HTMLElement;
        expect(bar.style.gridColumn).toBe('2 / 32');
        expect(bar.classList.contains('dim-cal__trailbar--after')).toBe(false);
        // The bar's name says it in full, where the chip is short.
        expect(String(bar.getAttribute('aria-label'))).toContain('Aberto');
        expect(String(bar.getAttribute('aria-label'))).toContain('No end date set');
        const chips = Array.from(page.querySelectorAll<HTMLElement>('.dim-cal__trailnoend'));
        expect(chips.map((chip) => [chip.textContent, chip.getAttribute('aria-hidden'), chip.style.getPropertyValue('--dim-cal-chip-start')]))
            .toEqual([['→ no end date', 'true', '90.375rem']]);
        expect(page.querySelector('.dim-cal__trailspill')).toBeNull();
        expect(page.querySelector('[data-region="dim-cal-trailchip"]')).toBeNull();
        // The trail's line ends with the course's 30 days: no chip and nothing after it.
        const span = page.querySelector<HTMLElement>('.dim-cal__trailspan') as HTMLElement;
        expect(span.style.gridColumn).toBe('2 / 32');
        expect(page.querySelectorAll('.dim-cal__trailrow--trail .dim-cal__trailnoend')).toHaveLength(0);
        expect(page.querySelector('.dim-cal__traildiamond--trail')).toBeNull();
    });

    it('places the chip a fixed gap after the bar at every zoom, by the zoom\'s day', async() => {
        const placed = async(zoom: string) => {
            const page = await mount(offered(trails([opened('2026-11-01')], '2026-12-31'), {zoom: zoom}));
            const chips = Array.from(page.querySelectorAll<HTMLElement>('.dim-cal__trailnoend'));
            document.body.replaceChildren();
            return chips.map((chip) => chip.style.getPropertyValue('--dim-cal-chip-start'));
        };
        expect(await placed('')).toEqual(['90.375rem']);
        expect(await placed('week')).toEqual(['30.375rem']);
        expect(await placed('month')).toEqual(['7.875rem']);
    });

    it('draws no chip when the 30 days run past the months held, and the bar is cut there', async() => {
        const page = await mount(offered(trails([opened('2026-11-10')])));
        const bar = page.querySelector<HTMLElement>('.dim-cal__trailbar') as HTMLElement;
        expect(bar.style.gridColumn).toBe('11 / 32');
        expect(bar.classList.contains('dim-cal__trailbar--after')).toBe(true);
        expect(page.querySelectorAll('.dim-cal__trailnoend')).toHaveLength(0);
    });

    it('draws no chip for a course with an end, or with an expected completion date', async() => {
        const page = await mount(offered(trails([trail()], '2026-12-31')));
        expect(page.querySelectorAll('.dim-cal__trailnoend')).toHaveLength(0);
        document.body.replaceChildren();
        const expected = await mount(offered(trails(
            [opened('2026-11-01', {end: at('2026-11-25', 12), expected: true}, at('2026-11-25', 12))],
            '2026-12-31'
        )));
        expect(expected.querySelectorAll('.dim-cal__trailnoend')).toHaveLength(0);
        expect((expected.querySelector('.dim-cal__trailbar') as HTMLElement).style.gridColumn).toBe('2 / 27');
        expect(String(expected.querySelector('.dim-cal__trailbar')?.getAttribute('aria-label'))).not.toContain('No end date set');
    });

    it('says in full in the step\'s popover that it has no end date, and not for a step with an end', async() => {
        const page = await mount(offered(trails([opened('2026-11-01')], '2026-12-31')));
        await act(async() => {
            fireEvent.click(page.querySelector('.dim-cal__trailbar') as HTMLElement);
        });
        const when = (await waitFor(() => {
            const row = document.querySelector('[data-region="dim-cal-trailwhen"]');
            expect(row).not.toBeNull();
            return row as HTMLElement;
        })).textContent;
        expect(String(when)).toContain('From November 1, 2026 · No end date set');
        document.body.replaceChildren();
        const closed = await mount(offered(trails([trail()], '2026-12-31')));
        await act(async() => {
            fireEvent.click(closed.querySelector('.dim-cal__trailbar') as HTMLElement);
        });
        const other = await waitFor(() => {
            const row = document.querySelector('[data-region="dim-cal-trailwhen"]');
            expect(row).not.toBeNull();
            return row as HTMLElement;
        });
        expect(String(other.textContent)).not.toContain('No end date set');
    });
});

describe('the folds (T3d)', () => {
    /**
     * Another trail of one course, between two days.
     *
     * @param {number} id The trail.
     * @param {string} name Its name.
     * @param {string} start Its course's first day.
     * @param {string} end Its course's last day.
     * @returns {object}
     */
    const other = (id: number, name: string, start = '2026-11-03', end = '2026-11-20'): Trail => trail({
        id: id,
        name: name,
        url: `https://example.com/r.php/theme_dimensions/landing/${id}`,
        first: at(start, 12),
        last: at(end, 12),
        steps: [{
            ...trail().steps[0], cmid: id * 10, courseid: id * 10 + 1, name: `${name} 1`, coursename: `${name} 1`,
            start: at(start, 12), end: at(end, 12), expected: false,
        }],
    });

    /**
     * A trail's own fold.
     *
     * @param {object} page The page.
     * @param {number} id The trail.
     * @returns {object}
     */
    const foldOf = (page: HTMLElement, id: number): HTMLElement =>
        page.querySelector<HTMLElement>(`[data-trail="${id}"] [data-action="dim-cal-trailfold"]`) as HTMLElement;

    /**
     * Whether each trail's steps are shown, by the fold's state and the steps' own.
     *
     * @param {object} page The page.
     * @param {Array} ids The trails.
     * @returns {Array} [aria-expanded, steps shown] per trail.
     */
    const opened = (page: HTMLElement, ids: number[]): Array<[string | null, boolean]> => ids.map((id) => [
        foldOf(page, id).getAttribute('aria-expanded'),
        !(page.querySelector<HTMLElement>(`#dim-cal-trailsteps-${id}`) as HTMLElement).hidden,
    ]);

    /**
     * The toggle beside the heading.
     *
     * @param {object} page The page.
     * @returns {object|null}
     */
    const allOf = (page: HTMLElement): HTMLElement | null =>
        page.querySelector<HTMLElement>('[data-action="dim-cal-trailfoldall"]');

    /**
     * The services' answers, December's trails one more trail of its own.
     *
     * @param {object} call The call.
     * @returns {Promise}
     */
    const december = ({methodname, args}: {methodname: string, args: Args}): Promise<unknown> => {
        if (methodname === TRAILS) {
            return Promise.resolve(args.month === '2026-12'
                ? trails([other(9, 'Dezembro', '2026-12-02', '2026-12-20')], '2026-12-31', '2026-12-01')
                : answerFor(args));
        }
        return Promise.resolve(methodname === 'theme_dimensions_get_calendar_range' ? answer([], args.from, args.to) : {});
    };

    it('opens with every trail folded when the answer holds two or more, and one alone open', async() => {
        const page = await mount(offered(trails([trail(), other(8, 'Outra')])));
        expect(opened(page, [7, 8])).toEqual([['false', false], ['false', false]]);
        const all = allOf(page) as HTMLElement;
        expect([all.getAttribute('aria-expanded'), all.getAttribute('aria-label'), all.getAttribute('aria-controls')])
            .toEqual(['false', 'Expand all', 'dim-cal-trailsteps-7 dim-cal-trailsteps-8']);
        expect(all.className).toBe('btn btn-icon icons-collapse-expand dim-cal__trailfold dim-cal__trailfoldall collapsed');
        expect(all.querySelector('.expanded-icon')?.innerHTML).toBe(ICONS.expanded);
        expect(all.closest('.dim-cal__trailheading')).not.toBeNull();
        expect(all.closest('[aria-hidden="true"]')).toBeNull();
        document.body.replaceChildren();

        const alone = await mount(offered(trails([trail()])));
        expect(opened(alone, [7])).toEqual([['true', true]]);
        expect(allOf(alone)).toBeNull();
    });

    it('expands every trail when any is folded and folds every one when all are open, asking nothing', async() => {
        const page = await mount(offered(trails([trail(), other(8, 'Outra')])));
        const all = allOf(page) as HTMLElement;
        const before = [window.location.href, fetched.mock.calls.length];
        await act(async() => {
            fireEvent.click(all);
        });
        expect(opened(page, [7, 8])).toEqual([['true', true], ['true', true]]);
        expect([all.getAttribute('aria-expanded'), all.getAttribute('aria-label')]).toEqual(['true', 'Collapse all']);
        expect(all.classList.contains('collapsed')).toBe(false);
        await act(async() => {
            fireEvent.click(all);
        });
        expect(opened(page, [7, 8])).toEqual([['false', false], ['false', false]]);
        expect([all.getAttribute('aria-expanded'), all.getAttribute('aria-label')]).toEqual(['false', 'Expand all']);

        // One opened by its own fold: still one folded, so the toggle expands.
        await act(async() => {
            fireEvent.click(foldOf(page, 7));
        });
        expect([all.getAttribute('aria-expanded'), all.getAttribute('aria-label')]).toEqual(['false', 'Expand all']);
        await act(async() => {
            fireEvent.click(all);
        });
        expect(opened(page, [7, 8])).toEqual([['true', true], ['true', true]]);
        expect([window.location.href, fetched.mock.calls.length]).toEqual(before);
    });

    it('folds a trail that arrives with a later month and keeps the reader\'s own folds', async() => {
        fetched.mockImplementation(december);
        const page = await mount(offered(trails([trail(), other(8, 'Outra')])));
        await act(async() => {
            fireEvent.click(foldOf(page, 7));
        });
        expect(opened(page, [7, 8])).toEqual([['true', true], ['false', false]]);

        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        await waitFor(() => expect(page.querySelector('[data-trail="9"]')).not.toBeNull());
        expect(opened(page, [7, 8, 9])).toEqual([['true', true], ['false', false], ['false', false]]);
        expect(allOf(page)?.getAttribute('aria-controls')).toBe('dim-cal-trailsteps-7 dim-cal-trailsteps-8 dim-cal-trailsteps-9');
    });

    it('keeps a trail that arrived alone open when another joins it, which arrives folded', async() => {
        fetched.mockImplementation(december);
        const page = await mount(offered(trails([trail()])));
        expect(allOf(page)).toBeNull();
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        await waitFor(() => expect(page.querySelector('[data-trail="9"]')).not.toBeNull());
        expect(opened(page, [7, 9])).toEqual([['true', true], ['false', false]]);
        expect(allOf(page)?.getAttribute('aria-label')).toBe('Expand all');
    });

    it('opens a trail chosen alone in the picker', async() => {
        const page = await mount(offered(trails([trail(), other(8, 'Outra')])));
        expect(opened(page, [7, 8])).toEqual([['false', false], ['false', false]]);
        await act(async() => {
            fireEvent.change(page.querySelector('[data-action="dim-cal-trail"]') as HTMLElement, {target: {value: '7'}});
        });
        await waitFor(() => expect(page.querySelector('[data-trail="8"]')).toBeNull());
        expect(opened(page, [7])).toEqual([['true', true]]);
        expect(allOf(page)).toBeNull();
    });

    it('opens a trail chosen alone in the picker though the reader folded it', async() => {
        const page = await mount(offered(trails([trail(), other(8, 'Outra')])));
        // Opened, then folded again: the reader's own fold, no longer the one it arrived with.
        await act(async() => {
            fireEvent.click(foldOf(page, 7));
        });
        await act(async() => {
            fireEvent.click(foldOf(page, 7));
        });
        expect(opened(page, [7])).toEqual([['false', false]]);
        await act(async() => {
            fireEvent.change(page.querySelector('[data-action="dim-cal-trail"]') as HTMLElement, {target: {value: '7'}});
        });
        await waitFor(() => expect(page.querySelector('[data-trail="8"]')).toBeNull());
        expect(opened(page, [7])).toEqual([['true', true]]);
    });
});
