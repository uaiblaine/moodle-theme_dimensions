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
 * Tests for what the page may offer to edit and the instants core is handed (PROPOSAL.md section 23.3).
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {zone} from '../../src/calendar/days';
import type {Edit} from '../../src/calendar/model';
import {
    MOVE_MINUTE,
    dayStart,
    dropRefusal,
    editOf,
    editsOf,
    instantOf,
    toolbarStart,
} from '../../src/calendar/moves';
import {answer, at, item} from './fixture';

/**
 * An entry of core's answer.
 *
 * @param {object} fields What differs from an editable, deletable, draggable event with no limits.
 * @returns {object} The entry.
 */
const edit = (fields: Partial<Edit> & {id: number}): Edit => ({
    edit: true,
    delete: true,
    drag: true,
    count: 1,
    minday: '',
    maxday: '',
    minerror: '',
    maxerror: '',
    ...fields,
});

describe('core\'s answers by item', () => {
    it('answers only core\'s own events that core listed', () => {
        const edits = editsOf({...answer([]), editing: [edit({id: 3})]});
        expect(editOf(edits, item({id: 3}))).toEqual(edit({id: 3}));
        expect(editOf(edits, item({id: 4}))).toBeNull();
        // An event course with the same id is not core's event.
        expect(editOf(edits, item({id: 3, source: 'course'}))).toBeNull();
    });

    it('reads an answer without the list as none', () => {
        expect(editsOf(answer([])).size).toBe(0);
    });
});

describe('the drop\'s guard', () => {
    it('refuses a day before the earliest or after the latest, as core does before asking', () => {
        const limited = edit({id: 1, minday: '2026-11-05', maxday: '2026-11-20'});
        expect(dropRefusal(limited, '2026-11-04')).toBe('min');
        expect(dropRefusal(limited, '2026-11-05')).toBeNull();
        expect(dropRefusal(limited, '2026-11-20')).toBeNull();
        expect(dropRefusal(limited, '2026-11-21')).toBe('max');
    });

    it('allows every day without limits', () => {
        expect(dropRefusal(edit({id: 1}), '1999-01-01')).toBeNull();
        expect(dropRefusal(edit({id: 1}), '2999-12-31')).toBeNull();
    });
});

describe('the instants core is handed', () => {
    it('reads a day\'s wall clock in the viewer\'s zone, never the browser\'s', () => {
        expect(instantOf('2026-11-10', MOVE_MINUTE, 'UTC')).toBe(at('2026-11-10', 12));
        expect(instantOf('2026-11-10', MOVE_MINUTE, 'America/Sao_Paulo')).toBe(at('2026-11-10', 15));
        expect(instantOf('2026-11-10', MOVE_MINUTE, 'Pacific/Kiritimati')).toBe(at('2026-11-09', 22));
        expect(instantOf('2026-11-10', 30, 'Asia/Kolkata')).toBe(at('2026-11-09', 19));
    });

    it('lands on its own day in every zone, a daylight-saving change included', () => {
        const zones = [
            'UTC',
            'Europe/London',
            'America/New_York',
            'Pacific/Kiritimati',
            'Pacific/Pago_Pago',
            'Australia/Lord_Howe',
        ];
        const days = ['2026-03-08', '2026-03-29', '2026-10-04', '2026-11-01', '2026-12-31'];
        zones.forEach((timezone) => {
            const reader = zone(timezone, 'en');
            days.forEach((iso) => {
                [0, MOVE_MINUTE, 23 * 60 + 59].forEach((minute) => {
                    expect([timezone, iso, minute, reader.day(instantOf(iso, minute, timezone))])
                        .toEqual([timezone, iso, minute, iso]);
                });
            });
        });
    });

    it('keeps the wall clock across a daylight-saving change', () => {
        const london = zone('Europe/London', 'en');
        expect(london.minutes(instantOf('2026-03-28', MOVE_MINUTE, 'Europe/London'))).toBe(MOVE_MINUTE);
        expect(london.minutes(instantOf('2026-03-29', MOVE_MINUTE, 'Europe/London'))).toBe(MOVE_MINUTE);
        expect(instantOf('2026-03-29', MOVE_MINUTE, 'Europe/London')).toBe(at('2026-03-29', 11));
    });

    it('reads an unknown zone as UTC', () => {
        expect(instantOf('2026-11-10', MOVE_MINUTE, 'Not/AZone')).toBe(at('2026-11-10', 12));
    });

    it('starts a new event from the toolbar now while today is shown, else on the span\'s first day', () => {
        const utc = zone('UTC', 'en');
        const now = at('2026-11-17', 10) + 25 * 60;
        const november = ['2026-11-16', '2026-11-17', '2026-11-18'];
        expect(toolbarStart(november, '2026-11-17', now, utc, 'UTC')).toBeNull();
        expect(toolbarStart(['2026-12-01', '2026-12-02'], '2026-11-17', now, utc, 'UTC')).toBe(at('2026-12-01', 10) + 25 * 60);
    });

    it('starts a new event on a day at the current time of day', () => {
        const recife = zone('America/Recife', 'en');
        const now = at('2026-11-17', 13);
        expect(dayStart('2026-11-10', now, recife, 'America/Recife')).toBe(at('2026-11-10', 13));
    });
});
