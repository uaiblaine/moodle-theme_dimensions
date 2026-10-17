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
 * The filter line's preview: pointing at a filter lights the cards on screen it would keep.
 *
 * The rule is the fleet's filter line contract (moodle-dev/docs/filter-line/CONTRACT.md, section 6), drawn
 * as block_compass's calendar strip draws its own: the cards a filter would keep wear a ring in the accent,
 * the others recede, and the stylesheet does both by remapping the card's own colour tokens, never by
 * opacity. A scope cell, the favourites star, an option of the Filters panel (a state, a field's value, a
 * quick date) and a suggestion of the search each preview the control they are or name.
 *
 * It answers a mouse or a pen pointing ({@see pointing}) and the keyboard's focus ({@see onFocus}), never a
 * finger, which has no hover to preview with: a tap is a press. It previews a control that would NARROW the
 * listing ({@see criterionOf}): one already in force, an "All" or the star already pressed would keep every
 * card on screen, which says nothing.
 *
 * What a filter would keep is decided here, from facts the server wrote on each card and nothing else
 * ({@see factsOf}): the state chip the course counts under, each filter field's value, the start day in the
 * site's timezone, and the favourite as the card's own star says it. Combined with the criteria in force,
 * read off the form ({@see inForce}): the filter replaces its own group's value and keeps every other. The
 * search term and the tree's category are in force too, but every card on screen already answered them and
 * no card carries what they are decided on, so they are not read. A list the server pages lights only what
 * it has drawn. A card that does not carry the fact a filter is decided on - a visitor's card states no
 * favourite, only the fields a visitor may see and a start day only where it prints one - makes the whole
 * preview stand down: the page never claims a card would go when it cannot know.
 *
 * Nothing here asks the server anything, reads the session or composes a word: the visitor's page loads it
 * through the filter line's module (categorypublic_source_test walks that graph). It animates nothing
 * either: the stylesheet's transitions live under a no-preference motion query, so under reduced motion the
 * change is instant.
 *
 * @module     theme_dimensions/categorypreview
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {Object} Every hook this module reads, all of them written by the toolbar's and the card's templates. */
const SELECTORS = {
    card: '[data-region="dim-card"]',
    cardstar: '[data-action="dim-star"]',
    group: '[data-region="dim-vitrine-group"]',
    radio: '[data-region="dim-vitrine-group"] input[type="radio"]',
    label: '[data-region="dim-vitrine-group"] label[for]',
    star: '[data-region="dim-line-star"]',
    quick: '[data-action="dim-quick"]',
    option: '[role="option"]',
};

/**
 * @type {Object} The request parameters a control of the line names: the server's criteria, as
 * categorylisting's PARAM spells them ({@see categorylisting}).
 */
const PARAM = {
    chip: 'chip',
    fieldprefix: 'cf_',
    from: 'from',
    to: 'to',
};

/** @type {Object} The classes this module writes: the listing previewing, a kept card, a card that recedes. */
const CLASSES = {
    previewing: 'dim-vitrine--previewing',
    lit: 'dim-card--lit',
    dim: 'dim-card--dim',
};

/** @type {HTMLElement|null} The listing section: its grid, its appended pages and the open panel's rail. */
let root = null;

/** @type {HTMLElement|null} The toolbar form, which holds every control a preview starts from. */
let form = null;

/** @type {HTMLElement|null} The control being previewed. */
let current = null;

/** @type {Object} The line's hooks: controlOf, the control a suggestion of the listbox names. */
let hooks = {controlOf: () => null};

/**
 * Whether a pointer event is a mouse's or a pen's: the two that hover. A finger has no hover state, and the
 * browser brackets every tap with an over and an out.
 *
 * @param {PointerEvent} event The event.
 * @return {Boolean}
 */
const pointing = (event) => event.pointerType === 'mouse' || event.pointerType === 'pen';

/**
 * The control a target of the form stands for: a pill's radio (from its label), the line's star, a quick
 * date, or the control a suggestion names; null for anything else.
 *
 * @param {EventTarget|null} target The target.
 * @return {HTMLElement|null}
 */
const controlOf = (target) => {
    if (!(target instanceof Element) || !form || !form.contains(target)) {
        return null;
    }
    const option = target.closest(SELECTORS.option);
    if (option) {
        return hooks.controlOf(option);
    }
    const label = target.closest(SELECTORS.label);
    if (label) {
        return document.getElementById(label.htmlFor);
    }
    return target.closest(SELECTORS.radio + ', ' + SELECTORS.star + ', ' + SELECTORS.quick);
};

