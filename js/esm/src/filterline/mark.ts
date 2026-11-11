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
 * A term marked in a text, in the text's own spelling, as runs a component draws rather than nodes it writes.
 *
 * The filter line marks its suggestions with {@see pieces()}; the card titles, which are the server's markup,
 * are marked on both shells by {@see ../areas/mark}, which reuses {@see fold()} and {@see runs()}.
 *
 * The match ignores case and accents, as the server's search does: each character is folded on its own
 * and every folded character remembers the character it came from, so a match found in the folded text is
 * cut from the original one - "Incêndio" is marked whole for "incendio", accent and capital included.
 *
 * @module     theme_dimensions/filterline/mark
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** One run of a text: its words, and whether they are the term. */
export type Piece = {text: string, marked: boolean};

/**
 * A text with its accents taken off and in lower case, character for character.
 *
 * @param {string} text Any text.
 * @returns {string}
 */
export const fold = (text: string): string => String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/**
 * The normalise rule for matching: accents off, lower case, trimmed.
 *
 * @param {string} text Any text.
 * @returns {string}
 */
export const normalise = (text: string | null | undefined): string => fold(text || '').trim();

/**
 * Where a folded needle matches a text, as [start, end) ranges of the original text, left to right and
 * never overlapping.
 *
 * @param {string} text The text, in its own spelling.
 * @param {string} needle The term, already folded.
 * @returns {Array} The ranges, as [start, end] pairs.
 */
export const runs = (text: string, needle: string): Array<[number, number]> => {
    if (needle === '') {
        return [];
    }
    let folded = '';
    const source: number[] = [];
    for (let index = 0; index < text.length; index++) {
        const piece = fold(text.charAt(index));
        for (let at = 0; at < piece.length; at++) {
            folded += piece.charAt(at);
            source.push(index);
        }
    }
    const found: Array<[number, number]> = [];
    let from = folded.indexOf(needle);
    while (from !== -1) {
        found.push([source[from], source[from + needle.length - 1] + 1]);
        from = folded.indexOf(needle, from + needle.length);
    }
    return found;
};

/**
 * A text cut into the runs to draw, the term's marked.
 *
 * @param {string} text The text, in its own spelling.
 * @param {string} term The normalised term; the empty string marks nothing.
 * @param {boolean} all Every match (a card title's rule), or the first alone (a suggestion's).
 * @returns {Array} The pieces, in order; one unmarked piece where nothing matches.
 */
export const pieces = (text: string, term: string, all = false): Piece[] => {
    const found = runs(text, term);
    const chosen = all ? found : found.slice(0, 1);
    if (!chosen.length) {
        return [{text: text, marked: false}];
    }
    const result: Piece[] = [];
    let last = 0;
    chosen.forEach(([start, end]) => {
        if (start > last) {
            result.push({text: text.slice(last, start), marked: false});
        }
        result.push({text: text.slice(start, end), marked: true});
        last = end;
    });
    if (last < text.length) {
        result.push({text: text.slice(last), marked: false});
    }
    return result;
};
