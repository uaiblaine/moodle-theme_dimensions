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
 * The filter line's behaviour, for both listing clients: its folding, the search that grows over it
 * and its suggestions, its floating layers and its two rows that scroll sideways.
 *
 * The rules are the fleet's filter line contract (moodle-dev/docs/filter-line/CONTRACT.md, sections
 * 2 to 4 and 8) and the owner's prototype beside it. The server draws the whole line and every
 * control on it works without this module; what this module adds is presentation and keyboard, and
 * every request it causes is made by the listing client's own handler of the control concerned.
 *
 * Folding ({@see fit}): the line measures its OWN width, never the viewport's, and takes the first
 * step at which its controls fit on one line - full; Filters without its word; the search as an
 * icon; two lines, the scope on the second, scrolling sideways, and the view toggle moved into the
 * Organise menu. It measures with every cell of the scope drawn and with no transition running, so
 * the step follows the width and the language and never the counts of the moment. The steps are
 * classes on the line, which the stylesheet reads.
 *
 * The search grows over the line from the third step ({@see openSearch}): the other controls slide
 * out, the field takes the line and a close button its last place; Escape or that button closes it
 * and gives the focus back to the control that opened it, without opening it again. A term left in
 * force while the field is folded away shows as the server's search chip under the line, which the
 * stylesheet reveals; nothing of it is composed here.
 *
 * The suggestions ({@see suggest}) are one listbox under the line, the field its combobox with the
 * active option named by aria-activedescendant, so the focus never leaves the field. They are the
 * filters already on the page - every scope cell, the favourites star, every option of the Filters
 * panel and its quick dates - whose label or group matches the typed text, accent- and
 * case-insensitively, word starts first and then by count, each with its group and a count the page
 * has shown for it. That count is the server's last answer given while the field held no term
 * ({@see remember}), so a term typed since - which the client searches as it is typed, and which
 * zeroes the options of a word that names no course - neither changes nor hides a suggestion. It
 * is the contract's "count it would give without the typed text" for the filters in force when the
 * term was typed, and never a count of the module's own; a page opened on an address that already
 * carries a term has no such answer, and offers the counts it shows, which include the term: a
 * stated departure from the contract, as is every count once a filter is applied while a term is in
 * force (the answer then counts under the term until the term goes). Choosing one presses
 * the control it names, so each client's handler applies it with one request, after the client has
 * dropped the term from its state (the arming option dropTerm): the term goes in that same request.
 *
 * Every floating layer - the listbox, the Filters panel, the Organise menu - is placed under the
 * line's real bottom, measured ({@see place}); on the two-line step the Filters panel is a bottom
 * sheet instead. A half-hidden item of a row that scrolls sideways scrolls itself fully into view
 * when it is pressed or focused ({@see reveal}), and a hidden side of such a row fades
 * ({@see fades}).
 *
 * Nothing here calls a web service, reads the session or composes a word: the visitor's module
 * imports it (categorypublic_source_test walks that graph). Every label, legend and count it shows
 * is read off the controls the server drew and the answers repaint, and its two headings are the
 * server's, written on the listbox.
 *
 * @module     theme_dimensions/categoryline
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from 'core/pending';

/** @type {Object} Every hook this module reads, all of them written by the toolbar's template. */
const SELECTORS = {
    toolbar: '[data-region="dim-vitrine-toolbar"]',
    line: '[data-region="dim-line"]',
    search: '[data-region="dim-line-search"]',
    field: 'input[type="search"]',
    opener: '[data-action="dim-search-open"]',
    closer: '[data-action="dim-search-close"]',
    listbox: '[data-region="dim-line-suggest"]',
    scope: '[data-region="dim-vitrine-group"][data-role="scope"]',
    group: '[data-region="dim-vitrine-group"]',
    pillcount: '[data-region="dim-pill-count"]',
    star: '[data-region="dim-line-star"]',
    favcount: '[data-region="dim-fav-count"]',
    date: '[data-region="dim-date"]',
    quick: '[data-action="dim-quick"]',
    daterange: '[data-region="dim-daterange"]',
    filters: '[data-region="dim-filters"]',
    panel: '[data-region="dim-filters-panel"]',
    organise: '[data-region="dim-organise"]',
    menu: '[data-region="dim-organise-menu"]',
    showas: '[data-region="dim-showas"] [data-action="dim-view"]',
    chips: '[data-region="dim-line-chips"]',
    option: '[role="option"]',
};

/** @type {String[]} The folding steps in the order they are tried, each a class dim-line--<step> on the line. */
const STEPS = ['s1', 's2', 's3', 's4'];

/**
 * @type {Array} What is tried, in order, as [step, whether the field is folded into its icon]: the
 * third step folds it, and the fourth keeps the field on its first line while it fits there.
 */
const TRIES = [['s1', false], ['s2', false], ['s3', true], ['s4', false], ['s4', true]];

