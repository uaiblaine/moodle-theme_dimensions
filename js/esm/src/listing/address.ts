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
 * The showcase listing's state as its address spells it: read from the address the way the server reads
 * it (criteria), and written back the way the server's own links spell it.
 *
 * Every function is handed the calling client's state ({@see ListingState}) and keeps nothing of its own.
 * Imported by the visitor's page: nothing here may read the session.
 *
 * @module     theme_dimensions/listing/address
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {period as calendarPeriod} from '../daterange/calendar';
import type {Period} from '../daterange/calendar';
import {SELECTORS} from './events';

/**
 * A listing client's state, which may carry fields of its own. `remembered`: every address names its
 * organisation; `viewchosen`: the view is written back; `defaultsort`: what a bare address gets here; `sort`:
 * the organisation (criteria::SORTS); `psort`: the grouped order, '' (the newest first) or name, always ''
 * under a flat organisation, which carries its order in `sort`.
 */
export type ListingState = {
    categoryid: number,
    page: number,
    total?: number,
    hasmore?: boolean,
    query: string,
    chip: string,
    fav: boolean,
    filters: Record<string, number>,
    sort: string,
    psort: string,
    defaultsort: string,
    remembered?: boolean,
    datefrom: string,
    dateto: string,
    cat: number,
    view: string,
    viewchosen: boolean,
    pagesize: number,
};

/** The request parameter names, the same ones the form and the server use. */
export const PARAM = {
    query: 'q',
    chip: 'chip',
    fav: 'fav',
    page: 'page',
    perpage: 'perpage',
    view: 'view',
    sort: 'sort',
    group: 'group',
    from: 'from',
    to: 'to',
    cat: 'cat',
    fieldprefix: 'cf_',
    open: 'open',
    psort: 'psort',
};

/** The parameters the hero's fold form carries: criteria::query_params(), never the page or the view. */
const FOLD_PARAMS = [PARAM.query, PARAM.chip, PARAM.fav, PARAM.from, PARAM.to, PARAM.cat, PARAM.sort, PARAM.perpage,
    PARAM.open, PARAM.psort];

/**
 * The state chip's values the filter line draws as its scope, "all" first: criteria::SCOPE, held equal by
 * category_listing_markup_test. Every other value is an option of the panel's "More statuses".
 */
export const SCOPE = ['', 'enrolled', 'open', 'scheduled'];

/** A chip's data-param for the period, whose two bounds it drops together: the server's CHIP_PERIOD. */
export const CHIP_PERIOD = 'period';

/** The state chip's value for the favourites before they were a toggle, read as the toggle (criteria). */
const LEGACY_FAVOURITES = 'favourites';

/**
 * The two organisations that are one shell, the server's is_grouped(): moving between shells is a navigation.
 */
export const GROUPED_SORTS = ['', 'category'];

/** The server's criteria::SORTS, the empty string first: what an address may name. */
const SORTS = ['', 'category', 'name', 'recent'];

/** The server's criteria::GROUPS, "Group by" in its order: the grouping by area, then none. */
export const GROUPS = ['category', 'none'];

/** The server's criteria::ORDERS, "Sort by" in its order. */
export const ORDERS = ['name', 'recent'];

/** The grouped order A-Z, the one value the grouped order is ever written with: criteria::SORT_NAME. */
const ORDER_NAME = 'name';

/**
 * The organisation and the grouped order a query string names, read from both spellings as the server's
 * criteria::organisation() reads them: a grouping by name with its order in sort, or the older spelling, an
 * organisation in sort and the grouped order in psort. Nothing named is the category's default.
 *
 * @param {URLSearchParams} params The query string to read.
 * @param {string} defaultsort The category's default, the empty string for the theme's.
 * @returns {object} sort (one of SORTS) and psort ('' or name, '' under a flat organisation).
 */
export const organisationOf = (params: URLSearchParams, defaultsort: string): {sort: string, psort: string} => {
    const group = params.get(PARAM.group) || '';
    const named = params.get(PARAM.sort) || '';
    let psort = params.get(PARAM.psort) === ORDER_NAME ? ORDER_NAME : '';
    let sort = SORTS.indexOf(named) > 0 ? named : '';
    if (GROUPS.indexOf(group) >= 0) {
        let order = psort === ORDER_NAME ? ORDER_NAME : 'recent';
        if (ORDERS.indexOf(named) >= 0) {
            order = named;
        }
        sort = group === 'none' ? order : 'category';
        psort = group === 'none' || order !== ORDER_NAME ? '' : ORDER_NAME;
    }
    sort = sort || defaultsort || '';
    return {sort: sort, psort: GROUPED_SORTS.indexOf(sort) >= 0 ? psort : ''};
};

