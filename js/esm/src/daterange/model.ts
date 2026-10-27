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
 * What the period control holds and every way it changes, as pure functions of that state.
 *
 * Two boxes, a start and an end, open one popover, and the box that opened it is the mode: the bound
 * the next press of a day sets. The presses write a DRAFT; "Done" or a press outside the control
 * applies it ({@see commit()}), and Escape or the keyboard walking out discards it ({@see close()}),
 * leaving the period that was in force. Each clearing control applies at once ({@see drop()}). The
 * same rules as amd/src/daterange.js, function for function; the component only renders this state
 * and tells the listing when {@see apply()} says the period in force changed.
 *
 * @module     theme_dimensions/daterange/model
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {bringIntoView, firstOf, monthAt, monthOf, onScreen, parse, period} from './calendar';
import type {Month} from './calendar';

/** The start's bound, named as its query parameter and the box's data-bound. */
export const FROM = 'from';

/** The end's bound, named the same way. */
export const TO = 'to';

/** One of the two bounds. */
export type Bound = typeof FROM | typeof TO;

/** Everything the control shows. Each date is YYYY-MM-DD or the empty string. */
export type Model = {
    // The period in force: what the listing is narrowed by.
    from: string,
    to: string,
    // What the boxes and the grids show: the period in force, or a draft of it while the popover is open.
    draftfrom: string,
    draftto: string,
    open: boolean,
    // The bound the next press of a day sets.
    mode: Bound,
    // The box the focus goes back to when the reader closes the popover.
    field: Bound,
    // The first month on screen.
    cursor: Month,
    // The day the roving tabindex is on.
    focused: string,
    // Whether the popover was ever opened: the prompt and the grids are written from then on.
    opened: boolean,
};

/** A change of the period in force, and whether it really changed. */
export type Applied = {model: Model, changed: boolean};

/**
 * The control as it mounts: the period the address says, shut, on the months of that period.
 *
 * @param {string} from The first day as given, or anything else.
 * @param {string} to The last day as given, or anything else.
 * @param {string} today The day the site calls today, YYYY-MM-DD.
 * @returns {object} The state; see Model.
 */
export const initial = (from: unknown, to: unknown, today: string): Model => {
    const given = period(from, to);
    return {
        from: given.from,
        to: given.to,
        draftfrom: given.from,
        draftto: given.to,
        open: false,
        mode: FROM,
        field: FROM,
        cursor: monthOf(given.from || given.to || today),
        focused: '',
        opened: false,
    };
};

/**
 * Make one bound the one the next press sets, and stand the grid on its day.
 *
 * The day stood on is the bound's own, else the other bound's, else the site's today, and the months
 * move as little as it takes to show it.
 *
 * @param {object} model The state.
 * @param {string} bound FROM or TO.
 * @param {string} today The day the site calls today.
 * @param {number} count How many months are on screen.
 * @returns {object} The new state.
 */
export const setMode = (model: Model, bound: string, today: string, count: number): Model => {
    const mode: Bound = bound === TO ? TO : FROM;
    const own = mode === TO ? model.draftto : model.draftfrom;
    const other = mode === TO ? model.draftfrom : model.draftto;
    const focused = own || other || today;
    return {...model, mode: mode, field: mode, focused: focused, cursor: bringIntoView(model.cursor, count, focused)};
};

/**
 * Open the popover from one of the boxes, on the months the period is in.
 *
 * @param {object} model The state.
 * @param {string} bound The bound of the box pressed.
 * @param {string} today The day the site calls today.
 * @param {number} count How many months are on screen.
 * @returns {object} The new state.
 */
export const open = (model: Model, bound: string, today: string, count: number): Model => {
    const opened = {
        ...model,
        draftfrom: model.from,
        draftto: model.to,
        cursor: monthOf(model.from || model.to || today),
        open: true,
        opened: true,
    };
    return setMode(opened, bound, today, count);
};

/**
 * Shut the popover, and the draft with it.
 *
 * A draft dies with the popover: this is what makes Escape discard. Left alive, the shut control would
 * show boxes naming a period the listing is not narrowed by.
 *
 * @param {object} model The state.
 * @returns {object} The new state.
 */
export const close = (model: Model): Model => ({...model, open: false, draftfrom: model.from, draftto: model.to});

/**
 * Make a period the one in force.
 *
 * The one place the period in force changes from inside the control. A period equal to the one in
 * force is no change, so clearing a bound that is not set does not fetch the same page again.
 *
 * @param {object} model The state.
 * @param {string} start The first day, or the empty string.
 * @param {string} end The last day, or the empty string.
 * @returns {object} The new state and whether the period in force changed.
 */
