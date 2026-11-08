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
 * Theme Dimensions - Tests for the related contents' reader
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\related;

use core_competency\course_competency;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../category/fixture.php');

/**
 * Which courses relate to a set of competencies, in what order, and which of them a reader is shown.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(finder::class)]
#[CoversClass(candidate::class)]
final class finder_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var int The category every course of a test lives in. */
    private int $categoryid;

    /** @var int[] Competency ids by name. */
    private array $comps = [];

    /**
     * Competencies on, as an administrator, with one framework of four competencies.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        self::forget();
        set_config('enabled', 1, 'core_competency');

        $this->categoryid = (int) self::getDataGenerator()->create_category()->id;
        $generator = self::competencies();
        $framework = $generator->create_framework(['idnumber' => 'FW1']);
        foreach (['a', 'b', 'c', 'd'] as $name) {
            $this->comps[$name] = (int) $generator->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'idnumber' => strtoupper($name),
            ])->get('id');
        }
    }

    /**
     * Core's competency generator.
     *
     * @return \core_competency_generator
     */
    private static function competencies(): \core_competency_generator {
        return self::getDataGenerator()->get_plugin_generator('core_competency');
    }

    /**
     * A course in the test's category, linked to competencies, public by default.
     *
     * @param array $links Competency name => course_competency::OUTCOME_* (or a list of names, linked for evidence).
     * @param array $record Course record fields.
     * @param int $state A discoverability state.
     * @return int The course id.
     */
    private function course(
        array $links,
        array $record = [],
        int $state = discoverability::STATE_PUBLIC
    ): int {
        $course = self::getDataGenerator()->create_course($record + ['category' => $this->categoryid]);
        foreach ($links as $key => $value) {
            [$name, $outcome] = is_int($key) ? [$value, course_competency::OUTCOME_EVIDENCE] : [$key, $value];
            self::competencies()->create_course_competency([
                'courseid' => $course->id,
                'competencyid' => $this->comps[$name],
                'ruleoutcome' => $outcome,
            ]);
        }
        if ($state !== discoverability::STATE_DEFAULT) {
            $this->set_course_state((int) $course->id, $state);
        }
        return (int) $course->id;
    }

    /**
     * The ids find() answers, in its order.
     *
     * @param mixed ...$args The arguments of find().
     * @return int[]
     */
    private static function found(mixed ...$args): array {
        return array_map(static fn(array $entry): int => $entry['candidate']->courseid, finder::find(...$args));
    }

    /**
     * An aggregate row as candidates() selects it.
     *
     * @param int $courseid The course.
     * @param int $shared Shared competencies.
     * @param int $total Linked competencies in all.
     * @param bool $grants Whether it grants a shared one.
     * @param int $startdate Start date.
     * @param string $fullname Name.
     * @return \stdClass
     */
    private static function row(
        int $courseid,
        int $shared,
        int $total,
        bool $grants = false,
        int $startdate = 0,
        string $fullname = 'Curso'
    ): \stdClass {
        return (object) [
            'courseid' => (string) $courseid,
            'shared' => (string) $shared,
            'total' => (string) $total,
            'grants' => $grants ? '1' : '0',
            'startdate' => (string) $startdate,
            'fullname' => $fullname,
        ];
    }

    /**
     * The order of the automatic mode: parity, then a course that grants one it shares, then overlap.
     */
    public function test_the_automatic_order_is_parity_then_grants_then_overlap(): void {
        $set = [1, 2, 3];
        $rows = [
            self::row(10, 1, 4),
            self::row(11, 2, 2),
            self::row(12, 1, 1, true),
            self::row(13, 3, 3),
            self::row(14, 3, 4, true),
            self::row(15, 2, 3),
        ];
        $ranked = finder::rank($set, $rows, finder::MODE_AUTO, finder::MATCH_ANY, 1000);

        $this->assertSame([13, 14, 12, 11, 15, 10], array_map(static fn(candidate $c): int => $c->courseid, $ranked));
        $this->assertSame(finder::TIER_PARITY, $ranked[0]->tier);
        $this->assertSame(finder::TIER_GRANTS, $ranked[1]->tier);
        $this->assertSame(finder::TIER_SHARES, $ranked[3]->tier);
        $this->assertEqualsWithDelta(2 / 3, $ranked[3]->overlap, 0.0001, 'Shares 2 of a union of 3.');
        $this->assertEqualsWithDelta(1 / 6, $ranked[5]->overlap, 0.0001, 'Shares 1 of a union of 6.');
    }

    /**
     * A tie goes to the nearest upcoming start, then the latest past one, then no date; then the name, then the id.
     */
    public function test_a_tie_goes_to_the_nearest_start_then_the_name(): void {
        $now = 1000000;
        $rows = [
            self::row(20, 1, 2, false, 0, 'A'),
            self::row(21, 1, 2, false, $now - 50, 'A'),
            self::row(22, 1, 2, false, $now - 10, 'A'),
            self::row(23, 1, 2, false, $now + 90, 'A'),
            self::row(24, 1, 2, false, $now + 10, 'Zeta'),
            self::row(25, 1, 2, false, $now + 10, 'alfa'),
            self::row(26, 1, 2, false, $now + 10, 'alfa'),
        ];
        $ranked = finder::rank([1], $rows, finder::MODE_AUTO, finder::MATCH_ANY, $now);

        $this->assertSame(
            [25, 26, 24, 23, 22, 21, 20],
            array_map(static fn(candidate $c): int => $c->courseid, $ranked)
        );
    }

    /**
     * The manual mode orders by how many of the chosen competencies a course covers, and MATCH_ALL keeps only the
     * courses covering all of them; parity means nothing there.
     */
    public function test_the_manual_order_counts_the_chosen_competencies(): void {
        $rows = [
            self::row(30, 1, 1),
            self::row(31, 2, 5),
            self::row(32, 1, 3, true),
        ];
        $any = finder::rank([1, 2], $rows, finder::MODE_MANUAL, finder::MATCH_ANY, 0);
        $this->assertSame([31, 32, 30], array_map(static fn(candidate $c): int => $c->courseid, $any));

        $all = finder::rank([1, 2], $rows, finder::MODE_MANUAL, finder::MATCH_ALL, 0);
        $this->assertSame([31], array_map(static fn(candidate $c): int => $c->courseid, $all));

        $auto = finder::rank([1, 2], $rows, finder::MODE_AUTO, finder::MATCH_ALL, 0);
        $this->assertCount(3, $auto, 'The automatic mode reads no match.');
    }

    /**
     * The aggregate reads each course's links: its shared count, its total and whether it grants a SHARED competency.
     *
     * A course granting only a competency outside the set is no granter; the parent and the excluded steps are no
     * candidates, nor is a course sharing nothing.
     */
    public function test_find_reads_the_links_of_each_course(): void {
        $parent = $this->course(['a', 'b']);
        $step = $this->course(['a', 'b']);
        $twin = $this->course(['a', 'b']);
        $granter = $this->course(['a' => course_competency::OUTCOME_COMPLETE, 'c']);
        $outsider = $this->course(['a', 'd' => course_competency::OUTCOME_COMPLETE, 'c']);
        $this->course(['c', 'd']);

        $set = finder::competencies_of([$parent]);
        $found = finder::find($set, [$parent, $step], finder::MODE_AUTO, finder::MATCH_ANY, 12);

        $this->assertSame([$twin, $granter, $outsider], array_map(static fn(array $e): int => $e['candidate']->courseid, $found));
        $this->assertSame(finder::TIER_GRANTS, $found[1]['candidate']->tier);
        $this->assertFalse($found[2]['candidate']->grants, 'Granting d, outside the set, grants nothing here.');
        $this->assertSame(3, $found[2]['candidate']->total);
        $this->assertSame($granter, (int) $found[1]['row']->id, 'The row of the course, in display::rows() shape.');
    }

    /**
     * A trail's set is the union of its own competencies and its steps'.
     */
    public function test_a_trails_set_is_the_union_of_its_steps(): void {
        $trail = $this->course(['a']);
        $one = $this->course(['b']);
        $two = $this->course(['b', 'c']);

        $this->assertSame(
            [$this->comps['a'], $this->comps['b'], $this->comps['c']],
            finder::competencies_of([$trail, $one, $two])
        );
        $this->assertSame([], finder::competencies_of([]));
    }

    /**
     * A visitor is shown the public courses alone; a listed course is no ghost, it is absent.
     */
    public function test_a_visitor_sees_public_courses_only(): void {
        $parent = $this->course(['a']);
        $public = $this->course(['a']);
        $listed = $this->course(['a'], [], discoverability::STATE_DEFAULT);
        $unlisted = $this->course(['a'], [], discoverability::STATE_UNLISTED);
        $this->assertNotEmpty([$listed, $unlisted]);

        $this->become(null);
        $this->assertSame([$public], self::found([$this->comps['a']], [$parent]));

        $this->become(guest_user());
        $this->assertSame([$public], self::found([$this->comps['a']], [$parent]), 'The guest account is a visitor.');
    }

    /**
     * An account sees what the showcase lists to it, less what it is enrolled in or has completed; the unlisted course
     * shows only with a way in.
     */
    public function test_an_account_sees_the_showcase_less_its_own_courses(): void {
        $parent = $this->course(['a']);
        $public = $this->course(['a'], ['fullname' => 'P']);
        $listed = $this->course(['a'], ['fullname' => 'Q'], discoverability::STATE_DEFAULT);
        $hidden = $this->course(['a'], ['visible' => 0]);
        $unlistedclosed = $this->course(['a'], [], discoverability::STATE_UNLISTED);
        $unlistedopen = $this->course(['a'], ['fullname' => 'R'], discoverability::STATE_UNLISTED);
        $enrolled = $this->course(['a']);
        $completed = $this->course(['a']);
        $this->enable_enrol('self');
        $this->enable_instance($unlistedopen, 'self');

        $learner = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($learner->id, $enrolled);
        global $DB;
        $DB->insert_record('course_completions', (object) [
            'userid' => $learner->id,
            'course' => $completed,
            'timeenrolled' => 0,
            'timestarted' => 0,
            'timecompleted' => time() - DAYSECS,
            'reaggregate' => 0,
        ]);
        $this->become($learner);

        $found = self::found([$this->comps['a']], [$parent], finder::MODE_AUTO, finder::MATCH_ANY, 12);
        sort($found);
        $this->assertSame([$public, $listed, $unlistedopen], $found);
        $this->assertNotContains($hidden, $found);
        $this->assertNotContains($unlistedclosed, $found);

        // The control: the same learner sees the enrolled course once the enrolment is gone.
        $instance = $DB->get_record('enrol', ['courseid' => $enrolled, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($instance, $learner->id);
        self::forget();
        $this->assertContains($enrolled, self::found([$this->comps['a']], [$parent], finder::MODE_AUTO, finder::MATCH_ANY, 12));
    }

    /**
     * The limit is honoured past the first slice: courses only the account's gate refuses, at the head of the
     * ranking, leave room for the ones after them.
     */
    public function test_the_limit_reaches_past_a_refused_head(): void {
        $parent = $this->course(['a', 'b']);
        // Twenty unlisted twins with no way in outrank everything: the statement keeps them (another account may have
        // a way in) and the gate shows this one none of them.
        for ($i = 0; $i < 20; $i++) {
            $this->course(['a', 'b'], [], discoverability::STATE_UNLISTED);
        }
        $first = $this->course(['a'], ['fullname' => 'Primeiro']);
        $second = $this->course(['b'], ['fullname' => 'Segundo']);

        $this->become(self::getDataGenerator()->create_user());
        $set = [$this->comps['a'], $this->comps['b']];
        $this->assertSame([$first, $second], self::found($set, [$parent], 'auto', 'any', 2));
        $this->assertSame([$first], self::found($set, [$parent], 'auto', 'any', 1));
    }

    /**
     * A visitor's block is never emptied by courses it may not see at the head of the ranking, however many: each
     * of the visitor's refusals is a term of the candidates' statement, so the ranking holds none of them.
     *
     * Thirty-seven of a kind outrank the public course: one more than the slices the gate reads (three of twelve).
     */
    public function test_a_visitor_is_not_left_empty_by_courses_it_may_not_see(): void {
        global $DB;

        $parent = $this->course(['a', 'b']);
        // The unlisted category is an ANCESTOR of its courses' own, so the path is read, not only the category.
        $unlistedcat = (int) self::getDataGenerator()->create_category()->id;
        $beneath = (int) self::getDataGenerator()->create_category(['parent' => $unlistedcat])->id;
        $hiddencat = (int) self::getDataGenerator()->create_category(['visible' => 0])->id;
        $this->set_category_state($unlistedcat, category_discoverability::STATE_UNLISTED);
        for ($i = 0; $i < 37; $i++) {
            $this->course(['a', 'b'], [], discoverability::STATE_DEFAULT);
            $this->course(['a', 'b'], ['category' => $beneath]);
            $this->course(['a', 'b'], ['category' => $hiddencat]);
        }
        // Core hides a course created in a hidden category; these were made visible there by hand, which core allows
        // and the plugin's predicate refuses by the category.
        $DB->set_field('course', 'visible', 1, ['category' => $hiddencat]);
        $public = $this->course(['a']);

        $this->become(null);
        $this->assertSame([$public], self::found([$this->comps['a'], $this->comps['b']], [$parent], 'auto', 'any', 1));
    }

    /**
     * The plugin still decides what a visitor sees: a course the statement's terms admit and the plugin refuses (an
     * ancestor category that is gone, which the plugin fails closed on) is not shown.
     */
    public function test_the_plugin_still_decides_for_a_visitor(): void {
        global $DB;

        $parent = $this->course(['a']);
        $top = self::getDataGenerator()->create_category();
        $below = self::getDataGenerator()->create_category(['parent' => $top->id]);
        $public = $this->course(['a'], ['category' => $below->id]);

        $this->become(null);
        $this->assertSame([$public], self::found([$this->comps['a']], [$parent]), 'Precondition: the course is public.');

        $DB->delete_records('course_categories', ['id' => $top->id]);
        $this->become(null);
        $this->assertSame([], self::found([$this->comps['a']], [$parent]));
    }

    /**
     * A hidden course is no suggestion, not even to a reader who may open it.
     */
    public function test_a_hidden_course_is_never_related(): void {
        $parent = $this->course(['a']);
        $hidden = $this->course(['a'], ['visible' => 0]);

        $this->become(get_admin());
        $this->assertSame([], self::found([$this->comps['a']], [$parent]), 'The administrator may open it.');

        // The control: the same course once visible.
        update_course((object) ['id' => $hidden, 'visible' => 1]);
        $this->become(get_admin());
        $this->assertSame([$hidden], self::found([$this->comps['a']], [$parent]));
    }

    /**
     * While context freezing is on, a frozen course is no suggestion, frozen itself or under a frozen category, for a
     * visitor and for an account; with freezing off the flags mean nothing.
     */
    public function test_a_frozen_course_is_no_suggestion(): void {
        $parent = $this->course(['a']);
        $frozencat = (int) self::getDataGenerator()->create_category()->id;
        $plain = $this->course(['a'], ['fullname' => 'A']);
        $frozen = $this->course(['a'], ['fullname' => 'B']);
        $under = $this->course(['a'], ['fullname' => 'C', 'category' => $frozencat]);
        \core\context\course::instance($frozen)->set_locked(true);
        \core\context\coursecat::instance($frozencat)->set_locked(true);

        set_config('contextlocking', 1);
        foreach (['visitor' => null, 'account' => self::getDataGenerator()->create_user()] as $who => $user) {
            $this->become($user);
            $this->assertSame([$plain], self::found([$this->comps['a']], [$parent]), "The {$who} is shown no frozen course.");
        }

        // The control: the same courses with freezing off.
        set_config('contextlocking', 0);
        $this->become(null);
        $this->assertSame([$plain, $frozen, $under], self::found([$this->comps['a']], [$parent]));
    }

    /**
     * A competency idnumber is read inside the framework named, and a parent narrows the choice to its own links.
     */
    public function test_resolve_reads_the_idnumber_inside_its_framework(): void {
        $other = self::competencies()->create_framework(['idnumber' => 'FW2']);
        $othera = (int) self::competencies()->create_competency([
            'competencyframeworkid' => $other->get('id'),
            'idnumber' => 'A',
        ])->get('id');

        $this->assertSame(
            ['ids' => [$this->comps['b'], $this->comps['a']], 'missing' => ['Z']],
            finder::resolve('FW1', [' B ', 'A', 'Z', '', 'A'])
        );
        $this->assertSame(['ids' => [$othera], 'missing' => []], finder::resolve('FW2', ['A']));
        $this->assertSame(['ids' => [], 'missing' => ['A']], finder::resolve('NOPE', ['A']));

        $parent = $this->course(['a']);
        $this->assertSame(
            ['ids' => [$this->comps['a']], 'missing' => ['B']],
            finder::resolve('FW1', ['A', 'B'], $parent),
            'B is in the framework and not linked to the parent.'
        );
    }

    /**
     * With competencies disabled there is nothing related, whatever is linked.
     */
    public function test_nothing_is_related_while_competencies_are_off(): void {
        $parent = $this->course(['a']);
        $this->course(['a']);
        $this->assertNotEmpty(self::found([$this->comps['a']], [$parent]), 'Precondition: one course relates.');

        set_config('enabled', 0, 'core_competency');
        $this->assertSame([], finder::competencies_of([$parent]));
        $this->assertSame([], self::found([$this->comps['a']], [$parent]));
        $this->assertSame(['ids' => [], 'missing' => ['A']], finder::resolve('FW1', ['A']));
    }

    /**
     * Six more candidates add no statement, for a visitor and for an account.
     */
    public function test_the_statements_do_not_grow_with_the_candidates(): void {
        $parent = $this->course(['a']);
        $ids = [];
        for ($i = 0; $i < 8; $i++) {
            $ids[] = $this->course(['a']);
        }
        $learner = self::getDataGenerator()->create_user();

        foreach (['visitor' => null, 'account' => $learner] as $who => $user) {
            // The first call of a viewer fills core's application caches, which a page shares with the rest of the
            // request; the measured calls are what the block itself costs.
            $this->become($user);
            finder::find([$this->comps['a']], [$parent]);
            $reads = [];
            foreach (['small' => array_slice($ids, 0, 2), 'big' => $ids] as $name => $kept) {
                // The candidates outside this size are excluded, so the ranking holds exactly this many.
                $excluded = array_merge([$parent], array_diff($ids, $kept));
                $this->become($user);
                $found = [];
                $reads[$name] = $this->reads(function () use ($excluded, &$found): void {
                    $found = self::found([$this->comps['a']], $excluded, finder::MODE_AUTO, finder::MATCH_ANY, 12);
                });
                $this->assertCount(count($kept), $found, "Precondition: the {$who} is shown every {$name} candidate.");
            }
            $this->assertSame($reads['small'], $reads['big'], "Six more candidates add no statement for the {$who}.");
        }
    }
}
