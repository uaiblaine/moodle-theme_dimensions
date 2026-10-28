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
 * The client of the category showcase.
 *
 * The page it runs on is already complete without it: the toolbar is a real GET form, the pager is
 * real links, every state of the listing has an address. So this module never builds a page - it
 * moves the one the server drew, and it renders a new row through the same template the server
 * used, so a row that arrives with page four is the row that would have arrived with page one.
 *
 * The URL holds every state of the listing. Beside it, a real account's organisation, view and
 * filters are remembered for THIS category through the theme's web service ({@see remember}), and
 * read back by the server for a bare address of the same category only - a filter carried into
 * another category is how a person concludes it is empty. The view is also the person's one
 * preference for every category with no state of its own.
 *
 * The anonymous shell draws the same listing and never loads this module; only the logged-in
 * shell's renderer arms it, and not for the guest. A visitor's live listing is categorypublic's.
 * The two paint an answer and write the address with the same functions (categorylisting), so the
 * two shells cannot be told apart row for row; what this module adds is what only an account has -
 * the progress of the courses the reader is enrolled in, the hover peek, the star and the view
 * remembered as a preference.
 *
 * The root of the course index arms it for its search line alone: the root reads the search and
 * nothing else, so there this module asks for the term alone, keeps the term alone in the address,
 * and paints an answer that has no course grid - the posters' numbers are the panel module's, told
 * through the same event as on a category page ({@see isRootPage}).
 *
 * One control of the toolbar is a navigation rather than a fetch, and the reason is structural:
 * the grouped organisation draws the areas and then the courses sitting directly in the category,
 * and the flat ones draw a bare grid, so moving between the two changes the shell and not the rows
 * in it. Every other move - between the two flat orders, and every filter, search, period and page
 * - is a fetch with a history entry.
 *
 * The posters of the areas and the panel between their rows are not this module's: since stage R3 they
 * are a React component's (js/esm/src/areas/Areas.tsx, appended by categorypanel), which is their only
 * writer. The two talk through events on the document - the view changed; a request for new criteria
 * went out; its answer arrived (with each area's shell, so a panel opened next knows its call to action
 * before its rows arrive, each area's results and the page's word on the areas as a whole); it failed;
 * and, back from the component, an area was opened by itself - and through the address, whose panel
 * parameters this module carries without owning them. The star is
 * categorystar's, and the searched term is marked in the cards' titles by categorymark.
 *
 * The filter line is a React component's since stage R2 of docs/vitrine-react/PLAN.md
 * (js/esm/src/filterline/FilterLine.tsx), handed the server's line by the shared mountLine(), as the
 * visitor's is since R4a; the calendar is R1's, appended by the shared mountCalendar(). The component
 * draws the line's own controls, which this module and the shared painters never write into: they tell it
 * what moved (the shared tellAnswer() after every answer, and every painter of the line's controls through
 * the line's events), and it says when a suggestion is about to press its control, so this module drops
 * the typed term first (dropTerm).
 *
 * @module     theme_dimensions/categoryvitrine
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {setUserPreference} from 'core_user/repository';
import * as CategoryMark from 'theme_dimensions/categorymark';
import * as CategoryStar from 'theme_dimensions/categorystar';
import * as DateRange from 'theme_dimensions/daterange';
import * as Listing from 'theme_dimensions/categorylisting';

/** @type {Object} Every hook this module reads, all of them written by the showcase's templates. */
const SELECTORS = {
    root: '[data-region="dim-vitrine"]',
    grid: '[data-region="dim-vitrine-grid"]',
    toolbar: '[data-region="dim-vitrine-toolbar"]',
    group: '[data-region="dim-vitrine-group"]',
    notice: '[data-region="dim-toolbar-notice"]',
    countnote: '[data-region="dim-vitrine-countnote"]',
    countnotetext: '[data-region="dim-vitrine-countnotetext"]',
    clear: '[data-region="dim-vitrine-clear"]',
    clearaction: '[data-action="dim-clear"]',
    chipremove: '[data-action="dim-chip-remove"]',
    eraser: '[data-action="dim-search-clear"]',
    pagerbar: '[data-region="dim-vitrine-pagerbar"]',
    loadmore: '[data-action="dim-loadmore"]',
    card: '[data-region="dim-card"]',
    details: '[data-region="dim-card-details"]',
    panel: '[data-region="dim-card-panel"]',
    peek: '.dim-card__peek',
    progress: '[data-region="dim-card-progress"]',
    search: 'input[type="search"]',
    viewbutton: '[data-action="dim-view"]',
    fav: '[data-action="dim-fav"]',
    quick: '[data-action="dim-quick"]',
    tree: '[data-region="dim-vitrine-tree"]',
    treelink: '[data-region="dim-vitrine-tree"] a[data-region="dim-tree-link"]',
    area: '[data-region="dim-area"]',
    areapanel: '[data-region="dim-panel"]',
    drawnline: '[data-region="dim-line"][data-line-drawn]',
};

/** @type {Object} The request parameter names, the same ones the form and the server use. */
const PARAM = Listing.PARAM;

/**
 * @type {Object} The events on the document: the four this module tells the panel's module about -
 * the view changed, a request for new criteria went out, its answer arrived, or it failed - and the
 * one it is told back, that an area was opened by itself on a single match.
 */
const EVENTS = {
    view: 'dim-vitrine:view',
    pending: 'dim-vitrine:pending',
    criteria: 'dim-vitrine:criteria',
    autoopened: 'dim-panel:autoopened',
    failed: 'dim-vitrine:failed',
};

/** @type {String} The preference holding the view every category without a state of its own opens in. */
const VIEW_PREFERENCE = 'theme_dimensions_categoryview';

/** @type {String} The service that remembers the state the reader left this category in. */
const REMEMBER_METHOD = 'theme_dimensions_remember_category_state';

/** @type {Number} Milliseconds of quiet before a changed state is remembered. */
const REMEMBER_DEBOUNCE = 500;

/** @type {Number} The longest a navigation waits for the state it leaves behind to be remembered. */
const REMEMBER_WAIT = 1000;

/** @type {String} The body class which hides the no-JavaScript submit and shows what only this module drives. */
const BODY_CLASS = 'dim-js';

/** @type {Number} Milliseconds of quiet before a typed term is sent. */
const SEARCH_DEBOUNCE = 300;

/** @type {Number} Milliseconds of quiet before the view preference is written. */
const VIEW_DEBOUNCE = 500;

/** @type {Number} Characters below which a term is not a search at all, the server's own floor. */
const MIN_SEARCH_LENGTH = 2;

/** @type {Number} How many course ids one details request may carry: enrichment::MAX_COURSES, which the server enforces. */
const BATCH_SIZE = 24;

/**
 * @type {Number} Milliseconds between drains of the pending set. A fixed interval, never a
 * debounce reset per id: a continuous scroll would then send nothing at all until it stopped.
 */
const BATCH_INTERVAL = 100;

/** @type {String} How far outside the viewport a card counts as worth asking about. */
const OBSERVER_MARGIN = '200px';

/** @type {Number} Milliseconds of hover before the peek panel is promoted. */
const PEEK_DELAY = 180;

/** @type {Number} The peek panel's own width plus its gap, for deciding which side it opens on. */
const PEEK_WIDTH = 340;

/** @type {Number} Below this viewport width the peek never opens: the panel renders inline there. */
const PEEK_MIN_WIDTH = 576;

/** @type {HTMLElement|null} The listing section. */
let root = null;

/** @type {Object|null} The request this page is showing, and what the server said about it. */
let state = null;

/** @type {Number} Monotonic ticket: a response whose ticket is not the current one is discarded. */
let seq = 0;

/**
 * @type {Number} The last page the server answered with, appended pages included. It is not
 * state.page: an appended page is state of this document with no address of its own, so writing
 * it into the query string would hand out a link to a document showing pages nought to three as
 * though it were page three alone. state.page follows the address; this follows the grid.
 */
let lastpage = 0;

/** @type {String} The latest answer's sentence for an automatic opening, '' when it named no area. */
let autoopenlabel = '';

/** @type {Number|null} The pending search timer. */
let searchtimer = null;

/**
 * @type {Pending|null} Held from a keystroke until the answer it leads to is painted, so that Behat
 * waits through the quiet period, which no request covers yet, and not only through the request.
 */
let searchpending = null;

/** @type {Number|null} The pending view preference timer. */
let viewtimer = null;

/** @type {Number|null} The pending timer of the state to remember. */
let remembertimer = null;

/** @type {String} The last state sent to be remembered, as its arguments' JSON: an unchanged one is not sent again. */
let rememberedsent = '';

/** @type {Number|null} The pending peek timer. */
let peektimer = null;

/** @type {IntersectionObserver|null} The one observer over the cards worth asking progress for. */
let observer = null;

/** @type {Set} Course ids seen in the viewport and not yet sent. */
const pending = new Set();

/** @type {Set} Course ids whose answer has arrived, or failed to. Neither is ever asked again. */
const settled = new Set();

/**
 * @type {Map} Course id to the progress sentence the server gave for it. An id is asked once and
 * once only, but its card can leave and come back - a filter replaces the grid - so the answer
 * outlives the card it was painted on.
 */
const answers = new Map();

/** @type {Boolean} Whether a details request is in flight: one at a time. */
let inflight = false;

/**
 * The element a delegated event happened inside, or null.
 *
 * @param {Event} event The event.
 * @param {String} selector The selector to look for.
 * @return {HTMLElement|null}
 */
const closestOf = (event, selector) => {
    const target = event.target;
    if (!(target instanceof Element)) {
        return null;
    }
    return target.closest(selector);
};

/**
 * One card of this listing by its course id.
 *
 * @param {Number} courseid The course id.
 * @return {HTMLElement|null}
 */
const cardOf = (courseid) => {
    if (!root || !Number.isInteger(courseid)) {
        return null;
    }
    return root.querySelector('[data-region="dim-card"][data-courseid="' + courseid + '"]');
};

/**
 * Whether this page is the root of the course index, whose one criterion is the search.
 *
 * The one place the root's difference is decided: the request carries the term alone, the state
 * keeps nothing else the address may say, and an answer is painted without a course grid.
 *
 * @return {Boolean}
 */
const isRootPage = () => state.categoryid === 0;

/* The shared listing (categorylisting), bound to this page's listing section and state: what this
   module paints and writes into the address is what the visitor's module does, by the same code. */
const rootOnly = () => Listing.rootOnly(state);
const isGrouped = (sort) => Listing.isGrouped(sort);
const pageSizeOf = (params) => Listing.pageSizeOf(params);
const catOf = (params, sort) => Listing.catOf(params, sort);
const periodOf = (params) => Listing.periodOf(params);
const buildUrl = () => Listing.buildUrl(state);
const syncFold = () => Listing.syncFold();
const withPanel = (href) => Listing.withPanel(href);
const writeUrl = (push) => Listing.writeUrl(state, push);
const readUrl = () => Listing.readUrl(state);
const narrows = () => Listing.narrows(state);
const filterArgs = () => Listing.filterArgs(state);
const paintView = (view) => Listing.paintView(root, view);
const markTree = () => Listing.markTree(root, state);
const paintControls = () => Listing.paintControls(root, state);
const paintFavToggle = () => Listing.paintFavToggle(root, state.fav);
const paintFavourites = (response) => Listing.paintFavourites(root, response);
const paintDates = () => Listing.paintDates(root, state);
const paintFilterBadge = (response) => Listing.paintFilterBadge(root, response);
const paintCount = (response) => Listing.paintCount(root, response);
const paintGroups = (response) => Listing.paintGroups(root, response);
const paintChips = (response) => Listing.paintChips(root, response);
const paintEraser = () => Listing.paintEraser(root);
const dropCriterion = (param) => Listing.dropCriterion(state, param);
const renderCards = (cards) => Listing.renderCards(cards);
const restoreMore = (more) => Listing.restoreMore(more);
const paintPager = (response, append, first) => Listing.paintPager(root, response, append, first);
const paintGrid = (grid, items, append) => Listing.paintGrid(root, state, grid, items, append, discard);
const paintDirect = (response) => Listing.paintDirect(root, response);
const armTreebox = () => Listing.armTreebox(root);
const mountCalendar = () => Listing.mountCalendar(root, state, setRange);
const mountLine = () => Listing.mountLine(root, dropTerm);
const tellAnswer = (response) => Listing.tellAnswer(root, response);
const focusTarget = () => Listing.focusTarget(root);

/**
 * The web service's arguments for a page of this request: the term alone at the root.
 *
 * @param {Number} page The zero-based page to fetch.
 * @return {Object}
 */
const rowsArgs = (page) => {
    if (isRootPage()) {
        return {categoryid: 0, query: state.query};
    }
    return {
        categoryid: state.categoryid,
        page: page,
        query: state.query,
        chip: state.chip,
        fav: state.fav,
        perpage: state.pagesize,
        filters: filterArgs(),
        sort: state.sort,
        datefrom: state.datefrom,
        dateto: state.dateto,
        cat: state.cat,
    };
};

/**
 * The state this category is remembered in: the organisation, the view, the state and the favourites, and the fields.
 *
 * The search, the period and the tree's category are not part of it (the server's remembered says why).
 *
 * @return {Object} The remembering service's arguments.
 */
const rememberArgs = () => ({
    categoryid: state.categoryid,
    sort: state.sort,
    view: state.view,
    chip: state.chip,
    fav: state.fav,
    filters: filterArgs(),
});

/**
 * Remember the state the reader is leaving this category in, for a real account on a category page.
 *
 * From the first change on, the reader has a state here, so every address this module writes names
 * its organisation (state.remembered, read by the shared buildUrl()) - set before the caller writes
 * the address, because the bare address now means that state to this reader. The write itself waits
 * for a quiet moment, and sends nothing when the state is the one sent last; a navigation asks for
 * it at once and may wait for it, because the page it loads reads it.
 *
 * @param {Boolean} now Whether to send at once rather than after the quiet.
 * @return {Promise} Settled when the state is sent, or at once when there is nothing to send.
 */
const remember = (now) => {
    if (!state.canpersist || isRootPage()) {
        return Promise.resolve();
    }
    state.remembered = true;
    window.clearTimeout(remembertimer);
    const send = () => {
        const args = rememberArgs();
        const key = JSON.stringify(args);
        if (key === rememberedsent) {
            return Promise.resolve();
        }
        rememberedsent = key;
        return Ajax.call([{methodname: REMEMBER_METHOD, args: args}])[0].catch(Notification.exception);
    };
    if (now) {
        return send();
    }
    remembertimer = window.setTimeout(send, REMEMBER_DEBOUNCE);
    return Promise.resolve();
};

/**
 * Load the address of the state on screen, once the state is remembered or the wait is over.
 *
 * The organisation that changes the shell is a page load: the state it leaves is remembered first,
 * because that page is drawn from it the next time the bare address is asked for, and the address
 * names the organisation either way. A remembering that takes too long, or fails, never holds the
 * reader back.
 *
 * @return {Promise} Settled once the page is asked for.
 */
const leave = async() => {
    await Promise.race([
        remember(true),
        new Promise((resolve) => window.setTimeout(resolve, REMEMBER_WAIT)),
    ]);
    window.location.assign(buildUrl().toString());
};

/**
 * Move to a view: the class, the address, and - for somebody core will store one for - the preference.
 *
 * The view is remembered for this category too, and the person's one preference still follows the
 * last view chosen anywhere, which is what a category with no state of its own opens in.
 *
 * @param {String} view Either cards or list.
 * @return {void}
 */
const setView = (view) => {
    state.view = view;
    state.viewchosen = true;
    paintView(view);
    remember(false);
    writeUrl(true);
    if (!state.canpersist) {
        return;
    }
    window.clearTimeout(viewtimer);
    viewtimer = window.setTimeout(() => {
        setUserPreference(VIEW_PREFERENCE, view).catch(Notification.exception);
    }, VIEW_DEBOUNCE);
};

/**
 * Add the automatic opening's sentence to the count line, once the panel's module has opened the area.
 *
 * The sentence is the answer's own ("Only Brigada has results; its courses are open below."), kept
 * from the last answer because the opening is the panel module's decision and comes after it.
 *
 * @return {void}
 */
const paintAutoOpened = () => {
    const note = root.querySelector(SELECTORS.countnote);
    const text = note ? note.querySelector(SELECTORS.countnotetext) : null;
    if (!text || autoopenlabel === '') {
        return;
    }
    text.textContent = autoopenlabel;
    note.hidden = false;
};

/**
 * Mark ids answered - or failed - and stop watching their cards.
 *
 * Progress does not change while a page is open, so there is nothing to re-check; and a failure
 * that is retried on every scroll hammers a server which has already said no. Unobserving is
 * also what keeps the watch list bounded by the unsettled rows rather than by every row ever
 * appended into the document.
 *
 * @param {Array} ids The course ids of a completed batch.
 * @return {void}
 */
const settle = (ids) => {
    ids.forEach((id) => {
        settled.add(id);
        /* The observer keeps running while a batch is in flight, so a card that left the
           viewport and came back was put back into the pending set after this batch took it
           out. Without this line the very next drain asks for it a second time. */
        pending.delete(id);
        const card = cardOf(id);
        if (card && observer) {
            observer.unobserve(card);
        }
    });
};

/**
 * Give a card a replaced grid is about to discard back to the observer, and forget it was pending.
 *
 * IntersectionObserver holds its targets strongly, so a filter change would otherwise leak every
 * enrolled card it discarded before that card was ever asked about - unbounded in the number of
 * filter changes, which is the one thing a showcase gets many of. The shared listing calls this
 * for each card of the grid before it replaces them ({@see Listing.paintGrid}).
 *
 * @param {HTMLElement} card The card about to leave the grid.
 * @return {void}
 */
const discard = (card) => {
    const courseid = Number(card.dataset.courseid);
    if (observer) {
        observer.unobserve(card);
    }
    pending.delete(courseid);
};

/**
 * Paint the progress sentences of one batch into their cards.
 *
 * @param {Array} details The details the server returned, one entry per id it was willing to answer.
 * @return {void}
 */
const paintProgress = (details) => {
    (details || []).forEach((detail) => {
        const courseid = Number(detail.id);
        if (!detail.progresslabel) {
            return;
        }
        answers.set(courseid, detail.progresslabel);
        paintAnswer(cardOf(courseid), courseid);
    });
};

/**
 * Paint the answer already held for a course onto a card of it, if there is one of each.
 *
 * @param {HTMLElement|null} card The card.
 * @param {Number} courseid The course id.
 * @return {void}
 */
const paintAnswer = (card, courseid) => {
    const label = answers.get(courseid);
    if (!card || !label) {
        return;
    }
    const slot = card.querySelector(SELECTORS.progress);
    if (!slot) {
        return;
    }
    slot.textContent = label;
    slot.hidden = false;
};

/**
 * Send one batch of the pending set, if there is one and nothing else is in flight.
 *
 * @return {Promise} Resolving when the batch has settled, one way or the other.
 */
const drainPending = async() => {
    if (inflight || pending.size === 0) {
        return;
    }
    const batch = Array.from(pending).filter((id) => !settled.has(id)).slice(0, BATCH_SIZE);
    if (!batch.length) {
        return;
    }
    batch.forEach((id) => pending.delete(id));
    inflight = true;
    try {
        const response = await Ajax.call([{
            methodname: 'theme_dimensions_get_card_details',
            args: {courseids: batch},
        }])[0];
        paintProgress(response.details);
    } catch (error) {
        Notification.exception(error);
    } finally {
        settle(batch);
        inflight = false;
    }
};

/**
 * Watch one card for progress, when it is a card progress means anything for.
 *
 * @param {HTMLElement} card The card.
 * @return {void}
 */
const watchCard = (card) => {
    const courseid = Number(card.dataset.courseid);
    if (!observer || card.dataset.state !== 'enrolled' || !Number.isInteger(courseid)) {
        return;
    }
    if (settled.has(courseid)) {
        // Asked already, and never asked twice - but this may be a second card of the same
        // course, drawn after a filter replaced the first one.
        paintAnswer(card, courseid);
        return;
    }
    observer.observe(card);
};

/**
 * Whether the hover peek may open at all right now.
 *
 * @return {Boolean}
 */
const peekable = () => window.matchMedia('(pointer: fine)').matches
    && window.innerWidth >= PEEK_MIN_WIDTH
    && state.view === 'cards';

/**
 * Promote the card's own details panel into a peek beside it.
 *
 * The native disclosure is untouched and stays the authoritative path: this is decoration for a
 * pointer, which is why it is aria-hidden and why nothing in it is reachable by keyboard.
 *
 * @param {HTMLElement} card The card being hovered.
 * @return {void}
 */
const openPeek = (card) => {
    const panel = card.querySelector(SELECTORS.panel);
    const details = card.querySelector(SELECTORS.details);
    if (!panel || (details && details.open)) {
        /* The panel this would preview is already on screen, opened by the reader. A peek here
           would be a second copy of the same text, and closing their disclosure to show one
           would take away what they opened - on a card the pointer never left. */
        return;
    }
    // One panel at a time: a peek that opens closes every disclosure and every other peek.
    closeOthers(null);
    let peek = card.querySelector(SELECTORS.peek);
    if (!peek) {
        peek = document.createElement('div');
        peek.className = 'dim-card__peek';
        peek.setAttribute('aria-hidden', 'true');
        const clone = panel.cloneNode(true);
        while (clone.firstChild) {
            peek.append(clone.firstChild);
        }
        card.append(peek);
    }
    const grid = root.querySelector(SELECTORS.grid);
    const edge = grid ? grid.getBoundingClientRect().right : window.innerWidth;
    peek.classList.toggle('dim-card__peek--flip', card.getBoundingClientRect().right + PEEK_WIDTH > edge);
    card.classList.add('dim-card--peeking');
};

/**
 * Close every open peek.
 *
 * @return {void}
 */
const closePeeks = () => {
    window.clearTimeout(peektimer);
    root.querySelectorAll('.dim-card--peeking').forEach((card) => card.classList.remove('dim-card--peeking'));
};

/**
 * Leave at most one panel of the grid open: the one being opened, and nothing beside it.
 *
 * The disclosure and the hover peek show the same panel. Two panels open at once are two
 * anchored slabs of text over the cards around them, and the reader who pressed the second one
 * did not ask for the first to stay, so every opening closes the rest, whichever of the two ways
 * it was opened.
 *
 * Closing a disclosure fires its own toggle event with open false, which the handler ignores, so
 * this cannot recurse. Setting open false on one already closed is a no-op and fires nothing.
 *
 * @param {HTMLElement|null} keep The disclosure being opened, or null when a peek is opening.
 * @return {void}
 */
const closeOthers = (keep) => {
    root.querySelectorAll(SELECTORS.details).forEach((details) => {
        if (details !== keep) {
            details.open = false;
        }
    });
    closePeeks();
};

/**
 * Attach every per-card behaviour, so a row from page four behaves like a row from page one.
 *
 * @param {Array} cards The cards to arm.
 * @return {void}
 */
const attachCards = (cards) => {
    cards.forEach((card) => {
        watchCard(card);
        card.addEventListener('mouseenter', () => {
            window.clearTimeout(peektimer);
            if (!peekable()) {
                return;
            }
            peektimer = window.setTimeout(() => openPeek(card), PEEK_DELAY);
        });
        card.addEventListener('mouseleave', () => {
            window.clearTimeout(peektimer);
            card.classList.remove('dim-card--peeking');
        });
    });
};

/**
 * Put one response onto the page, unless a newer request has gone out while its cards were rendering.
 *
 * The render is asynchronous, so the latest request is checked again once it resolves: an answer
 * overtaken there would otherwise paint the count line, rewrite the posters and tell the panel's
 * module about a question nobody is asking any more - which can open an area by itself that the
 * newer answer would not, and nothing closes a panel on its own.
 *
 * @param {Object} response The rows response.
 * @param {Boolean} append Whether to append the rows rather than replace them.
 * @param {Number} ticket The request's ticket.
 * @return {Promise} Resolving when the page has been repainted, or at once for an overtaken answer.
 */
const applyResponse = async(response, append, ticket) => {
    const items = await renderCards(response.cards || []);
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
        paintGrid(grid, items, append);
    }
    const notice = root.querySelector(SELECTORS.notice);
    if (notice) {
        notice.hidden = !response.searchtooshort;
    }
    const clear = root.querySelector(SELECTORS.clear);
    if (clear && response.clearurl) {
        clear.href = withPanel(response.clearurl);
    }
    paintFilterBadge(response);
    paintCount(response);
    paintChips(response);
    paintGroups(response);
    paintFavourites(response);
    attachCards(items.map((item) => item.querySelector(SELECTORS.card)).filter((card) => card !== null));
    paintPager(response, append, items.length ? items[0] : null);
    if (!append) {
        paintDirect(response);
        paintShells(response);
    }
    // The line's numbers are the component's to draw: the scope's cells, the star, the Filters badge.
    tellAnswer(response);
};

