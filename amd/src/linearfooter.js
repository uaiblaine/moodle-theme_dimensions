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
 * Core's linear navigation footer on a phone, shown again near the end of the page.
 *
 * Below 768px Boost's sticky footer module (theme_boost/sticky-footer) hides every sticky footer on the way down the
 * page and shows it on the way up. For the linear navigation footer that hides "Next" and the manual completion
 * button exactly when the reader reaches the end of the activity, which they reach scrolling down. This module leaves
 * core's behaviour as it is along the page and shows the footer again whenever the end is near (END of the screen's
 * height left to scroll), as core shows it: Boost's class on the body and on #page, which also gives the page the
 * room under it.
 *
 * Core listens for the scroll on the document; this listens on the window, where the same event arrives after it, so
 * on the way down near the end core's hide and this show happen in one task, before the browser paints, and the
 * footer does not flicker. It follows the footer's state as core announces it: a footer core disabled stays hidden.
 * The Behat site never hides the footer (core's module stands down there), and neither does this.
 *
 * @module     theme_dimensions/linearfooter
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {eventTypes} from 'core/sticky-footer';

const SELECTORS = {
    FOOTER: '.stickyfooter',
    LINEAR: '.course-linear-navigation',
    PAGE: '#page',
};

/** The class Boost's module shows the footer with, on the body and on #page. */
const SHOWN = 'hasstickyfooter';

/** The width from which Boost's module never hides the footer (its scrollSpy compares document.body.clientWidth). */
const PHONE = 768;

/** How much of the screen's height may be left to scroll for the end to be near. */
const END = 0.25;

/** Whether core has the footer enabled: its own default, then what it announces. */
let enabled = false;

/**
 * Whether the end of the page is near: less than END of the screen's height left to scroll, or none (a short page,
 * or the bounce past the end).
 *
 * @returns {boolean}
 */
const nearEnd = () => {
    const left = document.documentElement.scrollHeight - window.innerHeight - window.scrollY;
    return left <= window.innerHeight * END;
};

/**
 * Show the footer again near the end of the page, on a phone, while core has it enabled.
 */
const onScroll = () => {
    // Core shows the footer on the way up: already shown, there is nothing to add and no layout to read for it.
    if (!enabled || document.body.classList.contains(SHOWN) || document.body.clientWidth >= PHONE || !nearEnd()) {
        return;
    }
    document.body.classList.add(SHOWN);
    document.querySelector(SELECTORS.PAGE)?.classList.add(SHOWN);
};

/**
 * Arm the module where the page's sticky footer is the linear navigation's.
 */
export const init = () => {
    const footer = document.querySelector(SELECTORS.FOOTER);
    if (!footer || !footer.querySelector(SELECTORS.LINEAR) || document.body.classList.contains('behat-site')) {
        return;
    }
    enabled = !footer.dataset.disable;
    document.addEventListener(eventTypes.stickyFooterStateChanged, (event) => {
        enabled = Boolean(event.detail && event.detail.enabled);
    });
    window.addEventListener('scroll', onScroll, {passive: true});
};
