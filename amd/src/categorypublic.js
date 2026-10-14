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
 * The visitor's client of a public category page: the listing narrows, orders and pages in place.
 *
 * The page it runs on is the anonymous shell, complete without it: the toolbar is a real GET form,
 * the pager is real links and every state of the listing has an address. This module moves that
 * page as the logged-in client moves its own - it paints an answer and writes the address with the
 * same functions (categorylisting, categoryposters), so the two shells cannot be told apart row for
 * row and an address it writes is the one the server's own links spell.
 *
 * It asks theme_dimensions_get_public_category_rows, and nothing else, through core/ajax
 * with login not required, which posts to lib/ajax/service-nologin.php: no session is read and
 * none is started, so the site's forced login never meets the request. It never sends a cachekey -
 * service.php answers a GET carrying one with ninety days of public caching, and the service
 * refuses it - and asks one call per request, which is all the service answers. The language is
 * the page's own (M.cfg.language), because the request carries no session to hold the reader's.
 *
 * What it imports is the point: nothing that reads the session, stars a course, writes a
 * preference or asks a logged-in service (categorypublic_source_test walks the whole graph). Its
 * mark on the body is dim-public-js and never dim-js, which is the logged-in client's.
 *
 * A poster's panel opens in place, through categoryareapanel - the logged-in client's own panel,
 * which this module arms with the visitor's half. Every poster of a visitor's page opens its panel
 * at rest and under a criterion alike, and stays the link it is: a press meant for another tab or
 * window goes to the link's address, the panel of that area under the criteria on screen, which is
 * also what every poster's link is written to after each change. A plain press asks the same
 * service for the area's rows: a public area by its own id, as a panel of its page; a merely listed
 * one, which has no page a visitor may open, as a slice of this page's listing narrowed to it, in a
 * flat order and on the first page. An open panel is asked again with every answer to new criteria,
 * once per question - an answer to criteria that did not change is the panel already on screen.
 *
 * A failure is never told in the service's words: it would speak the request's language rather
 * than the page's, and say nothing a visitor can act on. The page's own sentence is shown and the
 * address of the request is loaded, which is the page a reader without this module gets - for a
 * panel, the poster's own link; an answer carrying no panel (a slice the server no longer draws as
 * one) is such a failure too.
 *
 * @module     theme_dimensions/categorypublic
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Config from 'core/config';
import Pending from 'core/pending';
import {getString} from 'core/str';
import * as AreaPanel from 'theme_dimensions/categoryareapanel';
import * as CategoryMark from 'theme_dimensions/categorymark';
import * as DateRange from 'theme_dimensions/daterange';
import * as Listing from 'theme_dimensions/categorylisting';
import * as Posters from 'theme_dimensions/categoryposters';

/** @type {Object} Every hook this module reads, all of them written by the showcase's templates. */
const SELECTORS = {
    root: '[data-region="dim-vitrine"]',
    grid: '[data-region="dim-vitrine-grid"]',
    toolbar: '[data-region="dim-vitrine-toolbar"]',
    group: '[data-region="dim-vitrine-group"]',
    notice: '[data-region="dim-toolbar-notice"]',
    clear: '[data-region="dim-vitrine-clear"]',
    clearaction: '[data-action="dim-clear"]',
    pagerbar: '[data-region="dim-vitrine-pagerbar"]',
    loadmore: '[data-action="dim-loadmore"]',
    search: 'input[type="search"]',
    viewbutton: '[data-action="dim-view"]',
    quick: '[data-action="dim-quick"]',
    treelink: '[data-region="dim-vitrine-tree"] a[data-region="dim-tree-link"]',
    areas: '[data-region="dim-areas"]',
    area: '[data-region="dim-area"]',
    control: '[data-region="dim-poster-control"]',
};

/** @type {String} The one web service this module calls: the visitor's, answered without a session. */
const METHOD = 'theme_dimensions_get_public_category_rows';

/** @type {String} The component every string of this module belongs to. */
const COMPONENT = 'theme_dimensions';

/** @type {String} The page's own sentence for a request it could not answer in place, preloaded by request.php. */
const FALLBACK = 'category_publicfallback';

