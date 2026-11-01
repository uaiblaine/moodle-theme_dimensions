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
 * The calendar page's client: the toolbar, the key chips, the category picker, "More filters" and the month grid.
 *
 * Mounted by core's react_autoinit on the node calendar_page.mustache draws, never moved (a node that leaves the
 * document is unmounted). The props carry the range's first answer, so the first paint asks for nothing; once the
 * strings are read (from core's cache, where the page put them) the component draws and hides the server's layer
 * in the same frame.
 *
 * - The latest request wins: a ticket is taken per request and checked after the await; a superseded answer is
 *   dropped. A Pending token (theme_dimensions/calendar:range) spans the request to the painted grid.
 * - The address has one writer (calendar/history.ts): a month moved pushes an entry, a change of state replaces it;
 *   Back and Forward read the address again (popstate) and ask for what it names, writing nothing.
 * - A change of keys or filters is remembered after a quiet ({@see QUIET}), never on every press, and an unchanged
 *   state is not sent again; a Pending token (theme_dimensions/calendar:remember) spans the quiet and the request.
 * - Every key off asks the range nothing: the answer is the window's days at zero.
 *
 * @module     theme_dimensions/calendar/Calendar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useLayoutEffect, useMemo, useRef, useState} from 'react';
import {fetchOne} from '@moodle/lms/core/ajax';
import Pending from '@moodle/lms/core/pending';
import {redirect} from '@moodle/lms/core/location';
import {load} from '../amd';
import {parse, say, shiftMonth} from '../daterange/calendar';
import CategoryPicker from './CategoryPicker';
import {gridWeeks, zone as zoneOf} from './days';
import {writeAddress} from './history';
import KeyChips from './KeyChips';
import {place} from './layout';
import type {Look} from './look';
import {
    KEYS,
    RANGE_METHOD,
    REMEMBER_METHOD,
    address,
    dayHref,
    emptyAnswer,
    keysOn,
    monthHref,
    normalise,
    rangeArgs,
    readAddress,
    sameState,
} from './model';
import type {Answer, Key, Props, State} from './model';
import MonthGrid from './MonthGrid';
import MoreFilters from './MoreFilters';
import {load as loadStrings} from './strings';
import type {Strings} from './strings';

/** How long a state must hold still before it is remembered, in ms. */
export const QUIET = 800;

/** The id of the month's caption, which labels the grid. */
const CAPTION = 'dim-cal-caption';

/** What the grid shows: a month and its answer, always together. */
type Shown = {month: string, answer: Answer};

/** How a request writes the address. */
type Write = 'push' | 'replace' | 'none';

/**
 * Show core's message for a failure.
 *
 * @param {Error} failure What failed.
 * @returns {Promise} Settles once the message is shown.
 */
const report = (failure: unknown): Promise<void> =>
    load('core/notification').then((notification) => notification.exception(failure));

/**
 * The day a month opens on: today when it is in the month, else the 1st.
 *
 * @param {string} month The month, YYYY-MM.
 * @param {string} today Today, YYYY-MM-DD.
 * @returns {string}
 */
export const openingDay = (month: string, today: string): string => (today.startsWith(month) ? today : `${month}-01`);

/**
 * The page.
 *
 * @param {object} props The server's props; see Props.
 * @returns {object|null} The page, or nothing until its strings are read.
 */
