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
 * Theme Dimensions - Tests for every door core offers to the class report
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\reportbuilder;

use context_course;
use core_competency\api;
use core_external\external_api;
use core_reportbuilder\exception\report_access_exception;
use core_reportbuilder\local\helpers\user_filter_manager;
use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\user_filter;
use core_reportbuilder\manager;
use core_reportbuilder\system_report_factory;
use core_reportbuilder\table\system_report_table;
use core_table\local\filter\filter;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\competency\classreport;
use theme_dimensions\reportbuilder\local\systemreports\class_ratings;

/**
 * The class report is rebuilt, and so re-checked, on every door core offers to a system report: the retrieve
 * service, the dynamic table service, the filter services and the download. Each refusal is derived from the
 * viewer and the report's persistent on the server, and each stands beside the control that is answered.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(class_ratings::class)]
#[CoversClass(classreport::class)]
final class class_ratings_doors_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass Another course the outsider teaches. */
    private $other;

    /** @var array User name => user: zelda (group A), yuri (group B), xena (no group), the learners. */
    private $learners = [];

    /** @var \stdClass A non-editing teacher of the course, in group A. */
    private $teacher;

    /** @var \stdClass A non-editing teacher of the course in no group. */
    private $nogroup;

    /** @var \stdClass An editing teacher of the course: accessallgroups. */
    private $editor;

    /** @var \stdClass A teacher of the other course only. */
    private $outsider;

    /** @var \stdClass[] The course's groups: a (zelda, the teacher) and b (yuri). */
    private $groups = [];

    /** @var int The course's report persistent. */
    private $reportid;

    /** @var int The other course's report persistent. */
    private $otherreportid;

    /**
     * Two courses, the first with three learners, three teachers and a competency, each with its report persistent.
     *
     * @param int $groupmode The first course's group mode, forced.
     * @return void
     */
    private function fixture(int $groupmode = NOGROUPS): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $this->course = $generator->create_course(['groupmode' => $groupmode, 'groupmodeforce' => 1]);
        $this->other = $generator->create_course();
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => 'Pastrywork',
        ]);
        $lpg->create_course_competency(['courseid' => $this->course->id, 'competencyid' => $competency->get('id')]);
        foreach (['zelda', 'yuri', 'xena'] as $name) {
            $this->learners[$name] = $generator->create_and_enrol($this->course, 'student', ['firstname' => ucfirst($name)]);
        }
        $this->teacher = $generator->create_and_enrol($this->course, 'teacher');
        $this->nogroup = $generator->create_and_enrol($this->course, 'teacher');
        $this->editor = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->outsider = $generator->create_and_enrol($this->other, 'teacher');
        $a = $generator->create_group(['courseid' => $this->course->id]);
        $b = $generator->create_group(['courseid' => $this->course->id]);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $this->learners['zelda']->id]);
        $generator->create_group_member(['groupid' => $b->id, 'userid' => $this->learners['yuri']->id]);
        $generator->create_group_member(['groupid' => $a->id, 'userid' => $this->teacher->id]);
        $this->groups = ['a' => $a, 'b' => $b];

        $this->setAdminUser();
        manager::reset_caches();
        $this->reportid = (int) class_ratings::for_course(context_course::instance($this->course->id))
            ->get_report_persistent()->get('id');
        $this->otherreportid = (int) class_ratings::for_course(context_course::instance($this->other->id))
            ->get_report_persistent()->get('id');
    }

    /**
     * Become a user with no group chosen yet, and no report instance cached for the previous one.
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
     * Call a service as its ajax door does: through core's dispatcher, with the session key.
     *
     * @param string $function The service.
     * @param array $args Its arguments.
     * @param bool $ajaxonly Whether the call comes from the ajax door.
     * @return array The dispatcher's answer: error, then data or exception.
     */
    private function call(string $function, array $args, bool $ajaxonly = true): array {
        $_POST['sesskey'] = sesskey();
        manager::reset_caches();

        return external_api::call_external_function($function, $args, $ajaxonly);
    }

    /**
     * The retrieve service's arguments for a course's report.
     *
     * @param int $courseid The course.
     * @param int $moduleid The posted activity.
     * @param int|null $groupid The posted group, or null for none.
     * @return array
     */
    private static function retrieve_args(int $courseid, int $moduleid = 0, ?int $groupid = null): array {
        $parameters = [['name' => class_ratings::PARAM_MODULE, 'value' => (string) $moduleid]];
        if ($groupid !== null) {
            $parameters[] = ['name' => class_ratings::PARAM_GROUP, 'value' => (string) $groupid];
        }

        return [
            'source' => class_ratings::class,
            'context' => ['contextid' => context_course::instance($courseid)->id],
            'component' => 'theme_dimensions',
            'area' => class_ratings::AREA,
            'itemid' => 0,
            'parameters' => $parameters,
            'page' => 0,
            'perpage' => 30,
        ];
    }

    /**
     * The dynamic table service's arguments for a report persistent, as core's JavaScript sends them.
     *
     * @param int $reportid The persistent.
     * @return array
     */
    private static function table_args(int $reportid): array {
        return [
            'component' => 'core_reportbuilder',
            'handler' => 'system_report_table',
            'uniqueid' => 'system-report-table-' . $reportid,
            'sortdata' => [],
            'filters' => [
                ['name' => 'reportid', 'jointype' => filter::JOINTYPE_ALL, 'values' => [$reportid]],
                [
                    'name' => 'parameters',
                    'jointype' => filter::JOINTYPE_ALL,
                    'values' => [json_encode([class_ratings::PARAM_MODULE => 0])],
                ],
            ],
            'jointype' => filter::JOINTYPE_ALL,
            'firstinitial' => null,
            'lastinitial' => null,
            'pagenumber' => null,
            'pagesize' => 30,
            'hiddencolumns' => [],
            'resetpreferences' => null,
        ];
    }

    /**
     * The stored filter values in core's table.
     *
     * @param int $reportid The report, or 0 for every report.
     * @return int
     */
    private function stored(int $reportid = 0): int {
        global $DB;

        return $DB->count_records(user_filter::TABLE, $reportid ? ['reportid' => $reportid] : []);
    }

    /**
     * Assert that a dispatcher answer is a refusal that carries nothing.
     *
     * @param array $answer The dispatcher's answer.
     * @return void
     */
    private function assert_refused(array $answer): void {
        $this->assertTrue($answer['error'], 'The door refused.');
        $this->assertArrayNotHasKey('data', $answer);
        $this->assertContains($answer['exception']->errorcode ?? '', [
            'errorreportview',
            'nopermissiontoaccesspage',
            'requireloginerror',
            'notenrolled',
        ]);
    }

    /**
     * The retrieve service: a student is refused, a teacher of another course naming this course's context is
     * refused (and answered in their own), the teacher of the course is answered with the class.
     *
     * @return void
     */
    public function test_the_retrieve_door_refuses_a_student_and_an_outsider(): void {
        $this->fixture();
        $this->as_user($this->teacher);
        $answer = $this->call('core_reportbuilder_retrieve_system_report', self::retrieve_args((int) $this->course->id), false);
        $this->assertFalse($answer['error'], 'The control: the teacher is answered.');
        $this->assertSame(3, $answer['data']['data']['totalrowcount']);
        $this->assertStringContainsString('Zelda', json_encode($answer['data']['data']['rows']));

        $this->as_user($this->learners['zelda']);
        $this->assert_refused(
            $this->call('core_reportbuilder_retrieve_system_report', self::retrieve_args((int) $this->course->id), false)
        );

        $this->as_user($this->outsider);
        $this->assert_refused(
            $this->call('core_reportbuilder_retrieve_system_report', self::retrieve_args((int) $this->course->id), false)
        );
        $answer = $this->call('core_reportbuilder_retrieve_system_report', self::retrieve_args((int) $this->other->id), false);
        $this->assertFalse($answer['error'], 'The control: the same teacher is answered in their own course.');
        $this->assertStringNotContainsString('Zelda', json_encode($answer));
    }

    /**
     * A tampered activity (one of another course, linked to a competency this course has) adds no column and no
     * error that names it; the course's own activity is the control that does add one.
     *
     * @return void
     */
    public function test_a_tampered_moduleid_adds_no_column(): void {
        $this->fixture();
        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $plating = $lpg->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Plating']);
        // Plating is a competency of both courses, linked to the other course's activity only.
        foreach ([$this->course, $this->other] as $course) {
            $lpg->create_course_competency(['courseid' => $course->id, 'competencyid' => $plating->get('id')]);
        }
        $foreign = $generator->create_module('page', ['course' => $this->other->id, 'name' => 'Hidden foreign page']);
        $own = $generator->create_module('page', ['course' => $this->course->id, 'name' => 'Own page']);
        $this->setAdminUser();
        api::add_competency_to_course_module($foreign->cmid, $plating->get('id'));
        api::add_competency_to_course_module($own->cmid, $plating->get('id'));

        $this->as_user($this->teacher);
        $answer = $this->call(
            'core_reportbuilder_retrieve_system_report',
            self::retrieve_args((int) $this->course->id, (int) $own->cmid),
            false
        );
        $this->assertFalse($answer['error']);
        $headers = json_encode($answer['data']['data']['headers']);
        $this->assertStringContainsString('Plating', $headers, 'The control: an own activity.');
        $this->assertStringNotContainsString('Pastrywork', $headers);

        $answer = $this->call(
            'core_reportbuilder_retrieve_system_report',
            self::retrieve_args((int) $this->course->id, (int) $foreign->cmid),
            false
        );
        $this->assertFalse($answer['error'], 'No error that tells the activity from a missing one.');
        $this->assertStringNotContainsString('Plating', json_encode($answer['data']['data']['headers']));
        $this->assertStringNotContainsString('Pastrywork', json_encode($answer['data']['data']['headers']));
        $this->assertStringNotContainsString('Hidden foreign page', json_encode($answer));
    }

    /**
     * The dynamic table service builds the report from the persistent's id: a student and an outsider naming this
     * course's persistent are refused, the teacher is answered.
     *
     * @return void
     */
    public function test_the_dynamic_table_door_refuses_a_student_and_an_outsider(): void {
        $this->fixture();
        $_POST['info'] = 'core_table_get_dynamic_table_content';

        $this->as_user($this->teacher);
        $answer = $this->call('core_table_get_dynamic_table_content', self::table_args($this->reportid));
        $this->assertFalse($answer['error'], 'The control: the teacher is answered.');
        $this->assertStringContainsString('Zelda', $answer['data']['html']);

        $this->as_user($this->learners['zelda']);
        $this->assert_refused($this->call('core_table_get_dynamic_table_content', self::table_args($this->reportid)));

        $this->as_user($this->outsider);
        $answer = $this->call('core_table_get_dynamic_table_content', self::table_args($this->reportid));
        $this->assert_refused($answer);
        $this->assertStringNotContainsString('Zelda', json_encode($answer));
    }

    /**
     * The filter services: a student is refused and stores nothing, a teacher of another course naming this
     * course's report is refused, the teacher's call stores one row; a refused reset removes nothing.
     *
     * @return void
     */
    public function test_the_filter_doors_refuse_a_student_and_an_outsider(): void {
        $this->fixture();
        $parameters = json_encode([class_ratings::PARAM_MODULE => 0]);
        $values = json_encode(['user:fullname_operator' => 1, 'user:fullname_value' => 'Zelda']);
        $set = ['reportid' => $this->reportid, 'parameters' => $parameters, 'values' => $values];

        $before = $this->stored();
        $this->as_user($this->learners['zelda']);
        $this->assert_refused($this->call('core_reportbuilder_set_filters', $set));
        $this->as_user($this->outsider);
        $this->assert_refused($this->call('core_reportbuilder_set_filters', $set));
        $this->assertSame($before, $this->stored(), 'A refusal stores nothing.');

        $this->as_user($this->teacher);
        $this->assertFalse($this->call('core_reportbuilder_set_filters', $set)['error'], 'The control: the teacher.');
        $this->assertSame($before + 1, $this->stored(), 'The teacher\'s call stores one row.');
        $this->assertSame(1, $this->stored($this->reportid));

        // A refused reset leaves the caller's own stored row where it was; the teacher's reset removes theirs.
        user_filter_manager::set($this->reportid, ['user:fullname_value' => 'Own'], (int) $this->learners['zelda']->id);
        $kept = $this->stored();
        $reset = ['reportid' => $this->reportid, 'parameters' => $parameters];
        $this->as_user($this->learners['zelda']);
        $this->assert_refused($this->call('core_reportbuilder_filters_reset', $reset));
        $this->as_user($this->outsider);
        $this->assert_refused($this->call('core_reportbuilder_filters_reset', $reset));
        $this->assertSame($kept, $this->stored(), 'A refusal removes nothing.');
        $this->as_user($this->teacher);
        $this->assertFalse($this->call('core_reportbuilder_filters_reset', $reset)['error'], 'The control: the teacher.');
        $this->assertSame($kept - 1, $this->stored(), 'The teacher\'s reset removes the teacher\'s row.');
    }

    /**
     * What core's download.php does for a system report: rebuild it from the persistent (the factory, which asks
     * can_view()), ask can_be_downloaded(), and read the table's rows.
     *
     * @param int $reportid The persistent's id.
     * @param array $extra More of the report's parameters, as the download's URL carries them.
     * @return int[] The user ids of the downloaded rows, sorted.
     * @throws report_access_exception When the report is refused.
     */
    private function downloaded(int $reportid, array $extra = []): array {
        manager::reset_caches();
        $persistent = new report($reportid);
        $parameters = [class_ratings::PARAM_MODULE => 0] + $extra;
        $systemreport = system_report_factory::create(
            $persistent->get('source'),
            $persistent->get_context(),
            $persistent->get('component'),
            $persistent->get('area'),
            (int) $persistent->get('itemid'),
            $parameters
        );
        if (!$systemreport->can_be_downloaded()) {
            throw new report_access_exception();
        }
        // The export writer prints the file's byte order mark as the table is set up, and core refuses to build a
        // second export while output is buffered: each download gets a buffer of its own, thrown away.
        ob_start();
        try {
            $table = system_report_table::create($reportid, $parameters + ['download' => 'csv']);
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
     * The ids of the named learners, sorted.
     *
     * @param string ...$names The learners' names in the fixture.
     * @return int[]
     */
    private function ids(string ...$names): array {
        $ids = array_map(fn(string $name): int => (int) $this->learners[$name]->id, $names);
        sort($ids);

        return $ids;
    }

    /**
     * The download: a student is refused, a teacher of another course naming this report is refused; with separate
     * groups a grader in group A downloads group A's learners, one in no group downloads nobody, and one holding
     * accessallgroups downloads everybody.
     *
     * @return void
     */
    public function test_the_download_door_follows_the_groups(): void {
        $this->fixture(SEPARATEGROUPS);

        $this->as_user($this->editor);
        $this->assertSame($this->ids('zelda', 'yuri', 'xena'), $this->downloaded($this->reportid), 'The control: accessallgroups.');
        $this->as_user($this->teacher);
        $this->assertSame($this->ids('zelda'), $this->downloaded($this->reportid), 'A group A grader gets group A.');
        $this->as_user($this->nogroup);
        $this->assertSame([], $this->downloaded($this->reportid), 'A grader in no group gets no row.');

        foreach ([$this->learners['zelda'], $this->outsider] as $refused) {
            $this->as_user($refused);
            try {
                $this->downloaded($this->reportid);
                $this->fail('The download was answered to ' . $refused->id);
            } catch (report_access_exception $exception) {
                $this->assertSame('errorreportview', $exception->errorcode);
            }
        }
    }

    /**
     * A group posted through core's own doors: a grader in group A who names group B is listed nobody by the retrieve
     * service and by the download, a group of nobody (however large) the same; naming A lists A, the control. A
     * negative group is the session's choice, which is the grader's own group, never everybody.
     *
     * @return void
     */
    public function test_a_posted_group_through_cores_doors_follows_the_group_rule(): void {
        $this->fixture(SEPARATEGROUPS);
        $a = (int) $this->groups['a']->id;
        $b = (int) $this->groups['b']->id;
        $course = (int) $this->course->id;
        $retrieve = fn(?int $group): array => $this->call(
            'core_reportbuilder_retrieve_system_report',
            self::retrieve_args($course, 0, $group),
            false
        );

        $this->as_user($this->teacher);
        $answer = $retrieve($a);
        $this->assertFalse($answer['error'], 'The control: naming the grader\'s own group.');
        $this->assertSame(1, $answer['data']['data']['totalrowcount']);
        $this->assertStringContainsString('Zelda', json_encode($answer['data']['data']['rows']));
        $this->assertSame($this->ids('zelda'), $this->downloaded($this->reportid, [class_ratings::PARAM_GROUP => $a]));

        foreach ([$b, 2147483647] as $group) {
            $answer = $retrieve($group);
            $this->assertFalse($answer['error'], 'A group the rule does not admit is no error, only no row.');
            $this->assertSame(0, $answer['data']['data']['totalrowcount'], 'Retrieve, group ' . $group);
            $this->assertStringNotContainsString('Yuri', json_encode($answer));
            $downloaded = $this->downloaded($this->reportid, [class_ratings::PARAM_GROUP => $group]);
            $this->assertSame([], $downloaded, 'Download, group ' . $group);
        }

        $answer = $retrieve(-1);
        $this->assertSame(1, $answer['data']['data']['totalrowcount'], 'A negative group is the session\'s: the grader\'s group.');
        $this->assertSame($this->ids('zelda'), $this->downloaded($this->reportid, [class_ratings::PARAM_GROUP => -1]));
    }
}
