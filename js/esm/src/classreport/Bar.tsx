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
 * The class report's filter bar: the shared bar (datareport/Bar) with the report's vocabulary.
 *
 * Filters draws one facet per shown competency, its rating: the scale's values and "Not rated", each option
 * with the learners it would list under every other criterion in force; they live in core's store
 * (core_reportbuilder_set_filters, filters.ts). The report's other filters (the learner's name, the identity
 * fields) are set in core's table view, which a note under the facets leads to, and appear here as chips that
 * remove them. Columns lists the learner, always shown, then every competency of the course (or the activity),
 * at most the props' maxcolumns of them shown at once. View is Automatic, Table or Cards, then core's table
 * view. Download is a menu of the site's enabled formats, each a link to core's reportbuilder/download.php with
 * the report's own parameters (the activity, the page's group and the search in force), so the file holds what
 * the list shows; the report applies the stored filters and the columns itself. It is offered once rows have
 * arrived and while there are any. Shaped after local_evidencehub's hub/Bar.tsx.
 *
 * @module     theme_dimensions/classreport/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {Bar as DataBar} from '../datareport/Bar';
import {fill, fillObject} from '../datareport/str';
import type {Chip, DownloadLink, FacetDefinition} from '../datareport/types';
import {choosable, chosen, chosenLabel} from './filters';
import type {Column, Filter, Format, ViewMode} from './types';

type BarProps = {
    labels: Record<string, string>,
    narrow: boolean,
    total: number | null,
    filters: Filter[],
    columns: Column[],
    maxcolumns: number,
    usedefault: boolean,
    haswidths: boolean,
    view: ViewMode,
    search: string,
    searchmin: number,
    searching: boolean,
    tableurl: string,
    /** Core's download.php with the report's id; '' offers no download. */
    downloadurl: string,
    formats: Format[],
    /** The report's parameters the download is built with: the activity, the page's group, the search in force. */
    moduleid: number,
    groupid: number,
    appliedsearch: string,
    /** Whether the rows can be downloaded now: they have arrived and there are some. */
    downloadable: boolean,
    onSearch: (text: string) => void,
    onFilter: (filter: Filter, value: string) => void,
    onRemoveFilter: (filter: Filter) => void,
    onClearFilters: () => void,
    onClearAll: () => void,
    onColumn: (key: string, on: boolean) => void,
    onDefaultColumns: () => void,
    onResetWidths: () => void,
    onView: (view: ViewMode) => void,
};

/** Every view the report offers. */
const VIEWS: ViewMode[] = ['auto', 'table', 'cards'];

/**
 * A report filter as a facet of the panel.
 *
 * @param {object} labels The strings.
 * @param {object} filter The filter, one the panel draws.
 * @returns {object} The facet.
 */
const facet = (labels: Record<string, string>, filter: Filter): FacetDefinition => ({
    name: filter.name,
    title: filter.title,
    options: filter.options,
    find: {label: labels.searchoptions, nomatch: labels.nooptionmatch},
});

/**
 * What an applied filter reads as on its chip: its heading and its option, or its heading alone.
 *
 * @param {object} labels The strings.
 * @param {object} filter The filter, applied.
 * @returns {object} The chip.
 */
const chip = (labels: Record<string, string>, filter: Filter): Chip => {
    const value = chosenLabel(filter);

    return {key: filter.name, text: value === '' ? filter.title : fillObject(labels.filterchip, {title: filter.title, value})};
};

/**
 * The address of one download: core's download.php with the format and the report's parameters, as core's own
 * download form sends them (a JSON object the report reads in initialise()).
 *
 * @param {string} url Core's download.php with the report's id.
 * @param {string} format The dataformat's name.
 * @param {number} moduleid The activity, or 0.
 * @param {number} groupid The page's group.
 * @param {string} search The search in force, or ''.
 * @returns {string} The address.
 */
export const downloadHref = (url: string, format: string, moduleid: number, groupid: number, search: string): string => {
    const parameters: Record<string, string | number> = {moduleid, groupid};
    if (search !== '') {
        parameters.search = search;
    }
    const query = new URLSearchParams({download: format, parameters: JSON.stringify(parameters)});

    return `${url}${url.includes('?') ? '&' : '?'}${query.toString()}`;
};

/**
 * The bar.
 *
 * @param {object} props See BarProps.
 * @returns {object} The bar.
 */
export const Bar = (props: BarProps): ReactElement => {
    const {labels, filters} = props;
    const panelfilters = filters.filter(choosable);
    const others = filters.filter((filter) => !choosable(filter));
    /**
     * The filter a facet or a chip names.
     *
     * @param {string} name The filter's name.
     * @returns {object|undefined} The filter.
     */
    const named = (name: string): Filter | undefined => filters.find((filter) => filter.name === name);
    const downloads: DownloadLink[] = props.downloadable && props.downloadurl !== '' ? props.formats.map((format) => ({
        key: format.value,
        label: format.label,
        href: downloadHref(props.downloadurl, format.value, props.moduleid, props.groupid, props.appliedsearch),
    })) : [];

    return (
        <DataBar
            labels={labels}
            narrow={props.narrow}
            total={props.total}
            facets={panelfilters.map((filter) => facet(labels, filter))}
            values={Object.fromEntries(panelfilters.map((filter) => [filter.name, chosen(filter)]))}
            panelnote={(
                <>
                    {panelfilters.length === 0 ? <p className="dim-comp-rview-note">{labels.nofacets}</p> : null}
                    {others.length > 0 ? (
                        <p className="dim-comp-rview-note">
                            {labels.morefiltershelp}{' '}
                            <a href={props.tableurl}>{labels.morefilters}</a>
                        </p>
                    ) : null}
                </>
            )}
            chips={filters.filter((filter) => filter.applied).map((filter) => chip(labels, filter))}
            columns={props.columns}
            limit={{most: props.maxcolumns, note: fill(labels.columnlimit, String(props.maxcolumns))}}
            usedefault={props.usedefault}
            haswidths={props.haswidths}
            view={props.view}
            views={VIEWS}
            tableurl={props.tableurl}
            downloads={downloads}
            search={props.search}
            searchmin={props.searchmin}
            searching={props.searching}
            onSearch={props.onSearch}
            onFacet={(name, value) => {
                const filter = named(name);
                if (filter) {
                    props.onFilter(filter, value);
                }
            }}
            onRemoveChip={(name) => {
                const filter = named(name);
                if (filter) {
                    props.onRemoveFilter(filter);
                }
            }}
            onClearFilters={props.onClearFilters}
            onClearAll={props.onClearAll}
            onColumn={props.onColumn}
            onDefaultColumns={props.onDefaultColumns}
            onResetWidths={props.onResetWidths}
            onView={props.onView}
        />
    );
};
