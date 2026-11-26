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
 * Theme Dimensions - Tests for the columns a reader chose on the class report
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Each cleaning step of the class report's column preference, on its own: the read that re-validates the ids against
 * the course now, a stored value of another shape, the widths' bounds, the cap on the ids, the length core's column
 * holds, and core's permission callback.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(classreportpreference::class)]
final class classreportpreference_test extends \advanced_testcase {
    /**
     * A course with a number of competencies.
     *
     * @param int $count How many competencies.
     * @return array [the course, the competency ids in the course's order].
     */
    private function course_with(int $count): array {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $course = $generator->create_course();
        $framework = $lpg->create_framework();
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
            $lpg->create_course_competency(['courseid' => $course->id, 'competencyid' => $competency->get('id')]);
            $ids[] = (int) $competency->get('id');
        }

        return [$course, $ids];
    }

    /**
     * A read keeps only the ids the course has now: one removed from the course since, and one of another course,
     * match nothing; when nothing is left, the choice is the default.
     *
     * @return void
     */
    public function test_a_read_keeps_only_the_courses_competencies_now(): void {
        global $DB;
        [$course, $ids] = $this->course_with(2);
        [, $elsewhere] = $this->course_with(1);
        $user = $this->getDataGenerator()->create_user();
        $name = classreportpreference::name((int) $course->id);
        set_user_preference($name, json_encode(['c' => [$ids[1], $elsewhere[0], $ids[0]], 'w' => []]), $user);

        // The course's order, whatever order was stored.
        $this->assertSame([$ids[0], $ids[1]], classreportpreference::read((int) $course->id, (int) $user->id)['columns']);

        $DB->delete_records('competency_coursecomp', ['courseid' => $course->id, 'competencyid' => $ids[1]]);
        $this->assertSame([$ids[0]], classreportpreference::read((int) $course->id, (int) $user->id)['columns']);
        $DB->delete_records('competency_coursecomp', ['courseid' => $course->id]);
        $this->assertNull(classreportpreference::read((int) $course->id, (int) $user->id)['columns']);
    }

    /**
     * A stored value of another shape reads as no choice, and never breaks the read.
     *
     * @return void
     */
    public function test_a_value_of_another_shape_reads_as_no_choice(): void {
        [$course, $ids] = $this->course_with(1);
        $user = $this->getDataGenerator()->create_user();
        $name = classreportpreference::name((int) $course->id);
        $default = ['columns' => null, 'widths' => []];

        foreach (['not json', '[1,2]', '{"c":"x","w":7}', '{"c":[-3,0,"y"]}'] as $raw) {
            set_user_preference($name, $raw, $user);
            $this->assertSame($default, classreportpreference::read((int) $course->id, (int) $user->id), $raw);
        }
        set_user_preference($name, '{"c":["' . $ids[0] . '"]}', $user);
        $this->assertSame([$ids[0]], classreportpreference::read((int) $course->id, (int) $user->id)['columns'], 'The control.');
    }

    /**
     * Widths are clamped to the bounds, rounded, dropped under a key of no column's shape or with no number, and kept
     * at most MAX_WIDTHS.
     *
     * @return void
     */
    public function test_the_widths_are_clamped_and_bounded(): void {
        $widths = [
            'learner' => 5,
            'comp:7' => 99999,
            'identity:email' => 120.6,
            'bad key' => 100,
            'comp:0' => 100,
            'comp:8' => 'wide',
        ];
        $this->assertSame(
            ['learner' => classreportpreference::MIN_WIDTH, 'comp:7' => classreportpreference::MAX_WIDTH, 'identity:email' => 121],
            classreportpreference::clean(['w' => $widths])['widths']
        );

        $many = [];
        for ($i = 1; $i <= classreportpreference::MAX_WIDTHS + 10; $i++) {
            $many['comp:' . $i] = 100;
        }
        $this->assertCount(classreportpreference::MAX_WIDTHS, classreportpreference::clean(['w' => $many])['widths']);
    }

    /**
     * The ids are positive integers, once each, at most MAX_COLUMNS, the first kept.
     *
     * @return void
     */
    public function test_the_ids_are_capped_and_unique(): void {
        $ids = range(1, classreport::MAX_COLUMNS + 5);
        $this->assertSame(
            range(1, classreport::MAX_COLUMNS),
            classreportpreference::clean(['c' => $ids])['columns']
        );
        $this->assertSame([4, 2], classreportpreference::clean(['c' => [4, 4, '2', -1, 'x', 0]])['columns']);
    }

    /**
     * A very long list is read only as far as the cap: the first distinct ids in order, whatever follows (repeats,
     * junk, thousands more) changes nothing.
     *
     * @return void
     */
    public function test_a_very_long_list_keeps_the_first_distinct_ids(): void {
        $head = array_merge([7, '7', 3], range(100, 100 + classreport::MAX_COLUMNS - 3));
        $tail = array_merge(array_fill(0, 3000, 'x'), range(5000, 7000), [7, 3]);
        $kept = classreportpreference::clean(['c' => array_merge($head, $tail)])['columns'];

        $this->assertCount(classreport::MAX_COLUMNS, $kept);
        $this->assertSame(array_slice(array_values(array_unique(array_map('intval', $head))), 0, classreport::MAX_COLUMNS), $kept);
        $this->assertSame([7, 3], array_slice($kept, 0, 2));
    }

    /**
     * The value stored never exceeds what core's column holds: the widths go, from the last, and the ids stay.
     *
     * @return void
     */
    public function test_the_stored_value_fits_cores_column(): void {
        [$course, $ids] = $this->course_with(classreport::MAX_COLUMNS);
        $user = $this->getDataGenerator()->create_user();
        $keys = array_map(static fn(int $id): string => 'comp:' . $id, $ids);
        $widths = [];
        for ($i = 0; $i < classreportpreference::MAX_WIDTHS; $i++) {
            $widths['identity:' . str_pad((string) $i, 40, 'x', STR_PAD_LEFT)] = 1000;
        }

        $stored = classreportpreference::write((int) $course->id, $keys, $widths, (int) $user->id);
        $raw = get_user_preferences(classreportpreference::name((int) $course->id), null, $user);
        $this->assertLessThanOrEqual(classreportpreference::MAX_LENGTH, \core_text::strlen($raw));
        $this->assertSame($ids, $stored['columns']);
        $this->assertGreaterThan(0, count($stored['widths']));
        $this->assertLessThan(classreportpreference::MAX_WIDTHS, count($stored['widths']));
        $this->assertSame(array_slice($widths, 0, count($stored['widths'])), $stored['widths'], 'The first widths stay.');
    }

    /**
     * Core's permission callback answers no for the class report's preference, which core finds by its pattern; a
     * preference the theme lets its owner write answers yes, the control.
     *
     * @return void
     */
    public function test_cores_permission_callback_refuses(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertTrue(\core_user::can_edit_preference(\theme_dimensions\local\focusmode::PREFERENCE_EXPANDED, $user));
        $this->assertFalse(\core_user::can_edit_preference(classreportpreference::name(12), $user));
        $this->assertNotNull(\core_user::get_preference_definition(classreportpreference::name(12)), 'Declared by pattern.');
    }
}
