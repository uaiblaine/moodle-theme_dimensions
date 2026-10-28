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
 * Two modules drive the listing in place, the logged-in account's and categorypublic for a visitor.
 * Each asks a web service of its own, and both paint the answer with these functions and write the
 * address with them, so a row, a count or an address on one shell is the same one on the other, and
 * an address a client writes is the one the server's own links spell. Both hand the toolbar's calendar,
 * its filter line and the posters with their panel to the React components through the mounts here
 * ({@see COMPONENTS}), which is the one place the components are named.
 *
 * Nothing here calls a web service, reads the session or writes a mark on the body: the visitor's
 * module imports this one, so a logged-in import here would ship to the internet with it
 * (categorypublic_source_test walks the import graph, the components included).
 *
 * The state every function is handed is the calling module's own object: categoryid, page, total,
 * hasmore, query, chip, fav (the favourites toggle, which combines with the chip), filters, sort
 * (the organisation in force), defaultsort (the one an address
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
    clear: '[data-region="dim-vitrine-clear"]',
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
    chiprow: '[data-region="dim-line-chips"]',
    chiplist: '[data-region="dim-line-chiplist"]',
    eraser: '[data-action="dim-search-clear"]',
    treelink: '[data-region="dim-vitrine-tree"] a[data-region="dim-tree-link"]',
    treebox: '[data-region="dim-vitrine-treebox"]',
    area: '[data-region="dim-area"]',
    direct: '[data-region="dim-direct"]',
    directskip: '[data-region="dim-direct-skip"]',
    fold: '[data-region="dim-hero-fold"]',
    star: '[data-region="dim-line-star"]',
    favfield: '[data-region="dim-fav-field"]',
    favcount: '[data-region="dim-fav-count"]',
    line: '[data-region="dim-line"]',
    linefield: '[data-region="dim-line-search"] input[type="search"]',
    listbox: '[data-region="dim-line-suggest"]',
    opener: '[data-action="dim-search-open"]',
    panel: '[data-region="dim-panel"]',
    adopted: '[data-region="dim-filters-panel"], [data-region="dim-organise-menu"], [data-region="dim-line-manage"]',
};

