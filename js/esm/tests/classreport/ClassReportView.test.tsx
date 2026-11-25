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
 * Tests for the class report's React view, by what it asks the server and what it draws.
 *
 * The view is rendered inside a shell like competency_classreport_view.mustache's (its root region around the
 * mount); the three services and core's filter services are core/ajax's fetchOne, mocked, each request a promise the
 * test settles when it chooses, so the order of the answers is the test's.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, fireEvent, render, waitFor} from '@testing-library/react';
import {fetchOne} from '@moodle/lms/core/ajax';
import ClassReportView, {RATED, ROOT, preferenceOf} from '../../src/classreport/ClassReportView';
import {NONE, percent, tiles} from '../../src/classreport/Band';
import {downloadHref} from '../../src/classreport/Bar';
import type {Column, Filter, RowsAnswer, SummaryAnswer, ViewProps} from '../../src/classreport/types';

jest.mock('@moodle/lms/core/ajax', () => ({fetchOne: jest.fn()}));

const fetched = fetchOne as jest.Mock;

/** The strings, each its own name, so an assertion reads which string was drawn; a few carry placeholders. */
const LABELS: Record<string, string> = new Proxy({
    count: '{$a} learners',
    count_one: '{$a} learner',
    columnlimit: 'At most {$a} competencies.',
    proficientof: '{$a->proficient} of {$a->learners} proficient',
    ofcells: '{$a->percent}% of {$a->cells} cells',
    learnerssub: '{$a} shown',
    learnerssub_one: '{$a} shown',
    percent: '{$a}%',
    meterlabel: '{$a->title}: {$a->percent}%',
    range: '{$a->from}-{$a->to} of {$a->total}',
    filterchip: '{$a->title}: {$a->value}',
    removefilter: 'Remove filter {$a}',
    resize: 'Resize {$a}',
    showcount: 'Show {$a}',
    cardline: '{$a->proficient}/{$a->shown}',
}, {get: (target: Record<string, string>, name: string) => target[name] ?? name});

/** The props the shell writes. */
const PROPS: ViewProps = {
    courseid: 7,
    moduleid: 0,
    groupid: 12,
    reportid: 33,
    perpage: 30,
    perpages: [30, 50, 100],
    searchmin: 2,
    maxcolumns: 30,
    tableurl: 'https://example.com/report/competency/index.php?id=7&view=table',
    downloadurl: 'https://example.com/reportbuilder/download.php?id=33',
    formats: [{value: 'csv', label: 'CSV'}],
    labels: LABELS,
};

/**
 * The catalogue: the learner, then competencies 1 to n, the first `active` of them shown.
 *
 * @param {number} n How many competencies.
 * @param {number} active How many are shown.
 * @returns {Array} The columns.
 */
const catalogue = (n: number, active: number): Column[] => [
    {key: 'learner', title: 'Full name', sortable: true, "default": true, active: true, fixed: true},
    ...Array.from({length: n}, (_, i) => ({
        key: `comp:${i + 1}`,
        title: `Competency ${i + 1}`,
        sortable: true,
        "default": i < 30,
        active: i < active,
        fixed: false,
    })),
];

/** A rating filter on competency 1. */
const FILTER: Filter = {
    name: 'coursecompetency:rating1',
    title: 'Rating in Competency 1',
    type: 'rating',
    facet: 'choice',
    options: [{value: '1', label: 'Good', count: 1}, {value: '0', label: 'Not rated', count: 1}],
    applied: false,
    values: '{}',
};

/**
 * A rows answer.
 *
 * @param {Array} names The learners' names, one row each.
 * @param {object} more Fields to replace.
 * @returns {object} The answer.
 */
