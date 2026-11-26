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
 * A hold on Moodle's pending-JavaScript counter, the one Behat waits on, taken at once and released once.
 *
 * Core's ajax holds the counter only while a request is in flight; between its answer and the render that
 * draws it, and across a write followed by the reads it causes, nothing would be pending and Behat would read
 * the page as settled before the rows exist. Core 5.3's Pending (`@moodle/lms/core/pending`) takes the counter
 * in its constructor, synchronously. local_evidencehub's copy calls M.util itself; the theme's tree uses core's
 * module, as its other components do.
 *
 * @module     theme_dimensions/datareport/pending
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from '@moodle/lms/core/pending';

/**
 * Take a hold; the returned function releases it, once, however many times it is called.
 *
 * @param {string} key The name the hold is counted under.
 * @returns {Function} Releases the hold.
 */
export const hold = (key: string): (() => void) => {
    const pending = new Pending(key);
    let held = true;

    return () => {
        if (held) {
            held = false;
            pending.resolve();
        }
    };
};
