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
 * The profile layer of a logged-in account: the user button opens it, a modal dialog with sub-views.
 *
 * The server prints the layer once per page, closed (templates/profile_layer.mustache), and arms this module only
 * where it printed it. Boost Union's user menu dropdown stays on the page as the fallback: until this module has
 * found the layer, the user button is Bootstrap's dropdown toggle. Armed, the module takes the button over - it
 * removes data-bs-toggle, so Bootstrap's data API no longer answers it, and says the button opens a dialog - and
 * answers the press itself.
 *
 * Open, the layer holds the focus (core/local/aria/focuslock), starting on the main view's close button. A row that
 * opens a sub-view shows it in place of the main view and moves the focus to its back button; back and Escape return
 * to the main view with the focus on that row. Escape closes a sub-view first and the layer only from its main view;
 * the close buttons and a press on the backdrop close it from anywhere; closing gives the focus back to the user
 * button. The arrow keys, Home and End move among a sub-view's items and the colour mode's options.
 *
 * The colour mode control carries core's hooks, so theme_boost/colourmode applies the mode and marks the chosen
 * option in every control of the page, this one included; the module initialises it where the layer carries the
 * control, and writes CLASSES.MODES on the body, by which scss/post.scss hides the navbar's own colour mode menu.
 * The Look options are theme_dimensions/flavourchoice's, delegated from the document.
 *
 * The motion is the stylesheet's (an animation on the open layer, under prefers-reduced-motion: no-preference); a
 * Pending token is held until it ends, so Behat never presses a row that is still sliding in.
 *
 * @module     theme_dimensions/profilelayer
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Dropdown} from 'bootstrap';
import {init as initColourMode} from 'theme_boost/colourmode';
import {trapFocus, untrapFocus} from 'core/local/aria/focuslock';
import Drawers from 'theme_boost/drawers';
import Pending from 'core/pending';

const SELECTORS = {
    LAYER: '[data-region="dimensions-profile"]',
    BACKDROP: '[data-region="dimensions-layer-backdrop"]',
    TOGGLE: '#user-menu-toggle',
    VIEW: '[data-view]',
    MAIN: '[data-view="main"]',
    TITLE: 'h2[id]',
    OPEN: '[data-action="dimensions-layer-open"]',
    BACK: '[data-action="dimensions-layer-back"]',
    CLOSE: '[data-action="dimensions-layer-close"]',
    MENU: '[role="menu"]',
    ITEM: '[role="menuitemradio"], [role="menuitem"]',
    MODES: '[role="radiogroup"][data-region="colourmode-menu"]',
    MODE: '[data-action="set-colourmode"]',
    PHONE: '[data-dimensions-phone]',
};

const CLASSES = {
    // On the layer once armed: amd/src/phonemenu.js reads it to put the phone's entries in the layer.
    ARMED: 'dimensions-layer-js',
    // On the body while an armed layer carries the colour mode control: scss/post.scss hides the navbar's menu by it.
    MODES: 'dimensions-profilelayer-modes',
};

/** The events the layer dispatches on itself; they bubble. amd/src/phonemenu.js listens for ARMED and SHOWN. */
export const EVENTS = {
    ARMED: 'theme_dimensions/profilelayer:armed',
    SHOWN: 'theme_dimensions/profilelayer:shown',
    HIDDEN: 'theme_dimensions/profilelayer:hidden',
};

/** The id core's drawer template gives Boost's primary drawer, the navigation drawer's home. */
const PRIMARY = 'theme_boost-drawers-primary';

/** The longest the opening motion may hold Behat, in milliseconds, should its animationend never come. */
const SETTLE = 600;

/** The keys that move among a sub-view's items, and where each one moves. */
const ITEMSTEPS = {
    ArrowDown: 1,
    ArrowUp: -1,
};

/** The keys that move the colour mode's choice, as a radio group's do. */
const MODESTEPS = {
    ArrowRight: 1,
    ArrowDown: 1,
    ArrowLeft: -1,
    ArrowUp: -1,
};

/** The page's one layer, its backdrop and the user button, once armed; whether it is open; the row of the sub-view. */
const state = {
    layer: null,
    backdrop: null,
    toggle: null,
    open: false,
    opener: null,
};

/**
 * Every view of the layer, the main one first.
 *
 * @returns {HTMLElement[]}
 */
const views = () => Array.from(state.layer.querySelectorAll(SELECTORS.VIEW));

/**
 * The view being shown.
 *
 * @returns {HTMLElement|undefined}
 */
const current = () => views().find((view) => !view.hidden);

/**
 * Show one view and hide every other one, and label the dialog by the shown view's title.
 *
 * @param {HTMLElement} view The view to show.
 */
const showView = (view) => {
    views().forEach((candidate) => {
        candidate.hidden = candidate !== view;
    });
    const title = view.querySelector(SELECTORS.TITLE);
    if (title) {
        state.layer.setAttribute('aria-labelledby', title.id);
    }
};