const rowsAnswer = (names: string[], more: Partial<RowsAnswer> = {}): RowsAnswer => ({
    courseid: 7,
    moduleid: 0,
    groupid: 12,
    search: '',
    nogroup: false,
    sort: '',
    direction: '',
    page: 0,
    perpage: 30,
    total: names.length,
    competencies: 2,
    columns: catalogue(2, 2),
    rows: names.map((name, i) => ({
        userid: i + 1,
        url: `https://example.com/report/competency/index.php?id=7&user=${i + 1}`,
        ratings: [{key: 'comp:1', competencyid: 1, grade: null, proficient: false}],
        cells: [
            {key: 'learner', html: `<a href="#">${name}</a>`},
            {key: 'comp:1', html: '<a class="dim-comp-classreport__cell" href="#">Not rated</a>'},
            {key: 'comp:2', html: 'Good'},
        ],
    })),
    counted: true,
    filters: [FILTER],
    preference: {usedefault: true, columns: [], widths: []},
    ...more,
});

/**
 * A summary answer.
 *
 * @param {object} more Fields to replace.
 * @returns {object} The answer.
 */
const summaryAnswer = (more: Partial<SummaryAnswer> = {}): SummaryAnswer => ({
    courseid: 7,
    moduleid: 0,
    groupid: 12,
    search: '',
    nogroup: false,
    learners: 2,
    cells: 4,
    rated: 1,
    proficient: 1,
    unrated: 1,
    competencies: [
        {key: 'comp:1', competencyid: 1, title: 'Competency 1', proficient: 1, notproficient: 0, notrated: 1},
        {key: 'comp:2', competencyid: 2, title: 'Competency 2', proficient: 0, notproficient: 0, notrated: 2},
    ],
    ...more,
});

type Request = {methodname: string, args: Record<string, unknown>, resolve: (value: unknown) => void,
    reject: (error: unknown) => void};

/** Every request made, in order, each settled by the test. */
let requests: Request[] = [];

beforeEach(() => {
    document.documentElement.lang = 'en';
    requests = [];
    fetched.mockReset();
    fetched.mockImplementation(({methodname, args}: {methodname: string, args: Record<string, unknown>}) =>
        new Promise((resolve, reject) => {
            requests.push({methodname, args, resolve, reject});
        }));
    try {
        window.localStorage.clear();
    } catch {
        // Nothing to clear.
    }
});

afterEach(() => {
    document.body.replaceChildren();
});

/**
 * The requests to one service that are still to be answered or were made, in order.
 *
 * @param {string} methodname The service.
 * @returns {Array} The requests.
 */
const to = (methodname: string): Request[] => requests.filter((request) => request.methodname === methodname);

const ROWS = 'theme_dimensions_class_report_rows';
const SUMMARY = 'theme_dimensions_class_report_summary';
const PREFERENCE = 'theme_dimensions_set_class_report_preference';

/**
 * Answer a request and let the view draw it.
 *
 * @param {object} request The request.
 * @param {*} value The answer.
 * @returns {Promise} Settled once drawn.
 */
const answer = async(request: Request, value: unknown): Promise<void> => {
    await act(async() => {
        request.resolve(value);
    });
};

/**
 * Render the view inside the shell's root, and answer its first two requests.
 *
 * @param {object} rows The first rows answer, or null to leave it pending.
 * @param {object} summary The first summary answer, or null to leave it pending.
 * @returns {Promise} The shell's root.
 */
const mount = async(rows: RowsAnswer | null, summary: SummaryAnswer | null): Promise<HTMLElement> => {
    const shell = document.createElement('div');
    shell.dataset.region = 'dim-comp-classreport';
    const node = document.createElement('div');
    shell.append(node);
    document.body.append(shell);
    const start = requests.length;
    await act(async() => {
        render(<ClassReportView {...PROPS} />, {container: node});
    });
    const own = requests.slice(start);
    if (rows !== null) {
        await answer(own.find((request) => request.methodname === ROWS) as Request, rows);
    }
    if (summary !== null) {
        await answer(own.find((request) => request.methodname === SUMMARY) as Request, summary);
    }
    return shell;
};

/**
 * The learners' names the rows region shows, in order.
 *
 * @param {HTMLElement} shell The shell.
 * @returns {Array} The names.
 */
