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
 * The visitor's live listing on a public category page: the entry request.php arms.
 *
 * `js_call_amd` can only name an AMD module, so this one hands the page to the TypeScript shell
 * (js/esm/src/listing/visitor.ts) through core's ES module loader plugin, as core's own core/str does.
 * Nothing else may be imported here: the visitor's import graph starts at this file
 * (categorypublic_source_test walks it).
 *
 * @module     theme_dimensions/listingvisitor
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {start} from 'core/esm!@moodle/lms/theme_dimensions/listing/visitor';

/**
 * Arm the visitor's live listing.
 *
 * @param {Number} categoryid The category the page lists, 0 for the root of the course index.
 * @return {void}
 */
export const init = (categoryid) => start(categoryid);
