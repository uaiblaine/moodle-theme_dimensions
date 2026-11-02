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
 * The event popover: one item's facts and actions, anchored to the bar or line that opened it, a bottom sheet on a
 * phone (docs/calendar/PROPOSAL.md sections 13.2 and 13.3).
 *
 * One at a time. role="dialog" labelled by the item's name, not modal: the focus moves to its heading on open;
 * Escape, the close button or a press outside close it and give the focus back to the item; Tab past its last
 * control closes it and gives the focus back to the item too, from which the next Tab moves on.
 *
 * - The time is the viewer's timezone's (the zone reader, as the grid places items), "all day" for a whole day, the
 *   span for a multi-day item. The place is the item's location, or an event course's venue and city.
 * - The overlap warning is text naming the other timed items the item intersects (calendar/overlap.ts), never
 *   colour alone.
 * - The description is read lazily, for core's events alone, through the `describe` it is handed: the logged-in page
 *   hands core's own formatted description, the one feeder of the trusted sink below. The latest popover wins: a
 *   ticket is taken per opening and checked when the answer comes, so an answer for an item no longer shown, or
 *   for a popover already closed, is dropped; a Pending token (theme_dimensions/calendar:description) spans it.
 *   The visitor's page hands no `describe` and a `plain` text instead (decision V2), drawn as text.
 * - The actions: the primary is model.ts primaryOf()'s; "Open" stays beside it when the primary is something else;
 *   the course's page comes third.
 *
 * The position is measured when the popover opens, never on the page's mount: the opener's box and the popover's
 * own, read once in a layout effect, and {@see anchor()} keeps it inside the viewport.
 *
 * @module     theme_dimensions/calendar/EventPopover
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useLayoutEffect, useRef, useState} from 'react';
import type {CSSProperties, KeyboardEvent} from 'react';
import Pending from '@moodle/lms/core/pending';
import {say} from '../daterange/calendar';
import {keyLabel} from './KeyChips';
import type {Placed} from './layout';
import {colourStyle, keyClass, placeOf, whenOf} from './look';
import type {Look} from './look';
import {itemHref, primaryOf, safeHref} from './model';
import type {Course, Item, Option, Primary} from './model';
import {isAllDay} from './overlap';
import {fill} from './strings';

/** The id of the heading, which labels the dialogue. */
export const TITLE = 'dim-cal-pop-title';

/** The room kept between the popover, its opener and the viewport's edges, in px. */
const GAP = 8;

/** A day as the popover says it. */
const DAY: Intl.DateTimeFormatOptions = {weekday: 'long', day: 'numeric', month: 'long'};

/** The day and month of a span's ends. */
const SPAN_DAY: Intl.DateTimeFormatOptions = {day: 'numeric', month: 'long'};

/** A box, as getBoundingClientRect() reads one. */
export type Box = {top: number, left: number, bottom: number, right: number};

/** A size. */
export type Size = {width: number, height: number};

/** What the popover is handed. */
type EventPopoverProps = {
    look: Look,
    entry: Placed,
    overlaps: Placed[],
    opener: HTMLElement,
    sheet: boolean,
    modalities: Option[],
    describe: ((id: number) => Promise<string>) | null,
    plain: string,
    onClose: (refocus: boolean) => void,
};

/**
 * Where the popover goes: under its opener when it fits, else above, else on the side with more room; its left edge
 * on the opener's, pulled inside the viewport. Pure.
 *
 * @param {object} opener The opener's box.
 * @param {object} size The popover's size.
 * @param {object} viewport The viewport's size.
 * @returns {object} {top, left} in px, for a fixed position.
 */
export const anchor = (opener: Box, size: Size, viewport: Size): {top: number, left: number} => {
    const below = opener.bottom + GAP;
    const above = opener.top - GAP - size.height;
    let top = below;
    if (below + size.height > viewport.height - GAP) {
        top = above >= GAP || opener.top > viewport.height - opener.bottom ? above : below;
    }
    top = Math.max(GAP, Math.min(top, viewport.height - GAP - size.height));
    const left = Math.max(GAP, Math.min(opener.left, viewport.width - GAP - size.width));
    return {top: top, left: left};
};

/**
 * When an item happens, as the popover says it.
 *
 * @param {object} entry The placed item.
 * @param {object} look The page's look.
 * @returns {string}
 */