const names = (shell: HTMLElement): string[] => {
    const cells = shell.querySelectorAll('[data-region="dim-comp-rview-rows"] tbody th');
    return Array.from(cells).map((cell) => (cell.textContent || '').trim());
};

describe('the class report view', () => {
    it('asks for the rows and the band in two requests, each naming the page\'s group, never -1', async() => {
        const shell = await mount(rowsAnswer(['Ana', 'Bia']), summaryAnswer());
        expect(to(ROWS)).toHaveLength(1);
        expect(to(SUMMARY)).toHaveLength(1);
        expect(to(ROWS)[0].args).toMatchObject({courseid: 7, moduleid: 0, groupid: 12, page: 0, perpage: 30});
        expect(to(SUMMARY)[0].args).toEqual({courseid: 7, moduleid: 0, groupid: 12, search: ''});
        expect(fetched.mock.calls.every(([request]) => request.args.groupid !== -1)).toBe(true);
        expect(names(shell)).toEqual(['Ana', 'Bia']);
        expect(shell.querySelector('[data-region="dim-comp-rview-total"]')?.textContent).toBe('2 learners');
    });

    it('paints only the newest answer of the rows', async() => {
        const shell = await mount(rowsAnswer(['Ana', 'Bia']), summaryAnswer());
        const sort = shell.querySelector('thead th[data-column="learner"] button') as HTMLButtonElement;
        fireEvent.click(sort);
        fireEvent.click(sort);
        const [, older, newer] = to(ROWS);
        expect(newer.args).toMatchObject({sort: 'learner'});
        // The newer answers first, then the older arrives late and is dropped.
        await answer(newer, rowsAnswer(['Bia', 'Ana'], {sort: 'learner', direction: 'desc'}));
        await answer(older, rowsAnswer(['Ana', 'Bia'], {sort: 'learner', direction: 'asc'}));
        expect(names(shell)).toEqual(['Bia', 'Ana']);
        expect(shell.querySelector('thead th[data-column="learner"]')?.getAttribute('aria-sort')).toBe('descending');
    });

    it('writes a column change, then reads the rows and the band again, and not before the write lands', async() => {
        const shell = await mount(rowsAnswer(['Ana']), summaryAnswer());
        fireEvent.click(shell.querySelector('button[aria-label="columns"]') as HTMLElement);
        const box = shell.querySelector('[data-region="dim-comp-rview-columns"] [data-column="comp:2"]') as HTMLElement;
        expect(box.getAttribute('aria-checked')).toBe('true');
        fireEvent.click(box);
        expect(to(PREFERENCE)).toHaveLength(1);
        expect(to(PREFERENCE)[0].args).toEqual({courseid: 7, usedefault: false, columns: ['comp:1'], widths: []});
        // The menu answers at once; nothing is read while the write is in flight.
        expect(box.getAttribute('aria-checked')).toBe('false');
        expect(to(ROWS)).toHaveLength(1);
        expect(to(SUMMARY)).toHaveLength(1);
        await answer(to(PREFERENCE)[0], {usedefault: false, columns: ['comp:1'], widths: []});
        expect(to(ROWS)).toHaveLength(2);
        expect(to(SUMMARY)).toHaveLength(2);
        expect(to(ROWS)[1].args).toMatchObject({groupid: 12, page: 0});
    });

    it('offers no more than the limit of competency columns and says why', async() => {
        const shell = await mount(rowsAnswer(['Ana'], {columns: catalogue(31, 30), competencies: 31}), summaryAnswer());
        fireEvent.click(shell.querySelector('button[aria-label="columns"]') as HTMLElement);
        const menu = shell.querySelector('[data-region="dim-comp-rview-columns"]') as HTMLElement;
        const extra = menu.querySelector('[data-column="comp:31"]') as HTMLElement;
        const shown = menu.querySelector('[data-column="comp:5"]') as HTMLElement;
        expect(extra.getAttribute('aria-disabled')).toBe('true');
        expect(shown.getAttribute('aria-disabled')).toBe('false');
        const note = menu.querySelector('.dim-comp-rview-menu-note') as HTMLElement;
        expect(note.textContent).toBe('At most 30 competencies.');
        expect(extra.getAttribute('aria-describedby')).toBe(note.id);
        fireEvent.click(extra);
        expect(to(PREFERENCE)).toHaveLength(0);
        // The control: one hidden makes room, and the unchecked one may be checked again.
        fireEvent.click(shown);
        expect(to(PREFERENCE)).toHaveLength(1);
        expect(extra.getAttribute('aria-disabled')).toBe('false');
        expect(menu.querySelector('.dim-comp-rview-menu-note')).toBeNull();
    });

    it('keeps the last competency column', async() => {
        const shell = await mount(rowsAnswer(['Ana'], {columns: catalogue(2, 1)}), summaryAnswer());
        fireEvent.click(shell.querySelector('button[aria-label="columns"]') as HTMLElement);
        const last = shell.querySelector('[data-region="dim-comp-rview-columns"] [data-column="comp:1"]') as HTMLElement;
        expect(last.getAttribute('aria-disabled')).toBe('true');
        fireEvent.click(last);
        expect(to(PREFERENCE)).toHaveLength(0);
    });

    it('draws a figure with no value as a dash', async() => {
        const shell = await mount(rowsAnswer(['Ana']), summaryAnswer({cells: 0, rated: 0, proficient: 0, unrated: 2,
            competencies: []}));
        const band = shell.querySelector('[data-region="dim-comp-rview-band"]') as HTMLElement;
        const value = (name: string): string =>
            band.querySelector(`[data-tile="${name}"] .dim-comp-rview-tile-value`)?.textContent ?? '';
        expect(value('learners')).toBe('2');
        expect(value('rated')).toBe(NONE);
        expect(value('proficient')).toBe(NONE);
        expect(value('unrated')).toBe(NONE);
        expect(band.textContent).toContain('nocompetencies');
    });

    it('draws a competency over no learner with a dash and no meter, and the others with theirs', async() => {
        const shell = await mount(rowsAnswer(['Ana']), summaryAnswer({learners: 0, cells: 0}));
        const band = shell.querySelector('[data-region="dim-comp-rview-band"]') as HTMLElement;
        expect(band.querySelectorAll('[role="progressbar"]')).toHaveLength(0);
        expect(band.querySelector('[data-competency="1"] .dim-comp-rview-hrow-n')?.textContent).toBe(NONE);

        const other = await mount(rowsAnswer(['Ana']), summaryAnswer());
        const meter = other.querySelector('[data-competency="1"] [role="progressbar"]') as HTMLElement;
        expect(meter.getAttribute('aria-valuenow')).toBe('50');
        expect(other.querySelector('[data-competency="1"] .dim-comp-rview-hrow-n')?.textContent).toBe('1 of 2 proficient');
    });

    it('says why there is no row: none at all, or none under the criteria in force', async() => {
        const shell = await mount(rowsAnswer([]), summaryAnswer());
        expect(shell.querySelector('.dim-comp-rview-empty-title')?.textContent).toBe('empty');

        const filtered = await mount(rowsAnswer([], {filters: [{...FILTER, applied: true,
            values: '{"coursecompetency:rating1_operator":1,"coursecompetency:rating1_value":"1"}'}]}), summaryAnswer());
        expect(filtered.querySelector('.dim-comp-rview-empty-title')?.textContent).toBe('nomatch');
        expect(filtered.querySelector('.dim-comp-rview-empty-text')?.textContent).toBe('nomatchhelp');
    });

    it('says the rows failed with a retry that asks again', async() => {
        const shell = await mount(null, summaryAnswer());
        await act(async() => {
            to(ROWS)[0].reject(new Error('down'));
        });
        const region = shell.querySelector('[data-region="dim-comp-rview-rows"]') as HTMLElement;
        expect(region.textContent).toContain('loaderror');
        const retry = Array.from(region.querySelectorAll('button')).find((button) => button.textContent === 'retry');
        fireEvent.click(retry as HTMLElement);
        expect(to(ROWS)).toHaveLength(2);
        await answer(to(ROWS)[1], rowsAnswer(['Ana']));
        expect(names(shell)).toEqual(['Ana']);
        expect(region.textContent).not.toContain('loaderror');
    });

    it('reads the same page of rows and the band again when core\'s grading dialogue closes', async() => {
        const shell = await mount(rowsAnswer(['Ana'], {total: 40}), summaryAnswer());
        fireEvent.click(Array.from(shell.querySelectorAll('.dim-comp-rview-pager button')).find((button) =>
            button.textContent === '2') as HTMLElement);
        await answer(to(ROWS)[1], rowsAnswer(['Zé'], {total: 40, page: 1}));
        expect(shell.matches(ROOT)).toBe(true);
        await act(async() => {
            shell.dispatchEvent(new CustomEvent(RATED, {bubbles: true, detail: {userid: 1, competencyid: 1, courseid: 7}}));
        });
        expect(to(ROWS)).toHaveLength(3);
        expect(to(ROWS)[2].args).toMatchObject({page: 1, groupid: 12, countfacets: true});
        expect(to(SUMMARY)).toHaveLength(2);
    });

    it('writes a facet as the whole set of stored values, with the report\'s parameters', async() => {
        const shell = await mount(rowsAnswer(['Ana']), summaryAnswer());
        fireEvent.click(shell.querySelector('button[aria-label="filters"]') as HTMLElement);
        fireEvent.click(Array.from(shell.querySelectorAll('[role="radio"]')).find((radio) =>
            (radio.textContent || '').startsWith('Good')) as HTMLElement);
        const write = to('core_reportbuilder_set_filters');
        expect(write).toHaveLength(1);
        expect(write[0].args).toEqual({
            reportid: 33,
            parameters: JSON.stringify({moduleid: 0, groupid: 12}),
            values: JSON.stringify({'coursecompetency:rating1_operator': 1, 'coursecompetency:rating1_value': '1'}),
        });
        expect(to(ROWS)).toHaveLength(1);
        await answer(write[0], true);
        await waitFor(() => expect(to(ROWS)).toHaveLength(2));
        expect(to(SUMMARY)).toHaveLength(2);
    });
});

