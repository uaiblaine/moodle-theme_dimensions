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
 * The listing of the category showcase as every client of it paints it and addresses it.
 *
 * Two modules drive the listing in place: categoryvitrine for a logged-in account and categorypublic
 * for a visitor. Each asks a web service of its own, and both paint the answer with these functions
 * and write the address with them, so a row, a count or an address on one shell is the same one on
 * the other, and an address a client writes is the one the server's own links spell.
 *
 * Nothing here calls a web service, reads the session or writes a mark on the body: the visitor's
 * module imports this one, so a logged-in import here would ship to the internet with it
 * (categorypublic_source_test walks the import graph).
 *
 * The state every function is handed is the calling module's own object: categoryid, page, total,
 * hasmore, query, chip, filters, sort (the organisation in force), defaultsort (the one an address
 * naming none gets on this category, from the server's payload), remembered (whether the reader left a
 * state on this category, so that every address names its organisation), datefrom, dateto, cat, view,
 * viewchosen (whether the view in the address was chosen) and pagesize. Two counters belong to the document rather than to a
 * request and live here: how many pages were appended into the grid, and how many times the count
 * line was rewritten. One listing client runs on a page.
 *
 * @module     theme_dimensions/categorylisting
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from 'core/pending';
import Templates from 'core/templates';
import * as CategoryMark from 'theme_dimensions/categorymark';
import * as DateRange from 'theme_dimensions/daterange';

/** @type {Object} Every hook this module reads, all of them written by the showcase's templates. */
const SELECTORS = {
    grid: '[data-region="dim-vitrine-grid"]',
    empty: '[data-region="dim-vitrine-empty"]',
    toolbar: '[data-region="dim-vitrine-toolbar"]',
    group: '[data-region="dim-vitrine-group"]',
    pilltrack: '[data-region="dim-pill-track"]',
    pillcount: '[data-region="dim-pill-count"]',
    filterbadge: '[data-region="dim-filtercount"]',
    filterbadgenumber: '[data-region="dim-filtercount-number"]',
    filterbadgelabel: '[data-region="dim-filtercount-label"]',
    count: '[data-region="dim-vitrine-count"]',
    countnumber: '[data-region="dim-vitrine-countnumber"]',
    counttext: '[data-region="dim-vitrine-counttext"]',
    seeall: '[data-region="dim-vitrine-seeall"]',
    countnote: '[data-region="dim-vitrine-countnote"]',
    pagerbar: '[data-region="dim-vitrine-pagerbar"]',
    loadmore: '[data-action="dim-loadmore"]',
    nextpage: '[data-region="dim-vitrine-nextpage"]',
    card: '[data-region="dim-card"]',
    cardtitle: '[data-region="dim-card-titlelink"]',
    search: 'input[type="search"]',
    viewbutton: '[data-action="dim-view"]',
    sort: '[data-region="dim-sort"]',
    date: '[data-region="dim-date"]',
    daterange: '[data-region="dim-daterange"]',
    dateinputs: '[data-region="dim-dateinputs"]',
    rangepill: '[data-region="dim-daterange-pill"]',
    treelink: '[data-region="dim-vitrine-tree"] a[data-region="dim-tree-link"]',
    treebox: '[data-region="dim-vitrine-treebox"]',
    area: '[data-region="dim-area"]',
    direct: '[data-region="dim-direct"]',
    directskip: '[data-region="dim-direct-skip"]',
    fold: '[data-region="dim-hero-fold"]',
};

/** @type {Object} The request parameter names, the same ones the form and the server use. */
export const PARAM = {
    query: 'q',
    chip: 'chip',
    page: 'page',
    perpage: 'perpage',
    view: 'view',
    sort: 'sort',
    from: 'from',
    to: 'to',
    cat: 'cat',
    fieldprefix: 'cf_',
    open: 'open',
    psort: 'psort',
};

/**
 * @type {Array} The parameters the hero's fold form carries, besides its own identity field: the
 * server's criteria::query_params(), which never holds the page or the view. The custom-field
 * filters are matched by their prefix.
 */
const FOLD_PARAMS = [PARAM.query, PARAM.chip, PARAM.from, PARAM.to, PARAM.cat, PARAM.sort, PARAM.perpage,
    PARAM.open, PARAM.psort];

