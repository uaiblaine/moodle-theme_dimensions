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
 * Tests for the category navigation's row once armed: the menu roles, the keys, one menu open at a time, the
 * outside press, and the moves into "More" with their sections, in jsdom.
 *
 * jsdom lays nothing out: the row of page.ts stubs each unit's width and the track's, and its fake ResizeObserver
 * sends the entries the tests ask for. With those widths every unit takes 580 and More 70, so a room of 400 moves
 * Competencies and Restore course into More, and 160 leaves Category alone on the row.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CLASSES, arm, init, step} from '../../src/navpills/row';
import {ID, drawRow, installObserver, layout, observers, removeObserver, resize} from './page';

beforeEach(() => {
    installObserver();
});

afterEach(() => {
    removeObserver();
    document.body.replaceChildren();
});

/**
 * A group's disclosure on the page.
 *
 * @param {string} key The group's key; `other` for More.
 * @returns {HTMLDetailsElement}
 */
const group = (key: string): HTMLDetailsElement =>
    document.querySelector(`details[data-group="${key}"]`) as HTMLDetailsElement;

/**
 * A disclosure's pill.
 *
 * @param {HTMLDetailsElement} details The disclosure.
 * @returns {HTMLElement}
 */
const pillOf = (details: HTMLDetailsElement): HTMLElement => details.querySelector(':scope > summary') as HTMLElement;

/**
 * More's menu.
 *
 * @returns {HTMLElement}
 */
const moreMenu = (): HTMLElement => group('other').querySelector('.dim-navpill-menu') as HTMLElement;

/**
 * Press a key on an element.
 *
 * @param {Element} target The element.
 * @param {string} key The key.
 * @returns {KeyboardEvent} The event, to read whether it was answered.
 */
const press = (target: Element, key: string): KeyboardEvent => {
    const event = new KeyboardEvent('keydown', {key: key, bubbles: true, cancelable: true});
    target.dispatchEvent(event);
    return event;
};

/**
 * Let the queued tasks run: jsdom fires a disclosure's toggle event after a timer, and Pending completes after one.
 *
 * @returns {Promise}
 */
const settle = (): Promise<void> => new Promise((resolve) => {
    setTimeout(resolve, 0);
});

/**
 * Each child of More's menu, as a short word: `a:<key>`, `separator`, `head:<text>` or `group:<items>`.
 *
 * @returns {Array}
 */
const moreShape = (): string[] => Array.from(moreMenu().children).map((child) => {
    if (child.matches('a')) {
        return 'a:' + (child as HTMLElement).dataset.key;
    }
    if (child.getAttribute('role') === 'separator') {
        return 'separator';
    }
    if (child.classList.contains(CLASSES.head)) {
        return 'head:' + child.textContent;
    }
    return child.getAttribute('role') + ':' + child.querySelectorAll('a').length;
});

/**
 * The keys of the units on the row, in the order drawn, More's included.
 *
 * @param {HTMLElement} nav The row's nav.
 * @returns {Array}
 */
const rowKeys = (nav: HTMLElement): string[] => Array.from(nav.children)
    .filter((child) => !(child as HTMLElement).hidden)
    .map((child) => (child as HTMLElement).dataset.group || (child as HTMLElement).dataset.key || '');

/**
 * How many times a key was given to M.util.js_complete() so far.
 *
 * @param {string} key The key.
 * @returns {number}
 */
const completed = (key: string): number => completeStack.filter((done) => done === key).length;

