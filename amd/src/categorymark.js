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
 * The searched term, marked in the titles of the course cards a narrowed page shows.
 *
 * A pure helper with no state and no mark on the body: the listing's module calls it over the
 * cards it draws and the panel's module over the panel's rail. It reads the title's heading, not its
 * link, because a visitor's card of a course with no page to open draws its title as plain text,
 * and a term found there is marked all the same.
 *
 * The match ignores case and accents, as the server's search does, and nothing is ever parsed from a
 * string: each text node of the heading is split into text nodes and mark elements built one by one.
 * Marking is idempotent - the marks a previous call left are taken out first - so a title marked at
 * load and again after a transplant carries one mark per match, never one inside another, and a term
 * cleared leaves no mark behind. A course found by its short name or its id number has no mark: the
 * term is not in its title.
 *
 * @module     theme_dimensions/categorymark
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {String} The heading of a course card, which holds its link or a visitor's plain-text title. */
const TITLE = '[data-region="dim-card-title"]';

/** @type {String} The class of one marked run, which the stylesheet paints. */
const MARK_CLASS = 'dim-mark';

/** @type {Number} Characters below which a term is not a search at all, the server's own floor. */
const MIN_LENGTH = 2;

/**
 * Fold a string for matching: its accents dropped, its case lowered.
 *
 * @param {String} text The text.
 * @return {String}
 */
const fold = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

/**
 * The term as the page searched for it: trimmed, its runs of space collapsed, folded.
 *
 * @param {String} term The address's term.
 * @return {String} The folded term, or '' when it is under the server's floor.
 */
const needleOf = (term) => {
    const collapsed = String(term || '').trim().replace(/\s+/g, ' ');
    return collapsed.length < MIN_LENGTH ? '' : fold(collapsed);
};

/**
 * Take the marks a previous call left out of one heading, and join the text they split.
 *
 * @param {HTMLElement} title The heading.
 * @return {void}
 */
const unmark = (title) => {
    title.querySelectorAll('mark.' + MARK_CLASS).forEach((mark) => {
        mark.replaceWith(document.createTextNode(mark.textContent));
    });
    title.normalize();
};

/**
 * Mark every run of one text node that matches, replacing the node by the pieces.
 *
 * Each character of the node is folded on its own and every folded character remembers the index of
 * the character it came from, so a match found in the folded text is cut from the original text:
 * "Incêndio" is marked whole for "incendio", accent and capital included.
 *
 * @param {Text} node The text node.
 * @param {String} needle The folded term.
 * @return {void}
 */
const markNode = (node, needle) => {
    const text = node.nodeValue;
    let folded = '';
    const source = [];
    for (let index = 0; index < text.length; index++) {
        const piece = fold(text[index]);
        for (let at = 0; at < piece.length; at++) {
            folded += piece[at];
            source.push(index);
        }
    }
    const runs = [];
    let from = folded.indexOf(needle);
    while (from !== -1) {
        runs.push([source[from], source[from + needle.length - 1] + 1]);
        from = folded.indexOf(needle, from + needle.length);
    }
    if (!runs.length) {
        return;
    }
    const fragment = document.createDocumentFragment();
    let last = 0;
    runs.forEach(([start, end]) => {
        if (start > last) {
            fragment.append(document.createTextNode(text.slice(last, start)));
        }
        const mark = document.createElement('mark');
        mark.className = MARK_CLASS;
        mark.textContent = text.slice(start, end);
        fragment.append(mark);
        last = end;
    });
    if (last < text.length) {
        fragment.append(document.createTextNode(text.slice(last)));
    }
    node.replaceWith(fragment);
};

/**
 * Mark the term in the title of every course card under an element.
 *
 * @param {HTMLElement|null} root The element whose cards are marked: a grid, a list item, the panel.
 * @param {String} term The term the page was searched for; '' or a term under the floor marks nothing.
 * @return {void}
 */
export const mark = (root, term) => {
    if (!root) {
        return;
    }
    const needle = needleOf(term);
    root.querySelectorAll(TITLE).forEach((title) => {
        unmark(title);
        if (needle === '') {
            return;
        }
        const walker = document.createTreeWalker(title, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }
        nodes.forEach((node) => markNode(node, needle));
    });
};
