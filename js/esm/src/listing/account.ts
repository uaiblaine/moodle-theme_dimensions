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
 * The logged-in shell of the showcase listing: what only an account has, over the shared store.
 *
 * The listing's and a panel's rows, the progress of the enrolled courses on screen (once per course), the state
 * remembered per category and the view preference, the hover peek, the automatic opening's note and the dim-js mark;
 * a failure is core's exception dialogue; it arms the card's star (./star). Armed by amd/src/listingaccount.js for a
 * real account only, never the guest (course_renderer.php); the visitor's page never loads it
 * (categorypublic_source_test).
 *
 * @module     theme_dimensions/listing/account
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import Fetch from '@moodle/lms/core/fetch';
import {redirect} from '@moodle/lms/core/location';
import {load} from '../amd';
import {period as calendarPeriod} from '../daterange/calendar';
import {LISTING, SELECTORS} from './events';
import * as Listing from './listing';
import {arm as armStar} from './star';
import {current, init} from './store';
import type {Rows, Shell} from './store';

/** Hooks the account's shell reads beside the shared ones. */
const HOOKS = {
    details: '[data-region="dim-card-details"]',
    panel: '[data-region="dim-card-panel"]',
    peek: '.dim-card__peek',
    progress: '[data-region="dim-card-progress"]',
    areapanel: '[data-region="dim-panel"]',
    countnotetext: '[data-region="dim-vitrine-countnotetext"]',
};

/** The listing's service, for a page and for a panel's rows. */
const ROWS_METHOD = 'theme_dimensions_get_category_rows';

/** The progress of the enrolled courses on screen. */
const DETAILS_METHOD = 'theme_dimensions_get_card_details';

/** The service that remembers the state the reader left this category in. */
const REMEMBER_METHOD = 'theme_dimensions_remember_category_state';

/** The preference holding the view every category without a state of its own opens in. */
const VIEW_PREFERENCE = 'theme_dimensions_categoryview';

/** The body class which hides the no-JavaScript submit and shows what only an account drives. */
const BODY_CLASS = 'dim-js';

/** Milliseconds of quiet before a changed state is remembered. */
const REMEMBER_DEBOUNCE = 500;

/** The longest a navigation waits for the state it leaves behind to be remembered. */
const REMEMBER_WAIT = 1000;

/** Milliseconds of quiet before the view preference is written. */
const VIEW_DEBOUNCE = 500;

/** How many course ids one details request may carry: enrichment::MAX_COURSES, which the server enforces. */
const BATCH_SIZE = 24;

/** Milliseconds between drains of the pending set: a fixed interval, so a continuous scroll still sends. */
const BATCH_INTERVAL = 100;

/** How far outside the viewport a card counts as worth asking about. */
const OBSERVER_MARGIN = '200px';

/** Milliseconds of hover before the peek panel is promoted. */
const PEEK_DELAY = 180;

/** The peek panel's own width plus its gap, for deciding which side it opens on. */
const PEEK_WIDTH = 340;

/** Below this viewport width the peek never opens: the panel renders inline there. */
const PEEK_MIN_WIDTH = 576;

/** How many courses a panel lists: the server's population::PANEL_SIZE. */
const PANEL_SIZE = 8;

/** The value the state chip carried for the favourites before they were a toggle. */
const LEGACY_FAVOURITES = 'favourites';

/** The one order a panel can be asked for besides its default. */
const PSORT_NAME = 'name';

/** The two flat orders of the web service a panel is listed in. */
const SORT = {
    recent: 'recent',
    name: 'name',
};

/** What changes leave the remembered state alone: the search, the tree and the period are not part of it. */
const UNREMEMBERED = ['term', 'tree', 'period'];

/** The listing section. */
let root: HTMLElement;

/** Whether core stores a preference and a remembered state for this reader: a real account. */
let canpersist = false;

/** The latest answer's sentence for an automatic opening, '' when it named no area. */
let autoopenlabel = '';

/** The pending view preference timer. */
let viewtimer: number | undefined;

/** The pending timer of the state to remember. */
let remembertimer: number | undefined;

/** The last state sent to be remembered, as its arguments' JSON: an unchanged one is not sent again. */
let rememberedsent = '';

/** The pending peek timer. */
let peektimer: number | undefined;

/** The one observer over the cards worth asking progress for. */
let observer: IntersectionObserver | null = null;

/** Course ids seen in the viewport and not yet sent. */
const pending = new Set<number>();

/** Course ids whose answer has arrived, or failed to. Neither is ever asked again. */
const settled = new Set<number>();

