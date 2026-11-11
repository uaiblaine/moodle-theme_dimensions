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
 * Tests for the week view's pure layer: the week's days from each first weekday
 * across a month, a year and a daylight-saving change, the span and its address, the all-day strip, the hour body's
 * columns, the hour window over the week, the "now" line and the keyboard's moves.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {zone} from '../../src/calendar/days';
import {place} from '../../src/calendar/layout';
import type {Item} from '../../src/calendar/model';
import type {Strings} from '../../src/calendar/strings';
import {
    deskOf,
    drawerOf,
    gridOf,
    hourBox,
    inSpan,
    monthOfSpan,
    moveInWeek,
    nowLine,
    openOf,
    openingOf,
    readSpan,
    spanDays,
    spanKey,
    spanLabels,
    spanOf,
    stepSpan,
    viewsOf,
    stepWeekFrom,
    weekCaption,
    weekDays,
    weekPlan,
    weekStart,
    weekStops,
} from '../../src/calendar/weeks';
import {STRINGS, at, item} from './fixture';

const UTC = zone('UTC', 'en');
const HOUR = 3600;
const DAY = 86400;

/** The week of 15 November 2026, Sunday first. */
const WEEK = weekDays('2026-11-15');

/**
 * Items placed in UTC.
 *
 * @param {Array} items The items.
 * @returns {Array} The placed items.
 */
const placed = (items: Item[]) => place(items, UTC);

describe('the week\'s days', () => {
    it('start on the viewer\'s first weekday', () => {
        expect(weekStart('2026-11-18', 0)).toBe('2026-11-15');
        expect(weekStart('2026-11-18', 1)).toBe('2026-11-16');
        expect(weekStart('2026-11-15', 0)).toBe('2026-11-15');
        expect(weekStart('2026-11-15', 1)).toBe('2026-11-09');
        expect(weekStart('2026-11-21', 6)).toBe('2026-11-21');
        expect(weekStart('not a day', 0)).toBe('');
    });

    it('are seven calendar days across a month boundary', () => {
        expect(weekStart('2026-11-01', 1)).toBe('2026-10-26');
        expect(weekDays('2026-10-26')).toEqual(
            ['2026-10-26', '2026-10-27', '2026-10-28', '2026-10-29', '2026-10-30', '2026-10-31', '2026-11-01']
        );
    });

    it('are seven calendar days across a year boundary', () => {
        expect(weekStart('2027-01-01', 0)).toBe('2026-12-27');
        expect(weekDays('2026-12-27')).toEqual(
            ['2026-12-27', '2026-12-28', '2026-12-29', '2026-12-30', '2026-12-31', '2027-01-01', '2027-01-02']
        );
        expect(stepSpan({view: 'week', week: '2026-12-27'}, '2026-12-30', 1)).toBe('2027-01-06');
        expect(weekStart('2027-01-06', 0)).toBe('2027-01-03');
    });

    it('keep the wall clock across a daylight-saving change: the same hour on both sides sits in the same row', () => {
        // New York leaves daylight saving on 1 November 2026: 09:00 is 13:00 UTC on the 31st and 14:00 UTC on the 1st.
        const york = zone('America/New_York', 'en');
        const days = weekDays(weekStart('2026-10-29', 1));
        expect(days[0]).toBe('2026-10-26');
        expect(days[6]).toBe('2026-11-01');
        const before = item({id: 1, timestart: at('2026-10-31', 13), timeduration: HOUR});
        const after = item({id: 2, timestart: at('2026-11-01', 14), timeduration: HOUR});
        const plan = weekPlan(days, place([before, after], york), york);
        expect(plan.columns[5].map((laid) => laid.entry.item.id)).toEqual([1]);
        expect(plan.columns[6].map((laid) => laid.entry.item.id)).toEqual([2]);
        expect(plan.window).toEqual({from: 8, to: 12});
        expect(hourBox(plan.columns[5][0], york, plan.window!).top).toBe(hourBox(plan.columns[6][0], york, plan.window!).top);
    });
});

