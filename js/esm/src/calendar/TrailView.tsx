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
 * The Trilhas view (docs/trail/PLAN.md section 7): the month's trails, one row per trail and its steps under it.
 *
 * - On a desktop, a grid of the month's days: each step a bar from its start to its end (trails.ts barOf()), a
 *   diamond on an expected completion date, a bar that passes the month's end continued by a chip in the last column
 *   ("Dec →"), one that began earlier marked at the left edge, an open one fading out; the trail's own row carries
 *   its span (trails.ts trailBarOf()). Weekends are hatched and today is a line down the rows. R3 (PROPOSAL.md section
 *   33): the days have a fixed width and only they scroll, in TrailScroll.tsx, the trail column and the last column held
 *   still; a trail's chevron folds its steps away (the view's own state, never saved); the legend follows the grid.
 * - On a phone, an agenda: each trail in a card, a learner's progress as one segment per step, the steps in order with
 *   their dates, a "Today" line between the steps behind and the steps ahead (trails.ts todayIndex()).
 * - For a learner of the trail each step carries its state (done, current, late, locked, upcoming) as a class and
 *   data-state from that fixed vocabulary, and the trail's row counts the steps done; for anyone else the bars are the
 *   trail colour, the schedule alone. The event types' colours never reach this view.
 * - A step opens its popover (TrailPopover.tsx) on a press, Enter or Space: the bars and the agenda's rows are buttons
 *   named by the step; the last column's chip repeats its bar for the pointer and is out of the tab order and the tree.
 *
 * Its ONE ResizeObserver watches the frame it is drawn in and reports the frame's width alone, the grids' rule
 * (MonthGrid.tsx PHONE): the frame's width does not depend on what it holds, so the phone decision cannot feed back
 * into the measure. Nothing is measured here, and no layout is read to place a bar: the bars, the weekends and today
 * are grid columns or multiples of the day's width the stylesheet declares; the scroller's reads are TrailScroll's.
 *
 * @module     theme_dimensions/calendar/TrailView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Fragment, useCallback, useEffect, useRef, useState} from 'react';
import type {CSSProperties, ReactNode} from 'react';
import {say, shiftMonth} from '../daterange/calendar';
import {isWeekend} from './days';
import type {Look} from './look';
import {safeHref} from './model';
import {fill} from './strings';
import type {Strings} from './strings';
import TrailPopover, {TrailIcon, stateLabel, stateLine, stepWhen} from './TrailPopover';
import TrailScroll from './TrailScroll';
import {barOf, beyondOf, monthDays, todayIndex, trailBarOf} from './trails';
import type {StepState, Trail, TrailAnswer, TrailFilter, TrailStep} from './trails';

/** What the view is handed. */
type TrailViewProps = {
    look: Look,
    month: string,
    answer: TrailAnswer,
    today: string,
    now: number,
    phone: boolean,
    captionid: string,
    chosen?: number,
    onWidth: (width: number) => void,
};

/** The open popover: the trail, the step and the element that opened it. */
type Popped = {trail: number, cmid: number, opener: HTMLElement};

/** The short month of the "next month" column. */
const NEXT_MONTH: Intl.DateTimeFormatOptions = {month: 'short'};

/** The long month of its accessible name. */
const LONG_MONTH: Intl.DateTimeFormatOptions = {month: 'long', year: 'numeric'};

/** A day's letter in the header. */
const WEEKDAY: Intl.DateTimeFormatOptions = {weekday: 'narrow'};

/** A short day: "Dec 10". */
const SHORT_DAY: Intl.DateTimeFormatOptions = {day: 'numeric', month: 'short'};

/** The states a learner's legend names, in the mockup's order. */
const LEGEND: StepState[] = ['done', 'current', 'upcoming', 'locked', 'late'];

/**
 * A step's accessible name: its name, its dates and its state.
 *
 * @param {object} step The step.
 * @param {object} look The page's look.
 * @param {number} now Now, in seconds.
 * @returns {string}
 */
const stepLabel = (step: TrailStep, look: Look, now: number): string =>
    [step.name, stepWhen(step, look), stateLine(step, look, now)].filter((part) => part !== '').join(', ');

/**
 * The trail's progress for a learner ("2 of 5 done"), else ''.
 *
 * @param {object} trail The trail.
 * @param {object} look The page's look.
 * @returns {string}
 */
