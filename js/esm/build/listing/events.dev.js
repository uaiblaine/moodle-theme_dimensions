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
const LISTING = {
  view: "dim-vitrine:view",
  pending: "dim-vitrine:pending",
  criteria: "dim-vitrine:criteria",
  failed: "dim-vitrine:failed",
  autoopened: "dim-panel:autoopened"
};
const LINE = {
  mounted: "dim-line:mounted",
  failed: "dim-line:failed",
  state: "dim-line:state",
  answer: "dim-line:answer",
  dropterm: "dim-line:dropterm"
};
const LINE_DRAWN = "data-line-drawn";
const CALENDAR = {
  pick: "dim-daterange:pick",
  set: "dim-daterange:set",
  mounted: "dim-daterange:mounted"
};
const AREAS = {
  mounted: "dim-areas:mounted",
  ask: "dim-areas:ask",
  question: "dim-areas:question",
  unanswered: "dim-areas:unanswered",
  painted: "dim-areas:painted"
};
const SELECTORS = {
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
  sort: '[data-region="dim-sort"]',
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
  adopted: '[data-region="dim-filters-panel"], [data-region="dim-period-layer"], [data-region="dim-organise-menu"], [data-region="dim-line-manage"]'
};
export {
  AREAS,
  CALENDAR,
  LINE,
  LINE_DRAWN,
  LISTING,
  SELECTORS
};
//# sourceMappingURL=events.dev.js.map
