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
 * The navbar that follows the reading: the band as progress, the context layer and auto-hide.
 *
 * Every decision about WHERE is the server's (classes/local/readingbar.php): this module is armed only on
 * a reading page, finds the context layer only where the server drew it, and hides the bar only where an
 * administrator asked for it. What it decides is WHEN, from the scroll:
 *
 * - The band (C3): on an activity page it is the share of the page scrolled; on the course page it is the
 *   reader's completion of the course, handed in by the server and never changed here.
 * - The context layer (C2): it comes in once the page header has gone under the navbar and the reader has
 *   gone on down a little; the global bar comes back on the way up.
 * - Auto-hide (C1): past the header, a longer way down takes the bar off the screen (its band stays) and
 *   the first way up brings it back, showing the global bar.
 *
 * The scroll listener is passive and only books one animation frame, where the whole decision runs:
 * the layout is read first and the classes written after. Distance thresholds in each direction keep the
 * bar from flickering. Nothing changes while the keyboard focus is in the bar, one of its menus is open, or
 * a drawer lies over the page. The motion is the stylesheet's, under prefers-reduced-motion: no-preference;
 * the module only writes classes and one custom property. On the Behat site the bar never moves or swaps
 * with the scroll: a scenario that scrolled would find the controls of the global bar out of reach.
 *
 * @module     theme_dimensions/readingbar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CourseEvents from 'core_course/events';
import {isLarge} from 'core/pagehelpers';

/** The main navbar. Boost Union's menu bar is a .navbar.fixed-top too, and carries none of this. */
const NAVBAR = 'nav.navbar.fixed-top:not(.boost-union-menubar)';

/** The custom property the band's fill reads (scss/post.scss), a fraction from 0 to 1. */
const PROGRESS = '--dimensions-band-progress';

const SELECTORS = {
    LAYER: '[data-region="dimensions-ctxbar"]',
    INDEX: '[data-region="dimensions-ctxbar-index"]',
    STATUS: '[data-region="dimensions-ctxbar-status"]',
    HEADER: '#page-header',
    MENUBAR: '.navbar.boost-union-menubar',
    EXPANDED: '[aria-expanded="true"]',
    OPENMENU: '.dropdown-menu.show',
    DRAWER: '[data-region="fixed-drawer"].show',
    MESSAGES: '[data-region="right-hand-drawer"].drawer:not(.hidden)',
};

const CLASSES = {
    CONTEXT: 'dimensions-ctx-on',
    AWAY: 'dimensions-navbar-away',
};

/** The drawers that are docked beside the page from the lg breakpoint up, and lie over it below. */
const DOCKED = ['theme_boost-drawers-courseindex', 'theme_boost-drawers-blocks'];

/** The pixels of travel in one direction, past the page header, before the bar changes. */
const DISTANCE = {
    SWAP: 24,
    HIDE: 96,
    BACK: 16,
};

/** The page's one bar: the elements, what this page lets the module do, and where the scroll was. */
const bar = {
    nav: null,
    layer: null,
    tracks: false,
    swaps: false,
    hides: false,
    context: false,
    away: false,
    progress: null,
    lastY: 0,
    travel: 0,
    frame: 0,
};

/**
 * Paint the band's fill, from the left.
 *
 * @param {number} fraction How much of the band is filled, from 0 to 1.
 */
const setBand = (fraction) => {
    const value = Math.min(1, Math.max(0, fraction)).toFixed(4);
    if (value === bar.progress) {
        return;
    }
    bar.progress = value;
    bar.nav.style.setProperty(PROGRESS, value);
};

/**
 * The share of the page scrolled; a page that does not scroll is read whole.
 *
 * @returns {number}
 */
const scrolled = () => {
    const max = document.documentElement.scrollHeight - window.innerHeight;
    if (max <= 0) {
        return 1;
    }
    return window.scrollY / max;
};

/**
 * Whether the page header (the activity's or the course's title) has gone under the navbar.
 *
 * @returns {boolean}
 */
const pastHeader = () => {
    const header = document.querySelector(SELECTORS.HEADER);
    if (!header) {
        return window.scrollY > bar.nav.offsetHeight;
    }
    return header.getBoundingClientRect().bottom <= bar.nav.offsetHeight;
};

/**
 * Whether the bar must stay as it is: the focus is in it, one of its menus is open, or a drawer lies over the page.
 *
 * A drawer docked beside the page (the course index and the blocks, from lg up) is no reason: it is open on every
 * page of a reader who keeps it so.
 *
 * @returns {boolean}
 */
const busy = () => {
    if (bar.nav.contains(document.activeElement)) {
        return true;
    }
    if (bar.nav.querySelector(SELECTORS.EXPANDED) || bar.nav.querySelector(SELECTORS.OPENMENU)) {
        return true;
    }
    if (document.querySelector(SELECTORS.MESSAGES)) {
        return true;
    }
    const large = isLarge();
    return Array.from(document.querySelectorAll(SELECTORS.DRAWER)).some((drawer) => !large || !DOCKED.includes(drawer.id));
};

