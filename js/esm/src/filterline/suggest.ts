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
 * The filter line's suggestions: which filters a text offers, and in what order.
 *
 * The suggestions are the filters already on the page - every scope cell, the favourites star, every option
 * of the Filters panel and the date layer's quick dates - whose label or group matches the typed text, accent- and
 * case-insensitively, word starts first and then by count, each with its group and a count the page has
 * shown for it.
 *
 * That count is the server's last answer given while the field held no term ({@see remember}), so a term
 * typed since - which the client searches as it is typed, and which zeroes the options of a word that
 * names no course - neither changes nor hides a suggestion. A page opened on an address that already
 * carries a term has no such answer and offers the counts it shows, which include the term: the count
 * without the term is the server's to answer, and asking it would cost a request per keystroke.
 *
 * @module     theme_dimensions/filterline/suggest
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {normalise} from './mark';
import {TIP} from './model';

/** The search's own floor (criteria::MIN_SEARCH_LENGTH): a shorter text suggests what an empty one does. */
export const MIN_TERM = 2;

/** How many filters the empty field offers to start with: the highest counts. */
export const START_COUNT = 5;

/** How many matching filters a typed text is offered. */
export const MATCH_COUNT = 8;

/** One filter a suggestion may apply: the control it presses, its words and the count the page showed. */
export type Offer = {
    control: HTMLElement,
    label: string,
    group: string,
    count: number | null,
    order: number,
};

/** What a control showed in the last answer given while the field held no term. */
export type Rest = {count: number | null, hidden: boolean};

/** Every hook the offers are read from, all of them written by the toolbar's template. */
const SELECTORS = {
    group: '[data-region="dim-vitrine-group"]',
    pillcount: '[data-region="dim-pill-count"]',
    star: '[data-region="dim-line-star"]',
    favcount: '[data-region="dim-fav-count"]',
    date: '[data-region="dim-date"]',
    quick: '[data-action="dim-quick"]',
};

/**
 * How well a normalised text matches a normalised term: 0 where a word starts with it, 1 where it is
 * inside a word, -1 where it is not there at all.
 *
 * @param {string} text The normalised text.
 * @param {string} term The normalised term.
 * @returns {number}
 */
export const matchOf = (text: string, term: string): number => {
    let at = text.indexOf(term);
    if (at < 0) {
        return -1;
    }
    while (at >= 0) {
        if (at === 0 || !/[a-z0-9]/.test(text.charAt(at - 1))) {
            return 0;
        }
        at = text.indexOf(term, at + 1);
    }
    return 1;
};

/**
 * The rank of a filter for a term: its label's word start first, then its group's, then inside its
 * label, then inside its group; -1 where neither holds the term.
 *
 * @param {object} item A filter: label and group.
 * @param {string} term The normalised term.
 * @returns {number}
 */
export const rankOf = (item: {label: string, group: string}, term: string): number => {
    const label = matchOf(normalise(item.label), term);
    const group = matchOf(normalise(item.group), term);
    if (label === 0) {
        return 0;
    }
    if (group === 0) {
        return 1;
    }
    if (label === 1) {
        return 2;
    }
    return group === 1 ? 3 : -1;
};

/**
 * The filters to suggest for a text: what matches it, best first; with less than the search's floor, the
 * few with the highest counts to start with.
 *
 * Word starts come first, then the larger count, then the order the page draws them in. A filter whose
 * count the page does not show sorts after every counted one.
 *
 * @param {Array} items The filters on the page.
 * @param {string} text What is in the field.
 * @returns {Array} The filters to offer, in order.
 */
export const suggest = <T extends {label: string, group: string, count: number | null, order: number}>(
    items: T[],
    text: string
): T[] => {
    const term = normalise(text);
    /**
     * A filter's count for the order: one the page does not show sorts after every counted one.
     *
     * @param {object} item The filter.
     * @returns {number}
     */
    const count = (item: T): number => (item.count === null ? -1 : item.count);
    if (term.length < MIN_TERM) {
        return items.slice().sort((a, b) => count(b) - count(a) || a.order - b.order).slice(0, START_COUNT);
    }
    return items.map((item) => ({item: item, rank: rankOf(item, term)}))
        .filter((entry) => entry.rank >= 0)
        .sort((a, b) => a.rank - b.rank || count(b.item) - count(a.item) || a.item.order - b.item.order)
        .slice(0, MATCH_COUNT)
        .map((entry) => entry.item);
};

/**
 * A label's own words, without the count drawn inside it.
 *
 * @param {HTMLElement} label The label.
 * @returns {string}
 */
export const wordsOf = (label: Element): string => Array.from(label.childNodes)
    .filter((node) => node.nodeType === Node.TEXT_NODE)
    .map((node) => node.textContent || '')
    .join('')
    .trim();

