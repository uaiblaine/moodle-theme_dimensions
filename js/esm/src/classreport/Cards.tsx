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
 * The class report's cards: the shared card view (datareport/Cards) with the report's cells.
 *
 * A card is the learner (their name and identity fields), one fixed line saying how many of the shown
 * competencies they are proficient in and how many are rated (from the row's typed ratings, the rows
 * answer's own figures), then a line per shown competency, its title and its rating label, behind "More" on
 * a narrow container.
 *
 * @module     theme_dimensions/classreport/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {Cards as DataCards} from '../datareport/Cards';
import type {CardContent} from '../datareport/Cards';
import {Lines} from '../datareport/cells';
import {fillObject, num} from '../datareport/str';
import {Learner, cellHtml} from './cells';
import type {Column, LearnerRow} from './types';

type CardsProps = {
    labels: Record<string, string>,
    columns: Column[],
    identity: Column[],
    rows: LearnerRow[],
    single: boolean,
};

/**
 * What one card holds.
 *
 * @param {object} labels The strings.
 * @param {Array} columns The competency columns shown.
 * @param {Array} identity The identity columns.
 * @param {object} row The row.
 * @returns {object} The card's first line, its fixed line and its list.
 */
const content = (labels: Record<string, string>, columns: Column[], identity: Column[], row: LearnerRow): CardContent => {
    const rated = row.ratings.filter((rating) => rating.grade !== null).length;
    const proficient = row.ratings.filter((rating) => rating.proficient).length;

    return {
        head: <Learner row={row} identity={identity} />,
        lines: row.ratings.length > 0 ? (
            <div className="dim-comp-rview-card-line">
                {fillObject(labels.cardline, {
                    proficient: num(proficient),
                    rated: num(rated),
                    shown: num(row.ratings.length),
                })}
            </div>
        ) : null,
        fields: columns.map((column) => ({
            key: column.key,
            title: column.title,
            content: <Lines parts={[cellHtml(row, column.key)]} />,
        })),
    };
};

/**
 * The cards.
 *
 * @param {object} props See CardsProps.
 * @returns {object} The list of cards.
 */
export const Cards = ({labels, columns, identity, rows, single}: CardsProps): ReactElement => (
    <DataCards<LearnerRow>
        labels={labels}
        rows={rows}
        single={single}
        renderCard={(row) => content(labels, columns, identity, row)}
    />
);
