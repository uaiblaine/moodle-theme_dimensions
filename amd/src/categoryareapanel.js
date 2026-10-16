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
 * The panel between the rows of posters, opened in place: what both clients of the showcase share.
 *
 * The page is complete without this module. Every poster is a link that reloads the page with its
 * panel drawn by the server (the open parameter), the panel's two orders and its Close are links,
 * and the fragment lands the reader on the panel. What this module adds is the same panel without
 * the reload: it asks its client for the area's first eight courses and renders them through the
 * panel's own template, the one the server renders - so a panel opened here and a panel drawn by
 * the server are one markup.
 *
 * One panel at a time, and one element for it: the list item the server always writes into the
 * grid. Its row is five integers on its own style attribute that the stylesheet reads per
 * breakpoint, so a resize moves it with no script; what the script does is keep its DOM order
 * right after the last poster of its poster's row, so the Tab order and a screen reader meet it
 * where the eye does. That move happens on open, on a breakpoint change and on a view change, and
 * never on a resize.
 *
 * The address is the page's own: the open area and its order are written with replaceState, never
 * pushState, keeping every other parameter as it is - and the three other holders of the panel
 * (the toolbar form, the hero's fold form and the clear link) are kept in step, or a no-JavaScript
 * submit, a fold or a clear would close the panel the reader has open.
 *
 * Under a criterion the posters are the results: each wears the number of results its area holds,
 * an area with none leaves its grid for the "nothing in N areas" summary - all but the open one,
 * which stays until it is closed. Every number and every sentence of that is the server's, carried
 * by the criteria's answer and written onto the posters by categoryposters; this module adds the
 * open panel, which it asks again for every answer ({@see criteria}), and composes nothing.
 *
 * Two clients arm it, and arm() is handed everything that differs between them: how an area's rows
 * are asked for - each client has a web service of its own -, what is painted once they are on
 * screen, whether a poster's link becomes a button or stays the link it is, whether the same
 * question is asked twice, and what a failure becomes. categorypanel arms it for a logged-in
 * account and categorypublic for a visitor. Nothing here calls a web service, reads the session or
 * stars a course, because the visitor's module imports this one (categorypublic_source_test walks
 * the import graph); its mark on the body is its own, dim-panel-js, which the stylesheet's panel
 * rules - the transitions, the rail's arrows and its counter - are written against.
 *
 * @module     theme_dimensions/categoryareapanel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from 'core/pending';
import Templates from 'core/templates';
import * as CategoryMark from 'theme_dimensions/categorymark';
import * as Posters from 'theme_dimensions/categoryposters';

/** @type {Object} Every hook this module reads, all of them data attributes of the showcase's templates. */
const SELECTORS = {
    areas: '[data-region="dim-areas"]',
    grid: '[data-region="dim-areas-grid"]',
    more: '[data-region="dim-areas-more"]',
    hint: '[data-region="dim-areas-hint"]',
    allempty: '[data-region="dim-areas-empty"]',
    nomatchsummary: '[data-region="dim-nomatch-summary"]',
    status: '[data-region="dim-panel-status"]',
    area: '[data-region="dim-area"]',
    control: '[data-region="dim-poster-control"]',
    trigger: '[data-region="dim-poster-trigger"]',
    emptynote: '[data-region="dim-poster-emptynote"]',
    covercontrols: '[data-region="dim-cover-controls"]',
    panel: '[data-region="dim-panel"]',
    section: '[data-region="dim-panel-section"]',
    title: '[data-region="dim-panel-title"]',
    subtitle: '[data-region="dim-panel-subtitle"]',
    sort: '[data-region="dim-panel-sort"]',
    sortoption: '[data-action="dim-panel-sort"]',
    close: '[data-action="dim-panel-close"]',
    also: '[data-region="dim-panel-also"]',
    body: '[data-region="dim-panel-body"]',
    rail: '[data-region="dim-panel-rail"]',
    prev: '[data-action="dim-panel-prev"]',
    next: '[data-action="dim-panel-next"]',
    foot: '[data-region="dim-panel-foot"]',
    counter: '[data-region="dim-panel-counter"]',
    retry: '[data-action="dim-panel-retry"]',
    openarea: '[data-action="dim-panel-openarea"]',
    card: '[data-region="dim-card"]',
    search: 'input[type="search"]',
    toolbar: '[data-region="dim-vitrine-toolbar"]',
    fold: '[data-region="dim-hero-fold"]',
    clear: '[data-region="dim-vitrine-clear"]',
    chip: '[data-action="dim-chip-remove"]',
    openfield: '[data-region="dim-open-field"]',
    psortfield: '[data-region="dim-psort-field"]',
    direct: '[data-region="dim-direct"]',
};

/** @type {String} The panel's template: the server renders it for ?open=, this module for a click. */
const TEMPLATE = 'theme_dimensions/category_panel';

/** @type {String} The panel element's id, which every poster's control controls. */
const PANEL_ID = 'dim-panel';

/** @type {String} The summary's id, which the server's Close of a zeroed area lands on: category_panel::ANCHOR_NOMATCH. */
const NOMATCH_ID = 'dim-nomatch';

/** @type {String} This module's own mark on the body. It never stands for another module's presence. */
const BODY_CLASS = 'dim-panel-js';

/** @type {Number} Milliseconds a request may take before the skeleton replaces the empty body. */
const SKELETON_DELAY = 150;

/** @type {Number} Pixels between the fixed navbar and a panel scrolled into view. */
const SCROLL_GAP = 48;

/** @type {String} Below this width the grid has two tracks: the stylesheet's own number. */
const WIDE = '(max-width: 1199.98px)';

/** @type {String} Below this width the grid has one track: the stylesheet's own number. */
const PHONE = '(max-width: 575.98px)';

/** @type {String} The reader who has asked the platform for less movement. */
const MOTION = '(prefers-reduced-motion: reduce)';

/** @type {String} A pointer that hovers and is precise: the rail's arrows are for it alone. */
const FINE = '(hover: hover) and (pointer: fine)';

/** @type {Number} The stylesheet's --dim-ds-motion-base: the panel opens and closes over it. */
const MOTION_BASE = 250;

/** @type {Number} The stylesheet's --dim-ds-motion-fast: the content crossfades over it. */
const MOTION_FAST = 150;

/** @type {Number} Milliseconds past the opening before the scroll rule looks at the panel. */
const SETTLE = 90;

/** @type {Number} Milliseconds a scroll is given to report its end before it is taken to be over. */
const SCROLL_END = 1000;

/** @type {Number} Pixels a mouse must travel before a press on the rail is a drag rather than a click. */
const DRAG_THRESHOLD = 5;

/** @type {Number} The rail's own gap, which one step of its arrows adds to a card's width. */
const RAIL_GAP = 16;

/** @type {Object} The request parameter names, the same ones the listing's modules and the server use. */
const PARAM = {
    open: 'open',
    psort: 'psort',
    query: 'q',
};

/** @type {String} The one order a panel can be asked for besides its default. */
const PSORT_NAME = 'name';

/**
 * @type {Object} The events on the document: the one this module listens to - the view changed,
 * which the shared listing sends for both clients - and the one it tells, that an area was opened
 * by itself.
 */
const EVENTS = {
    view: 'dim-vitrine:view',
    autoopened: 'dim-panel:autoopened',
};

