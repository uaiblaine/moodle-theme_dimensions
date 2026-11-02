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
 * Theme Dimensions - What one calendar block costs when the theme draws it
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\output\calendar_block_month;
use theme_dimensions\output\calendar_block_upcoming;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * The statements one block render adds on top of core's, at the C1 baseline's shape (range_budget_test's seed: the C0
 * probe's month for a student in 25 courses).
 *
 * Core's calendar_get_view() runs first whatever the theme does (the block calls it before the renderer), so what the
 * theme adds is its own range answer and its template, measured as range_budget_test measures a call (perf_get_reads()
 * after one warm call, every per-request memo reset). Core's half is measured the same way, for the record. "Now" is
 * the 10th of next month at 09:00, so the month block draws the seeded month and the agenda its next 21 days.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(blocks::class)]
final class blocks_budget_test extends \advanced_testcase {
    use fixture;

    /** @var int The month block's own statements at 25 courses: 533 measured, a tenth over. */
    private const MONTH_BUDGET = 586;

    /** @var int The agenda's own statements at 25 courses: 226 measured (a 7-day piece), a tenth over. */
    private const UPCOMING_BUDGET = 249;

    /**
     * The C0 probe's month for a student enrolled in $n courses: range_budget_test's seed, kept in step with it.
     *
     * @param int $n The courses.
     * @return \stdClass The student.
     */
    private function seed(int $n): \stdClass {
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
            for ($k = 0; $k < 6; $k++) {
                $generator->create_event([
                    'eventtype' => 'course',
                    'courseid' => $course->id,
                    'timestart' => self::at(2 + (($k * 4 + $i) % 27), 10),
                    'timeduration' => HOURSECS,
                ]);
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
     * One render as a new request: its statements and milliseconds, after one warm call.
     *
     * @param callable $render The render.
     * @return array [statements, milliseconds, output]
     */
    private function measure(callable $render): array {
        $render();
        $output = '';
        $milliseconds = 0.0;
        $reads = $this->reads(function () use ($render, &$output, &$milliseconds): void {
            \core_calendar\local\event\container::reset_caches();
            \course_modinfo::clear_instance_cache();
            $before = hrtime(true);
            $output = $render();
            $milliseconds = (hrtime(true) - $before) / 1e6;
        });
        return [$reads, $milliseconds, $output];
    }

    /**
     * At 25 courses each block's own half stays within its measured budget; core's half is recorded beside it.
     *
     * Measured on m503b (PHP 8.4, PostgreSQL 18), 2026-11-01 (docs/calendar/PROPOSAL.md section 18): core's month
     * block 733 statements and about 880 ms, core's upcoming block 13 and 23 ms; the theme's month 533 and about
     * 105 ms, its agenda 226 and 45 ms. The bounds keep a tenth of headroom over the theme's statements.
     *
     * @return void
     */
    public function test_one_block_render_stays_within_its_budget(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $student = $this->seed(25);
        $this->setUser($student);
        $now = self::at(10, 9);
        $PAGE->set_url('/my/');
        $renderer = $PAGE->get_renderer('core_calendar');

        $core = [];
        foreach (['monthblock', 'upcoming_mini'] as $view) {
            $core[$view] = $this->measure(function () use ($now, $view, $renderer): string {
                [$data, $template] = calendar_get_view(\calendar_information::create($now, SITEID, null), $view, true);
                return $renderer->render_from_template($template, $data);
            });
        }
        $month = $this->measure(function () use ($now, $renderer): string {
            $month = blocks::month($now);
            $block = new calendar_block_month(
                $month['month'],
                $month['today'],
                $month['firstday'],
                $month['state'],
                $month['answer']
            );
            return $renderer->render_from_template(calendar_block_month::TEMPLATE, $block->export_for_template($renderer));
        });
        $upcoming = $this->measure(function () use ($now, $renderer): string {
            $upcoming = blocks::upcoming($now);
            $block = new calendar_block_upcoming($upcoming['items'], $upcoming['courses'], $now);
            return $renderer->render_from_template(calendar_block_upcoming::TEMPLATE, $block->export_for_template($renderer));
        });

        $this->assertStringContainsString('dim-cal-key--course', $month[2], 'The month was read.');
        $this->assertSame(10, substr_count($upcoming[2], 'data-region="dim-calblock-item"'), 'Core\'s default count.');
        $this->assertLessThanOrEqual(self::MONTH_BUDGET, $month[0], 'The month block\'s statements.');
        $this->assertLessThanOrEqual(self::UPCOMING_BUDGET, $upcoming[0], 'The agenda\'s statements.');
        $this->assertLessThan($core['monthblock'][0], $month[0], 'The theme\'s month costs less than core\'s, which it adds to.');
        $this->assertGreaterThan(0, $core['upcoming_mini'][0], 'Core\'s agenda was measured.');
    }
}
