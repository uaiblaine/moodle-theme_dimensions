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
use theme_dimensions\local\landingfields;
use theme_dimensions\local\related\choice;

/**
 * Gives a course that has just been created the landing page defaults it did not answer, and stores the related
 * courses' manual choice the course form submitted.
 *
 * create_course() dispatches this hook last, after core saved the custom fields the data
 * carried, so every path through create_course() is covered and a field answered on the way
 * in is already a row: the course form, the approval of a course request,
 * core_course_create_courses, tool_uploadcourse and the test generator alike. update_course()
 * dispatches it too, with isnewcourse false, and gets nothing: an existing course never
 * changes. Restore and duplication never call create_course(), so a course they make keeps
 * what its backup carried - intended, a copy is not a new course.
 *
 * The manual choice is written on a creation and on an update alike, and only from a submission of the course form
 * that carried its autocomplete ({@see choice::MARKER}) while the select says Manual: a web service or an upload that
 * names the select alone, or a form whose autocomplete was hidden, leaves the stored choice as it is.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_form_submission {
    /**
     * Store the manual choice the form submitted, then write the defaults into a new course; an update gets none.
     *
     * @param \core_course\hook\after_form_submission $hook The hook.
     * @return void
     */
    public static function callback(\core_course\hook\after_form_submission $hook): void {
        $data = $hook->get_data();
        if (empty($data->id)) {
            return;
        }
        self::store_related_choice($data);
        if (!$hook->isnewcourse) {
            return;
        }
        landingdefaults::apply_to_new_course((int) $data->id);
    }

    /**
     * Store the competencies the course form's autocomplete chose, as pairs, while the select says Manual.
     *
     * An empty choice is an answer once the form carried the autocomplete: a multiple autocomplete with nothing
     * chosen submits nothing, so its absence beside the marker stores none. Ids are kept only among the competencies
     * linked to the saved course ({@see choice::pairs()}), whatever the request claims.
     *
     * @param \stdClass $data The submitted course data, with its id.
     * @return void
     */
    private static function store_related_choice(\stdClass $data): void {
        $select = 'customfield_' . landingfields::SHORTNAME_RELATED;
        if (empty($data->{choice::MARKER}) || (int) ($data->$select ?? 0) !== landingfields::RELATED_MANUAL) {
            return;
        }
        $course = get_course((int) $data->id);
        $chosen = (array) ($data->{choice::ELEMENT} ?? []);
        choice::store((int) $course->id, choice::pairs($chosen, choice::linked_to_course($course)));
    }
}
