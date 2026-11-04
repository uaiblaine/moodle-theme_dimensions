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
 * The event popover's Edit and Delete, for an event core lets the viewer edit.
 *
 * Edit opens core's event form for the event; Delete is core's own: its wrapper carries the markers core's crud reads
 * (data-type="event", data-event-id, data-event-title, data-event-count, as core's summary modal carries them) and
 * core's registerRemove() listens on it, so the confirmation, the series question and the delete are core's. The
 * parent keys this component by the item: jQuery caches what it read of the markers per element, and a wrapper reused
 * for another event would hand core the first event's id.
 *
 * @module     theme_dimensions/calendar/EditActions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef} from 'react';
import Pending from '@moodle/lms/core/pending';
import {armDelete} from './editing';
import type {Edit, Item} from './model';
import type {Strings} from './strings';

/** What the actions are handed. */
type EditActionsProps = {
    item: Item,
    edit: Edit,
    strings: Strings,
    onEdit: () => void,
    onFailure: (failure: unknown) => void,
};

/**
 * Escape a name the way Mustache's double stash does: core's crud reads the attribute back and shows it as HTML, and
 * React's own escaping is undone when the DOM decodes the attribute.
 *
 * @param {string} name The plain name.
 * @returns {string}
 */
const escapeHtml = (name: string): string => name.replace(/[&<>"']/g, (char) => (
    {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#039;'}[char] as string
));

/**
 * Edit and Delete, as core's answer allows them; nothing when it allows neither.
 *
 * @param {object} props See EditActionsProps.
 * @returns {object|null}
 */
const EditActions = ({item, edit, strings, onEdit, onFailure}: EditActionsProps) => {
    const root = useRef<HTMLDivElement | null>(null);

    // Core listens on the wrapper once it is drawn; a press before that is held by the Pending token's wait.
    useEffect(() => {
        if (!edit.delete || !root.current) {
            return;
        }
        const pending = new Pending('theme_dimensions/calendar:delete');
        armDelete(root.current).catch(onFailure).finally(() => pending.resolve());
    }, [edit.delete]);

    if (!edit.edit && !edit.delete) {
        return null;
    }
    return (
        <div
            ref={root}
            className="dim-cal__popedit"
            data-region="dim-cal-edit"
            data-type="event"
            data-event-id={item.id}
            data-event-title={escapeHtml(item.name)}
            data-event-count={edit.count}
        >
            {edit.edit ? (
                <button type="button" className="btn btn-secondary" data-action="dim-cal-edit" onClick={onEdit}>
                    {strings.calendar_edit}
                </button>
            ) : null}
            {edit.delete ? (
                <button type="button" className="btn btn-outline-danger" data-action="delete">{strings.calendar_delete}</button>
            ) : null}
        </div>
    );
};

export default EditActions;
