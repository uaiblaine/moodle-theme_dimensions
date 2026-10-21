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
 * Theme Dimensions - Focus mode entry and leave endpoint, the fallback without JavaScript
 *
 * A learner may enter focus mode on any activity, and leave it again, for the rest of the session
 * in that course. With JavaScript the theme_dimensions/focusmode module switches the page in place
 * and records the choice through the set_focus_mode web service; this endpoint is what the same two
 * forms do without it, and it records the same choice and comes back to the same activity. Both are
 * state changes on the session, which is why this is reached by a POST form carrying a sesskey and
 * not by a link: a bare GET which flips a session flag is the shape a link prefetcher walks into.
 * Every decision the endpoint takes belongs to local\focusmode; the file itself is guards and a
 * redirect.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_dimensions\local\focusmode;

$cmid = required_param('cmid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($cmid);

require_login($course, false, $cm);
require_sesskey();

if (!focusmode::master_enabled() || (int) $course->id === (int) SITEID) {
    throw new \moodle_exception('focusmode_unavailable', 'theme_dimensions');
}

if ($action === 'enter') {
    focusmode::choose((int) $course->id, true);
    redirect($cm->url);
} else if ($action === 'leave') {
    // A page with no ordinary skin to go back to leaves the course instead, and records nothing.
    if (focusmode::leaves_course($cm)) {
        redirect(focusmode::close_url($cm));
    }
    focusmode::choose((int) $course->id, false);
    redirect($cm->url);
} else {
    throw new \moodle_exception('invalidaction');
}
