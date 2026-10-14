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
 * Theme Dimensions - The star of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use theme_dimensions\local\category\guard;
use theme_dimensions\local\category\wishlist;

/**
 * The one write of the showcase: star or unstar a course of the listing.
 *
 * Only the order of the checks lives here: validate_parameters(),
 * require_login() and the guest account's refusal - the same refusal every
 * miss carries - then {@see guard::course()}, which refuses the site course,
 * derives the course context with a non-throwing lookup, validates its
 * category's context, asks core's visibility question and then the plugin's
 * course predicate, and finally the delegate, which writes core's own
 * favourite row and fires the event when the state changed.
 *
 * The write is idempotent in both directions and the delegate says why; the
 * answer is the state the course is in afterwards, so a client that raced
 * itself paints the truth rather than its own guess.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toggle_wishlist extends external_api {
    /** @var string Component name for the refusal. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * The parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course whose star was pressed.'),
            'state' => new external_value(PARAM_BOOL, 'True to star the course, false to unstar it.'),
        ]);
    }

    /**
     * Put the course in, or out of, this person's favourites.
     *
     * @param int $courseid The course whose star was pressed.
     * @param bool $state True to star it, false to unstar it.
     * @return array The course and the state it is now in.
     * @throws \moodle_exception The one refusal, for every course this viewer may not discover.
     */
    public static function execute(int $courseid, bool $state): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'state' => $state,
        ]);

        require_login();
        if (isguestuser()) {
            throw new \moodle_exception(guard::ERROR_COURSE, self::COMPONENT);
        }

        $course = guard::course((int) $params['courseid']);

        return [
            'courseid' => (int) $course->id,
            'favourite' => wishlist::toggle((int) $course->id, (bool) $params['state']),
        ];
    }

    /**
     * What the function returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'The course whose star was pressed.'),
            'favourite' => new external_value(PARAM_BOOL, 'Whether the course is now in this person\'s favourites.'),
        ]);
    }
}
