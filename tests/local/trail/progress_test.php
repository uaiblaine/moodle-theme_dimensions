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
 * Theme Dimensions - Tests for how far a learner has gone along a trail
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\trail;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * The states of a trail's steps, for a learner of the trail course.
 *
 * Each test builds the trail it needs: steps whose activity tracks completion or does not, a referenced course the
 * learner completed or not, a restriction on an earlier step, and windows that passed or not.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(progress::class)]
final class progress_test extends \advanced_testcase {
    /** @var \stdClass The trail course. */
    private \stdClass $trail;

    /** @var \stdClass The learner, enrolled in the trail course. */
    private \stdClass $learner;

    /**
     * Skip without the module; a trail course that tracks completion and its learner.
     */
    protected function setUp(): void {
        parent::setUp();
        if (\core_component::get_component_directory('mod_subcourse') === null) {
            $this->markTestSkipped('mod_subcourse is not installed.');
        }
        $this->resetAfterTest();
        set_config('enableavailability', 1);
        set_config('enablecompletion', 1);
        $generator = self::getDataGenerator();
        $this->trail = $generator->create_course(['fullname' => 'Trilha', 'enablecompletion' => 1]);
        $this->learner = $generator->create_user();
        $generator->enrol_user($this->learner->id, $this->trail->id, 'student');
    }

    /**
     * A step referring to a new course.
     *
     * @param string $name The activity's name.
     * @param bool $tracked Whether the activity tracks completion (manually).
     * @param array $course The referenced course's record fields.
     * @param array $extra The activity's further fields (availability, completionexpected).
     * @return \stdClass ['cmid' => int, 'refid' => int].
     */
    private function step(string $name, bool $tracked, array $course = [], array $extra = []): \stdClass {
        $generator = self::getDataGenerator();
        $ref = $generator->create_course($course + ['fullname' => "Curso {$name}", 'enablecompletion' => 1]);
        $completion = $tracked ? ['completion' => COMPLETION_TRACKING_MANUAL] : ['completion' => COMPLETION_TRACKING_NONE];
        $activity = $generator->create_module('subcourse', [
            'course' => $this->trail->id,
            'refcourse' => $ref->id,
            'name' => $name,
        ] + $completion + $extra);
        return (object) ['cmid' => (int) $activity->cmid, 'refid' => (int) $ref->id];
    }

    /**
     * Mark a manually tracked step's activity complete for the learner.
     *
     * @param int $cmid The step's activity.
     * @return void
     */
    private function complete_activity(int $cmid): void {
        $cm = get_fast_modinfo($this->trail, $this->learner->id)->get_cm($cmid);
        (new \completion_info($this->trail))->update_state($cm, COMPLETION_COMPLETE, (int) $this->learner->id);
    }

    /**
     * Mark a referenced course complete for the learner.
     *
     * @param int $courseid The course.
     * @param int $time When.
     * @return void
     */
    private function complete_course(int $courseid, int $time): void {
        $record = new \completion_completion(['course' => $courseid, 'userid' => $this->learner->id]);
        $record->mark_complete($time);
    }

    /**
     * The learner's progress over the trail as it stands now.
     *
     * @param int|null $now The moment, now by default.
     * @return array {@see progress::of()}.
     */
    private function progress(?int $now = null): array {
        get_fast_modinfo(0, 0, true);
        $this->setUser($this->learner);
        $trail = trail::of(get_course($this->trail->id));
        return progress::of(get_course($this->trail->id), $trail->steps, $now ?? time());
    }

    /**
     * Done by the activity, done by the course where the activity tracks nothing, the activity winning where it
     * tracks, a step locked behind it with core's reason, and the rest up next.
     */
    public function test_each_state_in_the_trails_order(): void {
        $future = ['startdate' => time() - WEEKSECS, 'enddate' => time() + 4 * WEEKSECS];
        $byactivity = $this->step('Por atividade', true, $future);
        $bycourse = $this->step('Pelo curso', false, $future);
        $activitywins = $this->step('Atividade & curso', true, $future);
        $locked = $this->step('Trancado', true, $future, [
            'availability' => json_encode([
                'op' => '&',
                'c' => [['type' => 'completion', 'cm' => $activitywins->cmid, 'e' => COMPLETION_COMPLETE]],
                'showc' => [true],
            ]),
        ]);
        $later = $this->step('Depois', true, $future);

        $this->complete_activity($byactivity->cmid);
        $this->complete_course($bycourse->refid, 1793000000);
        // The course is complete, but the activity tracks completion and says it is not.
        $this->complete_course($activitywins->refid, 1793000000);

        $progress = $this->progress();
        $states = array_map(static fn(array $entry): string => $entry['state'], $progress['states']);
        $this->assertSame([
            $byactivity->cmid => progress::DONE,
            $bycourse->cmid => progress::DONE,
            $activitywins->cmid => progress::CURRENT,
            $locked->cmid => progress::LOCKED,
            $later->cmid => progress::UPCOMING,
        ], $states);
        $this->assertSame($activitywins->cmid, $progress['next']);
        $this->assertSame(2, $progress['done']);
        $this->assertSame(1793000000, $progress['states'][$bycourse->cmid]['completed']);
        $this->assertGreaterThan(0, $progress['states'][$byactivity->cmid]['completed']);

        // Core's reason, in the plain spelling: the name as the author wrote it, not upper-cased, no markup.
        $reason = $progress['states'][$locked->cmid]['reason'];
        $this->assertStringContainsString('Atividade & curso', $reason);
        $this->assertStringNotContainsString('<', $reason);
        $this->assertStringNotContainsString('&amp;', $reason);
        $this->assertNull($progress['states'][$later->cmid]['reason']);
    }

