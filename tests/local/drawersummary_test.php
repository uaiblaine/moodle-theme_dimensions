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
 * Theme Dimensions - Tests for what the mobile drawer learns once it is shown
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the drawer's deferred summary: the card's three steps, its completion count, the course count.
 *
 * The recently accessed items are recorded by block_recentlyaccesseditems' observer of
 * course_module_viewed, so each test that needs one triggers that event as a page view would.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(drawersummary::class)]
final class drawersummary_test extends \advanced_testcase {
    /**
     * A course with completion on, two pages with manual completion, and a student enrolled in it.
     *
     * @return array course, student, pages (two cm records).
     */
    private function course_with_pages(): array {
        $generator = self::getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1, 'fullname' => 'Competencies & maps']);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $pages = [];
        foreach (['Reading 1', 'Reading 2'] as $name) {
            $pages[] = $generator->create_module('page', [
                'course' => $course->id,
                'name' => $name,
                'completion' => COMPLETION_TRACKING_MANUAL,
            ]);
        }

        return [$course, $student, $pages];
    }

    /**
     * Record a view of a course module the way the page does, which is what feeds the recent items.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $page The page module record.
     * @return void
     */
    private function view(\stdClass $course, \stdClass $page): void {
        $event = \mod_page\event\course_module_viewed::create([
            'objectid' => $page->id,
            'context' => \context_module::instance($page->cmid),
        ]);
        $event->add_record_snapshot('course', $course);
        $event->trigger();
    }

    /**
     * The card is the most recent activity, with its course and the completion count under it.
     *
     * @return void
     */
    public function test_the_card_is_the_most_recent_activity_with_its_progress(): void {
        $this->resetAfterTest();
        [$course, $student, $pages] = $this->course_with_pages();
        $this->setUser($student);
        $this->view($course, $pages[0]);
        $this->waitForSecond();
        $this->view($course, $pages[1]);
        $completion = new \completion_info($course);
        $completion->update_state(get_fast_modinfo($course)->get_cm($pages[0]->cmid), COMPLETION_COMPLETE, $student->id);

        $card = drawersummary::resume();

        $this->assertTrue($card['hasitem']);
        $this->assertSame('Reading 2', $card['name'], 'The most recent of the two.');
        $this->assertSame((new \moodle_url('/mod/page/view.php', ['id' => $pages[1]->cmid]))->out(false), $card['url']);
        $this->assertTrue($card['hascompletion']);
        $this->assertSame(1, $card['completed']);
        $this->assertSame(2, $card['total']);
        $this->assertSame(50, $card['percent']);
        $this->assertSame(
            'Competencies & maps · ' . get_string('drawer_progress', 'theme_dimensions', (object) ['completed' => 1, 'total' => 2]),
            $card['detail'],
            'Plain spelling: the client writes it as text.'
        );
    }

    /**
     * An item the reader may no longer see is never linked: the card asks again of the cm it would print.
     *
     * The helper drops a hidden module itself, so the record is handed in directly, as a helper that had
     * let it through would hand it over; the visible module after it is the control the card falls to.
     *
     * @return void
     */
    public function test_a_hidden_activity_is_never_linked_even_if_a_list_names_it(): void {
        $this->resetAfterTest();
        [$course, $student, $pages] = $this->course_with_pages();
        set_coursemodule_visible($pages[0]->cmid, 0);
        $this->setUser($student);
        $cm = get_fast_modinfo($course)->get_cm($pages[0]->cmid);
        $this->assertFalse($cm->uservisible, 'Precondition: hidden from the student.');

        $hidden = (object) ['courseid' => $course->id, 'cmid' => $pages[0]->cmid];
        $visible = (object) ['courseid' => $course->id, 'cmid' => $pages[1]->cmid];

        $this->assertNull(drawersummary::from_recent_items([$hidden]));
        $card = drawersummary::from_recent_items([$hidden, $visible]);
        $this->assertSame('Reading 2', $card['name'], 'Control: the visible one after it is linked.');
    }

    /**
     * Without a recent activity the card is the course entered last; without either, there is no card.
     *
     * @return void
     */
    public function test_the_card_falls_back_to_the_last_course_and_then_to_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $student] = $this->course_with_pages();
        $this->setUser($student);

        $this->assertFalse(drawersummary::resume()['hasitem'], 'Nothing opened, no course entered: no card.');
        $this->assertSame(drawersummary::empty_resume(), drawersummary::resume());

        $DB->insert_record('user_lastaccess', ['userid' => $student->id, 'courseid' => $course->id, 'timeaccess' => time()]);
        $card = drawersummary::resume();
        $this->assertTrue($card['hasitem']);
        $this->assertSame('Competencies & maps', $card['name']);
        $this->assertSame((new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false), $card['url']);
        $this->assertSame(0, $card['completed']);
        $this->assertSame(2, $card['total']);
        $this->assertSame(
            get_string('drawer_progress', 'theme_dimensions', (object) ['completed' => 0, 'total' => 2]),
            $card['detail'],
            'A course card carries the count alone.'
        );
    }

    /**
     * The block switched off is a block with nothing to say.
     *
     * @return void
     */
    public function test_the_recent_items_need_the_block_enabled(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $student, $pages] = $this->course_with_pages();
        $this->setUser($student);
        $this->view($course, $pages[0]);

        $this->assertCount(1, drawersummary::recent_items(), 'Control: the block is on and recorded the view.');

        $DB->set_field('block', 'visible', 0, ['name' => drawersummary::RECENT_BLOCK]);
        $this->assertSame([], drawersummary::recent_items());
    }

    /**
     * The count needs completion on for the course and the person tracked in it.
     *
     * @return void
     */
    public function test_the_count_needs_completion_on_and_a_tracked_person(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $student] = $this->course_with_pages();
        $teacher = self::getDataGenerator()->create_and_enrol($course, 'editingteacher');

        // The count is asked for the session's own user, whose view of the course module is what core counts.
        $this->setUser($student);
        $this->assertSame(['completed' => 0, 'total' => 2], drawersummary::completion($course, (int) $student->id), 'Control.');
        $this->setUser($teacher);
        $this->assertNull(drawersummary::completion($course, (int) $teacher->id), 'A teacher is not tracked.');
        $this->setUser($student);

        // The modules keep their completion settings; only the course's switch goes off.
        $DB->set_field('course', 'enablecompletion', 0, ['id' => $course->id]);
        rebuild_course_cache($course->id, true);
        $course = get_course($course->id);
        $this->assertNotEmpty((new \completion_info($course))->get_activities(), 'Precondition: the modules still track.');
        $this->assertNull(drawersummary::completion($course, (int) $student->id));
    }

    /**
     * The delegate loads the completion library itself: a web service request loads none.
     *
     * In this process the library is always loaded by the time a test can make a course (the data
     * generator requires course/lib.php, which requires it), so only the spelling can be pinned, and it
     * is pinned inside the one method that needs it.
     *
     * @return void
     */
    public function test_the_completion_library_is_required_where_it_is_used(): void {
        $method = new \ReflectionMethod(drawersummary::class, 'completion');
        $lines = file($method->getFileName());
        $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        $this->assertStringContainsString("require_once(\$CFG->libdir . '/completionlib.php');", $body);
    }

    /**
     * The courses in progress are core's timeline classification, and the sentence says one or many.
     *
     * @return void
     */
    public function test_the_courses_in_progress_follow_cores_classification(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $student = $generator->create_user();
        $now = time();
        $running = $generator->create_course(['startdate' => $now - DAYSECS, 'enddate' => $now + WEEKSECS]);
        $ended = $generator->create_course(['startdate' => $now - 2 * WEEKSECS, 'enddate' => $now - WEEKSECS]);
        $future = $generator->create_course(['startdate' => $now + WEEKSECS, 'enddate' => $now + 2 * WEEKSECS]);
        foreach ([$running, $ended, $future] as $course) {
            $generator->enrol_user($student->id, $course->id, 'student');
        }
        $this->setUser($student);

        $this->assertSame(1, drawersummary::in_progress());
        $this->assertSame(get_string('drawer_inprogress_one', 'theme_dimensions'), drawersummary::in_progress_text(1));
        $this->assertSame(get_string('drawer_inprogress_many', 'theme_dimensions', 3), drawersummary::in_progress_text(3));
        $this->assertSame('', drawersummary::in_progress_text(0));

        $second = $generator->create_course(['startdate' => $now - DAYSECS, 'enddate' => 0]);
        $generator->enrol_user($student->id, $second->id, 'student');
        $summary = drawersummary::build();
        $this->assertSame(2, $summary['inprogress']);
        $this->assertSame(get_string('drawer_inprogress_many', 'theme_dimensions', 2), $summary['inprogresstext']);
    }
}