/**
 * The criterion a control would put in force, or null where pressing it would narrow nothing.
 *
 * @param {HTMLElement|null} control The control.
 * @return {Object|null} {kind: 'chip', value}, {kind: 'fav'}, {kind: 'field', id, value} or {kind: 'period', from, to}.
 */
const criterionOf = (control) => {
    if (!control || !control.isConnected) {
        return null;
    }
    if (control.matches(SELECTORS.star)) {
        return control.getAttribute('aria-pressed') === 'true' ? null : {kind: 'fav'};
    }
    if (control.matches(SELECTORS.quick)) {
        return control.hasAttribute('aria-current') ? null
            : {kind: 'period', from: control.dataset.from || '', to: control.dataset.to || ''};
    }
    if (!control.matches(SELECTORS.radio) || control.checked) {
        return null;
    }
    if (control.name === PARAM.chip) {
        return control.value === '' ? null : {kind: 'chip', value: control.value};
    }
    if (control.name.indexOf(PARAM.fieldprefix) === 0 && control.value !== '0') {
        return {kind: 'field', id: control.name.slice(PARAM.fieldprefix.length), value: control.value};
    }
    return null;
};

/**
 * The criteria in force, as the form holds them: the state chip, the favourites, each field and the period.
 *
 * @return {Object} {chip, fav, fields: {id: value}, from, to}; '' or false where a criterion is not in force.
 */
const inForce = () => {
    const force = {chip: '', fav: false, fields: {}, from: '', to: ''};
    form.querySelectorAll(SELECTORS.radio + ':checked').forEach((input) => {
        if (input.name === PARAM.chip) {
            force.chip = input.value;
        } else if (input.name.indexOf(PARAM.fieldprefix) === 0 && input.value !== '0') {
            force.fields[input.name.slice(PARAM.fieldprefix.length)] = input.value;
        }
    });
    const star = form.querySelector(SELECTORS.star);
    force.fav = Boolean(star) && star.getAttribute('aria-pressed') === 'true';
    const from = form.querySelector('input[name="' + PARAM.from + '"]');
    const to = form.querySelector('input[name="' + PARAM.to + '"]');
    force.from = from ? from.value : '';
    force.to = to ? to.value : '';
    return force;
};

/**
 * The facts a card carries, each null where the card does not state it.
 *
 * @param {HTMLElement} card The card.
 * @return {Object} {chip, fav, fields: {id: value}|null, start}.
 */
const factsOf = (card) => {
    const star = card.querySelector(SELECTORS.cardstar);
    let fields = null;
    if (card.dataset.fields) {
        fields = {};
        card.dataset.fields.split(' ').forEach((pair) => {
            const [id, value] = pair.split(':');
            fields[id] = value;
        });
    }
    return {
        chip: card.dataset.chip ? card.dataset.chip : null,
        fav: star ? star.getAttribute('aria-pressed') === 'true' : null,
        fields: fields,
        start: card.hasAttribute('data-start') ? card.dataset.start : null,
    };
};

/**
 * Whether a start day falls inside a period, each bound optional: the server's day_start() comparison,
 * which holds on the days of the site's timezone; a course with no start date is in no period.
 *
 * @param {String} start The start day, YYYY-MM-DD, or ''.
 * @param {String} from The first day, or ''.
 * @param {String} to The last day, or ''.
 * @return {Boolean}
 */
const within = (start, from, to) => start !== '' && (from === '' || start >= from) && (to === '' || start <= to);

/**
 * Whether a card would stay: true, false, or null where it does not carry the fact the criterion needs.
 *
 * Each criterion in force but the one replaced is asked of the card too, where the card states it; one it
 * does not state was already answered by the server for every card on screen.
 *
 * @param {Object} facts The card's facts ({@see factsOf}).
 * @param {Object} criterion The criterion pointed at ({@see criterionOf}).
 * @param {Object} force The criteria in force ({@see inForce}).
 * @return {Boolean|null}
 */
const keeps = (facts, criterion, force) => {
    const wanted = Object.assign({}, force, {fields: Object.assign({}, force.fields)});
    if (criterion.kind === 'chip') {
        wanted.chip = criterion.value;
    } else if (criterion.kind === 'fav') {
        wanted.fav = true;
    } else if (criterion.kind === 'field') {
        wanted.fields[criterion.id] = criterion.value;
    } else {
        wanted.from = criterion.from;
        wanted.to = criterion.to;
    }
    const needed = criterion.kind === 'field' ? facts.fields && facts.fields[criterion.id]
        : {chip: facts.chip, fav: facts.fav, period: facts.start}[criterion.kind];
    if (needed === null || needed === undefined) {
        return null;
    }
    if (wanted.chip !== '' && facts.chip !== null && facts.chip !== wanted.chip) {
        return false;
    }
    if (wanted.fav && facts.fav === false) {
        return false;
    }
    const fields = facts.fields || {};
    const fieldmiss = Object.keys(wanted.fields).some((id) => id in fields && fields[id] !== wanted.fields[id]);
    if (fieldmiss) {
        return false;
    }
    return (wanted.from === '' && wanted.to === '') || facts.start === null || within(facts.start, wanted.from, wanted.to);
};

