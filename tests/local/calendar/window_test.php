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
 * Theme Dimensions - The calendar range's window
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Two ISO days, inclusive, at most six weeks, in a timezone; anything else is the one refusal.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(window::class)]
final class window_test extends \basic_testcase {
    /**
     * A six-week window is answered, its bounds midnight to the last second, its days in order.
     *
     * @return void
     */
    public function test_a_six_week_window(): void {
        $zone = new \DateTimeZone('America/Sao_Paulo');
        $window = window::from_iso('2026-10-26', '2026-12-06', $zone);

        $this->assertCount(window::MAX_DAYS, $window->days);
        $this->assertSame('2026-10-26', $window->days[0]);
        $this->assertSame('2026-12-06', $window->days[41]);
        $this->assertSame((new \DateTimeImmutable('2026-10-26 00:00', $zone))->getTimestamp(), $window->tstart);
        $this->assertSame((new \DateTimeImmutable('2026-12-07 00:00', $zone))->getTimestamp() - 1, $window->tend);
        // One day is a window too.
        $this->assertSame(['2026-11-01'], window::from_iso('2026-11-01', '2026-11-01', $zone)->days);
    }

    /**
     * Across a daylight-saving change the days stay calendar days: no boundary moves by an hour.
     *
     * @return void
     */
    public function test_days_are_calendar_days_across_a_clock_change(): void {
        $zone = new \DateTimeZone('Europe/London');
        $window = window::from_iso('2026-10-20', '2026-11-05', $zone);

        $this->assertCount(17, $window->days);
        $this->assertSame(5, $window->day_index((new \DateTimeImmutable('2026-10-25 00:00', $zone))->getTimestamp()));
        $this->assertSame(5, $window->day_index((new \DateTimeImmutable('2026-10-25 23:59:59', $zone))->getTimestamp()));
        $this->assertSame(6, $window->day_index((new \DateTimeImmutable('2026-10-26 00:00', $zone))->getTimestamp()));
        $this->assertSame((new \DateTimeImmutable('2026-11-06 00:00', $zone))->getTimestamp() - 1, $window->tend);
        // Clamped to the window on both sides.
        $this->assertSame(0, $window->day_index($window->tstart - 3600));
        $this->assertSame(16, $window->day_index($window->tend + 3600));
    }

    /**
     * Requests the window refuses.
     *
     * @return array
     */
    public static function refused(): array {
        return [
            'forty-three days' => ['2026-10-26', '2026-12-07'],
            'reversed' => ['2026-11-02', '2026-11-01'],
            'no padding' => ['2026-11-1', '2026-11-02'],
            'impossible date' => ['2026-02-30', '2026-03-02'],
            'a time' => ['2026-11-01 00:00', '2026-11-02'],
            'words' => ['today', 'tomorrow'],
            'a trailing newline' => ["2026-11-01\n", '2026-11-02'],
            'empty' => ['', ''],
        ];
    }

    /**
     * Every malformed, reversed or oversized window is the one refusal.
     *
     * @param string $from The first day.
     * @param string $to The last day.
     * @return void
     */
    #[DataProvider('refused')]
    public function test_refused(string $from, string $to): void {
        $this->expectException(refusal::class);
        window::from_iso($from, $to, new \DateTimeZone('UTC'));
    }
}
