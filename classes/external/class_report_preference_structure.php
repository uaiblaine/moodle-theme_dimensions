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
 * Theme Dimensions - The shape of a reader's columns on the class report
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * The shape of a reader's columns and widths on the class report of one course, shared by the rows and by the
 * preference's writer; {@see \theme_dimensions\local\competency\classreportpreference::export()} fills it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class class_report_preference_structure {
    /**
     * The structure.
     *
     * @return external_single_structure Whether the default columns show, the chosen keys and the widths.
     */
    public static function get(): external_single_structure {
        return new external_single_structure([
            'usedefault' => new external_value(PARAM_BOOL, 'Whether the default columns show'),
            'columns' => new external_multiple_structure(new external_value(PARAM_RAW, 'Column key, comp:<competency id>')),
            'widths' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_RAW, 'Column key'),
                'width' => new external_value(PARAM_INT, 'Width in pixels'),
            ])),
        ]);
    }
}