/**
 * Take every mark of a preview off the listing.
 *
 * @return {void}
 */
export const clear = () => {
    current = null;
    if (!root) {
        return;
    }
    root.classList.remove(CLASSES.previewing);
    root.querySelectorAll('.' + CLASSES.lit + ', .' + CLASSES.dim).forEach((card) => {
        card.classList.remove(CLASSES.lit, CLASSES.dim);
    });
};

/**
 * Light the cards on screen a control would keep and let the others recede; clear where it previews nothing.
 *
 * @param {HTMLElement|null} control The control: a pill's radio, the line's star, a quick date.
 * @return {void}
 */
export const show = (control) => {
    const criterion = root ? criterionOf(control) : null;
    const cards = criterion ? Array.from(root.querySelectorAll(SELECTORS.card)) : [];
    if (!cards.length) {
        clear();
        return;
    }
    const force = inForce();
    const verdicts = cards.map((card) => keeps(factsOf(card), criterion, force));
    if (verdicts.indexOf(null) >= 0) {
        // A card that does not state the fact: the preview cannot know, so it says nothing.
        clear();
        return;
    }
    current = control;
    root.classList.add(CLASSES.previewing);
    cards.forEach((card, index) => {
        card.classList.toggle(CLASSES.lit, verdicts[index]);
        card.classList.toggle(CLASSES.dim, !verdicts[index]);
    });
};

/**
 * Bring the preview in line with a repainted listing: the cards changed, or the control went into force.
 *
 * @return {void}
 */
export const refresh = () => {
    if (current) {
        show(current);
    }
};

/**
 * A mouse or a pen entered something of the form: preview the control it stands for, or leave the preview.
 *
 * The listener is delegated, so it is the over event and not the enter, which does not bubble; moving inside
 * one control changes nothing.
 *
 * @param {PointerEvent} event The event.
 * @return {void}
 */
const onOver = (event) => {
    if (!pointing(event)) {
        return;
    }
    const control = controlOf(event.target);
    if (control === current) {
        return;
    }
    if (control) {
        show(control);
    } else {
        clear();
    }
};

/**
 * A mouse or a pen left the form: the preview goes with it.
 *
 * @param {PointerEvent} event The event.
 * @return {void}
 */
const onOut = (event) => {
    if (!pointing(event)) {
        return;
    }
    const next = event.relatedTarget instanceof Node ? event.relatedTarget : null;
    if (!next || !form.contains(next)) {
        clear();
    }
};

/**
 * The keyboard's focus reached a control: preview it. A focus that a press gave (a click on a pill's label
 * focuses its radio) is not the keyboard's, and that press is applying the filter already.
 *
 * @param {FocusEvent} event The event.
 * @return {void}
 */
const onFocus = (event) => {
    const control = controlOf(event.target);
    if (control && event.target.matches(':focus-visible')) {
        show(control);
    } else if (current) {
        clear();
    }
};

/**
 * The focus left the form: the preview goes with it.
 *
 * @param {FocusEvent} event The event.
 * @return {void}
 */
const onBlur = (event) => {
    const next = event.relatedTarget instanceof Node ? event.relatedTarget : null;
    if (!next || !form.contains(next)) {
        clear();
    }
};

/**
 * Arm the preview over a listing's toolbar.
 *
 * @param {HTMLElement} listing The listing section, which holds every card the preview may light.
 * @param {HTMLElement} toolbar The toolbar form.
 * @param {Object} options The line's hooks: controlOf, the control a suggestion names, or null.
 * @return {void}
 */
export const arm = (listing, toolbar, options) => {
    root = listing;
    form = toolbar;
    hooks = Object.assign({controlOf: () => null}, options || {});
    form.addEventListener('pointerover', onOver);
    form.addEventListener('pointerout', onOut);
    form.addEventListener('focusin', onFocus);
    form.addEventListener('focusout', onBlur);
    // A press applies the filter: the cards about to be replaced keep no mark meanwhile.
    form.addEventListener('change', clear);
    form.addEventListener('click', (event) => {
        if (controlOf(event.target)) {
            clear();
        }
    });
};
