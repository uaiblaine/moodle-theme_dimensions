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
 * The Trilhas view's horizontal scroller, drawn as block_compass's calendar
 * strip is: one native scroller with its scrollbar hidden, its overscroll kept from the page, snapped by the day; round
 * arrows over the ends of the days' header, a fade over a side it can still scroll to, and under the grid the map, a 2px
 * line of the days held whose thumb is the days in view and whose tick is today.
 *
 * - The grid inside it holds its trail column still (position: sticky), so only the days move; their widths are
 *   trails.ts TRACK, and every position is trails.ts arithmetic over the scroller's three numbers.
 * - The layout reads are the scroller's: once per opening (the `opening` key: a new set of months) to bring today into
 *   view (trails.ts openingLeft()) and paint the thumb, never on another render; on a scroll, to paint the thumb and
 *   the arrows and tell the view which days are in view; on an arrow or a target the view asks for, where it is; on the
 *   map, the pointer's place along it. The thumb follows the scroll by its style, without a render; the arrows and fades
 *   re-render only when one of them changes. Every position is written through one place ({@see place}).
 * - A month added before the first day held moves the content right by its days: the `shift` key moves the position
 *   by the same width (trails.ts keptLeft(), over the last measure: no further read), so the view stays still.
 * - A scroll settles on `scrollend`, or a quiet after its last event where the browser has no such event: the view is
 *   told the first day in view (its month is the address's), and a target asked for is reached.
 * - The map is aria-hidden and out of the tab order: the bars and the courses' rows are its Tab stops (the browser
 *   scrolls a focused bar into view, past the still column by the scroller's scroll padding), and the arrows are the
 *   named single-pointer way (WCAG 2.5.7). A press on the line jumps there, the thumb drags; snap is off while it does.
 *
 * @module     theme_dimensions/calendar/TrailScroll
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useLayoutEffect, useRef, useState} from 'react';
import type {MutableRefObject, PointerEvent, ReactNode} from 'react';
import type {Strings} from './strings';
import {keptLeft, nearEnd, openingLeft, pageLeft, scrubLeft, shiftedTarget, shownDays, targetLeft, trackOf} from './trails';
import type {ScrollTarget, Track} from './trails';

/** How long after a scroll's last event it is taken as settled where the browser sends no `scrollend`, in ms. */
export const SETTLE_MS = 150;

/** How long a target asked for may take to be reached before it is taken as reached, in ms. */
export const ARRIVE_MS = 700;

/** Which side of what is held the days in view have reached: the start (scrolling back to it), the end, or neither. */
export type Near = 'start' | 'end' | '';

/** A move of the first day held: its key changes once per move, by `days` days back (negative: forward). */
export type Shift = {key: number, days: number};

/**
 * What the view may ask of the scroller: to go to a target, smoothly unless the reader asked for less motion. The
 * promise says whether the target was reached (true) or superseded by another one, or the scroller went (false).
 */
export type ScrollControl = {go: (target: ScrollTarget) => Promise<boolean>};

/** What the scroller is handed. */
type TrailScrollProps = {
    days: number,
    todayat: number,
    openat: number,
    opening: string,
    shift: Shift,
    control: MutableRefObject<ScrollControl | null>,
    onView: (first: number, last: number, near: Near) => void,
    onSettle: (first: number) => void,
    strings: Strings,
    children: ReactNode,
};

/** Which ways the scroller can still go, and whether it scrolls at all. */
type Ways = {back: boolean, ahead: boolean, scrolls: boolean};

/** A target on its way: where it is, what settles it, and the fallback that settles it anyway. */
type Arrival = {target: ScrollTarget, resolve: (reached: boolean) => void, timer: ReturnType<typeof setTimeout> | null};

/**
 * A fraction as a percentage for a style.
 *
 * @param {number} fraction The fraction.
 * @returns {string}
 */
const percent = (fraction: number): string => `${(fraction * 100).toFixed(3)}%`;

/**
 * Whether the reader asked for less motion; false where the browser cannot say.
 *
 * @returns {boolean}
 */
