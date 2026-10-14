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
 * The one calendar of the category showcase: a start box and an end box over one popover.
 *
 * The toolbar without this module is two native date fields and four links, and that page is
 * complete: it posts `from` and `to` and the server resolves both in the site timezone. This
 * module puts two boxes over that pair - the pair stays in the document, hidden, still carrying
 * the parameters - and both open the same popover of two months. Either bound may be set alone,
 * and what each one keeps is the server's rule (criteria): a start alone keeps the courses
 * starting on that day or later, an end alone those starting on that day or earlier.
 *
 * Which bound a press of a day sets is the box that opened the popover, its mode. In the start's
 * mode the press sets the start, drops an end that would now come before it, and moves to the
 * end's mode, so two presses describe a range. In the end's mode the press sets the end, unless
 * it falls before the start: then it begins the period again from that day and the end is asked
 * for again. A period is therefore never described backwards (see press()).
 *
 * The presses make a draft, and the reader ends the choice: "Done" applies it, a press outside
 * the control applies it too, and Escape discards it and leaves the period that was in force.
 * Nothing is fetched, written to the address or counted on the filter badge in between. The
 * quick options beside the boxes, each box's own clearing control and the footer's are one-press
 * intents and apply at once. Every change of the period in force goes through apply().
 *
 * It does no timezone arithmetic. Every boundary is resolved in the site's timezone by PHP: the
 * quick options carry theirs on the link, the date the site calls today arrives on the fieldset
 * (data-today), and the day a week begins on beside it (data-firstday). The browser's clock reads
 * the reader's timezone, which differs from the site's for part of every day, so a calendar that
 * asked it what today is would mark a different day from the one the "Today" link filters by.
 *
 * The calendar arithmetic itself - weekday of a date, length of a month, the day either side of
 * one - runs on the {y, m, d} of an ISO string through Date.UTC, never through a local-time Date:
 * UTC has no offset and no daylight saving, so a day added is a day added.
 *
 * @module     theme_dimensions/daterange
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import Templates from 'core/templates';
import {getString, getStrings} from 'core/str';

/** @type {Object} Every hook this module reads, all of them written by its own template. */
const SELECTORS = {
    box: '[data-action="dim-daterange-open"]',
    label: '[data-region="dim-daterange-label"]',
    value: '[data-region="dim-daterange-value"]',
    clear: '[data-action="dim-daterange-clear"]',
    done: '[data-action="dim-daterange-done"]',
    pop: '[data-region="dim-daterange-pop"]',
    prompt: '[data-region="dim-daterange-prompt"]',
    monthblock: '[data-region="dim-daterange-monthblock"]',
    month: '[data-region="dim-daterange-month"]',
    grid: '[data-region="dim-daterange-grid"]',
    prev: '[data-action="dim-daterange-prev"]',
    next: '[data-action="dim-daterange-next"]',
    day: '[data-region="dim-daterange-day"]',
};

/** @type {String} The template this module mounts, rendered by core and never assembled here. */
const TEMPLATE = 'theme_dimensions/category_daterange';

/** @type {String} The component every string of this control belongs to. */
const COMPONENT = 'theme_dimensions';

/** @type {String} The start's bound, named as its query parameter and the box's data-bound. */
const FROM = 'from';

/** @type {String} The end's bound, named the same way. */
const TO = 'to';

/** @type {Number} How many day cells a week row holds. */
const WEEK = 7;

/** @type {String} A week beginning on a Sunday, which is where the weekday names are read from. */
const NAME_WEEK = '2026-01-04';

/**
 * @type {String} Where the popover shows two months rather than one.
 *
 * The stylesheet widens the popover from the same breakpoint (category_scss_test holds the two
 * numbers together): below it a second month would not fit beside the first, and a month the
 * stylesheet hid while this module walked the focus into it would take the focus out of the
 * control and close it.
 */
const WIDE = '(min-width: 768px)';

/** @type {Object} The Intl options every date of the boxes and the pill is written with. */
const MEDIUM = {dateStyle: 'medium'};

/** @type {HTMLElement|null} The mounted control. */
let root = null;

/** @type {HTMLElement|null} The popover. */
let pop = null;

/** @type {Object} The two boxes, keyed by their bound. */
let boxes = {};

/** @type {HTMLElement|null} The box the focus goes back to when the popover closes: the mode's. */
let field = null;

/** @type {MediaQueryList|null} Whether the popover is wide enough for two months. */
let wide = null;

/** @type {HTMLElement|null} The region of the count row the removable pill is written into. */
let pillregion = null;

/** @type {Object} The strings this module writes itself, fetched once at mount. */
let strings = {clear: '', choosefrom: '', chooseto: ''};

