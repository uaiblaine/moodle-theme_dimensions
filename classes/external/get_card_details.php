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
 * Theme Dimensions - What the cards on screen learn about themselves
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
use theme_dimensions\local\category\enrichment;
use theme_dimensions\local\category\guard;

/**
 * The viewport batch: completion progress for the cards the reader can actually see.
 *
 * Unlike the other web services of the showcase, the ids are not validated but
 * filtered: an id this viewer may not see is dropped from the answer and never
 * named, because reporting "you may not see 41" answers "does 41 exist". So
 * there is no per-id refusal, and the property a test can check is that a
 * withheld id is absent from an answer that still carries an admitted one.
 *
 * The context validated is the system's. A batch spans up to
 * {@see enrichment::MAX_COURSES} courses, so there is no single course context
 * to validate, and validating each course's own would run require_login()
 * against it, which throws for anybody not enrolled - the ordinary case of
 * every card on a category page. The per-course question (core's visibility
 * pass plus local_unlistedcourses' predicate) is asked inside
 * {@see enrichment::details()}.
 *
 * The batch cap is checked before login: it is a question about the shape of
 * the request, says nothing about the site, and stops one call asking about a
 * whole category.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_card_details extends external_api {
    /** @var string Component name for the refusal. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * The parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'A course id of the batch.'),
                'The courses on screen, at most ' . enrichment::MAX_COURSES . ' of them.'
            ),
        ]);
    }

    /**
     * What the cards of this batch can say once they are on screen.
     *
     * @param array $courseids The course ids of the batch.
     * @return array The details, one entry per id the viewer may see.
     * @throws \invalid_parameter_exception On an empty or oversized batch.
     * @throws \moodle_exception The course refusal, for the guest account.
     */
    public static function execute(array $courseids): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseids' => $courseids]);

        // The cap, before login: the shape of the request, never a fact about the site.
        $ids = $params['courseids'];
        if (!$ids || count($ids) > enrichment::MAX_COURSES) {
            throw new \invalid_parameter_exception(
                'A batch holds one to ' . enrichment::MAX_COURSES . ' courses.'
            );
        }

        require_login();
        if (isguestuser()) {
            throw new \moodle_exception(guard::ERROR_COURSE, self::COMPONENT);
        }
        // The batch spans courses; the context of the call itself is the site's. See the class docblock.
        self::validate_context(\core\context\system::instance());

        return ['details' => enrichment::details($ids)];
    }

    /**
     * What the function returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'details' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'The course id.'),
                    'hascompletion' => new external_value(PARAM_BOOL, 'Whether completion is on in the course.'),
                    'progress' => new external_value(
                        PARAM_INT,
                        'The percentage complete, null when there is no number for this viewer.',
                        VALUE_OPTIONAL,
                        null,
                        NULL_ALLOWED
                    ),
                    'progresslabel' => new external_value(
                        PARAM_TEXT,
                        'The localised sentence to paint beside the badge, absent without a number.',
                        VALUE_OPTIONAL
                    ),
                ]),
                'One entry per course of the batch this viewer may see; the others are dropped in silence.'
            ),
        ]);
    }
}
