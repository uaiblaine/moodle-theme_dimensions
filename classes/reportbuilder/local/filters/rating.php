<?php
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
 * Theme Dimensions - The filter on one competency's rating in the class report
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\reportbuilder\local\filters;

use core_reportbuilder\local\filters\select;

/**
 * Core's select filter, reading its options from the filter it was built with every time.
 *
 * Core's select keeps the options of a filter in a function static keyed by the filter's name, for the rest of the
 * process. A rating filter is named after its competency and its options are that competency's scale, so the name
 * of another course's or another test's competency with the same id would hand back the first scale's items, and a
 * value outside them filters nothing ({@see select::get_sql_filter()}). The options are an array already built in
 * the request, so reading them again costs nothing.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rating extends select {
    /**
     * The options of the filter as it was built in this request.
     *
     * @return array Value => label.
     */
    protected function get_select_options(): array {
        return (array) $this->filter->get_options();
    }
}
