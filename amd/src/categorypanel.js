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
 * The panel between the rows of posters for a logged-in account: one area's courses, opened in place.
 *
 * Since stage R3 of docs/vitrine-react/PLAN.md the posters and the panel of the logged-in shell are a
 * React component (js/esm/src/areas/Areas.tsx), which the shared listing's mountAreas() appends to the
 * areas section for this module, as it does for the visitor's since R4a, and which owns them from then on:
 * the panel's frame, the posters' result layer and the summary of the areas with no result. What this
 * module keeps is what belongs to an account, which the component neither calls nor reads: the listing's
 * web service a panel is asked through, the progress of the enrolled courses a panel shows, and the star
 * of each card.
 *
 * It runs on every page of the logged-in shell, the root included, and never on the anonymous shell nor
 * for the guest. It answers the component through mountAreas(): the component asks for an area's rows
 * (this module's service's promise is the answer) and says the rows are on screen (this module paints
 * their progress); it names no question, so every request is asked, and takes no failure over, so a
 * failure is the panel's inline error. The listing's module talks to the component directly, through its
 * events on the document.
 *
 * @module     theme_dimensions/categorypanel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import * as CategoryStar from 'theme_dimensions/categorystar';
import * as DateRange from 'theme_dimensions/daterange';
import * as Listing from 'core/esm!@moodle/lms/theme_dimensions/listing/listing';

/** @type {Object} Every hook this module reads, all of them data attributes of the showcase's templates. */
const SELECTORS = {
    areas: '[data-region="dim-areas"]',
    panel: '[data-region="dim-panel"]',
    card: '[data-region="dim-card"]',
    progress: '[data-region="dim-card-progress"]',
};

/** @type {Number} How many courses a panel lists: the server's population::PANEL_SIZE. */
const PANEL_SIZE = 8;

/** @type {Object} The request parameter names, the same ones the listing's module and the server use. */
const PARAM = {
    query: 'q',
    chip: 'chip',
    fav: 'fav',
    from: 'from',
    to: 'to',
    fieldprefix: 'cf_',
};

/**
 * @type {String} The value the state chip carried for the favourites before they were a toggle: an
 * address that still says so asks for the toggle and no chip, as the server's criteria reads it.
 */
const LEGACY_FAVOURITES = 'favourites';

/** @type {String} The one order a panel can be asked for besides its default. */
const PSORT_NAME = 'name';

/** @type {Object} The two flat orders of the web service a panel is listed in. */
const SORT = {
    recent: 'recent',
    name: 'name',
};

/** @type {HTMLElement|null} The areas section of this page. */
let areas = null;

/**
 * Whether this page is the root, which reads its search and no filter, and so sends the term alone with a panel.
 *
 * @return {Boolean}
 */
const isRoot = () => Number(areas.dataset.categoryid) === 0;

/**
 * The web service's arguments for an area: its subtree, eight courses, the page's criteria.
 *
 * The criteria are read from the address, which the listing's module keeps true; the tree's own
 * category is never sent, because the service refuses one that is not below the area asked about.
 * The period is read through the calendar's reading of it, as the listing's module reads it: either
 * bound may be absent, and a hand-edited pair given backwards, which the page put in order, would
 * otherwise reach a service that refuses it. The root reads its search and no filter, so the term
 * alone narrows a panel there.
 *
 * @param {Number} id The area.
 * @param {String} psort The panel's order: the empty string (the newest first) or name.
 * @return {Object}
 */
const panelArgs = (id, psort) => {
    const address = new URL(window.location.href).searchParams;
    let params = address;
    if (isRoot()) {
        params = new URLSearchParams();
        if (address.has(PARAM.query)) {
            params.set(PARAM.query, address.get(PARAM.query));
        }
    }
    const filters = [];
    params.forEach((value, name) => {
        if (name.startsWith(PARAM.fieldprefix) && Number(value) > 0) {
            filters.push({fieldid: Number(name.slice(PARAM.fieldprefix.length)), value: Number(value)});
        }
    });
    const period = DateRange.period(params.get(PARAM.from), params.get(PARAM.to));
    const chip = params.get(PARAM.chip) || '';
    return {
        categoryid: id,
        page: 0,
        perpage: PANEL_SIZE,
        sort: psort === PSORT_NAME ? SORT.name : SORT.recent,
        query: params.get(PARAM.query) || '',
        chip: chip === LEGACY_FAVOURITES ? '' : chip,
        fav: params.get(PARAM.fav) === '1' || chip === LEGACY_FAVOURITES,
        filters: filters,
        datefrom: period.from,
        dateto: period.to,
        withcategories: true,
    };
};

/**
 * Ask the listing's web service for an area's rows: the panel module's one question.
 *
 * @param {HTMLElement} poster The area's poster.
 * @param {String} psort The panel's order.
 * @return {Promise} The answer, its panel's context included.
 */
const ask = (poster, psort) => {
    const id = Number(poster.dataset.categoryid);
    return Ajax.call([{
        methodname: 'theme_dimensions_get_category_rows',
        args: panelArgs(id, psort),
    }])[0];
};

/**
 * Paint the progress of the panel's enrolled courses, in one batch of at most eight.
 *
 * The listing's own observer never watches a card of the panel: this is its one request, made once
 * the rows are on screen. A failure leaves the cards exactly as they are, with nothing announced.
 *
 * @param {HTMLElement} panel The panel's element.
 * @return {Promise} Resolving when the batch has been dealt with.
 */
const paintProgress = async(panel) => {
    const cards = Array.from(panel.querySelectorAll(SELECTORS.card)).filter((card) => card.dataset.state === 'enrolled');
    const ids = cards.map((card) => Number(card.dataset.courseid)).filter((id) => Number.isInteger(id) && id > 0);
    if (!ids.length) {
        return;
    }
    try {
        const response = await Ajax.call([{
            methodname: 'theme_dimensions_get_card_details',
            args: {courseids: ids.slice(0, PANEL_SIZE)},
        }])[0];
        (response.details || []).forEach((detail) => {
            const card = cards.find((one) => Number(one.dataset.courseid) === Number(detail.id));
            const slot = card ? card.querySelector(SELECTORS.progress) : null;
            if (!slot || !detail.progresslabel) {
                return;
            }
            slot.textContent = detail.progresslabel;
            slot.style.setProperty('--dim-progress', Number(detail.progress || 0) + '%');
            slot.hidden = false;
        });
    } catch (error) {
        // Progress is decoration: a card without it is still the card the server drew.
    }
};

/**
 * Hand the posters and their panel to the component, through the shared mount: the logged-in shell's
 * options are none ({}), and its answers are this module's service and its progress.
 *
 * @return {Boolean} Whether the posters were handed over.
 */
const mountAreas = () => Listing.mountAreas(areas, {}, {ask: ask, painted: paintProgress});

/**
 * Arm the panels of this page for a logged-in account.
 *
 * @return {void}
 */
export const init = () => {
    areas = document.querySelector(SELECTORS.areas);
    if (!areas || !areas.querySelector(SELECTORS.panel)) {
        return;
    }
    // The star of the panel's cards, the root's included; categorystar's init() is idempotent.
    CategoryStar.init();
    mountAreas();
};