/** @type {String[]} The steps at which the search grows over the line; at the first two nothing moves. */
const MORPH_STEPS = ['s3', 's4'];

/** @type {String} The step whose Filters panel is a bottom sheet and whose scope has a line of its own. */
const TWO_LINES = 's4';

/** @type {Object} The classes this module writes. */
const CLASSES = {
    step: 'dim-line--',
    measuring: 'dim-line--measuring',
    searching: 'dim-line--searching',
    folded: 'dim-line--folded',
    sheet: 'dim-toolbar--sheet',
    fadeleft: 'dim-scroll--fade-left',
    faderight: 'dim-scroll--fade-right',
    active: 'dim-line__option--active',
    anchored: 'dim-line__suggest--anchored',
    mark: 'dim-mark',
};

/** @type {Number} Pixels between the line's real bottom and a layer placed under it: the stylesheet's own gap. */
const GAP = 6;

/** @type {Number} Pixels kept clear between a revealed item and the edge of the row it scrolls in. */
const REVEAL_PAD = 16;

/** @type {Number} The search's own floor (criteria::MIN_SEARCH_LENGTH): a shorter text suggests what an empty one does. */
const MIN_TERM = 2;

/** @type {Number} How many filters the empty field offers to start with: the highest counts. */
const START_COUNT = 5;

/** @type {Number} How many matching filters a typed text is offered. */
const MATCH_COUNT = 8;

/** @type {Number} The narrowest the listbox is drawn where it hangs under the field rather than across the line. */
const MIN_LIST_WIDTH = 288;

/** @type {Number} Milliseconds a Tab press counts as the reason the field was focused. */
const TAB_INTENT = 1000;

/** @type {HTMLElement|null} The line. */
let line = null;

/** @type {HTMLElement|null} The toolbar form around it. */
let form = null;

/** @type {HTMLInputElement|null} The search field. */
let field = null;

/** @type {Object} The elements of the line this module drives, found once at arming. */
const parts = {};

/**
 * @type {WeakMap} What each filter control showed in the last answer given while the field held no
 * term: {count, hidden}. A control created since is read as it stands.
 */
const rest = new WeakMap();

/** @type {Object} The calling client's hooks: dropTerm, which takes the typed term out of its state. */
let hooks = {dropTerm: () => null};

/** @type {Object} The line's state: its step, the morph, the listbox and the reasons a focus is the reader's. */
const state = {
    step: '',
    folded: false,
    searching: false,
    open: false,
    from: null,
    quiet: false,
    intent: false,
    tabat: 0,
    width: -1,
    active: -1,
    items: [],
};

/**
 * Text with its accents taken off and in lower case, character for character, so a match found in it
 * is a match in the text it came from.
 *
 * @param {String} text Any text.
 * @return {String}
 */
const fold = (text) => String(text).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

/**
 * The fleet's normalise rule for matching: accents off, lower case, trimmed.
 *
 * @param {String} text Any text.
 * @return {String}
 */
export const normalise = (text) => fold(text || '').trim();

/**
 * How well a normalised text matches a normalised term: 0 where a word starts with it, 1 where it is
 * inside a word, -1 where it is not there at all.
 *
 * @param {String} text The normalised text.
 * @param {String} term The normalised term.
 * @return {Number}
 */
const matchOf = (text, term) => {
    let at = text.indexOf(term);
    if (at < 0) {
        return -1;
    }
    while (at >= 0) {
        if (at === 0 || !/[a-z0-9]/.test(text.charAt(at - 1))) {
            return 0;
        }
        at = text.indexOf(term, at + 1);
    }
    return 1;
};

/**
 * The rank of a filter for a term: its label's word start first, then its group's, then inside its
 * label, then inside its group; -1 where neither holds the term.
 *
 * @param {Object} item A filter: label and group.
 * @param {String} term The normalised term.
 * @return {Number}
 */
const rankOf = (item, term) => {
    const label = matchOf(normalise(item.label), term);
    const group = matchOf(normalise(item.group), term);
    if (label === 0) {
        return 0;
    }
    if (group === 0) {
        return 1;
    }
    if (label === 1) {
        return 2;
    }
    return group === 1 ? 3 : -1;
};

/**
 * The filters to suggest for a text: what matches it, best first; with less than the search's floor,
 * the few with the highest counts to start with.
 *
 * Matching is accent- and case-insensitive on both sides (normalise). Word starts come first, then
 * the larger count, then the order the page draws them in. A filter whose count the page does not
 * show sorts after every counted one.
 *
 * The counts are the server's last answer given while the field held no term ({@see remember}); where
 * there is none (a page opened on an address carrying a term) they are the answer on the page, which
 * counted under that term: there, a departure from the contract's "the count it would give without
 * the typed text" (section 3), which only the server could answer, with a request per keystroke.
 *
 * @param {Array} items The filters on the page: label, group, count (null where none is shown), order.
 * @param {String} text What is in the field.
 * @return {Array} The filters to offer, in order.
 */
