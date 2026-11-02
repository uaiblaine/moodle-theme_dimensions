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
 * Theme Dimensions - The content of one course's "More information" dialogue
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
use local_unlistedcourses\access;
use theme_dimensions\local\category\dialogue;
use theme_dimensions\local\category\guard;

/**
 * What a course's dialogue shows beyond its card, fetched when a reader asks for it: one course per call.
 *
 * The answer is content only - the excerpt, the fact tiles, the programme's first lines and their two
 * labels ({@see dialogue::content()}). Never a state, a button or an address: those are the page's
 * decision, carried by the card the dialogue opened from, so the dialogue cannot contradict its card.
 *
 * The order is a security property:
 *
 * 1. validate_parameters().
 * 2. require_login(), and the guest refused with the showcase's course refusal, before any lookup: every
 *    service of the account's shell refuses the guest, who is never armed with this module either.
 * 3. validate_context() on the SYSTEM context. A course's own context would run require_login() against
 *    it, which throws for anybody not enrolled - the ordinary reader of a showcase - and its category's
 *    context would be a lookup of the course before the refusal below; the right to see a course is asked
 *    of the course itself in step 4.
 * 4. Discoverability exactly as {@see \theme_dimensions\local\category\enrichment::details()} decides it
 *    for the cards on screen: core's can_view_course_info() first, then local_unlistedcourses'
 *    filter_courses(). The plugin's predicate answers true wholesale when nothing is unlisted and for a
 *    site admin, so it is a layer on top of core's question, never instead of it.
 * 5. One refusal for every miss: the site course (which core's question admits outright), an id nothing
 *    holds, a course core hides and one the plugin withholds answer the same exception with the same
 *    message ({@see guard::ERROR_COURSE}), so the service is no oracle over course ids.
 * 6. Then, and only then, the data.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_course_dialogue extends external_api {
    /** @var string Component name for the refusal. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * The parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course whose dialogue is opened.'),
        ]);
    }

    /**
     * The content of one course's dialogue, for a reader the showcase would show the course to.
     *
     * @param int $courseid The course.
     * @return array The content ({@see dialogue::content()}).
     * @throws \moodle_exception The course refusal, for the guest and for every course the page would not list.
     */
    public static function execute(int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);

        require_login();
        if (isguestuser()) {
            throw new \moodle_exception(guard::ERROR_COURSE, self::COMPONENT);
        }
        // One course, but the context of the call is the site's: see the class docblock, step 3.
        self::validate_context(\core\context\system::instance());

        $courseid = (int) $params['courseid'];
        // Core's question below answers true outright for the site course, which is never on the showcase.
        if ($courseid === (int) SITEID) {
            throw new \moodle_exception(guard::ERROR_COURSE, self::COMPONENT);
        }
        $course = $DB->get_record('course', ['id' => $courseid], dialogue::COURSE_FIELDS);
        if (!$course || !\core_course_category::can_view_course_info($course)) {
            throw new \moodle_exception(guard::ERROR_COURSE, self::COMPONENT);
        }
        // The plugin's course term after core's, as enrichment::details() asks it.
        if (!access::filter_courses([$courseid => $course])) {
            throw new \moodle_exception(guard::ERROR_COURSE, self::COMPONENT);
        }

        return dialogue::content($course);
    }

    /**
     * What the function returns. Card text is PARAM_RAW: PARAM_TEXT would throw for the whole answer on one
     * decoded `&lt;` in a summary or a programme line.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'excerpt' => new external_value(PARAM_RAW, 'The course summary reduced to one shortened paragraph, plain.'),
            'hasexcerpt' => new external_value(PARAM_BOOL, 'Whether there is an excerpt.'),
            'facts' => new external_multiple_structure(
                new external_single_structure([
                    'label' => new external_value(PARAM_RAW, 'The tile\'s label, as the site stored the field name.'),
                    'value' => new external_value(PARAM_RAW, 'The tile\'s value, plain.'),
                    'icon' => new external_value(PARAM_ALPHANUMEXT, 'The Font Awesome icon of the tile, decorative.'),
                ]),
                'The fact tiles: the date or the workload, the modality, the place, the contacts.'
            ),
            'hasfacts' => new external_value(PARAM_BOOL, 'Whether there are fact tiles.'),
            'factslabel' => new external_value(PARAM_TEXT, 'The label of the fact tiles.'),
            'learn' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'One line of the programme, plain.'),
                'The first lines of the programme, one checklist item each.'
            ),
            'haslearn' => new external_value(PARAM_BOOL, 'Whether there is a programme to list.'),
            'learnlabel' => new external_value(PARAM_TEXT, 'The heading of that checklist.'),
        ]);
    }
}
