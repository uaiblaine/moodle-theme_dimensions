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
 * The showcase listing's painters: how both clients put a web service's answer onto the page the server drew.
 *
 * Every word and count is the answer's; a row is rendered through the server's card template into list items
 * never diffed inside; a painter of the line's controls asks drawnByLine() and tells the component instead.
 * The two counters of the document live here, one listing per page. Imported by the visitor's page: nothing
 * here may call a web service, read the session or write a mark on the body.
 *
 * @module     theme_dimensions/listing/paint
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from '@moodle/lms/core/pending';
import {load} from '../amd';
import {markTitles} from '../areas/mark';
import {CHIP_PERIOD, GROUPED_SORTS, PARAM, SCOPE, withPanel} from './address';
import type {ListingState} from './address';
import {LINE, LISTING, SELECTORS} from './events';
import {drawnByLine, tellCalendar, tellLine} from './mounts';
import type {Option} from './mounts';

/** The card partial: one template, one row schema, page one and every later page. */
const CARD_TEMPLATE = 'theme_dimensions/category_card';

/** How many pages one document appends before paging goes back to the server's link: it bounds the DOM. */
const PAGE_WINDOW = 10;

/** The width below which the tree is a disclosure: the stylesheet's md breakpoint (category_scss_test). */
const TREE_PHONE = '(max-width: 767.98px)';

/** A facet of an answer: its options, and the "any" triple beside them, value zero. */
type Facet = {name: string, anyid: string, anylabel: string, anychecked?: boolean, options?: Option[]};

/** A chip of the row under the filter line, as the answer's builder writes it. */
type Chip = {param: string, label: string, url: string, removelabel?: string};

/** The parts of a rows answer the painters read; both web services return this shape. */
export type Answer = {
    statusgroup?: {options?: Option[]} | null,
    facets?: Facet[],
    favourites?: {available?: boolean, pressed?: boolean, count?: number | string} | null,
    filtercount?: number | string,
    filtercountlabel?: string,
    countnumber?: string,
    countline?: string,
    seeallurl?: string,
    hasfilters?: boolean,
    chips?: Chip[],
    clearall?: boolean,
    nexturl?: string,
    hasmore?: boolean,
    total?: number | string,
};

/** How many pages have been appended into this document. */
let appended = 0;

/** Where the number goes in the Filters panel's "Show" sentence: category_toolbar::SHOW_PLACEHOLDER. */
const SHOW_NUMBER = '{n}';

/** How many times the count line has been rewritten, so a repeat still announces. */
let announcements = 0;

/**
 * The element a delegated event happened inside, or null.
 *
 * @param {Event} event The event.
 * @param {string} selector The selector to look for.
 * @returns {HTMLElement|null}
 */
export const closestOf = (event: Event, selector: string): HTMLElement | null => {
    const target = event.target;
    if (!(target instanceof Element)) {
        return null;
    }
    return target.closest<HTMLElement>(selector);
};

/**
 * Paint the view onto the container and the toggle's two buttons. No preference, no address.
 *
 * @param {HTMLElement} root The listing section.
 * @param {string} view Either cards or list.
 * @returns {void}
 */
export const paintView = (root: HTMLElement, view: string): void => {
    root.classList.toggle('dim-vitrine--cards', view === 'cards');
    root.classList.toggle('dim-vitrine--list', view === 'list');
    root.querySelectorAll<HTMLButtonElement>(SELECTORS.viewbutton).forEach((button) => {
        if (!drawnByLine(button)) {
            button.setAttribute('aria-pressed', button.value === view ? 'true' : 'false');
        }
    });
    tellLine(root, LINE.state, {view: view});
    document.dispatchEvent(new CustomEvent(LISTING.view, {detail: {view: view}}));
};

/**
 * Mark the tree entry the listing is narrowed to, and unmark every other.
 *
 * A loop over every link, never "unmark the old, mark the new", which leaves two marks the first time a
 * repaint and a press disagree about the old one. With no category chosen the page's own entry is marked.
 *
 * @param {HTMLElement} root The listing section.
 * @param {object} state The calling client's state.
 * @returns {void}
 */
