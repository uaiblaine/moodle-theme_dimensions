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
 * The listing's three React components, mounted for both shells, and what the listing tells them.
 *
 * The components are named here ({@see COMPONENTS}) and nowhere else, and are visitor-safe by rule
 * (categorypublic_source_test walks them). Every mount puts its listeners on before its node: core's
 * react_autoinit may mount it at once, and a component already imported must find somebody to hear it.
 *
 * @module     theme_dimensions/listing/mounts
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from '@moodle/lms/core/pending';
import {SCOPE} from './address';
import type {ListingState} from './address';
import {AREAS, CALENDAR, LINE, LINE_DRAWN, SELECTORS} from './events';

/** The React components both shells draw, each named here and nowhere else. */
const COMPONENTS = {
    calendar: '@moodle/lms/theme_dimensions/daterange/DateRange',
    line: '@moodle/lms/theme_dimensions/filterline/FilterLine',
    areas: '@moodle/lms/theme_dimensions/areas/Areas',
};

/** An answer's option of a radio group, as both web services return it. */
export type Option = {
    id: string,
    value: string | number,
    label: string,
    count?: number | string | null,
    hascount?: boolean,
    checked?: boolean,
};

/** What a page's client answers the posters' component with ({@see mountAreas}). */
export type AreasClient = {
    ask: (poster: HTMLElement, psort: string) => unknown,
    question?: (poster: HTMLElement, psort: string) => string,
    unanswered?: (poster: HTMLElement, psort: string) => boolean,
    painted?: (panel: HTMLElement) => unknown,
};

/**
 * Whether the filter line's component draws an element, which the listing then never writes into.
 *
 * The three frames the component adopts (the Filters panel, the Organise menu, the managers' menu) stay the
 * listing's to paint. Until the component marks the line, and on a line never handed over, every painter
 * paints the server's line.
 *
 * @param {Element|null} element An element of the toolbar.
 * @returns {boolean}
 */
export const drawnByLine = (element: Element | null): boolean => {
    const line = element ? element.closest(SELECTORS.line) : null;
    return element !== null && line !== null && line.hasAttribute(LINE_DRAWN) && !element.closest(SELECTORS.adopted);
};

/**
 * Tell the filter line's component something, where a component draws the line.
 *
 * @param {HTMLElement} root The listing section.
 * @param {string} type One of LINE's events.
 * @param {object} detail What it is told.
 * @returns {boolean} Whether a component draws the line and was told.
 */
export const tellLine = (root: HTMLElement, type: string, detail: object): boolean => {
    const line = root.querySelector(SELECTORS.line);
    if (!line || !line.hasAttribute(LINE_DRAWN)) {
        return false;
    }
    line.dispatchEvent(new CustomEvent(type, {detail: detail}));
    return true;
};

/**
 * Tell the filter line's component an answer's numbers - the result count, and the scope in SCOPE's
 * order - called after the painters, so the panel's groups its suggestions read are painted. A visitor's
 * answer carries no scope and no favourites.
 *
 * @param {HTMLElement} root The listing section.
 * @param {object} response The rows response.
 * @returns {void}
 */
export const tellAnswer = (root: HTMLElement, response: Record<string, unknown>): void => {
    const group = response.statusgroup as {options?: Option[]} | null | undefined;
    const options = group ? (group.options || []) : [];
    /**
     * Where an option stands in SCOPE's order, or -1 outside the scope.
     *
     * @param {object} option The option.
     * @returns {number}
     */
    const rank = (option: Option): number => SCOPE.indexOf(String(option.value));
    tellLine(root, LINE.answer, {
        // The bar's count is the count sentence's number, as the server formatted it.
        total: typeof response.countnumber === 'string' ? response.countnumber : undefined,
        // No option at all is a page whose state sort was switched off under it: the scope stays as drawn.
        scope: options.length ? options.filter((option) => rank(option) >= 0).sort((a, b) => rank(a) - rank(b)) : null,
        favourites: response.favourites || null,
        filtercount: Number(response.filtercount || 0),
        filtercountlabel: response.filtercountlabel || '',
    });
};