/**
 * Tell the posters' component an answer that replaced the grid: each area's shell and results, and the
 * page's word on the areas.
 *
 * The shell is what a panel needs before its rows arrive - the call to action's address and its label -
 * and both carry the page's criteria, so a filter changes them. They are composed by the server for every
 * area of the answer and travel in the event's accordion; the component writes them onto the posters,
 * which it alone writes on this shell (stage R3), and then re-asks the panel that is open, which is a
 * listing of the same criteria one level down and would otherwise be the one part of the page still
 * answering the previous question. Nothing here writes into the areas.
 *
 * The event carries the rest of the answer the posters need: each area's results and whether it opens,
 * the page's word on the areas as a whole (the summary's sentences, the reveal, "nothing anywhere", the
 * automatic opening), whether the answer narrows, and its total. This module keeps only the automatic
 * opening's sentence, which it adds to the count line if the component does open the area.
 *
 * It runs on a replaced grid and never on an appended page: an append is more of the same question, and
 * re-asking the open panel for it would be a request per page.
 *
 * @param {Object} response The rows response.
 * @return {void}
 */
const paintShells = (response) => {
    const accordion = response.accordion || [];
    const areas = response.areas || {};
    autoopenlabel = areas.autoopenlabel || '';
    document.dispatchEvent(new CustomEvent(EVENTS.criteria, {detail: {
        accordion: accordion,
        areas: areas,
        filtered: Boolean(areas.filtered),
        total: Number(response.total),
    }}));
};

