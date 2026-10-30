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
 * The favourite star of the category showcase's course cards, wherever a card is drawn.
 *
 * A card's star lives on the listing's grid, inside a panel between the posters and on the root's panels; the
 * account's shell arms it on every page of the logged-in showcase, the root's directory included ({@see ./account
 * start}). One delegated listener on the document, never one per card, so a card painted into a panel or appended
 * by "load more" behaves like a card the server drew; arm() is idempotent.
 *
 * The body class it writes is its own mark and shows the star; one module's mark never stands for another's
 * presence. The visitor's page never loads it (categorypublic_source_test).
 *
 * @module     theme_dimensions/listing/star
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import {load} from '../amd';

/** Every hook this module reads, written by the card template. */
const SELECTORS = {
    star: '[data-action="dim-star"]',
    starlabel: '[data-region="dim-star-label"]',
};

/** The body class that shows the star: this module's own mark. */
const BODY_CLASS = 'dim-star-js';

/** The theme's guarded wishlist service: never core's, which checks nothing a showcase must. */
const TOGGLE_METHOD = 'theme_dimensions_toggle_wishlist';

/** Whether the one listener is already installed. */
let armed = false;

/**
 * Paint one star's three faces: the pressed state, the glyph and the label of what it would do next.
 *
 * @param {HTMLElement} button The star button.
 * @param {boolean} on Whether the course is now a favourite.
 * @returns {void}
 */
const paintStar = (button: HTMLElement, on: boolean): void => {
    button.setAttribute('aria-pressed', on ? 'true' : 'false');
    const icon = button.querySelector('i');
    if (icon) {
        icon.classList.toggle('fa-solid', on);
        icon.classList.toggle('fa-regular', !on);
    }
    const label = button.querySelector(SELECTORS.starlabel);
    const text = on ? button.dataset.starRemove : button.dataset.starAdd;
    if (label && text) {
        label.textContent = text;
    }
};

/**
 * Flip a star optimistically, then tell the server - and put it back if the server refused.
 *
 * The favourites toggle's count is not refreshed here: it is one number the reader has just changed themselves,
 * and it comes right with the next listing request.
 *
 * @param {HTMLElement} button The star button.
 * @returns {Promise} Resolving when the round trip has finished.
 */
const toggleStar = async(button: HTMLElement): Promise<void> => {
    if (button.dataset.busy === '1') {
        // Two clicks in flight race in the server, and the last to arrive decides the stored state, which need not
        // be the last the reader asked for.
        return;
    }
    button.dataset.busy = '1';
    const courseid = Number(button.dataset.courseid);
    const wanted = button.getAttribute('aria-pressed') !== 'true';
    paintStar(button, wanted);
    try {
        const response = await fetchOne<{favourite: boolean}>({
            methodname: TOGGLE_METHOD,
            args: {courseid: courseid, state: wanted},
        });
        paintStar(button, Boolean(response.favourite));
    } catch (error) {
        paintStar(button, !wanted);
        const Notification = await load('core/notification');
        Notification.exception(error);
    } finally {
        button.dataset.busy = '0';
    }
};

/**
 * Arm the star: its mark on the body and one delegated listener. Safe to call more than once.
 *
 * @returns {void}
 */
export const arm = (): void => {
    document.body.classList.add(BODY_CLASS);
    if (armed) {
        return;
    }
    armed = true;
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const star = target.closest<HTMLElement>(SELECTORS.star);
        if (!star) {
            return;
        }
        event.preventDefault();
        toggleStar(star);
    });
};
