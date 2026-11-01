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
        foreach (['2027-13', '27-02', '2027-2', 'x', '0999-01'] as $bad) {
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
     * Core's month and day views are spelled with core's day, month and year, never a timestamp.
     *
     * @return void
     */
    public function test_the_classic_calendar_address(): void {
        $this->resetAfterTest();
        $url = page::classic('2026-02');
        $this->assertSame(['view' => 'month', 'cal_d' => '1', 'cal_m' => '2', 'cal_y' => '2026'], $url->params());
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
}
