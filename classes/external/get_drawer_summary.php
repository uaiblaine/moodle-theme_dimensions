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
 * Theme Dimensions - What the mobile drawer learns once it is shown
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
use theme_dimensions\local\drawersummary;

/**
 * The mobile drawer's deferred summary: the "Continue where you left off" card.
 *
 * Asked by amd/src/drawer.js the first time Boost's primary drawer is shown, never on page load. Every
 * decision is {@see drawersummary}'s; this class is the door.
 *
 * It takes no parameter and answers about the session's own user only, so there is nothing a caller can
 * name to learn about anybody else. A real account only: the guest account is refused after login, as the
 * drawer is never drawn for it (core counts the guest as logged in, so require_login() alone admits it).
 * The context validated is the system's: the answer spans courses, and validating one course's context
 * would run require_login() against it.
 *
 * Read-only, so it raises no event: it opens nothing, writes no preference and changes no state the
 * person could be told about. The one write on its path is the recently accessed items helper deleting
 * the record of a course module that no longer exists, which is that block's own housekeeping.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_drawer_summary extends external_api {
    /**
     * The parameters of the function: none.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * The current user's summary.
     *
     * @return array resume.
     * @throws \require_login_exception Without a session.
     * @throws \moodle_exception For the guest account.
     */
    public static function execute(): array {
        self::validate_parameters(self::execute_parameters(), []);

        require_login(null, false, null, false, true);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        self::validate_context(\core\context\system::instance());

        return drawersummary::build();
    }

    /**
     * What the function returns: an allowlist, every value plain text for the client's textContent.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'resume' => new external_single_structure([
                'hasitem' => new external_value(PARAM_BOOL, 'Whether there is something to continue.'),
                'name' => new external_value(PARAM_TEXT, 'The activity or the course, plain.'),
                'url' => new external_value(PARAM_URL, 'Where the card leads, empty without one.'),
                'detail' => new external_value(PARAM_TEXT, 'The line under the name, plain, composed by the server.'),
                'kind' => new external_value(PARAM_ALPHA, 'The course\'s kind (course, event or trail), empty without a card.'),
                'hascompletion' => new external_value(PARAM_BOOL, 'Whether the course has a completion count for the user.'),
                'completed' => new external_value(PARAM_INT, 'Activities completed, 0 without a count.'),
                'total' => new external_value(PARAM_INT, 'Activities to complete, 0 without a count.'),
                'percent' => new external_value(PARAM_INT, 'The completed share, floored, 0 without a count.'),
            ]),
        ]);
    }
}
