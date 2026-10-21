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
 * Theme Dimensions - Record a switch of focus mode made in place
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
use theme_dimensions\local\focusmode;

/**
 * Record that the reader switched focus mode on or off in one activity's course, without a reload.
 *
 * The page has already changed skin when this is called: theme_dimensions/focusmode moves the body
 * class first and records the choice second, holding core/pending meanwhile. What is recorded is
 * the session's choice for the activity's COURSE ({@see focusmode::choose()}), so the next page of
 * that course opens the same way; the next course follows its own switch.
 *
 * The order of the checks: validate_parameters(), the master switch (off, the feature does not
 * exist and nothing is written), then the course module, its context and validate_context(), which
 * ends in require_login() on the module's own course and cm - so a reader who may not see the
 * activity is refused before anything is written. The site course has no focus mode. Leaving is
 * refused where the page has no ordinary skin to go back to, a single activity course that switched
 * focus mode on, whose exit is a link out of the course ({@see focusmode::leaves_course()}).
 *
 * The guest account is not refused, deliberately: focus mode is offered to it (the choice lives in
 * the session, which a guest has) exactly as the no-JavaScript fallback, focus.php, offers it. No
 * capability either, for the same reason as that endpoint: viewing the activity is the whole right,
 * and require_login() with the cm checks it. No event: the write is a display flag in the reader's
 * own session, never stored, and logging every switch would put a row per click in the log.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_focus_mode extends external_api {
    /**
     * The parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The activity whose page was switched.'),
            'active' => new external_value(PARAM_BOOL, 'True when the page is now in focus mode, false when it left it.'),
        ]);
    }

    /**
     * Record the session's choice of focus mode in the activity's course.
     *
     * @param int $cmid The course module.
     * @param bool $active Whether the page is now in focus mode.
     * @return array The choice the course's next page reads.
     * @throws \moodle_exception While the master switch is off, on the site course, and for an exit
     *         the page does not offer.
     */
    public static function execute(int $cmid, bool $active): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'active' => $active,
        ]);
        if (!focusmode::master_enabled()) {
            throw new \moodle_exception('focusmode_unavailable', focusmode::COMPONENT);
        }

        [$course, $cm] = get_course_and_cm_from_cmid((int) $params['cmid']);
        $context = \core\context\module::instance((int) $cm->id);
        self::validate_context($context);

        $courseid = (int) $course->id;
        if ($courseid === (int) SITEID) {
            throw new \moodle_exception('focusmode_unavailable', focusmode::COMPONENT);
        }
        if (!$params['active'] && focusmode::leaves_course($cm)) {
            throw new \moodle_exception('focusmode_unavailable', focusmode::COMPONENT);
        }

        focusmode::choose($courseid, (bool) $params['active']);

        return ['active' => focusmode::chosen($courseid)];
    }

    /**
     * What the function returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'active' => new external_value(PARAM_BOOL, 'Whether the course\'s next page opens in focus mode for this session.'),
        ]);
    }
}
