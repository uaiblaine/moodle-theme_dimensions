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
 * The category navigation's row of nav pills, once a script runs: menus with their keys, and "More" for what does not fit.
 *
 * The server draws the whole row and it works without this module (templates/category_navpills.mustache): a plain
 * pill is a link, a group a native disclosure whose menu holds one link per destination. Armed, the row claims the
 * menu roles only now that the keys they promise work (D91): the summary of each group gets `aria-haspopup`,
 * `aria-controls` and `aria-expanded`, its menu `role="menu"`, its links `role="menuitem"`. One menu is open at a
 * time; ArrowDown, Enter or Space on a pill opens its menu on the first item; inside, the arrows move with wrap,
 * Home and End go to the ends, Escape closes and gives the focus back to the pill, and Tab out closes. A press
 * outside closes without moving the focus. A group's menu that would cross the viewport's end opens end-aligned.
 *
 * The overflow (D83, D89): the armed capsule stops wrapping, and what does not fit moves into "More" from the
 * row's end, "Category" last of all; the arithmetic is {@see ./fit fit()}. A moved group becomes a headed section of
 * More's menu, a separator between sections and after the nodes More holds of its own; a moved plain pill becomes
 * an item, its server-drawn icon shown. Nodes are moved, never cloned: one link per destination and no duplicate id.
 * The row is measured once per pass, with every unit put back in the server's order first, at arming (the first
 * entry of a ResizeObserver on the track), after the fonts are ready and on every change of the track's width; never
 * while a menu is open, which defers the pass to its closing. More takes the selected look, its class and its dot,
 * when it holds the current page (D82), and the pass pays for that dot; only the current page's own link carries
 * aria-current (D96).
 *
 * The row is an account's (the visitor's shell draws none), and nothing a visitor's page loads imports this module.
 *
 * @module     theme_dimensions/navpills/row
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from '@moodle/lms/core/pending';
import {fit} from './fit';
import type {Fitted} from './fit';

/** The key of the page itself, which moves into "More" last of all: navgroups::MAIN. */
export const MAIN = 'categorymain';

/** Every hook this module reads, written by the row's template. */
const SELECTORS = {
    row: '[data-region="dim-navpills"]',
    more: '[data-region="dim-navpills-more"]',
    menu: ':scope > .dim-navpill-menu',
    summary: ':scope > summary',
    item: '.dim-navpill-menu__item',
    menuitem: '[role="menuitem"]',
    label: '.dim-navpill__label',
};

/** Every class this module writes. */
export const CLASSES = {
    live: 'dim-navpills--live',
    pill: 'dim-navpill',
    item: 'dim-navpill-menu__item',
    head: 'dim-navpill-menu__head',
    section: 'dim-navpill-menu__section',
    separator: 'dim-navpill-menu__sep',
    end: 'dim-navpill-menu--end',
    selected: 'is-selected',
    dot: 'dim-navpill__dot',
};

/** The keys that open a pill's menu on its first item. */
const OPENERS = ['ArrowDown', 'Enter', ' '];

/** One unit of the row, as the server drew it: a plain pill or a group. */
type Unit = {
    /** The unit's node on the row: the pill's link, or the group's disclosure. */
    element: HTMLElement,
    /** The group's disclosure; null for a plain pill. */
    group: HTMLDetailsElement | null,
    /** The group's own menu; null for a plain pill. */
    menu: HTMLElement | null,
    /** The group's links, in the server's order. */
    items: HTMLElement[],
    /** Whether the unit is the page itself, which moves last. */
    main: boolean,
};

/** One armed row. */
export type Row = {
    nav: HTMLElement,
    /** The track the capsule sits on: its width is the room. */
    track: HTMLElement,
    /** The units on the row, in the server's order, More not among them. */
    units: Unit[],
    more: HTMLDetailsElement,
    moremenu: HTMLElement,
    /** The links More holds of its own, which never move. */
    own: HTMLElement[],
    /** Whether the server drew More selected (one of its own links is the current page). */
    servercurrent: boolean,
    /** The dot this module puts on More when a unit it holds is the current page. */
    dot: HTMLElement,
    /** The units in More now, in the server's order. */
    moved: Unit[],
    /** The headings, sections and separators this module added to More's menu. */
    added: HTMLElement[],
    /** The disclosure open now, if any. */
    open: HTMLDetailsElement | null,
    /** Whether a pass was asked while a menu was open: it runs when the menu closes. */
    stale: boolean,
    /** The track's width at the last pass a ResizeObserver asked for. */
    width: number,
};