/**
 * Hold a Pending token while the opening motion runs, so Behat reads the layer only once it stands still.
 *
 * @param {HTMLElement} layer The layer, just shown.
 */
const settle = (layer) => {
    const pending = new Pending('theme_dimensions/profilelayer:open');
    if (window.getComputedStyle(layer).animationName === 'none') {
        pending.resolve();
        return;
    }
    let done = false;
    const finish = () => {
        if (!done) {
            done = true;
            pending.resolve();
        }
    };
    layer.addEventListener('animationend', finish, {once: true});
    window.setTimeout(finish, SETTLE);
};

/**
 * Open the layer on its main view, hold the focus in it and put it on the main view's close button.
 */
const open = () => {
    if (state.open) {
        return;
    }
    state.open = true;
    // The navigation drawer and the layer are never open together.
    const primary = document.getElementById(PRIMARY);
    if (primary && primary.classList.contains('show')) {
        Drawers.getDrawerInstanceForNode(primary).closeDrawer({focusOnOpenButton: false});
    }
    const main = state.layer.querySelector(SELECTORS.MAIN);
    showView(main);
    state.backdrop.hidden = false;
    state.layer.hidden = false;
    state.toggle.setAttribute('aria-expanded', 'true');
    settle(state.layer);
    trapFocus(state.layer);
    main.querySelector(SELECTORS.CLOSE)?.focus();
    state.layer.dispatchEvent(new CustomEvent(EVENTS.SHOWN, {bubbles: true}));
};

/**
 * Close the layer, put it back on its main view for the next opening and give the focus back to the user button.
 *
 * The focus is released before it is given back: while it is held, focuslock would pull it into the layer again.
 *
 * @param {boolean} returnFocus Whether the focus goes back to the user button; not when another surface takes it.
 */
const close = (returnFocus = true) => {
    if (!state.open) {
        return;
    }
    state.open = false;
    state.opener = null;
    untrapFocus();
    state.layer.hidden = true;
    state.backdrop.hidden = true;
    showView(state.layer.querySelector(SELECTORS.MAIN));
    state.toggle.setAttribute('aria-expanded', 'false');
    if (returnFocus) {
        state.toggle.focus();
    }
    state.layer.dispatchEvent(new CustomEvent(EVENTS.HIDDEN, {bubbles: true}));
};

/**
 * Show the sub-view a row opens, and move the focus to its back button.
 *
 * @param {HTMLElement} row The row that was pressed.
 */
const enter = (row) => {
    const view = document.getElementById(row.getAttribute('aria-controls'));
    if (!view || !state.layer.contains(view)) {
        return;
    }
    state.opener = row;
    showView(view);
    view.querySelector(SELECTORS.BACK)?.focus();
};

/**
 * Go back to the main view, and give the focus back to the row that opened the sub-view being left.
 */
const leave = () => {
    showView(state.layer.querySelector(SELECTORS.MAIN));
    const opener = state.opener;
    state.opener = null;
    if (opener) {
        opener.focus();
    }
};

/**
 * Where a key moves the focus among a list of controls.
 *
 * @param {String} key The key pressed.
 * @param {Number} index The focused control's place.
 * @param {Number} count How many controls there are.
 * @param {Object} steps The keys that step, and by how much.
 * @returns {Number} The place to move to, or -1 for a key that moves nothing.
 */
const step = (key, index, count, steps) => {
    if (key in steps) {
        return (index + steps[key] + count) % count;
    }
    if (key === 'Home') {
        return 0;
    }
    if (key === 'End') {
        return count - 1;
    }
    return -1;
};

/**
 * Move among a sub-view's items with the arrows, Home and End, and choose with Space as Enter does.
 *
 * @param {KeyboardEvent} event The key press.
 * @returns {boolean} Whether the key was the menu's.
 */
const stepItem = (event) => {
    const item = event.target.closest(SELECTORS.ITEM);
    const menu = item ? item.closest(SELECTORS.MENU) : null;
    if (!menu) {
        return false;
    }
    if (event.key === ' ') {
        item.click();
        return true;
    }
    const items = Array.from(menu.querySelectorAll(SELECTORS.ITEM));
    const next = step(event.key, items.indexOf(item), items.length, ITEMSTEPS);
    if (next < 0) {
        return false;
    }
    items[next].focus();
    return true;
};

/**
 * Move the colour mode's choice with the arrows, Home and End, by pressing core's option, which applies the mode.
 *
 * @param {KeyboardEvent} event The key press.
 * @returns {boolean} Whether the key was the group's.
 */
const stepMode = (event) => {
    const option = event.target.closest(SELECTORS.MODE);
    const group = option ? option.closest(SELECTORS.MODES) : null;
    if (!group) {
        return false;
    }
    const options = Array.from(group.querySelectorAll(SELECTORS.MODE));
    const next = step(event.key, options.indexOf(option), options.length, MODESTEPS);
    if (next < 0) {
        return false;
    }
    options[next].focus();
    options[next].click();
    return true;
};

