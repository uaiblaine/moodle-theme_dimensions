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
 * The filter line's folding steps, as a rule over numbers.
 *
 * The fleet contract's section 2 (moodle-dev/docs/filter-line/CONTRACT.md), as the AMD line's fit() read it
 * before R4a: the line takes the first step at which its controls fit
 * on its OWN width - full; Filters without its word; the search as an icon; two lines, the scope on the
 * second and the view toggle in the Organise menu - and the last one whatever it measures.
 *
 * The AMD module tries each step on the page and reads whether a control is drawn past an edge, on every
 * width change. This port measures, once per content, how wide the line must be for each step
 * ({@see ./measure}), and then takes a step from a width alone: a width change (a ResizeObserver entry)
 * forces no layout. The widths a step needs are the line's breakpoints for that content and language.
 *
 * @module     theme_dimensions/filterline/steps
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** A folding step, each a class dim-line--<step> on the line, which the stylesheet reads. */
export type Step = 's1' | 's2' | 's3' | 's4';

/** A step, and whether the field is folded into its icon at it. */
export type Fold = {step: Step, folded: boolean};

/** The steps, in the order they are tried. */
export const STEPS: Step[] = ['s1', 's2', 's3', 's4'];

/**
 * What is tried, in order: the third step folds the field, and the fourth keeps the field on its first
 * line while it fits there, then folds it.
 */
export const TRIES: Fold[] = [
    {step: 's1', folded: false},
    {step: 's2', folded: false},
    {step: 's3', folded: true},
    {step: 's4', folded: false},
    {step: 's4', folded: true},
];

/** The steps at which the search grows over the line; at the first two nothing moves. */
export const MORPH_STEPS: Step[] = ['s3', 's4'];

/** The step whose Filters panel is a bottom sheet and whose scope has a line of its own. */
export const TWO_LINES: Step = 's4';

/**
 * Pixels a control may run past an edge and still fit: a control is past the edge only beyond one pixel (the
 * AMD line's overflows() rule), which keeps a rounding of a sub-pixel width from folding the line.
 */
export const TOLERANCE = 1;

/** The classes the line carries. */
export const CLASSES = {
    step: 'dim-line--',
    measuring: 'dim-line--measuring',
    searching: 'dim-line--searching',
    folded: 'dim-line--folded',
};

/**
 * The fold a line of a width takes, from the widths each try needs.
 *
 * @param {Array} required The width each of TRIES needs, in order; a try left out is never taken.
 * @param {number} width The line's own content width.
 * @returns {object} The first try that fits, or the last one whatever it measures.
 */
export const choose = (required: number[], width: number): Fold => {
    for (let index = 0; index < TRIES.length - 1; index++) {
        if (index < required.length && required[index] <= width + TOLERANCE) {
            return TRIES[index];
        }
    }
    return TRIES[TRIES.length - 1];
};

/**
 * Whether the widths measured so far decide the fold of a line of a width.
 *
 * {@see ./measure stepWidths()} stops at the first try that fits, so a line that narrows past it needs the
 * tries left out measured before choose() may answer: without them it would jump to the last try.
 *
 * @param {Array} required The width each try measured so far needs, in TRIES' order.
 * @param {number} width The line's own content width.
 * @returns {boolean}
 */
export const settled = (required: number[], width: number): boolean =>
    required.length >= TRIES.length || required.some((need) => need <= width + TOLERANCE);

/**
 * Whether the search grows over the line at a step.
 *
 * @param {object} fold The fold.
 * @returns {boolean}
 */
export const morphs = (fold: Fold | null): boolean => fold !== null && MORPH_STEPS.indexOf(fold.step) >= 0;

/**
 * Whether two folds are the same.
 *
 * @param {object} one A fold, or null.
 * @param {object} two A fold, or null.
 * @returns {boolean}
 */
export const same = (one: Fold | null, two: Fold | null): boolean =>
    one === two || (one !== null && two !== null && one.step === two.step && one.folded === two.folded);

/**
 * Write a fold onto the line's classes, leaving every other class it carries alone.
 *
 * @param {HTMLElement} line The line.
 * @param {object} fold The fold, or null for none (the line as the server drew it).
 * @returns {void}
 */
export const paint = (line: HTMLElement, fold: Fold | null): void => {
    STEPS.forEach((name) => line.classList.toggle(CLASSES.step + name, fold !== null && fold.step === name));
    line.classList.toggle(CLASSES.folded, fold !== null && fold.folded);
};
