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
 * Theme Dimensions - Visibility parity of the calendar range with core's month view
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
 * The range never shows an event core's month view would hide, nor hides one it would show.
 *
 * One month, three viewers (a student, an editing teacher, a manager at the system level with
 * calendar_adminseesall off), and the cases that make visibility interesting. Each case states, per viewer,
 * whether core shows it, and both readings are held to that table as well as to each other. A case that
 * nobody sees or everybody sees proves nothing about a rule alone, so each rule's case is seen by one viewer
 * and hidden from another; the rows every viewer sees (and another user's event, which none does) are the
 * controls that the reading ran.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(core_events::class)]
#[CoversClass(scope::class)]
final class core_events_test extends \advanced_testcase {
    use fixture;

    /**
     * Seed the month: two courses (one hidden), a hidden category, groups, activities and events of every kind.
     *
     * @return array [users by role, event ids by case name, assign cm ids by case name]
     */
    private function seed(): array {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enableavailability', 1);
        set_config('enablecompletion', 1);
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $hiddencategory = $generator->create_category(['visible' => 0]);
        $course = $generator->create_course([
            'category' => $category->id,
            'groupmode' => SEPARATEGROUPS,
            'groupmodeforce' => 1,
            'enablecompletion' => 1,
        ]);
        $hiddencourse = $generator->create_course(['category' => $category->id, 'visible' => 0]);

        $student = $generator->create_user();
        $teacher = $generator->create_user();
        $manager = $generator->create_user();
        $other = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($student->id, $hiddencourse->id, 'student');
        $generator->enrol_user($other->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($teacher->id, $hiddencourse->id, 'editingteacher');
        $generator->enrol_user($manager->id, $course->id, 'student');
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerrole, $manager->id, \core\context\system::instance()->id);

        $g1 = $generator->create_group(['courseid' => $course->id]);
        $g2 = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $g1->id, 'userid' => $student->id]);
        $generator->create_group_member(['groupid' => $g2->id, 'userid' => $other->id]);

        $events = [];
        $events['site'] = $generator->create_event(['eventtype' => 'site', 'timestart' => self::at(1)])->id;
        $events['long'] = $generator->create_event([
            'eventtype' => 'course',
            'courseid' => $course->id,
            'timestart' => self::at(3),
            'timeduration' => 10 * DAYSECS,
        ])->id;
        $events['hiddencourse'] = $generator->create_event([
            'eventtype' => 'course',
            'courseid' => $hiddencourse->id,
            'timestart' => self::at(4),
        ])->id;
        $events['hiddencat'] = $generator->create_event([
            'eventtype' => 'category',
            'categoryid' => $hiddencategory->id,
            'timestart' => self::at(5),
        ])->id;
        $events['g1'] = $generator->create_event([
            'eventtype' => 'group',
            'courseid' => $course->id,
            'groupid' => $g1->id,
            'timestart' => self::at(6),
        ])->id;
        $events['g2'] = $generator->create_event([
            'eventtype' => 'group',
            'courseid' => $course->id,
            'groupid' => $g2->id,
            'timestart' => self::at(6),
        ])->id;
        $events['mine'] = $generator->create_event([
            'eventtype' => 'user',
            'userid' => $student->id,
            'timestart' => self::at(7),
        ])->id;
        $events['otheruser'] = $generator->create_event([
            'eventtype' => 'user',
            'userid' => $other->id,
            'timestart' => self::at(7),
        ])->id;

        $cms = [];
        $assigns = [
            'hiddenact' => ['visible' => 0],
            'restricted' => ['availability' => json_encode([
                'op' => '&',
                'c' => [['type' => 'date', 'd' => '>=', 't' => self::at(20)]],
                'showc' => [false],
            ])],
            'submitted' => ['assignsubmission_onlinetext_enabled' => 1, 'submissiondrafts' => 0],
            'notsubmitted' => ['assignsubmission_onlinetext_enabled' => 1],
        ];
        $day = 8;
        foreach ($assigns as $name => $extra) {
            $assign = $generator->create_module('assign', $extra + ['course' => $course->id, 'duedate' => self::at($day++, 17)]);
            $cms[$name] = (int) $assign->cmid;
            $events[$name] = (int) $DB->get_field('event', 'id', ['modulename' => 'assign', 'instance' => $assign->id,
                'eventtype' => 'due'], MUST_EXIST);
        }
        foreach (['completed', 'expected'] as $name) {
            $assign = $generator->create_module('assign', [
                'course' => $course->id,
                'completion' => COMPLETION_TRACKING_MANUAL,
                'completionexpected' => self::at($day++, 12),
            ]);
            $cms[$name] = (int) $assign->cmid;
            $events[$name] = (int) $DB->get_field('event', 'id', ['modulename' => 'assign', 'instance' => $assign->id,
                'eventtype' => \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED], MUST_EXIST);
        }

        // A module's event that is not an action event: its row says type 0.
        $events['standard'] = (int) $generator->create_event([
            'eventtype' => 'custom',
            'courseid' => $course->id,
            'modulename' => 'assign',
            'instance' => $DB->get_field('course_modules', 'instance', ['id' => $cms['notsubmitted']]),
            'type' => CALENDAR_EVENT_TYPE_STANDARD,
            'timestart' => self::at(14),
        ])->id;

        /** @var \mod_assign_generator $assigngenerator */
        $assigngenerator = $generator->get_plugin_generator('mod_assign');
        $assigngenerator->create_submission([
            'userid' => $student->id,
            'cmid' => $cms['submitted'],
            'onlinetext' => 'Done.',
            'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
        ]);
        $this->setUser($student);
        $completion = new \completion_info(get_course($course->id));
        $completion->update_state(get_fast_modinfo($course->id)->get_cm($cms['completed']), COMPLETION_COMPLETE);
        $this->setAdminUser();

        return [['student' => $student, 'teacher' => $teacher, 'manager' => $manager], $events, $cms];
    }

    /**
     * Who core shows each case to: the table both readings are held to.
     *
     * @return array case => [student, teacher, manager].
     */
    private static function expected(): array {
        return [
            'site' => [true, true, true],
            'long' => [true, true, true],
            'hiddencourse' => [false, true, false],
            'hiddencat' => [false, false, true],
            'g1' => [true, true, true],
            'g2' => [false, true, true],
            'mine' => [true, false, false],
            'otheruser' => [false, false, false],
            'hiddenact' => [false, true, true],
            'restricted' => [false, true, true],
            'submitted' => [true, true, true],
            'notsubmitted' => [true, true, true],
            'completed' => [false, true, true],
            'expected' => [true, true, true],
            'standard' => [true, true, true],
        ];
    }

    /**
     * For each viewer, the range's core ids are core's month-view ids, and both follow the table case by case.
     *
     * @return void
     */
    public function test_the_range_shows_exactly_what_cores_month_view_shows(): void {
        [$users, $events] = $this->seed();
        $column = ['student' => 0, 'teacher' => 1, 'manager' => 2];

        foreach ($users as $role => $user) {
            $this->as_viewer($user);
            $core = self::core_month_ids();
            $this->as_viewer($user);
            $mine = self::item_ids((new core_events())->items(self::month_window(), scope::for_viewer(), keys::ALL));

            $this->assertSame($core, $mine, $role);
            foreach (self::expected() as $case => $seen) {
                $this->assertSame($seen[$column[$role]], in_array((int) $events[$case], $core, true), "core: $role, $case");
                $this->assertSame($seen[$column[$role]], in_array((int) $events[$case], $mine, true), "range: $role, $case");
            }
        }
    }

    /**
     * An assignment not yet submitted carries its action; a submitted one is still an action event, with none.
     *
     * The entity of a submitted assignment's due date has no type: only its row says it is an action event.
     *
     * @return void
     */
    public function test_a_submitted_due_date_is_an_action_event_without_an_action(): void {
        [$users, $events] = $this->seed();
        $this->as_viewer($users['student']);
        $byid = [];
        foreach ((new core_events())->items(self::month_window(), scope::for_viewer(), keys::ALL) as $item) {
            $byid[$item['id']] = $item;
        }

        $open = $byid[(int) $events['notsubmitted']];
        $this->assertTrue($open['actionevent']);
        $this->assertNotNull($open['action']);
        $this->assertTrue($open['action']['actionable']);
        $this->assertStringContainsString('/mod/assign/view.php', $open['url']);

        $done = $byid[(int) $events['submitted']];
        $this->assertTrue($done['actionevent']);
        $this->assertNull($done['action']);
        $this->assertFalse($done['overdue']);

        // The controls: a standard event of a module, and a course event, are no action events at all.
        $this->assertFalse($byid[(int) $events['standard']]['actionevent']);
        $this->assertSame(keys::ACTIVITY, $byid[(int) $events['standard']]['key']);
        $this->assertFalse($byid[(int) $events['long']]['actionevent']);
        $this->assertSame(10 * DAYSECS, $byid[(int) $events['long']]['timeduration']);
        $this->assertSame(keys::ACTIVITY, $open['key']);
        $this->assertSame(keys::COURSE, $byid[(int) $events['long']]['key']);
        $this->assertSame(keys::SITE, $byid[(int) $events['site']]['key']);
        $this->assertSame(keys::GROUP, $byid[(int) $events['g1']]['key']);
        $this->assertSame(keys::USER, $byid[(int) $events['mine']]['key']);
    }

    /**
     * Names and locations travel in the plain spelling: no tag, no entity.
     *
     * @return void
     */
    public function test_names_and_locations_are_plain(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = self::getDataGenerator()->create_user();
        self::getDataGenerator()->create_event([
            'eventtype' => 'user',
            'userid' => $user->id,
            'name' => 'Q&A <b>live</b>',
            'location' => 'Room <i>4</i> & hall',
            'timestart' => self::at(3),
        ]);
        $this->as_viewer($user);

        $items = (new core_events())->items(self::month_window(), scope::for_viewer(), keys::ALL);

        $this->assertCount(1, $items);
        $this->assertSame('Q&A live', $items[0]['name']);
        $this->assertSame('Room 4 & hall', $items[0]['location']);
    }

    /**
     * A category event of a category local_unlistedcourses withholds is dropped; listed again, it is back.
     *
     * A deliberate difference from core's calendar, which shows it.
     *
     * @return void
     */
    public function test_a_category_event_of_an_unlisted_category_is_dropped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $top = $generator->create_category();
        $listed = $generator->create_category(['parent' => $top->id]);
        $unlisted = $generator->create_category(['parent' => $top->id]);
        $student = $generator->create_user();
        $ids = [];
        foreach (['listed' => $listed, 'unlisted' => $unlisted] as $name => $category) {
            $ids[$name] = (int) $generator->create_event([
                'eventtype' => 'category',
                'categoryid' => $category->id,
                'timestart' => self::at(5),
            ])->id;
        }
        $this->set_category_state((int) $unlisted->id, category_discoverability::STATE_UNLISTED);

        // Narrowed to the parent: the student holds no course, and core's category events follow the subtree.
        $items = function () use ($student, $top): array {
            $this->as_viewer($student);
            $scope = scope::for_viewer((int) $top->id);
            return self::item_ids((new core_events())->items(self::month_window(), $scope, keys::ALL));
        };
        $this->assertContains($ids['listed'], $items(), 'The control: the listed category event is answered.');
        $this->assertNotContains($ids['unlisted'], $items());

        $this->set_category_state((int) $unlisted->id, category_discoverability::STATE_DEFAULT);
        $this->assertContains($ids['unlisted'], $items());
    }
}
