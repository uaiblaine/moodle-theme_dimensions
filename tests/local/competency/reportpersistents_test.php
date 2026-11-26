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
 * Theme Dimensions - Tests for the lifecycle of the class report's persistents
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use context_course;
use core_reportbuilder\local\helpers\user_filter_manager;
use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\user_filter;
use core_reportbuilder\local\report\base;
use core_reportbuilder\manager;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\reportbuilder\local\systemreports\class_ratings;

/**
 * A course's report persistent is one row, the oldest, and goes with the course together with the filter values
 * readers stored on it; another course's report is the control every time.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reportpersistents::class)]
#[CoversClass(class_ratings::class)]
final class reportpersistents_test extends \advanced_testcase {
    /**
     * The class report's persistent of a course, created as a first view does.
     *
     * @param \stdClass $course The course.
     * @return int The persistent's id.
     */
    private function persistent_of(\stdClass $course): int {
        manager::reset_caches();
        $report = class_ratings::for_course(context_course::instance($course->id));

        return (int) $report->get_report_persistent()->get('id');
    }

    /**
     * The stored filter values of a report.
     *
     * @param int $reportid The report.
     * @return int
     */
    private function stored(int $reportid): int {
        global $DB;

        return $DB->count_records(user_filter::TABLE, ['reportid' => $reportid]);
    }

    /**
     * Two persistents for one course (two first views racing): the next read keeps the oldest with its readers'
     * filter values, deletes the other with its own, and a persistent of another course is untouched.
     *
     * @return void
     */
    public function test_a_duplicate_persistent_is_removed_and_the_oldest_keeps_its_filters(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $other = $generator->create_course();
        $reader = $generator->create_user();

        $oldest = $this->persistent_of($course);
        $control = $this->persistent_of($other);
        $duplicate = manager::create_report_persistent((object) [
            'type' => base::TYPE_SYSTEM_REPORT,
            'source' => class_ratings::class,
            'contextid' => context_course::instance($course->id)->id,
            'component' => 'theme_dimensions',
            'area' => class_ratings::AREA,
            'itemid' => 0,
        ]);
        $extra = (int) $duplicate->get('id');
        $this->assertGreaterThan($oldest, $extra, 'Precondition: two persistents, the second newer.');
        user_filter_manager::set($oldest, ['user:fullname_value' => 'Sam'], (int) $reader->id);
        user_filter_manager::set($extra, ['user:fullname_value' => 'Kim'], (int) $reader->id);
        user_filter_manager::set($control, ['user:fullname_value' => 'Lee'], (int) $reader->id);

        $this->assertSame($oldest, $this->persistent_of($course), 'The read answers with the oldest.');
        $contextid = context_course::instance($course->id)->id;
        $this->assertSame(1, $DB->count_records(report::TABLE, ['contextid' => $contextid, 'source' => class_ratings::class]));
        $this->assertFalse($DB->record_exists(report::TABLE, ['id' => $extra]));
        $this->assertSame(0, $this->stored($extra), 'The deleted persistent takes its filter values.');
        $this->assertSame(1, $this->stored($oldest), 'The oldest keeps its reader\'s filter values.');
        $this->assertSame(1, $this->stored($control), 'The control: another course\'s report is untouched.');
        $this->assertSame($control, $this->persistent_of($other));
    }

    /**
     * Deleting a course deletes the class report's persistents of its context and the stored filter values; another
     * course's report and values stay.
     *
     * Core dispatches an external observer when the transaction commits, which a test never does unless it is told
     * not to roll back.
     *
     * @return void
     */
    public function test_a_deleted_course_takes_its_report_and_stored_filters(): void {
        global $DB;
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $other = $generator->create_course();
        $reader = $generator->create_user();
        $gone = $this->persistent_of($course);
        $kept = $this->persistent_of($other);
        user_filter_manager::set($gone, ['user:fullname_value' => 'Sam'], (int) $reader->id);
        user_filter_manager::set($kept, ['user:fullname_value' => 'Lee'], (int) $reader->id);

        delete_course($course, false);

        $this->assertFalse($DB->record_exists(report::TABLE, ['id' => $gone]), 'The deleted course\'s report is gone.');
        $this->assertSame(0, $this->stored($gone), 'With the readers\' stored filter values.');
        $this->assertTrue($DB->record_exists(report::TABLE, ['id' => $kept]), 'The control: another course\'s report stays.');
        $this->assertSame(1, $this->stored($kept));
    }
}
