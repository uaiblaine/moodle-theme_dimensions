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
 * Focus mode switched in place, and the width control of its bar.
 *
 * The page carries both skins: Boost Union's layout untouched, and the bar and the foot of the
 * content, which the stylesheet shows only under the body class. The server decides the first
 * paint (the body class, the header actions in the bar or in the page header), so there is no
 * flash of the wrong mode and the page is correct with JavaScript switched off, where the entry
 * and exit forms post to focus.php and reload. This module turns those two forms into a switch in
 * place: it moves the body class, the header actions and the focus, announces the new mode, and
 * records the choice through theme_dimensions_set_focus_mode for the next page of the course.
 *
 * The header actions are moved, never copied: the manual completion toggle's listener is delegated
 * from the document (course/amd/src/manual_completion_toggle.js), so the one node works wherever it
 * lands, and a hidden second copy would go stale the moment the visible one was pressed.
 *
 * Guests are the reason for the canpersist flag of the width control. They get focus mode - the
 * choice lives in the PHP session, which a guest has - but core refuses to write a user preference
 * for them, so for a guest the width is toggled for that page view and no request is issued.
 *
 * @module     theme_dimensions/focusmode
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {getString} from 'core/str';
import {setUserPreference} from 'core_user/repository';

const SELECTORS = {
    bar: '.dimensions-focusbar',
    width: '[data-action="dimensions-focus-width"]',
    expandicon: '[data-region="dimensions-focus-width-expand"]',
    contracticon: '[data-region="dimensions-focus-width-contract"]',
    enter: '[data-action="dimensions-focus-enter"]',
    leave: '[data-action="dimensions-focus-leave"]',
    barexit: [
        '.dimensions-focusbar [data-action="dimensions-focus-leave"]',
        '.dimensions-focusbar [data-action="dimensions-focus-exit"]',
    ].join(', '),
    status: '[data-region="dimensions-focus-status"]',
    baractions: '[data-region="dimensions-focus-headeractions"]',
    headeractions: '#page-header [data-region="header-actions-container"]',
    settingsmenu: '#region-main-settings-menu',
    openmenus: '[data-bs-toggle="dropdown"][aria-expanded="true"]',
    tooltiptriggers: '[aria-describedby]',
    maininner: '#page.drawers .main-inner',
};

/** @type {String} The body class of focus mode, the one the whole stylesheet is scoped under. */
const FOCUSCLASS = 'dimensions-focusmode';

/** @type {String} The body class which lifts the reading column to the content maximum. */
const EXPANDEDCLASS = 'dimensions-focusmode-expanded';

/** @type {String} Bootstrap's own display utility, used to swap the two glyphs. */
const HIDDENCLASS = 'd-none';

/** @type {String} The wrapper class of a header action in the bar. */
const BARACTIONCLASS = 'dimensions-focusbar-action';

/** @type {String[]} The wrapper classes of a header action in core's page header (core/full_header). */
const HEADERACTIONCLASSES = ['header-action', 'ms-2'];

/** @type {String} The prefix of the tab's own memory of the mode, keyed by course, for the back-forward cache. */
const STORAGEPREFIX = 'theme_dimensions_focus_';

/**
 * The state of the page, set by init().
 *
 * @type {{config: Object, active: Boolean, expanded: Boolean}}
 */
const state = {
    config: {},
    active: false,
    expanded: false,
};

/**
 * Paint one width state onto the body and the button.
 *
 * The button's accessible name never changes: the server renders it once, and aria-pressed
 * carries the state, which is how a toggle button is announced ("Wide reading column, pressed").
 * A label that also flipped would be read as a second, contradictory state. The expanded class is
 * on the body only while focus mode is: it lifts the column of the focus skin alone.
 *
 * @return {void}
 */
const paintWidth = () => {
    document.body.classList.toggle(EXPANDEDCLASS, state.active && state.expanded);

    const button = document.querySelector(SELECTORS.bar + ' ' + SELECTORS.width);
    if (!button) {
        return;
    }
    button.setAttribute('aria-pressed', state.expanded ? 'true' : 'false');
    const expandicon = button.querySelector(SELECTORS.expandicon);
    if (expandicon) {
        expandicon.classList.toggle(HIDDENCLASS, state.expanded);
    }
    const contracticon = button.querySelector(SELECTORS.contracticon);
    if (contracticon) {
        contracticon.classList.toggle(HIDDENCLASS, !state.expanded);
    }
};

