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
 * Theme Dimensions - Tests for the shared cache of a course's category
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for coursecategory: the answer is cached, and every course event that can change it forgets it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(coursecategory::class)]
final class coursecategory_test extends \advanced_testcase {
    /**
     * A move, an edit of the category in the course form and a deletion are each seen at once.
     *
     * The control is a write straight to the table, which fires no event: the cached category stands, so the cache
     * is what answers and the observers are what make each of the three changes seen.
     *
     * @return void
     */
    public function test_a_move_an_edit_and_a_deletion_are_seen(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $first = (int) $generator->create_category()->id;
        $second = (int) $generator->create_category()->id;
        $third = (int) $generator->create_category()->id;
        $course = $generator->create_course(['category' => $first]);
        $id = (int) $course->id;

        $this->assertSame($first, coursecategory::of($id));
        $DB->set_field('course', 'category', $second, ['id' => $id]);
        $this->assertSame($first, coursecategory::of($id), 'Control: a write without an event leaves the cached answer.');
        $DB->set_field('course', 'category', $first, ['id' => $id]);

        move_courses([$id], $second);
        $this->assertSame($second, coursecategory::of($id), 'A move fires course_updated.');

        update_course((object) ['id' => $id, 'category' => $third]);
        $this->assertSame($third, coursecategory::of($id), 'The course form\'s edit fires course_updated.');

        delete_course($id, false);
        $this->assertSame(0, coursecategory::of($id), 'A deleted course names no category.');
    }

    /**
     * A creation and a restore forget whatever the cache held for the course's id.
     *
     * An entry for an id can predate its course (an id that named none answers 0) or outlive a restore over the
     * course. Each event is fired over a poisoned entry: the course's real category is read again.
     *
     * @return void
     */
    public function test_a_creation_and_a_restore_forget_the_entry(): void {
        $this->resetAfterTest();
        $category = (int) self::getDataGenerator()->create_category()->id;
        $course = self::getDataGenerator()->create_course(['category' => $category]);
        $id = (int) $course->id;
        $context = \core\context\course::instance($id);
        $cache = \cache::make('theme_dimensions', coursecategory::CACHE);

        $cache->set($id, 0);
        $this->assertSame(0, coursecategory::of($id), 'Control: the poisoned entry answers.');
        \core\event\course_created::create([
            'objectid' => $id,
            'context' => $context,
            'other' => ['shortname' => $course->shortname, 'fullname' => $course->fullname],
        ])->trigger();
        $this->assertSame($category, coursecategory::of($id), 'course_created forgets it.');

        $cache->set($id, 0);
        \core\event\course_restored::create([
            'objectid' => $id,
            'courseid' => $id,
            'context' => $context,
            'other' => [
                'type' => 'course',
                'target' => 3,
                'mode' => 10,
                'operation' => 'restore',
                'samesite' => true,
                'originalcourseid' => $id,
            ],
        ])->trigger();
        $this->assertSame($category, coursecategory::of($id), 'course_restored forgets it.');
    }
}
