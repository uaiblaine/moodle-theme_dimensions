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
 * The toolbar's view switch: Month and Week on both shells, Day on a phone, and Trilhas on the logged-in shell where the
 * page offers it.
 *
 * A group of toggle buttons, the view shown pressed. Pressing another one is the shell's: it keeps the selected day and
 * shows the week holding it, its month, or the day itself. The choices are weeks.ts viewsOf(): the day only while the
 * frame is a phone's.
 *
 * @module     theme_dimensions/calendar/ViewSwitch
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Strings} from './strings';
import {viewsOf} from './weeks';
import type {View} from './weeks';

/** What the switch is handed. */
type ViewSwitchProps = {
    strings: Strings,
    view: View,
    phone: boolean,
    trails?: boolean,
    onView: (view: View) => void,
};

/**
 * A view's name on its button.
 *
 * @param {object} strings The page's strings.
 * @param {string} view The view.
 * @returns {string}
 */
const nameOf = (strings: Strings, view: View): string => {
    if (view === 'day') {
        return strings.calendar_viewday;
    }
    if (view === 'trail') {
        return strings.calendar_viewtrail;
    }
    return view === 'week' ? strings.calendar_viewweek : strings.calendar_viewmonth;
};

/** What one of the switch's buttons is handed. */
type ViewButtonProps = {strings: Strings, one: View, view: View, onView: (view: View) => void};

/**
 * One view's button, pressed while its view is shown.
 *
 * @param {object} props See ViewButtonProps.
 * @returns {object} The button.
 */
const ViewButton = ({strings, one, view, onView}: ViewButtonProps) => (
    <button
        type="button"
        className="dim-cal__view"
        aria-pressed={view === one}
        data-action={`dim-cal-view-${one}`}
        onClick={() => onView(one)}
    >{nameOf(strings, one)}</button>
);

/**
 * The switch.
 *
 * @param {object} props See ViewSwitchProps.
 * @returns {object} The group.
 */
const ViewSwitch = ({strings, view, phone, trails = false, onView}: ViewSwitchProps) => (
    <div className="dim-cal__views" role="group" aria-label={strings.calendar_view} data-region="dim-cal-views">
        {viewsOf(phone).map((one) => (
            <ViewButton key={one} strings={strings} one={one} view={view} onView={onView} />
        ))}
        {trails ? <ViewButton strings={strings} one="trail" view={view} onView={onView} /> : null}
    </div>
);

export default ViewSwitch;
