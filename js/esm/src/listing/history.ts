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
 * The one writer of the showcase's history entries in js/esm/src.
 *
 * On two disjoint sets of parameters: the listing's criteria ({@see writeUrl}) and the panel's open area and
 * order, replaced and never pushed ({@see replaceKeeping}). listing_source_test fails a write anywhere else.
 *
 * @module     theme_dimensions/listing/history
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {buildUrl, syncFold} from './address';
import type {ListingState} from './address';

/**
 * Write a state into the address bar, and bring the fold form in line with it.
 *
 * @param {object} state The calling client's state.
 * @param {boolean} push Whether this is a new history entry rather than a correction of this one.
 * @returns {void}
 */
export const writeUrl = (state: ListingState, push: boolean): void => {
    const url = buildUrl(state).toString();
    if (push) {
        window.history.pushState({}, '', url);
    } else {
        window.history.replaceState({}, '', url);
    }
    syncFold();
};

/**
 * Replace this history entry's address, keeping the entry's state: a write that adds no entry the reader
 * would have to go back through.
 *
 * @param {string} url The whole address.
 * @returns {void}
 */
export const replaceKeeping = (url: string): void => {
    window.history.replaceState(window.history.state, '', url);
};
