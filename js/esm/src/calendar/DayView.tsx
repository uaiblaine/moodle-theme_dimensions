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
 * The phone's Day view: one day at the frame's whole width.
 *
 * A region labelled by the toolbar's caption, which names the day (its long date) and whose arrows and "Today" move
 * it; its head holds the "New event" "+" while the page offers it; under it the drawer's own body (DayDrawer.tsx
 * DayBody): the all-day strip, then the whole day's hour column in its scroll box, with the drawer's creation paths
 * (a double-tap or a held touch on an hour row). Items open the popover through the page's delegated listener.
 *
 * Shown only on a phone, so no grid is drawn beside it: its one ResizeObserver watches the frame it is drawn in and
 * reports the frame's width alone, the grids' rule (MonthGrid.tsx PHONE), so the page leaves the Day view when the
 * frame becomes a desktop's. The frame's width does not depend on what it holds. Nothing is measured on the mount
 * path: the observer's entries are the measures.
 *
 * @module     theme_dimensions/calendar/DayView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef} from 'react';
import type {ReactNode} from 'react';
import type {Placed} from './layout';
import type {Look} from './look';
import {DayBody} from './DayDrawer';

/** What the view is handed. */
type DayViewProps = {
    look: Look,
    iso: string,
    placed: Placed[],
    captionid: string,
    action?: ReactNode,
    onCreate?: (hour: number) => void,
    onWidth: (width: number) => void,
};

/**
 * The view.
 *
 * @param {object} props See DayViewProps.
 * @returns {object} The region.
 */
const DayView = ({look, iso, placed, captionid, action, onCreate, onWidth}: DayViewProps) => {
    const root = useRef<HTMLElement | null>(null);
    const width = useRef<(width: number) => void>(onWidth);
    width.current = onWidth;

    useEffect(() => {
        const frame = root.current?.parentElement || null;
        if (!frame || typeof ResizeObserver === 'undefined') {
            return undefined;
        }
        const observer = new ResizeObserver((entries) => {
            entries.forEach((entry) => {
                // The phone decision reads the frame's width only, as the grids' do.
                if (entry.target === frame && entry.contentRect.width > 0) {
                    width.current(entry.contentRect.width);
                }
            });
        });
        observer.observe(frame);
        return () => observer.disconnect();
    }, []);

    return (
        <section
            ref={root}
            className="dim-cal__drawer dim-cal__drawer--dayview"
            role="region"
            aria-labelledby={captionid}
            data-region="dim-cal-dayview"
            data-day={iso}
        >
            {action ? <div className="dim-cal__drawerhead dim-cal__dayviewhead">{action}</div> : null}
            <DayBody look={look} iso={iso} placed={placed} list={false} onCreate={onCreate} />
        </section>
    );
};

export default DayView;
