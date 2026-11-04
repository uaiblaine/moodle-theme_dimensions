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
 * The Trilhas view's horizontal scroller (docs/calendar/PROPOSAL.md section 33), drawn as block_compass's calendar
 * strip is: one native scroller with its scrollbar hidden, its overscroll kept from the page, snapped by the day; round
 * arrows over the ends of the days' header, a fade over a side it can still scroll to, and under the grid the map, a 2px
 * line of the whole month whose thumb is the days in view and whose tick is today.
 *
 * - The grid inside it holds its trail column and its "next month" column still (position: sticky), so only the days
 *   move; their widths are trails.ts TRACK, and every position is trails.ts arithmetic over the scroller's three numbers.
 * - The layout reads are the scroller's: once per opening (the `opening` key: the month and its trails) to bring today
 *   into view (trails.ts openingLeft()) and paint the thumb, never on another render; on a scroll, to paint the thumb
 *   and the arrows; on an arrow, a page of days; on the map, the pointer's place along it. The thumb follows the scroll
 *   by its style, without a render; the arrows and fades re-render only when one of them changes.
 * - The map is aria-hidden and out of the tab order: the bars inside the scroller are its Tab stops (the browser scrolls
 *   a focused bar into view, past the still columns by the scroller's scroll padding), and the arrows are the named
 *   single-pointer way (WCAG 2.5.7). A press on the line jumps there, the thumb drags; snap is off while it does.
 *
 * @module     theme_dimensions/calendar/TrailScroll
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useLayoutEffect, useRef, useState} from 'react';
import type {PointerEvent, ReactNode} from 'react';
import type {Strings} from './strings';
import {openingLeft, pageLeft, scrubLeft, trackOf} from './trails';
import type {Track} from './trails';

/** What the scroller is handed. */
type TrailScrollProps = {
    days: number,
    todayat: number,
    opening: string,
    strings: Strings,
    children: ReactNode,
};

/** Which ways the scroller can still go, and whether it scrolls at all. */
type Ways = {back: boolean, ahead: boolean, scrolls: boolean};

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
const still = (): boolean => typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * The scroller.
 *
 * @param {object} props See TrailScrollProps.
 * @returns {object} The card's scroller with its arrows and fades, and the map under it.
 */
const TrailScroll = ({days, todayat, opening, strings, children}: TrailScrollProps) => {
    const box = useRef<HTMLDivElement | null>(null);
    const thumb = useRef<HTMLSpanElement | null>(null);
    const drag = useRef<{grab: number, span: number} | null>(null);
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
     * Paint what follows the scroll: the thumb by its style, the arrows and the fades when one of them changes.
     *
     * @param {object} track The scroller's geometry.
     * @returns {void}
     */
    const paint = (track: Track): void => {
        if (thumb.current) {
            thumb.current.style.left = percent(track.start);
            thumb.current.style.width = percent(track.span);
        }
        const next = {back: track.back, ahead: track.ahead, scrolls: track.max > 0};
        setWays((now) => (now.back === next.back && now.ahead === next.ahead && now.scrolls === next.scrolls ? now : next));
    };

    // Once per opening: today into view, then the thumb and the arrows where that left them.
    useLayoutEffect(() => {
        const element = box.current;
        if (!element) {
            return;
        }
        element.scrollLeft = openingLeft(todayat, trackOf(0, element.scrollWidth, element.clientWidth, days));
        paint(measure(element));
    }, [opening]);

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
        const left = pageLeft(element.scrollLeft, direction, track);
        if (typeof element.scrollTo === 'function') {
            element.scrollTo({left: left, behavior: still() ? 'auto' : 'smooth'});
        } else {
            element.scrollLeft = left;
        }
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
        element.scrollLeft = scrubLeft(at, grip.grab, measure(element), days);
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
                    onScroll={(event) => paint(measure(event.currentTarget))}
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
