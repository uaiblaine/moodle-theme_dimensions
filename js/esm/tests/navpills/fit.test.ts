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
 * Tests for the category navigation's fit arithmetic: which units stay on the row and which move into "More".
 *
 * The row: Category 90, Settings 100, User and access 140, Courses and content 160, Competencies 120, 2px apart, and
 * "More" 70 wide. Every unit on the row takes 610 + 4 * 2 = 618.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fit, TOLERANCE} from '../../src/navpills/fit';

/** The units' widths, in the row's order: Category first. */
const WIDTHS = [90, 100, 140, 160, 120];

/** The gap between two children of the capsule. */
const GAP = 2;

/** The width of "More". */
const MORE = 70;

/**
 * Fit the example's row in a room.
 *
 * @param {number} room The room.
 * @param {boolean} own Whether More holds nodes of its own.
 * @returns {object} What fit() decided.
 */
const fitIn = (room: number, own = false) => fit({widths: WIDTHS, gap: GAP, room: room, more: MORE, last: 0, own: own});

describe('fit', () => {
    it('moves nothing and hides More when every unit fits', () => {
        expect(fitIn(1000)).toEqual({keep: [0, 1, 2, 3, 4], move: [], more: false});
    });

    it('keeps a row that fits exactly, gaps counted, and moves one past it', () => {
        expect(fitIn(618)).toEqual({keep: [0, 1, 2, 3, 4], move: [], more: false});
        expect(fitIn(618 - TOLERANCE)).toEqual({keep: [0, 1, 2, 3, 4], move: [], more: false});
        // One pixel short: the last unit moves, and More is paid for with its gap.
        expect(fitIn(617)).toEqual({keep: [0, 1, 2, 3], move: [4], more: true});
    });

    it('moves the last units first, until the rest fit beside More', () => {
        // Category to Courses and content with More: 490 + 70 + 4 * 2 = 568.
        expect(fitIn(568)).toEqual({keep: [0, 1, 2, 3], move: [4], more: true});
        expect(fitIn(567)).toEqual({keep: [0, 1, 2], move: [3, 4], more: true});
    });

    it('moves Category last of all, whatever its place on the row', () => {
        // Category and More: 90 + 2 + 70 = 162.
        expect(fitIn(162)).toEqual({keep: [0], move: [1, 2, 3, 4], more: true});
        expect(fitIn(161)).toEqual({keep: [], move: [0, 1, 2, 3, 4], more: true});
        // Named last at the row's end, it still moves after every other unit.
        const atEnd = fit({widths: [100, 140, 90], gap: GAP, room: 162, more: MORE, last: 2});
        expect(atEnd).toEqual({keep: [2], move: [0, 1], more: true});
    });

    it('shows More for its own nodes, and pays for it, though every unit would fit alone', () => {
        expect(fitIn(1000, true)).toEqual({keep: [0, 1, 2, 3, 4], move: [], more: true});
        // 618 fits the units alone, not the units and More.
        expect(fitIn(618, true)).toEqual({keep: [0, 1, 2, 3], move: [4], more: true});
    });

    it('moves every unit in a room of zero, or of nothing at all', () => {
        expect(fitIn(0)).toEqual({keep: [], move: [0, 1, 2, 3, 4], more: true});
        expect(fitIn(-20)).toEqual({keep: [], move: [0, 1, 2, 3, 4], more: true});
        expect(fitIn(Number.NaN)).toEqual({keep: [], move: [0, 1, 2, 3, 4], more: true});
    });

    it('has nothing to decide for a row with no unit', () => {
        expect(fit({widths: [], gap: GAP, room: 0, more: MORE})).toEqual({keep: [], move: [], more: false});
        expect(fit({widths: [], gap: GAP, room: 0, more: MORE, own: true})).toEqual({keep: [], move: [], more: true});
    });
});
