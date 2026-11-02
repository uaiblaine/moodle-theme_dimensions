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
 * Theme Dimensions - Tests for the calendar page's decision helpers
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
 * The month grid's window, the address both entries spell, and the narrowing the page drops.
 *
 * The route's own answers (the visitor, the guest, the calendar type, a page served) are
 * tests/route/controller/calendar_test.php's.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(page::class)]
final class page_test extends \advanced_testcase {
    use fixture;

    /**
     * Forget the request parameters a test set, and the router a test built.
     *
     * @return void
     */
    protected function tearDown(): void {
        $_GET = [];
        \core\di::reset_container();
        self::forget_calendar();
        parent::tearDown();
    }

    /**
     * Months and first weekdays, with the grid's first and last day.
     *
     * @return array
     */
    public static function grids(): array {
        return [
            'November 2026 from Sunday, five weeks' => ['2026-11', 0, '2026-11-01', '2026-12-05'],
            'November 2026 from Monday, six weeks' => ['2026-11', 1, '2026-10-26', '2026-12-06'],
            'February 2026 from Sunday, four weeks' => ['2026-02', 0, '2026-02-01', '2026-02-28'],
            'August 2026 from Sunday, six weeks' => ['2026-08', 0, '2026-07-26', '2026-09-05'],
        ];
    }

    /**
     * The grid is whole weeks from the one holding the 1st to the one holding the last day: one window.
     *
     * @param string $month The month.
     * @param int $firstday The first weekday.
     * @param string $from The grid's first day.
     * @param string $to Its last.
     * @return void
     */
    #[DataProvider('grids')]
    public function test_the_grid_is_whole_weeks(string $month, int $firstday, string $from, string $to): void {
        $this->assertSame([$from, $to], page::grid($month, $firstday));
        window::from_iso($from, $to, new \DateTimeZone('UTC'));
    }

    /**
     * A malformed month is the current one; a real one is kept.
     *
     * @return void
     */
    public function test_the_month_from_the_request(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $_GET = ['month' => '2027-02'];
        $this->assertSame('2027-02', page::month_from_request());
        foreach (['2027-13', '27-02', '2027-2', 'x', '0999-01', "2027-02\n"] as $bad) {
            $_GET = ['month' => $bad];
            $this->assertSame(page::today()->format('Y-m'), page::month_from_request(), $bad);
        }
    }

    /**
     * The route while the router is configured, the script otherwise; the script's redirect carries the month and the
     * state the address named, and nothing when it named none.
     *
     * @return void
     */
    public function test_the_address_and_the_forwarded_parameters(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $CFG->routerconfigured = false;
        $this->assertSame((new \core\url('/theme/dimensions/calendar.php'))->out(false), page::url()->out(false));

        $_GET = ['month' => '2026-11', 'keys' => ['site'], 'city' => 'Natal'];
        $params = page::forwarded_params();
        $this->assertSame('2026-11', $params['month']);
        $this->assertSame(['site'], $params['keys']);
        $this->assertSame('Natal', $params['city']);

        $_GET = [];
        $this->assertSame([], page::forwarded_params(), 'A bare address forwards nothing: the saved state applies there.');

        $CFG->routerconfigured = true;
        \core\di::reset_container();
        $this->assertStringEndsWith('/theme_dimensions/calendar', page::url()->out(false));
    }

