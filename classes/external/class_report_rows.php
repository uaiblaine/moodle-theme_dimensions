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
 * Theme Dimensions - A page of the class report of competency ratings
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
 * A page of the class report's learners, read headlessly (theme_dimensions_class_report_rows).
 *
 * The course comes from the client and the whole access rule runs on it first ({@see classreportengine::locate()}:
 * the course, validate_context(), guests, competencies enabled, the class report's may_view()); then the report is
 * built with the activity, the group and the search as its own parameters, so its can_view(), its base conditions and
 * its group rule run, and the rows are the system report's own, through the table core builds for it
 * ({@see classreportengine::rows()}). A posted group goes through the report's group rule, which narrows a group the
 * reader may not read to no row. A client sets the filters with core_reportbuilder_set_filters and reads the rows
 * again. Writes nothing to the session: read-only.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class class_report_rows extends external_api {
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
                'The group, 0 for all participants, -1 for the one chosen on the page',
                VALUE_DEFAULT,
                -1
            ),
            'search' => new external_value(PARAM_TEXT, 'Part of the learner\'s name, two characters or more', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'The page, from 0', VALUE_DEFAULT, 0),
            'perpage' => new external_value(
                PARAM_INT,
                'Rows per page: 30, 50 or 100',
                VALUE_DEFAULT,
                classreportengine::DEFAULT_PERPAGE
            ),
            'sort' => new external_value(PARAM_RAW, 'The column key to sort by, empty for the report\'s sort', VALUE_DEFAULT, ''),
            'dir' => new external_value(PARAM_ALPHA, 'asc or desc', VALUE_DEFAULT, 'asc'),
            'countfacets' => new external_value(PARAM_BOOL, 'Whether to count the filter panel\'s options', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Read a page of learners.
     *
     * @param int $courseid The course.
     * @param int $moduleid An activity, or 0.
     * @param int $groupid A group, 0 for all participants, -1 for the session's choice.
     * @param string $search Part of a name.
     * @param int $page The page, from 0.
     * @param int $perpage Rows per page.
     * @param string $sort The column key to sort by.
     * @param string $dir asc or desc.
     * @param bool $countfacets Whether to count the filter panel's options.
     * @return array See execute_returns().
     * @throws \core_reportbuilder\exception\report_access_exception When the reader may not read the class report.
     * @throws \moodle_exception For a guest, and with competencies disabled.
     */
    public static function execute(
        int $courseid,
        int $moduleid = 0,
        int $groupid = -1,
        string $search = '',
        int $page = 0,
        int $perpage = classreportengine::DEFAULT_PERPAGE,
        string $sort = '',
        string $dir = 'asc',
        bool $countfacets = true
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'moduleid' => $moduleid,
            'groupid' => $groupid,
            'search' => $search,
            'page' => $page,
            'perpage' => $perpage,
            'sort' => $sort,
            'dir' => $dir,
            'countfacets' => $countfacets,
        ]);
        $context = classreportengine::locate($params['courseid']);
        $report = classreportengine::report($context, $params['moduleid'], $params['groupid'], $params['search']);

        return classreportengine::rows($report, [
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'sort' => $params['sort'],
            'direction' => $params['dir'],
            'countfacets' => $params['countfacets'],
        ]);
    }

    /**
     * What comes back: an allowlist.
     *
     * Titles, labels and names are plain, which the client renders as text; cells are the report's formatted HTML,
     * escaped by its formatters.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'The course'),
            'moduleid' => new external_value(PARAM_INT, 'The activity applied, 0 for none'),
            'groupid' => new external_value(PARAM_INT, 'The group asked for, -1 for the one chosen on the page'),
            'search' => new external_value(PARAM_RAW, 'The search applied, empty for none'),
            'nogroup' => new external_value(PARAM_BOOL, 'Whether the group rule leaves the reader no learner'),
            'sort' => new external_value(PARAM_RAW, 'The column key sorted by, empty for the report\'s own sort'),
            'direction' => new external_value(PARAM_ALPHA, 'asc, desc, or empty'),
            'page' => new external_value(PARAM_INT, 'The page returned, from 0'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page'),
            'total' => new external_value(PARAM_INT, 'Learners across every page'),
            'competencies' => new external_value(PARAM_INT, 'The competencies of the course, or of the activity'),
            'columns' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_RAW, 'Column key: learner, identity:<field> or comp:<competency id>'),
                'title' => new external_value(PARAM_RAW, 'Title, plain'),
                'sortable' => new external_value(PARAM_BOOL, 'Whether it sorts'),
                'default' => new external_value(PARAM_BOOL, 'Whether the default set has it'),
                'active' => new external_value(PARAM_BOOL, 'Whether the report shows it'),
                'fixed' => new external_value(PARAM_BOOL, 'Whether it is always shown'),
            ])),
            'rows' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'The learner'),
                'url' => new external_value(PARAM_URL, 'The learner\'s breakdown'),
                'ratings' => new external_multiple_structure(new external_single_structure([
                    'key' => new external_value(PARAM_RAW, 'Column key, comp:<competency id>'),
                    'competencyid' => new external_value(PARAM_INT, 'The competency'),
                    'grade' => new external_value(
                        PARAM_INT,
                        'The rating on the competency\'s scale, from 1; null when not rated',
                        VALUE_REQUIRED,
                        null,
                        NULL_ALLOWED
                    ),
                    'proficient' => new external_value(PARAM_BOOL, 'Whether the rating is proficient'),
                ])),
                'cells' => new external_multiple_structure(new external_single_structure([
                    'key' => new external_value(PARAM_RAW, 'Column key'),
                    'html' => new external_value(PARAM_RAW, 'Formatted cell'),
                ])),
            ])),
            'counted' => new external_value(PARAM_BOOL, 'Whether the filters\' options carry their counts'),
            'filters' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_RAW, 'Filter unique identifier'),
                'title' => new external_value(PARAM_RAW, 'Title, plain'),
                'type' => new external_value(PARAM_ALPHANUMEXT, 'Filter class name'),
                'facet' => new external_value(PARAM_ALPHA, 'The panel\'s facet: choice, or empty for none'),
                'options' => new external_multiple_structure(new external_single_structure([
                    'value' => new external_value(PARAM_RAW, 'Value'),
                    'label' => new external_value(PARAM_RAW, 'Label, plain'),
                    'count' => new external_value(
                        PARAM_INT,
                        'Learners with this option under every other criterion, -1 when not counted'
                    ),
                ])),
                'applied' => new external_value(PARAM_BOOL, 'Whether the reader\'s stored values narrow the rows'),
                'values' => new external_value(PARAM_RAW, 'The reader\'s stored values for it, JSON'),
            ])),
            'preference' => class_report_preference_structure::get(),
        ]);
    }
}
