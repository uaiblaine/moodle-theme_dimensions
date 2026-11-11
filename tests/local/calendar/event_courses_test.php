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
 * Theme Dimensions - The event courses of the calendar range
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\calendar\source\event_courses;
use theme_dimensions\local\category\classifier;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * An event course the viewer is in is theirs; one they could join is open; nothing else reaches the calendar.
 *
 * Every refused course differs from the open control in one property: hidden, in an unlisted category, its
 * enrolment closed, its dates outside the window, a course of the course model, a row of the viewer's that is
 * not active. The control is answered in the same call, so a refusal cannot pass because nothing answers.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(event_courses::class)]
#[CoversClass(\theme_dimensions\local\category\population::class)]
final class event_courses_test extends \advanced_testcase {
    use fixture;

    /** @var \stdClass The viewer. */
    private \stdClass $student;

    /** @var int[] Course ids by case name. */
    private array $courses = [];

    /**
     * An open event course with an enabled self enrolment.
     *
     * @param int $categoryid Its category.
     * @param int $start Its first day of next month.
     * @param int $end Its last day, 0 for none.
     * @param array $record Further course fields.
     * @param array $instance Further self instance fields.
     * @return int The course id.
     */
    private function open_course(int $categoryid, int $start, int $end, array $record = [], array $instance = []): int {
        $course = self::event_course($categoryid, $start, $end, $record);
        $this->enable_instance((int) $course->id, 'self', $instance + ['customint6' => 1]);
        return (int) $course->id;
    }

    /**
     * The fixture: one course per case.
     *
     * @return void
     */
    private function seed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $category = (int) $generator->create_category()->id;
        $unlisted = (int) $generator->create_category()->id;
        $this->student = $generator->create_user();

        $mine = self::event_course($category, 3, 5);
        $generator->enrol_user($this->student->id, $mine->id, 'student');
        $this->courses['mine'] = (int) $mine->id;
        $this->courses['open'] = $this->open_course($category, 10, 12);
        $this->courses['oneday'] = $this->open_course($category, 15, 0);
        $this->courses['hidden'] = $this->open_course($category, 10, 12, ['visible' => 0]);
        $this->courses['unlistedcat'] = $this->open_course($unlisted, 10, 12);
        $this->courses['unlistedcourse'] = $this->open_course($category, 10, 12);
        $this->courses['closed'] = $this->open_course($category, 10, 12, [], ['enrolenddate' => time() - DAYSECS]);
        $this->courses['outside'] = $this->open_course($category, 70, 72);
        $suspended = $this->open_course($category, 10, 12);
        $generator->enrol_user($this->student->id, $suspended, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->courses['suspended'] = $suspended;
        $regular = $generator->create_course(['category' => $category, 'startdate' => self::at(10, 0)]);
        self::set_model((int) $regular->id, landingfields::MODEL_COURSE);
        $this->enable_instance((int) $regular->id, 'self', ['customint6' => 1]);
        $this->courses['coursemodel'] = (int) $regular->id;

        $this->set_category_state($unlisted, category_discoverability::STATE_UNLISTED);
        $this->set_course_state($this->courses['unlistedcourse'], discoverability::STATE_UNLISTED);
    }

    /**
     * The source's items for the student over next month, keyed by course id.
     *
     * @param string[] $on The keys.
     * @param int $categoryid A category narrowing.
     * @return array [items by course id, the source]
     */
    private function items(array $on = keys::ALL, int $categoryid = 0): array {
        $this->as_viewer($this->student);
        $source = new event_courses(classifier::enrolments((int) $this->student->id));
        $byid = [];
        foreach ($source->items(self::month_window(), scope::for_viewer($categoryid), $on) as $item) {
            $byid[$item['id']] = $item;
        }
        return [$byid, $source];
    }