    /**
     * A step undone after it was completed keeps a completion record with a time, and still answers 0: "completed"
     * is the time of a step that is done, as of() documents.
     */
    public function test_an_undone_step_has_no_completed_time(): void {
        $future = ['startdate' => time() - WEEKSECS, 'enddate' => time() + 4 * WEEKSECS];
        $step = $this->step('Desfeito', true, $future);
        $this->complete_activity($step->cmid);
        $this->assertGreaterThan(0, $this->progress()['states'][$step->cmid]['completed'], 'The control: done has a time.');

        $cm = get_fast_modinfo($this->trail, $this->learner->id)->get_cm($step->cmid);
        (new \completion_info($this->trail))->update_state($cm, COMPLETION_INCOMPLETE, (int) $this->learner->id);
        $data = (new \completion_info(get_course($this->trail->id)))->get_data($cm, true, (int) $this->learner->id);
        $this->assertGreaterThan(0, (int) $data->timemodified, 'The precondition: the record keeps its time.');

        $state = $this->progress()['states'][$step->cmid];
        $this->assertNotSame(progress::DONE, $state['state']);
        $this->assertSame(0, $state['completed']);
    }

    /**
     * An open step whose end passed is late, and is the next step when it comes first; the open step after it is
     * up next, not current.
     */
    public function test_a_passed_end_is_late_and_may_be_next(): void {
        $late = $this->step('Atrasado', true, ['startdate' => time() - 8 * WEEKSECS, 'enddate' => time() - WEEKSECS]);
        $open = $this->step('Em dia', true, ['startdate' => time() - WEEKSECS, 'enddate' => time() + 4 * WEEKSECS]);

        $progress = $this->progress();
        $this->assertSame(progress::LATE, $progress['states'][$late->cmid]['state']);
        $this->assertSame(progress::UPCOMING, $progress['states'][$open->cmid]['state']);
        $this->assertSame($late->cmid, $progress['next']);

        // The control: the same trail a fortnight earlier, when the first step's end had not passed.
        $earlier = $this->progress(time() - 2 * WEEKSECS);
        $this->assertSame(progress::CURRENT, $earlier['states'][$late->cmid]['state']);
    }

    /**
     * A late step after the current one stays late, and the current one is next; a step with no end is never late.
     */
    public function test_a_late_step_after_the_current_one_is_not_next(): void {
        $current = $this->step('Agora', true, ['startdate' => time() - WEEKSECS, 'enddate' => time() + 4 * WEEKSECS]);
        $late = $this->step('Atrasado', true, ['startdate' => time() - 8 * WEEKSECS, 'enddate' => time() - WEEKSECS]);
        $open = $this->step('Sem fim', true, ['startdate' => time() - 8 * WEEKSECS, 'enddate' => 0]);

        $progress = $this->progress();
        $this->assertSame(progress::CURRENT, $progress['states'][$current->cmid]['state']);
        $this->assertSame(progress::LATE, $progress['states'][$late->cmid]['state']);
        $this->assertSame(progress::UPCOMING, $progress['states'][$open->cmid]['state']);
        $this->assertSame($current->cmid, $progress['next']);
    }

    /**
     * A step done is done whatever its window or restriction says; a trail done to the end has no next step.
     */
    public function test_done_wins_over_late_and_a_finished_trail_has_no_next(): void {
        $first = $this->step('Primeiro', true, ['startdate' => time() - 8 * WEEKSECS, 'enddate' => time() - WEEKSECS]);
        $second = $this->step('Segundo', false, ['startdate' => time() - 8 * WEEKSECS, 'enddate' => time() - WEEKSECS]);
        $this->complete_activity($first->cmid);
        $this->complete_course($second->refid, time() - DAYSECS);

        $progress = $this->progress();
        $this->assertSame(progress::DONE, $progress['states'][$first->cmid]['state']);
        $this->assertSame(progress::DONE, $progress['states'][$second->cmid]['state']);
        $this->assertNull($progress['next']);
        $this->assertSame(2, $progress['done']);
    }

    /**
     * Another learner's completion of the referenced course is not this learner's.
     */
    public function test_a_course_completion_is_the_readers_own(): void {
        $step = $this->step('Pelo curso', false, ['startdate' => time() - WEEKSECS, 'enddate' => time() + WEEKSECS]);
        $other = self::getDataGenerator()->create_user();
        (new \completion_completion(['course' => $step->refid, 'userid' => $other->id]))->mark_complete(time());
        // The learner's own row, for a course begun and not completed: a row is not a completion.
        (new \completion_completion(['course' => $step->refid, 'userid' => $this->learner->id]))->mark_inprogress(time());

        $this->assertSame(progress::CURRENT, $this->progress()['states'][$step->cmid]['state']);

        // The control: the learner's own completion is read.
        $this->complete_course($step->refid, time());
        $this->assertSame(progress::DONE, $this->progress()['states'][$step->cmid]['state']);
    }
}
