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
 * The posters of the areas as an answer under a criterion paints them: their results and the summary.
 *
 * Under a criterion the posters are the results: each wears the number of results its area holds,
 * an area with none leaves its grid for the "nothing in N areas" summary - all but the open one,
 * which stays until it is closed - the reveal past the slice follows the posters left behind it,
 * and the "nothing anywhere" box and its "search all areas" link follow the answer. Every number
 * and every sentence of that is the server's, carried by the answer's accordion and its word on the
 * areas as a whole; this module writes them onto the page and composes nothing.
 *
 * categoryareapanel paints them with every answer for the visitor's client, categorypublic, which marks
 * them pending while its request is out; since stage R3 of docs/vitrine-react/PLAN.md the logged-in shell
 * paints its posters with a React component of the ESM tree (js/esm/src/areas/posters.ts), which follows
 * these painters function for function until R4, so a rule changes in both. Nothing here calls a web
 * service or reads the session: the visitor's module imports this one. What the painting depends on -
 * whether a panel is open, and what a poster's control becomes when the answer changes whether it
 * opens - is handed in.
 *
 * @module     theme_dimensions/categoryposters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {Object} Every hook this module reads, all of them data attributes of the showcase's templates. */
const SELECTORS = {
    grid: '[data-region="dim-areas-grid"]',
    more: '[data-region="dim-areas-more"]',
    moresummary: '[data-region="dim-areas-moresummary"]',
    allempty: '[data-region="dim-areas-empty"]',
    searchall: '[data-region="dim-areas-searchall"]',
    nomatch: '[data-region="dim-areas-nomatch"]',
    nomatchsummary: '[data-region="dim-nomatch-summary"]',
    nomatchlist: '[data-region="dim-nomatch-list"]',
    area: '[data-region="dim-area"]',
    control: '[data-region="dim-poster-control"]',
    pill: '[data-region="dim-poster-pill"]',
};

/**
 * @type {String} What a pill says while the criteria's answer is out: a placeholder glyph and not a
 * sentence, so nothing is read out and nothing on the poster moves when the number arrives.
 */
const PENDING = '…';

/** @type {Object} The classes this module writes; it reads none of them. */
const CLASSES = {
    pillzero: 'dim-poster__pill--zero',
};

/**
 * One poster of the areas by its area's id.
 *
 * @param {HTMLElement} areas The areas section.
 * @param {Number} id The area.
 * @return {HTMLElement|null}
 */
const posterOf = (areas, id) => {
    if (!Number.isInteger(id) || id <= 0) {
        return null;
    }
    return areas.querySelector(SELECTORS.area + '[data-categoryid="' + id + '"]');
};

/**
 * The grid item a poster sits in.
 *
 * @param {HTMLElement} poster The poster.
 * @return {HTMLElement}
 */
const itemOf = (poster) => poster.parentElement;

/**
 * Whether the page is in result mode: a criterion narrows it and it has areas. The server's own
 * switch (category_listing::results()), written on the areas section and rewritten from every answer.
 *
 * @param {HTMLElement} areas The areas section.
 * @return {Boolean}
 */
export const isFiltered = (areas) => areas.dataset.filtered === '1';

/**
 * A request for new criteria went out: the grids are busy and, if it narrows, every pill waits.
 *
 * Nothing moves until the answer is in: the posters keep their places
 * and a pill says only that its number is coming, so no zero colour is left on an area the new
 * question may well find results in.
 *
 * @param {HTMLElement} areas The areas section.
 * @param {Object} detail Whether the requested criteria narrow the page (filtered).
 * @return {void}
 */
export const pending = (areas, detail) => {
    areas.querySelectorAll(SELECTORS.grid).forEach((grid) => grid.setAttribute('aria-busy', 'true'));
    if (!detail.filtered) {
        return;
    }
    areas.querySelectorAll(SELECTORS.pill).forEach((pill) => {
        pill.textContent = PENDING;
        pill.hidden = false;
        pill.classList.remove(CLASSES.pillzero);
    });
};

/**
 * Write a poster's pill from what the latest answer left on the poster: its words, whether it shows,
 * its zero colours, and whether the control is described by it.
 *
 * The pill changes silently (no live region: the count line and the panel speak for the page), and
 * the control is described by it only while it shows. The words are the server's, kept on the poster
 * (data-resultlabel), so a failed request can put back what its pending state took away.
 *
 * @param {HTMLElement} poster The poster.
 * @param {Boolean} filtered Whether the page is in result mode.
 * @return {void}
 */