/**
 * Ask the server for one page, and put it on the screen unless something newer has been asked since.
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
    // A new question for the posters: the panel's module holds their numbers until it is answered.
    if (!append && root.querySelector(SELECTORS.area)) {
        document.dispatchEvent(new CustomEvent(EVENTS.pending, {detail: {filtered: narrows()}}));
    }
    try {
        const response = await Ajax.call([{
            methodname: 'theme_dimensions_get_category_rows',
            args: rowsArgs(page),
        }])[0];
        if (ticket !== seq) {
            return;
        }
        await applyResponse(response, append, ticket);
    } catch (error) {
        // The posters were told a question was out; the latest one failed, so they are told it will not be answered.
        if (ticket === seq && !append && root.querySelector(SELECTORS.area)) {
            document.dispatchEvent(new CustomEvent(EVENTS.failed));
        }
        Notification.exception(error);
    } finally {
        restoreMore(more);
    }
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
    state.page = 0;
    writeUrl(true);
    await requestRows(0, false);
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
 * The module's `pending` is the set of course ids waiting for their progress; this token is core's.
 *
 * @param {HTMLInputElement} search The search box.
 * @return {void}
 */
const onSearchInput = (search) => {
    cancelSearch();
    paintEraser();
    const token = new Pending('theme_dimensions/categoryvitrine:search');
    searchpending = token;
    searchtimer = window.setTimeout(async() => {
        searchpending = null;
        try {
            await onSearch(search.value);
        } finally {
            token.resolve();
        }
    }, SEARCH_DEBOUNCE);
};

