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
 * Theme Dimensions - The band of the class report of competency ratings
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

/**
 * The band of the class report (theme_dimensions_class_report_summary).
 *
 * The figures are read over the rows the rows call would list ({@see classreportengine::summary()}): the same report,
 * built with the same activity, group and search behind the same door ({@see classreportengine::locate()}), so a
 * group-limited grader's figures are their rows'. Read-only.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class class_report_summary extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course'),
            'moduleid' => new external_value(
                PARAM_INT,
                'An activity whose competencies are the columns, 0 for none',
                VALUE_DEFAULT,
                0
            ),
            'groupid' => new external_value(
                PARAM_INT,
                'The group, 0 for every group the reader may read; never negative',
                VALUE_DEFAULT,
                0
            ),
            'search' => new external_value(PARAM_TEXT, 'Part of the learner\'s name, two characters or more', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Compute the band's figures.
     *
     * @param int $courseid The course.
     * @param int $moduleid An activity, or 0.
     * @param int $groupid A group, 0 for every group the reader may read; negative is refused.
     * @param string $search Part of a name.
     * @return array See execute_returns().
     * @throws \core_reportbuilder\exception\report_access_exception When the reader may not read the class report.
     * @throws \moodle_exception For a guest, and with competencies disabled.
     */
    public static function execute(int $courseid, int $moduleid = 0, int $groupid = 0, string $search = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'moduleid' => $moduleid,
            'groupid' => $groupid,
            'search' => $search,
        ]);
        $context = classreportengine::locate($params['courseid']);

        return classreportengine::summary(
            classreportengine::report($context, $params['moduleid'], $params['groupid'], $params['search'])
        );
    }

    /**
     * What comes back: an allowlist. Titles are plain.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'The course'),
            'moduleid' => new external_value(PARAM_INT, 'The activity applied, 0 for none'),
            'groupid' => new external_value(PARAM_INT, 'The group asked for, 0 for every group the reader may read'),
            'search' => new external_value(PARAM_RAW, 'The search applied, empty for none'),
            'nogroup' => new external_value(PARAM_BOOL, 'Whether the group rule leaves the reader no learner'),
            'learners' => new external_value(PARAM_INT, 'Learners listed'),
            'cells' => new external_value(PARAM_INT, 'Cells of the shown competency columns: learners times columns'),
            'rated' => new external_value(PARAM_INT, 'Rated cells of the shown competency columns'),
            'proficient' => new external_value(PARAM_INT, 'Proficient cells of the shown competency columns'),
            'unrated' => new external_value(PARAM_INT, 'Learners with no rating in any shown competency column'),
            'competencies' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_RAW, 'Column key, comp:<competency id>'),
                'competencyid' => new external_value(PARAM_INT, 'The competency'),
                'title' => new external_value(PARAM_RAW, 'Its name, plain'),
                'proficient' => new external_value(PARAM_INT, 'Learners rated proficient'),
                'notproficient' => new external_value(PARAM_INT, 'Learners rated, not proficient'),
                'notrated' => new external_value(PARAM_INT, 'Learners not rated'),
            ])),
        ]);
    }
}