/**
 * A length of a computed style in pixels, 0 when there is none.
 *
 * @param {string} value The computed value.
 * @returns {number}
 */
const px = (value: string): number => parseFloat(value) || 0;

/**
 * A disclosure's summary, its pill.
 *
 * @param {HTMLDetailsElement} details The disclosure.
 * @returns {HTMLElement|null}
 */
const summaryOf = (details: HTMLDetailsElement): HTMLElement | null => details.querySelector<HTMLElement>(SELECTORS.summary);

/**
 * A disclosure's menu.
 *
 * @param {HTMLDetailsElement} details The disclosure.
 * @returns {HTMLElement|null}
 */
const menuOf = (details: HTMLDetailsElement): HTMLElement | null => details.querySelector<HTMLElement>(SELECTORS.menu);

/**
 * The items of a disclosure's menu, every section's, in the order they are drawn.
 *
 * @param {HTMLDetailsElement} details The disclosure.
 * @returns {Array}
 */
const itemsOf = (details: HTMLDetailsElement): HTMLElement[] => {
    const menu = menuOf(details);
    return menu ? Array.from(menu.querySelectorAll<HTMLElement>(SELECTORS.menuitem)) : [];
};

/**
 * Every disclosure of the row, the groups in the row's order and More last.
 *
 * @param {object} row The row.
 * @returns {Array}
 */
const disclosures = (row: Row): HTMLDetailsElement[] => {
    const groups = row.units.map((unit) => unit.group).filter((group): group is HTMLDetailsElement => group !== null);
    return [...groups, row.more];
};

/**
 * The item a key moves the focus to inside a menu, with wrap; null for a key the menu does not answer.
 *
 * @param {string} key The key pressed.
 * @param {number} index The focused item's index, -1 for none.
 * @param {number} count How many items the menu holds.
 * @returns {number|null}
 */
export const step = (key: string, index: number, count: number): number | null => {
    if (count === 0) {
        return null;
    }
    if (key === 'ArrowDown') {
        return (index + 1) % count;
    }
    if (key === 'ArrowUp') {
        return index <= 0 ? count - 1 : index - 1;
    }
    if (key === 'Home') {
        return 0;
    }
    if (key === 'End') {
        return count - 1;
    }
    return null;
};

/**
 * Read the row the server drew: its units in order, More, its own links and whether it holds the current page.
 *
 * @param {HTMLElement} nav The row's nav.
 * @returns {object|null} The row, or null when the markup is not the row's.
 */
const collect = (nav: HTMLElement): Row | null => {
    const more = nav.querySelector<HTMLDetailsElement>(SELECTORS.more);
    const moremenu = more ? menuOf(more) : null;
    const track = nav.parentElement;
    if (!more || !moremenu || !track) {
        return null;
    }
    const elements = Array.from(nav.children).filter((child): child is HTMLElement => child instanceof HTMLElement);
    const units = elements.filter((element) => element !== more).map((element): Unit => {
        const group = element instanceof HTMLDetailsElement ? element : null;
        const menu = group ? menuOf(group) : null;
        return {
            element: element,
            group: group,
            menu: menu,
            items: menu ? Array.from(menu.querySelectorAll<HTMLElement>(SELECTORS.item)) : [],
            main: element.dataset.key === MAIN,
        };
    });
    const dot = document.createElement('span');
    dot.className = CLASSES.dot;
    dot.setAttribute('aria-hidden', 'true');
    return {
        nav: nav,
        track: track,
        units: units,
        more: more,
        moremenu: moremenu,
        own: Array.from(moremenu.querySelectorAll<HTMLElement>(SELECTORS.item)),
        servercurrent: Boolean(summaryOf(more)?.classList.contains(CLASSES.selected)),
        dot: dot,
        moved: [],
        added: [],
        open: null,
        stale: false,
        width: -1,
    };
};

