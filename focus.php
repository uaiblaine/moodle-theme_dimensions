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
 * Theme Dimensions - Focus mode entry and leave endpoint
 *
 * A learner may open focus mode on any activity of a course which did not switch it on, and
 * leave it again. Both are state changes on the session, which is why this is reached by a POST
 * form carrying a sesskey and not by a link: a bare GET which flips a session flag is the shape
 * a link prefetcher walks into. Every decision the endpoint takes belongs to local\focusmode;
 * the file itself is guards and a redirect.
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

if ($action === 'enter') {
    focusmode::enter_session((int) $course->id);
    redirect($cm->url);
} else if ($action === 'leave') {
    focusmode::leave_session((int) $course->id);
    redirect(focusmode::close_url($cm));
} else {
    throw new \moodle_exception('invalidaction');
}
