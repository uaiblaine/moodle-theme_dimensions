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
 * The learning plans page: block_dimensions' plan cards kept through core's re-renders, and the bar above them.
 *
 * The theme's copy of tool_lp/plans_page starts this module from its js block, on the first paint and again
 * every time core's tool_lp/planactions replaces the region after a row action (it re-renders the template on
 * the client from tool_lp_data_for_plans_page and runs the js block again). That re-run is the re-arm point, so
 * no observer is needed. The state below lives as long as the page, never across page loads.
 *
 * - First paint (the root carries data-dim-layout): the layout's item classes and each plan's card are remembered.
 * - Core re-rendered (no data-dim-layout): the remembered classes and cards are put back at once, so nothing
 *   flashes, then theme_dimensions_get_plan_cards is asked for fresh cards (a status chip, a button or a trail may
 *   have changed). The latest request wins; a failure keeps what is there, since the fallback card already names
 *   the plan.
 * - The bar: a scope by status (a cell counting nothing is not drawn, so drafts only appear where core shows them),
 *   the count of plans shown, and a search over the plans' names in place.
 *
 * @module     theme_dimensions/plancards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Pending from 'core/pending';
import {getString} from 'core/str';

/** The web service that answers the cards after a re-render. */
const CARDS = 'theme_dimensions_get_plan_cards';

/** The statuses in the scope's order, as the template writes them on each plan. */
const ORDER = ['active', 'review', 'complete', 'draft'];

const SELECTORS = {
    ROOT: '[data-region="plans"]',
    NODE: '[data-region="plan-node"]',
    EXTRA: '[data-region="dim-comp-extra"]',
    FALLBACK: '.dim-comp-plan-fallback',
    TITLE: 'h4',
    BAR: '[data-region="dim-comp-bar"]',
    SCOPE: '[data-region="dim-comp-scope"]',
    CELL: '[data-region="dim-comp-scope"] [role="radio"]',
    CELLCOUNT: '[data-region="dim-comp-scope-count"]',
    TOTAL: '[data-region="dim-comp-total"]',
    OPEN: '[data-action="dim-comp-search-open"]',
    FIELD: '.dim-comp-search-field',
    INPUT: '.dim-comp-search-input',
    ERASER: '[data-action="dim-comp-search-clear"]',
    NOMATCH: '[data-region="dim-comp-nomatch"]',
    TRIGGER: '.dim-comp-plan-menu-trigger',
    TRIGGERNAME: '.visually-hidden',
};

/** What the page remembers between core's re-renders. */
const state = {
    itemclasses: '',
    cards: new Map(),
    scope: '',
    text: '',
    ticket: 0,
    paints: 0,
    slashwired: false,
};

/**
 * A text as the search compares it: lower case, accents dropped.
 *
 * @param {string} text The text.
 * @returns {string}
 */
const fold = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

/**
 * The current region: core replaces it whole, so it is looked up again rather than kept.
 *
 * @returns {HTMLElement|null}
 */
const region = () => document.querySelector(SELECTORS.ROOT);

/**
 * Write the layout's item classes on every plan, dropping the ones an earlier answer wrote.
 *
 * @param {HTMLElement} root The region.
 * @param {string} previous The classes written before.
 * @param {string} next The classes to write.
 */
const writeClasses = (root, previous, next) => {
    const old = previous.split(/\s+/).filter(Boolean);
    const add = next.split(/\s+/).filter(Boolean);
    root.querySelectorAll(SELECTORS.NODE).forEach((node) => {
        node.classList.remove(...old);
        node.classList.add(...add);
    });
};

/**
 * Put each remembered card in its plan's place. The HTML is the server's own rendering of the block's template.
 *
 * @param {HTMLElement} root The region.
 */
const writeCards = (root) => {
    root.querySelectorAll(SELECTORS.NODE).forEach((node) => {
        const html = state.cards.get(node.dataset.id);
        const extra = node.querySelector(SELECTORS.EXTRA);
        if (html !== undefined && extra) {
            extra.innerHTML = html;
        }
    });
};

/**
 * Remember the first paint's layout and cards.
 *
 * @param {HTMLElement} root The region.
 */