/**
 * Where a client's own focus on the search should go: the field, or its icon where the field is folded away.
 *
 * @param {HTMLElement} root The listing section.
 * @returns {HTMLElement|null} The control to focus.
 */
export const focusTarget = (root: HTMLElement): HTMLElement | null => {
    const search = root.querySelector<HTMLElement>(SELECTORS.search);
    const opener = root.querySelector<HTMLElement>(SELECTORS.opener);
    if (!search || search.getClientRects().length > 0 || !opener || opener.getClientRects().length === 0) {
        return search;
    }
    return opener;
};

/**
 * Hand the toolbar's filter line to its React component.
 *
 * The mount node is the server's line itself, so it keeps its place, its classes and the sibling rules the
 * stylesheet writes against it. Core's react_autoinit watches added nodes and never attributes, so the line
 * is put back where it stands once named; the listeners are on before that, and the mount is pending until
 * the component says it is drawn or that it cannot draw it. A line with no field, no listbox or no form id
 * is left to the server's markup.
 *
 * @param {HTMLElement} root The listing section.
 * @param {Function} dropTerm Called before a suggestion's control is pressed: takes the typed term out of
 *   the caller's state, so the one request that control causes carries none.
 * @returns {boolean} Whether the line was handed over.
 */
export const mountLine = (root: HTMLElement, dropTerm: () => void): boolean => {
    const form = root.querySelector(SELECTORS.toolbar);
    const line = form ? form.querySelector<HTMLElement>(SELECTORS.line) : null;
    /* The attribute, never the property: the visitor's script form carries a hidden field named "id",
       which a form's named-control lookup returns for form.id, and the component would be handed {}. */
    const formid = form ? form.getAttribute('id') : null;
    if (!line || !line.querySelector(SELECTORS.linefield) || !line.querySelector(SELECTORS.listbox) || !formid) {
        return false;
    }
    const pending = new Pending('theme_dimensions/listing:line');
    line.addEventListener(LINE.dropterm, () => dropTerm());
    line.addEventListener(LINE.mounted, () => pending.resolve(), {once: true});
    line.addEventListener(LINE.failed, () => pending.resolve(), {once: true});
    line.dataset.reactComponent = COMPONENTS.line;
    line.dataset.reactProps = JSON.stringify({form: formid});
    // Put back where it stands: the observer hears an added node, and the line keeps its place and any focus.
    const focused = line.contains(document.activeElement) ? document.activeElement as HTMLElement : null;
    line.parentNode?.insertBefore(line, line.nextSibling);
    if (focused) {
        focused.focus({preventScroll: true});
    }
    return true;
};

/**
 * Hide the two native date fields behind a calendar that is up.
 *
 * @param {HTMLElement} root The listing section.
 * @returns {void}
 */
const hideDateFields = (root: HTMLElement): void => {
    const fields = root.querySelector<HTMLElement>(SELECTORS.dateinputs);
    if (fields) {
        fields.hidden = true;
        fields.querySelectorAll<HTMLInputElement>('input[type="date"]').forEach((input) => {
            input.tabIndex = -1;
        });
    }
};

/**
 * Tell the calendar component the period the state holds, without it telling anybody back.
 *
 * @param {Element|null} region The toolbar's calendar region.
 * @param {object} state The calling client's state.
 * @returns {void}
 */
export const tellCalendar = (region: Element | null, state: ListingState): void => {
    if (region) {
        region.dispatchEvent(new CustomEvent(CALENDAR.set, {detail: {from: state.datefrom, to: state.dateto}}));
    }
};

/**
 * Append the calendar component to its region.
 *
 * The mount is a NEW child (react_autoinit watches added nodes), its props the props object itself. The
 * fields hide only once the component says it is drawn, which it never says without the site's today, and
 * the period is told again then: a quick option or Back may have come while the component was on its way.
 *
 * @param {HTMLElement} root The listing section.
 * @param {HTMLElement} fieldset The period's fieldset, carrying the site's today and first weekday.
 * @param {HTMLElement} region The empty region the calendar goes into.
 * @param {object} state The calling client's state.
 * @param {Function} onpick Called with the two bounds of the period whenever the reader changes it.
 * @returns {void}
 */