describe('the span and its address', () => {
    it('is the month or the week holding a day', () => {
        expect(spanOf('week', '2026-11-18', 0)).toEqual({view: 'week', week: '2026-11-15'});
        expect(spanOf('month', '2026-11-18', 0)).toEqual({view: 'month', month: '2026-11'});
        expect(spanDays({view: 'week', week: '2026-11-15'}, 0)).toEqual(WEEK);
        expect(spanDays({view: 'month', month: '2026-11'}, 0)).toHaveLength(35);
    });

    it('opens on today when it holds it, else on its first day', () => {
        expect(openingOf({view: 'week', week: '2026-11-15'}, '2026-11-17')).toBe('2026-11-17');
        expect(openingOf({view: 'week', week: '2026-11-22'}, '2026-11-17')).toBe('2026-11-22');
        expect(openingOf({view: 'month', month: '2026-12'}, '2026-11-17')).toBe('2026-12-01');
    });

    it('names the selected day\'s month, or the week\'s first day\'s', () => {
        expect(monthOfSpan({view: 'week', week: '2026-11-29'}, '2026-12-02')).toBe('2026-12');
        expect(monthOfSpan({view: 'week', week: '2026-11-29'}, '2027-01-20')).toBe('2026-11');
        expect(monthOfSpan({view: 'month', month: '2026-11'}, '2026-12-02')).toBe('2026-11');
    });

    it('moves by a week in the week view and by a month in the month view', () => {
        expect(stepSpan({view: 'week', week: '2026-11-15'}, '2026-11-17', -1)).toBe('2026-11-10');
        expect(stepSpan({view: 'month', month: '2026-11'}, '2026-11-17', 1)).toBe('2026-12-17');
    });

    it('writes view=week and the week first, the rest as written, and the month alone for the month view', () => {
        const href = 'http://localhost/r.php/theme_dimensions/calendar?month=2026-11&day=2026-11-17&keys%5B%5D=site';
        const week = new URL(inSpan(href, {view: 'week', week: '2026-11-15'}));
        expect([...week.searchParams.keys()]).toEqual(['view', 'week', 'day', 'keys[]']);
        expect(week.searchParams.get('week')).toBe('2026-11-15');
        expect(week.searchParams.has('month')).toBe(false);
        const month = new URL(inSpan(week.toString(), {view: 'month', month: '2026-11'}));
        expect([...month.searchParams.keys()]).toEqual(['month', 'day', 'keys[]']);
    });

    it('reads it back as the server does: exactly view=week, the week snapped to its first day', () => {
        const base = 'http://localhost/calendar.php';
        const today = '2027-01-20';
        expect(readSpan(`${base}?view=week&week=2026-11-18`, 0, '2026-11', today)).toEqual({view: 'week', week: '2026-11-15'});
        expect(readSpan(`${base}?view=week&week=2026-11-31`, 1, '2026-11', today)).toEqual({view: 'week', week: '2026-10-26'});
        expect(readSpan(`${base}?view=Week&week=2026-11-18`, 0, '2026-11', today)).toEqual({view: 'month', month: '2026-11'});
        expect(readSpan(`${base}?week=2026-11-18`, 0, '2026-12', today)).toEqual({view: 'month', month: '2026-12'});
    });

    it('falls back as the server does: the week of the month\'s opening day, and only inside its years', () => {
        const read = (week: string, today: string) =>
            readSpan(`http://localhost/calendar.php?view=week${week}`, 0, '2026-11', today);
        const inmonth = '2026-11-18';
        // The month holds today: its opening day is today (page::opening_day()), not its 1st.
        expect(read('&week=nonsense', inmonth)).toEqual({view: 'week', week: '2026-11-15'});
        expect(read('', inmonth)).toEqual({view: 'week', week: '2026-11-15'});
        // Control: a month that does not hold today opens on its 1st.
        expect(read('&week=nonsense', '2027-01-20')).toEqual({view: 'week', week: '2026-11-01'});
        // A real day of a year outside 1900 to 2999 is refused like a malformed one; the bounds themselves are kept.
        expect(read('&week=1899-12-31', inmonth)).toEqual({view: 'week', week: '2026-11-15'});
        expect(read('&week=3000-01-01', inmonth)).toEqual({view: 'week', week: '2026-11-15'});
        expect(read('&week=1900-01-03', inmonth)).toEqual({view: 'week', week: '1899-12-31'});
        expect(read('&week=2999-12-30', inmonth)).toEqual({view: 'week', week: '2999-12-29'});
    });

    it('captions the week with its first and last day, the year once', () => {
        expect(weekCaption(weekDays('2026-12-27'), STRINGS as Strings, 'en')).toBe('December 27 to January 2, 2027');
    });
});

