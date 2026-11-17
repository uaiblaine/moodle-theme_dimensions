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
 * The category navigation's row of nav pills: the entry the core renderer's full_header() arms.
 *
 * `js_call_amd` can only name an AMD module, so this one hands the row to the TypeScript module
 * (js/esm/src/navpills/row.ts) through core's ES module loader plugin, as the listing's two entries do.
 * It is armed only for a row with a menu to open: a group, or More with nodes of its own.
 *
 * @module     theme_dimensions/navpills
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {init as arm} from 'core/esm!@moodle/lms/theme_dimensions/navpills/row';

/**
 * Arm the row drawn under this id.
 *
 * @param {String} uniqid The row's id, its nav's id attribute.
 * @return {void}
 */
export const init = (uniqid) => arm(uniqid);
