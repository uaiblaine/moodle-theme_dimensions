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
 * The mobile drawer of a logged-in account: sliding sub-panels and the summary loaded when it is shown.
 *
 * The server draws every panel flat (templates/drawer_nav.mustache), each row with children a link to
 * its panel's anchor, so the drawer works without this module. Armed, the panels become layers: one is
 * current, every other one is inert and off-screen, an opener moves the focus to the new panel's back
 * control and the way back returns it to the row that opened it, and Escape inside a sub-panel goes
 * back one level. Escape anywhere else in Boost's primary drawer closes it through Boost's own drawers
 * module, which gives the focus back to the menu button (D71): Boost listens for no key itself. The
 * motion is the stylesheet's, under prefers-reduced-motion: no-preference.
 *
 * The "Continue where you left off" card is asked of the server once per page, the first time Boost's
 * primary drawer is shown, and never on page load: it costs statements the page itself never pays. A
 * failure takes the card away quietly.
 *
 * @module     theme_dimensions/drawer
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Drawers from 'theme_boost/drawers';
import Pending from 'core/pending';

/** The id core's drawer template gives Boost's primary drawer, the one this drawer lives in. */
const DRAWER = 'theme_boost-drawers-primary';

/** The web service of the deferred summary. */
const SUMMARY = 'theme_dimensions_get_drawer_summary';

const SELECTORS = {
    PANEL: '[data-region="dimensions-drawer-panel"]',
    OPEN: '[data-action="dimensions-drawer-open"]',
    BACK: '[data-action="dimensions-drawer-back"]',
    RESUME: '[data-region="dimensions-drawer-resume"]',
    RESUMELINK: '[data-region="dimensions-drawer-resume-link"]',
    RESUMETITLE: '[data-region="dimensions-drawer-resume-title"]',
    RESUMESUB: '[data-region="dimensions-drawer-resume-sub"]',
    RESUMEBAR: '[data-region="dimensions-drawer-resume-bar"]',
};

/** The class Boost's drawers module writes on a drawer while it is shown. */
const SHOWN = 'show';

/** The rows that opened the panels on the way down, innermost last. */
const openers = [];

/**
 * Every panel of the drawer, root first.
 *
 * @param {HTMLElement} root The drawer.
 * @returns {HTMLElement[]}
 */
const panels = (root) => Array.from(root.querySelectorAll(SELECTORS.PANEL));

/**
 * The panel being shown.
 *
 * @param {HTMLElement} root The drawer.
 * @returns {HTMLElement|undefined}
 */
const current = (root) => panels(root).find((panel) => panel.dataset.state === 'current');

/**
 * Show one panel and put every other one away: the ones on its way up to the root to the left, the
 * rest to the right, and all of them inert, so neither the keyboard nor a screen reader reaches a
 * panel nobody can see.
 *
 * @param {HTMLElement} root The drawer.
 * @param {HTMLElement} panel The panel to show.
 */
const show = (root, panel) => {
    const above = new Set();
    let parent = panel.dataset.parent ? document.getElementById(panel.dataset.parent) : null;
    while (parent) {
        above.add(parent);
        parent = parent.dataset.parent ? document.getElementById(parent.dataset.parent) : null;
    }
    panels(root).forEach((candidate) => {
        const hidden = candidate !== panel;
        candidate.toggleAttribute('inert', hidden);
        if (hidden) {
            candidate.dataset.state = above.has(candidate) ? 'before' : 'after';
        } else {
            candidate.dataset.state = 'current';
        }
    });
    // A long panel left scrolled must not open the next one half way down.
    const scroller = root.closest('.drawercontent');
    if (scroller) {
        scroller.scrollTop = 0;
    }
};

/**
 * Open the panel a row leads to, and move the focus to its back control.
 *
 * @param {HTMLElement} root The drawer.
 * @param {HTMLElement} opener The row that was pressed.
 */
const enter = (root, opener) => {
    const panel = document.getElementById(opener.dataset.target);
    if (!panel || !root.contains(panel)) {
        return;
    }
    openers.push(opener);
    show(root, panel);
    // The panel is still sliding in from the right, and the viewport's overflow hidden is a scroll container:
    // a plain focus() scrolls it to reveal the control, and the panel stays drawn that far to the left for as
    // long as another panel keeps the viewport wider than itself. show() has put the control in view already.
    panel.querySelector(SELECTORS.BACK)?.focus({preventScroll: true});
};

/**
 * Go back one level, and give the focus back to the row that opened the level being left.
 *
 * @param {HTMLElement} root The drawer.
 */
const leave = (root) => {
    const panel = current(root);
    const parent = panel && panel.dataset.parent ? document.getElementById(panel.dataset.parent) : null;
    if (!parent) {
        return;
    }
    show(root, parent);
    const opener = openers.pop();
    if (opener && parent.contains(opener)) {
        opener.focus();
    }
};

/**
 * Put the drawer back on its root panel without moving the focus, for the next time it is opened.
 *
 * @param {HTMLElement} root The drawer.
 */