/**
 * @type {String} This module's own mark on the body. It hides the no-JavaScript submits and shows
 * the controls only a client drives, exactly as the logged-in client's dim-js does, and it is a
 * second name on purpose: one module's mark must never stand for another module's presence.
 */
const BODY_CLASS = 'dim-public-js';

/** @type {Number} Milliseconds of quiet before a typed term is sent: the logged-in client's own. */
const SEARCH_DEBOUNCE = 300;

/** @type {Number} Characters below which a term is not a search at all, the server's own floor. */
const MIN_SEARCH_LENGTH = 2;

/** @type {String} The fragment a poster's link lands on: category_panel::ANCHOR_PANEL. */
const PANEL_ANCHOR = 'dim-panel';

/** @type {Number} How many courses a panel lists: the server's population::PANEL_SIZE. */
const PANEL_SIZE = 8;

/**
 * @type {Number} Milliseconds a panel's request waits for the reader to stop pressing. Every
 * question is a read the site pays for, so a reader running a finger along the posters asks once.
 */
const PANEL_QUIET = 120;

/** @type {String} The one order a panel can be asked for besides its default. */
const PSORT_NAME = 'name';

/** @type {Object} The two flat orders a panel is listed in, which a slice must name. */
const SORT = {
    recent: 'recent',
    name: 'name',
};

/** @type {Object} The request parameter names, the same ones the form and the server use. */
const PARAM = Listing.PARAM;

/** @type {HTMLElement|null} The listing section. */
let root = null;

/** @type {HTMLElement|null} The areas section, on a grouped page and at the root. */
let areas = null;

/** @type {Object|null} The request this page is showing: categorylisting's state, with no state chip. */
let state = null;

/** @type {Number} Monotonic ticket: a response whose ticket is not the current one is discarded. */
let seq = 0;

/** @type {Number} The last page the server answered with, appended pages included: the grid's, not the address's. */
let lastpage = 0;

/** @type {Number|null} The pending search timer. */
let searchtimer = null;

/**
 * @type {Pending|null} Held from a keystroke until the answer it leads to is painted, so that Behat
 * waits through the quiet period, which no request covers yet, and not only through the request.
 */
let searchpending = null;

/**
 * Drop from the state what a visitor's page never honours: the state chip.
 *
 * The visitor's population classifies nothing, so the server drops a chip from the request and the
 * service does not declare one; a chip read off a hand-edited address would otherwise be counted as
 * a filter the page has no control for, and written back into every address.
 *
 * @return {void}
 */
const publicOnly = () => {
    state.chip = '';
    Listing.rootOnly(state);
};

/**
 * The web service's arguments for a page of this request: the term alone at the root, and the page's language.
 *
 * @param {Number} page The zero-based page to fetch.
 * @return {Object}
 */
const rowsArgs = (page) => {
    if (Listing.isRoot(state)) {
        return {categoryid: 0, query: state.query, lang: Config.language};
    }
    return {
        categoryid: state.categoryid,
        page: page,
        query: state.query,
        perpage: state.pagesize,
        filters: Listing.filterArgs(state),
        sort: state.sort,
        datefrom: state.datefrom,
        dateto: state.dateto,
        cat: state.cat,
        lang: Config.language,
    };
};

/**
 * The address of one area's panel under the criteria on screen: what its poster links to.
 *
 * The server's own vocabulary (category_panel::page_address()): the page's parameters, the area as
 * the open one, the panel's order when it is not the default, and the panel's fragment.
 *
 * @param {Number} id The area.
 * @param {String} psort The panel's order: the empty string (the newest first) or name.
 * @return {URL}
 */
const panelAddress = (id, psort) => {
    const url = Listing.buildUrl(state);
    url.searchParams.set(PARAM.open, String(id));
    if (psort === PSORT_NAME) {
        url.searchParams.set(PARAM.psort, PSORT_NAME);
    } else {
        url.searchParams.delete(PARAM.psort);
    }
    url.hash = PANEL_ANCHOR;
    return url;
};

