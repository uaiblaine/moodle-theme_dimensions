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
 * One day's items: the drawer beside the grid on a desktop, the agenda sheet under it on a phone,
 * and the body the phone's Day view draws (DayView.tsx).
 *
 * A region labelled by its heading, the long date, which is aria-live so moving the selected day with the keyboard
 * while it is open says the new day. Not modal: the grid stays usable beside it. Enter on a day opens it with the
 * focus on its heading; Escape inside it, or its close button, closes it and gives the focus back to the day.
 *
 * - The drawer: an all-day strip (the bars covering the day, whole-day items, event courses), then the hour column
 *   with the overlap layout (calendar/overlap.ts). The column is the whole day, 00:00 to 24:00, in a scroll box of its
 *   own that is the drawer's body (HourScroll.tsx): it
 *   opens on the day's busy band (overlap.ts hourWindow()), or on the working hours (EMPTY_WINDOW) of a day with
 *   nothing timed on it, and is a Tab stop of its own while it holds no item.
 * - The sheet: the same content as a list, no hour column.
 *
 * An overlapping item carries a visible label as well as its column. Every item is a link to its own page that
 * opens the popover on a press (the page's delegated listener), as the grid's items do. Handed onCreate (the page
 * offers "New event"), a double-click on an hour row of the column opens core's form at that hour; the "New event"
 * "+" button in its head is the keyboard's way. On a day with nothing on it the column is still drawn while creation
 * is offered, under the "nothing on this day" line. A touch (or pen) held on an hour row for {@see HOLD_MS} without
 * moving more than {@see HOLD_SLOP}px opens the form at that hour too, the phone's way: a
 * move, a lift or the browser taking the touch to scroll the box (pointercancel) drops it, so it never fights the scroll.
 *
 * {@see DayBody} is everything under the drawer's head, exported for the Day view, so the day is drawn by one piece of
 * code wherever it is shown.
 *
 * @module     theme_dimensions/calendar/DayDrawer
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useLayoutEffect, useMemo, useRef} from 'react';
import type {CSSProperties, KeyboardEvent, MouseEvent, PointerEvent, ReactNode} from 'react';
import {say} from '../daterange/calendar';
import type {Placed} from './layout';
import {colourStyle, keyClass, placeOf, whenOf} from './look';
import type {Look} from './look';
import {itemHref} from './model';
import HourScroll from './HourScroll';
import {EMPTY_WINDOW, FULL_DAY, dayPlan, hourWindow, itemKey} from './overlap';
import type {HourWindow, Laid} from './overlap';

