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
 * An event's description, read lazily from core and once per page life.
 *
 * The range answers no description (PROPOSAL.md section 5.5): the popover asks core's
 * core_calendar_get_calendar_event_by_id when it opens on one of core's events, which runs core's own visibility
 * check (the event vault, then calendar_view_event_allowed()) and answers the event exporter's description, already
 * through format_text() (core's exporter formats a property that has a format beside it), or null. A refusal, a
 * failure and an empty description all read as '' here: the popover draws nothing for them.
 *
 * One request per event per page life: the answer, a refusal included, is kept by event id until the page goes.
 * Which popover's answer is shown is the popover's decision (a ticket, EventPopover.tsx); this module only reads.
 *
 * @module     theme_dimensions/calendar/description
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import {EVENT_METHOD} from './model';

/** What core's service answers, in the part read here. */
type EventAnswer = {event: {description?: string | null}};

/** The descriptions asked for in this page's life, by event id. */
const cache = new Map<number, Promise<string>>();

/**
 * The description of one of core's events, as core's formatted HTML, or '' when there is none to draw.
 *
 * @param {number} id The event's id ({event}.id: an item whose source is core).
 * @returns {Promise} Resolves with the HTML; never rejects.
 */
export const description = (id: number): Promise<string> => {
    let known = cache.get(id);
    if (known === undefined) {
        known = fetchOne<EventAnswer>({methodname: EVENT_METHOD, args: {eventid: id}})
            .then((answer) => String(answer?.event?.description || ''))
            .catch(() => '');
        cache.set(id, known);
    }
    return known;
};

/**
 * Forget every description: for the tests, which share the module's page life.
 *
 * @returns {void}
 */
export const forget = (): void => cache.clear();