/** @type {String} The first day of the period in force, YYYY-MM-DD, or the empty string. */
let from = '';

/** @type {String} The last day of the period in force, YYYY-MM-DD, or the empty string. */
let to = '';

/**
 * @type {String} The first day the control is showing: the period in force, or a draft of it.
 *
 * The presses write this pair and the boxes and the grids show it; nothing is applied until
 * "Done" is pressed or the reader presses outside the control (see commit()). Escape discards it
 * and puts the period in force back (see close()). While the popover is shut the pair equals the
 * one in force.
 */
let draftfrom = '';

/** @type {String} The last day the control is showing, by the same rule. */
let draftto = '';

/** @type {String} The bound the next press of a day sets: FROM or TO. */
let mode = FROM;

/** @type {Object|null} The first month on screen, as a plain {y, m} with m from 1 to 12. */
let cursor = null;

/** @type {String} The day the roving tabindex is on, YYYY-MM-DD. */
let focused = '';

/** @type {Number} The weekday a week begins on here, 0 for Sunday: the site's, never a guess. */
let firstday = 0;

/** @type {String} The day the site calls today, YYYY-MM-DD, as the server wrote it. */
let today = '';

/** @type {Function|null} What to call with the two bounds whenever the period in force changes. */
let onpick = null;

/**
 * How many days a month holds.
 *
 * @param {Number} y The year.
 * @param {Number} m The month, 1 to 12.
 * @return {Number} The number of days.
 */
const monthLength = (y, m) => new Date(Date.UTC(y, m, 0)).getUTCDate();

/**
 * An ISO date as the triple the arithmetic below runs on.
 *
 * The shape is the server's (criteria::parse_date()) and so is the refusal of a day the calendar
 * does not have, such as the 31st of September: a value the server would drop must not reach a
 * request it would refuse.
 *
 * @param {String} iso A date as YYYY-MM-DD.
 * @return {Object|null} The {y, m, d} triple, or null when the string is not a date.
 */
const parse = (iso) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || ''));
    if (!match) {
        return null;
    }
    const parts = {y: Number(match[1]), m: Number(match[2]), d: Number(match[3])};
    if (parts.m < 1 || parts.m > 12 || parts.d < 1 || parts.d > monthLength(parts.y, parts.m)) {
        return null;
    }
    return parts;
};

/**
 * The triple back as an ISO date.
 *
 * @param {Object} parts The {y, m, d} triple.
 * @return {String} The date as YYYY-MM-DD.
 */
const format = (parts) => String(parts.y).padStart(4, '0')
    + '-' + String(parts.m).padStart(2, '0')
    + '-' + String(parts.d).padStart(2, '0');

/**
 * The instant at midnight UTC of a calendar day, which is the only Date this module builds.
 *
 * UTC throughout, and deliberately: a local-time Date shifts by the reader's own offset, and a
 * day either side of a daylight saving transition is then 23 or 25 hours long. Nothing here is
 * an instant in anybody's day - these are calendar squares - so the timezone with no offset and
 * no transitions is the right one to count them in.
 *
 * @param {Object} parts The {y, m, d} triple.
 * @return {Date} Midnight UTC of that day.
 */
const utc = (parts) => new Date(Date.UTC(parts.y, parts.m - 1, parts.d));

/**
 * The calendar day a whole number of days after another one.
 *
 * @param {String} iso A date as YYYY-MM-DD.
 * @param {Number} days Whole days to add, negative to go back.
 * @return {String} The date as YYYY-MM-DD, or the empty string when the input was not a date.
 */
const shift = (iso, days) => {
    const parts = parse(iso);
    if (!parts) {
        return '';
    }
    const moved = utc(parts);
    moved.setUTCDate(moved.getUTCDate() + days);
    return format({y: moved.getUTCFullYear(), m: moved.getUTCMonth() + 1, d: moved.getUTCDate()});
};

/**
 * The calendar day a whole number of months after another one, clamped to the month's length.
 *
 * The clamp is what makes "one month after the 31st of January" the 28th or 29th of February
 * rather than the 2nd or 3rd of March, which is where a bare setUTCMonth() rolls it to.
 *
 * @param {String} iso A date as YYYY-MM-DD.
 * @param {Number} months Whole months to add, negative to go back.
 * @return {String} The date as YYYY-MM-DD, or the empty string when the input was not a date.
 */
const shiftMonth = (iso, months) => {
    const parts = parse(iso);
    if (!parts) {
        return '';
    }
    const moved = utc({y: parts.y, m: parts.m, d: 1});
    moved.setUTCMonth(moved.getUTCMonth() + months);
    const y = moved.getUTCFullYear();
    const m = moved.getUTCMonth() + 1;
    return format({y: y, m: m, d: Math.min(parts.d, monthLength(y, m))});
};