export const whenText = (entry: Placed, look: Look): string => {
    if (entry.first !== entry.last) {
        return fill(look.strings.calendar_span, {
            from: say(entry.first, SPAN_DAY, look.language),
            to: say(entry.last, SPAN_DAY, look.language),
        });
    }
    const day = say(entry.first, DAY, look.language);
    return `${day} · ${isAllDay(entry.item, look.zone) ? look.strings.calendar_allday : whenOf(entry.item, look)}`;
};

/**
 * The label of the primary action.
 *
 * @param {object} primary The primary action.
 * @param {object} look The page's look.
 * @returns {string}
 */
export const primaryLabel = (primary: Primary, look: Look): string => {
    switch (primary.kind) {
        case 'action':
            return primary.name;
        case 'join':
            switch (primary.join) {
                case 'free':
                    return look.strings.calendar_join_free;
                case 'key':
                    return look.strings.calendar_join_key;
                default:
                    return look.strings.calendar_join_open;
            }
        default:
            return look.strings.calendar_open;
    }
};

/** What the popover says of an item beyond its name and time. */
type Facts = {place: string, modality: string, course: Course | null, participants: number | null};

/**
 * The facts of an item: its place, and for an event course its modality and its participant count (a number only,
 * decision D3); the course for anything course-bound.
 *
 * @param {object} item The item.
 * @param {object} look The page's look.
 * @param {Array} modalities The modality field's options.
 * @returns {object} The facts.
 */
export const factsOf = (item: Item, look: Look, modalities: Option[]): Facts => {
    const course = look.courses.get(item.courseid) || null;
    const eventcourse = course !== null && item.source === 'course';
    return {
        place: placeOf(item, look),
        modality: eventcourse ? modalities.find((option) => option.value === course.modality)?.label || '' : '',
        course: course,
        participants: eventcourse ? course.participants : null,
    };
};

/**
 * The popover.
 *
 * @param {object} props See EventPopoverProps.
 * @returns {object} The dialogue.
 */