export const suggest = (items, text) => {
    const term = normalise(text);
    const count = (item) => (item.count === null ? -1 : item.count);
    if (term.length < MIN_TERM) {
        return items.slice().sort((a, b) => count(b) - count(a) || a.order - b.order).slice(0, START_COUNT);
    }
    return items.map((item) => ({item: item, rank: rankOf(item, term)}))
        .filter((entry) => entry.rank >= 0)
        .sort((a, b) => a.rank - b.rank || count(b.item) - count(a.item) || a.item.order - b.item.order)
        .slice(0, MATCH_COUNT)
        .map((entry) => entry.item);
};

/**
 * Whether an element is drawn at all.
 *
 * @param {HTMLElement|null} element The element.
 * @return {Boolean}
 */
const shown = (element) => Boolean(element) && element.getClientRects().length > 0;

/**
 * Whether the reader asked for no motion.
 *
 * @return {Boolean}
 */
const still = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Move the focus without it counting as the reader's: the field's focus listener stays quiet.
 *
 * @param {HTMLElement|null} element Where the focus goes.
 * @return {void}
 */
const quietly = (element) => {
    if (!element) {
        return;
    }
    state.quiet = true;
    try {
        element.focus({preventScroll: true});
    } finally {
        state.quiet = false;
    }
};

/**
 * A label's own words, without the count drawn inside it.
 *
 * @param {HTMLElement} label The label.
 * @return {String}
 */
const wordsOf = (label) => Array.from(label.childNodes)
    .filter((node) => node.nodeType === Node.TEXT_NODE)
    .map((node) => node.textContent)
    .join('')
    .trim();

/**
 * The count a label shows, or null where it shows none.
 *
 * @param {HTMLElement} label The label.
 * @return {Number|null}
 */
const countOf = (label) => {
    const count = label.querySelector(SELECTORS.pillcount);
    if (!count || count.hidden || count.textContent.trim() === '') {
        return null;
    }
    return Number(count.textContent);
};

/**
 * The words of a group's legend.
 *
 * @param {HTMLElement|null} fieldset The group.
 * @return {String}
 */
const legendOf = (fieldset) => {
    const legend = fieldset ? fieldset.querySelector('legend') : null;
    return legend ? legend.textContent.trim() : '';
};

/**
 * Keep what every filter control shows, while the field holds no term: the counts a suggestion offers.
 *
 * Called at arming and before every repaint, so it records each answer the client paints while the
 * field is empty, and none while a typed term is being searched.
 *
 * @return {void}
 */
const remember = () => {
    if (normalise(field.value) !== '') {
        return;
    }
    form.querySelectorAll(SELECTORS.group).forEach((fieldset) => {
        fieldset.querySelectorAll('input[type="radio"]').forEach((input) => {
            const label = fieldset.querySelector('label[for="' + input.id + '"]');
            if (label) {
                rest.set(input, {count: countOf(label), hidden: fieldset.hidden || input.hidden || label.hidden});
            }
        });
    });
    const count = parts.star ? parts.star.querySelector(SELECTORS.favcount) : null;
    if (count) {
        rest.set(parts.star, {count: Number(count.textContent), hidden: false});
    }
};

/**
 * Every filter on the page a suggestion may apply, with the counts the page showed before a term was typed.
 *
 * A filter already in force, the "All" of a group (which narrows nothing), anything hidden and
 * anything counted at zero are left out: choosing one of those would add nothing or empty the
 * listing. Hidden and counted are read as the page stood before a term was typed ({@see remember}).
 *
 * @return {Array} Entries of {control, label, group, count, order}.
 */
const filters = () => {
    const found = [];
    const add = (control, label, group, count) => {
        if (label !== '' && count !== 0) {
            found.push({control: control, label: label, group: group, count: count, order: found.length});
        }
    };
    form.querySelectorAll(SELECTORS.group).forEach((fieldset) => {
        const group = legendOf(fieldset);
        fieldset.querySelectorAll('input[type="radio"]').forEach((input) => {
            const label = fieldset.querySelector('label[for="' + input.id + '"]');
            if (!label || input.checked || input.value === '' || input.value === '0') {
                return;
            }
            const known = rest.get(input) || {count: countOf(label), hidden: input.hidden || label.hidden};
            if (!known.hidden) {
                add(input, wordsOf(label), group, known.count);
            }
        });
    });
    const star = parts.star;
    if (star && star.getAttribute('aria-pressed') !== 'true') {
        const count = star.querySelector(SELECTORS.favcount);
        const known = rest.get(star) || {count: count ? Number(count.textContent) : null};
        add(star, star.title || '', '', known.count);
    }
    const date = form.querySelector(SELECTORS.date);
    if (date) {
        date.querySelectorAll(SELECTORS.quick).forEach((link) => {
            if (!link.hasAttribute('aria-current')) {
                add(link, link.textContent.trim(), legendOf(date), null);
            }
        });
    }
    return found;
};