/**
 * A period as the server keeps it: each bound a real day or nothing, and the two in order.
 *
 * The one reading of a period that arrives from outside this control - an address, a quick
 * option, the listing's state - in this module and in the two that send a period to the web
 * service. It is criteria's rule written in the client: a bound that is not a date is dropped and
 * the other stands alone, and a pair given backwards is the same period in order. The web service
 * refuses a pair given backwards, so a reader of the address that skipped this would turn a
 * hand-edited link into an error.
 *
 * @param {String} start The first day as given, or anything else.
 * @param {String} end The last day as given, or anything else.
 * @return {Object} The {from, to} pair, each YYYY-MM-DD or the empty string.
 */
export const period = (start, end) => {
    const first = parse(start) ? String(start) : '';
    const last = parse(end) ? String(end) : '';
    if (first !== '' && last !== '' && last < first) {
        return {from: last, to: first};
    }
    return {from: first, to: last};
};

/**
 * The document's language, which is what every name this control prints is formatted in.
 *
 * @return {String|undefined} The language tag, or undefined for the browser's own default.
 */
const language = () => document.documentElement.lang || undefined;

/**
 * One date, written the way the reader's language writes a date.
 *
 * @param {String} iso A date as YYYY-MM-DD.
 * @param {Object} options The Intl options.
 * @return {String} The formatted date, the ISO string when Intl cannot format it, or the empty
 *   string when the input is not a date.
 */
const say = (iso, options) => {
    const parts = parse(iso);
    if (!parts) {
        return '';
    }
    try {
        return new Intl.DateTimeFormat(language(), Object.assign({timeZone: 'UTC'}, options)).format(utc(parts));
    } catch (error) {
        return iso;
    }
};

/**
 * Whether the popover is open.
 *
 * @return {Boolean}
 */
const isOpen = () => Boolean(pop) && !pop.hidden;

/**
 * A period in the reader's own language, which is what the pill of the count row prints.
 *
 * Three sentences, one per shape a period may take, each a language string: a range, a start
 * alone ("From 17 Sep 2026") and an end alone ("Until 24 Sep 2026").
 *
 * @param {String} start The first day, YYYY-MM-DD, or the empty string.
 * @param {String} end The last day, YYYY-MM-DD, or the empty string.
 * @return {Promise} Resolving to the sentence, or to the empty string when there is no period.
 */
const sentence = async(start, end) => {
    const first = say(start, MEDIUM);
    const last = say(end, MEDIUM);
    if (first !== '' && last !== '') {
        return getString('category_daterangepill', COMPONENT, {from: first, to: last});
    }
    if (first !== '') {
        return getString('category_daterangepillfrom', COMPONENT, first);
    }
    if (last !== '') {
        return getString('category_daterangepillto', COMPONENT, last);
    }
    return '';
};

/**
 * Write both boxes from the period they show: the date, the label, the clearing control, the mode.
 *
 * A box's label is its name while it is empty and leaves the picture once it holds a date; it
 * stays in the box's accessible name either way, so a screen reader reads "From, 17 Sep 2026".
 *
 * @return {void}
 */
const paintBoxes = () => {
    [FROM, TO].forEach((bound) => {
        const box = boxes[bound];
        const shown = bound === TO ? draftto : draftfrom;
        const slot = box.querySelector(SELECTORS.value);
        const label = box.querySelector(SELECTORS.label);
        const clear = root.querySelector(SELECTORS.clear + '[data-bound="' + bound + '"]');
        if (slot) {
            slot.textContent = say(shown, MEDIUM);
        }
        if (label) {
            label.classList.toggle('visually-hidden', shown !== '');
        }
        if (clear) {
            clear.hidden = shown === '';
        }
        box.classList.toggle('dim-daterange__field--active', isOpen() && bound === mode);
    });
    root.classList.toggle('dim-daterange--set', draftfrom !== '' || draftto !== '');
};

/**
 * Write the removable pill of the count row from the period in force.
 *
 * The pill is the same period in the one row a reader who collapsed the filter panel can still
 * see, and it is a control rather than a label because the period it names is the one filter
 * with nowhere else to drop it from once the panel is shut. It states the period in force, never
 * a draft: it is where a reader who shut the panel reads what the listing is narrowed by.
 *
 * @return {Promise} Resolving when the pill has been written.
 */