/**
 * Point every poster's link at its area's panel under the address on screen.
 *
 * The server drew each one for the address the page was loaded with - the open poster's to the
 * page without its panel -, so after a change made in place a press meant for another tab would
 * load the criteria the reader has just changed. Every poster opens its panel here, the open one
 * included, so each links to its own.
 *
 * @return {void}
 */
const linkPosters = () => {
    if (!areas) {
        return;
    }
    areas.querySelectorAll(SELECTORS.area).forEach((poster) => {
        const control = poster.querySelector(SELECTORS.control);
        if (!control || control.tagName !== 'A') {
            return;
        }
        control.href = panelAddress(Number(poster.dataset.categoryid), '').toString();
    });
};

/**
 * Load an address as a page, even one that differs from the address on screen by its fragment alone.
 *
 * A fragment is a move inside the document and not a load: a panel's link - whose open parameter the
 * panel module wrote into the address when it opened - would only scroll to the panel the module
 * could not fill. Such an address is put in place and the page loaded again.
 *
 * @param {URL} url The address.
 * @return {void}
 */
const load = (url) => {
    const target = url.toString();
    const bare = new URL(target);
    bare.hash = '';
    const here = new URL(window.location.href);
    here.hash = '';
    if (bare.toString() === here.toString()) {
        window.history.replaceState(window.history.state, '', target);
        window.location.reload();
        return;
    }
    window.location.assign(target);
};

/**
 * Load the address of a state rather than fetching it: the plain page a reader without this module gets.
 *
 * @param {URL} url The address.
 * @return {void}
 */
const navigate = (url) => {
    url.hash = '';
    load(url);
};

/**
 * Give up on answering in place: say so in the page's own words, then load the address asked for.
 *
 * Never the service's message: a refusal speaks the request's language and not the page's, and
 * nothing in it is for a visitor. The sentence is preloaded with the page, so a failure of the
 * network costs it nothing; if it is missing all the same, the page that loads next is the answer.
 * The address keeps the fragment it is handed: a panel's lands on the panel.
 *
 * @param {URL} url The address of the request that failed.
 * @return {Promise} Resolving when the page has been asked for.
 */
const fallBack = async(url) => {
    try {
        const text = await getString(FALLBACK, COMPONENT);
        const notice = root.querySelector(SELECTORS.notice);
        if (notice) {
            notice.textContent = text;
            notice.hidden = false;
        }
    } catch (failure) {
        // The sentence is a courtesy: the page loading next answers the reader either way.
    }
    load(url);
};

/**
 * Ask the visitor's web service one question: the one call site of this module.
 *
 * Three arguments to core/ajax: async, and login not required, which posts to service-nologin.php.
 * A sixth would be a cachekey, which turns the request into a GET answered with ninety days of
 * public caching; the service refuses it.
 *
 * @param {Object} args The service's arguments.
 * @return {Promise} The answer.
 */
const ask = (args) => {
    return Ajax.call([{
        methodname: METHOD,
        args: args,
    }], true, false)[0];
};

/**
 * The service's arguments for one poster's panel, the page's criteria read off the address.
 *
 * The address is what the shared panel module reads its open panel from, and the listing writes it
 * before every request, so a panel opened by the back button asks the criteria the listing is
 * about to ask too. The root reads its term alone, as its page does. A public area is asked by its
 * own id, as the panel of its page (withcategories). A merely listed one - its poster has no page
 * to link its title to - has no page a visitor may open: it is asked as a slice of this page's
 * listing, which the service answers only in a flat order and on the first page, and never with
 * the tree's category, a page number or a panel's request beside it (rows::require_wellformed_slice()).
 *
 * @param {HTMLElement} poster The area's poster.
 * @param {String} psort The panel's order: the empty string (the newest first) or name.
 * @return {Object}
 */
