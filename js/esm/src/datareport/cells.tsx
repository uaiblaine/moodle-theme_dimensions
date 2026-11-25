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
 * A cell drawn from the server's formatted HTML, one fact per line.
 *
 * A data report's cells are formatted on the server, where the masking and the escaping ran, so a cell
 * is inserted as HTML and never built here: the one HTML sink of the view. The first line is the cell's
 * line and the others its sub-lines. Every line ends in an ellipsis, its whole text in its title.
 *
 * @module     theme_dimensions/datareport/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {plain} from './str';

/**
 * One cell's content: its first line, then its sub-lines.
 *
 * @param {object} props The cell's lines, as HTML.
 * @returns {object} The lines.
 */
export const Lines = ({parts}: {parts: string[]}): ReactElement => (
    <>
        {parts.map((part, index) => (
            <span
                key={index}
                className={index === 0 ? 'dim-comp-rview-c' : 'dim-comp-rview-sub'}
                title={plain(part)}
                dangerouslySetInnerHTML={{__html: part}}
            />
        ))}
    </>
);
