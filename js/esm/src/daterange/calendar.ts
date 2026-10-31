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
 * The calendar arithmetic of the showcase's period control, with no timezone in it.
 *
 * Every boundary of the period filter is resolved by PHP in the site's timezone; what the client
 * computes is the calendar itself - the weekday of a date, the length of a month, the day either
 * side of one - on the {y, m, d} of an ISO string through Date.UTC. UTC has no offset and no daylight
 * saving, so a day added is a day added whatever the reader's own timezone; a local-time Date would
 * shift a calendar square by that offset. The browser's clock is never read: the day the site calls
 * today arrives from the server.
 *
 * {@see period()} is criteria's reading of a period, the one both shells read an address through (the listing's
 * store and its shells, docs/vitrine-react/R4b.md).
 *
 * @module     theme_dimensions/daterange/calendar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** A calendar day as its three numbers, the month from 1 to 12. */
export type Day = {y: number, m: number, d: number};

/** A calendar month, from 1 to 12. */
export type Month = {y: number, m: number};

/** A period: each bound YYYY-MM-DD or the empty string. */
export type Period = {from: string, to: string};

/** How many day cells a week row holds. */
export const WEEK = 7;

/** A week beginning on a Sunday, which is where the weekday names are read from. */
const NAME_WEEK = '2026-01-04';

/**
 * How many days a month holds.
 *
 * @param {number} y The year.
 * @param {number} m The month, 1 to 12.
 * @returns {number} The number of days.
 */
export const monthLength = (y: number, m: number): number => new Date(Date.UTC(y, m, 0)).getUTCDate();

/**
 * An ISO date as the triple the arithmetic runs on.
 *
 * The shape is the server's (criteria::parse_date()) and so is the refusal of a day the calendar does
 * not have, such as the 31st of September: a value the server would drop must not reach a request it
 * would refuse.
 *
 * @param {string} iso A date as YYYY-MM-DD.
 * @returns {object|null} The {y, m, d} triple, or null when the string is not a date.
 */
export const parse = (iso: unknown): Day | null => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || ''));
    if (!match) {
        return null;
    }
    const parts = {y: Number(match[1]), m: Number(match[2]), d: Number(match[3])};
    if (parts.m < 1 || parts.m > 12 || parts.d < 1 || parts.d > monthLength(parts.y, parts.m)) {
        return null;
    }
    return parts;
};

/**
 * The triple back as an ISO date.
 *
 * @param {object} parts The {y, m, d} triple.
 * @returns {string} The date as YYYY-MM-DD.
 */
export const format = (parts: Day): string => String(parts.y).padStart(4, '0')
    + '-' + String(parts.m).padStart(2, '0')
    + '-' + String(parts.d).padStart(2, '0');

/**
 * Midnight UTC of a calendar day, the only Date this module builds for a day.
 *
 * @param {object} parts The {y, m, d} triple.
 * @returns {Date} Midnight UTC of that day.
 */
export const utc = (parts: Day): Date => new Date(Date.UTC(parts.y, parts.m - 1, parts.d));

/**
 * The calendar day a whole number of days after another one.
 *
 * @param {string} iso A date as YYYY-MM-DD.
 * @param {number} days Whole days to add, negative to go back.
 * @returns {string} The date as YYYY-MM-DD, or the empty string when the input was not a date.
 */
export const shift = (iso: string, days: number): string => {
    const parts = parse(iso);
    if (!parts) {
        return '';
    }
    const moved = utc(parts);
    moved.setUTCDate(moved.getUTCDate() + days);
    return format({y: moved.getUTCFullYear(), m: moved.getUTCMonth() + 1, d: moved.getUTCDate()});
};

/**
 * The calendar day a whole number of months after another one, clamped to the month's length.
 *
 * The clamp makes "one month after the 31st of January" the 28th or 29th of February rather than
 * the 2nd or 3rd of March, which is where a bare setUTCMonth() rolls it to.
 *
 * @param {string} iso A date as YYYY-MM-DD.
 * @param {number} months Whole months to add, negative to go back.
 * @returns {string} The date as YYYY-MM-DD, or the empty string when the input was not a date.
 */
export const shiftMonth = (iso: string, months: number): string => {
    const parts = parse(iso);
    if (!parts) {
        return '';
    }
    const moved = utc({y: parts.y, m: parts.m, d: 1});
    moved.setUTCMonth(moved.getUTCMonth() + months);
    const y = moved.getUTCFullYear();
    const m = moved.getUTCMonth() + 1;
    return format({y: y, m: m, d: Math.min(parts.d, monthLength(y, m))});
};

/**
 * A period as the server keeps it: each bound a real day or nothing, and the two in order.
 *
 * criteria's rule written in the client: a bound that is not a date is dropped and the other stands
 * alone, and a pair given backwards is the same period in order (the web service refuses a pair
 * given backwards).
 *
 * @param {string} start The first day as given, or anything else.
 * @param {string} end The last day as given, or anything else.
 * @returns {object} The {from, to} pair, each YYYY-MM-DD or the empty string.
 */
export const period = (start: unknown, end: unknown): Period => {
    const first = parse(start) ? String(start) : '';
    const last = parse(end) ? String(end) : '';
    if (first !== '' && last !== '' && last < first) {
        return {from: last, to: first};
    }
    return {from: first, to: last};
};