const panelArgs = (poster, psort) => {
    const id = Number(poster.dataset.categoryid);
    const sort = psort === PSORT_NAME ? SORT.name : SORT.recent;
    const asked = {categoryid: state.categoryid, view: state.view, viewchosen: state.viewchosen};
    Listing.readUrl(asked);
    if (Listing.isRoot(asked)) {
        return {
            categoryid: id,
            page: 0,
            perpage: PANEL_SIZE,
            sort: sort,
            query: asked.query,
            withcategories: true,
            lang: Config.language,
        };
    }
    const narrowing = {
        query: asked.query,
        filters: Listing.filterArgs(asked),
        datefrom: asked.datefrom,
        dateto: asked.dateto,
        lang: Config.language,
    };
    if (poster.dataset.titleurl === '') {
        return Object.assign({categoryid: state.categoryid, slice: id, sort: sort}, narrowing);
    }
    return Object.assign({categoryid: id, page: 0, perpage: PANEL_SIZE, sort: sort, withcategories: true}, narrowing);
};

/**
 * Ask for one poster's panel: the shared panel module's question, through the one call site.
 *
 * @param {HTMLElement} poster The area's poster.
 * @param {String} psort The panel's order.
 * @return {Promise} The answer, its panel's context included when the service draws one.
 */
const askPanel = (poster, psort) => {
    return ask(panelArgs(poster, psort));
};

/**
 * The question a panel's request asks, so that the shared module never sends the same one twice.
 *
 * @param {HTMLElement} poster The area's poster.
 * @param {String} psort The panel's order.
 * @return {String}
 */
const panelQuestion = (poster, psort) => {
    return JSON.stringify(panelArgs(poster, psort));
};

/**
 * A panel could not be answered in place: load the poster's own link, with the panel's order.
 *
 * The page the server draws for that address is the panel a reader without this module gets, so
 * a slice the server no longer admits - the area became public, or left the page, since it loaded
 * - ends on the page that says what it now is, and never in a loop: arming fetches nothing.
 *
 * @param {HTMLElement} poster The area's poster.
 * @param {String} psort The panel's order.
 * @return {Boolean} Always true: the failure is this module's.
 */
const panelFailed = (poster, psort) => {
    fallBack(panelAddress(Number(poster.dataset.categoryid), psort));
    return true;
};

/**
 * Write each area of an answer onto its poster, the summary and the reveal, ask the open panel again, and point the links.
 *
 * The shell a panel shows before its rows arrive - the call to action's address and label - carries
 * the criteria, so it is written from the answer, as the logged-in listing writes it. The rest is
 * the shared panel module's painting, which the logged-in client runs too: the posters' results,
 * the summary, the reveal, the "nothing anywhere" box, and the open panel placed and asked again.
 *
 * @param {Object} response The rows response.
 * @return {void}
 */
const paintAreas = (response) => {
    if (!areas) {
        return;
    }
    const accordion = response.accordion || [];
    accordion.forEach((entry) => {
        const poster = areas.querySelector(SELECTORS.area + '[data-categoryid="' + Number(entry.id) + '"]');
        if (poster) {
            poster.dataset.ctaurl = entry.ctaurl || '';
            poster.dataset.ctalabel = entry.ctalabel || '';
        }
    });
    AreaPanel.criteria({accordion: accordion, areas: response.areas || {}});
    linkPosters();
};

/**
 * Put one response onto the page, unless a newer request has gone out while its cards were rendering.
 *
 * @param {Object} response The rows response.
 * @param {Boolean} append Whether to append the rows rather than replace them.
 * @param {Number} ticket The request's ticket.
 * @return {Promise} Resolving when the page has been repainted, or at once for an overtaken answer.
 */
const applyResponse = async(response, append, ticket) => {
    const items = await Listing.renderCards(response.cards || []);
    if (ticket !== seq) {
        return;
    }
    lastpage = Number(response.page);
    if (!append) {
        state.page = lastpage;
    }
    state.total = Number(response.total);
    state.hasmore = Boolean(response.hasmore);
    // The root has no course grid: everything below it is still the answer's to paint.
    const grid = root.querySelector(SELECTORS.grid);
    if (grid) {
        Listing.paintGrid(root, state, grid, items, append, null);
    }
    const notice = root.querySelector(SELECTORS.notice);
    if (notice) {
        notice.hidden = !response.searchtooshort;
    }
    const clear = root.querySelector(SELECTORS.clear);
    if (clear) {
        if (response.clearurl) {
            clear.href = Listing.withPanel(response.clearurl);
        }
        clear.hidden = !response.hasfilters;
    }
    Listing.paintFilterBadge(root, response);
    Listing.paintCount(root, response);
    Listing.paintGroups(root, response);
    Listing.paintPager(root, response, append, items.length ? items[0] : null);
    if (!append) {
        Listing.paintDirect(root, response);
        paintAreas(response);
    }
};