const paintPill = async() => {
    if (!pillregion) {
        return;
    }
    const applied = await sentence(from, to);
    if (applied === '') {
        pillregion.replaceChildren();
        return;
    }
    const pill = document.createElement('button');
    pill.type = 'button';
    pill.className = 'dim-rangepill';
    pill.dataset.action = 'dim-daterange-clear';
    pill.append(document.createTextNode(applied + ' '));
    const cross = document.createElement('i');
    cross.className = 'fa fa-xmark';
    cross.setAttribute('aria-hidden', 'true');
    const name = document.createElement('span');
    name.className = 'visually-hidden';
    name.textContent = strings.clear;
    pill.append(cross, name);
    pillregion.replaceChildren(pill);
};

/**
 * Write the sentence above the months that says which bound the next press sets.
 *
 * It is a live region, so it is written only when it changes: a mode a reader already heard is
 * not read to them again on every press.
 *
 * @return {void}
 */
const paintPrompt = () => {
    const prompt = root.querySelector(SELECTORS.prompt);
    const text = mode === TO ? strings.chooseto : strings.choosefrom;
    if (prompt && prompt.textContent !== text) {
        prompt.textContent = text;
    }
};

/**
 * The row of weekday names, beginning on the day the site begins its weeks on.
 *
 * The names are the browser's own for the document's language, read off seven consecutive days
 * of a real week rather than from a table of our own - a table would be a second spelling of
 * every language pack on the site.
 *
 * @return {HTMLElement} The header row.
 */
const headRow = () => {
    const row = document.createElement('div');
    row.className = 'dim-daterange__row dim-daterange__row--head';
    row.setAttribute('role', 'row');
    for (let index = 0; index < WEEK; index++) {
        const cell = document.createElement('span');
        cell.className = 'dim-daterange__dow';
        cell.setAttribute('role', 'columnheader');
        const day = shift(NAME_WEEK, (firstday + index) % WEEK);
        cell.textContent = say(day, {weekday: 'short'});
        cell.setAttribute('aria-label', say(day, {weekday: 'long'}));
        row.append(cell);
    }
    return row;
};

/**
 * One empty square, so that a week's columns line up with the names above them.
 *
 * @return {HTMLElement} The blank cell.
 */
const blank = () => {
    const cell = document.createElement('span');
    cell.className = 'dim-daterange__day dim-daterange__day--blank';
    cell.setAttribute('role', 'gridcell');
    return cell;
};

/**
 * One day of a month, as the button that picks it.
 *
 * A real button, so that Enter and Space activate it with no key handler of our own: the two
 * keys the grid pattern asks for are the two a button already answers, and a handler that
 * re-implemented them would also have to suppress the button's own activation to keep from
 * picking the same day twice.
 *
 * The marking is the period the control shows, a draft included. Each bound that is set is a
 * filled end; the days strictly between the two are tinted, and only when both are set - a start
 * alone or an end alone marks its one day, because the period it keeps runs off the calendar.
 *
 * @param {String} iso The day, as YYYY-MM-DD.
 * @return {HTMLElement} The day cell.
 */
const dayCell = (iso) => {
    const cell = document.createElement('button');
    cell.type = 'button';
    cell.className = 'dim-daterange__day';
    cell.setAttribute('role', 'gridcell');
    cell.dataset.region = 'dim-daterange-day';
    cell.dataset.date = iso;
    cell.textContent = String(parse(iso).d);
    // The name of a day cell is the date and not the number: "18" alone is read out of its month.
    cell.setAttribute('aria-label', say(iso, {dateStyle: 'long'}));
    cell.tabIndex = iso === focused ? 0 : -1;
    const both = draftfrom !== '' && draftto !== '';
    const edge = iso === draftfrom || iso === draftto;
    const inside = both && iso > draftfrom && iso < draftto;
    cell.setAttribute('aria-selected', edge || inside ? 'true' : 'false');
    cell.classList.toggle('dim-daterange__day--in', inside);
    cell.classList.toggle('dim-daterange__day--edge', edge);
    // The ends of a range join the tint between them; a range of one day is one round end.
    cell.classList.toggle('dim-daterange__day--start', both && iso === draftfrom && draftfrom !== draftto);
    cell.classList.toggle('dim-daterange__day--end', both && iso === draftto && draftfrom !== draftto);
    if (iso === today) {
        cell.setAttribute('aria-current', 'date');
    }
    return cell;
};

/**
 * The weeks of one month, each a row of seven cells, blanks included.
 *
 * @param {Object} month The month as a plain {y, m}.
 * @return {Array} The rows, in order.
 */