/** @type {Object} The classes this module writes; it reads none of them. */
const CLASSES = {
    posteropen: 'dim-poster--open',
    posterempty: 'dim-poster--empty',
    areasopen: 'dim-areas--open',
    shut: 'dim-panel--shut',
    still: 'dim-panel--static',
    fading: 'dim-panel--fading',
    arrowoff: 'dim-panel__arrow--off',
    dragging: 'dim-panel__rail--dragging',
};

/** @type {HTMLElement|null} The areas section of this page. */
let areas = null;

/** @type {HTMLElement|null} The panel's list item, the one element every panel is drawn into. */
let panel = null;

/** @type {HTMLElement|null} The live region the panel's outcome is announced through. */
let status = null;

/**
 * @type {Object} What the client that armed the panels handed in ({@see arm}): how an area's rows
 * are asked for, the question a request would ask, what is painted once the rows are on screen,
 * what a failure becomes, whether the posters stay links, and how long a request waits for quiet.
 */
const client = {
    ask: () => Promise.reject(new Error('The panels are not armed.')),
    question: () => '',
    painted: () => null,
    unanswered: () => false,
    links: false,
    quiet: 0,
};

/**
 * @type {Object} What is open: the area, its order and who opened it (the reader, 'user', or the
 * automatic opening on a single match, 'auto'); the latest request's ticket, so an answer to a
 * question somebody has since changed is thrown away; the question that request asked and the
 * ticket it was asked under, so the same question about the same open panel is not asked twice;
 * the turn, counting every opening and closing, so a step of an opening that has been superseded
 * does nothing; the paint, counting the transplants started, so only the latest one writes; the
 * skeleton's timer; whether nothing matches anywhere with no panel open, as the latest criteria
 * said it; and whether each area opens, by id, as they said it too - the one poster a flip must
 * wait for is the open one.
 */
const state = {
    open: 0,
    psort: '',
    openedby: '',
    ticket: 0,
    asked: '',
    askedticket: -1,
    turn: 0,
    paint: 0,
    skeleton: null,
    allempty: false,
    expandable: new Map(),
};

/** @type {Boolean} Whether the pointer that just went up was dragging the rail. */
let dragged = false;

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
 * Whether the reader has asked for less movement.
 *
 * @return {Boolean}
 */
const reduced = () => window.matchMedia(MOTION).matches;

/**
 * One poster of this page by its area's id.
 *
 * @param {Number} id The area.
 * @return {HTMLElement|null}
 */
const posterOf = (id) => {
    if (!areas || !Number.isInteger(id) || id <= 0) {
        return null;
    }
    return areas.querySelector(SELECTORS.area + '[data-categoryid="' + id + '"]');
};

/**
 * The grid item a poster sits in.
 *
 * @param {HTMLElement} poster The poster.
 * @return {HTMLElement}
 */
const itemOf = (poster) => poster.parentElement;

/**
 * Whether a poster opens a panel at all. One with nothing to open is a link to the area's page.
 *
 * @param {HTMLElement|null} poster The poster.
 * @return {Boolean}
 */
const isExpandable = (poster) => Boolean(poster) && poster.dataset.expandable === '1';

/**
 * Whether this page is the root of the course index, where no course sits directly.
 *
 * @return {Boolean}
 */
const isRoot = () => Number(areas.dataset.categoryid) === 0;

/**
 * Whether the page is in result mode: a criterion narrows it and it has areas. The server's own
 * switch (category_listing::results()), written on the areas section and rewritten from every answer.
 *
 * @return {Boolean}
 */
const isFiltered = () => areas.dataset.filtered === '1';

/**
 * Whether a poster's area holds no result under the page's criterion, which is what sends it to the summary.
 *
 * @param {HTMLElement|null} poster The poster.
 * @return {Boolean}
 */
const isZeroed = (poster) => Boolean(poster) && isFiltered() && Number(poster.dataset.count) === 0;

/**
 * The term the page was searched for, which the rail's titles are marked with - the root's too,
 * whose one criterion it is.
 *
 * @return {String}
 */
const termOf = () => new URL(window.location.href).searchParams.get(PARAM.query) || '';

/**
 * The control of a poster that opens its panel in place, or null.
 *
 * For the logged-in client it is the button the poster's link was turned into. A client that
 * keeps the posters links (the visitor's) drives the link of a poster that opens, which then
 * still goes to its address for a press meant for another tab or window.
 *
 * @param {HTMLElement|null} poster The poster.
 * @return {HTMLElement|null}
 */
const driven = (poster) => {
    const control = poster ? poster.querySelector(SELECTORS.control) : null;
    if (!control) {
        return null;
    }
    if (control.tagName === 'BUTTON') {
        return control;
    }
    return client.links && control.tagName === 'A' && isExpandable(poster) ? control : null;
};

/**
 * Whether a click on a link was meant for another tab or window, which the link's own address answers.
 *
 * A middle press and a secondary one arrive as events of their own (auxclick, contextmenu) and
 * never reach a click listener; a press with a modifier and one another listener has already
 * dealt with do.
 *
 * @param {MouseEvent} event The click.
 * @return {Boolean}
 */
const elsewhere = (event) => event.defaultPrevented || event.button !== 0
    || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey;

/**
 * Replace a link by a button of the same class, children and data attributes.
 *
 * The page drew links, because that is what works without this module; with it the same controls
 * change something on the page and are buttons. The children move over, so the name and the glyph
 * are the server's own and nothing is composed.
 *
 * @param {HTMLElement} link The link.
 * @param {Object} attributes The attributes the button carries besides its class and data attributes.
 * @return {HTMLElement} The button.
 */
const toButton = (link, attributes) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = link.className;
    Object.keys(link.dataset).forEach((key) => {
        button.dataset[key] = link.dataset[key];
    });
    Object.keys(attributes).forEach((name) => {
        button.setAttribute(name, attributes[name]);
    });
    button.append(...link.childNodes);
    link.replaceWith(button);
    return button;
};

/**
 * Replace a poster's button by the link it was, to the area's own page: toButton() the other way.
 *
 * An area with no course at rest is a link to its page, and a filter cleared in place makes one of
 * a poster that was a button while a criterion opened it (its panel said "no results"). Only the
 * class, the data attributes and the pill's description travel; the button's state does not belong
 * on a link.
 *
 * @param {HTMLElement} button The poster's button.
 * @param {String} href The area's own page.
 * @return {HTMLElement} The link.
 */
const toLink = (button, href) => {
    const link = document.createElement('a');
    link.className = button.className;
    link.href = href;
    Object.keys(button.dataset).forEach((key) => {
        link.dataset[key] = button.dataset[key];
    });
    const described = button.getAttribute('aria-describedby');
    if (described) {
        link.setAttribute('aria-describedby', described);
    }
    link.append(...button.childNodes);
    button.replaceWith(link);
    return link;
};

/**
 * Turn an expandable poster's link into the control that opens its panel.
 *
 * For the logged-in client the link becomes a button. toButton() copies the data attributes
 * alone, so the pill's description is handed over by name: a poster drawn in result mode is
 * described by its pill, and a button without it would stop saying how many results the area
 * holds. A client keeping the posters links has the link announced as the button it now acts as
 * - its state and the panel it controls - while it keeps its address.
 *
 * @param {HTMLElement} poster The poster.
 * @return {void}
 */
const convertPoster = (poster) => {
    const control = poster.querySelector(SELECTORS.control);
    if (!control || control.tagName === 'BUTTON' || !isExpandable(poster)) {
        return;
    }
    const id = Number(poster.dataset.categoryid);
    const attributes = {
        'aria-expanded': id === state.open ? 'true' : 'false',
        'aria-controls': PANEL_ID,
    };
    if (client.links) {
        attributes.role = 'button';
        Object.keys(attributes).forEach((name) => control.setAttribute(name, attributes[name]));
        return;
    }
    const described = control.getAttribute('aria-describedby');
    if (described) {
        attributes['aria-describedby'] = described;
    }
    toButton(control, attributes);
};

