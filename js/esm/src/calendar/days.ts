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
 * The calendar page's days: the month grid, and the day and the time an instant falls on for the viewer.
 *
 * The grid is the showcase's timezone-free date core (daterange/calendar.ts, imported, never copied): weeks() lays the
 * month out on the site's first weekday, and the blanks it leaves are filled with the neighbouring months' days, the
 * same whole weeks local\calendar\page::grid() asks the range for. The browser's clock and its own timezone are never
 * read: today arrives from the server, and an instant is read in the VIEWER's timezone, which the server names, through
 * Intl's own zone data (a formatter, no arithmetic of ours); a zone Intl does not know reads as UTC.
 *
 * @module     theme_dimensions/calendar/days
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {WEEK, format, shift, weeks} from '../daterange/calendar';
import type {Month} from '../daterange/calendar';

/**
 * A month string as the {y, m} the date core takes.
 *
 * @param {string} month The month, YYYY-MM, known to be one.
 * @returns {object} The month.
 */
export const monthParts = (month: string): Month => ({y: Number(month.slice(0, 4)), m: Number(month.slice(5, 7))});

/**
 * The month a whole number of months after another one.
 *
 * @param {string} month The month, YYYY-MM.
 * @param {number} offset Whole months, negative to go back.
 * @returns {string} The month, YYYY-MM.
 */
export const monthAfter = (month: string, offset: number): string => {
    const parts = monthParts(month);
    const index = parts.y * 12 + (parts.m - 1) + offset;
    return format({y: Math.floor(index / 12), m: (index % 12) + 1, d: 1}).slice(0, 7);
};

/**
 * The month grid: whole weeks from the one holding the 1st to the one holding the last day, every slot a day.
 *
 * @param {string} month The month, YYYY-MM.
 * @param {number} firstday The weekday a week begins on, 0 for Sunday.
 * @returns {Array} The weeks, each seven days as YYYY-MM-DD.
 */
export const gridWeeks = (month: string, firstday: number): string[][] => {
    const rows = weeks(monthParts(month), firstday);
    const first = `${month}-01`;
    const lead = rows[0].indexOf(first);
    const start = shift(first, -lead);
    return rows.map((row, index) => Array.from({length: WEEK}, (unused, slot) => shift(start, index * WEEK + slot)));
};

/** What reads an instant in the viewer's timezone. */
export type Zone = {
    day: (timestamp: number) => string,
    time: (timestamp: number) => string,
};

/**
 * A formatter for a timezone, the zone falling back to UTC when Intl does not know it.
 *
 * @param {string|undefined} language The language tag.
 * @param {object} options The Intl options, without the zone.
 * @param {string} timezone The zone.
 * @returns {object} The formatter.
 */
const formatterIn = (language: string | undefined, options: Intl.DateTimeFormatOptions, timezone: string): Intl.DateTimeFormat => {
    try {
        return new Intl.DateTimeFormat(language, {...options, timeZone: timezone});
    } catch (error) {
        return new Intl.DateTimeFormat(language, {...options, timeZone: 'UTC'});
    }
};

/**
 * The reader of instants in one timezone: the day as YYYY-MM-DD and the time as the language writes it.
 *
 * @param {string} timezone The viewer's timezone, as the server names it.
 * @param {string|undefined} language The language tag, for the time.
 * @returns {object} day() and time().
 */
export const zone = (timezone: string, language: string | undefined): Zone => {
    const days = formatterIn('en-CA', {year: 'numeric', month: '2-digit', day: '2-digit'}, timezone);
    const times = formatterIn(language, {hour: '2-digit', minute: '2-digit'}, timezone);
    const memo = new Map<number, string>();
    return {
        /**
         * The day an instant falls on in the zone.
         *
         * @param {number} timestamp The instant, in seconds.
         * @returns {string} YYYY-MM-DD.
         */
        day: (timestamp: number): string => {
            const second = Math.floor(timestamp);
            let made = memo.get(second);
            if (made === undefined) {
                const parts = Object.fromEntries(days.formatToParts(new Date(second * 1000))
                    .map((part) => [part.type, part.value]));
                made = `${parts.year}-${parts.month}-${parts.day}`;
                memo.set(second, made);
            }
            return made;
        },
        /**
         * The time of an instant in the zone, as the language writes it.
         *
         * @param {number} timestamp The instant, in seconds.
         * @returns {string} The time.
         */
        time: (timestamp: number): string => times.format(new Date(timestamp * 1000)),
    };
};
