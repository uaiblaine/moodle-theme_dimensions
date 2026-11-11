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
 * The scroll box of an hour column, the drawer's and the week's.
 *
 * The column inside it is the whole day (overlap.ts FULL_DAY); the box starts where overlap.ts boxTopOf() puts it,
 * once per opening (the `opening` key: a day, or a week, and its band), from the box's one layout read, never on the
 * page's mount path, and again when the grid hands a new `height` (the drawer is capped to the grid's height only once
 * the grid's observer has reported, which shrinks the box after the first placing) until the reader's first wheel,
 * touch or key on the box; a new opening starts over. The look is block_compass's calendar strip: no native scrollbar,
 * its overscroll kept from the page, and a fade at the top or the bottom only while the box can still scroll that way
 * (overlap.ts edgesOf(), read on scroll), the fades taking no pointer and hidden from the screen reader. Handed
 * `focusable` (it holds no focusable item), the box is a Tab stop with the label it is handed, so the arrows and the
 * Page keys scroll it.
 *
 * @module     theme_dimensions/calendar/HourScroll
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useLayoutEffect, useRef, useState} from 'react';
import type {ReactNode, UIEvent} from 'react';
import {FULL_DAY, edgesOf, boxTopOf} from './overlap';
import type {Edges, HourWindow} from './overlap';

/** What the box is handed. */
type HourScrollProps = {
    band: HourWindow,
    busy: boolean,
    opening: string,
    height?: number,
    label: string,
    focusable: boolean,
    role?: string,
    variant: string,
    children: ReactNode,
};

/**
 * Whether two edge readings differ, so an unchanged scroll re-renders nothing.
 *
 * @param {object} a One reading.
 * @param {object} b Another.
 * @returns {boolean}
 */
const moved = (a: Edges, b: Edges): boolean => a.up !== b.up || a.down !== b.down;

/**
 * The box.
 *
 * @param {object} props See HourScrollProps.
 * @returns {object} The box, its fades beside it.
 */
const HourScroll = ({band, busy, opening, height = 0, label, focusable, role, variant, children}: HourScrollProps) => {
    const box = useRef<HTMLDivElement | null>(null);
    const [edges, setEdges] = useState<Edges>({up: false, down: false});

    // The opening the position was last set for, and whether the reader has taken the box since.
    const placed = useRef<string>('');
    const touched = useRef<boolean>(false);

    useLayoutEffect(() => {
        const element = box.current;
        if (!element) {
            return;
        }
        if (placed.current !== opening) {
            placed.current = opening;
            touched.current = false;
        } else if (touched.current) {
            return;
        }
        const hours = FULL_DAY.to - FULL_DAY.from;
        element.scrollTop = boxTopOf(band, busy, element.scrollHeight / hours, element.clientHeight);
        setEdges(edgesOf(element.scrollTop, element.scrollHeight, element.clientHeight));
    }, [opening, height]);

    /**
     * The reader takes the box (wheel, touch or key): the opening's position is not re-set when the height changes.
     *
     * @returns {void}
     */
    const take = (): void => {
        touched.current = true;
    };

    /**
     * A scroll moves the fades.
     *
     * @param {object} event The scroll.
     * @returns {void}
     */
    const onScroll = (event: UIEvent<HTMLDivElement>): void => {
        const element = event.currentTarget;
        const next = edgesOf(element.scrollTop, element.scrollHeight, element.clientHeight);
        setEdges((now) => (moved(now, next) ? next : now));
    };

    return (
        <div className={`dim-cal__hourbox dim-cal__hourbox--${variant}`} role="presentation">
            <div
                ref={box}
                className="dim-cal__hourscroll"
                data-region="dim-cal-hourscroll"
                role={role}
                tabIndex={focusable ? 0 : undefined}
                aria-label={focusable || role ? label : undefined}
                onScroll={onScroll}
                onWheel={take}
                onTouchStart={take}
                onKeyDown={take}
            >
                {children}
            </div>
            {edges.up ? <span className="dim-cal__hourfade dim-cal__hourfade--up" aria-hidden="true"></span> : null}
            {edges.down ? <span className="dim-cal__hourfade dim-cal__hourfade--down" aria-hidden="true"></span> : null}
        </div>
    );
};

export default HourScroll;