/**
 * Deal with an organisation being chosen: a fetch inside one shell, a navigation between two.
 *
 * The grouped organisation draws the posters of the areas, with the panel between their rows, and
 * then the grid of the courses sitting directly in the category; the flat ones draw a grid of the
 * whole subtree alone. Those are different documents, not different rows, so a move between them
 * loads the address rather than fetching into markup that cannot hold the answer - a fetch would
 * leave the posters standing over a flat listing, or paint a grouped page's rows into a page with
 * nowhere to put its areas.
 *
 * Between the two flat orders, and for every filter, it is an ordinary fetch: the shell is the
 * same and only the rows in it change.
 *
 * @param {String} sort The organisation chosen.
 * @return {void}
 */
const onSort = (sort) => {
    if (sort === state.sort) {
        return;
    }
    const was = isGrouped(state.sort);
    state.sort = sort;
    state.page = 0;
    if (was !== isGrouped(sort)) {
        leave();
        return;
    }
    remember(false);
    writeUrl(true);
    requestRows(0, false);
};

/**
 * Deal with a category being pressed in the tree: it is a filter and not a way out of the page.
 *
 * Every entry is a real address of this page carrying the tree's own parameter, so the control
 * works with no JavaScript at all; with this module the press is intercepted and becomes a fetch
 * with a history entry, exactly as a chip or a term is. The id
 * is read off the link rather than out of its address, because the address is the server's
 * spelling and this is the client's question.
 *
 * The page's own entry - the first one - carries the page's category and means "all of it": it
 * is what clears the filter, so it is taken as zero rather than sent as a category the server
 * would drop anyway.
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
    state.page = 0;
    writeUrl(true);
    markTree();
    requestRows(0, false);
};

/**
 * Deal with a quick date option: the server resolved both of its bounds, so nothing is computed.
 *
 * The option stays a real link for a reader without this module, which is why the press is
 * intercepted rather than the markup changed: the same element is an address and a control.
 *
 * @param {HTMLElement} link The quick option pressed.
 * @return {void}
 */
