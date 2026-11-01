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
 * The key chips: one toggle button per key, in its key's colours, with the count of the range on screen.
 *
 * A chip is a button with aria-pressed. A chip off is a key the range is not asked for, so it carries no count:
 * the server never counted it.
 *
 * @module     theme_dimensions/calendar/KeyChips
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {KEYS} from './model';
import type {Key} from './model';
import type {Strings} from './strings';

/** What the chips are handed. */
type KeyChipsProps = {
    strings: Strings,
    off: Key[],
    counts: Record<string, number>,
    onToggle: (key: Key) => void,
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
 * The chips.
 *
 * @param {object} props See KeyChipsProps.
 * @returns {object} The group of chips.
 */
const KeyChips = ({strings, off, counts, onToggle}: KeyChipsProps) => (
    <div className="dim-cal__chiprow" role="group" aria-label={strings.calendar_keys} data-region="dim-cal-chips">
        {KEYS.map((key) => {
            const on = !off.includes(key);
            return (
                <button
                    key={key}
                    type="button"
                    className={`dim-cal__chip dim-cal-key--${key}`}
                    aria-pressed={on}
                    data-key={key}
                    onClick={() => onToggle(key)}
                >
                    <span className="dim-cal__swatch" aria-hidden="true"></span>
                    <span className="dim-cal__chiplabel">{keyLabel(strings, key)}</span>
                    {on ? <span className="dim-cal__chipcount">{counts[key] || 0}</span> : null}
                </button>
            );
        })}
    </div>
);

export default KeyChips;
