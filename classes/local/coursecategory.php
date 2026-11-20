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
 * Theme Dimensions - The category of a course, from a shared cache
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * The category of a course, kept in the application cache `coursecategory`.
 *
 * Core asks the renderer's get_generated_image_for_id() once per course card without an image, with the id alone
 * (course_summary_exporter, on My courses and the Dashboard), so the cover's category cannot be read for a page of
 * cards at once. The answer is the same for everybody and changes only when the course is moved, so it is cached
 * in a shared application cache: one node reads it, every node reuses it.
 *
 * The entry of a course is deleted on every event that can change or end it ({@see course_changed()}, db/events.php):
 * a move fires course_updated (core's move_courses(), and update_course() for a category edited in the form), a
 * deletion course_deleted. course_created and course_restored drop an entry an id held before it named a course.
 * A plugin that writes course.category straight to the table leaves the old category until the cache is purged; the
 * entry only colours a cover, so a stale one shows the wrong colour and nothing more.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coursecategory {
    /** @var string The cache's name in db/caches.php. */
    public const CACHE = 'coursecategory';

    /**
     * The category id of a course, 0 for an id that names no course.
     *
     * @param int $courseid The course id.
     * @return int The category id.
     */
    public static function of(int $courseid): int {
        global $DB;

        $cache = \cache::make('theme_dimensions', self::CACHE);
        $category = $cache->get($courseid);
        if ($category === false) {
            $category = (int) $DB->get_field('course', 'category', ['id' => $courseid]);
            $cache->set($courseid, $category);
        }
        return (int) $category;
    }

    /**
     * Forget a course's category when the course is created, restored, updated or deleted.
     *
     * @param \core\event\base $event One of the course events db/events.php names.
     * @return void
     */
    public static function course_changed(\core\event\base $event): void {
        \cache::make('theme_dimensions', self::CACHE)->delete((int) $event->courseid);
    }
}