export const markTree = (root: HTMLElement, state: ListingState): void => {
    root.querySelectorAll<HTMLElement>(SELECTORS.treelink).forEach((link) => {
        const id = Number(link.dataset.categoryid);
        const on = state.cat === 0 ? id === state.categoryid : id === state.cat;
        link.classList.toggle('dim-tree__link--on', on);
        if (on) {
            link.setAttribute('aria-current', 'true');
        } else {
            link.removeAttribute('aria-current');
        }
    });
};

/**
 * Put the period back onto both spellings of the control: the calendar and the two fields.
 *
 * @param {HTMLElement} root The listing section.
 * @param {object} state The calling client's state.
 * @returns {void}
 */
export const paintDates = (root: HTMLElement, state: ListingState): void => {
    tellCalendar(root.querySelector(SELECTORS.daterange), state);
    const fields = root.querySelector(SELECTORS.dateinputs);
    if (!fields) {
        return;
    }
    const inputs = fields.querySelectorAll<HTMLInputElement>('input[type="date"]');
    inputs.forEach((input) => {
        if (input.name === PARAM.from) {
            input.value = state.datefrom;
        } else if (input.name === PARAM.to) {
            input.value = state.dateto;
        }
    });
};

/**
 * Put the favourites toggle in a pressed or released state: its attribute, its glyph and its field.
 *
 * @param {HTMLElement} root The listing section.
 * @param {boolean} on Whether the toggle is on.
 * @returns {void}
 */
export const paintFavToggle = (root: HTMLElement, on: boolean): void => {
    const field = root.querySelector<HTMLInputElement>(SELECTORS.favfield);
    if (field) {
        field.disabled = !on;
    }
    const star = root.querySelector<HTMLButtonElement>(SELECTORS.star);
    if (!star || drawnByLine(star)) {
        tellLine(root, LINE.state, {fav: Boolean(on)});
        return;
    }
    star.setAttribute('aria-pressed', on ? 'true' : 'false');
    star.value = on ? '0' : '1';
    const glyph = star.querySelector('.fa-star');
    if (glyph) {
        glyph.classList.toggle('fa-solid', on);
        glyph.classList.toggle('fa-regular', !on);
    }
};

/**
 * Show the search's eraser exactly while the field holds text.
 *
 * @param {HTMLElement} root The listing section.
 * @returns {void}
 */
export const paintEraser = (root: HTMLElement): void => {
    const search = root.querySelector<HTMLInputElement>(SELECTORS.search);
    const eraser = root.querySelector<HTMLElement>(SELECTORS.eraser);
    // The component's eraser follows its own field.
    if (search && eraser && !drawnByLine(eraser)) {
        eraser.hidden = search.value === '';
    }
};

/**
 * Put a state back onto the controls, after the reader moved through their history.
 *
 * @param {HTMLElement} root The listing section.
 * @param {object} state The calling client's state.
 * @returns {void}
 */
export const paintControls = (root: HTMLElement, state: ListingState): void => {
    tellLine(root, LINE.state, {query: state.query, chip: state.chip, fav: Boolean(state.fav), view: state.view});
    const search = root.querySelector<HTMLInputElement>(SELECTORS.search);
    if (search && !drawnByLine(search)) {
        search.value = state.query;
    }
    paintEraser(root);
    root.querySelectorAll<HTMLElement>(SELECTORS.group).forEach((fieldset) => {
        if (drawnByLine(fieldset)) {
            return;
        }
        const name = fieldset.dataset.name || '';
        const current = name === PARAM.chip
            ? state.chip
            : String(state.filters[name.slice(PARAM.fieldprefix.length)] || 0);
        fieldset.querySelectorAll<HTMLInputElement>('input[type="radio"]').forEach((input) => {
            input.checked = input.value === current;
        });
    });
    const sort = root.querySelector(SELECTORS.sort);
    if (sort) {
        // The empty value and the grouped default are one request: the default's chip is pressed for either.
        sort.querySelectorAll<HTMLInputElement>('input[type="radio"]').forEach((input) => {
            input.checked = state.sort === ''
                ? GROUPED_SORTS.indexOf(input.value) >= 0
                : input.value === state.sort;
        });
    }
    paintFavToggle(root, state.fav);
    paintDates(root, state);
    markTree(root, state);
    paintView(root, state.view);
};