/**
 * The address a failed request falls back to: the state's, or the page an append was asking for.
 *
 * @param {Number} page The zero-based page the request asked for.
 * @param {Boolean} append Whether it was an append.
 * @return {URL}
 */
const fallbackUrl = (page, append) => {
    const url = Listing.buildUrl(state);
    if (append) {
        url.searchParams.set(PARAM.page, String(page));
    }
    url.hash = '';
    return url;
};

/**
 * Ask the visitor's web service for one page, and put it on the screen unless something newer has been asked since.
 *
 * While the answer is out the listing is busy, and so are the posters' grids, whose pills wait when
 * the request narrows. Only the latest request may fall back to a page load.
 *
 * @param {Number} page The zero-based page to fetch.
 * @param {Boolean} append Whether to append the rows rather than replace them.
 * @return {Promise} Resolving when the request has been dealt with, successfully or not.
 */
const requestRows = async(page, append) => {
    const ticket = ++seq;
    const more = root.querySelector(SELECTORS.loadmore);
    if (append && more) {
        more.disabled = true;
        more.textContent = more.dataset.loading || more.textContent;
    }
    if (!append) {
        root.setAttribute('aria-busy', 'true');
        if (areas) {
            Posters.pending(areas, {filtered: Listing.narrows(state)});
        }
    }
    try {
        const response = await ask(rowsArgs(page));
        if (ticket !== seq) {
            return;
        }
        await applyResponse(response, append, ticket);
    } catch (error) {
        if (ticket === seq) {
            fallBack(fallbackUrl(page, append));
        }
    } finally {
        if (ticket === seq) {
            root.removeAttribute('aria-busy');
        }
        Listing.restoreMore(more);
    }
};

/**
 * Apply a changed criterion: written into the address and fetched. An open panel stays open and is
 * asked again with the answer ({@see paintAreas}).
 *
 * @return {Promise} Resolving when the request has been dealt with.
 */
const apply = () => {
    state.page = 0;
    Listing.writeUrl(state, true);
    linkPosters();
    return requestRows(0, false);
};

/**
 * Deal with a typed term: the two-character floor is mirrored here, the rest is the server's.
 *
 * @param {String} raw What is in the box.
 * @return {Promise} Resolving when the term has been dealt with, at once for one below the floor.
 */
const onSearch = async(raw) => {
    const term = raw.trim();
    const notice = root.querySelector(SELECTORS.notice);
    if (term.length > 0 && term.length < MIN_SEARCH_LENGTH) {
        if (notice) {
            notice.hidden = false;
        }
        return;
    }
    if (notice) {
        notice.hidden = true;
    }
    state.query = term;
    await apply();
};

/**
 * Drop a typed term that has not been sent yet, and the pending token held for it.
 *
 * @return {void}
 */
const cancelSearch = () => {
    window.clearTimeout(searchtimer);
    if (searchpending) {
        searchpending.resolve();
        searchpending = null;
    }
};

/**
 * Send the box's term once the reader has stopped typing, holding one pending token until it is painted.
 *
 * @param {HTMLInputElement} search The search box.
 * @return {void}
 */
const onSearchInput = (search) => {
    cancelSearch();
    const pending = new Pending('theme_dimensions/categorypublic:search');
    searchpending = pending;
    searchtimer = window.setTimeout(async() => {
        searchpending = null;
        try {
            await onSearch(search.value);
        } finally {
            pending.resolve();
        }
    }, SEARCH_DEBOUNCE);
};

