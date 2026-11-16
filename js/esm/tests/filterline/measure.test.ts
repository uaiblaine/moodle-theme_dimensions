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
 * Tests for what the filter line measures: the width each step needs, with every scope cell drawn.
 *
 * jsdom lays nothing out, so each control's box is stubbed as the stylesheet would draw it at each try: the
 * search's capsule holds the open field where the try opens it in place and the search's button otherwise, and
 * the two-line step takes the scope off the first line. The cluster is the same at every step.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {requiredWidth, stepWidths} from '../../src/filterline/measure';
import {drawPage} from './page';

afterEach(() => {
    document.body.replaceChildren();
});

/** The open field's own width and its floor, inside the search's capsule. */
const FIELD = 220;
const FLOOR = 160;

/**
 * Lay the line's controls out in a row, each as wide as the classes on the line make it, the open field inside its
 * capsule as wide as the line's classes make it too.
 *
 * @param {HTMLElement} line The line.
 * @param {Function} widthOf The width of a child for the line's classes, 0 for none drawn.
 * @returns {void}
 */
const layout = (line: HTMLElement, widthOf: (child: Element, classes: DOMTokenList) => number): void => {
    const gap = 10;
    Array.from(line.children).forEach((child) => {
        (child as HTMLElement).getBoundingClientRect = () => {
            let left = 0;
            for (const sibling of Array.from(line.children)) {
                if (sibling === child) {
                    break;
                }
                const width = widthOf(sibling, line.classList);
                left += width > 0 ? width + gap : 0;
            }
            const width = widthOf(child, line.classList);
            return {left: left, right: left + width, width: width, top: 0, bottom: 40, height: 40, x: left, y: 0} as DOMRect;
        };
    });
    const search = line.querySelector('[data-region="dim-line-search"]') as HTMLElement;
    search.style.minWidth = `${FLOOR}px`;
    search.getBoundingClientRect = () => {
        const width = line.classList.contains('dim-line--open') ? FIELD : 0;
        return {left: 0, right: width, width: width, top: 0, bottom: 32, height: 32, x: 0, y: 0} as DOMRect;
    };
};

/**
 * The box of a control of the example's line at the line's try, as the stylesheet draws it.
 *
 * @param {Element} child The control.
 * @param {DOMTokenList} classes The line's classes.
 * @returns {number}
 */
const example = (child: Element, classes: DOMTokenList): number => {
    const region = child.getAttribute('data-region') || child.getAttribute('data-action') || '';
    const two = classes.contains('dim-line--s2');
    const open = classes.contains('dim-line--open');
    const absent = child.querySelector('label[hidden]') !== null;
    const widths: Record<string, number> = {
        'dim-vitrine-group': two ? 0 : (absent ? 230 : 300),
        'dim-line-cluster': 216,
        // The button and the managers' menu, or the open field and the menu.
        'dim-line-searchcap': open ? 40 + FIELD : 76,
    };
    return widths[region] || 0;
};

describe('theme_dimensions/filterline/measure', () => {
    it('counts the controls from the first one\'s start to the last one\'s end, the open field at its floor', () => {
        const {line} = drawPage();
        layout(line, example);
        line.classList.add('dim-line--s1', 'dim-line--open');
        // The scope as drawn (its absent cell hidden), the cluster, the capsule with its field at the floor, two gaps.
        expect(requiredWidth(line)).toBe(230 + 216 + 40 + FLOOR + 2 * 10);
        line.classList.remove('dim-line--open');
        // Folded to its button the search has no field to give way.
        expect(requiredWidth(line)).toBe(230 + 216 + 76 + 2 * 10);
    });

    it('measures every try once, with every scope cell drawn and nothing in transition, and puts the line back', () => {
        const {line} = drawPage();
        line.classList.add('dim-line--s1', 'dim-line--over', 'dim-line--searching');
        const absent = line.querySelector('label[for="dim-chip-7-open"]') as HTMLElement;
        expect(absent.hidden).toBe(true);
        let drawnwhilemeasured = false;
        layout(line, (child, classes) => {
            if (child === line.firstElementChild && !absent.hidden) {
                drawnwhilemeasured = true;
            }
            return example(child, classes);
        });

        const widths = stepWidths(line, {step: 's1', over: true});

        expect(widths).toEqual([
            300 + 216 + 40 + FLOOR + 20,
            300 + 216 + 76 + 20,
            216 + 76 + 10,
        ]);
        expect(drawnwhilemeasured).toBe(true);
        expect(absent.hidden).toBe(true);
        expect(Array.from(line.classList).sort()).toEqual(['dim-line', 'dim-line--over', 'dim-line--s1', 'dim-line--searching']);
    });

    it('stops at the first try that fits the width it is given, so a wide line pays one layout', () => {
        const {line} = drawPage();
        const tried: string[] = [];
        layout(line, (child, classes) => {
            const fold = Array.from(classes).filter((name) => /--s\d|--over|--open/.test(name)).sort().join(' ');
            // Every control's box reads its siblings' widths too: count each try once, as it comes.
            if (fold !== '' && tried[tried.length - 1] !== fold) {
                tried.push(fold);
            }
            return example(child, classes);
        });
        const full = 300 + 216 + 40 + FLOOR + 20;

        expect(stepWidths(line, null, 1200)).toEqual([full]);
        expect(tried).toEqual(['dim-line--open dim-line--s1']);

        // Narrower than the first try: it goes on to the second, which fits, and no further.
        tried.length = 0;
        expect(stepWidths(line, null, full - 50)).toEqual([full, 300 + 216 + 76 + 20]);
        expect(tried).toEqual(['dim-line--open dim-line--s1', 'dim-line--over dim-line--s1']);
        expect(line.classList.contains('dim-line--measuring')).toBe(false);
        expect(line.classList.contains('dim-line--open')).toBe(false);
    });

    it('measures under the measuring class, never with the search open over the line, and puts an open field back', () => {
        const {line} = drawPage();
        const classes = new Set<string>();
        layout(line, (child, current) => {
            classes.add(Array.from(current).sort().join(' '));
            return 10;
        });
        line.classList.add('dim-line--searching');
        stepWidths(line, null);
        expect(Array.from(classes)).toEqual([
            'dim-line dim-line--measuring dim-line--open dim-line--s1',
            'dim-line dim-line--measuring dim-line--over dim-line--s1',
            'dim-line dim-line--measuring dim-line--over dim-line--s2',
        ]);
        expect(line.classList.contains('dim-line--measuring')).toBe(false);
        expect(line.classList.contains('dim-line--searching')).toBe(true);

        line.classList.remove('dim-line--searching');
        line.classList.add('dim-line--open');
        stepWidths(line, {step: 's1', over: false});
        expect(Array.from(line.classList).sort()).toEqual(['dim-line', 'dim-line--open', 'dim-line--s1']);
    });
});
