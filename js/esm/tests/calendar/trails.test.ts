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
    BATCH_MONTHS, CHIP_GAP, LOADED_CAP, OPENING_MONTHS, batchOf, MONTH_CAP, NAMED_BAR, OPEN_DAYS, WEEK_LABEL_LEAST, STEP_INSET, TRACK, barOf, datesOf, dayLeft, dayWidth, shiftedTarget, chipLeft, extendLoaded, keptLeft, linksOf,
    loadedOf, mergedOf, monthDays, monthStep, monthsOf, namedBar, nearEnd, openingLeft, pageLeft, rangeLabel, reachOf, readTrail,
    readZoom, scrubLeft, shownDays, targetLeft, todayIndex, todayLeft, trackOf, trailArgs, trailBarOf, weekCells, weekLabel, weekLabelled, capOf,
    windowDays, withTrail, zoomOf, zoomedLeft,
} from '../../src/calendar/trails';
import type {LinkPiece, Trail, TrailAnswer, TrailBar, TrailStep} from '../../src/calendar/trails';
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

    it('draws a step with a start and no end as its first 30 days, the start day and 29 more', () => {
        expect(OPEN_DAYS).toBe(30);
        // From the 1st: the 30th, inside the month, with no edge to cut it.
        expect(barOf(step({start: at('2027-03-01', 12), end: 0}), march, utc))
            .toEqual({from: 0, to: 29, before: false, after: false, open: true, milestone: false});
        // From the 2nd: the 31st, the last day held, which is not past it.
        expect(barOf(step({end: 0}), march, utc)).toEqual({from: 1, to: 30, before: false, after: false, open: true, milestone: false});
    });

    it('cuts an open step\'s 30 days at the end of the months held, as any bar is', () => {
        expect(barOf(step({start: at('2027-03-10', 12), end: 0}), march, utc))
            .toEqual({from: 9, to: 30, before: false, after: true, open: true, milestone: false});
        const held = [...march, ...monthDays('2027-04')];
        expect(barOf(step({start: at('2027-03-10', 12), end: 0}), held, utc))
            .toEqual({from: 9, to: 38, before: false, after: false, open: true, milestone: false});
        // One that began before the days held is cut at their start, and one whose 30 days ended before them is not drawn.
        expect(barOf(step({start: at('2027-02-20', 12), end: 0}), march, utc))
            .toEqual({from: 0, to: 20, before: true, after: false, open: true, milestone: false});
        expect(barOf(step({start: at('2027-01-20', 12), end: 0}), march, utc)).toBeNull();
    });

    it('does not take a step with an expected completion date for open', () => {
        const expected = barOf(step({end: at('2027-03-25', 12), expected: true}), march, utc);
        expect(expected).toEqual({from: 1, to: 24, before: false, after: false, open: false, milestone: true});
        expect(chipLeft(expected as TrailBar, march.length, 'day')).toBeNull();
        // The same step with the date closed: its own end, never its start's 30 days.
        expect(barOf(step({end: at('2027-03-05', 12)}), march, utc)?.open).toBe(false);
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

describe('chipLeft', () => {
    const bar = (to: number, open = true): TrailBar => ({from: 0, to: to, before: false, after: false, open: open, milestone: false});

    it('places the chip a gap after the open bar\'s last day, by the zoom\'s day arithmetic', () => {
        expect(CHIP_GAP).toBe(0.375);
        // The bar's last day is the 11th (index 10): its end is 33rem by days, 11rem by weeks, 2.75rem by months.
        expect(chipLeft(bar(10), 61, 'day')).toBe(33.375);
        expect(chipLeft(bar(10), 61, 'week')).toBe(11.375);
        expect(chipLeft(bar(10), 61, 'month')).toBe(3.125);
    });

    it('draws none for a bar that is not open, or whose 30 days reach the last day held or run past it', () => {
        expect(chipLeft(bar(10, false), 61, 'day')).toBeNull();
        expect(chipLeft(bar(59), 61, 'day')).toBe(180.375);
        expect(chipLeft(bar(60), 61, 'day')).toBeNull();
        const held = barOf(step({start: at('2027-03-10', 12), end: 0}), march, utc) as TrailBar;
        expect([held.after, chipLeft(held, march.length, 'day')]).toEqual([true, null]);
        const loaded = [...march, ...monthDays('2027-04')];
        const next = barOf(step({start: at('2027-03-10', 12), end: 0}), loaded, utc) as TrailBar;
        expect(chipLeft(next, loaded.length, 'day')).toBe((38 + 1) * 3 + 0.375);
    });
});

describe('trailBarOf', () => {
    it('spans a trail\'s period and is never a milestone', () => {
        const trail = {first: at('2027-03-02', 12), last: at('2027-04-10', 12)} as Trail;
        expect(trailBarOf(trail, march, utc)).toEqual({from: 1, to: 30, before: false, after: true, open: false, milestone: false});
    });

    it('ends the line of a trail left open where its open step\'s 30 days end', () => {
        const open = trailOf(1, 'Alfa', '2027-03-02', '');
        expect(trailBarOf(open, march, utc)).toEqual({from: 1, to: 30, before: false, after: false, open: true, milestone: false});
        const early = trailOf(1, 'Alfa', '2027-03-01', '');
        expect(trailBarOf(early, march, utc)?.to).toBe(29);
        // Across the months held the line is cut at their edge, as a bar is.
        const late = trailOf(1, 'Alfa', '2027-03-10', '');
        expect(trailBarOf(late, march, utc)).toEqual({from: 9, to: 30, before: false, after: true, open: true, milestone: false});
        // A later end of another step is the line's end; the trail's own last end says nothing while it is open.
        const closed = step({cmid: 11, start: at('2027-03-01', 12), end: at('2027-03-31', 12)});
        expect(trailBarOf({...early, steps: [early.steps[0], closed]}, march, utc)?.to).toBe(30);
        // An open step starting after the other ends carries the line 29 days past its own start.
        const held = [...march, ...monthDays('2027-04')];
        const second = step({cmid: 12, start: at('2027-03-20', 12), end: 0});
        expect(trailBarOf({...early, steps: [closed, second]}, held, utc)?.to).toBe(48);
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

    it('take an answer of three months in a row, appended or prepended, across a year end', () => {
        const held = loadedOf(answerOf('2026-11-01', '2026-12-31', []));
        const three = answerOf('2027-01-01', '2027-03-31', []);
        const appended = extendLoaded(held, three, capOf('month'));
        expect(appended?.loaded.months).toEqual(['2026-11', '2026-12', '2027-01', '2027-02', '2027-03']);
        expect(appended?.loaded.answers.slice(2)).toEqual([three, three, three]);
        expect(appended?.shift).toBe(0);
        const before = answerOf('2026-08-01', '2026-10-31', []);
        const prepended = extendLoaded(held, before, capOf('month'));
        expect(prepended?.loaded.months).toEqual(['2026-08', '2026-09', '2026-10', '2026-11', '2026-12']);
        // August, September and October: 31 + 30 + 31 days.
        expect(prepended?.shift).toBe(92);
        const yearend = extendLoaded(loadedOf(answerOf('2027-02-01', '2027-03-31', [])), answerOf('2026-11-01', '2027-01-31', []));
        expect(yearend?.loaded.months).toEqual(['2026-11', '2026-12', '2027-01', '2027-02', '2027-03']);
        expect(yearend?.shift).toBe(30 + 31 + 31);
        // A batch that overlaps what is held, or leaves a gap, is refused.
        expect(extendLoaded(held, answerOf('2026-12-01', '2027-02-28', []))).toBeNull();
        expect(extendLoaded(held, answerOf('2027-02-01', '2027-04-30', []))).toBeNull();
        expect(extendLoaded(held, answerOf('2026-07-01', '2026-09-30', []))).toBeNull();
    });

    it('drop the far side past the cap for a batch too, each end telling the days that moved', () => {
        // Ten months held (the cap of the week zoom plus four): a batch of three appended drops the first three.
        const held = loadedOf(answerOf('2026-01-01', '2026-10-31', []));
        const appended = extendLoaded(held, answerOf('2026-11-01', '2027-01-31', []), 12);
        expect(appended?.loaded.months).toHaveLength(12);
        expect(appended?.loaded.months[0]).toBe('2026-02');
        expect(appended?.shift).toBe(-31);
        const full = loadedOf(answerOf('2026-03-01', '2027-02-28', []));
        const limited = extendLoaded(full, answerOf('2027-03-01', '2027-05-31', []), 12);
        expect(limited?.loaded.months[0]).toBe('2026-06');
        // March, April and May go: 31 + 30 + 31 days.
        expect(limited?.shift).toBe(-92);
        const back = extendLoaded(full, answerOf('2025-12-01', '2026-02-28', []), 12);
        expect(back?.loaded.months).toHaveLength(12);
        expect(back?.loaded.months[0]).toBe('2025-12');
        expect(back?.loaded.months[11]).toBe('2026-11');
        expect(back?.shift).toBe(31 + 31 + 28);
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

    it('reads a step\'s days unclipped, an open one running its 30 days and one with no date being none', () => {
        expect(reachOf(leg(1, '2026-10-30', '2026-11-03'), '2026-11-01', utc)).toEqual({from: -2, to: 2});
        expect(reachOf(leg(1, '2026-11-03', ''), '2026-11-01', utc)).toEqual({from: 2, to: 31});
        expect(reachOf(leg(1, '', '2026-11-03'), '2026-11-01', utc)).toEqual({from: 2, to: 2});
        expect(reachOf(leg(1, '', ''), '2026-11-01', utc)).toBeNull();
    });

    it('joins a course to one that starts after it ends by an elbow into its start', () => {
        expect(linksOf([leg(1, '2026-11-02', '2026-11-09'), leg(2, '2026-11-13', '2026-11-23')], november, utc, true)).toEqual([
            {kind: 'horizontal', left: 27, top: 1.6875, width: 0.625, height: 0.125},
            {kind: 'vertical', left: 27.5625, top: 1.6875, width: 0.125, height: 3.625},
            {kind: 'horizontal', left: 27.625, top: 5.1875, width: 8, height: 0.125},
            {kind: 'right', left: 35.625, top: 5, width: 0.375, height: 0.5},
        ]);
    });

    it('drops from a course still running into the next, on the day the next starts', () => {
        expect(linksOf([leg(1, '2026-11-02', '2026-11-16'), leg(2, '2026-11-10', '2026-11-24')], november, utc, true)).toEqual([
            {kind: 'dot', left: 27.4375, top: 2.3125, width: 0.375, height: 0.375},
            {kind: 'vertical', left: 27.5625, top: 2.5, width: 0.125, height: 1.625},
            {kind: 'down', left: 27.375, top: 4.125, width: 0.5, height: 0.375},
        ]);
        // An open course is still running whenever the next starts after its start.
        expect(linksOf([leg(1, '2026-11-02', ''), leg(2, '2026-11-20', '2026-11-24')], november, utc, true)
            .map((piece) => piece.kind)).toEqual(['dot', 'vertical', 'down']);
    });

    it('leaves an open course by the end of its 30 days, with the elbow, drop and detour rules of a closed one', () => {
        const held = [...november, ...monthDays('2026-12')];
        // Opened on the 2nd, it runs to December 1st (day 30): the elbow leaves the end of that day, 93rem in.
        expect(linksOf([leg(1, '2026-11-02', ''), leg(2, '2026-12-20', '2026-12-24')], held, utc, true)[0])
            .toEqual({kind: 'horizontal', left: 93, top: 1.6875, width: 0.625, height: 0.125});
        // The next starting on the 25th, after the 2nd and before the 30 days end: the drop.
        expect(linksOf([leg(1, '2026-11-02', ''), leg(2, '2026-11-25', '2026-11-28')], held, utc, true)
            .map((piece) => piece.kind)).toEqual(['dot', 'vertical', 'down']);
        // The next starting on December 2nd, the day after the 30 days: the elbow, though the course never ends (the line
        // into the next bar is the arrowhead's length, no more, and draws nothing of its own).
        expect(linksOf([leg(1, '2026-11-02', ''), leg(2, '2026-12-02', '2026-12-04')], held, utc, true)
            .map((piece) => piece.kind)).toEqual(['horizontal', 'vertical', 'right']);
        // The next starting before it: the detour on the left.
        expect(linksOf([leg(1, '2026-11-12', ''), leg(2, '2026-11-04', '2026-11-08')], held, utc, true)
            .map((piece) => piece.kind)).toEqual(['horizontal', 'vertical', 'horizontal', 'right']);
    });

    it('detours on the left into a course that starts before the previous one', () => {
        expect(linksOf([leg(1, '2026-11-11', '2026-11-23'), leg(2, '2026-11-04', '2026-11-15')], november, utc, true)).toEqual([
            {kind: 'horizontal', left: 8.4375, top: 1.6875, width: 21.5625, height: 0.125},
            {kind: 'vertical', left: 8.375, top: 1.6875, width: 0.125, height: 3.625},
            {kind: 'horizontal', left: 8.4375, top: 5.1875, width: 0.1875, height: 0.125},
            {kind: 'right', left: 8.625, top: 5, width: 0.375, height: 0.5},
        ]);
    });

    it('runs to the edge of what is held when the other end is in a month not held', () => {
        const ahead = linksOf([leg(1, '2026-11-02', '2026-11-09'), leg(2, '2026-12-10', '2026-12-20')], november, utc, true);
        expect(ahead.map((piece) => piece.kind)).toEqual(['horizontal', 'vertical', 'horizontal']);
        expect(ahead[2].left + ahead[2].width).toBe(30 * TRACK.day);
        const behind = linksOf([leg(1, '2026-10-02', '2026-10-09'), leg(2, '2026-11-13', '2026-11-23')], november, utc, true);
        expect(behind).toEqual([
            {kind: 'horizontal', left: 0, top: 5.1875, width: 35.625, height: 0.125},
            {kind: 'right', left: 35.625, top: 5, width: 0.375, height: 0.5},
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
        expect(linksOf([misordered, c], november, utc, true)[0]).toEqual({kind: 'horizontal', left: 6, top: 1.6875,
            width: 0.625, height: 0.125});
        expect(linksOf([a, c, none], november, utc, true)).toHaveLength(4);
        expect(linksOf([a, c], november, utc, false)).toEqual([]);
    });
});

describe('the zoom (T3c)', () => {
    it('reads a day\'s width per zoom from TRACK, and the address\'s zoom as the server does', () => {
        expect([dayWidth('day'), dayWidth('week'), dayWidth('month')]).toEqual([TRACK.day, TRACK.week, TRACK.month]);
        expect([dayWidth('day'), dayWidth('week'), dayWidth('month')]).toEqual([3, 1, 0.25]);
        // The mockup's three months fit about 400px by months: 92 days at 16px a rem.
        expect(92 * dayWidth('month') * 16).toBe(368);
        expect([zoomOf('week'), zoomOf('month'), zoomOf('day'), zoomOf('Week'), zoomOf(''), zoomOf(null), zoomOf(['week'])])
            .toEqual(['week', 'month', 'day', 'day', 'day', 'day', 'day']);
        expect(readZoom('https://example.com/r.php/theme_dimensions/calendar?view=trail&zoom=month')).toBe('month');
        expect(readZoom('https://example.com/r.php/theme_dimensions/calendar?view=trail&zoom=year')).toBe('day');
        expect(readZoom('https://example.com/r.php/theme_dimensions/calendar?view=trail')).toBe('day');
    });

    it('writes the zoom beside the filters, days never, and takes it out with them', () => {
        const base = 'https://example.com/r.php/theme_dimensions/calendar?view=trail&month=2026-11';
        expect(new URL(withTrail(base, {trail: 7, mine: false}, 'week')).searchParams.get('zoom')).toBe('week');
        expect(new URL(withTrail(base, {trail: 0, mine: false}, 'month')).searchParams.get('zoom')).toBe('month');
        expect(new URL(withTrail(`${base}&zoom=week`, {trail: 0, mine: false}, 'day')).searchParams.has('zoom')).toBe(false);
        expect(new URL(withTrail(`${base}&zoom=week`, {trail: 0, mine: false})).searchParams.has('zoom')).toBe(false);
        expect(new URL(withTrail(`${base}&zoom=week&trail=7`, null, 'week')).searchParams.has('zoom')).toBe(false);
    });

    it('keeps the day at the start of the view across a switch, the same day at the new width', () => {
        // The 12th day held at the start: 11 days in.
        expect(zoomedLeft(11 * 48, 'day', 'week')).toBe(11 * 16);
        expect(zoomedLeft(11 * 16, 'week', 'month')).toBe(11 * 4);
        expect(zoomedLeft(11 * 4, 'month', 'day')).toBe(11 * 48);
        expect(zoomedLeft(0, 'day', 'month')).toBe(0);
        expect(zoomedLeft(500, 'week', 'week')).toBe(500);
        // Read back through the scroller's arithmetic at the new zoom, the first day in view is the same.
        const at = (zoom: 'day' | 'week' | 'month', left: number) => {
            const pitch = dayWidth(zoom) * 16;
            return shownDays(trackOf(left, (61 + TRACK.label / dayWidth(zoom)) * pitch, 600, 61, dayWidth(zoom)), 61).first;
        };
        expect(at('day', 20 * 48)).toBe(20);
        expect(at('week', zoomedLeft(20 * 48, 'day', 'week'))).toBe(20);
    });

    it('reads the scroller at the zoom\'s day width', () => {
        const total = (61 + TRACK.label / TRACK.week) * 16;
        const track = trackOf(160, total, 1000, 61, TRACK.week);
        expect(Math.round(track.pitch)).toBe(16);
        expect(Math.round(track.view)).toBe(660);
        expect(shownDays(track, 61)).toEqual({first: 10, last: 51});
        expect(dayLeft(10, track)).toBe(160);
        expect(dayLeft(30, track)).toBe(Math.round(track.max));
    });

    it('names a bar only when it is wide enough for it, at every zoom', () => {
        const bar = (from: number, to: number) => ({from: from, to: to, before: false, after: false, open: false, milestone: false});
        expect(NAMED_BAR).toBe(3.5);
        // By days the least is one day: a one-day bar keeps its name there (3rem, under NAMED_BAR).
        expect([namedBar(bar(4, 4), 'day'), namedBar(bar(4, 5), 'day')]).toEqual([true, true]);
        expect(TRACK.day).toBeLessThan(NAMED_BAR);
        // By weeks, three days are not, four are.
        expect([namedBar(bar(4, 6), 'week'), namedBar(bar(4, 7), 'week')]).toEqual([false, true]);
        // By months, thirteen days are not, fourteen are.
        expect([namedBar(bar(0, 12), 'month'), namedBar(bar(0, 13), 'month')]).toEqual([false, true]);
    });
});

describe('capOf and weekLabelled (T3c follow-up)', () => {
    it('holds twelve months by months and six by days and weeks', () => {
        expect([capOf('day'), capOf('week'), capOf('month')]).toEqual([LOADED_CAP, LOADED_CAP, MONTH_CAP]);
        expect([LOADED_CAP, MONTH_CAP]).toEqual([6, 12]);
    });

    it('asks three months at a time by months, as many as the cap leaves room for, and one month otherwise', () => {
        expect([OPENING_MONTHS, BATCH_MONTHS]).toEqual([2, 3]);
        expect([2, 5, 8, 9].map((held) => batchOf('month', held))).toEqual([3, 3, 3, 3]);
        expect([10, 11, 12, 14].map((held) => batchOf('month', held))).toEqual([2, 1, 1, 1]);
        expect(['day', 'week'].map((zoom) => [2, 5].map((held) => batchOf(zoom as 'day' | 'week', held)))).toEqual([[1, 1], [1, 1]]);
    });

    it('drops the far side to the month zoom\'s cap only past twelve, telling the shift', () => {
        let held = loadedOf(answerOf('2027-01-01', '2027-02-28', []));
        ['2027-03', '2027-04', '2027-05', '2027-06', '2027-07', '2027-08', '2027-09', '2027-10', '2027-11', '2027-12'].forEach(
            (month) => {
                held = (extendLoaded(held, answerOf(`${month}-01`, `${month}-28`, []), capOf('month')) as {loaded: typeof held}).loaded;
            });
        expect(held.months).toHaveLength(12);
        const next = extendLoaded(held, answerOf('2028-01-01', '2028-01-31', []), capOf('month'));
        expect(next?.loaded.months[0]).toBe('2027-02');
        expect(next?.shift).toBe(-31);
        // Back at a weeks cap the same twelve drop to six, the first seven months going with their days told.
        const back = extendLoaded(held, answerOf('2028-01-01', '2028-01-31', []), capOf('week'));
        expect(back?.loaded.months).toHaveLength(6);
        expect(back?.loaded.months[0]).toBe('2027-08');
        expect(back?.shift).toBe(-(31 + 28 + 31 + 30 + 31 + 30 + 31));
    });

    it('draws a week\'s label only where the cell is wide enough, by weeks', () => {
        const cell = (length: number) => ({at: 0, length: length, first: '2026-11-01', last: '2026-11-01'});
        expect(WEEK_LABEL_LEAST).toBe(2.5);
        expect([1, 2, 3, 7].map((length) => weekLabelled(cell(length), 'week'))).toEqual([false, false, true, true]);
        // Off the weeks the row has no labels.
        expect([weekLabelled(cell(7), 'month'), weekLabelled(cell(7), 'day')]).toEqual([false, false]);
    });
});

describe('weekCells (T3c)', () => {
    it('cuts the days held into weeks from the viewer\'s first weekday, the first and the last partial', () => {
        // November 2026 begins on a Sunday: from Monday, its 1st is a week of its own.
        const monday = weekCells(monthDays('2026-11'), 1);
        expect(monday.slice(0, 3)).toEqual([
            {at: 0, length: 1, first: '2026-11-01', last: '2026-11-01'},
            {at: 1, length: 7, first: '2026-11-02', last: '2026-11-08'},
            {at: 8, length: 7, first: '2026-11-09', last: '2026-11-15'},
        ]);
        expect(monday[monday.length - 1]).toEqual({at: 29, length: 1, first: '2026-11-30', last: '2026-11-30'});
        expect(monday.reduce((sum, cell) => sum + cell.length, 0)).toBe(30);
        // From Sunday, the 1st opens a whole week.
        expect(weekCells(monthDays('2026-11'), 0)[0]).toEqual({at: 0, length: 7, first: '2026-11-01', last: '2026-11-07'});
    });

    it('runs a week across a month and a year', () => {
        const held = windowDays(['2026-11', '2026-12', '2027-01']);
        const cells = weekCells(held, 1);
        expect(cells.find((cell) => cell.first === '2026-11-30')).toEqual({at: 29, length: 7, first: '2026-11-30',
            last: '2026-12-06'});
        expect(cells.find((cell) => cell.first === '2026-12-28')).toEqual({at: 57, length: 7, first: '2026-12-28',
            last: '2027-01-03'});
        expect(cells[cells.length - 1]).toEqual({at: 85, length: 7, first: '2027-01-25', last: '2027-01-31'});
        expect(cells.reduce((sum, cell) => sum + cell.length, 0)).toBe(held.length);
        expect(weekCells([], 1)).toEqual([]);
    });
});

describe('datesOf (T3c)', () => {
    /**
     * A course's dates line, Intl's thin spaces read as plain ones.
     *
     * @param {string} from The start, '' for none.
     * @param {string} to The end, '' for none.
     * @param {string} language The language tag.
     * @returns {object}
     */
    const dates = (from: string, to: string, language: string) => {
        const got = datesOf(from === '' ? 0 : at(from, 12), to === '' ? 0 : at(to, 12), utc, language);
        return {kind: got.kind, text: got.text.replace(/\s/g, ' ')};
    };

    it('says both ends as one range, one month name in a month and one year in a year', () => {
        expect(dates('2026-11-02', '2026-11-20', 'pt-BR')).toEqual({kind: 'range', text: '2–20 nov. 2026'});
        expect(dates('2026-11-16', '2026-12-12', 'pt-BR')).toEqual({kind: 'range', text: '16 nov. – 12 dez. 2026'});
        expect(dates('2026-12-15', '2027-01-20', 'pt-BR')).toEqual({kind: 'range', text: '15 dez. 2026 – 20 jan. 2027'});
        expect(dates('2026-11-02', '2026-11-20', 'en-GB')).toEqual({kind: 'range', text: '2–20 Nov 2026'});
        expect(dates('2026-12-15', '2027-01-20', 'en-GB')).toEqual({kind: 'range', text: '15 Dec 2026 – 20 Jan 2027'});
        // The language's own order.
        expect(dates('2026-11-16', '2026-12-12', 'en')).toEqual({kind: 'range', text: 'Nov 16 – Dec 12, 2026'});
        // One day, and dates out of order read as the start day, as the bar draws them.
        expect(dates('2026-11-02', '2026-11-02', 'pt-BR')).toEqual({kind: 'range', text: '2 nov. 2026'});
        expect(dates('2026-11-02', '2026-10-20', 'pt-BR')).toEqual({kind: 'range', text: '2 nov. 2026'});
    });

    it('gives a start alone, an end alone, or none, for the view to word', () => {
        expect(dates('2026-12-01', '', 'pt-BR')).toEqual({kind: 'from', text: '1 dez. 2026'});
        expect(dates('', '2026-11-20', 'en-GB')).toEqual({kind: 'until', text: '20 Nov 2026'});
        expect(dates('', '', 'en')).toEqual({kind: 'none', text: ''});
    });

    it('reads the days in the viewer\'s timezone', () => {
        // 02:00 UTC on the 2nd is still the 1st in São Paulo.
        const early = Date.UTC(2026, 10, 2, 2) / 1000;
        expect(datesOf(early, 0, zone('America/Sao_Paulo', 'pt-BR'), 'pt-BR').text.replace(/\s/g, ' ')).toBe('1 nov. 2026');
        expect(datesOf(early, 0, utc, 'pt-BR').text.replace(/\s/g, ' ')).toBe('2 nov. 2026');
    });
});

describe('linksOf at week and month zoom (T3c)', () => {
    const november = monthDays('2026-11');

    /**
     * A step between two days of the timeline.
     *
     * @param {number} cmid Its activity.
     * @param {string} from Its first day.
     * @param {string} to Its last day.
     * @returns {object}
     */
    const leg = (cmid: number, from: string, to: string): TrailStep => step({cmid: cmid, start: at(from, 12), end: at(to, 12)});

    /**
     * Whether no piece has a negative or empty measure.
     *
     * @param {Array} pieces The pieces.
     * @returns {boolean}
     */
    const whole = (pieces: LinkPiece[]): boolean => pieces.every((piece) => piece.left >= 0 && piece.top >= 0
        && piece.width > 0 && piece.height > 0);

    it('narrows the elbow to half a narrow gap, never under its least', () => {
        const pair = [leg(1, '2026-11-02', '2026-11-09'), leg(2, '2026-11-13', '2026-11-23')];
        // By weeks the gap is 3rem: the elbow keeps the day zoom's offset.
        expect(linksOf(pair, november, utc, true, 'week')).toEqual([
            {kind: 'horizontal', left: 9, top: 1.6875, width: 0.625, height: 0.125},
            {kind: 'vertical', left: 9.5625, top: 1.6875, width: 0.125, height: 3.625},
            {kind: 'horizontal', left: 9.625, top: 5.1875, width: 2, height: 0.125},
            {kind: 'right', left: 11.625, top: 5, width: 0.375, height: 0.5},
        ]);
        // By months the gap is .75rem: the elbow comes half way, and the line into the next bar has no room left.
        expect(linksOf(pair, november, utc, true, 'month')).toEqual([
            {kind: 'horizontal', left: 2.25, top: 1.6875, width: 0.375, height: 0.125},
            {kind: 'vertical', left: 2.5625, top: 1.6875, width: 0.125, height: 3.625},
            {kind: 'right', left: 2.625, top: 5, width: 0.375, height: 0.5},
        ]);
    });

    it('joins two courses on following days with an elbow at its least, at every zoom', () => {
        const pair = [leg(1, '2026-11-02', '2026-11-09'), leg(2, '2026-11-10', '2026-11-23')];
        ['day', 'week', 'month'].forEach((zoom) => {
            const pieces = linksOf(pair, november, utc, true, zoom as 'day' | 'week' | 'month');
            const start = 9 * dayWidth(zoom as 'day' | 'week' | 'month');
            expect(whole(pieces)).toBe(true);
            expect(pieces[0]).toEqual({kind: 'horizontal', left: start, top: 1.6875, width: 0.1875, height: 0.125});
            expect(pieces[pieces.length - 1]).toEqual({kind: 'right', left: start + 0.1875, top: 5, width: 0.375,
                height: 0.5});
        });
    });

    it('scales the drop into the day with the day\'s width', () => {
        const pair = [leg(1, '2026-11-02', '2026-11-16'), leg(2, '2026-11-10', '2026-11-24')];
        // A day is 3rem: the day zoom's offset; 1rem: half of it; .25rem: the least.
        expect(linksOf(pair, november, utc, true, 'day')[0].left).toBe(27.625 - 0.1875);
        expect(linksOf(pair, november, utc, true, 'week')).toEqual([
            {kind: 'dot', left: 9.3125, top: 2.3125, width: 0.375, height: 0.375},
            {kind: 'vertical', left: 9.4375, top: 2.5, width: 0.125, height: 1.625},
            {kind: 'down', left: 9.25, top: 4.125, width: 0.5, height: 0.375},
        ]);
        expect(linksOf(pair, november, utc, true, 'month').map((piece) => piece.left)).toEqual([2.1875, 2.3125, 2.125]);
    });

    it('draws no piece of a negative or empty length at any zoom, a detour included', () => {
        const chains = [
            [leg(1, '2026-11-02', '2026-11-09'), leg(2, '2026-11-10', '2026-11-12')],
            [leg(1, '2026-11-02', '2026-11-09'), leg(2, '2026-11-11', '2026-11-12')],
            [leg(1, '2026-11-11', '2026-11-23'), leg(2, '2026-11-04', '2026-11-15')],
            [leg(1, '2026-11-02', '2026-11-16'), leg(2, '2026-11-03', '2026-11-24')],
        ];
        chains.forEach((chain) => ['day', 'week', 'month'].forEach((zoom) => {
            const pieces = linksOf(chain, november, utc, true, zoom as 'day' | 'week' | 'month');
            expect(pieces.length).toBeGreaterThan(0);
            expect(whole(pieces)).toBe(true);
            // Every piece inside the days held at this zoom.
            expect(pieces.every((piece) => piece.left + piece.width <= 30 * dayWidth(zoom as 'day' | 'week' | 'month')))
                .toBe(true);
        }));
    });
});

describe('the review of T3c', () => {
    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('labels a week by its month, and one that crosses a month with the end\'s month too', () => {
        const label = (cell: {at: number, length: number, first: string, last: string}, language: string) => {
            const got = weekLabel(cell, language);
            return [got.kicker, got.days].map((text) => text.replace(/\s/g, ' '));
        };
        expect(label({at: 1, length: 7, first: '2026-11-02', last: '2026-11-08'}, 'pt-BR')).toEqual(['nov.', '2–8']);
        expect(label({at: 0, length: 7, first: '2026-10-26', last: '2026-11-01'}, 'pt-BR')).toEqual(['out.', '26–1 nov.']);
        expect(label({at: 0, length: 7, first: '2026-12-28', last: '2027-01-03'}, 'en')).toEqual(['Dec', '28–3 Jan']);
        expect(label({at: 29, length: 1, first: '2026-11-30', last: '2026-11-30'}, 'en')).toEqual(['Nov', '30']);
    });

    it('keeps a range\'s separator that is a word, compacting only inside one date', () => {
        // A language whose range literal is a word ("2 a 20 de nov. de 2026"): only the dates' own words go.
        jest.spyOn(Intl.DateTimeFormat.prototype, 'formatRangeToParts').mockReturnValue([
            {type: 'day', value: '2', source: 'startRange'},
            {type: 'literal', value: ' a ', source: 'shared'},
            {type: 'day', value: '20', source: 'endRange'},
            {type: 'literal', value: ' de ', source: 'shared'},
            {type: 'month', value: 'nov.', source: 'shared'},
            {type: 'literal', value: ' de ', source: 'shared'},
            {type: 'year', value: '2026', source: 'shared'},
        ] as Intl.DateTimeRangeFormatPart[]);
        expect(datesOf(at('2026-11-02', 12), at('2026-11-20', 12), utc, 'pt-BR').text).toBe('2 a 20 nov. 2026');
        // Between two months, the word stays as well, and each side's own words go.
        jest.spyOn(Intl.DateTimeFormat.prototype, 'formatRangeToParts').mockReturnValue([
            {type: 'day', value: '16', source: 'startRange'},
            {type: 'literal', value: '. ', source: 'startRange'},
            {type: 'month', value: 'Nov', source: 'startRange'},
            {type: 'literal', value: ' bis ', source: 'shared'},
            {type: 'day', value: '12', source: 'endRange'},
            {type: 'literal', value: ' de ', source: 'endRange'},
            {type: 'month', value: 'Dez', source: 'endRange'},
            {type: 'literal', value: ' ', source: 'shared'},
            {type: 'year', value: '2026', source: 'shared'},
        ] as Intl.DateTimeRangeFormatPart[]);
        expect(datesOf(at('2026-11-16', 12), at('2026-12-12', 12), utc, 'de').text).toBe('16. Nov bis 12 Dez 2026');
    });

    it('scales the detour with the day, its turn kept inside the days held, no piece of a negative length', () => {
        const november = monthDays('2026-11');
        const leg = (cmid: number, from: string, to: string): TrailStep => step({cmid: cmid, start: at(from, 12), end: at(to, 12)});
        const pair = [leg(1, '2026-11-11', '2026-11-23'), leg(2, '2026-11-04', '2026-11-15')];
        // By weeks the gap is 7rem: the day zoom's room before the next bar.
        expect(linksOf(pair, november, utc, true, 'week')).toEqual([
            {kind: 'horizontal', left: 2.4375, top: 1.6875, width: 7.5625, height: 0.125},
            {kind: 'vertical', left: 2.375, top: 1.6875, width: 0.125, height: 3.625},
            {kind: 'horizontal', left: 2.4375, top: 5.1875, width: 0.1875, height: 0.125},
            {kind: 'right', left: 2.625, top: 5, width: 0.375, height: 0.5},
        ]);
        // By months the next bar starts .75rem in: the turn keeps the room the days held leave before it.
        expect(linksOf(pair, november, utc, true, 'month')).toEqual([
            {kind: 'horizontal', left: 0.1875, top: 1.6875, width: 2.3125, height: 0.125},
            {kind: 'vertical', left: 0.125, top: 1.6875, width: 0.125, height: 3.625},
            {kind: 'horizontal', left: 0.1875, top: 5.1875, width: 0.1875, height: 0.125},
            {kind: 'right', left: 0.375, top: 5, width: 0.375, height: 0.5},
        ]);
        // A next course starting the 2nd, .25rem in: the turn moves in to stay inside the days held, and the line down is
        // still drawn (at LINK.detour it fell before the first day and was cut away).
        const early = [leg(1, '2026-11-11', '2026-11-23'), leg(2, '2026-11-02', '2026-11-15')];
        const pieces = linksOf(early, november, utc, true, 'month');
        expect(pieces.map((piece) => piece.kind)).toEqual(['horizontal', 'vertical', 'right']);
        expect(pieces.every((piece) => piece.left >= 0 && piece.width > 0 && piece.height > 0)).toBe(true);
        expect(pieces[1]).toEqual({kind: 'vertical', left: 0, top: 1.6875, width: 0.125, height: 3.625});
    });
});