const dayRows = (month) => {
    const first = format({y: month.y, m: month.m, d: 1});
    const lead = (utc(parse(first)).getUTCDay() - firstday + WEEK) % WEEK;
    const length = monthLength(month.y, month.m);
    const rows = [];
    let row = null;
    for (let slot = 0; slot < lead + length; slot++) {
        if (slot % WEEK === 0) {
            row = document.createElement('div');
            row.className = 'dim-daterange__row';
            row.setAttribute('role', 'row');
            rows.push(row);
        }
        row.append(slot < lead ? blank() : dayCell(format({y: month.y, m: month.m, d: slot - lead + 1})));
    }
    const last = rows[rows.length - 1];
    for (let fill = last ? last.childElementCount : WEEK; fill < WEEK; fill++) {
        last.append(blank());
    }
    return rows;
};

/**
 * How many months the popover shows: two where they fit side by side, one below that.
 *
 * @return {Number} One or two.
 */
const monthCount = () => (wide && wide.matches ? 2 : 1);

/**
 * The month some months after the first one on screen.
 *
 * @param {Number} offset Whole months after the cursor.
 * @return {Object} The month as a plain {y, m}.
 */
const monthAt = (offset) => {
    const parts = parse(shiftMonth(format({y: cursor.y, m: cursor.m, d: 1}), offset));
    return {y: parts.y, m: parts.m};
};

/**
 * Whether a day falls in one of the months on screen.
 *
 * @param {String} iso A date as YYYY-MM-DD.
 * @return {Boolean}
 */
const onScreen = (iso) => {
    const firstshown = format({y: cursor.y, m: cursor.m, d: 1});
    const lastmonth = monthAt(monthCount() - 1);
    const lastshown = format({y: lastmonth.y, m: lastmonth.m, d: monthLength(lastmonth.y, lastmonth.m)});
    return iso >= firstshown && iso <= lastshown;
};

/**
 * Move the months on screen as little as it takes for a day to be on one of them.
 *
 * @param {String} iso A date as YYYY-MM-DD.
 * @return {void}
 */
const bringIntoView = (iso) => {
    const parts = parse(iso);
    if (!parts || onScreen(iso)) {
        return;
    }
    const firstshown = format({y: cursor.y, m: cursor.m, d: 1});
    // A day before the months on screen opens them on its month, one after them ends them on it.
    const start = iso < firstshown ? iso : shiftMonth(format({y: parts.y, m: parts.m, d: 1}), 1 - monthCount());
    const moved = parse(start);
    cursor = {y: moved.y, m: moved.m};
};

/**
 * Draw the months on screen: each one's caption, its column heads and its day cells.
 *
 * The second month's block is hidden where only one fits, so a day the focus walks to is always
 * a day that is drawn. Each grid is labelled by its own caption.
 *
 * @return {void}
 */
const paintGrid = () => {
    const blocks = Array.from(root.querySelectorAll(SELECTORS.monthblock));
    blocks.forEach((block, index) => {
        const caption = block.querySelector(SELECTORS.month);
        const grid = block.querySelector(SELECTORS.grid);
        block.hidden = index >= monthCount();
        if (!caption || !grid) {
            return;
        }
        if (block.hidden) {
            // A month drawn at a wider width leaves no day behind that the focus could be sent to.
            grid.replaceChildren();
            return;
        }
        const month = monthAt(index);
        caption.textContent = say(format({y: month.y, m: month.m, d: 1}), {year: 'numeric', month: 'long'});
        grid.setAttribute('role', 'grid');
        grid.setAttribute('aria-labelledby', caption.id);
        grid.replaceChildren(headRow(), ...dayRows(month));
    });
};

/**
 * Put the focus on the day the roving tabindex is on.
 *
 * Every repaint of the grids replaces their cells, so the focused day leaves the document and the
 * focus falls to the body, and the focusout handler in listen() would then close the popover
 * after the first press of a range. Every caller that repaints calls this synchronously, before
 * that handler's timeout runs.
 *
 * @return {void}
 */
const focusDay = () => {
    const cell = pop.querySelector('[data-date="' + focused + '"]') || pop.querySelector(SELECTORS.day);
    if (cell) {
        cell.focus();
    }
};

/**
 * Repaint everything the popover shows and put the focus back on the day it stands on.
 *
 * @return {void}
 */
const repaint = () => {
    paintBoxes();
    paintPrompt();
    paintGrid();
    focusDay();
};

/**
 * Make one bound the one the next press sets, and stand the grid on its day.
 *
 * The day stood on is the bound's own, else the other bound's, else the site's today.
 *
 * @param {String} bound FROM or TO.
 * @return {void}
 */
const setMode = (bound) => {
    mode = bound === TO ? TO : FROM;
    field = boxes[mode];
    const own = mode === TO ? draftto : draftfrom;
    const other = mode === TO ? draftfrom : draftto;
    focused = own || other || today;
    bringIntoView(focused);
    repaint();
};