/**
 * The grouping in force, by name: what "Group by" shows chosen.
 *
 * @param {object} state The calling client's state.
 * @returns {string} One of GROUPS.
 */
export const groupOf = (state: ListingState): string => (GROUPED_SORTS.indexOf(state.sort) >= 0 ? 'category' : 'none');

/**
 * The order in force, whatever the grouping: what "Sort by" shows chosen, criteria::order().
 *
 * @param {object} state The calling client's state.
 * @returns {string} One of ORDERS.
 */
export const orderOf = (state: ListingState): string => {
    if (GROUPED_SORTS.indexOf(state.sort) >= 0) {
        return state.psort === ORDER_NAME ? ORDER_NAME : 'recent';
    }
    return state.sort;
};

/**
 * Put a grouping and an order into a state, each kept as the other one left it (fleet Design System D49).
 *
 * @param {object} state The calling client's state.
 * @param {string} group One of GROUPS.
 * @param {string} order One of ORDERS.
 * @returns {void}
 */
export const organise = (state: ListingState, group: string, order: string): void => {
    if (group === 'none') {
        state.sort = order;
        state.psort = '';
        return;
    }
    // Into the grouping the default is named, so a flat-default category's address can name it (criteria::sort_params());
    // within it the spelling stays, so a reorder writes no organisation the address did not name.
    state.sort = isGrouped(state.sort) ? state.sort : 'category';
    state.psort = order === ORDER_NAME ? ORDER_NAME : '';
};

/**
 * Whether a state is the root's, the course index whose one criterion is the search.
 *
 * @param {object} state The calling client's state.
 * @returns {boolean}
 */
export const isRoot = (state: ListingState): boolean => state.categoryid === 0;

/**
 * Drop from the state everything the root does not read: it honours its search and nothing else.
 *
 * @param {object} state The calling client's state.
 * @returns {void}
 */
