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
 * The "More filters" panel: the course model, the modality, the city, "only what I have to do" and
 * the colouring.
 *
 * A disclosure: the button says how many filters narrow the calendar, and the panel applies every change at once (the
 * page asks the range again and remembers the state after a quiet). The model's and the modality's boxes all start
 * checked, meaning every option, as the event types' dropdown starts with every key on:
 * the server reads an empty list as every option, so an empty list draws every box on, unchecking one writes the
 * others, and checking them all again writes the empty list (model.ts shownOn(), keptAfter()). The last box on of a
 * group is disabled: "none" has no spelling in the server's contract. Each input carries data-on, the state the
 * component drew, which a test reads where the live checked property is out of its reach. "Clear filters" is the
 * toolbar's, beside this button (Calendar.tsx): it puts back the keys and the category scope as well as these.
 *
 * @module     theme_dimensions/calendar/MoreFilters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {keptAfter, narrows, shownOn} from './model';
import type {Colour, Option, State} from './model';
import {usePopover} from './popover';
import {fill} from './strings';
import type {Strings} from './strings';

/** What the panel is handed. */
type MoreFiltersProps = {
    strings: Strings,
    state: State,
    options: {model: Option[], modality: Option[], city: string[]},
    onChange: (change: Partial<State>) => void,
};

/**
 * How many of the panel's filters narrow the calendar: a group whose list misses one of its options (one each, however
 * many boxes are off), the city and "only what I have to do".
 *
 * @param {object} state The state.
 * @param {object} options The options offered.
 * @returns {number}
 */
export const filterCount = (state: State, options: {model: Option[], modality: Option[]}): number =>
    (narrows(state.model, options.model) ? 1 : 0) + (narrows(state.modality, options.modality) ? 1 : 0)
    + (state.city !== '' ? 1 : 0) + (state.actiononly ? 1 : 0);

/**
 * The panel.
 *
 * @param {object} props See MoreFiltersProps.
 * @returns {object} The button and its panel.
 */
const MoreFilters = ({strings, state, options, onChange}: MoreFiltersProps) => {
    const popover = usePopover();
    const count = filterCount(state, options);
    const panelid = 'dim-cal-morepanel';
    const cities = options.city.includes(state.city) || state.city === '' ? options.city : [...options.city, state.city];

    /**
     * One group of checkboxes over a course field's options.
     *
     * @param {string} name model or modality.
     * @param {string} legend The group's name.
     * @param {Array} choices The options.
     * @param {Array} kept The values kept, empty for every one.
     * @returns {object|null} The group, or nothing when the field has no option.
     */
    const group = (name: 'model' | 'modality', legend: string, choices: Option[], kept: number[]) => {
        if (!choices.length) {
            return null;
        }
        const on = choices.filter((option) => shownOn(kept, option.value)).length;
        return (
            <fieldset>
                <legend>{legend}</legend>
                {choices.map((option) => {
                    const checked = shownOn(kept, option.value);
                    return (
                        <label key={option.value} className="dim-cal__check">
                            <input
                                type="checkbox"
                                name={name}
                                value={option.value}
                                checked={checked}
                                disabled={checked && on === 1}
                                data-on={checked ? '1' : '0'}
                                onChange={(event) => onChange({
                                    [name]: keptAfter(kept, choices, option.value, event.target.checked),
                                })}
                            />
                            {option.label}
                        </label>
                    );
                })}
            </fieldset>
        );
    };

    return (
        <div className="dim-cal__picker" data-region="dim-cal-more">
            <button
                ref={popover.button}
                type="button"
                className="btn btn-secondary dim-cal__pickerbtn"
                aria-expanded={popover.open}
                aria-controls={panelid}
                onClick={popover.toggle}
            >
                <i className="fa fa-sliders" aria-hidden="true"></i>
                {strings.calendar_morefilters}
                {count ? (
                    <span className="dim-cal__badge">
                        <span aria-hidden="true">{count}</span>
                        <span className="visually-hidden">{fill(strings.calendar_filtercount, count)}</span>
                    </span>
                ) : null}
            </button>
            <div
                ref={popover.panel}
                className="dim-cal__popover"
                id={panelid}
                role="group"
                aria-label={strings.calendar_morefilters}
                hidden={!popover.open}
                onKeyDown={popover.onKeyDown}
            >
                {group('model', strings.calendar_model, options.model, state.model)}
                {group('modality', strings.calendar_modality, options.modality, state.modality)}
                <label className="dim-cal__field">
                    <span>{strings.calendar_city}</span>
                    <select
                        className="form-select"
                        value={state.city}
                        onChange={(event) => onChange({city: event.target.value})}
                    >
                        <option value="">{strings.calendar_cityany}</option>
                        {cities.map((city) => <option key={city} value={city}>{city}</option>)}
                    </select>
                </label>
                <label className="dim-cal__check">
                    <input
                        type="checkbox"
                        checked={state.actiononly}
                        onChange={(event) => onChange({actiononly: event.target.checked})}
                    />
                    {strings.calendar_actiononly}
                </label>
                <fieldset>
                    <legend>{strings.calendar_colourby}</legend>
                    {(['key', 'category'] as Colour[]).map((colour) => (
                        <label key={colour} className="dim-cal__check">
                            <input
                                type="radio"
                                name="dim-cal-colour"
                                value={colour}
                                checked={state.colour === colour}
                                onChange={() => onChange({colour: colour})}
                            />
                            {colour === 'key' ? strings.calendar_colourbykey : strings.calendar_colourbycategory}
                        </label>
                    ))}
                </fieldset>
            </div>
        </div>
    );
};

export default MoreFilters;