const Calendar = (props: Props) => {
    const language = document.documentElement.lang || undefined;
    const firstday = Number(props.firstday) || 0;
    const [strings, setStrings] = useState<Strings | null>(null);
    const [shown, setShown] = useState<Shown>({month: props.month, answer: props.answer});
    const [state, setState] = useState<State>(() => normalise(props.state));
    const [selected, setSelected] = useState<string>(() => openingDay(props.month, props.today));
    const [focusing, setFocusing] = useState<number>(0);

    const root = useRef<HTMLDivElement | null>(null);
    const ticket = useRef<number>(0);
    const painting = useRef<Pending | null>(null);
    const mounting = useRef<Pending | null>(null);
    const remembered = useRef<State>(normalise(props.state));
    const quiet = useRef<ReturnType<typeof setTimeout> | null>(null);
    const holding = useRef<Pending | null>(null);
    const latest = useRef<State>(normalise(props.state));

    const zone = useMemo(() => zoneOf(props.timezone, language), [props.timezone, language]);
    const weeks = useMemo(() => gridWeeks(shown.month, firstday), [shown.month, firstday]);
    const placed = useMemo(() => place(shown.answer.events, zone), [shown.answer, zone]);

    useEffect(() => {
        mounting.current = new Pending('theme_dimensions/calendar:mount');
        loadStrings()
            .then((values) => {
                setStrings(values);
                return values;
            })
            .catch((failure) => {
                mounting.current?.resolve();
                mounting.current = null;
                report(failure);
            });
        /**
         * Ask again for what the address names, writing nothing: Back and Forward.
         *
         * @returns {void}
         */
        const onPop = (): void => {
            const read = readAddress(window.location.href);
            const month = read.month || props.month;
            request(month, read.state || normalise(props.state), 'none', openingDay(month, props.today), false);
        };
        window.addEventListener('popstate', onPop);
        return () => {
            window.removeEventListener('popstate', onPop);
            mounting.current?.resolve();
            painting.current?.resolve();
            holding.current?.resolve();
            if (quiet.current !== null) {
                clearTimeout(quiet.current);
            }
        };
    }, []);

    // The server's layer goes in the frame this layer is first painted in, and the mount is settled.
    useLayoutEffect(() => {
        if (!strings) {
            return;
        }
        const page = root.current?.closest('[data-region="dim-cal"]');
        const server = page?.querySelector<HTMLElement>('[data-region="dim-cal-server"]');
        if (server) {
            server.hidden = true;
        }
        mounting.current?.resolve();
        mounting.current = null;
    }, [strings]);

    // An answer is settled once it is painted.
    useEffect(() => {
        painting.current?.resolve();
        painting.current = null;
    }, [shown]);

    /**
     * Ask the range for a month and a state, and show the answer if it is still the latest request's.
     *
     * @param {string} month The month, YYYY-MM.
     * @param {object} next The state.
     * @param {string} write How the address is written: push, replace or none.
     * @param {string} select The day to select once the month is shown.
     * @param {boolean} focus Whether the focus follows the selected day.
     * @returns {Promise} Settles once the answer is taken or dropped.
     */
    const request = async(month: string, next: State, write: Write, select: string, focus: boolean): Promise<void> => {
        const seq = ++ticket.current;
        if (write !== 'none') {
            writeAddress(address(props.urls.page, month, next), write === 'push');
        }
        const pending = new Pending('theme_dimensions/calendar:range');
        const days = gridWeeks(month, firstday).flat();
        try {
            const answer = keysOn(next).length
                ? await fetchOne<Answer>({methodname: RANGE_METHOD, args: rangeArgs(days[0], days[days.length - 1], next)})
                : emptyAnswer(days);
            if (seq !== ticket.current) {
                pending.resolve();
                return;
            }
            painting.current?.resolve();
            painting.current = pending;
            setShown({month: month, answer: answer});
            setSelected(select);
            if (focus) {
                setFocusing((tick) => tick + 1);
            }
        } catch (failure) {
            pending.resolve();
            if (seq === ticket.current) {
                report(failure);
            }
        }
    };

    /**
     * Remember a state after a quiet, unless it is the one remembered last.
     *
     * @param {object} next The state.
     * @returns {void}
     */
    const remember = (next: State): void => {
        latest.current = next;
        if (quiet.current !== null) {
            clearTimeout(quiet.current);
        }
        if (!holding.current) {
            holding.current = new Pending('theme_dimensions/calendar:remember');
        }
        quiet.current = setTimeout(() => {
            quiet.current = null;
            const hold = holding.current;
            holding.current = null;
            const sent = latest.current;
            if (sameState(sent, remembered.current)) {
                hold?.resolve();
                return;
            }
            fetchOne({methodname: REMEMBER_METHOD, args: {...sent}})
                .then((stored) => {
                    remembered.current = sent;
                    return stored;
                })
                .catch(report)
                .finally(() => hold?.resolve());
        }, QUIET);
    };

    /**
     * Take a change of state: show it, write it, remember it.
     *
     * @param {object} fields The fields that change.
     * @returns {void}
     */
    const change = (fields: Partial<State>): void => {
        const next = normalise({...state, ...fields});
        if (sameState(next, state)) {
            return;
        }
        setState(next);
        request(shown.month, next, 'replace', selected, false);
        remember(next);
    };

    /**
     * Go to the month a day is in, selecting the day.
     *
     * @param {string} iso The day.
     * @param {boolean} focus Whether the focus follows.
     * @returns {void}
     */
    const toDay = (iso: string, focus: boolean): void => {
        request(iso.slice(0, 7), state, 'push', iso, focus);
    };

    /**
     * Open a day: core's day view in C2 (model.dayHref(), the seam C3 replaces with the drawer).
     *
     * @param {string} iso The day.
     * @returns {void}
     */
    const openDay = (iso: string): void => redirect(dayHref(props.urls.calendar, iso));

    if (!strings) {
        return null;
    }

    const look: Look = {
        strings: strings,
        zone: zone,
        language: language,
        colour: state.colour,
        courses: new Map(shown.answer.courses.map((course) => [course.id, course])),
        categories: new Map(shown.answer.categories.map((category) => [category.id, category])),
        home: props.urls.home,
    };
    const counts: Record<string, number> = Object.fromEntries(KEYS.map((key) => [key, 0]));
    shown.answer.events.forEach((item) => {
        counts[item.key] = (counts[item.key] || 0) + 1;
    });
    const cities = [...new Set([...props.options.city, ...shown.answer.courses.map((course) => course.city)])]
        .filter((city) => city !== '').sort((a, b) => a.localeCompare(b, language));
    const caption = say(`${shown.month}-01`, {month: 'long', year: 'numeric'}, language);
    /**
     * Move a month from the selected day, the focus left where it is.
     *
     * @param {number} offset Whole months.
     * @returns {void}
     */
    const step = (offset: number): void => toDay(shiftMonth(parse(selected) ? selected : `${shown.month}-01`, offset), false);

    return (
        <div ref={root} className="dim-cal__app" data-region="dim-cal-app">
            <div className="dim-cal__toolbar">
                <h1 className="dim-cal__title">{strings.calendar_title}</h1>
                <button type="button" className="btn btn-secondary dim-cal__today" onClick={() => toDay(props.today, false)}>
                    {strings.calendar_today}
                </button>
                <button
                    type="button"
                    className="btn btn-secondary dim-cal__step"
                    data-action="dim-cal-prev"
                    aria-label={strings.calendar_prevmonth}
                    onClick={() => step(-1)}
                >
                    <i className="fa fa-chevron-left" aria-hidden="true"></i>
                </button>
                <button
                    type="button"
                    className="btn btn-secondary dim-cal__step"
                    data-action="dim-cal-next"
                    aria-label={strings.calendar_nextmonth}
                    onClick={() => step(1)}
                >
                    <i className="fa fa-chevron-right" aria-hidden="true"></i>
                </button>
                <p className="dim-cal__caption" id={CAPTION} aria-live="polite" data-region="dim-cal-caption">{caption}</p>
                <span className="dim-cal__spacer"></span>
                <div className="dim-cal__views" role="group" aria-label={strings.calendar_view}>
                    <span className="dim-cal__view dim-cal__view--active" aria-current="true">{strings.calendar_viewmonth}</span>
                </div>
                <a className="dim-cal__classic" href={monthHref(props.urls.calendar, shown.month)}>{strings.calendar_classic}</a>
            </div>
            <div className="dim-cal__toolbar">
                <KeyChips
                    strings={strings}
                    off={state.off}
                    counts={counts}
                    onToggle={(key: Key) => change({off: state.off.includes(key)
                        ? state.off.filter((other) => other !== key) : [...state.off, key]})}
                />
                <span className="dim-cal__spacer"></span>
                <CategoryPicker
                    strings={strings}
                    tree={props.tree}
                    categoryid={state.categoryid}
                    recursive={state.recursive}
                    onChange={change}
                />
                <MoreFilters
                    strings={strings}
                    state={state}
                    options={{model: props.options.model, modality: props.options.modality, city: cities}}
                    onChange={change}
                />
            </div>
            <MonthGrid
                look={look}
                month={shown.month}
                weeks={weeks}
                placed={placed}
                today={props.today}
                selected={weeks.flat().includes(selected) ? selected : openingDay(shown.month, props.today)}
                focusing={focusing}
                captionid={CAPTION}
                onSelect={(iso) => {
                    setSelected(iso);
                    setFocusing((tick) => tick + 1);
                }}
                onMonth={(iso) => toDay(iso, true)}
                onOpen={openDay}
            />
            {shown.answer.truncated ? <p className="dim-cal__status">{strings.calendar_truncated}</p> : null}
        </div>
    );
};

export default Calendar;