/**
 * Bring the favourites toggle in line with an answer: whether it is pressed, and the number in its name.
 *
 * @param {HTMLElement} root The listing section.
 * @param {object} response The rows response.
 * @returns {void}
 */
export const paintFavourites = (root: HTMLElement, response: Answer): void => {
    const toggle = response.favourites;
    if (!toggle || !toggle.available) {
        return;
    }
    paintFavToggle(root, Boolean(toggle.pressed));
    const count = root.querySelector(SELECTORS.favcount);
    if (count && !drawnByLine(count)) {
        count.textContent = String(Number(toggle.count) || 0);
    }
};

/**
 * Rewrite the badge on the Filter summary, the one number a collapsed panel still shows.
 *
 * @param {HTMLElement} root The listing section.
 * @param {object} response The rows response.
 * @returns {void}
 */
export const paintFilterBadge = (root: HTMLElement, response: Answer): void => {
    const badge = root.querySelector<HTMLElement>(SELECTORS.filterbadge);
    if (!badge || drawnByLine(badge)) {
        return;
    }
    const count = Number(response.filtercount || 0);
    const number = badge.querySelector(SELECTORS.filterbadgenumber);
    if (number) {
        number.textContent = String(count);
    }
    const label = badge.querySelector(SELECTORS.filterbadgelabel);
    if (label) {
        label.textContent = response.filtercountlabel || '';
    }
    badge.hidden = count === 0;
};

/**
 * Rewrite the live count, in a way a screen reader announces even when the sentence repeats.
 *
 * A live region speaks only when its text changes, and the same sentence after a filter that left the same
 * numbers is the case a reader most needs told: a counter of zero-width spaces after it makes every pass differ.
 * Every word is the answer's: the number, the sentence, "See all as a list", and the automatic opening's note,
 * which every answer takes away. The sentence is visually hidden wherever the bar's count says what it says,
 * and shown where it says where the results lie across the areas, which is exactly when the answer offers
 * "See all as a list" (the template's own condition). The same number goes to the bar's count on a line the
 * server drew (the line's component is told it otherwise, tellAnswer()) and into the Filters panel's "Show",
 * through the sentence the server wrote with {n} in the number's place.
 *
 * @param {HTMLElement} root The listing section.
 * @param {object} response The rows response: countnumber, countline and seeallurl.
 * @returns {void}
 */
export const paintCount = (root: HTMLElement, response: Answer): void => {
    const number = response.countnumber || '';
    const total = root.querySelector(SELECTORS.total);
    if (total && !drawnByLine(total)) {
        total.textContent = number;
    }
    const show = root.querySelector<HTMLElement>(SELECTORS.filtersshow);
    const template = show ? show.dataset.dimTemplate || '' : '';
    if (show && template.indexOf(SHOW_NUMBER) >= 0) {
        show.textContent = template.split(SHOW_NUMBER).join(number);
    }
    const url = response.seeallurl || '';
    const line = root.querySelector(SELECTORS.count);
    if (!line) {
        return;
    }
    line.classList.toggle('visually-hidden', url === '');
    const bold = line.querySelector(SELECTORS.countnumber);
    if (bold) {
        bold.textContent = number;
    }
    const text = line.querySelector(SELECTORS.counttext);
    if (text) {
        announcements += 1;
        const marker = document.createElement('span');
        marker.className = 'visually-hidden';
        marker.textContent = '​'.repeat((announcements % 3) + 1);
        text.replaceChildren(document.createTextNode(response.countline || ''), marker);
    }
    // "See all as a list" and the automatic opening's note follow the chips in the row, outside the sentence.
    const seeall = root.querySelector<HTMLElement>(SELECTORS.seeall);
    if (seeall) {
        const link = seeall.querySelector('a');
        if (link && url !== '') {
            link.href = url;
        }
        seeall.hidden = url === '';
    }
    const note = root.querySelector<HTMLElement>(SELECTORS.countnote);
    if (note) {
        note.hidden = true;
    }
};