/** @type {Object} The request parameter names, the same ones the form and the server use. */
export const PARAM = {
    query: 'q',
    chip: 'chip',
    fav: 'fav',
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
const FOLD_PARAMS = [PARAM.query, PARAM.chip, PARAM.fav, PARAM.from, PARAM.to, PARAM.cat, PARAM.sort, PARAM.perpage,
    PARAM.open, PARAM.psort];

/**
 * @type {String[]} The state chip's values the filter line draws as its scope, the empty string ("all")
 * first: the server's criteria::SCOPE, which category_listing_markup_test holds equal to this list.
 * Every other value of the state chip is an option of the panel's "More statuses", the same radio
 * group by name, and an answer's status group is split between the two by this list.
 */
export const SCOPE = ['', 'enrolled', 'open', 'scheduled'];

/** @type {String} A chip's data-param for the period, whose two bounds it drops together: the server's CHIP_PERIOD. */
const CHIP_PERIOD = 'period';

/**
 * @type {String} The value the state chip carried for the favourites before they were a toggle: an
 * address that still says so is read as the toggle with no state, as the server's criteria reads it.
 */
const LEGACY_FAVOURITES = 'favourites';

/** @type {Object} The one event this module sends: the view changed, which moves the panel's row. */
const EVENTS = {
    view: 'dim-vitrine:view',
};

/**
 * @type {Object} The React components both shells draw (docs/vitrine-react/PLAN.md, R1 to R4a), each
 * named here and nowhere else: the toolbar's calendar, its filter line, and the posters with the panel
 * between their rows. They are visitor-safe by rule: nothing they reach names a session module or a
 * logged-in service (categorypublic_source_test walks them).
 */
const COMPONENTS = {
    calendar: '@moodle/lms/theme_dimensions/daterange/DateRange',
    line: '@moodle/lms/theme_dimensions/filterline/FilterLine',
    areas: '@moodle/lms/theme_dimensions/areas/Areas',
};

/**
 * @type {Object} The events the calendar component and this module exchange on the toolbar's
 * dim-daterange region: js/esm/src/daterange/DateRange.tsx spells the same three names. The
 * component says it is drawn (mounted) and that the reader changed the period (pick, detail {from,
 * to}); this module tells it the period the address says (set, the same detail), silently.
 */
const CALENDAR = {
    pick: 'dim-daterange:pick',
    set: 'dim-daterange:set',
    mounted: 'dim-daterange:mounted',
};

/**
 * @type {Object} The events the filter line component and the listing exchange on the
 * line: js/esm/src/filterline/FilterLine.tsx spells the same five names. The component says it is drawn
 * (mounted), or that it cannot draw the markup it was handed (failed: the line stays the server's), and that
 * a suggestion is about to press its control (dropterm); the listing tells it its own state (state, detail
 * {query, chip, fav, view} or any part of it) and an answer's numbers (answer).
 */
export const LINE = {
    mounted: 'dim-line:mounted',
    failed: 'dim-line:failed',
    state: 'dim-line:state',
    answer: 'dim-line:answer',
    dropterm: 'dim-line:dropterm',
};

/**
 * @type {String} The attribute the component writes on the line once it draws it, and which every painter
 * here reads: FilterLine.tsx's DRAWN.
 */
const LINE_DRAWN = 'data-line-drawn';

/**
 * @type {Object} The events the posters' component and the page's client exchange on the areas section,
 * heard here for the client ({@see mountAreas}): js/esm/src/areas/panel.ts spells the same names. The
 * component asks for an area's rows (ask, detail {poster, psort, answer}: the client puts its answer's
 * promise in it while the event is dispatched), asks for the question that request would ask (question,
 * detail {poster, psort, question}), offers a failure before its inline error (unanswered, detail {poster,
 * psort, taken}), says the rows are on screen (painted, detail {panel}) and says it is drawn (mounted).
 */
const AREAS = {
    mounted: 'dim-areas:mounted',
    ask: 'dim-areas:ask',
    question: 'dim-areas:question',
    unanswered: 'dim-areas:unanswered',
    painted: 'dim-areas:painted',
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
 * Whether the filter line's component draws an element, which this module then never writes into.
 *
 * Stages R2 and R4a of docs/vitrine-react/PLAN.md: on both shells the line is a React component, which
 * draws the line's own controls - the scope, the search, the star, the Filters badge, the view toggle - and
 * holds three frames of server markup it never diffs inside (the Filters panel, the Organise menu, the
 * managers' menu), which stay this module's to paint. A painter here paints what the component does not
 * draw and tells the component the rest ({@see tellLine}). The component marks the line once it draws it,
 * so until then - and on a line it was never handed ({@see mountLine}) - this answers false and every
 * painter paints the server's line.
 *
 * @param {Element|null} element An element of the toolbar.
 * @return {Boolean}
 */
const drawnByLine = (element) => {
    const line = element ? element.closest(SELECTORS.line) : null;
    return Boolean(line) && line.hasAttribute(LINE_DRAWN) && !element.closest(SELECTORS.adopted);
};

/**
 * Tell the filter line's component something, where a component draws the line.
 *
 * @param {HTMLElement} root The listing section.
 * @param {String} type One of LINE's events.
 * @param {Object} detail What it is told.
 * @return {Boolean} Whether a component draws the line and was told.
 */
const tellLine = (root, type, detail) => {
    const line = root.querySelector(SELECTORS.line);
    if (!line || !line.hasAttribute(LINE_DRAWN)) {
        return false;
    }
    line.dispatchEvent(new CustomEvent(type, {detail: detail}));
    return true;
};

/**
 * Tell the filter line's component an answer's numbers: the scope's part of the status group, in SCOPE's
 * order, the favourites toggle and the Filters badge. Called by both clients after the painters, so the
 * panel's groups the component's suggestions read are painted already. A visitor's answer carries no scope
 * and no favourites, which leaves both as the server drew them (none).
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} response The rows response.
 * @return {void}
 */
export const tellAnswer = (root, response) => {
    const options = response.statusgroup ? (response.statusgroup.options || []) : [];
    const rank = (option) => SCOPE.indexOf(String(option.value));
    tellLine(root, LINE.answer, {
        // No option at all is a page whose state sort was switched off under it: the scope stays as drawn.
        scope: options.length ? options.filter((option) => rank(option) >= 0).sort((a, b) => rank(a) - rank(b)) : null,
        favourites: response.favourites || null,
        filtercount: Number(response.filtercount || 0),
        filtercountlabel: response.filtercountlabel || '',
    });
};

/**
 * Where a client's own focus on the search should go: the field, or its icon where the field is folded away.
 *
 * A client gives the search the focus after a control it pressed hides itself ("Clear all", a chip); a
 * field folded into its icon cannot take it, and the focus would fall to the top of the document. Read
 * off the page, so it holds for the component's line and the root's search line alike;
 * the field's own rule keeps a focus moved here from opening the search.
 *
 * @param {HTMLElement} root The listing section.
 * @return {HTMLElement|null} The control to focus.
 */
export const focusTarget = (root) => {
    const search = root.querySelector(SELECTORS.search);
    const opener = root.querySelector(SELECTORS.opener);
    if (!search || search.getClientRects().length > 0 || !opener || opener.getClientRects().length === 0) {
        return search;
    }
    return opener;
};

/**
 * Hand the toolbar's filter line to its React component: the logged-in shell's since stage R2, the visitor's
 * since R4a.
 *
 * The mount node is the server's line itself, so it keeps its place in the form, its classes and the
 * sibling rules the stylesheet writes against it, and the component's first render reads the markup it
 * replaces. Core's react_autoinit watches added nodes and never attributes, so the line is named for the
 * component and put back where it stands, which the observer sees as an added node. The listeners are on
 * before that, and the mount is held as pending until the component says it is drawn, or that it cannot
 * draw the line (Pending's resolve() runs once, whichever comes first).
 *
 * A line the component cannot draw - no field, no listbox, or a form with no id - is left to the
 * server's markup, which works without any of this.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Function} dropTerm Called before a suggestion's control is pressed: takes the typed term out of
 *   the caller's state, so the one request that control causes carries none.
 * @return {Boolean} Whether the line was handed over.
 */
export const mountLine = (root, dropTerm) => {
    const form = root.querySelector(SELECTORS.toolbar);
    const line = form ? form.querySelector(SELECTORS.line) : null;
    /* The attribute, never the property: the visitor's script form carries a hidden field named "id",
       which a form's named-control lookup returns for form.id, and the component would be handed {}. */
    const formid = form ? form.getAttribute('id') : null;
    if (!line || !line.querySelector(SELECTORS.linefield) || !line.querySelector(SELECTORS.listbox) || !formid) {
        return false;
    }
    const pending = new Pending('theme_dimensions/categorylisting:line');
    line.addEventListener(LINE.dropterm, () => dropTerm());
    line.addEventListener(LINE.mounted, () => pending.resolve(), {once: true});
    line.addEventListener(LINE.failed, () => pending.resolve(), {once: true});
    line.dataset.reactComponent = COMPONENTS.line;
    line.dataset.reactProps = JSON.stringify({form: formid});
    // Put back where it stands: the observer hears an added node, and the line keeps its place and any focus.
    const focused = line.contains(document.activeElement) ? document.activeElement : null;
    line.parentNode.insertBefore(line, line.nextSibling);
    if (focused) {
        focused.focus({preventScroll: true});
    }
    return true;
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
    state.fav = false;
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
 * The state chip as the address states it, the older spelling of the favourites left out.
 *
 * @param {URLSearchParams} params The query string to read.
 * @return {String} The chip, or the empty string.
 */
export const chipOf = (params) => {
    const chip = params.get(PARAM.chip) || '';
    return chip === LEGACY_FAVOURITES ? '' : chip;
};

/**
 * Whether the address asks for the favourites: the toggle, or the chip that spelled them before it.
 *
 * Whether the reader has favourites at all is the server's question; the toggle is drawn only for one
 * who has, and an answer that does not honour it says so (paintFavourites()).
 *
 * @param {URLSearchParams} params The query string to read.
 * @return {Boolean}
 */
export const favOf = (params) => params.get(PARAM.fav) === '1' || params.get(PARAM.chip) === LEGACY_FAVOURITES;

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
    [PARAM.query, PARAM.chip, PARAM.fav, PARAM.page, PARAM.view, PARAM.sort, PARAM.from, PARAM.to, PARAM.cat]
        .forEach((key) => params.delete(key));
    if (state.query !== '') {
        params.set(PARAM.query, state.query);
    }
    if (state.chip !== '') {
        params.set(PARAM.chip, state.chip);
    }
    if (state.fav) {
        params.set(PARAM.fav, '1');
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
    state.chip = chipOf(params);
    state.fav = favOf(params);
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
 * A term, a state, the favourites, a custom field or a period, which narrows with either of its bounds; the tree's
 * category never counts here, because the one organisation that has areas draws no tree.
 *
 * @param {Object} state The calling module's state.
 * @return {Boolean}
 */
export const narrows = (state) => state.query !== ''
    || state.chip !== ''
    || Boolean(state.fav)
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
 * Paint the view onto the container and the toggle's two buttons. No preference, no address.
 *
 * The toggle is the filter line's one group of two icon buttons. The panel module is told, because
 * the panel's row is a different line of the grid in the list view.
 *
 * @param {HTMLElement} root The listing section.
 * @param {String} view Either cards or list.
 * @return {void}
 */
export const paintView = (root, view) => {
    root.classList.toggle('dim-vitrine--cards', view === 'cards');
    root.classList.toggle('dim-vitrine--list', view === 'list');
    root.querySelectorAll(SELECTORS.viewbutton).forEach((button) => {
        if (!drawnByLine(button)) {
            button.setAttribute('aria-pressed', button.value === view ? 'true' : 'false');
        }
    });
    tellLine(root, LINE.state, {view: view});
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
    tellCalendar(root.querySelector(SELECTORS.daterange), state);
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
 * Put the favourites toggle in a pressed or released state: its attribute, its glyph and its field.
 *
 * The glyph is the course card's own star, solid when pressed, as the template draws it. The hidden
 * field is what carries the toggle through a submission of the form by any other control, so it is
 * kept in step for the moment the module is gone; and the button posts what a press would turn it to.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Boolean} on Whether the toggle is on.
 * @return {void}
 */
export const paintFavToggle = (root, on) => {
    const field = root.querySelector(SELECTORS.favfield);
    if (field) {
        field.disabled = !on;
    }
    const star = root.querySelector(SELECTORS.star);
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
 * The eraser is a client's control (dim-live-only) and the server draws it hidden on an empty
 * field; from then on the text in the field decides, whoever put it there: a keystroke, the
 * history, or a clear.
 *
 * @param {HTMLElement} root The listing section.
 * @return {void}
 */
export const paintEraser = (root) => {
    const search = root.querySelector(SELECTORS.search);
    const eraser = root.querySelector(SELECTORS.eraser);
    // The component's eraser follows its own field.
    if (search && eraser && !drawnByLine(eraser)) {
        eraser.hidden = search.value === '';
    }
};

/**
 * Put a state back onto the controls, after the reader moved through their history.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} state The calling module's state.
 * @return {void}
 */
export const paintControls = (root, state) => {
    tellLine(root, LINE.state, {query: state.query, chip: state.chip, fav: Boolean(state.fav), view: state.view});
    const search = root.querySelector(SELECTORS.search);
    if (search && !drawnByLine(search)) {
        search.value = state.query;
    }
    paintEraser(root);
    root.querySelectorAll(SELECTORS.group).forEach((fieldset) => {
        if (drawnByLine(fieldset)) {
            return;
        }
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
    paintFavToggle(root, state.fav);
    paintDates(root, state);
    markTree(root, state);
    paintView(root, state.view);
};

/**
 * Bring the favourites toggle in line with an answer: whether it is pressed, and the number in its name.
 *
 * Both come from the server, which honours the toggle only for a reader who has favourites on a page
 * that classifies its courses; a toggle the answer says is not available is left as it is, because a
 * page drawn without one has nothing to repaint.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} response The rows response.
 * @return {void}
 */
export const paintFavourites = (root, response) => {
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
 * Both halves of it move together and neither is computed here. The digit is what the server
 * counts as a filter held by the panel ({@see criteria::filter_count()}: each custom field, the
 * whole period as one, and a state from "More statuses") and the sentence beside it is that number in the
 * reader's own language. Painting the digit alone would leave a screen reader being read the
 * count the page was loaded with.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} response The rows response.
 * @return {void}
 */
export const paintFilterBadge = (root, response) => {
    const badge = root.querySelector(SELECTORS.filterbadge);
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
 * The count is two regions of one paragraph - the number, set in bold, and the sentence after it -
 * and two more follow it in the row under the filter line: the flat list of every result, offered
 * only when the page is narrowed and something matched, and the note an automatic opening adds,
 * which every new answer takes away again. All four are written by the server's answer and none is
 * composed here; nothing else of the paragraph is touched, so none of its regions is lost to a
 * repaint of another.
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
    // "See all as a list" and the automatic opening's note follow the chips in the row, outside the sentence.
    const seeall = root.querySelector(SELECTORS.seeall);
    const url = response.seeallurl || '';
    if (seeall) {
        const link = seeall.querySelector('a');
        if (link && url !== '') {
            link.href = url;
        }
        seeall.hidden = url === '';
    }
    const note = root.querySelector(SELECTORS.countnote);
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
    const ordered = [];
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
        ordered.push(input, label);
        const count = label.querySelector(SELECTORS.pillcount);
        if (count) {
            count.textContent = option.hascount ? String(option.count) : '';
            count.hidden = !option.hascount;
        }
    });
    /* A pill that was absent and comes back is created last, and the group is drawn in the answer's
       order: move only what is out of place, so the focused radio is never detached. */
    let previous = null;
    let expected = Array.from(track.children).find((child) => child.matches('input[type="radio"], label')) || null;
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
 * The split is SCOPE, the server's own list, so a value is drawn where the server would have drawn
 * it. The status group's "all" is one of its options, with the value the empty string, and belongs
 * to the scope. An answer with no option at all is a page whose state sort was switched off under
 * it, and leaves both as they are (paintGroup()); an answer whose options are all scope values
 * empties "More statuses", which then hides, as the server draws it hidden with nothing to offer.
 *
 * @param {HTMLElement|null} scope The scope's fieldset, or null when the line draws none.
 * @param {HTMLElement|null} more The "More statuses" fieldset, or null.
 * @param {Array} options The status group's options of this request.
 * @return {void}
 */
const paintStatus = (scope, more, options) => {
    if (!options.length) {
        return;
    }
    const isScope = (option) => SCOPE.indexOf(String(option.value)) >= 0;
    // The scope is drawn in SCOPE's order whatever order the answer lists it in.
    const rank = (option) => SCOPE.indexOf(String(option.value));
    paintGroup(scope, PARAM.chip, options.filter(isScope).sort((a, b) => rank(a) - rank(b)));
    if (!more) {
        return;
    }
    const others = options.filter((option) => !isScope(option));
    if (others.length) {
        paintGroup(more, PARAM.chip, others);
    } else {
        more.querySelectorAll('input[type="radio"]').forEach((input) => {
            input.hidden = true;
            input.checked = false;
            const label = more.querySelector('label[for="' + input.id + '"]');
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
 * @param {Object} response The rows response.
 * @return {void}
 */
export const paintGroups = (root, response) => {
    const groups = new Map();
    root.querySelectorAll(SELECTORS.group).forEach((fieldset) => {
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
 * Build one chip of the row under the filter line, with the markup the server's category_chip writes.
 *
 * Every part of it is the answer's: the label, the address dropping that one criterion (with the
 * address bar's open panel, which a rows request does not carry), what it drops, and the words
 * that name the press.
 *
 * @param {Object} chip The answer's chip: param, label, url and removelabel.
 * @return {HTMLElement} The link.
 */
const createChip = (chip) => {
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
 * The row shows while anything narrows the page (the answer's hasfilters, the server's own
 * has_filters()); at rest it keeps the count sentence alone, visually hidden, because that sentence
 * is the page's live region and must stay in the document. The chips are the answer's, painted
 * where the server draws them - the period's in the calendar's own region of the row, the rest in
 * the list - and "Clear all" shows from two criteria up, the answer's clearall. The clear link's
 * address is the caller's to repaint, since it carries the open panel the same way.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} response The rows response.
 * @return {void}
 */
export const paintChips = (root, response) => {
    const row = root.querySelector(SELECTORS.chiprow);
    if (!row) {
        return;
    }
    const narrowed = Boolean(response.hasfilters);
    row.classList.toggle('dim-line__chips--rest', !narrowed);
    const count = row.querySelector(SELECTORS.count);
    if (count) {
        count.classList.toggle('visually-hidden', !narrowed);
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
    const clear = row.querySelector(SELECTORS.clear);
    if (clear) {
        clear.hidden = !response.clearall;
    }
};

/**
 * Drop from a state the one criterion a chip names, as the server's address for that chip drops it.
 *
 * @param {Object} state The calling module's state.
 * @param {String} param The chip's data-param: q (the search's, shown where the field is folded away), a
 *   field's parameter, chip, cat, or period.
 * @return {Boolean} Whether the chip named a criterion this state can drop.
 */
export const dropCriterion = (state, param) => {
    if (param === PARAM.query) {
        state.query = '';
    } else if (param === PARAM.chip) {
        state.chip = '';
    } else if (param === PARAM.cat) {
        state.cat = 0;
    } else if (param === CHIP_PERIOD) {
        state.datefrom = '';
        state.dateto = '';
    } else if (param && param.startsWith(PARAM.fieldprefix)) {
        delete state.filters[param.slice(PARAM.fieldprefix.length)];
    } else {
        return false;
    }
    state.page = 0;
    return true;
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
 * Hide the two native date fields behind a calendar that is up.
 *
 * The fields are hidden and not removed: they are the form's own transport, the client keeps
 * their values in step, and a control removed from the document is a control that cannot come
 * back if the calendar ever fails to mount. tabindex is the other half of hiding one - the
 * hidden attribute keeps them out of the tab order already, and the attribute is set beside it
 * so that nothing which unhides a field by CSS alone can put it silently back in.
 *
 * @param {HTMLElement} root The listing section.
 * @return {void}
 */
const hideDateFields = (root) => {
    const fields = root.querySelector(SELECTORS.dateinputs);
    if (fields) {
        fields.hidden = true;
        fields.querySelectorAll('input[type="date"]').forEach((input) => {
            input.tabIndex = -1;
        });
    }
};

/**
 * Tell the calendar component the period the state holds, without it telling anybody back.
 *
 * Sent on every paint of the period, on both shells; before the component is drawn the event reaches no
 * one, and the mounted listener tells it the period again.
 *
 * @param {HTMLElement|null} region The toolbar's calendar region.
 * @param {Object} state The calling module's state.
 * @return {void}
 */
const tellCalendar = (region, state) => {
    if (region) {
        region.dispatchEvent(new CustomEvent(CALENDAR.set, {detail: {from: state.datefrom, to: state.dateto}}));
    }
};

/**
 * Append the calendar component to its region: the logged-in shell's calendar since stage R1, the
 * visitor's since R4a.
 *
 * Core's react_autoinit watches added nodes and never attributes, so the mount is a NEW child carrying
 * the component's name and its props, which are the props object itself: an object nested under a key
 * of its own renders nothing. Both listeners are in place before the node goes in, so no event of the
 * component can arrive before there is somebody to hear it. The fields are hidden only once the
 * component says it is drawn, which it never says without the site's today: then they stay as the
 * server drew them. The period is told again at that moment, because the reader may have pressed a
 * quick option or Back while the component was still on its way.
 *
 * @param {HTMLElement} root The listing section.
 * @param {HTMLElement} fieldset The period's fieldset, carrying the site's today and first weekday.
 * @param {HTMLElement} region The empty region the calendar goes into.
 * @param {Object} state The calling module's state.
 * @param {Function} onpick Called with the two bounds of the period whenever the reader changes it.
 * @return {void}
 */
const appendCalendar = (root, fieldset, region, state, onpick) => {
    region.addEventListener(CALENDAR.pick, (event) => {
        onpick(event.detail.from, event.detail.to);
    });
    // Every time: core's react_autoinit mounts the node again after a move with the line, and the period it was
    // appended with may be old by then.
    region.addEventListener(CALENDAR.mounted, () => {
        hideDateFields(root);
        tellCalendar(region, state);
    });
    const node = document.createElement('div');
    node.dataset.reactComponent = COMPONENTS.calendar;
    node.dataset.reactProps = JSON.stringify({
        id: state.categoryid,
        firstday: fieldset.dataset.firstday,
        today: fieldset.dataset.today,
        from: state.datefrom,
        to: state.dateto,
    });
    region.append(node);
};

/**
 * Mount the calendar component over the two native date fields, which hide behind it once it is drawn.
 *
 * One calendar on both shells since stage R4a of docs/vitrine-react/PLAN.md; the AMD module's mount is
 * armed by no shell (amd/src/daterange.js stays for its period(), the reading of an address both clients
 * share). A toolbar without the period's region gets nothing.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} state The calling module's state.
 * @param {Function} onpick Called with the two bounds of the period whenever the reader changes it.
 * @return {Promise} Resolving once the component is appended, or at once when there is no toolbar.
 */
export const mountCalendar = async(root, state, onpick) => {
    const fieldset = root.querySelector(SELECTORS.date);
    const region = fieldset ? fieldset.querySelector(SELECTORS.daterange) : null;
    if (!region) {
        return;
    }
    appendCalendar(root, fieldset, region, state, onpick);
};

/**
 * Hand the posters and their panel to the React component: listen first, then append its node.
 *
 * Both shells since stage R4a of docs/vitrine-react/PLAN.md. The node is an empty, hidden child of the
 * areas section, so the mount draws no box: the component takes over the posters and the panel the server
 * drew. Core's react_autoinit mounts it when it is added; the mount is held as pending until the component
 * says it is drawn, so Behat never presses a poster that is still a plain link. The listeners go on before
 * the node, so a component already imported cannot ask before anybody answers. A page whose areas carry no
 * panel is left as the server drew it.
 *
 * The component calls no service: the page's client answers it. `ask` (poster, psort) is its answer's
 * promise; `question` (poster, psort), where a client names one, is the question that answer is to, asked
 * once per opening; `unanswered` (poster, psort) is true when the client took a failure over; `painted`
 * (panel) is told once the rows are on screen. The props are the shell's options, the props object itself
 * (js/esm/src/areas/panel.ts optionsOf()).
 *
 * @param {HTMLElement|null} areas The areas section.
 * @param {Object} props The component's props: {} on the logged-in shell, the visitor's options on its own.
 * @param {Object} client What the page's client answers with: ask, and optionally question, unanswered and painted.
 * @return {Boolean} Whether the posters were handed over.
 */
export const mountAreas = (areas, props, client) => {
    if (!areas || !areas.querySelector(SELECTORS.panel)) {
        return false;
    }
    const pending = new Pending('theme_dimensions/categorylisting:areas');
    areas.addEventListener(AREAS.ask, (event) => {
        event.detail.answer = client.ask(event.detail.poster, event.detail.psort);
    });
    if (client.question) {
        areas.addEventListener(AREAS.question, (event) => {
            event.detail.question = client.question(event.detail.poster, event.detail.psort);
        });
    }
    if (client.unanswered) {
        areas.addEventListener(AREAS.unanswered, (event) => {
            event.detail.taken = client.unanswered(event.detail.poster, event.detail.psort);
        });
    }
    if (client.painted) {
        areas.addEventListener(AREAS.painted, (event) => client.painted(event.detail.panel));
    }
    areas.addEventListener(AREAS.mounted, () => pending.resolve(), {once: true});
    const node = document.createElement('div');
    node.hidden = true;
    node.dataset.reactComponent = COMPONENTS.areas;
    node.dataset.reactProps = JSON.stringify(props);
    areas.append(node);
    return true;
};
