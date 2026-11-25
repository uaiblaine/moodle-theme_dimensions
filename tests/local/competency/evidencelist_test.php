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
 * Theme Dimensions - Tests for the evidence page's dated list of the platform's records
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use core_competency\evidence;
use core_competency\user_competency;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for {@see evidencelist}: the two access rules, the rows left out and folded, the keyset paging, the filters and
 * the cost of a page.
 *
 * Every refusal sits beside a control that is answered, and every row left out sits beside a row of the same shape that
 * stays, so a rule that holds for the wrong reason (nothing to answer with) reddens.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(evidencelist::class)]
final class evidencelist_test extends \advanced_testcase {
    /** @var int A fixed instant the fixtures date their rows from: 15 October 2026, noon UTC. */
    private const NOON = 1792065600;

    /** @var int One day in seconds. */
    private const DAY = 86400;

    /**
     * The fold's months are counted back from now: freeze now a few weeks after the fixtures' instant, so the
     * fixtures stay inside the fold's window whenever the suite runs.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->mock_clock_with_frozen(self::NOON + 20 * self::DAY);
    }

    /**
     * A learner with two competencies of one framework, each with its user competency.
     *
     * @return array [learner, competency 1, competency 2, user competency 1, user competency 2].
     */
    private function learner(): array {
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $learner = $generator->create_user(['firstname' => 'Lena', 'lastname' => 'Learner']);
        $framework = $lpg->create_framework();
        $one = $lpg->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Kitchen hygiene']);
        $two = $lpg->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Garden safety']);
        $ucone = $lpg->create_user_competency(['userid' => $learner->id, 'competencyid' => $one->get('id')]);
        $uctwo = $lpg->create_user_competency(['userid' => $learner->id, 'competencyid' => $two->get('id')]);

        return [$learner, $one, $two, $ucone, $uctwo];
    }

    /**
     * Write one evidence row of core_competency at a given time.
     *
     * @param user_competency $uc The user competency.
     * @param string $identifier The description's string identifier in core_competency.
     * @param int $time Its timecreated.
     * @param array $extra Other columns.
     * @return int The row's id.
     */
    private function record(user_competency $uc, string $identifier, int $time, array $extra = []): int {
        global $DB;
        $lpg = self::getDataGenerator()->get_plugin_generator('core_competency');
        $evidence = $lpg->create_evidence(array_merge([
            'usercompetencyid' => $uc->get('id'),
            'descidentifier' => $identifier,
            'desccomponent' => 'core_competency',
            // Core stores the description's parameter as JSON (evidence::set_desca()).
            'desca' => json_encode('Bread'),
        ], $extra));
        $DB->set_field(evidence::TABLE, 'timecreated', $time, ['id' => $evidence->get('id')]);

        return (int) $evidence->get('id');
    }

    /**
     * Write one evidence row of another component, as local_evidencehub writes its validity log.
     *
     * Inserted directly: the persistent refuses a description whose string the site lacks, and the hub may be absent.
     *
     * @param user_competency $uc The user competency.
     * @param int $time Its timecreated.
     * @param string $component The component.
     * @return int The row's id.
     */
    private function foreign(user_competency $uc, int $time, string $component = 'local_evidencehub'): int {
        global $DB;
        return (int) $DB->insert_record(evidence::TABLE, (object) [
            'usercompetencyid' => $uc->get('id'),
            'contextid' => \context_system::instance()->id,
            'action' => evidence::ACTION_LOG,
            'actionuserid' => null,
            'descidentifier' => 'evidence_status_valid',
            'desccomponent' => $component,
            'desca' => json_encode('2027-03-14'),
            'url' => null,
            'grade' => null,
            'note' => null,
            'timecreated' => $time,
            'timemodified' => $time,
            'usermodified' => 2,
        ]);
    }

