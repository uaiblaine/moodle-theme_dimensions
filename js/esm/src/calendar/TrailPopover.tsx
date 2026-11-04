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
 * A step's popover in the Trilhas view: its category, its name, "Course 3 of 5 · trail",
 * its dates and where they come from, its state and core's availability text for a learner, "View trail" and "Open
 * course".
 *
 * The event popover's behaviour (EventPopover.tsx), for a step: role="dialog" labelled by the name, not modal; anchored
 * to the bar that opened it by the same rule ({@see anchor}), a bottom sheet on a phone; the focus on its heading once
 * shown; Escape, the close button, a press outside, or Tab past its last control close it and give the focus back to
 * the bar. Its two layout reads are the opener's box and its own, on an opening.
 *
 * It draws a band in the step's category colours (look.ts
 * categoryCover(), else the state's), the category and the name, the state as a pill, a row with the trail's icon for
 * "Course 3 of 5 · trail", a row with the calendar's for the dates and their source, core's reason in a quiet box, and
 * the actions at the end, "View trail" before "Open course".
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
import {safeHref} from './model';
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
 * The popover.
 *
 * @param {object} props See TrailPopoverProps.
 * @returns {object} The dialog.
 */
const TrailPopover = ({look, trail, step, opener, sheet, now, onClose}: TrailPopoverProps) => {
    const dialog = useRef<HTMLDivElement | null>(null);
    const heading = useRef<HTMLHeadingElement | null>(null);
    const focused = useRef<number>(0);
    const [at, setAt] = useState<{top: number, left: number, maxHeight?: number} | null>(null);

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
    const style: CSSProperties = {};
    if (at) {
        style.top = `${at.top}px`;
        style.left = `${at.left}px`;
        if (at.maxHeight !== undefined) {
            style.maxHeight = `${at.maxHeight}px`;
            style.overflowY = 'auto';
        }
    }
    const tone = step.state ? ` dim-cal__trailstate--${step.state}` : '';

    return (
        <div
            ref={dialog}
            className={'dim-cal__pop dim-cal__trailpop' + (sheet ? ' dim-cal__pop--sheet' : '')
                + (!sheet && !at ? ' dim-cal__pop--measuring' : '')}
            role="dialog"
            aria-labelledby={TITLE}
            data-region="dim-cal-trailpopover"
            data-cmid={step.cmid}
            style={style}
            onKeyDown={onKeyDown}
        >
            <div
                className={`dim-cal__popcover dim-cal__trailcover${tone}`}
                style={categoryCover(step.categoryid, look)}
                aria-hidden="true"
            ></div>
            <div className="dim-cal__pophead dim-cal__trailpophead">
                <div className="dim-cal__trailpopnames">
                    {category ? <span className="dim-cal__trailcategory">{category.name}</span> : null}
                    <h2 ref={heading} className="dim-cal__poptitle" id={TITLE} tabIndex={-1}>{step.name}</h2>
                </div>
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
            {state !== '' ? (
                <p className={`dim-cal__trailstate${tone}`} data-region="dim-cal-trailstate">{state}</p>
            ) : null}
            <p className="dim-cal__poprow" data-region="dim-cal-trailposition">
                <TrailIcon />
                <span>
                    {fill(look.strings.calendar_trailposition, {
                        position: step.position,
                        count: trail.steps.length,
                        trail: trail.name,
                    })}
                </span>
            </p>
            <p className="dim-cal__poprow" data-region="dim-cal-trailwhen">
                <i className="fa fa-calendar dim-cal__popicon" aria-hidden="true"></i>
                <span>
                    <span className="visually-hidden">{`${look.strings.calendar_when}: `}</span>
                    {stepWhen(step, look)}
                    <small className="dim-cal__trailsource">
                        {step.expected ? look.strings.calendar_trailexpected : look.strings.calendar_trailcoursedates}
                    </small>
                </span>
            </p>
            {step.coursename !== step.name ? (
                <p className="dim-cal__poprow" data-region="dim-cal-trailcoursename">
                    <i className="fa fa-graduation-cap dim-cal__popicon" aria-hidden="true"></i>
                    <span>
                        <span className="visually-hidden">{`${look.strings.calendar_course}: `}</span>
                        {step.coursename}
                    </span>
                </p>
            ) : null}
            {step.state === 'locked' && step.reason !== '' ? (
                <p className="dim-cal__popquiet" data-region="dim-cal-trailreason">{step.reason}</p>
            ) : null}
            <div className="dim-cal__popactions dim-cal__trailpopactions">
                <a className="btn btn-secondary" href={safeHref(trail.url, look.home)}>{look.strings.calendar_trailpage}</a>
                <a className="btn btn-primary" href={safeHref(step.url, look.home)}>{look.strings.calendar_trailcourse}</a>
            </div>
        </div>
    );
};

export default TrailPopover;