/** Course id to the progress sentence the server gave for it: an id is asked once, and its card can come back. */
const answers = new Map<number, string>();

/** Whether a details request is in flight: one at a time. */
let inflight = false;

/**
 * Show core's exception dialogue for a failure.
 *
 * @param {object} error The failure.
 * @returns {Promise} Resolving once shown.
 */
const exception = async(error: unknown): Promise<void> => {
    const Notification = await load('core/notification');
    await Notification.exception(error);
};

/**
 * Whether this page is the root of the course index, whose one criterion is the search.
 *
 * @returns {boolean}
 */
const isRootPage = (): boolean => Listing.isRoot(current());

/**
 * The web service's arguments for a page of this request: the term alone at the root.
 *
 * @param {number} page The zero-based page to fetch.
 * @returns {object}
 */
const rowsArgs = (page: number): Record<string, unknown> => {
    const state = current();
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
        filters: Listing.filterArgs(state),
        sort: state.sort,
        datefrom: state.datefrom,
        dateto: state.dateto,
        cat: state.cat,
    };
};

/**
 * Ask the listing's service for a page. Every failure is shown, an overtaken one's too; the store paints only
 * the latest answer.
 *
 * @param {number} page The zero-based page to fetch.
 * @returns {Promise} The answer.
 */
const rows = (page: number): Promise<Rows> => {
    const answer = fetchOne<Rows>({
        methodname: ROWS_METHOD,
        args: rowsArgs(page),
    });
    answer.catch(exception);
    return answer;
};

/**
 * The state this category is remembered in: the organisation, the view, the state and the favourites, and the
 * fields. The search, the period and the tree's category are not part of it (the server's remembered says why).
 *
 * @returns {object} The remembering service's arguments.
 */
const rememberArgs = (): Record<string, unknown> => {
    const state = current();
    return {
        categoryid: state.categoryid,
        sort: state.sort,
        view: state.view,
        chip: state.chip,
        fav: state.fav,
        filters: Listing.filterArgs(state),
    };
};

/**
 * Remember the state the reader is leaving this category in, for a real account on a category page.
 *
 * From the first change on, the reader has a state here, so every address the store writes names its
 * organisation (state.remembered, read by the shared buildUrl()) - set before the store writes the address,
 * because the bare address now means that state to this reader. The write waits for a quiet moment and sends
 * nothing when the state is the one sent last; a navigation asks for it at once and may wait for it.
 *
 * @param {boolean} now Whether to send at once rather than after the quiet.
 * @returns {Promise} Settled when the state is sent, or at once when there is nothing to send.
 */
const remember = (now: boolean): Promise<unknown> => {
    if (!canpersist || isRootPage()) {
        return Promise.resolve();
    }
    current().remembered = true;
    window.clearTimeout(remembertimer);
    /**
     * Send the state, unless it is the one sent last.
     *
     * @returns {Promise}
     */
    const send = (): Promise<unknown> => {
        const args = rememberArgs();
        const key = JSON.stringify(args);
        if (key === rememberedsent) {
            return Promise.resolve();
        }
        rememberedsent = key;
        return fetchOne({methodname: REMEMBER_METHOD, args: args}).catch(exception);
    };
    if (now) {
        return send();
    }
    remembertimer = window.setTimeout(send, REMEMBER_DEBOUNCE);
    return Promise.resolve();
};

/**
 * Load the address of the state on screen, once the state is remembered or the wait is over: the organisation
 * that changes the shell is a page load, drawn from that state the next time the bare address is asked for.
 *
 * @returns {Promise} Settled once the page is asked for.
 */
const leave = async(): Promise<void> => {
    await Promise.race([
        remember(true),
        new Promise((resolve) => window.setTimeout(resolve, REMEMBER_WAIT)),
    ]);
    redirect(Listing.buildUrl(current()).toString());
};

/**
 * Hear a change before the store writes the address: remember it, and the view as the person's preference too.
 *
 * @param {string} kind What changed: term, tree, period, filter, view or history.
 * @returns {void}
 */
const changed = (kind: string): void => {
    if (UNREMEMBERED.indexOf(kind) >= 0) {
        return;
    }
    remember(false);
    if (kind !== 'view' || !canpersist) {
        return;
    }
    const view = current().view;
    window.clearTimeout(viewtimer);
    viewtimer = window.setTimeout(() => {
        Fetch.performPost('core_user', `current/preferences/${VIEW_PREFERENCE}`, {body: {value: view}}).catch(exception);
    }, VIEW_DEBOUNCE);
};

/**
 * Add the automatic opening's sentence to the count line, once the posters' component has opened the area.
 *
 * @returns {void}
 */
