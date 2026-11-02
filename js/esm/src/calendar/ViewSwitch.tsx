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
 * The toolbar's view switch, Month and Week (docs/calendar/PROPOSAL.md section 21), on both shells.
 *
 * A group of two toggle buttons, the view shown pressed. Pressing the other one is the shell's: it keeps the selected
 * day and shows the week holding it, or its month.
 *
 * @module     theme_dimensions/calendar/ViewSwitch
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Strings} from './strings';
import type {View} from './weeks';

/** What the switch is handed. */
type ViewSwitchProps = {
    strings: Strings,
    view: View,
    onView: (view: View) => void,
};

/**
 * The switch.
 *
 * @param {object} props See ViewSwitchProps.
 * @returns {object} The group.
 */
const ViewSwitch = ({strings, view, onView}: ViewSwitchProps) => (
    <div className="dim-cal__views" role="group" aria-label={strings.calendar_view} data-region="dim-cal-views">
        <button
            type="button"
            className="dim-cal__view"
            aria-pressed={view === 'month'}
            data-action="dim-cal-view-month"
            onClick={() => onView('month')}
        >{strings.calendar_viewmonth}</button>
        <button
            type="button"
            className="dim-cal__view"
            aria-pressed={view === 'week'}
            data-action="dim-cal-view-week"
            onClick={() => onView('week')}
        >{strings.calendar_viewweek}</button>
    </div>
);

export default ViewSwitch;