const onQuick = (link) => {
    setRange(link.dataset.from || '', link.dataset.to || '');
};

/**
 * Take a period: onto the state, onto both spellings of the control, into the address, fetched.
 *
 * Either bound may be empty. The pair is read through the calendar's own reading of a period, so
 * whatever hands one over - the calendar or a quick option's link - the state holds what the
 * server would.
 *
 * @param {String} datefrom The first day, YYYY-MM-DD, or the empty string.
 * @param {String} dateto The last day, YYYY-MM-DD, or the empty string.
 * @return {void}
 */
const setRange = (datefrom, dateto) => {
    const period = DateRange.period(datefrom, dateto);
    state.datefrom = period.from;
    state.dateto = period.to;
    state.page = 0;
    paintDates();
    writeUrl(true);
    requestRows(0, false);
};

/**
 * Deal with a chip or a facet being chosen.
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
    if (name === PARAM.chip) {
        state.chip = input.value;
    } else if (name && name.startsWith(PARAM.fieldprefix)) {
        const fieldid = name.slice(PARAM.fieldprefix.length);
        if (Number(input.value) > 0) {
            state.filters[fieldid] = Number(input.value);
        } else {
            delete state.filters[fieldid];
        }
    } else {
        return;
    }
    state.page = 0;
    remember(false);
    writeUrl(true);
    requestRows(0, false);
};

/**
 * Deal with the favourites toggle being pressed: a filter that combines with the state chip.
 *
 * The toggle is a submit button for a reader without this module, posting what a press turns it
 * to; here the press is a fetch with a history entry, exactly as a chip is, and the toggle is
 * repainted at once rather than after the answer, because aria-pressed is what was pressed.
 *
 * @return {void}
 */