const paintPill = (poster, filtered) => {
    const pill = poster.querySelector(SELECTORS.pill);
    const label = poster.dataset.resultlabel || '';
    const has = filtered && Boolean(pill) && label !== '';
    if (pill) {
        pill.textContent = label;
        pill.hidden = !has;
        pill.classList.toggle(CLASSES.pillzero, has && Number(poster.dataset.count) === 0);
    }
    const control = poster.querySelector(SELECTORS.control);
    if (control && has) {
        control.setAttribute('aria-describedby', pill.id);
    } else if (control) {
        control.removeAttribute('aria-describedby');
    }
};

/**
 * The request for new criteria failed: the grids stop being busy and every pill says the latest answer again.
 *
 * Nothing else moved while the question was out, so the pills are all there is to put back, each from
 * its poster's own data and the page's result mode, which are still the previous answer's. Without
 * this a failed request would leave every pill showing the pending ellipsis (PENDING) - and each
 * control described by it - until some later request succeeded.
 *
 * @param {HTMLElement} areas The areas section.
 * @return {void}
 */
export const failed = (areas) => {
    areas.querySelectorAll(SELECTORS.grid).forEach((grid) => grid.removeAttribute('aria-busy'));
    areas.querySelectorAll(SELECTORS.area).forEach((poster) => paintPill(poster, isFiltered(areas)));
};

/**
 * Write each area of the criteria's answer onto its poster: the pill, the counts, whether it opens,
 * and whether it stays in the grid.
 *
 * An area with no result under a criterion leaves its grid through the hidden attribute on its item -
 * never removed - except the open one, which stays in its place until the reader closes it.
 *
 * Whether each area opens is recorded on the caller's state and handed to the caller's own
 * expandable(): what a poster's control becomes when that changes is the caller's business - a
 * button or a link for the logged-in client, a link announced as a button or not for the visitor's.
 *
 * @param {HTMLElement} areas The areas section.
 * @param {Array} accordion The answer's areas: id, count, resultlabel, resultcount and expandable.
 * @param {Boolean} filtered Whether the answer is in result mode.
 * @param {Object} state The caller's open area (open, 0 for none) and its record of which areas open (expandable, a Map).
 * @param {Function} expandable Called with a poster and whether it now opens.
 * @return {void}
 */
export const paintPosters = (areas, accordion, filtered, state, expandable) => {
    /* Back at rest, every poster the answer does not name goes back to rest with it: the root at
       rest counts nothing, so its answer names no area, and without this pass a search
       cleared in place would leave the last term's pills and its hidden posters on the page. On a
       category page the answer names every poster, so the pass changes nothing there. */
    if (!filtered) {
        const named = new Set(accordion.map((entry) => Number(entry.id)));
        areas.querySelectorAll(SELECTORS.area).forEach((poster) => {
            if (named.has(Number(poster.dataset.categoryid))) {
                return;
            }
            poster.dataset.resultcount = '';
            poster.dataset.resultlabel = '';
            paintPill(poster, false);
            itemOf(poster).hidden = false;
        });
    }
    accordion.forEach((entry) => {
        const id = Number(entry.id);
        const poster = posterOf(areas, id);
        if (!poster) {
            return;
        }
        const count = Number(entry.count) || 0;
        poster.dataset.count = String(count);
        poster.dataset.resultcount = entry.resultcount || '';
        poster.dataset.resultlabel = entry.resultlabel || '';
        paintPill(poster, filtered);
        state.expandable.set(id, Boolean(entry.expandable));
        expandable(poster, Boolean(entry.expandable));
        itemOf(poster).hidden = filtered && count === 0 && id !== state.open;
    });
};

/**
 * Bring the "nothing in N areas" summary in line with the open panel: the open area is not in it.
 *
 * The list names every area with no result, the open one included, and hides the open one's entry
 * while its poster stays in the grid; the sentence counts the entries shown, which is why the
 * server writes both of its spellings on the element. Nothing is composed: the one choice made
 * here is between those two.
 *
 * @param {HTMLElement} areas The areas section.
 * @param {Number} open The open area, 0 for none.
 * @return {void}
 */
export const recountSummary = (areas, open) => {
    const box = areas.querySelector(SELECTORS.nomatch);
    const list = box ? box.querySelector(SELECTORS.nomatchlist) : null;
    if (!list) {
        return;
    }
    const items = Array.from(list.children);
    let openzero = false;
    items.forEach((item) => {
        item.hidden = Number(item.dataset.categoryid) === open;
        openzero = openzero || item.hidden;
    });
    const label = (openzero ? box.dataset.labelLess : box.dataset.labelAll) || '';
    const summary = box.querySelector(SELECTORS.nomatchsummary);
    if (summary) {
        summary.textContent = label;
    }
    box.hidden = !isFiltered(areas) || label === '';
};