/**
 * Open the popover from one of the boxes, on the months the period is in.
 *
 * Both boxes say they are expanded, because both control this one popover; which of them the
 * next press serves is the active box's class and the prompt above the months.
 *
 * @param {String} bound The bound of the box pressed, FROM or TO.
 * @return {void}
 */
const open = (bound) => {
    draftfrom = from;
    draftto = to;
    const start = parse(draftfrom || draftto || today);
    cursor = {y: start.y, m: start.m};
    pop.hidden = false;
    [FROM, TO].forEach((name) => boxes[name].setAttribute('aria-expanded', 'true'));
    setMode(bound);
};

/**
 * Close the popover, and put the focus back on the mode's box when the reader is the one closing it.
 *
 * A draft dies with the popover; this is what makes Escape discard. Left alive, the shut control
 * would show boxes naming a period the listing is not narrowed by, so every path that closes
 * without applying first repaints the boxes with the period in force.
 *
 * @param {Boolean} restore Whether the focus belongs back on the box.
 * @return {void}
 */
const close = (restore) => {
    if (!isOpen()) {
        return;
    }
    pop.hidden = true;
    [FROM, TO].forEach((name) => boxes[name].setAttribute('aria-expanded', 'false'));
    draftfrom = from;
    draftto = to;
    paintBoxes();
    if (restore && field) {
        field.focus();
    }
};

/**
 * Make a period the one in force, and tell the listing when it changed.
 *
 * The one place the period in force changes from inside this control and the one place it leaves
 * it: "Done", a press outside, each box's clearing control, the footer's and the pill all come
 * through here. A period equal to the one in force asks for nothing, so clearing a bound that is
 * not set does not fetch the same page again.
 *
 * @param {String} start The first day, YYYY-MM-DD, or the empty string.
 * @param {String} end The last day, YYYY-MM-DD, or the empty string.
 * @return {void}
 */
const apply = (start, end) => {
    const changed = start !== from || end !== to;
    from = start;
    to = end;
    draftfrom = start;
    draftto = end;
    paintBoxes();
    if (isOpen()) {
        paintGrid();
    }
    if (!changed) {
        return;
    }
    paintPill().catch(Notification.exception);
    if (onpick) {
        onpick(from, to);
    }
};

/**
 * Take one press of a day, for the bound the popover is in the mode of.
 *
 * In the start's mode the day becomes the start, an end that would now come before it is
 * dropped, and the mode moves on to the end: two presses describe a range. In the end's mode the
 * day becomes the end - unless it falls before the start. A day before the start is read as the
 * start of the period the reader means rather than as an end the period cannot have, which is
 * the slip a reader makes after pressing a start and then an earlier day: that day becomes the
 * start, the end is dropped, and the end is still what is asked for. So no press ever leaves a
 * period running backwards, and a start or an end alone is a period like any other.
 *
 * Nothing is applied and the popover stays open: the reader has said where the period runs, not
 * that they are finished with the calendar. What ends the choice is {@see commit()}.
 *
 * @param {String} iso The day pressed.
 * @return {void}
 */
const press = (iso) => {
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
    field = boxes[mode];
    focused = iso;
    repaint();
};

/**
 * End the choice: the draft becomes the period in force, and the popover closes.
 *
 * What "Done" does, and what a press outside the control does: walking away from a period you
 * have described is the same intent said without the button. Escape and the keyboard walking
 * out take {@see close()} alone, which discards. A start or an end alone is a period the server
 * answers, so any draft is applied as it stands.
 *
 * @param {Boolean} restore Whether the focus belongs back on the box.
 * @return {void}
 */
const commit = (restore) => {
    const start = draftfrom;
    const end = draftto;
    close(restore);
    apply(start, end);
};

/**
 * Drop one bound, or the whole period, from what the boxes show, and apply the rest at once.
 *
 * Each box's clearing control drops its own bound and leaves the other one as the boxes show it;
 * the footer's control and the pill drop both. A clearing control is a one-press intent, like a
 * quick option, so it applies rather than drafting.
 *
 * @param {String} bound FROM, TO, or the empty string for the whole period.
 * @return {void}
 */
const drop = (bound) => {
    const start = bound === TO ? draftfrom : '';
    const end = bound === FROM ? draftto : '';
    if (bound === '') {
        mode = FROM;
        if (isOpen()) {
            paintPrompt();
        }
    }
    apply(start, end);
};

/**
 * The key handling of the grids: the arrows walk the calendar, the pages walk the months.
 *
 * Enter and Space are deliberately absent: every day is a real button and answers both already.
 * Escape closes the popover and hands the focus back to the box of the mode, and discards a
 * draft: a key that dismisses a control may not leave its choice behind. The arrows answer only
 * on a day, so a box keeps its own keys.
 *
 * @param {KeyboardEvent} event The key press.
 * @return {void}
 */
