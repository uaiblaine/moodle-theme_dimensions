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
 * phone.
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
 *   The visitor's page hands no `describe` and a `plain` text instead, drawn as text.
 * - The actions: the primary is model.ts primaryOf()'s; "Open" stays beside it when the primary is something else
 *   and the item has a page of its own; the course's page comes third. An item with no page of its own (a personal
 *   event, a site event) has neither: the popover never leads to the site's home.
 * - An event course (source `course`) opens with its cover: the course's image, an <img> with an empty alt (never a
 *   url in a style), else its category's branding pair (look.ts coverStyle()), else its key's colours, and a date tile
 *   (the month's abbreviation and the day), hidden from the screen reader, which reads the date in the facts.
 *
 * The position is measured when the popover opens, never on the page's mount: the opener's box and the popover's
 * own, and the fixed navbar's bottom, read once in a layout effect, and {@see anchor()} keeps it inside the viewport
 * below the navbar.
 *
 * @module     theme_dimensions/calendar/EventPopover
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useLayoutEffect, useRef, useState} from 'react';
import type {CSSProperties, KeyboardEvent, ReactNode} from 'react';
import Pending from '@moodle/lms/core/pending';
import {say} from '../daterange/calendar';
import {keyLabel} from './EventTypes';
import type {Placed} from './layout';
import {colourStyle, coverStyle, keyClass, placeOf, whenOf} from './look';
import type {Look} from './look';
import {itemHref, opens, primaryOf, safeHref} from './model';
import type {Course, Item, Option, Primary} from './model';
import {isAllDay} from './overlap';
import {fill} from './strings';
import type {Strings} from './strings';

/** The id of the heading, which labels the dialogue. */
export const TITLE = 'dim-cal-pop-title';

/** The room kept between the popover, its opener and the viewport's edges, in px. */
const GAP = 8;

/** A day as the popover says it. */
const DAY: Intl.DateTimeFormatOptions = {weekday: 'long', day: 'numeric', month: 'long'};

/** The day and month of a span's ends. */
const SPAN_DAY: Intl.DateTimeFormatOptions = {day: 'numeric', month: 'long'};

/** The month of the cover's date tile. */
const TILE_MONTH: Intl.DateTimeFormatOptions = {month: 'short'};

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
    models: Option[],
    describe: ((id: number) => Promise<string>) | null,
    plain: string,
    actions?: ReactNode,
    onClose: (refocus: boolean) => void,
};

/**
 * Where the popover goes: under its opener when it fits, else above when that fits, else on the side with more room;
 * its left edge on the opener's, pulled inside the viewport. It never goes above `reserved` + the gap, the bottom of
 * the page's fixed navbar read once on opening, so a bar in the first rows flips below its opener instead of
 * hiding the popover's cover under the navbar. Only when neither side holds the popover is its height capped
 * (`maxHeight`) so it scrolls inside. Pure.
 *
 * @param {object} opener The opener's box.
 * @param {object} size The popover's size.
 * @param {object} viewport The viewport's size.
 * @param {number} reserved The viewport's top that is not the page's own: the fixed navbar's bottom, 0 without one.
 * @returns {object} {top, left, maxHeight?} in px, for a fixed position.
 */
export const anchor = (
    opener: Box,
    size: Size,
    viewport: Size,
    reserved: number = 0
): {top: number, left: number, maxHeight?: number} => {
    const least = reserved + GAP;
    const most = viewport.height - GAP;
    const below = opener.bottom + GAP;
    const above = opener.top - GAP - size.height;
    let top = below;
    if (below + size.height > most) {
        top = above >= least || opener.top - GAP - least > most - below ? above : below;
    }
    const height = Math.min(size.height, most - least);
    top = Math.max(least, Math.min(top, most - height));
    const left = Math.max(GAP, Math.min(opener.left, viewport.width - GAP - size.width));
    return size.height > height ? {top: top, left: left, maxHeight: height} : {top: top, left: left};
};

/**
 * The bottom of the page's fixed navbar in the viewport, 0 when there is none.
 *
 * @returns {number} px.
 */
