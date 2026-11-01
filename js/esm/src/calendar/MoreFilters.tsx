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
 * The "More filters" panel (decision D5): the course model, the modality, the city, "only what I have to do" and
 * the colouring (decision D6).
 *
 * A disclosure: the button says how many filters are on, the panel applies every change at once (the page asks
 * the range again and remembers the state after a quiet), and "Clear filters" puts every filter of the panel
 * back, the colouring and the category included in neither.
 *
 * @module     theme_dimensions/calendar/MoreFilters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
 * How many of the panel's filters are on.
 *
 * @param {object} state The state.
 * @returns {number}
 */
export const filterCount = (state: State): number => state.model.length + state.modality.length
    + (state.city !== '' ? 1 : 0) + (state.actiononly ? 1 : 0);

/**
 * A list with a value added or taken out.
 *
 * @param {Array} list The list.
 * @param {number} value The value.
 * @param {boolean} present Whether it is in.
 * @returns {Array} The new list.
 */
const toggled = (list: number[], value: number, present: boolean): number[] =>
    (present ? [...list.filter((other) => other !== value), value] : list.filter((other) => other !== value));

/**
 * The panel.
 *
 * @param {object} props See MoreFiltersProps.
 * @returns {object} The button and its panel.
 */
const MoreFilters = ({strings, state, options, onChange}: MoreFiltersProps) => {
    const popover = usePopover();
    const count = filterCount(state);
    const panelid = 'dim-cal-morepanel';
    const cities = options.city.includes(state.city) || state.city === '' ? options.city : [...options.city, state.city];

    /**
     * One group of checkboxes over a course field's options.
     *
     * @param {string} name model or modality.
     * @param {string} legend The group's name.
     * @param {Array} choices The options.
     * @param {Array} kept The values on.
     * @returns {object|null} The group, or nothing when the field has no option.
     */
    const group = (name: 'model' | 'modality', legend: string, choices: Option[], kept: number[]) => (choices.length ? (
        <fieldset>
            <legend>{legend}</legend>
            {choices.map((option) => (
                <label key={option.value} className="dim-cal__check">
                    <input
                        type="checkbox"
                        name={name}
                        value={option.value}
                        checked={kept.includes(option.value)}
                        onChange={(event) => onChange({[name]: toggled(kept, option.value, event.target.checked)})}
                    />
                    {option.label}
                </label>
            ))}
        </fieldset>
    ) : null);

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
                <button
                    type="button"
                    className="btn btn-link dim-cal__clear"
                    disabled={count === 0}
                    onClick={() => onChange({model: [], modality: [], city: '', actiononly: false})}
                >{strings.calendar_clearfilters}</button>
            </div>
        </div>
    );
};

export default MoreFilters;
