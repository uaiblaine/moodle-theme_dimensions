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
 * The navbar's controls in the user menu on a phone, or in the profile layer where it is armed.
 *
 * Every decision about WHICH controls is the server's (classes/local/phonenav.php): it draws an inert holder of user
 * menu entries, one per control the navbar really carries. This module moves them to the top of the user menu's main
 * panel, or, where amd/src/profilelayer.js has armed the profile layer, into the layer's first group (D75), and then
 * marks the nav with dimensions-phonenav-<key> per entry, by which scss/phonenav.scss hides each control below md. So
 * nothing is hidden before its stand-in is in place, and a page this module never ran on keeps every control. The
 * two modules arm in either order: an entry already in the user menu moves on to the layer when the layer says it is
 * armed, and a layer that never arms leaves the entries in Boost Union's dropdown, which is then the user button's.
 *
 * An entry never repeats an action: it calls the hidden control. The messaging drawer is opened by its own toggle, focus
 * mode by its own form (amd/src/focusmode.js intercepts the submit, as it does for the navbar's button), edit mode by
 * core's switch. A counted entry repeats the hidden control's unread count, read again each time the menu or the layer
 * opens, since core and local_mail update their own badges as messages arrive.
 *
 * @module     theme_dimensions/phonemenu
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';

const SELECTORS = {
    HOLDER: 'template[data-region="dimensions-phonemenu"]',
    MAIN: '#carousel-item-main',
    NAME: '.loggedinas',
    DIVIDER: '.dropdown-divider',
    MENU: '#user-action-menu',
    TOGGLE: '#user-menu-toggle',
    ENTRY: '[data-dimensions-phone]',
    COUNT: '[data-region="dimensions-phonemenu-count"]',
    UNREAD: '[data-region="dimensions-phonemenu-unread"]',
    MENUDIVIDER: '#user-action-menu .dimensions-phonemenu-divider',
    LAYER: '[data-region="dimensions-profile"]',
    LAYERGROUP: '[data-region="dimensions-layer-phone"]',
    LAYERLIST: '[data-region="dimensions-layer-phone-list"]',
};

/** The class amd/src/profilelayer.js writes on the layer once armed, and the events it dispatches on it. */
const LAYER = {
    ARMED: 'dimensions-layer-js',
    ARMEDEVENT: 'theme_dimensions/profilelayer:armed',
    SHOWNEVENT: 'theme_dimensions/profilelayer:shown',
};

/** The hidden controls each entry stands for, direct children of the navbar's #usernavigation. */
const CONTROLS = {
    messages: '#usernavigation > [data-region="popover-region-messages"] .popover-region-toggle',
    mail: '#usernavigation > #local-mail-navbar a',
    focus: '#usernavigation > .dimensions-focus-entry [data-action="dimensions-focus-enter"]',
    edit: '#usernavigation > .editmode-switch-form input[type="checkbox"]',
};

/** Where each counted control keeps its unread count. */
const COUNTS = {
    messages: '#usernavigation > [data-region="popover-region-messages"] [data-region="count-container"]',
    mail: '#usernavigation > #local-mail-navbar .local-mail-navbar-count',
};

/**
 * The number a control's badge shows, or 0 when it shows none.
 *
 * Core hides an empty count with its .hidden class and local_mail with the hidden attribute; either way a badge that
 * is not shown is no count.
 *
 * @param {string} key The entry's key.
 * @returns {number}
 */
const unread = (key) => {
    const badge = COUNTS[key] ? document.querySelector(COUNTS[key]) : null;
    if (!badge || badge.hidden || badge.classList.contains('hidden')) {
        return 0;
    }
    // Core's container holds the digit and a visually hidden sentence beside it; the digit is the aria-hidden one.
    const digit = badge.querySelector('[aria-hidden="true"]') || badge;
    const count = parseInt(digit.textContent.trim(), 10);
    return Number.isNaN(count) ? 0 : count;
};

/**
 * Repeat each counted control's count on its entry: the digit for the eye, the sentence for a screen reader.
 *
 * The entries are wherever they were put, the user menu or the layer; the holder's own are inside a template, which
 * no query reaches.
 *
 * @returns {Promise<void>}
 */
const paintCounts = async() => {
    const entries = Array.from(document.querySelectorAll(SELECTORS.ENTRY)).filter((entry) => entry.querySelector(SELECTORS.COUNT));
    await Promise.all(entries.map(async(entry) => {
        const count = unread(entry.dataset.dimensionsPhone);
        const badge = entry.querySelector(SELECTORS.COUNT);
        const sentence = entry.querySelector(SELECTORS.UNREAD);
        badge.hidden = count === 0;
        badge.textContent = count === 0 ? '' : String(count);
        sentence.textContent = count === 0 ? '' : await getString('navbarphone_unread', 'theme_dimensions', count);
    }));
};

/**
 * Run the action an entry stands for, on the hidden control itself.
 *
 * @param {Event} event The click on an action entry.
 */
const onAction = (event) => {
    const entry = event.target.closest(SELECTORS.ENTRY);
    const control = entry ? document.querySelector(CONTROLS[entry.dataset.dimensionsPhone]) : null;
    if (!control) {
        return;
    }
    event.preventDefault();
    if (entry.dataset.dimensionsPhone === 'focus') {
        control.form.requestSubmit(control);
        return;
    }
    control.click();
};

/**
 * Open the messaging drawer in place, as its own toggle does; the entry's address is the fallback.
 *
 * Only once this click has finished: core_message/message_drawer closes the drawer on any activation on the document
 * outside the drawer and its toggle, and this click, still on its way up from the entry, is one. Opened at once, the
 * drawer would be closed again by the same click before it paints.
 *
 * @param {Event} event The click on the messages entry.
 */
const onMessages = (event) => {
    const control = document.querySelector(CONTROLS.messages);
    if (!control) {
        return;
    }
    event.preventDefault();
    window.setTimeout(() => control.click());
};

/**
 * Put the entries at the top of the user menu's main panel, after the name block the theme switches on for phones, so
 * the menu still opens on who is logged in.
 *
 * @param {HTMLElement} main The user menu's main panel.
 * @param {DocumentFragment} fragment The holder's entries and their divider.
 */
const intoMenu = (main, fragment) => {
    const name = main.querySelector(SELECTORS.NAME);
    const after = name ? name.nextElementSibling : null;
    if (after && after.matches(SELECTORS.DIVIDER)) {
        after.after(fragment);
    } else {
        main.prepend(fragment);
    }
};

/**
 * Make the entries the first group of the armed profile layer: rows of the layer's list, out of the user menu.
 *
 * Each entry keeps its listeners, its data and its count; it loses the menu's item class, role and roving tab stop,
 * which the layer's lists of links do not have. The group is shown below md only (scss/profilelayer.scss).
 *
 * @param {HTMLElement} layer The armed layer.
 * @param {HTMLElement[]} entries The entries.
 * @returns {boolean} Whether the layer took them: false when it has no group to hold them.
 */
const intoLayer = (layer, entries) => {
    const group = layer.querySelector(SELECTORS.LAYERGROUP);
    const list = group ? group.querySelector(SELECTORS.LAYERLIST) : null;
    if (!list) {
        return false;
    }
    entries.forEach((entry) => {
        entry.classList.remove('dropdown-item');
        entry.classList.add('dimensions-layer-row');
        entry.removeAttribute('role');
        entry.removeAttribute('tabindex');
        const row = document.createElement('li');
        row.append(entry);
        list.append(row);
    });
    document.querySelectorAll(SELECTORS.MENUDIVIDER).forEach((divider) => divider.remove());
    group.hidden = false;
    return true;
};

/**
 * Move the server's entries into the user menu or the armed layer, once, and arm them.
 */
export const init = () => {
    const holder = document.querySelector(SELECTORS.HOLDER);
    const main = document.querySelector(SELECTORS.MAIN);
    const menu = document.querySelector(SELECTORS.MENU);
    if (!holder || !main || !menu) {
        return;
    }
    const fragment = holder.content.cloneNode(true);
    holder.remove();
    const entries = Array.from(fragment.querySelectorAll(SELECTORS.ENTRY));
    entries.forEach((entry) => {
        if (entry.dataset.action === 'dimensions-phonemenu-action') {
            entry.addEventListener('click', onAction);
        } else if (entry.dataset.dimensionsPhone === 'messages') {
            entry.addEventListener('click', onMessages);
        }
    });

    const layer = document.querySelector(SELECTORS.LAYER);
    if (!layer || !layer.classList.contains(LAYER.ARMED) || !intoLayer(layer, entries)) {
        intoMenu(main, fragment);
        if (layer) {
            // The layer's module may arm after this one: the entries follow it there.
            layer.addEventListener(LAYER.ARMEDEVENT, () => intoLayer(layer, entries), {once: true});
        }
    }
    // Only now that each entry is in the menu or the layer may the stylesheet hide the control it stands for.
    const nav = menu.closest('.navbar');
    entries.forEach((entry) => {
        nav.classList.add('dimensions-phonenav-' + entry.dataset.dimensionsPhone);
    });

    const toggle = document.querySelector(SELECTORS.TOGGLE);
    const dropdown = toggle ? toggle.closest('.dropdown') : null;
    if (dropdown) {
        dropdown.addEventListener('show.bs.dropdown', () => {
            paintCounts().catch(() => null);
        });
    }
    document.addEventListener(LAYER.SHOWNEVENT, () => {
        paintCounts().catch(() => null);
    });
    paintCounts().catch(() => null);
};
