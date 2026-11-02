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
 * The month grid and its keyboard (docs/calendar/PROPOSAL.md section 11.3).
 *
 * role="grid" labelled by the month's caption, a columnheader row of weekdays, then a row per week whose seven
 * gridcells each hold one day button. One day button is in the Tab order, the selected day's (roving tabindex):
 *
 * - ArrowLeft and ArrowRight move a day, ArrowUp and ArrowDown a week, Home and End to the week's first and last
 *   day; a move past the grid's days goes to that day's month (the page asks the range for it);
 * - PageUp and PageDown go to the previous and next month, on the same day number or the month's last;
 * - Enter and Space press the day button, which opens the day: the drawer beside the grid, or on a phone the sheet
 *   under it (Calendar.tsx);
 * - A click anywhere on a day but its controls (onGround handed) opens it as its button does, a convenience for the
 *   pointer: the button stays the keyboard's way in. A double-click there opens core's form for a new event on that
 *   day while the page offers "New event" (onCreate handed); the keyboard's way is the drawer's "New event";
 * - F2 on a day that has items moves into them: the first item of the day takes the focus, Tab and Shift+Tab cycle
 *   the day's items (its bars, its own items, its "+N"), Escape goes back to the day. The items are links and
 *   buttons out of the Tab order, so Tab from a day leaves the grid as from any other control.
 *
 * ONE ResizeObserver measures the grid, never per cell and never read on the mount path (its first entries are the
 * first measures): the rows' container, whose height decides the density (until then the stylesheet's height stands,
 * layout.ROW_DEFAULT), and the frame the grid shares with the drawer, whose width decides the phone layout
 * ({@see PHONE}). The frame, not the grid: the open drawer narrows the grid, and the grid's own width would flip the
 * layout to the phone's the moment the drawer opened.
 *
 * @module     theme_dimensions/calendar/MonthGrid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useLayoutEffect, useMemo, useRef, useState} from 'react';
import type {KeyboardEvent} from 'react';
import {WEEK, say, shift, shiftMonth} from '../daterange/calendar';
import {ROW_DEFAULT, barSlots, dayCounts, weekLayout} from './layout';
import type {Dots, Placed} from './layout';
import type {Look} from './look';
import {isWeekend} from './days';
import WeekRow from './WeekRow';

/** What the grid is handed. */
type MonthGridProps = {
    look: Look,
    month: string,
    weeks: string[][],
    placed: Placed[],
    today: string,
    selected: string,
    focusing: number,
    captionid: string,
    dots: Map<string, Dots> | null,
    onSelect: (iso: string) => void,
    onMonth: (iso: string) => void,
    onOpen: (iso: string) => void,
    onGround?: (iso: string, hour: number | null, clicks: number) => void,
    onCreate?: (iso: string, hour: number | null) => void,
    onWidth: (width: number) => void,
};

/** The frame's width under which the month is a phone's, in px: the dots and the sheet. */
export const PHONE = 576;

/** The weekday names' format. */
const WEEKDAY: Intl.DateTimeFormatOptions = {weekday: 'short'};

/**
 * The day a key moves to from a day, or null when the key moves nothing.
 *
 * @param {string} key The key.
 * @param {string} iso The day stood on.
 * @param {Array} week The week the day is in.
 * @returns {object|null} {day, month}: the day, and whether the month must change whatever the grid shows.
 */
export const stepFrom = (key: string, iso: string, week: string[]): {day: string, month: boolean} | null => {
    switch (key) {
        case 'ArrowLeft':
            return {day: shift(iso, -1), month: false};
        case 'ArrowRight':
            return {day: shift(iso, 1), month: false};
        case 'ArrowUp':
            return {day: shift(iso, -WEEK), month: false};
        case 'ArrowDown':
            return {day: shift(iso, WEEK), month: false};
        case 'Home':
            return {day: week[0], month: false};
        case 'End':
            return {day: week[week.length - 1], month: false};
        case 'PageUp':
            return {day: shiftMonth(iso, -1), month: true};
        case 'PageDown':
            return {day: shiftMonth(iso, 1), month: true};
        default:
            return null;
    }
};

/**
 * The grid.
 *
 * @param {object} props See MonthGridProps.
 * @returns {object} The grid.
 */
