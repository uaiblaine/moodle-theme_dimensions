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
 * Theme Dimensions - The viewer's calendar sets against core's, and the narrowing
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use local_unlistedcourses\category_discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\calendar\source\core_events;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * The sets are core's, set for set; a category narrows to its whole subtree where core keeps two levels.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(scope::class)]
final class scope_test extends \advanced_testcase {
    use fixture;

    /**
     * A three-level tree with one course and one course event at each level, and a student enrolled in all three.
     *
     * @return array [student, [parent, child, grandchild] categories, [courses], [event ids]]
     */
    private function tree(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $parent = $generator->create_category();
        $child = $generator->create_category(['parent' => $parent->id]);
        $grandchild = $generator->create_category(['parent' => $child->id]);
        $student = $generator->create_user();
        $courses = [];
        $events = [];
        foreach ([$parent, $child, $grandchild] as $i => $category) {
            $course = $generator->create_course(['category' => $category->id]);
            $generator->enrol_user($student->id, $course->id, 'student');
            $courses[] = $course;
            $events[] = (int) $generator->create_event([
                'eventtype' => 'course',
                'courseid' => $course->id,
                'timestart' => self::at(4 + $i),
            ])->id;
        }
        $group = $generator->create_group(['courseid' => $courses[0]->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
        $generator->create_event(['eventtype' => 'category', 'categoryid' => $grandchild->id, 'timestart' => self::at(9)]);
        return [$student, [$parent, $child, $grandchild], $courses, $events];
    }

    /**
     * The four sets of core's calendar_information, as calendar_get_view() hands them to api::get_events().
     *
     * @param \calendar_information $calendar Core's calendar.
     * @return array [users, groups, courses, categories]
     */
    private static function core_sets(\calendar_information $calendar): array {
        return array_map(static function ($param) {
            if ($param === true) {
                return null;
            }
            if ($param === false) {
                return [];
            }
            return is_array($param) ? $param : [$param];
        }, [$calendar->users, $calendar->groups, $calendar->courses, $calendar->categories]);
    }

    /**
     * The site, a category read as core reads it (direct children) and a course: the four sets equal core's.
     *
     * @return void
     */
    public function test_the_sets_are_cores(): void {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        [$student, $categories, $courses] = $this->tree();
        $this->as_viewer($student);
        $time = self::at(2);

        $cases = [
            'site' => [\calendar_information::create($time, SITEID), scope::for_viewer()],
            'category' => [
                \calendar_information::create($time, SITEID, (int) $categories[0]->id),
                scope::for_viewer((int) $categories[0]->id, false),
            ],
            'course' => [
                \calendar_information::create($time, (int) $courses[1]->id),
                scope::for_viewer(0, true, (int) $courses[1]->id),
            ],
        ];
        foreach ($cases as $name => [$calendar, $scope]) {
            $this->assertEquals(
                self::core_sets($calendar),
                [$scope->users, $scope->groups, $scope->courseids, $scope->categories],
                $name
            );
        }
        // The control: the site's course set holds the three courses and the site, so the comparison compared something.
        $this->assertCount(4, $cases['site'][1]->courseids);
        $this->assertNotEmpty($cases['site'][1]->groups);
    }

    /**
     * Over three levels, the recursive category reaches the grandchild's course; core's reading (and ours without
     * recursion) stops at the child, and agrees with core's month view.
     *
     * @return void
     */
    public function test_a_category_narrows_to_its_whole_subtree_where_core_keeps_two_levels(): void {
        [$student, $categories, , $events] = $this->tree();
        $this->as_viewer($student);
        $window = self::month_window();
        $parentid = (int) $categories[0]->id;

        $recursive = self::item_ids((new core_events())->items($window, scope::for_viewer($parentid), keys::ALL));
        $this->as_viewer($student);
        $direct = self::item_ids((new core_events())->items($window, scope::for_viewer($parentid, false), keys::ALL));
        $this->as_viewer($student);
        $core = self::core_month_ids($parentid);

        $this->assertContains($events[0], $recursive);
        $this->assertContains($events[1], $recursive);
        $this->assertContains($events[2], $recursive, 'The grandchild\'s course is in the subtree.');
        $this->assertContains($events[1], $direct);
        $this->assertNotContains($events[2], $direct, 'Core keeps the category and its direct children only.');
        $this->assertSame($core, $direct);
        $this->assertNotContains($events[2], $core);
    }

    /**
     * A course that is not one of the viewer's, a nonexistent one, a hidden or nonexistent category: one refusal.
     *
     * @return void
     */
    public function test_every_narrowing_miss_is_the_same_refusal(): void {
        [$student, $categories, $courses] = $this->tree();
        $generator = self::getDataGenerator();
        $foreign = $generator->create_course();
        $hidden = $generator->create_category(['visible' => 0]);
        $this->as_viewer($student);

        $messages = [];
        foreach (
            [
                [0, (int) $foreign->id],
                [0, 999999],
                [0, (int) SITEID],
                [(int) $hidden->id, 0],
                [999999, 0],
            ] as [$categoryid, $courseid]
        ) {
            try {
                scope::for_viewer($categoryid, true, $courseid);
                $this->fail("No refusal for category $categoryid, course $courseid.");
            } catch (refusal $e) {
                $messages[] = $e->getMessage();
            }
        }
        $this->assertCount(1, array_unique($messages));
        // The controls: the viewer's own course and a visible category are answered.
        $this->assertSame((int) $courses[0]->id, scope::for_viewer(0, true, (int) $courses[0]->id)->course_ids()[0]);
        $this->assertCount(3, scope::for_viewer((int) $categories[0]->id)->course_ids());
    }

    /**
     * Building the sets writes nothing into the session, where core's create() writes its key bitmask.
     *
     * @return void
     */
    public function test_the_sets_leave_the_session_alone(): void {
        global $CFG, $SESSION;
        require_once($CFG->dirroot . '/calendar/lib.php');
        [$student] = $this->tree();
        $this->as_viewer($student);
        unset($SESSION->calendarshoweventtype);

        scope::for_viewer();
        $this->assertObjectNotHasProperty('calendarshoweventtype', $SESSION);

        // The control: core's own create() writes it.
        \calendar_information::create(self::at(2), SITEID);
        $this->assertObjectHasProperty('calendarshoweventtype', $SESSION);
    }

    /**
     * A category local_unlistedcourses withholds is refused as a nonexistent id is; listed, it narrows.
     *
     * @return void
     */
    public function test_an_unlisted_category_is_the_same_refusal_as_a_missing_one(): void {
        [$student, $categories] = $this->tree();
        $unlisted = (int) $categories[0]->id;
        $this->set_category_state($unlisted, category_discoverability::STATE_UNLISTED);
        $this->as_viewer($student);

        $messages = [];
        foreach ([$unlisted, 999999] as $categoryid) {
            try {
                scope::for_viewer($categoryid);
                $this->fail("No refusal for category $categoryid.");
            } catch (refusal $e) {
                $messages[] = $e->getMessage();
            }
        }
        $this->assertCount(1, array_unique($messages));

        // The control: once listed, the same category narrows.
        $this->set_category_state($unlisted, category_discoverability::STATE_DEFAULT);
        $this->as_viewer($student);
        $this->assertCount(3, scope::for_viewer($unlisted)->course_ids());
    }

    /**
     * The viewer's own course whose category row is gone is refused, not thrown at; the intact one is answered.
     *
     * @return void
     */
    public function test_a_course_whose_category_is_gone_is_the_one_refusal(): void {
        global $DB;
        [$student, , $courses] = $this->tree();
        $this->as_viewer($student);
        $this->assertSame([(int) $courses[1]->id], scope::for_viewer(0, true, (int) $courses[1]->id)->course_ids());

        $DB->set_field('course', 'category', 999999, ['id' => $courses[1]->id]);
        $this->as_viewer($student);
        $this->expectException(refusal::class);
        scope::for_viewer(0, true, (int) $courses[1]->id);
    }

    /**
     * The site view reads core's session cache and never sets it; core's own path does.
     *
     * @return void
     */
    public function test_the_site_view_never_writes_the_session_cache(): void {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        [$student] = $this->tree();
        $this->as_viewer($student);
        $cache = \cache::make('core', 'calendar_categories');
        $cache->purge();
        $this->assertFalse($cache->get('site'));

        $sets = scope::for_viewer();
        $this->assertNotEmpty($sets->categories);
        $this->assertFalse($cache->get('site'), 'The scope computed the set and kept it to itself.');

        // The control: core fills it, and the scope then reads what core wrote.
        \calendar_information::create(self::at(2), SITEID);
        $this->assertNotFalse($cache->get('site'));
        $this->assertEqualsCanonicalizing($cache->get('site'), scope::for_viewer()->categories);
    }

    /**
     * A session key that was there holding null is put back as null, and an absent one stays absent.
     *
     * @return void
     */
    public function test_a_null_session_key_is_restored_not_removed(): void {
        global $SESSION;
        [$student] = $this->tree();
        $this->as_viewer($student);
        $SESSION->calendarshoweventtype = null;

        scope::for_viewer();
        $this->assertTrue(property_exists($SESSION, 'calendarshoweventtype'));
        $this->assertNull($SESSION->calendarshoweventtype);

        unset($SESSION->calendarshoweventtype);
        scope::for_viewer();
        $this->assertFalse(property_exists($SESSION, 'calendarshoweventtype'), 'The control: an absent key stays absent.');
    }
}
