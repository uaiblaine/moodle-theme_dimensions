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
 * Tests for the filter line's preview: what it previews, what it decides from, and whom it answers.
 *
 * The page is the template's example: the state chip "Under review" in force from "More statuses", the
 * field 12 at "Online", the period 17 to 24 September 2026, the favourites off.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Preview from '../../src/filterline/preview';
import {drawPage, pointer} from './page';
import type {Card, Page} from './page';

afterEach(() => {
    Preview.reset();
    document.body.replaceChildren();
});

/**
 * Draw the page and arm the preview over it.
 *
 * @param {Array} cards The cards.
 * @returns {object} The page.
 */
const armed = (cards: Card[]): Page => {
    const page = drawPage(cards);
    Preview.arm(page.listing, page.form, {});
    return page;
};

/**
 * One control of the example's form.
 *
 * @param {Page} page The page.
 * @param {string} selector The control.
 * @returns {HTMLElement}
 */
const control = (page: Page, selector: string): HTMLElement => page.form.querySelector<HTMLElement>(selector) as HTMLElement;

/**
 * The marks each card wears: lit, dim or nothing.
 *
 * @param {Page} page The page.
 * @returns {Array}
 */
const marks = (page: Page): string[] => page.cards.map((card) => {
    if (card.classList.contains(Preview.CLASSES.lit)) {
        return 'lit';
    }
    return card.classList.contains(Preview.CLASSES.dim) ? 'dim' : '';
});

describe('theme_dimensions/filterline/preview', () => {
    it('previews a control that would narrow, and nothing in force, nor an "All"', () => {
        const page = armed([]);
        expect(Preview.criterionOf(control(page, '#dim-chip-7-enrolled'))).toEqual({kind: 'chip', value: 'enrolled'});
        expect(Preview.criterionOf(control(page, '#dim-chip-7-all'))).toBeNull();
        expect(Preview.criterionOf(control(page, '#dim-chip-7-pending'))).toBeNull();
        expect(Preview.criterionOf(control(page, '#dim-cf-12-2'))).toEqual({kind: 'field', id: '12', value: '2'});
        expect(Preview.criterionOf(control(page, '#dim-cf-12-any'))).toBeNull();
        expect(Preview.criterionOf(control(page, '#dim-cf-12-1'))).toBeNull();
        expect(Preview.criterionOf(control(page, '[data-region="dim-line-star"]'))).toEqual({kind: 'fav'});
        const quick = page.form.querySelectorAll<HTMLElement>('[data-action="dim-quick"]');
        expect(Preview.criterionOf(quick[0])).toEqual({kind: 'period', from: '2026-09-17', to: '2026-09-17'});
        expect(Preview.criterionOf(quick[1])).toBeNull();
    });

    it('reads the criteria in force off the form', () => {
        const page = armed([]);
        expect(Preview.inForce(page.form)).toEqual({
            chip: 'pending',
            fav: false,
            fields: {'12': '1'},
            from: '2026-09-17',
            to: '2026-09-24',
        });
    });

    it('keeps a card by the facts it states, the pointed filter replacing its own group alone', () => {
        const force = {chip: '', fav: false, fields: {'12': '1'}, from: '', to: ''};
        const facts = {chip: 'enrolled', fav: false, fields: {'12': '1'}, start: '2026-09-20'};
        expect(Preview.keeps(facts, {kind: 'chip', value: 'enrolled'}, force)).toBe(true);
        expect(Preview.keeps(facts, {kind: 'chip', value: 'open'}, force)).toBe(false);
        expect(Preview.keeps(facts, {kind: 'field', id: '12', value: '2'}, force)).toBe(false);
        expect(Preview.keeps(facts, {kind: 'fav'}, force)).toBe(false);
        expect(Preview.keeps(facts, {kind: 'period', from: '2026-09-19', to: '2026-09-21'}, force)).toBe(true);
        expect(Preview.keeps(Object.assign({}, facts, {fields: {'12': '2'}}), {kind: 'chip', value: 'enrolled'}, force)).toBe(false);
    });

    it('stands down on a fact a card does not state: "not known" is never "goes"', () => {
        const force = {chip: '', fav: false, fields: {}, from: '', to: ''};
        expect(Preview.keeps({chip: null, fav: null, fields: null, start: null}, {kind: 'chip', value: 'open'}, force)).toBeNull();
        expect(Preview.keeps({chip: 'open', fav: null, fields: null, start: null}, {kind: 'fav'}, force)).toBeNull();
        expect(Preview.keeps({chip: 'open', fav: true, fields: {'3': '0'}, start: null}, {kind: 'field', id: '12', value: '2'}, force))
            .toBeNull();
    });

    it('lights the cards a control keeps and lets the others recede; one card unknown stands it all down', () => {
        const page = armed([{chip: 'pending', fields: '12:1'}, {chip: 'enrolled', fields: '12:1'}, {chip: 'pending', fields: '12:2'}]);
        Preview.show(control(page, '#dim-cf-12-2'));
        expect(page.listing.classList.contains(Preview.CLASSES.previewing)).toBe(true);
        expect(marks(page)).toEqual(['dim', 'dim', 'lit']);
        Preview.clear();
        expect(marks(page)).toEqual(['', '', '']);
        expect(page.listing.classList.contains(Preview.CLASSES.previewing)).toBe(false);

        const unknown = armed([{chip: 'pending', fields: '12:2'}, {chip: 'pending'}]);
        Preview.reset();
        Preview.arm(unknown.listing, unknown.form, {});
        Preview.show(control(unknown, '#dim-cf-12-2'));
        expect(marks(unknown)).toEqual(['', '']);
    });

    it('answers a mouse or a pen and never a finger, and goes with the pointer leaving the form', () => {
        const page = armed([{chip: 'enrolled'}, {chip: 'open'}]);
        const label = control(page, 'label[for="dim-chip-7-enrolled"]');
        label.dispatchEvent(pointer('pointerover', 'touch'));
        expect(marks(page)).toEqual(['', '']);
        label.dispatchEvent(pointer('pointerover', 'mouse'));
        expect(marks(page)).toEqual(['lit', 'dim']);
        label.dispatchEvent(pointer('pointerout', 'mouse', document.body));
        expect(marks(page)).toEqual(['', '']);
        label.dispatchEvent(pointer('pointerover', 'pen'));
        expect(marks(page)).toEqual(['lit', 'dim']);
        // A press applies the filter: the cards about to be replaced keep no mark meanwhile.
        label.click();
        expect(marks(page)).toEqual(['', '']);
    });

    it('refreshes over a repainted listing and asks the line for the control a suggestion names', () => {
        const page = drawPage([{chip: 'enrolled'}, {chip: 'open'}]);
        const option = document.createElement('div');
        option.setAttribute('role', 'option');
        page.form.append(option);
        Preview.arm(page.listing, page.form, {controlOf: () => control(page, '#dim-chip-7-enrolled')});
        option.dispatchEvent(pointer('pointerover', 'mouse'));
        expect(marks(page)).toEqual(['lit', 'dim']);
        page.cards[1].dataset.chip = 'enrolled';
        Preview.refresh();
        expect(marks(page)).toEqual(['lit', 'lit']);
    });
});