export const rootOnly = (state: ListingState): void => {
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
 * @param {URLSearchParams} params The query string to read.
 * @returns {number} The requested size, or 0.
 */
export const pageSizeOf = (params: URLSearchParams): number => {
    const size = Number(params.get(PARAM.perpage));
    return Number.isInteger(size) && size > 0 ? size : 0;
};

/**
 * The organisation in force for a query string: the one it names, else the category's default.
 *
 * @param {URLSearchParams} params The query string to read.
 * @param {string} defaultsort The category's default, the empty string for the theme's.
 * @returns {string} The organisation.
 */
export const sortOf = (params: URLSearchParams, defaultsort: string): string => organisationOf(params, defaultsort).sort;

/**
 * Whether an organisation draws the areas, which is the server's own is_grouped().
 *
 * @param {string} sort The organisation's value.
 * @returns {boolean} Whether that organisation is the grouped one.
 */
export const isGrouped = (sort: string): boolean => GROUPED_SORTS.indexOf(sort) >= 0;

/**
 * The tree's category as the address states it, and zero whenever the page cannot use it.
 *
 * @param {URLSearchParams} params The query string to read.
 * @param {string} sort The organisation that query string names.
 * @returns {number} The category, or 0.
 */
export const catOf = (params: URLSearchParams, sort: string): number => {
    const cat = Number(params.get(PARAM.cat));
    return isGrouped(sort) || !Number.isInteger(cat) || cat <= 0 ? 0 : cat;
};

/**
 * The period as the address states it, read the way the server reads it: a bound that is not a date is
 * dropped and a pair given backwards is put in order, because the web service refuses a pair given
 * backwards rather than guessing at it.
 *
 * @param {URLSearchParams} params The query string to read.
 * @returns {object} The {from, to} pair, each YYYY-MM-DD or the empty string.
 */
export const periodOf = (params: URLSearchParams): Period => calendarPeriod(params.get(PARAM.from), params.get(PARAM.to));

/**
 * The state chip as the address states it, the older spelling of the favourites left out.
 *
 * @param {URLSearchParams} params The query string to read.
 * @returns {string} The chip, or the empty string.
 */
export const chipOf = (params: URLSearchParams): string => {
    const chip = params.get(PARAM.chip) || '';
    return chip === LEGACY_FAVOURITES ? '' : chip;
};

/**
 * Whether the address asks for the favourites: the toggle, or the chip that spelled them before it.
 *
 * @param {URLSearchParams} params The query string to read.
 * @returns {boolean}
 */
export const favOf = (params: URLSearchParams): boolean =>
    params.get(PARAM.fav) === '1' || params.get(PARAM.chip) === LEGACY_FAVOURITES;

/**
 * The address a state is at: the same parameter names the form posts.
 *
 * @param {object} state The calling client's state.
 * @returns {URL} The address.
 */
export const buildUrl = (state: ListingState): URL => {
    const url = new URL(window.location.href);
    const params = url.searchParams;
    Array.from(params.keys())
        .filter((key) => key.startsWith(PARAM.fieldprefix))
        .forEach((key) => params.delete(key));
    // The grouping by name is what the form posts without JavaScript; the addresses spell the older way (criteria).
    [PARAM.query, PARAM.chip, PARAM.fav, PARAM.page, PARAM.view, PARAM.sort, PARAM.group, PARAM.from, PARAM.to, PARAM.cat]
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
    /* For a reader who left a state on this category the organisation is ALWAYS written, as sort_params() does:
       the bare address means that reader's remembered state, not the page on screen. */
    if (state.remembered) {
        params.set(PARAM.sort, state.sort === '' ? 'category' : state.sort);
    }
    // Otherwise only when it is not the category's default: the grouped one too, on a flat-default category.
    if (state.sort !== '' && state.sort !== (state.defaultsort || '')) {
        params.set(PARAM.sort, state.sort);
    }
    // Each bound on its own: a start alone and an end alone are periods the server answers (criteria).
    if (state.datefrom !== '') {
        params.set(PARAM.from, state.datefrom);
    }
    if (state.dateto !== '') {
        params.set(PARAM.to, state.dateto);
    }
    // The tree's category, never under the grouped organisation, which draws no tree (the server drops it).
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
    // A view stamped into a shared link would override the recipient's own preference: written only when chosen.
    if (state.viewchosen) {
        params.set(PARAM.view, state.view);
    }
    // The open panel rides along untouched, except into a flat organisation, which draws no posters.
    if (!isGrouped(state.sort)) {
        params.delete(PARAM.open);
        params.delete(PARAM.psort);
    } else if (state.psort === ORDER_NAME) {
        // The grouped order, which the posters' component writes as well, from the same state (criteria::order_params()).
        params.set(PARAM.psort, ORDER_NAME);
    } else {
        params.delete(PARAM.psort);
    }
    return url;
};

/**
 * Rewrite the hero fold form's criteria from the address, so that folding keeps what is on screen.
 *
 * @returns {void}
 */
export const syncFold = (): void => {
    const form = document.querySelector(SELECTORS.fold);
    if (!form) {
        return;
    }
    /**
     * Whether the fold form carries a parameter.
     *
     * @param {string} name The parameter's name.
     * @returns {boolean}
     */
    const carried = (name: string): boolean => FOLD_PARAMS.indexOf(name) >= 0 || name.startsWith(PARAM.fieldprefix);
    form.querySelectorAll<HTMLInputElement>('input[type="hidden"]').forEach((input) => {
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
 * @param {string} href The server's address.
 * @returns {string} The same address with the address bar's open and psort, when it has them.
 */
export const withPanel = (href: string): string => {
    const target = new URL(href, window.location.href);
    const current = new URL(window.location.href).searchParams;
    [PARAM.open, PARAM.psort].forEach((name) => {
        if (current.has(name)) {
            target.searchParams.set(name, current.get(name) || '');
        } else {
            target.searchParams.delete(name);
        }
    });
    return target.toString();
};

/**
 * Read the address bar back into a state, always at page zero.
 *
 * @param {object} state The calling client's state.
 * @returns {void}
 */
export const readUrl = (state: ListingState): void => {
    const params = new URL(window.location.href).searchParams;
    state.query = params.get(PARAM.query) || '';
    state.chip = chipOf(params);
    state.fav = favOf(params);
    const organisation = organisationOf(params, state.defaultsort);
    state.sort = organisation.sort;
    state.psort = organisation.psort;
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
 * Whether a state narrows the page: the server's has_filters() without the tree's category, which the one
 * caller (the posters' pending event) can leave out because a grouped page never carries one.
 *
 * @param {object} state The calling client's state.
 * @returns {boolean}
 */
export const narrows = (state: ListingState): boolean => state.query !== ''
    || state.chip !== ''
    || Boolean(state.fav)
    || Object.keys(state.filters).length > 0
    || state.datefrom !== ''
    || state.dateto !== '';

/**
 * The custom-field filters, in the shape both web services declare for them.
 *
 * @param {object} state The calling client's state.
 * @returns {Array} One entry of fieldid and value per filter.
 */
export const filterArgs = (state: ListingState): Array<{fieldid: number, value: number}> =>
    Object.keys(state.filters).map((fieldid) => ({
        fieldid: Number(fieldid),
        value: Number(state.filters[fieldid]),
    }));

/**
 * Drop from a state the one criterion a chip names, as the server's address for that chip drops it.
 *
 * @param {object} state The calling client's state.
 * @param {string} param The chip's data-param: q (the search's, shown where the field is folded away), a
 *   field's parameter, chip, cat, or period.
 * @returns {boolean} Whether the chip named a criterion this state can drop.
 */
export const dropCriterion = (state: ListingState, param: string): boolean => {
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