/**
 * Make a link a menu item, or give it back to the row as a plain pill.
 *
 * @param {HTMLElement} link The link.
 * @param {boolean} inmenu Whether it is now in a menu.
 * @param {boolean} swap Whether its class changes too: a plain pill's, moved into More or back.
 * @returns {void}
 */
const itemise = (link: HTMLElement, inmenu: boolean, swap: boolean): void => {
    if (swap) {
        link.classList.toggle(CLASSES.pill, !inmenu);
        link.classList.toggle(CLASSES.item, inmenu);
    }
    if (inmenu) {
        link.setAttribute('role', 'menuitem');
        link.tabIndex = -1;
    } else {
        link.removeAttribute('role');
        link.removeAttribute('tabindex');
    }
};

/**
 * Claim the menu roles the keys now keep: on every summary, every menu and every link in a menu.
 *
 * @param {object} row The row.
 * @returns {void}
 */
const addRoles = (row: Row): void => {
    disclosures(row).forEach((details) => {
        const summary = summaryOf(details);
        const menu = menuOf(details);
        if (!summary || !menu) {
            return;
        }
        summary.setAttribute('aria-haspopup', 'menu');
        summary.setAttribute('aria-controls', menu.id);
        summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');
        menu.setAttribute('role', 'menu');
        menu.querySelectorAll<HTMLElement>(SELECTORS.item).forEach((link) => itemise(link, true, false));
    });
};

/**
 * Open a group's menu end-aligned when, start-aligned, it would cross the viewport's end.
 *
 * The one layout read outside the measuring pass, made as the menu opens: a menu is not drawn while it is closed.
 * More's menu opens end-aligned already.
 *
 * @param {object} row The row.
 * @param {HTMLDetailsElement} details The disclosure just opened.
 * @returns {void}
 */
const place = (row: Row, details: HTMLDetailsElement): void => {
    const menu = menuOf(details);
    if (!menu || details === row.more) {
        return;
    }
    menu.classList.remove(CLASSES.end);
    const rect = menu.getBoundingClientRect();
    const rtl = window.getComputedStyle(row.nav).direction === 'rtl';
    const end = document.documentElement.clientWidth;
    menu.classList.toggle(CLASSES.end, rtl ? rect.left < 0 : end > 0 && rect.right > end);
};

/**
 * Note that a disclosure opened, by this module or natively: the others close, and it is placed.
 *
 * @param {object} row The row.
 * @param {HTMLDetailsElement} details The disclosure.
 * @returns {void}
 */
const opened = (row: Row, details: HTMLDetailsElement): void => {
    if (row.open === details) {
        return;
    }
    // Recorded first: closing another menu runs a deferred pass, which must see this one open.
    row.open = details;
    disclosures(row).forEach((other) => {
        if (other !== details && other.open) {
            close(row, other, false);
        }
    });
    summaryOf(details)?.setAttribute('aria-expanded', 'true');
    place(row, details);
};

/**
 * Note that a disclosure closed, by this module or natively; a pass asked for while it was open runs now.
 *
 * @param {object} row The row.
 * @param {HTMLDetailsElement} details The disclosure.
 * @returns {void}
 */
const closed = (row: Row, details: HTMLDetailsElement): void => {
    summaryOf(details)?.setAttribute('aria-expanded', 'false');
    menuOf(details)?.classList.remove(CLASSES.end);
    if (row.open !== details) {
        return;
    }
    row.open = null;
    if (row.stale) {
        measure(row);
    }
};

/**
 * Open a disclosure's menu, closing any other, and focus its first item if asked.
 *
 * @param {object} row The row.
 * @param {HTMLDetailsElement} details The disclosure.
 * @param {boolean} focus Whether the first item takes the focus.
 * @returns {void}
 */
export const open = (row: Row, details: HTMLDetailsElement, focus: boolean): void => {
    details.open = true;
    opened(row, details);
    if (focus) {
        itemsOf(details)[0]?.focus();
    }
};

/**
 * Close a disclosure's menu, and give the focus back to its pill (More's, if the pill has moved there) if asked.
 *
 * @param {object} row The row.
 * @param {HTMLDetailsElement} details The disclosure.
 * @param {boolean} focus Whether its pill takes the focus.
 * @returns {void}
 */
