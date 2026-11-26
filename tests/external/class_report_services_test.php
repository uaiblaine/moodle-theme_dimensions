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
 * Theme Dimensions - Tests for the class report's web services
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use context_course;
use core_competency\api;
use core_external\external_api;
use core_privacy\local\request\writer;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\manager;
use core_reportbuilder\table\system_report_table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use theme_dimensions\local\competency\classreport;
use theme_dimensions\local\competency\classreportengine;
use theme_dimensions\local\competency\classreportpreference;
use theme_dimensions\local\competency\reportpersistents;
use theme_dimensions\reportbuilder\local\systemreports\class_ratings;
use theme_dimensions\reportbuilder\local\systemreports\class_ratings_table;

/**
 * The class report's three web services: each refusal entered through the service itself, beside the control that is
 * answered; the group rule on rows, figures and facet counts; the cells equal to the report's own table; the column
 * preference (cleaning, one reader's choice, the default, its removal and export, core's services refused); the search
 * on the rows, the figures and the download; and the statements of a call bounded.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(class_report_rows::class)]
#[CoversClass(class_report_summary::class)]
#[CoversClass(set_class_report_preference::class)]
#[CoversClass(classreportengine::class)]
#[CoversClass(classreportpreference::class)]
#[CoversClass(class_ratings::class)]
#[CoversClass(class_ratings_table::class)]
#[CoversClass(classreport::class)]
#[CoversClass(reportpersistents::class)]
#[CoversClass(\theme_dimensions\privacy\provider::class)]
#[CoversFunction('xmldb_theme_dimensions_uninstall')]
final class class_report_services_test extends \advanced_testcase {
    /** @var string The rows service. */
    private const ROWS = 'theme_dimensions_class_report_rows';

    /** @var string The summary service. */
    private const SUMMARY = 'theme_dimensions_class_report_summary';

    /** @var string The preference's writer. */
    private const SET = 'theme_dimensions_set_class_report_preference';

    /** @var \stdClass The course. */
    private $course;

    /** @var array Learners by name: zelda, yuri, xena. */
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
     * resetAfterTest() does not restore superglobals: drop the session key the calls plant in $_POST.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        parent::tearDown();
    }

    /**
     * A course with two competencies on two scales, three learners, a teacher and an editing teacher.
     *
     * Zelda is Great (proficient) in the first and Expert (proficient) in the second; Yuri is Bad (not proficient) in
     * the first; Xena has no rating.
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
        foreach (['zelda', 'yuri', 'xena'] as $name) {
            $this->learners[$name] = $generator->create_and_enrol(
                $this->course,
                'student',
                ['firstname' => ucfirst($name), 'lastname' => 'Learner']
            );
        }
        $this->teacher = $generator->create_and_enrol($this->course, 'teacher');
        $this->editor = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->rate('zelda', $this->first, 3, 1);
        $this->rate('yuri', $this->first, 1, 0);
        $this->rate('zelda', $this->second, 2, 1);
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
     * Become a user with no group chosen yet, and no report instance kept for the previous one.
     *
     * @param \stdClass $user The user.
     * @return void
     */
    private function as_user(\stdClass $user): void {
        global $SESSION;

        $this->setUser($user);
        $SESSION->activegroup = [];
        manager::reset_caches();
    }

    /**
     * Call a service as its ajax door does: through core's dispatcher, with the session key, a request of its own.
     *
     * @param string $function The service.
     * @param array $args Its arguments.
     * @return array The dispatcher's answer: error, then data or exception.
     */
    private function call(string $function, array $args): array {
        $_POST['sesskey'] = sesskey();
        manager::reset_caches();

        return external_api::call_external_function($function, $args, true);
    }

    /**
     * Call a service that must answer, and return its data.
     *
     * @param string $function The service.
     * @param array $args Its arguments.
     * @return array The data.
     */
    private function answer(string $function, array $args): array {
        $answer = $this->call($function, $args);
        $this->assertFalse($answer['error'], $function . ': ' . json_encode($answer['exception'] ?? null));

        return $answer['data'];
    }

    /**
     * Assert that a dispatcher answer is a refusal with an error code and carries nothing.
     *
     * @param array $answer The dispatcher's answer.
     * @param string $errorcode The expected error code.
     * @param string $message What the call was.
     * @return void
     */
    private function assert_refused(array $answer, string $errorcode, string $message): void {
        $this->assertTrue($answer['error'], $message . ': the door refused.');
        $this->assertArrayNotHasKey('data', $answer, $message);
        $this->assertSame($errorcode, $answer['exception']->errorcode ?? '', $message);
    }

    /**
     * The three services with arguments for the fixture's course.
     *
     * @param int $courseid The course named.
     * @return array Service => arguments.
     */
    private function services(int $courseid): array {
        return [
            self::ROWS => ['courseid' => $courseid],
            self::SUMMARY => ['courseid' => $courseid],
            self::SET => ['courseid' => $courseid, 'columns' => ['comp:' . $this->second->get('id')]],
        ];
    }

    /**
     * The user ids of a rows answer, sorted.
     *
     * @param array $data The rows answer.
     * @return int[]
     */
    private static function row_ids(array $data): array {
        $ids = array_map('intval', array_column($data['rows'], 'userid'));
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
     * The competency keys a rows answer shows, in the report's column order.
     *
     * @param array $data The rows answer.
     * @return string[]
     */
    private static function active_competencies(array $data): array {
        $keys = [];
        foreach ($data['columns'] as $column) {
            if ($column['active'] && str_starts_with($column['key'], 'comp:')) {
                $keys[] = $column['key'];
            }
        }

        return $keys;
    }

    /**
     * The competency filters a rows answer offers.
     *
     * @param array $data The rows answer.
     * @return string[] The filters' names.
     */
    private static function competency_filters(array $data): array {
        return array_values(preg_grep('~^coursecompetency:comp~', array_column($data['filters'], 'name')));
    }

    /**
     * The counts of one competency's filter in a rows answer, by option value.
     *
     * @param array $data The rows answer.
     * @param \core_competency\competency $competency The competency.
     * @return array Value => count.
     */
    private static function counts(array $data, $competency): array {
        foreach ($data['filters'] as $filter) {
            if ($filter['name'] === 'coursecompetency:comp' . $competency->get('id')) {
                return array_column($filter['options'], 'count', 'value');
            }
        }

        return [];
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
     * Whether a user holds a stored choice on the fixture's course.
     *
     * @param \stdClass $user The user.
     * @return bool
     */
    private function has_choice(\stdClass $user): bool {
        global $DB;

        return $DB->record_exists('user_preferences', [
            'userid' => $user->id,
            'name' => classreportpreference::name((int) $this->course->id),
        ]);
    }

    /**
     * The teacher is answered by every service; a learner is refused by every one, and stores nothing.
     *
     * @return void
     */
    public function test_every_service_answers_the_teacher_and_refuses_a_learner(): void {
        $this->class_of_three();

        $this->as_user($this->teacher);
        foreach ($this->services((int) $this->course->id) as $function => $args) {
            $this->answer($function, $args);
        }
        $this->assertTrue($this->has_choice($this->teacher), 'The control: the teacher stored a choice.');

        $this->as_user($this->learners['zelda']);
        foreach ($this->services((int) $this->course->id) as $function => $args) {
            $this->assert_refused($this->call($function, $args), 'errorreportview', $function);
        }
        $this->assertFalse($this->has_choice($this->learners['zelda']), 'A refused learner stores nothing.');
    }

    /**
     * The guest account is refused by every service even when it has entered the course through guest access and its
     * role holds every capability the class report asks for (so the guest rule is the only one left), and stores
     * nothing.
     *
     * @return void
     */
    public function test_every_service_refuses_the_guest_holding_the_capabilities(): void {
        global $CFG, $DB;
        $this->class_of_three();
        $context = context_course::instance($this->course->id);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $this->course->id, 'enrol' => 'guest']);
        $guestrole = (int) $CFG->guestroleid;
        foreach (['moodle/competency:coursecompetencyview', classreport::USERVIEW] as $capability) {
            assign_capability($capability, CAP_ALLOW, $guestrole, $context);
        }
        accesslib_clear_all_caches_for_unit_testing();

        $this->setGuestUser();
        manager::reset_caches();
        // The course page first, as a visitor of it would: core grants the temporary guest access there.
        require_login($this->course, false, null, false, true);
        $this->assertTrue(classreport::may_view($context), 'Precondition: only the guest rule refuses the guest.');
        foreach ($this->services((int) $this->course->id) as $function => $args) {
            $this->assert_refused($this->call($function, $args), 'noguest', $function);
        }
        $this->assertFalse($this->has_choice(guest_user()));
    }

    /**
     * A teacher without usercompetencyview at course level is refused by every service; with it, answered.
     *
     * @return void
     */
    public function test_every_service_refuses_without_usercompetencyview(): void {
        $this->class_of_three();
        $this->as_user($this->teacher);
        $this->answer(self::ROWS, ['courseid' => $this->course->id]);

        assign_capability(classreport::USERVIEW, CAP_PROHIBIT, self::role('teacher'), context_course::instance($this->course->id));
        accesslib_clear_all_caches_for_unit_testing();
        $this->as_user($this->teacher);
        foreach ($this->services((int) $this->course->id) as $function => $args) {
            $this->assert_refused($this->call($function, $args), 'errorreportview', $function);
        }
        $this->assertFalse($this->has_choice($this->teacher));
    }

    /**
     * Competencies switched off on the site: every service refuses with core's own refusal.
     *
     * @return void
     */
    public function test_every_service_refuses_with_competencies_off(): void {
        $this->class_of_three();
        $this->as_user($this->teacher);
        $this->answer(self::SUMMARY, ['courseid' => $this->course->id]);

        set_config('enabled', 0, 'core_competency');
        foreach ($this->services((int) $this->course->id) as $function => $args) {
            $this->assert_refused($this->call($function, $args), 'competenciesarenotenabled', $function);
        }
        $this->assertFalse($this->has_choice($this->teacher));
    }

    /**
     * A course that does not exist answers as a course the caller cannot enter, word for word; the teacher of the
     * course is answered.
     *
     * @return void
     */
    public function test_a_missing_course_answers_as_an_unreachable_one(): void {
        $this->class_of_three();
        $outsider = $this->getDataGenerator()->create_user();
        $missing = (int) $this->course->id + 1000;

        $this->as_user($this->teacher);
        $this->answer(self::ROWS, ['courseid' => $this->course->id]);
        foreach ($this->services($missing) as $function => $args) {
            $this->assert_refused($this->call($function, $args), 'errorreportview', $function . ' of a missing course');
        }
        $this->as_user($outsider);
        $messages = [];
        foreach ($this->services((int) $this->course->id) as $function => $args) {
            $answer = $this->call($function, $args);
            $this->assert_refused($answer, 'errorreportview', $function . ' by an outsider');
            $messages[] = $answer['exception']->message;
        }
        $this->as_user($this->teacher);
        $answer = $this->call(self::ROWS, ['courseid' => $missing]);
        $this->assertSame($messages[0], $answer['exception']->message, 'One refusal for both.');
    }

    /**
     * The site course is no class: refused even to the administrator, who is answered on the course.
     *
     * @return void
     */
    public function test_the_site_course_is_refused(): void {
        $this->class_of_three();
        $this->setAdminUser();
        manager::reset_caches();
        $this->answer(self::ROWS, ['courseid' => $this->course->id]);

        $this->assertFalse(classreport::may_view(context_course::instance(SITEID)));
        foreach ($this->services((int) SITEID) as $function => $args) {
            $this->assert_refused($this->call($function, $args), 'errorreportview', $function);
        }
    }

    /**
     * Separate groups: a grader in group A gets A's rows, A's figures and A's facet counts; a grader in no group gets
     * none of them; a grader holding accessallgroups gets the class.
     *
     * @return void
     */
    public function test_a_separate_groups_grader_gets_their_groups_rows_figures_and_counts(): void {
        $this->class_of_three(SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        $a = $generator->create_group(['courseid' => $this->course->id]);
        $b = $generator->create_group(['courseid' => $this->course->id]);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $this->learners['zelda']->id]);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $this->learners['yuri']->id]);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $this->teacher->id]);
        $nogroup = $generator->create_and_enrol($this->course, 'teacher');
        $args = ['courseid' => $this->course->id];

        $this->as_user($this->editor);
        $rows = $this->answer(self::ROWS, $args);
        $this->assertSame($this->ids('zelda', 'yuri', 'xena'), self::row_ids($rows), 'The control: accessallgroups.');
        $this->assertSame(3, $this->answer(self::SUMMARY, $args)['learners']);
        $this->assertSame(3, array_sum(self::counts($rows, $this->first)));

        $this->as_user($this->teacher);
        $rows = $this->answer(self::ROWS, $args);
        $this->assertSame($this->ids('zelda'), self::row_ids($rows));
        $this->assertSame(1, $rows['total']);
        $summary = $this->answer(self::SUMMARY, $args);
        $this->assertSame([1, 2], [$summary['learners'], $summary['proficient']]);
        $this->assertSame(['0' => 0, '1' => 0, '2' => 0, '3' => 1], self::counts($rows, $this->first));

        $this->as_user($nogroup);
        $rows = $this->answer(self::ROWS, $args);
        $this->assertSame([], $rows['rows']);
        $this->assertTrue($rows['nogroup']);
        $this->assertSame(0, $this->answer(self::SUMMARY, $args)['learners']);
        $this->assertSame(0, array_sum(self::counts($rows, $this->first)));
    }

    /**
     * Separate groups: a posted group the grader is not in lists nobody, figures and counts included; the grader's own
     * group and "all participants" (their own groups) list their group.
     *
     * @return void
     */
    public function test_a_posted_group_the_grader_may_not_read_lists_nobody(): void {
        $this->class_of_three(SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        $a = $generator->create_group(['courseid' => $this->course->id]);
        $b = $generator->create_group(['courseid' => $this->course->id]);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $this->learners['zelda']->id]);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $this->learners['yuri']->id]);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $this->teacher->id]);
        $this->as_user($this->teacher);

        $own = $this->answer(self::ROWS, ['courseid' => $this->course->id, 'groupid' => (int) $a->id]);
        $this->assertSame($this->ids('zelda'), self::row_ids($own), 'The control: the grader\'s own group.');
        $this->assertSame((int) $a->id, $own['groupid']);
        $all = $this->answer(self::ROWS, ['courseid' => $this->course->id, 'groupid' => 0]);
        $this->assertSame($this->ids('zelda'), self::row_ids($all), 'All participants: the grader\'s own groups.');

        $args = ['courseid' => $this->course->id, 'groupid' => (int) $b->id];
        $rows = $this->answer(self::ROWS, $args);
        $this->assertSame([], $rows['rows']);
        $this->assertTrue($rows['nogroup']);
        $this->assertSame(0, array_sum(self::counts($rows, $this->first)));
        $this->assertSame(0, $this->answer(self::SUMMARY, $args)['learners']);
    }

    /**
     * Visible groups: a posted group of another course lists nobody, even one holding a learner of the course; a group
     * of the course lists its members.
     *
     * @return void
     */
    public function test_a_posted_group_of_another_course_lists_nobody(): void {
        $this->class_of_three(VISIBLEGROUPS);
        $generator = $this->getDataGenerator();
        $b = $generator->create_group(['courseid' => $this->course->id]);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $this->learners['yuri']->id]);
        // Another course's group holding a learner of this course: a group the rule did not check would list her.
        $other = $generator->create_course();
        $generator->enrol_user($this->learners['zelda']->id, $other->id, 'student');
        $elsewhere = $generator->create_group(['courseid' => $other->id]);
        $generator->create_group_member(['groupid' => $elsewhere->id, 'userid' => $this->learners['zelda']->id]);
        $this->as_user($this->teacher);

        $rows = $this->answer(self::ROWS, ['courseid' => $this->course->id, 'groupid' => (int) $b->id]);
        $this->assertSame($this->ids('yuri'), self::row_ids($rows), 'The control: a group of the course.');

        $args = ['courseid' => $this->course->id, 'groupid' => (int) $elsewhere->id];
        $rows = $this->answer(self::ROWS, $args);
        $this->assertSame([], $rows['rows']);
        $this->assertTrue($rows['nogroup']);
        $this->assertSame(0, $this->answer(self::SUMMARY, $args)['learners']);
    }

    /**
     * Two calls of one request (a `core/ajax` batch) that differ in their search: core keeps the first call's report
     * for the whole request, so the second must be refused rather than answered from it. The same call repeated is
     * answered, and the second call alone, in a request of its own, is answered with its own rows.
     *
     * @return void
     */
    public function test_a_second_call_in_one_request_is_never_answered_with_the_first_ones_rows(): void {
        $this->class_of_three();
        $this->as_user($this->teacher);
        $_POST['sesskey'] = sesskey();
        $args = ['courseid' => $this->course->id, 'search' => 'Zelda'];

        $first = external_api::call_external_function(self::ROWS, $args, true);
        $this->assertFalse($first['error']);
        $this->assertSame($this->ids('zelda'), self::row_ids($first['data']));

        // No manager::reset_caches() between the calls, as in a batch.
        $repeat = external_api::call_external_function(self::SUMMARY, $args, true);
        $this->assertFalse($repeat['error'], 'The same report in the same request is answered.');
        $this->assertSame(1, $repeat['data']['learners']);

        $second = external_api::call_external_function(self::ROWS, ['search' => 'Yuri'] + $args, true);
        $this->assert_refused($second, 'invalidparameter', 'Another search in the same request');
        $moved = external_api::call_external_function(self::SUMMARY, ['moduleid' => 99999] + $args, true);
        $this->assert_refused($moved, 'invalidparameter', 'Another activity in the same request');
        $regrouped = external_api::call_external_function(self::ROWS, ['groupid' => 12345] + $args, true);
        $this->assert_refused($regrouped, 'invalidparameter', 'Another group in the same request');

        $alone = $this->answer(self::ROWS, ['search' => 'Yuri'] + $args);
        $this->assertSame($this->ids('yuri'), self::row_ids($alone), 'The control: its own request.');
        $this->assertSame('Yuri', $alone['search']);
    }

    /**
     * The preference's writer reads at most 200 column keys and 100 widths: one more is refused before anything is
     * cleaned or stored; the full lists are accepted and cut to the cap and to MAX_WIDTHS.
     *
     * @return void
     */
    public function test_a_list_past_the_services_bound_is_refused(): void {
        $this->class_of_three();
        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $keys = [];
        for ($i = 0; $i < classreport::MAX_COLUMNS + 2; $i++) {
            $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
            $lpg->create_course_competency(['courseid' => $this->course->id, 'competencyid' => $competency->get('id')]);
            $keys[] = 'comp:' . $competency->get('id');
        }
        $forged = [];
        for ($i = 1; count($keys) + count($forged) < classreportpreference::MAX_POSTED_COLUMNS + 1; $i++) {
            $forged[] = 'comp:' . (900000000 + $i);
        }
        $toomany = array_merge($keys, $forged);
        $this->assertCount(classreportpreference::MAX_POSTED_COLUMNS + 1, $toomany);
        $this->as_user($this->teacher);

        $this->assert_refused(
            $this->call(self::SET, ['courseid' => $this->course->id, 'columns' => $toomany]),
            'invalidparameter',
            '201 columns'
        );
        $this->assertFalse($this->has_choice($this->teacher), 'A refusal stores nothing.');

        $stored = $this->answer(self::SET, ['courseid' => $this->course->id, 'columns' => array_slice($toomany, 0, 200)]);
        $this->assertSame(array_slice($keys, 0, classreport::MAX_COLUMNS), $stored['columns'], 'The control: 200, cut to 30.');

        $widths = [];
        for ($i = 1; $i <= classreportpreference::MAX_POSTED_WIDTHS + 1; $i++) {
            $widths[] = ['key' => 'comp:' . $i, 'width' => 100];
        }
        $this->assert_refused(
            $this->call(self::SET, ['courseid' => $this->course->id, 'widths' => $widths]),
            'invalidparameter',
            '101 widths'
        );
        $stored = $this->answer(self::SET, [
            'courseid' => $this->course->id,
            'columns' => [$keys[0]],
            'widths' => array_slice($widths, 0, classreportpreference::MAX_POSTED_WIDTHS),
        ]);
        $this->assertCount(classreportpreference::MAX_WIDTHS, $stored['widths'], 'The control: 100, cut to MAX_WIDTHS.');
    }

    /**
     * A service has no page to take the group from: a negative group is an invalid parameter in both reads, 0 is every
     * group the rule admits, and a group that is nobody's reads as nobody, however large the id.
     *
     * @return void
     */
    public function test_a_negative_group_is_refused_and_a_huge_one_lists_nobody(): void {
        $this->class_of_three(SEPARATEGROUPS);
        [$a] = $this->two_groups();
        $this->as_user($this->teacher);

        foreach ([self::ROWS, self::SUMMARY] as $function) {
            foreach ([-1, -5] as $negative) {
                $result = $this->call($function, ['courseid' => $this->course->id, 'groupid' => $negative]);
                $this->assert_refused($result, 'invalidparameter', $function . ' with group ' . $negative);
                // Refused as a negative group, before a report is built: the engine's check of one report per
                // request answers with the same code, so the message is what tells the two apart.
                $this->assertStringContainsString(
                    'The group is 0 for every group',
                    (string) ($result['exception']->debuginfo ?? ''),
                    $function . ' with group ' . $negative
                );
            }
        }
        $this->assertSame(
            $this->ids('zelda'),
            self::row_ids($this->answer(self::ROWS, ['courseid' => $this->course->id, 'groupid' => (int) $a->id])),
            'The control: the grader\'s own group.'
        );
        $this->assertSame(
            $this->ids('zelda'),
            self::row_ids($this->answer(self::ROWS, ['courseid' => $this->course->id])),
            'The default is 0: the grader\'s own groups.'
        );
        $huge = ['courseid' => $this->course->id, 'groupid' => 2147483647];
        $rows = $this->answer(self::ROWS, $huge);
        $this->assertSame([], $rows['rows']);
        $this->assertTrue($rows['nogroup']);
        $this->assertSame(0, $this->answer(self::SUMMARY, $huge)['learners']);
    }

    /**
     * Visible groups, with and without accessallgroups: the groups of the course's default grouping may be chosen, by
     * the editor and by the teacher alike, and nobody has a choice by default.
     *
     * @return void
     */
    public function test_visible_groups_may_be_chosen_with_or_without_accessallgroups(): void {
        $this->class_of_three(VISIBLEGROUPS);
        [$a, $b] = $this->two_groups();
        $context = context_course::instance($this->course->id);
        $this->assertTrue(has_capability('moodle/site:accessallgroups', $context, $this->editor));
        $this->assertFalse(has_capability('moodle/site:accessallgroups', $context, $this->teacher));

        foreach ([$this->editor, $this->teacher] as $reader) {
            $this->as_user($reader);
            $who = 'reader ' . $reader->id;
            $all = $this->answer(self::ROWS, ['courseid' => $this->course->id]);
            $this->assertSame($this->ids('zelda', 'yuri', 'xena'), self::row_ids($all), $who . ': no choice lists everybody.');
            $rows = $this->answer(self::ROWS, ['courseid' => $this->course->id, 'groupid' => (int) $b->id]);
            $this->assertSame($this->ids('yuri'), self::row_ids($rows), $who . ': any group may be chosen.');
            $summary = $this->answer(self::SUMMARY, ['courseid' => $this->course->id, 'groupid' => (int) $a->id]);
            $this->assertSame(1, $summary['learners'], $who);
            $rows = $this->answer(self::ROWS, ['courseid' => $this->course->id, 'groupid' => 2147483647]);
            $this->assertSame([], self::row_ids($rows), $who . ': a group of nobody lists nobody.');
        }
    }

    /**
     * The groups a grader may read and choose are those of the course's default grouping, in separate groups and in
     * visible ones: a grader in A and B, the grouping holding A only, lists A's learners and nobody for a posted B;
     * with no default grouping both groups are theirs, the control.
     *
     * @return void
     */
    public function test_the_default_grouping_limits_the_groups_that_may_be_read(): void {
        global $DB;
        $this->class_of_three(SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        [$a, $b] = $this->two_groups();
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $this->teacher->id]);
        $grouping = $generator->create_grouping(['courseid' => $this->course->id]);
        $generator->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $a->id]);
        $id = ['courseid' => $this->course->id];

        $this->as_user($this->teacher);
        $this->assertSame(
            $this->ids('zelda', 'yuri'),
            self::row_ids($this->answer(self::ROWS, $id)),
            'The control: without a default grouping both of the grader\'s groups are theirs.'
        );

        $DB->set_field('course', 'defaultgroupingid', $grouping->id, ['id' => $this->course->id]);
        rebuild_course_cache((int) $this->course->id, true);
        $this->as_user($this->teacher);
        $this->assertSame($this->ids('zelda'), self::row_ids($this->answer(self::ROWS, $id)), 'B is outside the grouping.');
        $this->assertSame(1, $this->answer(self::SUMMARY, $id)['learners']);
        $posted = $this->answer(self::ROWS, $id + ['groupid' => (int) $b->id]);
        $this->assertSame([], $posted['rows']);
        $this->assertTrue($posted['nogroup']);
        $this->assertSame($this->ids('zelda'), self::row_ids($this->answer(self::ROWS, $id + ['groupid' => (int) $a->id])));

        // Visible groups: the same grouping decides what may be chosen.
        $DB->set_field('course', 'groupmode', VISIBLEGROUPS, ['id' => $this->course->id]);
        rebuild_course_cache((int) $this->course->id, true);
        $this->as_user($this->teacher);
        $this->assertSame(
            $this->ids('zelda'),
            self::row_ids($this->answer(self::ROWS, $id + ['groupid' => (int) $a->id])),
            'The control: a group of the grouping may be chosen.'
        );
        $posted = $this->answer(self::ROWS, $id + ['groupid' => (int) $b->id]);
        $this->assertSame([], $posted['rows'], 'A group outside the grouping lists nobody.');
        $this->assertTrue($posted['nogroup']);
    }

    /**
     * An activity the viewer cannot see is an unknown one: a reader without moodle/course:viewhiddenactivities who
     * posts the id of a hidden activity gets no column and no narrowing; an activity of the course they can see, and
     * an editing teacher posting the hidden one, are the controls that do get its competencies.
     *
     * @return void
     */
    public function test_a_hidden_activity_adds_no_column_for_a_viewer_who_cannot_see_it(): void {
        $this->class_of_three();
        $generator = $this->getDataGenerator();
        $hidden = $generator->create_module('page', ['course' => $this->course->id, 'visible' => 0]);
        $shown = $generator->create_module('page', ['course' => $this->course->id]);
        $this->setAdminUser();
        api::add_competency_to_course_module($hidden->cmid, $this->first->get('id'));
        api::add_competency_to_course_module($shown->cmid, $this->first->get('id'));

        // A grader who reads the class report but holds no viewhiddenactivities.
        $roleid = create_role('Class grader', 'dimclassgrader', '');
        set_role_contextlevels($roleid, [CONTEXT_COURSE]);
        foreach ([classreport::USERVIEW, 'moodle/competency:coursecompetencyview'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, \context_system::instance()->id);
        }
        $viewer = $generator->create_and_enrol($this->course, 'dimclassgrader');
        $context = context_course::instance($this->course->id);
        $this->assertFalse(has_capability('moodle/course:viewhiddenactivities', $context, $viewer));
        $id = ['courseid' => $this->course->id];
        $firstkey = 'comp:' . $this->first->get('id');

        $this->as_user($viewer);
        $everyone = $this->answer(self::ROWS, $id);
        $data = $this->answer(self::ROWS, $id + ['moduleid' => (int) $shown->cmid]);
        $this->assertSame([$firstkey], self::active_competencies($data), 'The control: an activity the viewer sees.');
        $this->assertSame(self::row_ids($everyone), self::row_ids($data));

        $data = $this->answer(self::ROWS, $id + ['moduleid' => (int) $hidden->cmid]);
        $this->assertSame([], self::active_competencies($data), 'No column from a hidden activity.');
        $this->assertSame(0, $data['competencies']);
        $this->assertSame(self::row_ids($everyone), self::row_ids($data), 'It narrows nothing.');
        $unknown = $this->answer(self::ROWS, $id + ['moduleid' => 99999]);
        $this->assertSame([$data['competencies'], self::active_competencies($data)], [
            $unknown['competencies'],
            self::active_competencies($unknown),
        ], 'Read as an unknown activity.');
        $summary = $this->answer(self::SUMMARY, $id + ['moduleid' => (int) $hidden->cmid]);
        $this->assertSame([], $summary['competencies']);

        $this->as_user($this->editor);
        $data = $this->answer(self::ROWS, $id + ['moduleid' => (int) $hidden->cmid]);
        $this->assertSame([$firstkey], self::active_competencies($data), 'The control: a teacher who sees hidden activities.');
        $this->assertSame(1, $data['competencies']);
    }

    /**
     * Two groups of the fixture's course: zelda in the first, yuri in the second, the teacher in the first.
     *
     * @return array The groups, A then B.
     */
    private function two_groups(): array {
        $generator = $this->getDataGenerator();
        $a = $generator->create_group(['courseid' => $this->course->id]);
        $b = $generator->create_group(['courseid' => $this->course->id]);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $this->learners['zelda']->id]);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $this->learners['yuri']->id]);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $this->teacher->id]);

        return [$a, $b];
    }

    /**
     * The service's cells are the report's own table's, cell for cell, for the same viewer, grading hooks included;
     * the typed values beside them say the rating and the proficiency.
     *
     * @return void
     */
    public function test_the_cells_are_the_reports_own_tables(): void {
        $this->class_of_three();
        $this->as_user($this->teacher);
        $data = $this->answer(self::ROWS, ['courseid' => $this->course->id, 'perpage' => 100]);

        manager::reset_caches();
        $report = class_ratings::for_course(context_course::instance($this->course->id));
        $table = system_report_table::create((int) $report->get_report_persistent()->get('id'), $report->get_parameters());
        $table->define_baseurl(new \moodle_url('/report/competency/index.php'));
        $table->setup();
        $table->query_db(100, false);
        $expected = [];
        foreach ($table->rawdata as $record) {
            $formatted = $table->format_row($record);
            foreach ($report->active_keys() as $key => $unique) {
                $expected[(int) $record->id][$key] = (string) $formatted[$report->get_column($unique)->get_column_alias()];
            }
        }
        $table->close_recordset();

        $answered = [];
        $ratings = [];
        foreach ($data['rows'] as $row) {
            foreach ($row['cells'] as $cell) {
                $answered[(int) $row['userid']][$cell['key']] = $cell['html'];
            }
            foreach ($row['ratings'] as $rating) {
                $ratings[(int) $row['userid']][$rating['key']] = [$rating['grade'], $rating['proficient']];
            }
        }
        ksort($expected);
        ksort($answered);
        $this->assertSame($expected, $answered);
        $first = 'comp:' . $this->first->get('id');
        $this->assertStringContainsString('data-user-competency="true"', $answered[$this->learners['xena']->id][$first]);
        $this->assertSame([3, true], $ratings[$this->learners['zelda']->id][$first]);
        $this->assertSame([1, false], $ratings[$this->learners['yuri']->id][$first]);
        $this->assertSame([null, false], $ratings[$this->learners['xena']->id][$first]);
    }

    /**
     * The catalogue lists every competency of the course; the first thirty are the default and the shown columns,
     * with a filter each; a choice shows its competencies alone, in the course's order whatever order was posted, with
     * their filters alone; the page's report reads the same choice.
     *
     * @return void
     */
    public function test_the_catalogue_lists_every_competency_and_active_follows_the_choice(): void {
        $this->class_of_three();
        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $last = null;
        for ($i = 0; $i < classreport::MAX_COLUMNS; $i++) {
            $last = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
            $lpg->create_course_competency(['courseid' => $this->course->id, 'competencyid' => $last->get('id')]);
        }
        $lastkey = 'comp:' . $last->get('id');
        $firstkey = 'comp:' . $this->first->get('id');
        $this->as_user($this->teacher);

        $data = $this->answer(self::ROWS, ['courseid' => $this->course->id]);
        $catalogue = array_values(array_filter($data['columns'], static fn(array $c): bool => str_starts_with($c['key'], 'comp:')));
        $this->assertCount(classreport::MAX_COLUMNS + 2, $catalogue);
        $this->assertSame(classreport::MAX_COLUMNS + 2, $data['competencies']);
        $this->assertCount(classreport::MAX_COLUMNS, self::active_competencies($data));
        $this->assertSame([true, true], [$catalogue[0]['active'], $catalogue[0]['default']]);
        $this->assertSame([false, false], [end($catalogue)['active'], end($catalogue)['default']]);
        $this->assertSame('Salads', $catalogue[0]['title']);
        $this->assertCount(classreport::MAX_COLUMNS, self::competency_filters($data));
        $this->assertSame('learner', $data['columns'][0]['key']);
        $this->assertTrue($data['columns'][0]['fixed']);
        $this->assertSame(get_string('competency_classreport_learner', 'theme_dimensions'), $data['columns'][0]['title']);
        $this->assertSame('Learner', $data['columns'][0]['title']);

        // Posted last-first: the report shows the course's order whatever order was posted.
        $stored = $this->answer(self::SET, ['courseid' => $this->course->id, 'columns' => [$lastkey, $firstkey]]);
        $this->assertSame([$firstkey, $lastkey], $stored['columns']);
        $data = $this->answer(self::ROWS, ['courseid' => $this->course->id]);
        $this->assertSame([$firstkey, $lastkey], self::active_competencies($data));
        $this->assertSame(
            ['coursecompetency:comp' . $this->first->get('id'), 'coursecompetency:comp' . $last->get('id')],
            self::competency_filters($data)
        );
        $cellkeys = array_column($data['rows'][0]['cells'], 'key');
        $this->assertSame([$firstkey, $lastkey], array_values(preg_grep('~^comp:~', $cellkeys)));
        $this->assertSame([$firstkey, $lastkey], $data['preference']['columns']);

        manager::reset_caches();
        $page = class_ratings::for_course(context_course::instance($this->course->id));
        $this->assertSame([$firstkey, $lastkey], array_values(preg_grep('~^comp:~', array_keys($page->active_keys()))));
    }

    /**
     * A forged id, an id of another course's competency, a key of another shape and a repeated key are dropped; a list
     * past the cap keeps its first thirty; widths are clamped and keys of no column's shape dropped.
     *
     * @return void
     */
    public function test_the_stored_choice_is_cleaned(): void {
        $this->class_of_three();
        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $other = $generator->create_course();
        $foreign = $lpg->create_competency(['competencyframeworkid' => $lpg->create_framework()->get('id')]);
        $lpg->create_course_competency(['courseid' => $other->id, 'competencyid' => $foreign->get('id')]);
        $firstkey = 'comp:' . $this->first->get('id');
        $this->as_user($this->teacher);

        $stored = $this->answer(self::SET, [
            'courseid' => $this->course->id,
            'columns' => ['comp:999999999', 'comp:' . $foreign->get('id'), 'comp:abc', 'learner', $firstkey, $firstkey],
            'widths' => [
                ['key' => $firstkey, 'width' => 5],
                ['key' => 'learner', 'width' => 99999],
                ['key' => 'bad key', 'width' => 100],
            ],
        ]);
        $this->assertSame([$firstkey], $stored['columns']);
        $this->assertFalse($stored['usedefault']);
        $this->assertSame([
            ['key' => $firstkey, 'width' => classreportpreference::MIN_WIDTH],
            ['key' => 'learner', 'width' => classreportpreference::MAX_WIDTH],
        ], $stored['widths']);
        $read = classreportpreference::read((int) $this->course->id);
        $this->assertSame([$firstkey], classreportpreference::export($read)['columns']);

        // Only forged ids: the default columns.
        $stored = $this->answer(self::SET, ['courseid' => $this->course->id, 'columns' => ['comp:' . $foreign->get('id')]]);
        $this->assertTrue($stored['usedefault']);

        $framework = $lpg->create_framework();
        $keys = [];
        for ($i = 0; $i < classreport::MAX_COLUMNS + 2; $i++) {
            $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
            $lpg->create_course_competency(['courseid' => $this->course->id, 'competencyid' => $competency->get('id')]);
            $keys[] = 'comp:' . $competency->get('id');
        }
        $stored = $this->answer(self::SET, ['courseid' => $this->course->id, 'columns' => $keys]);
        $this->assertSame(array_slice($keys, 0, classreport::MAX_COLUMNS), $stored['columns']);
    }

    /**
     * One reader's choice is theirs: another reader of the same course reads the default.
     *
     * @return void
     */
    public function test_one_readers_choice_is_never_anothers(): void {
        $this->class_of_three();
        $secondkey = 'comp:' . $this->second->get('id');
        $both = ['comp:' . $this->first->get('id'), $secondkey];

        $this->as_user($this->teacher);
        $this->answer(self::SET, ['courseid' => $this->course->id, 'columns' => [$secondkey]]);
        $this->assertSame([$secondkey], self::active_competencies($this->answer(self::ROWS, ['courseid' => $this->course->id])));

        $this->as_user($this->editor);
        $data = $this->answer(self::ROWS, ['courseid' => $this->course->id]);
        $this->assertSame($both, self::active_competencies($data));
        $this->assertTrue($data['preference']['usedefault']);
    }

    /**
     * "Use the default" forgets the choice, leaving no row, and the rows show the default columns again.
     *
     * @return void
     */
    public function test_use_the_default_leaves_no_row(): void {
        $this->class_of_three();
        $both = ['comp:' . $this->first->get('id'), 'comp:' . $this->second->get('id')];
        $this->as_user($this->teacher);
        $this->answer(self::SET, [
            'courseid' => $this->course->id,
            'columns' => [$both[1]],
            'widths' => [['key' => 'learner', 'width' => 200]],
        ]);
        $this->assertTrue($this->has_choice($this->teacher), 'Precondition: a choice is stored.');

        $stored = $this->answer(self::SET, ['courseid' => $this->course->id, 'usedefault' => true, 'columns' => [$both[1]]]);
        $this->assertSame(['usedefault' => true, 'columns' => [], 'widths' => []], $stored);
        $this->assertFalse($this->has_choice($this->teacher));
        $this->assertSame($both, self::active_competencies($this->answer(self::ROWS, ['courseid' => $this->course->id])));
    }

    /**
     * The figures add up to the rows: the learners are the rows' total, and per shown competency the proficient, the
     * rated not proficient and the unrated add up to them; a stored filter narrows both alike, and its own facet counts
     * the rows without it.
     *
     * @return void
     */
    public function test_the_summary_adds_up_to_the_rows(): void {
        $this->class_of_three();
        $this->as_user($this->editor);
        $args = ['courseid' => $this->course->id];

        $rows = $this->answer(self::ROWS, $args);
        $summary = $this->answer(self::SUMMARY, $args);
        $this->assertSame($rows['total'], $summary['learners']);
        $this->assertSame([3, 6, 3, 2, 1], [
            $summary['learners'],
            $summary['cells'],
            $summary['rated'],
            $summary['proficient'],
            $summary['unrated'],
        ]);
        $figures = [];
        foreach ($summary['competencies'] as $competency) {
            $this->assertSame(
                $summary['learners'],
                $competency['proficient'] + $competency['notproficient'] + $competency['notrated'],
                $competency['title']
            );
            $figures[$competency['title']] = [$competency['proficient'], $competency['notproficient'], $competency['notrated']];
        }
        $this->assertSame(['Salads' => [1, 1, 1], 'Desserts' => [1, 0, 2]], $figures);

        $report = class_ratings::for_course(context_course::instance($this->course->id));
        $filter = 'coursecompetency:comp' . $this->first->get('id');
        $report->set_filter_values([$filter . '_operator' => select::EQUAL_TO, $filter . '_value' => 3]);
        $rows = $this->answer(self::ROWS, $args);
        $summary = $this->answer(self::SUMMARY, $args);
        $this->assertSame($this->ids('zelda'), self::row_ids($rows));
        $this->assertSame([1, 1], [$rows['total'], $summary['learners']]);
        $this->assertSame(['0' => 1, '1' => 1, '2' => 0, '3' => 1], self::counts($rows, $this->first), 'Counted without itself.');
        $this->assertSame(['0' => 0, '1' => 0, '2' => 1], self::counts($rows, $this->second), 'Counted under it.');
    }

    /**
     * The search narrows the rows, the figures and the download alike; a search of one character searches nothing.
     *
     * @return void
     */
    public function test_the_search_narrows_rows_figures_and_download(): void {
        $this->class_of_three();
        $this->as_user($this->teacher);
        $context = context_course::instance($this->course->id);

        $rows = $this->answer(self::ROWS, ['courseid' => $this->course->id, 'search' => 'ELD']);
        $this->assertSame($this->ids('zelda'), self::row_ids($rows));
        $this->assertSame('ELD', $rows['search']);
        $this->assertSame(1, $this->answer(self::SUMMARY, ['courseid' => $this->course->id, 'search' => 'eld'])['learners']);

        $short = $this->answer(self::ROWS, ['courseid' => $this->course->id, 'search' => 'Z']);
        $this->assertSame($this->ids('zelda', 'yuri', 'xena'), self::row_ids($short), 'One character searches nothing.');
        $this->assertSame('', $short['search']);

        manager::reset_caches();
        $searched = class_ratings::for_course($context, 0, null, 'eld')->get_parameters();
        $this->assertSame($this->ids('zelda'), $this->downloaded($searched));
        manager::reset_caches();
        $plain = class_ratings::for_course($context)->get_parameters();
        $this->assertSame($this->ids('zelda', 'yuri', 'xena'), $this->downloaded($plain), 'The control: no search.');
    }

    /**
     * The rows of a download, read as core's download.php reads them, from the report's parameters.
     *
     * @param array $parameters The report's parameters.
     * @return int[] The user ids, sorted.
     */
    private function downloaded(array $parameters): array {
        manager::reset_caches();
        $report = class_ratings::for_course(
            context_course::instance($this->course->id),
            (int) ($parameters[class_ratings::PARAM_MODULE] ?? 0),
            null,
            (string) ($parameters[class_ratings::PARAM_SEARCH] ?? '')
        );
        $this->assertTrue($report->can_be_downloaded());
        // The export writer prints the file's byte order mark as the table is set up: a buffer of its own, thrown away.
        ob_start();
        try {
            $table = system_report_table::create(
                (int) $report->get_report_persistent()->get('id'),
                $parameters + ['download' => 'csv']
            );
            $table->define_baseurl(new \moodle_url('/reportbuilder/download.php'));
            $table->setup();
            $table->query_db(0, false);
            $ids = [];
            foreach ($table->rawdata as $record) {
                $ids[] = (int) $record->id;
            }
            $table->close_recordset();
        } finally {
            ob_end_clean();
        }
        sort($ids);

        return $ids;
    }

    /**
     * Deleting a course removes every reader's choice on it, and uninstalling the theme every choice; another
     * course's choice, and another plugin's preference, stay.
     *
     * @return void
     */
    public function test_course_deletion_and_uninstall_remove_the_choices(): void {
        global $CFG, $DB;
        // The course_deleted observer is external: core dispatches it when the outer transaction commits, which a
        // test's own transaction never does.
        $this->preventResetByRollback();
        $this->class_of_three();
        $other = $this->getDataGenerator()->create_course();
        foreach ([$this->teacher, $this->editor] as $reader) {
            set_user_preference(classreportpreference::name((int) $this->course->id), '{"c":[1],"w":{}}', $reader->id);
            set_user_preference(classreportpreference::name((int) $other->id), '{"c":[2],"w":{}}', $reader->id);
        }
        set_user_preference('theme_dimensions_classreportx', 'kept', $this->teacher->id);
        $named = static fn(int $courseid): int => $DB->count_records(
            'user_preferences',
            ['name' => classreportpreference::name($courseid)]
        );

        delete_course($this->course, false);
        $this->assertSame(0, $named((int) $this->course->id));
        $this->assertSame(2, $named((int) $other->id), 'The control: another course\'s choices stay.');

        require_once($CFG->dirroot . '/theme/dimensions/db/uninstall.php');
        xmldb_theme_dimensions_uninstall();
        $this->assertSame(0, $named((int) $other->id));
        $this->assertTrue(
            $DB->record_exists('user_preferences', ['name' => 'theme_dimensions_classreportx']),
            'The control: a preference outside the pattern stays.'
        );
    }

    /**
     * A data request exports each of the reader's choices, as stored, under its own name; another reader's stays out.
     *
     * @return void
     */
    public function test_the_choices_are_exported(): void {
        $this->class_of_three();
        $secondkey = 'comp:' . $this->second->get('id');
        $this->as_user($this->teacher);
        $this->answer(self::SET, ['courseid' => $this->course->id, 'columns' => [$secondkey]]);
        $name = classreportpreference::name((int) $this->course->id);

        writer::reset();
        \theme_dimensions\privacy\provider::export_user_preferences((int) $this->teacher->id);
        $exported = writer::with_context(\context_system::instance())->get_user_preferences('theme_dimensions');
        $this->assertObjectHasProperty($name, $exported);
        $this->assertSame(get_user_preferences($name, null, $this->teacher->id), $exported->{$name}->value);
        $this->assertSame(
            get_string('privacy:metadata:preference:classreport', 'theme_dimensions'),
            $exported->{$name}->description
        );

        writer::reset();
        \theme_dimensions\privacy\provider::export_user_preferences((int) $this->editor->id);
        $exported = writer::with_context(\context_system::instance())->get_user_preferences('theme_dimensions');
        $this->assertObjectNotHasProperty($name, (object) $exported);
    }

    /**
     * Core's preference services refuse to write the choice (core_user_set_user_preferences and
     * core_user_update_user_preferences); the same calls write a preference the theme lets core write.
     *
     * @return void
     */
    public function test_cores_preference_services_refuse_to_write_the_choice(): void {
        $this->class_of_three();
        $this->as_user($this->teacher);
        $name = classreportpreference::name((int) $this->course->id);
        $focus = \theme_dimensions\local\focusmode::PREFERENCE_EXPANDED;

        $answer = $this->call('core_user_set_user_preferences', ['preferences' => [
            ['name' => $name, 'value' => '{"c":[1]}', 'userid' => $this->teacher->id],
            ['name' => $focus, 'value' => '1', 'userid' => $this->teacher->id],
        ]]);
        $this->assertFalse($answer['error']);
        $this->assertNull(get_user_preferences($name, null, $this->teacher->id));
        $this->assertSame('1', get_user_preferences($focus, null, $this->teacher->id), 'The control: the same call writes.');

        $answer = $this->call('core_user_update_user_preferences', ['preferences' => [
            ['type' => $name, 'value' => '{"c":[1]}'],
            ['type' => $focus, 'value' => '0'],
        ]]);
        $this->assertFalse($answer['error']);
        $this->assertNull(get_user_preferences($name, null, $this->teacher->id));
        $this->assertSame('0', get_user_preferences($focus, null, $this->teacher->id), 'The control: the same call writes.');
    }

    /**
     * The statements of a rows call and of a summary call do not grow with the learners, and grow at most one per
     * competency column. Each size is called once to warm the caches, then measured.
     *
     * @return void
     */
    public function test_the_statements_of_a_call_are_bounded(): void {
        global $DB;
        $this->class_of_three();
        $this->as_user($this->teacher);
        $measure = function (string $function) use ($DB): int {
            $this->answer($function, ['courseid' => $this->course->id]);
            $start = $DB->perf_get_reads();
            $this->answer($function, ['courseid' => $this->course->id]);
            return $DB->perf_get_reads() - $start;
        };

        $three = [$measure(self::ROWS), $measure(self::SUMMARY)];
        for ($i = 0; $i < 6; $i++) {
            $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        }
        $nine = [$measure(self::ROWS), $measure(self::SUMMARY)];
        $this->assertSame($three, $nine, 'Three learners cost ' . json_encode($three) . ', nine ' . json_encode($nine) . '.');

        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        for ($i = 0; $i < 4; $i++) {
            $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
            $lpg->create_course_competency(['courseid' => $this->course->id, 'competencyid' => $competency->get('id')]);
        }
        $six = [$measure(self::ROWS), $measure(self::SUMMARY)];
        foreach ([0, 1] as $call) {
            $this->assertLessThanOrEqual(
                $nine[$call] + 4,
                $six[$call],
                'Two columns cost ' . json_encode($nine) . ', six ' . json_encode($six) . '.'
            );
        }
    }
}
