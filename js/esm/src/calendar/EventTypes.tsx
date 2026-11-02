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
 * The event types' filter: one toolbar button and its dropdown of checkboxes, one per key it is handed, each with its
 * key's swatch and the count of the range on screen (docs/calendar/PROPOSAL.md section 27.2).
 *
 * The button names the filter and says how much of it is on: "All" while every key is, else a badge "N of M". The
 * dropdown is popover.ts's disclosure (a press outside closes it; Escape closes it from anywhere while it is open,
 * through a document listener, because the list re-renders under a press of "Select all" and the focus can fall
 * outside the dropdown, where no handler on it would see the key; the focus goes back to the button) holding real
 * checkboxes with their labels and a "Select all" button. A key unchecked is a key the range is not asked for, so it
 * carries no count: the server never counted it. The state is the same `off` as the chips' it
 * replaces, so nothing changes on the server.
 *
 * @module     theme_dimensions/calendar/EventTypes
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect} from 'react';
import type {Key} from './model';
import {usePopover} from './popover';
import {fill} from './strings';
import type {Strings} from './strings';

/** What the filter is handed. */
type EventTypesProps = {
    strings: Strings,
    keys: readonly Key[],
    off: Key[],
    counts: Record<string, number>,
    onChange: (off: Key[]) => void,
};

/**
 * The name of a key: a literal string per key, never a composed id.
 *
 * @param {object} strings The page's strings.
 * @param {string} key The key.
 * @returns {string} The name.
 */
export const keyLabel = (strings: Strings, key: Key): string => {
    switch (key) {
        case 'site':
            return strings.calendar_key_site;
        case 'category':
            return strings.calendar_key_category;
        case 'course':
            return strings.calendar_key_course;
        case 'group':
            return strings.calendar_key_group;
        case 'user':
            return strings.calendar_key_user;
        case 'activity':
            return strings.calendar_key_activity;
        case 'open':
            return strings.calendar_key_open;
        default:
            return strings.calendar_key_other;
    }
};

/**
 * The keys off once one is checked or unchecked, in the order they are handed.
 *
 * @param {Array} keys The keys the filter offers.
 * @param {Array} off The keys off.
 * @param {string} key The key pressed.
 * @param {boolean} on Whether it is now checked.
 * @returns {Array} The keys off.
 */
export const toggledOff = (keys: readonly Key[], off: Key[], key: Key, on: boolean): Key[] =>
    keys.filter((other) => (other === key ? !on : off.includes(other)));

/**
 * "Clear filters": a toolbar button drawn only while a filter is applied (the page decides which), beside the filters.
 *
 * @param {object} props shown, the button's label, and what it does.
 * @returns {object|null} The button, or nothing.
 */
export const ClearFilters = ({shown, label, onClear}: {shown: boolean, label: string, onClear: () => void}) => (shown ? (
    <button type="button" className="btn btn-link dim-cal__clear" data-action="dim-cal-clear" onClick={onClear}>
        {label}
    </button>
) : null);

/**
 * The filter.
 *
 * @param {object} props See EventTypesProps.
 * @returns {object} The button and its dropdown.
 */
const EventTypes = ({strings, keys, off, counts, onChange}: EventTypesProps) => {
    const popover = usePopover();
    const panelid = 'dim-cal-typespanel';
    const on = keys.filter((key) => !off.includes(key)).length;
    const all = on === keys.length;
    const {open, shut} = popover;
    useEffect(() => {
        if (!open) {
            return undefined;
        }
        /**
         * Escape closes the dropdown wherever the focus is.
         *
         * @param {Event} event The key press.
         * @returns {void}
         */
        const escape = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                shut(true);
            }
        };
        document.addEventListener('keydown', escape);
        return () => document.removeEventListener('keydown', escape);
    }, [open, shut]);
    const some = fill(strings.calendar_eventtypes_some, {on: on, total: keys.length});
    return (
        <div className="dim-cal__picker" data-region="dim-cal-types">
            <button
                ref={popover.button}
                type="button"
                className="btn btn-secondary dim-cal__pickerbtn"
                aria-expanded={popover.open}
                aria-controls={panelid}
                data-action="dim-cal-types"
                onClick={popover.toggle}
            >
                <i className="fa fa-tags" aria-hidden="true"></i>
                {strings.calendar_eventtypes}
                {all ? <span className="dim-cal__typesall">{strings.calendar_eventtypes_all}</span> : (
                    <span className="dim-cal__badge" data-region="dim-cal-types-count">{some}</span>
                )}
            </button>
            <div
                ref={popover.panel}
                className="dim-cal__popover dim-cal__typespanel"
                id={panelid}
                role="group"
                aria-label={strings.calendar_eventtypes}
                hidden={!popover.open}
                onKeyDown={popover.onKeyDown}
            >
                <button
                    type="button"
                    className="btn btn-link dim-cal__selectall"
                    data-action="dim-cal-types-all"
                    disabled={all}
                    onClick={() => onChange([])}
                >{strings.calendar_selectall}</button>
                {keys.map((key) => {
                    const checked = !off.includes(key);
                    return (
                        <label key={key} className={`dim-cal__check dim-cal__typecheck dim-cal-key--${key}`}>
                            <input
                                type="checkbox"
                                name="dim-cal-type"
                                value={key}
                                checked={checked}
                                data-key={key}
                                onChange={(event) => onChange(toggledOff(keys, off, key, event.target.checked))}
                            />
                            <span className="dim-cal__swatch" aria-hidden="true"></span>
                            <span className="dim-cal__chiplabel">{keyLabel(strings, key)}</span>
                            {checked ? <span className="dim-cal__chipcount">{counts[key] || 0}</span> : null}
                        </label>
                    );
                })}
            </div>
        </div>
    );
};

export default EventTypes;
