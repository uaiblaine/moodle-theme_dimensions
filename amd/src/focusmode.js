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
 * The width control of the focus mode bar.
 *
 * The state is resolved on the server and painted into the body class and the button before this
 * module runs, so there is no flash of the wrong measure and the page is correct with JavaScript
 * switched off. This module moves the state when the button is pressed and, when the person's
 * choice can be stored at all, remembers it.
 *
 * Guests are the reason for the canpersist flag. They get focus mode - the manual session
 * lives in the PHP session, which a guest has - but core refuses to write a user preference
 * for them, so for a guest the class is toggled for that page view and no request is issued.
 * Asking anyway would be refused and shown as an exception dialogue.
 *
 * @module     theme_dimensions/focusmode
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {setUserPreference} from 'core_user/repository';
import Notification from 'core/notification';

const SELECTORS = {
    bar: '.dimensions-focusbar',
    width: '[data-action="dimensions-focus-width"]',
    expandicon: '[data-region="dimensions-focus-width-expand"]',
    contracticon: '[data-region="dimensions-focus-width-contract"]',
};

/** @type {String} The body class which lifts the reading column to the content maximum. */
const EXPANDEDCLASS = 'dimensions-focusmode-expanded';

/** @type {String} Bootstrap's own display utility, used to swap the two glyphs. */
const HIDDENCLASS = 'd-none';

/**
 * Paint one state onto the body and the button.
 *
 * The button's accessible name never changes: the server renders it once, and aria-pressed
 * carries the state, which is how a toggle button is announced ("Wide reading column, pressed").
 * A label that also flipped would be read as a second, contradictory state.
 *
 * @param {HTMLElement} button The width button.
 * @param {Boolean} expanded Whether the reading column is now widened.
 * @return {void}
 */
const paint = (button, expanded) => {
    document.body.classList.toggle(EXPANDEDCLASS, expanded);
    button.setAttribute('aria-pressed', expanded ? 'true' : 'false');

    const expandicon = button.querySelector(SELECTORS.expandicon);
    if (expandicon) {
        expandicon.classList.toggle(HIDDENCLASS, expanded);
    }
    const contracticon = button.querySelector(SELECTORS.contracticon);
    if (contracticon) {
        contracticon.classList.toggle(HIDDENCLASS, !expanded);
    }
};

/**
 * Arm the width control of the focus bar.
 *
 * @param {Object} config The state the renderer resolved: expanded, canpersist, preference.
 * @return {void}
 */
export const init = (config) => {
    const bar = document.querySelector(SELECTORS.bar);
    const button = bar ? bar.querySelector(SELECTORS.width) : null;
    if (!bar || !button) {
        return;
    }

    let expanded = Boolean(config.expanded);
    paint(button, expanded);

    button.addEventListener('click', () => {
        expanded = !expanded;
        paint(button, expanded);

        if (config.canpersist) {
            setUserPreference(config.preference, expanded).catch(Notification.exception);
        }
    });
};