const EventPopover = ({look, entry, overlaps, opener, sheet, modalities, describe, plain, onClose}: EventPopoverProps) => {
    const {item} = entry;
    const dialog = useRef<HTMLDivElement | null>(null);
    const heading = useRef<HTMLHeadingElement | null>(null);
    const ticket = useRef<number>(0);
    const focused = useRef<string>('');
    const [text, setText] = useState<string | null>(null);
    const [at, setAt] = useState<{top: number, left: number} | null>(null);

    // The description: one request per event per page life, the latest opening's answer alone shown.
    useEffect(() => {
        const turn = ++ticket.current;
        if (item.source !== 'core' || describe === null) {
            setText('');
            return undefined;
        }
        setText(null);
        const pending = new Pending('theme_dimensions/calendar:description');
        describe(item.id)
            .then((html) => {
                if (turn === ticket.current) {
                    setText(html);
                }
                return html;
            })
            .finally(() => pending.resolve());
        return () => {
            ticket.current++;
        };
    }, [item.source, item.id, describe]);

    // The position, measured once the popover is drawn for this item: an opening, never the page's mount.
    useLayoutEffect(() => {
        if (sheet || !dialog.current) {
            setAt(null);
            return;
        }
        const box = opener.getBoundingClientRect();
        const own = dialog.current.getBoundingClientRect();
        setAt(anchor(box, {width: own.width, height: own.height}, {width: window.innerWidth, height: window.innerHeight}));
    }, [item.source, item.id, opener, sheet]);

    // The focus moves to the heading once the popover is SHOWN, once per opened item: while it is measuring it is
    // visibility: hidden and focus() is a no-op there. A re-measure of the same item does not take the focus back.
    useLayoutEffect(() => {
        const key = `${item.source}-${item.id}`;
        if ((sheet || at !== null) && focused.current !== key) {
            focused.current = key;
            heading.current?.focus();
        }
    }, [sheet, at, item.source, item.id]);

    // A press outside the popover and its opener closes it.
    useEffect(() => {
        /**
         * Close on a press elsewhere.
         *
         * @param {Event} event The press.
         * @returns {void}
         */
        const outside = (event: Event): void => {
            const target = event.target as Node | null;
            if (target && !dialog.current?.contains(target) && !opener.contains(target)) {
                onClose(true);
            }
        };
        document.addEventListener('mousedown', outside);
        return () => document.removeEventListener('mousedown', outside);
    }, [opener, onClose]);

    /**
     * Escape closes; Tab past the last control closes and gives the focus back to the item.
     *
     * @param {object} event The key press.
     * @returns {void}
     */
    const onKeyDown = (event: KeyboardEvent): void => {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            onClose(true);
            return;
        }
        if (event.key === 'Tab' && !event.shiftKey) {
            const controls = Array.from(dialog.current?.querySelectorAll<HTMLElement>('a[href], button') || []);
            if (controls.length && event.target === controls[controls.length - 1]) {
                event.preventDefault();
                onClose(true);
            }
        }
    };

    const {place, modality, course, participants} = factsOf(item, look, modalities);
    const primary = primaryOf(item, look.courses, look.home);
    const open = itemHref(item, look.courses, look.home);
    const style: CSSProperties = {...colourStyle(item, look)};
    if (at) {
        style.top = `${at.top}px`;
        style.left = `${at.left}px`;
    }

    return (
        <div
            ref={dialog}
            className={`dim-cal__pop${sheet ? ' dim-cal__pop--sheet' : ''}${!sheet && !at ? ' dim-cal__pop--measuring' : ''}`}
            role="dialog"
            aria-labelledby={TITLE}
            data-region="dim-cal-popover"
            data-source={item.source}
            data-id={item.id}
            style={style}
            onKeyDown={onKeyDown}
        >
            <div className="dim-cal__pophead">
                <span className={`dim-cal__kind ${keyClass(item)}`}>
                    <span className="dim-cal__swatch" aria-hidden="true"></span>
                    {keyLabel(look.strings, item.key)}
                </span>
                <button
                    type="button"
                    className="btn btn-icon dim-cal__close"
                    aria-label={look.strings.calendar_close}
                    data-action="dim-cal-pop-close"
                    onClick={() => onClose(true)}
                >
                    <i className="fa fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <h2 ref={heading} className="dim-cal__poptitle" id={TITLE} tabIndex={-1}>{item.name}</h2>
            <dl className="dim-cal__popfacts">
                <dt>{look.strings.calendar_when}</dt>
                <dd>{whenText(entry, look)}</dd>
                {place !== '' ? <dt>{look.strings.calendar_place}</dt> : null}
                {place !== '' ? <dd>{place}</dd> : null}
                {modality !== '' ? <dt>{look.strings.calendar_modality}</dt> : null}
                {modality !== '' ? <dd>{modality}</dd> : null}
                {course ? <dt>{look.strings.calendar_course}</dt> : null}
                {course ? <dd>{course.fullname}</dd> : null}
            </dl>
            {participants !== null ? (
                <p className="dim-cal__popnote">
                    {participants === 1 ? look.strings.calendar_participants_one
                        : fill(look.strings.calendar_participants_many, participants)}
                </p>
            ) : null}
            {overlaps.length ? (
                <p className="dim-cal__overlapwarn" data-region="dim-cal-overlap">
                    <i className="fa fa-triangle-exclamation" aria-hidden="true"></i>
                    {fill(look.strings.calendar_overlaps, overlaps.map((other) => other.item.name).join(', '))}
                </p>
            ) : null}
            {text === null ? <p className="dim-cal__poploading">{look.strings.calendar_descriptionloading}</p> : null}
            {plain !== '' ? (
                <p className="dim-cal__popdesc dim-cal__popdesc--text" data-region="dim-cal-description">{plain}</p>
            ) : null}
            {text ? (
                // Core's own formatted HTML: core's event service's description, which core's event exporter ran
                // through format_text() for this viewer, handed in as describe by the logged-in page alone; nothing
                // else may reach it. The one trusted sink of the calendar.
                <div
                    className="dim-cal__popdesc"
                    data-region="dim-cal-description"
                    dangerouslySetInnerHTML={{__html: text}}
                ></div>
            ) : null}
            <div className="dim-cal__popactions">
                <a className="btn btn-primary" href={primary.url} data-action={`dim-cal-${primary.kind}`}>
                    {primaryLabel(primary, look)}
                </a>
                {primary.kind !== 'open' ? (
                    <a className="btn btn-secondary" href={open} data-action="dim-cal-open">{look.strings.calendar_open}</a>
                ) : null}
                {course && course.url !== course.landingurl ? (
                    <a className="btn btn-link" href={safeHref(course.url, look.home)} data-action="dim-cal-course">
                        {look.strings.calendar_coursepage}
                    </a>
                ) : null}
            </div>
        </div>
    );
};

export default EventPopover;
