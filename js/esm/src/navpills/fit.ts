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
 * Which units of the category navigation stay on the row and which move into "More": arithmetic only.
 *
 * The row module measures once ({@see ./row measure()}) and hands the numbers here, so the decision reads no layout
 * and is tested in Jest on its own. Units move from the row's end, the page itself ("Category") last of all (D83);
 * "More" is shown when anything moved or when it holds nodes of its own (D89), and its width is then paid on the
 * row, with the gap before it: the width it takes selected, its dot included, once it holds the unit with the
 * current page (D82).
 *
 * @module     theme_dimensions/navpills/fit
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Pixels a row may overrun its room by and still fit: the subpixel rounding of the boxes it was measured from. */
export const TOLERANCE = 0.5;

/** What the row measured: every number in pixels, every width a unit's border box. */
export type Measures = {
    /** The units' widths, in the row's order, "More" not among them. */
    widths: number[],
    /** The gap the capsule puts between two of its children. */
    gap: number,
    /** The room the capsule's content may take: the track's width less the capsule's own padding and border. */
    room: number,
    /** The width of the "More" pill. */
    more: number,
    /** The width of "More" once it holds the current page, its dot drawn; `more` when not given. */
    selected?: number,
    /** The index of the unit that holds the current page, whose move selects "More"; -1 for none. */
    current?: number,
    /** The index of the unit that moves last of all, the page itself; -1 for none. */
    last?: number,
    /** Whether "More" holds nodes of its own, which show it whatever fits. */
    own?: boolean,
};

/** What fits: the indexes that stay and those that move, each in the row's order, and whether "More" is shown. */
export type Fitted = {
    keep: number[],
    move: number[],
    more: boolean,
};

/**
 * The width a row of these units takes, "More" after them or not.
 *
 * @param {Array} widths Every unit's width.
 * @param {Array} kept The indexes on the row.
 * @param {number} gap The gap between two children of the capsule.
 * @param {number} more The width of "More", or 0 when it is not shown.
 * @returns {number}
 */
const span = (widths: number[], kept: number[], gap: number, more: number): number => {
    const count = kept.length + (more > 0 ? 1 : 0);
    const sum = kept.reduce((total, index) => total + widths[index], 0) + more;
    return count > 0 ? sum + gap * (count - 1) : 0;
};

/**
 * Decide which units stay on the row and which move into "More".
 *
 * Nothing moves and "More" stays hidden when every unit fits and "More" has nothing of its own. Otherwise "More" is
 * shown and paid for, and units move from the row's end until the rest fit beside it; the unit named last moves only
 * once every other has. "More" is paid at its selected width from the move of the unit holding the current page on,
 * which can move one more unit. A room of zero, or one no unit fits, moves every unit.
 *
 * @param {object} measures What the row measured.
 * @returns {object} The indexes kept and moved, and whether "More" is shown.
 */
export const fit = (measures: Measures): Fitted => {
    const {widths, gap, more} = measures;
    const room = Number.isFinite(measures.room) ? Math.max(0, measures.room) : 0;
    const last = measures.last ?? -1;
    const current = measures.current ?? -1;
    const selected = measures.selected ?? more;
    const own = Boolean(measures.own);
    const all = widths.map((width, index) => index);

    if (!own && span(widths, all, gap, 0) <= room + TOLERANCE) {
        return {keep: all, move: [], more: false};
    }

    // The order units leave in: from the end, the one named last at the very end.
    const order = all.filter((index) => index !== last).reverse();
    if (last >= 0 && last < widths.length) {
        order.push(last);
    }
    const moved = new Set<number>();
    for (const index of order) {
        const kept = all.filter((candidate) => !moved.has(candidate));
        if (span(widths, kept, gap, moved.has(current) ? selected : more) <= room + TOLERANCE) {
            break;
        }
        moved.add(index);
    }
    return {
        keep: all.filter((index) => !moved.has(index)),
        move: all.filter((index) => moved.has(index)),
        more: own || moved.size > 0,
    };
};
