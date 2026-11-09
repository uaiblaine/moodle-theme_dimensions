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
 * A step's popover in the Trilhas view, the course card's dialog variant (moodle-dev/docs/course-card/CONTRACT.md,
 * section 9): its course's cover, its category over its name, its dates as the meta line, "Course 3 of 5 · trail" in the
 * plugin's slot, its state and core's availability text for a learner, "View trail" and the main action.
 *
 * The event popover's behaviour (EventPopover.tsx), for a step: role="dialog" labelled by the name, not modal; anchored
 * to the bar that opened it by the same rule ({@see anchor}), a bottom sheet on a phone; the focus on its heading once
 * shown; Escape, the close button, a press outside, or Tab past its last control close it and give the focus back to
 * the bar. Its two layout reads are the opener's box and its own, on an opening.
 *
 * - The cover is the step's course image (the answer's `image`, {@see safeImage()}: an address or the theme's SVG
 *   cover), an <img> with an empty alt; the category's band (look.ts categoryCover(), else the state's) is only the
 *   ground under it, which shows when the image is missing or fails to load. The close button sits on the cover where a
 *   card's star would: the course dialogue's dark disc at 32 px.
 * - Boost Union's card view off (`covers` false, calendar_page::props()): no cover; the category and the close button
 *   share a row at the top of the body, the close the calendar's standard icon button, since the disc is made to sit on
 *   an image; the popover keeps a top edge in the category's colour.
 * - A completed step: the state pill is the green seal, the cover gets a green base, and the main action reads "Review"
 *   instead of "Open course". There is no progress bar: the service carries no percentage per step (D11).
 * - The buttons are the calendar's own (D12): "View trail" btn-secondary, the main action btn-primary.
 *
 * @module     theme_dimensions/calendar/TrailPopover
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useLayoutEffect, useRef, useState} from 'react';
import type {CSSProperties, KeyboardEvent} from 'react';
import {say} from '../daterange/calendar';
import {anchor} from './EventPopover';
import {categoryCover} from './look';
import type {Look} from './look';
import {safeHref, safeImage} from './model';
import {fill} from './strings';
import type {Strings} from './strings';
import type {StepState, Trail, TrailStep} from './trails';

/** The popover's heading id. */
const TITLE = 'dim-cal-trailpop-title';

/** A day in the popover's dates. */
const DAY: Intl.DateTimeFormatOptions = {day: 'numeric', month: 'long', year: 'numeric'};

/** What the popover is handed. */
type TrailPopoverProps = {
    look: Look,
    trail: Trail,
    step: TrailStep,
    opener: HTMLElement,
    sheet: boolean,
    now: number,
    covers?: boolean,
    onClose: (refocus: boolean) => void,
};

/**
 * The trail's icon two stops joined by a winding path, drawn in the text's
 * colour. Decorative: whatever carries it names the trail in words.
 *
 * @param {object} props The icon's size, 16 by default.
 * @returns {object} The icon.
 */
export const TrailIcon = ({size = 16}: {size?: number}) => (
    <svg
        className="dim-cal__trailicon"
        width={size}
        height={size}
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        aria-hidden="true"
        focusable="false"
    >
        <circle cx="5" cy="18" r="2"></circle>
        <circle cx="19" cy="6" r="2"></circle>
        <path d="M7 18h6a4 4 0 0 0 0-8h-2a4 4 0 0 1 0-8h6"></path>
    </svg>
);

/**
 * A state's tag, or '' for a reader who is not a learner of the trail.
 *
 * @param {object} strings The page's strings.
 * @param {string} state The state.
 * @returns {string}
 */
export const stateLabel = (strings: Strings, state: StepState): string => {
    switch (state) {
        case 'done':
            return strings.landing_trail_state_done;
        case 'current':
            return strings.landing_trail_state_current;
        case 'late':
            return strings.landing_trail_state_late;
        case 'locked':
            return strings.landing_trail_state_locked;
        case 'upcoming':
            return strings.landing_trail_state_upcoming;
        default:
            return '';
    }
};

/**
 * A step's dates in one line: its span, "From" an open start, "Until" an end alone (local\calendar\page's server layer,
 * calendar_page::step_when(), says the same).
 *
 * @param {object} step The step.
 * @param {object} look The page's look.
 * @returns {string}
 */
export const stepWhen = (step: TrailStep, look: Look): string => {
    const start = step.start > 0 ? say(look.zone.day(step.start), DAY, look.language) : '';
    const end = step.end > 0 ? say(look.zone.day(step.end), DAY, look.language) : '';
    if (start !== '' && end !== '') {
        return fill(look.strings.calendar_span, {from: start, to: end});
    }
    if (start !== '') {
        return fill(look.strings.calendar_trailfrom, start);
    }
    return end !== '' ? fill(look.strings.calendar_until, end) : '';
};

