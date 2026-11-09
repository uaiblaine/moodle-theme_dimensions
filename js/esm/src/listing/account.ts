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
 * The listing's and a panel's rows, the progress and completion of the enrolled courses on screen (once per course), the state
 * remembered per category and the view preference, the automatic opening's note and the dim-js mark;
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
    progress: '[data-region="dim-card-progress"]',
    pct: '[data-region="dim-card-pct"]',
    pill: '[data-region="dim-card-pill"]',
    cta: '[data-region="dim-card-cta"]',
    areapanel: '[data-region="dim-panel"]',
    countnotetext: '[data-region="dim-vitrine-countnotetext"]',
};

/** The listing's service, for a page and for a panel's rows. */
const ROWS_METHOD = 'theme_dimensions_get_category_rows';

/** The progress of the enrolled courses on screen, and whether the viewer completed them. */
const DETAILS_METHOD = 'theme_dimensions_get_card_details';

/** What the details service answers for one course. */
type Detail = {
    id: number | string,
    completed?: boolean,
    progress?: number | null,
    progresslabel?: string,
    progresspct?: string,
};

/** The card's mark once its course is completed: the stylesheet takes the bar away and greens the cover's base. */
const DONE_CLASS = 'dim-card--done';

/** The state pill's own classes once it is the completed seal, and its icon. */
const DONE_PILL = 'dim-badge--done';
const DONE_ICON = 'fa fa-check';

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

/** The one observer over the cards worth asking progress for. */
let observer: IntersectionObserver | null = null;

/** Course ids seen in the viewport and not yet sent. */
const pending = new Set<number>();

/** Course ids whose answer has arrived, or failed to. Neither is ever asked again. */
const settled = new Set<number>();

/** Course id to the details the server gave for it: an id is asked once, and its card can come back. */
const answers = new Map<number, Detail>();

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
 * Turn an enrolled card into the completed one (D4, option A): the seal for the pill, "Review" for the main action.
 *
 * The words are the card's own (data-done, data-review: the server writes them on an enrolled card alone), and the
 * mark takes the progress row away in the stylesheet as well as here, so a card never shows the bar and the seal.
 *
 * @param {HTMLElement} card The card.
 * @returns {void}
 */
const markDone = (card: HTMLElement): void => {
    const seal = card.dataset.done || '';
    const review = card.dataset.review || '';
    card.classList.add(DONE_CLASS);
    const row = card.querySelector<HTMLElement>(HOOKS.progress);
    if (row) {
        row.hidden = true;
    }
    const pill = card.querySelector<HTMLElement>(HOOKS.pill);
    if (pill) {
        Array.from(pill.classList).filter((name) => name.startsWith('dim-badge--')).forEach((name) => pill.classList.remove(name));
        pill.classList.add(DONE_PILL);
        const icon = pill.querySelector('i');
        pill.textContent = ' ' + seal;
        if (icon) {
            icon.className = DONE_ICON;
            pill.prepend(icon);
        }
    }
    const cta = card.querySelector<HTMLElement>(HOOKS.cta);
    if (cta && review) {
        cta.textContent = review;
        cta.classList.remove('btn-primary');
        cta.classList.add('btn-outline-primary');
    }
};

/**
 * Paint one course's details onto one of its cards: the completed state, or else the progress row.
 *
 * The one painter of the flat list's cards and the panel's. A course with no number (no completion tracking, or Boost
 * Union's progress setting off) leaves the row hidden, never at 0 %; a completed course trades the row for the seal.
 *
 * @param {HTMLElement} card The card.
 * @param {object} detail The course's details.
 * @returns {void}
 */
const paintDetail = (card: HTMLElement, detail: Detail): void => {
    if (detail.completed && card.dataset.done) {
        markDone(card);
        return;
    }
    const row = card.querySelector<HTMLElement>(HOOKS.progress);
    if (!row || !detail.progresslabel || typeof detail.progress !== 'number') {
        return;
    }
    row.style.setProperty('--dim-progress', detail.progress + '%');
    row.setAttribute('aria-valuenow', String(detail.progress));
    row.setAttribute('aria-label', detail.progresslabel);
    const pct = row.querySelector<HTMLElement>(HOOKS.pct);
    if (pct) {
        pct.textContent = detail.progresspct || '';
    }
    row.hidden = false;
};

/**
 * Paint the answer already held for a course onto a card of it, if there is one of each.
 *
 * @param {HTMLElement|null} card The card.
 * @param {number} courseid The course id.
 * @returns {void}
 */
const paintAnswer = (card: HTMLElement | null, courseid: number): void => {
    const detail = answers.get(courseid);
    if (!card || !detail) {
        return;
    }
    paintDetail(card, detail);
};

/**
 * Paint the details of one batch into their cards.
 *
 * @param {Array} details The details the server returned, one entry per id it was willing to answer.
 * @returns {void}
 */
const paintProgress = (details: Detail[]): void => {
    (details || []).forEach((detail) => {
        const courseid = Number(detail.id);
        answers.set(courseid, detail);
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
        const response = await fetchOne<{details: Detail[]}>({
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
 * Attach every per-card behaviour, so a row from page four behaves like a row from page one.
 *
 * @param {Array} cards The cards to arm.
 * @returns {void}
 */
const attachCards = (cards: HTMLElement[]): void => {
    cards.forEach((card) => {
        watchCard(card);
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
 * Paint the progress and completion of the panel's enrolled courses, in one batch of at most eight, once the rows are
 * on screen, through the flat list's painter. A failure leaves the cards as they are: progress is decoration.
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
        const response = await fetchOne<{details: Detail[]}>({
            methodname: DETAILS_METHOD,
            args: {courseids: ids.slice(0, PANEL_SIZE)},
        });
        (response.details || []).forEach((detail) => {
            const card = cards.find((one) => Number(one.dataset.courseid) === Number(detail.id));
            if (card) {
                paintDetail(card, detail);
            }
        });
    } catch (error) {
        // Progress is decoration: a card without it is still the card the server drew.
    }
};

/**
 * Wire what only an account's listing hears: the automatic opening.
 *
 * @returns {void}
 */
const listen = (): void => {
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
    // A calendar that could not mount is core's exception dialogue.
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

    /* A course card inside the panel between the posters is a smaller presentation with its "More information" out of
       reach: its progress is asked in one batch with the panel's rows. */
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
