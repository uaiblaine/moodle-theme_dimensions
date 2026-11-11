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
 * The month calendar block's client: the arrows move the month through the range service, the days are one Tab stop.
 *
 * The server draws the whole block (theme_dimensions/calendar_block_month, classes/output/calendar_block_month.php),
 * and every control is a link to the theme's calendar page, so the block works without this module. Armed, a press of
 * an arrow asks the range service for the next month's grid with the state the block drew (nothing is asked while
 * every key is off), builds the partial's context the way the server builds it ({@see context()}), and paints
 * theme_dimensions/calendar_block_month_body in place; the latest press wins, and a failure follows the link the arrow
 * already is. The keyboard is the page's grid contract in small: arrows by day and week, Home and End, PageUp and
 * PageDown by month, Enter follows the day's link.
 *
 * @module     theme_dimensions/calendar/block
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import Pending from '@moodle/lms/core/pending';
import {redirect} from '@moodle/lms/core/location';
import {getStrings} from '@moodle/lms/core/stringUtils';
import {load} from '../amd';
import {say, shift} from '../daterange/calendar';
import {gridWeeks, monthAfter} from './days';
import {dayDots} from './layout';
import {RANGE_METHOD} from './account';
import {DAY_PARAM, emptyAnswer, keysOn, normalise, rangeArgs} from './model';
import type {Answer, State} from './model';
import {fill} from './strings';

/** The partial the server drew and this module paints again. */
export const TEMPLATE = 'theme_dimensions/calendar_block_month_body';

/** The block's hooks, as the templates write them. */
export const SELECTORS = {
    root: '[data-region="dim-calblock-month"]',
    body: '[data-region="dim-calblock-body"]',
    status: '[data-region="dim-calblock-status"]',
    nav: '[data-action="dim-calblock-nav"]',
    day: '[data-region="dim-calblock-day"]',
    weekday: '[data-region="dim-calblock-weekday"]',
};

/** The class an armed root carries: one listener per block, however often the page asks. */
const MARK = 'dim-calblock-js';

/** The strings a day's label reads (local\calendar\blocks::STRINGS, which hands them over with the page). */
const STRING_KEYS = [
    'calendar_dayitems_many',
    'calendar_dayitems_none',
    'calendar_dayitems_one',
    'calendar_daylabel',
    'calendar_daytoday',
] as const;

/** The strings, by name. */
export type Labels = Record<typeof STRING_KEYS[number], string>;

/** A weekday's names, as the table's head prints them. */
export type Weekday = {short: string, long: string};

/** What a root carries: the month shown, today, the week's first day, the page's address, the state the block drew. */
export type Config = {month: string, today: string, firstday: number, page: string, state: State};