const paintAutoOpened = (): void => {
    const note = root.querySelector<HTMLElement>(SELECTORS.countnote);
    const text = note ? note.querySelector(HOOKS.countnotetext) : null;
    if (!note || !text || autoopenlabel === '') {
        return;
    }
    text.textContent = autoopenlabel;
    note.hidden = false;
};

/**
 * One card of this listing by its course id.
 *
 * @param {number} courseid The course id.
 * @returns {HTMLElement|null}
 */
const cardOf = (courseid: number): HTMLElement | null => {
    if (!Number.isInteger(courseid)) {
        return null;
    }
    return root.querySelector<HTMLElement>('[data-region="dim-card"][data-courseid="' + courseid + '"]');
};

/**
 * Mark ids answered - or failed - and stop watching their cards: progress does not change while a page is open,
 * and a failure retried on every scroll hammers a server which has already said no.
 *
 * @param {Array} ids The course ids of a completed batch.
 * @returns {void}
 */
const settle = (ids: number[]): void => {
    ids.forEach((id) => {
        settled.add(id);
        /* The observer keeps running while a batch is in flight, so a card that left the viewport and came back
           was put back into the pending set after this batch took it out. */
        pending.delete(id);
        const card = cardOf(id);
        if (card && observer) {
            observer.unobserve(card);
        }
    });
};

/**
 * Give a card a replaced grid is about to discard back to the observer, and forget it was pending:
 * IntersectionObserver holds its targets strongly, so every filter change would otherwise leak its cards.
 *
 * @param {HTMLElement} card The card about to leave the grid.
 * @returns {void}
 */
const discard = (card: HTMLElement): void => {
    const courseid = Number(card.dataset.courseid);
    if (observer) {
        observer.unobserve(card);
    }
    pending.delete(courseid);
};

/**
 * Paint the answer already held for a course onto a card of it, if there is one of each.
 *
 * @param {HTMLElement|null} card The card.
 * @param {number} courseid The course id.
 * @returns {void}
 */
const paintAnswer = (card: HTMLElement | null, courseid: number): void => {
    const label = answers.get(courseid);
    if (!card || !label) {
        return;
    }
    const slot = card.querySelector<HTMLElement>(HOOKS.progress);
    if (!slot) {
        return;
    }
    slot.textContent = label;
    slot.hidden = false;
};

/**
 * Paint the progress sentences of one batch into their cards.
 *
 * @param {Array} details The details the server returned, one entry per id it was willing to answer.
 * @returns {void}
 */
const paintProgress = (details: Array<{id: number | string, progresslabel?: string}>): void => {
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
 * Send one batch of the pending set, if there is one and nothing else is in flight.
 *
 * @returns {Promise} Resolving when the batch has settled, one way or the other.
 */
const drainPending = async(): Promise<void> => {
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
        const response = await fetchOne<{details: Array<{id: number, progresslabel?: string}>}>({
            methodname: DETAILS_METHOD,
            args: {courseids: batch},
        });
        paintProgress(response.details);
    } catch (error) {
        exception(error);
    } finally {
        settle(batch);
        inflight = false;
    }
};

/**
 * Watch one card for progress, when it is a card progress means anything for.
 *
 * @param {HTMLElement} card The card.
 * @returns {void}
 */
const watchCard = (card: HTMLElement): void => {
    const courseid = Number(card.dataset.courseid);
    if (!observer || card.dataset.state !== 'enrolled' || !Number.isInteger(courseid)) {
        return;
    }
    if (settled.has(courseid)) {
        // Asked already, and never asked twice - but this may be a second card of the same course.
        paintAnswer(card, courseid);
        return;
    }
    observer.observe(card);
};

/**
 * Whether the hover peek may open at all right now.
 *
 * @returns {boolean}
 */
const peekable = (): boolean => window.matchMedia('(pointer: fine)').matches
    && window.innerWidth >= PEEK_MIN_WIDTH
    && current().view === 'cards';

/**
 * Close every open peek.
 *
 * @returns {void}
 */
const closePeeks = (): void => {
    window.clearTimeout(peektimer);
    root.querySelectorAll('.dim-card--peeking').forEach((card) => card.classList.remove('dim-card--peeking'));
};

/**
 * Leave at most one panel of the grid open: the one being opened, and nothing beside it, whichever of the two ways
 * it was opened. Closing a disclosure fires its own toggle with open false, which the listener ignores.
 *
 * @param {HTMLDetailsElement|null} keep The disclosure being opened, or null when a peek is opening.
 * @returns {void}
 */