describe('the week\'s layout', () => {
    it('puts multi-day items, whole days and event courses in the strip, as bars across their columns', () => {
        const items = [
            item({id: 1, name: 'Congress', timestart: at('2026-11-17'), timeduration: 3 * DAY}),
            item({id: 2, name: 'Holiday', timestart: at('2026-11-20'), timeduration: DAY}),
            item({id: 3, name: 'Course', source: 'course', timestart: at('2026-11-16', 9), timeduration: HOUR}),
            item({id: 4, name: 'Meeting', timestart: at('2026-11-16', 9), timeduration: HOUR}),
            item({id: 5, name: 'Running on', timestart: at('2026-11-10'), timeduration: 7 * DAY}),
        ];
        const plan = weekPlan(WEEK, placed(items), UTC);
        const bars = plan.strip.bars.map((segment) => [segment.placed.item.id, segment.start, segment.end, segment.clipstart]);
        expect(bars).toEqual(expect.arrayContaining([[1, 2, 4, false], [2, 5, 5, false], [3, 1, 1, false], [5, 0, 1, true]]));
        expect(bars).toHaveLength(4);
        expect(plan.columns[1].map((laid) => laid.entry.item.id)).toEqual([4]);
        expect(plan.columns.flat()).toHaveLength(1);
    });

    it('draws every slot of the strip, a bar never zigzagging', () => {
        const items = [1, 2, 3, 4, 5].map((id) => item({id: id, timestart: at('2026-11-16'), timeduration: 2 * DAY}));
        const plan = weekPlan(WEEK, placed(items), UTC);
        expect(plan.strip.bars).toHaveLength(5);
        expect(plan.strip.used).toBe(5);
        expect(plan.strip.hidden).toEqual([0, 0, 0, 0, 0, 0, 0]);
    });

    it('lays overlapping items side by side, the drawer\'s columns', () => {
        const items = [
            item({id: 1, timestart: at('2026-11-17', 14), timeduration: HOUR}),
            item({id: 2, timestart: at('2026-11-17', 14) + 1800, timeduration: HOUR}),
            item({id: 3, timestart: at('2026-11-17', 17), timeduration: HOUR}),
        ];
        const shape = weekPlan(WEEK, placed(items), UTC).columns[2]
            .map((laid) => [laid.entry.item.id, laid.column, laid.columns, laid.overlaps.length]);
        expect(shape).toEqual([[1, 0, 2, 1], [2, 1, 2, 1], [3, 0, 1, 0]]);
    });

    it('windows the hours over the whole week, an hour either side, at least four', () => {
        const items = [
            item({id: 1, timestart: at('2026-11-16', 9), timeduration: HOUR}),
            item({id: 2, timestart: at('2026-11-20', 15), timeduration: HOUR}),
        ];
        expect(weekPlan(WEEK, placed(items), UTC).window).toEqual({from: 8, to: 17});
        const one = [item({id: 3, timestart: at('2026-11-18', 12), timeduration: HOUR})];
        expect(weekPlan(WEEK, placed(one), UTC).window).toEqual({from: 11, to: 15});
        const outside = [...items, item({id: 4, timestart: at('2026-11-23', 2), timeduration: HOUR})];
        expect(weekPlan(WEEK, placed(outside), UTC).window).toEqual({from: 8, to: 17});
    });

    it('has no hour window when nothing is timed', () => {
        const items = [item({id: 1, timestart: at('2026-11-17'), timeduration: 2 * DAY})];
        expect(weekPlan(WEEK, placed(items), UTC).window).toBeNull();
    });

    it('places an hour item in shares of the window and of the width', () => {
        const items = [
            item({id: 1, timestart: at('2026-11-17', 9), timeduration: 2 * HOUR}),
            item({id: 2, timestart: at('2026-11-17', 10), timeduration: HOUR}),
        ];
        const plan = weekPlan(WEEK, placed(items), UTC);
        expect(plan.window).toEqual({from: 8, to: 12});
        expect(hourBox(plan.columns[2][0], UTC, plan.window!)).toEqual({top: 0.25, height: 0.5, left: 0, width: 0.5});
        expect(hourBox(plan.columns[2][1], UTC, plan.window!)).toEqual({top: 0.5, height: 0.25, left: 0.5, width: 0.5});
    });
});

