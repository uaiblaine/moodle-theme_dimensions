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
 * The showcase listing's store: the state of the page on screen, every action a reader takes, and the one
 * pipeline from an action to the address and the request.
 *
 * The page is complete without it; the store moves the page the server drew. A shell is injected
 * ({@see Shell}): its service, its failure, its mark and its rules. Nothing here names a shell, a service or
 * the session: the visitor's page imports this module. One listing runs on a page, so the state lives at
 * module scope.
 *
 * @module     theme_dimensions/listing/store
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from '@moodle/lms/core/pending';
import {period} from '../daterange/calendar';
import {markTitles} from '../areas/mark';
import type {ListingState} from './address';
import type {AreasClient} from './mounts';
import type {Answer} from './paint';
import * as Listing from './listing';
import {LISTING, SELECTORS} from './events';

/** A rows answer: the painters' part and the page's own numbers. */
export type Rows = Answer & {cards?: object[], page?: number | string, searchtooshort?: boolean, clearurl?: string,
    accordion?: object[], areas?: Record<string, unknown>};

/**
 * What a shell gives the store. `rows` asks its service for a page; `failed` takes over the latest request
 * that could not be answered; `changed` hears what changed (term, tree, period, filter, view, history) BEFORE the
 * address is written, so a shell that remembers can mark the state first; `written` runs after every address the
 * store writes; `leave` loads the address of a change of shell; `normalise` drops what the shell never honours;
 * `discard` is handed every card a replaced grid drops; `painted` the cards an answer just drew; `answered` sees
 * an answer that replaced the grid, before the posters' component does; `unmounted` hears a calendar that could not
 * mount, over the native fields the server drew, which still work.
 */
export type Shell = {
    mark: string,
    normalise: (state: ListingState) => void,
    rows: (page: number) => Promise<Rows>,
    failed: (page: number, append: boolean, error: unknown) => void,
    changed: (kind: string) => void,
    written: () => void,
    leave: (url: URL) => void,
    discard: ((card: HTMLElement) => void) | null,
    painted: (items: HTMLElement[]) => void,
    answered: (response: Rows) => void,
    unmounted: (error: unknown) => void,
    areas: {props: object, client: AreasClient},
};

/** Milliseconds of quiet before a typed term is sent. */
const SEARCH_DEBOUNCE = 300;

/** Characters below which a term is not a search at all, the server's own floor. */
const MIN_SEARCH_LENGTH = 2;

/** The listing section. */
let root: HTMLElement;

/** The areas section, on a grouped page and at the root, or null. */
let areas: HTMLElement | null = null;

/** The request the page is showing. */
let state: ListingState;

/** The injected shell. */
let shell: Shell;

/** Monotonic ticket: a response whose ticket is not the current one is discarded. */
let seq = 0;

/** The last page the server answered with, appended pages included: the grid's, not the address's. */
let lastpage = 0;

/** The pending search timer. */
let searchtimer: number | undefined;

/** Held from a keystroke until the answer it leads to is painted, so Behat waits through the quiet too. */
let searchpending: Pending | null = null;

/**
 * The state the store holds, which its shell reads a request's arguments off.
 *
 * @returns {object}
 */
export const current = (): ListingState => state;

/**
 * Put one response onto the page, unless a newer request has gone out while its cards were rendering.
 *
 * @param {object} response The rows response.
 * @param {boolean} append Whether to append the rows rather than replace them.
 * @param {number} ticket The request's ticket.
 * @returns {Promise} Resolving when the page has been repainted, or at once for an overtaken answer.
 */