/** @type {Object} The one event this module sends: the view changed, which moves the panel's row. */
const EVENTS = {
    view: 'dim-vitrine:view',
};

/** @type {String} The card partial: one template, one row schema, page one and every later page. */
const CARD_TEMPLATE = 'theme_dimensions/category_card';

/**
 * @type {Array} The two organisations that are one shell: the empty value and the named default
 * mean the same request, which is what the server's own is_grouped() says. Everything else is
 * flat, and moving between the two shells is a navigation because the markup differs.
 */
const GROUPED_SORTS = ['', 'category'];

/** @type {String[]} The server's criteria::SORTS, the empty string first: what an address may name. */
const SORTS = ['', 'category', 'name', 'recent'];

/**
 * @type {Number} How many pages may be appended into one document before paging goes back to
 * real links. It bounds the DOM, the observer's watch list and the memory a long session
 * accumulates; the escape is the pager that was already there, and a real navigation starts a
 * fresh document with its own page-one baseline.
 */
const PAGE_WINDOW = 10;

/**
 * @type {String} The width below which the tree is a disclosure the reader opens, and at and
 * above which the stylesheet hides its summary and the list simply shows. It is the only place
 * this number is written on the client's side and it must stay the stylesheet's md breakpoint: a
 * module closing the box at a width where the summary that reopens it is hidden would leave a
 * reader with no tree and no control.
 */
const TREE_PHONE = '(max-width: 767.98px)';

/** @type {Number} How many pages have been appended into this document. */
let appended = 0;

/** @type {Number} How many times the count line has been rewritten, so a repeat still announces. */
let announcements = 0;

/**
 * The element a delegated event happened inside, or null.
 *
 * @param {Event} event The event.
 * @param {String} selector The selector to look for.
 * @return {HTMLElement|null}
 */
export const closestOf = (event, selector) => {
    const target = event.target;
    if (!(target instanceof Element)) {
        return null;
    }
    return target.closest(selector);
};

/**
 * Whether a state is the root's, the course index whose one criterion is the search.
 *
 * @param {Object} state The calling module's state.
 * @return {Boolean}
 */
export const isRoot = (state) => state.categoryid === 0;

/**
 * Drop from the state everything the root does not read: it honours its search and nothing else.
 *
 * The server drops the same things from the root's address and the web services refuse them, so a
 * state that kept a chip or a period read off a hand-edited address would count a filter the page
 * has no control for, and ask for one the answer refuses.
 *
 * @param {Object} state The calling module's state.
 * @return {void}
 */
export const rootOnly = (state) => {
    if (!isRoot(state)) {
        return;
    }
    state.chip = '';
    state.filters = {};
    state.sort = '';
    state.datefrom = '';
    state.dateto = '';
    state.cat = 0;
    state.page = 0;
    state.pagesize = 0;
};

/**
 * The page size the address asks for, or 0 for the site default.
 *
 * The showcase honours a perpage in its query string - the toolbar carries it as a hidden field
 * and every pager link re-emits it - so a request that does not carry it is answered at the site
 * default instead. On a listing opened at ?perpage=6 that silently re-cuts every later page:
 * page one comes back at the default size, the appended rows overlap the ones on screen or skip
 * the ones between, and nothing in the payload or the address says it happened.
 *
 * @param {URLSearchParams} params The query string to read.
 * @return {Number} The requested size, or 0.
 */
export const pageSizeOf = (params) => {
    const size = Number(params.get(PARAM.perpage));
    return Number.isInteger(size) && size > 0 ? size : 0;
};

/**
 * The organisation in force for a query string: the one it names, else the category's default.
 *
 * The server's own resolution (criteria's constructor): an address naming no organisation, or one
 * outside the vocabulary, gets the default the page's payload carries. Every reader of the state
 * reads the organisation in force, so a category whose default is a flat order is flat with
 * nothing in the address.
 *
 * @param {URLSearchParams} params The query string to read.
 * @param {String} defaultsort The category's default, the empty string for the theme's.
 * @return {String} The organisation.
 */
export const sortOf = (params, defaultsort) => {
    const sort = params.get(PARAM.sort) || '';
    return SORTS.indexOf(sort) > 0 ? sort : (defaultsort || '');
};

