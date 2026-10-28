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
 * What the filter line reads off the page: the width each step needs, and where its floating layers go.
 *
 * Every function here reads layout, and each says when it forces one. The steps' widths are measured
 * ONCE per content - the words, the counts, the slots - with the line under the measuring class (one
 * row that never wraps, no transition, only the search shrinking, down to its step's floor) and every
 * cell of the scope drawn, a cell the counts hide shown for the measure and hidden again before anything
 * is painted: so a step follows the width and the language and never the numbers of the moment, as
 * amd/src/categoryline.js's fit() measures. A width change then needs no measure at all
 * ({@see ./steps choose()}).
 *
 * @module     theme_dimensions/filterline/measure
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CLASSES, TRIES, paint} from './steps';
import type {Fold} from './steps';

/** Pixels between the line's real bottom and a layer placed under it: the stylesheet's own gap. */
export const GAP = 6;

/** Pixels kept clear between a revealed item and the edge of the row it scrolls in. */
export const REVEAL_PAD = 16;

/** The narrowest the listbox is drawn where it hangs under the field rather than across the line. */
export const MIN_LIST_WIDTH = 288;

/** The search's region on the line: the one control that shrinks while the line is measured. */
const SEARCH = '[data-region="dim-line-search"]';

/** The listbox of the suggestions: a layer, never a control of the line. */
const LISTBOX = '[data-region="dim-line-suggest"]';

/** The scope, which has a line of its own at the two-line step. */
const SCOPE = '[data-region="dim-vitrine-group"][data-role="scope"]';

/**
 * Whether an element is drawn at all.
 *
 * @param {Element|null} element The element.
 * @returns {boolean}
 */
export const shown = (element: Element | null): boolean => Boolean(element) && (element as Element).getClientRects().length > 0;

/**
 * The line's own content width: its box less its padding. Forces a layout.
 *
 * @param {HTMLElement} line The line.
 * @returns {number}
 */
export const contentWidth = (line: HTMLElement): number => {
    const style = window.getComputedStyle(line);
    return line.clientWidth - (parseFloat(style.paddingLeft) || 0) - (parseFloat(style.paddingRight) || 0);
};

/**
 * How wide the line must be for the controls drawn now to fit on one row.
 *
 * Under the measuring class nothing shrinks but the search, so the controls run from the first one's
 * start to the last one's end; the search is counted at its step's floor (its min-width), whatever room
 * it was given, because the room past the floor is what a narrower line takes from it first. The listbox
 * is a layer and never counts, and a control the step does not draw has no box. Forces a layout.
 *
 * @param {HTMLElement} line The line, under the measuring class and one step's classes.
 * @returns {number}
 */
export const requiredWidth = (line: HTMLElement): number => {
    let start = Infinity;
    let end = -Infinity;
    let slack = 0;
    Array.from(line.children).forEach((child) => {
        if (child.matches(LISTBOX)) {
            return;
        }
        const rect = child.getBoundingClientRect();
        if (rect.width <= 0) {
            return;
        }
        start = Math.min(start, rect.left);
        end = Math.max(end, rect.right);
        if (child.matches(SEARCH)) {
            const floor = parseFloat(window.getComputedStyle(child).minWidth) || 0;
            slack = Math.max(0, rect.width - floor);
        }
    });
    return end > start ? end - start - slack : 0;
};

/**
 * The width each of TRIES needs, measured once with every scope cell drawn and nothing in transition.
 *
 * The open search is set aside for the measure (its slide would read as controls of no width), and the
 * fold the line carried, the searching class and every hidden cell are put back before the caller paints.
 * Forces one layout per try.
 *
 * @param {HTMLElement} line The line.
 * @param {object} drawn The fold the line carries now, put back afterwards; null for none.
 * @returns {Array} One width per try, in TRIES' order.
 */
export const stepWidths = (line: HTMLElement, drawn: Fold | null): number[] => {
    const searching = line.classList.contains(CLASSES.searching);
    const scope = line.querySelector(SCOPE);
    const absent = scope ? Array.from(scope.querySelectorAll<HTMLElement>('label[hidden]')) : [];
    line.classList.add(CLASSES.measuring);
    line.classList.remove(CLASSES.searching);
    absent.forEach((label) => {
        label.hidden = false;
    });
    const widths = TRIES.map((fold) => {
        paint(line, fold);
        return requiredWidth(line);
    });
    absent.forEach((label) => {
        label.hidden = true;
    });
    paint(line, drawn);
    line.classList.toggle(CLASSES.searching, searching);
    // The transitions come back only once the line is drawn as it stays, so nothing slides from the measure.
    void line.offsetWidth;
    line.classList.remove(CLASSES.measuring);
    return widths;
};

/**
 * The bottom of the line's first line of controls, as drawn: the floating layers hang under it.
 *
 * The scope of the two-line step is a line of its own, which the suggestions cover while the search is
 * open over the line; and a control slid out of the way has no box to count.
 *
 * @param {HTMLElement} line The line.
 * @param {boolean} twolines Whether the line is at the two-line step.
 * @returns {number} A viewport coordinate.
 */