/**
 * Close what is open inside the chrome about to be hidden: dropdowns, tooltips, the focused control's menu.
 *
 * A menu left open under an element that stops being displayed stays open over the page, or comes
 * back open on the way back. Escape is what Bootstrap's own data API and core's popover regions
 * close on, so it is sent rather than any instance being reached for.
 *
 * @return {void}
 */
const closeMenus = () => {
    const escape = () => new KeyboardEvent('keydown', {key: 'Escape', bubbles: true});
    if (document.activeElement && document.activeElement !== document.body) {
        document.activeElement.dispatchEvent(escape());
    }
    document.querySelectorAll(SELECTORS.openmenus).forEach((toggle) => toggle.dispatchEvent(escape()));
    document.querySelectorAll(SELECTORS.tooltiptriggers).forEach((trigger) => {
        const described = document.getElementById(trigger.getAttribute('aria-describedby'));
        if (described && described.classList.contains('tooltip')) {
            trigger.dispatchEvent(new Event('mouseleave'));
            trigger.dispatchEvent(new FocusEvent('focusout'));
        }
    });
};

/**
 * Move the header actions to the skin about to be shown.
 *
 * Into the bar: every action of core's page header but the region main settings menu, which
 * full_header() adds itself and focus mode hides with the rest of the chrome. Back: every action
 * the bar holds, ahead of whatever the header kept, which is the order core printed them in.
 *
 * @param {Boolean} intobar True to move them into the bar.
 * @return {void}
 */
const moveHeaderActions = (intobar) => {
    const bar = document.querySelector(SELECTORS.baractions);
    const header = document.querySelector(SELECTORS.headeractions);
    if (!bar || !header) {
        return;
    }

    if (intobar) {
        Array.from(header.children).forEach((action) => {
            if (action.querySelector(SELECTORS.settingsmenu)) {
                return;
            }
            action.classList.remove(...HEADERACTIONCLASSES);
            action.classList.add(BARACTIONCLASS);
            bar.appendChild(action);
        });
        return;
    }

    const anchor = header.firstChild;
    Array.from(bar.children).forEach((action) => {
        action.classList.remove(BARACTIONCLASS);
        action.classList.add(...HEADERACTIONCLASSES);
        header.insertBefore(action, anchor);
    });
};

/**
 * Remember the mode for this course in the tab, for a page the back-forward cache restores.
 *
 * Wrapped in try and catch: storage can throw (a private window, blocked site data), and the
 * memory is a convenience the page is correct without.
 *
 * @param {Boolean} active The mode.
 * @return {void}
 */
const remember = (active) => {
    try {
        window.sessionStorage.setItem(STORAGEPREFIX + state.config.courseid, active ? '1' : '0');
    } catch {
        // Nothing to do: the next page asks the server anyway.
    }
};

/**
 * The mode this tab last chose for the course, or null when it does not know.
 *
 * @return {Boolean|null}
 */
const remembered = () => {
    try {
        const value = window.sessionStorage.getItem(STORAGEPREFIX + state.config.courseid);
        return value === null ? null : value === '1';
    } catch {
        return null;
    }
};

/**
 * Tell the media in the page that the reading column changed width.
 *
 * Video players, H5P and iframes size themselves on resize. Sent once at once and once when the
 * column's own transition ends, because the width moves over the motion token's duration.
 *
 * @return {void}
 */
const announceResize = () => {
    window.dispatchEvent(new Event('resize'));
    const inner = document.querySelector(SELECTORS.maininner);
    if (inner) {
        inner.addEventListener('transitionend', () => window.dispatchEvent(new Event('resize')), {once: true});
    }
};

/**
 * Show one skin: the body class, the header actions, the width, and nothing recorded.
 *
 * @param {Boolean} active True for focus mode.
 * @return {void}
 */
const paint = (active) => {
    closeMenus();
    moveHeaderActions(active);
    state.active = active;
    document.body.classList.toggle(FOCUSCLASS, active);
    paintWidth();
    announceResize();
};