/** A long date, as the page's day label spells it. */
const LONG_DAY: Intl.DateTimeFormatOptions = {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'};

/**
 * The page's address of a month, and of a day of it: bare, so the page reads the saved state the block drew.
 *
 * @param {string} page The page's address (data-page).
 * @param {string} month The month, YYYY-MM.
 * @param {string} day The day, YYYY-MM-DD, or '' for the month alone.
 * @returns {string} The address.
 */
export const pageHref = (page: string, month: string, day = ''): string => {
    const url = new URL(page, window.location.href);
    url.search = '';
    url.searchParams.set('month', month);
    if (day !== '') {
        url.searchParams.set(DAY_PARAM, day);
    }
    return url.toString();
};

/**
 * A day's count as its label words it.
 *
 * @param {object} labels The strings.
 * @param {number} count The day's items.
 * @returns {string} The words.
 */
const countText = (labels: Labels, count: number): string => {
    if (count === 0) {
        return labels.calendar_dayitems_none;
    }
    return count === 1 ? labels.calendar_dayitems_one : fill(labels.calendar_dayitems_many, count);
};

/**
 * The partial's context for a month, as calendar_block_month::body() builds it.
 *
 * @param {object} config The block's root data, with the month to draw.
 * @param {object} answer The range's answer for the month's grid.
 * @param {Array} weekdays The table head's names, read back from the server's paint.
 * @param {object} labels The strings.
 * @param {string|undefined} language The language tag.
 * @returns {object} The context.
 */
export const context = (
    config: Config,
    answer: Answer,
    weekdays: Weekday[],
    labels: Labels,
    language: string | undefined
): Record<string, unknown> => {
    const counts = new Map(answer.days.map((day) => [day.date, day.counts]));
    const prev = monthAfter(config.month, -1);
    const next = monthAfter(config.month, 1);
    return {
        caption: say(`${config.month}-01`, {month: 'long', year: 'numeric'}, language),
        monthurl: pageHref(config.page, config.month),
        prevmonth: prev,
        prevurl: pageHref(config.page, prev),
        nextmonth: next,
        nexturl: pageHref(config.page, next),
        weekdays: weekdays,
        weeks: gridWeeks(config.month, config.firstday).map((week) => ({days: week.map((date) => {
            const dayCounts = counts.get(date);
            const count = Object.values(dayCounts ?? {}).reduce((sum, value) => sum + value, 0);
            const dots = dayDots(dayCounts);
            const today = date === config.today;
            const label = fill(labels.calendar_daylabel, {date: say(date, LONG_DAY, language), count: countText(labels, count)});
            return {
                date: date,
                number: Number(date.slice(8)),
                url: pageHref(config.page, date.slice(0, 7), date),
                label: today ? `${label}, ${labels.calendar_daytoday}` : label,
                outside: !date.startsWith(config.month),
                today: today,
                dots: dots.keys.map((key) => ({key: key})),
                more: dots.more,
            };
        })})),
    };
};

/**
 * A root's data, or null when it is not one the server drew whole.
 *
 * @param {HTMLElement} root The block's root.
 * @returns {object|null} The config.
 */
export const readConfig = (root: HTMLElement): Config | null => {
    const {month, today, firstday, page, state} = root.dataset;
    if (!month || !today || !page || !/^\d{4}-\d{2}$/.test(month)) {
        return null;
    }
    let parsed: State;
    try {
        parsed = normalise(JSON.parse(state || '{}'));
    } catch {
        return null;
    }
    return {month: month, today: today, firstday: Number(firstday) || 0, page: page, state: parsed};
};

/** The latest press per block: a superseded answer is dropped. */
const tickets = new WeakMap<HTMLElement, number>();

/**
 * The days of the month shown, in order, without the neighbours' days.
 *
 * @param {HTMLElement} root The block's root.
 * @returns {Array} The day links.
 */
const ownDays = (root: HTMLElement): HTMLAnchorElement[] => {
    const month = root.dataset.month || '';
    return Array.from(root.querySelectorAll<HTMLAnchorElement>(SELECTORS.day))
        .filter((day) => (day.dataset.date || '').startsWith(month));
};

/**
 * Make one day the grid's only Tab stop: the one asked for, else today, else the month's first.
 *
 * @param {HTMLElement} root The block's root.
 * @param {string} wanted The day to hold the stop, YYYY-MM-DD, or ''.
 * @returns {HTMLAnchorElement|null} The day holding it.
 */
export const rove = (root: HTMLElement, wanted = ''): HTMLAnchorElement | null => {
    const days = Array.from(root.querySelectorAll<HTMLAnchorElement>(SELECTORS.day));
    const own = ownDays(root);
    const stop = days.find((day) => wanted !== '' && day.dataset.date === wanted)
        ?? own.find((day) => day.dataset.date === root.dataset.today)
        ?? own[0]
        ?? null;
    days.forEach((day) => day.setAttribute('tabindex', day === stop ? '0' : '-1'));
    return stop;
};

/**
 * The day a key moves to from a day, or null for a key the grid does not take.
 *
 * @param {string} key The key's name.
 * @param {string} date The day, YYYY-MM-DD.
 * @param {number} column The day's column, 0 to 6.
 * @returns {string|null} The day, or null.
 */
export const target = (key: string, date: string, column: number): string | null => {
    switch (key) {
        case 'ArrowLeft':
            return shift(date, -1);
        case 'ArrowRight':
            return shift(date, 1);
        case 'ArrowUp':
            return shift(date, -7);
        case 'ArrowDown':
            return shift(date, 7);
        case 'Home':
            return shift(date, -column);
        case 'End':
            return shift(date, 6 - column);
        default:
            return null;
    }
};

/**
 * The same day number in another month, or that month's last day.
 *
 * @param {string} date The day, YYYY-MM-DD.
 * @param {string} month The other month, YYYY-MM.
 * @returns {string} The day in it.
 */
export const sameDay = (date: string, month: string): string => {
    const last = shift(`${monthAfter(month, 1)}-01`, -1);
    const wanted = `${month}-${date.slice(8)}`;
    return wanted > last ? last : wanted;
};

/**
 * Move a block to a month: ask the range, paint the partial, put the Tab stop and the focus back.
 *
 * @param {HTMLElement} root The block's root.
 * @param {string} month The month, YYYY-MM.
 * @param {string} href Where the press would have led without this module.
 * @param {Function} refocus Where the focus goes once painted, given the new body.
 * @returns {Promise} Resolves once painted, or once the press is superseded.
 */
export const go = async(
    root: HTMLElement,
    month: string,
    href: string,
    refocus: (root: HTMLElement) => void
): Promise<void> => {
    const config = readConfig(root);
    const body = root.querySelector<HTMLElement>(SELECTORS.body);
    if (!config || !body) {
        redirect(href);
        return;
    }
    const seq = (tickets.get(root) ?? 0) + 1;
    tickets.set(root, seq);
    const pending = new Pending('theme_dimensions/calendar:block');
    try {
        const days = gridWeeks(month, config.firstday).flat();
        const answer = keysOn(config.state).length
            ? await fetchOne<Answer>({methodname: RANGE_METHOD, args: rangeArgs(days[0], days[days.length - 1], config.state)})
            : emptyAnswer(days);
        if (seq !== tickets.get(root)) {
            return;
        }
        const values = await getStrings(STRING_KEYS.map((key) => ({key: key, component: 'theme_dimensions'})));
        const labels = Object.fromEntries(STRING_KEYS.map((key, index) => [key, values[index]])) as Labels;
        const weekdays = Array.from(root.querySelectorAll<HTMLElement>(SELECTORS.weekday)).map((cell) => ({
            "short": cell.textContent?.trim() ?? '',
            "long": cell.querySelector('abbr')?.getAttribute('title') ?? '',
        }));
        const language = document.documentElement.lang || undefined;
        const made = context({...config, month: month}, answer, weekdays, labels, language);
        const templates = await load('core/templates');
        const {html, js} = await templates.renderForPromise(TEMPLATE, made);
        if (seq !== tickets.get(root)) {
            return;
        }
        templates.replaceNodeContents(body, html, js);
        root.dataset.month = month;
        const status = root.querySelector(SELECTORS.status);
        if (status) {
            status.textContent = String(made.caption);
        }
        refocus(root);
    } catch (failure) {
        if (seq === tickets.get(root)) {
            redirect(href);
        }
    } finally {
        pending.resolve();
    }
};

/**
 * Whether a click is a plain one: a modified or a middle click is the browser's (a new tab, a copied link).
 *
 * @param {MouseEvent} event The click.
 * @returns {boolean}
 */
const plain = (event: MouseEvent): boolean => event.button === 0
    && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey;

/**
 * Arm one block: the arrows' clicks and the grid's keys, delegated on its root, which the paints never replace.
 *
 * @param {HTMLElement} root The block's root.
 * @returns {void}
 */
export const arm = (root: HTMLElement): void => {
    if (root.classList.contains(MARK) || !readConfig(root)) {
        return;
    }
    root.classList.add(MARK);
    rove(root);

    root.addEventListener('click', (event: MouseEvent) => {
        const nav = (event.target as Element | null)?.closest<HTMLAnchorElement>(SELECTORS.nav);
        if (!nav || !plain(event) || !/^\d{4}-\d{2}$/.test(nav.dataset.month || '')) {
            return;
        }
        event.preventDefault();
        const forward = nav.dataset.month === monthAfter(root.dataset.month || '', 1);
        go(root, nav.dataset.month || '', nav.href, (painted) => {
            rove(painted);
            painted.querySelectorAll<HTMLElement>(SELECTORS.nav)[forward ? 1 : 0]?.focus();
        });
    });

    root.addEventListener('keydown', (event: KeyboardEvent) => {
        const day = (event.target as Element | null)?.closest<HTMLAnchorElement>(SELECTORS.day);
        const date = day?.dataset.date || '';
        if (!day || !date || event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }
        const cell = day.closest('td');
        const column = cell ? Array.from(cell.parentElement?.children ?? []).indexOf(cell) : 0;
        if (event.key === 'PageUp' || event.key === 'PageDown') {
            event.preventDefault();
            const month = monthAfter(root.dataset.month || date.slice(0, 7), event.key === 'PageUp' ? -1 : 1);
            const wanted = sameDay(date, month);
            go(root, month, pageHref(root.dataset.page || '', month), (painted) => rove(painted, wanted)?.focus());
            return;
        }
        const next = target(event.key, date, column);
        if (next === null) {
            return;
        }
        event.preventDefault();
        const found = root.querySelector<HTMLAnchorElement>(`${SELECTORS.day}[data-date="${next}"]`);
        if (found) {
            rove(root, next);
            found.focus();
            return;
        }
        const month = next.slice(0, 7);
        go(root, month, pageHref(root.dataset.page || '', month, next), (painted) => rove(painted, next)?.focus());
    });
};

/**
 * Arm every month block the page carries.
 *
 * @returns {void}
 */
export const start = (): void => {
    document.querySelectorAll<HTMLElement>(SELECTORS.root).forEach((root) => arm(root));
};