export const lineBottom = (line: HTMLElement, twolines: boolean): number => {
    let bottom = 0;
    Array.from(line.children).forEach((child) => {
        if (child.matches(LISTBOX) || (twolines && child.matches(SCOPE))) {
            return;
        }
        const rect = child.getBoundingClientRect();
        if (rect.width > 0 && rect.height > 0) {
            bottom = Math.max(bottom, rect.bottom);
        }
    });
    return bottom || line.getBoundingClientRect().bottom;
};

/**
 * Place a floating layer under the line's real bottom, whatever its containing block.
 *
 * Measured, never a fixed offset, so a wrapped line, a taller control or the second line of the two-line
 * step never puts a layer over the field or the close button. The Filters panel of the two-line step is
 * a bottom sheet and takes no offset at all.
 *
 * @param {HTMLElement} line The line.
 * @param {HTMLElement|null} layer The listbox, the Filters panel or the Organise menu.
 * @param {boolean} twolines Whether the line is at the two-line step.
 * @param {boolean} sheet Whether the layer is the panel drawn as a bottom sheet.
 * @returns {void}
 */
export const place = (line: HTMLElement, layer: HTMLElement | null, twolines: boolean, sheet: boolean): void => {
    if (!layer) {
        return;
    }
    if (sheet) {
        layer.style.top = '';
        return;
    }
    const parent = layer.offsetParent;
    if (!parent || !shown(layer)) {
        return;
    }
    const top = parent.getBoundingClientRect().top + parent.clientTop;
    layer.style.top = Math.round(lineBottom(line, twolines) - top + GAP) + 'px';
};

/**
 * Place the listbox: under the field at the first two steps, across the line from the third.
 *
 * @param {HTMLElement} line The line.
 * @param {HTMLElement} listbox The listbox.
 * @param {boolean} anchored Whether it hangs under the field.
 * @param {boolean} twolines Whether the line is at the two-line step.
 * @returns {void}
 */
export const placeList = (line: HTMLElement, listbox: HTMLElement, anchored: boolean, twolines: boolean): void => {
    const search = line.querySelector<HTMLElement>(SEARCH);
    listbox.classList.toggle('dim-line__suggest--anchored', anchored);
    if (anchored && search) {
        const base = line.getBoundingClientRect();
        const box = search.getBoundingClientRect();
        const width = Math.min(base.width, Math.max(box.width, MIN_LIST_WIDTH));
        listbox.style.left = Math.round(Math.min(box.left - base.left, base.width - width)) + 'px';
        listbox.style.width = Math.round(width) + 'px';
    } else {
        listbox.style.left = '';
        listbox.style.width = '';
    }
    place(line, listbox, twolines, false);
};

/**
 * Show a row's fades on the sides where it hides something.
 *
 * @param {HTMLElement|null} scroller A row that scrolls sideways.
 * @returns {void}
 */
export const fades = (scroller: HTMLElement | null): void => {
    if (!scroller) {
        return;
    }
    const max = scroller.scrollWidth - scroller.clientWidth;
    const x = scroller.scrollLeft;
    // Right to left, scrollLeft runs from 0 down to minus the overflow.
    const rtl = window.getComputedStyle(scroller).direction === 'rtl';
    const left = rtl ? max + x : x;
    const right = rtl ? -x : max - x;
    scroller.classList.toggle('dim-scroll--fade-left', max > 1 && left > 2);
    scroller.classList.toggle('dim-scroll--fade-right', max > 1 && right > 2);
};

/**
 * Scroll a half-hidden item of a row fully into view, with room to spare.
 *
 * @param {HTMLElement|null} scroller The row.
 * @param {Element|null} item The item pressed or focused.
 * @param {boolean} still Whether the reader asked for no motion.
 * @returns {void}
 */
export const reveal = (scroller: HTMLElement | null, item: Element | null, still: boolean): void => {
    if (!scroller || !item || !scroller.contains(item) || scroller.scrollWidth <= scroller.clientWidth) {
        return;
    }
    const box = scroller.getBoundingClientRect();
    const rect = item.getBoundingClientRect();
    let dx = 0;
    if (rect.left < box.left + REVEAL_PAD) {
        dx = rect.left - box.left - REVEAL_PAD;
    } else if (rect.right > box.right - REVEAL_PAD) {
        dx = rect.right - box.right + REVEAL_PAD;
    }
    if (dx !== 0) {
        scroller.scrollBy({left: dx, behavior: still ? 'auto' : 'smooth'});
    }
};

/**
 * The item a press or a focus inside a row stands for: a radio is drawn by its label.
 *
 * @param {HTMLElement} form The toolbar form.
 * @param {EventTarget|null} target The event's target.
 * @returns {Element|null}
 */
export const itemOf = (form: HTMLElement, target: EventTarget | null): Element | null => {
    if (!(target instanceof Element)) {
        return null;
    }
    if (target.matches('input[type="radio"]')) {
        return form.querySelector(`label[for="${target.id}"]`);
    }
    return target.closest('label, a, button');
};