/**
 * Write a text into an element with the term marked where it matches, in the text's own spelling.
 *
 * @param {HTMLElement} element Where the text goes.
 * @param {String} text The text.
 * @param {String} term The normalised term, or the empty string.
 * @return {void}
 */
const markInto = (element, text, term) => {
    const map = [];
    let folded = '';
    for (let i = 0; i < text.length; i++) {
        const piece = fold(text.charAt(i));
        folded += piece;
        for (let j = 0; j < piece.length; j++) {
            map.push(i);
        }
    }
    const at = term === '' ? -1 : folded.indexOf(term);
    if (at < 0) {
        element.textContent = text;
        return;
    }
    const start = map[at];
    const end = map[at + term.length - 1] + 1;
    const mark = document.createElement('mark');
    mark.className = CLASSES.mark;
    mark.textContent = text.slice(start, end);
    element.replaceChildren(document.createTextNode(text.slice(0, start)), mark, document.createTextNode(text.slice(end)));
};

/**
 * One option of the listbox: its label, its group and its count, all in its accessible name.
 *
 * @param {Object} item The filter.
 * @param {Number} index Its place in the list.
 * @param {String} term The normalised term, or the empty string.
 * @return {HTMLElement}
 */
const optionOf = (item, index, term) => {
    const option = document.createElement('div');
    option.id = parts.listbox.id + '-' + index;
    option.className = 'dim-line__option';
    option.setAttribute('role', 'option');
    option.setAttribute('aria-selected', 'false');
    option.dataset.index = String(index);
    const plus = document.createElement('i');
    plus.className = 'fa fa-plus dim-line__optionplus';
    plus.setAttribute('aria-hidden', 'true');
    const label = document.createElement('span');
    label.className = 'dim-line__optionlabel';
    markInto(label, item.label, term);
    option.append(plus, label);
    if (item.group !== '') {
        const group = document.createElement('span');
        group.className = 'dim-line__optiongroup';
        group.textContent = item.group;
        option.append(document.createTextNode(' '), group);
    }
    if (item.count !== null) {
        const count = document.createElement('span');
        count.className = 'dim-line__optioncount';
        count.textContent = String(item.count);
        option.append(document.createTextNode(' '), count);
    }
    return option;
};

/**
 * The bottom of the line's first line of controls, as drawn: the floating layers hang under it.
 *
 * The scope of the two-line step is a line of its own, which the suggestions cover while the
 * search is open over the line, as the prototype draws it; and a control slid out of the way has
 * no box to count.
 *
 * @return {Number} A viewport coordinate.
 */
const lineBottom = () => {
    let bottom = 0;
    Array.from(line.children).forEach((child) => {
        if (child === parts.listbox || (state.step === TWO_LINES && child === parts.scope)) {
            return;
        }
        const rect = child.getBoundingClientRect();
        if (rect.width > 0 && rect.height > 0) {
            bottom = Math.max(bottom, rect.bottom);
        }
    });
    return bottom || line.getBoundingClientRect().bottom;
};

/**
 * Place a floating layer under the line's real bottom, whatever its containing block.
 *
 * The bottom is measured, never a fixed offset, so a wrapped line, a taller control or the second
 * line of the two-line step never puts a layer over the field or the close button. The Filters
 * panel of the two-line step is a bottom sheet and takes no offset at all.
 *
 * @param {HTMLElement|null} layer The listbox, the Filters panel or the Organise menu.
 * @return {void}
 */
const place = (layer) => {
    if (!layer) {
        return;
    }
    if (layer === parts.panel && state.step === TWO_LINES) {
        layer.style.top = '';
        return;
    }
    const parent = layer.offsetParent;
    if (!parent || !shown(layer)) {
        return;
    }
    const top = parent.getBoundingClientRect().top + parent.clientTop;
    layer.style.top = Math.round(lineBottom() - top + GAP) + 'px';
};

/**
 * Place the listbox: under the field at the first two steps, across the line from the third.
 *
 * @return {void}
 */
const placeList = () => {
    const listbox = parts.listbox;
    const anchored = state.step === 's1' || state.step === 's2';
    listbox.classList.toggle(CLASSES.anchored, anchored);
    if (anchored && parts.search) {
        const base = line.getBoundingClientRect();
        const box = parts.search.getBoundingClientRect();
        const width = Math.min(base.width, Math.max(box.width, MIN_LIST_WIDTH));
        listbox.style.left = Math.round(Math.min(box.left - base.left, base.width - width)) + 'px';
        listbox.style.width = Math.round(width) + 'px';
    } else {
        listbox.style.left = '';
        listbox.style.width = '';
    }
    place(listbox);
};

/**
 * Show a row's fades on the sides where it hides something.
 *
 * @param {HTMLElement|null} scroller A row that scrolls sideways.
 * @return {void}
 */