/**
 * Whether an organisation draws the areas, which is the server's own is_grouped().
 *
 * @param {String} sort The organisation's value.
 * @return {Boolean} Whether that organisation is the grouped one.
 */
export const isGrouped = (sort) => GROUPED_SORTS.indexOf(sort) >= 0;

/**
 * The tree's category as the address states it, and zero whenever the page cannot use it.
 *
 * The grouped organisation draws no tree, so the server drops the criterion there at
 * construction; a client that kept it would mark an entry of a tree that is not on the page and
 * count a filter the response does not count.
 *
 * Whether the id is one this page offers is the server's question: it drops what it cannot use,
 * as it drops an unknown sort.
 *
 * @param {URLSearchParams} params The query string to read.
 * @param {String} sort The organisation that query string names.
 * @return {Number} The category, or 0.
 */
export const catOf = (params, sort) => {
    const cat = Number(params.get(PARAM.cat));
    return isGrouped(sort) || !Number.isInteger(cat) || cat <= 0 ? 0 : cat;
};

/**
 * The period as the address states it, read the way the server reads it.
 *
 * A bound that is not a date is dropped and a pair given backwards is put in order, exactly as
 * criteria does it on the page's own render: the state then asks the web service for the page on
 * screen, and the service refuses a pair given backwards rather than guessing at it.
 *
 * @param {URLSearchParams} params The query string to read.
 * @return {Object} The {from, to} pair, each YYYY-MM-DD or the empty string.
 */
export const periodOf = (params) => DateRange.period(params.get(PARAM.from), params.get(PARAM.to));

/**
 * The address a state is at: the same parameter names the form posts.
 *
 * Everything else in the query string is left alone - the page size above all, which the server
 * honours and no client owns.
 *
 * It is built rather than written, because callers need it for different things: one writes it
 * into the history, the organisation that changes the shell loads it, and the visitor's module
 * loads it when its web service cannot answer.
 *
 * @param {Object} state The calling module's state.
 * @return {URL} The address.
 */
export const buildUrl = (state) => {
    const url = new URL(window.location.href);
    const params = url.searchParams;
    Array.from(params.keys())
        .filter((key) => key.startsWith(PARAM.fieldprefix))
        .forEach((key) => params.delete(key));
    [PARAM.query, PARAM.chip, PARAM.page, PARAM.view, PARAM.sort, PARAM.from, PARAM.to, PARAM.cat]
        .forEach((key) => params.delete(key));
    if (state.query !== '') {
        params.set(PARAM.query, state.query);
    }
    if (state.chip !== '') {
        params.set(PARAM.chip, state.chip);
    }
    /* For a reader who left a state on this category the organisation is ALWAYS written, the
       default too and the theme's own as category, exactly as the server's sort_params() does
       then: the bare address gets that reader's remembered state, so an address without it would
       be read back as that state rather than as the page on screen. */
    if (state.remembered) {
        params.set(PARAM.sort, state.sort === '' ? 'category' : state.sort);
    }
    /* Otherwise the organisation is written only when it is not the category's default, exactly as
       the server's sort_params() builds its links: the bare address gets the default, and the
       shorter of the two is the one a reader would have got from a link. Anything else is named -
       the grouped organisation too, on a category whose default is flat, or the address would send
       the reader who pressed it back to the flat order. */
    if (state.sort !== '' && state.sort !== (state.defaultsort || '')) {
        params.set(PARAM.sort, state.sort);
    }
    /* Each bound is written on its own: a start alone and an end alone are periods the server
       answers (criteria), so the address keeps whichever of them the state holds. */
    if (state.datefrom !== '') {
        params.set(PARAM.from, state.datefrom);
    }
    if (state.dateto !== '') {
        params.set(PARAM.to, state.dateto);
    }
    /* The tree's category, never under the grouped organisation: that page draws no tree and the
       server drops the criterion there, so an address carrying both would state a filter its
       answer does not have. The organisation is the one control that navigates, so this is also
       what drops the criterion on the way from a flat order into the grouped one. */
    if (state.cat > 0 && !isGrouped(state.sort)) {
        params.set(PARAM.cat, String(state.cat));
    }
    Object.keys(state.filters).forEach((fieldid) => {
        if (state.filters[fieldid]) {
            params.set(PARAM.fieldprefix + fieldid, String(state.filters[fieldid]));
        }
    });
    if (state.page > 0) {
        params.set(PARAM.page, String(state.page));
    }
    /* A view stamped into every link a reader shares would override the recipient's own stored
       preference with the sharer's, so it is written only when it was chosen. */
    if (state.viewchosen) {
        params.set(PARAM.view, state.view);
    }
    /* The open panel is the panel's own, and it rides along untouched - except into a flat
       organisation, which draws no posters: the server drops the parameter there at construction,
       so an address carrying it would name a panel its own answer does not have. */
    if (!isGrouped(state.sort)) {
        params.delete(PARAM.open);
        params.delete(PARAM.psort);
    }
    return url;
};