/**
 * Build one pill - the input and its label - with the markup the toolbar template writes.
 *
 * @param {HTMLElement} track The pill track of the group.
 * @param {string} name The radio group's name.
 * @param {object} option The option the server returned.
 * @returns {object} The input and the label, keyed input and label.
 */
const createPill = (track: HTMLElement, name: string, option: Option): {input: HTMLInputElement, label: HTMLLabelElement} => {
    const input = document.createElement('input');
    input.type = 'radio';
    input.className = 'dim-pill__input visually-hidden';
    input.name = name;
    input.id = option.id;
    input.value = String(option.value);
    const label = document.createElement('label');
    label.className = 'dim-pill';
    label.htmlFor = option.id;
    label.append(document.createTextNode(option.label + ' '));
    const count = document.createElement('span');
    count.className = 'dim-pill__count';
    count.dataset.region = 'dim-pill-count';
    label.append(count);
    track.append(input, label);
    return {input: input, label: label};
};

/**
 * Bring one radio group in line with what the server would have rendered for this request.
 *
 * Updated in place rather than replaced, so focus and the native radio behaviour survive a filter change.
 *
 * @param {HTMLElement|null} fieldset The group's fieldset, or null when this page has no such group.
 * @param {string} name The radio group's name.
 * @param {Array} options The options of this request.
 * @returns {void}
 */
const paintGroup = (fieldset: HTMLElement | null, name: string, options: Option[]): void => {
    if (!fieldset || !options.length) {
        // No options: the state sort was switched off under the page, which still draws its group.
        return;
    }
    const track = fieldset.querySelector<HTMLElement>(SELECTORS.pilltrack) || fieldset;
    const inputs = new Map<string, HTMLInputElement>();
    const labels = new Map<string, HTMLLabelElement>();
    track.querySelectorAll<HTMLInputElement>('input[type="radio"]').forEach((input) => inputs.set(input.value, input));
    track.querySelectorAll<HTMLLabelElement>('label').forEach((label) => labels.set(label.htmlFor, label));
    const seen = new Set<string>();
    const ordered: HTMLElement[] = [];
    options.forEach((option) => {
        const value = String(option.value);
        seen.add(value);
        let input = inputs.get(value);
        let label = input ? labels.get(input.id) : undefined;
        if (!input || !label) {
            const made = createPill(track, name, option);
            input = made.input;
            label = made.label;
        }
        input.hidden = false;
        input.checked = Boolean(option.checked);
        label.hidden = false;
        ordered.push(input, label);
        const count = label.querySelector<HTMLElement>(SELECTORS.pillcount);
        if (count) {
            count.textContent = option.hascount ? String(option.count) : '';
            count.hidden = !option.hascount;
        }
    });
    /* A pill that was absent and comes back is created last, and the group is drawn in the answer's order:
       move only what is out of place, so the focused radio is never detached. */
    let previous: ChildNode | null = null;
    const head = Array.from(track.children).find((child) => child.matches('input[type="radio"], label'));
    let expected: ChildNode | null = head || null;
    ordered.forEach((node) => {
        expected = previous ? previous.nextSibling : expected;
        if (node !== expected) {
            track.insertBefore(node, expected);
        }
        previous = node;
    });
    inputs.forEach((input, value) => {
        if (seen.has(value)) {
            return;
        }
        input.hidden = true;
        input.checked = false;
        const label = labels.get(input.id);
        if (label) {
            label.hidden = true;
        }
    });
};

/**
 * Split the answer's status group between the line's scope and the panel's "More statuses", and paint both.
 *
 * @param {HTMLElement|null} scope The scope's fieldset, or null when the line draws none.
 * @param {HTMLElement|null} more The "More statuses" fieldset, or null.
 * @param {Array} options The status group's options of this request.
 * @returns {void}
 */
