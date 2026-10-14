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
 * Submits the guest entry's form as soon as the interstitial page loads.
 *
 * The form is the whole mechanism and works without this module: its button posts the same
 * fields. This module only saves the visitor the press. It submits once, and through
 * requestSubmit() where the browser has it, so the form's own submit event and validation run as
 * they would for the button; a robot that runs no script never submits anything, which is what
 * keeps a link preview from opening a guest session.
 *
 * It does not submit when the page was reached through the browser's history. The visitor who
 * presses Back on the course page would otherwise be forwarded straight back into the course,
 * a page they could never leave backwards; from history they get the card and its button.
 *
 * @module     theme_dimensions/hotsiteguest
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Whether this document was reached through the browser's Back or Forward buttons.
 *
 * @return {Boolean} True for a history traversal.
 */
const fromHistory = () => {
    if (!window.performance || typeof window.performance.getEntriesByType !== 'function') {
        return false;
    }
    const entries = window.performance.getEntriesByType('navigation');
    return entries.length > 0 && entries[0].type === 'back_forward';
};

/**
 * Submit the form with this id, once, unless the page came from history.
 *
 * @param {String} formid The id of the interstitial's form.
 * @return {void}
 */
export const init = (formid) => {
    const form = document.getElementById(formid);
    if (!form || form.dataset.dimensionsSubmitted || fromHistory()) {
        return;
    }
    form.dataset.dimensionsSubmitted = '1';
    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
    } else {
        form.submit();
    }
};
