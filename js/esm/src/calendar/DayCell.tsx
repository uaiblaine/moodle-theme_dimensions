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
 * One day of the grid: its ground, its day button, the bars that start in it, and its own items at their density.
 *
 * The cell is the row's gridcell and lends its children to the week row's grid (display: contents), so a bar it
 * holds can span the following days' columns. The day button is the grid's one Tab stop when the day is the
 * selected one (roving tabindex); its label is the long date, the count of every item touching the day and "today"
 * when it is. The content draws one of three densities (calendar/layout.ts): a card, lines, or a summary ending in
 * "+N" with a dot per key of what was left out, a button that opens the day and names it. On a phone the content is
 * a dot per key with items instead (layout.ts dayDots()), hidden from the screen reader: the day button's label
 * says the count, and the day's sheet lists the items.
 *
 * @module     theme_dimensions/calendar/DayCell
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {CSSProperties, MouseEvent, PointerEvent, ReactNode} from 'react';
import {say} from '../daterange/calendar';
import {isWeekend} from './days';
import {itemHref} from './model';
import type {CellPlan, Dots, Placed} from './layout';
import {colourStyle, keyClass, placeOf, whenOf} from './look';
import type {Look} from './look';
import {fill} from './strings';

/** The long date of a day button's label. */
const LONG_DAY: Intl.DateTimeFormatOptions = {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'};

/** The day and month a "+N" names. */
const SHORT_DAY: Intl.DateTimeFormatOptions = {day: 'numeric', month: 'long'};

/** What a day is handed. */
type DayCellProps = {
    iso: string,
    column: number,
    month: string,
    today: string,
    selected: boolean,
    count: number,
    plan: CellPlan,
    contentRow: number,
    look: Look,
    bars: ReactNode,
    dots: Dots | null,
    onOpen: (iso: string) => void,
    onGround?: (iso: string, hour: number | null, clicks: number) => void,
    onCreate?: (iso: string, hour: number | null) => void,
};

/**
 * The count part of a day's label.
 *
 * @param {object} look The page's look.
 * @param {number} count The items touching the day.
 * @returns {string}
 */
const countText = (look: Look, count: number): string => {
    if (count === 0) {
        return look.strings.calendar_dayitems_none;
    }
    return count === 1 ? look.strings.calendar_dayitems_one : fill(look.strings.calendar_dayitems_many, count);
};

/**
 * A day button's label.
 *
 * @param {object} look The page's look.
 * @param {string} iso The day.
 * @param {number} count The items touching it.
 * @param {boolean} istoday Whether it is today.
 * @returns {string}
 */
export const dayLabel = (look: Look, iso: string, count: number, istoday: boolean): string => {
    const label = fill(look.strings.calendar_daylabel, {date: say(iso, LONG_DAY, look.language), count: countText(look, count)});
    return istoday ? `${label}, ${look.strings.calendar_daytoday}` : label;
};

/**
 * One of the day's own items as a line.
 *
 * @param {object} entry The placed item.
 * @param {string} iso The day.
 * @param {object} look The page's look.
 * @returns {object} The link.
 */
const line = (entry: Placed, iso: string, look: Look) => (
    <a
        key={`${entry.item.source}-${entry.item.id}`}
        className={`dim-cal__line ${keyClass(entry.item)}${entry.item.overdue ? ' dim-cal__line--overdue' : ''}`}
        href={itemHref(entry.item, look.courses, look.home)}
        style={colourStyle(entry.item, look)}
        tabIndex={-1}
        draggable={look.movable?.(entry.item) ? true : undefined}
        data-cal-item=""
        data-cal-pop=""
        data-source={entry.item.source}
        data-id={entry.item.id}
        data-first={iso}
        data-last={iso}
    >
        <span className="dim-cal__dot" aria-hidden="true"></span>
        <span className="dim-cal__time">{look.zone.time(entry.item.timestart)}</span>
        <span className="dim-cal__name">{entry.item.name}</span>
    </a>
);

/**
 * Whether a press landed on a day's ground: anywhere in the day but on one of its controls (the day button, an item, a
 * bar, "+N"), whose own clicks keep their meaning (an item's opens its popover, never the day too).
 *
 * @param {EventTarget} target What was pressed.
 * @returns {boolean}
 */
export const isGround = (target: EventTarget | null): boolean =>
    target instanceof Element && target.closest('a, button, [data-cal-item]') === null;

/**
 * The day's hover mark, for a mouse alone: a finger brackets every tap with an enter and a leave, so both halves are
 * gated by the pointer's type, as the showcase's hover logic is. The mark is an attribute on the day's container, which
 * the stylesheet paints under `(hover: hover)`; nothing is rendered again.
 *
 * @param {boolean} on Whether the pointer enters (true) or leaves.
 * @returns {Function} The handler.
 */
export const hoverMark = (on: boolean) => (event: PointerEvent<HTMLElement>): void => {
    if (event.pointerType !== 'mouse') {
        return;
    }
    if (on) {
        event.currentTarget.setAttribute('data-cal-hover', '');
    } else {
        event.currentTarget.removeAttribute('data-cal-hover');
    }
};

/**
 * The handlers of a day's container: a click on its ground opens the day (its click count handed on, so the first of a
 * double-click opens it and the second nothing), a double-click there opens core's form for a new event on the day,
 * and the mouse's hover mark; each only when the page hands what it calls.
 *
 * @param {string} iso The day.
 * @param {Function|undefined} onGround What a press on the ground does.
 * @param {Function|undefined} onCreate What a double-click on the ground does.
 * @returns {object} The handlers.
 */
const groundHandlers = (
    iso: string,
    onGround: DayCellProps['onGround'],
    onCreate: DayCellProps['onCreate']
) => ({
    ...(onGround ? {
        /**
         * A click on the ground opens the day.
         *
         * @param {object} event The click.
         * @returns {void}
         */
        onClick: (event: MouseEvent): void => {
            if (isGround(event.target)) {
                onGround(iso, null, event.detail);
            }
        },
        onPointerEnter: hoverMark(true),
        onPointerLeave: hoverMark(false),
    } : {}),
    ...(onCreate ? {
        /**
         * A double-click on the ground opens core's form for a new event on the day.
         *
         * @param {object} event The double-click.
         * @returns {void}
         */
        onDoubleClick: (event: MouseEvent): void => {
            if (isGround(event.target)) {
                event.preventDefault();
                onCreate(iso, null);
            }
        },
    } : {}),
});

/**
 * The day.
 *
 * @param {object} props See DayCellProps.
 * @returns {object} The gridcell.
 */
const DayCell = (props: DayCellProps) => {
    const {iso, column, month, today, selected, count, plan, contentRow, look, bars, dots, onOpen, onGround, onCreate} = props;
    const other = !iso.startsWith(month);
    const istoday = iso === today;
    const ground = ['dim-cal__ground'];
    if (other) {
        ground.push('dim-cal__ground--other');
    }
    if (isWeekend(iso)) {
        ground.push('dim-cal__ground--weekend');
    }

    const numclasses = ['dim-cal__daynum'];
    if (other) {
        numclasses.push('dim-cal__daynum--other');
    }
    if (istoday) {
        numclasses.push('dim-cal__daynum--today');
    }
    const card = plan.density === 'card' ? plan.shown[0].item : null;
    const place = card ? placeOf(card, look) : '';
    return (
        <div
            className="dim-cal__day"
            role="gridcell"
            data-day={iso}
            data-cal-drop={look.movable ? iso : undefined}
            {...groundHandlers(iso, onGround, onCreate)}
        >
            <div className={ground.join(' ')} style={{gridColumn: column + 1, gridRow: '1 / -1'}} aria-hidden="true"></div>
            <button
                type="button"
                className={numclasses.join(' ')}
                style={{gridColumn: column + 1, gridRow: 1}}
                tabIndex={selected ? 0 : -1}
                aria-label={dayLabel(look, iso, count, istoday)}
                aria-current={istoday ? 'date' : undefined}
                data-role="day"
                data-day={iso}
                onClick={() => onOpen(iso)}
            >{Number(iso.slice(8, 10))}</button>
            {bars}
            {dots ? (
                <span className="dim-cal__daydots" style={{gridColumn: column + 1, gridRow: 2}} aria-hidden="true">
                    {dots.keys.map((key) => <span key={key} className={`dim-cal__daydot dim-cal-key--${key}`}></span>)}
                    {dots.more ? <span className="dim-cal__daydotmore">+</span> : null}
                </span>
            ) : null}
            {dots || plan.density === 'none' ? null : (
                <div className="dim-cal__content" style={{gridColumn: column + 1, gridRow: contentRow} as CSSProperties}>
                    {card ? (
                        <a
                            className={`dim-cal__card ${keyClass(card)}`}
                            href={itemHref(card, look.courses, look.home)}
                            style={colourStyle(card, look)}
                            tabIndex={-1}
                            draggable={look.movable?.(card) ? true : undefined}
                            data-cal-item=""
                            data-cal-pop=""
                            data-source={card.source}
                            data-id={card.id}
                            data-first={iso}
                            data-last={iso}
                        >
                            <span className="dim-cal__cardmeta">{whenOf(card, look)}</span>
                            <span className="dim-cal__cardtitle">{card.name}</span>
                            {place !== '' ? <span className="dim-cal__cardmeta">{place}</span> : null}
                        </a>
                    ) : plan.shown.map((entry) => line(entry, iso, look))}
                    {plan.more ? (
                        <button
                            type="button"
                            className="dim-cal__overflow"
                            tabIndex={-1}
                            aria-label={fill(look.strings.calendar_moreaction, {
                                count: plan.more,
                                date: say(iso, SHORT_DAY, look.language),
                            })}
                            data-cal-item=""
                            data-first={iso}
                            data-last={iso}
                            onClick={() => onOpen(iso)}
                        >
                            {fill(look.strings.calendar_morelabel, plan.more)}
                            <span className="dim-cal__moredots" aria-hidden="true">
                                {plan.dots.map((key, index) => <span key={index} className={`dim-cal-key--${key}`}></span>)}
                            </span>
                        </button>
                    ) : null}
                </div>
            )}
        </div>
    );
};

export default DayCell;