const paintStatus = (scope: HTMLElement | null, more: HTMLElement | null, options: Option[]): void => {
    if (!options.length) {
        return;
    }
    /**
     * Whether an option belongs to the line's scope.
     *
     * @param {object} option The option.
     * @returns {boolean}
     */
    const isScope = (option: Option): boolean => SCOPE.indexOf(String(option.value)) >= 0;
    /**
     * Where an option stands in SCOPE's order: the scope is drawn in that order whatever order the answer lists it in.
     *
     * @param {object} option The option.
     * @returns {number}
     */
    const rank = (option: Option): number => SCOPE.indexOf(String(option.value));
    paintGroup(scope, PARAM.chip, options.filter(isScope).sort((a, b) => rank(a) - rank(b)));
    if (!more) {
        return;
    }
    const others = options.filter((option) => !isScope(option));
    if (others.length) {
        paintGroup(more, PARAM.chip, others);
    } else {
        more.querySelectorAll<HTMLInputElement>('input[type="radio"]').forEach((input) => {
            input.hidden = true;
            input.checked = false;
            const label = more.querySelector<HTMLElement>('label[for="' + input.id + '"]');
            if (label) {
                label.hidden = true;
            }
        });
    }
    more.hidden = others.length === 0;
};

/**
 * Bring every group of the toolbar in line with the response.
 *
 * @param {HTMLElement} root The listing section.
 * @param {object} response The rows response.
 * @returns {void}
 */
export const paintGroups = (root: HTMLElement, response: Answer): void => {
    const groups = new Map<string, HTMLElement>();
    root.querySelectorAll<HTMLElement>(SELECTORS.group).forEach((fieldset) => {
        // The component's scope is told by tellAnswer(), and left out here: paintStatus() then paints "More statuses" alone.
        if (drawnByLine(fieldset)) {
            return;
        }
        // The state chip is two fieldsets of one radio group: the line's scope and the panel's "More statuses".
        const name = fieldset.dataset.name || '';
        groups.set(name === PARAM.chip ? name + ':' + (fieldset.dataset.role || '') : name, fieldset);
    });
    if (response.statusgroup) {
        paintStatus(groups.get(PARAM.chip + ':scope') || null, groups.get(PARAM.chip + ':more') || null,
            response.statusgroup.options || []);
    }
    (response.facets || []).forEach((facet) => {
        // A facet's "all" is the any* triple beside its options, put back at their head or the pass hides it.
        const any: Option = {
            id: facet.anyid,
            value: 0,
            label: facet.anylabel,
            count: null,
            hascount: false,
            checked: Boolean(facet.anychecked),
        };
        paintGroup(groups.get(facet.name) || null, facet.name, [any].concat(facet.options || []));
    });
};

/**
 * Build one chip of the row under the filter line, with the markup the server's category_chip writes.
 *
 * @param {object} chip The answer's chip: param, label, url and removelabel.
 * @returns {HTMLElement} The link.
 */
const createChip = (chip: Chip): HTMLAnchorElement => {
    const link = document.createElement('a');
    link.className = 'dim-chip';
    link.href = withPanel(chip.url);
    link.dataset.action = 'dim-chip-remove';
    link.dataset.param = chip.param;
    link.append(document.createTextNode(chip.label + ' '));
    const cross = document.createElement('i');
    cross.className = 'fa fa-xmark';
    cross.setAttribute('aria-hidden', 'true');
    const name = document.createElement('span');
    name.className = 'visually-hidden';
    name.textContent = chip.removelabel || '';
    link.append(cross, name);
    return link;
};

/**
 * Bring the row under the filter line in line with an answer: whether it shows, its chips, "Clear all".
 *
 * @param {HTMLElement} root The listing section.
 * @param {object} response The rows response.
 * @returns {void}
 */