    /**
     * The enrolled event course is the viewer's, the joinable one is open, and every refused case is absent.
     *
     * @return void
     */
    public function test_mine_open_and_refused(): void {
        $this->seed();
        [$items, $source] = $this->items();

        $this->assertSame(keys::COURSE, $items[$this->courses['mine']]['key']);
        $this->assertSame(keys::OPEN, $items[$this->courses['open']]['key']);
        $this->assertSame('open', $source->joins()[$this->courses['open']]);
        foreach (['hidden', 'unlistedcat', 'closed', 'outside', 'suspended', 'coursemodel'] as $case) {
            $this->assertArrayNotHasKey($this->courses[$case], $items, $case);
        }
        // The plugin keeps an unlisted COURSE discoverable to somebody who could join it now; its category term does not.
        $this->assertSame(keys::OPEN, $items[$this->courses['unlistedcourse']]['key']);

        $mine = $items[$this->courses['mine']];
        $this->assertSame('course', $mine['source']);
        $this->assertSame(self::at(3, 0), $mine['timestart']);
        $this->assertSame(self::at(5, 23) - self::at(3, 0), $mine['timeduration']);
        $this->assertFalse($mine['held']);
        $this->assertStringContainsString((string) $this->courses['mine'], $mine['url']);
        $this->assertSame(0, $items[$this->courses['oneday']]['timeduration'], 'No end date: the start day alone.');
        $this->assertFalse($source->truncated());
    }

    /**
     * "held" is judged against the instant the caller passes, so one answer has one clock.
     *
     * @return void
     */
    public function test_held_is_judged_against_the_given_instant(): void {
        $this->resetAfterTest();
        $row = $this->getDataGenerator()->create_course(['startdate' => 1000, 'enddate' => 2000]);
        $item = new \ReflectionMethod(event_courses::class, 'item');
        $item->setAccessible(true);

        // Well before the real clock, so only the passed instant can tell the three apart.
        $this->assertFalse($item->invoke(null, $row, keys::COURSE, 2000)['held'], 'The end instant itself is not held.');
        $this->assertTrue($item->invoke(null, $row, keys::COURSE, 2001)['held']);
        $this->assertFalse($item->invoke(null, $row, keys::COURSE, 1500)['held'], 'Not held before its end.');
    }

    /**
     * The controls of three refused cases: the hidden, unlisted-category and closed courses are open once their one
     * difference is undone.
     *
     * @return void
     */
    public function test_each_refused_case_answers_once_its_difference_is_undone(): void {
        global $DB;
        $this->seed();
        $DB->set_field('course', 'visible', 1, ['id' => $this->courses['hidden']]);
        $this->set_category_state(
            (int) $DB->get_field('course', 'category', ['id' => $this->courses['unlistedcat']]),
            category_discoverability::STATE_DEFAULT
        );
        $DB->set_field('enrol', 'enrolenddate', 0, ['courseid' => $this->courses['closed'], 'enrol' => 'self']);

        [$items] = $this->items();
        foreach (['hidden', 'unlistedcat', 'closed'] as $case) {
            $this->assertSame(keys::OPEN, $items[$this->courses[$case]]['key'] ?? null, $case);
        }
    }

    /**
     * Each key asks its own half: "course" alone answers no open course, "open" alone none of the viewer's.
     *
     * @return void
     */
    public function test_each_half_answers_its_own_key(): void {
        $this->seed();
        [$course] = $this->items([keys::COURSE]);
        [$open] = $this->items([keys::OPEN]);

        $this->assertSame([$this->courses['mine']], array_keys($course));
        $this->assertArrayNotHasKey($this->courses['mine'], $open);
        $this->assertArrayHasKey($this->courses['open'], $open);
    }

    /**
     * A category narrowing narrows the open layer too; a course narrowing has none.
     *
     * @return void
     */
    public function test_the_narrowing_applies_to_the_open_layer(): void {
        global $DB;
        $this->seed();
        $own = (int) $DB->get_field('course', 'category', ['id' => $this->courses['open']]);
        $elsewhere = (int) self::getDataGenerator()->create_category()->id;

        [$inside] = $this->items([keys::OPEN], $own);
        [$outside] = $this->items([keys::OPEN], $elsewhere);
        $this->as_viewer($this->student);
        $source = new event_courses(classifier::enrolments((int) $this->student->id));
        $narrowed = $source->items(self::month_window(), scope::for_viewer(0, true, $this->courses['mine']), keys::ALL);

        $this->assertArrayHasKey($this->courses['open'], $inside);
        $this->assertArrayNotHasKey($this->courses['open'], $outside);
        $this->assertSame([$this->courses['mine']], array_column($narrowed, 'id'));
    }