const closeOthers = (keep: HTMLDetailsElement | null): void => {
    root.querySelectorAll<HTMLDetailsElement>(HOOKS.details).forEach((details) => {
        if (details !== keep) {
            details.open = false;
        }
    });
    closePeeks();
};

/**
 * Promote the card's own details panel into a peek beside it: decoration for a pointer, aria-hidden, while the
 * native disclosure stays the authoritative path.
 *
 * @param {HTMLElement} card The card being hovered.
 * @returns {void}
 */
const openPeek = (card: HTMLElement): void => {
    const panel = card.querySelector(HOOKS.panel);
    const details = card.querySelector<HTMLDetailsElement>(HOOKS.details);
    if (!panel || (details && details.open)) {
        /* The panel this would preview is already on screen, opened by the reader: closing their disclosure to
           show a second copy would take away what they opened, on a card the pointer never left. */
        return;
    }
    // One panel at a time: a peek that opens closes every disclosure and every other peek.
    closeOthers(null);
    let peek = card.querySelector(HOOKS.peek);
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
 * Attach every per-card behaviour, so a row from page four behaves like a row from page one.
 *
 * @param {Array} cards The cards to arm.
 * @returns {void}
 */
const attachCards = (cards: HTMLElement[]): void => {
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
 * The cards an answer just drew, armed.
 *
 * @param {Array} items The list items just inserted.
 * @returns {void}
 */
const painted = (items: HTMLElement[]): void => {
    const cards = items.map((item) => item.querySelector<HTMLElement>(SELECTORS.card));
    attachCards(cards.filter((card): card is HTMLElement => card !== null));
};

/**
 * The latest request failed: the posters were told a question was out, so they are told it will not be answered.
 * The dialogue is the request's own ({@see rows}).
 *
 * @param {number} page The zero-based page it asked for.
 * @param {boolean} append Whether it was an append.
 * @returns {void}
 */
const failed = (page: number, append: boolean): void => {
    if (!append && root.querySelector(SELECTORS.area)) {
        document.dispatchEvent(new CustomEvent(LISTING.failed));
    }
};

/**
 * Keep an answer's sentence for an automatic opening, which the posters' component may then make.
 *
 * @param {object} response The rows response.
 * @returns {void}
 */
const answered = (response: Rows): void => {
    const word = response.areas || {};
    autoopenlabel = typeof word.autoopenlabel === 'string' ? word.autoopenlabel : '';
};

/**
 * The web service's arguments for an area's panel: its subtree, eight courses, the page's criteria read off the
 * address. The tree's own category is never sent (the service refuses one not below the area), the period is read
 * as the calendar reads one, and the root reads its search and no filter.
 *
 * @param {number} id The area.
 * @param {string} psort The panel's order: the empty string (the newest first) or name.
 * @returns {object}
 */
const panelArgs = (id: number, psort: string): Record<string, unknown> => {
    const address = new URL(window.location.href).searchParams;
    let params = address;
    if (isRootPage()) {
        params = new URLSearchParams();
        if (address.has(Listing.PARAM.query)) {
            params.set(Listing.PARAM.query, address.get(Listing.PARAM.query) || '');
        }
    }
    const filters: Array<{fieldid: number, value: number}> = [];
    params.forEach((value, name) => {
        if (name.startsWith(Listing.PARAM.fieldprefix) && Number(value) > 0) {
            filters.push({fieldid: Number(name.slice(Listing.PARAM.fieldprefix.length)), value: Number(value)});
        }
    });
    const range = calendarPeriod(params.get(Listing.PARAM.from), params.get(Listing.PARAM.to));
    const chip = params.get(Listing.PARAM.chip) || '';
    return {
        categoryid: id,
        page: 0,
        perpage: PANEL_SIZE,
        sort: psort === PSORT_NAME ? SORT.name : SORT.recent,
        query: params.get(Listing.PARAM.query) || '',
        chip: chip === LEGACY_FAVOURITES ? '' : chip,
        fav: params.get(Listing.PARAM.fav) === '1' || chip === LEGACY_FAVOURITES,
        filters: filters,
        datefrom: range.from,
        dateto: range.to,
        withcategories: true,
    };
};

/**
 * Ask the listing's web service for an area's rows: the posters' component's question.
 *
 * @param {HTMLElement} poster The area's poster.
 * @param {string} psort The panel's order.
 * @returns {Promise} The answer, its panel's context included.
 */
const ask = (poster: HTMLElement, psort: string): Promise<Rows> => {
    const id = Number(poster.dataset.categoryid);
    return fetchOne<Rows>({
        methodname: ROWS_METHOD,
        args: panelArgs(id, psort),
    });
};

/**
 * Paint the progress of the panel's enrolled courses, in one batch of at most eight, once the rows are on screen.
 * A failure leaves the cards as they are: progress is decoration.
 *
 * @param {HTMLElement} panel The panel's element.
 * @returns {Promise} Resolving when the batch has been dealt with.
 */
const paintPanelProgress = async(panel: HTMLElement): Promise<void> => {
    const cards = Array.from(panel.querySelectorAll<HTMLElement>(SELECTORS.card))
        .filter((card) => card.dataset.state === 'enrolled');
    const ids = cards.map((card) => Number(card.dataset.courseid)).filter((id) => Number.isInteger(id) && id > 0);
    if (!ids.length) {
        return;
    }
    try {
        const response = await fetchOne<{details: Array<{id: number, progress?: number, progresslabel?: string}>}>({
            methodname: DETAILS_METHOD,
            args: {courseids: ids.slice(0, PANEL_SIZE)},
        });
        (response.details || []).forEach((detail) => {
            const card = cards.find((one) => Number(one.dataset.courseid) === Number(detail.id));
            const slot = card ? card.querySelector<HTMLElement>(HOOKS.progress) : null;
            if (!slot || !detail.progresslabel) {
                return;
            }
            slot.textContent = detail.progresslabel;
            slot.style.setProperty('--dim-progress', Number(detail.progress || 0) + '%');
            slot.hidden = false;
        });
    } catch (error) {
        // Progress is decoration: a card without it is still the card the server drew.
    }
};

/**
 * Wire what only an account's listing hears: the one open panel, Escape, and the automatic opening.
 *
 * @returns {void}
 */
const listen = (): void => {
    /* In the capture phase: a details element's toggle event does not bubble, so a listener on the listing only
       ever hears it on the way down; delegated, so a card appended by load more behaves like one the server drew. */
    root.addEventListener('toggle', (event) => {
        const details = event.target;
        if (!(details instanceof HTMLDetailsElement) || !details.open) {
            return;
        }
        if (details.matches(HOOKS.details)) {
            closeOthers(details);
        }
    }, true);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            // Whatever is open: the disclosure the reader pressed as well as the pointer's peek.
            closeOthers(null);
        }
    });
    // The posters' component opened the one area holding every result: the count line says so.
    document.addEventListener(LISTING.autoopened, paintAutoOpened);
};

