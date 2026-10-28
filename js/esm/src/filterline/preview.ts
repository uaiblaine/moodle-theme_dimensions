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
 * amd/src/categorypreview.js, function for function, for the logged-in shell's component; the visitor's
 * shell keeps that module until R4, so a rule of the preview changes in both. It acts on the server's
 * cards and on the toolbar form, never on anything a component draws, and writes three classes: the
 * listing previewing, a kept card, a card that recedes. The stylesheet does both by remapping the card's
 * own colour tokens, never by opacity.
 *
 * It answers a mouse or a pen pointing ({@see pointing}) and the keyboard's focus ({@see onFocus}), never
 * a finger, which has no hover to preview with. It previews a control that would NARROW the listing
 * ({@see criterionOf}): one already in force, an "All" or the star already pressed would keep every card.
 * What a filter would keep is decided from facts the server wrote on each card and nothing else
 * ({@see factsOf}), with the criteria in force read off the form ({@see inForce}); a card that does not
 * state the fact a filter is decided on makes the whole preview stand down - the page never claims a card
 * would go when it cannot know. Nothing here asks the server anything, reads the session or composes a
 * word, and it animates nothing.
 *
 * @module     theme_dimensions/filterline/preview
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Every hook this module reads, all of them written by the toolbar's and the card's templates. */
const SELECTORS = {
    card: '[data-region="dim-card"]',
    cardstar: '[data-action="dim-star"]',
    radio: '[data-region="dim-vitrine-group"] input[type="radio"]',
    label: '[data-region="dim-vitrine-group"] label[for]',
    star: '[data-region="dim-line-star"]',
    quick: '[data-action="dim-quick"]',
    option: '[role="option"]',
};

/** The request parameters a control of the line names: the server's criteria, as categorylisting's PARAM spells them. */
const PARAM = {
    chip: 'chip',
    fieldprefix: 'cf_',
    from: 'from',
    to: 'to',
};

/** The classes this module writes: the listing previewing, a kept card, a card that recedes. */
export const CLASSES = {
    previewing: 'dim-vitrine--previewing',
    lit: 'dim-card--lit',
    dim: 'dim-card--dim',
};

/** The criterion a control would put in force. */
export type Criterion =
    {kind: 'chip', value: string}
    | {kind: 'fav'}
    | {kind: 'field', id: string, value: string}
    | {kind: 'period', from: string, to: string};

/** The criteria in force, as the form holds them; '' or false where one is not in force. */
export type Force = {chip: string, fav: boolean, fields: Record<string, string>, from: string, to: string};

/** The facts a card states, each null where it does not state it. */
export type Facts = {chip: string | null, fav: boolean | null, fields: Record<string, string> | null, start: string | null};

/** The line's hook: the control a suggestion of the listbox names. */
export type Hooks = {controlOf: (option: Element) => HTMLElement | null};

/** The listing section: its grid, its appended pages and the open panel's rail. */
let root: HTMLElement | null = null;

/** The toolbar form, which holds every control a preview starts from. */
let form: HTMLElement | null = null;

/** The control being previewed. */
let current: HTMLElement | null = null;

/**
 * The control no suggestion names: the hook's answer until the line hands its own.
 *
 * @returns {null}
 */
const none = (): null => null;

/** The line's hooks. */
let hooks: Hooks = {controlOf: none};

/**
 * Whether a pointer event is a mouse's or a pen's: the two that hover. A finger has no hover state, and
 * the browser brackets every tap with an over and an out.
 *
 * @param {PointerEvent} event The event.
 * @returns {boolean}
 */
export const pointing = (event: PointerEvent): boolean => event.pointerType === 'mouse' || event.pointerType === 'pen';

/**
 * The control a target of the form stands for: a pill's radio (from its label), the line's star, a quick
 * date, or the control a suggestion names; null for anything else.
 *
 * @param {EventTarget|null} target The target.
 * @returns {HTMLElement|null}
 */
export const controlOf = (target: EventTarget | null): HTMLElement | null => {
    if (!(target instanceof Element) || !form || !form.contains(target)) {
        return null;
    }
    const option = target.closest(SELECTORS.option);
    if (option) {
        return hooks.controlOf(option);
    }
    const label = target.closest<HTMLLabelElement>(SELECTORS.label);
    if (label) {
        return document.getElementById(label.htmlFor);
    }
    return target.closest<HTMLElement>(`${SELECTORS.radio}, ${SELECTORS.star}, ${SELECTORS.quick}`);
};