const reset = (root) => {
    openers.length = 0;
    const first = panels(root)[0];
    if (first) {
        show(root, first);
    }
};

/**
 * Answer a press inside the drawer: a row with children or a back control.
 *
 * @param {HTMLElement} root The drawer.
 * @param {MouseEvent} event The click.
 */
const onClick = (root, event) => {
    if (event.defaultPrevented) {
        return;
    }
    const opener = event.target.closest(SELECTORS.OPEN);
    if (opener && root.contains(opener)) {
        event.preventDefault();
        enter(root, opener);
        return;
    }
    if (event.target.closest(SELECTORS.BACK)) {
        event.preventDefault();
        leave(root);
    }
};

/**
 * Answer a key inside the drawer: Escape inside a sub-panel goes back one level, and is the drawer's alone.
 *
 * Escape at the root goes on to Boost's primary drawer, which {@see closeOnEscape} closes.
 *
 * @param {HTMLElement} root The drawer.
 * @param {KeyboardEvent} event The key press.
 */
const onKey = (root, event) => {
    if (event.key !== 'Escape') {
        return;
    }
    const panel = current(root);
    if (panel && panel.dataset.parent) {
        event.preventDefault();
        event.stopPropagation();
        leave(root);
    }
};

/**
 * Close Boost's primary drawer on an Escape no sub-panel took: the root panel's, or its header's close button's.
 *
 * Through Boost's own instance, so its backdrop, its events and its focus are Boost's: closeDrawer() gives the focus
 * back to the button that opened the drawer. Boost's drawers module listens for no key of its own.
 *
 * @param {HTMLElement} drawer Boost's primary drawer.
 * @param {KeyboardEvent} event The key press.
 */
const closeOnEscape = (drawer, event) => {
    if (event.key !== 'Escape' || event.defaultPrevented || !drawer.classList.contains(SHOWN)) {
        return;
    }
    event.preventDefault();
    Drawers.getDrawerInstanceForNode(drawer).closeDrawer();
};

/**
 * Fill the card from the summary, or take it away when there is nothing to continue.
 *
 * Every value is written as text: the server composed the sentences, the browser composes nothing.
 *
 * @param {HTMLElement} card The card.
 * @param {Object} resume The summary's resume.
 */
const paintResume = (card, resume) => {
    if (!resume || !resume.hasitem) {
        card.remove();
        return;
    }
    card.querySelector(SELECTORS.RESUMELINK).href = resume.url;
    card.querySelector(SELECTORS.RESUMETITLE).textContent = resume.name;
    card.querySelector(SELECTORS.RESUMESUB).textContent = resume.detail;
    const bar = card.querySelector(SELECTORS.RESUMEBAR);
    if (resume.hascompletion) {
        bar.style.width = `${resume.percent}%`;
    } else {
        card.classList.add('dimensions-drawer-resume--norail');
    }
    card.removeAttribute('aria-busy');
    card.classList.add('dimensions-drawer-resume--ready');
};

/**
 * Ask for the summary and paint it.
 *
 * @param {HTMLElement} card The card.
 */
const load = async(card) => {
    const pending = new Pending('theme_dimensions/drawer:summary');
    try {
        const summary = await Ajax.call([{methodname: SUMMARY, args: {}}])[0];
        paintResume(card, summary.resume);
    } catch (error) {
        card.remove();
    } finally {
        pending.resolve();
    }
};

/**
 * Load the summary the first time the primary drawer is shown, and never before.
 *
 * Boost dispatches its drawers' events on the drawer itself and they bubble, so one listener on the
 * document hears every drawer; it answers the primary one only, once, and then stops listening.
 *
 * @param {HTMLElement} root The drawer.
 */
const armSummary = (root) => {
    const card = root.querySelector(SELECTORS.RESUME);
    if (!card) {
        return;
    }
    const onShown = (event) => {
        if (!event.target || event.target.id !== DRAWER) {
            return;
        }
        document.removeEventListener(Drawers.eventTypes.drawerShown, onShown);
        load(card);
    };
    document.addEventListener(Drawers.eventTypes.drawerShown, onShown);
};

/**
 * Arm the drawer.
 *
 * @param {String} id The drawer's id.
 */
export const init = (id) => {
    const root = document.getElementById(id);
    if (!root || root.classList.contains('dimensions-drawer-js')) {
        return;
    }
    reset(root);
    root.classList.add('dimensions-drawer-js');
    root.addEventListener('click', (event) => onClick(root, event));
    root.addEventListener('keydown', (event) => onKey(root, event));
    const drawer = root.closest('#' + DRAWER);
    if (drawer) {
        drawer.addEventListener('keydown', (event) => closeOnEscape(drawer, event));
    }
    document.addEventListener(Drawers.eventTypes.drawerHidden, (event) => {
        if (event.target && event.target.id === DRAWER) {
            reset(root);
        }
    });
    armSummary(root);
};
