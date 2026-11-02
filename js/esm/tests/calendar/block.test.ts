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
 * Tests for the month calendar block's client (PROPOSAL.md sections 17.2 and 17.3).
 *
 * The block is drawn as calendar_block_month.mustache draws it, by a stand-in for core/templates that writes the
 * partial's hooks from the context it is given; the range is core/ajax's fetchOne, mocked.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import {redirect} from '@moodle/lms/core/location';
import {arm, context, pageHref, sameDay, target} from '../../src/calendar/block';
import type {Config, Labels, Weekday} from '../../src/calendar/block';
import type {Answer} from '../../src/calendar/model';
import {DEFAULT_STATE, KEYS} from '../../src/calendar/model';
import {STRINGS, answer} from './fixture';

jest.mock('@moodle/lms/core/ajax', () => ({fetchOne: jest.fn()}));
jest.mock('@moodle/lms/core/location', () => ({redirect: jest.fn()}));

const fetched = fetchOne as jest.Mock;
const redirected = redirect as jest.Mock;

const PAGE = 'https://example.com/r.php/theme_dimensions/calendar';

const LABELS = Object.fromEntries(['calendar_dayitems_many', 'calendar_dayitems_none', 'calendar_dayitems_one',
    'calendar_daylabel', 'calendar_daytoday'].map((key) => [key, STRINGS[key]])) as Labels;

const WEEKDAYS: Weekday[] = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((name) => ({"short": name, "long": name}));

/** What the stand-in for core/templates was asked to render, in order. */
let rendered: Array<Record<string, unknown>> = [];

/**
 * The partial's hooks, written from a context as the template writes them.
 *
 * @param {object} made The context.
 * @returns {string} The markup.
 */
const partial = (made: Record<string, unknown>): string => {
    const weeks = made.weeks as Array<{days: Array<{date: string, url: string, label: string}>}>;
    const head = (made.weekdays as Weekday[])
        .map((day) => `<th data-region="dim-calblock-weekday"><abbr title="${day.long}">${day.short}</abbr></th>`).join('');
    const rows = weeks.map((week) => `<tr>${week.days.map((day) => `<td><a data-region="dim-calblock-day" `
        + `data-date="${day.date}" href="${day.url}" aria-label="${day.label}">${Number(day.date.slice(8))}</a></td>`)
        .join('')}</tr>`).join('');
    return `<a data-action="dim-calblock-nav" data-month="${made.prevmonth}" href="${made.prevurl}">‹</a>`
        + `<span data-region="caption">${made.caption}</span>`
        + `<a data-action="dim-calblock-nav" data-month="${made.nextmonth}" href="${made.nexturl}">›</a>`
        + `<table><thead><tr>${head}</tr></thead><tbody>${rows}</tbody></table>`;
};

/**
 * The block as the server draws it, for November 2026 on a Sunday-first grid.
 *
 * @param {object} state The state the block drew.
 * @returns {HTMLElement} Its root, armed.
 */
const block = (state = DEFAULT_STATE): HTMLElement => {
    const config: Config = {month: '2026-11', today: '2026-11-03', firstday: 0, page: PAGE, state: state};
    const root = document.createElement('div');
    root.dataset.region = 'dim-calblock-month';
    root.dataset.month = config.month;
    root.dataset.today = config.today;
    root.dataset.firstday = '0';
    root.dataset.page = PAGE;
    root.dataset.state = JSON.stringify(state);
    const body = document.createElement('div');
    body.dataset.region = 'dim-calblock-body';
    body.innerHTML = partial(context(config, answer([], '2026-11-01', '2026-12-05'), WEEKDAYS, LABELS, 'en'));
    const status = document.createElement('div');
    status.dataset.region = 'dim-calblock-status';
    root.append(body, status);
    document.body.append(root);
    arm(root);
    return root;
};

/**
 * A day's link.
 *
 * @param {HTMLElement} root The block.
 * @param {string} date The day.
 * @returns {HTMLElement}
 */
const day = (root: HTMLElement, date: string): HTMLElement =>
    root.querySelector(`[data-region="dim-calblock-day"][data-date="${date}"]`) as HTMLElement;

/**
 * An arrow.
 *
 * @param {HTMLElement} root The block.
 * @param {number} index 0 for previous, 1 for next.
 * @returns {HTMLElement}
 */