/**
 * The criterion a control would put in force, or null where pressing it would narrow nothing.
 *
 * @param {HTMLElement|null} control The control.
 * @returns {object|null}
 */
export const criterionOf = (control: HTMLElement | null): Criterion | null => {
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
    const input = control as HTMLInputElement;
    if (!control.matches(SELECTORS.radio) || input.checked) {
        return null;
    }
    if (input.name === PARAM.chip) {
        return input.value === '' ? null : {kind: 'chip', value: input.value};
    }
    if (input.name.indexOf(PARAM.fieldprefix) === 0 && input.value !== '0') {
        return {kind: 'field', id: input.name.slice(PARAM.fieldprefix.length), value: input.value};
    }
    return null;
};

/**
 * The criteria in force, as the form holds them: the state chip, the favourites, each field and the period.
 *
 * @param {HTMLElement} toolbar The toolbar form.
 * @returns {object}
 */
export const inForce = (toolbar: HTMLElement): Force => {
    const force: Force = {chip: '', fav: false, fields: {}, from: '', to: ''};
    toolbar.querySelectorAll<HTMLInputElement>(`${SELECTORS.radio}:checked`).forEach((input) => {
        if (input.name === PARAM.chip) {
            force.chip = input.value;
        } else if (input.name.indexOf(PARAM.fieldprefix) === 0 && input.value !== '0') {
            force.fields[input.name.slice(PARAM.fieldprefix.length)] = input.value;
        }
    });
    const star = toolbar.querySelector(SELECTORS.star);
    force.fav = Boolean(star) && star?.getAttribute('aria-pressed') === 'true';
    const from = toolbar.querySelector<HTMLInputElement>(`input[name="${PARAM.from}"]`);
    const to = toolbar.querySelector<HTMLInputElement>(`input[name="${PARAM.to}"]`);
    force.from = from ? from.value : '';
    force.to = to ? to.value : '';
    return force;
};

/**
 * The facts a card carries, each null where the card does not state it.
 *
 * @param {HTMLElement} card The card.
 * @returns {object}
 */
export const factsOf = (card: HTMLElement): Facts => {
    const star = card.querySelector(SELECTORS.cardstar);
    let fields: Record<string, string> | null = null;
    if (card.dataset.fields) {
        fields = {};
        card.dataset.fields.split(' ').forEach((pair) => {
            const [id, value] = pair.split(':');
            (fields as Record<string, string>)[id] = value;
        });
    }
    return {
        chip: card.dataset.chip ? card.dataset.chip : null,
        fav: star ? star.getAttribute('aria-pressed') === 'true' : null,
        fields: fields,
        start: card.hasAttribute('data-start') ? (card.dataset.start || '') : null,
    };
};

/**
 * Whether a start day falls inside a period, each bound optional: the server's day_start() comparison,
 * which holds on the days of the site's timezone; a course with no start date is in no period.
 *
 * @param {string} start The start day, YYYY-MM-DD, or ''.
 * @param {string} from The first day, or ''.
 * @param {string} to The last day, or ''.
 * @returns {boolean}
 */
export const within = (start: string, from: string, to: string): boolean =>
    start !== '' && (from === '' || start >= from) && (to === '' || start <= to);

/**
 * Whether a card would stay: true, false, or null where it does not carry the fact the criterion needs.
 *
 * Each criterion in force but the one replaced is asked of the card too, where the card states it; one it
 * does not state was already answered by the server for every card on screen.
 *
 * @param {object} facts The card's facts.
 * @param {object} criterion The criterion pointed at.
 * @param {object} force The criteria in force.
 * @returns {boolean|null}
 */