/**
 * Make a poster open a panel, or make it the link to its area's page again.
 *
 * A page loaded under a criterion draws every poster that can open (an area with nothing matching
 * opens its "no results" panel), and a criterion cleared in place brings back the areas with no
 * course at all, which are links reading "No courses yet" again - not buttons opening an empty
 * panel. Everything that says which one a poster is flips together: its class, its attribute, the
 * note naming the link's destination, the trigger's word and the control itself. The open poster
 * is left as it is until it closes, so the panel the reader has open never loses its control. A
 * client keeping the posters links leaves the link in place and takes its button state off it.
 *
 * @param {HTMLElement} poster The poster.
 * @param {Boolean} on Whether it opens a panel.
 * @return {void}
 */
const setExpandable = (poster, on) => {
    if (isExpandable(poster) === on || Number(poster.dataset.categoryid) === state.open) {
        return;
    }
    poster.dataset.expandable = on ? '1' : '0';
    poster.classList.toggle(CLASSES.posterempty, !on);
    const note = poster.querySelector(SELECTORS.emptynote);
    if (note) {
        note.hidden = on;
    }
    const trigger = poster.querySelector(SELECTORS.trigger);
    const word = trigger ? trigger.dataset[on ? 'open' : 'empty'] : '';
    if (trigger && word) {
        trigger.textContent = word;
    }
    const control = poster.querySelector(SELECTORS.control);
    const focused = Boolean(control) && document.activeElement === control;
    if (on) {
        convertPoster(poster);
    } else if (control && control.tagName === 'BUTTON' && poster.dataset.titleurl) {
        toLink(control, poster.dataset.titleurl);
    } else if (control && client.links) {
        ['role', 'aria-expanded', 'aria-controls'].forEach((name) => control.removeAttribute(name));
    }
    const now = poster.querySelector(SELECTORS.control);
    if (focused && now && now !== control) {
        now.focus({preventScroll: true});
    }
};

/**
 * Turn the panel's no-JavaScript links into buttons: the two orders a radio group, Close a button,
 * and a zero panel's suggestions buttons that open their area in place.
 *
 * Only a panel the server drew has links; the ones this module renders are buttons already.
 *
 * @return {void}
 */
const convertPanel = () => {
    panel.querySelectorAll('a' + SELECTORS.openarea).forEach((link) => {
        toButton(link, {});
    });
    const sort = panel.querySelector(SELECTORS.sort);
    if (sort) {
        sort.setAttribute('role', 'radiogroup');
        sort.querySelectorAll('a' + SELECTORS.sortoption).forEach((link) => {
            const checked = link.getAttribute('aria-current') === 'true';
            const button = toButton(link, {
                'role': 'radio',
                'aria-checked': checked ? 'true' : 'false',
                'tabindex': checked ? '0' : '-1',
            });
            button.removeAttribute('aria-current');
        });
    }
    const close = panel.querySelector('a' + SELECTORS.close);
    if (close) {
        toButton(close, {'aria-label': close.getAttribute('aria-label') || ''});
    }
};

/**
 * Put a poster in or out of the open state: its class, its control's state and its trigger's word.
 *
 * @param {HTMLElement|null} poster The poster.
 * @param {Boolean} on Whether it is the open one.
 * @return {void}
 */
const setPosterOpen = (poster, on) => {
    if (!poster) {
        return;
    }
    poster.classList.toggle(CLASSES.posteropen, on);
    const control = driven(poster);
    if (control) {
        control.setAttribute('aria-expanded', on ? 'true' : 'false');
    }
    const trigger = poster.querySelector(SELECTORS.trigger);
    const word = trigger ? trigger.dataset[on ? 'close' : 'open'] : '';
    if (trigger && word) {
        trigger.textContent = word;
    }
};

/**
 * Keep one hidden field of a form in step with the panel: created, changed or removed.
 *
 * @param {HTMLElement|null} form The form.
 * @param {String} selector The field's hook.
 * @param {String} name The field's name.
 * @param {String} value Its value, or the empty string for no field at all.
 * @return {void}
 */
