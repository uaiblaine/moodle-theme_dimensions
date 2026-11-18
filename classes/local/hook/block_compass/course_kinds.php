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
 * Tells block_compass which course custom field says whether a course is a course, an event or a trail.
 *
 * Registered in db/hooks.php for \block_compass\hook\course_kinds; without the block nothing calls this. It names
 * the enrolment page model field ({@see landingfields::SHORTNAME_MODEL}) and three of its stored values with their
 * nouns in the current language, the glyph each kind wears on this theme's own cards
 * ({@see \theme_dimensions\output\category_card}, whose model-to-icon match this must stay in step with) and, for
 * the event, that it is dated, so the block's cards state its dates as this theme's do (block_compass ADR-029).
 * It reads nothing: the block asks on every response of its open-courses line and of its course list, and uses
 * the field only while core lists it as visible to everyone, as the theme creates it.
 *
 * A block older than ADR-029 takes four arguments; PHP drops the two extra ones a user function does not declare,
 * so the answer stays valid there and draws the kinds without icons or dates.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_kinds {
    /**
     * Answers the hook: the model field, a course for MODEL_COURSE, an event for
     * MODEL_EVENT and a trail for MODEL_TRAIL, in that order (the block keeps it, `course` first), each with its
     * glyph, the event alone dated.
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
                get_string('compass_kind_courses', 'theme_dimensions'),
                'fa-graduation-cap'
            );
            $hook->add_kind(
                landingfields::MODEL_EVENT,
                'event',
                get_string('compass_kind_event', 'theme_dimensions'),
                get_string('compass_kind_events', 'theme_dimensions'),
                'fa-calendar-day',
                true
            );
            $hook->add_kind(
                landingfields::MODEL_TRAIL,
                'trail',
                get_string('compass_kind_trail', 'theme_dimensions'),
                get_string('compass_kind_trails', 'theme_dimensions'),
                'fa-route'
            );
        } catch (\Throwable $exception) {
            debugging(
                'theme_dimensions: the course kinds for block_compass could not be named: ' . $exception->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }
}