export const keeps = (facts: Facts, criterion: Criterion, force: Force): boolean | null => {
    const wanted: Force = Object.assign({}, force, {fields: Object.assign({}, force.fields)});
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
    let needed: unknown;
    if (criterion.kind === 'field') {
        needed = facts.fields ? facts.fields[criterion.id] : null;
    } else if (criterion.kind === 'chip') {
        needed = facts.chip;
    } else if (criterion.kind === 'fav') {
        needed = facts.fav;
    } else {
        needed = facts.start;
    }
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
 * @returns {void}
 */
export const clear = (): void => {
    current = null;
    if (!root) {
        return;
    }
    root.classList.remove(CLASSES.previewing);
    root.querySelectorAll(`.${CLASSES.lit}, .${CLASSES.dim}`).forEach((card) => {
        card.classList.remove(CLASSES.lit, CLASSES.dim);
    });
};

/**
 * Light the cards on screen a control would keep and let the others recede; clear where it previews nothing.
 *
 * @param {HTMLElement|null} control The control: a pill's radio, the line's star, a quick date.
 * @returns {void}
 */
export const show = (control: HTMLElement | null): void => {
    const criterion = root ? criterionOf(control) : null;
    const listing = root;
    const cards = criterion && listing ? Array.from(listing.querySelectorAll<HTMLElement>(SELECTORS.card)) : [];
    if (!criterion || !listing || !form || !cards.length) {
        clear();
        return;
    }
    const force = inForce(form);
    const verdicts = cards.map((card) => keeps(factsOf(card), criterion, force));
    if (verdicts.indexOf(null) >= 0) {
        // A card that does not state the fact: the preview cannot know, so it says nothing.
        clear();
        return;
    }
    current = control;
    listing.classList.add(CLASSES.previewing);
    cards.forEach((card, index) => {
        card.classList.toggle(CLASSES.lit, verdicts[index] === true);
        card.classList.toggle(CLASSES.dim, verdicts[index] !== true);
    });
};

/**
 * Bring the preview in line with a repainted listing: the cards changed, or the control went into force.
 *
 * @returns {void}
 */
export const refresh = (): void => {
    if (current) {
        show(current);
    }
};

/**
 * A mouse or a pen entered something of the form: preview the control it stands for, or leave the preview.
 *
 * The listener is delegated, so it is the over event and not the enter, which does not bubble; moving
 * inside one control changes nothing.
 *
 * @param {PointerEvent} event The event.
 * @returns {void}
 */
const onOver = (event: PointerEvent): void => {
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
 * @returns {void}
 */
const onOut = (event: PointerEvent): void => {
    if (!pointing(event)) {
        return;
    }
    const next = event.relatedTarget instanceof Node ? event.relatedTarget : null;
    if (!next || !form || !form.contains(next)) {
        clear();
    }
};

/**
 * The keyboard's focus reached a control: preview it. A focus that a press gave (a click on a pill's label
 * focuses its radio) is not the keyboard's, and that press is applying the filter already.
 *
 * @param {FocusEvent} event The event.
 * @returns {void}
 */
const onFocus = (event: FocusEvent): void => {
    const control = controlOf(event.target);
    if (control && event.target instanceof Element && event.target.matches(':focus-visible')) {
        show(control);
    } else if (current) {
        clear();
    }
};

/**
 * The focus left the form: the preview goes with it.
 *
 * @param {FocusEvent} event The event.
 * @returns {void}
 */
const onBlur = (event: FocusEvent): void => {
    const next = event.relatedTarget instanceof Node ? event.relatedTarget : null;
    if (!next || !form || !form.contains(next)) {
        clear();
    }
};

/**
 * A press on a control applies its filter: the cards about to be replaced keep no mark meanwhile.
 *
 * @param {Event} event The click.
 * @returns {void}
 */
const onPress = (event: Event): void => {
    if (controlOf(event.target)) {
        clear();
    }
};

/**
 * Arm the preview over a listing's toolbar, once.
 *
 * @param {HTMLElement} listing The listing section, which holds every card the preview may light.
 * @param {HTMLElement} toolbar The toolbar form.
 * @param {object} options The line's hooks: controlOf, the control a suggestion names.
 * @returns {void}
 */
export const arm = (listing: HTMLElement, toolbar: HTMLElement, options: Partial<Hooks>): void => {
    if (form === toolbar) {
        return;
    }
    root = listing;
    form = toolbar;
    hooks = Object.assign({controlOf: none}, options || {});
    form.addEventListener('pointerover', onOver);
    form.addEventListener('pointerout', onOut);
    form.addEventListener('focusin', onFocus);
    form.addEventListener('focusout', onBlur);
    form.addEventListener('change', clear);
    form.addEventListener('click', onPress);
};

/**
 * Forget the form armed, for a test that arms another.
 *
 * @returns {void}
 */
export const reset = (): void => {
    clear();
    root = null;
    form = null;
    hooks = {controlOf: none};
};
