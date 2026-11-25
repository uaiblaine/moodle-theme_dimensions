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
 * - The bar: a scope by status (a cell counting nothing is not drawn, so drafts only appear where core shows them;
 *   each cell counts every plan of its status, whatever the other criteria), the count of plans shown, the
 *   favourites star (only where block_dimensions offers it: the viewer's own list, favourites on), Filters over the
 *   plan templates' tags (OR inside a group, AND across groups), Organise (sort only: core's order, which is by
 *   name, due date, last change), a chips row of the criteria in force, and a search over the plans' names in place.
 * - Favourites and tags: read from the first paint's attributes, kept in state.meta through core's re-renders and
 *   refreshed from every answer. The star itself is block_dimensions' module, loaded by the name the server writes;
 *   its change event updates state.meta. The theme writes no favourite.
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

/** The event block_dimensions' star dispatches on its button after a toggle: that module's EVENT, held by a test. */
const FAVCHANGED = 'block_dimensions/favourite:changed';

/** The statuses in the scope's order, as the template writes them on each plan. */
const ORDER = ['active', 'review', 'complete', 'draft'];

/** The service's favourite flag (get_plan_cards::FAVOURITE_*): -1 no star, 0 not a favourite, 1 a favourite. */
const FAVOURITE = {NONE: -1, YES: 1};

/** The sort keys of Organise; the first is core's own order. */
const SORTS = ['name', 'due', 'modified'];

/** A group with more options than this is a searched list (filter-panel.md, D115). */
const LISTED = 8;

/** The rows a searched list draws at most. */
const ROWS = 50;

/** The bar's narrow step (filter-bar.md): the panel is a bottom sheet and the menu hangs from the bar's end. */
const NARROW = '(max-width: 575.98px)';

/** What a plan without meta reads as: no star, no tag. */
const NOMETA = {favourite: null, tags: new Map()};

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
    SEARCH: '[data-region="dim-comp-search"]',
    CLUSTER: '[data-region="dim-comp-cluster"]',
    FAV: '[data-action="dim-comp-fav"]',
    FILTERS: '[data-action="dim-comp-filters"]',
    BADGE: '[data-region="dim-comp-badge"]',
    ORGANISE: '[data-action="dim-comp-organise"]',
    MENU: '[data-region="dim-comp-organise-menu"]',
    ITEM: '[role="menuitemradio"]',
    PANEL: '[data-region="dim-comp-fpanel"]',
    PANELBODY: '[data-region="dim-comp-fpanel-body"]',
    CLEARFILTERS: '[data-action="dim-comp-clearfilters"]',
    SHOW: '[data-action="dim-comp-show"]',
    CHIPS: '[data-region="dim-comp-chips"]',
    GRID: '.dim-comp-plans-grid',
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
    meta: new Map(),
    favourites: false,
    tagfilters: [],
    starmodule: '',
    starloaded: false,
    favonly: false,
    picked: new Map(),
    sort: SORTS[0],
    layer: '',
    docwired: false,
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
 * The tag filters, from the first paint's JSON attribute or a service answer: only well-formed entries are kept.
 *
 * @param {Array|string|null} source The parsed list, or the attribute's text.
 * @returns {Array} [{key, label}].
 */
const readFilters = (source) => {
    let list = source;
    if (typeof source === 'string' || source === null) {
        try {
            list = JSON.parse(source || '[]');
        } catch (error) {
            list = [];
        }
    }
    if (!Array.isArray(list)) {
        return [];
    }
    return list.filter((filter) => filter && typeof filter.key === 'string' && typeof filter.label === 'string')
        .map((filter) => ({key: filter.key, label: filter.label}));
};

/**
 * Remember the first paint's layout, cards, favourites and tags.
 *
 * @param {HTMLElement} root The region.
 */