export const close = (row: Row, details: HTMLDetailsElement, focus: boolean): void => {
    details.open = false;
    closed(row, details);
    if (focus) {
        // The pass closed() may run can have moved this group into More: its pill is hidden then, and More takes the focus.
        const pill = details.hidden ? summaryOf(row.more) : summaryOf(details);
        pill?.focus();
    }
};

/**
 * Whether a unit holds the current page: a plain pill that is it, or a group with it among its links.
 *
 * Only a link carries aria-current (D96): a group's pill, a summary, shows the selected look through its class.
 *
 * @param {object} unit The unit.
 * @returns {boolean}
 */
const holdsCurrent = (unit: Unit): boolean =>
    [unit.element, ...unit.items].some((link) => link.getAttribute('aria-current') === 'page');

/**
 * Give More the selected look while it holds the current page, unless the server drew it so.
 *
 * The look is the class and the dot, never aria-current: More is a summary, not the current page's link (D96).
 *
 * @param {object} row The row.
 * @returns {void}
 */
const paintMore = (row: Row): void => {
    const summary = summaryOf(row.more);
    if (row.servercurrent || !summary) {
        return;
    }
    const current = row.moved.some(holdsCurrent);
    summary.classList.toggle(CLASSES.selected, current);
    if (current) {
        summary.prepend(row.dot);
    } else {
        row.dot.remove();
    }
};

/**
 * Put every unit back on the row in the server's order, and More back to its own links.
 *
 * A node already in its place is not moved, so a pill with the focus keeps it.
 *
 * @param {object} row The row.
 * @returns {void}
 */
const restore = (row: Row): void => {
    row.moved.forEach((unit) => {
        if (unit.group && unit.menu) {
            unit.menu.append(...unit.items);
            unit.group.hidden = false;
        } else {
            itemise(unit.element, false, true);
        }
    });
    row.added.forEach((element) => element.remove());
    row.added = [];
    row.moved = [];
    let anchor: HTMLElement = row.more;
    for (let index = row.units.length - 1; index >= 0; index--) {
        const element = row.units[index].element;
        if (element.parentElement !== row.nav || element.nextElementSibling !== anchor) {
            row.nav.insertBefore(element, anchor);
        }
        anchor = element;
    }
    row.more.hidden = row.own.length === 0;
    paintMore(row);
};

/**
 * Add one element this module made to More's menu.
 *
 * @param {object} row The row.
 * @param {string} tag The element's tag.
 * @param {string} name Its class.
 * @returns {HTMLElement}
 */
const make = (row: Row, tag: string, name: string): HTMLElement => {
    const element = document.createElement(tag);
    element.className = name;
    row.moremenu.append(element);
    row.added.push(element);
    return element;
};

/**
 * Move the units fit() moved into More, in the row's order, and show or hide More.
 *
 * A group is a section headed by its own name (its pill's word, the server's), a plain pill an item; a separator
 * goes before every section that follows something, More's own links included, and between a run of plain items
 * and a section.
 *
 * @param {object} row The row, every unit on it.
 * @param {object} fitted What fit() decided.
 * @returns {void}
 */
const apply = (row: Row, fitted: Fitted): void => {
    let above = row.own.length > 0;
    let plain = false;
    fitted.move.forEach((index) => {
        const unit = row.units[index];
        if (unit.group && unit.menu) {
            if (above) {
                make(row, 'div', CLASSES.separator).setAttribute('role', 'separator');
            }
            const head = make(row, 'div', CLASSES.head);
            head.id = unit.menu.id + '-head';
            head.textContent = unit.group.querySelector(SELECTORS.label)?.textContent ?? '';
            const section = make(row, 'div', CLASSES.section);
            section.setAttribute('role', 'group');
            section.setAttribute('aria-labelledby', head.id);
            section.append(...unit.items);
            unit.group.hidden = true;
            plain = false;
        } else {
            if (above && !plain) {
                make(row, 'div', CLASSES.separator).setAttribute('role', 'separator');
            }
            itemise(unit.element, true, true);
            row.moremenu.append(unit.element);
            plain = true;
        }
        above = true;
        row.moved.push(unit);
    });
    row.more.hidden = !fitted.more;
    paintMore(row);
};

