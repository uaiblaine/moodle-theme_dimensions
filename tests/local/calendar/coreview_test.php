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
 * Theme Dimensions - Tests for the decision that sends core's month and day views to the calendar page
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
    /**
     * Forget the request parameters and the router a test built.
     *
     * @return void
     */
    protected function tearDown(): void {
        $_GET = [];
        \core\di::reset_container();
        parent::tearDown();
    }

    /** @var int The account every decision makes unless it names another. */
    private const ACCOUNT = 7;

    /**
     * Decide a request the way the callback does, with every input a real, Gregorian, setting-on reader unless given.
     *
     * @param array $request The request's parameters.
     * @param array $with Overrides: enabled, account (0 for a visitor or the guest), gregorian, session (a stdClass),
     *     timezone, now.
     * @return \core\url|null
     */
    private function decide(array $request, array $with = []): ?\core\url {
        return coreview::target(
            $request,
            $with['enabled'] ?? true,
            $with['account'] ?? self::ACCOUNT,
            $with['gregorian'] ?? true,
            $with['session'] ?? new \stdClass(),
            new \DateTimeZone($with['timezone'] ?? 'UTC'),
            $with['now'] ?? 1800000000
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
     * The views that stay on core's page, each beside the same request sent once the one reason is gone.
     *
     * @return array
     */
    public static function kept_on_core(): array {
        $month = ['view' => 'month', 'cal_d' => 1, 'cal_m' => 3, 'cal_y' => 2027];
        return [
            'upcoming' => [['view' => 'upcoming'] + $month, [], ['view' => 'month'] + $month],
            'no view at all' => [['cal_d' => 1, 'cal_m' => 3, 'cal_y' => 2027], [], $month],
            'the mini upcoming view' => [['view' => 'upcoming_mini'] + $month, [], $month],
            'a course calendar' => [$month + ['course' => (int) SITEID + 1], [], $month + ['course' => (int) SITEID]],
            'an explicit course 0, which core refuses' => [$month + ['course' => 0], [], $month],
            'a visitor or the guest' => [$month, ['account' => 0], $month],
            'another calendar type' => [$month, ['gregorian' => false], $month],
            'the setting off' => [$month, ['enabled' => false], $month],
            'classic for this session' => [$month, ['session' => (object) [coreview::SESSION_FLAG => self::ACCOUNT]], $month],
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
     * The classic marker sets the session flag and is never sent; the flag keeps the next requests on core's page.
     *
     * @return void
     */
    public function test_the_classic_marker_sets_the_flag_and_is_not_sent(): void {
        $this->resetAfterTest();
        $session = new \stdClass();
        $request = ['view' => 'month', 'cal_d' => 1, 'cal_m' => 3, 'cal_y' => 2027];

        $this->assertNull($this->decide($request + ['classic' => 1], ['session' => $session]));
        $this->assertSame(self::ACCOUNT, $session->{coreview::SESSION_FLAG});
        $this->assertNull($this->decide($request, ['session' => $session]), 'The arrows and the reload stay on core.');
        $this->assertNotNull($this->decide($request), 'Without the marker, and without the flag, it is sent.');
    }

    /**
     * The flag is the account's: a visitor's marker writes none, and a flag another account wrote keeps no one on core.
     *
     * The session survives a login, so a flag set before it must not decide for the account that logs in.
     *
     * @return void
     */
    public function test_the_flag_belongs_to_the_account_that_set_it(): void {
        $this->resetAfterTest();
        $request = ['view' => 'month', 'cal_d' => 1, 'cal_m' => 3, 'cal_y' => 2027];

        $session = new \stdClass();
        $this->assertNull($this->decide($request + ['classic' => 1], ['account' => 0, 'session' => $session]));
        $this->assertObjectNotHasProperty(coreview::SESSION_FLAG, $session, 'A visitor\'s marker writes no flag.');

        $session = (object) [coreview::SESSION_FLAG => self::ACCOUNT + 1];
        $this->assertNotNull($this->decide($request, ['session' => $session]), 'Another account\'s flag does not count.');
        $session = (object) [coreview::SESSION_FLAG => self::ACCOUNT];
        $this->assertNull($this->decide($request, ['session' => $session]), 'This account\'s flag does.');
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
     * Every address the page spells for core's calendar carries the marker, and is never sent (the loop guard).
     *
     * @return void
     */
    public function test_the_pages_own_classic_address_is_never_sent(): void {
        $this->resetAfterTest();
        $session = new \stdClass();
        $params = page::classic('2027-03')->params();
        $this->assertSame('1', (string) $params['classic']);

        $this->assertNull($this->decide($params, ['session' => $session]));
        $this->assertSame(self::ACCOUNT, $session->{coreview::SESSION_FLAG});
        unset($params['classic']);
        $this->assertNotNull($this->decide($params), 'Without the marker the same address is sent.');
    }

    /**
     * The page clears the flag once the reader is a real account on it; the guest and the visitor never reach that line.
     *
     * @return void
     */
    public function test_the_page_clears_the_flag(): void {
        global $SESSION;
        $this->resetAfterTest();

        $this->setGuestUser();
        $SESSION->{coreview::SESSION_FLAG} = self::ACCOUNT;
        page::handle();
        $this->assertSame(
            self::ACCOUNT,
            $SESSION->{coreview::SESSION_FLAG},
            'The guest is sent to core\'s calendar, which keeps the choice.'
        );

        // Set after setUser(), which starts an empty session: the flag must be there when the page opens.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $SESSION->{coreview::SESSION_FLAG} = (int) $user->id;
        $this->assertNotNull(page::handle()->page);
        $this->assertObjectNotHasProperty(coreview::SESSION_FLAG, $SESSION);
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
            'classic' => '1',
        ];
        $params = coreview::request_params();
        $this->assertSame('day', $params['view']);
        $this->assertSame(9, $params['cal_d']);
        $this->assertSame(5, $params['category']);
        $this->assertSame(1, $params['classic']);
        $this->assertSame(0, $params['time']);
        unset($_GET['course']);
        $this->assertSame((int) SITEID, coreview::request_params()['course'], 'An absent course is the site, as in core.');
    }
}