/**
 * Keep one option of the colour mode in the tab order: the chosen one.
 *
 * @param {HTMLElement} option The option chosen.
 */
const rove = (option) => {
    const group = option.closest(SELECTORS.MODES);
    if (!group) {
        return;
    }
    group.querySelectorAll(SELECTORS.MODE).forEach((candidate) => {
        candidate.tabIndex = candidate === option ? 0 : -1;
    });
};

/**
 * Answer a press inside the layer: a close button, a back button, a row that opens a sub-view, a colour mode option,
 * or a phone entry, whose action belongs to a surface the layer would otherwise lie over.
 *
 * A Look option is left to theme_dimensions/flavourchoice and a link to the browser.
 *
 * @param {MouseEvent} event The click.
 */
const onClick = (event) => {
    if (event.target.closest(SELECTORS.CLOSE)) {
        event.preventDefault();
        close();
        return;
    }
    if (event.target.closest(SELECTORS.BACK)) {
        event.preventDefault();
        leave();
        return;
    }
    const row = event.target.closest(SELECTORS.OPEN);
    if (row) {
        event.preventDefault();
        enter(row);
        return;
    }
    const option = event.target.closest(SELECTORS.MODE);
    if (option) {
        rove(option);
        return;
    }
    if (event.target.closest(SELECTORS.PHONE)) {
        close();
    }
};

/**
 * Answer a key inside the layer: Escape closes a sub-view first, then the layer; the arrows move in a list.
 *
 * Escape is the layer's alone while it is open, so it is not handed on.
 *
 * @param {KeyboardEvent} event The key press.
 */
const onKey = (event) => {
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        if (current() !== state.layer.querySelector(SELECTORS.MAIN)) {
            leave();
        } else {
            close();
        }
        return;
    }
    if (stepItem(event) || stepMode(event)) {
        event.preventDefault();
    }
};

/**
 * Answer the user button: it opens the layer, or closes it if it were open.
 *
 * @param {MouseEvent} event The click.
 */
const onToggle = (event) => {
    event.preventDefault();
    if (state.open) {
        close();
    } else {
        open();
    }
};

/**
 * Space presses the user button, as on a button: it is a link with role="button", which Space would scroll past.
 *
 * @param {KeyboardEvent} event The key press.
 */
const onToggleKey = (event) => {
    if (event.key === ' ') {
        onToggle(event);
    }
};

/**
 * Take the user button over from Boost Union's dropdown: Bootstrap's data API stops answering it, and it says it
 * opens the layer's dialog.
 *
 * A dropdown already opened before the module ran is closed and forgotten first.
 *
 * @param {HTMLElement} toggle The user button.
 * @param {HTMLElement} layer The layer.
 */
const takeOver = (toggle, layer) => {
    const dropdown = Dropdown.getInstance(toggle);
    if (dropdown) {
        dropdown.hide();
        dropdown.dispose();
    }
    toggle.removeAttribute('data-bs-toggle');
    toggle.setAttribute('aria-haspopup', 'dialog');
    toggle.setAttribute('aria-controls', layer.id);
    toggle.setAttribute('aria-expanded', 'false');
    toggle.addEventListener('click', onToggle);
    toggle.addEventListener('keydown', onToggleKey);
};

/**
 * Arm the page's profile layer, once.
 *
 * Nothing is taken over unless the layer, its backdrop and the user button are all on the page: without them the
 * user button stays Boost Union's dropdown toggle.
 */
export const init = () => {
    const layer = document.querySelector(SELECTORS.LAYER);
    const backdrop = document.querySelector(SELECTORS.BACKDROP);
    const toggle = document.querySelector(SELECTORS.TOGGLE);
    if (!layer || !backdrop || !toggle || layer.classList.contains(CLASSES.ARMED)) {
        return;
    }
    state.layer = layer;
    state.backdrop = backdrop;
    state.toggle = toggle;

    takeOver(toggle, layer);
    layer.addEventListener('click', onClick);
    layer.addEventListener('keydown', onKey);
    backdrop.addEventListener('click', () => close());
    // Another drawer opening takes the screen: the layer goes without taking the focus back.
    document.addEventListener(Drawers.eventTypes.drawerShow, () => close(false));

    const modes = layer.querySelector(SELECTORS.MODES);
    if (modes) {
        initColourMode();
        const chosen = modes.querySelector(`${SELECTORS.MODE}[aria-checked="true"]`);
        if (chosen) {
            rove(chosen);
        }
        document.body.classList.add(CLASSES.MODES);
    }

    layer.classList.add(CLASSES.ARMED);
    layer.dispatchEvent(new CustomEvent(EVENTS.ARMED, {bubbles: true}));
};
