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
 * Theme Dimensions - Tests for the class report of competency ratings
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\reportbuilder;

use context_course;
use core_reportbuilder\exception\report_access_exception;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\manager;
use core_reportbuilder\system_report_factory;
use core_reportbuilder\table\system_report_table;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\competency\classreport;
use theme_dimensions\reportbuilder\local\entities\coursecompetency;
use theme_dimensions\reportbuilder\local\filters\rating;
use theme_dimensions\reportbuilder\local\systemreports\class_ratings;

/**
 * The class report: who may read it, which learners it lists, its columns, cells, sort and filters, and that reading
 * it writes nothing.
 *
 * Every refusal stands beside the control that the same viewer is admitted with the force off. The rows are read
 * through core's own table (system_report_table), as the page, its sorts and page turns and the download read them.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(class_ratings::class)]
#[CoversClass(classreport::class)]
#[CoversClass(coursecompetency::class)]
#[CoversClass(rating::class)]
final class class_ratings_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private $course;

    /** @var array Learners by name: s1, s2, s3. */
    private $learners = [];

    /** @var \stdClass A non-editing teacher: rates, no accessallgroups. */
    private $teacher;

    /** @var \stdClass An editing teacher: rates, holds accessallgroups. */
    private $editor;

    /** @var \core_competency\competency On the framework's scale: Bad, Good, Great. */
    private $first;

    /** @var \core_competency\competency On its own scale: Novice, Expert. */
    private $second;

    /**
     * A course with two competencies on two scales, three learners, a teacher and an editing teacher.
     *
     * s1 is Great (proficient) in the first and Expert (proficient) in the second; s2 is Bad (not proficient) in the
     * first; s3 has no row at all.
     *
     * @param int $groupmode The course's group mode, forced.
     * @return void
     */
    private function class_of_three(int $groupmode = NOGROUPS): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $this->course = $generator->create_course(['groupmode' => $groupmode, 'groupmodeforce' => 1]);
        $scalea = $generator->create_scale(['scale' => 'Bad, Good, Great']);
        $scaleb = $generator->create_scale(['scale' => 'Novice, Expert']);
        $framework = $lpg->create_framework(['scaleid' => $scalea->id]);
        $this->first = $lpg->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Salads']);
        $this->second = $lpg->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Desserts',
            'scaleid' => $scaleb->id,
        ]);
        foreach ([$this->first, $this->second] as $competency) {
            $lpg->create_course_competency(['courseid' => $this->course->id, 'competencyid' => $competency->get('id')]);
        }
        foreach (['s1', 's2', 's3'] as $name) {
            $this->learners[$name] = $generator->create_and_enrol($this->course, 'student', ['firstname' => $name]);
        }
        $this->teacher = $generator->create_and_enrol($this->course, 'teacher');
        $this->editor = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->rate('s1', $this->first, 3, 1);
        $this->rate('s2', $this->first, 1, 0);
        $this->rate('s1', $this->second, 2, 1);
    }

    /**
     * Store a learner's rating in the course, as core keeps it.
     *
     * @param string $learner The learner's name in the fixture.
     * @param \core_competency\competency $competency The competency.
     * @param int $grade The rating, from 1.
     * @param int $proficiency 1 or 0.
     * @return void
     */
    private function rate(string $learner, $competency, int $grade, int $proficiency): void {
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_user_competency_course([
            'userid' => $this->learners[$learner]->id,
            'competencyid' => $competency->get('id'),
            'courseid' => $this->course->id,
            'grade' => $grade,
            'proficiency' => $proficiency,
        ]);
    }

    /**
     * Log in as a user, with no group chosen yet in this session.
     *
     * @param \stdClass $user The user.
     * @return void
     */
    private function as_user(\stdClass $user): void {
        global $SESSION;
        $this->setUser($user);
        $SESSION->activegroup = [];
    }

    /**
     * Choose a group in the page's group selector, as index.php stores it for the session.
     *
     * @param int $groupid The group, 0 for all participants.
     * @param string|int $mode The session's group mode key: SEPARATEGROUPS, VISIBLEGROUPS or 'aag'.
     * @return void
     */
    private function choose(int $groupid, $mode): void {
        global $SESSION;
        $SESSION->activegroup[$this->course->id][$mode][(int) $this->course->defaultgroupingid] = $groupid;
    }

    /**
     * Read the report's rows through core's own table, as the current user.
     *
     * @param array|null $sort [catalogue key, SORT_ASC or SORT_DESC], or null for the report's own order.
     * @param int $moduleid The activity parameter.
     * @return array [the rows' user ids in order, user id => catalogue key => formatted cell, the report].
     */
    private function read(?array $sort = null, int $moduleid = 0): array {
        manager::reset_caches();
        $report = class_ratings::for_course(context_course::instance($this->course->id), $moduleid);
        $table = system_report_table::create((int) $report->get_report_persistent()->get('id'), $report->get_parameters());
        $table->define_baseurl(new \moodle_url('/report/competency/index.php'));
        $sortdata = [];
        if ($sort !== null) {
            $alias = $report->get_column($report->active_keys()[$sort[0]])->get_column_alias();
            $sortdata[] = ['sortby' => $alias, 'sortorder' => $sort[1]];
        }
        $table->set_sortdata($sortdata);
        $table->setup();
        $table->query_db(100, false);
        $ids = [];
        $cells = [];
        foreach ($table->rawdata as $record) {
            $id = (int) $record->id;
            $ids[] = $id;
            $formatted = $table->format_row($record);
            foreach ($report->active_keys() as $key => $unique) {
                $cells[$id][$key] = (string) $formatted[$report->get_column($unique)->get_column_alias()];
            }
        }
        $table->close_recordset();

        return [$ids, $cells, $report];
    }

    /**
     * The user ids of the current user's rows, sorted.
     *
     * @return int[]
     */
    private function row_ids(): array {
        [$ids] = $this->read();
        sort($ids);

        return $ids;
    }

    /**
     * The ids of the named learners, sorted.
     *
     * @param string ...$names Learners' names in the fixture.
     * @return int[]
     */
    private function ids(string ...$names): array {
        $ids = array_map(fn(string $name): int => (int) $this->learners[$name]->id, $names);
        sort($ids);

        return $ids;
    }

    /**
     * The competency keys of a report's columns, in display order.
     *
     * @param class_ratings $report The report.
     * @return string[]
     */
    private static function comp_keys(class_ratings $report): array {
        return array_values(preg_grep('~^comp:~', array_keys($report->active_keys())));
    }

    /**
     * The id of a role by its short name.
     *
     * @param string $shortname The role.
     * @return int
     */
    private static function role(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * A student cannot open the class report, page or download; a teacher can, and lists the class.
     *
     * The download builds the report as the page does (core's download.php calls system_report_factory::create(),
     * which runs can_view() in the constructor), so the refusal is the same door.
     *
     * @return void
     */
    public function test_a_student_is_refused_and_a_teacher_is_not(): void {
        $this->class_of_three();
        $context = context_course::instance($this->course->id);

        $this->as_user($this->teacher);
        $this->assertTrue(classreport::may_view($context));
        $this->assertSame($this->ids('s1', 's2', 's3'), $this->row_ids(), 'The control: the teacher reads the class.');
        $this->assertTrue($this->read()[2]->can_be_downloaded(), 'The control: the teacher may download it.');

        $this->as_user($this->learners['s1']);
        $this->assertFalse(classreport::may_view($context));
        manager::reset_caches();
        $this->expectException(report_access_exception::class);
        system_report_factory::create(class_ratings::class, $context, 'theme_dimensions', class_ratings::AREA, 0, []);
    }

    /**
     * A teacher without the user competencies of the course as a whole (usercompetencyview at course level) is
     * refused the class mode.
     *
     * @return void
     */
    public function test_a_viewer_without_usercompetencyview_at_course_level_is_refused(): void {
        $this->class_of_three();
        $context = context_course::instance($this->course->id);
        $this->as_user($this->teacher);
        $this->assertTrue(classreport::may_view($context), 'The control: the teacher holds it.');

        assign_capability(classreport::USERVIEW, CAP_PROHIBIT, self::role('teacher'), $context);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(classreport::may_view($context));
    }

    /**
     * A teacher without the course's competencies (neither coursecompetencyview nor coursecompetencymanage) is
     * refused, whatever they may read of the learners.
     *
     * @return void
     */
    public function test_a_viewer_without_the_courses_competencies_is_refused(): void {
        $this->class_of_three();
        $context = context_course::instance($this->course->id);
        $this->as_user($this->teacher);
        $this->assertTrue(classreport::may_view($context), 'The control: the teacher reads them.');

        assign_capability('moodle/competency:coursecompetencyview', CAP_PROHIBIT, self::role('teacher'), $context);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(classreport::USERVIEW, $context), 'Precondition: the learners stay readable.');
        $this->assertFalse(classreport::may_view($context));
    }

    /**
     * A user holding every competency capability at the system context but no way into the course is refused; the
     * same user enrolled is admitted.
     *
     * @return void
     */
    public function test_a_viewer_who_cannot_enter_the_course_is_refused(): void {
        $this->class_of_three();
        $context = context_course::instance($this->course->id);
        $outsider = $this->getDataGenerator()->create_user();
        $roleid = create_role('Competency reader', 'compreader', '');
        $system = \context_system::instance();
        assign_capability('moodle/competency:coursecompetencyview', CAP_ALLOW, $roleid, $system);
        assign_capability(classreport::USERVIEW, CAP_ALLOW, $roleid, $system);
        role_assign($roleid, $outsider->id, $system);
        $this->as_user($outsider);
        $this->assertFalse(classreport::may_view($context));

        $this->getDataGenerator()->enrol_user($outsider->id, $this->course->id, 'student');
        $this->assertTrue(classreport::may_view($context), 'The control: enrolled, the same user is admitted.');
    }

    /**
     * Competencies switched off on the site: no class report, even for the teacher.
     *
     * @return void
     */
    public function test_disabled_competencies_refuse_the_class_report(): void {
        $this->class_of_three();
        $context = context_course::instance($this->course->id);
        $this->as_user($this->teacher);
        $this->assertTrue(classreport::may_view($context), 'The control: enabled.');

        set_config('enabled', 0, 'core_competency');
        $this->assertFalse(classreport::may_view($context));
    }

    /**
     * The rows are the gradable learners only: neither teacher is listed, although both are enrolled.
     *
     * @return void
     */
    public function test_the_rows_are_the_gradable_learners(): void {
        $this->class_of_three();
        $this->as_user($this->editor);

        $rows = $this->row_ids();
        $this->assertSame($this->ids('s1', 's2', 's3'), $rows);
        $this->assertNotContains((int) $this->teacher->id, $rows);
        $this->assertNotContains((int) $this->editor->id, $rows);
    }

    /**
     * Separate groups: a teacher in group A reads group A's learners only; an editing teacher (accessallgroups) reads
     * everybody.
     *
     * @return void
     */
    public function test_a_separate_groups_grader_reads_their_group_only(): void {
        $this->class_of_three(SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        $a = $generator->create_group(['courseid' => $this->course->id, 'name' => 'A']);
        $b = $generator->create_group(['courseid' => $this->course->id, 'name' => 'B']);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $this->learners['s1']->id]);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $this->learners['s2']->id]);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $this->teacher->id]);

        $this->as_user($this->editor);
        $this->assertSame($this->ids('s1', 's2', 's3'), $this->row_ids(), 'The control: accessallgroups reads everybody.');

        $this->as_user($this->teacher);
        $this->assertSame($this->ids('s1'), $this->row_ids());
    }

    /**
     * Separate groups: a teacher in no group reads no learner (core's page would show everybody); joining group B,
     * the same teacher reads B's learners.
     *
     * @return void
     */
    public function test_a_separate_groups_grader_in_no_group_reads_nobody(): void {
        $this->class_of_three(SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        $b = $generator->create_group(['courseid' => $this->course->id, 'name' => 'B']);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $this->learners['s2']->id]);

        $this->as_user($this->teacher);
        [$ids, , $report] = $this->read();
        $this->assertSame([], $ids);
        $this->assertTrue($report->no_group());

        $generator->create_group_member(['groupid' => $b->id, 'userid' => $this->teacher->id]);
        $this->as_user($this->teacher);
        [$ids, , $report] = $this->read();
        $this->assertSame($this->ids('s2'), $ids, 'The control: in a group, the teacher reads its members.');
        $this->assertFalse($report->no_group());
    }

    /**
     * Separate groups: a teacher in two groups reads the one chosen in the page's selector.
     *
     * @return void
     */
    public function test_the_chosen_group_narrows_a_grader_in_two_groups(): void {
        $this->class_of_three(SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        $a = $generator->create_group(['courseid' => $this->course->id, 'name' => 'A']);
        $b = $generator->create_group(['courseid' => $this->course->id, 'name' => 'B']);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $this->learners['s1']->id]);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $this->learners['s2']->id]);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $this->teacher->id]);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $this->teacher->id]);

        $this->as_user($this->teacher);
        $this->choose((int) $a->id, SEPARATEGROUPS);
        $this->assertSame($this->ids('s1'), $this->row_ids());
        $this->choose((int) $b->id, SEPARATEGROUPS);
        $this->assertSame($this->ids('s2'), $this->row_ids());
    }

    /**
     * Accessallgroups with a group chosen reads that group; visible groups with "All participants" read everybody.
     *
     * @return void
     */
    public function test_a_chosen_group_narrows_and_all_participants_does_not(): void {
        global $DB;
        $this->class_of_three(SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        $b = $generator->create_group(['courseid' => $this->course->id, 'name' => 'B']);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $this->learners['s2']->id]);

        $this->as_user($this->editor);
        $this->choose((int) $b->id, 'aag');
        $this->assertSame($this->ids('s2'), $this->row_ids());

        $DB->set_field('course', 'groupmode', VISIBLEGROUPS, ['id' => $this->course->id]);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $this->teacher->id]);
        $this->as_user($this->teacher);
        $this->choose(0, VISIBLEGROUPS);
        $this->assertSame($this->ids('s1', 's2', 's3'), $this->row_ids());
    }

    /**
     * The columns are the learner, the identity fields, then the course's competencies in the course's order (not
     * the order they were created in), keyed comp:<id>.
     *
     * @return void
     */
    public function test_the_columns_are_the_courses_competencies_in_order(): void {
        global $DB;
        $this->class_of_three();
        $this->as_user($this->teacher);

        [, , $report] = $this->read();
        $first = 'comp:' . $this->first->get('id');
        $second = 'comp:' . $this->second->get('id');
        $keys = array_keys($report->active_keys());
        $this->assertSame('learner', $keys[0]);
        $this->assertSame([$first, $second], self::comp_keys($report));
        $this->assertSame(2, $report->total_competencies());

        $DB->set_field('competency_coursecomp', 'sortorder', 9, ['competencyid' => $this->first->get('id')]);
        [, , $report] = $this->read();
        $this->assertSame([$second, $first], self::comp_keys($report));
    }

    /**
     * Each cell names the rating on THAT competency's scale, says the proficiency in words, and an unrated learner
     * reads "Not rated".
     *
     * @return void
     */
    public function test_a_cell_names_the_rating_on_its_own_scale(): void {
        $this->class_of_three();
        $this->as_user($this->teacher);
        [, $cells] = $this->read();
        $first = 'comp:' . $this->first->get('id');
        $second = 'comp:' . $this->second->get('id');
        $text = static fn(string $html): string => trim(preg_replace('~\s+~', ' ', strip_tags($html)));

        $this->assertSame('Great Proficient', $text($cells[$this->learners['s1']->id][$first]));
        $this->assertSame('Expert Proficient', $text($cells[$this->learners['s1']->id][$second]));
        $this->assertSame('Bad Not proficient', $text($cells[$this->learners['s2']->id][$first]));
        $this->assertSame('Not rated', $text($cells[$this->learners['s3']->id][$first]));
        $this->assertStringContainsString('dim-comp-pill--proficient', $cells[$this->learners['s1']->id][$first]);
        $this->assertStringContainsString('dim-comp-pill--notproficient', $cells[$this->learners['s2']->id][$first]);
        $this->assertStringContainsString(
            'report/competency/index.php?id=' . $this->course->id . '&amp;user=' . $this->learners['s1']->id,
            $cells[$this->learners['s1']->id]['learner']
        );
    }

    /**
     * A competency column sorts by its rating, the unrated last going down and first going up.
     *
     * @return void
     */
    public function test_a_competency_column_sorts_by_its_rating(): void {
        $this->class_of_three();
        $this->as_user($this->teacher);
        $key = 'comp:' . $this->first->get('id');

        [$down] = $this->read([$key, SORT_DESC]);
        $this->assertSame([
            (int) $this->learners['s1']->id,
            (int) $this->learners['s2']->id,
            (int) $this->learners['s3']->id,
        ], $down);
        [$up] = $this->read([$key, SORT_ASC]);
        $this->assertSame(array_reverse($down), $up);
    }

    /**
     * A competency's filter keeps the learners of one rating, or the unrated ones.
     *
     * @return void
     */
    public function test_a_competency_filter_keeps_one_rating(): void {
        $this->class_of_three();
        $this->as_user($this->teacher);
        [, , $report] = $this->read();
        $filter = 'coursecompetency:comp' . $this->first->get('id');
        $this->assertNotNull($report->get_filter($filter));

        $report->set_filter_values([$filter . '_operator' => select::EQUAL_TO, $filter . '_value' => 3]);
        [$ids] = $this->read();
        $this->assertSame($this->ids('s1'), $ids);

        $report->set_filter_values([$filter . '_operator' => select::EQUAL_TO, $filter . '_value' => 0]);
        [$ids] = $this->read();
        $this->assertSame($this->ids('s3'), $ids, 'The unrated learner, who has no row at all.');
    }

    /**
     * The grading dialogue's hooks are on the cells of a viewer who may rate, and nowhere for one who may not.
     *
     * @return void
     */
    public function test_the_cell_hooks_are_for_a_grader_only(): void {
        $this->class_of_three();
        $first = 'comp:' . $this->first->get('id');
        $s3 = (int) $this->learners['s3']->id;
        $this->as_user($this->teacher);
        [, $cells] = $this->read();
        $this->assertStringContainsString('data-user-competency="true"', $cells[$s3][$first], 'The control: a grader.');
        $this->assertStringContainsString('data-userid="' . $s3 . '"', $cells[$s3][$first]);
        $this->assertStringContainsString('data-competencyid="' . $this->first->get('id') . '"', $cells[$s3][$first]);
        $this->assertStringContainsString('data-courseid="' . $this->course->id . '"', $cells[$s3][$first]);

        assign_capability(classreport::GRADE, CAP_PROHIBIT, self::role('teacher'), context_course::instance($this->course->id));
        accesslib_clear_all_caches_for_unit_testing();
        $this->as_user($this->teacher);
        [, $cells] = $this->read();
        $this->assertStringNotContainsString('data-user-competency', $cells[$s3][$first]);
        $this->assertStringContainsString('user_competency_in_course.php', $cells[$s3][$first], 'The link stays.');
    }

    /**
     * Reading the report inserts no competency_usercompcourse row; core's export of one learner does (the control).
     *
     * @return void
     */
    public function test_reading_the_report_inserts_no_row(): void {
        global $DB, $PAGE;
        $this->class_of_three();
        $this->as_user($this->teacher);
        $PAGE->set_context(context_course::instance($this->course->id));
        $before = $DB->count_records(coursecompetency::TABLE);

        [$ids] = $this->read();
        $this->assertCount(3, $ids);
        $this->assertSame($before, $DB->count_records(coursecompetency::TABLE));

        $export = new \report_competency\output\report($this->course->id, $this->learners['s3']->id, 0);
        $export->export_for_template($PAGE->get_renderer('core'));
        $this->assertSame($before + 2, $DB->count_records(coursecompetency::TABLE), 'The control: core inserts.');
    }

    /**
     * With an activity, the columns are the competencies linked to it; an activity of another course gives none.
     *
     * @return void
     */
    public function test_an_activity_keeps_its_competencies(): void {
        $this->class_of_three();
        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $page = $generator->create_module('page', ['course' => $this->course->id]);
        $lpg->create_course_module_competency(['cmid' => $page->cmid, 'competencyid' => $this->second->get('id')]);
        $other = $generator->create_course();
        $elsewhere = $generator->create_module('page', ['course' => $other->id]);
        $this->as_user($this->teacher);

        [, , $report] = $this->read(null, (int) $page->cmid);
        $this->assertSame(['comp:' . $this->second->get('id')], self::comp_keys($report));

        [, , $report] = $this->read(null, (int) $elsewhere->cmid);
        $this->assertSame(0, $report->total_competencies());
        [, , $report] = $this->read();
        $this->assertSame(2, $report->total_competencies(), 'The control: no activity, every competency.');
    }

    /**
     * Past the cap, the first competencies in the course's order are the columns and the total says how many exist.
     *
     * @return void
     */
    public function test_the_columns_stop_at_the_cap(): void {
        $this->class_of_three();
        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $last = null;
        for ($i = 0; $i < classreport::MAX_COLUMNS - 1; $i++) {
            $last = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
            $lpg->create_course_competency(['courseid' => $this->course->id, 'competencyid' => $last->get('id')]);
        }
        $this->as_user($this->teacher);

        [, , $report] = $this->read();
        $this->assertSame(classreport::MAX_COLUMNS + 1, $report->total_competencies());
        $this->assertSame(classreport::MAX_COLUMNS, $report->shown_competencies());
        $this->assertArrayNotHasKey('comp:' . $last->get('id'), $report->active_keys(), 'The last in order is past the cap.');
    }

    /**
     * The statements of a page do not grow with the learners, and grow at most one per competency column.
     *
     * Each size is read once to warm the caches, then measured.
     *
     * @return void
     */
    public function test_the_statements_of_a_page_are_bounded(): void {
        global $DB;
        $this->class_of_three();
        $this->as_user($this->teacher);
        $measure = function () use ($DB): int {
            $this->read();
            $start = $DB->perf_get_reads();
            $this->read();
            return $DB->perf_get_reads() - $start;
        };

        $three = $measure();
        for ($i = 0; $i < 6; $i++) {
            $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        }
        $nine = $measure();
        $this->assertSame($three, $nine, "Three learners cost {$three} statements, nine cost {$nine}.");

        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        for ($i = 0; $i < 4; $i++) {
            $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
            $lpg->create_course_competency(['courseid' => $this->course->id, 'competencyid' => $competency->get('id')]);
        }
        $six = $measure();
        $this->assertLessThanOrEqual($nine + 4, $six, "Two columns cost {$nine} statements, six cost {$six}.");
    }
}