export const paintChips = (root: HTMLElement, response: Answer): void => {
    const row = root.querySelector(SELECTORS.chiprow);
    if (!row) {
        return;
    }
    const narrowed = Boolean(response.hasfilters);
    row.classList.toggle('dim-line__chips--rest', !narrowed);
    // The Filters panel's "Clear filters" is the clear link by another name, offered while anything narrows.
    const panelclear = root.querySelector<HTMLElement>(SELECTORS.filtersclear);
    if (panelclear) {
        panelclear.hidden = !narrowed;
    }
    const chips = response.chips || [];
    const list = row.querySelector(SELECTORS.chiplist);
    if (list) {
        list.replaceChildren(...chips.filter((chip) => chip.param !== CHIP_PERIOD).map(createChip));
    }
    const period = row.querySelector(SELECTORS.rangepill);
    if (period) {
        period.replaceChildren(...chips.filter((chip) => chip.param === CHIP_PERIOD).map(createChip));
    }
    const clear = row.querySelector<HTMLElement>(SELECTORS.clear);
    if (clear) {
        clear.hidden = !response.clearall;
    }
};

/**
 * Let a batch of freshly inserted rows fade in.
 *
 * A row is inserted under the enter class (`opacity: 0`): unrevealed, it is in the document and invisible. Two
 * frames, because an element transitions from the style it was laid out with. Held as pending JavaScript:
 * WebDriver reads an element at opacity 0 as not displayed.
 *
 * @param {Array} items The list items just inserted.
 * @returns {void}
 */
const reveal = (items: HTMLElement[]): void => {
    const pending = new Pending('theme_dimensions/listing:reveal');
    window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
        items.forEach((item) => item.classList.remove('dim-vitrine__item--enter'));
        // The stylesheet's duration, read rather than restated: none under reduced motion.
        const fade = items.length ? parseFloat(window.getComputedStyle(items[0]).transitionDuration) * 1000 : 0;
        window.setTimeout(() => pending.resolve(), fade || 0);
    }));
};

/**
 * Render the returned rows into list items, through the server's own card template.
 *
 * The items are inserted in ONE call, into a holder the caller then empties into the grid: each insertion
 * announces its node to core's filter event, and Boost's `enableTooltips()` answers every announced node with a
 * `document` keydown listener it never removes, so one insertion per card left a listener per card for the
 * rest of the page (the panel's transplant announces its holder once the same way).
 *
 * @param {Array} cards The card contexts.
 * @returns {Promise} Resolving to the list items, in order.
 */
export const renderCards = async(cards: object[]): Promise<HTMLElement[]> => {
    const Templates = await load('core/templates');
    const rendered = await Promise.all(cards.map((card) => Templates.renderForPromise(CARD_TEMPLATE, card)));
    if (!rendered.length) {
        return [];
    }
    const holder = document.createElement('ul');
    const html = rendered.map((result) => `<li class="dim-vitrine__item dim-vitrine__item--enter">${result.html}</li>`);
    const js = rendered.map((result) => result.js).filter((one) => one.trim() !== '');
    Templates.appendNodeContents(holder, html.join(''), js.join(';\n'));
    return Array.from(holder.children) as HTMLElement[];
};

/**
 * Give the load more button its label and its enabled state back.
 *
 * @param {HTMLElement|null} more The load more button.
 * @returns {void}
 */
export const restoreMore = (more: HTMLButtonElement | null): void => {
    if (!more) {
        return;
    }
    more.disabled = false;
    more.textContent = more.dataset.label || more.textContent;
};

/**
 * Move focus after an append, so a keyboard reader is never dropped onto the document body.
 *
 * The restore comes first: a disabled button is not focusable, so focusing it before re-enabling it is a
 * silent no-op that leaves the reader at the top of the document.
 *
 * @param {HTMLElement|null} more The load more button.
 * @param {HTMLElement|null} next The continue link.
 * @param {HTMLElement|null} first The first newly appended row.
 * @returns {void}
 */
