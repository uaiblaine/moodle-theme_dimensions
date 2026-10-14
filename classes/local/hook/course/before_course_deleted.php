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
 * Theme Dimensions - Course deletion hook callback
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\course;

use theme_dimensions\local\hotsite\links;

/**
 * Forgets a course's hotsite short address when the course is deleted.
 *
 * Core never cleans the shortlink table, so without this a deleted course would
 * leave a code answering a not-found page for ever.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_course_deleted {
    /**
     * Forget the course's short codes.
     *
     * @param \core_course\hook\before_course_deleted $hook The hook.
     * @return void
     */
    public static function callback(\core_course\hook\before_course_deleted $hook): void {
        $courseid = (int) $hook->course->id;
        try {
            links::forget($courseid);
        } catch (\Throwable $exception) {
            // A short address left behind must never stop a course from being deleted.
            debugging(
                'theme_dimensions: the hotsite short address of course ' . $courseid
                    . ' could not be forgotten: ' . $exception->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }
}
