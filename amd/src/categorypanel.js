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
 * The panel itself - one area at a time, its place between the rows, its address, its two orders,
 * its rail, its keyboard and its states - is categoryareapanel's, which the visitor's module arms
 * too. What this module adds is what belongs to an account: the listing's web service a panel is
 * asked through, the progress of the enrolled courses the panel's cards show, the star of each
 * card, and the automatic opening of the one area holding every result.
 *
 * It runs on every page of the logged-in shell, the root included, and never on the anonymous
 * shell, whose panels categorypublic arms, nor for the guest. The listing's own module coordinates with it through events on the
 * document: a request for new criteria went out, its answer arrived, or it failed; the panel
 * module answers with one of its own when it opened an area by itself, and both hear the view
 * change from the shared listing.
 *
 * @module     theme_dimensions/categorypanel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import * as AreaPanel from 'theme_dimensions/categoryareapanel';
import * as Posters from 'theme_dimensions/categoryposters';
import * as CategoryStar from 'theme_dimensions/categorystar';
import * as DateRange from 'theme_dimensions/daterange';

/** @type {Object} Every hook this module reads, all of them data attributes of the showcase's templates. */
const SELECTORS = {
    areas: '[data-region="dim-areas"]',
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

/**
 * @type {Object} The events on the document the listing's module tells this one about: a request
 * for new criteria went out, its answer arrived, or it failed.
 */
const EVENTS = {
    pending: 'dim-vitrine:pending',
    criteria: 'dim-vitrine:criteria',
    failed: 'dim-vitrine:failed',
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
 * The criteria's answer arrived: the panel module paints it, then the one area holding every result opens.
 *
 * The automatic opening comes last, after the posters carry the new numbers and the open panel has
 * been asked again ({@see AreaPanel.criteria}).
 *
 * @param {Object} detail The answer's accordion, its word on the areas, whether it narrows, its total.
 * @return {void}
 */
const onCriteria = (detail) => {
    AreaPanel.criteria(detail);
    AreaPanel.autoOpen(detail.areas || {});
};

/**
 * Wire the listing module's three events.
 *
 * @return {void}
 */
const listenToListing = () => {
    document.addEventListener(EVENTS.pending, (event) => Posters.pending(areas, event.detail || {}));
    document.addEventListener(EVENTS.failed, () => Posters.failed(areas));
    // The criteria changed: the posters are their results, and the open panel is a listing of them one level down.
    document.addEventListener(EVENTS.criteria, (event) => onCriteria(event.detail || {}));
};

/**
 * Arm the panels of this page for a logged-in account.
 *
 * @return {void}
 */
export const init = () => {
    areas = document.querySelector(SELECTORS.areas);
    if (!areas || !AreaPanel.arm({ask: ask, painted: paintProgress})) {
        return;
    }
    // The star of the panel's cards, the root's included; categorystar's init() is idempotent.
    CategoryStar.init();
    listenToListing();
};
