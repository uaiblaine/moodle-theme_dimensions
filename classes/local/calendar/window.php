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
 * Theme Dimensions - The days one calendar range answers for.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

/**
 * The window of one range call: two ISO days, both inclusive, at most {@see MAX_DAYS} of them, in the viewer's timezone.
 *
 * A month grid with its neighbours' days is six weeks, so one window is one grid and one call. The days are
 * the viewer's own ({@see \core_date::get_user_timezone_object()}), as core's month view computes its bounds:
 * the first instant is midnight of `from`, the last is one second before midnight after `to`, the
 * "-1 second" of calendar_get_view(). Days are walked with DateTimeImmutable::modify('+1 day'), never by
 * adding 86,400 seconds, so a daylight-saving change inside the window moves no boundary.
 *
 * Only the Gregorian calendar type is answered ({@see require_gregorian()}): the ISO days on the wire and
 * the client's grid are Gregorian, and a viewer on another calendar type is refused, so the page that
 * mounts the grid must check the type first and leave such a viewer on core's calendar.
 *
 * The parse is a question about the shape of the request and says nothing about the site, so it may run
 * before login; it refuses with the same {@see refusal} as every other miss.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class window {
    /** @var int The most days one window may hold: six weeks, one month grid. */
    public const MAX_DAYS = 42;

    /** @var string The first day, YYYY-MM-DD. */
    public readonly string $from;

    /** @var string The last day, YYYY-MM-DD, inclusive. */
    public readonly string $to;

    /** @var int Midnight of the first day in the viewer's timezone. */
    public readonly int $tstart;

    /** @var int The last second of the last day in the viewer's timezone. */
    public readonly int $tend;

    /** @var string[] Every day of the window, YYYY-MM-DD, in order. */
    public readonly array $days;

    /** @var int[] Midnight of every day of the window, and of the day after the last, in order. */
    private array $bounds;

    /**
     * Build a window from its two parsed ends; use {@see from_iso()}.
     *
     * @param \DateTimeImmutable $first Midnight of the first day, in the viewer's timezone.
     * @param int $count The number of days, 1 to MAX_DAYS.
     */
    private function __construct(\DateTimeImmutable $first, int $count) {
        $days = [];
        $bounds = [];
        $day = $first;
        for ($i = 0; $i < $count; $i++) {
            $days[] = $day->format('Y-m-d');
            $bounds[] = $day->getTimestamp();
            $day = $day->modify('+1 day');
        }
        $bounds[] = $day->getTimestamp();
        $this->days = $days;
        $this->bounds = $bounds;
        $this->from = $days[0];
        $this->to = $days[$count - 1];
        $this->tstart = $bounds[0];
        $this->tend = $bounds[$count] - 1;
    }

    /**
     * Parse a window, refusing anything malformed, reversed or longer than MAX_DAYS.
     *
     * @param string $from The first day, YYYY-MM-DD.
     * @param string $to The last day, YYYY-MM-DD, inclusive.
     * @param \DateTimeZone|null $timezone The timezone the days are in; the current user's when null.
     * @return self
     * @throws refusal On any malformed, reversed or oversized window.
     */
    public static function from_iso(string $from, string $to, ?\DateTimeZone $timezone = null): self {
        $timezone ??= \core_date::get_user_timezone_object();
        $first = self::midnight($from, $timezone);
        $last = self::midnight($to, $timezone);
        if ($last < $first) {
            throw new refusal();
        }
        // Calendar days counted in UTC, never in seconds: a daylight-saving change makes a local day 23 or 25 hours long.
        $utc = new \DateTimeZone('UTC');
        $count = (int) self::midnight($from, $utc)->diff(self::midnight($to, $utc))->days + 1;
        if ($count > self::MAX_DAYS) {
            throw new refusal();
        }
        return new self($first, $count);
    }

    /**
     * Refuse a viewer whose calendar type is not the Gregorian one.
     *
     * A deleted calendar type falls back to the Gregorian one in core ({@see \core_calendar\type_factory}),
     * and is answered like it.
     *
     * @return void
     * @throws refusal For any other calendar type.
     */
    public static function require_gregorian(): void {
        if (!(\core_calendar\type_factory::get_calendar_instance() instanceof \calendartype_gregorian\structure)) {
            throw new refusal();
        }
    }

    /**
     * The index of the window's day an instant falls on, clamped to the window.
     *
     * @param int $timestamp The instant.
     * @return int 0 for the first day (or anything before it) up to count - 1 (or anything after the last).
     */
    public function day_index(int $timestamp): int {
        $low = 0;
        $high = count($this->days) - 1;
        if ($timestamp < $this->bounds[0]) {
            return 0;
        }
        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);
            if ($this->bounds[$mid] <= $timestamp) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }
        return $low;
    }

    /**
     * Midnight of one ISO day in a timezone, refusing anything but a real YYYY-MM-DD date.
     *
     * @param string $iso The day.
     * @param \DateTimeZone $timezone The timezone.
     * @return \DateTimeImmutable
     * @throws refusal On a malformed or impossible date.
     */
    private static function midnight(string $iso, \DateTimeZone $timezone): \DateTimeImmutable {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $parts)) {
            throw new refusal();
        }
        if (!checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new refusal();
        }
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso, $timezone);
        if ($day === false) {
            throw new refusal();
        }
        return $day;
    }
}
