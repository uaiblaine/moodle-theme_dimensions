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
 * The one calendar of the showcase, on both shells: a start box and an end box over the months, inside the date
 * button's layer.
 *
 * The markup is the template category_daterange's, hook for hook (scss/category.scss and the Behat steps read
 * those hooks); the rules are ./model's and there is no timezone arithmetic (./calendar). The layer around it -
 * the heading, the quick options, the foot with "Clear period" and "Show" - is the server's (category_toolbar),
 * and the layer opens and closes as a native disclosure the filter line places: nothing here opens or shuts.
 *
 * It is mounted by the listing's mountCalendar(), on both shells, as a node APPENDED
 * to the layer's empty dim-daterange region: core's react_autoinit observes added nodes and never
 * attributes. The props are what the server resolved and the client may not compute: id, firstday,
 * today, from, to. It talks to the listing through events on that region, never through a callback:
 *
 * - it sends `dim-daterange:mounted` once it is drawn, and only then does the listing hide the two
 *   native date fields, which stay the form's transport;
 * - it sends `dim-daterange:pick`, detail {from, to}, whenever the period in force changes: on every press
 *   of a day and every clear, so the list is refreshed at once while the layer stays open;
 * - it takes `dim-daterange:set`, detail {from, to}, the period the address says, silently.
 *
 * Without the day the site calls today it draws nothing and says nothing ({@see DateRange}).
 *
 * @module     theme_dimensions/daterange/DateRange
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useLayoutEffect, useRef, useState} from 'react';
import type {KeyboardEvent, ReactNode} from 'react';
import Pending from '@moodle/lms/core/pending';
import {getStrings} from '@moodle/lms/core/stringUtils';
import {load} from '../amd';
import {WEEK, firstOf, monthAt, parse, say, shift, shiftMonth, weekdays, weeks} from './calendar';
import type {Month} from './calendar';
import {FROM, TO} from './model';
import type {Applied, Bound, Model} from './model';
import * as State from './model';
import {CALENDAR} from '../listing/events';

/** The events the control and the listing exchange on the layer's region, spelled once in listing/events.ts. */
export const EVENTS = CALENDAR;

/** The layer's region the control is mounted in, which the listing tells it the period through. */
const REGION = '[data-region="dim-daterange"]';

/** A day cell. */
const DAY = '[data-region="dim-daterange-day"]';

/** The component every string of this control belongs to. */
const COMPONENT = 'theme_dimensions';

/** The strings the control writes: the template category_daterange's, and the two prompts between the turns. */
const KEYS = [
    'category_daterange',
    'category_datefrom',
    'category_dateto',
    'category_dateclearfrom',
    'category_dateclearto',
    'category_dateprevmonth',
    'category_datenextmonth',
    'category_datechoosefrom',
    'category_datechooseto',
] as const;

/** The strings by key. */
type Strings = Record<typeof KEYS[number], string>;

/**
 * Where the layer shows two months rather than one: the stylesheet widens the month row from the same
 * breakpoint (category_scss_test holds the two numbers together), and a
 * month the stylesheet hid while the focus walked into it would take the focus out of the control.
 */
const WIDE = '(min-width: 768px)';

/** The Intl options every date of the boxes is written with. */
const MEDIUM: Intl.DateTimeFormatOptions = {dateStyle: 'medium'};

/**
 * Where a key moves the day stood on: the arrows walk the days and the pages walk the months. Enter and
 * Space are absent on purpose: a day is a real button and answers both, and a handler of ours would
 * pick the same day twice.
 *
 * @param {string} key The key pressed.
 * @param {string} iso The day stood on.
 * @returns {string|null} The day to move to, the empty string when the day stood on is no date, or
 *   null when the key moves nothing.
 */