    /**
     * An enrolment is not a licence to be named: the enrolled event course goes through the showcase's gate.
     *
     * @return void
     */
    public function test_an_enrolled_event_course_is_named_only_where_the_showcase_lists_it(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $category = (int) $generator->create_category()->id;
        $this->student = $generator->create_user();
        $teacher = $generator->create_user();
        $hidden = self::event_course($category, 3, 5, ['visible' => 0]);
        $shown = self::event_course($category, 6, 7);
        foreach ([$hidden, $shown] as $course) {
            $generator->enrol_user($this->student->id, $course->id, 'student');
            $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        }

        [$items] = $this->items([keys::COURSE]);
        $this->assertArrayHasKey((int) $shown->id, $items, 'The control: the visible course is answered.');
        $this->assertArrayNotHasKey((int) $hidden->id, $items);

        // The control of the difference: the same course, visible, is answered.
        $DB->set_field('course', 'visible', 1, ['id' => $hidden->id]);
        [$items] = $this->items([keys::COURSE]);
        $this->assertArrayHasKey((int) $hidden->id, $items);

        // Whoever may view hidden courses keeps it.
        $DB->set_field('course', 'visible', 0, ['id' => $hidden->id]);
        $this->student = $teacher;
        [$items] = $this->items([keys::COURSE]);
        $this->assertArrayHasKey((int) $hidden->id, $items);
    }

    /**
     * Past the cap the earliest open courses are answered and the source says the rest were left out.
     *
     * Changes that must make it fail: cutting the open layer by anything but start date, such as the latest
     * start first or no order at all, which answers the same number of courses but not the earliest ones.
     *
     * @return void
     */
    public function test_the_open_layer_is_capped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->enable_enrol('self');
        $category = (int) self::getDataGenerator()->create_category()->id;
        $this->student = self::getDataGenerator()->create_user();
        $ids = [];
        for ($i = 0; $i <= event_courses::OPEN_CAP; $i++) {
            $ids[$i] = $this->open_course($category, 1 + intdiv($i, 3), 0);
        }

        [$items, $source] = $this->items([keys::OPEN]);

        $this->assertCount(event_courses::OPEN_CAP, $items);
        $this->assertTrue($source->truncated());
        // The one left out is the last to start; the first to start is answered.
        $this->assertArrayHasKey($ids[0], $items, 'The earliest open course is answered.');
        $this->assertArrayNotHasKey($ids[event_courses::OPEN_CAP], $items, 'The latest open course is the one left out.');
    }

    /**
     * Courses the gate withholds cost a read and never a place: 50 hidden earlier ones do not hide a later joinable one.
     *
     * @return void
     */
    public function test_withheld_candidates_do_not_push_a_joinable_course_out(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->enable_enrol('self');
        $category = (int) self::getDataGenerator()->create_category()->id;
        $this->student = self::getDataGenerator()->create_user();
        for ($i = 0; $i < event_courses::OPEN_CAP; $i++) {
            $this->open_course($category, 1, 0, ['visible' => 0]);
        }
        $late = $this->open_course($category, 20, 0);

        [$items, $source] = $this->items([keys::OPEN]);

        $this->assertSame([$late], array_keys($items));
        $this->assertFalse($source->truncated(), 'Withheld courses are not "more in the catalogue".');
    }

    /**
     * Past the candidate bound the answer says so, even when every candidate read was withheld.
     *
     * @return void
     */
    public function test_the_candidate_bound_is_reported(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->enable_enrol('self');
        $category = (int) self::getDataGenerator()->create_category()->id;
        $this->student = self::getDataGenerator()->create_user();
        for ($i = 0; $i <= event_courses::OPEN_CANDIDATES; $i++) {
            $this->open_course($category, 1, 0, ['visible' => 0]);
        }

        [$items, $source] = $this->items([keys::OPEN]);

        $this->assertSame([], array_keys($items));
        $this->assertTrue($source->truncated());
    }
}