/**
 * What the shell does once an address is written: nothing, its state is the address's.
 *
 * @returns {void}
 */
const nothing = (): void => undefined;

/** The logged-in shell for the store. */
const SHELL: Shell = {
    mark: BODY_CLASS,
    normalise: Listing.rootOnly,
    rows: rows,
    failed: failed,
    changed: changed,
    written: nothing,
    leave: leave,
    discard: discard,
    painted: painted,
    answered: answered,
    // A calendar that could not mount is core's exception dialogue, as the AMD client it replaced had it.
    unmounted: exception,
    areas: {
        props: {},
        client: {
            ask: ask,
            painted: paintPanelProgress,
        },
    },
};

/**
 * Arm the category showcase for a real account.
 *
 * @returns {void}
 */
export const start = (): void => {
    // First, whatever the page draws: the root's directory has no listing to arm and its panels' cards have stars.
    armStar();
    const found = document.querySelector<HTMLElement>(SELECTORS.root);
    if (!found) {
        return;
    }
    root = found;
    let payload: Record<string, unknown> = {};
    try {
        payload = JSON.parse(root.dataset.vitrine || '{}');
    } catch (error) {
        payload = {};
    }
    canpersist = Boolean(payload.canpersist);
    if (!init(SHELL, Number(payload.categoryid) || Number(root.dataset.categoryid) || 0)) {
        return;
    }
    observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            const courseid = Number((entry.target as HTMLElement).dataset.courseid);
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

    /* A course card inside the panel between the posters is a smaller presentation with its disclosure out of reach:
       it is never peeked, and its progress is asked in one batch with the panel's rows. */
    attachCards(Array.from(root.querySelectorAll<HTMLElement>(SELECTORS.card)).filter((card) => !card.closest(HOOKS.areapanel)));
    listen();

    /* The page was drawn with the reader's remembered state wherever the address named none: the address is written
       out in full now, so a reload, Back or a copied address names what is on screen. What was drawn is what is
       remembered, so it is not sent again. */
    if (current().remembered) {
        rememberedsent = JSON.stringify(rememberArgs());
        Listing.writeUrl(current(), false);
    }
};