describe('the "now" line', () => {
    const window = {from: 8, to: 18};

    it('is on today\'s column, at its share of the window', () => {
        expect(nowLine(WEEK, at('2026-11-17', 13), UTC, window)).toEqual({column: 2, share: 0.5});
    });

    it('is not drawn outside the window, outside the week, or without one', () => {
        expect(nowLine(WEEK, at('2026-11-17', 7), UTC, window)).toBeNull();
        expect(nowLine(WEEK, at('2026-11-17', 18), UTC, window)).toBeNull();
        expect(nowLine(WEEK, at('2026-11-24', 13), UTC, window)).toBeNull();
        expect(nowLine(WEEK, at('2026-11-17', 13), UTC, null)).toBeNull();
        expect(nowLine(WEEK, at('2026-11-17', 8), UTC, window)).toEqual({column: 2, share: 0});
    });

    it('falls on the viewer\'s day, not UTC\'s', () => {
        const recife = zone('America/Recife', 'en');
        // 01:00 UTC on the 18th is 22:00 on the 17th in Recife.
        expect(nowLine(WEEK, at('2026-11-18', 1), recife, {from: 20, to: 24})).toEqual({column: 2, share: 0.5});
    });
});

describe('the keyboard\'s moves', () => {
    const items = [
        item({id: 1, timestart: at('2026-11-16'), timeduration: 3 * DAY}),
        item({id: 2, timestart: at('2026-11-17', 9), timeduration: HOUR}),
        item({id: 3, timestart: at('2026-11-17', 14), timeduration: HOUR}),
        item({id: 4, timestart: at('2026-11-18', 13), timeduration: HOUR}),
    ];
    const stops = weekStops(weekPlan(WEEK, placed(items), UTC), UTC);

    it('walks a day strip first, then by start', () => {
        expect(stops[2].map((stop) => stop.key)).toEqual(['core-1', 'core-2', 'core-3']);
        expect(stops[3].map((stop) => stop.key)).toEqual(['core-1', 'core-4']);
        expect(stops[0]).toEqual([]);
    });

    it('a header enters its first item with ArrowDown or F2, and nothing when the day is empty', () => {
        expect(moveInWeek('ArrowDown', {column: 2, index: -1}, stops)).toEqual({column: 2, index: 0});
        expect(moveInWeek('F2', {column: 2, index: -1}, stops)).toEqual({column: 2, index: 0});
        expect(moveInWeek('ArrowDown', {column: 0, index: -1}, stops)).toBeNull();
        expect(moveInWeek('ArrowRight', {column: 2, index: -1}, stops)).toBeNull();
    });

    it('ArrowDown and ArrowUp walk the day, ArrowUp from the first goes back to the header', () => {
        expect(moveInWeek('ArrowDown', {column: 2, index: 0}, stops)).toEqual({column: 2, index: 1});
        expect(moveInWeek('ArrowDown', {column: 2, index: 2}, stops)).toBeNull();
        expect(moveInWeek('ArrowUp', {column: 2, index: 1}, stops)).toEqual({column: 2, index: 0});
        expect(moveInWeek('ArrowUp', {column: 2, index: 0}, stops)).toEqual({column: 2, index: -1});
        expect(moveInWeek('Home', {column: 2, index: 2}, stops)).toEqual({column: 2, index: 0});
        expect(moveInWeek('End', {column: 2, index: 0}, stops)).toEqual({column: 2, index: 2});
        expect(moveInWeek('Escape', {column: 2, index: 1}, stops)).toEqual({column: 2, index: -1});
    });

    it('ArrowLeft and ArrowRight go to the neighbouring day\'s item nearest in time, or its header', () => {
        expect(moveInWeek('ArrowRight', {column: 2, index: 2}, stops)).toEqual({column: 3, index: 1});
        expect(moveInWeek('ArrowRight', {column: 2, index: 0}, stops)).toEqual({column: 3, index: 0});
        expect(moveInWeek('ArrowLeft', {column: 1, index: 0}, stops)).toEqual({column: 0, index: -1});
        expect(moveInWeek('ArrowLeft', {column: 0, index: 0}, [[{key: 'core-9', minutes: 60}]])).toBeNull();
    });

    it('a header moves a day, to the week\'s ends and a week away', () => {
        expect(stepWeekFrom('ArrowRight', '2026-11-21', WEEK)).toBe('2026-11-22');
        expect(stepWeekFrom('ArrowLeft', '2026-11-15', WEEK)).toBe('2026-11-14');
        expect(stepWeekFrom('Home', '2026-11-18', WEEK)).toBe('2026-11-15');
        expect(stepWeekFrom('End', '2026-11-18', WEEK)).toBe('2026-11-21');
        expect(stepWeekFrom('PageDown', '2026-11-18', WEEK)).toBe('2026-11-25');
        expect(stepWeekFrom('PageUp', '2026-11-18', WEEK)).toBe('2026-11-11');
        expect(stepWeekFrom('ArrowDown', '2026-11-18', WEEK)).toBeNull();
    });
});