const progressOf = (trail: Trail, look: Look): string => (trail.enrolled
    ? fill(look.strings.calendar_trailprogress, {done: trail.done, count: trail.steps.length})
    : '');

/**
 * A count of courses: "1 course", "5 courses".
 *
 * @param {object} strings The page's strings.
 * @param {number} count The count.
 * @returns {string}
 */
const coursesOf = (strings: Strings, count: number): string => (count === 1
    ? strings.landing_trail_onecourse
    : fill(strings.landing_trail_coursecount, count));

/**
 * The trail's line under its name: "Enrolled · 2 of 5 done" for a learner, "Not enrolled · 2 courses" otherwise.
 *
 * @param {object} trail The trail.
 * @param {object} look The page's look.
 * @returns {string}
 */
const metaOf = (trail: Trail, look: Look): string => fill(look.strings.landing_trail_whenjoin, trail.enrolled
    ? {a: look.strings.calendar_trailenrolled, b: progressOf(trail, look)}
    : {a: look.strings.calendar_trailnotenrolled, b: coursesOf(look.strings, trail.steps.length)});

/**
 * The toolbar's count: "1 trail · 5 courses".
 *
 * @param {object} strings The page's strings.
 * @param {object} answer The trails answer.
 * @returns {string}
 */
export const countOf = (strings: Strings, answer: TrailAnswer): string => fill(strings.landing_trail_whenjoin, {
    a: answer.trails.length === 1 ? strings.calendar_trailcountone : fill(strings.calendar_trailcountmany, answer.trails.length),
    b: coursesOf(strings, answer.trails.reduce((sum, trail) => sum + trail.steps.length, 0)),
});

/**
 * A date's parts as one compact line: a literal holding a word ("4 de dez." in Portuguese) gives way to a space, so the
 * last column's 76px hold it ("4 dez."); the order and the month's spelling stay the language's.
 *
 * @param {Array} parts The formatter's parts.
 * @returns {string}
 */
const compact = (parts: Intl.DateTimeFormatPart[]): string => parts
    .map((part) => (part.type === 'literal' && /\p{L}/u.test(part.value) ? ' ' : part.value))
    .join('').replace(/\s+/g, ' ').trim();

/**
 * A day as a short compact date ("Dec 10", "10 dez.").
 *
 * @param {string} iso The day, YYYY-MM-DD.
 * @param {string|undefined} language The language tag.
 * @returns {string}
 */
const shortOf = (iso: string, language: string | undefined): string => {
    try {
        return compact(new Intl.DateTimeFormat(language, {...SHORT_DAY, timeZone: 'UTC'})
            .formatToParts(new Date(`${iso}T00:00:00Z`)));
    } catch (error) {
        return say(iso, SHORT_DAY, language);
    }
};

/**
 * Two days as one short compact range ("Dec 1 – 18", "1–18 dez."), the language's own spelling where Intl has one.
 *
 * @param {string} from The first day, YYYY-MM-DD.
 * @param {string} to The last day, YYYY-MM-DD.
 * @param {string|undefined} language The language tag.
 * @returns {string}
 */
const shortRange = (from: string, to: string, language: string | undefined): string => {
    try {
        return compact(new Intl.DateTimeFormat(language, {...SHORT_DAY, timeZone: 'UTC'})
            .formatRangeToParts(new Date(`${from}T00:00:00Z`), new Date(`${to}T00:00:00Z`)));
    } catch (error) {
        return `${shortOf(from, language)} – ${shortOf(to, language)}`;
    }
};

/** What the view's filters are handed: the trail choice and "Só as minhas", then the page's own controls. */
type TrailFiltersProps = {
    strings: Strings,
    filter: TrailFilter,
    answer: TrailAnswer | null,
    onChange: (fields: Partial<TrailFilter>) => void,
    children?: ReactNode,
};

/**
 * The Trilhas view's toolbar row: "Trilha" (every trail the answer offers, "Todas as trilhas" first) drawn as a button
 * with the trail's icon over a real select, "Só as minhas" as a toggle button, the page's category picker and "Clear
 * filters", and the count of what is drawn at the end. The event types are not part of it.
 *
 * @param {object} props See TrailFiltersProps.
 * @returns {object} The row.
 */
