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
 * The course competencies page: the theme's extras kept through core's re-renders.
 *
 * The theme's copy of tool_lp/course_competencies_page starts this module from its js block, on the first paint and
 * again every time core's tool_lp/competencies or tool_lp/course_competency_settings replaces the region (after adding
 * or removing a competency, changing a course outcome or the ratings setting: they render the template on the client
 * from tool_lp_data_for_course_competencies_page and run the js block again). That re-run is the re-arm point, so no
 * observer is needed. Reordering moves the rows in place and renders nothing: the extras move with their rows.
 *
 * - First paint (the root carries data-dim-extras): each competency's extras and each activity's are remembered.
 * - Core re-rendered (no data-dim-extras, on a page whose first paint had them): the remembered extras are put back at
 *   once, so nothing flashes, then theme_dimensions_get_course_competency_extras is asked for fresh ones (a competency
 *   may have been added; the figures are the server's). The latest request wins; a failure keeps what is there.
 * - A page whose first paint had no extras (the site course) stays core's.
 *
 * Every extra is the theme's own template rendered from the service's plain values, which its double stashes escape.
 *
 * @module     theme_dimensions/coursecompetencies
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Pending from 'core/pending';
import Templates from 'core/templates';

/** The web service that answers the extras after a re-render. */
const EXTRAS = 'theme_dimensions_get_course_competency_extras';

/** The theme's two templates, one per competency and one per activity. */
const TEMPLATES = {
    EXTRA: 'theme_dimensions/competency_course_extra',
    CM: 'theme_dimensions/competency_course_cm',
};

const SELECTORS = {
    ROOT: '[data-region="coursecompetenciespage"]',
    ROW: 'tr.drag-samenode[data-id]',
    EXTRA: '[data-region="dim-comp-extra"]',
    CM: '[data-region="dim-comp-cm"]',
};

/** What the page remembers between core's re-renders: the markup of each slot, and the latest request's ticket. */
const state = {
    armed: false,
    courseid: 0,
    extras: new Map(),
    cms: new Map(),
    ticket: 0,
};

/**
 * The key of an activity's slot: an activity may serve several competencies, each with its own outcome.
 *
 * @param {string} competencyid The competency.
 * @param {string} cmid The activity.
 * @returns {string}
 */
const cmKey = (competencyid, cmid) => `${competencyid}/${cmid}`;

/**
 * Visit every slot of the region: each competency's extra and each activity's.
 *
 * @param {HTMLElement} root The region.
 * @param {Function} onExtra Called with (slot, competency id).
 * @param {Function} onCm Called with (slot, the activity's key).
 */
const visit = (root, onExtra, onCm) => {
    root.querySelectorAll(SELECTORS.ROW).forEach((row) => {
        const competencyid = row.getAttribute('data-id');
        const extra = row.querySelector(SELECTORS.EXTRA);
        if (extra) {
            onExtra(extra, competencyid);
        }
        row.querySelectorAll(SELECTORS.CM).forEach((slot) => {
            onCm(slot, cmKey(competencyid, slot.getAttribute('data-cmid')));
        });
    });
};

/**
 * Remember the first paint's extras.
 *
 * @param {HTMLElement} root The region.
 */
const remember = (root) => {
    visit(
        root,
        (slot, competencyid) => state.extras.set(competencyid, slot.innerHTML),
        (slot, key) => state.cms.set(key, slot.innerHTML)
    );
};

/**
 * Put every remembered extra back in its slot. The markup is the theme's own templates, rendered by the server on the
 * first paint or by this module from the service's plain values.
 *
 * @param {HTMLElement} root The region.
 */
const restore = (root) => {
    visit(
        root,
        (slot, competencyid) => {
            if (state.extras.has(competencyid)) {
                slot.innerHTML = state.extras.get(competencyid);
            }
        },
        (slot, key) => {
            if (state.cms.has(key)) {
                slot.innerHTML = state.cms.get(key);
            }
        }
    );
};

/**
 * Render the service's answer into markup per slot.
 *
 * @param {Object} answer The service's answer.
 * @returns {Promise<Object>} {extras: Map, cms: Map}.
 */
const render = async(answer) => {
    const extras = new Map();
    const cms = new Map();
    const jobs = [];
    answer.competencies.forEach((entry) => {
        const competencyid = String(entry.competencyid);
        jobs.push(Templates.renderForPromise(TEMPLATES.EXTRA, entry).then(({html}) => {
            extras.set(competencyid, html);
            return html;
        }));
        entry.activities.forEach((activity) => {
            jobs.push(Templates.renderForPromise(TEMPLATES.CM, activity).then(({html}) => {
                cms.set(cmKey(competencyid, String(activity.cmid)), html);
                return html;
            }));
        });
    });
    await Promise.all(jobs);
    return {extras, cms};
};

/**
 * Ask for fresh extras after core re-rendered the region, and put them in place.
 *
 * @returns {Promise<void>}
 */
const refill = async() => {
    const ticket = ++state.ticket;
    const pending = new Pending('theme_dimensions/coursecompetencies:refill');
    try {
        const answer = await Ajax.call([{methodname: EXTRAS, args: {courseid: state.courseid}}])[0];
        const fresh = await render(answer);
        if (ticket !== state.ticket) {
            return;
        }
        state.extras = fresh.extras;
        state.cms = fresh.cms;
        const current = document.querySelector(SELECTORS.ROOT);
        if (current) {
            restore(current);
        }
    } catch (error) {
        window.console.error(error);
    } finally {
        pending.resolve();
    }
};

/**
 * Start on the first paint, and again after each of core's re-renders of the region.
 *
 * @param {number} courseid The course, as core's js block writes it.
 */
export const init = (courseid) => {
    const root = document.querySelector(SELECTORS.ROOT);
    if (!root) {
        return;
    }
    if (root.hasAttribute('data-dim-extras')) {
        state.armed = true;
        state.courseid = Number(courseid);
        remember(root);
        return;
    }
    if (!state.armed || state.courseid !== Number(courseid)) {
        return;
    }
    restore(root);
    refill();
};
