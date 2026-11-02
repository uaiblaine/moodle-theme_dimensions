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
 * The logged-in calendar's door to core's own calendar editing: core's event form, its delete confirmation, its move
 * service and its events, reached through the bridge.
 *
 * Nothing is written here: core's form (core_calendar/modal_event_form, core_calendar_submit_create_update_form),
 * core's confirmation and delete (core_calendar/crud's registerRemove(), core_calendar_delete_calendar_events) and
 * core's move (core_calendar/repository's updateEventStartDay()) do the work, each checking the viewer again. The page
 * is told when core says something changed, through core's own events (core_calendar/events), which core triggers
 * with jQuery on the body: a native listener never hears them, so they are listened to with jQuery.
 *
 * The visitor's calendar never loads this module (tests/local/category/categorypublic_source_test.php): the AMD
 * modules it adds to the bridge's type are named here, and the bridge names none of them.
 *
 * @module     theme_dimensions/calendar/editing
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from '@moodle/lms/core/stringUtils';
import {load, loadMany} from '../amd';

/** What jQuery is used for here: a wrapped element and its delegated events. */
type Wrapped = {on: (events: string, handler: () => void) => unknown};

/** The part of core's event form modal the page uses, as core's crud uses it. */
type FormModal = {
    setContextId: (id: number) => void,
    setCourseId: (id: number) => void,
    setEventId: (id: number) => void,
    setStartTime: (time: number) => void,
    show: () => void,
};

/** Core_calendar/modal_event_form: its class, whose create() builds the modal. */
type FormModule = {create: () => Promise<FormModal>};

/** The part of core_calendar/crud the page uses. */
type CrudModule = {registerRemove: (root: Wrapped) => void};

/** The part of core_calendar/repository the page uses. */
type RepositoryModule = {updateEventStartDay: (eventid: number, daytimestamp: number) => Promise<unknown>};

/** The events core's calendar triggers on the body (core_calendar/events). */
type EventsModule = {created: string, updated: string, deleted: string};

declare module '../amd' {
    // eslint-disable-next-line no-unused-vars
    interface Modules {
        'jquery': (target: Element | string) => Wrapped,
        'core_calendar/modal_event_form': FormModule,
        'core_calendar/crud': CrudModule,
        'core_calendar/repository': RepositoryModule,
        'core_calendar/events': EventsModule,
    }
}

/** What core said changed. */
export type Change = 'created' | 'updated' | 'deleted';

/** What core's form is handed: core's context and course, and a new event's start or the event to edit. */
export type FormRequest = {context: number, course: number, event: number | null, start: number | null};

/**
 * The answer to a change before a page names its own: nothing.
 *
 * @returns {void}
 */
const nothing = (): void => undefined;

/** The page's answer to a change core announced. */
let changed: (change: Change) => void = nothing;

/** The listening to core's events, once per page. */
let listening: Promise<void> | null = null;

/** The one form modal of the page, as core's crud keeps one. */
let form: Promise<FormModal> | null = null;

/**
 * Name what the page does when core says an event was created, updated or deleted.
 *
 * @param {Function} callback The page's refresh.
 * @returns {void}
 */
export const onChange = (callback: (change: Change) => void): void => {
    changed = callback;
};

/**
 * Listen to core's events on the body, once per page.
 *
 * @returns {Promise} Settles once listening; a failure is not kept, so the next use tries again.
 */
const listen = (): Promise<void> => {
    if (!listening) {
        listening = loadMany(['jquery', 'core_calendar/events'])
            .then(([jquery, events]) => {
                const body = jquery('body');
                body.on(events.created, () => changed('created'));
                body.on(events.updated, () => changed('updated'));
                body.on(events.deleted, () => changed('deleted'));
                return undefined;
            })
            .catch((failure) => {
                listening = null;
                throw failure;
            });
    }
    return listening;
};

/**
 * Open core's event form: a new event with core's context, course and start, or an event to edit by its id, as core's
 * crud hands them (registerEventFormModal() for a new one, registerEditListeners() for an edit).
 *
 * Core's modal remembers the element that had the focus and gives it back on closing.
 *
 * @param {object} request What the form is handed.
 * @returns {Promise} Settles once the form is shown.
 */
export const openForm = async(request: FormRequest): Promise<void> => {
    await listen();
    if (!form) {
        form = load('core_calendar/modal_event_form').then((modal) => modal.create());
        form.catch(() => {
            form = null;
        });
    }
    const modal = await form;
    modal.setCourseId(request.course);
    if (request.event !== null) {
        modal.setEventId(request.event);
    } else {
        if (request.start !== null) {
            modal.setStartTime(request.start);
        }
    }
    modal.setContextId(request.context);
    modal.show();
};

/**
 * Hand a popover's actions to core's delete: core's crud reads the event from the markers they carry
 * (data-type="event", data-event-id, -title, -count), asks its own confirmation and deletes through its service.
 *
 * @param {HTMLElement} element The actions' element, which carries core's markers.
 * @returns {Promise} Settles once core listens on it.
 */
export const armDelete = async(element: HTMLElement): Promise<void> => {
    await listen();
    const [jquery, crud] = await loadMany(['jquery', 'core_calendar/crud']);
    crud.registerRemove(jquery(element));
};

/**
 * Move an event's start to another day through core's service, which keeps the time of day and checks again.
 *
 * @param {number} eventid The event.
 * @param {number} daytimestamp An instant inside the day, in the viewer's timezone.
 * @returns {Promise} Core's answer; rejects with core's exception.
 */
export const moveStart = (eventid: number, daytimestamp: number): Promise<unknown> =>
    load('core_calendar/repository').then((repository) => repository.updateEventStartDay(eventid, daytimestamp));

/**
 * Say why a drop was refused, as core's month view says it: core's "invalid date" over the limit's own message.
 *
 * @param {string} message The limit's message, core's.
 * @returns {Promise} Settles once it is shown.
 */
export const refuse = async(message: string): Promise<void> => {
    const [title, notification] = await Promise.all([getString('errorinvaliddate', 'calendar'), load('core/notification')]);
    await notification.exception({name: title, message: message || title});
};

/**
 * Forget the page's listening and form, for a test that mounts a fresh page.
 *
 * @returns {void}
 */
export const forget = (): void => {
    changed = nothing;
    listening = null;
    form = null;
};
