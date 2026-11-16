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
 * Tests for the filter line's steps as a rule over widths.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {TOLERANCE, TRIES, choose, morphs, paint, same, settled} from '../../src/filterline/steps';

/** The widths each try needs on a line of the example: one line with the field open, one line, two lines. */
const WIDTHS = [900, 700, 300];

describe('theme_dimensions/filterline/steps', () => {
    it('tries one line with the field in place, one line with the search over it, then two lines', () => {
        expect(TRIES.map((fold) => `${fold.step}${fold.over ? '+' : ''}`)).toEqual(['s1', 's1+', 's2+']);
    });

    it('takes the first try whose width fits, with a pixel of tolerance', () => {
        expect(choose(WIDTHS, 1200)).toEqual({step: 's1', over: false});
        expect(choose(WIDTHS, 900)).toEqual({step: 's1', over: false});
        expect(choose(WIDTHS, 900 - TOLERANCE)).toEqual({step: 's1', over: false});
        expect(choose(WIDTHS, 898)).toEqual({step: 's1', over: true});
        expect(choose(WIDTHS, 700)).toEqual({step: 's1', over: true});
        expect(choose(WIDTHS, 698)).toEqual({step: 's2', over: true});
    });

    it('takes the last try whatever it measures', () => {
        expect(choose(WIDTHS, 320)).toEqual({step: 's2', over: true});
        expect(choose(WIDTHS, 10)).toEqual({step: 's2', over: true});
        expect(choose([], 2000)).toEqual({step: 's2', over: true});
    });

    it('lets the widths measured so far decide only while one of them fits, or every try was measured', () => {
        // A wide line measured the first try alone: it decides any width the first try fits.
        expect(settled([900], 1200)).toBe(true);
        expect(settled([900], 900 - TOLERANCE)).toBe(true);
        // Narrowed past it, the tries left out are needed: choose() would jump to the last try.
        expect(settled([900], 898)).toBe(false);
        expect(settled([900, 700], 600)).toBe(false);
        expect(settled([900, 700], 700)).toBe(true);
        expect(settled(WIDTHS, 10)).toBe(true);
        expect(settled([], 2000)).toBe(false);
    });

    it('grows the search over the line where the open field does not fit beside the controls', () => {
        expect(TRIES.map(morphs)).toEqual([false, true, true]);
        expect(morphs(null)).toBe(false);
    });

    it('compares folds by value', () => {
        expect(same({step: 's1', over: true}, {step: 's1', over: true})).toBe(true);
        expect(same({step: 's1', over: false}, {step: 's1', over: true})).toBe(false);
        expect(same({step: 's1', over: true}, {step: 's2', over: true})).toBe(false);
        expect(same(null, null)).toBe(true);
        expect(same(null, TRIES[0])).toBe(false);
    });

    it('writes one step and the fold on the line and leaves its other classes alone', () => {
        const line = document.createElement('div');
        line.className = 'dim-line dim-line--searching';
        paint(line, {step: 's2', over: true});
        expect(line.className).toBe('dim-line dim-line--searching dim-line--s2 dim-line--over');
        paint(line, {step: 's1', over: false});
        expect(line.className).toBe('dim-line dim-line--searching dim-line--s1');
        paint(line, null);
        expect(line.className).toBe('dim-line dim-line--searching');
    });
});