const arrow = (root: HTMLElement, index: number): HTMLElement =>
    root.querySelectorAll<HTMLElement>('[data-action="dim-calblock-nav"]')[index];

/**
 * Wait for every queued promise.
 *
 * @returns {Promise}
 */
const settle = async(): Promise<void> => {
    for (let i = 0; i < 10; i++) {
        await Promise.resolve();
    }
};

beforeEach(() => {
    document.documentElement.lang = 'en';
    Object.entries(STRINGS).forEach(([key, value]) => mockString(key, 'theme_dimensions', value));
    rendered = [];
    fetched.mockReset();
    fetched.mockImplementation(({args}: {args: {from: string, to: string}}) => Promise.resolve(answer([], args.from, args.to)));
    redirected.mockReset();
    mockAmdModule('core/templates', {
        renderForPromise: jest.fn(async(name: string, made: Record<string, unknown>) => {
            rendered.push(made);
            return {html: partial(made), js: ''};
        }),
        replaceNodeContents: jest.fn((element: Element, html: string) => {
            element.innerHTML = html;
        }),
    });
});

afterEach(() => {
    document.body.replaceChildren();
});

describe('theme_dimensions/calendar/block', () => {
    it('builds the partial as the server does: dots by key, the neighbours outside, today, bare links', () => {
        const counts = (fields: Record<string, number>): Record<string, number> =>
            Object.fromEntries(KEYS.map((key) => [key, fields[key] ?? 0]));
        const given: Answer = {
            ...answer([], '2026-11-01', '2026-12-05'),
            days: [
                {date: '2026-11-03', counts: counts({site: 1, course: 2})},
                {date: '2026-11-04', counts: counts({site: 1, category: 1, course: 1, group: 1, open: 1})},
            ],
        };
        const made = context({month: '2026-11', today: '2026-11-03', firstday: 0, page: PAGE, state: DEFAULT_STATE},
            given, WEEKDAYS, LABELS, 'en');
        const days = (made.weeks as Array<{days: Array<Record<string, unknown>>}>).flatMap((week) => week.days);

        expect(days).toHaveLength(35);
        const third = days.find((one) => one.date === '2026-11-03') as Record<string, unknown>;
        expect(third.dots).toEqual([{key: 'site'}, {key: 'course'}]);
        expect(third.more).toBe(false);
        expect(third.today).toBe(true);
        expect(third.label).toBe('Tuesday, November 3, 2026, 3 items, today');
        expect(third.url).toBe(`${PAGE}?month=2026-11&day=2026-11-03`);
        const fourth = days.find((one) => one.date === '2026-11-04') as Record<string, unknown>;
        expect(fourth.dots).toEqual([{key: 'site'}, {key: 'category'}, {key: 'course'}, {key: 'group'}]);
        expect(fourth.more).toBe(true);
        expect(fourth.today).toBe(false);
        const padding = days.find((one) => one.date === '2026-12-01') as Record<string, unknown>;
        expect(padding.outside).toBe(true);
        expect(padding.url).toBe(`${PAGE}?month=2026-12&day=2026-12-01`);
        expect(made.prevurl).toBe(`${PAGE}?month=2026-10`);
        expect(made.nexturl).toBe(`${PAGE}?month=2026-12`);
        expect(made.monthurl).toBe(`${PAGE}?month=2026-11`);
        expect(made.caption).toBe('November 2026');
    });

    it('keeps the page address bare: the month and the day, whatever the page address carried', () => {
        expect(pageHref(`${PAGE}?keys[]=site`, '2026-11')).toBe(`${PAGE}?month=2026-11`);
        expect(pageHref(PAGE, '2026-11', '2026-11-09')).toBe(`${PAGE}?month=2026-11&day=2026-11-09`);
    });

    it('moves the month through the range with the state the block drew, and says the new month', async() => {
        const root = block({...DEFAULT_STATE, off: ['open'], categoryid: 4});
        arrow(root, 1).click();
        await settle();

        expect(fetched).toHaveBeenCalledTimes(1);
        const {methodname, args} = fetched.mock.calls[0][0];
        expect(methodname).toBe('theme_dimensions_get_calendar_range');
        expect(args).toMatchObject({from: '2026-11-29', to: '2027-01-02', categoryid: 4});
        expect(args.keys).not.toContain('open');
        expect(root.dataset.month).toBe('2026-12');
        expect(day(root, '2026-12-15')).not.toBeNull();
        expect(root.querySelector('[data-region="dim-calblock-status"]')?.textContent).toBe('December 2026');
        expect(document.activeElement).toBe(arrow(root, 1));
        expect(redirected).not.toHaveBeenCalled();
    });

    it('asks nothing with every key off', async() => {
        const root = block({...DEFAULT_STATE, off: [...KEYS]});
        arrow(root, 0).click();
        await settle();

        expect(fetched).not.toHaveBeenCalled();
        expect(root.dataset.month).toBe('2026-10');
    });

    it('leaves a modified click to the browser', () => {
        const root = block();
        let prevented: boolean | null = null;
        /**
         * Read whether the block took the click, then keep jsdom from navigating.
         *
         * @param {Event} event The click.
         * @returns {void}
         */
        const reader = (event: Event): void => {
            prevented = event.defaultPrevented;
            event.preventDefault();
        };
        document.addEventListener('click', reader);
        arrow(root, 1).dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true, ctrlKey: true}));
        document.removeEventListener('click', reader);

        expect(prevented).toBe(false);
        expect(fetched).not.toHaveBeenCalled();
    });

    it('paints the latest press alone', async() => {
        const answers: Array<(value: Answer) => void> = [];
        fetched.mockImplementation(() => new Promise((resolve) => answers.push(resolve)));
        const root = block();
        arrow(root, 1).click();
        arrow(root, 0).click();
        answers[1](answer([], '2026-09-27', '2026-11-07'));
        await settle();
        answers[0](answer([], '2026-11-29', '2027-01-02'));
        await settle();

        expect(rendered).toHaveLength(1);
        expect(root.dataset.month).toBe('2026-10');
    });

    it('follows the link when the range fails', async() => {
        fetched.mockImplementation(() => Promise.reject(new Error('refused')));
        const root = block();
        arrow(root, 1).click();
        await settle();

        expect(redirected).toHaveBeenCalledWith(`${PAGE}?month=2026-12`);
        expect(root.dataset.month).toBe('2026-11');
    });

    it('holds one Tab stop on today and moves it with the arrows, Home and End', () => {
        const root = block();
        const stops = Array.from(root.querySelectorAll('[data-region="dim-calblock-day"]'))
            .filter((link) => link.getAttribute('tabindex') === '0');
        expect(stops).toEqual([day(root, '2026-11-03')]);

        day(root, '2026-11-03').focus();
        day(root, '2026-11-03').dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowDown', bubbles: true}));
        expect(document.activeElement).toBe(day(root, '2026-11-10'));
        expect(day(root, '2026-11-10').getAttribute('tabindex')).toBe('0');
        expect(day(root, '2026-11-03').getAttribute('tabindex')).toBe('-1');
        day(root, '2026-11-10').dispatchEvent(new KeyboardEvent('keydown', {key: 'End', bubbles: true}));
        expect(document.activeElement).toBe(day(root, '2026-11-14'));
        expect(fetched).not.toHaveBeenCalled();
    });

    it('turns the month with PageDown and lands on the same day number', async() => {
        const root = block();
        day(root, '2026-11-03').focus();
        day(root, '2026-11-03').dispatchEvent(new KeyboardEvent('keydown', {key: 'PageDown', bubbles: true}));
        await settle();

        expect(root.dataset.month).toBe('2026-12');
        expect(document.activeElement).toBe(day(root, '2026-12-03'));
    });

    it('names the day a key moves to, and the same day in a shorter month', () => {
        expect(target('ArrowLeft', '2026-11-01', 0)).toBe('2026-10-31');
        expect(target('ArrowUp', '2026-11-03', 2)).toBe('2026-10-27');
        expect(target('Home', '2026-11-05', 4)).toBe('2026-11-01');
        expect(target('Enter', '2026-11-05', 4)).toBeNull();
        expect(sameDay('2026-01-31', '2026-02')).toBe('2026-02-28');
        expect(sameDay('2026-01-15', '2026-02')).toBe('2026-02-15');
    });
});