const applyResponse = async(response: Rows, append: boolean, ticket: number): Promise<void> => {
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
    const grid = root.querySelector<HTMLElement>(SELECTORS.grid);
    if (grid) {
        Listing.paintGrid(root, state, grid, items, append, shell.discard);
    }
    const notice = root.querySelector<HTMLElement>(SELECTORS.notice);
    if (notice) {
        notice.hidden = !response.searchtooshort;
    }
    const clear = root.querySelector<HTMLAnchorElement>(SELECTORS.clear);
    if (clear && response.clearurl) {
        clear.href = Listing.withPanel(response.clearurl);
    }
    Listing.paintFilterBadge(root, response);
    Listing.paintCount(root, response);
    Listing.paintChips(root, response);
    Listing.paintGroups(root, response);
    Listing.paintFavourites(root, response);
    shell.painted(items);
    Listing.paintPager(root, response, append, items.length ? items[0] : null);
    if (!append) {
        Listing.paintDirect(root, response);
        paintAreas(response);
    }
    // The line's numbers are the component's to draw, after the painters: its suggestions read their counts.
    Listing.tellAnswer(root, response);
};

/**
 * Tell an answer that replaced the grid to the shell, then the posters' component.
 *
 * The shell first: the component handles the event synchronously and may open an area inside it, whose note the
 * shell writes from the answer it was handed.
 *
 * @param {object} response The rows response.
 * @returns {void}
 */
const paintAreas = (response: Rows): void => {
    if (!areas) {
        return;
    }
    const word = response.areas || {};
    shell.answered(response);
    document.dispatchEvent(new CustomEvent(LISTING.criteria, {detail: {
        accordion: response.accordion || [],
        areas: word,
        filtered: Boolean(word.filtered),
        total: Number(response.total),
    }}));
};

/**
 * Ask the shell's service for one page, and put it on the screen unless something newer has been asked since.
 *
 * @param {number} page The zero-based page to fetch.
 * @param {boolean} append Whether to append the rows rather than replace them.
 * @returns {Promise} Resolving when the request has been dealt with, successfully or not.
 */
const requestRows = async(page: number, append: boolean): Promise<void> => {
    const ticket = ++seq;
    /* One token from the call until the answer is painted, or dropped: the template load between the request's
       own pending and the render's waits on the module loader's timer, a gap Behat would read as a settled page. */
    const pending = new Pending('theme_dimensions/listing:rows');
    const more = root.querySelector<HTMLButtonElement>(SELECTORS.loadmore);
    if (append && more) {
        more.disabled = true;
        more.textContent = more.dataset.loading || more.textContent;
    }
    if (!append) {
        root.setAttribute('aria-busy', 'true');
        // A new question for the posters: the component holds their numbers until it is answered.
        if (areas) {
            document.dispatchEvent(new CustomEvent(LISTING.pending, {detail: {filtered: Listing.narrows(state)}}));
        }
    }
    try {
        const response = await shell.rows(page);
        if (ticket !== seq) {
            return;
        }
        await applyResponse(response, append, ticket);
    } catch (error) {
        if (ticket === seq) {
            shell.failed(page, append, error);
        }
    } finally {
        if (ticket === seq) {
            root.removeAttribute('aria-busy');
        }
        Listing.restoreMore(more);
        pending.resolve();
    }
};

/**
 * Apply a changed criterion: the shell told, a new history entry, then the request. An open panel stays open and
 * is asked again with the answer.
 *
 * @param {string} kind What changed: term, tree, period or filter.
 * @returns {Promise} Resolving when the request has been dealt with.
 */
const apply = (kind: string): Promise<void> => {
    state.page = 0;
    shell.changed(kind);
    Listing.writeUrl(state, true);
    shell.written();
    return requestRows(0, false);
};

/**
 * Deal with a typed term: the two-character floor is mirrored here, the rest is the server's.
 *
 * @param {string} raw What is in the box.
 * @returns {Promise} Resolving when the term has been dealt with, at once for one below the floor.
 */
const onSearch = async(raw: string): Promise<void> => {
    const term = raw.trim();
    const notice = root.querySelector<HTMLElement>(SELECTORS.notice);
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
    await apply('term');
};

