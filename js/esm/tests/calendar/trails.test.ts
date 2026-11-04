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
 * Tests for the Trilhas view's model: where a step's bar goes on the days held, the phone's "Today" line, the address,
 * the months held and their merge, the scroll arithmetic and the connectors.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {zone} from '../../src/calendar/days';
import {
    LOADED_CAP, STEP_INSET, TRACK, barOf, dayLeft, shiftedTarget, extendLoaded, keptLeft, linksOf, loadedOf, mergedOf, monthDays, monthStep,
    monthsOf, nearEnd, openingLeft, pageLeft, rangeLabel, reachOf, readTrail, scrubLeft, shownDays, targetLeft, todayIndex,
    todayLeft, trackOf, trailArgs, trailBarOf, windowDays, withTrail,
} from '../../src/calendar/trails';
import type {Trail, TrailAnswer, TrailStep} from '../../src/calendar/trails';
import {at} from './fixture';

const utc = zone('UTC', 'en');
const march = monthDays('2027-03');

/**
 * A step of the answer's shape.
 *
 * @param {object} fields The fields that differ from a step running through March 2027.
 * @returns {object}
 */
const step = (fields: Partial<TrailStep>): TrailStep => ({
    cmid: 1,
    position: 1,
    name: 'Alfa',
    courseid: 2,
    coursename: 'Alfa',
    categoryid: 0,
    start: at('2027-03-02', 12),
    end: at('2027-03-20', 12),
    expected: false,
    state: '',
    reason: '',
    completed: 0,
    url: 'https://example.com/course/view.php?id=2',
    next: false,
    ...fields,
});

describe('monthDays', () => {
    it('lists the month\'s own days, a leap February included', () => {
        expect(march).toHaveLength(31);
        expect([march[0], march[30]]).toEqual(['2027-03-01', '2027-03-31']);
        expect(monthDays('2028-02')).toHaveLength(29);
    });
});

describe('barOf', () => {
    it('runs from the start to the end inside the month', () => {
        expect(barOf(step({}), march, utc)).toEqual({from: 1, to: 19, before: false, after: false, open: false, milestone: false});
    });

    it('clips a step that began earlier and one that ends in a later month', () => {
        const bar = barOf(step({start: at('2027-02-20', 12), end: at('2027-04-10', 12)}), march, utc);
        expect(bar).toEqual({from: 0, to: 30, before: true, after: true, open: false, milestone: false});
    });

    it('leaves a step with no end open to the month\'s edge', () => {
        expect(barOf(step({end: 0}), march, utc)).toEqual({from: 1, to: 30, before: false, after: true, open: true, milestone: false});
    });

    it('draws an expected date inside the month as a milestone, and not one past it', () => {
        expect(barOf(step({expected: true}), march, utc)?.milestone).toBe(true);
        expect(barOf(step({expected: true, end: at('2027-04-02', 12)}), march, utc)?.milestone).toBe(false);
    });

    it('draws an end alone as a milestone on its day', () => {
        expect(barOf(step({start: 0}), march, utc)).toEqual({from: 19, to: 19, before: false, after: false, open: false, milestone: true});
    });

    it('draws nothing for a step outside the month or without a date', () => {
        expect(barOf(step({start: at('2027-04-02', 12), end: at('2027-04-20', 12)}), march, utc)).toBeNull();
        expect(barOf(step({start: at('2027-01-02', 12), end: at('2027-02-20', 12)}), march, utc)).toBeNull();
        expect(barOf(step({start: 0, end: 0}), march, utc)).toBeNull();
    });

    it('reads an end before the start as the start day alone', () => {
        expect(barOf(step({start: at('2027-03-10', 12), end: at('2027-03-05', 12)}), march, utc))
            .toEqual({from: 9, to: 9, before: false, after: false, open: false, milestone: false});
        // Even when the end is before the first day held: the start is held, and so is its bar.
        expect(barOf(step({start: at('2027-03-10', 12), end: at('2027-02-20', 12)}), march, utc))
            .toEqual({from: 9, to: 9, before: false, after: false, open: false, milestone: false});
        expect(reachOf(step({start: at('2027-03-10', 12), end: at('2027-02-20', 12)}), march[0], utc)).toEqual({from: 9, to: 9});
    });

    it('draws no milestone for an end alone past the last day held', () => {
        expect(barOf(step({start: 0, end: at('2027-04-02', 12)}), march, utc)).toBeNull();
        expect(barOf(step({start: 0, end: at('2027-03-31', 12)}), march, utc)?.from).toBe(30);
    });

    it('reads the days in the viewer\'s timezone', () => {
        // 01:00 UTC on the 2nd is still the 1st in São Paulo, three hours behind.
        const late = step({start: at('2027-03-02', 1), end: at('2027-03-20', 12)});
        expect(barOf(late, march, utc)?.from).toBe(1);
        expect(barOf(late, march, zone('America/Sao_Paulo', 'en'))?.from).toBe(0);
    });
});