/**
 * Give the course index control core's toggler attribute only while it can be seen.
 *
 * Boost's drawers module finds a drawer's toggle by that attribute, the first one in the document - which is this
 * control, the navbar coming first - and moves the focus to it when the drawer closes. Hidden with the layer, the
 * control cannot take the focus, which would be lost; without the attribute, Boost finds its own toggle instead.
 *
 * @param {boolean} shown Whether the layer is shown.
 */
const syncToggler = (shown) => {
    const index = bar.layer ? bar.layer.querySelector(SELECTORS.INDEX) : null;
    if (!index) {
        return;
    }
    if (shown) {
        index.setAttribute('data-toggler', 'drawers');
    } else {
        index.removeAttribute('data-toggler');
    }
};

/**
 * Show the context layer, or the global bar.
 *
 * @param {boolean} on Whether the context layer is shown.
 */
const setContext = (on) => {
    if (!bar.swaps || bar.context === on) {
        return;
    }
    bar.context = on;
    bar.nav.classList.toggle(CLASSES.CONTEXT, on);
    syncToggler(on);
};

/**
 * Take the bar off the screen, its band left at the top, or bring it back.
 *
 * The class is the body's: the drawers, the anchors and the sticky elements read the navbar's offset from it.
 *
 * @param {boolean} away Whether the bar is away.
 */
const setAway = (away) => {
    if (!bar.hides || bar.away === away) {
        return;
    }
    bar.away = away;
    document.body.classList.toggle(CLASSES.AWAY, away);
};

/**
 * Decide the bar from the scroll: the animation frame's work, every read before the first write.
 */
const decide = () => {
    bar.frame = 0;
    const y = Math.max(0, window.scrollY);
    const delta = y - bar.lastY;
    bar.lastY = y;
    const fraction = bar.tracks ? scrolled() : null;
    const moves = (bar.swaps || bar.hides) && delta !== 0;
    const held = moves && busy();
    const past = moves && !held && pastHeader();

    if (fraction !== null) {
        setBand(fraction);
    }
    if (!moves) {
        return;
    }
    if (held) {
        bar.travel = 0;
        return;
    }
    bar.travel = (delta > 0) === (bar.travel > 0) ? bar.travel + delta : delta;
    if (!past || bar.travel <= -DISTANCE.BACK) {
        setAway(false);
        setContext(false);
        return;
    }
    if (bar.travel >= DISTANCE.SWAP) {
        setContext(true);
    }
    if (bar.travel >= DISTANCE.HIDE) {
        setAway(true);
    }
};

/**
 * Book the frame the decision runs in, once however many scroll events arrive before it.
 */
const onScroll = () => {
    if (!bar.frame) {
        bar.frame = window.requestAnimationFrame(decide);
    }
};

/**
 * Follow the activity's completion when core's footer button changes it: the status only says what core says.
 */
const armStatus = () => {
    const status = bar.layer ? bar.layer.querySelector(SELECTORS.STATUS) : null;
    if (!status) {
        return;
    }
    document.addEventListener(CourseEvents.manualCompletionToggled, (event) => {
        const detail = event.detail;
        if (!detail || String(detail.cmid) !== status.dataset.cmid) {
            return;
        }
        status.dataset.state = detail.completed ? 'complete' : 'pending';
    });
};

/**
 * Arm the bar of a reading page.
 *
 * @param {object} config From readingbar::client_config().
 * @param {string} config.kind activity or course.
 * @param {number|null} config.progress The reader's completion of the course, a percentage; null elsewhere.
 * @param {boolean} config.autohide Whether the bar hides on the way down here.
 * @param {boolean} config.behat Whether this is the Behat site.
 */
export const init = (config) => {
    const nav = document.querySelector(NAVBAR);
    if (!nav) {
        return;
    }
    bar.nav = nav;
    bar.layer = nav.querySelector(SELECTORS.LAYER);
    if (config.progress !== null && config.progress !== undefined) {
        setBand(config.progress / 100);
    }
    armStatus();

    const live = !config.behat;
    bar.tracks = config.kind === 'activity';
    bar.swaps = live && bar.layer !== null;
    bar.hides = live && config.autohide && document.querySelector(SELECTORS.MENUBAR) === null;
    // The layer starts hidden wherever it swaps; where it never does (the Behat site) the control keeps core's attribute.
    syncToggler(!bar.swaps);
    if (!bar.tracks && !bar.swaps && !bar.hides) {
        return;
    }
    if (bar.hides) {
        nav.addEventListener('focusin', () => setAway(false));
    }
    bar.lastY = Math.max(0, window.scrollY);
    window.addEventListener('scroll', onScroll, {passive: true});
    window.addEventListener('resize', onScroll, {passive: true});
    onScroll();
};
