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
 * One week's piece of a spanning bar: a link to the item, in the slot the row gave it.
 *
 * The bar sits in the week row's own grid (grid-column: start / span n, grid-row: its slot under the day numbers),
 * with open ends where the item goes on before or after the week. Its label is the item's name and its WHOLE span,
 * whichever week's piece the reader meets. It is out of the Tab order: the grid's keyboard reaches it from a day it
 * covers (F2, then Tab).
 *
 * @module     theme_dimensions/calendar/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {CSSProperties} from 'react';
import {itemHref} from './model';
import type {Segment} from './layout';
import {barLabel, colourStyle, keyClass, placeOf} from './look';
import type {Look} from './look';

/** What a bar is handed. */
type BarProps = {
    segment: Segment,
    week: string[],
    look: Look,
};

/**
 * The bar.
 *
 * @param {object} props See BarProps.
 * @returns {object} The link.
 */
const Bar = ({segment, week, look}: BarProps) => {
    const {item, first, last} = segment.placed;
    const span = segment.end - segment.start + 1;
    const place = span >= 3 ? placeOf(item, look) : '';
    const classes = ['dim-cal__bar', keyClass(item)];
    if (segment.clipstart) {
        classes.push('dim-cal__bar--clipstart');
    }
    if (segment.clipend) {
        classes.push('dim-cal__bar--clipend');
    }
    const style = {
        ...colourStyle(item, look),
        gridColumn: `${segment.start + 1} / span ${span}`,
        gridRow: segment.slot + 2,
    } as CSSProperties;
    return (
        <a
            className={classes.join(' ')}
            href={itemHref(item, look.courses, look.home)}
            style={style}
            tabIndex={-1}
            aria-label={barLabel(item, first, last, look)}
            data-cal-item=""
            data-first={week[segment.start]}
            data-last={week[segment.end]}
            data-source={item.source}
            data-id={item.id}
        >
            {segment.clipstart ? <span aria-hidden="true">‹ </span> : null}
            <span className="dim-cal__name">{item.name}{place !== '' ? ` · ${place}` : ''}</span>
            {segment.clipend ? <span aria-hidden="true"> ›</span> : null}
        </a>
    );
};

export default Bar;