describe('todayIndex', () => {
    const now = at('2027-03-15', 12);

    it('is the first step not done whose end has not passed', () => {
        const steps = [
            step({cmid: 1, state: 'done', end: at('2027-03-10', 12)}),
            step({cmid: 2, end: at('2027-03-12', 12), state: 'late'}),
            step({cmid: 3, end: at('2027-03-20', 12)}),
        ];
        expect(todayIndex(steps, now)).toBe(2);
    });

    it('counts a step with no end as ahead, and is the count when every step is behind', () => {
        expect(todayIndex([step({end: 0})], now)).toBe(0);
        expect(todayIndex([step({end: at('2027-03-01', 12)})], now)).toBe(1);
        expect(todayIndex([step({state: 'done'})], now)).toBe(1);
    });
});

describe('the address', () => {
    it('writes the trail and "only mine" when set and takes them out otherwise', () => {
        const base = 'https://example.com/r.php/theme_dimensions/calendar?view=trail&month=2027-03';
        const written = withTrail(base, {trail: 12, mine: true});
        expect(new URL(written).searchParams.get('trail')).toBe('12');
        expect(new URL(written).searchParams.get('mine')).toBe('1');
        expect(new URL(withTrail(written, {trail: 0, mine: false})).search).toBe('?view=trail&month=2027-03');
        expect(new URL(withTrail(written, null)).searchParams.has('trail')).toBe(false);
    });

    it('reads them as the server does, anything else dropped', () => {
        expect(readTrail('https://example.com/?trail=12&mine=1')).toEqual({trail: 12, mine: true});
        expect(readTrail('https://example.com/?trail=-3&mine=yes')).toEqual({trail: 0, mine: false});
        expect(readTrail('https://example.com/?trail=12345678901')).toEqual({trail: 0, mine: false});
        expect(readTrail('https://example.com/')).toEqual({trail: 0, mine: false});
    });
});

describe('trailBarOf', () => {
    it('spans a trail\'s period and is never a milestone', () => {
        const trail = {first: at('2027-03-02', 12), last: at('2027-04-10', 12)} as Trail;
        expect(trailBarOf(trail, march, utc)).toEqual({from: 1, to: 30, before: false, after: true, open: false, milestone: false});
    });
});

describe('the service\'s arguments', () => {
    it('name the months asked for, one by default', () => {
        const filter = {trail: 7, mine: true};
        expect(trailArgs('2027-03', filter, 4, false)).toEqual({month: '2027-03', trail: 7, mine: true, categoryid: 4,
            recursive: false, months: 1});
        expect(trailArgs('2027-03', filter, 0, true, 2).months).toBe(2);
    });
});

/**
 * A trail of the answer's shape.
 *
 * @param {number} id Its id.
 * @param {string} name Its name.
 * @param {string} first Its period's first day.
 * @param {string} last Its period's last day, '' for an open period.
 * @returns {object}
 */
const trailOf = (id: number, name: string, first: string, last: string): Trail => ({
    id: id,
    name: name,
    url: `https://example.com/${id}`,
    categoryid: 0,
    enrolled: false,
    done: 0,
    first: at(first, 12),
    last: last === '' ? 0 : at(last, 12),
    steps: [step({cmid: id * 10, start: at(first, 12), end: last === '' ? 0 : at(last, 12)})],
});