const onFav = () => {
    state.fav = !state.fav;
    state.page = 0;
    paintFavToggle();
    remember(false);
    writeUrl(true);
    requestRows(0, false);
};

/**
 * Clear every filter in place, and give the focus to the search box.
 *
 * The three controls that clear - the count row's link, the areas' "nothing matches" box and the
 * empty listing's link - are real addresses for a reader without this module (the server's
 * keep_params(): the organisation and the open panel stay). With it, the state is emptied the way
 * the server would empty it, the controls are repainted from it and the page is asked again, and
 * the panel's own parameters ride along in the address. The focus moves to the search box because
 * the control that was pressed is about to hide itself, and a focus left on it would fall to the
 * top of the document.
 *
 * @return {void}
 */
const onClear = () => {
    cancelSearch();
    state.query = '';
    state.chip = '';
    state.fav = false;
    state.filters = {};
    state.datefrom = '';
    state.dateto = '';
    state.cat = 0;
    state.page = 0;
    const notice = root.querySelector(SELECTORS.notice);
    if (notice) {
        notice.hidden = true;
    }
    paintControls();
    // Clearing the filters clears the remembered ones of this category too.
    remember(false);
    writeUrl(true);
    requestRows(0, false);
    // The field, or its icon where the line folded it away: a folded field cannot take the focus.
    const search = focusTarget();
    if (search) {
        search.focus({preventScroll: true});
    }
};

