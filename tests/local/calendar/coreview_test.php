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
 * Theme Dimensions - Tests for the decision that sends core's calendar to the calendar page
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Every refusal of {@see coreview} beside the same request sent once its one reason is gone.
 *
 * The callback that reads the request and ends it with redirect() cannot run under PHPUnit; everything it decides is
 * here, and what it does is theme_dimensions_calendar.feature's.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(coreview::class)]
#[CoversClass(page::class)]
final class coreview_test extends \advanced_testcase {
    use fixture;

    /**
     * Forget the request parameters and the router a test built.
     *
     * @return void
     */
    protected function tearDown(): void {
        $_GET = [];
        \core\di::reset_container();
        self::forget_calendar();
        parent::tearDown();
    }

    /** @var int The account every decision makes unless it names another. */
    private const ACCOUNT = 7;

    /**
     * Decide a request the way the callback does, with every input a real, Gregorian, setting-on reader unless given.
     *
     * @param array $request The request's parameters.
     * @param array $with Overrides: enabled, account (0 for a visitor or the guest), gregorian, timezone, now,
     *     categoryof (a course's category the viewer may open; by default every course is in category 40 + its id).
     * @return \core\url|null
     */
    private function decide(array $request, array $with = []): ?\core\url {
        return coreview::target(
            $request,
            $with['enabled'] ?? true,
            $with['account'] ?? self::ACCOUNT,
            $with['gregorian'] ?? true,
            new \DateTimeZone($with['timezone'] ?? 'UTC'),
            $with['now'] ?? 1800000000,
            $with['categoryof'] ?? fn(int $courseid): int => 40 + $courseid
        );
    }

    /**
     * Month and day views are sent, with the month, the day and the category.
     *
     * @return void
     */
    public function test_the_month_and_the_day_are_sent_with_what_the_address_meant(): void {
        $this->resetAfterTest();
        $month = $this->decide(['view' => 'month', 'cal_d' => 1, 'cal_m' => 3, 'cal_y' => 2027]);
        $this->assertSame(['month' => '2027-03'], $month->params());
        $this->assertSame(page::url()->out_omit_querystring(), $month->out_omit_querystring());

        $day = $this->decide(['view' => 'day', 'cal_d' => 9, 'cal_m' => 3, 'cal_y' => 2027]);
        $this->assertSame(['month' => '2027-03', 'day' => '2027-03-09'], $day->params());

        $category = $this->decide(['view' => 'month', 'cal_d' => 1, 'cal_m' => 3, 'cal_y' => 2027, 'category' => 12]);
        $this->assertSame(['month' => '2027-03', 'categoryid' => '12'], $category->params());
        $written = $this->decide(['view' => 'month', 'cal_d' => 1, 'cal_m' => 3, 'cal_y' => 2027, 'categoryid' => 13]);
        $this->assertSame('13', $written->param('categoryid'), 'The name core writes into its own address is read too.');
        $this->assertNull($month->param('categoryid'), 'No category is invented.');
        $unknown = $this->decide(
            ['view' => 'month', 'cal_d' => 1, 'cal_m' => 3, 'cal_y' => 2027, 'lookahead' => 7, 'course' => (int) SITEID]
        );
        $this->assertSame(['month' => '2027-03'], $unknown->params(), 'Nothing else is carried.');
    }

    /**
     * Both spellings of the address, with the router off and on.
     *
     * @return void
     */
    public function test_the_address_is_the_pages_whichever_way_it_is_spelled(): void {
        global $CFG;
        $this->resetAfterTest();
        $request = ['view' => 'month', 'cal_d' => 1, 'cal_m' => 3, 'cal_y' => 2027];
        $CFG->routerconfigured = false;
        $this->assertSame(
            (new \core\url('/theme/dimensions/calendar.php', ['month' => '2027-03']))->out(false),
            $this->decide($request)->out(false)
        );
        $CFG->routerconfigured = true;
        \core\di::reset_container();
        $this->assertStringEndsWith('/theme_dimensions/calendar?month=2027-03', $this->decide($request)->out(false));
    }