describe('the day view, a phone\'s (R1d)', () => {
    const DAYSPAN = {view: 'day' as const, day: '2026-11-18'};

    it('is the day itself: its one day asked for, its month, its key and its opening', () => {
        expect(spanOf('day', '2026-11-18', 0)).toEqual(DAYSPAN);
        expect(spanDays(DAYSPAN, 0)).toEqual(['2026-11-18']);
        expect(monthOfSpan(DAYSPAN, '2026-12-01')).toBe('2026-11');
        expect(spanKey(DAYSPAN)).toBe('day:2026-11-18');
        expect(spanKey(DAYSPAN)).not.toBe(spanKey({view: 'month', month: '2026-11'}));
        expect(openingOf(DAYSPAN, '2026-11-17')).toBe('2026-11-18');
    });

    it('moves a day at a time, across a month and a year', () => {
        expect(stepSpan(DAYSPAN, '2026-11-18', 1)).toBe('2026-11-19');
        expect(stepSpan(DAYSPAN, '2026-11-18', -1)).toBe('2026-11-17');
        expect(stepSpan({view: 'day', day: '2026-11-30'}, '2026-11-30', 1)).toBe('2026-12-01');
        expect(stepSpan({view: 'day', day: '2027-01-01'}, '2027-01-01', -1)).toBe('2026-12-31');
    });

    it('writes view=day and its day first, the open day\'s day= replaced, the rest as written', () => {
        const href = 'http://localhost/r.php/theme_dimensions/calendar?month=2026-11&day=2026-11-03&city=Natal';
        expect(inSpan(href, DAYSPAN))
            .toBe('http://localhost/r.php/theme_dimensions/calendar?view=day&day=2026-11-18&city=Natal');
        expect(inSpan('http://localhost/c?view=week&week=2026-11-15', DAYSPAN)).toBe('http://localhost/c?view=day&day=2026-11-18');
    });

    it('reads it back as the server does: exactly view=day, a day of the month\'s years, else the opening day', () => {
        expect(readSpan('http://localhost/c?view=day&day=2026-11-18', 0, '2026-11', '2026-11-17')).toEqual(DAYSPAN);
        expect(readSpan('http://localhost/c?view=Day&day=2026-11-18', 0, '2026-11', '2026-11-17'))
            .toEqual({view: 'month', month: '2026-11'});
        ['2026-11-31', 'nope', '1899-12-31', '3000-01-01', ''].forEach((day) => {
            expect(readSpan(`http://localhost/c?view=day&day=${day}`, 0, '2027-02', '2026-11-17'))
                .toEqual({view: 'day', day: '2027-02-01'});
        });
        expect(readSpan('http://localhost/c?view=day', 0, '2026-11', '2026-11-17')).toEqual({view: 'day', day: '2026-11-17'});
        expect(readSpan('http://localhost/c?view=day&day=1900-01-01', 0, '2026-11', '2026-11-17'))
            .toEqual({view: 'day', day: '1900-01-01'});
    });

    it('captions the day with its name and date, and its arrows move a day', () => {
        expect(spanLabels(DAYSPAN, ['2026-11-18'], STRINGS as unknown as Strings, 'en')).toEqual({
            caption: 'Wednesday, November 18, 2026',
            previous: 'Previous day',
            next: 'Next day',
        });
    });

    it('is offered by the switch on a phone only', () => {
        expect(viewsOf(true)).toEqual(['month', 'week', 'day']);
        expect(viewsOf(false)).toEqual(['month', 'week']);
    });

    it('is drawn only on a phone: a desktop draws its month, with the day\'s drawer', () => {
        expect(gridOf(DAYSPAN, true)).toBe('day');
        expect(gridOf(DAYSPAN, false)).toBe('month');
        expect(gridOf({view: 'week', week: '2026-11-15'}, false)).toBe('week');
        expect(drawerOf(DAYSPAN, null, ['2026-11-18'], false)).toBe('2026-11-18');
        expect(drawerOf(DAYSPAN, '2026-11-18', ['2026-11-18'], true)).toBeNull();
        expect(drawerOf({view: 'month', month: '2026-11'}, '2026-11-18', ['2026-11-18'], false)).toBe('2026-11-18');
        expect(drawerOf({view: 'month', month: '2026-11'}, '2026-12-18', ['2026-11-18'], false)).toBeNull();
    });

    it('is asked for as its month, the day opened, when the frame is not a phone\'s', () => {
        expect(deskOf(DAYSPAN, false, 0)).toEqual({span: {view: 'month', month: '2026-11'}, open: '2026-11-18'});
        expect(deskOf(DAYSPAN, true, 0)).toEqual({span: DAYSPAN, open: null});
        const week = {view: 'week' as const, week: '2026-11-15'};
        expect(deskOf(week, false, 0)).toEqual({span: week, open: null});
    });

    it('opens no day from its address: its day= is the view\'s own', () => {
        expect(openOf(DAYSPAN, '2026-11-18', ['2026-11-18'])).toBeNull();
        expect(openOf({view: 'month', month: '2026-11'}, '2026-11-18', ['2026-11-18'])).toBe('2026-11-18');
        expect(openOf({view: 'month', month: '2026-11'}, '2026-12-18', ['2026-11-18'])).toBeNull();
    });
});
