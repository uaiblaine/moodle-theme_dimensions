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
 * The class competency report, drawn in React (phase 6 part two of docs/competency/PLAN.md).
 *
 * report/competency/index.php without a learner renders the shell (theme_dimensions\output\class_report_view)
 * with configuration and strings; the learners and the figures come from the theme's web services, which read
 * the system report class_ratings headlessly, so access, the group rule, the stored filters and the chosen
 * columns are the report's, as on core's table. Three regions load on their own:
 *
 * - the rows (theme_dimensions_class_report_rows): a sort or a page reloads them alone;
 * - the band (theme_dimensions_class_report_summary): a filter or the search reload it with the rows;
 * - the columns (theme_dimensions_set_class_report_preference, the preference's one writer): a column change
 *   is written, and the rows and the band are read again only once the write has landed, because the report
 *   reads the columns in initialise() and the band's figures are over the shown columns. A width is written
 *   and read again by nobody: it is presentation.
 *
 * Every request posts the group the page resolved (the props' groupid, never -1), so a read never depends on
 * the session's choice changing in another tab. One request is in flight per region in effect: core's ajax
 * cannot abort, so each region numbers its requests and drops any answer but the newest. The search is sent
 * 300 ms after the last key, from two characters. The first load draws skeletons with aria-busy; later ones
 * keep the old rows, dimmed, under a 2 px line. The view (Automatic, Table, Cards) is remembered in this browser
 * only, and Automatic is the cards under a 576 px container.
 *
 * A grader's rating cell carries the grading dialogue's hooks; theme_dimensions/classratings (armed by the
 * shell) opens core's dialogue from it and, when it closes, dispatches RATED on the shell's root: the view reads
 * the rows (the page kept) and the band again. Composed after local_evidencehub's hub/HubView.tsx, without its
 * scope, presets, selection, dock and drawer.
 *
 * @module     theme_dimensions/classreport/ClassReportView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import type {ReactElement} from 'react';
import {rememberView, storedView, useNarrow} from '../datareport/layout';
import {Pager} from '../datareport/Pager';
import {hold} from '../datareport/pending';
import {RowsRegion} from '../datareport/Rows';
import {Band} from './Band';
import {Bar} from './Bar';
import {Cards} from './Cards';
import {LEAD, SIZING} from './cells';
import {withChoice, without} from './filters';
import {Grid} from './Grid';
import {getRows, getSummary, resetFilters, setFilters, setPreference} from './repository';
import type {Target} from './repository';
import type {Column, Filter, LearnerRow, Preference, RowsAnswer, RowsQuery, SummaryAnswer, ViewMode,
    ViewProps} from './types';

/**
 * The event theme_dimensions/classratings dispatches on the shell's root when core's grading dialogue closes;
 * amd/src/classratings.js names the same literal (tests/local/classratings_source_test.php).
 */
export const RATED = 'theme_dimensions/classratings:rated';

/** The shell's root, the region classratings.js listens in (templates/competency_classreport_view.mustache). */
export const ROOT = '[data-region="dim-comp-classreport"]';

/** The wait before the search is sent, after the last key (data-report.md). */
const SEARCH_DELAY = 300;

/** The wait before a width set from the keyboard is saved, after the last key. */
const WIDTH_DELAY = 500;

/** The browser key the reader's view choice is remembered under: a convenience of this browser alone. */
const VIEW_KEY = 'theme_dimensions_classreport_view';

/** The columns the first skeleton draws, before the report has said which it shows. */
const SKELETON_COLUMNS = [LEAD, 'a', 'b', 'c', 'd'];

/**
 * The widths as the preference stores them.
 *
 * @param {object} map The widths by key.
 * @returns {Array} Each: key, width.
 */
const widthList = (map: Record<string, number>): {key: string, width: number}[] =>
    Object.entries(map).map(([key, width]) => ({key, width}));

/**
 * The preference to write for the columns in force and some widths.
 *
 * A reader on the default columns keeps them: no column is posted, which the server stores as "no choice" with
 * the widths beside it (classreportpreference::write()), so "Default columns" stays where it was.
 *
 * @param {boolean} usedefault Whether the default columns show.
 * @param {Array} chosen The chosen competency columns' keys, as the report shows them.
 * @param {object} widths The widths by key.
 * @returns {object} The preference.
 */
export const preferenceOf = (usedefault: boolean, chosen: string[], widths: Record<string, number>): Preference =>
    ({usedefault: false, columns: usedefault ? [] : chosen, widths: widthList(widths)});

/**
 * The view.
 *
 * @param {object} props The props class_report_view::props() wrote; see ViewProps.
 * @returns {object} The view.
 */
const ClassReportView = (props: ViewProps): ReactElement => {
    const {labels, reportid} = props;
    const target = useMemo<Target>(() => ({courseid: props.courseid, moduleid: props.moduleid, groupid: props.groupid}),
        [props.courseid, props.moduleid, props.groupid]);
    const root = useRef<HTMLDivElement | null>(null);
    // Automatic is cards under a 576 px container: the report's own width, not the window's.
    const narrow = useNarrow(root);
    const [view, setViewState] = useState<ViewMode>(() => storedView(VIEW_KEY));

    const initial: RowsQuery = {search: '', page: 0, perpage: props.perpage, sort: '', direction: 'asc'};
    const query = useRef<RowsQuery>(initial);
    const [current, setCurrent] = useState<RowsQuery>(initial);
    const [searchtext, setSearchtext] = useState('');

    const [rows, setRows] = useState<RowsAnswer | null>(null);
    const [rowsloading, setRowsloading] = useState(true);
    const [rowserror, setRowserror] = useState(false);
    const [summary, setSummary] = useState<SummaryAnswer | null>(null);
    const [bandloading, setBandloading] = useState(true);
    const [banderror, setBanderror] = useState(false);
    // A write in flight (filters, columns): both regions dim at the press, not when the write has answered.
    const [writing, setWriting] = useState(false);
    const writes = useRef(0);
    const [widths, setWidths] = useState<Record<string, number>>({});
    const [counted, setCounted] = useState<Record<string, Filter>>({});

    const rowsseq = useRef(0);
    const bandseq = useRef(0);
    const rowshold = useRef<(() => void) | null>(null);
    const bandhold = useRef<(() => void) | null>(null);
    const searchtimer = useRef<number | null>(null);
    const searchhold = useRef<(() => void) | null>(null);
    const widthsread = useRef(false);
    const widthtimer = useRef<number | null>(null);
    const widthhold = useRef<(() => void) | null>(null);
    const pendingwidths = useRef<Record<string, number> | null>(null);
    const flushref = useRef<() => void>(() => undefined);

    /**
     * Read a page of rows; an answer to an older request than the newest is dropped.
     *
     * @param {object} asked What to read.
     * @param {boolean} facets Whether the panel's counts can have moved, and so are asked for.
     * @returns {void}
     */
    const loadRows = useCallback((asked: RowsQuery, facets: boolean): void => {
        const seq = ++rowsseq.current;
        if (!rowshold.current) {
            rowshold.current = hold('theme_dimensions/classreport:rows');
        }
        setRowsloading(true);
        getRows(target, asked, facets).then((answer) => {
            if (seq !== rowsseq.current) {
                return;
            }
            if (answer.counted) {
                setCounted(Object.fromEntries(answer.filters.map((filter) => [filter.name, filter])));
            }
            if (!widthsread.current) {
                widthsread.current = true;
                setWidths(Object.fromEntries(answer.preference.widths.map((entry) => [entry.key, entry.width])));
            }
            if (answer.page !== query.current.page) {
                // Past the last page (a filter shrank the rows): the server answered the last one.
                query.current = {...query.current, page: answer.page};
                setCurrent(query.current);
            }
            setRows(answer);
            setRowserror(false);
            setRowsloading(false);
            return;
        }).catch(() => {
            if (seq === rowsseq.current) {
                setRowserror(true);
                setRowsloading(false);
            }
        });
    }, [target]);

    /**
     * Read the band's figures; an answer to an older request than the newest is dropped.
     *
     * @param {object} asked The search to summarise.
     * @returns {void}
     */
    const loadBand = useCallback((asked: RowsQuery): void => {
        const seq = ++bandseq.current;
        if (!bandhold.current) {
            bandhold.current = hold('theme_dimensions/classreport:band');
        }
        setBandloading(true);
        getSummary(target, asked.search).then((answer) => {
            if (seq === bandseq.current) {
                setSummary(answer);
                setBanderror(false);
                setBandloading(false);
            }
            return;
        }).catch(() => {
            if (seq === bandseq.current) {
                setBanderror(true);
                setBandloading(false);
            }
        });
    }, [target]);

    /**
     * Change what is asked for and read the regions it moves.
     *
     * @param {object} change The parts of the query that change.
     * @param {boolean} readband Whether the band moves (and so the panel's counts).
     * @returns {void}
     */
    const run = useCallback((change: Partial<RowsQuery>, readband: boolean): void => {
        query.current = {...query.current, ...change};
        setCurrent(query.current);
        loadRows(query.current, readband);
        if (readband) {
            loadBand(query.current);
        }
    }, [loadRows, loadBand]);

    // Behat waits on these holds: each is released once the answer it waited for has been drawn.
    useEffect(() => {
        if (!rowsloading && rowshold.current) {
            rowshold.current();
            rowshold.current = null;
        }
    }, [rowsloading, rows, rowserror]);
    useEffect(() => {
        if (!bandloading && bandhold.current) {
            bandhold.current();
            bandhold.current = null;
        }
    }, [bandloading, summary, banderror]);

    // The first load: both regions, in parallel, each in a request of its own.
    useEffect(() => {
        loadRows(query.current, true);
        loadBand(query.current);
    }, [loadRows, loadBand]);

    // A rating given in core's dialogue: the rows of the same page and the band are read again. The hold is
    // taken in the event's own call, so Behat never sees a settled page between the close and the read.
    useEffect(() => {
        const element = root.current;
        const host: EventTarget = element?.closest(ROOT) ?? document;
        /**
         * Read both regions again after a rating.
         *
         * @returns {void}
         */
        const rated = (): void => {
            run({}, true);
        };
        host.addEventListener(RATED, rated);

        return () => host.removeEventListener(RATED, rated);
    }, [run]);

    // A pending search is dropped with the component, and its hold released; a pending width is saved.
    useEffect(() => () => {
        if (searchtimer.current !== null) {
            window.clearTimeout(searchtimer.current);
        }
        searchhold.current?.();
        flushref.current();
    }, []);

    /**
     * Remember the view and draw it.
     *
     * @param {string} chosen auto, table or cards.
     * @returns {void}
     */
    const setView = (chosen: ViewMode): void => {
        setViewState(chosen);
        rememberView(VIEW_KEY, chosen);
    };

    /**
     * The search as typed: sent 300 ms after the last key, from the minimum length; cleared at once.
     *
     * @param {string} text What the field holds.
     * @returns {void}
     */
    const onSearch = (text: string): void => {
        setSearchtext(text);
        if (searchtimer.current !== null) {
            window.clearTimeout(searchtimer.current);
            searchtimer.current = null;
        }
        const trimmed = text.trim();
        const applied = trimmed.length >= props.searchmin ? trimmed : '';
        if (applied === query.current.search) {
            searchhold.current?.();
            searchhold.current = null;
            return;
        }
        if (text === '') {
            searchhold.current?.();
            searchhold.current = null;
            run({search: '', page: 0}, true);
            return;
        }
        if (!searchhold.current) {
            searchhold.current = hold('theme_dimensions/classreport:search');
        }
        searchtimer.current = window.setTimeout(() => {
            searchtimer.current = null;
            run({search: applied, page: 0}, true);
            searchhold.current?.();
            searchhold.current = null;
        }, SEARCH_DELAY);
    };

    /**
     * Write something the rows depend on, then read both regions again. The regions dim at the press: the
     * write is a round trip of its own, and the reader would otherwise wait for it with nothing moving.
     *
     * @param {string} key The hold's name.
     * @param {Function} write The write: core's filters, or the theme's preference.
     * @param {object} change What the read after the write asks for differently.
     * @returns {void}
     */
    const writeThenRead = (key: string, write: () => Promise<unknown>, change: Partial<RowsQuery>): void => {
        const release = hold(key);
        writes.current += 1;
        setWriting(true);
        write().then(() => {
            run(change, true);
            return;
        }).catch(() => {
            setRowserror(true);
        }).finally(() => {
            writes.current -= 1;
            setWriting(writes.current > 0);
            release();
        });
    };

    /**
     * Store a new set of filter values in core's store, then read both regions again from the first page.
     *
     * @param {Function} write The write: core's set or reset.
     * @returns {void}
     */
    const writeFilters = (write: () => Promise<boolean>): void =>
        writeThenRead('theme_dimensions/classreport:filters', write, {page: 0});

    // The panel draws each filter as the rows last counted it, its applied values as they are now.
    const filters: Filter[] = (rows?.filters ?? []).map((filter) => (counted[filter.name]
        ? {...filter, options: counted[filter.name].options}
        : filter));

    const allcolumns = rows?.columns ?? [];
    const usedefault = rows?.preference.usedefault ?? true;
    const chosenkeys = allcolumns.filter((column) => !column.fixed && column.active).map((column) => column.key);

    /**
     * Show or hide one competency: the choice is written, then the rows and the band are read with it. The menu
     * answers at once; the grid follows when the rows come back with the column.
     *
     * @param {string} key The column key.
     * @param {boolean} on Whether to show it.
     * @returns {void}
     */
    const onColumn = (key: string, on: boolean): void => {
        const shown = chosenkeys.length + (on ? 1 : -1);
        if (!on && shown < 1) {
            return;
        }
        if (on && shown > props.maxcolumns) {
            return;
        }
        const next = on ? [...chosenkeys, key] : chosenkeys.filter((chosen) => chosen !== key);
        if (rows) {
            setRows({
                ...rows,
                columns: rows.columns.map((column) => (column.key === key ? {...column, active: on} : column)),
                preference: {...rows.preference, usedefault: false, columns: next},
            });
        }
        const preference: Preference = {usedefault: false, columns: next, widths: widthList(widths)};
        writeThenRead('theme_dimensions/classreport:columns', () => setPreference(props.courseid, preference), {});
    };

    /**
     * Save the widths waiting to be saved, if any: once, whatever number of steps led to them.
     *
     * @returns {void}
     */
    const flushWidths = (): void => {
        if (widthtimer.current !== null) {
            window.clearTimeout(widthtimer.current);
            widthtimer.current = null;
        }
        const next = pendingwidths.current;
        pendingwidths.current = null;
        if (next !== null) {
            const release = hold('theme_dimensions/classreport:width');
            setPreference(props.courseid, preferenceOf(usedefault, chosenkeys, next)).catch(() => undefined).finally(release);
        }
        widthhold.current?.();
        widthhold.current = null;
    };
    // The timer and the unmount call the newest flush, which writes the columns as they are then.
    flushref.current = flushWidths;

    /**
     * One column's width, drawn at once; null restores its default. A drag's release and a double click write it
     * now; a key step (defer) waits for the last key, or for the handle to lose the focus.
     *
     * @param {string} key The column key.
     * @param {number|null} width Pixels, or null.
     * @param {boolean} defer Whether to wait for more steps before writing.
     * @returns {void}
     */
    const onWidth = (key: string, width: number | null, defer = false): void => {
        const next = {...widths};
        if (width === null) {
            delete next[key];
        } else {
            next[key] = width;
        }
        setWidths(next);
        pendingwidths.current = next;
        if (!defer) {
            flushWidths();
            return;
        }
        if (widthtimer.current !== null) {
            window.clearTimeout(widthtimer.current);
        }
        if (!widthhold.current) {
            widthhold.current = hold('theme_dimensions/classreport:widthkeys');
        }
        widthtimer.current = window.setTimeout(() => flushref.current(), WIDTH_DELAY);
    };

    /**
     * Sort by a column: a second press on the sorted one turns the order round.
     *
     * @param {string} key The column key.
     * @returns {void}
     */
    const onSort = (key: string): void => {
        const sorted = rows?.sort ?? '';
        const direction = sorted === key && rows?.direction === 'asc' ? 'desc' : 'asc';
        run({sort: key, direction, page: 0}, false);
    };

    const list: LearnerRow[] = useMemo(() => (rows?.rows ?? []).map((row) => ({...row, id: row.userid})), [rows]);
    const filtered = filters.some((filter) => filter.applied) || current.search !== '';
    const identity = allcolumns.filter((column) => column.key.startsWith('identity:'));
    const competencies = allcolumns.filter((column) => !column.fixed && column.active);
    const shown = allcolumns.filter((column) => column.key === LEAD || (!column.fixed && column.active));
    const cards = view === 'cards' || (view === 'auto' && narrow);
    // The rows as the reader views them; the region draws them only once an answer has some.
    let body: ReactElement | null = null;
    if (rows !== null) {
        body = cards ? (
            <Cards labels={labels} columns={competencies} identity={identity} rows={list} single={narrow} />
        ) : (
            <Grid
                labels={labels}
                columns={shown}
                identity={identity}
                rows={list}
                widths={widths}
                sort={rows.sort}
                direction={rows.direction}
                onSort={onSort}
                onWidth={onWidth}
                onWidthCommit={() => flushref.current()}
            />
        );
    }
    const skeleton: Column[] = SKELETON_COLUMNS.map((key) => ({
        key,
        title: key === LEAD ? labels.learner : '',
        sortable: false,
        "default": true,
        active: true,
        fixed: key === LEAD,
    }));

    return (
        <div className="dim-comp-rview-root" ref={root} data-region="dim-comp-rview-view">
            <Band
                labels={labels}
                summary={summary}
                loading={bandloading || writing}
                error={banderror}
                onRetry={() => loadBand(query.current)}
            />
            <Bar
                labels={labels}
                narrow={narrow}
                total={rows === null ? null : rows.total}
                filters={filters}
                columns={allcolumns.filter((column) => !column.key.startsWith('identity:'))}
                maxcolumns={props.maxcolumns}
                usedefault={usedefault}
                haswidths={Object.keys(widths).length > 0}
                view={view}
                search={searchtext}
                searchmin={props.searchmin}
                searching={rowsloading}
                tableurl={props.tableurl}
                downloadurl={props.downloadurl}
                formats={props.formats}
                moduleid={props.moduleid}
                groupid={props.groupid}
                appliedsearch={current.search}
                downloadable={rows !== null && rows.total > 0}
                onSearch={onSearch}
                onFilter={(filter, value) => writeFilters(() => setFilters(reportid, target, withChoice(filters, filter, value)))}
                onRemoveFilter={(filter) => writeFilters(() => setFilters(reportid, target, without(filters, filter)))}
                onClearFilters={() => writeFilters(() => resetFilters(reportid, target))}
                onClearAll={() => {
                    setSearchtext('');
                    query.current = {...query.current, search: ''};
                    if (filters.some((filter) => filter.applied)) {
                        writeFilters(() => resetFilters(reportid, target));
                    } else {
                        run({page: 0}, true);
                    }
                }}
                onColumn={onColumn}
                onDefaultColumns={() => {
                    // The server forgets the widths with the column choice.
                    setWidths({});
                    if (rows) {
                        setRows({...rows, preference: {usedefault: true, columns: [], widths: []}});
                    }
                    writeThenRead('theme_dimensions/classreport:columns',
                        () => setPreference(props.courseid, {usedefault: true, columns: [], widths: []}), {});
                }}
                onResetWidths={() => {
                    setWidths({});
                    const release = hold('theme_dimensions/classreport:width');
                    setPreference(props.courseid, preferenceOf(usedefault, chosenkeys, {}))
                        .catch(() => undefined).finally(release);
                }}
                onView={setView}
            />
            <RowsRegion
                labels={labels}
                loading={rowsloading || writing}
                loaded={rows !== null}
                error={rowserror}
                total={rows?.total ?? 0}
                filtered={filtered}
                cards={cards}
                narrow={narrow}
                skeleton={{columns: skeleton, lead: LEAD, widths, sizing: SIZING}}
                onRetry={() => loadRows(query.current, true)}
            >
                {body}
            </RowsRegion>
            {rows !== null && rows.total > 0 ? (
                <Pager
                    labels={labels}
                    page={current.page}
                    perpage={current.perpage}
                    perpages={props.perpages}
                    total={rows.total}
                    shown={rows.rows.length}
                    narrow={narrow}
                    onPage={(page) => run({page}, false)}
                    onPerpage={(perpage) => run({perpage, page: 0}, false)}
                />
            ) : null}
        </div>
    );
};

export default ClassReportView;
