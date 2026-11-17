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
 * A row of the category navigation as templates/category_navpills.mustache draws it, and the layout jsdom lacks.
 *
 * The row: Category (current unless a test names another destination), the groups Settings and User and access, a
 * plain pill Restore course (a group of one), the group Competencies, and More, hidden and empty unless it holds a
 * node of its own (Insights). jsdom lays nothing out, so each unit's width and the track's are stubbed (More is DOT
 * wider while its pill holds a dot), and a ResizeObserver is faked so a test says when its entry comes.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** The row's id, and so the prefix of every menu's. */
export const ID = 'dim-navpills-6740a1b2c3d4e';

/** The units' widths by their key (a plain pill's data-key, a group's data-group), More's under `other`. */
export const WIDTHS: Record<string, number> = {
    categorymain: 90,
    settings: 100,
    access: 140,
    restorecourse: 120,
    competencies: 130,
    other: 70,
};

/** What More's dot adds to its width: the 4px dot and the pill's 6px gap. */
export const DOT = 10;

/**
 * One destination of a menu, as the template draws it.
 *
 * @param {string} key The node's key.
 * @param {string} text Its text.
 * @param {boolean} current Whether it is the current page.
 * @returns {string}
 */
const item = (key: string, text: string, current = false): string =>
    `<a class="dim-navpill-menu__item" href="https://example.com/${key}" data-key="${key}"`
    + `${current ? ' aria-current="page"' : ''}><i class="icon fa fa-link" aria-hidden="true"></i>`
    + `<span class="dim-navpill-menu__label">${text}</span>`
    + `${current ? '<span class="dim-navpill__dot" aria-hidden="true"></span>' : ''}</a>`;

/**
 * One group, as the template draws it.
 *
 * @param {string} key The group's key.
 * @param {string} name Its name.
 * @param {string} items Its destinations' markup.
 * @param {string} more For More: its own class and attributes after the group's; nothing for a group.
 * @param {boolean} selected Whether it holds the current page: its pill selected, with the dot.
 * @returns {string}
 */
const group = (key: string, name: string, items: string, more = '', selected = false): string =>
    `<details class="dim-navpill-group${more ? ' dim-navpill-group--more' : ''}" data-group="${key}"${more}>`
    + `<summary class="dim-navpill${selected ? ' is-selected' : ''}">`
    + `${selected ? '<span class="dim-navpill__dot" aria-hidden="true"></span>' : ''}`
    + `<span class="dim-navpill__label">${name}</span>`
    + '<span class="dim-navpill__chevron"><i class="icon fa fa-chevron-down" aria-hidden="true"></i></span></summary>'
    + `<div class="dim-navpill-menu" id="${ID}-${key}">${items}</div></details>`;

/**
 * One plain pill, as the template draws it.
 *
 * @param {string} key The node's key.
 * @param {string} text Its text.
 * @param {boolean} current Whether it is the current page.
 * @returns {string}
 */
const pill = (key: string, text: string, current = false): string =>
    `<a class="dim-navpill" href="https://example.com/${key}" data-key="${key}"${current ? ' aria-current="page"' : ''}>`
    + '<span class="dim-navpill__icon"><i class="icon fa fa-link" aria-hidden="true"></i></span>'
    + `${current ? '<span class="dim-navpill__dot" aria-hidden="true"></span>' : ''}`
    + `<span class="dim-navpill__label">${text}</span></a>`;

/**
 * Draw the row in the document, in the hero's wrapper and track, with a link after it.
 *
 * The group holding the current destination is drawn as the server draws it: its pill selected, with the dot, and no
 * aria-current, which the destination's own link alone carries (D96).
 *
 * @param {boolean} own Whether More holds a node of its own.
 * @param {string} current The key of the current destination.
 * @returns {HTMLElement} The row's nav.
 */
