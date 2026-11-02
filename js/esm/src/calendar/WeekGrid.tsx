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
 * The week: a time grid on a desktop, a seven-day agenda on a phone (docs/calendar/PROPOSAL.md sections 21.2, 21.3).
 *
 * The grid is role="grid" labelled by the caption, in three rows: the day headers (seven columnheaders, each holding
 * the day's button), the all-day strip (a rowheader, then seven gridcells holding the bars that start on each day, laid
 * out by layout.ts weekLayout() so a bar spans its columns) and the hour body (a rowheader, then a gridcell per day
 * with its timed items in the drawer's columns, over the week's hour window: no scroll box of its own). The layout is
 * weeks.ts weekPlan(), pure.
 *
 * - One Tab stop, the selected day's header (roving tabindex). On a header the arrows move a day (past the week's ends
 *   the shell fetches the adjacent week), Home and End go to the week's ends, PageUp and PageDown a week; Enter opens
 *   the day (the shell's drawer); ArrowDown or F2 enters the day's items. On an item ArrowUp and ArrowDown walk the
 *   day's items, ArrowLeft and ArrowRight the neighbouring day's nearest in time, Escape goes back to the header, and
 *   Enter or Space is left to the shell's delegated listener, which opens the popover (weeks.ts moveInWeek()).
 * - Every item is a link to its own page marked data-cal-pop, as the month's are, out of the Tab order.
 * - The "now" line marks today's column at the server's time plus the minutes since the paint (the browser's clock is
 *   read only as time elapsed), aria-hidden.
 * - ONE ResizeObserver, on the frame the week shares with the drawer, never read on the mount path: below
 *   MonthGrid.PHONE the shell draws the agenda instead, seven sections whose header opens the day's sheet.
 *
 * @module     theme_dimensions/calendar/WeekGrid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useLayoutEffect, useMemo, useRef, useState} from 'react';
import type {CSSProperties, KeyboardEvent, ReactNode} from 'react';
import {say} from '../daterange/calendar';
import Bar from './Bar';
import {dayLabel} from './DayCell';
import {dayCounts} from './layout';
import type {Placed} from './layout';
import {colourStyle, keyClass, whenOf} from './look';
import type {Look} from './look';
import {itemHref} from './model';
import {itemKey} from './overlap';
import type {Laid} from './overlap';
import {hourBox, moveInWeek, nowLine, stepWeekFrom, weekPlan, weekStops} from './weeks';
import type {Cursor} from './weeks';

/** How often the "now" line moves, in ms. */
export const TICK = 60000;

/** What the week is handed. */
type WeekGridProps = {
    look: Look,
    days: string[],
    placed: Placed[],
    today: string,
    now: number,
    selected: string,
    focusing: number,
    captionid: string,
    phone: boolean,
    onSelect: (iso: string) => void,
    onWeek: (iso: string) => void,
    onOpen: (iso: string) => void,
    onWidth: (width: number) => void,
};

/** The weekday's name in a header. */
const WEEKDAY: Intl.DateTimeFormatOptions = {weekday: 'short'};

/** The long date of an agenda day. */
const LONG_DAY: Intl.DateTimeFormatOptions = {weekday: 'long', day: 'numeric', month: 'long'};

/** The day and month of a multi-day item's ends. */
const SPAN_DAY: Intl.DateTimeFormatOptions = {day: 'numeric', month: 'short'};

/**
 * The hour labels, as the language writes an hour.
 *
 * @param {string|undefined} language The language tag.
 * @returns {Array} 24 labels.
 */
const hourLabels = (language: string | undefined): string[] => {
    const format = new Intl.DateTimeFormat(language, {hour: '2-digit', minute: '2-digit', timeZone: 'UTC'});
    return Array.from({length: 24}, (unused, hour) => format.format(new Date(Date.UTC(1970, 0, 1, hour))));
};

/**
 * What the agenda and the strip say of a whole-day item's time: "All day", or its span.
 *
 * @param {object} entry The placed item.
 * @param {object} look The page's look.
 * @returns {string}
 */
const alldayWhen = (entry: Placed, look: Look): string => (entry.first === entry.last
    ? look.strings.calendar_allday
    : `${say(entry.first, SPAN_DAY, look.language)}–${say(entry.last, SPAN_DAY, look.language)}`);

/**
 * The week.
 *
 * @param {object} props See WeekGridProps.
 * @returns {object} The grid, or the agenda.
 */
