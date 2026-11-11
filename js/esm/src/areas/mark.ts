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
 * The searched term marked on the titles of cards, a panel's and the listing's grid's alike, over the filter line's
 * matching ({@see ../filterline/mark runs}).
 *
 * On the text of the title's heading (data-region="dim-card-title"), never on a link's hook: a card with no
 * page to open draws its title as a plain span. It unmarks before it marks, so it is idempotent.
 *
 * @module     theme_dimensions/areas/mark
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fold, runs} from '../filterline/mark';

/** The title's heading on a card. */
const TITLE = '[data-region="dim-card-title"]';

/** The class every mark carries. */
const MARK_CLASS = 'dim-mark';

/** Characters below which a term marks nothing: the server's floor, criteria::MIN_SEARCH_LENGTH. */
const MIN_LENGTH = 2;

/**
 * The needle a term marks: collapsed, folded, or nothing under the floor.
 *
 * @param {string} term The term.
 * @returns {string}
 */
const needleOf = (term: string): string => {
    const collapsed = String(term || '').trim().replace(/\s+/g, ' ');
    return collapsed.length < MIN_LENGTH ? '' : fold(collapsed);
};

/**
 * Mark one text node's every run of the needle.
 *
 * @param {Text} node The text node.
 * @param {string} needle The folded needle.
 * @returns {void}
 */
const markNode = (node: Text, needle: string): void => {
    const text = node.nodeValue || '';
    const found = runs(text, needle);
    if (!found.length) {
        return;
    }
    const fragment = document.createDocumentFragment();
    let last = 0;
    found.forEach(([start, end]) => {
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
 * Mark the term on every card title under a root, unmarking first.
 *
 * @param {Element|null} root The panel.
 * @param {string} term The term.
 * @returns {void}
 */
export const markTitles = (root: Element | null, term: string): void => {
    if (!root) {
        return;
    }
    const needle = needleOf(term);
    root.querySelectorAll(TITLE).forEach((title) => {
        const marks = title.querySelectorAll(`mark.${MARK_CLASS}`);
        marks.forEach((mark) => mark.replaceWith(document.createTextNode(mark.textContent || '')));
        if (marks.length) {
            title.normalize();
        }
        if (needle === '') {
            return;
        }
        const walker = document.createTreeWalker(title, NodeFilter.SHOW_TEXT);
        const nodes: Text[] = [];
        while (walker.nextNode()) {
            nodes.push(walker.currentNode as Text);
        }
        nodes.forEach((node) => markNode(node, needle));
    });
};
