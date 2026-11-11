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
 * What the page may offer to edit, and the instants core's calendar services are handed: pure, no request.
 *
 * Every decision here is core's, read from the answer (`editing`, local\calendar\editing::entries()): an item is
 * edited, deleted or dragged only when core's month view would. What is computed here is the guard of a drop (the
 * limits core gave, as core's month_view_drag_drop checks them before it asks) and the instants core's form and
 * core_calendar_update_event_start_day take, which are timestamps: the day the reader picked, in the viewer's
 * timezone (Intl's zone data, as days.ts reads instants), never the browser's.
 *
 * @module     theme_dimensions/calendar/moves
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Zone} from './days';
import type {Answer, Edit, Item} from './model';

/** The minute of the day a move is sent at: core's service reads only the day, and noon is inside it in every zone. */
export const MOVE_MINUTE = 12 * 60;

/**
 * Core's answers by event id.
 *
 * @param {object} answer The range's answer.
 * @returns {Map} Event id => its entry.
 */
export const editsOf = (answer: Answer): Map<number, Edit> =>
    new Map((answer.editing || []).map((entry) => [entry.id, entry]));

/**
 * Core's answer for an item: only core's own events have one, and only those the viewer may edit.
 *
 * @param {Map} edits The answer's entries by id.
 * @param {object} item The item.
 * @returns {object|null} The entry, or null.
 */
export const editOf = (edits: Map<number, Edit>, item: Item): Edit | null =>
    (item.source === 'core' ? edits.get(item.id) || null : null);

/**
 * Why a drop on a day is refused, as core's month view refuses it before asking: before the earliest day core allows,
 * or after the latest.
 *
 * @param {object} edit The item's entry.
 * @param {string} day The day dropped on, YYYY-MM-DD.
 * @returns {string|null} 'min', 'max', or null when the day is allowed.
 */
export const dropRefusal = (edit: Edit, day: string): 'min' | 'max' | null => {
    if (edit.minday !== '' && day < edit.minday) {
        return 'min';
    }
    if (edit.maxday !== '' && day > edit.maxday) {
        return 'max';
    }
    return null;
};

/**
 * A formatter of wall clocks in a zone, the zone falling back to UTC when Intl does not know it (days.ts' rule).
 *
 * @param {string} timezone The zone.
 * @returns {object} The formatter.
 */
const clockIn = (timezone: string): Intl.DateTimeFormat => {
    const options: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
    };
    try {
        return new Intl.DateTimeFormat('en-GB', {...options, timeZone: timezone});
    } catch (error) {
        return new Intl.DateTimeFormat('en-GB', {...options, timeZone: 'UTC'});
    }
};

/**
 * The wall clock an instant shows in a zone, written as if it were UTC, in seconds.
 *
 * @param {object} clock The zone's formatter.
 * @param {number} at The instant, in seconds.
 * @returns {number}
 */
const wallOf = (clock: Intl.DateTimeFormat, at: number): number => {
    const parts = Object.fromEntries(clock.formatToParts(new Date(at * 1000)).map((part) => [part.type, part.value]));
    return Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day), Number(parts.hour) % 24,
        Number(parts.minute), Number(parts.second)) / 1000;
};

/**
 * The instant a day and a minute of its wall clock name in a zone.
 *
 * The zone's offset is read at a first guess and again at the instant it gives, so a day whose offset changes still
 * lands on its own wall clock; a minute a daylight-saving change skips lands an hour off, inside the same day.
 *
 * @param {string} iso The day, YYYY-MM-DD.
 * @param {number} minute The minute of the day, 0 to 1439.
 * @param {string} timezone The viewer's timezone, as the server names it.
 * @returns {number} The instant, in seconds.
 */
export const instantOf = (iso: string, minute: number, timezone: string): number => {
    const clock = clockIn(timezone);
    const wall = Date.UTC(Number(iso.slice(0, 4)), Number(iso.slice(5, 7)) - 1, Number(iso.slice(8, 10)),
        Math.floor(minute / 60), minute % 60) / 1000;
    const first = wall - (wallOf(clock, wall) - wall);
    return wall - (wallOf(clock, first) - first);
};

/**
 * The start core's form is handed for a new event from the toolbar: none while the span shows today (the form starts
 * now), else the span's first day at the current time of day, as core's crud does with its first day's
 * neweventtimestamp.
 *
 * @param {Array} own The span's own days: the month's, or the week's seven.
 * @param {string} today Today, YYYY-MM-DD.
 * @param {number} now The server's clock, in seconds.
 * @param {object} zone The viewer's zone reader.
 * @param {string} timezone The viewer's timezone.
 * @returns {number|null} The instant, or null.
 */
export const toolbarStart = (own: string[], today: string, now: number, zone: Zone, timezone: string): number | null =>
    (own.includes(today) || !own.length ? null : instantOf(own[0], zone.minutes(now), timezone));

/**
 * The start core's form is handed for a new event on a day: the day at the current time of day, as core's day view
 * hands its neweventtimestamp.
 *
 * @param {string} day The day, YYYY-MM-DD.
 * @param {number} now The server's clock, in seconds.
 * @param {object} zone The viewer's zone reader.
 * @param {string} timezone The viewer's timezone.
 * @returns {number} The instant.
 */
export const dayStart = (day: string, now: number, zone: Zone, timezone: string): number =>
    instantOf(day, zone.minutes(now), timezone);

/**
 * The start core's form is handed for a new event at an hour of a day: the day at that hour of its wall clock in the
 * viewer's timezone, what a double-click on an hour row opens (core's modal_event_form setStartTime()).
 *
 * @param {string} day The day, YYYY-MM-DD.
 * @param {number} hour The hour, 0 to 23.
 * @param {string} timezone The viewer's timezone.
 * @returns {number} The instant.
 */
export const hourStart = (day: string, hour: number, timezone: string): number => instantOf(day, hour * 60, timezone);

/**
 * The start core's form is handed for a new event created on the grid: at the hour when one is named
 * ({@see hourStart()}), else the day at the current time of day ({@see dayStart()}).
 *
 * @param {string} day The day, YYYY-MM-DD.
 * @param {number|null} hour The hour, 0 to 23, or null.
 * @param {number} now The server's clock, in seconds.
 * @param {object} zone The viewer's zone reader.
 * @param {string} timezone The viewer's timezone.
 * @returns {number} The instant.
 */
export const createStart = (day: string, hour: number | null, now: number, zone: Zone, timezone: string): number =>
    (hour === null ? dayStart(day, now, zone, timezone) : hourStart(day, hour, timezone));