/** The long date of the heading. */
const LONG_DAY: Intl.DateTimeFormatOptions = {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'};

/** The day and month of a multi-day item's ends. */
const SPAN_DAY: Intl.DateTimeFormatOptions = {day: 'numeric', month: 'short'};

/** The id of the heading, which labels the region. */
export const HEADING = 'dim-cal-day-heading';

/** How long a touch is held on an hour row before it opens core's form, in ms. */
export const HOLD_MS = 500;

/** How far a held touch may move, in px, before it is a scroll and no longer a hold. */
export const HOLD_SLOP = 10;

/** A touch held on an hour row: where it went down, and the timer that opens the form. */
type Hold = {x: number, y: number, timer: number};

/** What the day's body is handed. */
type DayBodyProps = {
    look: Look,
    iso: string,
    gridheight?: number,
    placed: Placed[],
    list: boolean,
    onCreate?: (hour: number) => void,
};

/** What the drawer is handed. */
type DayDrawerProps = {
    look: Look,
    iso: string,
    gridheight?: number,
    placed: Placed[],
    variant: 'drawer' | 'sheet',
    focusing: number,
    action?: ReactNode,
    onCreate?: (hour: number) => void,
    onClose: () => void,
};

/**
 * The hour labels of the column, as the language writes an hour.
 *
 * @param {string|undefined} language The language tag.
 * @returns {Array} 24 labels.
 */
const hourLabels = (language: string | undefined): string[] => {
    const format = new Intl.DateTimeFormat(language, {hour: '2-digit', minute: '2-digit', timeZone: 'UTC'});
    return Array.from({length: 24}, (unused, hour) => format.format(new Date(Date.UTC(1970, 0, 1, hour))));
};

/**
 * The column's own properties: how many hour rows it draws.
 *
 * @param {object} span The column's hours.
 * @returns {object} The local property.
 */
const rows = (span: HourWindow): CSSProperties => ({'--dim-cal-hourcount': String(span.to - span.from)} as CSSProperties);

/**
 * Where an hour item sits in the column: its top and height in shares of the column's hours, clipped to them, its
 * column as shares of the width
 * (scss/calendar.scss turns them into a position beside the hour labels).
 *
 * @param {object} laid The laid item.
 * @param {object} look The page's look.
 * @param {object} span The column's hours.
 * @returns {object} The local position properties.
 */
const position = (laid: Laid, look: Look, span: HourWindow): CSSProperties => {
    const from = span.from * 60;
    const total = (span.to - span.from) * 60;
    const length = Math.max(laid.entry.item.timeduration / 60, 0);
    const start = Math.max(from, look.zone.minutes(laid.entry.item.timestart));
    const end = Math.min(span.to * 60, look.zone.minutes(laid.entry.item.timestart) + length);
    return {
        '--dim-cal-top': `${((start - from) / total) * 100}%`,
        '--dim-cal-height': `${(Math.max(end - start, 0) / total) * 100}%`,
        '--dim-cal-share-left': String(laid.column / laid.columns),
        '--dim-cal-share-width': String(1 / laid.columns),
    } as CSSProperties;
};

/**
 * The hour row an event's target is in, or null.
 *
 * @param {EventTarget} target The target.
 * @returns {HTMLElement|null}
 */
const rowOf = (target: EventTarget | null): HTMLElement | null =>
    (target instanceof Element ? target.closest<HTMLElement>('[data-hour]') : null);

/**
 * Whether a held touch has moved past the slop: then it is a scroll, not a hold.
 *
 * @param {object} hold The hold.
 * @param {number} x The pointer's x now.
 * @param {number} y The pointer's y now.
 * @returns {boolean}
 */
export const strayed = (hold: {x: number, y: number}, x: number, y: number): boolean =>
    Math.abs(x - hold.x) > HOLD_SLOP || Math.abs(y - hold.y) > HOLD_SLOP;

/**
 * One item as a link: its time, its name, its place, and the overlap label when it overlaps.
 *
 * @param {object} entry The placed item.
 * @param {string} when Its time as the drawer says it.
 * @param {boolean} overlapping Whether it overlaps another item of the day.
 * @param {object} look The page's look.
 * @param {string} extra More classes.
 * @param {object} style Its position, in the hour column.
 * @returns {object} The link.
 */
const link = (entry: Placed, when: string, overlapping: boolean, look: Look, extra: string, style: CSSProperties = {}) => {
    const place = placeOf(entry.item, look);
    return (
        <a
            key={itemKey(entry.item)}
            className={`dim-cal__dayitem ${keyClass(entry.item)} ${extra}${overlapping ? ' dim-cal__dayitem--overlap' : ''}`}
            href={itemHref(entry.item, look.courses, look.home)}
            style={{...colourStyle(entry.item, look), ...style}}
            data-cal-pop=""
            data-source={entry.item.source}
            data-id={entry.item.id}
        >
            <span className="dim-cal__daywhen">{when}</span>
            <span className="dim-cal__dayname">{entry.item.name}</span>
            {place !== '' ? <span className="dim-cal__dayplace">{place}</span> : null}
            {overlapping ? <span className="dim-cal__overlapmark">{look.strings.calendar_overlapmark}</span> : null}
        </a>
    );
};

/**
 * The day's body: the "nothing on this day" line, the all-day strip, then the hour column in its scroll box, or the
 * sheet's list (`list`) of the timed items.
 *
 * @param {object} props See DayBodyProps.
 * @returns {object} The body's parts.
 */
export const DayBody = ({look, iso, gridheight = 0, placed, list, onCreate}: DayBodyProps) => {
    const plan = useMemo(() => dayPlan(iso, placed, look.zone), [iso, placed, look.zone]);
    const labels = useMemo(() => hourLabels(look.language), [look.language]);
    const span = FULL_DAY;
    const busy = plan.hours.length > 0;
    const band = useMemo(() => (busy ? hourWindow(plan.hours, look.zone) : EMPTY_WINDOW), [busy, plan, look.zone]);
    const hold = useRef<Hold | null>(null);

    /**
     * Drop a held touch, its timer cleared.
     *
     * @returns {void}
     */
    const drop = (): void => {
        if (hold.current) {
            window.clearTimeout(hold.current.timer);
            hold.current = null;
        }
    };

    // A hold never outlives its day or the body.
    useEffect(() => drop, [iso]);

    /**
     * A double-click on an hour row (not on an item) opens core's form at that hour.
     *
     * @param {object} event The double-click.
     * @returns {void}
     */
    const onDoubleClick = (event: MouseEvent): void => {
        const row = event.target instanceof Element ? event.target.closest<HTMLElement>('[data-hour]') : null;
        if (onCreate && row) {
            event.preventDefault();
            onCreate(Number(row.dataset.hour));
        }
    };

    /**
     * A touch or a pen pressed on an hour row starts a hold; a mouse never does (it has the double-click).
     *
     * @param {object} event The press.
     * @returns {void}
     */
    const onPointerDown = (event: PointerEvent): void => {
        const row = rowOf(event.target);
        drop();
        if (!onCreate || !row || event.pointerType === 'mouse') {
            return;
        }
        const hour = Number(row.dataset.hour);
        hold.current = {
            x: event.clientX,
            y: event.clientY,
            timer: window.setTimeout(() => {
                hold.current = null;
                onCreate(hour);
            }, HOLD_MS),
        };
    };

    /**
     * A held touch that moves past the slop is a scroll: the hold is dropped.
     *
     * @param {object} event The move.
     * @returns {void}
     */
    const onPointerMove = (event: PointerEvent): void => {
        if (hold.current && strayed(hold.current, event.clientX, event.clientY)) {
            drop();
        }
    };

    const empty = !plan.allday.length && !plan.hours.length;
    /**
     * What the all-day strip says of an item's time: its span when it covers several days, else "All day".
     *
     * @param {object} entry The placed item.
     * @returns {string}
     */
    const allday = (entry: Placed): string => {
        if (entry.first === entry.last) {
            return look.strings.calendar_allday;
        }
        return `${say(entry.first, SPAN_DAY, look.language)}–${say(entry.last, SPAN_DAY, look.language)}`;
    };

    return (
        <>
            {empty ? <p className="dim-cal__dayempty">{look.strings.calendar_dayempty}</p> : null}
            {plan.allday.length ? (
                <div className="dim-cal__allday" data-region="dim-cal-allday">
                    {plan.allday.map((entry) => link(entry, allday(entry), false, look, 'dim-cal__dayitem--allday'))}
                </div>
            ) : null}
            {!list && (busy || onCreate) ? (
                <HourScroll
                    band={band}
                    busy={busy}
                    opening={`${iso}:${band.from}-${band.to}:${busy ? 'busy' : 'empty'}`}
                    height={gridheight}
                    label={look.strings.calendar_hoursbox}
                    focusable={!busy}
                    role={busy ? undefined : 'group'}
                    variant="day"
                >
                    <div
                        className="dim-cal__hours"
                        data-region="dim-cal-hours"
                        style={rows(span)}
                        onDoubleClick={onCreate ? onDoubleClick : undefined}
                        onPointerDown={onCreate ? onPointerDown : undefined}
                        onPointerMove={onCreate ? onPointerMove : undefined}
                        onPointerUp={onCreate ? drop : undefined}
                        onPointerCancel={onCreate ? drop : undefined}
                        onPointerLeave={onCreate ? drop : undefined}
                    >
                        {labels.slice(span.from, span.to).map((label, hour) => (
                            <span
                                key={span.from + hour}
                                className="dim-cal__hour"
                                data-hour={span.from + hour}
                                aria-hidden="true"
                            >{label}</span>
                        ))}
                        {plan.hours.map((laid) => link(laid.entry, whenOf(laid.entry.item, look), laid.overlaps.length > 0,
                            look, laid.entry.item.timeduration > 0 ? 'dim-cal__dayitem--timed' : 'dim-cal__dayitem--point',
                            position(laid, look, span)))}
                    </div>
                </HourScroll>
            ) : null}
            {list && plan.hours.length ? (
                <div className="dim-cal__daylist" data-region="dim-cal-hours">
                    {plan.hours.map((laid) => link(laid.entry, whenOf(laid.entry.item, look), laid.overlaps.length > 0, look,
                        'dim-cal__dayitem--line'))}
                </div>
            ) : null}
        </>
    );
};

/**
 * The drawer, or the sheet.
 *
 * @param {object} props See DayDrawerProps.
 * @returns {object} The region.
 */
const DayDrawer = ({look, iso, gridheight = 0, placed, variant, focusing, action, onCreate, onClose}: DayDrawerProps) => {
    const heading = useRef<HTMLHeadingElement | null>(null);

    useLayoutEffect(() => {
        if (focusing) {
            heading.current?.focus();
        }
    }, [focusing]);

    /**
     * Escape inside the region closes it.
     *
     * @param {object} event The key press.
     * @returns {void}
     */
    const onKeyDown = (event: KeyboardEvent): void => {
        if (event.key === 'Escape' && !event.defaultPrevented) {
            event.preventDefault();
            onClose();
        }
    };

    return (
        <section
            className={`dim-cal__drawer dim-cal__drawer--${variant}`}
            role="region"
            aria-labelledby={HEADING}
            data-region="dim-cal-day"
            data-day={iso}
            onKeyDown={onKeyDown}
        >
            <div className="dim-cal__drawerhead">
                <h2 ref={heading} className="dim-cal__drawertitle" id={HEADING} tabIndex={-1} aria-live="polite">
                    {say(iso, LONG_DAY, look.language)}
                </h2>
                {action}
                <button
                    type="button"
                    className="btn btn-icon dim-cal__close"
                    aria-label={look.strings.calendar_close}
                    data-action="dim-cal-day-close"
                    onClick={onClose}
                >
                    <i className="fa fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <DayBody
                look={look}
                iso={iso}
                gridheight={gridheight}
                placed={placed}
                list={variant === 'sheet'}
                onCreate={onCreate}
            />
        </section>
    );
};

export default DayDrawer;
