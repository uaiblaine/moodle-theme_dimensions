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
 * Theme Dimensions - Course form after_form_definition_after_data hook callback
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\course;

use theme_dimensions\local\landingdefaults;

/**
 * Preselects the landing page defaults in the settings form of a course being created.
 *
 * Dispatched by course_edit_form::definition_after_data(), after set_data() put core's own
 * values in the form: the field default ('', the empty choice) for a new course, the stored
 * value for an existing one. Only a new course is touched, so an existing course's form keeps
 * showing what it holds. setDefault() never overrides a submitted value - QuickForm's
 * updateValue reads the constants, then the submission, and the defaults last
 * (lib/pear/HTML/QuickForm/element.php) - so a creator who changed the choice, the empty
 * one included, keeps it through a failed validation and on save.
 *
 * What the creator submits is stored by core as a row, and a row is an answer
 * {@see landingdefaults::apply_to_new_course()} leaves alone, so the form and the save
 * hook agree without either asking the other.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_form_definition_after_data {
    /**
     * Preselect each default the site sets, on a new course only.
     *
     * @param \core_course\hook\after_form_definition_after_data $hook The hook.
     * @return void
     */
    public static function callback(\core_course\hook\after_form_definition_after_data $hook): void {
        if (!empty($hook->formwrapper->get_course()->id)) {
            return;
        }
        $mform = $hook->mform;
        foreach (array_keys(landingdefaults::settings()) as $shortname) {
            $element = 'customfield_' . $shortname;
            if (!$mform->elementExists($element)) {
                continue;
            }
            $value = landingdefaults::value($shortname);
            if ($value === landingdefaults::NONE) {
                continue;
            }
            $mform->setDefault($element, $value);
        }
    }
}
