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
 * An account's live listing on a category page: the entry course_renderer.php arms for a real account.
 *
 * `js_call_amd` can only name an AMD module, so this one hands the page to the TypeScript shell
 * (js/esm/src/listing/account.ts) through core's ES module loader plugin. It also arms the star of every card
 * on the page, the panel's included: categorystar stays AMD until R5, outside the ESM tree and its bridge.
 *
 * @module     theme_dimensions/listingaccount
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as CategoryStar from 'theme_dimensions/categorystar';
import {start} from 'core/esm!@moodle/lms/theme_dimensions/listing/account';

/**
 * Arm an account's live listing and its stars.
 *
 * @return {void}
 */
export const init = () => {
    // One delegated listener, armed once: categorystar's init() is idempotent.
    CategoryStar.init();
    start();
};