/**
 * An answer over whole months.
 *
 * @param {string} from The first day.
 * @param {string} to The last day.
 * @param {Array} trails The trails.
 * @param {boolean} truncated Whether it was cut.
 * @returns {object}
 */
const answerOf = (from: string, to: string, trails: Trail[], truncated = false): TrailAnswer => ({
    from: from,
    to: to,
    truncated: truncated,
    options: trails.map((trail) => ({id: trail.id, name: trail.name})),
    trails: trails,
    categories: [{id: trails.length, name: `C${trails.length}`, bg: '#ffffff', ink: '#000000'}],
});

describe('the months held', () => {
    it('are whole months, an answer of two holding both', () => {
        expect(monthsOf('2026-11-01', '2027-01-31')).toEqual(['2026-11', '2026-12', '2027-01']);
        expect(windowDays(['2027-02', '2027-03'])).toHaveLength(59);
        const two = answerOf('2027-03-01', '2027-04-30', []);
        expect(loadedOf(two)).toEqual({months: ['2027-03', '2027-04'], answers: [two, two]});
    });

    it('grow by the month after the last or before the first, the view kept still by the shift', () => {
        const held = loadedOf(answerOf('2027-03-01', '2027-04-30', []));
        const may = answerOf('2027-05-01', '2027-05-31', []);
        expect(extendLoaded(held, may)).toEqual({loaded: {months: ['2027-03', '2027-04', '2027-05'],
            answers: [held.answers[0], held.answers[0], may]}, shift: 0});
        const feb = answerOf('2027-02-01', '2027-02-28', []);
        expect(extendLoaded(held, feb)?.loaded.months).toEqual(['2027-02', '2027-03', '2027-04']);
        expect(extendLoaded(held, feb)?.shift).toBe(28);
    });

    it('stay contiguous: a month held or not beside them is refused', () => {
        const held = loadedOf(answerOf('2027-03-01', '2027-04-30', []));
        expect(extendLoaded(held, answerOf('2027-04-01', '2027-04-30', []))).toBeNull();
        expect(extendLoaded(held, answerOf('2027-06-01', '2027-06-30', []))).toBeNull();
        expect(extendLoaded(held, answerOf('2027-01-01', '2027-01-31', []))).toBeNull();
    });

    it('drop the far side past the cap, the first day\'s move told when it is the start that goes', () => {
        let held = loadedOf(answerOf('2027-01-01', '2027-02-28', []));
        ['2027-03', '2027-04', '2027-05', '2027-06'].forEach((month) => {
            held = (extendLoaded(held, answerOf(`${month}-01`, `${month}-28`, [])) as {loaded: typeof held}).loaded;
        });
        expect(held.months).toHaveLength(LOADED_CAP);
        const july = extendLoaded(held, answerOf('2027-07-01', '2027-07-31', []));
        expect(july?.loaded.months).toEqual(['2027-02', '2027-03', '2027-04', '2027-05', '2027-06', '2027-07']);
        expect(july?.shift).toBe(-31);
        const december = extendLoaded(held, answerOf('2026-12-01', '2026-12-31', []));
        expect(december?.loaded.months).toEqual(['2026-12', '2027-01', '2027-02', '2027-03', '2027-04', '2027-05']);
        expect(december?.shift).toBe(31);
    });

    it('keep the view still by moving the position by the days the first day moved', () => {
        expect(keptLeft(200, 31, 48)).toBe(1688);
        expect(keptLeft(1600, -31, 48)).toBe(112);
        expect(keptLeft(100, -31, 48)).toBe(0);
    });
});

