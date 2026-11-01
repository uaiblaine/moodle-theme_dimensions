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
 * Theme Dimensions - population::admitted(), the gate for callers outside the listing
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use core\context_helper;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * A row outside the scope's admitted categories and any row for an anonymous scope are not admitted.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(population::class)]
final class admitted_test extends \advanced_testcase {
    use fixture;

    /**
     * The population rows of these courses, as the listing's statement selects them.
     *
     * @param int[] $courseids The courses.
     * @return \stdClass[] Rows keyed by id.
     */
    private function rows(array $courseids): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $state = discoverability::state_sql('c');
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        return $DB->get_records_sql(
            "SELECT c.id, c.category, c.sortorder, c.visible, c.fullname, c.startdate,
                    {$state['column']} AS liststate, {$ctxselect}
               FROM {course} c
               JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse
                    {$state['join']}
              WHERE c.id {$insql}
           ORDER BY c.id",
            $params + ['contextcourse' => CONTEXT_COURSE]
        );
    }

    /**
     * Inside the narrowed root a row is admitted; the sibling category's row is not, and the root scope admits both.
     *
     * @return void
     */
    public function test_a_row_outside_the_narrowed_scope_is_not_admitted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $inside = $generator->create_category();
        $outside = $generator->create_category();
        $in = $generator->create_course(['category' => $inside->id]);
        $out = $generator->create_course(['category' => $outside->id]);
        $student = $generator->create_user();
        $this->setUser($student);
        self::forget();
        $rows = $this->rows([(int) $in->id, (int) $out->id]);

        $narrowed = population::admitted(population::scope(new criteria((int) $inside->id)), $rows);
        $this->assertSame([(int) $in->id], array_keys($narrowed));

        // The control: the root scope knows both categories and admits both rows.
        $root = population::admitted(population::scope(new criteria(0)), $rows);
        $this->assertSame([(int) $in->id, (int) $out->id], array_keys($root));
    }

    /**
     * An anonymous scope admits nothing to a caller outside the listing; the logged-in scope over the same rows does.
     *
     * @return void
     */
    public function test_an_anonymous_scope_admits_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = self::getDataGenerator()->create_category();
        $course = self::getDataGenerator()->create_course(['category' => $category->id]);
        $this->setUser(self::getDataGenerator()->create_user());
        self::forget();
        $rows = $this->rows([(int) $course->id]);
        $scope = population::scope(new criteria(0));
        $this->assertSame([(int) $course->id], array_keys(population::admitted($scope, $rows)));

        $scope->anonymous = true;
        $this->assertSame([], population::admitted($scope, $rows));
    }
}