const stepOf = (key: string, iso: string): string | null => {
    switch (key) {
        case 'ArrowLeft':
            return shift(iso, -1);
        case 'ArrowRight':
            return shift(iso, 1);
        case 'ArrowUp':
            return shift(iso, -WEEK);
        case 'ArrowDown':
            return shift(iso, WEEK);
        case 'PageUp':
            return shiftMonth(iso, -1);
        case 'PageDown':
            return shiftMonth(iso, 1);
        default:
            return null;
    }
};

/** What the server hands the control, as data-react-props carries it. */
type DateRangeProps = {
    id: number | string,
    firstday: number | string,
    today: string,
    from?: string,
    to?: string,
};

/**
 * The weekday a week begins on: the site's, and Sunday when what arrived is no weekday.
 *
 * @param {number|string} value The server's value.
 * @returns {number} 0 for Sunday to 6.
 */
const weekStart = (value: unknown): number => {
    const day = Number(value);
    return Number.isInteger(day) && day >= 0 && day < WEEK ? day : 0;
};

/**
 * How many months a width shows.
 *
 * @param {MediaQueryList} query The wide media query.
 * @returns {number} One or two.
 */
const monthsFor = (query: MediaQueryList): number => (query.matches ? 2 : 1);

/**
 * Show core's message for a failure: the strings could not be fetched, so nothing is drawn.
 *
 * @param {Error} failure What failed.
 * @returns {Promise} Settles once the message is shown.
 */
const report = (failure: unknown): Promise<void> =>
    load('core/notification').then((notification) => notification.exception(failure));

/**
 * One empty square, so that a week's columns line up with the names above them.
 *
 * @param {number} slot Its place in the week, which keys it.
 * @returns {object} The blank cell.
 */
const blank = (slot: number): ReactNode => (
    <span key={`blank-${slot}`} className="dim-daterange__day dim-daterange__day--blank" role="gridcell"></span>
);

/**
 * The control, once the day the site calls today is known to be a date.
 *
 * @param {object} props The server's values; see DateRangeProps.
 * @returns {object|null} The control, or nothing until its strings have arrived.
 */
