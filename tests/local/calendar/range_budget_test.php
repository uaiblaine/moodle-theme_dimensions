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
 * Theme Dimensions - The statement budget of the calendar range
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\external\get_calendar_range;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * What one month grid costs in statements: flat in the events where the theme reads, bounded where core does.
 *
 * Statements are perf_get_reads(), which counts statements (a recordset is 3 on PostgreSQL), of the whole service
 * function after one warm call, every per-request memo reset as a new request starts ({@see fixture::reads()}, plus
 * the event container's and modinfo's statics). The shape is: per course six one-hour events, a 3-day and a 10-day
 * event, a group event and two assignments with due dates; two site, three user and one category event.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(range::class)]
#[CoversClass(get_calendar_range::class)]
final class range_budget_test extends \advanced_testcase {
    use fixture;

    /**
     * The probe's month for a student enrolled in $n courses over a parent category and two subcategories.
     *
     * @param int $n The courses.
     * @param bool $withactivities Whether the courses carry the two assignments and the group event.
     * @param int $plain One-hour course events per course.
     * @return \stdClass The student.
     */
    private function seed(int $n, bool $withactivities = true, int $plain = 6): \stdClass {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $parent = $generator->create_category();
        $categories = [
            $parent,
            $generator->create_category(['parent' => $parent->id]),
            $generator->create_category(['parent' => $parent->id]),
        ];
        $student = $generator->create_user();
        for ($i = 0; $i < $n; $i++) {
            $course = $generator->create_course(['category' => $categories[$i % 3]->id]);
            $generator->enrol_user($student->id, $course->id, 'student');
            for ($k = 0; $k < $plain; $k++) {
                $generator->create_event([
                    'eventtype' => 'course',
                    'courseid' => $course->id,
                    'timestart' => self::at(2 + (($k * 4 + $i) % 27), 10),
                    'timeduration' => HOURSECS,
                ]);
            }
            if (!$withactivities) {
                continue;
            }
            $group = $generator->create_group(['courseid' => $course->id]);
            $generator->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
            $generator->create_event([
                'eventtype' => 'course',
                'courseid' => $course->id,
                'timestart' => self::at(2 + $i % 10),
                'timeduration' => 3 * DAYSECS,
            ]);
            $generator->create_event([
                'eventtype' => 'course',
                'courseid' => $course->id,
                'timestart' => self::at(4 + $i % 3),
                'timeduration' => 10 * DAYSECS,
            ]);
            $generator->create_event([
                'eventtype' => 'group',
                'courseid' => $course->id,
                'groupid' => $group->id,
                'timestart' => self::at(12 + $i % 10, 14),
            ]);
            for ($a = 0; $a < 2; $a++) {
                $generator->create_module('assign', [
                    'course' => $course->id,
                    'duedate' => self::at($a ? 20 + $i % 8 : 10 + $i % 10, 23),
                ]);
            }
        }
        $generator->create_event(['eventtype' => 'site', 'timestart' => self::at(5)]);
        $generator->create_event(['eventtype' => 'site', 'timestart' => self::at(15), 'timeduration' => 2 * DAYSECS]);
        for ($u = 0; $u < 3; $u++) {
            $generator->create_event(['eventtype' => 'user', 'userid' => $student->id, 'timestart' => self::at(7 + $u, 8)]);
        }
        $generator->create_event(['eventtype' => 'category', 'categoryid' => $parent->id, 'timestart' => self::at(18)]);
        return $student;
    }

    /**
     * The service's answer for next month, with the statements and milliseconds of a call as a new request.
     *
     * @param \stdClass $student The viewer.
     * @return array [answer, statements, milliseconds]
     */
    private function measure(\stdClass $student): array {
        $this->setUser($student);
        $start = self::month_start();
        $args = [$start->format('Y-m-d'), $start->modify('last day of this month')->format('Y-m-d')];
        // One warm call, as on a site in use: the application caches (modinfo, categories) are filled.
        get_calendar_range::execute(...$args);
        $answer = null;
        $milliseconds = 0.0;
        $reads = $this->reads(function () use ($args, &$answer, &$milliseconds): void {
            \core_calendar\local\event\container::reset_caches();
            \course_modinfo::clear_instance_cache();
            $before = hrtime(true);
            $answer = get_calendar_range::execute(...$args);
            $milliseconds = (hrtime(true) - $before) / 1e6;
        });
        return [$answer, $reads, $milliseconds];
    }

    /**
     * The theme's own reading is flat in the number of events: four times the plain events, the same statements.
     *
     * Plain course events cost core's factory nothing per event (no module, no category), so what grows with them
     * is the theme's: the dictionaries, the custom fields, the images, the branding.
     *
     * @return void
     */
    public function test_flat_in_the_number_of_events(): void {
        [$few, $fewreads] = $this->measure($this->seed(5, false, 2));
        self::forget_calendar();
        [$many, $manyreads] = $this->measure($this->seed(5, false, 8));

        $this->assertGreaterThan(count($few['events']) * 2, count($many['events']));
        $this->assertSame($fewreads, $manyreads);
    }

    /**
     * The probe's shape at 5 and 25 courses stays within its budget, and its answer within its weight.
     *
     * Each bound is the shape's cost plus a tenth: 123 statements and 26,638 bytes of JSON at 5 courses, 562 and
     * 109,311 at 25. What grows with the courses is core's module callbacks and the events themselves, which are
     * nine tenths of the answer's weight.
     *
     * @return void
     */
    public function test_the_month_stays_within_its_budget(): void {
        foreach ([5 => [136, 29302], 25 => [619, 120243]] as $n => [$statements, $bytes]) {
            [$answer, $reads] = $this->measure($this->seed($n));
            $json = json_encode($answer);

            $this->assertGreaterThan(10 * $n, count($answer['events']), "$n courses: the month was read.");
            $this->assertLessThanOrEqual($statements, $reads, "$n courses: statements");
            $this->assertLessThanOrEqual($bytes, strlen($json), "$n courses: bytes");
            $this->assertStringNotContainsString('data:', $json);
            self::forget_calendar();
        }
    }
}