const syncField = (form, selector, name, value) => {
    if (!form) {
        return;
    }
    let input = form.querySelector(selector);
    if (value === '') {
        if (input) {
            input.remove();
        }
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
 * Keep the three other holders of the panel in step with the address.
 *
 * The toolbar's form (its no-JavaScript submit), the hero's fold form (folding keeps the panel)
 * and the clear link and the chips under the filter line (clearing the filters, or one of them,
 * keeps the panel, as the server's own addresses do). Each would otherwise reload the page with the
 * panel the reader had when it loaded.
 *
 * @return {void}
 */
const syncHolders = () => {
    const open = state.open > 0 ? String(state.open) : '';
    const psort = state.open > 0 && state.psort === PSORT_NAME ? PSORT_NAME : '';
    [document.querySelector(SELECTORS.toolbar), document.querySelector(SELECTORS.fold)].forEach((form) => {
        syncField(form, SELECTORS.openfield, PARAM.open, open);
        syncField(form, SELECTORS.psortfield, PARAM.psort, psort);
    });
    const links = [document.querySelector(SELECTORS.clear)].concat(Array.from(document.querySelectorAll(SELECTORS.chip)));
    links.forEach((link) => {
        if (!link || !link.getAttribute('href')) {
            return;
        }
        const url = new URL(link.getAttribute('href'), window.location.href);
        if (open !== '') {
            url.searchParams.set(PARAM.open, open);
        } else {
            url.searchParams.delete(PARAM.open);
        }
        if (psort !== '') {
            url.searchParams.set(PARAM.psort, psort);
        } else {
            url.searchParams.delete(PARAM.psort);
        }
        link.href = url.toString();
    });
};

/**
 * Write the open area and its order into the address, replacing this history entry.
 *
 * Every other parameter is left exactly as it is - the listing's module owns those - and so is the
 * history: opening a panel is not a new state to go back through, and replaceState is what keeps a
 * reload, a shared link and the back button all agreeing with the page.
 *
 * @return {void}
 */
const writeAddress = () => {
    const url = new URL(window.location.href);
    if (state.open > 0) {
        url.searchParams.set(PARAM.open, String(state.open));
    } else {
        url.searchParams.delete(PARAM.open);
    }
    if (state.open > 0 && state.psort === PSORT_NAME) {
        url.searchParams.set(PARAM.psort, PSORT_NAME);
    } else {
        url.searchParams.delete(PARAM.psort);
    }
    // The no-JavaScript fragments are this page's own; a stale one would scroll a reload there.
    if (url.hash === '#' + PANEL_ID || url.hash === '#' + NOMATCH_ID || url.hash.startsWith('#dim-area-')) {
        url.hash = '';
    }
    window.history.replaceState(window.history.state, '', url.toString());
    syncHolders();
};

/**
 * Move a node right after another, keeping the focus where it was when it was inside.
 *
 * moveBefore() moves an element without taking it out of the document, so the focus, a running
 * transition and a playing film survive; where it does not exist, insertBefore() moves it and the
 * focus is put back by hand.
 *
 * @param {HTMLElement} node The node to move.
 * @param {HTMLElement} ref The node it goes after.
 * @return {void}
 */
const moveAfter = (node, ref) => {
    const parent = ref.parentElement;
    const next = ref.nextElementSibling;
    if (next === node) {
        return;
    }
    const active = document.activeElement;
    const inside = node.contains(active);
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
 * Place the panel: its row by the five integers, its colour by the poster's, its DOM order after the last poster of the row.
 *
 * The integers are the open poster's place among the visible posters of its grid: the row after
 * its own at three, two and one column, and the column the arrow points at. The stylesheet reads
 * the one for its breakpoint, so a resize needs nothing from here. The DOM order does, and is set
 * from the laid-out page: the last poster sharing the open poster's row.
 *
 * @return {void}
 */
const place = () => {
    const poster = posterOf(state.open);
    if (!poster || !panel) {
        return;
    }
    const item = itemOf(poster);
    const grid = item.parentElement;
    if (panel.parentElement !== grid) {
        moveAfter(panel, item);
    }
    const items = Array.from(grid.children).filter((el) => el !== panel && !el.hidden && el.querySelector(SELECTORS.area));
    const index = Math.max(0, items.indexOf(item));
    panel.style.setProperty('--dim-row-3', String(Math.floor(index / 3) + 2));
    panel.style.setProperty('--dim-row-2', String(Math.floor(index / 2) + 2));
    panel.style.setProperty('--dim-row-1', String(index + 2));
    panel.style.setProperty('--dim-col-3', String(index % 3));
    panel.style.setProperty('--dim-col-2', String(index % 2));
    /* The open area's band paints the panel's rail, head and glow: copied from the poster's own
       style, which the server built through its one colour sanitiser, so the panel wears exactly
       the colour of the poster it opens under (the server writes the same on a panel it drew). */
    const band = poster.style.getPropertyValue('--dim-card-band').trim();
    if (band !== '') {
        panel.style.setProperty('--dim-area-bg', band);
    }
    const top = item.offsetTop;
    let last = item;
    items.forEach((el) => {
        if (Math.abs(el.offsetTop - top) < 2) {
            last = el;
        }
    });
    moveAfter(panel, last);
};

/**
 * The fixed navbar's height, as the stylesheet declares it.
 *
 * @return {Number} Pixels.
 */
const navbar = () => parseFloat(window.getComputedStyle(areas).getPropertyValue('--dim-navbar')) || 0;

/**
 * If the panel does not fit on screen, scroll until its top sits under the navbar with a gap.
 *
 * The promise is the scroll's: a smooth one moves the posters for a few hundred milliseconds, and a
 * press aimed at one while it moves lands wherever the page has gone, so the opening holds a
 * pending token until it settles ({@see openArea}). A scroll that never reports its end - one the
 * page has no room for, or a browser without scrollend - is taken to be over after SCROLL_END.
 *
 * @return {Promise} Resolving once the page has stopped moving, at once when it does not move.
 */
const ensureVisible = () => new Promise((resolve) => {
    if (!state.open || !panel || panel.hidden) {
        resolve();
        return;
    }
    const rect = panel.getBoundingClientRect();
    const bar = navbar();
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
 * @param {String} text The sentence.
 * @return {void}
 */
const announce = (text) => {
    if (!status) {
        return;
    }
    status.textContent = '';
    window.requestAnimationFrame(() => {
        status.textContent = text;
    });
};

/**
 * The call to action's address for the order the panel is in: the shell's, with its sort swapped.
 *
 * @param {HTMLElement} poster The poster whose shell it is.
 * @return {String}
 */
const ctaUrl = (poster) => {
    const href = poster.dataset.ctaurl || '';
    if (href === '' || state.psort !== PSORT_NAME) {
        return href;
    }
    const url = new URL(href, window.location.href);
    url.searchParams.set('sort', PSORT_NAME);
    return url.toString();
};

/**
 * The panel's context before its rows arrive: the shell the poster carries, and one state.
 *
 * Nothing here is composed: the name, the title's address and the call to action come off the
 * poster, written there by the server (and rewritten after a filter by the listing's module), and
 * every sentence is a string of the template.
 *
 * @param {HTMLElement} poster The poster.
 * @param {Object} flags One of isloading, isskeleton or iserror.
 * @return {Object} The template context.
 */
const shellContext = (poster, flags) => {
    const titleurl = poster.dataset.titleurl || '';
    const cta = ctaUrl(poster);
    return Object.assign({
        id: Number(poster.dataset.categoryid),
        name: poster.dataset.name || '',
        titleurl: titleurl,
        haslink: titleurl !== '',
        isd20: false,
        subtitle: '',
        psortname: state.psort === PSORT_NAME,
        sorturlrecent: '',
        sorturlname: '',
        closeurl: '',
        also: {has: false, pills: [], hasmore: false, morecount: '', moreurl: ''},
        hascards: false,
        few: false,
        counter: '',
        tile: {has: false, count: '', label: '', url: ''},
        isrows: false,
        isempty: false,
        iszero: false,
        zero: '',
        suggestions: [],
        hassuggestions: false,
        cta: {has: cta !== '', url: cta, label: poster.dataset.ctalabel || ''},
        seeaslist: {has: false, url: ''},
        jsmode: true,
        isloading: false,
        isskeleton: false,
        iserror: false,
        cards: [],
    }, flags);
};

/**
 * Put the panel's transition classes in the state one step starts from, whatever another step left.
 *
 * The crossfade (fading), the swap between rows (static) and the collapsed box (shut) each last
 * one transition, and the step that adds one takes it off when it finishes. A step superseded
 * before it finishes - a second press inside the crossfade, a close before the first render is
 * back - never gets there, and a class left behind draws an open panel invisible or collapsed to
 * nothing. So every step that starts a transition clears all three first.
 *
 * @param {...String} classes The transition classes the step starts with.
 * @return {void}
 */
const settle = (...classes) => {
    panel.classList.remove(CLASSES.shut, CLASSES.still, CLASSES.fading);
    panel.classList.add(...classes);
};

/**
 * Render the whole panel into its element: on an opening and on a move to another area.
 *
 * The render is asynchronous, so the check that the opening it belongs to is still the current
 * one is made after it resolves and before the document is touched: an opening superseded while
 * its render was out must not write its area's header over the newer one.
 *
 * @param {Object} context The template context.
 * @param {Number} turn The turn of the opening the render belongs to.
 * @return {Promise<Boolean>} Resolving true once it is in the document, false when it was superseded.
 */
const renderWhole = async(context, turn) => {
    const rendered = await Templates.renderForPromise(TEMPLATE, context);
    if (turn !== state.turn) {
        return false;
    }
    Templates.replaceNodeContents(panel, rendered.html, rendered.js);
    return true;
};

/**
 * Render a later state of the same area and transplant only what changes, keeping the header.
 *
 * The heading, the two orders and Close stay where they are, so the focus a reader has on one of
 * them stays too; the subtitle's text, the strip, the body and the footer are replaced. A focused
 * control inside a replaced region comes back by its focus key, else the focus goes to the title.
 *
 * It writes only if its request is still the latest and no transplant has been started since,
 * both checked after the render resolves: the skeleton's render and the answer's run side by side
 * when the answer lands just after the skeleton's delay, and without the second check whichever
 * finished last would own the body - a skeleton for good.
 *
 * @param {Object} context The template context.
 * @param {Number} ticket The ticket of the request the state belongs to.
 * @return {Promise<Boolean>} Resolving true once the regions are in the document, false when superseded.
 */
const transplant = async(context, ticket) => {
    const paint = ++state.paint;
    const rendered = await Templates.renderForPromise(TEMPLATE, context);
    if (ticket !== state.ticket || paint !== state.paint) {
        return false;
    }
    const holder = document.createElement('div');
    Templates.appendNodeContents(holder, rendered.html, rendered.js);
    const active = document.activeElement;
    const inside = Boolean(active) && panel.contains(active);
    const key = inside ? active.dataset.focuskey || '' : '';
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
    // The focus was on something replaced - a card's link, its star, a pill of the strip - and left with it.
    if (inside && !panel.contains(document.activeElement)) {
        const keyed = key !== '' ? panel.querySelector('[data-focuskey="' + key + '"]') : null;
        const target = keyed || panel.querySelector(SELECTORS.title);
        if (target) {
            target.focus({preventScroll: true});
        }
    }
    return true;
};

/**
 * Mark the two orders busy or not while a request is out: one question at a time.
 *
 * @param {Boolean} busy Whether a request is out.
 * @return {void}
 */
const setSortBusy = (busy) => {
    panel.querySelectorAll(SELECTORS.sortoption).forEach((option) => {
        option.setAttribute('aria-disabled', busy ? 'true' : 'false');
    });
};

/**
 * Paint the two orders: which one is checked, and which one the Tab key reaches.
 *
 * @return {void}
 */
const paintSort = () => {
    panel.querySelectorAll(SELECTORS.sortoption).forEach((option) => {
        const on = (option.dataset.psort === PSORT_NAME) === (state.psort === PSORT_NAME);
        option.setAttribute('aria-checked', on ? 'true' : 'false');
        option.tabIndex = on ? 0 : -1;
    });
};

/**
 * Update the rail's arrows and its phone counter from where the rail is scrolled to.
 *
 * @return {void}
 */
const updateRail = () => {
    const rail = panel ? panel.querySelector(SELECTORS.rail) : null;
    if (!rail) {
        return;
    }
    const max = rail.scrollWidth - rail.clientWidth;
    const prev = panel.querySelector(SELECTORS.prev);
    const next = panel.querySelector(SELECTORS.next);
    if (prev) {
        prev.classList.toggle(CLASSES.arrowoff, rail.scrollLeft <= 2);
    }
    if (next) {
        next.classList.toggle(CLASSES.arrowoff, rail.scrollLeft >= max - 2);
    }
    const counter = panel.querySelector(SELECTORS.counter);
    const cards = rail.querySelectorAll(SELECTORS.card);
    if (counter && cards.length) {
        const step = cards[0].parentElement.offsetWidth + RAIL_GAP;
        const at = Math.min(cards.length, Math.round(rail.scrollLeft / step) + 1);
        counter.textContent = at + ' / ' + cards.length;
    }
};

/**
 * Scroll the rail by one card and its gap, in either direction.
 *
 * @param {Number} direction -1 or 1.
 * @return {void}
 */
const scrollRail = (direction) => {
    const rail = panel.querySelector(SELECTORS.rail);
    const first = rail ? rail.firstElementChild : null;
    if (!first) {
        return;
    }
    rail.scrollBy({left: direction * (first.offsetWidth + RAIL_GAP), behavior: reduced() ? 'auto' : 'smooth'});
};

/**
 * The other areas of this page holding a result, which a zero panel suggests.
 *
 * They are page data, like the cards: the web service answers for one area and knows nothing of its
 * siblings, so they are read off the posters, in the posters' order, from what the server wrote on
 * each and every later answer rewrote - the id, the name, the count, the formatted count and the
 * pill's words, which are the button's spoken count (the digit alone would be read with no unit).
 *
 * @return {Array} One entry of id, name, count, countlabel and url per area; empty when nothing narrows the page.
 */
const suggestions = () => {
    if (!isFiltered()) {
        return [];
    }
    return Array.from(areas.querySelectorAll(SELECTORS.area))
        .filter((poster) => Number(poster.dataset.categoryid) !== state.open && Number(poster.dataset.count) > 0)
        .map((poster) => ({
            id: Number(poster.dataset.categoryid),
            name: poster.dataset.name || '',
            count: poster.dataset.resultcount || '',
            countlabel: poster.dataset.resultlabel || '',
            url: '',
        }));
};

/**
 * Whether the open panel already shows, or is being sent, the answer to this question.
 *
 * A client that names its question (the visitor's, whose every request is a read the site pays for
 * on the open internet) asks it once per opening: an answer to criteria that did not change - the
 * back button over a change of view, a panel the server drew for the address - is the one on
 * screen. A client that names none is asked every time.
 *
 * @param {String} question The question, or the empty string.
 * @return {Boolean}
 */
const answered = (question) => question !== '' && question === state.asked && state.askedticket === state.ticket;

/**
 * Wait for the client's quiet, when it names one: a reader pressing poster after poster asks once.
 *
 * @param {Number} ticket The ticket of the request that waits.
 * @return {Promise<Boolean>} Resolving true when the request is still the latest once the quiet is over.
 */
const quiet = async(ticket) => {
    if (client.quiet > 0) {
        await new Promise((resolve) => window.setTimeout(resolve, client.quiet));
    }
    return ticket === state.ticket;
};

/**
 * The panel's context from an answer: the server's panel, its cards and the page's suggestions.
 *
 * An answer carrying no panel, or another area's, is not this panel's: the client's question was
 * dropped by the server (a visitor's slice of a child the page no longer draws as one), which is a
 * failure here like any other.
 *
 * @param {Object} response The answer.
 * @param {Number} id The area asked about.
 * @return {Object} The template context.
 */
const answerContext = (response, id) => {
    if (!response.panel || Number(response.panel.id) !== id) {
        throw new Error('The answer carries no panel of this area.');
    }
    const offered = suggestions();
    return Object.assign({}, response.panel, {
        cards: response.cards || [],
        jsmode: true,
        suggestions: offered,
        hassuggestions: offered.length > 0,
    });
};

/**
 * Ask for the open area's rows, and paint the answer unless something newer has been asked since.
 *
 * The body stays empty for SKELETON_DELAY past the client's quiet; a slower answer gets the
 * skeleton first. The answer is rows, the empty state or the zero state, all drawn by the template
 * from the server's context; a failure is the client's to take over first (the visitor's loads the
 * poster's own address), and otherwise the inline error with its retry, never a dialogue - the call
 * to action to the area's page is still there, drawn from the poster's shell. A zero panel is
 * announced as two sentences of the server's: its own and the page's word on where the results are.
 *
 * @return {Promise} Resolving when the request has been dealt with.
 */
const request = async() => {
    const id = state.open;
    const poster = posterOf(id);
    if (!poster) {
        return;
    }
    const question = client.question(poster, state.psort);
    if (answered(question)) {
        return;
    }
    const ticket = ++state.ticket;
    state.asked = question;
    state.askedticket = ticket;
    // The quiet is a timer no request covers yet: held from here until the answer is painted.
    const pending = new Pending('theme_dimensions/categoryareapanel:request');
    window.clearTimeout(state.skeleton);
    setSortBusy(true);
    state.skeleton = window.setTimeout(() => {
        if (ticket === state.ticket) {
            transplant(shellContext(poster, {isskeleton: true}), ticket);
        }
    }, SKELETON_DELAY + client.quiet);
    try {
        if (!(await quiet(ticket))) {
            return;
        }
        const response = await client.ask(poster, state.psort);
        if (ticket !== state.ticket) {
            return;
        }
        window.clearTimeout(state.skeleton);
        const context = answerContext(response, id);
        if (!(await transplant(context, ticket))) {
            return;
        }
        CategoryMark.mark(panel, termOf());
        // The rows say how many; the empty state says it in the body's own sentence, the zero one in two.
        const body = panel.querySelector(SELECTORS.body);
        let said = context.subtitle;
        if (context.iszero) {
            said = (String(context.zero || '') + ' ' + (areas.dataset.elsewhere || '')).trim();
        } else if (!context.isrows && body) {
            said = body.textContent.trim().replace(/\s+/g, ' ');
        }
        announce(said);
        updateRail();
        client.painted(panel);
    } catch (error) {
        if (ticket !== state.ticket) {
            return;
        }
        window.clearTimeout(state.skeleton);
        state.asked = '';
        if (client.unanswered(poster, state.psort)) {
            return;
        }
        if (!(await transplant(shellContext(poster, {iserror: true}), ticket))) {
            return;
        }
        const subtitle = panel.querySelector(SELECTORS.subtitle);
        announce(subtitle ? subtitle.textContent.trim() : '');
    } finally {
        if (ticket === state.ticket) {
            setSortBusy(false);
        }
        pending.resolve();
    }
};

/**
 * Put the focus on the panel's heading, without scrolling and without asking for a ring.
 *
 * The browser decides whether the ring shows: a keyboard reader gets it, a pointer does not.
 *
 * @return {void}
 */
const focusTitle = () => {
    const title = panel.querySelector(SELECTORS.title);
    if (title) {
        title.focus({preventScroll: true});
    }
};

/**
 * Hide the hint under the grid: the reader has found what it says.
 *
 * @return {void}
 */
const hideHint = () => {
    const hint = areas.querySelector(SELECTORS.hint);
    if (hint) {
        hint.hidden = true;
    }
};

/**
 * Bring the "nothing in N areas" summary in line with the open panel ({@see Posters.recountSummary}).
 *
 * @return {void}
 */
const recountSummary = () => Posters.recountSummary(areas, state.open);

/**
 * Hide the reveal past the slice when no poster behind it is left in its grid ({@see Posters.fitReveal}).
 *
 * @return {void}
 */
const fitReveal = () => Posters.fitReveal(areas);

/**
 * Show the "nothing matches anywhere" box exactly when the criteria said so and no panel is open.
 *
 * @return {void}
 */
const paintAllEmpty = () => Posters.paintAllEmpty(areas, state.allempty, state.open);

/**
 * Put the focus on the summary's own control, where a closed zeroed area has just gone.
 *
 * @return {void}
 */
const focusSummary = () => {
    const summary = areas.querySelector(SELECTORS.nomatchsummary);
    if (summary) {
        summary.focus({preventScroll: true});
    }
};

/**
 * Give a poster the opening state the latest criteria gave it, once it is no longer the open one.
 *
 * @param {HTMLElement|null} poster The poster.
 * @return {void}
 */
const applyExpandable = (poster) => {
    const id = poster ? Number(poster.dataset.categoryid) : 0;
    if (state.expandable.has(id)) {
        setExpandable(poster, state.expandable.get(id));
    }
};

/**
 * Put an area's poster where its panel can be seen: the reveal around it shown and open, its item in the grid.
 *
 * @param {HTMLElement} poster The poster.
 * @return {void}
 */
const unveil = (poster) => {
    const reveal = poster.closest(SELECTORS.more);
    if (reveal) {
        reveal.hidden = false;
        reveal.open = true;
    }
    itemOf(poster).hidden = false;
};

/**
 * Whether the panel moves inside one row: the open poster and the next one share a grid and a line.
 *
 * A zeroed open poster never shares one: it leaves its grid on the move, so the row it shared may
 * be gone by the time the panel lands.
 *
 * @param {HTMLElement|null} prev The poster whose panel is open, or null.
 * @param {HTMLElement} poster The poster being opened.
 * @return {Boolean}
 */
const sharesRow = (prev, poster) => Boolean(prev) && !isZeroed(prev) && !panel.hidden
    && itemOf(prev).parentElement === itemOf(poster).parentElement
    && Math.abs(itemOf(prev).offsetTop - itemOf(poster).offsetTop) < 2;

/**
 * Open one area's panel, closing the one that was open: one panel at a time.
 *
 * In the same row the content crossfades and the arrow slides; in another row the panel moves
 * with every transition suppressed, and the page is scrolled by however far the poster moved, so
 * the poster the reader pressed stays under their pointer.
 *
 * An open area the criterion zeroed stays in its grid only while it is open: moving
 * to another area sends it to the summary first, so the row it shared may be gone and the move is
 * always treated as one to another row. An automatic opening leaves the hint where it is - the
 * reader has not yet found what it says - and records who opened the panel.
 *
 * @param {Number} id The area.
 * @param {Object} options focus (the title takes the focus), scroll (the scroll rule runs), psort
 *     (the order to open in, from the address), write (whether the address is written) and auto
 *     (whether it was opened on a single match rather than by the reader).
 * @return {Promise} Resolving when the panel is on its way.
 */
const openArea = async(id, options) => {
    const poster = posterOf(id);
    if (!isExpandable(poster) || id === state.open) {
        return;
    }
    unveil(poster);
    const prev = state.open ? posterOf(state.open) : null;
    const before = poster.getBoundingClientRect().top;
    const prevhides = isZeroed(prev);
    const sameRow = sharesRow(prev, poster);
    setPosterOpen(prev, false);
    setPosterOpen(poster, true);
    if (prevhides) {
        itemOf(prev).hidden = true;
        fitReveal();
    }
    state.open = id;
    state.openedby = options.auto ? 'auto' : 'user';
    state.psort = options.psort || '';
    state.ticket += 1;
    const turn = ++state.turn;
    applyExpandable(prev);
    recountSummary();
    paintAllEmpty();
    areas.classList.add(CLASSES.areasopen);
    if (!options.auto) {
        hideHint();
    }
    if (options.write !== false) {
        writeAddress();
    }
    const shell = shellContext(poster, {isloading: true});
    if (sameRow && !reduced()) {
        place();
        settle(CLASSES.fading);
        // Held until the request takes over with its own, so Behat never sees the crossfade as settled.
        const crossfade = new Pending('theme_dimensions/categoryareapanel:crossfade');
        window.setTimeout(async() => {
            try {
                // A step superseded here leaves the classes to the step that superseded it, which settled them.
                if (turn !== state.turn || !(await renderWhole(shell, turn))) {
                    return;
                }
                panel.classList.remove(CLASSES.fading);
                if (options.focus) {
                    focusTitle();
                }
                request();
            } finally {
                crossfade.resolve();
            }
        }, MOTION_FAST);
        return;
    }
    settle(CLASSES.still, CLASSES.shut);
    panel.hidden = false;
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
    // The layout is read once more so the shut state is the one the transition starts from.
    panel.getBoundingClientRect();
    window.requestAnimationFrame(() => {
        if (turn !== state.turn) {
            return;
        }
        panel.classList.remove(CLASSES.still);
        window.requestAnimationFrame(() => {
            if (turn === state.turn) {
                panel.classList.remove(CLASSES.shut);
            }
        });
    });
    if (options.focus) {
        focusTitle();
    }
    request();
    if (options.scroll !== false) {
        const scroll = new Pending('theme_dimensions/categoryareapanel:scroll');
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
 * Close the open panel: it collapses, then leaves the document's flow; the focus goes back.
 *
 * An area the criterion zeroed leaves the grid as it closes and joins the summary, so the focus
 * that would have gone back to its poster goes to the summary instead: the poster is no longer on
 * screen to take it.
 *
 * @param {Boolean} focus Whether the focus returns to the poster's control.
 * @param {Boolean} write Whether the address is written.
 * @return {void}
 */
const closeArea = (focus, write) => {
    if (!state.open) {
        return;
    }
    const poster = posterOf(state.open);
    const zeroed = isZeroed(poster);
    setPosterOpen(poster, false);
    state.open = 0;
    state.openedby = '';
    state.psort = '';
    state.ticket += 1;
    state.turn += 1;
    window.clearTimeout(state.skeleton);
    if (zeroed) {
        itemOf(poster).hidden = true;
        fitReveal();
    }
    applyExpandable(poster);
    recountSummary();
    paintAllEmpty();
    areas.classList.remove(CLASSES.areasopen);
    settle(CLASSES.shut);
    const hide = () => {
        if (!state.open) {
            panel.hidden = true;
            panel.replaceChildren();
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
    const control = poster ? poster.querySelector(SELECTORS.control) : null;
    if (focus && zeroed) {
        focusSummary();
    } else if (focus && control) {
        control.focus({preventScroll: true});
    }
};

/**
 * Open a poster's panel, or close it when it is the open one: what a press on its control does.
 *
 * @param {HTMLElement} poster The poster.
 * @return {void}
 */
const toggleArea = (poster) => {
    const id = Number(poster.dataset.categoryid);
    if (id === state.open) {
        closeArea(true, true);
    } else {
        openArea(id, {focus: true});
    }
};

/**
 * Change the open panel's order, and ask for it.
 *
 * @param {String} psort The empty string (the default, recent) or name.
 * @return {void}
 */
const setPsort = (psort) => {
    const value = psort === PSORT_NAME ? PSORT_NAME : '';
    if (!state.open || state.psort === value || panel.querySelector(SELECTORS.sortoption + '[aria-disabled="true"]')) {
        return;
    }
    state.psort = value;
    paintSort();
    writeAddress();
    request();
};

/**
 * Deal with a press inside the areas: a poster's control opens or closes its panel.
 *
 * A poster that stays a link still goes to its address for a press meant for another tab or
 * window, exactly as it does without this module.
 *
 * @param {Event} event The click.
 * @return {void}
 */
const onAreasClick = (event) => {
    const control = closestOf(event, SELECTORS.control);
    if (!control || panel.contains(control)) {
        return;
    }
    const poster = control.closest(SELECTORS.area);
    if (!isExpandable(poster) || driven(poster) !== control) {
        return;
    }
    if (control.tagName === 'A' && elsewhere(event)) {
        return;
    }
    event.preventDefault();
    toggleArea(poster);
};

/**
 * Deal with a press inside the panel: the orders, Close, a zero panel's suggestions, the retry and
 * the rail's arrows.
 *
 * A suggestion acts as a button only, like the orders: the server's link reloads the page with that
 * area open, which is what it should do wherever this module has not converted it.
 *
 * @param {Event} event The click.
 * @return {void}
 */
const onPanelClick = (event) => {
    const option = closestOf(event, SELECTORS.sortoption);
    if (option && option.tagName === 'BUTTON') {
        event.preventDefault();
        setPsort(option.dataset.psort || '');
        return;
    }
    const suggestion = closestOf(event, SELECTORS.openarea);
    if (suggestion && suggestion.tagName === 'BUTTON') {
        event.preventDefault();
        openArea(Number(suggestion.dataset.categoryid), {focus: true});
        return;
    }
    const close = closestOf(event, SELECTORS.close);
    if (close && close.tagName === 'BUTTON') {
        event.preventDefault();
        closeArea(true, true);
        return;
    }
    if (closestOf(event, SELECTORS.retry)) {
        event.preventDefault();
        request();
        return;
    }
    if (closestOf(event, SELECTORS.prev)) {
        scrollRail(-1);
        return;
    }
    if (closestOf(event, SELECTORS.next)) {
        scrollRail(1);
    }
};

/**
 * Space on a poster that stays a link opens or closes its panel, as it would a button's.
 *
 * The link is announced as a button, and a button answers Space; a link alone would scroll the
 * page. Enter needs nothing here: on a link it is a click.
 *
 * @param {KeyboardEvent} event The key.
 * @return {Boolean} Whether the key was a poster's.
 */
const onSpace = (event) => {
    if (event.key !== ' ' || event.repeat || !client.links) {
        return false;
    }
    const control = closestOf(event, SELECTORS.control);
    const poster = control ? control.closest(SELECTORS.area) : null;
    if (!control || control.tagName !== 'A' || panel.contains(control) || driven(poster) !== control) {
        return false;
    }
    event.preventDefault();
    toggleArea(poster);
    return true;
};

/**
 * Escape closes the open panel from inside it or from its poster; the arrows move the order.
 *
 * Not from the search box, where Escape empties the field, and not from the film's controls,
 * where it stops the film: the panel must not close under either.
 *
 * @param {KeyboardEvent} event The key.
 * @return {void}
 */
const onKeydown = (event) => {
    if (event.key === 'Escape' && state.open) {
        const active = document.activeElement;
        if (!(active instanceof Element) || active.matches(SELECTORS.search) || active.closest(SELECTORS.covercontrols)) {
            return;
        }
        const poster = posterOf(state.open);
        if (panel.contains(active) || (poster && poster.contains(active))) {
            event.preventDefault();
            closeArea(true, true);
        }
        return;
    }
    if (onSpace(event)) {
        return;
    }
    const option = closestOf(event, SELECTORS.sortoption);
    const arrows = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'];
    if (option && option.tagName === 'BUTTON' && arrows.indexOf(event.key) >= 0) {
        event.preventDefault();
        setPsort(state.psort === PSORT_NAME ? '' : PSORT_NAME);
        const checked = panel.querySelector(SELECTORS.sortoption + '[aria-checked="true"]');
        if (checked) {
            checked.focus();
        }
    }
};

/**
 * A mouse drag of the rail scrolls it, and the click that ends a drag is not a click.
 *
 * @param {PointerEvent} event The press.
 * @return {void}
 */
const onPointerDown = (event) => {
    if (event.pointerType !== 'mouse' || event.button !== 0) {
        return;
    }
    const rail = closestOf(event, SELECTORS.rail);
    if (!rail || rail.scrollWidth <= rail.clientWidth + 2) {
        return;
    }
    const startx = event.clientX;
    const startleft = rail.scrollLeft;
    let moved = false;
    const move = (step) => {
        const dx = step.clientX - startx;
        if (!moved && Math.abs(dx) > DRAG_THRESHOLD) {
            moved = true;
            rail.classList.add(CLASSES.dragging);
        }
        if (moved) {
            rail.scrollLeft = startleft - dx;
        }
    };
    const up = () => {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', up);
        if (moved) {
            dragged = true;
            rail.classList.remove(CLASSES.dragging);
            window.setTimeout(() => {
                dragged = false;
            }, 0);
        }
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
};

/**
 * The back and forward buttons: open, close, or nothing when the page is already in that state.
 *
 * @return {void}
 */
const onPopState = () => {
    const params = new URL(window.location.href).searchParams;
    const id = Number(params.get(PARAM.open)) || 0;
    const psort = params.get(PARAM.psort) === PSORT_NAME ? PSORT_NAME : '';
    if (id === state.open && psort === state.psort) {
        return;
    }
    if (id === 0) {
        /* A focus inside the panel would be deleted with it, and one on the open poster may leave the
           grid with it when the area is zeroed: closeArea() puts either on the poster or the summary. */
        const active = document.activeElement;
        const open = posterOf(state.open);
        closeArea(panel.contains(active) || (Boolean(open) && open.contains(active)), false);
        syncHolders();
        return;
    }
    if (id === state.open) {
        state.psort = psort;
        paintSort();
        syncHolders();
        request();
        return;
    }
    if (isExpandable(posterOf(id))) {
        openArea(id, {focus: false, scroll: false, psort: psort, write: false});
        syncHolders();
    }
};

/**
 * Re-place the panel after a breakpoint or a view change: its row and its DOM order.
 *
 * @return {void}
 */
const relayout = () => {
    if (!state.open) {
        return;
    }
    place();
    updateRail();
};

/**
 * Open the one area holding every result, when the answer names one: the logged-in client's call.
 *
 * Three guards: the focus stays where the reader is typing, a panel the reader
 * opened is never swapped for another, and nothing is ever closed by this - a later answer that is
 * no longer a single match leaves the panel open, under the open-stays rule. Only with a client
 * module: without one the page's own links say the same.
 *
 * @param {Object} data The answer's word on the areas as a whole: autoopen, the area's id or 0.
 * @return {void}
 */
export const autoOpen = (data) => {
    const id = Number(data.autoopen) || 0;
    if (!id || id === state.open || (state.open && state.openedby === 'user')) {
        return;
    }
    openArea(id, {focus: false, scroll: false, auto: true});
    if (state.open === id) {
        document.dispatchEvent(new CustomEvent(EVENTS.autoopened, {detail: {id: id}}));
    }
};

/**
 * The criteria's answer arrived: the result mode is written onto the areas, in one order.
 *
 * The grids stop being busy; each poster takes its pill, its counts and whether it opens; the zeroed
 * areas leave their grids; the summary, the reveal and the "nothing anywhere" box follow; and the
 * panel is placed among the posters still shown and asked again, after the posters carry the new
 * numbers because its zero state's suggestions are read off them. The count line was painted by the
 * client's listing before it handed the answer over, so it speaks first.
 *
 * @param {Object} detail The answer's accordion and its word on the areas.
 * @return {void}
 */
export const criteria = (detail) => {
    if (!areas) {
        return;
    }
    const accordion = detail.accordion || [];
    const data = detail.areas || {};
    const filtered = Boolean(data.filtered);
    areas.querySelectorAll(SELECTORS.grid).forEach((grid) => grid.removeAttribute('aria-busy'));
    areas.dataset.filtered = filtered ? '1' : '0';
    areas.dataset.elsewhere = data.elsewhere || '';
    Posters.paintPosters(areas, accordion, filtered, state, setExpandable);
    Posters.paintSummary(areas, accordion, data, state.open);
    Posters.paintReveal(areas, data);
    state.allempty = Boolean(data.allempty);
    paintAllEmpty();
    Posters.paintSearchAll(areas, data.searchallurl || '');
    if (state.open) {
        place();
        request();
    }
};

/**
 * Wire every listener of the panel. All are delegated, so a panel rendered later behaves alike.
 *
 * @return {void}
 */
const listen = () => {
    areas.addEventListener('click', onAreasClick);
    panel.addEventListener('click', (event) => {
        if (dragged) {
            event.preventDefault();
            event.stopPropagation();
            dragged = false;
        }
    }, true);
    panel.addEventListener('click', onPanelClick);
    // The rail scrolls itself and a scroll event does not bubble, so the panel hears it on the way down.
    panel.addEventListener('scroll', (event) => {
        if (event.target instanceof Element && event.target.matches(SELECTORS.rail)) {
            updateRail();
        }
    }, true);
    panel.addEventListener('pointerdown', onPointerDown);
    panel.addEventListener('dragstart', (event) => {
        if (closestOf(event, SELECTORS.rail)) {
            event.preventDefault();
        }
    });
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('popstate', onPopState);
    // The reveal past the slice: closing it over the open poster closes its panel with it.
    areas.addEventListener('toggle', (event) => {
        const details = event.target;
        if (!(details instanceof HTMLDetailsElement) || !details.matches(SELECTORS.more)) {
            return;
        }
        const poster = posterOf(state.open);
        if (!details.open && poster && details.contains(poster)) {
            closeArea(false, true);
        } else {
            relayout();
        }
    }, true);
    [WIDE, PHONE, FINE].forEach((query) => {
        window.matchMedia(query).addEventListener('change', relayout);
    });
    document.addEventListener(EVENTS.view, relayout);
};

/**
 * Whether nothing matches anywhere, as the server's render says it with no panel open.
 *
 * The server draws the "nothing anywhere" box hidden while a panel is open, so a page loaded with a
 * zeroed area open does not say what its close must show. It is read off what the same render
 * does say: the page is in result mode, every poster counts nothing, and the courses sitting here
 * are hidden, which a grouped page with areas does exactly when none of them matches - or the page
 * is the root, where no course sits.
 *
 * @return {Boolean}
 */
const loadedAllEmpty = () => {
    const box = areas.querySelector(SELECTORS.allempty);
    if (!box || !isFiltered()) {
        return false;
    }
    if (!box.hidden) {
        return true;
    }
    // The root has no course of its own, so nothing sits there to match.
    const direct = document.querySelector(SELECTORS.direct);
    const nonehere = isRoot() || (Boolean(direct) && direct.hidden);
    const posters = Array.from(areas.querySelectorAll(SELECTORS.area));
    return nonehere && posters.every((poster) => Number(poster.dataset.count) === 0);
};

/**
 * Take over a panel the server drew for an address carrying it: no request, no scroll.
 *
 * Its rows are the answer to the address's question, so that question counts as asked: an answer
 * to the same criteria later asks it again only for a client that names no question.
 *
 * @return {void}
 */
const adopt = () => {
    const section = panel.querySelector(SELECTORS.section);
    const id = section ? Number(section.dataset.categoryid) : 0;
    const poster = posterOf(id);
    if (panel.hidden || !isExpandable(poster)) {
        return;
    }
    state.open = id;
    state.openedby = 'user';
    state.psort = new URL(window.location.href).searchParams.get(PARAM.psort) === PSORT_NAME ? PSORT_NAME : '';
    state.asked = client.question(poster, state.psort);
    state.askedticket = state.ticket;
    convertPanel();
    CategoryMark.mark(panel, termOf());
    panel.classList.remove(CLASSES.shut);
    areas.classList.add(CLASSES.areasopen);
};

/**
 * Arm the panels of this page for one client.
 *
 * @param {Object} options What the client hands in: ask (poster, psort - a promise of the area's
 *     rows answer, its panel included), question (poster, psort - the question that answer is to,
 *     or the empty string to ask every time), painted (panel - called once the rows are on
 *     screen), unanswered (poster, psort - true when the client took a failure over), links
 *     (whether the posters stay links) and quiet (milliseconds a request waits for the reader to
 *     stop pressing).
 * @return {Boolean} Whether this page has posters and their panel, and they are armed.
 */
export const arm = (options) => {
    areas = document.querySelector(SELECTORS.areas);
    panel = areas ? areas.querySelector(SELECTORS.panel) : null;
    if (!areas || !panel) {
        return false;
    }
    Object.assign(client, options);
    status = areas.querySelector(SELECTORS.status);
    state.allempty = loadedAllEmpty();
    adopt();
    areas.querySelectorAll(SELECTORS.area).forEach(convertPoster);
    document.body.classList.add(BODY_CLASS);
    if (state.open) {
        place();
        updateRail();
        client.painted(panel);
    }
    listen();
    return true;
};
