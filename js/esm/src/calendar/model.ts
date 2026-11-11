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
 * The calendar page's data: the range's answer, the page's state, and the addresses both are spelled in.
 *
 * The answer is the range service's (classes/local/calendar/range.php, get_calendar_range's returns); the state is
 * classes/local/calendar/state.php's canonical array, and {@see query()} spells it as state::query() does, so an
 * address the client writes is one the server reads back as the same state. With JavaScript an item opens the event
 * popover and a day the day drawer (or the phone's sheet); {@see itemHref()} stays every item's href, for a middle
 * click or a copied link. The popover's primary action is chosen in one place, {@see primaryOf()}.
 *
 * @module     theme_dimensions/calendar/model
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {parse} from '../daterange/calendar';
import type {TrailProps} from './trails';

/** The eight keys, in the order the chips are drawn (keys::ALL). */
export const KEYS = ['site', 'category', 'course', 'group', 'user', 'activity', 'other', 'open'] as const;

/** One key. */
export type Key = typeof KEYS[number];

/** The action core offers on an action event. */
export type Action = {name: string, url: string, itemcount: number, actionable: boolean};

/** One item of the answer: one of core's events, or an event course. */
export type Item = {
    id: number,
    source: string,
    key: Key,
    name: string,
    timestart: number,
    timeduration: number,
    courseid: number,
    categoryid: number,
    groupid: number,
    modulename: string,
    instance: number,
    component: string,
    eventtype: string,
    location: string,
    url: string,
    actionevent: boolean,
    action: Action | null,
    overdue: boolean,
    held: boolean,
};

/** One course of the answer's dictionary. */
export type Course = {
    id: number,
    fullname: string,
    shortname: string,
    categoryid: number,
    url: string,
    image: string,
    model: number,
    modality: number,
    city: string,
    venue: string,
    landingurl: string,
    enrolled: boolean,
    participants: number | null,
    join: string,
};

/** One category of the answer's dictionary, with its branding pair. */
export type Category = {id: number, name: string, bg: string, ink: string};

/**
 * Core's editing answers for one event the viewer may edit (local\calendar\editing::entries()): the summary's Edit,
 * core's candelete and draggable, the series' count, and the drop limits as days with core's messages.
 */
export type Edit = {
    id: number,
    edit: boolean,
    delete: boolean,
    drag: boolean,
    count: number,
    minday: string,
    maxday: string,
    minerror: string,
    maxerror: string,
};

/** The range's whole answer for one window; `editing` is the logged-in range's (the visitor's is empty). */
export type Answer = {
    from: string,
    to: string,
    truncated: boolean,
    events: Item[],
    courses: Course[],
    categories: Category[],
    days: Array<{date: string, counts: Record<string, number>}>,
    editing?: Edit[],
};

/** The colourings (state::COLOURS). */
export type Colour = 'key' | 'category';

/** What the page shows (state::to_array()). */
export type State = {
    off: Key[],
    categoryid: number,
    recursive: boolean,
    model: number[],
    modality: number[],
    city: string,
    actiononly: boolean,
    colour: Colour,
};

/** One category the picker offers (local\calendar\tree). */
export type TreeNode = {id: number, name: string, parent: number, depth: number, bg: string, ink: string, courses: number};

/** One option of a course select field. */
export type Option = {value: number, label: string};

/** Core's answers for creating (calendar_page::props()): whether "New event" shows, and what core's form is handed. */
export type Edits = {create: boolean, context: number, course: number};

/** The page's props, as calendar_page::props() builds them. */
export type Props = {
    month: string,
    view: string,
    week: string,
    day: string,
    today: string,
    now: number,
    firstday: number,
    timezone: string,
    answer: Answer,
    state: State,
    tree: TreeNode[],
    options: {model: Option[], modality: Option[], city: string[]},
    urls: {page: string, home: string},
    edits?: Edits,
    trails?: TrailProps,
};

/** The address parameter of the open day (local\calendar\page::PARAM_DAY): navigation, never state. */
export const DAY_PARAM = 'day';

/** A fresh page's state (new state()). */
export const DEFAULT_STATE: State = {
    off: [],
    categoryid: 0,
    recursive: true,
    model: [],
    modality: [],
    city: '',
    actiononly: false,
    colour: 'key',
};

/**
 * The keys that are on, in vocabulary order.
 *
 * @param {object} state The state.
 * @returns {Array} The keys.
 */
export const keysOn = (state: State): Key[] => KEYS.filter((key) => !state.off.includes(key));

/**
 * Whether two states are the same state.
 *
 * @param {object} a One state.
 * @param {object} b The other.
 * @returns {boolean}
 */
export const sameState = (a: State, b: State): boolean => JSON.stringify(normalise(a)) === JSON.stringify(normalise(b));

/**
 * A state in its canonical order, every list sorted: what the server would store for it.
 *
 * @param {object} state The state.
 * @returns {object} The canonical state.
 */
export const normalise = (state: State): State => ({
    off: KEYS.filter((key) => state.off.includes(key)),
    categoryid: Math.max(0, Math.trunc(Number(state.categoryid) || 0)),
    recursive: state.recursive !== false,
    model: [...new Set(state.model)].sort((a, b) => a - b),
    modality: [...new Set(state.modality)].sort((a, b) => a - b),
    city: String(state.city || '').trim(),
    actiononly: state.actiononly === true,
    colour: state.colour === 'category' ? 'category' : 'key',
});

/**
 * The address parameters of a state, spelled as state::query() spells them.
 *
 * @param {object} state The state.
 * @returns {Array} [name, value] pairs, arrays as name[] members.
 */
export const query = (state: State): Array<[string, string]> => {
    const pairs: Array<[string, string]> = [];
    const on = keysOn(state);
    if (state.off.length) {
        (on.length ? on : ['']).forEach((key) => pairs.push(['keys[]', key]));
    }
    if (state.categoryid) {
        pairs.push(['categoryid', String(state.categoryid)]);
    }
    pairs.push(['recursive', state.recursive ? '1' : '0']);
    state.model.forEach((value) => pairs.push(['model[]', String(value)]));
    state.modality.forEach((value) => pairs.push(['modality[]', String(value)]));
    if (state.city !== '') {
        pairs.push(['city', state.city]);
    }
    if (state.actiononly) {
        pairs.push(['actiononly', '1']);
    }
    pairs.push(['colour', state.colour]);
    return pairs;
};

/**
 * The page's address for a month, a state and the open day.
 *
 * @param {string} base The page's address without a query (urls.page).
 * @param {string} month The month, YYYY-MM.
 * @param {object} state The state.
 * @param {string} day The open day, YYYY-MM-DD, or '' for none.
 * @returns {string} The address.
 */
export const address = (base: string, month: string, state: State, day = ''): string => {
    const url = new URL(base, window.location.href);
    url.search = '';
    url.searchParams.append('month', month);
    if (day !== '') {
        url.searchParams.append(DAY_PARAM, day);
    }
    query(state).forEach(([name, value]) => url.searchParams.append(name, value));
    return url.toString();
};

/**
 * An address with its open day set, or taken out: the rest of it is left as written, so opening a day never turns
 * a bare address into one naming the state.
 *
 * @param {string} href The address.
 * @param {string} day The open day, YYYY-MM-DD, or '' to take it out.
 * @returns {string} The address.
 */
export const withDay = (href: string, day: string): string => {
    const url = new URL(href, window.location.href);
    if (day === '') {
        url.searchParams.delete(DAY_PARAM);
    } else {
        url.searchParams.set(DAY_PARAM, day);
    }
    return url.toString();
};

/**
 * Whether a string is a month, YYYY-MM.
 *
 * @param {string} month The candidate.
 * @returns {boolean}
 */
export const isMonth = (month: unknown): month is string => parse(`${String(month)}-01`) !== null
    && /^\d{4}-\d{2}$/.test(String(month));

/**
 * The month and the state an address names: the state null when the address names none of it, as the server reads it.
 *
 * @param {string} href The address.
 * @returns {object} {month: string|null, state: object|null}.
 */
export const readAddress = (href: string): {month: string | null, state: State | null} => {
    const params = new URL(href, window.location.href).searchParams;
    const month = params.get('month');
    const named = ['keys[]', 'categoryid', 'recursive', 'model[]', 'modality[]', 'city', 'actiononly', 'colour']
        .some((name) => params.has(name));
    if (!named) {
        return {month: isMonth(month) ? month : null, state: null};
    }
    const keys = params.getAll('keys[]');
    /**
     * The whole numbers an array parameter holds.
     *
     * @param {string} name The parameter.
     * @returns {Array} The numbers.
     */
    const ints = (name: string): number[] => params.getAll(name).map(Number).filter((value) => Number.isInteger(value));
    return {
        month: isMonth(month) ? month : null,
        state: normalise({
            off: params.has('keys[]') ? KEYS.filter((key) => !keys.includes(key)) : [],
            categoryid: Number(params.get('categoryid') || 0),
            recursive: params.get('recursive') !== '0',
            model: ints('model[]'),
            modality: ints('modality[]'),
            city: params.get('city') || '',
            actiononly: params.get('actiononly') === '1',
            colour: params.get('colour') === 'category' ? 'category' : 'key',
        }),
    };
};

/**
 * The range service's arguments for a window and a state; called only with a key on (the service reads none as all).
 *
 * @param {string} from The first day.
 * @param {string} to The last day.
 * @param {object} state The state.
 * @returns {object} The arguments.
 */
export const rangeArgs = (from: string, to: string, state: State): Record<string, unknown> => ({
    from: from,
    to: to,
    keys: keysOn(state),
    categoryid: state.categoryid,
    recursive: state.recursive,
    model: state.model,
    modality: state.modality,
    city: state.city,
    actiononly: state.actiononly,
});

/**
 * The answer for a window with every key off: no item, every day at zero. Nothing is asked of the server.
 *
 * @param {Array} days The window's days.
 * @returns {object} The answer.
 */
export const emptyAnswer = (days: string[]): Answer => ({
    from: days[0],
    to: days[days.length - 1],
    truncated: false,
    events: [],
    courses: [],
    categories: [],
    days: days.map((date) => ({date: date, counts: Object.fromEntries(KEYS.map((key) => [key, 0]))})),
});

/**
 * An address from the answer, kept only when it is a web address: relative, http or https.
 *
 * Every address the server builds and the page puts in an href goes through here, so a `javascript:` or `data:`
 * address an activity module put in an action event never runs on a click.
 *
 * @param {string} url The address the answer carries.
 * @param {string} fallback What to use instead.
 * @returns {string} The address as given, or the fallback.
 */
export const safeHref = (url: string, fallback: string): string => {
    try {
        const {protocol} = new URL(url, window.location.href);
        return url !== '' && (protocol === 'http:' || protocol === 'https:') ? url : fallback;
    } catch {
        return fallback;
    }
};

/** The one data address an image may carry: the cover the theme's renderer draws (local\card\cover::PREFIX), whole. */
const COVER_DATA = /^data:image\/svg\+xml;base64,[A-Za-z0-9+/]+={0,2}$/;

/**
 * An image's address from the answer: a web address ({@see safeHref()}), or the SVG cover of a course with no image of
 * its own as the server spells it (local\card\cover::data_uri(), core's geometric pattern), base64 and nothing else.
 *
 * @param {string} url The address the answer carries.
 * @returns {string} The address as given, or ''.
 */
export const safeImage = (url: string): string => (COVER_DATA.test(url) ? url : safeHref(url, ''));

/**
 * Where an item leads: its own page, else its course's page, else the site's home (calendar_page::item_url()).
 *
 * The item's href in the grid, the drawer and the agenda: with JavaScript a press opens the popover, and this address
 * is what a middle click and a copied link follow. The home is the fallback only because a link needs an href; the
 * popover never offers it ({@see primaryOf()}).
 *
 * @param {object} item The item.
 * @param {Map} courses The answer's courses by id.
 * @param {string} home The site's home.
 * @returns {string} The address.
 */
export const itemHref = (item: Item, courses: Map<number, Course>, home: string): string => {
    const own = safeHref(item.url, '');
    if (own !== '') {
        return own;
    }
    return safeHref(courses.get(item.courseid)?.url ?? '', safeHref(home, '/'));
};

/** The popover's primary action: core's action, the event course's way in, the item's own page, or none. */
export type Primary =
    {kind: 'action', name: string, url: string}
    | {kind: 'join', join: 'open' | 'free' | 'key', url: string}
    | {kind: 'open', url: string}
    | {kind: 'none'};

/**
 * Whether an item has a page of its own to open: its own address, or its course's (a real course: the answer's
 * dictionary never holds the site course).
 *
 * A personal event and a site event have neither; their href in the grid falls back to the site's home only because a
 * link needs one ({@see itemHref()}), and the popover offers no "Open" for them.
 *
 * @param {object} item The item.
 * @param {Map} courses The answer's courses by id.
 * @returns {boolean}
 */
export const opens = (item: Item, courses: Map<number, Course>): boolean =>
    safeHref(item.url, '') !== '' || safeHref(courses.get(item.courseid)?.url ?? '', '') !== '';

/**
 * The popover's primary action for an item: the item's own, never a guess.
 *
 * - An action event core says the viewer can act on now (`actionable`) leads to core's action, under its name.
 * - An event course the viewer may join (the answer's `join`, local_unlistedcourses' next action as the showcase
 *   reads it) leads to its landing page, whose enrolment is the way in.
 * - An item with a page of its own ({@see opens()}) is "Open", {@see itemHref()}.
 * - Anything else (a personal event, a site event) has none: never the site's home.
 *
 * @param {object} item The item.
 * @param {Map} courses The answer's courses by id.
 * @param {string} home The site's home.
 * @returns {object} The primary action.
 */
export const primaryOf = (item: Item, courses: Map<number, Course>, home: string): Primary => {
    const actionUrl = item.action === null ? '' : safeHref(item.action.url, '');
    if (item.action !== null && item.action.actionable && actionUrl !== '') {
        return {kind: 'action', name: item.action.name, url: actionUrl};
    }
    const course = courses.get(item.courseid);
    if (item.source === 'course' && course && (course.join === 'open' || course.join === 'free' || course.join === 'key')) {
        return {kind: 'join', join: course.join, url: safeHref(course.landingurl, itemHref(item, courses, home))};
    }
    if (!opens(item, courses)) {
        return {kind: 'none'};
    }
    return {kind: 'open', url: itemHref(item, courses, home)};
};

/**
 * Whether any filter is applied: a key off, a "More filters" value, or a category scope other than the whole calendar
 * with its subcategories. The colouring is how the page draws, not a filter. Handed the options "More filters" offers,
 * a list counts only when it narrows them ({@see narrows()}), as the panel's badge counts it.
 *
 * @param {object} state The state.
 * @param {object} options The model's and the modality's options, when the page has them.
 * @returns {boolean}
 */
export const filtered = (state: State, options?: {model: Option[], modality: Option[]}): boolean => state.off.length > 0
    || (options ? narrows(state.model, options.model) : state.model.length > 0)
    || (options ? narrows(state.modality, options.modality) : state.modality.length > 0)
    || state.city !== '' || state.actiononly || state.categoryid !== 0 || !state.recursive;

/**
 * Whether an option is drawn checked in "More filters": the server reads an empty list as every option (state.php), so
 * an empty list draws every box on, as the event types' dropdown draws every key on.
 *
 * @param {Array} kept The values kept, empty for every one.
 * @param {number} value The option's value.
 * @returns {boolean}
 */
export const shownOn = (kept: number[], value: number): boolean => kept.length === 0 || kept.includes(value);

/**
 * Whether a kept list narrows anything: it misses one of the options offered. A list naming every option (a saved
 * state from before an option went, or from the no-JavaScript form) narrows nothing.
 *
 * @param {Array} kept The values kept, empty for every one.
 * @param {Array} choices The options offered.
 * @returns {boolean}
 */
export const narrows = (kept: number[], choices: Option[]): boolean => kept.length > 0
    && choices.some((option) => !kept.includes(option.value));

/**
 * The list kept once a box is checked or unchecked: the options drawn checked after the press, in the options' order,
 * and the empty list again when that is every option, so a saved "all" stays empty and an option added later starts
 * on. Unchecking the last box on changes nothing: an empty list would read as every option, and "none" has no spelling
 * in the server's contract (the panel disables that box).
 *
 * @param {Array} kept The values kept, empty for every one.
 * @param {Array} choices The options offered.
 * @param {number} value The option pressed.
 * @param {boolean} on Whether it is now checked.
 * @returns {Array} The values kept.
 */
export const keptAfter = (kept: number[], choices: Option[], value: number, on: boolean): number[] => {
    const next = choices.map((option) => option.value)
        .filter((other) => (other === value ? on : shownOn(kept, other)));
    if (!next.length) {
        return kept;
    }
    return next.length === choices.length ? [] : next;
};

/**
 * The fields "Clear filters" puts back, in one change: every key on, no "More filters" value, the whole calendar.
 *
 * @returns {object} The fields, the colouring left as it is.
 */
export const cleared = (): Partial<State> => ({
    off: [],
    model: [],
    modality: [],
    city: '',
    actiononly: false,
    categoryid: 0,
    recursive: true,
});

/**
 * A colour the server certified, as #rrggbb, or null: what reaches a style property is checked again here.
 *
 * @param {string} value The candidate.
 * @returns {string|null}
 */
export const hex = (value: unknown): string | null => (/^#[0-9a-f]{6}$/i.test(String(value)) ? String(value) : null);