export const apply = (model: Model, start: string, end: string): Applied => {
    const changed = start !== model.from || end !== model.to;
    return {model: {...model, from: start, to: end, draftfrom: start, draftto: end}, changed: changed};
};

/**
 * Take one press of a day, for the bound the popover is in the mode of.
 *
 * In the start's mode the day becomes the start, an end that would now come before it is dropped, and
 * the mode moves on to the end: two presses describe a range. In the end's mode the day becomes the
 * end - unless it falls before the start, which is the slip of a reader who pressed a start and then
 * an earlier day: that day becomes the start, the end is dropped and the end is still asked for. No
 * press leaves a period running backwards, and nothing is applied: the reader ends the choice.
 *
 * @param {object} model The state.
 * @param {string} iso The day pressed.
 * @returns {object} The new state.
 */
export const press = (model: Model, iso: string): Model => {
    let {draftfrom, draftto, mode} = model;
    if (mode === FROM) {
        draftfrom = iso;
        if (draftto !== '' && draftto < iso) {
            draftto = '';
        }
        mode = TO;
    } else if (draftfrom !== '' && iso < draftfrom) {
        draftfrom = iso;
        draftto = '';
    } else {
        draftto = iso;
    }
    return {...model, draftfrom: draftfrom, draftto: draftto, mode: mode, field: mode, focused: iso};
};

/**
 * End the choice: the draft becomes the period in force and the popover shuts.
 *
 * What "Done" does and what a press outside the control does. A start or an end alone is a period the
 * server answers, so any draft is applied as it stands.
 *
 * @param {object} model The state.
 * @returns {object} The new state and whether the period in force changed.
 */
export const commit = (model: Model): Applied => apply(close(model), model.draftfrom, model.draftto);

/**
 * Drop one bound, or the whole period, from what the boxes show, and apply the rest at once.
 *
 * A box's clearing control drops its own bound and keeps the other as the boxes show it; the footer's
 * drops both and puts the mode back on the start.
 *
 * @param {object} model The state.
 * @param {string} bound FROM, TO, or the empty string for the whole period.
 * @returns {object} The new state and whether the period in force changed.
 */
export const drop = (model: Model, bound: string): Applied => {
    const start = bound === TO ? model.draftfrom : '';
    const end = bound === FROM ? model.draftto : '';
    return apply(bound === '' ? {...model, mode: FROM} : model, start, end);
};

/**
 * Move the months on screen by one, and stand the grid on the first day of the first of them.
 *
 * @param {object} model The state.
 * @param {number} offset Minus one or one.
 * @returns {object} The new state.
 */
export const step = (model: Model, offset: number): Model => {
    const cursor = monthAt(model.cursor, offset);
    return {...model, cursor: cursor, focused: firstOf(cursor)};
};

/**
 * Move the roving tabindex onto a day, bringing its month on screen when it is not.
 *
 * @param {object} model The state.
 * @param {string} iso The day to move to.
 * @param {number} count How many months are on screen.
 * @returns {object|null} The new state, or null when the day is no date and nothing moves.
 */
export const moveTo = (model: Model, iso: string, count: number): Model | null => {
    if (!parse(iso)) {
        return null;
    }
    const cursor = onScreen(model.cursor, count, iso) ? model.cursor : bringIntoView(model.cursor, count, iso);
    return {...model, focused: iso, cursor: cursor};
};

/**
 * Keep the day stood on visible when the width gains or loses the second month.
 *
 * @param {object} model The state.
 * @param {number} count How many months are on screen now.
 * @returns {object} The new state.
 */
export const widen = (model: Model, count: number): Model =>
    ({...model, cursor: bringIntoView(model.cursor, count, model.focused)});

/**
 * Put a period onto the control without telling anybody: what the address says, painted.
 *
 * The path a quick option, the back button and a cleared filter take. The caller that set it knows
 * what to do next, so nothing here reports a change: telling the listing would fetch the page the
 * caller has just fetched. An open popover stays open and shows the period as its draft.
 *
 * @param {object} model The state.
 * @param {string} start The first day as given, or anything else.
 * @param {string} end The last day as given, or anything else.
 * @returns {object} The new state.
 */
export const set = (model: Model, start: unknown, end: unknown): Model => {
    const given = period(start, end);
    return {...model, from: given.from, to: given.to, draftfrom: given.from, draftto: given.to};
};