const onKey = (event) => {
    if (event.key === 'Escape') {
        event.preventDefault();
        close(true);
        return;
    }
    const steps = {
        ArrowLeft: () => shift(focused, -1),
        ArrowRight: () => shift(focused, 1),
        ArrowUp: () => shift(focused, -WEEK),
        ArrowDown: () => shift(focused, WEEK),
        PageUp: () => shiftMonth(focused, -1),
        PageDown: () => shiftMonth(focused, 1),
    };
    const step = steps[event.key];
    const onday = event.target instanceof Element && event.target.closest(SELECTORS.day);
    if (!step || !isOpen() || !onday) {
        return;
    }
    event.preventDefault();
    moveTo(step());
};

/**
 * Move the roving tabindex onto a day, bringing its month on screen when it is not.
 *
 * @param {String} iso The day to move to.
 * @return {void}
 */
const moveTo = (iso) => {
    if (!parse(iso)) {
        return;
    }
    focused = iso;
    if (onScreen(iso)) {
        pop.querySelectorAll(SELECTORS.day).forEach((cell) => {
            cell.tabIndex = cell.dataset.date === focused ? 0 : -1;
        });
    } else {
        bringIntoView(iso);
        paintGrid();
    }
    focusDay();
};

/**
 * Which way one press of the month navigation moves, and zero when it was not one.
 *
 * @param {Element} target What was pressed.
 * @return {Number} Minus one, one, or zero.
 */
const stepOf = (target) => {
    if (target.closest(SELECTORS.prev)) {
        return -1;
    }
    return target.closest(SELECTORS.next) ? 1 : 0;
};

/**
 * Move the months on screen by one, and stand the grid on the first day of the first of them.
 *
 * @param {Number} step Minus one or one.
 * @return {void}
 */
const onStep = (step) => {
    cursor = monthAt(step);
    focused = format({y: cursor.y, m: cursor.m, d: 1});
    paintGrid();
    focusDay();
};

/**
 * Take a press of a box: open the popover in its mode, or move an open popover to that mode.
 *
 * A box never closes the popover. After a start the end is the mode, and a reader pressing the end
 * box to say so must not lose the start they have just described; Done, a press outside and
 * Escape are the ways out.
 *
 * @param {HTMLElement} box The box pressed.
 * @return {void}
 */
const onBox = (box) => {
    const bound = box.dataset.bound === TO ? TO : FROM;
    if (isOpen()) {
        setMode(bound);
    } else {
        open(bound);
    }
};

/**
 * Take a press of a clearing control, and keep the focus where the reader can use it.
 *
 * A box's own control hides itself once its bound is gone, which would drop the focus to the
 * body and, with it, close the popover; the focus goes to its box instead.
 *
 * @param {HTMLElement} control The clearing control pressed.
 * @return {void}
 */
const onClear = (control) => {
    const bound = control.dataset.bound === FROM || control.dataset.bound === TO ? control.dataset.bound : '';
    drop(bound);
    if (bound !== '') {
        boxes[bound].focus();
    }
};

/**
 * Take one press inside the control: a box, a clearing control, "Done", a month step or a day.
 *
 * @param {Event} event The press.
 * @return {void}
 */
const onClick = (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
        return;
    }
    /* Matched by the action, not by an element: each box's control, the popover's footer and the
       count row's pill all carry it, and a handler bound to one of them would leave the others
       dead. Which bound goes is the control's data-bound, and none means the whole period. */
    const clear = target.closest(SELECTORS.clear);
    if (clear) {
        event.preventDefault();
        onClear(clear);
        return;
    }
    if (target.closest(SELECTORS.done)) {
        event.preventDefault();
        commit(true);
        return;
    }
    const box = target.closest(SELECTORS.box);
    if (box) {
        event.preventDefault();
        onBox(box);
        return;
    }
    const step = stepOf(target);
    if (step !== 0) {
        event.preventDefault();
        onStep(step);
        return;
    }
    const day = target.closest(SELECTORS.day);
    if (day) {
        event.preventDefault();
        press(day.dataset.date);
    }
};

/**
 * Wire the control: its own presses, its keys, the two ways a popover stops being open, and the width.
 *
 * @return {void}
 */
