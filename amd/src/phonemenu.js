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
 * The navbar's controls in the user menu on a phone.
 *
 * Every decision about WHICH controls is the server's (classes/local/phonenav.php): it draws an inert holder of user
 * menu entries, one per control the navbar really carries. This module moves them to the top of the user menu's main
 * panel and then marks the nav with dimensions-phonenav-<key> per entry, by which scss/phonenav.scss hides each control
 * below md. So nothing is hidden before its stand-in is in place, and a page this module never ran on keeps every
 * control.
 *
 * An entry never repeats an action: it calls the hidden control. The messaging drawer is opened by its own toggle, focus
 * mode by its own form (amd/src/focusmode.js intercepts the submit, as it does for the navbar's button), edit mode by
 * core's switch. A counted entry repeats the hidden control's unread count, read again each time the menu opens, since
 * core and local_mail update their own badges as messages arrive.
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
 * @param {HTMLElement} menu The user menu.
 * @returns {Promise<void>}
 */
const paintCounts = async(menu) => {
    const entries = Array.from(menu.querySelectorAll(SELECTORS.ENTRY)).filter((entry) => entry.querySelector(SELECTORS.COUNT));
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
 * drawer was closed again by the same click before it was ever painted.
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
 * Move the server's entries to the top of the user menu, once, and arm them.
 */
export const init = () => {
    const holder = document.querySelector(SELECTORS.HOLDER);
    const main = document.querySelector(SELECTORS.MAIN);
    const menu = document.querySelector(SELECTORS.MENU);
    if (!holder || !main || !menu) {
        return;
    }
    // After the name block the theme switches on for phones, so the menu still opens on who is logged in.
    const name = main.querySelector(SELECTORS.NAME);
    const after = name ? name.nextElementSibling : null;
    if (after && after.matches(SELECTORS.DIVIDER)) {
        after.after(holder.content.cloneNode(true));
    } else {
        main.prepend(holder.content.cloneNode(true));
    }
    holder.remove();
    // Only now that each entry is in the menu may the stylesheet hide the control it stands for.
    const nav = menu.closest('.navbar');
    main.querySelectorAll(SELECTORS.ENTRY).forEach((entry) => {
        nav.classList.add('dimensions-phonenav-' + entry.dataset.dimensionsPhone);
    });

    main.querySelectorAll('[data-action="dimensions-phonemenu-action"]').forEach((entry) => {
        entry.addEventListener('click', onAction);
    });
    const messages = main.querySelector('[data-dimensions-phone="messages"]');
    if (messages) {
        messages.addEventListener('click', onMessages);
    }

    const toggle = document.querySelector(SELECTORS.TOGGLE);
    const dropdown = toggle ? toggle.closest('.dropdown') : null;
    if (dropdown) {
        dropdown.addEventListener('show.bs.dropdown', () => {
            paintCounts(menu).catch(() => null);
        });
    }
    paintCounts(menu).catch(() => null);
};
