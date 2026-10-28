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
 * Tests for a term marked in a text: categorymark's rule, as runs.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fold, normalise, pieces, runs} from '../../src/filterline/mark';

describe('theme_dimensions/filterline/mark', () => {
    it('folds accents and case away, character for character', () => {
        expect(fold('Incêndio Ação')).toBe('incendio acao');
        expect(normalise('  Ação  ')).toBe('acao');
        expect(normalise(null)).toBe('');
    });

    it('cuts a match from the original spelling, accent and capital included', () => {
        expect(pieces('Combate a Incêndio', 'incendio')).toEqual([
            {text: 'Combate a ', marked: false},
            {text: 'Incêndio', marked: true},
        ]);
    });

    it('marks the first match of a suggestion and every match of a title', () => {
        expect(pieces('Online online', 'on')).toEqual([
            {text: 'On', marked: true},
            {text: 'line online', marked: false},
        ]);
        expect(pieces('Online online', 'on', true).filter((piece) => piece.marked)).toHaveLength(2);
        expect(runs('aaaa', 'aa')).toEqual([[0, 2], [2, 4]]);
    });

    it('marks nothing for an empty term or one that is not there', () => {
        expect(pieces('Rescue', '')).toEqual([{text: 'Rescue', marked: false}]);
        expect(pieces('Rescue', 'fire')).toEqual([{text: 'Rescue', marked: false}]);
    });
});