describe('the class report\'s pure parts', () => {
    it('builds a download with the format and the report\'s parameters, the search only when in force', () => {
        const url = new URL(downloadHref('https://example.com/reportbuilder/download.php?id=33', 'csv', 4, 12, 'Ana & Bia'));
        expect(url.searchParams.get('id')).toBe('33');
        expect(url.searchParams.get('download')).toBe('csv');
        expect(JSON.parse(url.searchParams.get('parameters') as string)).toEqual({moduleid: 4, groupid: 12, search: 'Ana & Bia'});
        const bare = new URL(downloadHref('https://example.com/reportbuilder/download.php?id=33', 'csv', 0, 0, ''));
        expect(JSON.parse(bare.searchParams.get('parameters') as string)).toEqual({moduleid: 0, groupid: 0});
    });

    it('keeps the default columns when only a width is written', () => {
        expect(preferenceOf(true, ['comp:1'], {learner: 300})).toEqual(
            {usedefault: false, columns: [], widths: [{key: 'learner', width: 300}]});
        expect(preferenceOf(false, ['comp:2'], {})).toEqual({usedefault: false, columns: ['comp:2'], widths: []});
    });

    it('takes no share of nothing', () => {
        expect(percent(1, 0)).toBeNull();
        expect(percent(1, 3)).toBe(33);
        expect(percent(5, 3)).toBe(100);
        const values = tiles(LABELS, summaryAnswer({cells: 0})).map((tile) => tile.value);
        expect(values).toEqual(['2', NONE, NONE, '1']);
    });
});