    /**
     * The script's redirect to the route is decided before any lookup: it forwards the category the address named
     * as written, even a hidden one, and reads no row (the page, behind the route, judges it).
     *
     * calendar.php itself needs config.php and ends in redirect(), so it cannot run under PHPUnit; the decision
     * it takes is links::routing_enabled() then page::forwarded_params(), both exercised here.
     *
     * @return void
     */
    public function test_the_script_forwards_the_address_without_any_lookup(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $hidden = self::getDataGenerator()->create_category(['visible' => 0]);
        $this->setUser(self::getDataGenerator()->create_user());

        $CFG->routerconfigured = true;
        \core\di::reset_container();
        $this->assertTrue(\theme_dimensions\local\landing\links::routing_enabled());

        $_GET = ['month' => '2026-11', state::PARAM_CATEGORY => (string) $hidden->id, 'city' => 'Natal'];
        $before = (int) $DB->perf_get_reads();
        $params = page::forwarded_params();
        $reads = (int) $DB->perf_get_reads() - $before;

        $this->assertSame((int) $hidden->id, (int) $params[state::PARAM_CATEGORY], 'Forwarded as written, not judged.');
        $this->assertSame('2026-11', $params['month']);
        $this->assertSame(0, $reads, 'The script reads no category, course or user row before redirecting.');

        $CFG->routerconfigured = false;
        \core\di::reset_container();
        $this->assertFalse(\theme_dimensions\local\landing\links::routing_enabled());
    }

    /**
     * Open days and what the page keeps of them, for November 2026 from Sunday (grid 2026-11-01 to 2026-12-05).
     *
     * @return array
     */
    public static function days(): array {
        return [
            'a day of the month' => ['2026-11', '2026-11-17', '2026-11-17'],
            'the grid\'s first day' => ['2026-11', '2026-11-01', '2026-11-01'],
            'the next month\'s day the grid shows' => ['2026-11', '2026-12-05', '2026-12-05'],
            'the day after the grid' => ['2026-11', '2026-12-06', ''],
            'the day before the grid' => ['2026-11', '2026-10-31', ''],
            'a day of another month' => ['2026-11', '2027-01-20', ''],
            'a day that does not exist' => ['2026-11', '2026-11-31', ''],
            'a date without its zeros' => ['2026-11', '2026-11-7', ''],
            'a timestamp' => ['2026-11', '1794787200', ''],
            'not a day' => ['2026-11', 'tomorrow', ''],
            'a day with a trailing newline' => ['2026-11', "2026-11-20\n", ''],
            'nothing' => ['2026-11', '', ''],
        ];
    }

