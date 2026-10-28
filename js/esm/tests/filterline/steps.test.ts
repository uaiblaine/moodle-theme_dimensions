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

import {TOLERANCE, TRIES, choose, morphs, paint, same} from '../../src/filterline/steps';

/** The widths each try needs on a line of the example: full, no word, folded, two lines, two lines folded. */
const WIDTHS = [900, 840, 620, 480, 300];

describe('theme_dimensions/filterline/steps', () => {
    it('tries the steps in the contract\'s order, folding the field at the third and last', () => {
        expect(TRIES.map((fold) => `${fold.step}${fold.folded ? '+' : ''}`)).toEqual(['s1', 's2', 's3+', 's4', 's4+']);
    });

    it('takes the first step whose width fits, with a pixel of tolerance', () => {
        expect(choose(WIDTHS, 1200)).toEqual({step: 's1', folded: false});
        expect(choose(WIDTHS, 900)).toEqual({step: 's1', folded: false});
        expect(choose(WIDTHS, 900 - TOLERANCE)).toEqual({step: 's1', folded: false});
        expect(choose(WIDTHS, 898)).toEqual({step: 's2', folded: false});
        expect(choose(WIDTHS, 700)).toEqual({step: 's3', folded: true});
        expect(choose(WIDTHS, 500)).toEqual({step: 's4', folded: false});
    });

    it('takes the last step whatever it measures', () => {
        expect(choose(WIDTHS, 320)).toEqual({step: 's4', folded: true});
        expect(choose(WIDTHS, 10)).toEqual({step: 's4', folded: true});
        expect(choose([], 2000)).toEqual({step: 's4', folded: true});
    });

    it('grows the search over the line from the third step only', () => {
        expect(TRIES.map(morphs)).toEqual([false, false, true, true, true]);
        expect(morphs(null)).toBe(false);
    });

    it('compares folds by value', () => {
        expect(same({step: 's3', folded: true}, {step: 's3', folded: true})).toBe(true);
        expect(same({step: 's4', folded: false}, {step: 's4', folded: true})).toBe(false);
        expect(same(null, null)).toBe(true);
        expect(same(null, TRIES[0])).toBe(false);
    });

    it('writes one step and the fold on the line and leaves its other classes alone', () => {
        const line = document.createElement('div');
        line.className = 'dim-line dim-line--searching';
        paint(line, {step: 's3', folded: true});
        expect(line.className).toBe('dim-line dim-line--searching dim-line--s3 dim-line--folded');
        paint(line, {step: 's1', folded: false});
        expect(line.className).toBe('dim-line dim-line--searching dim-line--s1');
        paint(line, null);
        expect(line.className).toBe('dim-line dim-line--searching');
    });
});
