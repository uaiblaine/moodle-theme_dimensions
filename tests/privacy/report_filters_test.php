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
 * Theme Dimensions - Tests for the filter values stored on the class report, as personal data
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\privacy;

use context_course;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_reportbuilder\local\helpers\user_filter_manager;
use core_reportbuilder\local\models\user_filter;
use core_reportbuilder\local\report\base;
use core_reportbuilder\manager;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\competency\reportpersistents;
use theme_dimensions\reportbuilder\local\systemreports\class_ratings;

/**
 * The filter values a reader stores on the class report are rows of core's table under the theme's report, which
 * core's provider leaves out (fleet decision D146): found in the reader's user context, exported with the course,
 * deleted on request and with the account, and removed with the theme. Another report's rows are the control every
 * time.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
#[CoversClass(reportpersistents::class)]
final class report_filters_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass A teacher with filters stored on the class report. */
    private $reader;

    /** @var \stdClass The course. */
    private $course;

    /** @var int The class report's persistent. */
    private $reportid;

    /** @var int Another source's report persistent, with the reader's filters too. */
    private $otherid;

    /**
     * A course's class report and another report, each with the reader's stored filter values.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['fullname' => 'Cookery']);
        $this->reader = $generator->create_and_enrol($this->course, 'teacher');
        $this->setUser($this->reader);
        $report = class_ratings::for_course(context_course::instance($this->course->id));
        $this->reportid = (int) $report->get_report_persistent()->get('id');
        user_filter_manager::set($this->reportid, ['user:fullname_operator' => 1, 'user:fullname_value' => 'Sam']);

        $other = manager::create_report_persistent((object) [
            'type' => base::TYPE_SYSTEM_REPORT,
            'source' => \core_admin\reportbuilder\local\systemreports\task_logs::class,
            'contextid' => \context_system::instance()->id,
            'component' => 'core_admin',
            'area' => '',
            'itemid' => 0,
        ]);
        $this->otherid = (int) $other->get('id');
        user_filter_manager::set($this->otherid, ['task_log:name_value' => 'x']);
        $this->setUser(null);
        manager::reset_caches();
    }

    /**
     * The reader's stored filter values on one report.
     *
     * @param int $reportid The report.
     * @return int
     */
    private function stored(int $reportid): int {
        global $DB;
        return $DB->count_records(user_filter::TABLE, ['reportid' => $reportid, 'usercreated' => $this->reader->id]);
    }

    /**
     * The reader's user context holds the data, and the reader is its user; another person's holds nothing.
     *
     * @return void
     */
    public function test_the_readers_user_context_is_found(): void {
        $usercontext = \context_user::instance($this->reader->id);
        $contextids = array_map('intval', provider::get_contexts_for_userid((int) $this->reader->id)->get_contextids());
        $this->assertSame([(int) $usercontext->id], $contextids);

        $userlist = new userlist($usercontext, 'theme_dimensions');
        provider::get_users_in_context($userlist);
        $this->assertSame([(int) $this->reader->id], array_map('intval', $userlist->get_userids()));

        $stranger = $this->getDataGenerator()->create_user();
        $this->assertSame([], provider::get_contexts_for_userid((int) $stranger->id)->get_contextids());
    }

    /**
     * The export carries the values and names the course the reader may open the report of, run as another user.
     *
     * @return void
     */
    public function test_the_export_names_the_course(): void {
        $usercontext = \context_user::instance($this->reader->id);
        $this->setAdminUser();
        provider::export_user_data(new approved_contextlist($this->reader, 'theme_dimensions', [(int) $usercontext->id]));

        $exported = writer::with_context($usercontext)->get_data([get_string('privacy:path:reportfilters', 'theme_dimensions')]);
        $this->assertCount(1, $exported->filters, 'Only the class report\'s values, not the other report\'s.');
        $this->assertSame('Cookery', $exported->filters[0]->course);
        $this->assertSame('Sam', $exported->filters[0]->filters->{'user:fullname_value'});
    }

    /**
     * Each deletion of a request removes the class report's values and keeps the other report's.
     *
     * @return void
     */
    public function test_a_request_deletes_the_values(): void {
        $usercontext = \context_user::instance($this->reader->id);
        provider::delete_data_for_user(new approved_contextlist($this->reader, 'theme_dimensions', [(int) $usercontext->id]));
        $this->assertSame(0, $this->stored($this->reportid));
        $this->assertSame(1, $this->stored($this->otherid), 'The control: another report is core\'s.');

        user_filter_manager::set($this->reportid, ['user:fullname_value' => 'Sam'], (int) $this->reader->id);
        provider::delete_data_for_users(new approved_userlist($usercontext, 'theme_dimensions', [(int) $this->reader->id]));
        $this->assertSame(0, $this->stored($this->reportid));

        user_filter_manager::set($this->reportid, ['user:fullname_value' => 'Sam'], (int) $this->reader->id);
        provider::delete_data_for_all_users_in_context($usercontext);
        $this->assertSame(0, $this->stored($this->reportid));
        $this->assertSame(1, $this->stored($this->otherid));
    }

    /**
     * Deleting the account removes the values (the user context the finder reads is gone by then).
     *
     * @return void
     */
    public function test_the_accounts_deletion_deletes_the_values(): void {
        $this->assertSame(1, $this->stored($this->reportid), 'Precondition.');
        delete_user($this->reader);
        $this->assertSame(0, $this->stored($this->reportid));
        $this->assertSame(1, $this->stored($this->otherid), 'The control: another report is core\'s.');
    }

    /**
     * The theme's uninstall removes its report persistents and their values, and no other report.
     *
     * @return void
     */
    public function test_the_uninstall_removes_the_reports(): void {
        global $DB;
        reportpersistents::delete_all();
        $this->assertFalse($DB->record_exists('reportbuilder_report', ['id' => $this->reportid]));
        $this->assertSame(0, $this->stored($this->reportid));
        $this->assertTrue($DB->record_exists('reportbuilder_report', ['id' => $this->otherid]));
        $this->assertSame(1, $this->stored($this->otherid));
    }
}
