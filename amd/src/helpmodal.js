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
 * A help text too long for core's help popover opens in a dialogue instead.
 *
 * Core's help icon (core/help_icon) opens a Bootstrap popover, which theme_boost/loader's enablePopovers() shows on
 * focus, on a click and on Enter, under its own focus, Tab and Escape handling. The theme caps the popover's body
 * (scss/post.scss, "Core's help popover"); a text past that cap scrolls inside a popover the keyboard cannot scroll,
 * with no title and no close control. Activated (a press or Enter on the icon), such a popover is replaced by a
 * core/modal holding the same HTML, titled with the icon's own name. A text that fits stays core's popover, untouched.
 *
 * Focus alone never opens the dialogue: core shows the popover when the icon receives focus, and a dialogue there
 * would take the keyboard away from a person tabbing through a form at every long help. Focus shows the popover, as
 * core does; Enter then opens the dialogue.
 *
 * The decision is taken where Bootstrap gives the tip to the page: 'inserted.bs.popover' fires right after the tip is
 * appended and before Bootstrap adds its 'show' class, in the same task as the show() that a focus or a click caused.
 * The body is measured there, capped by the theme's stylesheet; if the show came from a press or Enter still under way
 * and the text overflows, the tip is kept invisible (PENDING) until the press ends in a click and the dialogue
 * replaces it, so nothing of the popover is painted. The swap runs from listeners on the window, after core's on the
 * document have shown the popover for that same click or key.
 *
 * Fail open: a missing instance, tip or body, an icon inside an open modal (no modal over a modal) or any error
 * leaves core's popover as it is, made visible again.
 *
 * @module     theme_dimensions/helpmodal
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Popover} from 'bootstrap';
import {getString} from 'core/str';
import Log from 'core/log';
import Pending from 'core/pending';

const SELECTORS = {
    TRIGGER: '.help-icon[data-bs-toggle="popover"]',
    BODY: '.popover-body',
    ICON: 'img, .icon',
    MODAL: '.modal',
};

/** The class that keeps a measured tip invisible while the dialogue replaces it ({@see scss/post.scss}). */
const PENDING = 'dimensions-help-pending';

/** The class of the dialogue's root, which gives its body the popover's reading rhythm ({@see scss/post.scss}). */
const ROOT = 'dimensions-help-modal';

/** Whether the document is wired: init() may be asked for more than once on a page. */
let wired = false;

/** The help icon a press or Enter is activating, from the pointerdown or keydown until its click or key is handled. */
let activating = null;

/** The help icon the dialogue gives focus back to: core's focusin handler would show its popover again. */
let returning = null;

/** Whether a dialogue is being opened: a key that also produces a click must not open two. */
let swapping = false;

/**
 * The help icon an event concerns, outside any modal.
 *
 * @param {EventTarget} target The event's target.
 * @returns {Element|null}
 */
const triggerOf = (target) => {
    const trigger = target instanceof Element ? target.closest(SELECTORS.TRIGGER) : null;

    return trigger && !trigger.closest(SELECTORS.MODAL) ? trigger : null;
};

/**
 * The tip and body of an icon's popover while Bootstrap has one in the page.
 *
 * @param {Element} trigger The help icon.
 * @returns {{popover: Popover, tip: HTMLElement, body: HTMLElement}|null}
 */
const partsOf = (trigger) => {
    const popover = Popover.getInstance(trigger);
    const tip = popover?.tip;
    const body = tip?.querySelector(SELECTORS.BODY);

    return body ? {popover, tip, body} : null;
};

/**
 * Whether the text overflows the body's cap. One pixel of slack absorbs the rounding of a fractional height.
 *
 * @param {HTMLElement} body The popover's body.
 * @returns {boolean}
 */
const overflows = (body) => body.scrollHeight > body.clientHeight + 1;

/**
 * Show a tip this module kept invisible: the fail-open path.
 *
 * @param {HTMLElement|null|undefined} tip The tip.
 */
const reveal = (tip) => {
    tip?.classList.remove(PENDING);
};

/**
 * Text as HTML: core/modal writes a string title into the page as markup (its setTitle()).
 *
 * @param {string} text Plain text.
 * @returns {string}
 */
const escaped = (text) => {
    const holder = document.createElement('span');
    holder.textContent = text;

    return holder.innerHTML;
};

/**
 * The dialogue's title: the icon's own name ("Help with ..."), its alt, then its title, then core's "Help".
 *
 * @param {Element} trigger The help icon.
 * @returns {Promise<string>} Plain text.
 */
const heading = async(trigger) => {
    const icon = trigger.querySelector(SELECTORS.ICON);
    const name = (icon?.getAttribute('alt') || icon?.getAttribute('title') || '').trim();

    return name || getString('help', 'core');
};

/**
 * Replace an activated help popover whose text overflows with the dialogue.
 *
 * The popover stays shown (and invisible) until the dialogue exists, so a failure anywhere gives core's popover back
 * whole. Focus returns to the icon when the dialogue closes, and the popover that focus would show is cancelled once.
 *
 * @param {Element} trigger The help icon.
 * @returns {Promise<void>}
 */