/**
 * Rewrite the hero fold form's criteria from the address, so that folding keeps what is on screen.
 *
 * The form was drawn with the criteria of the server's render, so after a filter applied in place
 * it would reload the page with the old ones. Every parameter it carries is rewritten from the
 * address, the panel's two included; its own identity field is left alone.
 *
 * @return {void}
 */
export const syncFold = () => {
    const form = document.querySelector(SELECTORS.fold);
    if (!form) {
        return;
    }
    const carried = (name) => FOLD_PARAMS.indexOf(name) >= 0 || name.startsWith(PARAM.fieldprefix);
    form.querySelectorAll('input[type="hidden"]').forEach((input) => {
        if (carried(input.name)) {
            input.remove();
        }
    });
    const before = form.querySelector('button');
    new URL(window.location.href).searchParams.forEach((value, name) => {
        if (!carried(name)) {
            return;
        }
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        if (name === PARAM.open) {
            input.dataset.region = 'dim-open-field';
        } else if (name === PARAM.psort) {
            input.dataset.region = 'dim-psort-field';
        }
        form.insertBefore(input, before);
    });
};

/**
 * An address the server built, carrying the open panel of the address bar.
 *
 * A rows request is asked for without the panel's parameters - it is not a panel's - so the clear
 * link it hands back would close the panel the reader has open, which is the one thing clearing
 * the filters must not do.
 *
 * @param {String} href The server's address.
 * @return {String} The same address with the address bar's open and psort, when it has them.
 */
export const withPanel = (href) => {
    const target = new URL(href, window.location.href);
    const current = new URL(window.location.href).searchParams;
    [PARAM.open, PARAM.psort].forEach((name) => {
        if (current.has(name)) {
            target.searchParams.set(name, current.get(name));
        } else {
            target.searchParams.delete(name);
        }
    });
    return target.toString();
};

/**
 * Write a state into the address bar, and bring the fold form in line with it.
 *
 * @param {Object} state The calling module's state.
 * @param {Boolean} push Whether this is a new history entry rather than a correction of this one.
 * @return {void}
 */
export const writeUrl = (state, push) => {
    const url = buildUrl(state).toString();
    if (push) {
        window.history.pushState({}, '', url);
    } else {
        window.history.replaceState({}, '', url);
    }
    syncFold();
};

/**
 * Read the address bar back into a state, always at page zero.
 *
 * @param {Object} state The calling module's state.
 * @return {void}
 */
export const readUrl = (state) => {
    const params = new URL(window.location.href).searchParams;
    state.query = params.get(PARAM.query) || '';
    state.chip = params.get(PARAM.chip) || '';
    state.sort = sortOf(params, state.defaultsort);
    const period = periodOf(params);
    state.datefrom = period.from;
    state.dateto = period.to;
    state.cat = catOf(params, state.sort);
    state.filters = {};
    params.forEach((value, key) => {
        if (key.startsWith(PARAM.fieldprefix) && Number(value) > 0) {
            state.filters[key.slice(PARAM.fieldprefix.length)] = Number(value);
        }
    });
    state.page = 0;
    const view = params.get(PARAM.view);
    if (view === 'cards' || view === 'list') {
        state.view = view;
        state.viewchosen = true;
    }
    state.pagesize = pageSizeOf(params);
    rootOnly(state);
};