describe('mergedOf', () => {
    it('draws a trail of two answers once, sorted as the server sorts, the options and categories once each', () => {
        const both = trailOf(1, 'Beta', '2027-03-20', '2027-04-10');
        const april = trailOf(2, 'alfa', '2027-04-02', '2027-04-20');
        const march2 = trailOf(3, 'Gama', '2027-03-20', '');
        const held = extendLoaded(loadedOf(answerOf('2027-03-01', '2027-03-31', [both, march2])),
            answerOf('2027-04-01', '2027-04-30', [april, {...both, name: 'Beta again'}], true));
        const merged = mergedOf((held as {loaded: ReturnType<typeof loadedOf>}).loaded, utc);
        expect([merged.from, merged.to, merged.truncated]).toEqual(['2027-03-01', '2027-04-30', true]);
        expect(merged.trails.map((trail) => [trail.id, trail.name])).toEqual([[1, 'Beta'], [3, 'Gama'], [2, 'alfa']]);
        expect(merged.options.map((option) => option.id)).toEqual([2, 1, 3]);
        expect(merged.categories.map((category) => category.id)).toEqual([2]);
    });

    it('keeps a trail only while its period meets the days held', () => {
        const january = trailOf(1, 'Jan', '2027-01-05', '2027-01-20');
        const open = trailOf(2, 'Open', '2027-01-05', '');
        const held = {months: ['2027-02', '2027-03'], answers: Array(2).fill(answerOf('2027-01-01', '2027-02-28', [january, open]))};
        expect(mergedOf(held, utc).trails.map((trail) => trail.id)).toEqual([2]);
    });
});

describe('the toolbar over the timeline', () => {
    it('goes back to the first day\'s month, or the month before from a 1st, and ahead a month', () => {
        expect(monthStep('2026-11-17', -1)).toBe('2026-11');
        expect(monthStep('2026-11-01', -1)).toBe('2026-10');
        expect(monthStep('2027-01-01', -1)).toBe('2026-12');
        expect(monthStep('2026-12-17', 1)).toBe('2027-01');
    });

    it('names the months in view in the viewer\'s language', () => {
        /**
         * The label with Intl's thin spaces read as plain ones.
         *
         * @param {string} first The first day in view.
         * @param {string} last The last day in view.
         * @param {string} language The language tag.
         * @returns {string}
         */
        const label = (first: string, last: string, language: string): string =>
            rangeLabel(first, last, language).replace(/\s/g, ' ');
        expect(label('2026-11-05', '2026-11-20', 'en')).toBe('November 2026');
        expect(label('2026-11-20', '2026-12-05', 'en')).toBe('November – December 2026');
        expect(label('2026-11-20', '2026-12-05', 'pt-BR')).toBe('novembro – dezembro de 2026');
        expect(label('2026-12-20', '2027-01-05', 'pt-BR')).toBe('dezembro de 2026 – janeiro de 2027');
    });
});