describe('arming', () => {
    it('claims the menu roles only once armed, and arms a row once', () => {
        const nav = drawRow();
        expect(nav.querySelector('[role]')).toBeNull();
        expect(nav.querySelector('[aria-haspopup]')).toBeNull();

        expect(arm(nav)).not.toBeNull();

        expect(nav.classList.contains(CLASSES.live)).toBe(true);
        const summary = pillOf(group('settings'));
        expect(summary.getAttribute('aria-haspopup')).toBe('menu');
        expect(summary.getAttribute('aria-controls')).toBe(`${ID}-settings`);
        expect(summary.getAttribute('aria-expanded')).toBe('false');
        expect(document.getElementById(`${ID}-settings`)?.getAttribute('role')).toBe('menu');
        expect(document.getElementById(`${ID}-other`)?.getAttribute('role')).toBe('menu');
        nav.querySelectorAll('.dim-navpill-menu__item').forEach((link) => {
            expect(link.getAttribute('role')).toBe('menuitem');
            expect(link.getAttribute('tabindex')).toBe('-1');
        });
        // A plain pill on the row is a link of the nav, not a menu's item.
        expect(nav.querySelector('a[data-key="categorymain"]')?.hasAttribute('role')).toBe(false);
        expect(observers(nav)).toBe(1);

        expect(arm(nav)).toBeNull();
        expect(observers(nav)).toBe(1);
    });

    it('arms through its id, and nothing that is not the row', () => {
        const nav = drawRow();
        init('nothing-here');
        expect(nav.classList.contains(CLASSES.live)).toBe(false);
        init(ID);
        expect(nav.classList.contains(CLASSES.live)).toBe(true);
    });

    it('holds a Pending token from the arming to its first pass', async() => {
        const key = 'theme_dimensions/navpills:arm';
        const before = completed(key);
        const nav = drawRow();
        arm(nav);
        await settle();
        expect(completed(key)).toBe(before);
        resize(nav, 400);
        await settle();
        expect(completed(key)).toBe(before + 1);
    });

    it('measures again once the fonts are ready, under a token of its own', async() => {
        const key = 'theme_dimensions/navpills:fonts';
        const before = completed(key);
        Object.defineProperty(document, 'fonts', {configurable: true, value: {ready: Promise.resolve()}});
        try {
            const nav = drawRow();
            layout(nav, 400);
            arm(nav);
            expect(group('other').hidden).toBe(true);
            await settle();
            expect(group('other').hidden).toBe(false);
            expect(completed(key)).toBe(before + 1);
        } finally {
            delete (document as unknown as {fonts?: unknown}).fonts;
        }
    });
});