export const drawRow = (own = false, current = 'categorymain'): HTMLElement => {
    const more = own ? item('insights', 'Insights') : '';
    const items = (pairs: Array<[string, string]>): string =>
        pairs.map(([key, text]) => item(key, text, key === current)).join('');
    const holds = (pairs: Array<[string, string]>): boolean => pairs.some(([key]) => key === current);
    const settings: Array<[string, string]> = [['categoryappearance', 'Category appearance'], ['edit', 'Settings']];
    const access: Array<[string, string]> = [['cohort', 'Cohorts'], ['permissions', 'Permissions']];
    const competencies: Array<[string, string]> = [
        ['competencyframeworks', 'Competency frameworks'],
        ['learningplantemplates', 'Learning plan templates'],
    ];
    document.body.innerHTML = '<div class="dim-hero__nav"><div class="dim-hero__navtrack">'
        + `<nav class="dim-navpills" id="${ID}" aria-label="Category navigation" data-region="dim-navpills">`
        + pill('categorymain', 'Category', current === 'categorymain')
        + group('settings', 'Settings', items(settings), '', holds(settings))
        + group('access', 'User and access', items(access), '', holds(access))
        + pill('restorecourse', 'Restore course', current === 'restorecourse')
        + group('competencies', 'Competencies', items(competencies), '', holds(competencies))
        + group('other', 'More', more, ` data-region="dim-navpills-more"${own ? '' : ' hidden'}`)
        + '</nav></div></div><a href="https://example.com/after" id="after">After</a>';
    return document.getElementById(ID) as HTMLElement;
};

/** The callbacks of every ResizeObserver made since the last install, by the element each observes. */
let observed: Array<{target: Element, callback: ResizeObserverCallback}> = [];

/**
 * Put a fake ResizeObserver on the window: its entries come when a test sends them.
 *
 * @returns {void}
 */
export const installObserver = (): void => {
    observed = [];
    (window as unknown as {ResizeObserver: unknown}).ResizeObserver = class {
        /** The callback. */
        callback: ResizeObserverCallback;

        /**
         * Keep the callback.
         *
         * @param {Function} callback The callback.
         */
        constructor(callback: ResizeObserverCallback) {
            this.callback = callback;
        }

        /**
         * Note the element observed.
         *
         * @param {Element} target The element.
         * @returns {void}
         */
        observe(target: Element): void {
            observed.push({target: target, callback: this.callback});
        }

        /**
         * Nothing to let go of.
         *
         * @returns {void}
         */
        disconnect(): void {
            // A fake observer holds nothing.
        }
    };
};

/**
 * Take the fake ResizeObserver off the window.
 *
 * @returns {void}
 */
export const removeObserver = (): void => {
    delete (window as unknown as {ResizeObserver?: unknown}).ResizeObserver;
    observed = [];
};

/**
 * Lay the row out: the track as wide as the room, each unit and More as wide as WIDTHS says, no gap or padding.
 *
 * More is DOT wider while its pill holds a dot, as a browser draws it.
 *
 * @param {HTMLElement} nav The row's nav.
 * @param {number} room The track's width.
 * @returns {void}
 */
export const layout = (nav: HTMLElement, room: number): void => {
    const track = nav.parentElement as HTMLElement;
    Object.defineProperty(track, 'clientWidth', {configurable: true, get: () => room});
    Array.from(nav.children).forEach((child) => {
        const element = child as HTMLElement;
        const key = element.dataset.group || element.dataset.key || '';
        const dot = (): number => (key === 'other' && element.querySelector(':scope > summary > .dim-navpill__dot') ? DOT : 0);
        element.getBoundingClientRect = () => ({width: (WIDTHS[key] ?? 0) + dot(), height: 32} as DOMRect);
    });
};

/**
 * Send the track's ResizeObserver an entry of this width, after laying the row out in it.
 *
 * @param {HTMLElement} nav The row's nav.
 * @param {number} room The track's width.
 * @returns {void}
 */
export const resize = (nav: HTMLElement, room: number): void => {
    layout(nav, room);
    const track = nav.parentElement as HTMLElement;
    observed.filter((entry) => entry.target === track).forEach((entry) => {
        entry.callback([{target: track, contentRect: {width: room} as DOMRectReadOnly} as ResizeObserverEntry],
            {} as ResizeObserver);
    });
};

/**
 * How many elements observe the track.
 *
 * @param {HTMLElement} nav The row's nav.
 * @returns {number}
 */
export const observers = (nav: HTMLElement): number => observed.filter((entry) => entry.target === nav.parentElement).length;
