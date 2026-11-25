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
 * Theme Dimensions - Store a reader's columns on the class report of a course
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use theme_dimensions\local\competency\classreportengine;
use theme_dimensions\local\competency\classreportpreference;

/**
 * Store the reader's columns and widths on the class report of one course
 * (theme_dimensions_set_class_report_preference).
 *
 * The competencies chosen and the widths dragged, in one user preference per course ({@see classreportpreference});
 * the report reads it in initialise() and intersects it with the course's competencies, so a stored key never adds a
 * column the course does not have. Taken behind the class report's own door ({@see classreportengine::locate()}),
 * without building the report: core keeps one instance of a report per request, so a report built here would keep
 * the old columns for a rows call batched after this one. Somebody who may not read the class report stores nothing.
 *
 * The preference's one writer: core's preference services may not write it
 * ({@see classreportpreference::preference_editable()}). No event: a reader's display preference, as core's own table
 * preferences, which log nothing.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_class_report_preference extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course'),
            'usedefault' => new external_value(
                PARAM_BOOL,
                'Show the default columns and widths, forgetting the choice',
                VALUE_DEFAULT,
                false
            ),
            'columns' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Column key, comp:<competency id>'),
                'The chosen competency columns, in order',
                VALUE_DEFAULT,
                []
            ),
            'widths' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_RAW, 'Column key'),
                    'width' => new external_value(PARAM_INT, 'Width in pixels'),
                ]),
                'Every width the reader set; empty for the default widths',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Store the choice.
     *
     * @param int $courseid The course.
     * @param bool $usedefault Whether to forget the choice.
     * @param array $columns The chosen keys.
     * @param array $widths Each: key, width.
     * @return array What was stored; see execute_returns().
     * @throws \core_reportbuilder\exception\report_access_exception When the reader may not read the class report.
     * @throws \core\exception\invalid_parameter_exception For more than 200 columns or 100 widths.
     * @throws \moodle_exception For a guest, and with competencies disabled.
     */
    public static function execute(int $courseid, bool $usedefault = false, array $columns = [], array $widths = []): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'usedefault' => $usedefault,
            'columns' => $columns,
            'widths' => $widths,
        ]);
        // Bounded before anything is cleaned or stored: a list this long is no reader's choice.
        if (
            count($params['columns']) > classreportpreference::MAX_POSTED_COLUMNS
            || count($params['widths']) > classreportpreference::MAX_POSTED_WIDTHS
        ) {
            throw new \core\exception\invalid_parameter_exception('Too many columns or widths.');
        }
        $context = classreportengine::locate($params['courseid']);
        $courseid = (int) $context->instanceid;

        if ($params['usedefault']) {
            return classreportpreference::export(classreportpreference::use_default($courseid));
        }
        $sizes = [];
        foreach ($params['widths'] as $width) {
            $sizes[(string) $width['key']] = $width['width'];
        }

        return classreportpreference::export(classreportpreference::write($courseid, $params['columns'], $sizes));
    }

    /**
     * What comes back: the choice as stored, cleaned.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return class_report_preference_structure::get();
    }
}