/**
 * Whether a control is on the screen and can take the focus.
 *
 * The way in is drawn more than once (the navbar's button, the context bar's, a phone's user menu entry), and on a
 * phone the navbar's own is hidden in place, so the first one in the document is not always one the focus can reach.
 *
 * @param {HTMLElement} element A control.
 * @return {Boolean}
 */
const shown = (element) => element.getClientRects().length > 0 && window.getComputedStyle(element).visibility !== 'hidden';

/**
 * Move the keyboard focus to the counterpart of the control that was pressed, and say the new mode.
 *
 * The pressed control has just stopped being displayed with the chrome it lives in, so the focus
 * would otherwise fall to the body. The status line is outside both skins; a string that cannot be
 * fetched leaves it unsaid, and never puts the page back.
 *
 * @param {Boolean} active The mode now shown.
 * @return {void}
 */
const settleFocus = (active) => {
    const target = Array.from(document.querySelectorAll(active ? SELECTORS.barexit : SELECTORS.enter)).find(shown);
    if (target) {
        target.focus();
    }
    const status = document.querySelector(SELECTORS.status);
    if (status) {
        getString(active ? 'focusmode_status_on' : 'focusmode_status_off', 'theme_dimensions')
            .then((text) => {
                status.textContent = text;
                return text;
            })
            .catch(() => null);
    }
};

/**
 * Switch the page, then record the choice for the course; put the page back if the server refused.
 *
 * core/pending is held while the request is in flight, so Behat - and a reader who follows a link
 * at once - wait for the choice to be recorded before the next page asks for it.
 *
 * @param {Boolean} active True to enter focus mode, false to leave it.
 * @return {Promise}
 */
const toggle = async(active) => {
    const pending = new Pending('theme_dimensions/focusmode:toggle');
    paint(active);
    remember(active);
    settleFocus(active);
    try {
        await Ajax.call([{
            methodname: 'theme_dimensions_set_focus_mode',
            args: {cmid: state.config.cmid, active: active},
        }])[0];
    } catch (error) {
        paint(!active);
        remember(!active);
        settleFocus(!active);
        Notification.exception(error);
    } finally {
        pending.resolve();
    }
};

/**
 * Intercept the submit of one of the two forms and switch in place instead.
 *
 * Delegated from the document, so it holds for the entry button the navbar draws and the exit the
 * bar draws whichever skin the page opened in. The plain exit link of a single activity course that
 * switched focus mode on is not a form, and leaves the course as it says.
 *
 * @param {Event} event The submit event.
 * @return {void}
 */
const onSubmit = (event) => {
    const submitter = event.submitter || event.target.querySelector('[type="submit"]');
    if (!submitter) {
        return;
    }
    if (submitter.matches(SELECTORS.enter)) {
        event.preventDefault();
        toggle(true);
    } else if (submitter.matches(SELECTORS.leave)) {
        event.preventDefault();
        toggle(false);
    }
};

/**
 * Bring a page the back-forward cache restored to the mode this tab last chose for the course.
 *
 * The page comes back exactly as it was left, and the reader may have switched the course since on
 * another page of it; the server already holds that choice, so this only paints and records nothing.
 *
 * @param {PageTransitionEvent} event The pageshow event.
 * @return {void}
 */
const onPageShow = (event) => {
    if (!event.persisted) {
        return;
    }
    const chosen = remembered();
    if (chosen !== null && chosen !== state.active && !(state.active && state.config.leavescourse)) {
        paint(chosen);
    }
};

/**
 * Arm the switch and the width control.
 *
 * @param {Object} config The state the renderer resolved: active, cmid, courseid, leavescourse,
 *     expanded, canpersist, preference.
 * @return {void}
 */
export const init = (config) => {
    state.config = config;
    state.active = Boolean(config.active);
    state.expanded = Boolean(config.expanded);
    remember(state.active);
    paintWidth();

    document.addEventListener('submit', onSubmit);
    window.addEventListener('pageshow', onPageShow);

    const button = document.querySelector(SELECTORS.bar + ' ' + SELECTORS.width);
    if (button) {
        button.addEventListener('click', () => {
            state.expanded = !state.expanded;
            paintWidth();
            announceResize();
            if (config.canpersist) {
                setUserPreference(config.preference, state.expanded).catch(Notification.exception);
            }
        });
    }
};