const remember = (root) => {
    state.itemclasses = root.dataset.dimItemclasses || '';
    root.querySelectorAll(SELECTORS.NODE).forEach((node) => {
        const extra = node.querySelector(SELECTORS.EXTRA);
        if (extra && !extra.querySelector(SELECTORS.FALLBACK)) {
            state.cards.set(node.dataset.id, extra.innerHTML);
        }
    });
};

/**
 * Ask for fresh cards after core re-rendered the region, and put them in place.
 *
 * @param {HTMLElement} root The region.
 * @returns {Promise<void>}
 */
const refill = async(root) => {
    const node = root.querySelector(SELECTORS.NODE);
    if (!node) {
        return;
    }
    const ticket = ++state.ticket;
    const pending = new Pending('theme_dimensions/plancards:refill');
    try {
        const answer = await Ajax.call([{methodname: CARDS, args: {userid: Number(node.dataset.userid)}}])[0];
        if (ticket !== state.ticket) {
            return;
        }
        answer.cards.forEach((card) => state.cards.set(String(card.id), card.html));
        const current = region();
        if (current) {
            writeClasses(current, state.itemclasses, answer.itemclasses);
            writeCards(current);
        }
        state.itemclasses = answer.itemclasses;
    } catch (error) {
        window.console.error(error);
    } finally {
        pending.resolve();
    }
};

/**
 * The plans per status.
 *
 * @param {HTMLElement[]} nodes The plans.
 * @returns {Object} Status => count.
 */
const count = (nodes) => {
    const counts = {};
    nodes.forEach((node) => {
        const status = node.dataset.dimStatus;
        counts[status] = (counts[status] || 0) + 1;
    });
    return counts;
};

/**
 * Show the plans the scope and the search leave, and say how many.
 *
 * @param {HTMLElement} root The region.
 * @returns {Promise<void>}
 */
const paint = async(root) => {
    const scoped = !root.querySelector(SELECTORS.SCOPE).hidden;
    const text = fold(state.text);
    let shown = 0;
    root.querySelectorAll(SELECTORS.NODE).forEach((node) => {
        const title = node.querySelector(`${SELECTORS.EXTRA} ${SELECTORS.TITLE}`);
        const inscope = !scoped || node.dataset.dimStatus === state.scope;
        const matches = text === '' || fold(title ? title.textContent : '').includes(text);
        node.hidden = !(inscope && matches);
        shown += node.hidden ? 0 : 1;
    });
    root.querySelector(SELECTORS.NOMATCH).hidden = shown > 0;

    const paintid = ++state.paints;
    const pending = new Pending('theme_dimensions/plancards:count');
    try {
        const words = shown === 1
            ? await getString('competency_plancountone', 'theme_dimensions')
            : await getString('competency_plancount', 'theme_dimensions', shown);
        if (paintid === state.paints) {
            root.querySelector(SELECTORS.TOTAL).textContent = words;
        }
    } catch (error) {
        window.console.error(error);
    } finally {
        pending.resolve();
    }
};

/**
 * Choose a scope cell: the radio state, the roving tab stop, the plans.
 *
 * @param {HTMLElement} root The region.
 * @param {string} value The status chosen.
 * @param {boolean} focus Whether the chosen cell takes the focus.
 */
const choose = (root, value, focus) => {
    state.scope = value;
    root.querySelectorAll(SELECTORS.CELL).forEach((cell) => {
        const chosen = cell.dataset.value === value;
        cell.setAttribute('aria-checked', chosen ? 'true' : 'false');
        cell.tabIndex = chosen ? 0 : -1;
        if (chosen && focus) {
            cell.focus();
        }
    });
    paint(root);
};

/**
 * Arm the scope: counts in the cells, empty cells away, arrows moving the choice.
 *
 * @param {HTMLElement} root The region.
 * @param {HTMLElement[]} nodes The plans.
 */
