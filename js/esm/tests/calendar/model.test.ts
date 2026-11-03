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
 * Tests for the calendar's data: the address round trip, the two seams, the grid's days.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {gridWeeks, monthAfter} from '../../src/calendar/days';
import {
    DEFAULT_STATE, address, cleared, emptyAnswer, filtered, itemHref, keptAfter, narrows, normalise, opens, primaryOf, query,
    rangeArgs, readAddress, safeHref, shownOn, withDay,
} from '../../src/calendar/model';
import type {Course, State} from '../../src/calendar/model';
import {fill} from '../../src/calendar/strings';
import {coursePageLabel} from '../../src/calendar/EventPopover';
import {STRINGS} from './fixture';
import {item} from './fixture';

const PAGE = 'http://localhost/r.php/theme_dimensions/calendar';

describe('the address', () => {
    it('a bare address names no state', () => {
        expect(readAddress(`${PAGE}?month=2026-11`)).toEqual({month: '2026-11', state: null});
    });

    it('reads back the state it was written from', () => {
        const state: State = {
            off: ['group', 'open'],
            categoryid: 7,
            recursive: false,
            model: [2],
            modality: [1, 3],
            city: 'Recife',
            actiononly: true,
            colour: 'category',
        };
        const read = readAddress(address(PAGE, '2026-12', state));
        expect(read).toEqual({month: '2026-12', state: normalise(state)});
    });

    it('names every key off with one empty member, as the server reads it', () => {
        const state = {...DEFAULT_STATE, off: [...['site', 'category', 'course', 'group', 'user', 'activity', 'other', 'open']]} as State;
        expect(query(state).filter(([name]) => name === 'keys[]')).toEqual([['keys[]', '']]);
        expect(readAddress(address(PAGE, '2026-11', state)).state?.off).toHaveLength(8);
    });

    it('lists no key while every key is on, and the default state still names itself', () => {
        expect(query(DEFAULT_STATE)).toEqual([['recursive', '1'], ['colour', 'key']]);
    });

    it('drops a malformed month', () => {
        expect(readAddress(`${PAGE}?month=2026-13`).month).toBeNull();
    });

    it('carries the open day, which names no state', () => {
        const written = new URL(address(PAGE, '2026-11', DEFAULT_STATE, '2026-11-17'));
        expect(written.searchParams.get('day')).toBe('2026-11-17');
        expect(new URL(address(PAGE, '2026-11', DEFAULT_STATE)).searchParams.has('day')).toBe(false);
        expect(readAddress(`${PAGE}?month=2026-11&day=2026-11-17`).state).toBeNull();
    });

    it('sets and takes out the open day and leaves the rest of the address as written', () => {
        const opened = withDay(`${PAGE}?month=2026-11`, '2026-11-17');
        expect(opened).toBe(`${PAGE}?month=2026-11&day=2026-11-17`);
        expect(withDay(opened, '2026-11-18')).toBe(`${PAGE}?month=2026-11&day=2026-11-18`);
        expect(withDay(opened, '')).toBe(`${PAGE}?month=2026-11`);
    });
});

describe('the seams', () => {
    it('an item opens its own page, else its course\'s, else the home', () => {
        const courses = new Map([[4, {url: 'https://example.com/course/view.php?id=4'} as never]]);
        expect(itemHref(item({id: 1, url: 'https://example.com/mod'}), courses, 'https://example.com/')).toBe('https://example.com/mod');
        expect(itemHref(item({id: 1, url: '', courseid: 4}), courses, 'https://example.com/'))
            .toBe('https://example.com/course/view.php?id=4');
        expect(itemHref(item({id: 1, url: '', courseid: 9}), courses, 'https://example.com/')).toBe('https://example.com/');
    });

    it('an item opens a page of its own only with its own address or a course of the answer', () => {
        const courses = new Map([[4, {url: 'https://example.com/course/view.php?id=4'} as never]]);
        expect(opens(item({id: 1, url: 'https://example.com/mod'}), courses)).toBe(true);
        expect(opens(item({id: 1, url: '', courseid: 4}), courses)).toBe(true);
        expect(opens(item({id: 1, url: '', courseid: 0}), courses)).toBe(false);
        expect(opens(item({id: 1, url: '', courseid: 1}), courses)).toBe(false);
        expect(opens(item({id: 1, url: 'javascript:alert(1)', courseid: 9}), courses)).toBe(false);
    });
});