const listen = () => {
    root.addEventListener('click', onClick);
    root.addEventListener('keydown', onKey);
    if (pillregion) {
        pillregion.addEventListener('click', onClick);
    }

    /* Anything pressed outside the open control closes it, and nothing is trapped: this is a
       disclosure and not a dialogue. The event is pointerdown rather than click, so that a press
       meant for a control elsewhere does not have to travel through a popover still on top of it.
       A press outside applies the draft, as "Done" would: the pointer's way out says "that will
       do", while Escape and the keyboard's walk-away say "never mind". Only while it is open: a
       press on the pill of the count row is outside the control too, and repainting that pill
       between its pointerdown and its click would take the click away from it. */
    document.addEventListener('pointerdown', (event) => {
        if (isOpen() && event.target instanceof Node && !root.contains(event.target)) {
            commit(false);
        }
    });

    /* And the keyboard's own way out of it. The timeout is what lets the focus land first:
       during focusout the next element has not been focused yet, so activeElement is the body
       and every move would read as a move out of the control, this one included. */
    root.addEventListener('focusout', () => {
        window.setTimeout(() => {
            if (!root.contains(document.activeElement)) {
                close(false);
            }
        }, 0);
    });

    // A width that gains or loses the second month redraws an open popover with the focus kept.
    wide.addEventListener('change', () => {
        if (isOpen()) {
            bringIntoView(focused);
            paintGrid();
            focusDay();
        }
    });
};

/**
 * Put a period onto the control without telling anybody: what the address says, painted.
 *
 * This is the path a quick option, the back button and a cleared filter all take. It never calls
 * back, because the caller that set it is the one that knows what to do next - a callback here
 * would fetch the page the caller has just fetched.
 *
 * @param {String} start The first day, YYYY-MM-DD, or the empty string.
 * @param {String} end The last day, YYYY-MM-DD, or the empty string.
 * @return {void}
 */
export const set = (start, end) => {
    if (!root) {
        return;
    }
    const given = period(start, end);
    from = given.from;
    to = given.to;
    draftfrom = from;
    draftto = to;
    paintBoxes();
    paintPill().catch(Notification.exception);
    if (isOpen()) {
        paintGrid();
    }
};

/**
 * Mount the control into the empty region the toolbar left for it.
 *
 * @param {HTMLElement} region The region to mount into.
 * @param {Object} options What the server resolved and the client may not compute: id, firstday,
 *   today, from, to, the pill region of the count row, and the onpick callback taking the two
 *   bounds of the period whenever it changes, either of them the empty string when it is not set.
 * @return {Promise} Resolving to true when the control is mounted, false when it is not.
 */
export const mount = async(region, options) => {
    if (!region || !parse(options.today)) {
        /* Without the day the site calls today there is no month to open on and no cell to mark,
           and the browser's own clock is the one answer this module may not use. The two native
           fields are then left exactly as they are, which is the page a reader without any of
           this gets. */
        return false;
    }
    const day = Number(options.firstday);
    firstday = Number.isInteger(day) && day >= 0 && day < WEEK ? day : 0;
    today = options.today;
    const given = period(options.from, options.to);
    from = given.from;
    to = given.to;
    draftfrom = from;
    draftto = to;
    mode = FROM;
    focused = '';
    pillregion = options.pill || null;
    onpick = typeof options.onpick === 'function' ? options.onpick : null;
    const [clear, choosefrom, chooseto] = await getStrings([
        {key: 'category_dateclear', component: COMPONENT},
        {key: 'category_datechoosefrom', component: COMPONENT},
        {key: 'category_datechooseto', component: COMPONENT},
    ]);
    strings = {clear: clear, choosefrom: choosefrom, chooseto: chooseto};
    const {html, js} = await Templates.renderForPromise(TEMPLATE, {
        fromid: 'dim-daterange-' + options.id + '-' + FROM,
        toid: 'dim-daterange-' + options.id + '-' + TO,
        popid: 'dim-daterange-pop-' + options.id,
        fromvalue: '',
        tovalue: '',
        hasfrom: false,
        hasto: false,
        prompt: '',
        month: '',
        nextmonth: '',
    });
    await Templates.replaceNodeContents(region, html, js);
    root = region.firstElementChild;
    pop = region.querySelector(SELECTORS.pop);
    boxes = {
        [FROM]: region.querySelector(SELECTORS.box + '[data-bound="' + FROM + '"]'),
        [TO]: region.querySelector(SELECTORS.box + '[data-bound="' + TO + '"]'),
    };
    if (!root || !pop || !boxes[FROM] || !boxes[TO] || !region.querySelector(SELECTORS.grid)) {
        root = null;
        return false;
    }
    field = boxes[FROM];
    wide = window.matchMedia(WIDE);
    const start = parse(from || to || today);
    cursor = {y: start.y, m: start.m};
    paintBoxes();
    await paintPill();
    listen();
    return true;
};
