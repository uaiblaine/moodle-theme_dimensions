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
 * The calendar tests' data: items of the range's shape, an answer, the page's props.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Answer, Item, Key, Props} from '../../src/calendar/model';
import {KEYS} from '../../src/calendar/model';

/**
 * Midnight UTC of a day, in seconds.
 *
 * @param {string} iso The day.
 * @param {number} hour The hour, UTC.
 * @returns {number}
 */
export const at = (iso: string, hour = 0): number => Date.UTC(Number(iso.slice(0, 4)), Number(iso.slice(5, 7)) - 1,
    Number(iso.slice(8, 10)), hour) / 1000;

/**
 * An item of the range's shape.
 *
 * @param {object} fields The fields that differ from a one-hour site event.
 * @returns {object} The item.
 */
export const item = (fields: Partial<Item> & {id: number}): Item => ({
    source: 'core',
    key: 'site' as Key,
    name: `Item ${fields.id}`,
    timestart: at('2026-11-10', 9),
    timeduration: 3600,
    courseid: 0,
    categoryid: 0,
    groupid: 0,
    modulename: '',
    instance: 0,
    component: '',
    eventtype: 'site',
    location: '',
    url: `https://example.com/event/${fields.id}`,
    actionevent: false,
    action: null,
    overdue: false,
    held: false,
    ...fields,
});

/**
 * An answer holding these items.
 *
 * @param {Array} events The items.
 * @param {string} from The first day.
 * @param {string} to The last day.
 * @returns {object} The answer.
 */
export const answer = (events: Item[], from = '2026-11-01', to = '2026-12-05'): Answer => ({
    from: from,
    to: to,
    truncated: false,
    events: events,
    courses: [],
    categories: [],
    days: [],
});

