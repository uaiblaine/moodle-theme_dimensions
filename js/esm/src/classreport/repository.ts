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
 * The only module of the class report's view that talks to the server's web services.
 *
 * Every call goes through core's ES module @moodle/lms/core/ajax (a component cannot import an AMD module),
 * and every call is ONE request of its own (fetchOne), never a batch: core keeps one instance of a report per
 * user for the whole request, so a rows call batched after a filter or preference write would read the report
 * built before it (the preference's writer never builds the report for that reason,
 * classes/external/set_class_report_preference.php). Core's ajax holds Behat's pending counter while each
 * request is in flight; the view holds it across the render that follows (datareport/pending.ts). Copied from
 * local_evidencehub's hub/repository.ts.
 *
 * The report is named by its course, its activity and the page's group: the group is always the one the page
 * resolved, never -1, so the session's choice is never read on another request than the page's. Core's filter
 * services rebuild the report from its persistent with the same activity and group as its parameters, so the
 * filters they read are the ones the rows show.
 *
 * @module     theme_dimensions/classreport/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import type {Preference, RowsAnswer, RowsQuery, SummaryAnswer} from './types';

/** The report's object: the course, the activity and the page's group. */
export type Target = {courseid: number, moduleid: number, groupid: number};

/**
 * A page of the learners.
 *
 * @param {object} target The course, the activity and the group.
 * @param {object} query What to read; see RowsQuery.
 * @param {boolean} countfacets Whether to count the filter panel's options: only when a count can have moved.
 * @returns {Promise} The answer.
 */
export const getRows = (target: Target, query: RowsQuery, countfacets: boolean): Promise<RowsAnswer> =>
    fetchOne<RowsAnswer>({
        methodname: 'theme_dimensions_class_report_rows',
        args: {
            ...target,
            search: query.search,
            page: query.page,
            perpage: query.perpage,
            sort: query.sort,
            dir: query.direction,
            countfacets,
        },
    });

/**
 * The band's figures over the same learners.
 *
 * @param {object} target The course, the activity and the group.
 * @param {string} search Part of the learner's name, or ''.
 * @returns {Promise} The answer.
 */
export const getSummary = (target: Target, search: string): Promise<SummaryAnswer> =>
    fetchOne<SummaryAnswer>({methodname: 'theme_dimensions_class_report_summary', args: {...target, search}});

/**
 * Store the reader's columns and widths on the course's class report: the preference's one writer.
 *
 * @param {number} courseid The course.
 * @param {object} preference Whether the default columns show, the chosen keys, the widths.
 * @returns {Promise} The preference as stored.
 */
export const setPreference = (courseid: number, preference: Preference): Promise<Preference> =>
    fetchOne<Preference>({
        methodname: 'theme_dimensions_set_class_report_preference',
        args: {
            courseid,
            usedefault: preference.usedefault,
            columns: preference.columns,
            widths: preference.widths,
        },
    });

/**
 * The report's own parameters, as core's services rebuild the report with them: the activity and the group.
 *
 * @param {object} target The course, the activity and the group.
 * @returns {string} The parameters, JSON.
 */
export const reportParameters = (target: Target): string =>
    JSON.stringify({moduleid: target.moduleid, groupid: target.groupid});

/**
 * Store the reader's filter values in core's own store for the report.
 *
 * @param {number} reportid The report persistent.
 * @param {object} target The course, the activity and the group.
 * @param {object} values Every filter value of the report, as core's filter form posts them.
 * @returns {Promise} Whether core stored them.
 */
export const setFilters = (reportid: number, target: Target, values: Record<string, unknown>): Promise<boolean> =>
    fetchOne<boolean>({
        methodname: 'core_reportbuilder_set_filters',
        args: {reportid, parameters: reportParameters(target), values: JSON.stringify(values)},
    });

/**
 * Forget every filter value the reader stored for the report.
 *
 * @param {number} reportid The report persistent.
 * @param {object} target The course, the activity and the group.
 * @returns {Promise} Whether core reset them.
 */
export const resetFilters = (reportid: number, target: Target): Promise<boolean> =>
    fetchOne<boolean>({
        methodname: 'core_reportbuilder_filters_reset',
        args: {reportid, parameters: reportParameters(target)},
    });
