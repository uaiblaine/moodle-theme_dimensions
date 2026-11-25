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
 * Theme Dimensions - The extras of the course competencies page, after core re-renders it
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_competency\api;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use theme_dimensions\local\competency\coursecompetencies;

/**
 * The extras of one course's competencies page, for amd/src/coursecompetencies.js after core's tool_lp/competencies
 * re-rendered the page.
 *
 * Core re-renders tool_lp/course_competencies_page on the client from tool_lp_data_for_course_competencies_page after
 * adding, removing or re-ruling a competency and after the ratings setting changes, which carries none of the theme's
 * extras; this door gives them back. Every decision is {@see coursecompetencies}'s.
 *
 * The access rule is core's, in core's order: the course loaded from the id (the site course refused: the page has no
 * extras there), `require_login($course, false, null, false, true)` and `validate_context()` on the course's context
 * (what core's `data_for_course_competencies_page()` does through `validate_context()`), competencies enabled, then
 * `api::list_course_competencies()` itself, which refuses a hidden course to a viewer without
 * `moodle/course:viewhiddencourses` and a viewer holding neither `moodle/competency:coursecompetencyview` nor
 * `moodle/competency:coursecompetencymanage` (the guest role holds neither), with core's own exceptions. Which extras
 * come back is decided on the server: the class distribution only to a viewer who may see the whole class
 * ({@see \theme_dimensions\local\competency\coursecompetencies::sees_class()}), the
 * learner's figures only for the session user; no user id is a parameter.
 *
 * Read-only, so it raises no event.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_course_competency_extras extends external_api {
    /**
     * The parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course whose competencies page is drawn.'),
        ]);
    }

    /**
     * The extras of a course's competencies page for the session user.
     *
     * @param int $courseid The course.
     * @return array staff, learner and competencies.
     * @throws \dml_missing_record_exception For a course that does not exist.
     * @throws \core\exception\invalid_parameter_exception For the site course.
     * @throws \core\exception\require_login_exception When core's login to the course refuses the user.
     * @throws \moodle_exception With competencies disabled.
     * @throws \core\exception\required_capability_exception When the viewer may not view the course's competencies.
     */
    public static function execute(int $courseid): array {
        ['courseid' => $courseid] = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);

        $course = get_course($courseid);
        if ((int) $course->id === SITEID) {
            throw new \core\exception\invalid_parameter_exception('The site course has no course competencies page extras.');
        }
        require_login($course, false, null, false, true);
        $context = \core\context\course::instance((int) $course->id);
        self::validate_context($context);
        api::require_enabled();
        $competencyids = [];
        foreach (api::list_course_competencies($course) as $item) {
            $competencyids[] = (int) $item['competency']->get('id');
        }

        $extras = coursecompetencies::for_viewer($course, $competencyids);

        return [
            'staff' => $extras['staff'],
            'learner' => $extras['learner'],
            'competencies' => $extras['competencies'],
        ];
    }

    /**
     * What the function returns: an allowlist, the contexts of the theme's two templates.
     *
     * Every text is plain (counts, dates, core's outcome names), cleaned as PARAM_TEXT, and printed by the templates'
     * double stashes. The tone is one of {@see coursecompetencies::TONES}.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'staff' => new external_value(PARAM_BOOL, 'Whether the viewer is shown the class distribution.'),
            'learner' => new external_value(PARAM_BOOL, 'Whether the viewer is shown their own figures.'),
            'competencies' => new external_multiple_structure(
                new external_single_structure([
                    'competencyid' => new external_value(PARAM_INT, 'The competency.'),
                    'hasdistribution' => new external_value(PARAM_BOOL, 'Whether the class distribution is shown.'),
                    'haslearners' => new external_value(PARAM_BOOL, 'Whether the course has a learner to count.'),
                    'progresstextvalue' => new external_value(PARAM_TEXT, 'How many learners are proficient, plain.'),
                    'percentagevalue' => new external_value(PARAM_INT, 'The proficient share, 0 to 100.'),
                    'percentlabelvalue' => new external_value(PARAM_TEXT, 'That share as a label, plain.'),
                    'ratedtext' => new external_value(PARAM_TEXT, 'How many are rated, not proficient, plain.'),
                    'notratedtext' => new external_value(PARAM_TEXT, 'How many are not rated, plain.'),
                    'haslearner' => new external_value(PARAM_BOOL, 'Whether the viewer\'s own figures are shown.'),
                    'evidencetext' => new external_value(PARAM_TEXT, 'The viewer\'s evidence in this course, plain.'),
                    'activities' => new external_multiple_structure(
                        new external_single_structure([
                            'cmid' => new external_value(PARAM_INT, 'The activity.'),
                            'outcome' => new external_value(PARAM_INT, 'Its outcome for the competency.'),
                            'outcomename' => new external_value(PARAM_TEXT, 'Core\'s name of the outcome, plain.'),
                            'outcometone' => new external_value(PARAM_ALPHA, 'The status label tone.'),
                            'hasstate' => new external_value(PARAM_BOOL, 'Whether the viewer\'s state is shown.'),
                            'isdone' => new external_value(PARAM_BOOL, 'The viewer completed it.'),
                            'istodo' => new external_value(PARAM_BOOL, 'The viewer has not completed it.'),
                            'statetext' => new external_value(PARAM_TEXT, 'The viewer\'s state, plain.'),
                        ]),
                        'The listed activities linked to the competency.'
                    ),
                ]),
                'One entry per course competency, in core\'s order.'
            ),
        ]);
    }
}