/** The English strings of lang/en, for the keys the page reads. */
export const STRINGS: Record<string, string> = {
    'calendar_actiononly': 'Only what I have to do',
    'calendar_allday': 'All day',
    'calendar_barlabel': '{$a->name}, {$a->span}',
    'calendar_category': 'Category',
    'calendar_categoryall': 'All my categories',
    'calendar_categorycourses': '{$a} courses',
    'calendar_categorynone': 'No category matches.',
    'calendar_categorysearch': 'Search categories',
    'calendar_categorysearchplaceholder': 'Category name',
    'calendar_categorytree': 'Categories',
    'calendar_city': 'City',
    'calendar_cityany': 'All',
    'calendar_clearfilters': 'Clear filters',
    'calendar_close': 'Close',
    'calendar_colourby': 'Colour by',
    'calendar_colourbycategory': 'Category',
    'calendar_colourbykey': 'Event key',
    'calendar_course': 'Course',
    'calendar_coursepage': 'Course page',
    'calendar_dayempty': 'Nothing on this day.',
    'calendar_dayitems_many': '{$a} items',
    'calendar_dayitems_none': 'no items',
    'calendar_dayitems_one': '1 item',
    'calendar_daylabel': '{$a->date}, {$a->count}',
    'calendar_daytoday': 'today',
    'calendar_delete': 'Delete',
    'calendar_descriptionloading': 'Loading the description…',
    'calendar_edit': 'Edit',
    'calendar_eventpage': 'Event page',
    'calendar_eventtypes': 'Event types',
    'calendar_eventtypes_all': 'All',
    'calendar_eventtypes_some': '{$a->on} of {$a->total}',
    'calendar_filtercount': '{$a} filters on',
    'calendar_hoursbox': 'Hours of the day',
    'calendar_join_free': 'Enter as a guest',
    'calendar_join_key': 'Enrol with a key',
    'calendar_join_open': 'Enrol',
    'calendar_key_activity': 'Activities',
    'calendar_key_category': 'Category',
    'calendar_key_course': 'Course',
    'calendar_key_group': 'Group',
    'calendar_key_open': 'Open events',
    'calendar_key_other': 'Other',
    'calendar_key_site': 'Site',
    'calendar_key_user': 'Mine',
    'calendar_keys': 'Event keys',
    'calendar_modality': 'Modality',
    'calendar_model': 'Model',
    'calendar_modelpage': '{$a} page',
    'calendar_moreaction': '{$a->count} more on {$a->date}',
    'calendar_morefilters': 'More filters',
    'calendar_morelabel': '+{$a} more',
    'calendar_newevent': 'New event',
    'calendar_neweventon': 'New event on {$a}',
    'calendar_nextday': 'Next day',
    'calendar_nextmonth': 'Next month',
    'calendar_nextweek': 'Next week',
    'calendar_open': 'Open',
    'calendar_overdue': 'Overdue',
    'calendar_overlapmark': 'Overlaps',
    'calendar_overlaps': 'Overlaps with {$a}',
    'calendar_participants_many': '{$a} participants',
    'calendar_participants_one': '1 participant',
    'calendar_place': 'Where',
    'calendar_prevday': 'Previous day',
    'calendar_prevmonth': 'Previous month',
    'calendar_prevweek': 'Previous week',
    'calendar_recursive': 'Include subcategories',
    'calendar_selectall': 'Select all',
    'calendar_span': '{$a->from} to {$a->to}',
    'calendar_timed': 'At set times',
    'calendar_title': 'Calendar',
    'calendar_today': 'Today',
    'calendar_trail': 'Trail',
    'calendar_trailall': 'All trails',
    'calendar_trailcountmany': '{$a} trails',
    'calendar_trailcountone': '1 trail',
    'calendar_trailcourse': 'Open course',
    'calendar_trailcoursedates': 'The course\'s dates',
    'calendar_trailearlier': 'Earlier days',
    'calendar_trailenrolled': 'Enrolled',
    'calendar_trailexpected': 'Expected completion date',
    'calendar_trailfrom': 'From {$a}',
    'calendar_trailheading': 'Trails and courses',
    'calendar_traillater': 'Later days',
    'calendar_trailmine': 'Only mine',
    'calendar_trailnext': 'Continues in {$a}',
    'calendar_trailnotenrolled': 'Not enrolled',
    'calendar_trailpage': 'View trail',
    'calendar_trailposition': 'Course {$a->position} of {$a->count} · {$a->trail}',
    'calendar_trailprogress': '{$a->done} of {$a->count} done',
    'calendar_trailsempty': 'No trail runs this month.',
    'calendar_trailstruncated': 'There are more trails than the calendar shows.',
    'calendar_trailtoggle': 'Courses of {$a}',
    'calendar_truncated': 'There are more open events than the calendar shows.',
    'calendar_truncatedpublic': 'There are more events than the calendar shows.',
    'calendar_until': 'Until {$a}',
    'calendar_view': 'View',
    'calendar_viewday': 'Day',
    'calendar_viewmonth': 'Month',
    'calendar_viewtrail': 'Trails',
    'calendar_viewweek': 'Week',
    'calendar_weekempty': 'Nothing this week.',
    'calendar_when': 'When',
    'calendar_withsub': 'with subcategories',
    'landing_trail_coursecount': '{$a} courses',
    'landing_trail_onecourse': '1 course',
    'landing_trail_startson': 'Starts on {$a}',
    'landing_trail_state_current': 'In progress',
    'landing_trail_state_done': 'Completed',
    'landing_trail_state_late': 'Overdue',
    'landing_trail_state_locked': 'Locked',
    'landing_trail_state_upcoming': 'Up next',
    'landing_trail_whenjoin': '{$a->a} · {$a->b}',
};

/**
 * The page's props for November 2026, Sunday first, in UTC.
 *
 * @param {Array} events The answer's items.
 * @param {object} more Props that differ.
 * @returns {object} The props.
 */
export const props = (events: Item[] = [], more: Partial<Props> = {}): Props => ({
    month: '2026-11',
    view: 'month',
    week: '',
    day: '',
    today: '2026-11-17',
    now: at('2026-11-17', 10),
    firstday: 0,
    timezone: 'UTC',
    answer: answer(events, '2026-11-01', '2026-12-05'),
    state: {off: [], categoryid: 0, recursive: true, model: [], modality: [], city: '', actiononly: false, colour: 'key'},
    tree: [
        {id: 3, name: 'School of Government', parent: 0, depth: 0, bg: '#0a4a85', ink: '#ffffff', courses: 2},
        {id: 4, name: 'Public Management', parent: 3, depth: 1, bg: '#2f6b00', ink: '#ffffff', courses: 1},
        {id: 5, name: 'Teacher Training', parent: 0, depth: 0, bg: '#60451f', ink: '#ffffff', courses: 0},
    ],
    options: {model: [{value: 1, label: 'Course'}, {value: 2, label: 'Event'}], modality: [{value: 1, label: 'Online'}], city: []},
    urls: {
        page: 'http://localhost/r.php/theme_dimensions/calendar',
        home: 'http://localhost/',
    },
    ...more,
});

/** Every key, for counts. */
export const ALL_KEYS = KEYS;