const fades = (scroller) => {
    if (!scroller) {
        return;
    }
    const max = scroller.scrollWidth - scroller.clientWidth;
    const x = scroller.scrollLeft;
    // Right to left, scrollLeft runs from 0 down to minus the overflow.
    const rtl = window.getComputedStyle(scroller).direction === 'rtl';
    const left = rtl ? max + x : x;
    const right = rtl ? -x : max - x;
    scroller.classList.toggle(CLASSES.fadeleft, max > 1 && left > 2);
    scroller.classList.toggle(CLASSES.faderight, max > 1 && right > 2);
};

/**
 * Scroll a half-hidden item of a row fully into view, with room to spare.
 *
 * @param {HTMLElement|null} scroller The row.
 * @param {HTMLElement|null} item The item pressed or focused.
 * @return {void}
 */
const reveal = (scroller, item) => {
    if (!scroller || !item || !scroller.contains(item) || scroller.scrollWidth <= scroller.clientWidth) {
        return;
    }
    const box = scroller.getBoundingClientRect();
    const rect = item.getBoundingClientRect();
    let dx = 0;
    if (rect.left < box.left + REVEAL_PAD) {
        dx = rect.left - box.left - REVEAL_PAD;
    } else if (rect.right > box.right - REVEAL_PAD) {
        dx = rect.right - box.right + REVEAL_PAD;
    }
    if (dx !== 0) {
        scroller.scrollBy({left: dx, behavior: still() ? 'auto' : 'smooth'});
    }
};

/**
 * The item a press or a focus inside a row stands for: a radio is drawn by its label.
 *
 * @param {EventTarget} target The event's target.
 * @return {HTMLElement|null}
 */
const itemOf = (target) => {
    if (!(target instanceof Element)) {
        return null;
    }
    if (target.matches('input[type="radio"]')) {
        return form.querySelector('label[for="' + target.id + '"]');
    }
    return target.closest('label, a, button');
};

/**
 * Put the scope where its step draws it: first on the line, or its own line after every control.
 *
 * Moved in the document and not only in the picture, so the focus order is the order the eye
 * reads; a focused cell is given its focus back once moved.
 *
 * @param {Boolean} down Whether the scope takes a line of its own.
 * @return {void}
 */
const placeScope = (down) => {
    const scope = parts.scope;
    if (!scope) {
        return;
    }
    const focused = scope.contains(document.activeElement) ? document.activeElement : null;
    if (down && scope.nextElementSibling !== parts.listbox) {
        line.insertBefore(scope, parts.listbox);
    } else if (!down && line.firstElementChild !== scope) {
        line.insertBefore(scope, line.firstElementChild);
    } else {
        return;
    }
    if (focused) {
        quietly(focused);
    }
};

/**
 * Draw the line at one step.
 *
 * @param {String} step One of STEPS.
 * @param {Boolean} folded Whether the field is folded into its icon.
 * @return {void}
 */
const setStep = (step, folded) => {
    STEPS.forEach((name) => line.classList.toggle(CLASSES.step + name, name === step));
    line.classList.toggle(CLASSES.folded, folded);
    state.step = step;
    state.folded = folded;
    placeScope(step === TWO_LINES);
};

/**
 * Whether a control of the line is drawn past either of its edges.
 *
 * While measuring the line does not wrap, so a line that does not fit pushes a control out; the
 * listbox is a layer and never counts.
 *
 * @return {Boolean}
 */
const overflows = () => {
    const box = line.getBoundingClientRect();
    const style = window.getComputedStyle(line);
    const left = box.left + parseFloat(style.paddingLeft) - 1;
    const right = box.right - parseFloat(style.paddingRight) + 1;
    return Array.from(line.children).some((child) => {
        if (child === parts.listbox) {
            return false;
        }
        const rect = child.getBoundingClientRect();
        return rect.width > 0 && (rect.right > right || rect.left < left);
    });
};

/**
 * Whether the search grows over the line at the step in force.
 *
 * @return {Boolean}
 */
const morphs = () => MORPH_STEPS.indexOf(state.step) >= 0;

/**
 * Fold the line: try each step in order and keep the first at which it fits.
 *
 * Measured with the line's own box, the open search set aside, every transition off (the measuring
 * class) and every cell of the scope drawn - a cell the counts hide is shown for the measure and
 * hidden again before anything is painted - so the step follows the width and the language and
 * never the numbers of the moment.
 *
 * @return {void}
 */
const fit = () => {
    if (!line || !line.isConnected) {
        return;
    }
    const searching = state.searching;
    line.classList.add(CLASSES.measuring);
    line.classList.remove(CLASSES.searching);
    const absent = parts.scope ? Array.from(parts.scope.querySelectorAll('label[hidden]')) : [];
    absent.forEach((label) => {
        label.hidden = false;
    });
    TRIES.some(([step, folded], index) => {
        setStep(step, folded);
        return index === TRIES.length - 1 || !overflows();
    });
    absent.forEach((label) => {
        label.hidden = true;
    });
    // An open search is put back before the transitions are, so nothing slides out a second time.
    state.searching = searching && morphs();
    line.classList.toggle(CLASSES.searching, state.searching);
    void line.offsetWidth;
    line.classList.remove(CLASSES.measuring);
    if (parts.opener) {
        parts.opener.setAttribute('aria-expanded', state.searching ? 'true' : 'false');
    }
    if (form) {
        form.classList.toggle(CLASSES.sheet, state.step === TWO_LINES && Boolean(parts.filters && parts.filters.open));
    }
    place(parts.panel);
    place(parts.menu);
    if (state.open) {
        placeList();
    }
    fades(parts.scope);
    fades(parts.chips);
};