/**
 * Whether a state narrows the page: the server's has_filters().
 *
 * A term, a state, a custom field or a period, which narrows with either of its bounds; the tree's
 * category never counts here, because the one organisation that has areas draws no tree.
 *
 * @param {Object} state The calling module's state.
 * @return {Boolean}
 */
export const narrows = (state) => state.query !== ''
    || state.chip !== ''
    || Object.keys(state.filters).length > 0
    || state.datefrom !== ''
    || state.dateto !== '';

/**
 * The custom-field filters, in the shape both web services declare for them.
 *
 * @param {Object} state The calling module's state.
 * @return {Array} One entry of fieldid and value per filter.
 */
export const filterArgs = (state) => Object.keys(state.filters).map((fieldid) => ({
    fieldid: Number(fieldid),
    value: Number(state.filters[fieldid]),
}));

/**
 * Paint the view onto the container and the toggle buttons of both copies. No preference, no address.
 *
 * The toolbar writes the toggle twice - once in the organisation row and once on the phone's search
 * line - and the stylesheet shows one per width, so both are painted. The panel module is told,
 * because the panel's row is a different line of the grid in the list view.
 *
 * @param {HTMLElement} root The listing section.
 * @param {String} view Either cards or list.
 * @return {void}
 */
export const paintView = (root, view) => {
    root.classList.toggle('dim-vitrine--cards', view === 'cards');
    root.classList.toggle('dim-vitrine--list', view === 'list');
    root.querySelectorAll(SELECTORS.viewbutton).forEach((button) => {
        button.setAttribute('aria-pressed', button.value === view ? 'true' : 'false');
    });
    document.dispatchEvent(new CustomEvent(EVENTS.view, {detail: {view: view}}));
};

/**
 * Mark the tree entry the listing is narrowed to, and unmark every other.
 *
 * Exactly one entry carries the marking, and which one is not a property of the entry pressed
 * but of the state: with no category chosen it is the page's own entry, the first one, which is
 * also the entry that clears the filter. So this is written as a loop over every link rather
 * than as "unmark the old one and mark the new" - the second spelling leaves two marks on the
 * screen the first time a repaint and a press disagree about which the old one was.
 *
 * The tree is not drawn at all under the grouped organisation, so this finds nothing there.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} state The calling module's state.
 * @return {void}
 */