    /**
     * The requests that stay on core's page, each beside the same request sent once the one reason is gone.
     *
     * @return array
     */
    public static function kept_on_core(): array {
        $month = ['view' => 'month', 'cal_d' => 1, 'cal_m' => 3, 'cal_y' => 2027];
        return [
            'a visitor or the guest' => [$month, ['account' => 0], $month],
            'another calendar type' => [$month, ['gregorian' => false], $month],
            'the setting off' => [$month, ['enabled' => false], $month],
        ];
    }

    /**
     * Each refusal answers null; the control, sent once the reason is removed, answers the page.
     *
     * @param array $request The request core keeps.
     * @param array $with The inputs that make it so.
     * @param array $control The same request with the reason removed.
     * @return void
     */
    #[DataProvider('kept_on_core')]
    public function test_each_refusal_keeps_core_and_its_control_is_sent(array $request, array $with, array $control): void {
        $this->resetAfterTest();
        $this->assertNull($this->decide($request, $with));
        $this->assertNotNull($this->decide($control), 'The same request, with its one reason gone.');
    }

    /**
     * Every view of core's calendar is sent: the day view with its day's drawer, any other view to the month alone.
     *
     * @return array
     */
    public static function views(): array {
        return [
            'month' => ['month', false],
            'day' => ['day', true],
            'upcoming' => ['upcoming', false],
            'the mini upcoming view' => ['upcoming_mini', false],
            'no view at all' => [null, false],
            'a view core does not know' => ['nonsense', false],
        ];
    }

    /**
     * A view is sent to the month of the day its address names; only the day view opens that day.
     *
     * @param string|null $view The view, or null for none.
     * @param bool $opensday Whether the day's drawer opens.
     * @return void
     */
    #[DataProvider('views')]
    public function test_every_view_is_sent(?string $view, bool $opensday): void {
        $this->resetAfterTest();
        $request = ['cal_d' => 9, 'cal_m' => 3, 'cal_y' => 2027] + ($view === null ? [] : ['view' => $view]);
        $target = $this->decide($request);
        $this->assertNotNull($target);
        $this->assertSame('2027-03', $target->param('month'));
        $this->assertSame($opensday ? '2027-03-09' : null, $target->param('day'));
    }

    /**
     * A course calendar is sent narrowed to the course's category; a course the viewer may not open names none.
     *
     * The category is asked of the closure only for an address naming a real course: the site's and 0 are no course.
     *
     * @return void
     */
    public function test_a_course_calendar_is_sent_with_its_category(): void {
        $this->resetAfterTest();
        $month = ['view' => 'month', 'cal_d' => 1, 'cal_m' => 3, 'cal_y' => 2027];
        $asked = [];
        $categoryof = function (int $courseid) use (&$asked): int {
            $asked[] = $courseid;
            return $courseid === 12 ? 5 : 0;
        };

        $course = $this->decide($month + ['course' => 12], ['categoryof' => $categoryof]);
        $this->assertSame(['month' => '2027-03', 'categoryid' => '5'], $course->params());

        $closed = $this->decide($month + ['course' => 13, 'category' => 8], ['categoryof' => $categoryof]);
        $this->assertSame(['month' => '2027-03'], $closed->params(), 'A course the viewer may not open: the plain page.');

        $site = $this->decide($month + ['course' => (int) SITEID, 'category' => 8], ['categoryof' => $categoryof]);
        $this->assertSame('8', $site->param('categoryid'), 'The site course is no course: the category is read.');
        $zero = $this->decide($month + ['course' => 0], ['categoryof' => $categoryof]);
        $this->assertSame(['month' => '2027-03'], $zero->params(), 'An explicit 0, which core refuses: the plain page.');
        $this->assertSame([12, 13], $asked, 'Only a real course is looked up.');
    }