export const still = (): boolean => typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Put the scroller at a position: animated when asked and the reader did not ask for less motion, at once otherwise.
 * The one place every position is written.
 *
 * @param {object} element The scroller.
 * @param {number} left The position.
 * @param {boolean} smooth Whether to animate.
 * @returns {void}
 */
const place = (element: HTMLDivElement, left: number, smooth: boolean): void => {
    if (typeof element.scrollTo === 'function') {
        element.scrollTo({left: left, behavior: smooth && !still() ? 'smooth' : 'auto'});
    } else {
        element.scrollLeft = left;
    }
};

/**
 * The scroller.
 *
 * @param {object} props See TrailScrollProps.
 * @returns {object} The card's scroller with its arrows and fades, and the map under it.
 */
const TrailScroll = ({days, todayat, openat, opening, shift, control, onView, onSettle, strings, children}: TrailScrollProps) => {
    const box = useRef<HTMLDivElement | null>(null);
    const thumb = useRef<HTMLSpanElement | null>(null);
    const drag = useRef<{grab: number, span: number} | null>(null);
    const last = useRef<Track | null>(null);
    const quiet = useRef<ReturnType<typeof setTimeout> | null>(null);
    const arriving = useRef<Arrival | null>(null);
    const reports = useRef({onView: onView, onSettle: onSettle, days: days});
    reports.current = {onView: onView, onSettle: onSettle, days: days};
    const [ways, setWays] = useState<Ways>({back: false, ahead: false, scrolls: false});
    const [dragging, setDragging] = useState<boolean>(false);

    /**
     * The scroller's geometry where it is now.
     *
     * @param {object} element The scroller.
     * @returns {object} See trails.ts Track.
     */
    const measure = (element: HTMLDivElement): Track => trackOf(element.scrollLeft, element.scrollWidth, element.clientWidth, days);

    /**
     * Paint what follows the scroll: the thumb by its style, the arrows and the fades when one of them changes; then tell
     * the view which days are in view and whether they reached an end of what is held (the start only while moving
     * back to it).
     *
     * @param {object} track The scroller's geometry.
     * @param {number} moved How far it moved since the last measure, negative back.
     * @returns {void}
     */
    const paint = (track: Track, moved: number): void => {
        last.current = track;
        if (thumb.current) {
            thumb.current.style.left = percent(track.start);
            thumb.current.style.width = percent(track.span);
        }
        const next = {back: track.back, ahead: track.ahead, scrolls: track.max > 0};
        setWays((now) => (now.back === next.back && now.ahead === next.ahead && now.scrolls === next.scrolls ? now : next));
        const shown = shownDays(track, reports.current.days);
        let near: Near = '';
        if (nearEnd(track, reports.current.days)) {
            near = 'end';
        } else if (moved < 0 && !track.back) {
            near = 'start';
        }
        reports.current.onView(shown.first, shown.last, near);
    };

    /**
     * The target on its way is settled: reached, or superseded by another (or the scroller gone); its fallback goes.
     *
     * @param {boolean} reached Whether it was reached.
     * @returns {void}
     */
    const arrive = (reached: boolean): void => {
        const arrival = arriving.current;
        arriving.current = null;
        if (arrival) {
            if (arrival.timer !== null) {
                clearTimeout(arrival.timer);
            }
            arrival.resolve(reached);
        }
    };

    /**
     * (Re)start the fallback of the target on its way: it settles this arrival only, and a scroll still under way puts
     * it off again ({@see scrolled}), so a long animation is not cut short.
     *
     * @returns {void}
     */
    const wait = (): void => {
        const arrival = arriving.current;
        if (!arrival) {
            return;
        }
        if (arrival.timer !== null) {
            clearTimeout(arrival.timer);
        }
        arrival.timer = setTimeout(() => {
            if (arriving.current === arrival) {
                arrive(true);
            }
        }, ARRIVE_MS);
    };

    /**
     * Tell the view the first day in view, where the scroll came to rest.
     *
     * @returns {void}
     */
    const tell = (): void => {
        if (last.current) {
            reports.current.onSettle(shownDays(last.current, reports.current.days).first);
        }
    };

    /**
     * The scroll settled: the view is told the first day in view, and a target asked for is reached.
     *
     * @returns {void}
     */
    const settle = (): void => {
        if (quiet.current !== null) {
            clearTimeout(quiet.current);
            quiet.current = null;
        }
        tell();
        arrive(true);
    };

    // Once per opening: today into view when the month opened on holds it, else that month's 1st, then the thumb, the
    // arrows and the days in view where that left them.
    useLayoutEffect(() => {
        const element = box.current;
        if (!element) {
            return;
        }
        place(element, openingLeft(openat, trackOf(0, element.scrollWidth, element.clientWidth, days)), false);
        paint(measure(element), 0);
        tell();
        // A new set of months: a target asked for over the old one is not reached.
        arrive(false);
    }, [opening]);

    // A month added before the first day held, or one dropped from the start: the position moves by its days. A target
    // on its way moved with the days: it is aimed at again from there, so the animation the move cut goes on to it.
    useLayoutEffect(() => {
        const element = box.current;
        const was = last.current;
        if (!element || !was || shift.days === 0) {
            return;
        }
        place(element, keptLeft(was.left, shift.days, was.pitch), false);
        last.current = {...was, left: keptLeft(was.left, shift.days, was.pitch)};
        const arrival = arriving.current;
        if (arrival) {
            arrival.target = shiftedTarget(arrival.target, shift.days);
            place(element, targetLeft(arrival.target, measure(element)), true);
            wait();
        }
    }, [shift.key]);

    // The browser's own end of a scroll, where it has one; the quiet after the last scroll event stands in otherwise.
    useEffect(() => {
        const element = box.current;
        if (!element) {
            return undefined;
        }
        element.addEventListener('scrollend', settle);
        return () => {
            element.removeEventListener('scrollend', settle);
            if (quiet.current !== null) {
                clearTimeout(quiet.current);
            }
            arrive(false);
        };
    }, []);

    /**
     * A scroll: paint, and settle after a quiet.
     *
     * @param {object} element The scroller.
     * @returns {void}
     */
    const scrolled = (element: HTMLDivElement): void => {
        const track = measure(element);
        paint(track, last.current ? track.left - last.current.left : 0);
        if (quiet.current !== null) {
            clearTimeout(quiet.current);
        }
        quiet.current = setTimeout(settle, SETTLE_MS);
        wait();
    };

    /**
     * Go to a target the view asks for: animated unless the reader asked for less motion; settled once the scroll ends,
     * at once when it is there already or moves at once. A target still on its way is superseded: its promise says so.
     *
     * @param {object} target The target.
     * @returns {Promise} True once the target is reached; false when another target or the scroller's end came first.
     */
    const go = (target: ScrollTarget): Promise<boolean> => {
        const element = box.current;
        arrive(false);
        if (!element) {
            return Promise.resolve(false);
        }
        const track = measure(element);
        const left = targetLeft(target, track);
        if (Math.abs(left - track.left) < 1 || still()) {
            place(element, left, false);
            return Promise.resolve(true);
        }
        return new Promise((resolve) => {
            arriving.current = {target: target, resolve: resolve, timer: null};
            wait();
            place(element, left, true);
        });
    };
    control.current = {go: go};

    /**
     * An arrow: a page of days back or ahead, smoothly unless the reader asked for less motion.
     *
     * @param {number} direction -1 back, 1 ahead.
     * @returns {void}
     */
    const page = (direction: number): void => {
        const element = box.current;
        if (!element) {
            return;
        }
        const track = measure(element);
        if ((direction < 0 && !track.back) || (direction > 0 && !track.ahead)) {
            return;
        }
        place(element, pageLeft(track.left, direction, track), true);
    };

    /**
     * Where the pointer is along the map, 0 to 1.
     *
     * @param {object} event The pointer event.
     * @returns {number}
     */
    const mapAt = (event: PointerEvent<HTMLDivElement>): number => {
        const line = event.currentTarget.getBoundingClientRect();
        return line.width > 0 ? Math.max(0, Math.min(1, (event.clientX - line.left) / line.width)) : 0;
    };

    /**
     * Move the days with the map: the scroll follows the pointer at once.
     *
     * @param {number} at Where the pointer is along the map, 0 to 1.
     * @returns {void}
     */
    const scrubTo = (at: number): void => {
        const element = box.current;
        const grip = drag.current;
        if (!element || !grip) {
            return;
        }
        place(element, scrubLeft(at, grip.grab, measure(element), days), false);
    };

    /**
     * A press on the map: on the thumb it grabs it where pressed; on the line it centres the thumb on the pointer and
     * the days follow at once.
     *
     * @param {object} event The pointer event.
     * @returns {void}
     */
    const mapDown = (event: PointerEvent<HTMLDivElement>): void => {
        const element = box.current;
        if (!element || (event.pointerType === 'mouse' && event.button !== 0)) {
            return;
        }
        const at = mapAt(event);
        const track = measure(element);
        const onthumb = at >= track.start - 0.01 && at <= track.start + track.span + 0.01;
        drag.current = {grab: onthumb ? at - track.start : track.span / 2, span: track.span};
        try {
            event.currentTarget.setPointerCapture(event.pointerId);
        } catch (error) {
            // A synthetic pointer has no capture; the drag still follows the moves over the map.
        }
        setDragging(true);
        if (!onthumb) {
            scrubTo(at);
        }
        event.preventDefault();
    };

    /**
     * The drag ends: snap comes back.
     *
     * @returns {void}
     */
    const mapUp = (): void => {
        if (drag.current !== null) {
            drag.current = null;
            setDragging(false);
        }
    };

    return (
        <div className="dim-cal__trailframe">
            <div className="dim-cal__trailcard">
                <div
                    ref={box}
                    className={`dim-cal__trailscroll${dragging ? ' dim-cal__trailscroll--free' : ''}`}
                    data-region="dim-cal-trailscroll"
                    onScroll={(event) => scrolled(event.currentTarget)}
                >
                    {children}
                </div>
                {ways.back ? <span className="dim-cal__trailfade dim-cal__trailfade--back" aria-hidden="true"></span> : null}
                {ways.ahead ? <span className="dim-cal__trailfade dim-cal__trailfade--ahead" aria-hidden="true"></span> : null}
                {ways.scrolls ? (
                    <button
                        type="button"
                        className="dim-cal__trailedge dim-cal__trailedge--back"
                        data-action="dim-cal-trail-back"
                        aria-label={strings.calendar_trailearlier}
                        aria-disabled={ways.back ? undefined : 'true'}
                        onClick={() => page(-1)}
                    >
                        <i className="fa fa-chevron-left" aria-hidden="true"></i>
                    </button>
                ) : null}
                {ways.scrolls ? (
                    <button
                        type="button"
                        className="dim-cal__trailedge dim-cal__trailedge--ahead"
                        data-action="dim-cal-trail-ahead"
                        aria-label={strings.calendar_traillater}
                        aria-disabled={ways.ahead ? undefined : 'true'}
                        onClick={() => page(1)}
                    >
                        <i className="fa fa-chevron-right" aria-hidden="true"></i>
                    </button>
                ) : null}
            </div>
            <div
                className={'dim-cal__trailmap' + (dragging ? ' dim-cal__trailmap--drag' : '')
                    + (ways.scrolls ? '' : ' dim-cal__trailmap--whole')}
                data-region="dim-cal-trailmap"
                aria-hidden="true"
                onPointerDown={mapDown}
                onPointerMove={(event) => {
                    if (drag.current !== null) {
                        scrubTo(mapAt(event));
                    }
                }}
                onPointerUp={mapUp}
                onPointerCancel={mapUp}
            >
                <span className="dim-cal__trailmapline">
                    <span ref={thumb} className="dim-cal__trailthumb" data-region="dim-cal-trailthumb"></span>
                    {todayat >= 0 ? (
                        <span
                            className="dim-cal__trailmaptoday"
                            style={{left: percent((todayat + 0.5) / Math.max(1, days))}}
                        ></span>
                    ) : null}
                </span>
            </div>
        </div>
    );
};

export default TrailScroll;
