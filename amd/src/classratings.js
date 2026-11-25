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
 * The class report's rating cells: core's grading dialogue, then Report Builder's own reload of the rows.
 *
 * A press on a cell carrying the hooks core's breakdown cells carry (data-user-competency="true", data-userid,
 * data-competencyid, data-courseid; the server writes them only for a viewer who may rate) does what core's
 * report_competency/grading_popup does in _handleClick and _contextLoaded: the same two web services, core's
 * tool_lp/user_competency_summary_in_course template with the user shown, in core's tool_lp/dialogue. Core's module
 * cannot be armed on the class report: when its dialogue closes it re-renders ONE learner's breakdown into its
 * region (_refresh). Here the dialogue's close dispatches Report Builder's tableReload event on the report, which
 * core_reportbuilder/report answers by reading the rows again, the page and sort kept.
 *
 * tests/local/classratings_source_test.php reads core's module for every name this one mirrors.
 *
 * @module     theme_dimensions/classratings
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';
import Templates from 'core/templates';
import {getString} from 'core/str';
import {dispatchEvent} from 'core/event_dispatcher';
import Dialogue from 'tool_lp/dialogue';
import * as reportEvents from 'core_reportbuilder/local/events';
import * as reportSelectors from 'core_reportbuilder/local/selectors';
import TableEvents from 'core_table/local/dynamic/events';

const ROOT = '[data-region="dim-comp-classreport"]';
const CELL = '[data-user-competency="true"]';
const SUMMARY = 'tool_lp_data_for_user_competency_summary_in_course';
const VIEWED = 'core_competency_user_competency_viewed_in_course';
const TEMPLATE = 'tool_lp/user_competency_summary_in_course';

let armed = false;

/**
 * The learner, the competency and the course a cell names, as integers.
 *
 * @param {HTMLElement} cell The cell's link.
 * @returns {Object} userid, competencyid, courseid.
 */
const cellArgs = (cell) => ({
    userid: parseInt(cell.getAttribute('data-userid'), 10),
    competencyid: parseInt(cell.getAttribute('data-competencyid'), 10),
    courseid: parseInt(cell.getAttribute('data-courseid'), 10),
});

/**
 * Read the report's rows again, keeping the page, under a Pending token until the table is painted.
 *
 * @param {HTMLElement|null} report The report's root (core_reportbuilder/report).
 */
const reload = (report) => {
    if (!report) {
        return;
    }
    const pending = new Pending('theme_dimensions/classratings:reload');
    report.addEventListener(TableEvents.tableContentRefreshed, () => pending.resolve(), {once: true});
    dispatchEvent(reportEvents.tableReload, {preservePagination: true}, report);
};

/**
 * Open core's grading dialogue for one cell.
 *
 * @param {HTMLElement} cell The cell's link.
 * @returns {Promise}
 */
const open = async(cell) => {
    const pending = new Pending('theme_dimensions/classratings:open');
    const report = cell.closest(reportSelectors.regions.report);
    const args = cellArgs(cell);
    try {
        const [context] = await Promise.all(Ajax.call([
            {methodname: SUMMARY, args},
            {methodname: VIEWED, args},
        ]));
        context.displayuser = true;
        const [title, {html, js}] = await Promise.all([
            getString('usercompetencysummary', 'report_competency'),
            Templates.renderForPromise(TEMPLATE, context),
        ]);
        return new Dialogue(
            title,
            html,
            () => {
                Templates.runTemplateJS(js);
                pending.resolve();
            },
            () => reload(report),
            true
        );
    } catch (error) {
        pending.resolve();
        return Notification.exception(error);
    }
};

/**
 * Listen for presses on the class report's rating cells, once per page.
 */
export const init = () => {
    if (armed) {
        return;
    }
    armed = true;
    document.addEventListener('click', (event) => {
        const cell = event.target.closest(CELL);
        if (!cell || !cell.closest(ROOT)) {
            return;
        }
        event.preventDefault();
        open(cell);
    });
};