    /**
     * A viewer holding the given capabilities in the learner's user context, through a role of their own.
     *
     * @param \stdClass $learner The learner.
     * @param array $capabilities The capabilities allowed.
     * @return \stdClass The viewer.
     */
    private function viewer_with(\stdClass $learner, array $capabilities): \stdClass {
        $generator = self::getDataGenerator();
        $viewer = $generator->create_user();
        $roleid = $generator->create_role();
        $system = \context_system::instance();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $system->id, true);
        }
        role_assign($roleid, $viewer->id, \context_user::instance($learner->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        return $viewer;
    }

    /**
     * The ids of a page's records, in the page's order.
     *
     * @param array $page What page() answered.
     * @return array The ids.
     */
    private static function ids(array $page): array {
        return array_map(static fn(array $record): int => $record['id'], $page['records']);
    }

    /**
     * Each half of the page has its own rule: a viewer core lets read the prior-learning evidence but not the user
     * competencies gets the first half only, and the other way round.
     *
     * @return void
     */
    public function test_each_half_follows_its_own_core_rule(): void {
        $this->resetAfterTest();
        [$learner] = $this->learner();

        $this->setUser($this->viewer_with($learner, ['moodle/competency:userevidenceview']));
        $this->assertSame(['prior' => true, 'platform' => false], evidencelist::access((int) $learner->id));

        $this->setUser($this->viewer_with($learner, ['moodle/competency:usercompetencyview']));
        $this->assertSame(['prior' => false, 'platform' => true], evidencelist::access((int) $learner->id));

        $this->setUser($learner);
        $both = ['prior' => true, 'platform' => true];
        $this->assertSame($both, evidencelist::access((int) $learner->id), 'Control: the owner reads both halves.');
    }

    /**
     * A stranger and another learner are refused the platform's records with core's exception; the owner and a
     * manager are answered.
     *
     * @return void
     */
    public function test_only_who_core_lets_read_the_user_competencies_gets_the_records(): void {
        $this->resetAfterTest();
        [$learner, , , $uc] = $this->learner();
        [$other] = $this->learner();
        $id = $this->record($uc, 'evidence_coursecompleted', self::NOON);

        $this->setUser($learner);
        $this->assertSame([$id], self::ids(evidencelist::page((int) $learner->id, [])), 'Control: the owner.');
        $this->setAdminUser();
        $this->assertSame([$id], self::ids(evidencelist::page((int) $learner->id, [])), 'Control: a manager.');

        foreach ([self::getDataGenerator()->create_user(), $other] as $refused) {
            $this->setUser($refused);
            try {
                evidencelist::page((int) $learner->id, []);
                $this->fail('A viewer core refuses was answered.');
            } catch (\required_capability_exception $exception) {
                $this->assertSame('nopermissions', $exception->errorcode);
            }
        }
    }

    /**
     * The rows core writes when a prior-learning evidence is linked are left out (they repeat the evidence, which the
     * page lists itself); the unlink row and every other record of core stay.
     *
     * @return void
     */
    public function test_the_link_rows_are_left_out(): void {
        $this->resetAfterTest();
        [$learner, , , $uc] = $this->learner();
        $this->record($uc, evidencelist::LINKED, self::NOON);
        $unlinked = $this->record($uc, 'evidence_evidenceofpriorlearningunlinked', self::NOON - 10);
        $completed = $this->record($uc, 'evidence_coursecompleted', self::NOON - 20);
        $this->setUser($learner);

        $page = evidencelist::page((int) $learner->id, []);

        $this->assertSame([$unlinked, $completed], self::ids($page));
        $this->assertSame(2, $page['total']);
        $this->assertSame([], $page['folds']);
    }

    /**
     * Another component's rows are never listed one by one: one line per component and month, with the count.
     *
     * @return void
     */
    public function test_other_components_are_folded_per_month(): void {
        $this->resetAfterTest();
        [$learner, , , $uc] = $this->learner();
        $core = $this->record($uc, 'evidence_coursecompleted', self::NOON);
        $this->foreign($uc, self::NOON - self::DAY);
        $this->foreign($uc, self::NOON - 2 * self::DAY);
        $this->foreign($uc, self::NOON - 30 * self::DAY);
        $this->setUser($learner);
        $this->setTimezone('UTC');

        $page = evidencelist::page((int) $learner->id, []);

        $this->assertSame([$core], self::ids($page), 'Folded rows are not records.');
        $lines = [];
        foreach ($page['folds'] as $fold) {
            $lines[$fold['month']] = [$fold['component'], $fold['count'], $fold['key']];
        }
        ksort($lines);
        $this->assertSame([
            '2026-09' => ['local_evidencehub', 1, 'local_evidencehub|2026-09'],
            '2026-10' => ['local_evidencehub', 2, 'local_evidencehub|2026-10'],
        ], $lines);
    }

    /**
     * Keyset paging returns every record exactly once and in order, with several rows on one timestamp across a page
     * boundary, and every folded row is counted once across the pages.
     *
     * @return void
     */
    public function test_keyset_paging_returns_every_row_once(): void {
        $this->resetAfterTest();
        [$learner, , , $uc, $uctwo] = $this->learner();
        $expected = [];
        foreach ([0, 0, 0, 0, 0, 1, 2, 2, 3] as $index => $daysago) {
            $time = self::NOON - $daysago * self::DAY;
            $id = $this->record($index % 2 ? $uc : $uctwo, 'evidence_coursecompleted', $time);
            $expected[] = [$time, $id];
        }
        $folded = 0;
        foreach ([0, 0, 1, 3, 4] as $daysago) {
            $this->foreign($uc, self::NOON - $daysago * self::DAY);
            $folded++;
        }
        usort($expected, static fn(array $a, array $b): int => [$b[0], $b[1]] <=> [$a[0], $a[1]]);
        $this->setUser($learner);

        $seen = [];
        $counted = 0;
        $time = 0;
        $id = 0;
        $pages = 0;
        do {
            $page = evidencelist::page((int) $learner->id, [], $time, $id, 4);
            $seen = array_merge($seen, self::ids($page));
            $counted += array_sum(array_column($page['folds'], 'count'));
            $this->assertSame($pages === 0 ? 9 : -1, $page['total'], 'Only the first page is counted.');
            [$time, $id] = [$page['nexttime'], $page['nextid']];
            $pages++;
        } while ($page['hasmore'] && $pages < 10);

        $this->assertSame(3, $pages);
        $this->assertSame(array_column($expected, 1), $seen);
        $this->assertSame($folded, $counted);
    }

    /**
     * A cursor is a pair of positive integers or nothing.
     *
     * @return void
     */
    public function test_a_half_cursor_is_refused(): void {
        $this->resetAfterTest();
        [$learner] = $this->learner();
        $this->setUser($learner);
        $this->assertSame([], evidencelist::page((int) $learner->id, [], 0, 0)['records'], 'Control: no cursor.');

        foreach ([[5, 0], [0, 5], [-1, 3], [3, -1]] as [$time, $id]) {
            try {
                evidencelist::page((int) $learner->id, [], $time, $id);
                $this->fail("The cursor {$time}, {$id} was accepted.");
            } catch (\invalid_parameter_exception $exception) {
                $this->assertSame('invalidparameter', $exception->errorcode);
            }
        }
    }

    /**
     * The competency filter, the plan filter (the plan's competencies, the learner's own plan only), the course
     * filter (the course and its activities) and the period each keep their rows and drop the others.
     *
     * @return void
     */
    public function test_each_filter(): void {
        $this->resetAfterTest();
        [$learner, $one, $two, $ucone, $uctwo] = $this->learner();
        [$other] = $this->learner();
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $course = $generator->create_course();
        $elsewhere = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $incontext = static fn(\core\context $context): array => ['contextid' => $context->id];
        $coursecontext = $incontext(\context_course::instance($course->id));
        $incourse = $this->record($ucone, 'evidence_coursecompleted', self::NOON, $coursecontext);
        $activity = $incontext(\context_module::instance($page->cmid));
        $inactivity = $this->record($uctwo, 'evidence_coursemodulecompleted', self::NOON - self::DAY, $activity);
        $elsewherecontext = $incontext(\context_course::instance($elsewhere->id));
        $outside = $this->record($ucone, 'evidence_coursecompleted', self::NOON - 40 * self::DAY, $elsewherecontext);
        $plan = $lpg->create_plan(['userid' => $learner->id, 'status' => \core_competency\plan::STATUS_ACTIVE]);
        $lpg->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $two->get('id')]);
        $otherplan = $lpg->create_plan(['userid' => $other->id]);
        $lpg->create_plan_competency(['planid' => $otherplan->get('id'), 'competencyid' => $one->get('id')]);
        // A draft of the learner's own, which core's default role does not let its owner read.
        $draft = $lpg->create_plan(['userid' => $learner->id, 'status' => \core_competency\plan::STATUS_DRAFT]);
        $lpg->create_plan_competency(['planid' => $draft->get('id'), 'competencyid' => $one->get('id')]);
        $this->setUser($learner);
        $userid = (int) $learner->id;

        $this->assertSame([$incourse, $inactivity, $outside], self::ids(evidencelist::page($userid, [])));
        $this->assertSame([$incourse, $outside], self::ids(evidencelist::page($userid, ['competencyid' => (int) $one->get('id')])));
        $this->assertSame([$inactivity], self::ids(evidencelist::page($userid, ['planid' => (int) $plan->get('id')])));
        // Another learner's plan, and a plan core does not let the viewer read, name no competency of this list.
        $this->assertSame([], self::ids(evidencelist::page($userid, ['planid' => (int) $otherplan->get('id')])));
        $this->assertSame([], self::ids(evidencelist::page($userid, ['planid' => (int) $draft->get('id')])));
        $this->assertSame([$incourse, $inactivity], self::ids(evidencelist::page($userid, ['courseid' => (int) $course->id])));
        $this->assertSame([$outside], self::ids(evidencelist::page($userid, ['courseid' => (int) $elsewhere->id])));
        $this->assertSame([$inactivity, $outside], self::ids(evidencelist::page($userid, ['to' => self::NOON - 60])));
        $this->assertSame([$incourse, $inactivity], self::ids(evidencelist::page($userid, ['from' => self::NOON - 2 * self::DAY])));
    }

    /**
     * A period is two calendar days in the reader's timezone, the last one whole; a malformed day is refused.
     *
     * @return void
     */
    public function test_the_period_is_read_in_the_readers_timezone(): void {
        $this->resetAfterTest();
        $this->setTimezone('America/Sao_Paulo');
        $this->setAdminUser();

        $this->assertSame([0, 0], evidencelist::period('', ''));
        [$from, $to] = evidencelist::period('2026-10-01', '2026-10-31');
        $zone = new \DateTimeZone('America/Sao_Paulo');
        $this->assertSame((new \DateTimeImmutable('2026-10-01 00:00', $zone))->getTimestamp(), $from);
        $this->assertSame((new \DateTimeImmutable('2026-11-01 00:00', $zone))->getTimestamp(), $to);

        foreach (['2026-02-30', '26-10-01', '2026/10/01', 'yesterday'] as $bad) {
            try {
                evidencelist::period($bad, '');
                $this->fail("The day {$bad} was accepted.");
            } catch (\invalid_parameter_exception $exception) {
                $this->assertSame('invalidparameter', $exception->errorcode);
            }
        }
    }

    /**
     * A record says what core says of it: core's description, the competency, the rating's name on the competency's
     * scale, who acted, the note, and an address only when it is http(s).
     *
     * @return void
     */
    public function test_a_record_carries_what_cores_summary_shows(): void {
        global $DB;
        $this->resetAfterTest();
        [$learner, $one, , $uc] = $this->learner();
        $teacher = self::getDataGenerator()->create_user(['firstname' => 'Tess', 'lastname' => 'Teacher']);
        $markup = self::getDataGenerator()->create_user(['firstname' => 'Tess', 'lastname' => 'Teacher']);
        // The form would refuse markup in a name: write it as an import or an older release could have.
        $DB->set_field('user', 'firstname', 'A <b>B</b>', ['id' => $markup->id]);
        $rated = $this->record($uc, 'evidence_manualoverride', self::NOON, [
            'action' => evidence::ACTION_OVERRIDE,
            'grade' => 1,
            'actionuserid' => $teacher->id,
            'note' => 'Solid work',
            'url' => 'https://example.com/work',
        ]);
        $this->record($uc, 'evidence_coursecompleted', self::NOON - 10, ['url' => 'ftp://example.com/file']);
        $byname = $this->record($uc, 'evidence_coursecompleted', self::NOON - 20, ['actionuserid' => $markup->id]);
        $this->setUser($learner);

        [$first, $second, $third] = evidencelist::page((int) $learner->id, [])['records'];

        $scale = $one->get_scale();
        $this->assertSame($rated, $first['id']);
        $this->assertSame('rating', $first['kind']);
        $this->assertSame(get_string('evidence_manualoverride', 'core_competency'), $first['title']);
        $this->assertSame('Kitchen hygiene', $first['competency']);
        $this->assertSame($scale->scale_items[0], $first['gradename']);
        $this->assertSame('Tess Teacher', $first['actor']);
        $this->assertSame('Solid work', $first['note']);
        $this->assertSame('https://example.com/work', $first['url']);
        $this->assertStringContainsString('/admin/tool/lp/user_competency.php?id=' . $uc->get('id'), $first['competencyurl']);
        $this->assertSame('course', $second['kind']);
        $this->assertSame(get_string('evidence_coursecompleted', 'core_competency', 'Bread'), $second['title']);
        $this->assertSame('', $second['gradename']);
        $this->assertSame('', $second['actor']);
        $this->assertSame('', $second['url'], 'An address that is not http(s) is withheld.');
        $this->assertSame($byname, $third['id']);
        $this->assertSame(clean_param($third['actor'], PARAM_TEXT), $third['actor'], 'The name is its own PARAM_TEXT cleaning.');
        $this->assertStringNotContainsString('<', $third['actor']);
        $this->assertStringContainsString('Teacher', $third['actor']);
        // Called as core/ajax calls it: with the session key, which resetAfterTest() does not take back out.
        $_POST['sesskey'] = sesskey();
        $answer = \core_external\external_api::call_external_function(
            'theme_dimensions_get_user_evidence_feed',
            ['userid' => $learner->id],
            true
        );
        unset($_POST['sesskey']);
        $this->assertFalse(
            $answer['error'],
            'A name with markup does not fail the whole response: ' . json_encode($answer['exception'] ?? null)
        );
    }

    /**
     * A page costs the same number of statements whatever the number of rows on it: names are read in bulk.
     *
     * @return void
     */
    public function test_a_page_costs_the_same_for_few_and_many_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $teachers = [$generator->create_user(), $generator->create_user(), $generator->create_user()];

        $reads = [];
        foreach ([2, 24] as $size) {
            [$learner, , , $ucone, $uctwo] = $this->learner();
            for ($i = 0; $i < $size; $i++) {
                $this->record($i % 2 ? $ucone : $uctwo, 'evidence_manualoverride', self::NOON - $i, [
                    'action' => evidence::ACTION_OVERRIDE,
                    'grade' => 1 + $i % 2,
                    'actionuserid' => $teachers[$i % 3]->id,
                ]);
                $this->foreign($ucone, self::NOON - $i - 5);
            }
            $this->setUser($learner);
            // Warm the caches a page of any size shares (contexts, capabilities, strings), then measure.
            evidencelist::page((int) $learner->id, []);
            $before = $DB->perf_get_reads();
            $page = evidencelist::page((int) $learner->id, []);
            $reads[$size] = $DB->perf_get_reads() - $before;
            $this->assertCount($size, $page['records'], 'Control: the page carries every row.');
        }

        $this->assertSame($reads[2], $reads[24]);
    }

    /**
     * The fold costs the same whatever the number of foreign rows: they are counted by one aggregate, never read.
     *
     * @return void
     */
    public function test_the_fold_does_not_grow_with_the_foreign_rows(): void {
        global $DB;
        $this->resetAfterTest();
        [$learner, , , $uc] = $this->learner();
        $this->record($uc, 'evidence_coursecompleted', time() - self::DAY);
        $this->setUser($learner);
        $this->setTimezone('UTC');

        $reads = [];
        $counted = [];
        $rows = [];
        foreach ([5, 500] as $size) {
            while (count($rows) < $size) {
                $rows[] = (object) [
                    'usercompetencyid' => $uc->get('id'),
                    'contextid' => \context_system::instance()->id,
                    'action' => evidence::ACTION_LOG,
                    'descidentifier' => 'evidence_status_valid',
                    'desccomponent' => 'local_evidencehub',
                    'desca' => json_encode('2027-03-14'),
                    'timecreated' => time() - (count($rows) % 20) * self::DAY,
                    'timemodified' => time(),
                    'usermodified' => 2,
                ];
            }
            $DB->delete_records(evidence::TABLE, ['desccomponent' => 'local_evidencehub']);
            $DB->insert_records(evidence::TABLE, $rows);
            // Warm the caches a page of any size shares, then measure.
            evidencelist::page((int) $learner->id, []);
            $before = $DB->perf_get_reads();
            $page = evidencelist::page((int) $learner->id, []);
            $reads[$size] = $DB->perf_get_reads() - $before;
            $counted[$size] = array_sum(array_column($page['folds'], 'count'));
        }

        $this->assertSame([5 => 5, 500 => 500], $counted, 'Control: every foreign row is counted.');
        $this->assertSame($reads[5], $reads[500]);
    }

    /**
     * A foreign row dated exactly as the page's last record is counted on one of two consecutive pages: the one whose
     * side of the keyset pair it is on.
     *
     * @return void
     */
    public function test_a_foreign_row_at_the_page_boundary_is_counted_once(): void {
        $this->resetAfterTest();
        [$learner, , , $uc] = $this->learner();
        $this->record($uc, 'evidence_coursecompleted', self::NOON);
        $this->foreign($uc, self::NOON - self::DAY);
        $boundary = $this->record($uc, 'evidence_coursecompleted', self::NOON - self::DAY);
        $this->foreign($uc, self::NOON - self::DAY);
        $this->record($uc, 'evidence_coursecompleted', self::NOON - 2 * self::DAY);
        $this->setUser($learner);

        $first = evidencelist::page((int) $learner->id, [], 0, 0, 2);
        $second = evidencelist::page((int) $learner->id, [], $first['nexttime'], $first['nextid'], 2);

        $this->assertSame($boundary, $first['nextid'], 'Control: the boundary record ends the first page.');
        $this->assertSame(1, array_sum(array_column($first['folds'], 'count')), 'The row above the pair.');
        $this->assertSame(1, array_sum(array_column($second['folds'], 'count')), 'The row below the pair.');
    }

    /**
     * Rows older than the months the fold counts apart are one more line: the cap hides no count.
     *
     * @return void
     */
    public function test_rows_older_than_the_cap_are_still_counted(): void {
        $this->resetAfterTest();
        [$learner, , , $uc] = $this->learner();
        $this->record($uc, 'evidence_coursecompleted', time() - self::DAY);
        $this->foreign($uc, time() - self::DAY);
        $old = time() - 1200 * self::DAY;
        $this->foreign($uc, $old);
        $this->foreign($uc, $old - self::DAY);
        $this->foreign($uc, $old - 40 * self::DAY);
        $this->setUser($learner);
        $this->setTimezone('UTC');

        $folds = [];
        foreach (evidencelist::page((int) $learner->id, [])['folds'] as $fold) {
            $folds[$fold['key']] = $fold['count'];
        }

        $this->assertSame(1, $folds['local_evidencehub|' . userdate(time() - self::DAY, '%Y-%m')], 'Control: a month in range.');
        $this->assertSame(3, $folds['local_evidencehub|' . evidencelist::EARLIER]);
        $this->assertCount(2, $folds);
    }

    /**
     * The facets: the learner's competencies, the courses their records came from that the viewer may see listed (a
     * hidden course only to whom core shows it), and the plans core lists to the viewer with their competencies.
     *
     * @return void
     */
    public function test_the_facets(): void {
        $this->resetAfterTest();
        [$learner, $one, $two, $uc] = $this->learner();
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $course = $generator->create_course(['fullname' => 'Bakery']);
        $this->record($uc, 'evidence_coursecompleted', self::NOON, ['contextid' => \context_course::instance($course->id)->id]);
        $hidden = $generator->create_course(['fullname' => 'Cellar', 'visible' => 0]);
        $this->record($uc, 'evidence_coursecompleted', self::NOON - 5, ['contextid' => \context_course::instance($hidden->id)->id]);
        $plan = $lpg->create_plan([
            'userid' => $learner->id,
            'name' => 'Cooking',
            'status' => \core_competency\plan::STATUS_ACTIVE,
        ]);
        $lpg->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $two->get('id')]);
        $this->setUser($learner);

        $facets = evidencelist::facets((int) $learner->id);

        $names = array_column($facets['competencies'], 'name', 'id');
        ksort($names);
        $expected = [(int) $one->get('id') => 'Kitchen hygiene', (int) $two->get('id') => 'Garden safety'];
        ksort($expected);
        $this->assertSame($expected, $names);
        $this->assertSame([['id' => (int) $course->id, 'name' => 'Bakery']], $facets['courses']);
        $this->setAdminUser();
        $names = array_column(evidencelist::facets((int) $learner->id)['courses'], 'name');
        $this->assertSame(['Bakery', 'Cellar'], $names, 'Control: a viewer core shows hidden courses to sees both.');
        $this->setUser($learner);
        $expected = [['id' => (int) $plan->get('id'), 'name' => 'Cooking', 'competencyids' => [(int) $two->get('id')]]];
        $this->assertSame($expected, $facets['plans']);
    }
}
