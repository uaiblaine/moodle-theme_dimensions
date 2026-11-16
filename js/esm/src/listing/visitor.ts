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
 * The visitor's shell of the showcase listing: a public category page narrows, orders and pages in place.
 *
 * It asks theme_dimensions_get_public_category_rows, and nothing else, with login not required, which posts
 * to lib/ajax/service-nologin.php: no session is read or started, so the site's forced login never meets the
 * request. Never with a cachekey (service.php answers such a GET with ninety days of public caching, and the
 * service refuses it), one call per request, in the page's own language. Its mark is dim-public-js.
 *
 * The posters stay their links (the component's links option): every link is written to its area's panel
 * under the criteria on screen. A press asks the service for the area's rows - a public area by its own id,
 * a merely listed one as a slice of this page's listing - once per opening, after a quiet; no area opens by
 * itself. A failure is told in the page's own words, never the service's, and the address of the request is
 * loaded: the page a reader without a client gets.
 *
 * What it imports is the point: nothing that reads the session or asks a logged-in service
 * (categorypublic_source_test walks the whole graph from its entry, amd/src/listingvisitor.js).
 *
 * @module     theme_dimensions/listing/visitor
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import Config from '@moodle/lms/core/config';
import {getString} from '@moodle/lms/core/stringUtils';
import type {ListingState} from './address';
import {replaceKeeping} from './history';
import * as Listing from './listing';
import {current, init} from './store';
import type {Rows} from './store';

/** Hooks the visitor's shell reads. */
const SELECTORS = {
    areas: '[data-region="dim-areas"]',
    area: '[data-region="dim-area"]',
    control: '[data-region="dim-poster-control"]',
    notice: '[data-region="dim-toolbar-notice"]',
};

/** The one web service the visitor's page calls, answered without a session. */
const METHOD = 'theme_dimensions_get_public_category_rows';

/** The component every string of this shell belongs to. */
const COMPONENT = 'theme_dimensions';

/** The page's own sentence for a request it could not answer in place, preloaded by request.php. */
const FALLBACK = 'category_publicfallback';

/** The visitor's mark on the body: never dim-js, which reveals what only an account drives. */
const BODY_CLASS = 'dim-public-js';

/** The fragment a poster's link lands on: category_panel::ANCHOR_PANEL. */
const PANEL_ANCHOR = 'dim-panel';

/** How many courses a panel lists: the server's population::PANEL_SIZE. */
const PANEL_SIZE = 8;

/** Milliseconds a panel's request waits for the reader to stop pressing: every question is a read the site pays for. */
const PANEL_QUIET = 120;

/** The posters' component's options on this shell (areas/panel.ts optionsOf()). */
const AREAS_PROPS = {
    links: true,
    quiet: PANEL_QUIET,
    autoopen: false,
};

/** The one order a panel can be asked for besides its default. */
const PSORT_NAME = 'name';

/** The two flat orders a panel is listed in, which a slice must name. */
const SORT = {
    recent: 'recent',
    name: 'name',
};

/** The request parameter names, the same ones the form and the server use. */
const PARAM = Listing.PARAM;

/**
 * Drop from the state what a visitor's page never honours: the state chip and the favourites.
 *
 * @param {object} state The store's state.
 * @returns {void}
 */
const publicOnly = (state: ListingState): void => {
    state.chip = '';
    state.fav = false;
    Listing.rootOnly(state);
};

/**
 * Ask the visitor's web service one question: the one call site of this shell, login not required and never
 * with a cachekey (the module docblock says why).
 *
 * @param {object} args The service's arguments.
 * @returns {Promise} The answer.
 */
const ask = (args: Record<string, unknown>): Promise<Rows> => {
    return fetchOne<Rows>({
        methodname: METHOD,
        args: args,
    }, {loginrequired: false});
};

/**
 * The web service's arguments for a page of this request: the term alone at the root, and the page's language.
 *
 * @param {number} page The zero-based page to fetch.
 * @returns {object}
 */
const rowsArgs = (page: number): Record<string, unknown> => {
    const state = current();
    if (Listing.isRoot(state)) {
        return {categoryid: 0, query: state.query, lang: Config.language};
    }
    return {
        categoryid: state.categoryid,
        page: page,
        query: state.query,
        perpage: state.pagesize,
        filters: Listing.filterArgs(state),
        // "Group by" and "Sort by" by name, the order in sort (criteria::organisation()).
        group: Listing.groupOf(state),
        sort: Listing.orderOf(state),
        datefrom: state.datefrom,
        dateto: state.dateto,
        cat: state.cat,
        lang: Config.language,
    };
};

/**
 * The address of one area's panel under the criteria on screen: what its poster links to
 * (category_panel::page_address()).
 *
 * @param {number} id The area.
 * @param {string} psort The panel's order: the empty string (the newest first) or name.
 * @returns {URL}
 */
const panelAddress = (id: number, psort: string): URL => {
    const url = Listing.buildUrl(current());
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
 * Point every poster's link at its area's panel under the address on screen, the open one's included.
 *
 * @returns {void}
 */
const linkPosters = (): void => {
    const areas = document.querySelector(SELECTORS.areas);
    if (!areas) {
        return;
    }
    areas.querySelectorAll<HTMLElement>(SELECTORS.area).forEach((poster) => {
        const control = poster.querySelector<HTMLAnchorElement>(SELECTORS.control);
        if (!control || control.tagName !== 'A') {
            return;
        }
        control.href = panelAddress(Number(poster.dataset.categoryid), '').toString();
    });
};

/**
 * Load an address as a page, even one that differs from the address on screen by its fragment alone: a
 * fragment is a move inside the document, which would only scroll to the panel the shell could not fill.
 *
 * @param {URL} url The address.
 * @returns {void}
 */
const load = (url: URL): void => {
    const target = url.toString();
    const bare = new URL(target);
    bare.hash = '';
    const here = new URL(window.location.href);
    here.hash = '';
    if (bare.toString() === here.toString()) {
        replaceKeeping(target);
        window.location.reload();
        return;
    }
    window.location.assign(target);
};

/**
 * Load the address of a state rather than fetching it: the plain page a reader without a client gets.
 *
 * @param {URL} url The address.
 * @returns {void}
 */
const navigate = (url: URL): void => {
    url.hash = '';
    load(url);
};

/**
 * Give up on answering in place: say so in the page's own words, then load the address asked for.
 *
 * Never the service's message: a refusal speaks the request's language, and nothing in it is for a visitor. The
 * sentence is preloaded with the page, so a failed network costs it nothing; the page that loads next answers
 * the reader either way.
 *
 * @param {URL} url The address of the request that failed, with its fragment.
 * @returns {Promise} Resolving when the page has been asked for.
 */
const fallBack = async(url: URL): Promise<void> => {
    try {
        const text = await getString(FALLBACK, COMPONENT);
        const notice = document.querySelector<HTMLElement>(SELECTORS.notice);
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
 * The address a failed request falls back to: the state's, or the page an append was asking for.
 *
 * @param {number} page The zero-based page the request asked for.
 * @param {boolean} append Whether it was an append.
 * @returns {URL}
 */
const fallbackUrl = (page: number, append: boolean): URL => {
    const url = Listing.buildUrl(current());
    if (append) {
        url.searchParams.set(PARAM.page, String(page));
    }
    url.hash = '';
    return url;
};

/**
 * The service's arguments for one poster's panel, the page's criteria read off the address.
 *
 * A public area is asked by its own id, as the panel of its page (withcategories). A merely listed one - its
 * poster has no page to link its title to - is asked as a slice of this page's listing, which the service
 * answers only in a flat order and on the first page, and never with the tree's category, a page number or a
 * panel's request beside it (rows::require_wellformed_slice()). The root reads its term alone.
 *
 * @param {HTMLElement} poster The area's poster.
 * @param {string} psort The panel's order: the empty string (the newest first) or name.
 * @returns {object}
 */
const panelArgs = (poster: HTMLElement, psort: string): Record<string, unknown> => {
    const id = Number(poster.dataset.categoryid);
    const sort = psort === PSORT_NAME ? SORT.name : SORT.recent;
    const state = current();
    const asked = {categoryid: state.categoryid, view: state.view, viewchosen: state.viewchosen} as ListingState;
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
 * Ask for one poster's panel: the posters' component's question, through the one call site.
 *
 * @param {HTMLElement} poster The area's poster.
 * @param {string} psort The panel's order.
 * @returns {Promise} The answer, its panel's context included when the service draws one.
 */
const askPanel = (poster: HTMLElement, psort: string): Promise<Rows> => {
    return ask(panelArgs(poster, psort));
};

/**
 * The question a panel's request asks, so that the posters' component never sends the same one twice.
 *
 * @param {HTMLElement} poster The area's poster.
 * @param {string} psort The panel's order.
 * @returns {string}
 */
const panelQuestion = (poster: HTMLElement, psort: string): string => {
    return JSON.stringify(panelArgs(poster, psort));
};

/**
 * A panel could not be answered in place: load the poster's own link, with the panel's order. A slice the
 * server no longer admits ends on the page that says what it now is, never in a loop: arming fetches nothing.
 *
 * @param {HTMLElement} poster The area's poster.
 * @param {string} psort The panel's order.
 * @returns {boolean} Always true: the failure is this shell's.
 */
const panelFailed = (poster: HTMLElement, psort: string): boolean => {
    fallBack(panelAddress(Number(poster.dataset.categoryid), psort));
    return true;
};

/**
 * The visitor's answer to a page of its request: its service, with its arguments.
 *
 * @param {number} page The zero-based page.
 * @returns {Promise} The answer.
 */
const rows = (page: number): Promise<Rows> => ask(rowsArgs(page));

/**
 * The latest request could not be answered in place: fall back to its address.
 *
 * @param {number} page The zero-based page it asked for.
 * @param {boolean} append Whether it was an append.
 * @returns {void}
 */
const failed = (page: number, append: boolean): void => {
    fallBack(fallbackUrl(page, append));
};

/**
 * What the visitor's shell does where the logged-in shell remembers or watches: nothing.
 *
 * @returns {void}
 */
const nothing = (): void => undefined;

/** The visitor's shell for the store. */
const SHELL = {
    mark: BODY_CLASS,
    normalise: publicOnly,
    rows: rows,
    failed: failed,
    changed: nothing,
    discard: null,
    painted: nothing,
    written: linkPosters,
    leave: navigate,
    answered: linkPosters,
    // The native fields stay the server's and work: the visitor is told nothing.
    unmounted: nothing,
    areas: {
        props: AREAS_PROPS,
        client: {
            ask: askPanel,
            question: panelQuestion,
            unanswered: panelFailed,
        },
    },
};

/**
 * Arm the visitor's live listing and its panels on a public category page.
 *
 * @param {number} categoryid The category the page lists, 0 for the root of the course index.
 * @returns {void}
 */
export const start = (categoryid: number): void => {
    init(SHELL, Number(categoryid));
};