/**
 * Mark one option of the listbox as the active one, or none.
 *
 * @param {Number} index The option's place, -1 for none.
 * @return {void}
 */
const activate = (index) => {
    const options = parts.listbox.querySelectorAll(SELECTORS.option);
    state.active = index >= 0 && index < options.length ? index : -1;
    options.forEach((option, at) => {
        option.setAttribute('aria-selected', at === state.active ? 'true' : 'false');
        option.classList.toggle(CLASSES.active, at === state.active);
    });
    if (state.active >= 0) {
        field.setAttribute('aria-activedescendant', options[state.active].id);
        options[state.active].scrollIntoView({block: 'nearest'});
    } else {
        field.removeAttribute('aria-activedescendant');
    }
};

/**
 * Take the listbox down.
 *
 * @return {void}
 */
const closeList = () => {
    state.open = false;
    state.items = [];
    state.active = -1;
    parts.listbox.hidden = true;
    parts.listbox.replaceChildren();
    field.setAttribute('aria-expanded', 'false');
    field.removeAttribute('aria-activedescendant');
};

/**
 * Fill the listbox for what is in the field, or take it down when nothing is left to offer.
 *
 * Its one heading is the server's: "Start with a filter" while the field holds less than the
 * search's floor, "Filters" for a typed text.
 *
 * @return {void}
 */
const render = () => {
    if (!state.open) {
        return;
    }
    const typed = normalise(field.value).length >= MIN_TERM;
    const term = typed ? normalise(field.value) : '';
    state.items = suggest(filters(), field.value);
    state.active = -1;
    field.removeAttribute('aria-activedescendant');
    if (!state.items.length) {
        parts.listbox.hidden = true;
        parts.listbox.replaceChildren();
        field.setAttribute('aria-expanded', 'false');
        return;
    }
    const listbox = parts.listbox;
    const heading = document.createElement('div');
    heading.id = listbox.id + '-heading';
    heading.className = 'dim-line__suggesthead';
    heading.setAttribute('role', 'presentation');
    heading.textContent = (typed ? listbox.dataset.labelFilters : listbox.dataset.labelStart) || '';
    const group = document.createElement('div');
    group.setAttribute('role', 'group');
    group.setAttribute('aria-labelledby', heading.id);
    group.append(heading, ...state.items.map((item, index) => optionOf(item, index, term)));
    listbox.replaceChildren(group);
    listbox.hidden = false;
    field.setAttribute('aria-expanded', 'true');
    placeList();
};

/**
 * Open the search: over the line from the third step, under the field before it, with the suggestions.
 *
 * @param {HTMLElement|null} from The control that opened it, which gets the focus back when it closes.
 * @return {void}
 */
const openSearch = (from) => {
    if (morphs() && !state.searching) {
        state.searching = true;
        state.from = from || field;
        line.classList.add(CLASSES.searching);
        if (parts.opener) {
            parts.opener.setAttribute('aria-expanded', 'true');
        }
    }
    state.open = true;
    if (document.activeElement !== field) {
        quietly(field);
    }
    render();
};

/**
 * Close the search and its suggestions; with back, the focus returns to the control that opened it.
 *
 * The focus is moved quietly, so it does not open the search again. A control that is no longer
 * drawn - the field folded away again, the icon at a step that shows the field - hands over to the
 * one that is.
 *
 * @param {Boolean} back Whether the focus goes back to the control that opened the search.
 * @return {void}
 */
const closeSearch = (back) => {
    closeList();
    if (!state.searching) {
        return;
    }
    state.searching = false;
    line.classList.remove(CLASSES.searching);
    if (parts.opener) {
        parts.opener.setAttribute('aria-expanded', 'false');
    }
    const from = state.from;
    state.from = null;
    if (back) {
        quietly([from, field, parts.opener].find((control) => shown(control)) || null);
    }
};

/**
 * Apply one suggestion: the term dropped, then the very control it names pressed.
 *
 * The client's handler of that control makes the one request, and the client's dropTerm has taken
 * the typed term out of its state first, so the term goes in that same request; the field is
 * emptied and the search stays open for the next pick, offering what is left.
 *
 * @param {Number} index The option's place.
 * @return {void}
 */
const choose = (index) => {
    const item = state.items[index];
    if (!item || !item.control.isConnected) {
        return;
    }
    field.value = '';
    hooks.dropTerm();
    item.control.click();
    render();
};