export const TrailFilters = ({strings, filter, answer, onChange, children}: TrailFiltersProps) => (
    <div className="dim-cal__toolbar dim-cal__trailtools" data-region="dim-cal-trailfilters">
        <label className={`dim-cal__trailpick${filter.trail > 0 ? ' dim-cal__trailpick--chosen' : ''}`}>
            <TrailIcon />
            <span className="visually-hidden">{strings.calendar_trail}</span>
            <select
                className="dim-cal__trailselect"
                data-action="dim-cal-trail"
                value={String(filter.trail)}
                onChange={(event) => onChange({trail: Number(event.currentTarget.value)})}
            >
                <option value="0">{strings.calendar_trailall}</option>
                {(answer ? answer.options : []).map((option) => (
                    <option key={option.id} value={String(option.id)}>{option.name}</option>
                ))}
            </select>
            <i className="fa fa-chevron-down dim-cal__trailcaret" aria-hidden="true"></i>
        </label>
        <button
            type="button"
            className="btn btn-secondary dim-cal__trailmine"
            data-action="dim-cal-trailmine"
            aria-pressed={filter.mine}
            onClick={() => onChange({mine: !filter.mine})}
        >
            <i className="fa fa-user" aria-hidden="true"></i>
            {strings.calendar_trailmine}
        </button>
        {children}
        <span className="dim-cal__spacer"></span>
        {answer && answer.trails.length > 0 ? (
            <span className="dim-cal__trailcount" data-region="dim-cal-trailcount">{countOf(strings, answer)}</span>
        ) : null}
    </div>
);

/**
 * The view.
 *
 * @param {object} props See TrailViewProps.
 * @returns {object} The region.
 */
