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
 * The names the showcase's listing and its React components share: their events and the hooks they read.
 *
 * Spelled here and nowhere else in js/esm/src. They stay DOM events: each
 * component is a root of its own, mounted when react_autoinit sees its node, and a listener put on before the
 * node makes that order safe. Imported by the visitor's page: nothing here may name a session module.
 *
 * @module     theme_dimensions/listing/events
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The events of the listing on the document: the view changed (view, detail {view}); a request for new
 * criteria went out (pending, detail {filtered}), its answer arrived (criteria, detail {accordion, areas,
 * filtered, total}) or failed (failed); and, sent back by the posters' component, an area opened by itself
 * (autoopened, detail {id}).
 */
export const LISTING = {
    view: 'dim-vitrine:view',
    pending: 'dim-vitrine:pending',
    criteria: 'dim-vitrine:criteria',
    failed: 'dim-vitrine:failed',
    autoopened: 'dim-panel:autoopened',
};

/**
 * The events on the filter line. The component says it is drawn (mounted), or that it cannot draw the
 * markup it was handed (failed: the line stays the server's), and that a suggestion is about to press its
 * control (dropterm); the listing tells it its own state (state, detail {query, chip, fav, view} or any part
 * of it) and an answer's numbers (answer).
 */
export const LINE = {
    mounted: 'dim-line:mounted',
    failed: 'dim-line:failed',
    state: 'dim-line:state',
    answer: 'dim-line:answer',
    dropterm: 'dim-line:dropterm',
};

/** The attribute the line component writes on the line once it draws it, and which every painter reads. */
export const LINE_DRAWN = 'data-line-drawn';

/**
 * The events on the toolbar's calendar region. The component says it is drawn (mounted) and that the reader
 * changed the period (pick, detail {from, to}, bubbling); the listing tells it the period the address says
 * (set, the same detail), silently.
 */
export const CALENDAR = {
    pick: 'dim-daterange:pick',
    set: 'dim-daterange:set',
    mounted: 'dim-daterange:mounted',
};

/**
 * The events on the areas section. The posters' component asks for an area's rows (ask, detail {poster,
 * psort, answer}: the page's client puts its answer's promise in it while the event is dispatched), asks for
 * the question that request would ask (question, detail {poster, psort, question}), offers a failure before
 * its inline error (unanswered, detail {poster, psort, taken}), says the rows are on screen (painted, detail
 * {panel}), says it is drawn (mounted) and that the panel's own order control changed the grouped order
 * (ordered, detail {psort}); the listing tells it the grouped order "Sort by" chose (order, detail {psort}).
 */
export const AREAS = {
    mounted: 'dim-areas:mounted',
    ask: 'dim-areas:ask',
    question: 'dim-areas:question',
    unanswered: 'dim-areas:unanswered',
    painted: 'dim-areas:painted',
    ordered: 'dim-areas:ordered',
    order: 'dim-areas:order',
};

/** Every hook the listing reads, all of them written by the showcase's templates. */
export const SELECTORS = {
    root: '[data-region="dim-vitrine"]',
    areas: '[data-region="dim-areas"]',
    notice: '[data-region="dim-toolbar-notice"]',
    clearaction: '[data-action="dim-clear"]',
    chipremove: '[data-action="dim-chip-remove"]',
    drawnline: '[data-region="dim-line"][data-line-drawn]',
    quick: '[data-action="dim-quick"]',
    fav: '[data-action="dim-fav"]',
    grid: '[data-region="dim-vitrine-grid"]',
    empty: '[data-region="dim-vitrine-empty"]',
    toolbar: '[data-region="dim-vitrine-toolbar"]',
    group: '[data-region="dim-vitrine-group"]',
    pilltrack: '[data-region="dim-pill-track"]',
    pillcount: '[data-region="dim-pill-count"]',
    filterbadge: '[data-region="dim-filtercount"]',
    filterbadgenumber: '[data-region="dim-filtercount-number"]',
    filterbadgelabel: '[data-region="dim-filtercount-label"]',
    count: '[data-region="dim-vitrine-count"]',
    total: '[data-region="dim-line-total"]',
    filters: '[data-region="dim-filters"]',
    filterspanel: '[data-region="dim-filters-panel"]',
    filtersclear: '[data-region="dim-filters-clear"]',
    filtersshow: '[data-action="dim-filters-show"]',
    periodshow: '[data-action="dim-period-show"]',
    periodbutton: '[data-region="dim-period-button"]',
    perioddot: '[data-region="dim-period-dot"]',
    periodclear: '[data-action="dim-period-clear"]',
    clear: '[data-region="dim-vitrine-clear"]',
    countnumber: '[data-region="dim-vitrine-countnumber"]',
    counttext: '[data-region="dim-vitrine-counttext"]',
    seeall: '[data-region="dim-vitrine-seeall"]',
    countnote: '[data-region="dim-vitrine-countnote"]',
    pagerbar: '[data-region="dim-vitrine-pagerbar"]',
    loadmore: '[data-action="dim-loadmore"]',
    nextpage: '[data-region="dim-vitrine-nextpage"]',
    card: '[data-region="dim-card"]',
    cardtitle: '[data-region="dim-card-titlelink"]',
    search: 'input[type="search"]',
    viewbutton: '[data-action="dim-view"]',
    viewicon: '[data-region="dim-view-icon"]',
    organiseitem: '[data-action="dim-organise"]',
    organisefield: '[data-region="dim-group-field"], [data-region="dim-order-field"]',
    date: '[data-region="dim-date"]',
    daterange: '[data-region="dim-daterange"]',
    dateinputs: '[data-region="dim-dateinputs"]',
    rangepill: '[data-region="dim-daterange-pill"]',
    chiprow: '[data-region="dim-line-chips"]',
    chiplist: '[data-region="dim-line-chiplist"]',
    eraser: '[data-action="dim-search-clear"]',
    treelink: '[data-region="dim-vitrine-tree"] a[data-region="dim-tree-link"]',
    treebox: '[data-region="dim-vitrine-treebox"]',
    area: '[data-region="dim-area"]',
    direct: '[data-region="dim-direct"]',
    directskip: '[data-region="dim-direct-skip"]',
    fold: '[data-region="dim-hero-fold"]',
    star: '[data-region="dim-line-star"]',
    favfield: '[data-region="dim-fav-field"]',
    favcount: '[data-region="dim-fav-count"]',
    line: '[data-region="dim-line"]',
    linefield: '[data-region="dim-line-search"] input[type="search"]',
    listbox: '[data-region="dim-line-suggest"]',
    opener: '[data-action="dim-search-open"]',
    panel: '[data-region="dim-panel"]',
    adopted: '[data-region="dim-filters-panel"], [data-region="dim-period-layer"], [data-region="dim-organise-menu"], '
        + '[data-region="dim-line-manage"]',
};
