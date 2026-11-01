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
 * The one writer of the calendar page's address.
 *
 * The listing's own writer (listing/history.ts) keeps the showcase's forms in step after each write, which this page
 * has none of; so the calendar has its own, and nothing else of calendar/ touches the history
 * (listing_source_test::test_only_the_history_module_writes_the_address names the two). A month moved pushes an
 * entry, so Back returns to the month before; a change of keys or filters replaces it, so Back leaves the page rather
 * than undoing every chip.
 *
 * @module     theme_dimensions/calendar/history
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Write the page's address.
 *
 * @param {string} url The address.
 * @param {boolean} push Whether it is a new entry (a month moved) or a correction of this one.
 * @returns {void}
 */
export const writeAddress = (url: string, push: boolean): void => {
    if (url === window.location.href) {
        return;
    }
    if (push) {
        window.history.pushState({}, '', url);
    } else {
        window.history.replaceState({}, '', url);
    }
};
