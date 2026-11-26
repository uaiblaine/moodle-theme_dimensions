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
 * The shapes the class report's view reads: its props and the three web services' answers.
 *
 * The answers are the allowlists of classes/external/class_report_rows.php, class_report_summary.php and
 * class_report_preference_structure.php, field for field; keep them in step. The props are what
 * theme_dimensions\output\class_report_view::props() writes.
 *
 * @module     theme_dimensions/classreport/types
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Column, FilterOption, ViewMode} from '../datareport/types';

export type {Column, FilterOption, ViewMode};

/** One download format: the dataformat's name and its label. */
export type Format = {value: string, label: string};

/** The props class_report_view::props() writes into the page: configuration and strings, never a record. */
export type ViewProps = {
    courseid: number,
    /** The activity whose competencies are the columns, 0 for none. */
    moduleid: number,
    /** The group the page resolved (0 for all participants): posted on every read, never -1. */
    groupid: number,
    /** The report persistent: core's filter services and core's download name it. */
    reportid: number,
    perpage: number,
    perpages: number[],
    searchmin: number,
    /** The most competency columns shown at once (classreport::MAX_COLUMNS). */
    maxcolumns: number,
    /** Index.php with view=table: core's table of the report. */
    tableurl: string,
    /** Core's reportbuilder/download.php with the report's id; the bar adds the format and the parameters. */
    downloadurl: string,
    /** The site's enabled download formats. */
    formats: Format[],
    labels: Record<string, string>,
};

/** One cell of a row: the report's own formatted HTML. */
export type Cell = {key: string, html: string};

/** A learner's rating in one shown competency. */
export type Rating = {key: string, competencyid: number, grade: number | null, proficient: boolean};

/** One row of the rows answer: a learner, the breakdown's address, the ratings and the cells. */
export type Row = {
    userid: number,
    url: string,
    ratings: Rating[],
    cells: Cell[],
};

/** A row as the shared parts draw it: the learner's id is unique in a page. */
export type LearnerRow = Row & {id: number};

/** The panel's facet that draws a filter: choice, or '' for one set in core's table view. */
export type Facet = '' | 'choice';

/** One of the report's filters, as the rows answer describes it. */
export type Filter = {
    name: string,
    title: string,
    type: string,
    facet: Facet,
    options: FilterOption[],
    applied: boolean,
    values: string,
};

/** The reader's columns and widths on this course's class report. */
export type Preference = {
    usedefault: boolean,
    columns: string[],
    widths: {key: string, width: number}[],
};

/** What both answers echo of the request, as the report applied it. */
export type Applied = {
    courseid: number,
    moduleid: number,
    groupid: number,
    search: string,
    nogroup: boolean,
};

/** The theme_dimensions_class_report_rows answer. */
export type RowsAnswer = Applied & {
    sort: string,
    direction: '' | 'asc' | 'desc',
    page: number,
    perpage: number,
    total: number,
    competencies: number,
    columns: Column[],
    rows: Row[],
    counted: boolean,
    filters: Filter[],
    preference: Preference,
};

/** One shown competency's figures in the band. */
export type CompetencyFigures = {
    key: string,
    competencyid: number,
    title: string,
    proficient: number,
    notproficient: number,
    notrated: number,
};

/** The theme_dimensions_class_report_summary answer. */
export type SummaryAnswer = Applied & {
    learners: number,
    cells: number,
    rated: number,
    proficient: number,
    unrated: number,
    competencies: CompetencyFigures[],
};

/** What a rows request asks for. */
export type RowsQuery = {
    search: string,
    page: number,
    perpage: number,
    sort: string,
    direction: 'asc' | 'desc',
};
