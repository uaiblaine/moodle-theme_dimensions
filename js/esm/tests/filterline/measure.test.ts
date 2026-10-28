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
 * jsdom lays nothing out, so each control's box is stubbed as the stylesheet would draw it at each step:
 * Filters loses its word from the second step, the field gives way to its 40px icon when folded, and the
 * fourth step takes the scope and the view toggle off the first line.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {requiredWidth, stepWidths} from '../../src/filterline/measure';
import {drawPage} from './page';

afterEach(() => {
    document.body.replaceChildren();
});

/**
 * Lay the line's controls out in a row, each as wide as the classes on the line make it.
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
};

/**
 * The box of a control of the example's line at the line's step, as the stylesheet draws it.
 *
 * @param {Element} child The control.
 * @param {DOMTokenList} classes The line's classes.
 * @returns {number}
 */
const example = (child: Element, classes: DOMTokenList): number => {
    const region = child.getAttribute('data-region') || child.getAttribute('data-action') || '';
    const four = classes.contains('dim-line--s4');
    const folded = classes.contains('dim-line--folded');
    const absent = child.querySelector('label[hidden]') !== null;
    const widths: Record<string, number> = {
        'dim-vitrine-group': four ? 0 : (absent ? 230 : 300),
        'dim-line-search': folded ? 0 : 400,
        'dim-search-open': folded ? 40 : 0,
        'dim-line-star': 40,
        'dim-filters': classes.contains('dim-line--s1') ? 120 : 60,
        'dim-organise': 40,
        'dim-view-row': four ? 0 : 80,
        'dim-line-manage': 42,
    };
    return widths[region] || 0;
};

describe('theme_dimensions/filterline/measure', () => {
    it('counts the controls from the first one\'s start to the last one\'s end, the search at its floor', () => {
        const {line} = drawPage();
        const search = line.querySelector('[data-region="dim-line-search"]') as HTMLElement;
        search.style.minWidth = '208px';
        layout(line, example);
        line.classList.add('dim-line--s1');
        // The scope as drawn (its absent cell hidden), the search at its floor, the rest, and six gaps.
        expect(requiredWidth(line)).toBe(230 + 208 + 40 + 120 + 40 + 80 + 42 + 6 * 10);
    });

    it('measures every step once, with every scope cell drawn and nothing in transition, and puts the line back', () => {
        const {line} = drawPage();
        const search = line.querySelector('[data-region="dim-line-search"]') as HTMLElement;
        search.style.minWidth = '208px';
        line.classList.add('dim-line--s3', 'dim-line--folded', 'dim-line--searching');
        const absent = line.querySelector('label[for="dim-chip-7-open"]') as HTMLElement;
        expect(absent.hidden).toBe(true);
        let drawnwhilemeasured = false;
        const read = example;
        layout(line, (child, classes) => {
            if (child === line.firstElementChild && !absent.hidden) {
                drawnwhilemeasured = true;
            }
            return read(child, classes);
        });

        const widths = stepWidths(line, {step: 's3', folded: true});

        expect(widths).toEqual([
            300 + 208 + 40 + 120 + 40 + 80 + 42 + 60,
            300 + 208 + 40 + 60 + 40 + 80 + 42 + 60,
            300 + 40 + 40 + 60 + 40 + 80 + 42 + 60,
            208 + 40 + 60 + 40 + 42 + 40,
            40 + 40 + 60 + 40 + 42 + 40,
        ]);
        expect(drawnwhilemeasured).toBe(true);
        expect(absent.hidden).toBe(true);
        expect(Array.from(line.classList).sort()).toEqual(['dim-line', 'dim-line--folded', 'dim-line--s3', 'dim-line--searching']);
    });

    it('stops at the first try that fits the width it is given, so a wide line pays one layout', () => {
        const {line} = drawPage();
        const search = line.querySelector('[data-region="dim-line-search"]') as HTMLElement;
        search.style.minWidth = '208px';
        const tried: string[] = [];
        layout(line, (child, classes) => {
            const fold = Array.from(classes).filter((name) => /--s\d|--folded/.test(name)).join(' ');
            // Every control's box reads its siblings' widths too: count each step once, as it comes.
            if (fold !== '' && tried[tried.length - 1] !== fold) {
                tried.push(fold);
            }
            return example(child, classes);
        });
        const full = 300 + 208 + 40 + 120 + 40 + 80 + 42 + 60;

        expect(stepWidths(line, null, 1200)).toEqual([full]);
        expect(tried).toEqual(['dim-line--s1']);

        // Narrower than the first try: it goes on to the second, which fits, and no further.
        tried.length = 0;
        expect(stepWidths(line, null, full - 50)).toEqual([full, full - 60]);
        expect(tried).toEqual(['dim-line--s1', 'dim-line--s2']);
        expect(line.classList.contains('dim-line--measuring')).toBe(false);
    });

    it('measures under the measuring class and never with the search open over the line', () => {
        const {line} = drawPage();
        const classes = new Set<string>();
        layout(line, (child, current) => {
            classes.add(Array.from(current).join(' '));
            return 10;
        });
        line.classList.add('dim-line--searching');
        stepWidths(line, null);
        expect(Array.from(classes)).toEqual([
            'dim-line dim-line--measuring dim-line--s1',
            'dim-line dim-line--measuring dim-line--s2',
            'dim-line dim-line--measuring dim-line--s3 dim-line--folded',
            'dim-line dim-line--measuring dim-line--s4',
            'dim-line dim-line--measuring dim-line--s4 dim-line--folded',
        ]);
        expect(line.classList.contains('dim-line--measuring')).toBe(false);
    });
});
