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
 * The class report's cells: the width policy, a cell's HTML by key and the learner's lead.
 *
 * Every cell is the report's formatted HTML (class_ratings' formatters, format_row()): the learner's name
 * linked to their breakdown, the identity fields, and per competency the rating in its status label, linked to
 * core's page of the learner's competency, with the grading dialogue's hooks for a reader who may rate
 * (theme_dimensions/classratings opens the dialogue from them). Nothing is built here from a value: the HTML is
 * inserted as it came, the one sink of the view, and every label the view writes is text.
 *
 * @module     theme_dimensions/classreport/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {makeSizing} from '../datareport/sizing';
import {plain} from '../datareport/str';
import type {Column, LearnerRow} from './types';

/** The lead column's key: the learner, always shown. */
export const LEAD = 'learner';

/** Default widths, in pixels: the learner, then a competency's rating (a status label of one or two words). */
const WIDTHS: Record<string, number> = {learner: 260};

/** The width of a column the table above does not name: a competency. */
const DEFAULT_WIDTH = 168;

/** The learner 160 px (data-report.md, "Resize handle": the first column), anything else 80 px. */
const MINIMUMS: Record<string, number> = {learner: 160};

/** The narrowest any other column may be dragged. */
const MINIMUM = 80;

/** The widest a column may be dragged; the server keeps up to 1600. */
const MAXIMUM = 800;

/** The class report's width policy. */
export const SIZING = makeSizing(WIDTHS, DEFAULT_WIDTH, MINIMUMS, MINIMUM, MAXIMUM);

/**
 * A row's cell for a column, as HTML; '' where the report gave none.
 *
 * @param {object} row The row.
 * @param {string} key The column key.
 * @returns {string} The cell's HTML.
 */
export const cellHtml = (row: LearnerRow, key: string): string => row.cells.find((cell) => cell.key === key)?.html ?? '';

/**
 * The identity columns' values of a row: the lines under the learner's name.
 *
 * @param {object} props The row and the identity columns.
 * @returns {object} The lines.
 */
export const IdentityLines = ({row, identity}: {row: LearnerRow, identity: Column[]}): ReactElement => (
    <>
        {identity.map((column) => {
            const html = cellHtml(row, column.key);
            return html === '' ? null : (
                <span
                    key={column.key}
                    className="dim-comp-rview-sub"
                    title={plain(html)}
                    dangerouslySetInnerHTML={{__html: html}}
                />
            );
        })}
    </>
);

/**
 * The learner's lead: the name as the report links it, the identity fields under it.
 *
 * @param {object} props The row and the identity columns.
 * @returns {object} The lead.
 */
export const Learner = ({row, identity}: {row: LearnerRow, identity: Column[]}): ReactElement => {
    const html = cellHtml(row, LEAD);

    return (
        <div className="dim-comp-rview-who">
            <span className="dim-comp-rview-c" title={plain(html)} dangerouslySetInnerHTML={{__html: html}} />
            <IdentityLines row={row} identity={identity} />
        </div>
    );
};
