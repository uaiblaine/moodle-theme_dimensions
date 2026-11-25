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
 * The report's filters as core stores them, read and rewritten for the panel and the chips.
 *
 * Core keeps every filter value of a report for a user in one store, keyed "<filter>_<field>", and
 * core_reportbuilder_set_filters REPLACES that whole set. So a change to one filter is written as every
 * stored value, with that filter's own keys changed, and a removal as every other filter's keys. Every
 * filter the panel draws is a competency's rating (the rows answer names its facet "choice"): the theme's
 * reportbuilder\local\filters\rating, core's select, which stores "<filter>_operator" and "<filter>_value" and
 * reads EQUAL_TO with one value, "Not rated" being the value 0. The learner's name and the identity fields are
 * set in core's table view and appear here as chips that remove them. Copied from local_evidencehub's
 * hub/filters.ts without the hub's own filter names.
 *
 * @module     theme_dimensions/classreport/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Filter} from './types';

/** Core_reportbuilder\local\filters\select::ANY_VALUE: the filter narrows nothing. */
const ANY_VALUE = 0;

/** Core_reportbuilder\local\filters\select::EQUAL_TO. */
const EQUAL_TO = 1;

/**
 * Whether the panel can draw a filter: a facet with options to offer.
 *
 * @param {object} filter The filter.
 * @returns {boolean} True for a facet with options.
 */
export const choosable = (filter: Filter): boolean => filter.facet !== '' && filter.options.length > 0;

/**
 * A filter's own stored values.
 *
 * @param {object} filter The filter.
 * @returns {object} Its keys and values, as stored.
 */
export const ownValues = (filter: Filter): Record<string, unknown> => {
    try {
        const parsed = JSON.parse(filter.values || '{}');
        return parsed !== null && typeof parsed === 'object' ? parsed as Record<string, unknown> : {};
    } catch {
        return {};
    }
};

/**
 * Every stored value of every filter, as one set.
 *
 * @param {Array} filters The report's filters.
 * @returns {object} The set core stores.
 */
export const allValues = (filters: Filter[]): Record<string, unknown> =>
    filters.reduce((all, filter) => ({...all, ...ownValues(filter)}), {} as Record<string, unknown>);

/**
 * The option a choosable filter is set to, or '' for none.
 *
 * @param {object} filter The filter.
 * @returns {string} The chosen option's value, or ''.
 */
export const chosen = (filter: Filter): string => {
    if (!filter.applied || filter.facet === '') {
        return '';
    }
    const own = ownValues(filter);
    const value = own[`${filter.name}_value`];
    if (value === undefined || value === null || Number(own[`${filter.name}_operator`] ?? ANY_VALUE) !== EQUAL_TO) {
        return '';
    }

    return String(value);
};

/**
 * The whole set without one filter's values.
 *
 * @param {Array} filters The report's filters.
 * @param {object} filter The filter to remove.
 * @returns {object} The set to store.
 */
export const without = (filters: Filter[], filter: Filter): Record<string, unknown> => {
    const values = allValues(filters);
    for (const key of Object.keys(values)) {
        if (key.startsWith(`${filter.name}_`)) {
            delete values[key];
        }
    }

    return values;
};

/**
 * The whole set with one filter set to an option, or released with '', in its class's own keys.
 *
 * @param {Array} filters The report's filters.
 * @param {object} filter The filter being set.
 * @param {string} value The option, or '' for any.
 * @returns {object} The set to store.
 */
export const withChoice = (filters: Filter[], filter: Filter, value: string): Record<string, unknown> => {
    const values = without(filters, filter);
    values[`${filter.name}_operator`] = value === '' ? ANY_VALUE : EQUAL_TO;
    values[`${filter.name}_value`] = value;

    return values;
};

/**
 * What a filter in force reads as on its chip: its option's label where it has one.
 *
 * @param {object} filter The filter, applied.
 * @returns {string} The option's label, or '' when the filter is not one of a list or names no offered option.
 */
export const chosenLabel = (filter: Filter): string => {
    const value = chosen(filter);
    if (value === '') {
        return '';
    }

    return filter.options.find((option) => option.value === value)?.label ?? '';
};
