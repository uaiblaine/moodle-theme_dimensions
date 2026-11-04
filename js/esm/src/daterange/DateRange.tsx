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
 * The one calendar of the showcase, on both shells: a start box and an end box over one popover.
 *
 * The markup is the template category_daterange's, hook for hook (scss/category.scss and the Behat steps read
 * those hooks); the rules are ./model's and there is no timezone arithmetic (./calendar).
 *
 * It is mounted by the listing's mountCalendar(), on both shells, as a node APPENDED
 * to the toolbar's empty dim-daterange region: core's react_autoinit observes added nodes and never
 * attributes. The props are what the server resolved and the client may not compute: id, firstday,
 * today, from, to. It talks to the listing through events on that region, never through a callback:
 *
 * - it sends `dim-daterange:mounted` once it is drawn, and only then does the listing hide the two
 *   native date fields, which stay the form's transport;
 * - it sends `dim-daterange:pick`, detail {from, to}, whenever the period in force changes;
 * - it takes `dim-daterange:set`, detail {from, to}, the period the address says, silently.
 *
 * Without the day the site calls today it draws nothing and says nothing, so the native fields stay
 * exactly as the server drew them: the browser's clock is the one answer this control may not use.
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

/** The events the control and the listing exchange on the toolbar's region, spelled once in listing/events.ts. */
export const EVENTS = CALENDAR;

/** The toolbar's region the control is mounted in, which the listing tells it the period through. */
const REGION = '[data-region="dim-daterange"]';

/** A day cell. */
const DAY = '[data-region="dim-daterange-day"]';

/** The component every string of this control belongs to. */
const COMPONENT = 'theme_dimensions';

/** The strings the control writes, all of them the template category_daterange's own. */
const KEYS = [
    'category_daterange',
    'category_datefrom',
    'category_dateto',
    'category_dateclearfrom',
    'category_dateclearto',
    'category_dateprevmonth',
    'category_datenextmonth',
    'category_dateclear',
    'category_datedone',
    'category_datechoosefrom',
    'category_datechooseto',
] as const;

/** The strings by key. */
type Strings = Record<typeof KEYS[number], string>;