describe('the scroller\'s arithmetic', () => {
    // 31 days at 48px with the still column (TRACK at 16px a rem), seen through 1000px.
    const total = (31 + TRACK.label / TRACK.day) * 48;

    it('reads a day\'s width, the days in view and the thumb from three numbers', () => {
        const track = trackOf(240, total, 1000, 31);
        expect(Math.round(track.pitch)).toBe(48);
        expect(Math.round(track.view)).toBe(660);
        expect(Math.round(track.max)).toBe(Math.round(total - 1000));
        expect(track.left).toBe(240);
        expect(track.start).toBeCloseTo(240 / (31 * 48));
        expect(track.span).toBeCloseTo(660 / (31 * 48));
        expect([track.back, track.ahead]).toEqual([true, true]);
        expect(trackOf(0, total, 1000, 31).back).toBe(false);
        expect(trackOf(total - 1000, total, 1000, 31).ahead).toBe(false);
        expect(trackOf(5000, total, 1000, 31).left).toBe(Math.round(total - 1000));
    });

    it('has nothing to scroll when the days fit', () => {
        expect(trackOf(0, 2000, 2000, 31)).toEqual({pitch: 2000 / (31 + 21.25 / 3), view: 2000, max: 0, left: 0, start: 0,
            span: 1, back: false, ahead: false});
    });

    it('opens on today with a day to spare, at the 1st when today is in view there or not held', () => {
        const track = trackOf(0, total, 1000, 31);
        expect(openingLeft(16, track)).toBe(204);
        expect(openingLeft(4, track)).toBe(0);
        expect(openingLeft(-1, track)).toBe(0);
        expect(openingLeft(30, track)).toBe(Math.round(track.max));
    });

    it('pages by the days in view less one, on a day\'s start, never past an end', () => {
        const track = trackOf(204, total, 1000, 31);
        expect(pageLeft(204, 1, track)).toBe(768);
        expect(pageLeft(204, -1, track)).toBe(0);
        expect(pageLeft(768, 1, track)).toBe(track.max);
    });

    it('scrubs from the map, the thumb kept on the line', () => {
        const track = trackOf(0, total, 1000, 31);
        expect(scrubLeft(0, 0, track, 31)).toBe(0);
        expect(scrubLeft(1, 0, track, 31)).toBeCloseTo(track.max);
        expect(scrubLeft(0.5, track.span / 2, track, 31)).toBeCloseTo((0.5 - track.span / 2) * 31 * 48);
    });

    it('puts a month\'s 1st, a course\'s bar and today where the view asks, kept between the ends', () => {
        // Two months held, 61 days.
        const wide = (61 + TRACK.label / TRACK.day) * 48;
        const track = trackOf(0, wide, 1000, 61);
        expect(dayLeft(30, track)).toBe(1440);
        expect(dayLeft(60, track)).toBe(Math.round(track.max));
        expect(targetLeft({kind: 'day', index: 30}, track)).toBe(1440);
        expect(targetLeft({kind: 'step', index: 10}, track)).toBe((10 - STEP_INSET) * 48);
        expect(targetLeft({kind: 'step', index: 1}, track)).toBe(0);
        // Today the 17th of the second month: far past its 1st's view, so the least scroll that shows it.
        expect(targetLeft({kind: 'today', index: 46, month: 30}, track)).toBe(48 * 48 - 660);
        // Today the 5th: its month's 1st at the start shows it.
        expect(todayLeft(34, 30, track)).toBe(1440);
        expect(targetLeft({kind: 'today', index: -1, month: 30}, track)).toBe(1440);
        expect(dayLeft(10, trackOf(0, 2000, 2000, 31))).toBe(0);
    });

    it('moves a target with the first day held', () => {
        expect(shiftedTarget({kind: 'day', index: 31}, 30)).toEqual({kind: 'day', index: 61});
        expect(shiftedTarget({kind: 'step', index: 40}, -31)).toEqual({kind: 'step', index: 9});
        expect(shiftedTarget({kind: 'today', index: 46, month: 30}, 30)).toEqual({kind: 'today', index: 76, month: 60});
        expect(shiftedTarget({kind: 'today', index: -1, month: 30}, 30)).toEqual({kind: 'today', index: -1, month: 60});
    });

    it('tells the days in view and when they near the end of what is held', () => {
        const wide = (61 + TRACK.label / TRACK.day) * 48;
        expect(shownDays(trackOf(204, wide, 1000, 61), 61)).toEqual({first: 4, last: 17});
        expect(shownDays(trackOf(1440, wide, 1000, 61), 61)).toEqual({first: 30, last: 43});
        expect(shownDays(trackOf(0, 0, 0, 61), 61)).toEqual({first: 0, last: 60});
        expect(nearEnd(trackOf(1440, wide, 1000, 61), 61)).toBe(false);
        expect(nearEnd(trackOf(2300, wide, 1000, 61), 61)).toBe(true);
        expect(nearEnd(trackOf(0, 0, 0, 61), 61)).toBe(false);
    });
});