const MonthGrid = (props: MonthGridProps) => {
    const {look, month, weeks, placed, today, selected, focusing, captionid, dots, onSelect, onMonth, onOpen} = props;
    const [height, setHeight] = useState<number>(ROW_DEFAULT);
    const [itemsday, setItemsday] = useState<string | null>(null);
    const grid = useRef<HTMLDivElement | null>(null);
    const rows = useRef<HTMLDivElement | null>(null);
    const count = useRef<number>(weeks.length);
    count.current = weeks.length;
    const width = useRef<(width: number) => void>(props.onWidth);
    width.current = props.onWidth;

    const days = useMemo(() => weeks.flat(), [weeks]);
    const counts = useMemo(() => dayCounts(days, placed), [days, placed]);
    const slots = barSlots(height);
    const layouts = useMemo(() => weeks.map((week) => weekLayout(week, placed, slots)), [weeks, placed, slots]);

    useEffect(() => {
        const element = rows.current;
        if (!element || typeof ResizeObserver === 'undefined') {
            return undefined;
        }
        const frame = grid.current?.parentElement || null;
        const observer = new ResizeObserver((entries) => {
            entries.forEach((entry) => {
                const box = entry.contentRect;
                if (entry.target === element && box.height > 0 && count.current > 0) {
                    setHeight(Math.round(box.height / count.current));
                }
                if (entry.target === frame && box.width > 0) {
                    width.current(box.width);
                }
            });
        });
        observer.observe(element);
        if (frame) {
            observer.observe(frame);
        }
        return () => observer.disconnect();
    }, []);

    useLayoutEffect(() => {
        if (focusing) {
            grid.current?.querySelector<HTMLElement>(`[data-role="day"][data-day="${selected}"]`)?.focus();
        }
    }, [focusing]);

    /**
     * The items of a day, in the order the grid draws them.
     *
     * @param {string} iso The day.
     * @returns {Array} The items' elements.
     */
    const itemsOf = (iso: string): HTMLElement[] => Array.from(grid.current?.querySelectorAll<HTMLElement>('[data-cal-item]') || [])
        .filter((element) => (element.dataset.first || '') <= iso && iso <= (element.dataset.last || ''));

    /**
     * The grid's keyboard (see the module's docblock).
     *
     * @param {object} event The key press.
     * @returns {void}
     */
    const onKeyDown = (event: KeyboardEvent): void => {
        const target = event.target as HTMLElement;
        if (itemsday !== null && target.hasAttribute('data-cal-item')) {
            const items = itemsOf(itemsday);
            if (event.key === 'Tab' && items.length) {
                event.preventDefault();
                const index = items.indexOf(target);
                items[(index + (event.shiftKey ? items.length - 1 : 1)) % items.length].focus();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                const day = itemsday;
                setItemsday(null);
                grid.current?.querySelector<HTMLElement>(`[data-role="day"][data-day="${day}"]`)?.focus();
            }
            return;
        }
        if (target.dataset.role !== 'day') {
            return;
        }
        const iso = target.dataset.day || selected;
        if (event.key === 'F2') {
            const items = itemsOf(iso);
            if (items.length) {
                event.preventDefault();
                setItemsday(iso);
                items[0].focus();
            }
            return;
        }
        const week = weeks.find((row) => row.includes(iso)) || weeks[0];
        const step = stepFrom(event.key, iso, week);
        if (!step || step.day === '') {
            return;
        }
        event.preventDefault();
        if (step.month || !days.includes(step.day)) {
            onMonth(step.day);
        } else {
            onSelect(step.day);
        }
    };

    return (
        <div
            ref={grid}
            className={`dim-cal__grid${dots ? ' dim-cal__grid--phone' : ''}`}
            role="grid"
            aria-labelledby={captionid}
            data-region="dim-cal-grid"
            onKeyDown={onKeyDown}
            onBlur={(event) => {
                if (!grid.current?.contains(event.relatedTarget as Node | null)) {
                    setItemsday(null);
                }
            }}
        >
            <div className="dim-cal__weekdays" role="row">
                {weeks[0].map((iso) => (
                    <div
                        key={iso}
                        className={`dim-cal__weekday${isWeekend(iso) ? ' dim-cal__weekday--weekend' : ''}`}
                        role="columnheader"
                    >{say(iso, WEEKDAY, look.language)}</div>
                ))}
            </div>
            <div ref={rows} className="dim-cal__weeks" role="rowgroup">
                {weeks.map((week, index) => (
                    <WeekRow
                        key={week[0]}
                        week={week}
                        layout={layouts[index]}
                        height={height}
                        month={month}
                        today={today}
                        selected={selected}
                        counts={counts}
                        look={look}
                        dots={dots}
                        onOpen={onOpen}
                        onGround={props.onGround}
                        onCreate={props.onCreate}
                    />
                ))}
            </div>
        </div>
    );
};

export default MonthGrid;
