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
 * Theme Dimensions - The answer to block_compass's course_kinds hook
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\block_compass;

use theme_dimensions\local\landingfields;

/**
 * Tells block_compass which course custom field says whether a course is a course or an event.
 *
 * Registered in db/hooks.php for \block_compass\hook\course_kinds; without the block nothing calls this. It names
 * the enrolment page model field ({@see landingfields::SHORTNAME_MODEL}) and two of its stored values with their
 * nouns in the current language. It reads nothing: the block asks on every response of its open-courses line,
 * and uses the field only while core lists it as visible to everyone, as the theme creates it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_kinds {
    /**
     * Answers the hook: the model field, a course for MODEL_COURSE and an event for MODEL_EVENT.
     *
     * Whatever throws is reported to developers and answered as nothing, as the block's own dispatch does: the
     * split by kind decorates a line, and a fault here must not take the block's response with it.
     *
     * @param \block_compass\hook\course_kinds $hook The question.
     * @return void
     */
    public static function answer(\block_compass\hook\course_kinds $hook): void {
        try {
            // Core reads db/hooks.php from disk, so this runs on a site where the theme is not installed yet.
            if (!get_config('theme_dimensions', 'version')) {
                return;
            }
            $hook->set_field(landingfields::SHORTNAME_MODEL);
            $hook->add_kind(
                landingfields::MODEL_COURSE,
                'course',
                get_string('compass_kind_course', 'theme_dimensions'),
                get_string('compass_kind_courses', 'theme_dimensions')
            );
            $hook->add_kind(
                landingfields::MODEL_EVENT,
                'event',
                get_string('compass_kind_event', 'theme_dimensions'),
                get_string('compass_kind_events', 'theme_dimensions')
            );
        } catch (\Throwable $exception) {
            debugging(
                'theme_dimensions: the course kinds for block_compass could not be named: ' . $exception->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }
}