describe('the popover\'s primary action', () => {
    const HOME = 'https://example.com/';
    /**
     * An event course of the answer's dictionary.
     *
     * @param {string} join What the viewer may do now.
     * @returns {object} The course.
     */
    const course = (join: string): Course => ({
        id: 4, fullname: 'Seminar', shortname: 'S', categoryid: 3, url: 'https://example.com/course/view.php?id=4', image: '',
        model: 2, modality: 1, city: '', venue: '', landingurl: 'https://example.com/landing/4', enrolled: false, participants: 8,
        join: join,
    });

    it('is core\'s action, under its name, when the viewer can act now', () => {
        const submit = 'https://example.com/submit';
        const action = item({id: 1, actionevent: true, action: {name: 'Add submission', url: submit, itemcount: 1, actionable: true}});
        expect(primaryOf(action, new Map(), HOME)).toEqual({kind: 'action', name: 'Add submission', url: submit});
    });

    it('is "Open" for an action the viewer cannot take now', () => {
        const waiting = item({id: 1, url: 'https://example.com/mod', actionevent: true, action: {name: 'Add submission',
            url: 'https://example.com/submit', itemcount: 1, actionable: false}});
        expect(primaryOf(waiting, new Map(), HOME)).toEqual({kind: 'open', url: 'https://example.com/mod'});
    });

    it('is the way in for an event course the viewer may join, to its landing page', () => {
        const entry = item({id: 4, source: 'course', key: 'open', courseid: 4, url: 'https://example.com/landing/4'});
        ['open', 'free', 'key'].forEach((join) => {
            expect(primaryOf(entry, new Map([[4, course(join)]]), HOME))
                .toEqual({kind: 'join', join: join, url: 'https://example.com/landing/4'});
        });
    });

    it('is none for a personal event and a site event: never the site\'s home', () => {
        const personal = item({id: 6, source: 'core', key: 'user', courseid: 0, url: ''});
        expect(primaryOf(personal, new Map(), HOME)).toEqual({kind: 'none'});
        const site = item({id: 7, source: 'core', key: 'site', courseid: 1, url: ''});
        expect(primaryOf(site, new Map([[4, course('')]]), HOME)).toEqual({kind: 'none'});
        const own = item({id: 8, source: 'core', key: 'user', courseid: 0, url: 'https://example.com/mod'});
        expect(primaryOf(own, new Map(), HOME)).toEqual({kind: 'open', url: 'https://example.com/mod'});
    });

    it('is "Open" for an event course the viewer may not join, and for a course event that names a join', () => {
        const entry = item({id: 4, source: 'course', key: 'course', courseid: 4, url: 'https://example.com/landing/4'});
        expect(primaryOf(entry, new Map([[4, course('')]]), HOME)).toEqual({kind: 'open', url: 'https://example.com/landing/4'});
        const event = item({id: 5, source: 'core', key: 'course', courseid: 4, url: 'https://example.com/event/5'});
        expect(primaryOf(event, new Map([[4, course('open')]]), HOME)).toEqual({kind: 'open', url: 'https://example.com/event/5'});
    });
});

describe('an address from the answer', () => {
    it('is kept only as a web address', () => {
        expect(safeHref('https://example.com/a', '#')).toBe('https://example.com/a');
        expect(safeHref('/mod/assign/view.php?id=3', '#')).toBe('/mod/assign/view.php?id=3');
        expect(safeHref('javascript:alert(1)', '#')).toBe('#');
        expect(safeHref('data:text/html,x', '#')).toBe('#');
        expect(safeHref('', '#')).toBe('#');
    });

    it('never leads a primary action to a script', () => {
        const bad = 'javascript:alert(1)';
        const action = item({id: 1, url: 'https://example.com/mod', actionevent: true,
            action: {name: 'Add submission', url: bad, itemcount: 1, actionable: true}});
        expect(primaryOf(action, new Map(), 'https://example.com/')).toEqual({kind: 'open', url: 'https://example.com/mod'});
        expect(itemHref(item({id: 2, url: bad}), new Map(), 'https://example.com/')).toBe('https://example.com/');
    });
});

describe('the filters applied', () => {
    it('are any key off, any "More filters" value and any category scope but the whole calendar', () => {
        expect(filtered(DEFAULT_STATE)).toBe(false);
        expect(filtered({...DEFAULT_STATE, colour: 'category'})).toBe(false);
        const each: Array<Partial<typeof DEFAULT_STATE>> = [
            {off: ['site']}, {model: [1]}, {modality: [2]}, {city: 'Recife'}, {actiononly: true}, {categoryid: 5},
            {recursive: false},
        ];
        each.forEach((fields) => expect(filtered({...DEFAULT_STATE, ...fields})).toBe(true));
    });

    it('are put back in one change, the colouring kept', () => {
        const busy = {
            ...DEFAULT_STATE, off: ['site', 'group'] as typeof DEFAULT_STATE.off, model: [1], modality: [2], city: 'Recife',
            actiononly: true, categoryid: 5, recursive: false, colour: 'category' as const,
        };
        const back = normalise({...busy, ...cleared()});
        expect(filtered(back)).toBe(false);
        expect(back.colour).toBe('category');
    });
});

