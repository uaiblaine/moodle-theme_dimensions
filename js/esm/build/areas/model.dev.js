var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The panel between the poster rows, as numbers and words: what Areas.tsx decides without the page.
 *
 * Every function here is pure: an address, a context, a place in a grid.
 *
 * @module     theme_dimensions/areas/model
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const SELECTORS = {
  areas: '[data-region="dim-areas"]',
  listing: '[data-region="dim-vitrine"]',
  grid: '[data-region="dim-areas-grid"]',
  more: '[data-region="dim-areas-more"]',
  moresummary: '[data-region="dim-areas-moresummary"]',
  hint: '[data-region="dim-areas-hint"]',
  allempty: '[data-region="dim-areas-empty"]',
  searchall: '[data-region="dim-areas-searchall"]',
  nomatch: '[data-region="dim-areas-nomatch"]',
  nomatchsummary: '[data-region="dim-nomatch-summary"]',
  nomatchlist: '[data-region="dim-nomatch-list"]',
  status: '[data-region="dim-panel-status"]',
  area: '[data-region="dim-area"]',
  control: '[data-region="dim-poster-control"]',
  trigger: '[data-region="dim-poster-trigger"]',
  emptynote: '[data-region="dim-poster-emptynote"]',
  pill: '[data-region="dim-poster-pill"]',
  covercontrols: '[data-region="dim-cover-controls"]',
  panel: '[data-region="dim-panel"]',
  section: '[data-region="dim-panel-section"]',
  title: '[data-region="dim-panel-title"]',
  subtitle: '[data-region="dim-panel-subtitle"]',
  sort: '[data-region="dim-panel-sort"]',
  sortoption: '[data-action="dim-panel-sort"]',
  close: '[data-action="dim-panel-close"]',
  also: '[data-region="dim-panel-also"]',
  body: '[data-region="dim-panel-body"]',
  rail: '[data-region="dim-panel-rail"]',
  prev: '[data-action="dim-panel-prev"]',
  next: '[data-action="dim-panel-next"]',
  foot: '[data-region="dim-panel-foot"]',
  counter: '[data-region="dim-panel-counter"]',
  retry: '[data-action="dim-panel-retry"]',
  openarea: '[data-action="dim-panel-openarea"]',
  card: '[data-region="dim-card"]',
  search: 'input[type="search"]',
  toolbar: '[data-region="dim-vitrine-toolbar"]',
  fold: '[data-region="dim-hero-fold"]',
  clear: '[data-region="dim-vitrine-clear"]',
  chip: '[data-action="dim-chip-remove"]',
  openfield: '[data-region="dim-open-field"]',
  psortfield: '[data-region="dim-psort-field"]',
  direct: '[data-region="dim-direct"]'
};
const CLASSES = {
  posteropen: "dim-poster--open",
  posterempty: "dim-poster--empty",
  areasopen: "dim-areas--open",
  shut: "dim-panel--shut",
  still: "dim-panel--static",
  fading: "dim-panel--fading",
  arrowoff: "dim-panel__arrow--off",
  dragging: "dim-panel__rail--dragging",
  pillzero: "dim-poster__pill--zero",
  list: "dim-vitrine--list"
};
const TEMPLATE = "theme_dimensions/category_panel";
const PANEL_ID = "dim-panel";
const NOMATCH_ID = "dim-nomatch";
const BODY_CLASS = "dim-panel-js";
const WIDE = "(max-width: 1199.98px)";
const PHONE = "(max-width: 575.98px)";
const MOTION = "(prefers-reduced-motion: reduce)";
const SKELETON_DELAY = 150;
const MOTION_BASE = 250;
const MOTION_FAST = 150;
const SETTLE = 90;
const SCROLL_END = 1e3;
const SCROLL_GAP = 48;
const DRAG_THRESHOLD = 5;
const RAIL_GAP = 16;
const PARAM = {
  open: "open",
  psort: "psort",
  query: "q"
};
const PSORT_NAME = "name";
const placement = /* @__PURE__ */ __name((index) => ({
  "--dim-row-3": String(Math.floor(index / 3) + 2),
  "--dim-row-2": String(Math.floor(index / 2) + 2),
  "--dim-row-1": String(index + 2),
  "--dim-col-3": String(index % 3),
  "--dim-col-2": String(index % 2)
}), "placement");
const columns = /* @__PURE__ */ __name((list, phone, wide) => {
  if (list || phone) {
    return 1;
  }
  return wide ? 2 : 3;
}, "columns");
const lastOfRow = /* @__PURE__ */ __name((index, count, tracks) => Math.max(0, Math.min(count - 1, Math.floor(index / tracks) * tracks + tracks - 1)), "lastOfRow");
const sameRow = /* @__PURE__ */ __name((first, second, tracks) => first >= 0 && second >= 0 && Math.floor(first / tracks) === Math.floor(second / tracks), "sameRow");
const ctaUrl = /* @__PURE__ */ __name((href, psort, base) => {
  if (href === "" || psort !== PSORT_NAME) {
    return href;
  }
  const url = new URL(href, base);
  url.searchParams.set("sort", PSORT_NAME);
  return url.toString();
}, "ctaUrl");
const shellContext = /* @__PURE__ */ __name((shell, psort, base, flags) => {
  const cta = ctaUrl(shell.ctaurl, psort, base);
  return {
    id: shell.id,
    name: shell.name,
    titleurl: shell.titleurl,
    haslink: shell.titleurl !== "",
    isd20: false,
    subtitle: "",
    psortname: psort === PSORT_NAME,
    sorturlrecent: "",
    sorturlname: "",
    closeurl: "",
    also: { has: false, pills: [], hasmore: false, morecount: "", moreurl: "" },
    hascards: false,
    few: false,
    counter: "",
    tile: { has: false, count: "", label: "", url: "" },
    isrows: false,
    isempty: false,
    iszero: false,
    zero: "",
    suggestions: [],
    hassuggestions: false,
    cta: { has: cta !== "", url: cta, label: shell.ctalabel },
    seeaslist: { has: false, url: "" },
    jsmode: true,
    isloading: false,
    isskeleton: false,
    iserror: false,
    cards: [],
    ...flags
  };
}, "shellContext");
const answerContext = /* @__PURE__ */ __name((response, id, offered) => {
  if (!response || !response.panel || Number(response.panel.id) !== id) {
    throw new Error("The answer carries no panel of this area.");
  }
  return {
    ...response.panel,
    cards: response.cards || [],
    jsmode: true,
    suggestions: offered,
    hassuggestions: offered.length > 0
  };
}, "answerContext");
const suggestions = /* @__PURE__ */ __name((posters, open, filtered) => {
  if (!filtered) {
    return [];
  }
  return posters.filter((poster) => poster.id !== open && poster.count > 0).map((poster) => ({
    id: poster.id,
    name: poster.name,
    count: poster.resultcount,
    countlabel: poster.resultlabel,
    url: ""
  }));
}, "suggestions");
const addressWith = /* @__PURE__ */ __name((href, open, psort) => {
  const url = new URL(href);
  if (open > 0) {
    url.searchParams.set(PARAM.open, String(open));
  } else {
    url.searchParams.delete(PARAM.open);
  }
  if (psort === PSORT_NAME) {
    url.searchParams.set(PARAM.psort, PSORT_NAME);
  } else {
    url.searchParams.delete(PARAM.psort);
  }
  if (url.hash === "#" + PANEL_ID || url.hash === "#" + NOMATCH_ID || url.hash.startsWith("#dim-area-")) {
    url.hash = "";
  }
  return url.toString();
}, "addressWith");
const holderHref = /* @__PURE__ */ __name((href, base, open, psort) => {
  const url = new URL(href, base);
  if (open > 0) {
    url.searchParams.set(PARAM.open, String(open));
  } else {
    url.searchParams.delete(PARAM.open);
  }
  if (psort === PSORT_NAME) {
    url.searchParams.set(PARAM.psort, PSORT_NAME);
  } else {
    url.searchParams.delete(PARAM.psort);
  }
  return url.toString();
}, "holderHref");
const readPanel = /* @__PURE__ */ __name((href) => {
  const params = new URL(href).searchParams;
  return {
    open: Number(params.get(PARAM.open)) || 0,
    psort: params.get(PARAM.psort) === PSORT_NAME ? PSORT_NAME : ""
  };
}, "readPanel");
const termOf = /* @__PURE__ */ __name((href) => new URL(href).searchParams.get(PARAM.query) || "", "termOf");
const announcement = /* @__PURE__ */ __name((context, body, elsewhere) => {
  if (context.iszero) {
    return (String(context.zero || "") + " " + elsewhere).trim();
  }
  if (!context.isrows) {
    return body.trim().replace(/\s+/g, " ");
  }
  return String(context.subtitle || "");
}, "announcement");
const summaryLabel = /* @__PURE__ */ __name((summary, open) => (summary.entries.some((entry) => entry.id === open) ? summary.less : summary.all) || "", "summaryLabel");
const summaryOf = /* @__PURE__ */ __name((accordion, word, nameOf) => ({
  all: word.nomatchlabelall || "",
  less: word.nomatchlabelless || "",
  entries: (word.filtered ? accordion.filter((entry) => Number(entry.count) === 0) : []).map((entry) => {
    const id = Number(entry.id);
    const name = nameOf(id);
    return {
      id,
      name: name === null ? String(entry.name || "") : name,
      url: entry.linkable && entry.url ? String(entry.url) : ""
    };
  })
}), "summaryOf");
export {
  BODY_CLASS,
  CLASSES,
  DRAG_THRESHOLD,
  MOTION,
  MOTION_BASE,
  MOTION_FAST,
  NOMATCH_ID,
  PANEL_ID,
  PARAM,
  PHONE,
  PSORT_NAME,
  RAIL_GAP,
  SCROLL_END,
  SCROLL_GAP,
  SELECTORS,
  SETTLE,
  SKELETON_DELAY,
  TEMPLATE,
  WIDE,
  addressWith,
  announcement,
  answerContext,
  columns,
  ctaUrl,
  holderHref,
  lastOfRow,
  placement,
  readPanel,
  sameRow,
  shellContext,
  suggestions,
  summaryLabel,
  summaryOf,
  termOf
};
//# sourceMappingURL=model.dev.js.map