/**
 * The measuring pass: every unit back on the row, one read of every width and of the room, then fit() and the moves.
 *
 * Deferred while a menu is open (it runs when the menu closes). A track that is not drawn has no width and is left
 * with every unit on the row: a ResizeObserver entry comes when it is drawn.
 *
 * @param {object} row The row.
 * @returns {void}
 */
export const measure = (row: Row): void => {
    if (row.open) {
        row.stale = true;
        return;
    }
    row.stale = false;
    restore(row);
    const hidden = row.more.hidden;
    row.more.hidden = false;
    const track = row.track.clientWidth;
    if (track <= 0) {
        row.more.hidden = hidden;
        return;
    }
    const widths = row.units.map((unit) => unit.element.getBoundingClientRect().width);
    const more = row.more.getBoundingClientRect().width;
    // More's width once it holds the current page, with the dot paintMore() then adds: unless the server drew it so.
    const current = row.units.findIndex(holdsCurrent);
    let selected = more;
    const summary = summaryOf(row.more);
    if (current >= 0 && !row.servercurrent && summary) {
        summary.prepend(row.dot);
        selected = row.more.getBoundingClientRect().width;
        row.dot.remove();
    }
    const capsule = window.getComputedStyle(row.nav);
    const trackstyle = window.getComputedStyle(row.track);
    const room = track - px(trackstyle.paddingLeft) - px(trackstyle.paddingRight)
        - px(capsule.paddingLeft) - px(capsule.paddingRight) - px(capsule.borderLeftWidth) - px(capsule.borderRightWidth);
    apply(row, fit({
        widths: widths,
        gap: px(capsule.columnGap),
        room: room,
        more: more,
        selected: selected,
        current: current,
        last: row.units.findIndex((unit) => unit.main),
        own: row.own.length > 0,
    }));
};

/**
 * Answer a key on a pill: open its menu on the first item, or close it.
 *
 * @param {object} row The row.
 * @param {HTMLDetailsElement} details The pill's disclosure.
 * @param {KeyboardEvent} event The key.
 * @returns {void}
 */
const pillKey = (row: Row, details: HTMLDetailsElement, event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        if (details.open) {
            event.preventDefault();
            close(row, details, false);
        }
        return;
    }
    if (!OPENERS.includes(event.key)) {
        return;
    }
    // The summary's own activation would toggle the disclosure a second time.
    event.preventDefault();
    if (details.open && event.key !== 'ArrowDown') {
        close(row, details, false);
        return;
    }
    open(row, details, true);
};

/**
 * Answer a key inside a menu: the arrows, Home and End move the focus; Escape closes and returns it to the pill.
 *
 * @param {object} row The row.
 * @param {HTMLDetailsElement} details The menu's disclosure.
 * @param {KeyboardEvent} event The key.
 * @returns {void}
 */
const menuKey = (row: Row, details: HTMLDetailsElement, event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        close(row, details, true);
        return;
    }
    const items = itemsOf(details);
    const next = step(event.key, items.indexOf(event.target as HTMLElement), items.length);
    if (next === null) {
        return;
    }
    event.preventDefault();
    items[next].focus();
};

/**
 * Route a key pressed in the row to the pill's or the menu's handler.
 *
 * @param {object} row The row.
 * @param {KeyboardEvent} event The key.
 * @returns {void}
 */
const onKey = (row: Row, event: KeyboardEvent): void => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
        return;
    }
    const details = target.closest<HTMLDetailsElement>('details');
    if (!details || !row.nav.contains(details)) {
        return;
    }
    if (target === summaryOf(details)) {
        pillKey(row, details, event);
    } else if (menuOf(details)?.contains(target)) {
        menuKey(row, details, event);
    }
};

/**
 * Keep Space from activating a pill on its way up, which some browsers do after the keydown was answered.
 *
 * @param {object} row The row.
 * @param {KeyboardEvent} event The key.
 * @returns {void}
 */
const onKeyUp = (row: Row, event: KeyboardEvent): void => {
    const target = event.target;
    if (event.key === ' ' && target instanceof HTMLElement && target.matches('summary') && row.nav.contains(target)) {
        event.preventDefault();
    }
};

/**
 * Note a disclosure opened or closed natively (a press on its pill): the one-open rule holds for those too.
 *
 * @param {object} row The row.
 * @param {Event} event The toggle event, heard in capture: it does not bubble.
 * @returns {void}
 */
