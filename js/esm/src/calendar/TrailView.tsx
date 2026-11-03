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
 *   milestone at an expected date, a bar that passes the month's end continued in the last column ("Dec →"), one that
 *   began earlier marked at the left edge, an open one fading out. Today's column is marked down the rows.
 * - On a phone, an agenda: each trail and its steps in order, with their dates, a "Today" line between the steps
 *   behind and the steps ahead (trails.ts todayIndex()).
 * - For a learner of the trail each step carries its state (done, current, late, locked, upcoming) as a class and
 *   data-state from that fixed vocabulary, and the trail's row counts the steps done; for anyone else the bars are the
 *   trail colour, the schedule alone. The event types' colours never reach this view.
 * - A step opens its popover (TrailPopover.tsx) on a press, Enter or Space: the bars and the agenda's names are
 *   buttons.
 *
 * Its ONE ResizeObserver watches the frame it is drawn in and reports the frame's width alone, the grids' rule
 * (MonthGrid.tsx PHONE): the frame's width does not depend on what it holds, so the phone decision cannot feed back
 * into the measure. Nothing is measured on the mount path, and no layout is read to place a bar: the bars are grid
 * columns.
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
import TrailPopover, {stateLine, stepWhen} from './TrailPopover';
import {barOf, monthDays, todayIndex} from './trails';
import type {Trail, TrailAnswer, TrailFilter, TrailStep} from './trails';

/** What the view is handed. */
type TrailViewProps = {
    look: Look,
    month: string,
    answer: TrailAnswer,
    today: string,
    now: number,
    phone: boolean,
    captionid: string,
    onWidth: (width: number) => void,
};

/** The open popover: the trail, the step and the element that opened it. */
type Popped = {trail: number, cmid: number, opener: HTMLElement};

/** The short month of the "next month" column. */
const NEXT_MONTH: Intl.DateTimeFormatOptions = {month: 'short'};

/** The long month of its accessible name. */
const LONG_MONTH: Intl.DateTimeFormatOptions = {month: 'long', year: 'numeric'};

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
 * The trail's progress line for a learner, else ''.
 *
 * @param {object} trail The trail.
 * @param {object} look The page's look.
 * @returns {string}
 */
const progressOf = (trail: Trail, look: Look): string => (trail.enrolled
    ? fill(look.strings.calendar_trailprogress, {done: trail.done, count: trail.steps.length})
    : '');

/** What the view's filters are handed: the trail choice and "Só as minhas", then the page's own controls. */
type TrailFiltersProps = {
    strings: Strings,
    filter: TrailFilter,
    answer: TrailAnswer | null,
    onChange: (fields: Partial<TrailFilter>) => void,
    children?: ReactNode,
};

/**
 * The Trilhas view's toolbar row: "Trilha" (every trail the answer offers, "Todas as trilhas" first), "Só as minhas",
 * and the page's category picker and "Clear filters" after them. The event types are not part of it.
 *
 * @param {object} props See TrailFiltersProps.
 * @returns {object} The row.
 */
export const TrailFilters = ({strings, filter, answer, onChange, children}: TrailFiltersProps) => (
    <div className="dim-cal__toolbar" data-region="dim-cal-trailfilters">
        <label className="dim-cal__trailpick">
            <span className="visually-hidden">{strings.calendar_trail}</span>
            <select
                className="form-select dim-cal__select"
                data-action="dim-cal-trail"
                value={String(filter.trail)}
                onChange={(event) => onChange({trail: Number(event.currentTarget.value)})}
            >
                <option value="0">{strings.calendar_trailall}</option>
                {(answer ? answer.options : []).map((option) => (
                    <option key={option.id} value={String(option.id)}>{option.name}</option>
                ))}
            </select>
        </label>
        <label className="dim-cal__trailmine">
            <input
                type="checkbox"
                data-action="dim-cal-trailmine"
                checked={filter.mine}
                onChange={(event) => onChange({mine: event.currentTarget.checked})}
            />
            {strings.calendar_trailmine}
        </label>
        <span className="dim-cal__spacer"></span>
        {children}
    </div>
);

/**
 * The view.
 *
 * @param {object} props See TrailViewProps.
 * @returns {object} The region.
 */
