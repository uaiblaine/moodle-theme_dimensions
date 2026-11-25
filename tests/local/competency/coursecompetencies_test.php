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
 * Theme Dimensions - Tests for the extras of the course competencies page
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use core_competency\course_module_competency;
use core_competency\user_competency_course;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/course_fixture.php');

/**
 * Tests for {@see coursecompetencies}: who is counted in the class distribution, who gets which extra, and what the
 * extras cost.
 *
 * Every refusal or exclusion sits beside a control that is counted or answered, so a figure of zero cannot hold a test
 * green for the wrong reason.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(coursecompetencies::class)]
#[CoversClass(courseclass::class)]
final class coursecompetencies_test extends \advanced_testcase {
    use course_fixture;

    /**
     * The aggregate counts the enrolled learners only: a rated row of somebody else, or of somebody enrolled without
     * core's gradable capability, is not counted; the enrolled learners' rows are, and a learner unenrolled leaves.
     *
     * @return void
     */
    public function test_the_distribution_counts_enrolled_learners_only(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->course_with_competencies(3, 1);
        $courseid = (int) $fixture['course']->id;
        $competencyid = $fixture['competencies'][0];
        [$proficient, $notproficient] = $fixture['learners'];
        $stranger = self::getDataGenerator()->create_user();
        // Gradable through a role in the course's category, never enrolled: core's capability alone is not the set.
        $roleonly = self::getDataGenerator()->create_user();
        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        role_assign($student, (int) $roleonly->id, \core\context\coursecat::instance((int) $fixture['course']->category)->id);

        $this->rate((int) $proficient->id, $courseid, $competencyid, 2, true);
        $this->rate((int) $notproficient->id, $courseid, $competencyid, 1, false);
        // Rows core's own statistics would count: a stranger's, a gradable user's who is not enrolled, the teacher's.
        $this->rate((int) $stranger->id, $courseid, $competencyid, 2, true);
        $this->rate((int) $roleonly->id, $courseid, $competencyid, 2, true);
        $this->rate((int) $fixture['teacher']->id, $courseid, $competencyid, 2, true);

        $counts = coursecompetencies::aggregate($courseid);
        $this->assertSame(['learners' => 3, 'rated' => 2, 'proficient' => 1], $counts[$competencyid]);

        // The control row leaves with its learner's enrolment.
        $manual = enrol_get_plugin('manual');
        $instance = $this->manual_instance($courseid);
        $manual->unenrol_user($instance, (int) $proficient->id);
        $counts = coursecompetencies::aggregate($courseid);
        $this->assertSame(['learners' => 2, 'rated' => 1, 'proficient' => 0], $counts[$competencyid]);
    }

    /**
     * A suspended enrolment is counted, as core's grading and report_competency count it (their `$onlyactive` is
     * false): core may still rate that learner in the course. A learner enrolled twice is one learner.
     *
     * @return void
     */
    public function test_a_suspended_learner_counts_and_two_enrolments_are_one_learner(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->course_with_competencies(1, 1);
        $courseid = (int) $fixture['course']->id;
        $competencyid = $fixture['competencies'][0];
        $context = \core\context\course::instance($courseid);
        $generator = self::getDataGenerator();
        $student = $DB->get_field('role', 'id', ['shortname' => 'student']);

        $suspended = $generator->create_user();
        $generator->enrol_user($suspended->id, $courseid, $student, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->rate((int) $suspended->id, $courseid, $competencyid, 2, true);
        // Core's own rule for rating in the course admits them: that is what is followed.
        $this->assertTrue(is_enrolled($context, $suspended, coursecompetencies::GRADABLE));
        $this->assertFalse(is_enrolled($context, $suspended, coursecompetencies::GRADABLE, true));

        $twice = $fixture['learners'][0];
        $self = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'self'], '*', MUST_EXIST);
        enrol_get_plugin('self')->enrol_user($self, (int) $twice->id, $student);
        $this->rate((int) $twice->id, $courseid, $competencyid, 1, false);

        $counts = coursecompetencies::aggregate($courseid);
        $this->assertSame(['learners' => 2, 'rated' => 2, 'proficient' => 1], $counts[$competencyid]);
    }

    /**
     * The site course has no distribution: every user is enrolled on it, and the page has no extras there.
     *
     * @return void
     */
    public function test_the_site_course_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->course_with_competencies(1, 1);

        // Control: a real course answers.
        $answer = coursecompetencies::for_viewer($fixture['course'], $fixture['competencies']);
        $this->assertTrue($answer['staff']);
        $this->assertCount(1, $answer['competencies']);

        $site = get_site();
        $answer = coursecompetencies::for_viewer($site, $fixture['competencies']);
        $this->assertSame(['staff' => false, 'learner' => false, 'competencies' => []], $answer);
        $this->expectException(\coding_exception::class);
        coursecompetencies::aggregate(SITEID);
    }

    /**
     * Staff get the distribution, a learner does not; a learner gets their own figures, staff do not.
     *
     * @return void
     */
    public function test_each_audience_gets_its_own_extras(): void {
        $this->resetAfterTest();
        $fixture = $this->course_with_competencies(2, 1);
        $course = $fixture['course'];
        $competencyid = $fixture['competencies'][0];
        $this->rate((int) $fixture['learners'][0]->id, (int) $course->id, $competencyid, 2, true);

        $this->setUser($fixture['teacher']);
        $staff = coursecompetencies::for_viewer($course, [$competencyid]);
        $this->assertTrue($staff['staff']);
        $this->assertFalse($staff['learner']);
        $entry = $staff['competencies'][0];
        $this->assertTrue($entry['hasdistribution']);
        $this->assertTrue($entry['haslearners']);
        $this->assertSame(50, $entry['percentagevalue']);
        $this->assertSame('1 of 2 proficient', $entry['progresstextvalue']);
        $this->assertSame('0 rated, not proficient', $entry['ratedtext']);
        $this->assertSame('1 not rated', $entry['notratedtext']);
        $this->assertFalse($entry['haslearner']);

        $this->setUser($fixture['learners'][1]);
        $learner = coursecompetencies::for_viewer($course, [$competencyid]);
        $this->assertFalse($learner['staff']);
        $this->assertTrue($learner['learner']);
        $entry = $learner['competencies'][0];
        $this->assertFalse($entry['hasdistribution']);
        $this->assertSame('', $entry['progresstextvalue']);
        $this->assertTrue($entry['haslearner']);
        $this->assertSame(get_string('competency_courseevidencenone', 'theme_dimensions'), $entry['evidencetext']);
    }

    /**
     * A learner's evidence count is theirs and this course's alone: another learner's, another course's and a plan's
     * evidence are not counted, their own in the course and its activities are.
     *
     * @return void
     */
    public function test_the_learners_figures_are_their_own(): void {
        $this->resetAfterTest();
        $fixture = $this->course_with_competencies(2, 1);
        $course = $fixture['course'];
        $courseid = (int) $course->id;
        $competencyid = $fixture['competencies'][0];
        [$mine, $other] = $fixture['learners'];
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $page = $generator->create_module('page', ['course' => $courseid]);
        $elsewhere = $generator->create_course();
        $coursecontext = \core\context\course::instance($courseid);

        $myuc = $lpg->create_user_competency(['userid' => $mine->id, 'competencyid' => $competencyid]);
        $otheruc = $lpg->create_user_competency(['userid' => $other->id, 'competencyid' => $competencyid]);
        $lpg->create_evidence(['usercompetencyid' => $myuc->get('id'), 'contextid' => $coursecontext->id]);
        $lpg->create_evidence([
            'usercompetencyid' => $myuc->get('id'),
            'contextid' => \core\context\module::instance((int) $page->cmid)->id,
        ]);
        $lpg->create_evidence([
            'usercompetencyid' => $myuc->get('id'),
            'contextid' => \core\context\course::instance((int) $elsewhere->id)->id,
        ]);
        $lpg->create_evidence(['usercompetencyid' => $myuc->get('id'), 'contextid' => \core\context\user::instance($mine->id)->id]);
        $lpg->create_evidence(['usercompetencyid' => $otheruc->get('id'), 'contextid' => $coursecontext->id]);

        $this->assertSame(2, coursecompetencies::evidence($courseid, (int) $mine->id, [$competencyid])[$competencyid]['count']);
        $this->assertSame(1, coursecompetencies::evidence($courseid, (int) $other->id, [$competencyid])[$competencyid]['count']);

        $this->setUser($other);
        $text = coursecompetencies::for_viewer($course, [$competencyid])['competencies'][0]['evidencetext'];
        $this->assertStringStartsWith('1 piece of evidence', $text);
        $this->setUser($mine);
        $text = coursecompetencies::for_viewer($course, [$competencyid])['competencies'][0]['evidencetext'];
        $this->assertStringStartsWith('2 pieces of evidence', $text);
    }

    /**
     * An activity core's page does not list to a viewer (hidden from them) gets no extra either; a visible one does,
     * with its own outcome, and the learner's state.
     *
     * @return void
     */
    public function test_a_hidden_activity_is_left_out_as_core_leaves_it_out(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $fixture = $this->course_with_competencies(1, 1);
        $course = $fixture['course'];
        $competencyid = $fixture['competencies'][0];
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $shown = $generator->create_module('page', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $hidden = $generator->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $lpg->create_course_module_competency([
            'cmid' => $shown->cmid,
            'competencyid' => $competencyid,
            'ruleoutcome' => course_module_competency::OUTCOME_EVIDENCE,
        ]);
        $lpg->create_course_module_competency([
            'cmid' => $hidden->cmid,
            'competencyid' => $competencyid,
            'ruleoutcome' => course_module_competency::OUTCOME_COMPLETE,
        ]);

        $learner = $fixture['learners'][0];
        $this->setUser($learner);
        $activities = coursecompetencies::for_viewer($course, [$competencyid])['competencies'][0]['activities'];
        $this->assertSame([(int) $shown->cmid], array_column($activities, 'cmid'));
        $this->assertSame('rated', $activities[0]['outcometone']);
        $this->assertTrue($activities[0]['istodo']);

        $cm = get_fast_modinfo($course)->get_cm((int) $shown->cmid);
        (new \completion_info($course))->update_state($cm, COMPLETION_COMPLETE, (int) $learner->id);
        $activities = coursecompetencies::for_viewer($course, [$competencyid])['competencies'][0]['activities'];
        $this->assertTrue($activities[0]['isdone']);
        $this->assertStringStartsWith('Completed on ', $activities[0]['statetext']);

        // Control: the teacher, who sees hidden activities, gets both, and no state.
        $this->setUser($fixture['teacher']);
        $activities = coursecompetencies::for_viewer($course, [$competencyid])['competencies'][0]['activities'];
        $cmids = array_column($activities, 'cmid');
        sort($cmids);
        $this->assertSame([(int) $shown->cmid, (int) $hidden->cmid], $cmids);
        $this->assertSame([false, false], array_column($activities, 'hasstate'));
    }

    /**
     * The statements the extras cost do not grow with the learners or the competencies: measured at two sizes, for
     * staff (the aggregate and its cache, purged first) and for a learner.
     *
     * @return void
     */
    public function test_the_cost_is_constant_in_learners_and_competencies(): void {
        global $DB;
        $this->resetAfterTest();
        $small = $this->course_with_competencies(2, 2);
        $large = $this->course_with_competencies(7, 5);
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        foreach ([$small, $large] as $fixture) {
            $page = $generator->create_module('page', ['course' => $fixture['course']->id]);
            foreach ($fixture['competencies'] as $index => $competencyid) {
                $lpg->create_course_module_competency(['cmid' => $page->cmid, 'competencyid' => $competencyid]);
                $proficient = $index % 2 === 1;
                foreach ($fixture['learners'] as $learner) {
                    $this->rate((int) $learner->id, (int) $fixture['course']->id, $competencyid, $proficient ? 2 : 1, $proficient);
                }
            }
        }

        $cost = [];
        foreach (['small' => $small, 'large' => $large] as $size => $fixture) {
            foreach (['staff' => $fixture['teacher'], 'learner' => $fixture['learners'][0]] as $audience => $user) {
                $this->setUser($user);
                // Warm the request-wide caches (modinfo, access), then measure a cold class cache.
                coursecompetencies::for_viewer($fixture['course'], $fixture['competencies']);
                \cache::make('theme_dimensions', 'coursecompetencyclass')->purge();
                $before = $DB->perf_get_reads();
                coursecompetencies::for_viewer($fixture['course'], $fixture['competencies']);
                $cost[$audience][$size] = $DB->perf_get_reads() - $before;
            }
        }

        $this->assertSame($cost['staff']['small'], $cost['staff']['large'], json_encode($cost));
        $this->assertSame($cost['learner']['small'], $cost['learner']['large'], json_encode($cost));
    }

    /**
     * The extras insert no competency_usercompcourse row: core's page inserts one per missing competency for a learner
     * (the control), the theme's extras none, and the theme's whole page exactly core's.
     *
     * @return void
     */
    public function test_a_learners_page_inserts_no_row_beyond_cores(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $fixture = $this->course_with_competencies(2, 3);
        $course = $fixture['course'];
        [$first, $second] = $fixture['learners'];
        $rows = fn(int $userid): int => $DB->count_records(user_competency_course::TABLE, ['userid' => $userid]);
        $PAGE->set_url('/admin/tool/lp/coursecompetencies.php', ['courseid' => $course->id]);
        $PAGE->set_context(\core\context\course::instance((int) $course->id));
        $PAGE->force_theme('dimensions');
        $output = $PAGE->get_renderer('tool_lp');

        // The control: core's own export inserts the learner's rows.
        $this->setUser($first);
        (new \tool_lp\output\course_competencies_page((int) $course->id, 0))->export_for_template($output);
        $this->assertSame(3, $rows((int) $first->id));

        // The extras alone insert nothing.
        $this->setUser($second);
        coursecompetencies::for_viewer($course, $fixture['competencies']);
        $this->assertSame(0, $rows((int) $second->id));

        // The theme's page inserts what core's inserts, and nothing more.
        $output->render(new \tool_lp\output\course_competencies_page((int) $course->id, 0));
        $this->assertSame(3, $rows((int) $second->id));
    }

    /**
     * Each activity outcome is core's name in its tone.
     *
     * @return void
     */
    public function test_each_outcome_is_cores_name_in_its_tone(): void {
        $this->resetAfterTest();
        $fixture = $this->course_with_competencies(0, 1);
        $course = $fixture['course'];
        $competencyid = $fixture['competencies'][0];
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $expected = [];
        foreach (coursecompetencies::TONES as $outcome => $tone) {
            $page = $generator->create_module('page', ['course' => $course->id]);
            $lpg->create_course_module_competency([
                'cmid' => $page->cmid,
                'competencyid' => $competencyid,
                'ruleoutcome' => $outcome,
            ]);
            $expected[(int) $page->cmid] = [(string) course_module_competency::get_ruleoutcome_name($outcome), $tone];
        }
        $this->setUser($fixture['teacher']);

        $activities = coursecompetencies::for_viewer($course, [$competencyid])['competencies'][0]['activities'];
        $this->assertCount(4, $activities);
        foreach ($activities as $activity) {
            $this->assertSame($expected[$activity['cmid']], [$activity['outcomename'], $activity['outcometone']]);
        }
    }

    /**
     * A course in separate groups mode with a non-editing teacher who is in one group, and a learner of that group
     * rated proficient.
     *
     * @return array The fixture of {@see course_with_competencies()}, 'course' read again from the database, plus
     *     'grader' (the non-editing teacher).
     */
    private function separate_groups_course(): array {
        global $DB;
        $fixture = $this->course_with_competencies(3, 1);
        $generator = self::getDataGenerator();
        $courseid = (int) $fixture['course']->id;
        $grader = $generator->create_and_enrol($fixture['course'], 'teacher');
        $group = $generator->create_group(['courseid' => $courseid]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $grader->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $fixture['learners'][0]->id]);
        $this->rate((int) $fixture['learners'][0]->id, $courseid, $fixture['competencies'][0], 2, true);
        $this->set_groupmode($courseid, SEPARATEGROUPS);
        $fixture['course'] = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $fixture['grader'] = $grader;

        return $fixture;
    }

    /**
     * Set a course's group mode and return the course as the database now holds it.
     *
     * @param int $courseid The course.
     * @param int $mode NOGROUPS, SEPARATEGROUPS or VISIBLEGROUPS.
     * @return \stdClass The course.
     */
    private function set_groupmode(int $courseid, int $mode): \stdClass {
        global $DB;
        $DB->set_field('course', 'groupmode', $mode, ['id' => $courseid]);

        return $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    }

    /**
     * Whether the shared class cache holds a value for the course.
     *
     * @param int $courseid The course.
     * @return bool
     */
    private function class_cache_holds(int $courseid): bool {
        return \cache::make('theme_dimensions', 'coursecompetencyclass')->get($courseid) !== false;
    }

    /**
     * A grader limited to their groups gets no class figures, and the aggregate does not run for them: the class
     * cache stays empty. The same teacher in a course without groups, or holding accessallgroups, gets them.
     *
     * @return void
     */
    public function test_a_grader_limited_to_their_groups_gets_no_class_figures(): void {
        $this->resetAfterTest();
        $fixture = $this->separate_groups_course();
        $course = $fixture['course'];
        $courseid = (int) $course->id;
        $competencyid = $fixture['competencies'][0];
        \cache::make('theme_dimensions', 'coursecompetencyclass')->purge();

        $this->setUser($fixture['grader']);
        $result = coursecompetencies::for_viewer($course, [$competencyid]);
        $this->assertFalse($result['staff']);
        $this->assertFalse($result['competencies'][0]['hasdistribution']);
        $this->assertSame('', $result['competencies'][0]['progresstextvalue']);
        $this->assertFalse($this->class_cache_holds($courseid), 'The aggregate must not run for this viewer.');

        // Control 1: the same teacher once the course has no groups.
        $nogroups = $this->set_groupmode($courseid, NOGROUPS);
        $result = coursecompetencies::for_viewer($nogroups, [$competencyid]);
        $this->assertTrue($result['staff']);
        $this->assertTrue($result['competencies'][0]['hasdistribution']);
        $this->assertSame('1 of 3 proficient', $result['competencies'][0]['progresstextvalue']);
        $this->assertTrue($this->class_cache_holds($courseid));

        // Control 1b: visible groups.
        \cache::make('theme_dimensions', 'coursecompetencyclass')->purge();
        $visible = $this->set_groupmode($courseid, VISIBLEGROUPS);
        $this->assertTrue(coursecompetencies::for_viewer($visible, [$competencyid])['staff']);
    }

    /**
     * The control that differs by the capability alone: separate groups, and accessallgroups on the teacher's role.
     *
     * @return void
     */
    public function test_a_grader_with_accessallgroups_gets_the_class_figures(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->separate_groups_course();
        $course = $fixture['course'];
        $competencyid = $fixture['competencies'][0];
        $context = \core\context\course::instance((int) $course->id);

        $this->setUser($fixture['grader']);
        $this->assertFalse(coursecompetencies::for_viewer($course, [$competencyid])['staff']);

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $roleid, $context->id, true);
        $context->mark_dirty();
        $result = coursecompetencies::for_viewer($course, [$competencyid]);
        $this->assertTrue($result['staff']);
        $this->assertSame('1 of 3 proficient', $result['competencies'][0]['progresstextvalue']);
    }

    /**
     * Whoever manages the course's competencies gets the figures in separate groups mode, a learner never does.
     *
     * @return void
     */
    public function test_a_manager_gets_the_class_figures_in_separate_groups_and_a_learner_none(): void {
        $this->resetAfterTest();
        $fixture = $this->separate_groups_course();
        $course = $fixture['course'];
        $competencyid = $fixture['competencies'][0];

        $this->setUser($fixture['teacher']);
        $result = coursecompetencies::for_viewer($course, [$competencyid]);
        $this->assertTrue($result['staff']);
        $this->assertTrue($result['competencies'][0]['hasdistribution']);

        $this->setUser($fixture['learners'][0]);
        $result = coursecompetencies::for_viewer($course, [$competencyid]);
        $this->assertFalse($result['staff']);
        $this->assertFalse($result['competencies'][0]['hasdistribution']);
    }

    /**
     * The theme's learner flag is core's: for a learner, a teacher and a user not enrolled, it equals whether core's
     * own page exports a gradable user for the same viewer.
     *
     * @return void
     */
    public function test_the_learner_flag_is_cores_gradable_rule(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $fixture = $this->course_with_competencies(1, 1);
        $course = $fixture['course'];
        $context = \core\context\course::instance((int) $course->id);
        $outsider = self::getDataGenerator()->create_user();
        $roleid = create_role('Viewer', 'dimviewer', 'Views course competencies');
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        assign_capability('moodle/competency:coursecompetencyview', CAP_ALLOW, $roleid, \core\context\system::instance()->id);
        role_assign($roleid, $outsider->id, \core\context\system::instance()->id);
        $PAGE->set_url('/admin/tool/lp/coursecompetencies.php', ['courseid' => $course->id]);
        $PAGE->set_context($context);
        $PAGE->force_theme('dimensions');
        $output = $PAGE->get_renderer('tool_lp');

        $expected = ['learner' => true, 'teacher' => false, 'outsider' => false];
        $viewers = ['learner' => $fixture['learners'][0], 'teacher' => $fixture['teacher'], 'outsider' => $outsider];
        foreach ($viewers as $name => $user) {
            $this->setUser($user);
            $page = new \tool_lp\output\course_competencies_page((int) $course->id, 0);
            $core = isset($page->export_for_template($output)->gradableuserid);
            $this->assertSame($expected[$name], $core, "Core's flag for the {$name}");
            $this->assertSame($core, coursecompetencies::is_learner($context), "The theme's flag for the {$name}");
        }
    }

    /**
     * The manual enrolment instance of a course.
     *
     * @param int $courseid The course.
     * @return \stdClass
     */
    private function manual_instance(int $courseid): \stdClass {
        global $DB;
        return $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', MUST_EXIST);
    }
}