const swap = async(trigger) => {
    if (swapping) {
        // The first swap owns the tip: a second activation (a key that also clicks) must not reveal it.
        return;
    }
    const parts = partsOf(trigger);
    if (!parts || !parts.tip.classList.contains('show') || !overflows(parts.body)) {
        reveal(parts?.tip);
        return;
    }
    const {popover, tip, body} = parts;
    swapping = true;
    tip.classList.add(PENDING);
    const pending = new Pending('theme_dimensions/helpmodal:swap');
    try {
        const title = await heading(trigger);
        // Core's build hands a module with a lone default export back as that export itself, so the class is the
        // module here; the `default` read covers a build that keeps the namespace.
        const loaded = await import('core/modal');
        const Modal = loaded.default ?? loaded;
        const modal = await Modal.create({
            title: escaped(title),
            // The HTML Bootstrap wrote into the popover, through core's allow list: never fetched a second time.
            body: body.innerHTML,
            removeOnClose: true,
            returnElement: trigger,
            // The dialogue scrolls as a whole, so the keyboard scrolls it from the focus core/modal gives it.
            scrollable: false,
        });
        if (!tip.classList.contains('show') || !trigger.isConnected) {
            // Core closed the popover meanwhile (a press elsewhere, Escape): nothing is left to replace.
            modal.destroy();
            return;
        }
        modal.getRoot()[0].classList.add(ROOT);
        returning = trigger;
        popover.hide();
        await modal.show();
    } catch (error) {
        Log.debug(error);
        returning = null;
        reveal(tip);
    } finally {
        swapping = false;
        pending.resolve();
    }
};

/**
 * A press or Enter starts on a help icon: remember it, so the popover it shows can be kept invisible.
 *
 * Any other press or key outside the dialogue means the focus it gave back has been used or was lost: stop
 * cancelling the icon's next popover.
 *
 * @param {Event} e The pointerdown or keydown, in the capture phase.
 */
const start = (e) => {
    if (returning && !(e.target instanceof Element && e.target.closest(`.${ROOT}`))) {
        returning = null;
    }
    const primary = e.type === 'pointerdown' ? e.button === 0 : e.key === 'Enter' && !e.repeat;
    const trigger = primary ? triggerOf(e.target) : null;
    if (activating && activating !== trigger && !swapping) {
        // The earlier activation never reached its click or key: its tip is core's popover again.
        reveal(partsOf(activating)?.tip);
    }
    activating = trigger;
};

/**
 * A press that ends off the icon produces no click on it: show the tip it kept invisible.
 *
 * @param {PointerEvent} e The pointerup or pointercancel, in the capture phase.
 */
const abandon = (e) => {
    if (!activating || (e.type === 'pointerup' && e.target instanceof Node && activating.contains(e.target))) {
        return;
    }
    reveal(partsOf(activating)?.tip);
    activating = null;
};

/**
 * Bootstrap has put a help popover's tip in the page: measure it, and keep a long one under a press invisible.
 *
 * @param {Event} e The inserted.bs.popover event, on the icon.
 */
const inserted = (e) => {
    const trigger = triggerOf(e.target);
    if (!trigger || trigger !== activating) {
        return;
    }
    const parts = partsOf(trigger);
    if (parts && overflows(parts.body)) {
        parts.tip.classList.add(PENDING);
    }
};

/**
 * Cancel the one popover core's focusin handler shows when the dialogue gives the focus back to its icon.
 *
 * Core's theme_boost/pending opens a pending promise named after the END event on every show.bs.popover, on the
 * body, before this listener on the document runs, and closes it only when shown.bs.popover fires. A cancelled show
 * never fires it, so the promise it opened is closed here: left open, every later wait for the page to settle
 * (Behat's, and any script's) would run to its timeout.
 *
 * @param {Event} e The show.bs.popover event, on the icon.
 */
const showing = (e) => {
    if (returning && e.target === returning) {
        returning = null;
        e.preventDefault();
        M.util.js_complete('shown.bs.popover');
    }
};

/**
 * A click or Enter on a help icon has reached the window, after core's listeners on the document: swap if long.
 *
 * @param {Event} e The click or keydown, in the bubble phase on the window.
 */
const activate = (e) => {
    if (e.type === 'keydown' && (e.key !== 'Enter' || e.repeat)) {
        return;
    }
    const trigger = triggerOf(e.target);
    if (!trigger) {
        return;
    }
    activating = null;
    swap(trigger);
};

/**
 * Wire the document and the window once.
 */
export const init = () => {
    if (wired) {
        return;
    }
    wired = true;
    document.addEventListener('pointerdown', start, true);
    document.addEventListener('keydown', start, true);
    document.addEventListener('pointerup', abandon, true);
    document.addEventListener('pointercancel', abandon, true);
    document.addEventListener('inserted.bs.popover', inserted);
    document.addEventListener('show.bs.popover', showing);
    window.addEventListener('click', activate);
    window.addEventListener('keydown', activate);
};
