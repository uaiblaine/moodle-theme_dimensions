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
 * request, the filters, the bars and the step's popover.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, fireEvent, render, waitFor} from '@testing-library/react';
import {fetchOne} from '@moodle/lms/core/ajax';
import Calendar from '../../src/calendar/Calendar';
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
            url: 'https://example.com/course/view.php?id=71', next: false,
        },
        {
            cmid: 72, position: 2, name: 'Prática', courseid: 73, coursename: 'Prática supervisionada', categoryid: 3,
            start: at('2026-11-11', 12), end: at('2026-12-10', 12), expected: false, state: '', reason: '', completed: 0,
            url: 'https://example.com/course/view.php?id=73', next: false,
        },
    ],
    ...fields,
});

/**
 * A trails answer for November 2026.
 *
 * @param {Array} trails The trails.
 * @returns {object}
 */
const trails = (trails: Trail[]): TrailAnswer => ({
    from: '2026-11-01',
    to: '2026-11-30',
    truncated: false,
    options: [{id: 7, name: 'Trilha de Gestão'}, {id: 8, name: 'Outra'}],
    trails: trails,
    categories: [{id: 3, name: 'Gestão', bg: '#ffffff', ink: '#000000'}],
});

beforeEach(() => {
    document.documentElement.lang = 'en';
    Object.entries(STRINGS).forEach(([key, value]) => mockString(key, 'theme_dimensions', value));
    fetched.mockReset();
    fetched.mockImplementation(({methodname, args}: {methodname: string, args: {from: string, to: string}}) => {
        if (methodname === TRAILS) {
            return Promise.resolve(trails([trail()]));
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
const offered = (shown: TrailAnswer | null, more: {trail?: number, mine?: boolean} = {}): Props => props([], {
    view: shown ? 'trail' : 'month',
    trails: {offered: true, answer: shown, trail: more.trail || 0, mine: more.mine || false},
});

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
        expect(trailRequests()).toEqual([{month: '2026-11', trail: 0, mine: false, categoryid: 0, recursive: true}]);
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
        expect(bars.map((bar) => bar.style.gridColumn)).toEqual(['3 / 12', '12 / 33']);
        expect(bars[0].classList.contains('dim-cal__trailbar--milestone')).toBe(true);
        expect(bars[1].classList.contains('dim-cal__trailbar--after')).toBe(true);
        expect(page.querySelector('.dim-cal__trailday--next')?.textContent).toBe('Dec →');
        expect(page.querySelector('.dim-cal__trailday--today')?.textContent).toBe('17');
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
        expect(page.querySelector('.dim-cal__trailprogress')?.textContent).toBe('1 of 2 done');
    });

    it('says when no trail runs this month', async() => {
        const page = await mount(offered(trails([])));
        expect(page.querySelector('[data-region="dim-cal-trailsempty"]')?.textContent).toBe('No trail runs this month.');
    });

    it('moves by the month, keeping the view and the trail', async() => {
        const page = await mount(offered(trails([trail()]), {trail: 7}));
        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        await waitFor(() => expect(trailRequests()).toHaveLength(1));
        expect(trailRequests()[0]).toEqual({month: '2026-12', trail: 7, mine: false, categoryid: 0, recursive: true});
        const url = new URL(window.location.href);
        expect([url.searchParams.get('view'), url.searchParams.get('month'), url.searchParams.get('trail')])
            .toEqual(['trail', '2026-12', '7']);
    });
});

describe('the filters', () => {
    it('keep one trail, then only mine, then clear', async() => {
        const page = await mount(offered(trails([trail()])));
        const select = page.querySelector<HTMLSelectElement>('[data-action="dim-cal-trail"]') as HTMLSelectElement;
        expect(Array.from(select.options).map((option) => option.textContent)).toEqual(['All trails', 'Trilha de Gestão', 'Outra']);
        expect(page.querySelector('[data-action="dim-cal-clear"]')).toBeNull();

        await act(async() => {
            fireEvent.change(select, {target: {value: '8'}});
        });
        await waitFor(() => expect(trailRequests()).toHaveLength(1));
        expect(trailRequests()[0].trail).toBe(8);
        expect(new URL(window.location.href).searchParams.get('trail')).toBe('8');

        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-trailmine"]') as HTMLElement);
        });
        await waitFor(() => expect(trailRequests()).toHaveLength(2));
        expect(trailRequests()[1]).toEqual({month: '2026-11', trail: 8, mine: true, categoryid: 0, recursive: true});
        expect(new URL(window.location.href).searchParams.get('mine')).toBe('1');

        await act(async() => {
            fireEvent.click(page.querySelector('[data-action="dim-cal-clear"]') as HTMLElement);
        });
        await waitFor(() => expect(trailRequests()).toHaveLength(3));
        expect(trailRequests()[2]).toEqual({month: '2026-11', trail: 0, mine: false, categoryid: 0, recursive: true});
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
            ['Open course', 'https://example.com/course/view.php?id=73'],
            ['View trail', 'https://example.com/r.php/theme_dimensions/landing/7'],
        ]);
        expect(pop.querySelector('[data-region="dim-cal-trailstate"]')).toBeNull();

        fireEvent.keyDown(pop, {key: 'Escape'});
        expect(page.querySelector('[data-region="dim-cal-trailpopover"]')).toBeNull();
        expect(document.activeElement).toBe(bar);
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
});
