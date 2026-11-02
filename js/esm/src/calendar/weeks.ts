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
 * The week view, pure: the span a page shows, the week's days, its layout, its "now" line and its keyboard's moves
 * (docs/calendar/PROPOSAL.md section 21).
 *
 * - A SPAN is what a page shows: a month (its grid of whole weeks, calendar/days.ts) or a week (seven days from the
 *   viewer's first weekday). The view and the week travel in the address as navigation, never in the saved state:
 *   `view=week&week=<first day>` (local\calendar\page::PARAM_VIEW and PARAM_WEEK), the month view writing neither.
 * - The week's LAYOUT is the month's and the drawer's, reused: the all-day strip is layout.ts weekLayout() over the
 *   items that are bars or fill whole days (overlap.ts isAllDay()), every slot shown; each day's hour items are
 *   overlap.ts dayPlan()'s, in the drawer's columns; and the hour window is overlap.ts hourWindow() over the whole
 *   week's hour items at once, so every timed item shows without an inner scroll.
 * - Days are calendar days counted in UTC (daterange/calendar.ts): a daylight-saving change moves no day, and an
 *   item's row is its wall-clock time in the viewer's timezone (days.ts zone().minutes()).
 *
 * @module     theme_dimensions/calendar/weeks
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {WEEK, parse, say, shift, shiftMonth, utc} from '../daterange/calendar';
import {gridWeeks} from './days';
import type {Zone} from './days';
import {weekLayout} from './layout';
import type {Placed, WeekLayout} from './layout';
import {dayPlan, hourWindow, isAllDay, itemKey} from './overlap';
import type {HourWindow, Laid} from './overlap';
import {fill} from './strings';
import type {Strings} from './strings';

/** The address parameter of the view (local\calendar\page::PARAM_VIEW): `week`, or absent for the month. */
export const VIEW_PARAM = 'view';

/** The address parameter of the week shown, its first day (local\calendar\page::PARAM_WEEK). */
export const WEEK_PARAM = 'week';

/** The two views. */
export type View = 'month' | 'week';

/** What a page shows: a month, YYYY-MM, or a week by its first day, YYYY-MM-DD. */
export type Span = {view: 'month', month: string} | {view: 'week', week: string};

/** The week's layout: the all-day strip, each day's hour items, and the hour window, null when nothing is timed. */
export type WeekPlan = {strip: WeekLayout, columns: Laid[][], window: HourWindow | null};

/** Where the "now" line falls: its day's column and its share of the hour window. */
export type NowLine = {column: number, share: number};

/** One stop of the keyboard in a day's column: an item's key and its start in minutes of the day (-1 for the strip). */
export type Stop = {key: string, minutes: number};

/** Where the keyboard stands: a day's column, and an item of it, or -1 for the day's header. */
export type Cursor = {column: number, index: number};

/** The position of an hour item, in shares of the hour window (top, height) and of the day's width (left, width). */
export type Box = {top: number, height: number, left: number, width: number};

/** What the toolbar says for a span: its caption, and the labels of "previous" and "next". */
export type SpanLabels = {caption: string, previous: string, next: string};

/** The format of a month caption. */
const MONTH: Intl.DateTimeFormatOptions = {month: 'long', year: 'numeric'};

/** The format of a week caption's first day. */
const FROM_DAY: Intl.DateTimeFormatOptions = {day: 'numeric', month: 'long'};

/** The format of a week caption's last day. */
const TO_DAY: Intl.DateTimeFormatOptions = {day: 'numeric', month: 'long', year: 'numeric'};

/**
 * The first day of the week holding a day, on the viewer's first weekday (local\calendar\page::week_start()).
 *
 * @param {string} iso A day, YYYY-MM-DD.
 * @param {number} firstday The weekday a week begins on, 0 for Sunday.
 * @returns {string} The week's first day, or the empty string when the input is not a day.
 */
export const weekStart = (iso: string, firstday: number): string => {
    const parts = parse(iso);
    if (!parts) {
        return '';
    }
    return shift(iso, -((utc(parts).getUTCDay() - firstday + WEEK) % WEEK));
};

/**
 * A week's seven days.
 *
 * @param {string} start The week's first day.
 * @returns {Array} The days, YYYY-MM-DD.
 */
export const weekDays = (start: string): string[] => Array.from({length: WEEK}, (unused, index) => shift(start, index));

/**
 * The span of a view holding a day.
 *
 * @param {string} view The view.
 * @param {string} iso The day.
 * @param {number} firstday The weekday a week begins on.
 * @returns {object} The span.
 */
export const spanOf = (view: View, iso: string, firstday: number): Span => (view === 'week'
    ? {view: 'week', week: weekStart(iso, firstday)}
    : {view: 'month', month: iso.slice(0, 7)});

/**
 * The days a span shows, which are the days the range is asked for: one call either way (at most 42 days).
 *
 * @param {object} span The span.
 * @param {number} firstday The weekday a week begins on.
 * @returns {Array} The days.
 */
export const spanDays = (span: Span, firstday: number): string[] => (span.view === 'week'
    ? weekDays(span.week)
    : gridWeeks(span.month, firstday).flat());

/**
 * A span's identity, to tell a move from a change within it.
 *
 * @param {object} span The span.
 * @returns {string}
 */
export const spanKey = (span: Span): string => (span.view === 'week' ? `week:${span.week}` : `month:${span.month}`);

/**
 * The month a span names: the month's own, or the month of a day of the week (the selected one, the week's first).
 *
 * @param {object} span The span.
 * @param {string} selected The selected day.
 * @returns {string} YYYY-MM.
 */
export const monthOfSpan = (span: Span, selected: string): string => {
    if (span.view === 'month') {
        return span.month;
    }
    return weekDays(span.week).includes(selected) ? selected.slice(0, 7) : span.week.slice(0, 7);
};

/**
 * The day a span opens on: today when it holds it, else the month's 1st or the week's first day.
 *
 * @param {object} span The span.
 * @param {string} today Today, YYYY-MM-DD.
 * @returns {string}
 */
export const openingOf = (span: Span, today: string): string => {
    if (span.view === 'month') {
        return today.startsWith(span.month) ? today : `${span.month}-01`;
    }
    return weekDays(span.week).includes(today) ? today : span.week;
};

/**
 * The day "previous" and "next" lead to from the selected day: a month or a week away.
 *
 * @param {object} span The span shown.
 * @param {string} iso The selected day.
 * @param {number} offset How many spans, negative to go back.
 * @returns {string} The day.
 */
export const stepSpan = (span: Span, iso: string, offset: number): string => (span.view === 'week'
    ? shift(iso, offset * WEEK)
    : shiftMonth(iso, offset));

/**
 * An address with its span written: `month=` for a month, `view=week&week=` for a week, first, the rest as written.
 *
 * @param {string} href The address (model.ts address(), whose month this replaces).
 * @param {object} span The span.
 * @returns {string} The address.
 */
export const inSpan = (href: string, span: Span): string => {
    const url = new URL(href, window.location.href);
    const rest = [...url.searchParams.entries()]
        .filter(([name]) => name !== 'month' && name !== VIEW_PARAM && name !== WEEK_PARAM);
    url.search = '';
    if (span.view === 'week') {
        url.searchParams.append(VIEW_PARAM, 'week');
        url.searchParams.append(WEEK_PARAM, span.week);
    } else {
        url.searchParams.append('month', span.month);
    }
    rest.forEach(([name, value]) => url.searchParams.append(name, value));
    return url.toString();
};

/**
 * The span an address names, as the server reads it (local\calendar\page::week_from_request()): the week for exactly
 * `view=week`, its day snapped to the week's first day; a week that is not a day, or whose year is outside the month's
 * bounds (1900 to 2999), being the week of the month's opening day (today when the month holds it, else its 1st,
 * page::opening_day()); the month for anything else.
 *
 * @param {string} href The address.
 * @param {number} firstday The weekday a week begins on.
 * @param {string} month The month the address names, or the page's.
 * @param {string} today Today, YYYY-MM-DD.
 * @returns {object} The span.
 */
export const readSpan = (href: string, firstday: number, month: string, today: string): Span => {
    const params = new URL(href, window.location.href).searchParams;
    if (params.get(VIEW_PARAM) !== 'week') {
        return {view: 'month', month: month};
    }
    const week = String(params.get(WEEK_PARAM) || '');
    const year = Number(week.slice(0, 4));
    const valid = parse(week) !== null && year >= 1900 && year <= 2999;
    return {view: 'week', week: weekStart(valid ? week : openingOf({view: 'month', month: month}, today), firstday)};
};

/**
 * The week's caption: its span, the first day without the year, the last with it ("1 November to 7 November 2026").
 *
 * @param {Array} days The week's days.
 * @param {object} strings The page's strings.
 * @param {string|undefined} language The language tag.
 * @returns {string}
 */
export const weekCaption = (days: string[], strings: Strings, language: string | undefined): string =>
    fill(strings.calendar_span, {from: say(days[0], FROM_DAY, language), to: say(days[days.length - 1], TO_DAY, language)});

/**
 * What the toolbar says for a span: the month's or the week's caption, and "previous" and "next" by the month or by the
 * week.
 *
 * @param {object} span The span.
 * @param {Array} days Its days.
 * @param {object} strings The page's strings.
 * @param {string|undefined} language The language tag.
 * @returns {object} The labels.
 */
export const spanLabels = (span: Span, days: string[], strings: Strings, language: string | undefined): SpanLabels => {
    if (span.view === 'week') {
        return {
            caption: weekCaption(days, strings, language),
            previous: strings.calendar_prevweek,
            next: strings.calendar_nextweek,
        };
    }
    return {
        caption: say(`${span.month}-01`, MONTH, language),
        previous: strings.calendar_prevmonth,
        next: strings.calendar_nextmonth,
    };
};

/**
 * A day, kept only when the span's days hold it.
 *
 * @param {string|null} iso The day, or null.
 * @param {Array} days The span's days.
 * @returns {string|null}
 */
export const dayIn = (iso: string | null, days: string[]): string | null => (iso !== null && days.includes(iso) ? iso : null);

/**
 * The day a span selects: the selected one when the span holds it, else the day the span opens on.
 *
 * @param {string} selected The selected day.
 * @param {object} span The span.
 * @param {Array} days Its days.
 * @param {string} today Today.
 * @returns {string}
 */
export const selectedIn = (selected: string, span: Span, days: string[], today: string): string =>
    (days.includes(selected) ? selected : openingOf(span, today));

/**
 * The week's layout: the all-day strip, each day's hour items in the drawer's columns, and the week's hour window.
 *
 * The strip holds every item touching the week that is a bar of the month grid (it spans more than one day, or it is
 * an event course) or that fills whole days; each is a bar across the columns it covers, clipped at the week's ends,
 * in the lowest slot free on all of them, and every slot is drawn. The hour window is the drawer's rule over the whole
 * week's hour items: from an hour before the earliest start to an hour after the latest end, at least four hours.
 *
 * @param {Array} days The week's days.
 * @param {Array} placed Every placed item of the answer.
 * @param {object} zone The viewer's timezone reader.
 * @returns {object} The plan.
 */
export const weekPlan = (days: string[], placed: Placed[], zone: Zone): WeekPlan => {
    const strip = placed
        .filter((entry) => entry.bar || isAllDay(entry.item, zone))
        .map((entry) => (entry.bar ? entry : {...entry, bar: true}));
    const columns = days.map((iso) => dayPlan(iso, placed, zone).hours);
    const timed = columns.flat();
    return {
        strip: weekLayout(days, strip, Math.max(strip.length, 1)),
        columns: columns,
        window: timed.length ? hourWindow(timed, zone) : null,
    };
};

/**
 * Where an hour item sits: its top and height in shares of the window, clipped to it, its column in shares of the
 * day's width (the drawer's position(), in shares).
 *
 * @param {object} laid The laid item.
 * @param {object} zone The viewer's timezone reader.
 * @param {object} window The hour window.
 * @returns {object} The box.
 */
export const hourBox = (laid: Laid, zone: Zone, window: HourWindow): Box => {
    const from = window.from * 60;
    const total = (window.to - window.from) * 60;
    const begins = zone.minutes(laid.entry.item.timestart);
    const start = Math.max(from, begins);
    const end = Math.min(window.to * 60, begins + Math.max(laid.entry.item.timeduration / 60, 0));
    return {
        top: (start - from) / total,
        height: Math.max(end - start, 0) / total,
        left: laid.column / laid.columns,
        width: 1 / laid.columns,
    };
};

/**
 * The "now" line: the column of the day an instant falls on in the viewer's timezone, and its share of the window.
 * Nothing when the week has no hour window, when the day is not in the week, or when the time is outside the window.
 *
 * @param {Array} days The week's days.
 * @param {number} now The instant, in seconds.
 * @param {object} zone The viewer's timezone reader.
 * @param {object|null} window The week's hour window.
 * @returns {object|null}
 */
export const nowLine = (days: string[], now: number, zone: Zone, window: HourWindow | null): NowLine | null => {
    if (window === null) {
        return null;
    }
    const column = days.indexOf(zone.day(now));
    const minutes = zone.minutes(now);
    if (column < 0 || minutes < window.from * 60 || minutes >= window.to * 60) {
        return null;
    }
    return {column: column, share: (minutes - window.from * 60) / ((window.to - window.from) * 60)};
};

/**
 * Each day's keyboard stops, in the order the arrows walk them: the strip's bars covering the day in their slots, then
 * its hour items by start (overlap.ts columns()' order).
 *
 * @param {object} plan The week's plan.
 * @param {object} zone The viewer's timezone reader.
 * @returns {Array} One list per day.
 */
export const weekStops = (plan: WeekPlan, zone: Zone): Stop[][] => plan.columns.map((laid, column) => [
    ...plan.strip.bars
        .filter((segment) => segment.start <= column && column <= segment.end)
        .sort((a, b) => a.slot - b.slot)
        .map((segment) => ({key: itemKey(segment.placed.item), minutes: -1})),
    ...laid.map((one) => ({key: itemKey(one.entry.item), minutes: zone.minutes(one.entry.item.timestart)})),
]);

/**
 * The day a key moves to from a day header, or null when the key moves nothing (section 21.3). A day outside the
 * week is the caller's to fetch.
 *
 * @param {string} key The key.
 * @param {string} iso The day stood on.
 * @param {Array} days The week's days.
 * @returns {string|null}
 */
export const stepWeekFrom = (key: string, iso: string, days: string[]): string | null => {
    switch (key) {
        case 'ArrowLeft':
            return shift(iso, -1);
        case 'ArrowRight':
            return shift(iso, 1);
        case 'Home':
            return days[0];
        case 'End':
            return days[days.length - 1];
        case 'PageUp':
            return shift(iso, -WEEK);
        case 'PageDown':
            return shift(iso, WEEK);
        default:
            return null;
    }
};

/**
 * Where a key moves the keyboard inside the week, or null when it moves nothing (section 21.3).
 *
 * From a day header (index -1), ArrowDown and F2 enter the day's first item. From an item, ArrowDown and ArrowUp walk
 * the day's items (ArrowUp from the first goes back to the header), Home and End go to its first and last, Escape to
 * the header, and ArrowLeft and ArrowRight to the neighbouring day's item nearest in time (the strip counting as the
 * day's top), or to that day's header when it has none; nothing at the week's ends.
 *
 * @param {string} key The key.
 * @param {object} at Where the keyboard stands.
 * @param {Array} stops Each day's stops (weekStops()).
 * @returns {object|null} The new cursor.
 */
export const moveInWeek = (key: string, at: Cursor, stops: Stop[][]): Cursor | null => {
    const items = stops[at.column] || [];
    if (at.index < 0) {
        return (key === 'ArrowDown' || key === 'F2') && items.length ? {column: at.column, index: 0} : null;
    }
    switch (key) {
        case 'ArrowDown':
            return at.index + 1 < items.length ? {column: at.column, index: at.index + 1} : null;
        case 'ArrowUp':
            return {column: at.column, index: at.index - 1};
        case 'Home':
            return {column: at.column, index: 0};
        case 'End':
            return {column: at.column, index: items.length - 1};
        case 'Escape':
            return {column: at.column, index: -1};
        case 'ArrowLeft':
        case 'ArrowRight': {
            const column = at.column + (key === 'ArrowLeft' ? -1 : 1);
            if (column < 0 || column >= stops.length) {
                return null;
            }
            const minutes = items[at.index]?.minutes ?? -1;
            let best = -1;
            stops[column].forEach((stop, index) => {
                if (best < 0 || Math.abs(stop.minutes - minutes) < Math.abs(stops[column][best].minutes - minutes)) {
                    best = index;
                }
            });
            return {column: column, index: best};
        }
        default:
            return null;
    }
};
