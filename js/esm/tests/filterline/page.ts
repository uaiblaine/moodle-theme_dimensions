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
 * A listing page as the server draws it, for the filter line's suites: the toolbar of the template's
 * example, and a grid of cards stating the facts the preview reads.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {TOOLBAR} from './toolbar';

/** The id of the example's form. */
export const FORM = 'dim-vitrine-form-7';

/** The parts of the page a test reaches for. */
export type Page = {
    listing: HTMLElement,
    form: HTMLFormElement,
    line: HTMLElement,
    cards: HTMLElement[],
};

/** One card: its state chip, its fields, its start day and whether its star is pressed (null: no star). */
export type Card = {chip?: string, fields?: string, start?: string, fav?: boolean | null};

/**
 * Draw the page into the document.
 *
 * @param {Array} cards The cards of the grid.
 * @returns {object} The page's parts.
 */
export const drawPage = (cards: Card[] = []): Page => {
    const listing = document.createElement('section');
    listing.dataset.region = 'dim-vitrine';
    listing.innerHTML = TOOLBAR;
    const grid = document.createElement('ul');
    grid.dataset.region = 'dim-vitrine-grid';
    cards.forEach((card, index) => {
        const article = document.createElement('article');
        article.dataset.region = 'dim-card';
        article.dataset.courseid = String(index + 1);
        if (card.chip !== undefined) {
            article.dataset.chip = card.chip;
        }
        if (card.fields !== undefined) {
            article.dataset.fields = card.fields;
        }
        if (card.start !== undefined) {
            article.dataset.start = card.start;
        }
        if (card.fav !== undefined && card.fav !== null) {
            const star = document.createElement('button');
            star.dataset.action = 'dim-star';
            star.setAttribute('aria-pressed', card.fav ? 'true' : 'false');
            article.append(star);
        }
        const item = document.createElement('li');
        item.append(article);
        grid.append(item);
    });
    listing.append(grid);
    document.body.append(listing);
    const form = listing.querySelector<HTMLFormElement>(`#${FORM}`) as HTMLFormElement;
    return {
        listing: listing,
        form: form,
        line: form.querySelector<HTMLElement>('[data-region="dim-line"]') as HTMLElement,
        cards: Array.from(listing.querySelectorAll<HTMLElement>('[data-region="dim-card"]')),
    };
};

/**
 * An element's tree as a string a test can compare: tag, attributes sorted, text trimmed, whitespace
 * between elements dropped. The browser lays whitespace between flex items out as nothing, and every
 * container of the line is a flex box.
 *
 * @param {Element} element The element.
 * @param {Array} ignore Attribute names to leave out, as name or tag:name.
 * @returns {string}
 */
export const shape = (element: Element, ignore: string[] = []): string => {
    const tag = element.tagName.toLowerCase();
    const attributes = Array.from(element.attributes)
        .filter((attribute) => ignore.indexOf(attribute.name) < 0 && ignore.indexOf(`${tag}:${attribute.name}`) < 0)
        .map((attribute) => `${attribute.name}="${attribute.value}"`)
        .sort();
    const children = Array.from(element.childNodes).map((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            const text = (node.textContent || '').replace(/\s+/g, ' ');
            return text.trim() === '' ? '' : text;
        }
        return node.nodeType === Node.ELEMENT_NODE ? shape(node as Element, ignore) : '';
    }).join('');
    return `<${[tag].concat(attributes).join(' ')}>${children}</${tag}>`;
};

/**
 * A pointer event of a kind, which jsdom does not construct.
 *
 * @param {string} type The event's type.
 * @param {string} pointerType mouse, pen or touch.
 * @param {Element|null} related The relatedTarget.
 * @returns {Event}
 */
export const pointer = (type: string, pointerType: string, related: Element | null = null): Event => {
    const event = new MouseEvent(type, {bubbles: true, relatedTarget: related});
    Object.defineProperty(event, 'pointerType', {value: pointerType});
    return event;
};