const armScope = (root, nodes) => {
    const counts = count(nodes);
    const cells = [...root.querySelectorAll(SELECTORS.CELL)];
    cells.forEach((cell) => {
        const n = counts[cell.dataset.value] || 0;
        cell.querySelector(SELECTORS.CELLCOUNT).textContent = String(n);
        cell.hidden = n === 0;
    });
    const drawn = cells.filter((cell) => !cell.hidden).sort(
        (a, b) => ORDER.indexOf(a.dataset.value) - ORDER.indexOf(b.dataset.value)
    );
    root.querySelector(SELECTORS.SCOPE).hidden = drawn.length < 2;
    if (!drawn.some((cell) => cell.dataset.value === state.scope)) {
        state.scope = drawn.length ? drawn[0].dataset.value : '';
    }

    drawn.forEach((cell, index) => {
        cell.addEventListener('click', () => choose(root, cell.dataset.value, false));
        cell.addEventListener('keydown', (e) => {
            const last = drawn.length - 1;
            const moves = {
                ArrowRight: index === last ? 0 : index + 1,
                ArrowDown: index === last ? 0 : index + 1,
                ArrowLeft: index === 0 ? last : index - 1,
                ArrowUp: index === 0 ? last : index - 1,
                Home: 0,
                End: last,
            };
            if (moves[e.key] === undefined) {
                return;
            }
            e.preventDefault();
            choose(root, drawn[moves[e.key]].dataset.value, true);
        });
    });
};

/**
 * Open or close the search field in place of its button.
 *
 * @param {HTMLElement} root The region.
 * @param {boolean} open Whether to open it.
 * @param {boolean} focus Whether the field (open) or the button (closed) takes the focus.
 */
const setSearchOpen = (root, open, focus) => {
    const button = root.querySelector(SELECTORS.OPEN);
    const field = root.querySelector(SELECTORS.FIELD);
    button.hidden = open;
    field.hidden = !open;
    button.classList.toggle('dim-comp-iconbtn--active', !open && state.text !== '');
    if (focus) {
        (open ? root.querySelector(SELECTORS.INPUT) : button).focus();
    }
};

/**
 * Arm the search in place: the button opens the field, Escape closes it keeping the text, the eraser clears it.
 *
 * @param {HTMLElement} root The region.
 */
const armSearch = (root) => {
    const input = root.querySelector(SELECTORS.INPUT);
    const eraser = root.querySelector(SELECTORS.ERASER);
    input.value = state.text;
    eraser.hidden = state.text === '';
    setSearchOpen(root, state.text !== '', false);

    root.querySelector(SELECTORS.OPEN).addEventListener('click', () => setSearchOpen(root, true, true));
    input.addEventListener('input', () => {
        state.text = input.value;
        eraser.hidden = state.text === '';
        paint(root);
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            setSearchOpen(root, false, true);
        }
    });
    input.addEventListener('blur', () => {
        if (input.value === '' && !root.querySelector(SELECTORS.FIELD).contains(document.activeElement)) {
            setSearchOpen(root, false, false);
        }
    });
    eraser.addEventListener('click', () => {
        input.value = '';
        state.text = '';
        eraser.hidden = true;
        input.focus();
        paint(root);
    });

    if (!state.slashwired) {
        state.slashwired = true;
        document.addEventListener('keydown', (e) => {
            const current = region();
            const target = e.target;
            const typing = target instanceof HTMLElement
                && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
            if (e.key !== '/' || typing || !current || current.querySelector(SELECTORS.BAR).hidden) {
                return;
            }
            e.preventDefault();
            setSearchOpen(current, true, true);
        });
    }
};

/**
 * Name each card's menu trigger in its tooltip, as text, from the visually hidden name the template wrote.
 *
 * @param {HTMLElement} root The region.
 */
const nameTriggers = (root) => {
    root.querySelectorAll(SELECTORS.TRIGGER).forEach((trigger) => {
        const name = trigger.querySelector(SELECTORS.TRIGGERNAME);
        if (name) {
            trigger.setAttribute('data-dim-comp-tip', name.textContent.trim());
        }
    });
};

/**
 * Start on the first paint, and again after each of core's re-renders of the region.
 */
export const init = () => {
    const root = region();
    if (!root) {
        return;
    }
    if (root.hasAttribute('data-dim-layout')) {
        remember(root);
    } else {
        writeClasses(root, '', state.itemclasses);
        writeCards(root);
        refill(root);
    }
    nameTriggers(root);

    const nodes = [...root.querySelectorAll(SELECTORS.NODE)];
    const bar = root.querySelector(SELECTORS.BAR);
    if (!bar || nodes.length === 0) {
        return;
    }
    armScope(root, nodes);
    armSearch(root);
    choose(root, state.scope, false);
    bar.hidden = false;
};