/**
 * "No end date set" for a step with a start and no end (an expected date is an end), in full: the popover's dates line
 * and the bar's accessible name say it, where the bar's chip says it short. Empty for any other step.
 *
 * @param {object} step The step.
 * @param {object} look The page's look.
 * @returns {string}
 */
export const stepOpenEnd = (step: TrailStep, look: Look): string =>
    step.start > 0 && step.end <= 0 ? look.strings.calendar_trailopenfull : '';

/**
 * The step's state line: "Starts on" for a current step whose course has not begun (the landing page's rule), else the
 * state's tag; '' for a reader who is not a learner.
 *
 * @param {object} step The step.
 * @param {object} look The page's look.
 * @param {number} now Now, in seconds.
 * @returns {string}
 */
export const stateLine = (step: TrailStep, look: Look, now: number): string => {
    if (step.state === 'current' && step.start > now) {
        return fill(look.strings.landing_trail_startson, say(look.zone.day(step.start), DAY, look.language));
    }
    return stateLabel(look.strings, step.state);
};

/**
 * The main action's label: "Review" for a completed step, "Open course" for any other (the course card contract,
 * section 3: a completed course reads "Review", whatever else is true of it).
 *
 * @param {object} step The step.
 * @param {object} strings The page's strings.
 * @returns {string}
 */
export const mainLabel = (step: TrailStep, strings: Strings): string =>
    step.state === 'done' ? strings.category_cta_review : strings.calendar_trailcourse;

/** Where the popover sits once measured: a desktop's anchored box, nothing for a sheet. */
type At = {top: number, left: number, maxHeight?: number} | null;

/**
 * The popover's inline style: its measured place, and without a cover its category's colours for the top edge.
 *
 * @param {object|null} at The measured place.
 * @param {boolean} covers Whether the popover draws its cover.
 * @param {object} step The step.
 * @param {object} look The page's look.
 * @returns {object} The style.
 */
const placed = (at: At, covers: boolean, step: TrailStep, look: Look): CSSProperties => {
    const style: CSSProperties = covers ? {} : {...categoryCover(step.categoryid, look)};
    if (at) {
        style.top = `${at.top}px`;
        style.left = `${at.left}px`;
        if (at.maxHeight !== undefined) {
            style.maxHeight = `${at.maxHeight}px`;
            style.overflowY = 'auto';
        }
    }
    return style;
};

/**
 * The popover's classes: the sheet on a phone, hidden while it measures, without its cover, completed.
 *
 * @param {boolean} sheet Whether it is a phone's bottom sheet.
 * @param {boolean} measuring Whether it is being measured, hidden.
 * @param {boolean} covers Whether it draws its cover.
 * @param {boolean} done Whether the step is completed.
 * @returns {string}
 */
const popClasses = (sheet: boolean, measuring: boolean, covers: boolean, done: boolean): string =>
    'dim-cal__pop dim-cal__trailpop' + (sheet ? ' dim-cal__pop--sheet' : '') + (measuring ? ' dim-cal__pop--measuring' : '')
        + (covers ? '' : ' dim-cal__trailpop--nocover') + (done ? ' dim-cal__trailpop--done' : '');

/**
 * The state pill: the state's own colours, the green seal with its check for a completed step.
 *
 * @param {object} props The step and the line {@see stateLine()} says of it.
 * @returns {object} The pill.
 */
const StatePill = ({step, state}: {step: TrailStep, state: string}) => {
    const done = step.state === 'done';
    const tone = step.state ? ` dim-cal__trailstate--${step.state}` : '';
    return (
        <p className={`dim-cal__trailstate${tone}` + (done ? ' dim-cal__trailseal' : '')} data-region="dim-cal-trailstate">
            {done ? <i className="fa fa-check" aria-hidden="true"></i> : null}
            {state}
        </p>
    );
};

/** What the cover is handed. */
type CoverProps = {
    step: TrailStep,
    look: Look,
    image: string,
    failed: string,
    setFailed: (image: string) => void,
    onClose: (refocus: boolean) => void,
};

/**
 * The cover: the step's image over its category's band (the band alone once the image fails), a green base for a
 * completed step, and the close disc where a card's star would be.
 *
 * @param {object} props See CoverProps.
 * @returns {object} The cover.
 */
const Cover = ({step, look, image, failed, setFailed, onClose}: CoverProps) => {
    const done = step.state === 'done';
    const tone = step.state ? ` dim-cal__trailstate--${step.state}` : '';
    return (
        <div
            className={`dim-cal__popcover dim-cal__trailcover${tone}` + (done ? ' dim-cal__trailcover--done' : '')}
            style={categoryCover(step.categoryid, look)}
            data-region="dim-cal-trailcover"
        >
            {image !== '' && image !== failed ? (
                <img className="dim-cal__popcoverimg" src={image} alt="" onError={() => setFailed(image)} />
            ) : null}
            <button
                type="button"
                className="dim-cal__coverclose"
                aria-label={look.strings.calendar_close}
                data-action="dim-cal-pop-close"
                onClick={() => onClose(true)}
            >
                <span className="btn-close" aria-hidden="true"></span>
            </button>
        </div>
    );
};

