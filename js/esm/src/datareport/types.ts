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
 * The shapes the data report's shared parts read (the Design System's data-report.md), copied from
 * local_evidencehub's datareport/types.ts without the parts this theme's reports do not draw (the scope, the
 * Columns menu's sets, the selection and the group facet's groupings).
 *
 * Nothing here names a view's rows or web services: a view adapts its own answers to these shapes and keeps its
 * own types beside its own code.
 *
 * @module     theme_dimensions/datareport/types
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export type ViewMode = 'auto' | 'table' | 'cards';

/** A column a view shows or offers; fixed is always shown and cannot be chosen away. */
export type Column = {
    key: string,
    title: string,
    sortable: boolean,
    default: boolean,
    active: boolean,
    fixed: boolean,
};

/** The least a row carries for the grid and the cards: its id, unique in the page. */
export type RowBase = {id: number};

/** One option of a facet, with the rows it would list under every other filter; -1 when not counted. */
export type FilterOption = {value: string, label: string, count: number};

/**
 * One facet the filter panel draws: a heading and its options, one of which may be chosen.
 *
 * Above eight options the facet is a searched list: `find` is that list's search label (a string with
 * {$a}, the number of options) and the note drawn when nothing matches.
 */
export type FacetDefinition = {
    name: string,
    title: string,
    options: FilterOption[],
    find: {label: string, nomatch: string},
};

/** One chip of what is in force: its text, and the key the removal names. */
export type Chip = {key: string, text: string};

/** One link of the Download menu. */
export type DownloadLink = {key: string, label: string, href: string};

/**
 * How many of the Columns menu's optional columns may be shown at once, and the line that says so when the
 * limit is reached; null for no limit.
 */
export type ColumnLimit = {most: number, note: string} | null;

/** A column's width policy: its default and minimum by key, and the widest any column may be dragged. */
export type Sizing = {
    defaultWidth: (key: string) => number,
    minimumWidth: (key: string) => number,
    maximum: number,
};