/** The formatters made so far, by language and options: an open month formats about 84 dates a render. */
const FORMATTERS = new Map<string, Intl.DateTimeFormat>();

/**
 * One formatter per language and options, made once: building one costs far more than formatting with it.
 *
 * @param {object} options The Intl options.
 * @param {string|undefined} language The language tag, or undefined for the browser's own default.
 * @returns {object} The formatter, told the zone is UTC.
 */
const formatter = (options: Intl.DateTimeFormatOptions, language: string | undefined): Intl.DateTimeFormat => {
    const key = JSON.stringify([language ?? null, options]);
    let made = FORMATTERS.get(key);
    if (!made) {
        made = new Intl.DateTimeFormat(language, {timeZone: 'UTC', ...options});
        FORMATTERS.set(key, made);
    }
    return made;
};

/**
 * One date, written the way a language writes a date.
 *
 * The formatter is told the zone is UTC because the Date it formats is midnight UTC of the day:
 * any other zone would print the day before or after it.
 *
 * @param {string} iso A date as YYYY-MM-DD.
 * @param {object} options The Intl options.
 * @param {string|undefined} language The language tag, or undefined for the browser's own default.
 * @returns {string} The formatted date, the ISO string when Intl cannot format it, or the empty
 *   string when the input is not a date.
 */
export const say = (iso: string, options: Intl.DateTimeFormatOptions, language: string | undefined): string => {
    const parts = parse(iso);
    if (!parts) {
        return '';
    }
    try {
        return formatter(options, language).format(utc(parts));
    } catch (error) {
        return iso;
    }
};

/**
 * The month a day falls in.
 *
 * @param {string} iso A date as YYYY-MM-DD, known to be one.
 * @returns {object} The month as a plain {y, m}.
 */
export const monthOf = (iso: string): Month => {
    const parts = parse(iso) as Day;
    return {y: parts.y, m: parts.m};
};

/**
 * The first day of a month.
 *
 * @param {object} month The month as a plain {y, m}.
 * @returns {string} The date as YYYY-MM-DD.
 */
export const firstOf = (month: Month): string => format({y: month.y, m: month.m, d: 1});

/**
 * The month some months after another one.
 *
 * @param {object} cursor The month counted from.
 * @param {number} offset Whole months after it, negative to go back.
 * @returns {object} The month as a plain {y, m}.
 */
export const monthAt = (cursor: Month, offset: number): Month => monthOf(shiftMonth(firstOf(cursor), offset));

/**
 * Whether a day falls in one of the months on screen.
 *
 * @param {object} cursor The first month on screen.
 * @param {number} count How many months are on screen, one or two.
 * @param {string} iso A date as YYYY-MM-DD.
 * @returns {boolean}
 */
export const onScreen = (cursor: Month, count: number, iso: string): boolean => {
    const last = monthAt(cursor, count - 1);
    return iso >= firstOf(cursor) && iso <= format({y: last.y, m: last.m, d: monthLength(last.y, last.m)});
};

/**
 * The first month on screen once a day has been brought onto one, moving the months as little as it takes.
 *
 * A day before the months on screen opens them on its month; one after them ends them on it.
 *
 * @param {object} cursor The first month on screen.
 * @param {number} count How many months are on screen, one or two.
 * @param {string} iso A date as YYYY-MM-DD.
 * @returns {object} The first month to show; the cursor itself when the day is already on screen or is no date.
 */
export const bringIntoView = (cursor: Month, count: number, iso: string): Month => {
    if (!parse(iso) || onScreen(cursor, count, iso)) {
        return cursor;
    }
    return iso < firstOf(cursor) ? monthOf(iso) : monthAt(monthOf(iso), 1 - count);
};

/**
 * Seven consecutive days of a real week, beginning on the weekday the site begins its weeks on.
 *
 * The weekday names are the browser's own for the document's language, read off these days rather
 * than from a table of our own, which would be a second spelling of every language pack on the site.
 *
 * @param {number} firstday The weekday a week begins on, 0 for Sunday.
 * @returns {Array} The seven days, as YYYY-MM-DD.
 */
export const weekdays = (firstday: number): string[] =>
    Array.from({length: WEEK}, (unused, index) => shift(NAME_WEEK, (firstday + index) % WEEK));

/**
 * The weeks of one month, each seven slots: a day as YYYY-MM-DD, or the empty string for a blank.
 *
 * The blanks line a week's columns up with the names above them, before the first day and after
 * the last.
 *
 * @param {object} month The month as a plain {y, m}.
 * @param {number} firstday The weekday a week begins on, 0 for Sunday.
 * @returns {Array} The rows, in order.
 */
export const weeks = (month: Month, firstday: number): string[][] => {
    const lead = (utc({y: month.y, m: month.m, d: 1}).getUTCDay() - firstday + WEEK) % WEEK;
    const length = monthLength(month.y, month.m);
    const slots = Math.ceil((lead + length) / WEEK) * WEEK;
    const rows: string[][] = [];
    for (let slot = 0; slot < slots; slot++) {
        if (slot % WEEK === 0) {
            rows.push([]);
        }
        const d = slot - lead + 1;
        rows[rows.length - 1].push(d >= 1 && d <= length ? format({y: month.y, m: month.m, d: d}) : '');
    }
    return rows;
};