describe('menus', () => {
    it('opens a menu on its first item from the keys, one at a time', async() => {
        arm(drawRow());
        const settings = group('settings');
        const access = group('access');

        expect(press(pillOf(settings), 'ArrowDown').defaultPrevented).toBe(true);
        expect(settings.open).toBe(true);
        expect(pillOf(settings).getAttribute('aria-expanded')).toBe('true');
        expect(document.activeElement).toBe(settings.querySelector('[data-key="categoryappearance"]'));

        press(pillOf(access), 'Enter');
        expect(access.open).toBe(true);
        expect(settings.open).toBe(false);
        expect(pillOf(settings).getAttribute('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(access.querySelector('[data-key="cohort"]'));

        press(pillOf(access), ' ');
        expect(access.open).toBe(false);
        await settle();

        // Natively: a press on a pill opens its disclosure, and the toggle event closes the other one.
        press(pillOf(access), 'ArrowDown');
        pillOf(settings).click();
        await settle();
        expect(settings.open).toBe(true);
        expect(access.open).toBe(false);
        expect(pillOf(settings).getAttribute('aria-expanded')).toBe('true');
    });

    it('moves inside a menu with wrap, to its ends, and closes on Escape with the focus back on its pill', () => {
        arm(drawRow());
        const settings = group('settings');
        const [appearance, edit] = Array.from(settings.querySelectorAll('a'));
        press(pillOf(settings), 'ArrowDown');

        press(appearance, 'ArrowDown');
        expect(document.activeElement).toBe(edit);
        press(edit, 'ArrowDown');
        expect(document.activeElement).toBe(appearance);
        press(appearance, 'ArrowUp');
        expect(document.activeElement).toBe(edit);
        press(edit, 'Home');
        expect(document.activeElement).toBe(appearance);
        press(appearance, 'End');
        expect(document.activeElement).toBe(edit);

        const escape = press(edit, 'Escape');
        expect(escape.defaultPrevented).toBe(true);
        expect(settings.open).toBe(false);
        expect(document.activeElement).toBe(pillOf(settings));
    });

    it('steps with wrap, and answers no other key', () => {
        expect(step('ArrowDown', -1, 3)).toBe(0);
        expect(step('ArrowDown', 2, 3)).toBe(0);
        expect(step('ArrowUp', 0, 3)).toBe(2);
        expect(step('ArrowUp', -1, 3)).toBe(2);
        expect(step('Home', 2, 3)).toBe(0);
        expect(step('End', 0, 3)).toBe(2);
        expect(step('a', 0, 3)).toBeNull();
        expect(step('ArrowDown', 0, 0)).toBeNull();
    });

    it('closes on a press outside without moving the focus, and stays open for a press inside', () => {
        arm(drawRow());
        const settings = group('settings');
        press(pillOf(settings), 'ArrowDown');
        const focused = document.activeElement;

        settings.querySelector('a')?.dispatchEvent(new Event('pointerdown', {bubbles: true}));
        expect(settings.open).toBe(true);

        document.getElementById('after')?.dispatchEvent(new Event('pointerdown', {bubbles: true}));
        expect(settings.open).toBe(false);
        expect(document.activeElement).toBe(focused);
    });

    it('closes when the focus tabs out of the open menu, not when it moves inside it', () => {
        arm(drawRow());
        const settings = group('settings');
        press(pillOf(settings), 'ArrowDown');
        const [appearance, edit] = Array.from(settings.querySelectorAll('a'));

        appearance.dispatchEvent(new FocusEvent('focusout', {bubbles: true, relatedTarget: edit}));
        expect(settings.open).toBe(true);
        appearance.dispatchEvent(new FocusEvent('focusout', {bubbles: true, relatedTarget: document.getElementById('after')}));
        expect(settings.open).toBe(false);
    });
});

describe('overflow', () => {
    it('leaves every unit on the row when they fit, More hidden', () => {
        const nav = drawRow();
        arm(nav);
        resize(nav, 1000);
        expect(rowKeys(nav)).toEqual(['categorymain', 'settings', 'access', 'restorecourse', 'competencies']);
        expect(group('other').hidden).toBe(true);
    });

    it('moves what does not fit into More, a group as a headed section, a plain pill as an item, nothing cloned', () => {
        const nav = drawRow();
        const links = Array.from(document.querySelectorAll('a'));
        arm(nav);
        resize(nav, 400);

        expect(rowKeys(nav)).toEqual(['categorymain', 'settings', 'access', 'other']);
        expect(moreShape()).toEqual(['a:restorecourse', 'separator', 'head:Competencies', 'group:2']);
        const head = moreMenu().querySelector(`.${CLASSES.head}`) as HTMLElement;
        expect(head.id).toBe(`${ID}-competencies-head`);
        expect(moreMenu().querySelector('[role="group"]')?.getAttribute('aria-labelledby')).toBe(head.id);
        expect(group('competencies').hidden).toBe(true);

        // The moved plain pill is an item now, its icon shown by the stylesheet; one link per destination, the same nodes.
        const restore = moreMenu().querySelector('a[data-key="restorecourse"]') as HTMLElement;
        expect(restore.classList.contains(CLASSES.item)).toBe(true);
        expect(restore.classList.contains(CLASSES.pill)).toBe(false);
        expect(restore.getAttribute('role')).toBe('menuitem');
        links.forEach((link) => expect(link.isConnected).toBe(true));
        expect(document.querySelectorAll('a')).toHaveLength(links.length);
        expect(document.querySelectorAll('a[data-key="restorecourse"]')).toHaveLength(1);
        const ids = Array.from(document.querySelectorAll('[id]')).map((element) => element.id);
        expect(new Set(ids).size).toBe(ids.length);

        // The keys reach every section of More.
        press(pillOf(group('other')), 'ArrowDown');
        expect(document.activeElement).toBe(restore);
        press(restore, 'End');
        expect(document.activeElement).toBe(moreMenu().querySelector('[data-key="learningplantemplates"]'));
    });

    it('lists More\'s own nodes first, then a separator before each moved unit', () => {
        const nav = drawRow(true);
        arm(nav);
        resize(nav, 1000);
        expect(group('other').hidden).toBe(false);
        expect(moreShape()).toEqual(['a:insights']);

        resize(nav, 400);
        expect(moreShape()).toEqual(['a:insights', 'separator', 'a:restorecourse', 'separator', 'head:Competencies', 'group:2']);
    });

    it('puts every unit back in the server\'s order before each pass', () => {
        const nav = drawRow();
        arm(nav);
        resize(nav, 160);
        expect(rowKeys(nav)).toEqual(['categorymain', 'other']);
        expect(moreShape()).toEqual([
            'head:Settings', 'group:2', 'separator', 'head:User and access', 'group:2', 'separator',
            'a:restorecourse', 'separator', 'head:Competencies', 'group:2',
        ]);

        resize(nav, 400);
        expect(rowKeys(nav)).toEqual(['categorymain', 'settings', 'access', 'other']);
        expect(moreShape()).toEqual(['a:restorecourse', 'separator', 'head:Competencies', 'group:2']);

        resize(nav, 1000);
        expect(rowKeys(nav)).toEqual(['categorymain', 'settings', 'access', 'restorecourse', 'competencies']);
        expect(moreShape()).toEqual([]);
        expect(Array.from(group('competencies').querySelectorAll('a')).map((link) => link.dataset.key))
            .toEqual(['competencyframeworks', 'learningplantemplates']);
        const restore = nav.querySelector(':scope > a[data-key="restorecourse"]') as HTMLElement;
        expect(restore.className).toBe(CLASSES.pill);
        expect(restore.hasAttribute('role')).toBe(false);
        expect(restore.hasAttribute('tabindex')).toBe(false);
    });

    it('moves Category last of all, and More then takes the selected look', () => {
        const nav = drawRow();
        arm(nav);
        resize(nav, 160);
        const more = pillOf(group('other'));
        expect(nav.querySelector(':scope > a[data-key="categorymain"]')).not.toBeNull();
        expect(more.classList.contains(CLASSES.selected)).toBe(false);

        resize(nav, 100);
        const category = moreMenu().querySelector('a[data-key="categorymain"]');
        expect(category?.getAttribute('aria-current')).toBe('page');
        expect(more.classList.contains(CLASSES.selected)).toBe(true);
        expect(more.firstElementChild?.classList.contains(CLASSES.dot)).toBe(true);
        // The look is the class and the dot: aria-current stays on the current page's own link (D96).
        expect(more.hasAttribute('aria-current')).toBe(false);
        expect(nav.querySelectorAll('[aria-current]')).toHaveLength(1);

        resize(nav, 1000);
        expect(more.classList.contains(CLASSES.selected)).toBe(false);
        expect(more.hasAttribute('aria-current')).toBe(false);
        expect(more.querySelector(`.${CLASSES.dot}`)).toBeNull();
    });

    it('pays for More\'s dot when the unit it takes holds the current page', () => {
        // Every unit but Competencies, beside More unselected: 450 + 70 = 520, which fits exactly.
        const control = drawRow(false, 'categorymain');
        arm(control);
        resize(control, 520);
        expect(rowKeys(control)).toEqual(['categorymain', 'settings', 'access', 'restorecourse', 'other']);
        removeObserver();
        installObserver();

        // Competencies holds the current page: More takes it and its dot, 80 wide, so Restore course moves as well.
        const nav = drawRow(false, 'learningplantemplates');
        arm(nav);
        resize(nav, 520);
        expect(rowKeys(nav)).toEqual(['categorymain', 'settings', 'access', 'other']);
        const more = pillOf(group('other'));
        expect(more.classList.contains(CLASSES.selected)).toBe(true);
        expect(more.querySelectorAll(`.${CLASSES.dot}`)).toHaveLength(1);
        expect(more.hasAttribute('aria-current')).toBe(false);
        // The one aria-current of the row is the destination's own link, wherever it now is.
        const current = nav.querySelectorAll('[aria-current]');
        expect(current).toHaveLength(1);
        expect((current[0] as HTMLElement).dataset.key).toBe('learningplantemplates');
    });

    it('never measures while a menu is open, and measures when it closes', () => {
        const nav = drawRow();
        arm(nav);
        resize(nav, 1000);
        const settings = group('settings');
        press(pillOf(settings), 'ArrowDown');

        resize(nav, 400);
        expect(group('other').hidden).toBe(true);
        expect(rowKeys(nav)).toEqual(['categorymain', 'settings', 'access', 'restorecourse', 'competencies']);

        press(settings.querySelector('a') as HTMLElement, 'Escape');
        expect(rowKeys(nav)).toEqual(['categorymain', 'settings', 'access', 'other']);
    });

    it('leaves every unit on the row while the track is not drawn', () => {
        const nav = drawRow();
        arm(nav);
        resize(nav, 0);
        expect(rowKeys(nav)).toEqual(['categorymain', 'settings', 'access', 'restorecourse', 'competencies']);
        expect(group('other').hidden).toBe(true);
    });
});

describe('a pass asked for while a menu is open', () => {
    it('does not run when another menu opens over it, and runs when that one closes', () => {
        const nav = drawRow();
        arm(nav);
        resize(nav, 1000);
        const settings = group('settings');
        const access = group('access');
        press(pillOf(settings), 'ArrowDown');

        // Only Category fits now: User and access would move into More if the pass ran with a menu open.
        resize(nav, 160);
        press(pillOf(access), 'ArrowDown');

        expect(settings.open).toBe(false);
        expect(access.open).toBe(true);
        expect(access.hidden).toBe(false);
        expect(rowKeys(nav)).toEqual(['categorymain', 'settings', 'access', 'restorecourse', 'competencies']);
        expect(document.activeElement).toBe(access.querySelector('[data-key="cohort"]'));

        press(access.querySelector('a') as HTMLElement, 'Escape');
        expect(rowKeys(nav)).toEqual(['categorymain', 'other']);
    });

    it('gives the focus to More when Escape closes a menu whose group the pass then moves there', () => {
        const nav = drawRow();
        arm(nav);
        resize(nav, 1000);
        const settings = group('settings');
        press(pillOf(settings), 'ArrowDown');
        resize(nav, 160);

        press(settings.querySelector('a') as HTMLElement, 'Escape');

        expect(settings.hidden).toBe(true);
        expect(document.activeElement).toBe(pillOf(group('other')));
    });
});

describe('the selected look the server drew on More', () => {
    it('is never cleared, and no dot is added, whatever the row holds', () => {
        const nav = drawRow(true);
        const more = pillOf(group('other'));
        // The server's look: the class and its dot, and no aria-current (D96).
        const serverdot = document.createElement('span');
        serverdot.className = CLASSES.dot;
        more.classList.add(CLASSES.selected);
        more.prepend(serverdot);
        arm(nav);

        resize(nav, 1000);
        expect(more.classList.contains(CLASSES.selected)).toBe(true);
        expect(more.querySelectorAll(`.${CLASSES.dot}`)).toHaveLength(1);

        // Category, the current page, moves into More: the server's look stands and carries no second dot.
        resize(nav, 100);
        expect(more.classList.contains(CLASSES.selected)).toBe(true);
        expect(more.querySelectorAll(`.${CLASSES.dot}`)).toHaveLength(1);
        expect(more.hasAttribute('aria-current')).toBe(false);
    });
});

describe('where a group\'s menu opens', () => {
    /**
     * Put a width on the viewport and a box on a group's menu, as jsdom lays out neither.
     *
     * @param {HTMLDetailsElement} details The group.
     * @param {number} left The menu's left edge.
     * @param {number} right The menu's right edge.
     * @returns {HTMLElement} The group's menu.
     */
    const place = (details: HTMLDetailsElement, left: number, right: number): HTMLElement => {
        Object.defineProperty(document.documentElement, 'clientWidth', {configurable: true, value: 500});
        const menu = details.querySelector('.dim-navpill-menu') as HTMLElement;
        menu.getBoundingClientRect = () => ({left: left, right: right} as DOMRect);
        return menu;
    };

    afterEach(() => {
        delete (document.documentElement as unknown as {clientWidth?: number}).clientWidth;
    });

    it('is end-aligned when it would cross the viewport\'s end, and start-aligned again once closed', () => {
        arm(drawRow());
        const settings = group('settings');
        const menu = place(settings, 450, 600);

        press(pillOf(settings), 'ArrowDown');
        expect(menu.classList.contains(CLASSES.end)).toBe(true);

        press(menu.querySelector('a') as HTMLElement, 'Escape');
        expect(menu.classList.contains(CLASSES.end)).toBe(false);

        menu.getBoundingClientRect = () => ({left: 100, right: 300} as DOMRect);
        press(pillOf(settings), 'ArrowDown');
        expect(menu.classList.contains(CLASSES.end)).toBe(false);
    });

    it('is end-aligned in a right-to-left row when it would cross the viewport\'s start', () => {
        const nav = drawRow();
        nav.style.direction = 'rtl';
        arm(nav);
        const settings = group('settings');
        const menu = place(settings, -40, 200);

        press(pillOf(settings), 'ArrowDown');
        expect(menu.classList.contains(CLASSES.end)).toBe(true);

        press(menu.querySelector('a') as HTMLElement, 'Escape');
        menu.getBoundingClientRect = () => ({left: 10, right: 600} as DOMRect);
        press(pillOf(settings), 'ArrowDown');
        expect(menu.classList.contains(CLASSES.end)).toBe(false);
    });
});

describe('Space on a pill', () => {
    it('is kept from activating it again on its way up, and no other key or element is touched', () => {
        arm(drawRow());
        const up = (target: Element, key: string): KeyboardEvent => {
            const event = new KeyboardEvent('keyup', {key: key, bubbles: true, cancelable: true});
            target.dispatchEvent(event);
            return event;
        };
        const pill = pillOf(group('settings'));
        expect(up(pill, ' ').defaultPrevented).toBe(true);
        expect(up(pill, 'Enter').defaultPrevented).toBe(false);
        expect(up(document.getElementById('after') as Element, ' ').defaultPrevented).toBe(false);
    });
});
