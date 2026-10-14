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
 * The course notice: its fold, its copy buttons and, on a MooTube course page, its place.
 *
 * The fold is drawn by the server from the person's preference before this module runs, so the
 * page is right with JavaScript off and shows no flash; this module moves aria-expanded, the
 * detail's hidden attribute and the quick copy buttons together, and stores the choice where it
 * can be stored (never for the guest account, which core refuses a preference). The copy buttons
 * follow core's copy_to_clipboard contract, which importing that module arms.
 *
 * MooTube (format_mtube) prepends its own #content-wrapper to #page as an absolutely positioned
 * layer the height of the screen, and #page itself is clamped to that height with its overflow
 * hidden: the region Boost Union's layout prints the notice into is painted underneath that layer
 * and can never be scrolled to. So on that page the notice is moved into the format's scrolling
 * #content, above its main content - the same kind of move the format makes with its own nodes.
 * The format's page shows nothing without JavaScript at all, so nothing is lost there without it.
 *
 * @module     theme_dimensions/coursenotice
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {setUserPreference} from 'core_user/repository';
import Notification from 'core/notification';
import 'core/copy_to_clipboard';

const SELECTORS = {
    toggle: '[data-action="dim-coursenotice-toggle"]',
    detail: '[data-region="dim-coursenotice-detail"]',
    quick: '[data-region="dim-coursenotice-quick"]',
    mtubecontent: '#content-wrapper #content',
    mtubemain: '#main-content-wrapper',
};

/** @type {String} The body id of a MooTube course page, from its page type course-view-mtube. */
const MTUBE_BODY_ID = 'page-course-view-mtube';

/** @type {String} The class the stylesheet spaces the notice with inside MooTube's content. */
const MTUBE_CLASS = 'dim-coursenotice--mtube';

/**
 * Move the notice into MooTube's scrolling content, above the format's main content.
 *
 * @param {HTMLElement} region The notice.
 * @return {void}
 */
const placeInMtube = (region) => {
    if (document.body.id !== MTUBE_BODY_ID) {
        return;
    }
    const content = document.querySelector(SELECTORS.mtubecontent);
    if (!content) {
        return;
    }
    const main = Array.from(content.children).find((child) => child.matches(SELECTORS.mtubemain));
    if (main) {
        content.insertBefore(region, main);
    } else {
        content.prepend(region);
    }
    region.classList.add(MTUBE_CLASS);
};

/**
 * Paint one fold onto the control, the detail and the quick copy buttons.
 *
 * The control's accessible name never changes; aria-expanded carries the state.
 *
 * @param {HTMLElement} region The notice.
 * @param {HTMLElement} toggle The fold control.
 * @param {Boolean} expanded Whether the detail is now shown.
 * @return {void}
 */
const paint = (region, toggle, expanded) => {
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    const detail = region.querySelector(SELECTORS.detail);
    if (detail) {
        detail.hidden = !expanded;
    }
    const quick = region.querySelector(SELECTORS.quick);
    if (quick) {
        quick.hidden = expanded;
    }
};

/**
 * Arm one notice.
 *
 * @param {String} regionid The id of the notice's region.
 * @return {void}
 */
export const init = (regionid) => {
    const region = document.getElementById(regionid);
    if (!region) {
        return;
    }
    placeInMtube(region);

    const toggle = region.querySelector(SELECTORS.toggle);
    if (!toggle) {
        return;
    }
    toggle.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') !== 'true';
        paint(region, toggle, expanded);
        if (region.dataset.canpersist === '1') {
            setUserPreference(region.dataset.preference, !expanded).catch(Notification.exception);
        }
    });
};