/**
 * The popover.
 *
 * @param {object} props See TrailPopoverProps.
 * @returns {object} The dialog.
 */
const TrailPopover = ({look, trail, step, opener, sheet, now, covers = true, onClose}: TrailPopoverProps) => {
    const dialog = useRef<HTMLDivElement | null>(null);
    const heading = useRef<HTMLHeadingElement | null>(null);
    const focused = useRef<number>(0);
    const [at, setAt] = useState<At>(null);
    // The image that failed to load, so the band under it shows instead of a broken image.
    const [failed, setFailed] = useState<string>('');

    // The position, measured once the popover is drawn for this step: an opening, never the page's mount.
    useLayoutEffect(() => {
        if (sheet || !dialog.current) {
            setAt(null);
            return;
        }
        const box = opener.getBoundingClientRect();
        const own = dialog.current.getBoundingClientRect();
        const bar = document.querySelector<HTMLElement>('.navbar.fixed-top');
        setAt(anchor(
            box,
            {width: own.width, height: own.height},
            {width: window.innerWidth, height: window.innerHeight},
            bar ? Math.max(0, bar.getBoundingClientRect().bottom) : 0
        ));
    }, [step.cmid, opener, sheet]);

    // The focus moves to the heading once the popover is shown, once per opened step: while it is measuring it is
    // visibility: hidden and focus() is a no-op there.
    useLayoutEffect(() => {
        if ((sheet || at !== null) && focused.current !== step.cmid) {
            focused.current = step.cmid;
            heading.current?.focus();
        }
    }, [sheet, at, step.cmid]);

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
     * Escape closes; Tab past the last control closes and gives the focus back to the bar.
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

    const category = look.categories.get(step.categoryid);
    const state = stateLine(step, look, now);
    const image = covers ? safeImage(step.image) : '';
    const names = category ? <span className="dim-cal__trailcategory">{category.name}</span> : null;
    const when = [stepWhen(step, look), stepOpenEnd(step, look)].filter((part) => part !== '').join(' · ');

    return (
        <div
            ref={dialog}
            className={popClasses(sheet, !sheet && !at, covers, step.state === 'done')}
            role="dialog"
            aria-labelledby={TITLE}
            data-region="dim-cal-trailpopover"
            data-cmid={step.cmid}
            style={placed(at, covers, step, look)}
            onKeyDown={onKeyDown}
        >
            {covers ? (
                <Cover step={step} look={look} image={image} failed={failed} setFailed={setFailed} onClose={onClose} />
            ) : (
                <div className="dim-cal__traildialogtop">
                    {names}
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
            )}
            <div className="dim-cal__trailpopnames">
                {covers ? names : null}
                <h2 ref={heading} className="dim-cal__poptitle" id={TITLE} tabIndex={-1}>{step.name}</h2>
            </div>
            <p className="dim-cal__trailmeta" data-region="dim-cal-trailwhen">
                <span className="visually-hidden">{`${look.strings.calendar_when}: `}</span>
                {when !== '' ? `${when} · ` : ''}
                <span className="dim-cal__trailsource">
                    {step.expected ? look.strings.calendar_trailexpected : look.strings.calendar_trailcoursedates}
                </span>
            </p>
            {step.coursename !== step.name ? (
                <p className="dim-cal__trailmeta" data-region="dim-cal-trailcoursename">
                    <span className="visually-hidden">{`${look.strings.calendar_course}: `}</span>
                    {step.coursename}
                </p>
            ) : null}
            <p className="dim-cal__poprow dim-cal__trailextra" data-region="dim-cal-trailposition">
                <TrailIcon />
                <span>
                    {fill(look.strings.calendar_trailposition, {
                        position: step.position,
                        count: trail.steps.length,
                        trail: trail.name,
                    })}
                </span>
            </p>
            {state !== '' ? <StatePill step={step} state={state} /> : null}
            {step.state === 'locked' && step.reason !== '' ? (
                <p className="dim-cal__popquiet" data-region="dim-cal-trailreason">{step.reason}</p>
            ) : null}
            <div className="dim-cal__popactions dim-cal__trailpopactions">
                <a className="btn btn-secondary" href={safeHref(trail.url, look.home)}>{look.strings.calendar_trailpage}</a>
                <a className="btn btn-primary" href={safeHref(step.url, look.home)}>{mainLabel(step, look.strings)}</a>
            </div>
        </div>
    );
};

export default TrailPopover;