export const markTree = (root, state) => {
    root.querySelectorAll(SELECTORS.treelink).forEach((link) => {
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
 * The native fields are hidden once the calendar has mounted and they are still the ones the
 * form would post, so they are kept in step rather than abandoned - a control that stops being
 * true the moment it stops being visible is a control nobody can debug.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} state The calling module's state.
 * @return {void}
 */
export const paintDates = (root, state) => {
    DateRange.set(state.datefrom, state.dateto);
    const fields = root.querySelector(SELECTORS.dateinputs);
    if (!fields) {
        return;
    }
    const inputs = fields.querySelectorAll('input[type="date"]');
    inputs.forEach((input) => {
        if (input.name === PARAM.from) {
            input.value = state.datefrom;
        } else if (input.name === PARAM.to) {
            input.value = state.dateto;
        }
    });
};

/**
 * Put a state back onto the controls, after the reader moved through their history.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} state The calling module's state.
 * @return {void}
 */
export const paintControls = (root, state) => {
    const search = root.querySelector(SELECTORS.search);
    if (search) {
        search.value = state.query;
    }
    root.querySelectorAll(SELECTORS.group).forEach((fieldset) => {
        const name = fieldset.dataset.name || '';
        const current = name === PARAM.chip
            ? state.chip
            : String(state.filters[name.slice(PARAM.fieldprefix.length)] || 0);
        fieldset.querySelectorAll('input[type="radio"]').forEach((input) => {
            input.checked = input.value === current;
        });
    });
    const sort = root.querySelector(SELECTORS.sort);
    if (sort) {
        /* The empty value and the grouped default are the same request, so the chip that is
           pressed for an address naming neither is the one whose value is the default. */
        sort.querySelectorAll('input[type="radio"]').forEach((input) => {
            input.checked = state.sort === ''
                ? GROUPED_SORTS.indexOf(input.value) >= 0
                : input.value === state.sort;
        });
    }
    paintDates(root, state);
    markTree(root, state);
    paintView(root, state.view);
};

/**
 * Rewrite the badge on the Filter summary, the one number a collapsed panel still shows.
 *
 * Both halves of it move together and neither is computed here. The digit is what the server
 * counts as a filter ({@see criteria::filter_count()}: a term, a state, the tree's category, each
 * custom field, and the whole period as one) and the sentence beside it is that number in the
 * reader's own language. Painting the digit alone would leave a screen reader being read the
 * count the page was loaded with.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} response The rows response.
 * @return {void}
 */
export const paintFilterBadge = (root, response) => {
    const badge = root.querySelector(SELECTORS.filterbadge);
    if (!badge) {
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
 * The line is four regions of one paragraph, all written by the server's answer and none composed
 * here: the number, set in bold; the sentence after it; the flat list of every result, offered only
 * when the page is narrowed and something matched; and the note an automatic opening adds, which
 * every new answer takes away again. Nothing else of the paragraph is touched, so none of its
 * regions is lost to a repaint of another.
 *
 * A live region only speaks when its text changes, and "2 courses in Brigada" after a filter that
 * happened to leave the same numbers is the case a reader most needs told. The counter appended
 * after the sentence is written in zero-width spaces: the text node differs on every pass, and
 * there is nothing in it to read out.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} response The rows response: countnumber, countline and seeallurl.
 * @return {void}
 */
export const paintCount = (root, response) => {
    const line = root.querySelector(SELECTORS.count);
    if (!line) {
        return;
    }
    const number = line.querySelector(SELECTORS.countnumber);
    if (number) {
        number.textContent = response.countnumber || '';
    }
    const text = line.querySelector(SELECTORS.counttext);
    if (text) {
        announcements += 1;
        const marker = document.createElement('span');
        marker.className = 'visually-hidden';
        marker.textContent = '​'.repeat((announcements % 3) + 1);
        text.replaceChildren(document.createTextNode(response.countline || ''), marker);
    }
    const seeall = line.querySelector(SELECTORS.seeall);
    const url = response.seeallurl || '';
    if (seeall) {
        const link = seeall.querySelector('a');
        if (link && url !== '') {
            link.href = url;
        }
        seeall.hidden = url === '';
    }
    const note = line.querySelector(SELECTORS.countnote);
    if (note) {
        note.hidden = true;
    }
};

/**
 * Build one pill - the input and its label - with the markup the toolbar template writes.
 *
 * @param {HTMLElement} track The pill track of the group.
 * @param {String} name The radio group's name.
 * @param {Object} option The option the server returned.
 * @return {Object} The input and the label, keyed input and label.
 */
const createPill = (track, name, option) => {
    const input = document.createElement('input');
    input.type = 'radio';
    input.className = 'dim-pill__input visually-hidden';
    input.name = name;
    input.id = option.id;
    input.value = String(option.value);
    const label = document.createElement('label');
    label.className = 'dim-pill';
    label.htmlFor = option.id;
    // The favourites pill wears the card star's glyph, as the toolbar template draws it.
    if (option.isfavourites) {
        const star = document.createElement('i');
        star.className = 'fa-solid fa-star dim-pill__star';
        star.setAttribute('aria-hidden', 'true');
        label.append(star);
    }
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
 * Counts move, options appear and disappear; the group is updated in place rather than replaced,
 * so focus and the native radio behaviour survive a filter change.
 *
 * @param {HTMLElement|null} fieldset The group's fieldset, or null when this page has no such group.
 * @param {String} name The radio group's name.
 * @param {Array} options The options of this request.
 * @return {void}
 */
const paintGroup = (fieldset, name, options) => {
    if (!fieldset || !options.length) {
        /* The page was drawn with the state group, then an administrator switched the state sort
           off, so the web service answers with no options. Read as "hide every pill", that would
           empty a group the page still renders. */
        return;
    }
    const track = fieldset.querySelector(SELECTORS.pilltrack) || fieldset;
    const inputs = new Map();
    const labels = new Map();
    track.querySelectorAll('input[type="radio"]').forEach((input) => inputs.set(input.value, input));
    track.querySelectorAll('label').forEach((label) => labels.set(label.htmlFor, label));
    const seen = new Set();
    options.forEach((option) => {
        const value = String(option.value);
        seen.add(value);
        let input = inputs.get(value);
        let label = input ? labels.get(input.id) : null;
        if (!input || !label) {
            const made = createPill(track, name, option);
            input = made.input;
            label = made.label;
        }
        input.hidden = false;
        input.checked = Boolean(option.checked);
        label.hidden = false;
        const count = label.querySelector(SELECTORS.pillcount);
        if (count) {
            count.textContent = option.hascount ? String(option.count) : '';
            count.hidden = !option.hascount;
        }
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
 * Bring every group of the toolbar in line with the response.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} response The rows response.
 * @return {void}
 */
export const paintGroups = (root, response) => {
    const groups = new Map();
    root.querySelectorAll(SELECTORS.group).forEach((fieldset) => groups.set(fieldset.dataset.name || '', fieldset));
    if (response.statusgroup) {
        // The status group's "all" is one of its options, with the value the empty string.
        paintGroup(groups.get(PARAM.chip) || null, PARAM.chip, response.statusgroup.options || []);
    }
    (response.facets || []).forEach((facet) => {
        // A facet's "all" is not in its options - it is the anyid/anylabel/anychecked triple
        // beside them, value zero. Left out, the pass below would hide the only pill that
        // clears the facet, so it is put back at the head of the list where the toolbar draws it.
        const any = {
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
 * Let a batch of freshly inserted rows fade in.
 *
 * Every row this module renders is inserted carrying the enter class, whose one rule is
 * `opacity: 0` - so a batch nobody reveals is in the document, in the accessibility tree and in
 * find-in-page, and invisible. Both of the grid's paths call it, the replace and the append.
 *
 * Two frames, because an element transitions from the style it was laid out with and one
 * inserted this frame has none; a single frame lands inside the same style recalculation.
 *
 * The frames and the fade are held as pending JavaScript: WebDriver reads an element at opacity 0
 * as not displayed, so a Behat step counting the visible rows of a grid just repainted would
 * otherwise count none while the frames are still to come.
 *
 * @param {Array} items The list items just inserted.
 * @return {void}
 */
const reveal = (items) => {
    const pending = new Pending('theme_dimensions/categorylisting:reveal');
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
 * @param {Array} cards The card contexts.
 * @return {Promise} Resolving to the list items, in order.
 */
export const renderCards = async(cards) => {
    const rendered = await Promise.all(cards.map((card) => Templates.renderForPromise(CARD_TEMPLATE, card)));
    return rendered.map((result) => {
        const item = document.createElement('li');
        item.className = 'dim-vitrine__item dim-vitrine__item--enter';
        Templates.appendNodeContents(item, result.html, result.js);
        return item;
    });
};

/**
 * Give the load more button its label and its enabled state back.
 *
 * @param {HTMLElement|null} more The load more button.
 * @return {void}
 */
export const restoreMore = (more) => {
    if (!more) {
        return;
    }
    more.disabled = false;
    more.textContent = more.dataset.label || more.textContent;
};

/**
 * Move focus after an append, so a keyboard reader is never dropped onto the document body.
 *
 * The restore comes first. Disabling the button blurred it to the document body when the request
 * went out, and a disabled element is not focusable, so focusing it before re-enabling it is a
 * silent no-op that leaves the reader who pressed "load more" at the top of the document.
 *
 * @param {HTMLElement|null} more The load more button.
 * @param {HTMLElement|null} next The continue link.
 * @param {HTMLElement|null} first The first newly appended row.
 * @return {void}
 */
const focusAfterAppend = (more, next, first) => {
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
        const title = first.querySelector(SELECTORS.cardtitle);
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
 * @param {Object} response The rows response.
 * @param {Boolean} append Whether this response was appended rather than replacing the grid.
 * @param {HTMLElement|null} first The first newly appended row, for focus.
 * @return {void}
 */
export const paintPager = (root, response, append, first) => {
    const bar = root.querySelector(SELECTORS.pagerbar);
    if (bar) {
        bar.hidden = true;
    }
    const more = root.querySelector(SELECTORS.loadmore);
    const next = root.querySelector(SELECTORS.nextpage);
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
 * A replace hands every card it is about to discard to the caller first, when the caller watches
 * cards (the logged-in module's progress observer): what it holds about them has to go with them.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} state The calling module's state; its query marks the rows.
 * @param {HTMLElement} grid The course grid.
 * @param {Array} items The list items just rendered.
 * @param {Boolean} append Whether to append them rather than replace the grid.
 * @param {Function|null} discard Called with each card a replace discards, before it goes; null for none.
 * @return {void}
 */
export const paintGrid = (root, state, grid, items, append, discard) => {
    if (append) {
        appended += 1;
        grid.append(...items);
    } else {
        appended = 0;
        if (discard) {
            grid.querySelectorAll(SELECTORS.card).forEach((card) => discard(card));
        }
        grid.replaceChildren(...items);
    }
    reveal(items);
    // The searched term, marked in the titles of the rows just drawn; an append marks its own.
    items.forEach((item) => CategoryMark.mark(item, state.query));
    const empty = root.querySelector(SELECTORS.empty);
    grid.hidden = grid.childElementCount === 0;
    if (empty) {
        empty.hidden = !grid.hidden;
    }
};

/**
 * Show the courses sitting directly in the category, and the link to them, exactly when one matches.
 *
 * On a grouped page with areas, a narrowed page shows where its results are:
 * "Courses in" goes when nothing sitting here matches, and so does the link past the posters that
 * lands on it. At rest the same rule holds, because the server hides the section whenever nothing
 * sits here at all. A page with no areas keeps the section whatever it holds - it is the page.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} response The rows response.
 * @return {void}
 */
export const paintDirect = (root, response) => {
    const direct = root.querySelector(SELECTORS.direct);
    if (!direct || !root.querySelector(SELECTORS.area)) {
        return;
    }
    const none = Number(response.total) === 0;
    direct.hidden = none;
    const skip = root.querySelector(SELECTORS.directskip);
    if (skip) {
        skip.hidden = none;
    }
};

/**
 * Close the tree's disclosure on a phone, and keep it usable when the width changes under it.
 *
 * The box ships open, and that is the reader-without-a-module's page at every width: the
 * stylesheet hides its summary from the breakpoint up, so there the list simply shows. Below it
 * the summary is a real control and the list starts collapsed, because a category with forty
 * children otherwise pushes the courses a screen and a half down.
 *
 * The change listener is the other half: at the breakpoint and up the summary is hidden, so a
 * box closed on a phone and then widened would leave the tree gone with no control anywhere to
 * bring it back.
 *
 * @param {HTMLElement} root The listing section.
 * @return {void}
 */
export const armTreebox = (root) => {
    const box = root.querySelector(SELECTORS.treebox);
    if (!box) {
        return;
    }
    const phone = window.matchMedia(TREE_PHONE);
    box.open = !phone.matches;
    phone.addEventListener('change', (event) => {
        box.open = !event.matches;
    });
};

/**
 * Mount the calendar over the two native date fields, and hide them behind it.
 *
 * The fields are hidden and not removed: they are the form's own transport, the client keeps
 * their values in step, and a control removed from the document is a control that cannot come
 * back if the calendar ever fails to mount. tabindex is the other half of hiding one - the
 * hidden attribute keeps them out of the tab order already, and the attribute is set beside it
 * so that nothing which unhides a field by CSS alone can put it silently back in.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} state The calling module's state.
 * @param {Function} onpick Called with the two bounds of the period whenever the reader changes it.
 * @return {Promise} Resolving when the calendar is up, or at once when there is no toolbar.
 */
export const mountCalendar = async(root, state, onpick) => {
    const fieldset = root.querySelector(SELECTORS.date);
    const region = fieldset ? fieldset.querySelector(SELECTORS.daterange) : null;
    if (!region) {
        return;
    }
    const mounted = await DateRange.mount(region, {
        id: state.categoryid,
        firstday: fieldset.dataset.firstday,
        today: fieldset.dataset.today,
        from: state.datefrom,
        to: state.dateto,
        pill: root.querySelector(SELECTORS.rangepill),
        onpick: onpick,
    });
    if (!mounted) {
        return;
    }
    const fields = root.querySelector(SELECTORS.dateinputs);
    if (fields) {
        fields.hidden = true;
        fields.querySelectorAll('input[type="date"]').forEach((input) => {
            input.tabIndex = -1;
        });
    }
};
