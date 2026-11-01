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
 * How an item looks and reads: its colour class or its category's pair, its place, its time, its labels.
 *
 * Shared by the bars and the day cells, so a bar and a line of the same item never disagree.
 *
 * @module     theme_dimensions/calendar/look
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {CSSProperties} from 'react';
import {say} from '../daterange/calendar';
import {hex} from './model';
import type {Category, Colour, Course, Item} from './model';
import type {Zone} from './days';
import {fill} from './strings';
import type {Strings} from './strings';

/** What every item view needs from the page. */
export type Look = {
    strings: Strings,
    zone: Zone,
    language: string | undefined,
    colour: Colour,
    courses: Map<number, Course>,
    categories: Map<number, Category>,
    home: string,
};

/** The day and month of a span's ends. */
const SPAN_DAY: Intl.DateTimeFormatOptions = {day: 'numeric', month: 'long'};

/**
 * The key class of an item: its key, or overdue for an overdue one.
 *
 * @param {object} item The item.
 * @returns {string} The class.
 */
export const keyClass = (item: Item): string => `dim-cal-key--${item.overdue ? 'overdue' : item.key}`;

/**
 * The colours an item is painted in when the reader colours by category: its category's branding pair, whole.
 *
 * An overdue item keeps its warning colours, and an item with no nameable category keeps its key's.
 *
 * @param {object} item The item.
 * @param {object} look The page's look.
 * @returns {object} The local colour properties, or nothing.
 */
export const colourStyle = (item: Item, look: Look): CSSProperties => {
    if (look.colour !== 'category' || item.overdue) {
        return {};
    }
    const categoryid = item.categoryid || look.courses.get(item.courseid)?.categoryid || 0;
    const category = look.categories.get(categoryid);
    const bg = category ? hex(category.bg) : null;
    const ink = category ? hex(category.ink) : null;
    if (!bg || !ink) {
        return {};
    }
    return {'--dim-cal-tint': bg, '--dim-cal-edge': ink, '--dim-cal-ink': ink} as CSSProperties;
};

/**
 * Where an item happens: its location, else its course's city and venue, else its course's name.
 *
 * @param {object} item The item.
 * @param {object} look The page's look.
 * @returns {string} The place, or the empty string.
 */
export const placeOf = (item: Item, look: Look): string => {
    if (item.location !== '') {
        return item.location;
    }
    const course = look.courses.get(item.courseid);
    if (!course) {
        return '';
    }
    return [course.city, course.venue].filter((part) => part !== '').join(' · ') || course.fullname;
};

/**
 * When a day's item happens: its time range, or the deadline of an item of no duration.
 *
 * @param {object} item The item.
 * @param {object} look The page's look.
 * @returns {string}
 */
export const whenOf = (item: Item, look: Look): string => {
    const start = look.zone.time(item.timestart);
    if (item.timeduration > 0) {
        return `${start}–${look.zone.time(item.timestart + item.timeduration)}`;
    }
    return item.overdue ? `${look.strings.calendar_overdue} · ${start}` : fill(look.strings.calendar_until, start);
};

/**
 * A bar's label: its name and its whole span ("10 to 12 November"), not the week's piece of it.
 *
 * @param {object} item The item.
 * @param {string} first Its first day.
 * @param {string} last Its last day.
 * @param {object} look The page's look.
 * @returns {string}
 */
export const barLabel = (item: Item, first: string, last: string, look: Look): string => {
    const from = say(first, SPAN_DAY, look.language);
    const span = first === last ? from : fill(look.strings.calendar_span, {from: from, to: say(last, SPAN_DAY, look.language)});
    return fill(look.strings.calendar_barlabel, {name: item.name, span: span});
};