    /**
     * The category a course calendar is sent with is the course's, and only for an account that may open the course.
     *
     * @return void
     */
    public function test_the_course_category_is_read_for_an_account_that_may_open_the_course(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $student = $generator->create_user();
        $stranger = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $this->setUser($student);
        $this->assertSame((int) $category->id, coreview::course_category((int) $course->id));
        $this->setUser($stranger);
        $this->assertSame(0, coreview::course_category((int) $course->id), 'Not enrolled: no category.');
        $this->assertSame(0, coreview::course_category((int) $course->id + 1000), 'No such course: no category.');
        $this->setAdminUser();
        $this->assertSame((int) $category->id, coreview::course_category((int) $course->id), 'Access without enrolment.');
    }

    /**
     * A course's category is named only when the page would accept it as a scope.
     *
     * An unlisted category (local_unlistedcourses) and a hidden one (core) are refused by the page, so a course in
     * either names no category; the listed control is named, and the admin, who sees both, gets both.
     *
     * @return void
     */
    public function test_a_withheld_or_hidden_category_is_not_named(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $listed = $generator->create_category();
        $unlisted = $generator->create_category();
        $hidden = $generator->create_category(['visible' => 0]);
        $this->set_category_state((int) $unlisted->id, \local_unlistedcourses\category_discoverability::STATE_UNLISTED);
        $student = $generator->create_user();
        $courses = [];
        foreach (['listed' => $listed, 'unlisted' => $unlisted, 'hidden' => $hidden] as $name => $category) {
            $courses[$name] = (int) $generator->create_course(['category' => $category->id])->id;
            $generator->enrol_user($student->id, $courses[$name], 'student');
        }

        $this->as_viewer($student);
        $this->assertSame((int) $listed->id, coreview::course_category($courses['listed']), 'The control.');
        $this->assertSame(0, coreview::course_category($courses['unlisted']), 'Withheld by local_unlistedcourses.');
        $this->assertSame(0, coreview::course_category($courses['hidden']), 'Hidden by core.');

        $this->setAdminUser();
        self::forget_calendar();
        $this->assertSame((int) $hidden->id, coreview::course_category($courses['hidden']), 'Core lets the admin see it.');
    }

    /**
     * A viewer on the calendar type core ships for tests is not Gregorian, the Gregorian one is.
     *
     * The input the callback computes is the one window::require_gregorian() reads.
     *
     * @return void
     */
    public function test_the_calendar_type_the_callback_reads(): void {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/calendar/tests/calendartype_test_example.php');
        $this->resetAfterTest();
        $request = ['view' => 'month', 'cal_d' => 1, 'cal_m' => 3, 'cal_y' => 2027];
        $this->setUser(self::getDataGenerator()->create_user());

        $this->assertTrue(coreview::gregorian());
        $this->assertNotNull($this->decide($request, ['gregorian' => coreview::gregorian()]));
        $USER->calendartype = 'test_example';
        $this->assertFalse(coreview::gregorian());
        $this->assertNull($this->decide($request, ['gregorian' => coreview::gregorian()]));
    }

    /**
     * The account the callback reads is the complement of the page's bounces: 0 for a visitor and the guest.
     *
     * @return void
     */
    public function test_the_account_is_neither_a_visitor_nor_the_guest(): void {
        $this->resetAfterTest();
        $this->setUser(0);
        $this->assertSame(0, coreview::account(), 'A visitor.');
        $this->setGuestUser();
        $this->assertSame(0, coreview::account(), 'The guest.');
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertSame((int) $user->id, coreview::account());
    }

    /**
     * The date of a timestamp is the viewer's, not UTC's, in both directions across midnight.
     *
     * @return array
     */
    public static function timezones(): array {
        return [
            'ahead of UTC' => ['Pacific/Auckland', gmmktime(23, 0, 0, 11, 30, 2026), '2026-12', '2026-12-01'],
            'behind UTC' => ['America/Los_Angeles', gmmktime(3, 0, 0, 12, 1, 2026), '2026-11', '2026-11-30'],
            'UTC itself' => ['UTC', gmmktime(23, 0, 0, 11, 30, 2026), '2026-11', '2026-11-30'],
        ];
    }