/**
 * Where the popover shows two months rather than one: the stylesheet widens it from the same
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
     * A redraw that changes the months replaces their cells, so the focused day leaves the document,
     * the focus falls to the body, and the focus-out rule below would shut the popover after the first
     * press of a range. The layout effect that answers runs before that rule's timeout can.
     *
     * @returns {void}
     */
    const focusDay = (): void => setFocusing((tick) => tick + 1);

    /**
     * Put the focus on a box: where the reader goes back to when the popover shuts.
     *
     * @param {string} bound FROM or TO.
     * @returns {void}
     */
    const restore = (bound: Bound): void => {
        (bound === TO ? toBox : fromBox).current?.focus();
    };

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
            update(State.set(state.current, detail.from, detail.to));
        };

        /**
         * A press outside the open control applies the draft, as "Done" would: the pointer's way out says
         * "that will do". Only while it is open: shut, the draft is the period in force, so committing it
         * would change nothing and only redraw the control on every press anywhere on the page.
         * Pointerdown rather than click, so that a press meant for a control elsewhere does not have to
         * travel through a popover still on top of it.
         *
         * @param {PointerEvent} event The press.
         * @returns {void}
         */
        const onPointer = (event: PointerEvent): void => {
            if (state.current.open && event.target instanceof Node && !element.contains(event.target)) {
                tell(State.commit(state.current));
            }
        };

        region?.addEventListener(EVENTS.set, onSet);
        document.addEventListener('pointerdown', onPointer);
        element.dispatchEvent(new CustomEvent(EVENTS.mounted, {bubbles: true}));
        hold.current?.resolve();
        hold.current = null;
        return () => {
            region?.removeEventListener(EVENTS.set, onSet);
            document.removeEventListener('pointerdown', onPointer);
        };
    }, [strings]);

    useEffect(() => {
        const query = window.matchMedia(WIDE);

        /**
         * A width that gains or loses the second month redraws an open popover with the focus kept.
         *
         * @returns {void}
         */
        const onWidth = (): void => {
            const next = monthsFor(query);
            months.current = next;
            setCount(next);
            if (state.current.open) {
                update(State.widen(state.current, next));
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
     * A box pressed: open the popover in its mode, or move an open popover to that mode. A box never
     * shuts it: a reader pressing the end box after a start must not lose the start.
     *
     * @param {string} bound The box's bound.
     * @returns {void}
     */
    const onBox = (bound: Bound): void => {
        const current = state.current;
        update(current.open
            ? State.setMode(current, bound, today, months.current)
            : State.open(current, bound, today, months.current));
        focusDay();
    };

    /**
     * A day pressed: the draft moves, nothing is applied.
     *
     * @param {string} iso The day.
     * @returns {void}
     */
    const onDay = (iso: string): void => {
        update(State.press(state.current, iso));
        focusDay();
    };

    /**
     * A month step pressed.
     *
     * @param {number} offset Minus one or one.
     * @returns {void}
     */
    const onStep = (offset: number): void => {
        update(State.step(state.current, offset));
        focusDay();
    };

    /**
     * "Done": the focus back on the box of the mode, the draft applied.
     *
     * @returns {void}
     */
    const onDone = (): void => {
        const current = state.current;
        restore(current.field);
        tell(State.commit(current));
    };

    /**
     * A clearing control pressed: it applies at once. A box's own hides itself once its bound is gone,
     * which would drop the focus to the body and shut the popover with it, so the focus goes to its box.
     *
     * @param {string} bound FROM, TO, or the empty string for the footer's.
     * @returns {void}
     */
    const onClear = (bound: string): void => {
        tell(State.drop(state.current, bound));
        if (bound === FROM || bound === TO) {
            restore(bound);
        }
    };

    /**
     * The keys: Escape shuts the popover, discards the draft and gives the box its focus back; the
     * arrows and the pages walk the days, on a day of the open popover only, so a box keeps its keys.
     *
     * @param {KeyboardEvent} event The key press.
     * @returns {void}
     */
    const onKey = (event: KeyboardEvent<HTMLDivElement>): void => {
        const current = state.current;
        if (event.key === 'Escape') {
            event.preventDefault();
            if (current.open) {
                update(State.close(current));
                restore(current.field);
            }
            return;
        }
        const target = stepOf(event.key, current.focused);
        const onday = event.target instanceof Element && event.target.closest(DAY);
        if (target === null || !current.open || !onday) {
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
     * The keyboard's own way out discards, as Escape does. The timeout lets the focus land first: during
     * focusout the next element is not focused yet, and every move would read as a move out.
     *
     * @returns {void}
     */
    const onBlur = (): void => {
        window.setTimeout(() => {
            const element = root.current;
            if (element && !element.contains(document.activeElement)) {
                update(State.close(state.current));
            }
        }, 0);
    };

    const popid = `dim-daterange-pop-${id}`;
    const both = model.draftfrom !== '' && model.draftto !== '';
    let prompt = '';
    if (model.opened) {
        prompt = model.mode === TO ? strings.category_datechooseto : strings.category_datechoosefrom;
    }

    /**
     * One box and the control that clears it.
     *
     * A box's label is its name while it is empty and leaves the picture once it holds a date; it stays
     * in the box's accessible name either way.
     *
     * @param {string} bound The bound.
     * @param {string} label The box's name.
     * @param {string} clear The clearing control's name.
     * @returns {object} The box.
     */
    const box = (bound: Bound, label: string, clear: string): ReactNode => {
        const value = bound === TO ? model.draftto : model.draftfrom;
        const active = model.open && bound === model.mode ? ' dim-daterange__field--active' : '';
        return (
            <div className="dim-daterange__box">
                <button
                    ref={bound === TO ? toBox : fromBox}
                    className={`form-control dim-daterange__field${active}`}
                    type="button"
                    id={`dim-daterange-${id}-${bound}`}
                    data-action="dim-daterange-open"
                    data-bound={bound}
                    aria-expanded={model.open ? 'true' : 'false'}
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
     * Each bound that is set is a filled end; the days strictly between are tinted only when both are
     * set, because a start or an end alone keeps a period that runs off the calendar.
     *
     * @param {string} iso The day.
     * @returns {object} The day cell.
     */
    const day = (iso: string): ReactNode => {
        const edge = iso === model.draftfrom || iso === model.draftto;
        const inside = both && iso > model.draftfrom && iso < model.draftto;
        const classes = ['dim-daterange__day'];
        if (inside) {
            classes.push('dim-daterange__day--in');
        }
        if (edge) {
            classes.push('dim-daterange__day--edge');
        }
        // The ends of a range join the tint between them; a range of one day is one round end.
        if (both && model.draftfrom !== model.draftto && iso === model.draftfrom) {
            classes.push('dim-daterange__day--start');
        }
        if (both && model.draftfrom !== model.draftto && iso === model.draftto) {
            classes.push('dim-daterange__day--end');
        }
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
                aria-selected={edge || inside ? 'true' : 'false'}
                aria-current={iso === today ? 'date' : undefined}
                onClick={() => onDay(iso)}
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
        const painted = model.opened && !hidden;
        const shown: Month = monthAt(model.cursor, index);
        const captionid = `${popid}-month-${index}`;
        return (
            <div key={index} className="dim-daterange__monthblock" data-region="dim-daterange-monthblock" hidden={hidden}>
                <span className="dim-daterange__month" id={captionid} data-region="dim-daterange-month" aria-live="polite">
                    {painted ? say(firstOf(shown), {year: 'numeric', month: 'long'}, language) : ''}
                </span>
                <div
                    className="dim-daterange__grid"
                    data-region="dim-daterange-grid"
                    role={painted ? 'grid' : undefined}
                    aria-labelledby={painted ? captionid : undefined}
                >
                    {painted ? (
                        <div className="dim-daterange__row dim-daterange__row--head" role="row">
                            {weekdays(weekstart).map((name) => (
                                <span
                                    key={name}
                                    className="dim-daterange__dow"
                                    role="columnheader"
                                    aria-label={say(name, {weekday: 'long'}, language)}
                                >{say(name, {weekday: 'short'}, language)}</span>
                            ))}
                        </div>
                    ) : null}
                    {painted ? weeks(shown, weekstart).map((row, rowindex) => (
                        <div key={`${firstOf(shown)}-${rowindex}`} className="dim-daterange__row" role="row">
                            {row.map((iso, slot) => (iso === '' ? blank(slot) : day(iso)))}
                        </div>
                    )) : null}
                </div>
            </div>
        );
    };

    const set = model.draftfrom !== '' || model.draftto !== '' ? ' dim-daterange--set' : '';
    return (
        <div ref={root} className={`dim-daterange${set}`} data-region="dim-daterange-field" onKeyDown={onKey} onBlur={onBlur}>
            <div className="dim-daterange__boxes" role="group" aria-label={strings.category_daterange}>
                {box(FROM, strings.category_datefrom, strings.category_dateclearfrom)}
                {box(TO, strings.category_dateto, strings.category_dateclearto)}
            </div>
            <div ref={pop} className="dim-daterange__pop" id={popid} data-region="dim-daterange-pop" hidden={!model.open}>
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
                <div className="dim-daterange__months">
                    {month(0)}
                    {month(1)}
                </div>
                <div className="dim-daterange__footer">
                    <button
                        className="btn btn-link btn-sm dim-daterange__footerclear"
                        type="button"
                        data-action="dim-daterange-clear"
                        onClick={() => onClear('')}
                    >{strings.category_dateclear}</button>
                    <button
                        className="btn btn-primary btn-sm dim-daterange__done"
                        type="button"
                        data-action="dim-daterange-done"
                        onClick={onDone}
                    >{strings.category_datedone}</button>
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