const onToggle = (row: Row, event: Event): void => {
    const details = event.target;
    if (!(details instanceof HTMLDetailsElement) || !disclosures(row).includes(details)) {
        return;
    }
    if (details.open) {
        opened(row, details);
    } else {
        closed(row, details);
    }
};

/**
 * Close the open menu when the focus leaves its disclosure for somewhere else (Tab out of it).
 *
 * @param {object} row The row.
 * @param {FocusEvent} event The focus leaving.
 * @returns {void}
 */
const onFocusOut = (row: Row, event: FocusEvent): void => {
    const next = event.relatedTarget;
    if (row.open && next instanceof Node && !row.open.contains(next)) {
        close(row, row.open, false);
    }
};

/**
 * Close the open menu on a press outside it, without moving the focus.
 *
 * @param {object} row The row.
 * @param {Event} event The press.
 * @returns {void}
 */
const onPress = (row: Row, event: Event): void => {
    const target = event.target;
    if (row.open && target instanceof Node && !row.open.contains(target)) {
        close(row, row.open, false);
    }
};

/**
 * Listen on the row, and on the document for a press outside it.
 *
 * @param {object} row The row.
 * @returns {void}
 */
const listen = (row: Row): void => {
    row.nav.addEventListener('toggle', (event) => onToggle(row, event), true);
    row.nav.addEventListener('keydown', (event) => onKey(row, event));
    row.nav.addEventListener('keyup', (event) => onKeyUp(row, event));
    row.nav.addEventListener('focusout', (event) => onFocusOut(row, event));
    document.addEventListener('pointerdown', (event) => onPress(row, event), true);
};

/**
 * Measure at arming and on every change of the track's width; the arming's token is released after the first pass.
 *
 * The first pass is the ResizeObserver's first entry, which every observed element gets, so arming forces no layout
 * of its own; without a ResizeObserver it runs at once.
 *
 * @param {object} row The row.
 * @param {Pending} pending The arming's token.
 * @returns {void}
 */
const observe = (row: Row, pending: Pending): void => {
    if (typeof ResizeObserver === 'undefined') {
        try {
            measure(row);
        } finally {
            pending.resolve();
        }
        return;
    }
    let first = true;
    const observer = new ResizeObserver((entries) => {
        const width = entries[entries.length - 1].contentRect.width;
        try {
            // A pass moves units inside the capsule, which never changes the track's width.
            if (first || width !== row.width) {
                row.width = width;
                measure(row);
            }
        } finally {
            if (first) {
                first = false;
                pending.resolve();
            }
        }
    });
    observer.observe(row.track);
};

/**
 * Measure again once the fonts are ready: a face that arrives late changes every pill's width.
 *
 * @param {object} row The row.
 * @returns {Promise} Resolving after the pass.
 */
const afterFonts = async(row: Row): Promise<void> => {
    if (!document.fonts) {
        return;
    }
    const pending = new Pending('theme_dimensions/navpills:fonts');
    try {
        await document.fonts.ready;
        measure(row);
    } finally {
        pending.resolve();
    }
};

/**
 * Arm one row: its live class, the menu roles, the listeners and the measuring passes. Safe to call more than once.
 *
 * @param {HTMLElement} nav The row's nav.
 * @returns {object|null} The row armed now, or null when it was armed already or is not the row's markup.
 */
export const arm = (nav: HTMLElement): Row | null => {
    if (nav.classList.contains(CLASSES.live)) {
        return null;
    }
    const row = collect(nav);
    if (!row) {
        return null;
    }
    const pending = new Pending('theme_dimensions/navpills:arm');
    nav.classList.add(CLASSES.live);
    addRoles(row);
    listen(row);
    observe(row, pending);
    afterFonts(row);
    return row;
};

/**
 * Arm the row the core renderer drew under this id: the entry amd/src/navpills.js hands over.
 *
 * @param {string} uniqid The row's id.
 * @returns {void}
 */
export const init = (uniqid: string): void => {
    const nav = document.getElementById(uniqid);
    if (nav instanceof HTMLElement && nav.matches(SELECTORS.row)) {
        arm(nav);
    }
};