    /**
     * A request that names a timestamp is read in the viewer's timezone.
     *
     * @param string $timezone The viewer's timezone.
     * @param int $time The request's timestamp.
     * @param string $month The month it falls in for the viewer.
     * @param string $day The day it falls on for the viewer.
     * @return void
     */
    #[DataProvider('timezones')]
    public function test_the_time_is_read_in_the_viewers_timezone(string $timezone, int $time, string $month, string $day): void {
        $this->resetAfterTest();
        $this->assertSame($month, $this->decide(['view' => 'month', 'time' => $time], ['timezone' => $timezone])->param('month'));
        $this->assertSame($day, $this->decide(['view' => 'day', 'time' => $time], ['timezone' => $timezone])->param('day'));
    }

    /**
     * A request that names no day is the current time; an invalid cal_* date falls back to the time, a valid one wins.
     *
     * @return void
     */
    public function test_the_calendar_date_is_used_only_when_it_is_a_date(): void {
        $this->resetAfterTest();
        $now = gmmktime(12, 0, 0, 6, 15, 2026);
        $this->assertSame('2026-06-15', $this->decide(['view' => 'day'], ['now' => $now])->param('day'), 'No day: now.');

        $time = gmmktime(12, 0, 0, 9, 2, 2026);
        $invalid = ['view' => 'day', 'cal_d' => 31, 'cal_m' => 11, 'cal_y' => 2026, 'time' => $time];
        $this->assertSame('2026-09-02', $this->decide($invalid, ['now' => $now])->param('day'), '31 November is no date.');
        $partial = ['view' => 'day', 'cal_d' => 0, 'cal_m' => 11, 'cal_y' => 2026, 'time' => $time];
        $this->assertSame('2026-09-02', $this->decide($partial, ['now' => $now])->param('day'));
        $valid = ['view' => 'day', 'cal_d' => 30, 'cal_m' => 11, 'cal_y' => 2026, 'time' => $time];
        $this->assertSame('2026-11-30', $this->decide($valid, ['now' => $now])->param('day'), 'A valid date wins over time.');
        $far = ['view' => 'day', 'cal_d' => 1, 'cal_m' => 1, 'cal_y' => 10000, 'time' => $time];
        $this->assertSame('2026-09-02', $this->decide($far, ['now' => $now])->param('day'), 'A five-digit year is no month.');
    }

    /**
     * The setting ships on and is off only when an explicit '0' is stored.
     *
     * @return void
     */
    public function test_the_setting_is_off_only_when_it_is_an_explicit_zero(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->theme = 'dimensions';
        $this->assertTrue(coreview::enabled(), 'Never written: on.');
        set_config(coreview::SETTING, '1', coreview::COMPONENT);
        $this->assertTrue(coreview::enabled());
        set_config(coreview::SETTING, '', coreview::COMPONENT);
        $this->assertTrue(coreview::enabled(), 'An empty value is not an explicit zero.');
        set_config(coreview::SETTING, '0', coreview::COMPONENT);
        $this->assertFalse(coreview::enabled());
    }

    /**
     * A site whose theme is another one keeps core's calendar: the page is drawn by this theme's CSS and client.
     *
     * @return void
     */
    public function test_another_site_theme_keeps_core(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->theme = 'boost';
        $this->assertFalse(coreview::enabled());
        $CFG->theme = 'dimensions';
        $this->assertTrue(coreview::enabled(), 'The control: this theme, the setting never written.');
    }

    /**
     * The request's parameters are read as core's view reads them.
     *
     * @return void
     */
    public function test_the_request_is_read_as_core_reads_it(): void {
        $this->resetAfterTest();
        $_GET = [
            'view' => 'day',
            'course' => '1',
            'cal_d' => '9',
            'cal_m' => '3',
            'cal_y' => '2027',
            'category' => '5',
        ];
        $params = coreview::request_params();
        $this->assertSame('day', $params['view']);
        $this->assertSame(9, $params['cal_d']);
        $this->assertSame(5, $params['category']);
        $this->assertSame(0, $params['time']);
        unset($_GET['course']);
        $this->assertSame((int) SITEID, coreview::request_params()['course'], 'An absent course is the site, as in core.');
    }
}