/**
 * Deal with an organisation being chosen: a fetch inside one shell, a navigation between two.
 *
 * The grouped organisation draws the posters and the flat ones a bare grid, so a move between the
 * two loads the address; between the two flat orders it is an ordinary fetch.
 *
 * @param {String} sort The organisation chosen.
 * @return {void}
 */
const onSort = (sort) => {
    if (sort === state.sort) {
        return;
    }
    const was = Listing.isGrouped(state.sort);
    state.sort = sort;
    state.page = 0;
    if (was !== Listing.isGrouped(sort)) {
        navigate(Listing.buildUrl(state));
        return;
    }
    apply();
};

/**
 * Deal with a category being pressed in the tree: a filter, fetched, and never a way out of the page.
 *
 * @param {HTMLElement} link The tree entry pressed.
 * @return {void}
 */
const onTree = (link) => {
    const id = Number(link.dataset.categoryid);
    if (!Number.isInteger(id) || id <= 0) {
        return;
    }
    const cat = id === state.categoryid ? 0 : id;
    if (cat === state.cat) {
        return;
    }
    state.cat = cat;
    Listing.markTree(root, state);
    apply();
};

/**
 * Take a period: onto the state, onto both spellings of the control, then applied.
 *
 * Either bound may be empty, and the pair is read through the calendar's own reading of a period,
 * whatever hands it over - the calendar or a quick option's link.
 *
 * @param {String} datefrom The first day, YYYY-MM-DD, or the empty string.
 * @param {String} dateto The last day, YYYY-MM-DD, or the empty string.
 * @return {void}
 */
const setRange = (datefrom, dateto) => {
    const period = DateRange.period(datefrom, dateto);
    state.datefrom = period.from;
    state.dateto = period.to;
    Listing.paintDates(root, state);
    apply();
};

/**
 * Deal with a facet or an organisation chip being chosen.
 *
 * @param {HTMLInputElement} input The radio that changed.
 * @return {void}
 */
const onChoose = (input) => {
    const group = input.closest(SELECTORS.group);
    const name = group && group.dataset.name ? group.dataset.name : input.name;
    if (name === PARAM.sort) {
        onSort(input.value);
        return;
    }
    if (!name || !name.startsWith(PARAM.fieldprefix)) {
        return;
    }
    const fieldid = name.slice(PARAM.fieldprefix.length);
    if (Number(input.value) > 0) {
        state.filters[fieldid] = Number(input.value);
    } else {
        delete state.filters[fieldid];
    }
    apply();
};

/**
 * Move to a view: the class and the address. A visitor has no preference to keep it in.
 *
 * @param {String} view Either cards or list.
 * @return {void}
 */
const setView = (view) => {
    state.view = view;
    state.viewchosen = true;
    Listing.paintView(root, view);
    Listing.writeUrl(state, true);
    linkPosters();
};

/**
 * Clear every filter in place, and give the focus to the search box.
 *
 * The control pressed is about to hide itself, and a focus left on it would fall to the top of the
 * document. An open panel stays open, asked again for the cleared criteria.
 *
 * @return {void}
 */
const onClear = () => {
    cancelSearch();
    state.query = '';
    state.filters = {};
    state.datefrom = '';
    state.dateto = '';
    state.cat = 0;
    const notice = root.querySelector(SELECTORS.notice);
    if (notice) {
        notice.hidden = true;
    }
    Listing.paintControls(root, state);
    apply();
    const search = root.querySelector(SELECTORS.search);
    if (search) {
        search.focus({preventScroll: true});
    }
};

/**
 * Wire every listener of the listing.
 *
 * @return {void}
 */