/**
 * Drop the one criterion a chip of the row under the filter line names, in place.
 *
 * The chip is a real address for a reader without this module (the server's, with that one
 * criterion dropped and everything else kept); here the state drops the same criterion, the
 * controls are repainted from it and the page is asked again, as any filter press does. The focus
 * goes to the search box because the chip pressed is about to leave the row.
 *
 * @param {HTMLElement} chip The chip pressed.
 * @return {Boolean} Whether the chip was handled; a chip naming nothing this state holds is left to its link.
 */
const onChipRemove = (chip) => {
    if (!dropCriterion(chip.dataset.param || '')) {
        return false;
    }
    paintControls();
    remember(false);
    writeUrl(true);
    requestRows(0, false);
    const search = focusTarget();
    if (search) {
        search.focus({preventScroll: true});
    }
    return true;
};

/**
 * Take the typed term out of the state, for a suggestion of the filter line about to apply its filter.
 *
 * The filter line empties the field and then presses the suggestion's control, whose handler here
 * makes the one request: with the term gone from the state first, that request carries none, and a
 * term still waiting for its debounce is never sent after it.
 *
 * @return {void}
 */
const dropTerm = () => {
    cancelSearch();
    state.query = '';
    const notice = root.querySelector(SELECTORS.notice);
    if (notice) {
        notice.hidden = true;
    }
    paintEraser();
};

/**
 * Empty the search box with its eraser, search again for nothing, and keep the focus in the box.
 *
 * @return {Promise} Resolving when the answer has been painted.
 */
const onErase = async() => {
    cancelSearch();
    const search = root.querySelector(SELECTORS.search);
    // The component's eraser has emptied its own field and kept the focus in it; this module writes into neither.
    if (search && !search.closest(SELECTORS.drawnline)) {
        search.value = '';
        search.focus({preventScroll: true});
    }
    paintEraser();
    await onSearch('');
};

/**
 * Wire every listener of the listing.
 *
 * @return {void}
 */
