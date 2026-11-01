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
 * One week row of the grid: seven days, the bars in their slots, each day's content under them.
 *
 * The row is a grid of its own: a 30px row for the day numbers, one row per bar slot it draws, and the rest for the
 * days' content, so a bar never zigzags and a day's content starts under the lowest bar of its row.
 *
 * @module     theme_dimensions/calendar/WeekRow
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Bar from './Bar';
import DayCell from './DayCell';
import {HEADER, SLOT, cellPlan} from './layout';
import type {WeekLayout} from './layout';
import type {Look} from './look';

/** What a row is handed. */
type WeekRowProps = {
    week: string[],
    layout: WeekLayout,
    height: number,
    month: string,
    today: string,
    selected: string,
    counts: Map<string, number>,
    look: Look,
    onOpen: (iso: string) => void,
};

/**
 * The row.
 *
 * @param {object} props See WeekRowProps.
 * @returns {object} The row.
 */
const WeekRow = ({week, layout, height, month, today, selected, counts, look, onOpen}: WeekRowProps) => {
    const rows = `${HEADER}px${layout.used ? ` repeat(${layout.used}, ${SLOT}px)` : ''} minmax(0, 1fr)`;
    return (
        <div className="dim-cal__week" role="row" style={{gridTemplateRows: rows}}>
            {week.map((iso, column) => (
                <DayCell
                    key={iso}
                    iso={iso}
                    column={column}
                    month={month}
                    today={today}
                    selected={iso === selected}
                    count={counts.get(iso) || 0}
                    plan={cellPlan(layout.timed[column], layout.hidden[column], layout.used, height)}
                    contentRow={layout.used + 2}
                    look={look}
                    bars={layout.bars.filter((segment) => segment.start === column).map((segment) => (
                        <Bar
                            key={`${segment.placed.item.source}-${segment.placed.item.id}`}
                            segment={segment}
                            week={week}
                            look={look}
                        />
                    ))}
                    onOpen={onOpen}
                />
            ))}
        </div>
    );
};

export default WeekRow;