const listen = () => {
    const form = root.querySelector(SELECTORS.toolbar);
    if (form) {
        // Enter in the search box: everything is applied in place already, as the logged-in client does.
        form.addEventListener('submit', (event) => event.preventDefault());
        const search = form.querySelector(SELECTORS.search);
        if (search) {
            search.addEventListener('input', () => onSearchInput(search));
        }
    }

    root.addEventListener('change', (event) => {
        const input = event.target;
        if (input instanceof HTMLInputElement && input.type === 'radio') {
            onChoose(input);
        }
    });

    root.addEventListener('click', (event) => {
        const clear = Listing.closestOf(event, SELECTORS.clearaction);
        if (clear) {
            event.preventDefault();
            onClear();
            return;
        }
        const quick = Listing.closestOf(event, SELECTORS.quick);
        if (quick) {
            event.preventDefault();
            setRange(quick.dataset.from || '', quick.dataset.to || '');
            return;
        }
        const view = Listing.closestOf(event, SELECTORS.viewbutton);
        if (view) {
            event.preventDefault();
            setView(view.value === 'list' ? 'list' : 'cards');
            return;
        }
        const branch = Listing.closestOf(event, SELECTORS.treelink);
        if (branch) {
            event.preventDefault();
            onTree(branch);
            return;
        }
        const more = Listing.closestOf(event, SELECTORS.loadmore);
        if (more) {
            event.preventDefault();
            requestRows(lastpage + 1, true);
        }
    });

    window.addEventListener('popstate', () => {
        Listing.readUrl(state);
        publicOnly();
        Listing.paintControls(root, state);
        Listing.syncFold();
        linkPosters();
        requestRows(0, false);
    });
};

/**
 * Arm the visitor's live listing and its panels on a public category page.
 *
 * Nothing is fetched here: the page on screen is the server's answer to its own address, so a
 * failure later can only ever load a page, and never loop. The panels are armed wherever the page
 * has posters, the root's directory without a search line included; a panel the server drew for
 * the address is taken over, its question counted as asked.
 *
 * @param {Number} categoryid The category the page lists, 0 for the root of the course index.
 * @return {void}
 */
export const init = (categoryid) => {
    root = document.querySelector(SELECTORS.root);
    const id = Number(categoryid);
    if (!root || !Number.isInteger(id) || id < 0) {
        return;
    }
    let payload = {};
    try {
        payload = JSON.parse(root.dataset.vitrine || '{}');
    } catch (error) {
        payload = {};
    }
    const params = new URL(window.location.href).searchParams;
    const period = Listing.periodOf(params);
    // The category's default organisation, which an address naming none gets (criteria's rule).
    const defaultsort = typeof payload.defaultsort === 'string' ? payload.defaultsort : '';
    const sort = Listing.sortOf(params, defaultsort);
    state = {
        categoryid: id,
        page: Number(payload.page) || 0,
        total: Number(payload.total) || 0,
        hasmore: Boolean(payload.hasmore),
        query: typeof payload.query === 'string' ? payload.query : '',
        chip: '',
        filters: payload.filters && typeof payload.filters === 'object' ? Object.assign({}, payload.filters) : {},
        // The organisation, the period and the tree's category are the address's: the toolbar is a GET form.
        defaultsort: defaultsort,
        sort: sort,
        datefrom: period.from,
        dateto: period.to,
        cat: Listing.catOf(params, sort),
        view: payload.view === 'list' ? 'list' : 'cards',
        viewchosen: params.has(PARAM.view),
        pagesize: Listing.pageSizeOf(params),
    };
    lastpage = state.page;
    publicOnly();
    areas = root.querySelector(SELECTORS.areas);
    AreaPanel.arm({
        ask: askPanel,
        question: panelQuestion,
        unanswered: panelFailed,
        links: true,
        quiet: PANEL_QUIET,
    });
    linkPosters();
    // The root is armed for its search line; a root with nothing to search draws none.
    if (Listing.isRoot(state) && !root.querySelector(SELECTORS.toolbar)) {
        return;
    }

    // This is what hides the submits only a reader without this module needs.
    document.body.classList.add(BODY_CLASS);

    // The searched term in the titles of the server's own rows.
    CategoryMark.mark(root.querySelector(SELECTORS.grid), state.query);

    // The one calendar of the design, over the two native fields; a calendar that cannot mount
    // leaves them exactly as the server drew them, and says nothing.
    Listing.mountCalendar(root, state, setRange).catch(() => null);

    // The numbered bar is the reader-without-this-module's pager and is now the button's job.
    const bar = root.querySelector(SELECTORS.pagerbar);
    if (bar) {
        bar.hidden = true;
    }

    Listing.armTreebox(root);
    listen();
};