/**
 * The field's keys: the arrows walk the suggestions, Enter and Space choose, Escape closes.
 *
 * Space chooses only once an option is active, so a reader typing two words is never interrupted.
 *
 * @param {KeyboardEvent} event The key.
 * @return {void}
 */
const onFieldKey = (event) => {
    const count = state.items.length;
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!state.open) {
            openSearch(state.from || field);
            return;
        }
        activate(count ? (state.active + 1) % count : -1);
    } else if (event.key === 'ArrowUp' && state.open) {
        event.preventDefault();
        activate(state.active <= 0 ? -1 : state.active - 1);
    } else if ((event.key === 'Enter' || event.key === ' ') && state.active >= 0) {
        event.preventDefault();
        choose(state.active);
    } else if (event.key === 'Escape' && (state.open || state.searching)) {
        // Before the browser empties a search field on the same key.
        event.preventDefault();
        closeSearch(true);
    }
};

/**
 * Close an open menu or panel of the line on Escape, and give its summary the focus.
 *
 * The calendar inside the Filters panel keeps Escape for its own draft.
 *
 * @param {KeyboardEvent} event The key.
 * @return {void}
 */
const onLineKey = (event) => {
    if (event.key !== 'Escape' || event.defaultPrevented || !(event.target instanceof Element)) {
        return;
    }
    if (state.searching && event.target !== field) {
        event.preventDefault();
        closeSearch(true);
        return;
    }
    const details = event.target.closest('details[open]');
    if (details && line.contains(details) && !event.target.closest(SELECTORS.daterange)) {
        event.preventDefault();
        details.open = false;
        quietly(details.querySelector('summary'));
    }
};

/**
 * The slash key: to the search, when the line is on screen and the focus is in no field.
 *
 * @param {KeyboardEvent} event The key.
 * @return {void}
 */
const onSlash = (event) => {
    const active = document.activeElement;
    if (active && (active.isContentEditable || active.closest('input, textarea, select'))) {
        return;
    }
    const rect = line.getBoundingClientRect();
    if (rect.bottom <= 0 || rect.top >= window.innerHeight || !rect.width) {
        return;
    }
    event.preventDefault();
    openSearch(state.folded && !state.searching ? parts.opener : field);
};

/**
 * Wire the rows that scroll sideways: their fades and the reveal of a half-hidden item.
 *
 * The reveal answers the press (click) and the focus, once the press has landed, so a row that
 * scrolls under a finger never hands the press to the item beside it.
 *
 * @return {void}
 */
const listenRows = () => {
    [parts.scope, parts.chips].filter(Boolean).forEach((scroller) => {
        scroller.addEventListener('scroll', () => fades(scroller), {passive: true});
        scroller.addEventListener('focusin', (event) => reveal(scroller, itemOf(event.target)));
        scroller.addEventListener('click', (event) => reveal(scroller, itemOf(event.target)));
    });
};

/**
 * Wire the field, the search's two buttons and the listbox.
 *
 * The field opens the search on the reader's focus only - a press on it or a Tab into it - and
 * never on a focus a client moved there itself, or quietly here (the guard that keeps a closed
 * search from opening again as its focus comes back).
 *
 * @return {void}
 */
const listenSearch = () => {
    field.addEventListener('pointerdown', () => {
        state.intent = true;
    });
    field.addEventListener('focusin', () => {
        const intent = state.intent || Date.now() - state.tabat < TAB_INTENT;
        state.intent = false;
        if (state.quiet || !intent) {
            return;
        }
        openSearch(field);
    });
    field.addEventListener('click', () => {
        if (!state.open) {
            openSearch(state.from || field);
        }
    });
    field.addEventListener('input', () => {
        if (state.open) {
            render();
        } else {
            openSearch(field);
        }
    });
    field.addEventListener('keydown', onFieldKey);
    if (parts.opener) {
        parts.opener.addEventListener('click', () => openSearch(parts.opener));
    }
    if (parts.closer) {
        parts.closer.addEventListener('click', () => closeSearch(true));
    }
    // A press on an option keeps the focus in the field, which is the combobox.
    parts.listbox.addEventListener('mousedown', (event) => event.preventDefault());
    parts.listbox.addEventListener('click', (event) => {
        const option = event.target instanceof Element ? event.target.closest(SELECTORS.option) : null;
        if (option) {
            choose(Number(option.dataset.index));
        }
    });
    line.addEventListener('focusout', (event) => {
        const next = event.relatedTarget instanceof Node ? event.relatedTarget : null;
        if (next === field || (next && parts.listbox.contains(next))) {
            return;
        }
        if (next && line.contains(next)) {
            // Still on the line - its close button, a control beside the field: the suggestions go.
            closeList();
            return;
        }
        // The focus left the line: the search closes where it stands, and nothing it hid keeps the focus.
        closeSearch(false);
    });
};