const TrailView = ({look, month, answer, today, now, phone, captionid, chosen = 0, onWidth}: TrailViewProps) => {
    const root = useRef<HTMLElement | null>(null);
    const width = useRef<(width: number) => void>(onWidth);
    width.current = onWidth;
    const [popped, setPopped] = useState<Popped | null>(null);
    const [folded, setFolded] = useState<number[]>([]);

    useEffect(() => {
        const frame = root.current?.parentElement || null;
        if (!frame || typeof ResizeObserver === 'undefined') {
            return undefined;
        }
        const observer = new ResizeObserver((entries) => {
            entries.forEach((entry) => {
                // The phone decision reads the frame's width only, as the grids' do.
                if (entry.target === frame && entry.contentRect.width > 0) {
                    width.current(entry.contentRect.width);
                }
            });
        });
        observer.observe(frame);
        return () => observer.disconnect();
    }, []);

    // A new month or a new answer closes the popover: its step may be gone.
    useEffect(() => setPopped(null), [answer]);

    const close = useCallback((refocus: boolean): void => {
        setPopped((was) => {
            if (was && refocus) {
                was.opener.focus();
            }
            return null;
        });
    }, []);

    /**
     * Fold a trail's steps away, or unfold them: the view's own state, never saved.
     *
     * @param {number} id The trail.
     * @returns {void}
     */
    const fold = (id: number): void => {
        setFolded((now) => (now.includes(id) ? now.filter((one) => one !== id) : [...now, id]));
    };

    const days = monthDays(month);
    const next = shiftMonth(days[0], 1);
    const todayat = days.indexOf(today);
    const columns = {'--dim-cal-trail-days': String(days.length)} as CSSProperties;
    const poppedtrail = popped ? answer.trails.find((trail) => trail.id === popped.trail) : undefined;
    const poppedstep = poppedtrail?.steps.find((step) => step.cmid === popped?.cmid);
    const learner = answer.trails.some((trail) => trail.enrolled);

    /**
     * A short day of an instant, in the viewer's timezone.
     *
     * @param {number} timestamp The instant, in seconds.
     * @returns {string}
     */
    const shortDay = (timestamp: number): string => shortOf(look.zone.day(timestamp), look.language);

    /**
     * The agenda's date of a step: its end, else its start, else nothing.
     *
     * @param {object} step The step.
     * @returns {string}
     */
    const stepDay = (step: TrailStep): string => {
        const at = step.end > 0 ? step.end : step.start;
        return at > 0 ? shortDay(at) : '';
    };

    /**
     * The button that opens a step's popover. The quiet one (the last column's chip) repeats a bar for the pointer: it
     * is out of the tab order and of the accessibility tree, the bar being the step's named control.
     *
     * @param {object} trail The trail.
     * @param {object} step The step.
     * @param {string} className Its classes.
     * @param {object} style Its grid placement.
     * @param {object} content What it draws.
     * @param {boolean} quiet Whether it repeats another control.
     * @returns {object}
     */
    const opener = (trail: Trail, step: TrailStep, className: string, style: CSSProperties | undefined, content: ReactNode,
        quiet: boolean = false) => (
        <button
            type="button"
            className={className}
            style={style}
            aria-label={quiet ? undefined : stepLabel(step, look, now)}
            aria-expanded={quiet ? undefined : popped?.cmid === step.cmid}
            aria-hidden={quiet ? 'true' : undefined}
            tabIndex={quiet ? -1 : undefined}
            data-region={quiet ? 'dim-cal-trailchip' : 'dim-cal-trailstep'}
            data-cmid={step.cmid}
            data-state={step.state || undefined}
            onClick={(event) => setPopped({trail: trail.id, cmid: step.cmid, opener: event.currentTarget})}
        >{content}</button>
    );

    /**
     * One step's row of the desktop grid: its number and name, its bar, its diamond and what lies past the month.
     *
     * @param {object} trail The trail.
     * @param {object} step The step.
     * @returns {object}
     */
    const stepRow = (trail: Trail, step: TrailStep) => {
        const bar = barOf(step, days, look.zone);
        const beyond = beyondOf(step, days, look.zone);
        const tone = step.state ? `--${step.state}` : '';
        let drawn = null;
        let diamond = null;
        if (bar) {
            const continued = bar.after && !bar.open;
            const classes = 'dim-cal__trailbar' + (tone ? ` dim-cal__trailbar${tone}` : '')
                + (bar.before ? ' dim-cal__trailbar--before' : '')
                + (continued ? ' dim-cal__trailbar--after' : '')
                + (bar.open ? ' dim-cal__trailbar--open' : '')
                + (bar.milestone ? ' dim-cal__trailbar--milestone' : '')
                + (step.next ? ' dim-cal__trailbar--next' : '');
            drawn = opener(trail, step, classes, {gridColumn: `${bar.from + 2} / ${bar.to + 3}`, gridRow: '1'}, step.name);
            if (bar.milestone) {
                diamond = (
                    <span
                        className={'dim-cal__traildiamond' + (tone ? ` dim-cal__traildiamond${tone}` : '')}
                        style={{gridColumn: String(bar.to + 2), gridRow: '1'}}
                        aria-hidden="true"
                    ></span>
                );
            }
        }
        let chip = '';
        if (beyond === 'continues') {
            chip = `→ ${shortDay(step.end)}`;
        } else if (beyond === 'after') {
            chip = shortRange(look.zone.day(step.start), look.zone.day(step.end), look.language);
        }
        return (
            <div className="dim-cal__trailrow dim-cal__trailrow--step" key={step.cmid} data-cmid={step.cmid}>
                <span className="dim-cal__traillabel dim-cal__trailsteplabel">
                    <span className={'dim-cal__trailnum' + (tone ? ` dim-cal__trailnum${tone}` : '')} aria-hidden="true">
                        {step.position}
                    </span>
                    <span className="dim-cal__trailname">{step.name}</span>
                </span>
                {drawn}
                {diamond}
                <span className="dim-cal__trailspill">
                    {chip !== ''
                        ? opener(trail, step, 'dim-cal__trailchip' + (tone ? ` dim-cal__trailchip${tone}` : ''), undefined,
                            chip, true)
                        : null}
                </span>
            </div>
        );
    };

    /**
     * One trail of the desktop grid: its row (the fold, the name, the line under it, its span) and its steps.
     *
     * @param {object} trail The trail.
     * @returns {object}
     */
    const trailRows = (trail: Trail) => {
        const open = !folded.includes(trail.id);
        const span = trailBarOf(trail, days, look.zone);
        const steps = `dim-cal-trailsteps-${trail.id}`;
        return (
            <div className="dim-cal__trail" key={trail.id} role="group" aria-label={trail.name} data-trail={trail.id}>
                <div
                    className={'dim-cal__trailrow dim-cal__trailrow--trail'
                        + (chosen === trail.id ? ' dim-cal__trailrow--chosen' : '')}
                >
                    <span className="dim-cal__traillabel dim-cal__trailcaption">
                        <button
                            type="button"
                            className="dim-cal__trailfold"
                            data-action="dim-cal-trailfold"
                            aria-expanded={open}
                            aria-controls={steps}
                            aria-label={fill(look.strings.calendar_trailtoggle, trail.name)}
                            onClick={() => fold(trail.id)}
                        >
                            <i className="fa fa-chevron-right" aria-hidden="true"></i>
                        </button>
                        <span className="dim-cal__trailnames">
                            <a className="dim-cal__trailname dim-cal__trailname--trail" href={safeHref(trail.url, look.home)}>
                                {trail.name}
                            </a>
                            <small className="dim-cal__trailmeta" data-region="dim-cal-trailmeta">{metaOf(trail, look)}</small>
                        </span>
                    </span>
                    {span ? (
                        <span
                            className={'dim-cal__trailspan' + (span.before ? ' dim-cal__trailspan--before' : '')
                                + (span.after ? ' dim-cal__trailspan--after' : '')}
                            style={{gridColumn: `${span.from + 2} / ${span.to + 3}`, gridRow: '1'}}
                            aria-hidden="true"
                        ></span>
                    ) : null}
                    {span && !span.after ? (
                        <span
                            className="dim-cal__traildiamond dim-cal__traildiamond--trail"
                            style={{gridColumn: String(span.to + 2), gridRow: '1'}}
                            aria-hidden="true"
                        ></span>
                    ) : null}
                    <span className="dim-cal__trailspill dim-cal__trailspill--trail">
                        {span && span.after && !span.open ? fill(look.strings.calendar_until, shortDay(trail.last)) : ''}
                    </span>
                </div>
                <div className="dim-cal__trailsteprows" id={steps} hidden={!open}>
                    {trail.steps.map((step) => stepRow(trail, step))}
                </div>
            </div>
        );
    };

    /**
     * The desktop grid, in its scroller, and its legend.
     *
     * @returns {object}
     */
    const desk = () => (
        <>
            <TrailScroll
                days={days.length}
                todayat={todayat}
                opening={`${month}:${answer.trails.map((trail) => trail.id).join(',')}`}
                strings={look.strings}
            >
                <div className="dim-cal__trailgrid" data-region="dim-cal-trailgrid" style={columns}>
                    <div className="dim-cal__trailrow dim-cal__trailrow--head" aria-hidden="true">
                        <span className="dim-cal__traillabel dim-cal__trailheading">{look.strings.calendar_trailheading}</span>
                        {days.map((iso, index) => (
                            <span
                                key={iso}
                                className={`dim-cal__trailday${isWeekend(iso) ? ' dim-cal__trailday--weekend' : ''}`
                                    + `${index === todayat ? ' dim-cal__trailday--today' : ''}`}
                                data-day={iso}
                            >
                                <span className="dim-cal__trailweekday">{say(iso, WEEKDAY, look.language)}</span>
                                <span className="dim-cal__traildaynum">{Number(iso.slice(8, 10))}</span>
                            </span>
                        ))}
                        <span
                            className="dim-cal__trailspill dim-cal__trailday dim-cal__trailday--next"
                            title={fill(look.strings.calendar_trailnext, say(next, LONG_MONTH, look.language))}
                        >
                            {`${say(next, NEXT_MONTH, look.language)} →`}
                        </span>
                    </div>
                    <div className="dim-cal__trailground" aria-hidden="true">
                        {days.map((iso, index) => (isWeekend(iso) ? (
                            <span
                                key={iso}
                                className="dim-cal__trailweekend"
                                style={{'--dim-cal-trail-at': String(index)} as CSSProperties}
                            ></span>
                        ) : null))}
                        {todayat >= 0 ? (
                            <span
                                className="dim-cal__trailnow"
                                style={{'--dim-cal-trail-at': String(todayat)} as CSSProperties}
                            ></span>
                        ) : null}
                    </div>
                    {answer.trails.map((trail) => trailRows(trail))}
                </div>
            </TrailScroll>
            <ul className="dim-cal__traillegend" data-region="dim-cal-traillegend">
                {learner ? LEGEND.map((state) => (
                    <li key={state}>
                        <span className={`dim-cal__trailswatch dim-cal__trailswatch--${state}`} aria-hidden="true"></span>
                        {stateLabel(look.strings, state)}
                    </li>
                )) : null}
                <li>
                    <span className="dim-cal__trailswatch" aria-hidden="true"></span>
                    {look.strings.calendar_trailnotenrolled}
                </li>
                <li>
                    <span className="dim-cal__traildiamond dim-cal__traildiamond--legend" aria-hidden="true"></span>
                    {look.strings.calendar_trailexpected}
                </li>
                <li>
                    <span className="dim-cal__traillegendnow" aria-hidden="true"></span>
                    {look.strings.calendar_today}
                </li>
            </ul>
        </>
    );

    /**
     * The phone's "Today" line.
     *
     * @returns {object}
     */
    const todayLine = () => (
        <li className="dim-cal__trailtoday" data-region="dim-cal-trailtoday">
            <span className="dim-cal__trailtodayword">{look.strings.calendar_today}</span>
            <span className="dim-cal__trailtodayline" aria-hidden="true"></span>
            <span className="dim-cal__trailtodaydate">{shortOf(today, look.language)}</span>
        </li>
    );

    /**
     * The phone's agenda.
     *
     * @returns {object}
     */
    const agenda = () => (
        <div className="dim-cal__trailagenda" data-region="dim-cal-trailagenda">
            {answer.trails.map((trail) => {
                const at = todayIndex(trail.steps, now);
                return (
                    <section
                        className="dim-cal__trail dim-cal__trailsheet"
                        key={trail.id}
                        aria-label={trail.name}
                        data-trail={trail.id}
                    >
                        <div className="dim-cal__trailsheethead">
                            <h3 className="dim-cal__trailtitle">
                                <a href={safeHref(trail.url, look.home)}>{trail.name}</a>
                            </h3>
                            <small className="dim-cal__trailprogress">
                                {trail.enrolled ? progressOf(trail, look) : coursesOf(look.strings, trail.steps.length)}
                            </small>
                        </div>
                        {trail.enrolled ? (
                            <span className="dim-cal__trailsegments" aria-hidden="true">
                                {trail.steps.map((step) => (
                                    <span
                                        key={step.cmid}
                                        className={'dim-cal__trailsegment'
                                            + (step.state ? ` dim-cal__trailsegment--${step.state}` : '')}
                                    ></span>
                                ))}
                            </span>
                        ) : null}
                        <ol className="dim-cal__trailsteps">
                            {trail.steps.map((step, index) => (
                                <Fragment key={step.cmid}>
                                    {index === at ? todayLine() : null}
                                    <li className={`dim-cal__trailstep${step.state ? ` dim-cal__trailstep--${step.state}` : ''}`}>
                                        {opener(trail, step, 'dim-cal__trailstepname', undefined, (
                                            <>
                                                <span className="dim-cal__trailstepdate">{stepDay(step)}</span>
                                                <span className="dim-cal__trailstepbar" aria-hidden="true"></span>
                                                <span className="dim-cal__trailsteptext">
                                                    <span className="dim-cal__trailstepline">
                                                        {`${step.position}. ${step.name}`}
                                                    </span>
                                                    <span className="dim-cal__trailwhen">{stepWhen(step, look)}</span>
                                                    {step.state ? (
                                                        <span className={`dim-cal__trailstate dim-cal__trailstate--${step.state}`}>
                                                            {stateLine(step, look, now)}
                                                        </span>
                                                    ) : null}
                                                </span>
                                            </>
                                        ))}
                                    </li>
                                </Fragment>
                            ))}
                            {at === trail.steps.length ? todayLine() : null}
                        </ol>
                    </section>
                );
            })}
        </div>
    );

    return (
        <section
            ref={root}
            className={`dim-cal__trails${phone ? ' dim-cal__trails--phone' : ''}`}
            role="region"
            aria-labelledby={captionid}
            data-region="dim-cal-trails"
        >
            {answer.truncated ? <p className="dim-cal__status">{look.strings.calendar_trailstruncated}</p> : null}
            {answer.trails.length === 0 ? (
                <p className="dim-cal__empty" data-region="dim-cal-trailsempty">{look.strings.calendar_trailsempty}</p>
            ) : null}
            {answer.trails.length > 0 && !phone ? desk() : null}
            {answer.trails.length > 0 && phone ? agenda() : null}
            {popped && poppedtrail && poppedstep ? (
                <TrailPopover
                    look={look}
                    trail={poppedtrail}
                    step={poppedstep}
                    opener={popped.opener}
                    sheet={phone}
                    now={now}
                    onClose={close}
                />
            ) : null}
        </section>
    );
};

export default TrailView;