const appendCalendar = (
    root: HTMLElement,
    fieldset: HTMLElement,
    region: HTMLElement,
    state: ListingState,
    onpick: (from: string, to: string) => void,
): void => {
    region.addEventListener(CALENDAR.pick, (event) => {
        const detail = (event as CustomEvent).detail;
        onpick(detail.from, detail.to);
    });
    // Every time: core's react_autoinit mounts the node again after a move with the line, and the period it was
    // appended with may be old by then.
    region.addEventListener(CALENDAR.mounted, () => {
        hideDateFields(root);
        tellCalendar(region, state);
    });
    const node = document.createElement('div');
    node.dataset.reactComponent = COMPONENTS.calendar;
    node.dataset.reactProps = JSON.stringify({
        id: state.categoryid,
        firstday: fieldset.dataset.firstday,
        today: fieldset.dataset.today,
        from: state.datefrom,
        to: state.dateto,
    });
    region.append(node);
};

/**
 * Mount the calendar component over the two native date fields, which hide behind it once it is drawn.
 *
 * @param {HTMLElement} root The listing section.
 * @param {object} state The calling client's state.
 * @param {Function} onpick Called with the two bounds of the period whenever the reader changes it.
 * @returns {Promise} Resolving once the component is appended, or at once when there is no toolbar.
 */
export const mountCalendar = async(
    root: HTMLElement,
    state: ListingState,
    onpick: (from: string, to: string) => void,
): Promise<void> => {
    const fieldset = root.querySelector<HTMLElement>(SELECTORS.date);
    const region = fieldset ? fieldset.querySelector<HTMLElement>(SELECTORS.daterange) : null;
    if (!fieldset || !region) {
        return;
    }
    appendCalendar(root, fieldset, region, state, onpick);
};

/**
 * Hand the posters and their panel to the React component: listen first, then append its node.
 *
 * The node is an empty, hidden child of the areas section, so the mount draws no box, and it is pending until
 * the component says it is drawn, so Behat never presses a poster that is still a plain link. The component
 * calls no service: the page's client answers it (ask's promise, question's question, unanswered's taking
 * the failure over, painted's rows). The props are the shell's options (areas/panel.ts optionsOf()).
 *
 * @param {HTMLElement|null} areas The areas section.
 * @param {object} props The component's props: {} on the logged-in shell, the visitor's options on its own.
 * @param {object} client What the page's client answers with: ask, and optionally question, unanswered and painted.
 * @returns {boolean} Whether the posters were handed over.
 */
export const mountAreas = (areas: HTMLElement | null, props: object, client: AreasClient): boolean => {
    if (!areas || !areas.querySelector(SELECTORS.panel)) {
        return false;
    }
    const pending = new Pending('theme_dimensions/listing:areas');
    areas.addEventListener(AREAS.ask, (event) => {
        const detail = (event as CustomEvent).detail;
        detail.answer = client.ask(detail.poster, detail.psort);
    });
    if (client.question) {
        const question = client.question;
        areas.addEventListener(AREAS.question, (event) => {
            const detail = (event as CustomEvent).detail;
            detail.question = question(detail.poster, detail.psort);
        });
    }
    if (client.unanswered) {
        const unanswered = client.unanswered;
        areas.addEventListener(AREAS.unanswered, (event) => {
            const detail = (event as CustomEvent).detail;
            detail.taken = unanswered(detail.poster, detail.psort);
        });
    }
    if (client.painted) {
        const painted = client.painted;
        areas.addEventListener(AREAS.painted, (event) => painted((event as CustomEvent).detail.panel));
    }
    areas.addEventListener(AREAS.mounted, () => pending.resolve(), {once: true});
    const node = document.createElement('div');
    node.hidden = true;
    node.dataset.reactComponent = COMPONENTS.areas;
    node.dataset.reactProps = JSON.stringify(props);
    areas.append(node);
    return true;
};