const remember = (root) => {
    state.itemclasses = root.dataset.dimItemclasses || '';
    state.favourites = root.getAttribute('data-dim-favourites') === '1';
    state.starmodule = root.getAttribute('data-dim-starmodule') || '';
    state.tagfilters = readFilters(root.getAttribute('data-dim-tagfilters'));
    root.querySelectorAll(SELECTORS.NODE).forEach((node) => {
        const extra = node.querySelector(SELECTORS.EXTRA);
        if (extra && !extra.querySelector(SELECTORS.FALLBACK)) {
            state.cards.set(node.dataset.id, extra.innerHTML);
        }
        const favourite = node.getAttribute('data-dim-fav');
        const tags = new Map();
        [...node.attributes].forEach((attribute) => {
            const match = /^data-dim-(tag\d+)$/.exec(attribute.name);
            if (match && attribute.value !== '') {
                tags.set(match[1], attribute.value);
            }
        });
        state.meta.set(node.dataset.id, {favourite: favourite === null ? null : favourite === '1', tags});
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
        answer.cards.forEach((card) => {
            state.cards.set(String(card.id), card.html);
            state.meta.set(String(card.id), {
                favourite: card.favourite === FAVOURITE.NONE ? null : card.favourite === FAVOURITE.YES,
                tags: new Map(card.tags.map((tag) => [tag.key, tag.value])),
            });
        });
        state.favourites = Boolean(answer.favourites);
        state.tagfilters = readFilters(answer.tagfilters);
        const current = region();
        if (current) {
            writeClasses(current, state.itemclasses, answer.itemclasses);
            writeCards(current);
            armTools(current);
            paint(current);
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
 * A new element with a class and, when given, its text.
 *
 * @param {string} tag The element's name.
 * @param {string} classname Its class list.
 * @param {string|undefined} text Its text, set as text.
 * @returns {HTMLElement}
 */
const make = (tag, classname, text) => {
    const element = document.createElement(tag);
    element.className = classname;
    if (text !== undefined) {
        element.textContent = text;
    }
    return element;
};

/**
 * The values of a tag group in force.
 *
 * @param {string} key The tag.
 * @returns {Set<string>}
 */
const pickedOf = (key) => state.picked.get(key) || new Set();

/**
 * Take a tag value in or out of force.
 *
 * @param {string} key The tag.
 * @param {string} value The value.
 * @returns {boolean} Whether the value is now in force.
 */
const toggle = (key, value) => {
    const values = new Set(pickedOf(key));
    if (values.has(value)) {
        values.delete(value);
    } else {
        values.add(value);
    }
    if (values.size) {
        state.picked.set(key, values);
    } else {
        state.picked.delete(key);
    }
    return values.has(value);
};

/**
 * The distinct values of a tag among the listed plans, every status, in the reader's collation.
 *
 * @param {HTMLElement} root The region.
 * @param {string} key The tag.
 * @returns {string[]}
 */
const optionsOf = (root, key) => {
    const values = new Set();
    root.querySelectorAll(SELECTORS.NODE).forEach((node) => {
        const value = (state.meta.get(node.dataset.id) || NOMETA).tags.get(key);
        if (value) {
            values.add(value);
        }
    });
    return [...values].sort((a, b) => a.localeCompare(b));
};

/**
 * Put the plans in the order Organise asks for, ties in core's order (each plan's rank, stamped at each render).
 *
 * @param {HTMLElement} root The region.
 */
const reorder = (root) => {
    const grid = root.querySelector(SELECTORS.GRID);
    if (!grid) {
        return;
    }
    const nodes = [...grid.querySelectorAll(`:scope > ${SELECTORS.NODE}`)];
    const rank = (node) => Number(node.dataset.dimRank) || 0;
    const time = (node, name) => Number(node.getAttribute(name)) || 0;
    const compare = {
        name: (a, b) => rank(a) - rank(b),
        due: (a, b) => {
            const first = time(a, 'data-dim-due');
            const second = time(b, 'data-dim-due');
            if (first === second) {
                return rank(a) - rank(b);
            }
            // A plan without a due date goes last.
            if (first === 0 || second === 0) {
                return first === 0 ? 1 : -1;
            }
            return first - second;
        },
        modified: (a, b) => (time(b, 'data-dim-modified') - time(a, 'data-dim-modified')) || (rank(a) - rank(b)),
    }[state.sort] || ((a, b) => rank(a) - rank(b));
    const sorted = [...nodes].sort(compare);
    if (sorted.some((node, index) => node !== nodes[index])) {
        grid.append(...sorted);
    }
};

/**
 * The chips of the criteria in force: each tag value, the star when on, the search text while its field is closed.
 *
 * @param {HTMLElement} root The region.
 * @returns {Promise<Array>} [{label, remove}].
 */
const chipsOf = async(root) => {
    const chips = [];
    for (const filter of state.tagfilters) {
        for (const value of [...pickedOf(filter.key)].sort((a, b) => a.localeCompare(b))) {
            chips.push({
                label: await getString('competency_planfilterchip', 'theme_dimensions', {group: filter.label, value}),
                remove: () => toggle(filter.key, value),
            });
        }
    }
    if (state.favonly) {
        chips.push({
            label: await getString('competency_planfavourites', 'theme_dimensions'),
            remove: () => {
                state.favonly = false;
            },
        });
    }
    if (state.text !== '' && root.querySelector(SELECTORS.FIELD).hidden) {
        chips.push({
            label: await getString('competency_plansearchchip', 'theme_dimensions', state.text),
            remove: () => clearText(root),
        });
    }
    return chips;
};

/**
 * Draw the chips row: a button per chip, named for what it removes, then "Clear all" with two or more.
 *
 * @param {HTMLElement} root The region.
 * @param {Array} chips What chipsOf() answered.
 * @returns {Promise<void>}
 */
const writeChips = async(root, chips) => {
    const buttons = await Promise.all(chips.map(async(chip) => {
        const button = make('button', 'dim-comp-chip', chip.label);
        button.type = 'button';
        button.setAttribute('aria-label', await getString('competency_planremovefilter', 'theme_dimensions', chip.label));
        const cross = make('i', 'fa fa-xmark');
        cross.setAttribute('aria-hidden', 'true');
        button.append(cross);
        button.addEventListener('click', async() => {
            chip.remove();
            await paint(root);
            focusAfterRemoval(root);
        });
        return button;
    }));
    if (buttons.length > 1) {
        const all = make('button', 'dim-comp-clearall', await getString('competency_planclearall', 'theme_dimensions'));
        all.type = 'button';
        all.addEventListener('click', async() => {
            state.picked.clear();
            state.favonly = false;
            clearText(root);
            await paint(root);
            focusAfterRemoval(root);
        });
        buttons.push(all);
    }
    const row = root.querySelector(SELECTORS.CHIPS);
    row.replaceChildren(...buttons);
    row.hidden = buttons.length === 0;
};

/**
 * After a chip went, the focus goes to the first chip left, else to Filters, else to the search button.
 *
 * @param {HTMLElement} root The region.
 */
const focusAfterRemoval = (root) => {
    const next = [root.querySelector(`${SELECTORS.CHIPS} button`), root.querySelector(SELECTORS.FILTERS),
        root.querySelector(SELECTORS.OPEN)].find((element) => element && !element.hidden);
    if (next) {
        next.focus();
    }
};

/**
 * Show the plans the scope, the star, the tags and the search leave, in the order asked for, and say how many.
 *
 * @param {HTMLElement} root The region.
 * @returns {Promise<void>}
 */
const paint = async(root) => {
    const scoped = !root.querySelector(SELECTORS.SCOPE).hidden;
    const text = fold(state.text);
    let shown = 0;
    root.querySelectorAll(SELECTORS.NODE).forEach((node) => {
        const meta = state.meta.get(node.dataset.id) || NOMETA;
        const title = node.querySelector(`${SELECTORS.EXTRA} ${SELECTORS.TITLE}`);
        const inscope = !scoped || node.dataset.dimStatus === state.scope;
        const matches = text === '' || fold(title ? title.textContent : '').includes(text);
        const faved = !state.favonly || meta.favourite === true;
        const tagged = [...state.picked].every(([key, values]) => values.has(meta.tags.get(key)));
        node.hidden = !(inscope && matches && faved && tagged);
        shown += node.hidden ? 0 : 1;
    });
    reorder(root);
    root.querySelector(SELECTORS.NOMATCH).hidden = shown > 0;
    root.querySelector(SELECTORS.FAV).setAttribute('aria-pressed', state.favonly ? 'true' : 'false');
    const inforce = [...state.picked.values()].reduce((sum, values) => sum + values.size, 0);
    const badge = root.querySelector(SELECTORS.BADGE);
    badge.textContent = String(inforce);
    badge.hidden = inforce === 0;

    const paintid = ++state.paints;
    const pending = new Pending('theme_dimensions/plancards:count');
    try {
        const words = shown === 1
            ? await getString('competency_plancountone', 'theme_dimensions')
            : await getString('competency_plancount', 'theme_dimensions', shown);
        const filtersname = inforce === 0
            ? await getString('competency_planfilters', 'theme_dimensions')
            : await getString('competency_planfilterscount', 'theme_dimensions', inforce);
        const chips = await chipsOf(root);
        if (paintid === state.paints) {
            root.querySelector(SELECTORS.TOTAL).textContent = words;
            const filters = root.querySelector(SELECTORS.FILTERS);
            filters.setAttribute('aria-label', filtersname);
            filters.setAttribute('data-dim-comp-tip', filtersname);
            await writeChips(root, chips);
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
    paint(root);
};

/**
 * Empty the search, from its chip or "Clear all": the field folds back to its button.
 *
 * @param {HTMLElement} root The region.
 */
const clearText = (root) => {
    state.text = '';
    root.querySelector(SELECTORS.INPUT).value = '';
    root.querySelector(SELECTORS.ERASER).hidden = true;
    setSearchOpen(root, false, false);
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
 * A layer and its trigger: 'panel' is Filters' panel, 'menu' is Organise's menu.
 *
 * @param {HTMLElement} root The region.
 * @param {string} name 'panel' or 'menu'.
 * @returns {{layer: HTMLElement, trigger: HTMLElement}}
 */
const partsOf = (root, name) => ({
    layer: root.querySelector(name === 'panel' ? SELECTORS.PANEL : SELECTORS.MENU),
    trigger: root.querySelector(name === 'panel' ? SELECTORS.FILTERS : SELECTORS.ORGANISE),
});

/**
 * Place a layer under the bar's control row (the panel, at the bar's end) or under its trigger (the menu, its end
 * on the trigger's end), never under the chips row; at the narrow step the menu hangs from the bar's end and the
 * panel is left to the stylesheet's bottom sheet.
 *
 * @param {HTMLElement} root The region.
 * @param {string} name 'panel' or 'menu'.
 */
const place = (root, name) => {
    const {layer, trigger} = partsOf(root, name);
    layer.style.removeProperty('top');
    layer.style.removeProperty('inset-inline-end');
    const narrow = window.matchMedia(NARROW).matches;
    if (narrow && name === 'panel') {
        return;
    }
    const bar = root.querySelector(SELECTORS.BAR).getBoundingClientRect();
    const own = trigger.getBoundingClientRect();
    let top = own.bottom;
    if (name === 'panel') {
        top = [SELECTORS.SCOPE, SELECTORS.CLUSTER, SELECTORS.SEARCH]
            .map((selector) => root.querySelector(selector))
            .filter((element) => element && !element.hidden)
            .reduce((bottom, element) => Math.max(bottom, element.getBoundingClientRect().bottom), bar.top);
    }
    let end = 0;
    if (!narrow && name === 'menu') {
        end = window.getComputedStyle(root).direction === 'rtl' ? own.left - bar.left : bar.right - own.right;
    }
    layer.style.top = `${Math.round(top - bar.top + 6)}px`;
    layer.style.insetInlineEnd = `${Math.round(end)}px`;
};

/**
 * Close the open layer, if any, its trigger taking the focus back when asked.
 *
 * @param {HTMLElement} root The region.
 * @param {boolean} focus Whether the trigger takes the focus.
 */
const closeLayer = (root, focus) => {
    if (state.layer === '') {
        return;
    }
    const {layer, trigger} = partsOf(root, state.layer);
    state.layer = '';
    layer.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    if (focus) {
        trigger.focus();
    }
};

/**
 * Open a layer, closing the other: one layer of the bar is open at a time.
 *
 * @param {HTMLElement} root The region.
 * @param {string} name 'panel' or 'menu'.
 */
const openLayer = (root, name) => {
    closeLayer(root, false);
    const {layer, trigger} = partsOf(root, name);
    state.layer = name;
    layer.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    place(root, name);
};

/**
 * A row of a searched list: a pressed toggle with a check box.
 *
 * @param {HTMLElement} root The region.
 * @param {string} key The tag.
 * @param {string} value The value.
 * @returns {HTMLElement}
 */
const listRow = (root, key, value) => {
    const row = make('button', 'dim-comp-fpanel-row');
    row.type = 'button';
    row.setAttribute('aria-pressed', pickedOf(key).has(value) ? 'true' : 'false');
    const box = make('span', 'dim-comp-menu-box');
    box.setAttribute('aria-hidden', 'true');
    const tick = make('i', 'fa fa-check');
    box.append(tick);
    row.append(box, make('span', '', value));
    row.addEventListener('click', () => {
        row.setAttribute('aria-pressed', toggle(key, value) ? 'true' : 'false');
        paint(root);
    });
    return row;
};

/**
 * Draw a searched list's rows: the chosen ones pinned on top whatever the search holds, then the matches, at most
 * ROWS of them, with a note when more match or when none does.
 *
 * @param {HTMLElement} root The region.
 * @param {HTMLElement} list The list.
 * @param {string} key The tag.
 * @param {string[]} options The group's values.
 * @param {string} find The search's text.
 * @returns {Promise<void>}
 */
const writeRows = async(root, list, key, options, find) => {
    const pending = new Pending('theme_dimensions/plancards:rows');
    try {
        const folded = fold(find);
        const pinned = options.filter((value) => pickedOf(key).has(value));
        const rest = options.filter((value) => !pickedOf(key).has(value) && (folded === '' || fold(value).includes(folded)));
        const shown = [...pinned, ...rest].slice(0, ROWS);
        const rows = shown.map((value) => listRow(root, key, value));
        const more = pinned.length + rest.length - shown.length;
        if (more > 0) {
            rows.push(make('p', 'dim-comp-fpanel-note', await getString('competency_planfindmore', 'theme_dimensions', more)));
        } else if (shown.length === 0) {
            rows.push(make('p', 'dim-comp-fpanel-note', await getString('competency_planfindnone', 'theme_dimensions')));
        }
        list.replaceChildren(...rows);
    } catch (error) {
        window.console.error(error);
    } finally {
        pending.resolve();
    }
};

/**
 * Build the Filters panel's body from the tag filters and the listed plans' values: a group per filter, its label
 * on a line of its own, its values as pressed toggles (a searched list past LISTED), each applied on press.
 *
 * @param {HTMLElement} root The region.
 * @returns {Promise<void>}
 */
const buildPanel = async(root) => {
    const body = root.querySelector(SELECTORS.PANELBODY);
    const uid = root.querySelector(SELECTORS.PANEL).id;
    const parts = [];
    const pending = new Pending('theme_dimensions/plancards:panel');
    try {
        for (const [index, filter] of state.tagfilters.entries()) {
            const options = optionsOf(root, filter.key);
            if (options.length === 0) {
                continue;
            }
            const head = make('div', 'dim-comp-fpanel-head', filter.label);
            head.id = `${uid}-g${index}`;
            parts.push(head);
            if (options.length > LISTED) {
                const label = await getString('competency_planfindvalues', 'theme_dimensions', options.length);
                const find = make('input', 'form-control form-control-sm dim-comp-fpanel-find');
                find.type = 'search';
                find.autocomplete = 'off';
                find.setAttribute('aria-label', label);
                find.placeholder = label;
                const list = make('div', 'dim-comp-fpanel-list');
                list.setAttribute('role', 'group');
                list.setAttribute('aria-labelledby', head.id);
                find.addEventListener('input', () => writeRows(root, list, filter.key, options, find.value));
                parts.push(find, list);
                await writeRows(root, list, filter.key, options, '');
                continue;
            }
            const group = make('div', 'dim-comp-fpanel-opts');
            group.setAttribute('role', 'group');
            group.setAttribute('aria-labelledby', head.id);
            options.forEach((value) => {
                const option = make('button', 'dim-comp-fpanel-opt', value);
                option.type = 'button';
                option.setAttribute('aria-pressed', pickedOf(filter.key).has(value) ? 'true' : 'false');
                option.addEventListener('click', () => {
                    option.setAttribute('aria-pressed', toggle(filter.key, value) ? 'true' : 'false');
                    paint(root);
                });
                group.append(option);
            });
            parts.push(group);
        }
        body.replaceChildren(...parts);
    } catch (error) {
        window.console.error(error);
    } finally {
        pending.resolve();
    }
};

/**
 * Drop the values in force that no listed plan carries any more, and the groups no filter offers.
 *
 * @param {HTMLElement} root The region.
 */
const prune = (root) => {
    const keys = state.tagfilters.map((filter) => filter.key);
    [...state.picked.keys()].forEach((key) => {
        const options = optionsOf(root, key);
        const values = new Set([...pickedOf(key)].filter((value) => options.includes(value)));
        if (keys.includes(key) && values.size) {
            state.picked.set(key, values);
        } else {
            state.picked.delete(key);
        }
    });
};

/**
 * Load block_dimensions' star module, by the name the server wrote, once favourites are offered.
 *
 * @returns {Promise<void>}
 */
const loadStar = async() => {
    if (state.starloaded || !state.favourites || state.starmodule === '') {
        return;
    }
    state.starloaded = true;
    const pending = new Pending('theme_dimensions/plancards:star');
    try {
        const Star = await new Promise((resolve, reject) => window.require([state.starmodule], resolve, reject));
        Star.init();
    } catch (error) {
        window.console.error(error);
    } finally {
        pending.resolve();
    }
};

/**
 * Show the star and Filters where they apply, after the first paint and after every answer.
 *
 * @param {HTMLElement} root The region.
 */
const armTools = (root) => {
    if (!state.favourites) {
        state.favonly = false;
    }
    root.querySelector(SELECTORS.FAV).hidden = !state.favourites;
    prune(root);
    const filters = root.querySelector(SELECTORS.FILTERS);
    filters.hidden = state.tagfilters.length === 0;
    if (filters.hidden && state.layer === 'panel') {
        closeLayer(root, false);
    }
    if (state.layer === 'panel') {
        buildPanel(root);
    }
    loadStar();
};

/**
 * Arm the star, Filters and its panel, and Organise and its menu, on one render of the region.
 *
 * @param {HTMLElement} root The region.
 */
const wireTools = (root) => {
    root.querySelector(SELECTORS.FAV).addEventListener('click', () => {
        state.favonly = !state.favonly;
        paint(root);
    });

    const filters = root.querySelector(SELECTORS.FILTERS);
    const panel = root.querySelector(SELECTORS.PANEL);
    filters.addEventListener('click', async() => {
        if (state.layer === 'panel') {
            closeLayer(root, false);
            return;
        }
        await buildPanel(root);
        openLayer(root, 'panel');
        const first = panel.querySelector('input, button');
        if (first) {
            first.focus();
        }
    });
    panel.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeLayer(root, true);
        }
    });
    root.querySelector(SELECTORS.CLEARFILTERS).addEventListener('click', async() => {
        state.picked.clear();
        await buildPanel(root);
        paint(root);
    });
    root.querySelector(SELECTORS.SHOW).addEventListener('click', () => closeLayer(root, true));

    const organise = root.querySelector(SELECTORS.ORGANISE);
    const items = [...root.querySelectorAll(`${SELECTORS.MENU} ${SELECTORS.ITEM}`)];
    const mark = () => items.forEach((item) => {
        const chosen = item.dataset.value === state.sort;
        item.setAttribute('aria-checked', chosen ? 'true' : 'false');
        item.tabIndex = chosen ? 0 : -1;
    });
    const enter = () => {
        openLayer(root, 'menu');
        (items.find((item) => item.dataset.value === state.sort) || items[0]).focus();
    };
    mark();
    organise.addEventListener('click', () => {
        if (state.layer === 'menu') {
            closeLayer(root, false);
        } else {
            enter();
        }
    });
    organise.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            enter();
        }
    });
    items.forEach((item, index) => {
        item.addEventListener('click', () => {
            if (SORTS.includes(item.dataset.value)) {
                state.sort = item.dataset.value;
            }
            mark();
            closeLayer(root, true);
            paint(root);
        });
        item.addEventListener('keydown', (e) => {
            const last = items.length - 1;
            const moves = {
                ArrowDown: index === last ? 0 : index + 1,
                ArrowUp: index === 0 ? last : index - 1,
                Home: 0,
                End: last,
            };
            if (e.key === 'Escape') {
                e.preventDefault();
                closeLayer(root, true);
                return;
            }
            if (e.key === 'Tab') {
                closeLayer(root, false);
                return;
            }
            if (moves[e.key] === undefined) {
                return;
            }
            e.preventDefault();
            items.forEach((other) => {
                other.tabIndex = -1;
            });
            items[moves[e.key]].tabIndex = 0;
            items[moves[e.key]].focus();
        });
    });
};

/**
 * The document's listeners, wired once for the page view: core replaces the region, never the document.
 *
 * - block_dimensions' star changed a plan: its meta follows, the remembered card takes the star's new state, and
 *   the list is painted again (a plan taken out of the favourites leaves a favourites-only list).
 * - A press outside the open layer and its trigger closes it.
 */
const wireDocument = () => {
    if (state.docwired) {
        return;
    }
    state.docwired = true;
    document.addEventListener(FAVCHANGED, (e) => {
        const detail = e.detail || {};
        const id = Number(detail.id);
        const meta = state.meta.get(String(id));
        if (detail.type !== 'plan' || !Number.isInteger(id) || !meta) {
            return;
        }
        meta.favourite = Boolean(detail.isfavourite);
        const current = region();
        if (!current) {
            return;
        }
        const node = current.querySelector(`${SELECTORS.NODE}[data-id="${id}"]`);
        const extra = node ? node.querySelector(SELECTORS.EXTRA) : null;
        if (extra && state.cards.has(String(id))) {
            state.cards.set(String(id), extra.innerHTML);
        }
        paint(current);
    });
    document.addEventListener('click', (e) => {
        const current = region();
        if (!current || state.layer === '' || !document.contains(e.target)) {
            return;
        }
        const {layer, trigger} = partsOf(current, state.layer);
        if (!layer.contains(e.target) && !trigger.contains(e.target)) {
            closeLayer(current, false);
        }
    });
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
    // Core's order, as this render drew it: the sort's ties and its "Name" go back to it.
    nodes.forEach((node, index) => {
        node.dataset.dimRank = String(index);
    });
    const bar = root.querySelector(SELECTORS.BAR);
    if (!bar || nodes.length === 0) {
        return;
    }
    state.layer = '';
    wireDocument();
    armScope(root, nodes);
    wireTools(root);
    armTools(root);
    armSearch(root);
    choose(root, state.scope, false);
    bar.hidden = false;
};
