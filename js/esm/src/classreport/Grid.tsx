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
 * The class report's grid: the shared bounded grid (datareport/Grid) with the report's cells.
 *
 * The lead column is the learner, their name linked to their breakdown as the report formats it, and the
 * identity fields the reader's report shows under it; every other column is a competency, its cell the
 * report's rating label, which a grader's press opens core's grading dialogue from (classratings.js).
 *
 * @module     theme_dimensions/classreport/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {Lines} from '../datareport/cells';
import {Grid as DataGrid} from '../datareport/Grid';
import {LEAD, Learner, SIZING, cellHtml} from './cells';
import type {Column, LearnerRow} from './types';

type GridProps = {
    labels: Record<string, string>,
    columns: Column[],
    identity: Column[],
    rows: LearnerRow[],
    widths: Record<string, number>,
    sort: string,
    direction: string,
    onSort: (key: string) => void,
    onWidth: (key: string, width: number | null, defer?: boolean) => void,
    onWidthCommit: () => void,
};

/**
 * The grid.
 *
 * @param {object} props See GridProps.
 * @returns {object} The frame and its table.
 */
export const Grid = ({labels, columns, identity, rows, widths, sort, direction, onSort, onWidth,
        onWidthCommit}: GridProps): ReactElement => (
    <DataGrid<LearnerRow>
        labels={labels}
        columns={columns}
        lead={LEAD}
        rows={rows}
        widths={widths}
        sizing={SIZING}
        sort={sort}
        direction={direction}
        renderLead={(row) => <Learner row={row} identity={identity} />}
        renderCell={(row, column) => <Lines parts={[cellHtml(row, column.key)]} />}
        onSort={onSort}
        onWidth={onWidth}
        onWidthCommit={onWidthCommit}
    />
);