/**
 * Rebuild the "nothing in N areas" summary from the criteria's answer: its entries and its two sentences.
 *
 * Each entry is the area's own page without the criterion (carrying it would open an empty page), or
 * its name as text where the answer says the area has no page this reader may open. The list is
 * built node by node and the name is the poster's own, so nothing reaches the page as markup.
 *
 * @param {HTMLElement} areas The areas section.
 * @param {Array} accordion The answer's areas.
 * @param {Object} data The answer's word on the areas as a whole.
 * @param {Number} open The open area, 0 for none.
 * @return {void}
 */
export const paintSummary = (areas, accordion, data, open) => {
    const box = areas.querySelector(SELECTORS.nomatch);
    const list = box ? box.querySelector(SELECTORS.nomatchlist) : null;
    if (!list) {
        return;
    }
    box.dataset.labelAll = data.nomatchlabelall || '';
    box.dataset.labelLess = data.nomatchlabelless || '';
    const zeros = data.filtered ? accordion.filter((entry) => Number(entry.count) === 0) : [];
    list.replaceChildren(...zeros.map((entry) => {
        const id = Number(entry.id);
        const poster = posterOf(areas, id);
        const name = poster ? poster.dataset.name || '' : entry.name || '';
        const item = document.createElement('li');
        item.dataset.categoryid = String(id);
        if (entry.linkable && entry.url) {
            const link = document.createElement('a');
            link.href = entry.url;
            link.textContent = name;
            item.append(link);
        } else {
            item.textContent = name;
        }
        return item;
    }));
    recountSummary(areas, open);
};

/**
 * Hide the reveal past the slice under a criterion when no poster behind it is left in its grid.
 *
 * The posters left are the ones holding a result and the open one, which is the server's own rule
 * for the reveal (category_listing::areas(), morehidden) read off the page, so it holds after an
 * answer and after the open poster behind it closes into the summary alike.
 *
 * @param {HTMLElement} areas The areas section.
 * @return {void}
 */
export const fitReveal = (areas) => {
    const more = areas.querySelector(SELECTORS.more);
    if (!more) {
        return;
    }
    const shown = Array.from(more.querySelectorAll(SELECTORS.area)).some((poster) => !itemOf(poster).hidden);
    more.hidden = isFiltered(areas) && !shown;
};

/**
 * Bring the reveal past the slice in line with the answer.
 *
 * Under a criterion it opens by itself when an area behind it holds a result, and is hidden when
 * none does and the open poster is not behind it either; at rest it is the reveal it always was,
 * its open state the reader's.
 *
 * @param {HTMLElement} areas The areas section.
 * @param {Object} data The answer's word on the areas as a whole: filtered, morelabel, moreresults.
 * @return {void}
 */
export const paintReveal = (areas, data) => {
    const more = areas.querySelector(SELECTORS.more);
    if (!more) {
        return;
    }
    const summary = more.querySelector(SELECTORS.moresummary);
    if (summary && data.morelabel) {
        summary.textContent = data.morelabel;
    }
    fitReveal(areas);
    if (Boolean(data.filtered) && Number(data.moreresults) > 0) {
        more.open = true;
    }
};

/**
 * Show the "nothing matches anywhere" box exactly when the criteria said so and no panel is open.
 *
 * An open panel is an area the reader is looking at, so the page is not empty while it is there.
 *
 * @param {HTMLElement} areas The areas section.
 * @param {Boolean} allempty Whether nothing matches anywhere, as the latest criteria said it.
 * @param {Number} open The open area, 0 for none.
 * @return {void}
 */
export const paintAllEmpty = (areas, allempty, open) => {
    const box = areas.querySelector(SELECTORS.allempty);
    if (box) {
        box.hidden = !(allempty && !open);
    }
};

/**
 * Write the "nothing anywhere" box's second way out from the latest answer: the same term in every area.
 *
 * The address is the server's (category_listing::results(), searchallurl): the root carrying the
 * term, where this reader may open the root, and empty where there is nothing to offer - a page
 * narrowed by filters alone, or the root itself.
 *
 * @param {HTMLElement} areas The areas section.
 * @param {String} url The address, or the empty string.
 * @return {void}
 */
export const paintSearchAll = (areas, url) => {
    const link = areas.querySelector(SELECTORS.searchall);
    if (!link) {
        return;
    }
    if (url) {
        link.href = url;
    }
    link.hidden = !url;
};