describe('the range\'s arguments', () => {
    it('ask for the keys that are on and the window', () => {
        const args = rangeArgs('2026-11-01', '2026-12-05', {...DEFAULT_STATE, off: ['site']});
        expect(args.keys).toEqual(['category', 'course', 'group', 'user', 'activity', 'other', 'open']);
        expect([args.from, args.to]).toEqual(['2026-11-01', '2026-12-05']);
    });

    it('every key off answers the window\'s days at zero', () => {
        const empty = emptyAnswer(['2026-11-01', '2026-11-02']);
        expect(empty.events).toEqual([]);
        expect(empty.days.map((day) => day.counts.site)).toEqual([0, 0]);
    });
});

describe('the grid', () => {
    it('fills whole weeks with the neighbouring months\' days', () => {
        const weeks = gridWeeks('2026-11', 0);
        expect(weeks).toHaveLength(5);
        expect(weeks[0][0]).toBe('2026-11-01');
        expect(weeks[4][6]).toBe('2026-12-05');
        const monday = gridWeeks('2026-11', 1);
        expect(monday[0][0]).toBe('2026-10-26');
        expect(monday[monday.length - 1][6]).toBe('2026-12-06');
    });

    it('moves months across a year', () => {
        expect(monthAfter('2026-12', 1)).toBe('2027-01');
        expect(monthAfter('2026-01', -1)).toBe('2025-12');
    });
});

describe('fill', () => {
    it('fills core\'s placeholders', () => {
        expect(fill('{$a} items', 3)).toBe('3 items');
        expect(fill('{$a->name}, {$a->span}', {name: 'A', span: 'B'})).toBe('A, B');
    });
});

describe('"More filters": every option on means every option (R1c)', () => {
    const CHOICES = [{value: 1, label: 'Course'}, {value: 2, label: 'Event'}, {value: 3, label: 'Trail'}];

    it('draws an empty list as every box on, and a list as its members', () => {
        expect(CHOICES.map((option) => shownOn([], option.value))).toEqual([true, true, true]);
        expect(CHOICES.map((option) => shownOn([2], option.value))).toEqual([false, true, false]);
    });

    it('writes the others when one is unchecked, and the empty list again when every one is back', () => {
        expect(keptAfter([], CHOICES, 2, false)).toEqual([1, 3]);
        expect(keptAfter([1, 3], CHOICES, 1, false)).toEqual([3]);
        expect(keptAfter([3], CHOICES, 1, true)).toEqual([1, 3]);
        expect(keptAfter([1, 3], CHOICES, 2, true)).toEqual([]);
    });

    it('never writes "none": unchecking the last box on keeps the list', () => {
        expect(keptAfter([3], CHOICES, 3, false)).toEqual([3]);
    });

    it('narrows only with a list that misses an option offered', () => {
        expect(narrows([], CHOICES)).toBe(false);
        expect(narrows([1, 2, 3], CHOICES)).toBe(false);
        expect(narrows([1, 3], CHOICES)).toBe(true);
        expect(filtered({...DEFAULT_STATE, model: [1, 2, 3]}, {model: CHOICES, modality: []})).toBe(false);
        expect(filtered({...DEFAULT_STATE, model: [1]}, {model: CHOICES, modality: []})).toBe(true);
        expect(filtered({...DEFAULT_STATE, model: [1, 2, 3]})).toBe(true);
    });
});

describe('the course button follows the course\'s model (R1c)', () => {
    // The known models' option labels differ from their strings here, so the string, not the label, is what is read.
    const MODELS = [{value: 1, label: 'Programme'}, {value: 2, label: 'Happening'}, {value: 3, label: 'Trail'}];
    const course = (model: number): Course => ({id: 4, fullname: 'A', shortname: 'A', categoryid: 0, url: '', image: '',
        model: model, modality: 0, city: '', venue: '', landingurl: '', enrolled: false, participants: null, join: ''});

    it('names each known model with its own string, any other with its option label', () => {
        const strings = STRINGS as Parameters<typeof coursePageLabel>[2];
        expect(coursePageLabel(course(0), MODELS, strings)).toBe('Course page');
        expect(coursePageLabel(course(1), MODELS, strings)).toBe('Course page');
        expect(coursePageLabel(course(2), MODELS, strings)).toBe('Event page');
        expect(coursePageLabel(course(3), MODELS, strings)).toBe('Trail page');
        expect(coursePageLabel(course(9), MODELS, strings)).toBe('Course page');
    });
});
