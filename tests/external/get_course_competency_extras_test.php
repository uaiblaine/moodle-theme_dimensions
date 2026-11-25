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
 * Theme Dimensions - Tests for the course competencies page's extras service
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_competency\course_module_competency;
use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\competency\coursecompetencies;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/competency/course_fixture.php');

/**
 * Tests for theme_dimensions_get_course_competency_extras, called as core/ajax calls it.
 *
 * The door mirrors core's tool_lp_data_for_course_competencies_page: login to the course, its context, competencies
 * enabled, then core's own list of the course's competencies with its capability check. Each refusal sits beside a
 * control that is answered; the distribution's audience is decided on the server, with a teacher's call as the
 * control of a learner's.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_course_competency_extras::class)]
#[CoversClass(coursecompetencies::class)]
final class get_course_competency_extras_test extends \advanced_testcase {
    use \theme_dimensions\local\competency\course_fixture;

    /** @var string The function's name. */
    private const NAME = 'theme_dimensions_get_course_competency_extras';

    /**
     * resetAfterTest() does not restore superglobals: drop the sesskey the calls plant in $_POST.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        parent::tearDown();
    }

    /**
     * Call the function as core/ajax does.
     *
     * @param int $courseid The course.
     * @return array call_external_function()'s answer.
     */
    private function ask(int $courseid): array {
        $_POST['sesskey'] = sesskey();

        return external_api::call_external_function(self::NAME, ['courseid' => $courseid], true);
    }

    /**
     * A course with two competencies, an activity linked to the first, and one learner rated proficient in it.
     *
     * @return array The fixture of {@see course_with_competencies()}, plus 'cmid'.
     */
    private function course(): array {
        $fixture = $this->course_with_competencies(2, 2);
        $courseid = (int) $fixture['course']->id;
        $generator = self::getDataGenerator();
        $page = $generator->create_module('page', ['course' => $courseid]);
        $generator->get_plugin_generator('core_competency')->create_course_module_competency([
            'cmid' => $page->cmid,
            'competencyid' => $fixture['competencies'][0],
            'ruleoutcome' => course_module_competency::OUTCOME_RECOMMEND,
        ]);
        $this->rate((int) $fixture['learners'][0]->id, $courseid, $fixture['competencies'][0], 2, true);
        $fixture['cmid'] = (int) $page->cmid;

        return $fixture;
    }

    /**
     * The teacher gets the distribution and the activity's outcome, in core's order of the competencies.
     *
     * @return void
     */
    public function test_the_teacher_gets_the_distribution(): void {
        $this->resetAfterTest();
        $fixture = $this->course();
        $this->setUser($fixture['teacher']);

        $result = $this->ask((int) $fixture['course']->id);

        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $data = $result['data'];
        $this->assertTrue($data['staff']);
        $this->assertFalse($data['learner']);
        $this->assertSame($fixture['competencies'], array_column($data['competencies'], 'competencyid'));
        $first = $data['competencies'][0];
        $this->assertTrue($first['hasdistribution']);
        $this->assertSame(50, $first['percentagevalue']);
        $this->assertSame('1 of 2 proficient', $first['progresstextvalue']);
        $this->assertSame([$fixture['cmid']], array_column($first['activities'], 'cmid'));
        $this->assertSame('status', $first['activities'][0]['outcometone']);
        $this->assertFalse($first['activities'][0]['hasstate']);
    }

    /**
     * A learner gets no distribution, whatever the class holds; their own figures instead. The teacher's call on the
     * same course is the control.
     *
     * @return void
     */
    public function test_a_learner_gets_no_distribution(): void {
        $this->resetAfterTest();
        $fixture = $this->course();
        $this->setUser($fixture['teacher']);
        $this->assertTrue($this->ask((int) $fixture['course']->id)['data']['competencies'][0]['hasdistribution']);

        $this->setUser($fixture['learners'][1]);
        $result = $this->ask((int) $fixture['course']->id);

        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $this->assertFalse($result['data']['staff']);
        $this->assertTrue($result['data']['learner']);
        foreach ($result['data']['competencies'] as $entry) {
            $this->assertFalse($entry['hasdistribution']);
            $this->assertSame('', $entry['progresstextvalue']);
            $this->assertSame('', $entry['ratedtext']);
            $this->assertSame(0, $entry['percentagevalue']);
            $this->assertTrue($entry['haslearner']);
        }
        // The activity's outcome is everybody's; it tracks no completion, so the learner gets no state for it.
        $activities = $result['data']['competencies'][0]['activities'];
        $this->assertSame([$fixture['cmid']], array_column($activities, 'cmid'));
        $this->assertFalse($activities[0]['hasstate']);
    }

    /**
     * A user not in the course is refused by core's login to it; the enrolled learner is the control.
     *
     * @return void
     */
    public function test_a_user_not_in_the_course_is_refused(): void {
        $this->resetAfterTest();
        $fixture = $this->course();
        $this->setUser($fixture['learners'][0]);
        $this->assertFalse($this->ask((int) $fixture['course']->id)['error'], 'Control: an enrolled learner is answered.');

        $this->setUser(self::getDataGenerator()->create_user());
        $result = $this->ask((int) $fixture['course']->id);

        $this->assertTrue($result['error']);
        $this->assertSame('requireloginerror', $result['exception']->errorcode);
    }

    /**
     * The guest account is refused as core refuses it: by the login to a course without guest access, and, where the
     * course lets guests in, by core's capability check (the guest role holds neither course competency capability).
     *
     * @return void
     */
    public function test_the_guest_is_refused_as_core_refuses_it(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->course();
        $courseid = (int) $fixture['course']->id;

        $this->setGuestUser();
        $result = $this->ask($courseid);
        $this->assertTrue($result['error']);
        $this->assertSame('requireloginerror', $result['exception']->errorcode);

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $courseid, 'enrol' => 'guest']);
        $result = $this->ask($courseid);
        $this->assertTrue($result['error']);
        $this->assertSame('nopermissions', $result['exception']->errorcode);
    }

    /**
     * Competencies disabled: refused with core's error; enabled, answered.
     *
     * @return void
     */
    public function test_disabled_competencies_are_refused(): void {
        $this->resetAfterTest();
        $fixture = $this->course();
        $this->setUser($fixture['teacher']);
        $this->assertFalse($this->ask((int) $fixture['course']->id)['error'], 'Control: enabled, answered.');

        set_config('enabled', 0, 'core_competency');
        $result = $this->ask((int) $fixture['course']->id);

        $this->assertTrue($result['error']);
        $this->assertSame('competenciesarenotenabled', $result['exception']->errorcode);
    }

    /**
     * A viewer without the course competency capabilities is refused by core's own check; with the capability back,
     * answered.
     *
     * @return void
     */
    public function test_a_viewer_without_the_view_capability_is_refused(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->course();
        $courseid = (int) $fixture['course']->id;
        $user = (int) $DB->get_field('role', 'id', ['shortname' => 'user']);
        $context = \core\context\course::instance($courseid);
        $this->setUser($fixture['learners'][0]);

        assign_capability('moodle/competency:coursecompetencyview', CAP_PROHIBIT, $user, $context->id);
        $result = $this->ask($courseid);
        $this->assertTrue($result['error']);
        $this->assertSame('nopermissions', $result['exception']->errorcode);

        unassign_capability('moodle/competency:coursecompetencyview', $user, $context->id);
        $this->assertFalse($this->ask($courseid)['error'], 'Control: the capability back, answered.');
    }

    /**
     * The site course is refused: the page has no extras there. A real course is the control.
     *
     * @return void
     */
    public function test_the_site_course_is_refused(): void {
        $this->resetAfterTest();
        $fixture = $this->course();
        $this->setAdminUser();
        $this->assertFalse($this->ask((int) $fixture['course']->id)['error'], 'Control: a course is answered.');

        $result = $this->ask(SITEID);

        $this->assertTrue($result['error']);
        $this->assertSame('invalidparameter', $result['exception']->errorcode);
    }

    /**
     * A learner's own figures are theirs: two learners asking the same course get their own evidence lines.
     *
     * @return void
     */
    public function test_each_learner_gets_their_own_figures(): void {
        $this->resetAfterTest();
        $fixture = $this->course();
        $courseid = (int) $fixture['course']->id;
        [$mine, $other] = $fixture['learners'];
        $lpg = self::getDataGenerator()->get_plugin_generator('core_competency');
        $uc = $lpg->create_user_competency(['userid' => $mine->id, 'competencyid' => $fixture['competencies'][0]]);
        $lpg->create_evidence(['usercompetencyid' => $uc->get('id'), 'contextid' => \core\context\course::instance($courseid)->id]);

        $this->setUser($mine);
        $text = $this->ask($courseid)['data']['competencies'][0]['evidencetext'];
        $this->assertStringStartsWith('1 piece of evidence', $text);

        $this->setUser($other);
        $text = $this->ask($courseid)['data']['competencies'][0]['evidencetext'];
        $this->assertSame(get_string('competency_courseevidencenone', 'theme_dimensions'), $text);
    }

    /**
     * A grader limited to their groups, in a course in separate groups mode, gets no distribution through the
     * service either; the same teacher holding accessallgroups is the control, and the editing teacher is too.
     *
     * @return void
     */
    public function test_a_grader_limited_to_their_groups_gets_no_distribution(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->course();
        $courseid = (int) $fixture['course']->id;
        $generator = self::getDataGenerator();
        $grader = $generator->create_and_enrol($fixture['course'], 'teacher');
        $group = $generator->create_group(['courseid' => $courseid]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $grader->id]);
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $courseid]);

        $this->setUser($grader);
        $result = $this->ask($courseid);
        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $this->assertFalse($result['data']['staff']);
        foreach ($result['data']['competencies'] as $entry) {
            $this->assertFalse($entry['hasdistribution']);
            $this->assertSame('', $entry['progresstextvalue']);
        }
        // The activity's outcome is still given.
        $this->assertSame([$fixture['cmid']], array_column($result['data']['competencies'][0]['activities'], 'cmid'));

        $this->setUser($fixture['teacher']);
        $this->assertTrue($this->ask($courseid)['data']['competencies'][0]['hasdistribution']);

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $roleid, \core\context\course::instance($courseid)->id, true);
        $this->setUser($grader);
        $this->assertTrue($this->ask($courseid)['data']['competencies'][0]['hasdistribution']);
    }
}