/**
 * Drop a typed term that has not been sent yet, and the pending token held for it.
 *
 * @returns {void}
 */
const cancelSearch = (): void => {
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
 * @returns {void}
 */
const onSearchInput = (search: HTMLInputElement): void => {
    cancelSearch();
    Listing.paintEraser(root);
    const pending = new Pending('theme_dimensions/listing:search');
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
 * Deal with an organisation being chosen: a fetch inside one shell, a navigation between two, because the
 * grouped organisation draws the posters and the flat ones a bare grid.
 *
 * @param {string} sort The organisation chosen.
 * @returns {void}
 */
const onSort = (sort: string): void => {
    if (sort === state.sort) {
        return;
    }
    const was = Listing.isGrouped(state.sort);
    state.sort = sort;
    state.page = 0;
    if (was !== Listing.isGrouped(sort)) {
        shell.leave(Listing.buildUrl(state));
        return;
    }
    apply('filter');
};

/**
 * Deal with a category being pressed in the tree: a filter, fetched, and never a way out of the page.
 *
 * @param {HTMLElement} link The tree entry pressed.
 * @returns {void}
 */
const onTree = (link: HTMLElement): void => {
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
    apply('tree');
};

/**
 * Take a period, read the way the server reads one: onto the state, onto both spellings of the control, applied.
 *
 * @param {string} datefrom The first day, YYYY-MM-DD, or the empty string.
 * @param {string} dateto The last day, YYYY-MM-DD, or the empty string.
 * @returns {void}
 */
const setRange = (datefrom: string, dateto: string): void => {
    const range = period(datefrom, dateto);
    state.datefrom = range.from;
    state.dateto = range.to;
    Listing.paintDates(root, state);
    apply('period');
};

/**
 * Deal with a facet or an organisation chip being chosen.
 *
 * @param {HTMLInputElement} input The radio that changed.
 * @returns {void}
 */
const onChoose = (input: HTMLInputElement): void => {
    const group = input.closest<HTMLElement>(SELECTORS.group);
    const name = group && group.dataset.name ? group.dataset.name : input.name;
    if (name === Listing.PARAM.sort) {
        onSort(input.value);
        return;
    }
    if (name === Listing.PARAM.chip) {
        state.chip = input.value;
    } else if (name && name.startsWith(Listing.PARAM.fieldprefix)) {
        const fieldid = name.slice(Listing.PARAM.fieldprefix.length);
        if (Number(input.value) > 0) {
            state.filters[fieldid] = Number(input.value);
        } else {
            delete state.filters[fieldid];
        }
    } else {
        return;
    }
    apply('filter');
};

/**
 * Deal with the favourites toggle being pressed: a filter that combines with the state chip, repainted at once,
 * because aria-pressed is what was pressed.
 *
 * @returns {void}
 */
const onFav = (): void => {
    state.fav = !state.fav;
    Listing.paintFavToggle(root, state.fav);
    apply('filter');
};

/**
 * Move to a view: the class and the address, no request.
 *
 * @param {string} view Either cards or list.
 * @returns {void}
 */
const setView = (view: string): void => {
    state.view = view;
    state.viewchosen = true;
    Listing.paintView(root, view);
    shell.changed('view');
    Listing.writeUrl(state, true);
    shell.written();
};

/**
 * Give the search the focus: the control pressed is about to hide itself, and the focus would fall to the top.
 *
 * @returns {void}
 */
const focusSearch = (): void => {
    // The field, or its icon where the line folded it away: a folded field cannot take the focus.
    const search = Listing.focusTarget(root);
    if (search) {
        search.focus({preventScroll: true});
    }
};

/**
 * Clear every filter in place, and give the focus to the search box. An open panel stays open.
 *
 * @returns {void}
 */
const onClear = (): void => {
    cancelSearch();
    state.query = '';
    state.chip = '';
    state.fav = false;
    state.filters = {};
    state.datefrom = '';
    state.dateto = '';
    state.cat = 0;
    const notice = root.querySelector<HTMLElement>(SELECTORS.notice);
    if (notice) {
        notice.hidden = true;
    }
    Listing.paintControls(root, state);
    apply('filter');
    focusSearch();
};

/**
 * Drop the one criterion a chip of the row under the filter line names, in place.
 *
 * @param {HTMLElement} chip The chip pressed.
 * @returns {boolean} Whether the chip was handled; a chip naming nothing this state holds is left to its link.
 */
const onChipRemove = (chip: HTMLElement): boolean => {
    if (!Listing.dropCriterion(state, chip.dataset.param || '')) {
        return false;
    }
    shell.normalise(state);
    Listing.paintControls(root, state);
    apply('filter');
    focusSearch();
    return true;
};

/**
 * Take the typed term out of the state, for a suggestion of the filter line about to apply its filter: the one
 * request its control makes then carries no term, and a term still waiting for its debounce is never sent.
 *
 * @returns {void}
 */
const dropTerm = (): void => {
    cancelSearch();
    state.query = '';
    const notice = root.querySelector<HTMLElement>(SELECTORS.notice);
    if (notice) {
        notice.hidden = true;
    }
    Listing.paintEraser(root);
};

/**
 * Empty the search box with its eraser, search again for nothing, and keep the focus in the box.
 *
 * @returns {Promise} Resolving when the answer has been painted.
 */
const onErase = async(): Promise<void> => {
    cancelSearch();
    const search = root.querySelector<HTMLInputElement>(SELECTORS.search);
    // The component's eraser has emptied its own field and kept the focus in it; the store writes into neither.
    if (search && !search.closest(SELECTORS.drawnline)) {
        search.value = '';
        search.focus({preventScroll: true});
    }
    Listing.paintEraser(root);
    await onSearch('');
};

/**
 * Back and forward: the address read back into the state, the controls put back, page zero asked for.
 *
 * @returns {void}
 */
const onPopState = (): void => {
    Listing.readUrl(state);
    shell.normalise(state);
    Listing.paintControls(root, state);
    Listing.syncFold();
    shell.written();
    shell.changed('history');
    requestRows(0, false);
};

/**
 * Wire every listener of the listing but the history's.
 *
 * @returns {void}
 */
const listen = (): void => {
    const form = root.querySelector(SELECTORS.toolbar);
    if (form) {
        // Enter in the search box: everything is applied in place already.
        form.addEventListener('submit', (event) => event.preventDefault());
        // Delegated: the line's component replaces the server's field with its own.
        form.addEventListener('input', (event) => {
            const search = Listing.closestOf(event, SELECTORS.search);
            if (search) {
                onSearchInput(search as HTMLInputElement);
            }
        });
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
        const chip = Listing.closestOf(event, SELECTORS.chipremove);
        if (chip && onChipRemove(chip)) {
            event.preventDefault();
            return;
        }
        if (Listing.closestOf(event, SELECTORS.eraser)) {
            event.preventDefault();
            // A failed request is the shell's to report (requestRows()), so nothing is left to report here.
            onErase().catch(() => null);
            return;
        }
        const quick = Listing.closestOf(event, SELECTORS.quick);
        if (quick) {
            event.preventDefault();
            setRange(quick.dataset.from || '', quick.dataset.to || '');
            return;
        }
        if (Listing.closestOf(event, SELECTORS.fav)) {
            // A submit button for a reader without a client; a fetch with a history entry here.
            event.preventDefault();
            onFav();
            return;
        }
        const view = Listing.closestOf(event, SELECTORS.viewbutton) as HTMLButtonElement | null;
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
};

/**
 * Arm the listing on its page for a shell. Nothing is fetched: the page on screen is the server's answer to its
 * own address, so a failure later can only ever load a page, and never loop.
 *
 * The history's listener goes on before the components are mounted, so Back reaches the state before the
 * posters' component reads the panel's half of the address. The posters are handed over wherever the page has
 * posters with a panel, the root's directory without a search line included; the filter line goes to its
 * component before the calendar's node goes inside it, or the line put back where it stands would take the
 * calendar out and in again.
 *
 * @param {object} given The shell.
 * @param {number} categoryid The category the page lists, 0 for the root of the course index.
 * @returns {boolean} Whether the listing was armed, which a root without a search line is not: its posters alone are.
 */
export const init = (given: Shell, categoryid: number): boolean => {
    const found = document.querySelector<HTMLElement>(SELECTORS.root);
    if (!found || !Number.isInteger(categoryid) || categoryid < 0) {
        return false;
    }
    root = found;
    shell = given;
    let payload: Record<string, unknown> = {};
    try {
        payload = JSON.parse(root.dataset.vitrine || '{}');
    } catch (error) {
        payload = {};
    }
    const params = new URL(window.location.href).searchParams;
    const range = Listing.periodOf(params);
    // The category's default organisation, which an address naming none gets (criteria's rule).
    const defaultsort = typeof payload.defaultsort === 'string' ? payload.defaultsort : '';
    /* A reader who left a state on this category may have arrived on its bare address, which the server drew with
       that state: the organisation is then the payload's, not the address's. */
    const remembered = payload.remembered === true;
    const sort = remembered && typeof payload.sort === 'string' ? payload.sort : Listing.sortOf(params, defaultsort);
    state = {
        categoryid: categoryid,
        page: Number(payload.page) || 0,
        total: Number(payload.total) || 0,
        hasmore: Boolean(payload.hasmore),
        query: typeof payload.query === 'string' ? payload.query : '',
        chip: typeof payload.chip === 'string' ? payload.chip : '',
        // The server read an older address's favourites chip as the toggle already.
        fav: payload.fav === true,
        filters: payload.filters && typeof payload.filters === 'object'
            ? Object.assign({}, payload.filters) as Record<string, number> : {},
        // The organisation, the period and the tree's category are the address's: the toolbar is a GET form.
        defaultsort: defaultsort,
        sort: sort,
        // Whether every address the store writes names its organisation (the shell's changed() sets it).
        remembered: remembered,
        datefrom: range.from,
        dateto: range.to,
        cat: Listing.catOf(params, sort),
        view: payload.view === 'list' ? 'list' : 'cards',
        viewchosen: params.has(Listing.PARAM.view),
        pagesize: Listing.pageSizeOf(params),
    };
    lastpage = state.page;
    shell.normalise(state);
    areas = root.querySelector<HTMLElement>(SELECTORS.areas);
    // The root is armed for its search line; a root with nothing to search draws none.
    const live = !(Listing.isRoot(state) && !root.querySelector(SELECTORS.toolbar));
    if (live) {
        // The same function, so a second start never doubles the listener.
        window.removeEventListener('popstate', onPopState);
        window.addEventListener('popstate', onPopState);
    }
    shell.written();
    Listing.mountAreas(areas, shell.areas.props, shell.areas.client);
    if (!live) {
        return false;
    }

    // What hides the submits only a reader without a client needs.
    document.body.classList.add(shell.mark);

    // The searched term in the titles of the server's own rows.
    markTitles(root.querySelector(SELECTORS.grid), state.query);

    Listing.mountLine(root, dropTerm);
    // A calendar that cannot mount leaves the native fields exactly as the server drew them; the shell decides what is said.
    Listing.mountCalendar(root, state, setRange).catch(shell.unmounted);

    // The numbered bar is the reader-without-a-client's pager and is now the button's job.
    const bar = root.querySelector<HTMLElement>(SELECTORS.pagerbar);
    if (bar) {
        bar.hidden = true;
    }

    Listing.armTreebox(root);
    listen();
    return true;
};
