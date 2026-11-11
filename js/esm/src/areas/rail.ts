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
 * The panel's rail: its arrows, its phone counter and the drag of a mouse.
 *
 * The rail itself is the panel template's markup; this module reads its scroll and writes its arrows' state and its
 * counter. Layout is read only where it is already clean: {@see railState}, which panel.ts calls from a
 * ResizeObserver entry (after layout, before paint) and from the rail's own scroll, and the reader's own press
 * ({@see scrollRail}, {@see dragRail}).
 *
 * @module     theme_dimensions/areas/rail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CLASSES, DRAG_THRESHOLD, RAIL_GAP, SELECTORS} from './model';
import {setClass} from './posters';

/** What the rail's controls show for where it is scrolled to. */
export type RailState = {prevoff: boolean, nextoff: boolean, at: number, count: number};

/**
 * The arrows' and the counter's state from the rail's scroll: a pure reading of four numbers.
 *
 * @param {number} left The rail's scrollLeft.
 * @param {number} max Its scrollWidth less its clientWidth.
 * @param {number} step One card's width and the rail's gap.
 * @param {number} count How many cards it holds.
 * @returns {object}
 */
export const railFrom = (left: number, max: number, step: number, count: number): RailState => ({
    prevoff: left <= 2,
    nextoff: left >= max - 2,
    at: count && step > 0 ? Math.min(count, Math.round(left / step) + 1) : 0,
    count: count,
});

/**
 * Read the rail's state off the page: a layout read, made only where the layout is clean.
 *
 * @param {Element} rail The rail.
 * @returns {object}
 */
export const railState = (rail: HTMLElement): RailState => {
    const cards = rail.querySelectorAll(SELECTORS.card);
    const first = cards.length ? (cards[0].parentElement as HTMLElement) : null;
    return railFrom(rail.scrollLeft, rail.scrollWidth - rail.clientWidth, first ? first.offsetWidth + RAIL_GAP : 0, cards.length);
};

/**
 * Write the rail's state onto its arrows and its counter, only what changes.
 *
 * @param {Element} panel The panel's element.
 * @param {object} state The rail's state.
 * @returns {void}
 */
export const paintRail = (panel: HTMLElement, state: RailState): void => {
    const prev = panel.querySelector(SELECTORS.prev);
    const next = panel.querySelector(SELECTORS.next);
    if (prev) {
        setClass(prev, CLASSES.arrowoff, state.prevoff);
    }
    if (next) {
        setClass(next, CLASSES.arrowoff, state.nextoff);
    }
    const counter = panel.querySelector(SELECTORS.counter);
    const text = state.at + ' / ' + state.count;
    if (counter && state.count && counter.textContent !== text) {
        counter.textContent = text;
    }
};

/**
 * Scroll the rail by one card and its gap, in either direction: on the reader's press.
 *
 * @param {Element} panel The panel's element.
 * @param {number} direction -1 or 1.
 * @param {boolean} still Whether the reader asked for less movement.
 * @returns {void}
 */
export const scrollRail = (panel: HTMLElement, direction: number, still: boolean): void => {
    const rail = panel.querySelector<HTMLElement>(SELECTORS.rail);
    const first = rail ? (rail.firstElementChild as HTMLElement | null) : null;
    if (!rail || !first) {
        return;
    }
    rail.scrollBy({left: direction * (first.offsetWidth + RAIL_GAP), behavior: still ? 'auto' : 'smooth'});
};

/**
 * A mouse drag of the rail scrolls it; the click that ends a drag is not a click.
 *
 * @param {PointerEvent} event The press.
 * @param {Function} dragged Called once a drag ended, so the click it ends is swallowed.
 * @returns {void}
 */
export const dragRail = (event: PointerEvent, dragged: () => void): void => {
    if (event.pointerType !== 'mouse' || event.button !== 0 || !(event.target instanceof Element)) {
        return;
    }
    const rail = event.target.closest<HTMLElement>(SELECTORS.rail);
    if (!rail || rail.scrollWidth <= rail.clientWidth + 2) {
        return;
    }
    const startx = event.clientX;
    const startleft = rail.scrollLeft;
    let moved = false;
    /**
     * Follow the mouse once it travelled past the threshold.
     *
     * @param {PointerEvent} step The move.
     * @returns {void}
     */
    const move = (step: PointerEvent): void => {
        const dx = step.clientX - startx;
        if (!moved && Math.abs(dx) > DRAG_THRESHOLD) {
            moved = true;
            rail.classList.add(CLASSES.dragging);
        }
        if (moved) {
            rail.scrollLeft = startleft - dx;
        }
    };
    /**
     * End the drag, and say so if it was one.
     *
     * @returns {void}
     */
    const up = (): void => {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', up);
        if (moved) {
            rail.classList.remove(CLASSES.dragging);
            dragged();
        }
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
};