const navbarBottom = (): number => {
    const bar = document.querySelector<HTMLElement>('.navbar.fixed-top');
    return bar ? Math.max(0, bar.getBoundingClientRect().bottom) : 0;
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

/**
 * The cover of an event course's popover, or null for anything else.
 *
 * @param {object} entry The placed item.
 * @param {object} look The page's look.
 * @returns {object|null} The cover.
 */
const cover = (entry: Placed, look: Look) => {
    const course = entry.item.source === 'course' ? look.courses.get(entry.item.courseid) : undefined;
    if (!course) {
        return null;
    }
    const image = safeHref(course.image, '');
    return (
        <div
            className={`dim-cal__popcover ${keyClass(entry.item)}`}
            style={coverStyle(course, look)}
            data-region="dim-cal-popcover"
        >
            {image !== '' ? <img className="dim-cal__popcoverimg" src={image} alt="" /> : null}
            <span className="dim-cal__poptile" aria-hidden="true">
                <span className="dim-cal__poptilemonth">{say(entry.first, TILE_MONTH, look.language)}</span>
                <span className="dim-cal__poptileday">{Number(entry.first.slice(8, 10))}</span>
            </span>
        </div>
    );
};

/** What the popover says of an item beyond its name and time. */
type Facts = {place: string, modality: string, course: Course | null, participants: number | null};

/**
 * The facts of an item: its place, and for an event course its modality and its participant count (a number only);
 * the course for anything course-bound.
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
        // The course has a row of its own below, so the place never falls back to its name.
        place: placeOf(item, look, false),
        modality: eventcourse ? modalities.find((option) => option.value === course.modality)?.label || '' : '',
        course: course,
        participants: eventcourse ? course.participants : null,
    };
};

/** The stored value of the page model "course" (landingfields::MODEL_COURSE). */
export const MODEL_COURSE = 1;

/** The stored value of the page model "event" (landingfields::MODEL_EVENT). */
export const MODEL_EVENT = 2;

/**
 * The text of the course's button, after the course's page model: a string of its own for each model the theme knows
 * (an unset model is a plain course), and for any other the generic one over the model's own option label as the
 * field is configured, so a model added to the field is named without code. A value with no option left reads as a
 * course.
 *
 * @param {object} course The item's course.
 * @param {Array} models The model field's options.
 * @param {object} strings The page's strings.
 * @returns {string}
 */
export const coursePageLabel = (course: Course, models: Option[], strings: Strings): string => {
    if (course.model === MODEL_EVENT) {
        return strings.calendar_eventpage;
    }
    const option = course.model !== MODEL_COURSE ? models.find((one) => one.value === course.model) : undefined;
    return option ? fill(strings.calendar_modelpage, option.label) : strings.calendar_coursepage;
};

/** What the actions are handed: the item, its course, the page's look, the model options and the editing actions. */
type PopActionsProps = {item: Item, course: Course | null, look: Look, models: Option[], actions?: ReactNode};

/**
 * The popover's actions: the primary, "Open" beside another primary when the item has a page of its own, the course's
 * page, and the editing actions it is handed. Nothing here falls back to the site's home.
 *
 * @param {object} props See PopActionsProps.
 * @returns {object} The row.
 */
const PopActions = ({item, course, look, models, actions}: PopActionsProps) => {
    const primary = primaryOf(item, look.courses, look.home);
    const open = opens(item, look.courses) ? itemHref(item, look.courses, look.home) : '';
    const coursepage = course && course.url !== course.landingurl ? safeHref(course.url, '') : '';
    return (
        <div className="dim-cal__popactions">
            {primary.kind !== 'none' ? (
                <a className="btn btn-primary" href={primary.url} data-action={`dim-cal-${primary.kind}`}>
                    {primaryLabel(primary, look)}
                </a>
            ) : null}
            {primary.kind !== 'open' && open !== '' ? (
                <a className="btn btn-secondary" href={open} data-action="dim-cal-open">{look.strings.calendar_open}</a>
            ) : null}
            {coursepage !== '' ? (
                <a className="btn btn-outline-secondary dim-cal__coursebtn" href={coursepage} data-action="dim-cal-course">
                    {course ? coursePageLabel(course, models, look.strings) : look.strings.calendar_coursepage}
                </a>
            ) : null}
            {actions}
        </div>
    );
};

/**
 * The popover.
 *
 * @param {object} props See EventPopoverProps.
 * @returns {object} The dialogue.
 */
const EventPopover = (props: EventPopoverProps) => {
    const {look, entry, overlaps, opener, sheet, modalities, models, describe, plain, actions, onClose} = props;
    const {item} = entry;
    const dialog = useRef<HTMLDivElement | null>(null);
    const heading = useRef<HTMLHeadingElement | null>(null);
    const ticket = useRef<number>(0);
    const focused = useRef<string>('');
    const [text, setText] = useState<string | null>(null);
    const [at, setAt] = useState<{top: number, left: number, maxHeight?: number} | null>(null);

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
        setAt(anchor(
            box,
            {width: own.width, height: own.height},
            {width: window.innerWidth, height: window.innerHeight},
            navbarBottom()
        ));
    }, [item.source, item.id, opener, sheet]);

    // The focus moves to the heading once the popover is shown, once per opened item: while it is measuring it is
    // visibility: hidden and focus() is a no-op there. A re-measure of the same item does not take the focus back.
    useLayoutEffect(() => {
        const key = `${item.source}-${item.id}`;
        if ((sheet || at !== null) && focused.current !== key) {
            focused.current = key;
            heading.current?.focus();
        }
    }, [sheet, at, item.source, item.id]);

    // A press outside the popover and its opener closes it. A press inside another dialogue does not: one of its
    // actions opened it over the popover (core's event form, core's delete confirmation), and the popover stays under
    // it so that the focus comes back to the button that opened it.
    useEffect(() => {
        /**
         * Close on a press elsewhere.
         *
         * @param {Event} event The press.
         * @returns {void}
         */
        const outside = (event: Event): void => {
            const target = event.target as Node | null;
            const over = target instanceof Element && target.closest('[role="dialog"]') !== null;
            if (target && !over && !dialog.current?.contains(target) && !opener.contains(target)) {
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
    const style: CSSProperties = {...colourStyle(item, look)};
    if (at) {
        style.top = `${at.top}px`;
        style.left = `${at.left}px`;
        if (at.maxHeight !== undefined) {
            style.maxHeight = `${at.maxHeight}px`;
            style.overflowY = 'auto';
        }
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
            {cover(entry, look)}
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
                // else may reach it. The calendar's one trusted sink of event content (the Trilhas view's fold icons
                // are the server's pix icons).
                <div
                    className="dim-cal__popdesc"
                    data-region="dim-cal-description"
                    dangerouslySetInnerHTML={{__html: text}}
                ></div>
            ) : null}
            <PopActions item={item} course={course} look={look} models={models} actions={actions} />
        </div>
    );
};

export default EventPopover;
