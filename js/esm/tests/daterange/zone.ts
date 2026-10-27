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
 * A reader's timezone west of UTC with daylight saving, for the calendar's tests.
 *
 * Jest hands a test a copy of process.env, so setting TZ there never reaches Node's clock, and the
 * runners are in UTC, where a local-time accessor reads exactly what its UTC twin reads and a test of
 * "no timezone arithmetic" passes against code full of it. So the local-time half of Date is replaced
 * for the length of a suite with New York's 2026 rules: five hours west, four between the transitions
 * of 2026-03-08 07:00 UTC and 2026-11-01 06:00 UTC. A local-time reading of a calendar square built at
 * midnight UTC is then the day before, and a local-time day added across a transition is not 24 hours.
 * Intl's formatter reads the process zone too, so it is given New York wherever its caller names none.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** An hour in milliseconds. */
const HOUR = 3600000;

/** The local-time methods replaced. */
const METHODS = ['getFullYear', 'getMonth', 'getDate', 'getDay', 'getHours', 'setDate', 'setMonth'] as const;

/**
 * New York's offset from UTC at an instant of 2026, in milliseconds.
 *
 * @param {number} time The instant.
 * @returns {number} The offset, negative.
 */
const offset = (time: number): number => (time >= Date.UTC(2026, 2, 8, 7) && time < Date.UTC(2026, 10, 1, 6) ? -4 : -5) * HOUR;

/**
 * The wall clock of an instant, as a Date whose UTC fields read it.
 *
 * @param {Date} date The instant.
 * @returns {Date} The wall clock.
 */
const wall = (date: Date): Date => new Date(date.getTime() + offset(date.getTime()));

/**
 * Write a wall clock back as the instant it names.
 *
 * @param {Date} date The Date to set.
 * @param {Date} clock The wall clock.
 * @returns {number} The new time.
 */
const back = (date: Date, clock: Date): number => {
    const guess = clock.getTime() - offset(clock.getTime());
    date.setTime(clock.getTime() - offset(guess));
    return date.getTime();
};

/**
 * Put Date's local-time methods in New York for a while.
 *
 * @returns {Function} Puts them back.
 */
export const westOfUtc = (): (() => void) => {
    const proto = Date.prototype as unknown as Record<string, unknown>;
    const saved = Object.fromEntries(METHODS.map((name) => [name, proto[name]]));
    proto.getFullYear = function(this: Date): number {
        return wall(this).getUTCFullYear();
    };
    proto.getMonth = function(this: Date): number {
        return wall(this).getUTCMonth();
    };
    proto.getDate = function(this: Date): number {
        return wall(this).getUTCDate();
    };
    proto.getDay = function(this: Date): number {
        return wall(this).getUTCDay();
    };
    proto.getHours = function(this: Date): number {
        return wall(this).getUTCHours();
    };
    proto.setDate = function(this: Date, day: number): number {
        const clock = wall(this);
        clock.setUTCDate(day);
        return back(this, clock);
    };
    proto.setMonth = function(this: Date, month: number): number {
        const clock = wall(this);
        clock.setUTCMonth(month);
        return back(this, clock);
    };
    const Format = Intl.DateTimeFormat;
    const zoned = function(locales?: string | string[], options?: Intl.DateTimeFormatOptions): Intl.DateTimeFormat {
        return new Format(locales, {timeZone: 'America/New_York', ...options});
    };
    zoned.prototype = Format.prototype;
    zoned.supportedLocalesOf = Format.supportedLocalesOf;
    Intl.DateTimeFormat = zoned as unknown as typeof Intl.DateTimeFormat;
    return () => {
        Object.assign(proto, saved);
        Intl.DateTimeFormat = Format;
    };
};
