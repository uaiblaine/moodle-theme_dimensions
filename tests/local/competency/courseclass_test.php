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
 * Theme Dimensions - Tests for the cache of the course competencies page's class distribution
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use core_competency\api;
use core_competency\evidence;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/course_fixture.php');

/**
 * Tests for {@see courseclass}: the figures are built once and served from the shared cache, and every event that
 * changes them makes the next read rebuild.
 *
 * Each event test first writes the change WITHOUT an event and reads the old figure back (the cache is serving, the
 * control), then lets core's event fire and reads the new one: deleting the observer's bump leaves the stale figure.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(courseclass::class)]
final class courseclass_test extends \advanced_testcase {
    use course_fixture;

    /**
     * Built once, then served from the cache: the second read costs the competency ids alone.
     *
     * @return void
     */
    public function test_the_figures_are_built_once_and_served_from_the_cache(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->course_with_competencies(2, 2);
        $courseid = (int) $fixture['course']->id;

        $before = $DB->perf_get_reads();
        $first = courseclass::counts($courseid);
        $built = $DB->perf_get_reads() - $before;
        $before = $DB->perf_get_reads();
        $second = courseclass::counts($courseid);
        $served = $DB->perf_get_reads() - $before;

        $this->assertSame($first, $second);
        $this->assertSame(1, $served, 'A cached read is the competency ids only.');
        $this->assertGreaterThan($served, $built);
    }

    /**
     * A write no event announces stays unseen while the value holds; a purge of the cache rebuilds it.
     *
     * @return void
     */
    public function test_a_purge_rebuilds(): void {
        $this->resetAfterTest();
        [$courseid, $competencyid, $learner] = $this->primed();

        $this->rate((int) $learner->id, $courseid, $competencyid, 2, true);
        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid), 'Control: the cache is serving.');

        \cache::make('theme_dimensions', 'coursecompetencyclass')->purge();
        $this->assertSame([2, 1, 1], $this->figures($courseid, $competencyid));
    }

    /**
     * A purge of the revisions rebuilds too: a missing revision is minted anew and matches no stored value.
     *
     * @return void
     */
    public function test_a_purge_of_the_revisions_rebuilds(): void {
        $this->resetAfterTest();
        [$courseid, $competencyid, $learner] = $this->primed();

        $this->rate((int) $learner->id, $courseid, $competencyid, 2, true);
        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid), 'Control: the cache is serving.');

        \cache::make('theme_dimensions', 'coursecompetencyrev')->purge();
        $this->assertSame([2, 1, 1], $this->figures($courseid, $competencyid));
    }

    /**
     * A value older than the rotation is rebuilt without any event.
     *
     * @return void
     */
    public function test_an_old_value_is_rebuilt(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen();
        [$courseid, $competencyid, $learner] = $this->primed();

        $this->rate((int) $learner->id, $courseid, $competencyid, 2, true);
        $clock->bump(courseclass::ROTATION - 1);
        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid), 'Control: still young, still served.');

        $clock->bump(1);
        $this->assertSame([2, 1, 1], $this->figures($courseid, $competencyid));
    }

    /**
     * A competency added to the course fires no event; the competency ids in the value see it.
     *
     * @return void
     */
    public function test_a_competency_added_rebuilds(): void {
        $this->resetAfterTest();
        [$courseid, $competencyid, , $fixture] = $this->primed();
        $lpg = self::getDataGenerator()->get_plugin_generator('core_competency');

        $added = $lpg->create_competency(['competencyframeworkid' => $fixture['framework']->get('id')]);
        $lpg->create_course_competency(['courseid' => $courseid, 'competencyid' => $added->get('id')]);

        $this->assertSame([2, 0, 0], $this->figures($courseid, (int) $added->get('id')));
        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid));
    }

    /**
     * A rating in the course: the rated_in_course event, alone.
     *
     * @return void
     */
    public function test_a_rating_in_the_course_rebuilds(): void {
        $this->resetAfterTest();
        [$courseid, $competencyid, $learner] = $this->primed();

        $row = $this->rate((int) $learner->id, $courseid, $competencyid, 2, true);
        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid), 'Control: the cache is serving.');

        \core\event\competency_user_competency_rated_in_course::create_from_user_competency_course($row)->trigger();
        $this->assertSame([2, 1, 1], $this->figures($courseid, $competencyid));
    }

    /**
     * A rating through core's own API, end to end: its evidence and its rating event both reach the observers.
     *
     * @return void
     */
    public function test_cores_rating_api_rebuilds(): void {
        $this->resetAfterTest();
        [$courseid, $competencyid, $learner] = $this->primed();
        $this->setAdminUser();

        api::grade_competency_in_course($courseid, (int) $learner->id, $competencyid, 2);

        [$learners, $rated] = $this->figures($courseid, $competencyid);
        $this->assertSame([2, 1], [$learners, $rated]);
    }

    /**
     * An evidence in an activity of the course (how a completion rates): the evidence event, alone. An evidence outside
     * the course (a plan's, in the user's context) changes no revision.
     *
     * @return void
     */
    public function test_an_evidence_in_the_course_rebuilds_and_elsewhere_does_not(): void {
        $this->resetAfterTest();
        [$courseid, $competencyid, $learner] = $this->primed();
        $page = self::getDataGenerator()->create_module('page', ['course' => $courseid]);
        $key = courseclass::course_key($courseid);

        $revision = courseclass::revision($key);
        api::add_evidence(
            (int) $learner->id,
            $competencyid,
            \core\context\user::instance((int) $learner->id)->id,
            evidence::ACTION_LOG,
            'invalidevidencedesc',
            'core_competency'
        );
        $this->assertSame($revision, courseclass::revision($key), 'An evidence outside the course bumped it.');

        api::add_evidence(
            (int) $learner->id,
            $competencyid,
            \core\context\module::instance((int) $page->cmid)->id,
            evidence::ACTION_COMPLETE,
            'invalidevidencedesc',
            'core_competency'
        );
        $this->assertNotSame($revision, courseclass::revision($key));
        [$learners, $rated] = $this->figures($courseid, $competencyid);
        $this->assertSame([2, 1], [$learners, $rated]);
    }

    /**
     * An enrolment created: the enrolment event, alone (the role was given in the course first, its own event consumed).
     *
     * @return void
     */
    public function test_an_enrolment_created_rebuilds(): void {
        global $DB;
        $this->resetAfterTest();
        [$courseid, $competencyid] = $this->primed();
        $newcomer = self::getDataGenerator()->create_user();
        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        role_assign($student, (int) $newcomer->id, \core\context\course::instance($courseid)->id);
        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid), 'Control: a role alone enrols nobody.');

        enrol_get_plugin('manual')->enrol_user($this->instance($courseid, 'manual'), (int) $newcomer->id);
        $this->assertSame([3, 0, 0], $this->figures($courseid, $competencyid));
    }

    /**
     * An enrolment deleted: the enrolment event, alone (the role is the category's, which the unenrolment keeps).
     *
     * @return void
     */
    public function test_an_enrolment_deleted_rebuilds(): void {
        global $DB;
        $this->resetAfterTest();
        [$courseid, $competencyid] = $this->primed();
        $leaver = self::getDataGenerator()->create_user();
        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        $category = (int) $DB->get_field('course', 'category', ['id' => $courseid]);
        role_assign($student, (int) $leaver->id, \core\context\coursecat::instance($category)->id);
        $manual = enrol_get_plugin('manual');
        $manual->enrol_user($this->instance($courseid, 'manual'), (int) $leaver->id);
        $this->assertSame([3, 0, 0], $this->figures($courseid, $competencyid), 'Control: counted.');

        $manual->unenrol_user($this->instance($courseid, 'manual'), (int) $leaver->id);
        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid));
    }

    /**
     * A role assigned and unassigned in the course: the role events, alone.
     *
     * @return void
     */
    public function test_a_role_in_the_course_rebuilds(): void {
        global $DB;
        $this->resetAfterTest();
        [$courseid, $competencyid] = $this->primed();
        $person = self::getDataGenerator()->create_user();
        enrol_get_plugin('manual')->enrol_user($this->instance($courseid, 'manual'), (int) $person->id);
        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid), 'Control: enrolled without a role.');

        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        $context = \core\context\course::instance($courseid);
        role_assign($student, (int) $person->id, $context->id);
        $this->assertSame([3, 0, 0], $this->figures($courseid, $competencyid));

        role_unassign($student, (int) $person->id, $context->id);
        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid));
    }

    /**
     * A role assigned in the course's category reaches every course: the site's revision.
     *
     * @return void
     */
    public function test_a_role_in_a_category_rebuilds(): void {
        global $DB;
        $this->resetAfterTest();
        [$courseid, $competencyid] = $this->primed();
        $person = self::getDataGenerator()->create_user();
        enrol_get_plugin('manual')->enrol_user($this->instance($courseid, 'manual'), (int) $person->id);
        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid), 'Control: enrolled without a role.');

        $site = courseclass::revision(courseclass::SITE);
        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        $category = (int) $DB->get_field('course', 'category', ['id' => $courseid]);
        role_assign($student, (int) $person->id, \core\context\coursecat::instance($category)->id);

        $this->assertNotSame($site, courseclass::revision(courseclass::SITE));
        $this->assertSame([3, 0, 0], $this->figures($courseid, $competencyid));
    }

    /**
     * A capability overridden and reset in the course: the capability events, alone.
     *
     * @return void
     */
    public function test_a_capability_override_in_the_course_rebuilds(): void {
        global $DB;
        $this->resetAfterTest();
        [$courseid, $competencyid] = $this->primed();
        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        $context = \core\context\course::instance($courseid);

        assign_capability(coursecompetencies::GRADABLE, CAP_PROHIBIT, $student, $context->id);
        $this->assertSame([0, 0, 0], $this->figures($courseid, $competencyid));

        unassign_capability(coursecompetencies::GRADABLE, $student, $context->id);
        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid));
    }

    /**
     * A user deleted: the site's revision. The deletion's write is made directly and the event triggered alone, so the
     * test reads the observer, not the unenrolment events delete_user() also fires.
     *
     * @return void
     */
    public function test_a_user_deleted_rebuilds(): void {
        global $DB;
        $this->resetAfterTest();
        [$courseid, $competencyid, $learner] = $this->primed();

        $DB->set_field('user', 'deleted', 1, ['id' => $learner->id]);
        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid), 'Control: the cache is serving.');

        \core\event\user_deleted::create([
            'objectid' => $learner->id,
            'relateduserid' => $learner->id,
            'context' => \core\context\user::instance((int) $learner->id),
            'other' => [
                'username' => $learner->username,
                'email' => $learner->email,
                'idnumber' => $learner->idnumber,
                'picture' => $learner->picture,
                'mnethostid' => $learner->mnethostid,
            ],
        ])->trigger();
        $this->assertSame([1, 0, 0], $this->figures($courseid, $competencyid));
    }

    /**
     * A role deleted: the site's revision.
     *
     * @return void
     */
    public function test_a_role_deleted_rebuilds(): void {
        $this->resetAfterTest();
        [$courseid] = $this->primed();
        $site = courseclass::revision(courseclass::SITE);
        $role = create_role('Probe', 'probe', 'A role to delete');

        delete_role($role);

        $this->assertNotSame($site, courseclass::revision(courseclass::SITE));
        $this->assertNotSame([], courseclass::counts($courseid));
    }

    /**
     * The course's ratings reset: the reset's end event, alone.
     *
     * @return void
     */
    public function test_a_course_reset_rebuilds(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        [$courseid, $competencyid, $learner] = $this->primed();
        $this->rate((int) $learner->id, $courseid, $competencyid, 2, true);
        \cache::make('theme_dimensions', 'coursecompetencyclass')->purge();
        $this->assertSame([2, 1, 1], $this->figures($courseid, $competencyid), 'Control: the rating is counted.');

        reset_course_userdata((object) ['id' => $courseid, 'reset_competency_ratings' => true]);

        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid));
    }

    /**
     * A role given below the course (an activity) changes no revision: the learners are judged in the course.
     *
     * @return void
     */
    public function test_a_role_in_an_activity_changes_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        [$courseid, , $learner] = $this->primed();
        $page = self::getDataGenerator()->create_module('page', ['course' => $courseid]);
        $course = courseclass::revision(courseclass::course_key($courseid));
        $site = courseclass::revision(courseclass::SITE);
        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher']);

        role_assign($teacher, (int) $learner->id, \core\context\module::instance((int) $page->cmid)->id);

        $this->assertSame($course, courseclass::revision(courseclass::course_key($courseid)));
        $this->assertSame($site, courseclass::revision(courseclass::SITE));
    }

    /**
     * A course with two learners and one competency, its figures built once.
     *
     * The observers are external (`'internal' => false`): core holds an external observer's event while a database
     * transaction is open and dispatches it on commit, and the test's own transaction is rolled back, never
     * committed. The rollback is given up first, so the events reach the observers as they do on a site, and
     * `resetAfterTest()` resets by truncation.
     *
     * @return array [course id, competency id, the first learner, the fixture].
     */
    private function primed(): array {
        $this->preventResetByRollback();
        $fixture = $this->course_with_competencies(2, 1);
        $courseid = (int) $fixture['course']->id;
        $competencyid = $fixture['competencies'][0];
        $this->assertSame([2, 0, 0], $this->figures($courseid, $competencyid));

        return [$courseid, $competencyid, $fixture['learners'][0], $fixture];
    }

    /**
     * An enrolment instance of a course.
     *
     * @param int $courseid The course.
     * @param string $plugin The enrolment plugin.
     * @return \stdClass
     */
    private function instance(int $courseid, string $plugin): \stdClass {
        global $DB;
        return $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => $plugin], '*', MUST_EXIST);
    }
}