describe('linksOf', () => {
    const november = monthDays('2026-11');

    /**
     * A step between two days of the timeline.
     *
     * @param {number} cmid Its activity.
     * @param {string} from Its first day, '' for none.
     * @param {string} to Its last day, '' for none.
     * @returns {object}
     */
    const leg = (cmid: number, from: string, to: string): TrailStep => step({
        cmid: cmid,
        start: from === '' ? 0 : at(from, 12),
        end: to === '' ? 0 : at(to, 12),
    });

    it('reads a step\'s days unclipped, an open one running on and one with no date being none', () => {
        expect(reachOf(leg(1, '2026-10-30', '2026-11-03'), '2026-11-01', utc)).toEqual({from: -2, to: 2});
        expect(reachOf(leg(1, '2026-11-03', ''), '2026-11-01', utc)).toEqual({from: 2, to: Infinity});
        expect(reachOf(leg(1, '', '2026-11-03'), '2026-11-01', utc)).toEqual({from: 2, to: 2});
        expect(reachOf(leg(1, '', ''), '2026-11-01', utc)).toBeNull();
    });

    it('joins a course to one that starts after it ends by an elbow into its start', () => {
        expect(linksOf([leg(1, '2026-11-02', '2026-11-09'), leg(2, '2026-11-13', '2026-11-23')], november, utc, true)).toEqual([
            {kind: 'horizontal', left: 27, top: 1.5625, width: 0.625, height: 0.125},
            {kind: 'vertical', left: 27.5625, top: 1.5625, width: 0.125, height: 3.375},
            {kind: 'horizontal', left: 27.625, top: 4.8125, width: 8, height: 0.125},
            {kind: 'right', left: 35.625, top: 4.625, width: 0.375, height: 0.5},
        ]);
    });

    it('drops from a course still running into the next, on the day the next starts', () => {
        expect(linksOf([leg(1, '2026-11-02', '2026-11-16'), leg(2, '2026-11-10', '2026-11-24')], november, utc, true)).toEqual([
            {kind: 'dot', left: 27.4375, top: 2.1875, width: 0.375, height: 0.375},
            {kind: 'vertical', left: 27.5625, top: 2.375, width: 0.125, height: 1.375},
            {kind: 'down', left: 27.375, top: 3.75, width: 0.5, height: 0.375},
        ]);
        // An open course is still running whenever the next starts after its start.
        expect(linksOf([leg(1, '2026-11-02', ''), leg(2, '2026-11-20', '2026-11-24')], november, utc, true)
            .map((piece) => piece.kind)).toEqual(['dot', 'vertical', 'down']);
    });

    it('detours on the left into a course that starts before the previous one', () => {
        expect(linksOf([leg(1, '2026-11-11', '2026-11-23'), leg(2, '2026-11-04', '2026-11-15')], november, utc, true)).toEqual([
            {kind: 'horizontal', left: 8.4375, top: 1.5625, width: 21.5625, height: 0.125},
            {kind: 'vertical', left: 8.375, top: 1.5625, width: 0.125, height: 3.375},
            {kind: 'horizontal', left: 8.4375, top: 4.8125, width: 0.1875, height: 0.125},
            {kind: 'right', left: 8.625, top: 4.625, width: 0.375, height: 0.5},
        ]);
    });

    it('runs to the edge of what is held when the other end is in a month not held', () => {
        const ahead = linksOf([leg(1, '2026-11-02', '2026-11-09'), leg(2, '2026-12-10', '2026-12-20')], november, utc, true);
        expect(ahead.map((piece) => piece.kind)).toEqual(['horizontal', 'vertical', 'horizontal']);
        expect(ahead[2].left + ahead[2].width).toBe(30 * TRACK.day);
        const behind = linksOf([leg(1, '2026-10-02', '2026-10-09'), leg(2, '2026-11-13', '2026-11-23')], november, utc, true);
        expect(behind).toEqual([
            {kind: 'horizontal', left: 0, top: 4.8125, width: 35.625, height: 0.125},
            {kind: 'right', left: 35.625, top: 4.625, width: 0.375, height: 0.5},
        ]);
    });

    it('breaks the chain at a course with no date, and draws nothing for a folded trail', () => {
        const a = leg(1, '2026-11-02', '2026-11-09');
        const none = leg(2, '', '');
        const c = leg(3, '2026-11-13', '2026-11-23');
        expect(linksOf([a, none, c], november, utc, true)).toEqual([]);
        // Dates out of order are read as the bar reads them: the start day alone, joined from there.
        const misordered = leg(4, '2026-11-02', '2026-10-20');
        expect(barOf(misordered, november, utc)).toMatchObject({from: 1, to: 1});
        expect(linksOf([misordered, c], november, utc, true)[0]).toEqual({kind: 'horizontal', left: 6, top: 1.5625,
            width: 0.625, height: 0.125});
        expect(linksOf([a, c, none], november, utc, true)).toHaveLength(4);
        expect(linksOf([a, c], november, utc, false)).toEqual([]);
    });
});
