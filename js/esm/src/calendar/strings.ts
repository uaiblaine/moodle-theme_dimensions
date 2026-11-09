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
 * The calendar page's strings: read once, then filled in place.
 *
 * The page hands every one of them to core's string cache with itself (calendar_page::STRINGS, through
 * strings_for_js), so {@see load()} resolves without a request. A label with a count or a date is filled here from
 * the string read once ({@see fill()}, core's own placeholders, {$a} and {$a->name}), rather than asked of
 * stringUtils again for each of the forty-two days of a grid. calendar_component_source_test holds the list against
 * the server's.
 *
 * @module     theme_dimensions/calendar/strings
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from '@moodle/lms/core/stringUtils';

/** The component. */
const COMPONENT = 'theme_dimensions';

/** Every string the page reads, in calendar_page::STRINGS' order. */
export const KEYS = [
    'calendar_actiononly',
    'calendar_allday',
    'calendar_barlabel',
    'calendar_category',
    'calendar_categoryall',
    'calendar_categorycourses',
    'calendar_categorynone',
    'calendar_categorysearch',
    'calendar_categorysearchplaceholder',
    'calendar_categorytree',
    'calendar_city',
    'calendar_cityany',
    'calendar_clearfilters',
    'calendar_close',
    'calendar_colourby',
    'calendar_colourbycategory',
    'calendar_colourbykey',
    'calendar_course',
    'calendar_coursepage',
    'calendar_dayempty',
    'calendar_dayitems_many',
    'calendar_dayitems_none',
    'calendar_dayitems_one',
    'calendar_daylabel',
    'calendar_daytoday',
    'calendar_delete',
    'calendar_descriptionloading',
    'calendar_edit',
    'calendar_eventpage',
    'calendar_eventtypes',
    'calendar_eventtypes_all',
    'calendar_eventtypes_some',
    'calendar_filtercount',
    'calendar_hoursbox',
    'calendar_join_free',
    'calendar_join_key',
    'calendar_join_open',
    'calendar_key_activity',
    'calendar_key_category',
    'calendar_key_course',
    'calendar_key_group',
    'calendar_key_open',
    'calendar_key_other',
    'calendar_key_site',
    'calendar_key_user',
    'calendar_keys',
    'calendar_modality',
    'calendar_model',
    'calendar_modelpage',
    'calendar_moreaction',
    'calendar_morefilters',
    'calendar_morelabel',
    'calendar_newevent',
    'calendar_neweventon',
    'calendar_nextday',
    'calendar_nextmonth',
    'calendar_nextweek',
    'calendar_open',
    'calendar_overdue',
    'calendar_overlapmark',
    'calendar_overlaps',
    'calendar_participants_many',
    'calendar_participants_one',
    'calendar_place',
    'calendar_prevday',
    'calendar_prevmonth',
    'calendar_prevweek',
    'calendar_recursive',
    'calendar_selectall',
    'calendar_span',
    'calendar_timed',
    'calendar_title',
    'calendar_today',
    'calendar_trail',
    'calendar_trailall',
    'calendar_trailcollapseall',
    'calendar_trailcountmany',
    'calendar_trailcountone',
    'calendar_trailcourse',
    'calendar_trailcoursedates',
    'calendar_trailearlier',
    'calendar_trailenrolled',
    'calendar_trailexpandall',
    'calendar_trailexpected',
    'calendar_trailfrom',
    'calendar_trailheading',
    'calendar_traillater',
    'calendar_trailmine',
    'calendar_trailmonths',
    'calendar_trailnodates',
    'calendar_trailnotenrolled',
    'calendar_trailopenchip',
    'calendar_trailopenfull',
    'calendar_trailpage',
    'calendar_trailposition',
    'calendar_trailprogress',
    'calendar_trailsempty',
    'calendar_trailshow',
    'calendar_trailstruncated',
    'calendar_trailtoggle',
    'calendar_trailzoom',
    'calendar_trailzoomday',
    'calendar_trailzoommonth',
    'calendar_trailzoomweek',
    'calendar_truncated',
    'calendar_truncatedpublic',
    'calendar_until',
    'calendar_view',
    'calendar_viewday',
    'calendar_viewmonth',
    'calendar_viewtrail',
    'calendar_viewweek',
    'calendar_weekempty',
    'calendar_when',
    'calendar_withsub',
    'category_cta_review',
    'landing_trail_coursecount',
    'landing_trail_onecourse',
    'landing_trail_startson',
    'landing_trail_state_current',
    'landing_trail_state_done',
    'landing_trail_state_late',
    'landing_trail_state_locked',
    'landing_trail_state_upcoming',
    'landing_trail_whenjoin',
] as const;

/** One string's name. */
export type StringKey = typeof KEYS[number];

/** The strings, by name. */
export type Strings = Record<StringKey, string>;

/**
 * Read every string once.
 *
 * @returns {Promise} The strings, by name.
 */
export const load = (): Promise<Strings> => getStrings(KEYS.map((key) => ({key: key, component: COMPONENT})))
    .then((values) => Object.fromEntries(KEYS.map((key, index) => [key, values[index]])) as Strings);

/**
 * A string with its placeholders filled, as core fills them: {$a} with a value, {$a->name} with a field.
 *
 * @param {string} template The string as read.
 * @param {object|string|number} values A value for {$a}, or the fields for {$a->name}.
 * @returns {string} The filled string.
 */
export const fill = (template: string, values: Record<string, string | number> | string | number): string => {
    if (typeof values !== 'object') {
        return template.split('{$a}').join(String(values));
    }
    return template.replace(/\{\$a->([a-z0-9_]+)\}/gi, (whole, name: string) => (name in values ? String(values[name]) : whole));
};
