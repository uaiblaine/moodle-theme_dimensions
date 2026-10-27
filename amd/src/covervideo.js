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
 * The cover film's entry: what the page arms, so that the film's module loads on first use.
 *
 * The film itself is theme_dimensions/local/coverfilm. This module is what the page arms, on both
 * shells and for everybody, and it does three things only:
 *
 * - It marks the body (dim-cover-js), which reveals the cluster and the progress bar, as the film's
 *   module did when it was armed with the page.
 * - It imports the film on the first intent towards a cover: a pointer over one, a focus inside one
 *   or a finger on one (the capture phase, so nothing in the card can hide the intent from it). A
 *   page whose covers nobody approaches never loads the film at all.
 * - It holds a press on a control of the cluster that arrives before the film has loaded: the
 *   press is stopped, and given again to the same control once the film is armed, so no control
 *   is ever a button that does nothing. A pointer still resting on the band when the film arrives
 *   is told again too, since the hover that started the load came before the film's listener.
 *
 * The load is pending while it runs, so nothing that waits for the page (Behat) reads it half-done.
 * It calls no web service, and imports core/pending and the film alone.
 *
 * @module     theme_dimensions/covervideo
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from 'core/pending';

/** @type {Object} The hooks this module reads: the band and the cluster's controls, both of the film's template. */
const SELECTORS = {
    media: '[data-region="dim-cover-media"]',
    control: '[data-action^="dim-cover-"]',
};

/** @type {String} The film's body class: the cluster and the progress bar are hidden without it. */
const BODY_CLASS = 'dim-cover-js';

/** @type {String[]} The intents that load the film, listened for in the capture phase until it has loaded. */
const INTENTS = ['pointerover', 'focusin', 'pointerdown'];

/** @type {Boolean} Whether the listeners are registered. */
let armed = false;

/** @type {Promise|null} The film's module once its import has started. */
let film = null;

/** @type {Boolean} Whether the film is armed, so that a press needs holding no more. */
let ready = false;

/**
 * The band an event happened in, or null.
 *
 * @param {Event} event The event.
 * @return {Element|null}
 */
const band = (event) => (event.target instanceof Element ? event.target.closest(SELECTORS.media) : null);

/**
 * Import and arm the film, once; the listeners of this module go when it is armed.
 *
 * @return {Promise} Resolving once the film listens.
 */
const load = () => {
    if (film === null) {
        const pending = new Pending('theme_dimensions/covervideo:load');
        film = import('theme_dimensions/local/coverfilm')
            .then((Film) => {
                Film.init();
                ready = true;
                INTENTS.forEach((type) => document.removeEventListener(type, onIntent, true));
                document.removeEventListener('click', onPress, true);
                return Film;
            })
            .finally(() => pending.resolve());
    }
    return film;
};

/**
 * An intent towards a cover: load the film, and tell it of a pointer still resting on the band.
 *
 * Only a pointer's arrival is told again: a focus or a finger needs nothing from the film until the
 * press it leads to, which onPress() holds. The pointer is told only while the band is still under
 * it (:hover), or the film would start under a pointer that has already left, with no departure
 * left to stop it.
 *
 * @param {Event} event The pointerover, focusin or pointerdown.
 * @return {void}
 */
const onIntent = (event) => {
    const where = band(event);
    if (!where) {
        return;
    }
    const target = event.target;
    load().then(() => {
        if (event.type === 'pointerover' && where.matches(':hover') && target.isConnected) {
            target.dispatchEvent(new PointerEvent('pointerover', {
                bubbles: true,
                composed: true,
                pointerType: event.pointerType,
                isPrimary: event.isPrimary,
            }));
        }
        return null;
    }).catch(() => null);
};

/**
 * A press on a control of the cluster before the film has loaded: hold it, and give it back after.
 *
 * @param {MouseEvent} event The click.
 * @return {void}
 */
const onPress = (event) => {
    const where = band(event);
    const control = where ? event.target.closest(SELECTORS.control) : null;
    if (ready || !control) {
        return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    load().then(() => {
        if (control.isConnected) {
            control.click();
        }
        return null;
    }).catch(() => null);
};

/**
 * Arm the entry: mark the body and wait for the first intent towards a cover. Idempotent.
 *
 * @return {void}
 */
export const init = () => {
    if (armed) {
        return;
    }
    armed = true;
    document.body.classList.add(BODY_CLASS);
    INTENTS.forEach((type) => document.addEventListener(type, onIntent, true));
    document.addEventListener('click', onPress, true);
};
