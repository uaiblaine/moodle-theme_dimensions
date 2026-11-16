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
 * Two boxes, a start and an end, over the months; the active box is the bound the next press of a day sets
 * (the mode). There is no draft: every press, a box's own clear and "Clear period" change the period in
 * force at once ({@see press()}, {@see drop()}), and the component tells the listing whenever
 * {@see apply()} says it really changed, so the list is refreshed while the layer stays open. The
 * component only renders this state.
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
    // The period in force: what the listing is narrowed by, and what the boxes and the grids show.
    from: string,
    to: string,
    // The bound the next press of a day sets: the active box.
    mode: Bound,
    // The first month on screen.
    cursor: Month,
    // The day the roving tabindex is on: always a day of the months on screen.
    focused: string,
    // The day under the pointer, which previews the band from the other bound; the empty string for none.
    hover: string,
};

/** A change of the period in force, and whether it really changed. */
export type Applied = {model: Model, changed: boolean};

/**
 * The box a period leaves active: the end while a start stands alone, the start otherwise.
 *
 * @param {string} from The first day, or the empty string.
 * @param {string} to The last day, or the empty string.
 * @returns {string} FROM or TO.
 */
export const modeFor = (from: string, to: string): Bound => (from !== '' && to === '' ? TO : FROM);

/**
 * The control as it mounts: the period the address says, on the months of that period.
 *
 * @param {string} from The first day as given, or anything else.
 * @param {string} to The last day as given, or anything else.
 * @param {string} today The day the site calls today, YYYY-MM-DD.
 * @returns {object} The state; see Model.
 */
export const initial = (from: unknown, to: unknown, today: string): Model => {
    const given = period(from, to);
    const focused = given.from || given.to || today;
    return {
        from: given.from,
        to: given.to,
        mode: modeFor(given.from, given.to),
        cursor: monthOf(focused),
        focused: focused,
        hover: '',
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
    const own = mode === TO ? model.to : model.from;
    const other = mode === TO ? model.from : model.to;
    const focused = own || other || today;
    return {...model, mode: mode, focused: focused, cursor: bringIntoView(model.cursor, count, focused)};
};

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
    return {model: {...model, from: start, to: end}, changed: changed};
};

/**
 * Take one press of a day, for the active box, and make the other box the active one.
 *
 * In the start's mode the day becomes the start and an end that would now come before it is dropped. In the
 * end's mode the day becomes the end - unless it falls before the start, which is the slip of a reader who
 * pressed a start and then an earlier day: that day becomes the start, the end is dropped and the end is
 * still asked for. No press leaves a period running backwards, and each press is applied at once.
 *
 * @param {object} model The state.
 * @param {string} iso The day pressed.
 * @returns {object} The new state and whether the period in force changed.
 */
export const press = (model: Model, iso: string): Applied => {
    let {from, to} = model;
    let mode: Bound = model.mode === TO ? FROM : TO;
    if (model.mode === FROM) {
        from = iso;
        if (to !== '' && to < iso) {
            to = '';
        }
    } else if (from !== '' && iso < from) {
        from = iso;
        to = '';
        mode = TO;
    } else {
        to = iso;
    }
    return apply({...model, mode: mode, focused: iso, hover: ''}, from, to);
};

/**
 * Drop one bound, or the whole period, at once.
 *
 * A box's clear drops its own bound, keeps the other and makes its box the active one; "Clear period" drops
 * both and makes the start the active one.
 *
 * @param {object} model The state.
 * @param {string} bound FROM, TO, or the empty string for the whole period.
 * @returns {object} The new state and whether the period in force changed.
 */
export const drop = (model: Model, bound: string): Applied => {
    const start = bound === TO ? model.from : '';
    const end = bound === FROM ? model.to : '';
    const mode: Bound = bound === TO ? TO : FROM;
    return apply({...model, mode: mode, hover: ''}, start, end);
};

/**
 * The days the band runs between, as drawn: the period in force, or the band a hovered day previews.
 *
 * While the active box's other bound is set, a hovered day on the right side of it previews the period a press
 * there would give: from that bound to the hovered day. Otherwise the drawn band is the period in force.
 *
 * @param {object} model The state.
 * @returns {object} The drawn ends, {from, to}, each YYYY-MM-DD or the empty string.
 */
export const drawn = (model: Model): {from: string, to: string} => {
    const hover = model.hover;
    if (hover !== '' && model.mode === TO && model.from !== '' && hover > model.from) {
        return {from: model.from, to: hover};
    }
    if (hover !== '' && model.mode === FROM && model.to !== '' && hover < model.to) {
        return {from: hover, to: model.to};
    }
    return {from: model.from, to: model.to};
};

/**
 * Take the day under the pointer, or none.
 *
 * @param {object} model The state.
 * @param {string} iso The hovered day, or the empty string.
 * @returns {object} The new state, the same object when nothing changes.
 */
export const hover = (model: Model, iso: string): Model => (model.hover === iso ? model : {...model, hover: iso});

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
 * The path a quick option, the back button, "Clear period" and a cleared filter take. The caller that set it
 * knows what to do next, so nothing here reports a change: telling the listing would fetch the page the
 * caller has just fetched. A period that really changed moves the active box as {@see modeFor()} says and
 * brings its first day on screen; the same period - the echo of the control's own press - changes nothing.
 *
 * @param {object} model The state.
 * @param {string} start The first day as given, or anything else.
 * @param {string} end The last day as given, or anything else.
 * @param {number} count How many months are on screen.
 * @returns {object} The new state.
 */
export const set = (model: Model, start: unknown, end: unknown, count: number): Model => {
    const given = period(start, end);
    if (given.from === model.from && given.to === model.to) {
        return model;
    }
    const day = given.from || given.to;
    const next: Model = {...model, from: given.from, to: given.to, mode: modeFor(given.from, given.to), hover: ''};
    if (day === '' || onScreen(model.cursor, count, day)) {
        return next;
    }
    return {...next, focused: day, cursor: bringIntoView(model.cursor, count, day)};
};