    /**
     * The open day is kept inside the month grid's window alone; outside it, malformed or impossible, it is dropped.
     *
     * @param string $month The month shown.
     * @param string $day The day the address names.
     * @param string $kept What the page keeps.
     * @return void
     */
    #[DataProvider('days')]
    public function test_the_open_day_is_kept_inside_the_window_only(string $month, string $day, string $kept): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $_GET = ['month' => $month, page::PARAM_DAY => $day];
        $this->assertSame($kept, page::day_from_request($month, 0));
    }

    /**
     * The window follows the first weekday: the same day is inside one grid and outside the other.
     *
     * @return void
     */
    public function test_the_window_follows_the_first_weekday(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $_GET = [page::PARAM_DAY => '2026-10-26'];
        $this->assertSame('2026-10-26', page::day_from_request('2026-11', 1), 'Monday first: the grid opens on 26 October.');
        $this->assertSame('', page::day_from_request('2026-11', 0), 'Sunday first: the grid opens on 1 November.');
    }

    /**
     * The open day is navigation, never state: an address naming the month and the day is still bare.
     *
     * @return void
     */
    public function test_the_open_day_is_not_state(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $this->assertNotContains(page::PARAM_DAY, state::STATE_PARAMS);
        $_GET = ['month' => '2026-11', page::PARAM_DAY => '2026-11-17'];
        $this->assertNull(state::from_request());
    }

    /**
     * The script's forward carries a real day as written, and drops one that is not a day; the page judges the window.
     *
     * @return void
     */
    public function test_the_forward_carries_the_open_day(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $_GET = ['month' => '2026-11', page::PARAM_DAY => '2027-01-20'];
        $before = (int) $DB->perf_get_reads();
        $params = page::forwarded_params();
        $this->assertSame(0, (int) $DB->perf_get_reads() - $before);
        $this->assertSame(['month' => '2026-11', page::PARAM_DAY => '2027-01-20'], $params, 'Forwarded as written, not judged.');

        $_GET = ['month' => '2026-11', page::PARAM_DAY => '2026-11-31'];
        $this->assertSame(['month' => '2026-11'], page::forwarded_params());
    }

    /**
     * A category the range would refuse is dropped from the state; one the viewer may narrow to is kept.
     *
     * @return void
     */
    public function test_a_refused_category_is_dropped(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $open = $generator->create_category();
        $hidden = $generator->create_category(['visible' => 0]);
        $user = $generator->create_user();
        $this->as_viewer($user);

        [, $kept] = page::scope_for(new state([], (int) $open->id));
        $this->assertSame((int) $open->id, $kept->categoryid);
        [$scope, $dropped] = page::scope_for(new state(['site'], (int) $hidden->id, false));
        $this->assertSame(0, $dropped->categoryid);
        $this->assertSame(['site'], $dropped->off, 'Only the category goes.');
        $this->assertFalse($dropped->recursive);
        $this->assertNull($scope->tree, 'The scope is the whole calendar.');
        [, $missing] = page::scope_for(new state([], 999999));
        $this->assertSame(0, $missing->categoryid);
    }

    /**
     * Weeks the address names and the first day the page keeps, with the month it falls back on.
     *
     * February 2027 opens on a Monday, so its 1st's week begins on 31 January for a reader whose week begins on Sunday.
     *
     * @return array
     */
    public static function weeks(): array {
        return [
            'a Wednesday, Sunday first' => ['2026-11-18', 0, '2027-02', '2026-11-15'],
            'a Wednesday, Monday first' => ['2026-11-18', 1, '2027-02', '2026-11-16'],
            'the first day itself' => ['2026-11-15', 0, '2027-02', '2026-11-15'],
            'a Sunday, Monday first' => ['2026-11-15', 1, '2027-02', '2026-11-09'],
            'across a month' => ['2026-11-01', 1, '2027-02', '2026-10-26'],
            'across a year' => ['2027-01-01', 0, '2027-02', '2026-12-27'],
            'a Saturday first' => ['2026-11-18', 6, '2027-02', '2026-11-14'],
            'a date without its zeros' => ['2026-11-7', 0, '2027-02', '2027-01-31'],
            'a day that does not exist' => ['2026-02-30', 0, '2027-02', '2027-01-31'],
            'a year before the month\'s bounds' => ['1899-12-31', 0, '2027-02', '2027-01-31'],
            'a year after them' => ['3000-01-01', 0, '2027-02', '2027-01-31'],
            'not a day' => ['next', 0, '2027-02', '2027-01-31'],
            'a day with a trailing newline' => ["2026-11-18\n", 0, '2027-02', '2027-01-31'],
            'nothing, Monday first' => ['', 1, '2027-02', '2027-02-01'],
        ];
    }

    /**
     * A real day names the week holding it, written as its first day on the viewer's first weekday; anything else is
     * the week holding the month's opening day.
     *
     * @param string $week The week the address names.
     * @param int $firstday The first weekday.
     * @param string $month The month the address names.
     * @param string $start The week's first day the page keeps.
     * @return void
     */
    #[DataProvider('weeks')]
    public function test_the_week_is_snapped_to_its_first_day(string $week, int $firstday, string $month, string $start): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $_GET = [page::PARAM_WEEK => $week];
        $this->assertSame($start, page::week_from_request($month, $firstday));
        $this->assertSame([$start, (new \DateTimeImmutable($start))->modify('+6 days')->format('Y-m-d')], page::week($start));
    }

    /**
     * The view is the week for exactly `week`, the month for anything else, a list included: dropped, never refused.
     *
     * @return void
     */
    public function test_the_view_is_dropped_outside_its_vocabulary(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $_GET = [page::PARAM_VIEW => 'week'];
        $this->assertSame(page::VIEW_WEEK, page::view_from_request(), 'The control: the week is read.');
        foreach (['month', 'Week', 'weeks', 'day', '', '1'] as $other) {
            $_GET = [page::PARAM_VIEW => $other];
            $this->assertSame(page::VIEW_MONTH, page::view_from_request(), $other);
        }
        $_GET = [];
        $this->assertSame(page::VIEW_MONTH, page::view_from_request());
    }

    /**
     * A list where one value is expected is dropped, never an exception: optional_param() would throw, and the public
     * calendar would answer a visitor with an error page.
     *
     * @return void
     */
    public function test_a_list_for_the_view_or_the_week_is_dropped(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $_GET = [page::PARAM_VIEW => ['week'], page::PARAM_WEEK => ['2026-11-18'], 'month' => '2027-02'];
        $this->assertSame(page::VIEW_MONTH, page::view_from_request());
        $this->assertSame('2027-01-31', page::week_from_request('2027-02', 0));
        $this->assertSame(['month' => '2027-02'], page::forwarded_params());
        $_GET = [page::PARAM_VIEW => 'week', page::PARAM_WEEK => '2026-11-18'];
        $this->assertSame('2026-11-15', page::week_from_request('2027-02', 0), 'The control: one value is read.');
    }

    /**
     * A list for the month or the open day is dropped too, in the readers and in the script's forward: optional_param()
     * would throw, and the public calendar would answer a visitor with an error page.
     *
     * @return void
     */
    public function test_a_list_for_the_month_or_the_day_is_dropped(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $_GET = ['month' => '2027-02', page::PARAM_DAY => '2027-02-10'];
        $this->assertSame('2027-02', page::month_from_request(), 'The control: one value is read.');
        $this->assertSame('2027-02-10', page::day_from_request('2027-02', 0));
        $this->assertSame(['month' => '2027-02', page::PARAM_DAY => '2027-02-10'], page::forwarded_params());
        $_GET = ['month' => ['2027-02'], page::PARAM_DAY => ['2027-02-10']];
        $this->assertSame(page::today()->format('Y-m'), page::month_from_request());
        $this->assertSame('', page::day_from_request('2027-02', 0));
        $this->assertSame([], page::forwarded_params());
    }

    /**
     * A list for the page's address reaches an account's page as the bare month, never an error.
     *
     * @return void
     */
    public function test_an_account_is_served_the_page_for_a_list_in_the_address(): void {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $this->resetAfterTest();
        $CFG->routerconfigured = false;
        $this->as_viewer(self::getDataGenerator()->create_user());
        $_GET = [
            'month' => ['2027-02'],
            page::PARAM_DAY => ['2027-02-10'],
            page::PARAM_VIEW => ['week'],
            page::PARAM_WEEK => ['2027-02-10'],
            'city' => ['Natal'],
            'categoryid' => ['3'],
        ];
        $GLOBALS['PAGE'] = new \moodle_page();
        $props = page::handle()->page->props();
        $this->assertSame(
            [page::VIEW_MONTH, page::today()->format('Y-m'), ''],
            [$props['view'], $props['month'], $props['day']]
        );

        self::forget_calendar();
        $_GET = ['month' => '2027-02', page::PARAM_DAY => '2027-02-10'];
        $_GET += [page::PARAM_VIEW => 'week', page::PARAM_WEEK => '2027-02-10'];
        $GLOBALS['PAGE'] = new \moodle_page();
        $props = page::handle()->page->props();
        $this->assertSame(
            [page::VIEW_WEEK, '2027-02', '2027-02-10'],
            [$props['view'], $props['month'], $props['day']],
            'The control.'
        );
    }

    /**
     * What the request shows in each view: the window is the week's seven days or the month's grid, the open day kept
     * inside it alone, and the week view's month the open day's, else the week's first day's.
     *
     * @return void
     */
    public function test_the_span_of_each_view(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $_GET = [page::PARAM_VIEW => 'week', page::PARAM_WEEK => '2026-12-02', page::PARAM_DAY => '2026-12-02'];
        $this->assertSame([page::VIEW_WEEK, '2026-12', '2026-11-29', '2026-12-02', '2026-11-29', '2026-12-05'], page::span(0));
        $_GET = [page::PARAM_VIEW => 'week', page::PARAM_WEEK => '2026-12-02'];
        $this->assertSame([page::VIEW_WEEK, '2026-11', '2026-11-29', '', '2026-11-29', '2026-12-05'], page::span(0));
        $_GET = ['month' => '2027-05', page::PARAM_VIEW => 'week', page::PARAM_WEEK => '2026-12-02'];
        $_GET[page::PARAM_DAY] = '2026-12-04';
        $this->assertSame(
            [page::VIEW_WEEK, '2026-12', '2026-11-29', '2026-12-04', '2026-11-29', '2026-12-05'],
            page::span(0),
            'The week decides the window and the month, whatever month the address names.'
        );
        $_GET = ['month' => '2026-11', page::PARAM_WEEK => '2026-12-02', page::PARAM_DAY => '2026-11-17'];
        $this->assertSame(
            [page::VIEW_MONTH, '2026-11', '', '2026-11-17', '2026-11-01', '2026-12-05'],
            page::span(0),
            'Without the view the week is ignored: the month\'s grid.'
        );
        window::from_iso('2026-11-29', '2026-12-05', new \DateTimeZone('UTC'));
    }

    /**
     * Open days in the week view, for the week of 15 November 2026 from Sunday, with their month-view control.
     *
     * @return array
     */
    public static function week_days(): array {
        return [
            'the week\'s first day' => ['2026-11-15', '2026-11-15'],
            'its last day' => ['2026-11-21', '2026-11-21'],
            'the day after it' => ['2026-11-22', ''],
            'the day before it' => ['2026-11-14', ''],
            'a day that does not exist' => ['2026-11-31', ''],
        ];
    }

    /**
     * The open day is kept inside the week alone in the week view; the same day may be inside the month's grid.
     *
     * @param string $day The day the address names.
     * @param string $kept What the week view keeps.
     * @return void
     */
    #[DataProvider('week_days')]
    public function test_the_open_day_is_kept_inside_the_week_only(string $day, string $kept): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $_GET = [page::PARAM_DAY => $day];
        $this->assertSame($kept, page::day_from_request('2026-11', 0, '2026-11-15'));
        if ($day === '2026-11-22' || $day === '2026-11-14') {
            $this->assertSame('', page::day_from_request('2026-12', 0, '2026-11-15'), 'The week decides, not the month.');
        }
        if ($day === '2026-11-22') {
            $this->assertSame($day, page::day_from_request('2026-11', 0), 'The control: the month\'s grid holds it.');
        }
    }

    /**
     * The view and the week are navigation, never state: an address naming only them and the day is still bare.
     *
     * @return void
     */
    public function test_the_view_and_the_week_are_not_state(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $this->assertNotContains(page::PARAM_VIEW, state::STATE_PARAMS);
        $this->assertNotContains(page::PARAM_WEEK, state::STATE_PARAMS);
        $_GET = [page::PARAM_VIEW => 'week', page::PARAM_WEEK => '2026-11-15', page::PARAM_DAY => '2026-11-17'];
        $this->assertNull(state::from_request());
        $_GET[state::PARAM_CITY] = 'Natal';
        $this->assertNotNull(state::from_request(), 'The control: a state parameter names the state.');
    }

    /**
     * The script's forward carries the week view and a real week as written, without a lookup; the page snaps the
     * week; another view and a malformed week are dropped.
     *
     * @return void
     */
    public function test_the_forward_carries_the_view_and_the_week(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $_GET = [page::PARAM_VIEW => 'week', page::PARAM_WEEK => '2026-11-18', page::PARAM_DAY => '2026-11-17'];
        $before = (int) $DB->perf_get_reads();
        $params = page::forwarded_params();
        $this->assertSame(0, (int) $DB->perf_get_reads() - $before);
        $this->assertSame(
            [page::PARAM_DAY => '2026-11-17', page::PARAM_VIEW => 'week', page::PARAM_WEEK => '2026-11-18'],
            $params,
            'Forwarded as written, not snapped.'
        );

        $_GET = [page::PARAM_VIEW => 'month', page::PARAM_WEEK => '2026-11-31'];
        $this->assertSame([], page::forwarded_params());
    }

    /**
     * The page's own address for each view: the month alone, or the view and the week, the open day after them.
     *
     * @return void
     */
    public function test_the_address_of_each_view(): void {
        $this->assertSame(
            [page::PARAM_VIEW => 'week', page::PARAM_WEEK => '2026-11-15', page::PARAM_DAY => '2026-11-17'],
            page::span_params(page::VIEW_WEEK, '2026-11', '2026-11-15', '2026-11-17')
        );
        $this->assertSame(['month' => '2026-11'], page::span_params(page::VIEW_MONTH, '2026-11', '', ''));
    }

    /**
     * The week view's first paint embeds range::answer() for the week's seven days, and its address keeps the view and
     * the week (a reload shows the same week); the month view's control embeds the month's grid.
     *
     * @return void
     */
    public function test_the_week_views_first_paint_is_the_ranges_answer_for_the_week(): void {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $this->resetAfterTest();
        $CFG->routerconfigured = false;
        $generator = self::getDataGenerator();
        $student = $generator->create_user();
        $course = $generator->create_course();
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->setAdminUser();
        $start = self::month_start();
        foreach (['Inside the week' => 1, 'The next week' => 8] as $name => $offset) {
            $generator->create_event([
                'name' => $name,
                'eventtype' => 'course',
                'courseid' => $course->id,
                'timestart' => $start->modify('+' . $offset . ' days 09:00')->getTimestamp(),
                'timeduration' => HOURSECS,
            ]);
        }
        $this->as_viewer($student);
        $firstday = (int) calendar_get_starting_weekday();
        $asked = $start->modify('+1 day')->format('Y-m-d');
        $week = page::week_start($asked, $firstday);
        [$from, $to] = page::week($week);

        $_GET = [page::PARAM_VIEW => 'week', page::PARAM_WEEK => $asked];
        $GLOBALS['PAGE'] = new \moodle_page();
        $answer = page::handle();
        $this->assertNull($answer->redirect);
        $props = $answer->page->props();
        $this->assertSame([page::VIEW_WEEK, $week], [$props['view'], $props['week']]);
        $this->assertSame([$from, $to], [$props['answer']['from'], $props['answer']['to']]);
        $this->assertSame(['Inside the week'], array_column($props['answer']['events'], 'name'));
        $url = $GLOBALS['PAGE']->url;
        $this->assertSame([page::VIEW_WEEK, $week, null], [$url->param('view'), $url->param('week'), $url->param('month')]);
        self::forget_calendar();
        $state = new state();
        $this->assertSame(
            range::answer(window::from_iso($from, $to), scope::for_viewer(), $state->on(), $state->filters()),
            $props['answer'],
            'The answer the service would give for the week.'
        );

        self::forget_calendar();
        $_GET = ['month' => $start->format('Y-m')];
        $GLOBALS['PAGE'] = new \moodle_page();
        $props = page::handle()->page->props();
        $this->assertSame([page::VIEW_MONTH, ''], [$props['view'], $props['week']], 'The control: the month view.');
        $this->assertSame(page::grid($start->format('Y-m'), $firstday)[0], $props['answer']['from']);
        $this->assertContains('The next week', array_column($props['answer']['events'], 'name'));
    }
}