const listen = () => {
    const form = root.querySelector(SELECTORS.toolbar);
    if (form) {
        // With the module running, every control of the toolbar is asynchronous; a submit would
        // reload the page and throw away the history entry this module just wrote.
        form.addEventListener('submit', (event) => event.preventDefault());
        // Delegated: the line's component replaces the server's field with its own.
        form.addEventListener('input', (event) => {
            const search = closestOf(event, SELECTORS.search);
            if (search) {
                onSearchInput(search);
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
        const clear = closestOf(event, SELECTORS.clearaction);
        if (clear) {
            // A real address for a reader without this module; the filters cleared in place here.
            event.preventDefault();
            onClear();
            return;
        }
        const chip = closestOf(event, SELECTORS.chipremove);
        if (chip && onChipRemove(chip)) {
            // A real address for a reader without this module; that one criterion dropped in place here.
            event.preventDefault();
            return;
        }
        if (closestOf(event, SELECTORS.eraser)) {
            event.preventDefault();
            onErase().catch(Notification.exception);
            return;
        }
        const quick = closestOf(event, SELECTORS.quick);
        if (quick) {
            event.preventDefault();
            onQuick(quick);
            return;
        }
        const view = closestOf(event, SELECTORS.viewbutton);
        if (view) {
            event.preventDefault();
            setView(view.value === 'list' ? 'list' : 'cards');
            return;
        }
        if (closestOf(event, SELECTORS.fav)) {
            // A submit button for a reader without this module; a fetch with a history entry here.
            event.preventDefault();
            onFav();
            return;
        }
        const branch = closestOf(event, SELECTORS.treelink);
        if (branch) {
            // A real address for a reader without this module; a fetch with a history entry here.
            event.preventDefault();
            onTree(branch);
            return;
        }
        const more = closestOf(event, SELECTORS.loadmore);
        if (more) {
            event.preventDefault();
            requestRows(lastpage + 1, true);
        }
    });

    /* In the capture phase: a details element's toggle event does not bubble, so a listener on
       the listing only ever hears it on the way down. Delegated rather than bound per card,
       because a card appended by the load-more path has to behave like a card the server drew. */
    root.addEventListener('toggle', (event) => {
        const details = event.target;
        if (!(details instanceof HTMLDetailsElement) || !details.open) {
            return;
        }
        if (details.matches(SELECTORS.details)) {
            closeOthers(details);
        }
    }, true);

    window.addEventListener('popstate', () => {
        readUrl();
        paintControls();
        syncFold();
        // Back and forward land on a state of this category, which is then the one it is left in.
        remember(false);
        requestRows(0, false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            // Whatever is open: the disclosure the reader pressed as well as the pointer's peek.
            closeOthers(null);
        }
    });

    // The panel's module opened the one area holding every result: the count line says so.
    document.addEventListener(EVENTS.autoopened, paintAutoOpened);
};

/**
 * Arm the category showcase.
 *
 * @return {void}
 */
export const init = () => {
    root = document.querySelector(SELECTORS.root);
    if (!root) {
        return;
    }
    let payload = {};
    try {
        payload = JSON.parse(root.dataset.vitrine || '{}');
    } catch (error) {
        payload = {};
    }
    const params = new URL(window.location.href).searchParams;
    const period = periodOf(params);
    const defaultsort = typeof payload.defaultsort === 'string' ? payload.defaultsort : '';
    /* A reader who left a state on this category may have arrived on its bare address, which the
       server drew with that state: the organisation is then the payload's, not the address's. */
    const isremembered = payload.remembered === true;
    const sort = isremembered && typeof payload.sort === 'string' ? payload.sort : Listing.sortOf(params, defaultsort);
    state = {
        categoryid: Number(payload.categoryid) || Number(root.dataset.categoryid) || 0,
        page: Number(payload.page) || 0,
        total: Number(payload.total) || 0,
        hasmore: Boolean(payload.hasmore),
        query: typeof payload.query === 'string' ? payload.query : '',
        chip: typeof payload.chip === 'string' ? payload.chip : '',
        // The server read an older address's favourites chip as the toggle already.
        fav: payload.fav === true,
        filters: payload.filters && typeof payload.filters === 'object' ? Object.assign({}, payload.filters) : {},
        /* The organisation and the period come from the address and not from the payload, which
           is where the server put them in the first place: the toolbar is a GET form, so the
           page on screen is the page that address asks for. An address naming no organisation
           gets the category's default, which the payload carries and criteria resolves alike;
           the empty string is the theme's default, the grouped organisation. */
        defaultsort: defaultsort,
        sort: sort,
        // Whether every address this module writes names its organisation: see remember().
        remembered: isremembered,
        datefrom: period.from,
        dateto: period.to,
        /* And so does the tree's category, for the same reason: it is a filter, the toolbar is a
           GET form, and the page on screen is the page that address asks for. */
        cat: catOf(params, sort),
        view: payload.view === 'list' ? 'list' : 'cards',
        /* Whether the view in the address bar was put there - by the toggle or by the address the
           reader arrived on: only a chosen view is written back into it. */
        viewchosen: params.has(PARAM.view),
        pagesize: pageSizeOf(params),
        canpersist: Boolean(payload.canpersist),
    };
    lastpage = state.page;
    // The root is armed for its search line; a root with nothing to search draws none.
    if (isRootPage() && !root.querySelector(SELECTORS.toolbar)) {
        return;
    }
    rootOnly();

    // This is what hides the submit only a reader without this module needs.
    document.body.classList.add(BODY_CLASS);

    observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            const courseid = Number(entry.target.dataset.courseid);
            if (!Number.isInteger(courseid)) {
                return;
            }
            if (entry.isIntersecting) {
                pending.add(courseid);
            } else {
                pending.delete(courseid);
            }
        });
    }, {rootMargin: OBSERVER_MARGIN});
    window.setInterval(drainPending, BATCH_INTERVAL);

    /* A course card inside the panel between the posters is the panel module's: it is a smaller
       presentation of the card with its disclosure out of reach, so it is never peeked and its
       progress is asked by that module, in one batch with the panel's rows. */
    attachCards(Array.from(root.querySelectorAll(SELECTORS.card)).filter((card) => !card.closest(SELECTORS.areapanel)));

    // The searched term in the titles of the server's own rows; the panel's are the panel module's.
    CategoryMark.mark(root.querySelector(SELECTORS.grid), state.query);

    // The star of every card on the page, the panel's included: one listener, armed once.
    CategoryStar.init();

    /* The filter line's component (stage R2) first: the mount is the line itself, put back where it stands,
       and the calendar's node goes inside it afterwards, so the observer never sees the calendar leave. */
    mountLine();

    // The one calendar of the design, over the two native fields. Nothing here waits on it: a
    // mount that cannot happen leaves those fields exactly as the server drew them.
    mountCalendar().catch(Notification.exception);

    // The numbered bar is the reader-without-this-module's pager and is now the button's job.
    // The button and the continue link were drawn by the server for this very state, so nothing
    // else in the region is touched until a response arrives.
    const bar = root.querySelector(SELECTORS.pagerbar);
    if (bar) {
        bar.hidden = true;
    }

    // The tree is a disclosure on a phone and a list at every width above it.
    armTreebox();
    listen();

    /* The page was drawn with the reader's remembered state wherever the address named none: the
       address is written out in full now, so a reload, Back or a copied address names what is on
       screen rather than meaning that state only to this reader. What was drawn is what is
       remembered, so it is not sent again. */
    if (state.remembered) {
        rememberedsent = JSON.stringify(rememberArgs());
        writeUrl(false);
    }
};