const Calendar = ({id, firstday, today, from, to}: DateRangeProps) => {
    const [strings, setStrings] = useState<Strings | null>(null);
    const [model, setModel] = useState<Model>(() => State.initial(from, to, today));
    const [count, setCount] = useState<number>(() => monthsFor(window.matchMedia(WIDE)));
    const [focusing, setFocusing] = useState<number>(0);

    // What the listeners read: the latest state, which a render may not have caught up with yet.
    const state = useRef<Model>(model);
    const months = useRef<number>(count);
    const hold = useRef<Pending | null>(null);
    const root = useRef<HTMLDivElement>(null);
    const pop = useRef<HTMLDivElement>(null);
    const fromBox = useRef<HTMLButtonElement>(null);
    const toBox = useRef<HTMLButtonElement>(null);

    const weekstart = weekStart(firstday);
    const language = document.documentElement.lang || undefined;

    /**
     * Take a new state, for the listeners at once and for the next render.
     *
     * @param {object} next The new state.
     * @returns {void}
     */
    const update = (next: Model): void => {
        state.current = next;
        setModel(next);
    };

    /**
     * Ask for the focus to be put back on the day stood on once the grids are redrawn.
     *
     * A redraw that changes the months replaces their cells, so the focused day leaves the document and the
     * focus would fall to the body; the layout effect that answers puts it on the day stood on.
     *
     * @returns {void}
     */
    const focusDay = (): void => setFocusing((tick) => tick + 1);

    /**
     * Take a change of the period in force, and tell the listing when it really changed.
     *
     * The one place a period leaves the control.
     *
     * @param {object} applied The new state and whether the period changed.
     * @returns {void}
     */
    const tell = (applied: Applied): void => {
        update(applied.model);
        if (applied.changed && root.current) {
            root.current.dispatchEvent(new CustomEvent(EVENTS.pick, {
                bubbles: true,
                detail: {from: applied.model.from, to: applied.model.to},
            }));
        }
    };

    useEffect(() => {
        hold.current = new Pending('theme_dimensions/daterange:mount');
        getStrings(KEYS.map((key) => ({key: key, component: COMPONENT})))
            .then((values) => {
                setStrings(Object.fromEntries(KEYS.map((key, index) => [key, values[index]])) as Strings);
                return values;
            })
            .catch((failure) => {
                hold.current?.resolve();
                hold.current = null;
                report(failure);
            });
        return () => {
            hold.current?.resolve();
            hold.current = null;
        };
    }, []);

    // A layout effect, so the listing hides the native fields in the same frame the boxes are painted.
    useLayoutEffect(() => {
        const element = root.current;
        if (!strings || !element) {
            return undefined;
        }
        const region = element.closest(REGION);

        /**
         * Take the period the address says, without telling anybody.
         *
         * @param {Event} event The listing's set event.
         * @returns {void}
         */
        const onSet = (event: Event): void => {
            const detail = (event as CustomEvent).detail || {};
            update(State.set(state.current, detail.from, detail.to, months.current));
        };

        region?.addEventListener(EVENTS.set, onSet);
        element.dispatchEvent(new CustomEvent(EVENTS.mounted, {bubbles: true}));
        hold.current?.resolve();
        hold.current = null;
        return () => {
            region?.removeEventListener(EVENTS.set, onSet);
        };
    }, [strings]);

    useEffect(() => {
        const query = window.matchMedia(WIDE);

        /**
         * A width that gains or loses the second month redraws the months with the day stood on kept on screen,
         * and the focus on it when it was in the grids.
         *
         * @returns {void}
         */
        const onWidth = (): void => {
            const next = monthsFor(query);
            months.current = next;
            setCount(next);
            update(State.widen(state.current, next));
            if (pop.current && pop.current.contains(document.activeElement)) {
                focusDay();
            }
        };
        query.addEventListener('change', onWidth);
        return () => query.removeEventListener('change', onWidth);
    }, []);

    useLayoutEffect(() => {
        if (focusing === 0 || !pop.current) {
            return;
        }
        const cell = pop.current.querySelector<HTMLElement>(`[data-date="${state.current.focused}"]`)
            || pop.current.querySelector<HTMLElement>(DAY);
        cell?.focus();
    }, [focusing]);

    if (!strings) {
        return null;
    }

    /**
     * A box pressed: it becomes the active one, and the grid stands on its day with the focus on it.
     *
     * @param {string} bound The box's bound.
     * @returns {void}
     */
    const onBox = (bound: Bound): void => {
        update(State.setMode(state.current, bound, today, months.current));
        focusDay();
    };

    /**
     * A day pressed: the active box's bound is set and applied at once, and the other box becomes the active one.
     *
     * @param {string} iso The day.
     * @returns {void}
     */
    const onDay = (iso: string): void => {
        tell(State.press(state.current, iso));
        focusDay();
    };

    /**
     * A month turn pressed.
     *
     * @param {number} offset Minus one or one.
     * @returns {void}
     */
    const onStep = (offset: number): void => {
        update(State.step(state.current, offset));
        focusDay();
    };

    /**
     * A box's clear pressed: it applies at once. It hides itself once its bound is gone, which would drop the
     * focus to the body, so the focus goes to its box.
     *
     * @param {string} bound FROM or TO.
     * @returns {void}
     */
    const onClear = (bound: Bound): void => {
        tell(State.drop(state.current, bound));
        (bound === TO ? toBox : fromBox).current?.focus();
    };

    /**
     * The arrows and the pages walk the days, on a day only, so a box keeps its keys. Escape is the filter
     * line's: it closes the layer and gives the date button its focus back.
     *
     * @param {KeyboardEvent} event The key press.
     * @returns {void}
     */
    const onKey = (event: KeyboardEvent<HTMLDivElement>): void => {
        const current = state.current;
        const target = stepOf(event.key, current.focused);
        const onday = event.target instanceof Element && event.target.closest(DAY);
        if (target === null || !onday) {
            return;
        }
        event.preventDefault();
        const next = State.moveTo(current, target, months.current);
        if (next) {
            update(next);
            focusDay();
        }
    };

    /**
     * The pointer over a day, or off the months: the band previews from the other bound to that day.
     *
     * @param {string} iso The day, or the empty string for none.
     * @returns {void}
     */
    const onHover = (iso: string): void => {
        const next = State.hover(state.current, iso);
        if (next !== state.current) {
            update(next);
        }
    };

    const popid = `dim-daterange-pop-${id}`;
    const prompt = model.mode === TO ? strings.category_datechooseto : strings.category_datechoosefrom;
    const band = State.drawn(model);
    const banded = band.from !== '' && band.to !== '' && band.from !== band.to;

    /**
     * One box and the control that clears it.
     *
     * A box's label is its name while it is empty and leaves the picture once it holds a date; it stays
     * in the box's accessible name either way. The active box is the pressed one.
     *
     * @param {string} bound The bound.
     * @param {string} label The box's name.
     * @param {string} clear The clearing control's name.
     * @returns {object} The box.
     */
    const box = (bound: Bound, label: string, clear: string): ReactNode => {
        const value = bound === TO ? model.to : model.from;
        const active = bound === model.mode;
        return (
            <div className="dim-daterange__box">
                <button
                    ref={bound === TO ? toBox : fromBox}
                    className={`dim-daterange__field${active ? ' dim-daterange__field--active' : ''}`}
                    type="button"
                    id={`dim-daterange-${id}-${bound}`}
                    data-action="dim-daterange-open"
                    data-bound={bound}
                    aria-pressed={active ? 'true' : 'false'}
                    aria-controls={popid}
                    onClick={() => onBox(bound)}
                >
                    <i className="fa fa-calendar-days dim-daterange__icon" aria-hidden="true"></i>
                    <span
                        className={`dim-daterange__label${value === '' ? '' : ' visually-hidden'}`}
                        data-region="dim-daterange-label"
                    >{label}</span>
                    <span className="dim-daterange__value" data-region="dim-daterange-value">{say(value, MEDIUM, language)}</span>
                </button>
                <button
                    className="dim-daterange__clear"
                    type="button"
                    data-action="dim-daterange-clear"
                    data-bound={bound}
                    hidden={value === ''}
                    onClick={() => onClear(bound)}
                >
                    <i className="fa fa-xmark" aria-hidden="true"></i><span className="visually-hidden">{clear}</span>
                </button>
            </div>
        );
    };

    /**
     * One day of a month, as the button that picks it.
     *
     * The drawn ends are filled and the days strictly between them are a band; the drawn period is the one in
     * force, or the one a hovered day previews ({@see State.drawn}). A bound alone is one end and no band,
     * because a start or an end alone keeps a period that runs off the calendar. What is selected is the period
     * in force, never the preview.
     *
     * @param {string} iso The day.
     * @returns {object} The day cell.
     */
    const day = (iso: string): ReactNode => {
        const classes = ['dim-daterange__day'];
        if (banded && iso > band.from && iso < band.to) {
            classes.push('dim-daterange__day--between');
        }
        if (iso === band.from || iso === band.to) {
            classes.push('dim-daterange__day--end');
        }
        const inforce = iso === model.from || iso === model.to
            || (model.from !== '' && model.to !== '' && iso > model.from && iso < model.to);
        return (
            <button
                key={iso}
                type="button"
                className={classes.join(' ')}
                role="gridcell"
                data-region="dim-daterange-day"
                data-date={iso}
                aria-label={say(iso, {dateStyle: 'long'}, language)}
                tabIndex={iso === model.focused ? 0 : -1}
                aria-selected={inforce ? 'true' : 'false'}
                aria-current={iso === today ? 'date' : undefined}
                onClick={() => onDay(iso)}
                onPointerEnter={() => onHover(iso)}
            >{String(Number(iso.slice(8)))}</button>
        );
    };

    /**
     * One month: its caption, and its grid with the weekday names and the weeks.
     *
     * The second month is hidden where only one fits, and then draws no day the focus could be sent to.
     *
     * @param {number} index 0 or 1.
     * @returns {object} The month's block.
     */
    const month = (index: number): ReactNode => {
        const hidden = index >= count;
        const shown: Month = monthAt(model.cursor, index);
        const captionid = `${popid}-month-${index}`;
        return (
            <div key={index} className="dim-daterange__monthblock" data-region="dim-daterange-monthblock" hidden={hidden}>
                <span className="dim-daterange__month" id={captionid} data-region="dim-daterange-month" aria-live="polite">
                    {hidden ? '' : say(firstOf(shown), {year: 'numeric', month: 'long'}, language)}
                </span>
                <div
                    className="dim-daterange__grid"
                    data-region="dim-daterange-grid"
                    role={hidden ? undefined : 'grid'}
                    aria-labelledby={hidden ? undefined : captionid}
                >
                    {hidden ? null : (
                        <div className="dim-daterange__row dim-daterange__row--head" role="row">
                            {weekdays(weekstart).map((name) => (
                                <span
                                    key={name}
                                    className="dim-daterange__dow"
                                    role="columnheader"
                                    aria-label={say(name, {weekday: 'long'}, language)}
                                >{say(name, {weekday: 'narrow'}, language)}</span>
                            ))}
                        </div>
                    )}
                    {hidden ? null : weeks(shown, weekstart).map((row, rowindex) => (
                        <div key={`${firstOf(shown)}-${rowindex}`} className="dim-daterange__row" role="row">
                            {row.map((iso, slot) => (iso === '' ? blank(slot) : day(iso)))}
                        </div>
                    ))}
                </div>
            </div>
        );
    };

    const set = model.from !== '' || model.to !== '' ? ' dim-daterange--set' : '';
    return (
        <div ref={root} className={`dim-daterange${set}`} data-region="dim-daterange-field" onKeyDown={onKey}>
            <div className="dim-daterange__boxes" role="group" aria-label={strings.category_daterange}>
                {box(FROM, strings.category_datefrom, strings.category_dateclearfrom)}
                {box(TO, strings.category_dateto, strings.category_dateclearto)}
            </div>
            <div ref={pop} className="dim-daterange__pop" id={popid} data-region="dim-daterange-pop">
                <div className="dim-daterange__nav">
                    <button
                        className="dim-daterange__step"
                        type="button"
                        data-action="dim-daterange-prev"
                        onClick={() => onStep(-1)}
                    >
                        <i className="fa fa-chevron-left" aria-hidden="true"></i>
                        <span className="visually-hidden">{strings.category_dateprevmonth}</span>
                    </button>
                    <p className="dim-daterange__prompt" data-region="dim-daterange-prompt" aria-live="polite">{prompt}</p>
                    <button
                        className="dim-daterange__step"
                        type="button"
                        data-action="dim-daterange-next"
                        onClick={() => onStep(1)}
                    >
                        <i className="fa fa-chevron-right" aria-hidden="true"></i>
                        <span className="visually-hidden">{strings.category_datenextmonth}</span>
                    </button>
                </div>
                <div className="dim-daterange__months" onPointerLeave={() => onHover('')}>
                    {month(0)}
                    {month(1)}
                </div>
            </div>
        </div>
    );
};

/**
 * The control, or nothing at all without the day the site calls today.
 *
 * Without it there is no month to open on and no cell to mark, and the browser's own clock is the one
 * answer this control may not use; drawing nothing sends no mounted event, so the native fields stay.
 *
 * @param {object} props The server's values; see DateRangeProps.
 * @returns {object|null} The control, or nothing.
 */
const DateRange = (props: DateRangeProps) => (parse(props.today) ? <Calendar {...props} /> : null);

export default DateRange;
