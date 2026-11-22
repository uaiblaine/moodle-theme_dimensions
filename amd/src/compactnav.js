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
 * The navbar's compact mode on tablets: the phone's pattern while the primary navigation overflows.
 *
 * Where the server armed it (classes/local/compactnav.php: the setting, not the Moodle app, not focus mode, a
 * navigation that can overflow at all), this module decides WHEN, by reading what core's React primary navigation
 * (core/nav/PrimaryNav, or Boost Union's copy of core/nav/Nav for smart menus) has decided:
 *
 * - The count: the top-level items the props the navigation was mounted with can send into "More" because of the
 *   width (neither a divider nor forced into "More"), less the pills it still draws in its bar. That difference IS
 *   the navigation's own overflow count, read from its output and never recomputed from widths here.
 * - The navigation has settled once its mount point carries the props' measuredclass (core's primarynav-measured).
 *   Until then nothing is decided, and the stylesheet holds the navigation back (scss/compactnav.scss).
 * - Compact while more than maxhidden items are in "More" and the window is between lg and xl (RANGE, the
 *   stylesheet's media-breakpoint-between(lg, xl)). The body gets dimensions-nav-compact, and core's toggler loses
 *   d-lg-none, whose display: none is !important and so cannot be outranked by the stylesheet.
 * - Once compact, the bar stays compact at any width up to the widest it was decided at (heldwidth), whatever the
 *   navigation splits meanwhile. The two modes lay the bar out differently (the hamburger takes room; the colour
 *   mode menu leaves for the drawer, scss/post.scss), so a split made while compact says nothing of the full bar:
 *   acting on it would flip between the two modes on every frame. Only more room than the bar was compact at, or
 *   leaving the range, ends the mode.
 *
 * A ResizeObserver on the navbar's container books a decision whenever the room changes; a MutationObserver on the
 * mount point books one whenever the navigation commits a new split (it re-measures from its own observers, and a
 * width the observers above report may be read before it has). Either only books one animation frame, where the
 * decision runs; nothing polls.
 *
 * The mobile drawer at tablet width: core gives it a backdrop and closes it on a press outside only below lg. While
 * the bar is compact a press outside it closes it (on the page the press is the scrim's and goes no further; on the
 * navbar it goes on, as core lets it on a phone), another drawer opening closes it, and the bar leaving the compact
 * mode closes it unless the focus is inside.
 *
 * @module     theme_dimensions/compactnav
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Drawers from 'theme_boost/drawers';
import Pending from 'core/pending';
import {isSmall} from 'core/pagehelpers';

/**
 * The main navbar. Boost Union's menu bar is a .navbar.fixed-top too, and so is focus mode's bar, printed before it on a
 * page that can switch in place.
 */
const NAVBAR = 'nav.navbar.fixed-top:not(.boost-union-menubar):not(.dimensions-focusbar)';

/** The range the mode lives in: media-breakpoint-between(lg, xl) of scss/compactnav.scss. */
const RANGE = '(min-width: 992px) and (max-width: 1199.98px)';

/** Core's default for the class its navigation writes on the mount point once its split has settled. */
const MEASURED_DEFAULT = 'primarynav-measured';

const SELECTORS = {
    CONTAINER: ':scope > .container-fluid',
    MOUNT: '.primary-navigation [data-react-component]',
    MENU: 'ul.more-nav',
    PILLS: ':scope > li:not(.dropdownmoremenu)',
    TOGGLER: '.navbar-toggler[data-target="theme_boost-drawers-primary"]',
    DRAWER: '#theme_boost-drawers-primary',
    BUTTONS: '[data-toggler="drawers"]',
};

const CLASSES = {
    ARMED: 'dimensions-navcompact-armed',
    MEASURED: 'dimensions-navcompact-measured',
    COMPACT: 'dimensions-nav-compact',
    TOGGLERHIDDEN: 'd-lg-none',
    SHOW: 'show',
};

/** The page's one navbar: its elements, what the navigation was mounted with, and the decision in force. */
const bar = {
    mount: null,
    container: null,
    toggler: null,
    drawer: null,
    range: null,
    candidates: 0,
    measuredclass: MEASURED_DEFAULT,
    maxhidden: 2,
    compact: false,
    heldwidth: 0,
    frame: 0,
};

/**
 * What the navigation was mounted with: how many items the width may send into "More", and its settled class.
 *
 * @param {HTMLElement} mount The React mount point.
 * @returns {object|null} candidates and measuredclass; null when the props cannot be read.
 */
const readProps = (mount) => {
    let props = null;
    try {
        props = JSON.parse(mount.getAttribute('data-react-props') || '');
    } catch (error) {
        return null;
    }
    if (!props || !Array.isArray(props.items)) {
        return null;
    }
    return {
        candidates: props.items.filter((item) => item && !item.divider && !item.forceintomoremenu).length,
        measuredclass: props.measuredclass || MEASURED_DEFAULT,
    };
};

/**
 * How many items the navigation has sent into "More" because of the width; null while it has not settled.
 *
 * @returns {number|null}
 */
const hidden = () => {
    if (!bar.mount.classList.contains(bar.measuredclass)) {
        return null;
    }
    const menu = bar.mount.querySelector(SELECTORS.MENU);
    if (!menu) {
        return null;
    }
    return Math.max(bar.candidates - menu.querySelectorAll(SELECTORS.PILLS).length, 0);
};

/**
 * The mobile drawer's own instance of core's Drawers, or null.
 *
 * @returns {Drawers|null}
 */
const drawer = () => (bar.drawer ? Drawers.getDrawerInstanceForNode(bar.drawer) : null);

/**
 * Whether the mobile drawer is open at a width core gives it no backdrop.
 *
 * @returns {boolean}
 */
const drawerOverPage = () => bar.compact && bar.drawer !== null && bar.drawer.classList.contains(CLASSES.SHOW) && !isSmall();

/**
 * Close the mobile drawer when the bar leaves the compact mode at tablet width: its hamburger is about to go.
 *
 * Below lg the hamburger stays (core's own), and a drawer holding the focus is left open: it has its own close
 * control, as core leaves such a drawer open when the window is resized.
 */
const release = () => {
    if (!bar.drawer || !bar.drawer.classList.contains(CLASSES.SHOW) || isSmall()) {
        return;
    }
    if (bar.drawer.contains(document.activeElement)) {
        return;
    }
    drawer()?.closeDrawer({focusOnOpenButton: false});
};

/**
 * Switch the compact mode on or off.
 *
 * @param {boolean} on Whether the navbar is compact.
 */
const setCompact = (on) => {
    if (bar.compact === on) {
        return;
    }
    bar.compact = on;
    document.body.classList.toggle(CLASSES.COMPACT, on);
    if (bar.toggler) {
        bar.toggler.classList.toggle(CLASSES.TOGGLERHIDDEN, !on);
    }
    if (!on) {
        bar.heldwidth = 0;
        release();
    }
};

/**
 * Decide the mode: the animation frame's work, every read before the first write.
 *
 * The hold: while compact, the bar stays so up to the widest container width an overflow was seen at, because a split
 * the navigation makes in the compact layout cannot say whether the full bar would fit (the module's docblock).
 */
const decide = () => {
    bar.frame = 0;
    const count = hidden();
    if (count === null) {
        return;
    }
    const inrange = bar.range.matches;
    const width = bar.container.clientWidth;
    const overflows = inrange && count > bar.maxhidden;
    const held = inrange && bar.compact && width <= bar.heldwidth;
    setCompact(overflows || held);
    if (overflows) {
        bar.heldwidth = Math.max(bar.heldwidth, width);
    }
    document.body.classList.add(CLASSES.MEASURED);
};

/**
 * Book the frame the decision runs in, once however many observations arrive before it.
 */
const schedule = () => {
    if (bar.frame) {
        return;
    }
    const pending = new Pending('theme_dimensions/compactnav:decide');
    bar.frame = window.requestAnimationFrame(() => {
        decide();
        pending.resolve();
    });
};

/**
 * A press outside the open mobile drawer at tablet width closes it, as core's backdrop does below lg.
 *
 * On the page the press is the scrim's and goes no further; on the navbar it goes on to its control.
 *
 * @param {MouseEvent} event The click, in the capture phase.
 */
const onPress = (event) => {
    if (!drawerOverPage()) {
        return;
    }
    const target = event.target;
    if (!(target instanceof Element) || bar.drawer.contains(target) || target.closest(SELECTORS.BUTTONS)) {
        return;
    }
    const instance = drawer();
    if (!instance) {
        return;
    }
    if (target.closest(NAVBAR)) {
        instance.closeDrawer({focusOnOpenButton: false});
        return;
    }
    event.preventDefault();
    event.stopPropagation();
    instance.closeDrawer();
};

/**
 * Another drawer opening closes the mobile drawer: from lg up core leaves both open, on the same side.
 *
 * @param {CustomEvent} event Boost's drawer show event.
 */
const onDrawerShow = (event) => {
    if (!drawerOverPage() || event.target === bar.drawer) {
        return;
    }
    drawer()?.closeDrawer({focusOnOpenButton: false});
};

/**
 * Arm the compact mode of the page's navbar.
 *
 * @param {object} config From compactnav::client_config().
 * @param {number} config.maxhidden The most items "More" may hold before the navbar goes compact.
 */
export const init = (config) => {
    // The server's answer: the setting off, the Moodle app and focus mode leave the body unarmed.
    if (!document.body.classList.contains(CLASSES.ARMED)) {
        return;
    }
    const nav = document.querySelector(NAVBAR);
    const mount = nav ? nav.querySelector(SELECTORS.MOUNT) : null;
    const props = mount ? readProps(mount) : null;
    const container = nav ? nav.querySelector(SELECTORS.CONTAINER) : null;
    if (!props || !container) {
        // Nothing to measure: the hold is lifted and the navbar stays core's.
        document.body.classList.add(CLASSES.MEASURED);
        return;
    }

    bar.mount = mount;
    bar.container = container;
    bar.toggler = nav.querySelector(SELECTORS.TOGGLER);
    bar.drawer = document.querySelector(SELECTORS.DRAWER);
    bar.range = window.matchMedia(RANGE);
    bar.candidates = props.candidates;
    bar.measuredclass = props.measuredclass;
    bar.maxhidden = config.maxhidden;

    new ResizeObserver(schedule).observe(container);
    new MutationObserver(schedule).observe(mount, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class'],
    });
    bar.range.addEventListener('change', schedule);
    document.addEventListener('click', onPress, true);
    document.addEventListener(Drawers.eventTypes.drawerShow, onDrawerShow);
    schedule();
};
