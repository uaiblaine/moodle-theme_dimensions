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
 * Theme Dimensions - Course after_form_submission hook callback
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\course;

use theme_dimensions\local\landingdefaults;

/**
 * Gives a course that has just been created the landing page defaults it did not answer.
 *
 * create_course() dispatches this hook last, after core saved the custom fields the data
 * carried, so every path through create_course() is covered and a field answered on the way
 * in is already a row: the course form, the approval of a course request,
 * core_course_create_courses, tool_uploadcourse and the test generator alike. update_course()
 * dispatches it too, with isnewcourse false, and gets nothing: an existing course never
 * changes. Restore and duplication never call create_course(), so a course they make keeps
 * what its backup carried - intended, a copy is not a new course.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_form_submission {
    /**
     * Write the defaults into a new course, and do nothing on an update.
     *
     * @param \core_course\hook\after_form_submission $hook The hook.
     * @return void
     */
    public static function callback(\core_course\hook\after_form_submission $hook): void {
        if (!$hook->isnewcourse) {
            return;
        }
        $data = $hook->get_data();
        if (empty($data->id)) {
            return;
        }
        landingdefaults::apply_to_new_course((int) $data->id);
    }
}
