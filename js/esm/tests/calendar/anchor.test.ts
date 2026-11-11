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
 * Tests for where the event popover goes: under its opener, flipped above, kept inside the viewport.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {anchor} from '../../src/calendar/EventPopover';

const VIEWPORT = {width: 1000, height: 800};
const SIZE = {width: 300, height: 200};

describe('the popover\'s anchor', () => {
    it('goes under its opener, on its left edge, when it fits', () => {
        expect(anchor({top: 100, left: 50, bottom: 120, right: 200}, SIZE, VIEWPORT)).toEqual({top: 128, left: 50});
    });

    it('flips above an opener near the bottom', () => {
        expect(anchor({top: 700, left: 50, bottom: 720, right: 200}, SIZE, VIEWPORT)).toEqual({top: 492, left: 50});
    });

    it('is pulled inside the viewport on the right and when neither side fits', () => {
        expect(anchor({top: 100, left: 900, bottom: 120, right: 990}, SIZE, VIEWPORT).left).toBe(692);
        const tall = {width: 300, height: 700};
        const placed = anchor({top: 390, left: 50, bottom: 410, right: 200}, tall, VIEWPORT);
        expect(placed.top).toBeGreaterThanOrEqual(8);
        expect(placed.top + tall.height).toBeLessThanOrEqual(VIEWPORT.height - 8);
    });

    it('stays under the fixed navbar: flips below an opener near the top, and never rises above it', () => {
        // A bar just under a 60px navbar: there is room below, so it goes there (above would start at -138px).
        expect(anchor({top: 70, left: 50, bottom: 90, right: 200}, SIZE, VIEWPORT, 60)).toEqual({top: 98, left: 50});
        // A tall popover near the bottom goes above, between the navbar's offset and the viewport's bottom gap.
        const flipped = anchor({top: 740, left: 50, bottom: 760, right: 200}, {width: 300, height: 600}, VIEWPORT, 60);
        expect(flipped.top).toBeGreaterThanOrEqual(68);
        expect(flipped.top + 600).toBeLessThanOrEqual(792);
        // Above, when it fits under the navbar: the offset is the least top.
        expect(anchor({top: 700, left: 50, bottom: 720, right: 200}, SIZE, VIEWPORT, 60).top).toBe(492);
    });

    it('keeps below an opener when above would start under the navbar, though it lands in the gap above it', () => {
        // Below does not fit (428 + 380 > 792) and above would start at 12px: clear of the viewport's 8px gap but
        // under the 60px navbar (least top 68). Above is refused for the navbar alone, so it stays below, pulled up
        // to the viewport's bottom gap (412). Changes that must make it fail: testing above against the gap
        // instead of the navbar's offset (it would pick above and clamp to 68), or ignoring the offset (12).
        const opener = {top: 400, left: 50, bottom: 420, right: 200};
        const size = {width: 300, height: 380};
        expect(anchor(opener, size, VIEWPORT, 60)).toEqual({top: 412, left: 50});
        expect(anchor(opener, size, VIEWPORT, 0)).toEqual({top: 12, left: 50});
    });

    it('caps the height, to scroll inside, only when neither side holds it under the navbar', () => {
        const capped = anchor({top: 390, left: 50, bottom: 410, right: 200}, {width: 300, height: 760}, VIEWPORT, 60);
        expect(capped.top).toBe(68);
        expect(capped.maxHeight).toBe(724);
        expect(anchor({top: 390, left: 50, bottom: 410, right: 200}, {width: 300, height: 700}, VIEWPORT, 60).maxHeight)
            .toBeUndefined();
    });
});
