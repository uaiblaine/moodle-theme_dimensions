var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The visitor's shell of the showcase listing: a public category page narrows, orders and pages in place.
 *
 * It asks theme_dimensions_get_public_category_rows, and nothing else, with login not required, which posts
 * to lib/ajax/service-nologin.php: no session is read or started, so the site's forced login never meets the
 * request. Never with a cachekey (service.php answers such a GET with ninety days of public caching, and the
 * service refuses it), one call per request, in the page's own language. Its mark is dim-public-js.
 *
 * The posters stay their links (the component's links option): every link is written to its area's panel
 * under the criteria on screen. A press asks the service for the area's rows - a public area by its own id,
 * a merely listed one as a slice of this page's listing - once per opening, after a quiet; no area opens by
 * itself. A failure is told in the page's own words, never the service's, and the address of the request is
 * loaded: the page a reader without a client gets.
 *
 * What it imports is the point: nothing that reads the session or asks a logged-in service
 * (categorypublic_source_test walks the whole graph from its entry, amd/src/listingvisitor.js).
 *
 * @module     theme_dimensions/listing/visitor
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fetchOne } from "@moodle/lms/core/ajax";
import Config from "@moodle/lms/core/config";
import { getString } from "@moodle/lms/core/stringUtils";
import { replaceKeeping } from "./history";
import * as Listing from "./listing";
import { current, init } from "./store";
const SELECTORS = {
  areas: '[data-region="dim-areas"]',
  area: '[data-region="dim-area"]',
  control: '[data-region="dim-poster-control"]',
  notice: '[data-region="dim-toolbar-notice"]'
};
const METHOD = "theme_dimensions_get_public_category_rows";
const COMPONENT = "theme_dimensions";
const FALLBACK = "category_publicfallback";
const BODY_CLASS = "dim-public-js";
const PANEL_ANCHOR = "dim-panel";
const PANEL_SIZE = 8;
const PANEL_QUIET = 120;
const AREAS_PROPS = {
  links: true,
  quiet: PANEL_QUIET,
  autoopen: false
};
const PSORT_NAME = "name";
const SORT = {
  recent: "recent",
  name: "name"
};
const PARAM = Listing.PARAM;
const publicOnly = /* @__PURE__ */ __name((state) => {
  state.chip = "";
  state.fav = false;
  Listing.rootOnly(state);
}, "publicOnly");
const ask = /* @__PURE__ */ __name((args) => {
  return fetchOne({
    methodname: METHOD,
    args
  }, { loginrequired: false });
}, "ask");
const rowsArgs = /* @__PURE__ */ __name((page) => {
  const state = current();
  if (Listing.isRoot(state)) {
    return { categoryid: 0, query: state.query, lang: Config.language };
  }
  return {
    categoryid: state.categoryid,
    page,
    query: state.query,
    perpage: state.pagesize,
    filters: Listing.filterArgs(state),
    // "Group by" and "Sort by" by name, the order in sort (criteria::organisation()).
    group: Listing.groupOf(state),
    sort: Listing.orderOf(state),
    datefrom: state.datefrom,
    dateto: state.dateto,
    cat: state.cat,
    lang: Config.language
  };
}, "rowsArgs");
const panelAddress = /* @__PURE__ */ __name((id, psort) => {
  const url = Listing.buildUrl(current());
  url.searchParams.set(PARAM.open, String(id));
  if (psort === PSORT_NAME) {
    url.searchParams.set(PARAM.psort, PSORT_NAME);
  } else {
    url.searchParams.delete(PARAM.psort);
  }
  url.hash = PANEL_ANCHOR;
  return url;
}, "panelAddress");
const linkPosters = /* @__PURE__ */ __name(() => {
  const areas = document.querySelector(SELECTORS.areas);
  if (!areas) {
    return;
  }
  areas.querySelectorAll(SELECTORS.area).forEach((poster) => {
    const control = poster.querySelector(SELECTORS.control);
    if (!control || control.tagName !== "A") {
      return;
    }
    control.href = panelAddress(Number(poster.dataset.categoryid), "").toString();
  });
}, "linkPosters");
const load = /* @__PURE__ */ __name((url) => {
  const target = url.toString();
  const bare = new URL(target);
  bare.hash = "";
  const here = new URL(window.location.href);
  here.hash = "";
  if (bare.toString() === here.toString()) {
    replaceKeeping(target);
    window.location.reload();
    return;
  }
  window.location.assign(target);
}, "load");
const navigate = /* @__PURE__ */ __name((url) => {
  url.hash = "";
  load(url);
}, "navigate");
const fallBack = /* @__PURE__ */ __name(async (url) => {
  try {
    const text = await getString(FALLBACK, COMPONENT);
    const notice = document.querySelector(SELECTORS.notice);
    if (notice) {
      notice.textContent = text;
      notice.hidden = false;
    }
  } catch (failure) {
  }
  load(url);
}, "fallBack");
const fallbackUrl = /* @__PURE__ */ __name((page, append) => {
  const url = Listing.buildUrl(current());
  if (append) {
    url.searchParams.set(PARAM.page, String(page));
  }
  url.hash = "";
  return url;
}, "fallbackUrl");
const panelArgs = /* @__PURE__ */ __name((poster, psort) => {
  const id = Number(poster.dataset.categoryid);
  const sort = psort === PSORT_NAME ? SORT.name : SORT.recent;
  const state = current();
  const asked = { categoryid: state.categoryid, view: state.view, viewchosen: state.viewchosen };
  Listing.readUrl(asked);
  if (Listing.isRoot(asked)) {
    return {
      categoryid: id,
      page: 0,
      perpage: PANEL_SIZE,
      sort,
      query: asked.query,
      withcategories: true,
      lang: Config.language
    };
  }
  const narrowing = {
    query: asked.query,
    filters: Listing.filterArgs(asked),
    datefrom: asked.datefrom,
    dateto: asked.dateto,
    lang: Config.language
  };
  if (poster.dataset.titleurl === "") {
    return Object.assign({ categoryid: state.categoryid, slice: id, sort }, narrowing);
  }
  return Object.assign({ categoryid: id, page: 0, perpage: PANEL_SIZE, sort, withcategories: true }, narrowing);
}, "panelArgs");
const askPanel = /* @__PURE__ */ __name((poster, psort) => {
  return ask(panelArgs(poster, psort));
}, "askPanel");
const panelQuestion = /* @__PURE__ */ __name((poster, psort) => {
  return JSON.stringify(panelArgs(poster, psort));
}, "panelQuestion");
const panelFailed = /* @__PURE__ */ __name((poster, psort) => {
  fallBack(panelAddress(Number(poster.dataset.categoryid), psort));
  return true;
}, "panelFailed");
const rows = /* @__PURE__ */ __name((page) => ask(rowsArgs(page)), "rows");
const failed = /* @__PURE__ */ __name((page, append) => {
  fallBack(fallbackUrl(page, append));
}, "failed");
const nothing = /* @__PURE__ */ __name(() => void 0, "nothing");
const SHELL = {
  mark: BODY_CLASS,
  normalise: publicOnly,
  rows,
  failed,
  changed: nothing,
  discard: null,
  painted: nothing,
  written: linkPosters,
  leave: navigate,
  answered: linkPosters,
  // The native fields stay the server's and work: the visitor is told nothing.
  unmounted: nothing,
  areas: {
    props: AREAS_PROPS,
    client: {
      ask: askPanel,
      question: panelQuestion,
      unanswered: panelFailed
    }
  }
};
const start = /* @__PURE__ */ __name((categoryid) => {
  init(SHELL, Number(categoryid));
}, "start");
export {
  start
};
//# sourceMappingURL=visitor.dev.js.map