/**
 * Wire the line's floating layers: placed as they open, the sheet's own way out, and the menu's "Show as".
 *
 * @return {void}
 */
const listenLayers = () => {
    /* In the capture phase: a details element's toggle event does not bubble. */
    line.addEventListener('toggle', (event) => {
        if (event.target === parts.filters) {
            form.classList.toggle(CLASSES.sheet, state.step === TWO_LINES && parts.filters.open);
            place(parts.panel);
        } else if (event.target === parts.organise) {
            place(parts.menu);
        }
    }, true);
    line.addEventListener('click', (event) => {
        const view = event.target instanceof Element ? event.target.closest(SELECTORS.showas) : null;
        if (view && parts.organise) {
            // The client's own view handler, on the listing, reads the button's value after this.
            parts.organise.open = false;
            quietly(parts.organise.querySelector('summary'));
        }
    });
    line.addEventListener('keydown', onLineKey);
    document.addEventListener('pointerdown', (event) => {
        const target = event.target instanceof Node ? event.target : null;
        if (!target) {
            return;
        }
        if ((state.open || state.searching) && !line.contains(target)) {
            closeSearch(false);
        }
        // The bottom sheet has no summary beside it to press again, so a press outside it closes it.
        if (state.step === TWO_LINES && parts.filters && parts.filters.open && !parts.filters.contains(target)) {
            parts.filters.open = false;
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Tab') {
            state.tabat = Date.now();
        } else if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && !event.defaultPrevented) {
            onSlash(event);
        }
    });
};

/**
 * Fold the line again once its width changed, after the frame, held as pending JavaScript.
 *
 * @return {void}
 */
const refit = () => {
    const pending = new Pending('theme_dimensions/categoryline:fit');
    window.requestAnimationFrame(() => {
        fit();
        pending.resolve();
    });
};

/**
 * Where a client's own focus on the search should go: the field, or its icon where the field is folded away.
 *
 * A client gives the search the focus after a control it pressed hides itself ("Clear all", a chip);
 * a field folded into its icon cannot take it, and the focus would fall to the top of the document.
 *
 * @param {HTMLElement|null} search The search field.
 * @return {HTMLElement|null} The control to focus.
 */
export const focusTarget = (search) => {
    if (!search || shown(search) || !shown(parts.opener)) {
        return search;
    }
    return parts.opener;
};

/**
 * Bring the line in line with a repainted answer: its step (a cell came back or went) and its suggestions.
 *
 * @return {void}
 */
export const repaint = () => {
    if (!line) {
        return;
    }
    remember();
    fit();
    render();
};

/**
 * Arm the filter line of a listing.
 *
 * Nothing changes on a page without the line (the root's search line has none). The field becomes
 * the combobox of the suggestions here, never in the template, because without this module there
 * is no listbox for it to control.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Object} options The client's hooks: dropTerm, called before a suggestion's control is
 *   pressed, takes the typed term out of the client's state, so the one request carries none.
 * @return {void}
 */
export const arm = (root, options) => {
    line = root ? root.querySelector(SELECTORS.line) : null;
    if (!line) {
        return;
    }
    form = line.closest(SELECTORS.toolbar) || line.parentElement;
    field = line.querySelector(SELECTORS.search + ' ' + SELECTORS.field);
    parts.listbox = line.querySelector(SELECTORS.listbox);
    if (!field || !parts.listbox) {
        line = null;
        return;
    }
    hooks = Object.assign({dropTerm: () => null}, options || {});
    parts.search = line.querySelector(SELECTORS.search);
    parts.opener = line.querySelector(SELECTORS.opener);
    parts.closer = line.querySelector(SELECTORS.closer);
    parts.scope = line.querySelector(SELECTORS.scope);
    parts.star = line.querySelector(SELECTORS.star);
    parts.filters = line.querySelector(SELECTORS.filters);
    parts.panel = line.querySelector(SELECTORS.panel);
    parts.organise = line.querySelector(SELECTORS.organise);
    parts.menu = line.querySelector(SELECTORS.menu);
    parts.chips = form.querySelector(SELECTORS.chips);

    const label = field.labels && field.labels.length ? field.labels[0].textContent.trim() : '';
    parts.listbox.setAttribute('role', 'listbox');
    parts.listbox.setAttribute('aria-label', label);
    field.setAttribute('role', 'combobox');
    field.setAttribute('aria-autocomplete', 'list');
    field.setAttribute('aria-expanded', 'false');
    field.setAttribute('aria-controls', parts.listbox.id);

    listenSearch();
    listenLayers();
    listenRows();
    remember();
    fit();
    // The line's own width, never the viewport's: a drawer opening changes the one and not the other.
    new ResizeObserver((entries) => {
        const width = Math.round(entries[0].contentRect.width);
        if (width !== state.width) {
            state.width = width;
            refit();
        }
    }).observe(line);
    // A webfont arriving late changes every width on the line.
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(() => refit()).catch(() => null);
    }
};