const focusAfterAppend = (more: HTMLButtonElement | null, next: HTMLElement | null, first: HTMLElement | null): void => {
    if (more && !more.hidden) {
        restoreMore(more);
        more.focus();
        return;
    }
    if (next && !next.hidden) {
        next.focus();
        return;
    }
    if (first) {
        const title = first.querySelector<HTMLElement>(SELECTORS.cardtitle);
        if (title) {
            title.focus();
            return;
        }
        first.tabIndex = -1;
        first.focus();
    }
};

/**
 * Bring the pager region in line: the numbered bar off, then the button or the link, never both.
 *
 * @param {HTMLElement} root The listing section.
 * @param {object} response The rows response.
 * @param {boolean} append Whether this response was appended rather than replacing the grid.
 * @param {HTMLElement|null} first The first newly appended row, for focus.
 * @returns {void}
 */
export const paintPager = (root: HTMLElement, response: Answer, append: boolean, first: HTMLElement | null): void => {
    const bar = root.querySelector<HTMLElement>(SELECTORS.pagerbar);
    if (bar) {
        bar.hidden = true;
    }
    const more = root.querySelector<HTMLButtonElement>(SELECTORS.loadmore);
    const next = root.querySelector<HTMLAnchorElement>(SELECTORS.nextpage);
    const closed = appended >= PAGE_WINDOW;
    if (next) {
        if (response.nexturl) {
            next.href = response.nexturl;
        }
        next.hidden = !(response.hasmore && closed);
    }
    if (more) {
        more.hidden = !response.hasmore || closed;
    }
    if (append) {
        focusAfterAppend(more, next, first);
    }
};

/**
 * Put the rows of a response into the course grid: appended, or replacing what was there.
 *
 * @param {HTMLElement} root The listing section.
 * @param {object} state The calling client's state; its query marks the rows.
 * @param {HTMLElement} grid The course grid.
 * @param {Array} items The list items just rendered.
 * @param {boolean} append Whether to append them rather than replace the grid.
 * @param {Function|null} discard Called with each card a replace discards, before it goes; null for none.
 * @returns {void}
 */
export const paintGrid = (
    root: HTMLElement,
    state: ListingState,
    grid: HTMLElement,
    items: HTMLElement[],
    append: boolean,
    discard: ((card: HTMLElement) => void) | null,
): void => {
    if (append) {
        appended += 1;
        grid.append(...items);
    } else {
        appended = 0;
        if (discard) {
            grid.querySelectorAll<HTMLElement>(SELECTORS.card).forEach((card) => discard(card));
        }
        grid.replaceChildren(...items);
    }
    reveal(items);
    // The searched term, marked in the titles of the rows just drawn; an append marks its own.
    items.forEach((item) => markTitles(item, state.query));
    const empty = root.querySelector<HTMLElement>(SELECTORS.empty);
    grid.hidden = grid.childElementCount === 0;
    if (empty) {
        empty.hidden = !grid.hidden;
    }
};

/**
 * Show the courses sitting directly in the category, and the link to them, exactly when one matches.
 *
 * @param {HTMLElement} root The listing section.
 * @param {object} response The rows response.
 * @returns {void}
 */
export const paintDirect = (root: HTMLElement, response: Answer): void => {
    const direct = root.querySelector<HTMLElement>(SELECTORS.direct);
    if (!direct || !root.querySelector(SELECTORS.area)) {
        return;
    }
    const none = Number(response.total) === 0;
    direct.hidden = none;
    const skip = root.querySelector<HTMLElement>(SELECTORS.directskip);
    if (skip) {
        skip.hidden = none;
    }
};

/**
 * Close the tree's disclosure on a phone, and keep it usable when the width changes under it.
 *
 * @param {HTMLElement} root The listing section.
 * @returns {void}
 */
export const armTreebox = (root: HTMLElement): void => {
    const box = root.querySelector<HTMLDetailsElement>(SELECTORS.treebox);
    if (!box) {
        return;
    }
    const phone = window.matchMedia(TREE_PHONE);
    box.open = !phone.matches;
    phone.addEventListener('change', (event) => {
        box.open = !event.matches;
    });
};