const WeekGrid = (props: WeekGridProps) => {
    const {look, days, placed, today, now, selected, focusing, captionid, phone, onSelect, onWeek, onOpen} = props;
    const [elapsed, setElapsed] = useState<number>(0);
    const root = useRef<HTMLDivElement | null>(null);
    const cursor = useRef<Cursor | null>(null);
    const width = useRef<(width: number) => void>(props.onWidth);
    width.current = props.onWidth;

    const plan = useMemo(() => weekPlan(days, placed, look.zone), [days, placed, look.zone]);
    const stops = useMemo(() => weekStops(plan, look.zone), [plan, look.zone]);
    const counts = useMemo(() => dayCounts(days, placed), [days, placed]);
    const labels = useMemo(() => hourLabels(look.language), [look.language]);
    const line = nowLine(days, (Number(now) || 0) + elapsed, look.zone, plan.window);

    useEffect(() => {
        const frame = root.current?.parentElement || null;
        if (!frame || typeof ResizeObserver === 'undefined') {
            return undefined;
        }
        const observer = new ResizeObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.contentRect.width > 0) {
                    width.current(entry.contentRect.width);
                }
            });
        });
        observer.observe(frame);
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        const started = Date.now();
        const timer = window.setInterval(() => setElapsed(Math.floor((Date.now() - started) / 1000)), TICK);
        return () => window.clearInterval(timer);
    }, []);

    useLayoutEffect(() => {
        if (focusing) {
            root.current?.querySelector<HTMLElement>(`[data-role="day"][data-day="${selected}"]`)?.focus();
        }
    }, [focusing]);

    /**
     * The element of an item of the grid, by its key.
     *
     * @param {string} key The item's key.
     * @returns {HTMLElement|null}
     */
    const itemElement = (key: string): HTMLElement | null => {
        const [source, id] = [key.slice(0, key.lastIndexOf('-')), key.slice(key.lastIndexOf('-') + 1)];
        return root.current?.querySelector<HTMLElement>(
            `[data-region="dim-cal-weekgrid"] [data-cal-pop][data-source="${source}"][data-id="${id}"]`
        ) || null;
    };

    /**
     * Move the keyboard: to a day header (which selects the day) or to an item.
     *
     * @param {object} next Where to.
     * @returns {void}
     */
    const go = (next: Cursor): void => {
        if (next.index < 0) {
            cursor.current = null;
            onSelect(days[next.column]);
            return;
        }
        cursor.current = next;
        itemElement(stops[next.column][next.index].key)?.focus();
    };

    /**
     * The grid's keyboard (section 21.3).
     *
     * @param {object} event The key press.
     * @returns {void}
     */
    const onKeyDown = (event: KeyboardEvent): void => {
        const target = event.target as HTMLElement;
        if (event.defaultPrevented) {
            return;
        }
        if (target.dataset.role === 'day') {
            const iso = target.dataset.day || selected;
            const into = moveInWeek(event.key, {column: days.indexOf(iso), index: -1}, stops);
            if (into) {
                event.preventDefault();
                go(into);
                return;
            }
            const day = stepWeekFrom(event.key, iso, days);
            if (!day) {
                return;
            }
            event.preventDefault();
            if (days.includes(day)) {
                onSelect(day);
            } else {
                onWeek(day);
            }
            return;
        }
        // Enter and Space move nothing here: they reach the shell's delegated listener, which opens the popover.
        const element = target.closest<HTMLElement>('[data-cal-pop]');
        if (!element) {
            return;
        }
        const key = `${element.dataset.source || ''}-${element.dataset.id || ''}`;
        let at = cursor.current;
        if (!at || stops[at.column]?.[at.index]?.key !== key) {
            const column = Math.max(0, days.indexOf(element.dataset.day || element.dataset.first || ''));
            at = {column: column, index: stops[column].findIndex((stop) => stop.key === key)};
        }
        const next = at.index < 0 ? null : moveInWeek(event.key, at, stops);
        if (!next) {
            return;
        }
        event.preventDefault();
        go(next);
    };

    /**
     * A day's header button, in the grid or the agenda.
     *
     * @param {string} iso The day.
     * @param {boolean} roving Whether the Tab stop roves (the grid) or every day is a stop (the agenda).
     * @param {object} content What it shows.
     * @returns {object} The button.
     */
    const header = (iso: string, roving: boolean, content: ReactNode) => (
        <button
            type="button"
            className={`dim-cal__weekhead${iso === today ? ' dim-cal__weekhead--today' : ''}`}
            tabIndex={roving && iso !== selected ? -1 : 0}
            aria-label={dayLabel(look, iso, counts.get(iso) || 0, iso === today)}
            aria-current={iso === today ? 'date' : undefined}
            data-role="day"
            data-day={iso}
            onClick={() => onOpen(iso)}
        >{content}</button>
    );

    /**
     * One timed item of the hour body, in its column and its share of the window.
     *
     * @param {object} laid The laid item.
     * @param {string} iso Its day.
     * @returns {object} The link.
     */
    const timed = (laid: Laid, iso: string) => {
        const item = laid.entry.item;
        const box = plan.window ? hourBox(laid, look.zone, plan.window) : {top: 0, height: 0, left: 0, width: 1};
        const style = {
            ...colourStyle(item, look),
            '--dim-cal-top': `${box.top * 100}%`,
            '--dim-cal-height': `${box.height * 100}%`,
            '--dim-cal-share-left': String(box.left),
            '--dim-cal-share-width': String(box.width),
        } as CSSProperties;
        const overlapping = laid.overlaps.length > 0;
        return (
            <a
                key={itemKey(item)}
                className={`dim-cal__weekitem ${keyClass(item)}${item.timeduration > 0 ? '' : ' dim-cal__weekitem--point'}`
                    + `${overlapping ? ' dim-cal__dayitem--overlap' : ''}`}
                href={itemHref(item, look.courses, look.home)}
                style={style}
                tabIndex={-1}
                data-cal-pop=""
                data-source={item.source}
                data-id={item.id}
                data-day={iso}
                data-column={laid.column}
                data-columns={laid.columns}
            >
                {overlapping ? <span className="dim-cal__overlapmark">{look.strings.calendar_overlapmark}</span> : null}
                <span className="dim-cal__weekname">{item.name}</span>
                <span className="dim-cal__weekwhen">{whenOf(item, look)}</span>
            </a>
        );
    };

    /**
     * One item of the phone's agenda: its time, its name, and the overlap label when it overlaps.
     *
     * @param {object} entry The placed item.
     * @param {string} when Its time as the agenda says it.
     * @param {boolean} overlapping Whether it overlaps another item of the day.
     * @returns {object} The list item.
     */
    const agendaItem = (entry: Placed, when: string, overlapping: boolean) => (
        <li key={itemKey(entry.item)}>
            <a
                className={`dim-cal__dayitem dim-cal__dayitem--line ${keyClass(entry.item)}`
                    + `${overlapping ? ' dim-cal__dayitem--overlap' : ''}`}
                href={itemHref(entry.item, look.courses, look.home)}
                style={colourStyle(entry.item, look)}
                data-cal-pop=""
                data-source={entry.item.source}
                data-id={entry.item.id}
            >
                <span className="dim-cal__daywhen">{when}</span>
                <span className="dim-cal__dayname">{entry.item.name}</span>
                {overlapping ? <span className="dim-cal__overlapmark">{look.strings.calendar_overlapmark}</span> : null}
            </a>
        </li>
    );

    /**
     * One day of the phone's agenda: its header, then the strip's items covering it and its timed items by start.
     *
     * @param {string} iso The day.
     * @param {number} column Its column in the week.
     * @returns {object} The section.
     */
    const agendaDay = (iso: string, column: number) => {
        const strip = plan.strip.bars.filter((segment) => segment.start <= column && column <= segment.end)
            .sort((a, b) => a.slot - b.slot);
        const empty = !strip.length && !plan.columns[column].length;
        return (
            <section key={iso} className="dim-cal__weekagendaday" data-day={iso}>
                <h3 className="dim-cal__weekagendatitle">
                    {header(iso, false, <span>{say(iso, LONG_DAY, look.language)}</span>)}
                </h3>
                {empty ? <p className="dim-cal__dayempty">{look.strings.calendar_dayempty}</p> : (
                    <ul className="dim-cal__weekagendalist">
                        {strip.map((segment) => agendaItem(segment.placed, alldayWhen(segment.placed, look), false))}
                        {plan.columns[column].map((laid) => agendaItem(laid.entry, whenOf(laid.entry.item, look),
                            laid.overlaps.length > 0))}
                    </ul>
                )}
            </section>
        );
    };

    if (phone) {
        return (
            <div ref={root} className="dim-cal__weekview" data-region="dim-cal-week">
                <div className="dim-cal__weekagenda" data-region="dim-cal-weekagenda" aria-labelledby={captionid} role="group">
                    {days.map(agendaDay)}
                </div>
            </div>
        );
    }

    const used = plan.strip.used;
    return (
        <div ref={root} className="dim-cal__weekview" data-region="dim-cal-week">
            <div
                className="dim-cal__weekgrid"
                role="grid"
                aria-labelledby={captionid}
                data-region="dim-cal-weekgrid"
                onKeyDown={onKeyDown}
                onBlur={(event) => {
                    if (!root.current?.contains(event.relatedTarget as Node | null)) {
                        cursor.current = null;
                    }
                }}
            >
                <div className="dim-cal__weekrow dim-cal__weekheads" role="row">
                    <div className="dim-cal__weekgutter" role="presentation"></div>
                    {days.map((iso) => (
                        <div key={iso} className="dim-cal__weekheadcell" role="columnheader">
                            {header(iso, true, (
                                <>
                                    <span className="dim-cal__weekdayname" aria-hidden="true">
                                        {say(iso, WEEKDAY, look.language)}
                                    </span>
                                    <span className="dim-cal__weekdaynum" aria-hidden="true">{Number(iso.slice(8, 10))}</span>
                                </>
                            ))}
                        </div>
                    ))}
                </div>
                <div className="dim-cal__weekrow dim-cal__weekstrip" role="row" data-region="dim-cal-weekstrip">
                    <div className="dim-cal__weekgutter dim-cal__weekgutter--strip" role="rowheader">
                        {look.strings.calendar_allday}
                    </div>
                    <div
                        className="dim-cal__weekstripgrid"
                        role="presentation"
                        style={{gridTemplateRows: `0 repeat(${used}, 1.5rem)`}}
                    >
                        {days.map((iso, column) => (
                            <div
                                key={iso}
                                className="dim-cal__day"
                                role="gridcell"
                                data-day={iso}
                                data-cal-drop={look.movable ? iso : undefined}
                            >
                                <div
                                    className={`dim-cal__ground${iso === selected ? ' dim-cal__ground--selected' : ''}`}
                                    style={{gridColumn: column + 1, gridRow: '1 / -1'}}
                                    aria-hidden="true"
                                ></div>
                                {plan.strip.bars.filter((segment) => segment.start === column).map((segment) => (
                                    <Bar key={itemKey(segment.placed.item)} segment={segment} week={days} look={look} />
                                ))}
                            </div>
                        ))}
                    </div>
                </div>
                {plan.window ? (
                    <div
                        className="dim-cal__weekrow dim-cal__weekhours"
                        role="row"
                        data-region="dim-cal-weekhours"
                        style={{'--dim-cal-hourcount': String(plan.window.to - plan.window.from)} as CSSProperties}
                    >
                        <div className="dim-cal__weekgutter dim-cal__weekgutter--hours" role="rowheader">
                            <span className="visually-hidden">{look.strings.calendar_timed}</span>
                            {labels.slice(plan.window.from, plan.window.to).map((label, hour) => (
                                <span key={hour} className="dim-cal__hour" aria-hidden="true">{label}</span>
                            ))}
                        </div>
                        {days.map((iso, column) => (
                            <div
                                key={iso}
                                className={`dim-cal__weekcol${iso === selected ? ' dim-cal__weekcol--selected' : ''}`}
                                role="gridcell"
                                data-region="dim-cal-weekcol"
                                data-day={iso}
                            >
                                {line !== null && line.column === column ? (
                                    <span
                                        className="dim-cal__now"
                                        style={{'--dim-cal-top': `${line.share * 100}%`} as CSSProperties}
                                        aria-hidden="true"
                                        data-region="dim-cal-now"
                                    ></span>
                                ) : null}
                                {plan.columns[column].map((laid) => timed(laid, iso))}
                            </div>
                        ))}
                    </div>
                ) : null}
            </div>
            {!plan.window && !plan.strip.bars.length ? (
                <p className="dim-cal__dayempty" data-region="dim-cal-weekempty">{look.strings.calendar_weekempty}</p>
            ) : null}
        </div>
    );
};

export default WeekGrid;