const TrailView = ({look, month, answer, today, now, phone, captionid, onWidth}: TrailViewProps) => {
    const root = useRef<HTMLElement | null>(null);
    const width = useRef<(width: number) => void>(onWidth);
    width.current = onWidth;
    const [popped, setPopped] = useState<Popped | null>(null);

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

    const days = monthDays(month);
    const next = shiftMonth(days[0], 1);
    const todayat = days.indexOf(today);
    const columns = {'--dim-cal-trail-days': String(days.length)} as CSSProperties;
    const poppedtrail = popped ? answer.trails.find((trail) => trail.id === popped.trail) : undefined;
    const poppedstep = poppedtrail?.steps.find((step) => step.cmid === popped?.cmid);

    /**
     * The button that opens a step's popover.
     *
     * @param {object} trail The trail.
     * @param {object} step The step.
     * @param {string} className Its classes.
     * @param {object} style Its grid placement.
     * @param {object} content What it draws.
     * @returns {object}
     */
    const opener = (trail: Trail, step: TrailStep, className: string, style: CSSProperties | undefined, content: string) => (
        <button
            type="button"
            className={className}
            style={style}
            aria-label={stepLabel(step, look, now)}
            aria-expanded={popped?.cmid === step.cmid}
            data-region="dim-cal-trailstep"
            data-cmid={step.cmid}
            data-state={step.state || undefined}
            onClick={(event) => setPopped({trail: trail.id, cmid: step.cmid, opener: event.currentTarget})}
        >{content}</button>
    );

    /**
     * One step's row of the desktop grid: its number and name, and its bar.
     *
     * @param {object} trail The trail.
     * @param {object} step The step.
     * @returns {object}
     */
    const stepRow = (trail: Trail, step: TrailStep) => {
        const bar = barOf(step, days, look.zone);
        const state = step.state ? ` dim-cal__trailbar--${step.state}` : '';
        let drawn = null;
        if (bar) {
            const continued = bar.after && !bar.open;
            const classes = 'dim-cal__trailbar' + state
                + (bar.before ? ' dim-cal__trailbar--before' : '')
                + (continued ? ' dim-cal__trailbar--after' : '')
                + (bar.open ? ' dim-cal__trailbar--open' : '')
                + (bar.milestone ? ' dim-cal__trailbar--milestone' : '')
                + (step.next ? ' dim-cal__trailbar--next' : '');
            const place = {gridColumn: `${bar.from + 2} / ${bar.to + 3 + (continued ? 1 : 0)}`, gridRow: '1'};
            drawn = opener(trail, step, classes, place, step.name);
        }
        return (
            <div className="dim-cal__trailrow" key={step.cmid} data-cmid={step.cmid} style={columns}>
                <span className="dim-cal__traillabel">
                    <span className="dim-cal__trailnum" aria-hidden="true">{step.position}</span>
                    <span className="dim-cal__trailname">{step.name}</span>
                </span>
                {todayat >= 0 ? (
                    <span
                        className="dim-cal__trailnow"
                        style={{gridColumn: String(todayat + 2), gridRow: '1'}}
                        aria-hidden="true"
                    ></span>
                ) : null}
                {drawn}
            </div>
        );
    };

    /**
     * The desktop grid.
     *
     * @returns {object}
     */
    const desk = () => (
        <div className="dim-cal__trailgrid" data-region="dim-cal-trailgrid">
            <div className="dim-cal__trailrow dim-cal__trailrow--head" style={columns} aria-hidden="true">
                <span className="dim-cal__traillabel"></span>
                {days.map((iso, index) => (
                    <span
                        key={iso}
                        className={`dim-cal__trailday${isWeekend(iso) ? ' dim-cal__trailday--weekend' : ''}`
                            + `${index === todayat ? ' dim-cal__trailday--today' : ''}`}
                    >{Number(iso.slice(8, 10))}</span>
                ))}
                <span className="dim-cal__trailday dim-cal__trailday--next" title={fill(look.strings.calendar_trailnext,
                    say(next, LONG_MONTH, look.language))}>
                    {`${say(next, NEXT_MONTH, look.language)} →`}
                </span>
            </div>
            {answer.trails.map((trail) => (
                <div className="dim-cal__trail" key={trail.id} role="group" aria-label={trail.name} data-trail={trail.id}>
                    <div className="dim-cal__trailrow dim-cal__trailrow--trail" style={columns}>
                        <span className="dim-cal__traillabel dim-cal__trailtitle">
                            <a href={safeHref(trail.url, look.home)}>{trail.name}</a>
                            {trail.enrolled ? <small className="dim-cal__trailprogress">{progressOf(trail, look)}</small> : null}
                        </span>
                    </div>
                    {trail.steps.map((step) => stepRow(trail, step))}
                </div>
            ))}
        </div>
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
                    <section className="dim-cal__trail" key={trail.id} aria-label={trail.name} data-trail={trail.id}>
                        <h3 className="dim-cal__trailtitle">
                            <a href={safeHref(trail.url, look.home)}>{trail.name}</a>
                            {trail.enrolled ? <small className="dim-cal__trailprogress">{progressOf(trail, look)}</small> : null}
                        </h3>
                        <ol className="dim-cal__trailsteps">
                            {trail.steps.map((step, index) => (
                                <Fragment key={step.cmid}>
                                    {index === at ? (
                                        <li className="dim-cal__trailtoday" data-region="dim-cal-trailtoday">
                                            <span>{look.strings.calendar_today}</span>
                                        </li>
                                    ) : null}
                                    <li className={`dim-cal__trailstep${step.state ? ` dim-cal__trailstep--${step.state}` : ''}`}>
                                        <span className="dim-cal__trailnum" aria-hidden="true">{step.position}</span>
                                        {opener(trail, step, 'dim-cal__trailstepname', undefined, step.name)}
                                        <span className="dim-cal__trailwhen">{stepWhen(step, look)}</span>
                                        {step.state ? (
                                            <span className={`dim-cal__trailstate dim-cal__trailstate--${step.state}`}>
                                                {stateLine(step, look, now)}
                                            </span>
                                        ) : null}
                                    </li>
                                </Fragment>
                            ))}
                            {at === trail.steps.length ? (
                                <li className="dim-cal__trailtoday" data-region="dim-cal-trailtoday">
                                    <span>{look.strings.calendar_today}</span>
                                </li>
                            ) : null}
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
