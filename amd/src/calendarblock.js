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
 * The month calendar block's entry: the one core_calendar_renderer arms when the theme draws the block.
 *
 * `js_call_amd` can only name an AMD module, so this one hands the page to the TypeScript client
 * (js/esm/src/calendar/block.ts) through core's ES module loader plugin.
 *
 * @module     theme_dimensions/calendarblock
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {start} from 'core/esm!@moodle/lms/theme_dimensions/calendar/block';

/**
 * Arm every month block the theme drew on the page.
 *
 * @return {void}
 */
export const init = () => start();
