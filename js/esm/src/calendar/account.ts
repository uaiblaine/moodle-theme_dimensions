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
 * The logged-in calendar's two services, kept out of the modules the visitor's calendar shares.
 *
 * The page's shell and the blocks' client import them; nothing a visitor's page loads may (the visitor's import walk,
 * tests/local/category/categorypublic_source_test.php, holds that).
 *
 * @module     theme_dimensions/calendar/account
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** The range service of a logged-in account. */
export const RANGE_METHOD = 'theme_dimensions_get_calendar_range';

/** The service that stores the account's state. */
export const REMEMBER_METHOD = 'theme_dimensions_remember_calendar_state';

/** The Trilhas view's service: the trails of one month (docs/trail/PLAN.md section 7). */
export const TRAILS_METHOD = 'theme_dimensions_get_calendar_trails';