/**
 * The count a label shows, or null where it shows none.
 *
 * @param {HTMLElement} label The label.
 * @returns {number|null}
 */
export const countOf = (label: Element): number | null => {
    const count = label.querySelector<HTMLElement>(SELECTORS.pillcount);
    if (!count || count.hidden || (count.textContent || '').trim() === '') {
        return null;
    }
    return Number(count.textContent);
};

/**
 * The words of a group's legend.
 *
 * @param {Element|null} fieldset The group.
 * @returns {string}
 */
const legendOf = (fieldset: Element | null): string => {
    const legend = fieldset ? fieldset.querySelector('legend') : null;
    return legend ? (legend.textContent || '').trim() : '';
};

/**
 * Each radio of the form's groups with its label and its group, the order the page draws them in.
 *
 * @param {HTMLElement} form The toolbar form.
 * @param {Function} each Called with the radio, its label and its fieldset.
 * @returns {void}
 */
const eachRadio = (form: HTMLElement, each: (input: HTMLInputElement, label: HTMLElement, fieldset: HTMLElement) => void):
    void => {
    form.querySelectorAll<HTMLElement>(SELECTORS.group).forEach((fieldset) => {
        fieldset.querySelectorAll<HTMLInputElement>('input[type="radio"]').forEach((input) => {
            const label = fieldset.querySelector<HTMLElement>(`label[for="${input.id}"]`);
            if (label) {
                each(input, label, fieldset);
            }
        });
    });
};

/**
 * Keep what every filter control shows, while the field holds no term: the counts a suggestion offers.
 *
 * Called when the line is drawn and after every answer is painted, so it records each answer painted
 * while the field is empty, and none while a typed term is being searched.
 *
 * @param {HTMLElement} form The toolbar form.
 * @param {string} text What is in the field.
 * @param {WeakMap} rest Where the counts are kept, by control.
 * @returns {void}
 */
export const remember = (form: HTMLElement, text: string, rest: WeakMap<Element, Rest>): void => {
    if (normalise(text) !== '') {
        return;
    }
    eachRadio(form, (input, label, fieldset) => {
        rest.set(input, {count: countOf(label), hidden: fieldset.hidden || input.hidden || label.hidden});
    });
    const star = form.querySelector(SELECTORS.star);
    const count = star ? star.querySelector(SELECTORS.favcount) : null;
    if (star && count) {
        rest.set(star, {count: Number(count.textContent), hidden: false});
    }
};

/**
 * Every filter on the page a suggestion may apply, with the counts the page showed before a term was typed.
 *
 * A filter already in force, the "All" of a group (which narrows nothing), anything hidden and anything
 * counted at zero are left out: choosing one of those would add nothing or empty the listing. Hidden and
 * counted are read as the page stood before a term was typed ({@see remember}).
 *
 * @param {HTMLElement} form The toolbar form.
 * @param {WeakMap} rest The counts kept while the field was empty.
 * @returns {Array} The offers, in the page's order.
 */
export const offers = (form: HTMLElement, rest: WeakMap<Element, Rest>): Offer[] => {
    const found: Offer[] = [];
    /**
     * Offer one filter, unless it has no words or would empty the listing.
     *
     * @param {HTMLElement} control The control it presses.
     * @param {string} label Its words.
     * @param {string} group Its group's words.
     * @param {number|null} count The count the page showed, or null.
     * @returns {void}
     */
    const add = (control: HTMLElement, label: string, group: string, count: number | null): void => {
        if (label !== '' && count !== 0) {
            found.push({control: control, label: label, group: group, count: count, order: found.length});
        }
    };
    eachRadio(form, (input, label, fieldset) => {
        if (input.checked || input.value === '' || input.value === '0') {
            return;
        }
        const known = rest.get(input) || {count: countOf(label), hidden: input.hidden || label.hidden};
        if (!known.hidden) {
            add(input, wordsOf(label), legendOf(fieldset), known.count);
        }
    });
    const star = form.querySelector<HTMLElement>(SELECTORS.star);
    if (star && star.getAttribute('aria-pressed') !== 'true') {
        const count = star.querySelector(SELECTORS.favcount);
        const known = rest.get(star) || {count: count ? Number(count.textContent) : null, hidden: false};
        // The star's word is its tooltip's, which repeats its name: it carries no title.
        add(star, star.getAttribute(TIP) || '', '', known.count);
    }
    const date = form.querySelector(SELECTORS.date);
    if (date) {
        date.querySelectorAll<HTMLElement>(SELECTORS.quick).forEach((link) => {
            if (!link.hasAttribute('aria-current')) {
                add(link, (link.textContent || '').trim(), legendOf(date), null);
            }
        });
    }
    return found;
};
