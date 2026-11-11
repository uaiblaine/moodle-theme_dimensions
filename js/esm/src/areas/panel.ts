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
 * The panel between the poster rows, on both shells: one area at a time, its place, its address, its
 * orders, its rail, its keyboard and its states, behind Areas.tsx.
 *
 * The visitor's half is a set of options ({@see Options}): posters that stay links, a question asked once per
 * opening after a quiet, a failure the page takes over, and no automatic opening. The state is read and written
 * synchronously; what React draws from it (the summary of the areas with no result) is told through `ui`.
 *
 * The panel's element - the one list item of the grid the server always writes - is this module's frame: its
 * hidden state, its three transition classes (only through {@see settle}), the five integers of its row and the
 * open area's colour on its style, and its place in the DOM. Its content is category_panel rendered by
 * core/templates and inserted whole ({@see renderWhole}) or region by region ({@see transplant}), never diffed.
 * The cards in it keep their own modules (the film, the dialogue, and on the logged-in shell what an account
 * adds, painted on `painted`).
 *
 * Layout: the row (the five integers) and the DOM order (after the last poster of the open one's row) are both
 * counted from the posters shown and the grid's tracks (matchMedia and the view class), never read off a box.
 * The reads left, each said where it is made: a move from one open area to another reads the pressed poster's
 * top before and after, to keep it under the pointer; the scroll rule reads the panel's box and the navbar's
 * height once the opening has settled; a style read (no layout) fixes the shut state before the grow; the rail
 * reads its scroll from a ResizeObserver entry and from its own scroll events. Nothing is measured at mount.
 *
 * @module     theme_dimensions/areas/panel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from '@moodle/lms/core/pending';
import {load} from '../amd';
import type {TemplatesModule} from '../amd';
import {markTitles} from './mark';
import {
    BODY_CLASS, CLASSES, MOTION, MOTION_BASE, MOTION_FAST, PHONE, PSORT_NAME, SCROLL_END, SCROLL_GAP, SELECTORS,
    SETTLE, SKELETON_DELAY, TEMPLATE, WIDE, addressWith, announcement, answerContext, columns, holderHref, lastOfRow,
    placement, readPanel, sameRow, shellContext, suggestions, summaryOf, termOf,
} from './model';
import type {Answer, Entry, PanelContext, Summary, Word} from './model';
import {
    convertPoster, driven, failed, fitReveal, isExpandable, isFiltered, isZeroed, itemOf, loadedAllEmpty, paintAllEmpty,
    paintPosters, paintReveal, paintSearchAll, pending, posterOf, setAttr, setClass, setExpandable, setHidden,
    setPosterOpen, shellOf, shownItems, toButton, unveil,
} from './posters';
import {dragRail, paintRail, railState, scrollRail} from './rail';
import {AREAS, LISTING} from '../listing/events';
import {replaceKeeping} from '../listing/history';

/**
 * The events the component and its page exchange, spelled once in listing/events.ts: the areas section's,
 * which the listing's mountAreas() hears for the page's client, and the listing's on the document.
 */
export const EVENTS = {...AREAS, ...LISTING};

/** What the component draws from the panel's state, handed in by it. */
export type Ui = {
    summary: (summary: Summary) => void,
    opened: (id: number) => void,
    filtered: (filtered: boolean) => void,
    sync: (update: () => void) => void,
};

/** The nodes the panel works over, found once. */
export type Nodes = {areas: HTMLElement, panel: HTMLElement, status: HTMLElement | null};

/** The detail of the ask event: the page's client fills `answer` while it is dispatched. */
export type Asked = {poster: HTMLElement, psort: string, answer: Promise<Answer> | null};

/** The detail of the question event: a client that names its question fills `question` while it is dispatched. */
export type Questioned = {poster: HTMLElement, psort: string, question: string};

/** The detail of the unanswered event: a client that takes a failure over sets `taken` while it is dispatched. */
export type Unanswered = {poster: HTMLElement, psort: string, taken: boolean};

/**
 * How a shell's posters and questions behave, from the mount's props: the logged-in shell passes none, the
 * visitor's all three.
 *
 * - links: the posters stay the server's links, announced as buttons; a press meant for another tab or window
 *   goes to the address, Space opens as Enter does.
 * - quiet: milliseconds a question waits for the reader to stop pressing; every one is a read the site pays for.
 * - autoopen: whether an answer naming the one area holding every result opens it by itself.
 */
export type Options = {links: boolean, quiet: number, autoopen: boolean};

/** What the mount's props may say, each part optional. */
export type Props = {links?: unknown, quiet?: unknown, autoopen?: unknown};

/**
 * The options a mount's props say: anything but the exact values reads as the logged-in shell's.
 *
 * @param {object} props The mount's props.
 * @returns {object}
 */
export const optionsOf = (props: Props): Options => ({
    links: props.links === true,
    quiet: typeof props.quiet === 'number' && Number.isInteger(props.quiet) && props.quiet > 0 ? props.quiet : 0,
    autoopen: props.autoopen !== false,
});

/** What a criteria answer's event carries. */
export type Criteria = {accordion?: Entry[], areas?: Word};

/**
 * The panel's state: what is open, its order and who opened it; the latest request's ticket; the question
 * that request asked and the ticket it was asked under, so the same question about the same open panel is
 * not asked twice; the turn, counting every opening and closing; the paint, counting the transplants
 * started; the skeleton's timer; whether nothing matches anywhere with no panel open; whether each area
 * opens, by id; whether the drag just ended; and whether the panel's mark is on the body.
 */
type State = {
    open: number,
    psort: string,
    openedby: string,
    ticket: number,
    asked: string,
    askedticket: number,
    turn: number,
    paint: number,
    skeleton: number,
    allempty: boolean,
    expandable: Map<number, boolean>,
    dragged: boolean,
    armed: boolean,
};

/** What a press opening an area asks for. */
type Opening = {focus?: boolean, scroll?: boolean, psort?: string, write?: boolean, auto?: boolean};

/** The panel's behaviour, built once per mount. */
export type Panel = ReturnType<typeof createPanel>;

/**
 * The open panel read off the server's render: the area its section names, when its poster opens.
 *
 * @param {object} nodes The areas, the panel and the status.
 * @returns {number} The area, 0 when the server drew none.
 */
export const drawnOpen = (nodes: Nodes): number => {
    const section = nodes.panel.querySelector<HTMLElement>(SELECTORS.section);
    const id = section ? Number(section.dataset.categoryid) : 0;
    return !nodes.panel.hidden && isExpandable(posterOf(nodes.areas, id)) ? id : 0;
};

/**
 * Whether a click on a link was meant for another tab or window, which the link's own address answers.
 *
 * A middle press and a secondary one arrive as events of their own (auxclick, contextmenu) and never reach a
 * click listener; a press with a modifier and one another listener has already dealt with do.
 *
 * @param {MouseEvent} event The click.
 * @returns {boolean}
 */
export const elsewhere = (event: MouseEvent): boolean => event.defaultPrevented || event.button !== 0
    || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey;

/**
 * Build the panel's behaviour over the server's areas.
 *
 * @param {object} nodes The areas, the panel and the status.
 * @param {object} ui What the component draws from the state.
 * @param {object} mode The shell's options ({@see optionsOf}).
 * @returns {object} The functions the component's listeners call.
 */
export const createPanel = (nodes: Nodes, ui: Ui, mode: Options = optionsOf({})) => {
    const {areas, panel, status} = nodes;
    const st: State = {
        open: 0,
        psort: '',
        openedby: '',
        ticket: 0,
        asked: '',
        askedticket: -1,
        turn: 0,
        paint: 0,
        skeleton: 0,
        allempty: false,
        expandable: new Map(),
        dragged: false,
        armed: false,
    };
    let templates: Promise<TemplatesModule> | null = null;
    let railobserver: ResizeObserver | null = null;

    /**
     * The template renderer, loaded once through the bridge.
     *
     * @returns {Promise}
     */
    const renderer = (): Promise<TemplatesModule> => {
        templates = templates || load('core/templates');
        return templates;
    };

    /**
     * Whether the reader asked for less movement.
     *
     * @returns {boolean}
     */
    const reduced = (): boolean => window.matchMedia(MOTION).matches;

    /**
     * Whether this page is the root of the course index, where no course sits directly.
     *
     * @returns {boolean}
     */
    const isRoot = (): boolean => Number(areas.dataset.categoryid) === 0;

    /**
     * The grid's tracks now: the stylesheet's breakpoints through matchMedia and the view's class, never a box.
     *
     * @returns {number}
     */
    const tracks = (): number => {
        const listing = areas.closest(SELECTORS.listing);
        return columns(
            Boolean(listing) && Boolean(listing?.classList.contains(CLASSES.list)),
            window.matchMedia(PHONE).matches,
            window.matchMedia(WIDE).matches
        );
    };

    /**
     * Put the panel's mark on the body, once: the stylesheet's panel rules (the transitions, the rail as a
     * scroll from 1200px up, its arrows and its phone counter) are written against it.
     *
     * A panel the server drew stays in its no-JavaScript presentation until this module first changes it,
     * because the mark redraws its rail: at mount the mark goes on only where no panel is open.
     *
     * @returns {void}
     */
    const arm = (): void => {
        if (!st.armed) {
            st.armed = true;
            document.body.classList.add(BODY_CLASS);
        }
    };

    /**
     * Put the panel's transition classes in the state one step starts from, whatever another step left.
     *
     * The crossfade (fading), the swap between rows (static) and the collapsed box (shut) each last one
     * transition, and the step that adds one takes it off when it finishes; a step superseded before it
     * finishes never gets there, so every step that starts a transition clears all three first.
     *
     * @param {...string} classes The transition classes the step starts with.
     * @returns {void}
     */
    const settle = (...classes: string[]): void => {
        panel.classList.remove(CLASSES.shut, CLASSES.still, CLASSES.fading);
        panel.classList.add(...classes);
    };

    /**
     * Ask the page's client for an area's rows: the panel's one question, and its one way out to a service.
     *
     * @param {Element} poster The area's poster.
     * @param {string} psort The panel's order.
     * @returns {Promise} The answer; rejected when nothing on the page answers.
     */
    const ask = (poster: HTMLElement, psort: string): Promise<Answer> => {
        const detail: Asked = {poster: poster, psort: psort, answer: null};
        areas.dispatchEvent(new CustomEvent(EVENTS.ask, {detail: detail}));
        return detail.answer || Promise.reject(new Error('Nothing on the page answers the panel.'));
    };

    /**
     * The question a request about an area would ask, as the page's client names it: the empty string, which
     * is asked every time, where nobody names one.
     *
     * @param {Element} poster The area's poster.
     * @param {string} psort The panel's order.
     * @returns {string}
     */
    const question = (poster: HTMLElement, psort: string): string => {
        const detail: Questioned = {poster: poster, psort: psort, question: ''};
        areas.dispatchEvent(new CustomEvent(EVENTS.question, {detail: detail}));
        return typeof detail.question === 'string' ? detail.question : '';
    };

    /**
     * Whether the open panel already shows, or is being sent, the answer to this question.
     *
     * A client that names its question (the visitor's, whose every request is a read the site pays for on the
     * open internet) asks it once per opening: an answer to criteria that did not change - Back over a change
     * of view, a panel the server drew for the address - is the one on screen. Every opening and closing takes
     * a ticket, so the same question asked of a new opening is asked again.
     *
     * @param {string} asking The question, or the empty string.
     * @returns {boolean}
     */
    const answered = (asking: string): boolean => asking !== '' && asking === st.asked && st.askedticket === st.ticket;

    /**
     * Wait for the shell's quiet, when it has one: a reader pressing poster after poster asks once.
     *
     * @param {number} ticket The ticket of the request that waits.
     * @returns {Promise} Resolving true when the request is still the latest once the quiet is over.
     */
    const quiet = async(ticket: number): Promise<boolean> => {
        if (mode.quiet > 0) {
            await new Promise((resolve) => window.setTimeout(resolve, mode.quiet));
        }
        return ticket === st.ticket;
    };

    /**
     * Offer a failure to the page's client before the inline error: the visitor's loads the poster's link.
     *
     * @param {Element} poster The area's poster.
     * @param {string} psort The panel's order.
     * @returns {boolean} Whether the client took it over.
     */
    const unanswered = (poster: HTMLElement, psort: string): boolean => {
        const detail: Unanswered = {poster: poster, psort: psort, taken: false};
        areas.dispatchEvent(new CustomEvent(EVENTS.unanswered, {detail: detail}));
        return detail.taken === true;
    };

    /**
     * Tell the page the rows are on screen, so it paints what belongs to it (an account's progress).
     *
     * @returns {void}
     */
    const painted = (): void => {
        areas.dispatchEvent(new CustomEvent(EVENTS.painted, {detail: {panel: panel}}));
    };

    /**
     * Watch the rail the panel now holds: its first entry and every resize read its scroll after layout.
     *
     * @returns {void}
     */
    const observeRail = (): void => {
        const rail = panel.querySelector<HTMLElement>(SELECTORS.rail);
        if (railobserver === null && typeof ResizeObserver !== 'undefined') {
            railobserver = new ResizeObserver((entries) => {
                const target = entries[entries.length - 1].target as HTMLElement;
                if (panel.contains(target)) {
                    paintRail(panel, railState(target));
                }
            });
        }
        railobserver?.disconnect();
        if (rail) {
            railobserver?.observe(rail);
        }
    };

    /**
     * The rail scrolled: its arrows and counter follow, read from a layout the scroll left clean.
     *
     * @param {Event} event The scroll, heard in the capture phase because it does not bubble.
     * @returns {void}
     */
    const onScroll = (event: Event): void => {
        if (event.target instanceof HTMLElement && event.target.matches(SELECTORS.rail)) {
            paintRail(panel, railState(event.target));
        }
    };

    /**
     * Move a node right after another, keeping the focus where it was when it was inside.
     *
     * moveBefore() moves an element without taking it out of the document, so the focus and a running
     * transition survive; where it does not exist, insertBefore() moves it and the focus is put back by hand.
     *
     * @param {Element} node The node to move.
     * @param {Element} ref The node it goes after.
     * @returns {void}
     */
    const moveAfter = (node: HTMLElement, ref: HTMLElement): void => {
        const parent = ref.parentElement as HTMLElement & {moveBefore?: (node: Node, child: Node | null) => void};
        const next = ref.nextElementSibling;
        if (next === node || node === ref) {
            return;
        }
        const active = document.activeElement as HTMLElement | null;
        const inside = Boolean(active) && node.contains(active);
        if (typeof parent.moveBefore === 'function') {
            try {
                parent.moveBefore(node, next);
                return;
            } catch (error) {
                // A move the browser refuses falls back to insertBefore below.
            }
        }
        parent.insertBefore(node, next);
        if (inside && active && document.activeElement !== active) {
            active.focus({preventScroll: true});
        }
    };

    /**
     * Place the panel: its row by the five integers, its colour by the poster's, its DOM order after the last
     * poster of its row - every write only where it changes, nothing read off a box.
     *
     * @returns {void}
     */
    const place = (): void => {
        const poster = posterOf(areas, st.open);
        if (!poster) {
            return;
        }
        const item = itemOf(poster);
        const grid = item.parentElement as HTMLElement;
        if (panel.parentElement !== grid) {
            moveAfter(panel, item);
        }
        const items = shownItems(grid, panel);
        const index = Math.max(0, items.indexOf(item));
        const integers = placement(index);
        (Object.keys(integers) as Array<keyof typeof integers>).forEach((name) => {
            if (panel.style.getPropertyValue(name).trim() !== integers[name]) {
                panel.style.setProperty(name, integers[name]);
            }
        });
        /* The open area's band paints the panel's rail, head and glow: copied from the poster's own style,
           which the server built through its one colour sanitiser (the server writes the same on a panel it
           drew), so the panel wears exactly the colour of the poster it opens under. */
        const band = poster.style.getPropertyValue('--dim-card-band').trim();
        if (band !== '' && panel.style.getPropertyValue('--dim-area-bg').trim() !== band) {
            panel.style.setProperty('--dim-area-bg', band);
        }
        moveAfter(panel, items[lastOfRow(index, items.length, tracks())] || item);
    };

    /**
     * If the panel does not fit on screen, scroll until its top sits under the navbar with a gap.
     *
     * It reads the panel's box once, past the opening's transition, on a page that has been laid out since.
     *
     * @returns {Promise} Resolving once the page has stopped moving.
     */
    const ensureVisible = (): Promise<void> => new Promise((resolve) => {
        if (!st.open || panel.hidden) {
            resolve();
            return;
        }
        const rect = panel.getBoundingClientRect();
        const bar = parseFloat(window.getComputedStyle(areas).getPropertyValue('--dim-navbar')) || 0;
        if (rect.bottom > window.innerHeight || rect.top < bar) {
            const timer = window.setTimeout(resolve, SCROLL_END);
            window.addEventListener('scrollend', () => {
                window.clearTimeout(timer);
                resolve();
            }, {once: true});
            window.scrollBy({top: rect.top - (bar + SCROLL_GAP), behavior: reduced() ? 'auto' : 'smooth'});
            return;
        }
        resolve();
    });

    /**
     * Say something through the panel's live region, so that a repeat is announced too.
     *
     * @param {string} text The sentence.
     * @returns {void}
     */
    const announce = (text: string): void => {
        if (!status) {
            return;
        }
        status.textContent = '';
        window.requestAnimationFrame(() => {
            status.textContent = text;
        });
    };

    /**
     * Render the whole panel into its element: on an opening and on a move to another area.
     *
     * The check that the opening is still the current one is made after the render resolves and before the
     * document is touched: an opening superseded while its render was out must not write its area's header.
     *
     * @param {object} context The template context.
     * @param {number} turn The turn of the opening the render belongs to.
     * @returns {Promise} Resolving true once it is in the document, false when it was superseded.
     */
    const renderWhole = async(context: PanelContext, turn: number): Promise<boolean> => {
        const engine = await renderer();
        const rendered = await engine.renderForPromise(TEMPLATE, context);
        if (turn !== st.turn) {
            return false;
        }
        engine.replaceNodeContents(panel, rendered.html, rendered.js);
        observeRail();
        return true;
    };

    /**
     * Render a later state of the same area and transplant only what changes, keeping the header.
     *
     * It writes only if its request is still the latest and no transplant has been started since, both
     * checked after the render resolves: the skeleton's render and the answer's run side by side when the
     * answer lands just after the skeleton's delay. A focused control inside a replaced region comes back by
     * its focus key, else the focus goes to the title.
     *
     * @param {object} context The template context.
     * @param {number} ticket The ticket of the request the state belongs to.
     * @returns {Promise} Resolving true once the regions are in the document, false when superseded.
     */
    const transplant = async(context: PanelContext, ticket: number): Promise<boolean> => {
        const paint = ++st.paint;
        const engine = await renderer();
        const rendered = await engine.renderForPromise(TEMPLATE, context);
        if (ticket !== st.ticket || paint !== st.paint) {
            return false;
        }
        const holder = document.createElement('div');
        engine.appendNodeContents(holder, rendered.html, rendered.js);
        const active = document.activeElement as HTMLElement | null;
        const inside = Boolean(active) && panel.contains(active);
        const key = inside && active ? active.dataset.focuskey || '' : '';
        const subtitle = panel.querySelector(SELECTORS.subtitle);
        const freshsubtitle = holder.querySelector(SELECTORS.subtitle);
        if (subtitle && freshsubtitle) {
            subtitle.textContent = freshsubtitle.textContent;
        }
        [SELECTORS.also, SELECTORS.body, SELECTORS.foot].forEach((selector) => {
            const current = panel.querySelector(selector);
            const fresh = holder.querySelector(selector);
            if (current && fresh) {
                current.replaceWith(fresh);
            }
        });
        if (inside && !panel.contains(document.activeElement)) {
            const keyed = key !== '' ? panel.querySelector<HTMLElement>(`[data-focuskey="${key}"]`) : null;
            const target = keyed || panel.querySelector<HTMLElement>(SELECTORS.title);
            target?.focus({preventScroll: true});
        }
        observeRail();
        return true;
    };

    /**
     * Mark the two orders busy or not while a request is out: one question at a time.
     *
     * @param {boolean} busy Whether a request is out.
     * @returns {void}
     */
    const setSortBusy = (busy: boolean): void => {
        panel.querySelectorAll(SELECTORS.sortoption).forEach((option) => setAttr(option, 'aria-disabled', busy ? 'true' : 'false'));
    };

    /**
     * Paint the two orders: which one is checked, and which one the Tab key reaches.
     *
     * @returns {void}
     */
    const paintSort = (): void => {
        panel.querySelectorAll<HTMLElement>(SELECTORS.sortoption).forEach((option) => {
            const on = (option.dataset.psort === PSORT_NAME) === (st.psort === PSORT_NAME);
            setAttr(option, 'aria-checked', on ? 'true' : 'false');
            option.tabIndex = on ? 0 : -1;
        });
    };

    /**
     * The page's suggestions for a zero panel, read off the posters.
     *
     * @returns {Array}
     */
    const offered = () => suggestions(
        Array.from(areas.querySelectorAll<HTMLElement>(SELECTORS.area)).map((poster) => ({
            id: Number(poster.dataset.categoryid),
            name: poster.dataset.name || '',
            count: Number(poster.dataset.count),
            resultcount: poster.dataset.resultcount || '',
            resultlabel: poster.dataset.resultlabel || '',
        })),
        st.open,
        isFiltered(areas)
    );

    /**
     * Ask for the open area's rows, and paint the answer unless something newer has been asked since.
     *
     * A question already answered for this opening is not asked again ({@see answered}). The body stays empty
     * for SKELETON_DELAY past the shell's quiet; a slower answer gets the skeleton first. A failure is the
     * page's client's to take over first ({@see unanswered}), and otherwise the inline error with its retry,
     * never a dialogue.
     *
     * @returns {Promise} Resolving when the request has been dealt with.
     */
    const request = async(): Promise<void> => {
        const id = st.open;
        const poster = posterOf(areas, id);
        if (!poster) {
            return;
        }
        const asking = question(poster, st.psort);
        if (answered(asking)) {
            return;
        }
        arm();
        const ticket = ++st.ticket;
        st.asked = asking;
        st.askedticket = ticket;
        // The quiet is a timer no request covers yet: held from here until the answer is painted.
        const held = new Pending('theme_dimensions/areas:request');
        window.clearTimeout(st.skeleton);
        setSortBusy(true);
        st.skeleton = window.setTimeout(() => {
            if (ticket === st.ticket) {
                transplant(shellContext(shellOf(poster), st.psort, window.location.href, {isskeleton: true}), ticket);
            }
        }, SKELETON_DELAY + mode.quiet);
        try {
            if (!(await quiet(ticket))) {
                return;
            }
            const response = await ask(poster, st.psort);
            if (ticket !== st.ticket) {
                return;
            }
            window.clearTimeout(st.skeleton);
            const context = answerContext(response, id, offered());
            if (!(await transplant(context, ticket))) {
                return;
            }
            markTitles(panel, termOf(window.location.href));
            const body = panel.querySelector(SELECTORS.body);
            announce(announcement(context, body ? body.textContent || '' : '', areas.dataset.elsewhere || ''));
            painted();
        } catch (error) {
            if (ticket !== st.ticket) {
                return;
            }
            window.clearTimeout(st.skeleton);
            st.asked = '';
            if (unanswered(poster, st.psort)) {
                return;
            }
            if (!(await transplant(shellContext(shellOf(poster), st.psort, window.location.href, {iserror: true}), ticket))) {
                return;
            }
            const subtitle = panel.querySelector(SELECTORS.subtitle);
            announce(subtitle ? (subtitle.textContent || '').trim() : '');
        } finally {
            if (ticket === st.ticket) {
                setSortBusy(false);
            }
            held.resolve();
        }
    };

    /**
     * Keep one hidden field of a form in step with the panel: created, changed or removed.
     *
     * @param {Element|null} form The form.
     * @param {string} selector The field's hook.
     * @param {string} name The field's name.
     * @param {string} value Its value, or the empty string for no field at all.
     * @returns {void}
     */
    const syncField = (form: Element | null, selector: string, name: string, value: string): void => {
        if (!form) {
            return;
        }
        let input = form.querySelector<HTMLInputElement>(selector);
        if (value === '') {
            input?.remove();
            return;
        }
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.dataset.region = selector === SELECTORS.openfield ? 'dim-open-field' : 'dim-psort-field';
            form.prepend(input);
        }
        input.value = value;
    };

    /**
     * Keep the three other holders of the panel in step with the address: the toolbar's form, the hero's
     * fold form, and the clear link and the chips under the filter line. Each would otherwise reload the
     * page with the panel the reader had when it loaded.
     *
     * @returns {void}
     */
    const syncHolders = (): void => {
        const open = st.open > 0 ? String(st.open) : '';
        const psort = st.open > 0 && st.psort === PSORT_NAME ? PSORT_NAME : '';
        [document.querySelector(SELECTORS.toolbar), document.querySelector(SELECTORS.fold)].forEach((form) => {
            syncField(form, SELECTORS.openfield, 'open', open);
            syncField(form, SELECTORS.psortfield, 'psort', psort);
        });
        const links = [document.querySelector<HTMLAnchorElement>(SELECTORS.clear)]
            .concat(Array.from(document.querySelectorAll<HTMLAnchorElement>(SELECTORS.chip)));
        links.forEach((link) => {
            const href = link ? link.getAttribute('href') : null;
            if (link && href) {
                link.href = holderHref(href, window.location.href, st.open, st.psort);
            }
        });
    };

    /**
     * Write the open area and its order into the address, replacing this history entry, never pushing one.
     *
     * @returns {void}
     */
    const writeAddress = (): void => {
        replaceKeeping(addressWith(window.location.href, st.open, st.psort));
        syncHolders();
    };

    /**
     * Put the focus on the panel's heading, without scrolling.
     *
     * @returns {void}
     */
    const focusTitle = (): void => {
        panel.querySelector<HTMLElement>(SELECTORS.title)?.focus({preventScroll: true});
    };

    /**
     * Put the focus on the summary's own control, where a closed zeroed area has just gone.
     *
     * @returns {void}
     */
    const focusSummary = (): void => {
        areas.querySelector<HTMLElement>(SELECTORS.nomatchsummary)?.focus({preventScroll: true});
    };

    /**
     * Give a poster the opening state the latest criteria gave it, once it is no longer the open one.
     *
     * @param {Element|null} poster The poster.
     * @returns {void}
     */
    const applyExpandable = (poster: HTMLElement | null): void => {
        const id = poster ? Number(poster.dataset.categoryid) : 0;
        if (poster && st.expandable.has(id)) {
            setExpandable(poster, Boolean(st.expandable.get(id)), st.open, mode.links);
        }
    };

    /**
     * Tell the component which area is open, drawn before this step goes on (the summary's sentence and its
     * entries, which the focus may be about to land in).
     *
     * @returns {void}
     */
    const recountSummary = (): void => {
        ui.sync(() => ui.opened(st.open));
    };

    /**
     * Whether the poster being opened shares a row of the same grid with the open one, among the posters shown.
     *
     * @param {Element|null} prev The poster whose panel is open, or null.
     * @param {Element} poster The poster being opened.
     * @returns {boolean} Whether the panel moves inside one row.
     */
    const sharesRow = (prev: HTMLElement | null, poster: HTMLElement): boolean => {
        if (!prev || isZeroed(areas, prev) || panel.hidden) {
            return false;
        }
        const grid = itemOf(poster).parentElement as HTMLElement;
        if (itemOf(prev).parentElement !== grid) {
            return false;
        }
        const items = shownItems(grid, panel);
        return sameRow(items.indexOf(itemOf(prev)), items.indexOf(itemOf(poster)), tracks());
    };

    /**
     * Move the open panel to another area of the same row: the content crossfades and the arrow slides.
     *
     * @param {object} shell The new area's shell context.
     * @param {number} turn The turn of the opening.
     * @param {boolean} focus Whether the title takes the focus.
     * @returns {void}
     */
    const crossfade = (shell: PanelContext, turn: number, focus: boolean): void => {
        place();
        settle(CLASSES.fading);
        // Held until the request takes over with its own, so Behat never sees the crossfade as settled.
        const held = new Pending('theme_dimensions/areas:crossfade');
        window.setTimeout(async() => {
            try {
                // A step superseded here leaves the classes to the step that superseded it, which settled them.
                if (turn !== st.turn || !(await renderWhole(shell, turn))) {
                    return;
                }
                panel.classList.remove(CLASSES.fading);
                if (focus) {
                    focusTitle();
                }
                request();
            } finally {
                held.resolve();
            }
        }, MOTION_FAST);
    };

    /**
     * Open the panel in another row, or for the first time: every transition off while it moves, then it grows.
     *
     * The page is scrolled by however far the pressed poster moved, so it stays under the pointer: its top was
     * read before the move and is read once after it, a move from one open area to another only.
     *
     * @param {Element} poster The poster being opened.
     * @param {Element|null} prev The poster whose panel was open, or null.
     * @param {number} before The pressed poster's top before the move.
     * @param {number} turn The turn of the opening.
     * @param {object} shell The area's shell context.
     * @param {object} options focus and scroll.
     * @returns {Promise} Resolving when the panel is on its way.
     */
    const moveTo = async(
        poster: HTMLElement,
        prev: HTMLElement | null,
        before: number,
        turn: number,
        shell: PanelContext,
        options: Opening
    ): Promise<void> => {
        settle(CLASSES.still, CLASSES.shut);
        setHidden(panel, false);
        if (!(await renderWhole(shell, turn))) {
            return;
        }
        place();
        if (prev) {
            const after = poster.getBoundingClientRect().top;
            if (Math.abs(after - before) > 1) {
                window.scrollBy(0, after - before);
            }
        }
        // A style read and no layout: the shut state, transitions off, is what the transition starts from.
        void window.getComputedStyle(panel).opacity;
        window.requestAnimationFrame(() => {
            if (turn !== st.turn) {
                return;
            }
            panel.classList.remove(CLASSES.still);
            window.requestAnimationFrame(() => {
                if (turn === st.turn) {
                    panel.classList.remove(CLASSES.shut);
                }
            });
        });
        if (options.focus) {
            focusTitle();
        }
        request();
        if (options.scroll !== false) {
            const scroll = new Pending('theme_dimensions/areas:scroll');
            window.setTimeout(async() => {
                try {
                    await ensureVisible();
                } finally {
                    scroll.resolve();
                }
            }, (reduced() ? 0 : MOTION_BASE) + SETTLE);
        }
    };

    /**
     * Open one area's panel, closing the one that was open: one panel at a time.
     *
     * In the same row the content crossfades and the arrow slides; in another row the panel moves with every
     * transition suppressed, and the page is scrolled by however far the pressed poster moved, so it stays
     * under the pointer (the one box read of a press: its top before any write and after the move).
     *
     * @param {number} id The area.
     * @param {object} options focus, scroll, psort, write and auto.
     * @returns {Promise} Resolving when the panel is on its way.
     */
    const openArea = async(id: number, options: Opening): Promise<void> => {
        const poster = posterOf(areas, id);
        if (!poster || !isExpandable(poster) || id === st.open) {
            return;
        }
        arm();
        unveil(poster);
        const prev = st.open ? posterOf(areas, st.open) : null;
        const before = prev ? poster.getBoundingClientRect().top : 0;
        const prevhides = isZeroed(areas, prev);
        const samerow = sharesRow(prev, poster);
        setPosterOpen(prev, false, mode.links);
        setPosterOpen(poster, true, mode.links);
        if (prev && prevhides) {
            setHidden(itemOf(prev), true);
            fitReveal(areas);
        }
        st.open = id;
        st.openedby = options.auto ? 'auto' : 'user';
        st.psort = options.psort || '';
        st.ticket += 1;
        const turn = ++st.turn;
        applyExpandable(prev);
        recountSummary();
        paintAllEmpty(areas, st.allempty, st.open);
        setClass(areas, CLASSES.areasopen, true);
        if (!options.auto) {
            const hint = areas.querySelector<HTMLElement>(SELECTORS.hint);
            if (hint) {
                setHidden(hint, true);
            }
        }
        if (options.write !== false) {
            writeAddress();
        }
        const shell = shellContext(shellOf(poster), st.psort, window.location.href, {isloading: true});
        if (samerow && !reduced()) {
            crossfade(shell, turn, Boolean(options.focus));
            return;
        }
        await moveTo(poster, prev, before, turn, shell, options);
    };

    /**
     * Close the open panel: it collapses, then leaves the document's flow; the focus goes back.
     *
     * An area the criterion zeroed leaves the grid as it closes and joins the summary, so the focus that
     * would have gone back to its poster goes to the summary instead.
     *
     * @param {boolean} focus Whether the focus returns to the poster's control.
     * @param {boolean} write Whether the address is written.
     * @returns {void}
     */
    const closeArea = (focus: boolean, write: boolean): void => {
        if (!st.open) {
            return;
        }
        arm();
        const poster = posterOf(areas, st.open);
        const zeroed = isZeroed(areas, poster);
        setPosterOpen(poster, false, mode.links);
        st.open = 0;
        st.openedby = '';
        st.psort = '';
        st.ticket += 1;
        st.turn += 1;
        window.clearTimeout(st.skeleton);
        if (poster && zeroed) {
            setHidden(itemOf(poster), true);
            fitReveal(areas);
        }
        applyExpandable(poster);
        recountSummary();
        paintAllEmpty(areas, st.allempty, st.open);
        setClass(areas, CLASSES.areasopen, false);
        settle(CLASSES.shut);
        /**
         * Take the collapsed panel out of the flow, unless another opening took it over meanwhile.
         *
         * @returns {void}
         */
        const hide = (): void => {
            if (!st.open) {
                setHidden(panel, true);
                panel.replaceChildren();
                railobserver?.disconnect();
            }
        };
        if (reduced()) {
            hide();
        } else {
            window.setTimeout(hide, MOTION_BASE);
        }
        if (status) {
            status.textContent = '';
        }
        if (write) {
            writeAddress();
        }
        const control = poster ? poster.querySelector<HTMLElement>(SELECTORS.control) : null;
        if (focus && zeroed) {
            focusSummary();
        } else if (focus && control) {
            control.focus({preventScroll: true});
        }
    };

    /**
     * Change the open panel's order, and ask for it.
     *
     * @param {string} psort The empty string (the default, recent) or name.
     * @returns {void}
     */
    const setPsort = (psort: string): void => {
        const value = psort === PSORT_NAME ? PSORT_NAME : '';
        if (!st.open || st.psort === value || panel.querySelector(`${SELECTORS.sortoption}[aria-disabled="true"]`)) {
            return;
        }
        st.psort = value;
        paintSort();
        writeAddress();
        request();
    };

    /**
     * Open a poster's panel, or close it when it is the open one.
     *
     * @param {Element} poster The poster.
     * @returns {void}
     */
    const toggleArea = (poster: HTMLElement): void => {
        const id = Number(poster.dataset.categoryid);
        if (id === st.open) {
            closeArea(true, true);
        } else {
            openArea(id, {focus: true});
        }
    };

    /**
     * A press inside the areas: a poster's control opens or closes its panel. A poster that is a link to its
     * page (one with nothing to open) goes there; on a shell keeping the posters links, a press meant for
     * another tab or window goes to the link's address too, exactly as it does without the component.
     *
     * @param {Event} event The click.
     * @returns {void}
     */
    const onAreasClick = (event: Event): void => {
        const control = event.target instanceof Element ? event.target.closest<HTMLElement>(SELECTORS.control) : null;
        if (!control || panel.contains(control)) {
            return;
        }
        const poster = control.closest<HTMLElement>(SELECTORS.area);
        if (!poster || !isExpandable(poster) || driven(poster, mode.links) !== control) {
            return;
        }
        if (control.tagName === 'A' && elsewhere(event as MouseEvent)) {
            return;
        }
        event.preventDefault();
        toggleArea(poster);
    };

    /**
     * Space on a poster that stays a link opens or closes its panel, as it would a button's.
     *
     * The link is announced as a button, and a button answers Space; a link alone would scroll the page.
     * Enter needs nothing here: on a link it is a click.
     *
     * @param {KeyboardEvent} event The key.
     * @returns {boolean} Whether the key was a poster's.
     */
    const onSpace = (event: KeyboardEvent): boolean => {
        if (event.key !== ' ' || event.repeat || !mode.links) {
            return false;
        }
        const control = event.target instanceof Element ? event.target.closest<HTMLElement>(SELECTORS.control) : null;
        const poster = control ? control.closest<HTMLElement>(SELECTORS.area) : null;
        if (!control || !poster || control.tagName !== 'A' || panel.contains(control) || driven(poster, true) !== control) {
            return false;
        }
        event.preventDefault();
        toggleArea(poster);
        return true;
    };

    /**
     * A press inside the panel: the orders, Close, a zero panel's suggestions, the retry and the rail's arrows.
     *
     * @param {Event} event The click.
     * @returns {void}
     */
    const onPanelClick = (event: Event): void => {
        if (!(event.target instanceof Element)) {
            return;
        }
        const target = event.target;
        const option = target.closest<HTMLElement>(SELECTORS.sortoption);
        if (option && option.tagName === 'BUTTON') {
            event.preventDefault();
            setPsort(option.dataset.psort || '');
            return;
        }
        const suggestion = target.closest<HTMLElement>(SELECTORS.openarea);
        if (suggestion && suggestion.tagName === 'BUTTON') {
            event.preventDefault();
            openArea(Number(suggestion.dataset.categoryid), {focus: true});
            return;
        }
        const close = target.closest(SELECTORS.close);
        if (close && close.tagName === 'BUTTON') {
            event.preventDefault();
            closeArea(true, true);
            return;
        }
        if (target.closest(SELECTORS.retry)) {
            event.preventDefault();
            request();
            return;
        }
        if (target.closest(SELECTORS.prev)) {
            scrollRail(panel, -1, reduced());
            return;
        }
        if (target.closest(SELECTORS.next)) {
            scrollRail(panel, 1, reduced());
        }
    };

    /**
     * The click that ends a drag of the rail is not a click: heard first, in the capture phase.
     *
     * @param {Event} event The click.
     * @returns {void}
     */
    const onDragClick = (event: Event): void => {
        if (st.dragged) {
            event.preventDefault();
            event.stopPropagation();
            st.dragged = false;
        }
    };

    /**
     * A mouse press on the rail may be a drag.
     *
     * @param {PointerEvent} event The press.
     * @returns {void}
     */
    const onPointerDown = (event: PointerEvent): void => {
        dragRail(event, () => {
            st.dragged = true;
            window.setTimeout(() => {
                st.dragged = false;
            }, 0);
        });
    };

    /**
     * A drag started on the rail drags nothing out of it.
     *
     * @param {Event} event The drag.
     * @returns {void}
     */
    const onDragStart = (event: Event): void => {
        if (event.target instanceof Element && event.target.closest(SELECTORS.rail)) {
            event.preventDefault();
        }
    };

    /**
     * Escape closes the open panel from inside it or from its poster, and from nowhere else: not from the
     * search box, where it empties the field, and not from the film's controls, where it stops the film. Space
     * opens a poster that stays a link ({@see onSpace}). The arrows move between the two orders.
     *
     * @param {KeyboardEvent} event The key.
     * @returns {void}
     */
    const onKeydown = (event: KeyboardEvent): void => {
        if (event.key === 'Escape' && st.open) {
            const active = document.activeElement;
            if (!(active instanceof Element) || active.matches(SELECTORS.search) || active.closest(SELECTORS.covercontrols)) {
                return;
            }
            const poster = posterOf(areas, st.open);
            if (panel.contains(active) || (poster && poster.contains(active))) {
                event.preventDefault();
                closeArea(true, true);
            }
            return;
        }
        if (onSpace(event)) {
            return;
        }
        const option = event.target instanceof Element ? event.target.closest(SELECTORS.sortoption) : null;
        const arrows = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'];
        if (option && option.tagName === 'BUTTON' && arrows.indexOf(event.key) >= 0) {
            event.preventDefault();
            setPsort(st.psort === PSORT_NAME ? '' : PSORT_NAME);
            panel.querySelector<HTMLElement>(`${SELECTORS.sortoption}[aria-checked="true"]`)?.focus();
        }
    };

    /**
     * The back and forward buttons: open, close, or nothing when the page is already in that state.
     *
     * @returns {void}
     */
    const onPopState = (): void => {
        const {open: id, psort} = readPanel(window.location.href);
        if (id === st.open && psort === st.psort) {
            return;
        }
        if (id === 0) {
            /* A focus inside the panel would be deleted with it, and one on the open poster may leave the grid
               with it when the area is zeroed: closeArea() puts either on the poster or the summary. */
            const active = document.activeElement;
            const open = posterOf(areas, st.open);
            closeArea(panel.contains(active) || (Boolean(open) && Boolean(open?.contains(active))), false);
            syncHolders();
            return;
        }
        if (id === st.open) {
            st.psort = psort;
            paintSort();
            syncHolders();
            request();
            return;
        }
        if (isExpandable(posterOf(areas, id))) {
            openArea(id, {focus: false, scroll: false, psort: psort, write: false});
            syncHolders();
        }
    };

    /**
     * Re-place the panel after a breakpoint or a view change: its DOM order (the rail follows its own resize).
     *
     * @returns {void}
     */
    const relayout = (): void => {
        if (st.open) {
            place();
        }
    };

    /**
     * The reveal past the slice closed over the open poster closes its panel with it.
     *
     * @param {Event} event The toggle, heard in the capture phase because it does not bubble.
     * @returns {void}
     */
    const onToggle = (event: Event): void => {
        const details = event.target;
        if (!(details instanceof HTMLDetailsElement) || !details.matches(SELECTORS.more)) {
            return;
        }
        const poster = posterOf(areas, st.open);
        if (!details.open && poster && details.contains(poster)) {
            closeArea(false, true);
        } else {
            relayout();
        }
    };

    /**
     * Open the one area holding every result, when the answer names one and the shell opens by itself.
     *
     * The focus stays where the reader is typing, a panel the reader opened is never swapped for another, and
     * nothing is ever closed by this. The visitor's shell has none (`autoopen` false).
     *
     * @param {object} word The answer's word on the areas: autoopen, the area's id or 0.
     * @returns {void}
     */
    const autoOpen = (word: Word): void => {
        const id = Number(word.autoopen) || 0;
        if (!mode.autoopen || !id || id === st.open || (st.open && st.openedby === 'user')) {
            return;
        }
        openArea(id, {focus: false, scroll: false, auto: true});
        if (st.open === id) {
            document.dispatchEvent(new CustomEvent(EVENTS.autoopened, {detail: {id: id}}));
        }
    };

    /**
     * The criteria's answer arrived: the result mode is written onto the areas, in one order, the open panel
     * asked again after the posters carry the new numbers, and the automatic opening last.
     *
     * @param {object} detail The answer's accordion and its word on the areas.
     * @returns {void}
     */
    const criteria = (detail: Criteria): void => {
        const accordion = detail.accordion || [];
        const word = detail.areas || {};
        const filtered = Boolean(word.filtered);
        areas.querySelectorAll(SELECTORS.grid).forEach((grid) => setAttr(grid, 'aria-busy', null));
        areas.dataset.filtered = filtered ? '1' : '0';
        areas.dataset.elsewhere = word.elsewhere || '';
        paintPosters(areas, accordion, filtered, st.open, st.expandable, mode.links);
        ui.sync(() => {
            ui.filtered(filtered);
            ui.summary(summaryOf(accordion, word, (id) => {
                const poster = posterOf(areas, id);
                return poster ? poster.dataset.name || '' : null;
            }));
        });
        paintReveal(areas, word);
        st.allempty = Boolean(word.allempty);
        paintAllEmpty(areas, st.allempty, st.open);
        paintSearchAll(areas, word.searchallurl || '');
        if (st.open) {
            place();
            request();
        }
        autoOpen(word);
    };

    /**
     * Turn a server-drawn panel's no-JavaScript links into buttons: the two orders a radio group, Close a
     * button, a zero panel's suggestions buttons that open their area in place.
     *
     * @returns {void}
     */
    const convertPanel = (): void => {
        panel.querySelectorAll<HTMLElement>(`a${SELECTORS.openarea}`).forEach((link) => toButton(link, {}));
        const sort = panel.querySelector(SELECTORS.sort);
        if (sort) {
            sort.setAttribute('role', 'radiogroup');
            sort.querySelectorAll<HTMLElement>(`a${SELECTORS.sortoption}`).forEach((link) => {
                const checked = link.getAttribute('aria-current') === 'true';
                const button = toButton(link, {
                    'role': 'radio',
                    'aria-checked': checked ? 'true' : 'false',
                    'tabindex': checked ? '0' : '-1',
                });
                button.removeAttribute('aria-current');
            });
        }
        const close = panel.querySelector<HTMLElement>(`a${SELECTORS.close}`);
        if (close) {
            toButton(close, {'aria-label': close.getAttribute('aria-label') || ''});
        }
    };

    /**
     * Take over the page at mount: a panel the server drew (no request, no scroll, no measure: the server
     * already placed it), every expandable poster's control, and the panel's mark where no panel is open.
     *
     * A drawn panel's rows are the answer to the address's question, so that question counts as asked: an
     * answer to the same criteria later asks it again only for a client that names no question.
     *
     * @returns {void}
     */
    const adopt = (): void => {
        st.allempty = loadedAllEmpty(areas, isRoot());
        const id = drawnOpen(nodes);
        if (id) {
            st.open = id;
            st.openedby = 'user';
            st.psort = readPanel(window.location.href).psort;
            st.asked = question(posterOf(areas, id) as HTMLElement, st.psort);
            st.askedticket = st.ticket;
            convertPanel();
            markTitles(panel, termOf(window.location.href));
            setClass(panel, CLASSES.shut, false);
            setClass(areas, CLASSES.areasopen, true);
        }
        areas.querySelectorAll<HTMLElement>(SELECTORS.area).forEach((poster) => convertPoster(poster, st.open, mode.links));
        if (!st.open) {
            arm();
            return;
        }
        place();
        observeRail();
        painted();
    };

    /**
     * Stop watching the rail: the component unmounted.
     *
     * @returns {void}
     */
    const disconnect = (): void => {
        railobserver?.disconnect();
        window.clearTimeout(st.skeleton);
    };

    /**
     * A request for new criteria went out: the posters wait ({@see ./posters pending}).
     *
     * @param {object} detail Whether the requested criteria narrow the page (filtered).
     * @returns {void}
     */
    const onPending = (detail: {filtered?: boolean}): void => pending(areas, Boolean(detail.filtered));

    /**
     * The request for new criteria failed: the posters say the latest answer again ({@see ./posters failed}).
     *
     * @returns {void}
     */
    const onFailed = (): void => failed(areas);

    /**
     * The panel's state, for the component's tests to read.
     *
     * @returns {object}
     */
    const state = (): Readonly<State> => st;

    return {
        adopt, disconnect, criteria, relayout, onAreasClick, onPanelClick, onDragClick, onPointerDown, onDragStart,
        onKeydown, onPopState, onToggle, onScroll, onPending, onFailed, state,
    };
};
