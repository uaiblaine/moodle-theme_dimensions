var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The showcase listing's store: the state of the page on screen, every action a reader takes, and the one
 * pipeline from an action to the address and the request.
 *
 * The page is complete without it; the store moves the page the server drew. A shell is injected
 * ({@see Shell}): its service, its failure, its mark and its rules. Nothing here names a shell, a service or
 * the session: the visitor's page imports this module. One listing runs on a page, so the state lives at
 * module scope.
 *
 * @module     theme_dimensions/listing/store
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Pending from "@moodle/lms/core/pending";
import { period } from "../daterange/calendar";
import { markTitles } from "../areas/mark";
import * as Listing from "./listing";
import { LISTING, SELECTORS } from "./events";
const SEARCH_DEBOUNCE = 300;
const MIN_SEARCH_LENGTH = 2;
let root;
let areas = null;
let state;
let shell;
let seq = 0;
let lastpage = 0;
let searchtimer;
let searchpending = null;
const current = /* @__PURE__ */ __name(() => state, "current");
const applyResponse = /* @__PURE__ */ __name(async (response, append, ticket) => {
  const items = await Listing.renderCards(response.cards || []);
  if (ticket !== seq) {
    return;
  }
  lastpage = Number(response.page);
  if (!append) {
    state.page = lastpage;
  }
  state.total = Number(response.total);
  state.hasmore = Boolean(response.hasmore);
  const grid = root.querySelector(SELECTORS.grid);
  if (grid) {
    Listing.paintGrid(root, state, grid, items, append, shell.discard);
  }
  const notice = root.querySelector(SELECTORS.notice);
  if (notice) {
    notice.hidden = !response.searchtooshort;
  }
  const clear = root.querySelector(SELECTORS.clear);
  if (clear && response.clearurl) {
    clear.href = Listing.withPanel(response.clearurl);
  }
  Listing.paintFilterBadge(root, response);
  Listing.paintCount(root, response);
  Listing.paintChips(root, response);
  Listing.paintGroups(root, response);
  Listing.paintFiltersControl(root);
  Listing.paintFavourites(root, response);
  shell.painted(items);
  Listing.paintPager(root, response, append, items.length ? items[0] : null);
  if (!append) {
    Listing.paintDirect(root, response);
    paintAreas(response);
  }
  Listing.tellAnswer(root, response);
}, "applyResponse");
const paintAreas = /* @__PURE__ */ __name((response) => {
  if (!areas) {
    return;
  }
  const word = response.areas || {};
  shell.answered(response);
  document.dispatchEvent(new CustomEvent(LISTING.criteria, { detail: {
    accordion: response.accordion || [],
    areas: word,
    filtered: Boolean(word.filtered),
    total: Number(response.total)
  } }));
}, "paintAreas");
const requestRows = /* @__PURE__ */ __name(async (page, append) => {
  const ticket = ++seq;
  const pending = new Pending("theme_dimensions/listing:rows");
  const more = root.querySelector(SELECTORS.loadmore);
  if (append && more) {
    more.disabled = true;
    more.textContent = more.dataset.loading || more.textContent;
  }
  if (!append) {
    root.setAttribute("aria-busy", "true");
    if (areas) {
      document.dispatchEvent(new CustomEvent(LISTING.pending, { detail: { filtered: Listing.narrows(state) } }));
    }
  }
  try {
    const response = await shell.rows(page);
    if (ticket !== seq) {
      return;
    }
    await applyResponse(response, append, ticket);
  } catch (error) {
    if (ticket === seq) {
      shell.failed(page, append, error);
    }
  } finally {
    if (ticket === seq) {
      root.removeAttribute("aria-busy");
    }
    Listing.restoreMore(more);
    pending.resolve();
  }
}, "requestRows");
const apply = /* @__PURE__ */ __name((kind) => {
  state.page = 0;
  shell.changed(kind);
  Listing.writeUrl(state, true);
  shell.written();
  return requestRows(0, false);
}, "apply");
const onSearch = /* @__PURE__ */ __name(async (raw) => {
  const term = raw.trim();
  const notice = root.querySelector(SELECTORS.notice);
  if (term.length > 0 && term.length < MIN_SEARCH_LENGTH) {
    if (notice) {
      notice.hidden = false;
    }
    return;
  }
  if (notice) {
    notice.hidden = true;
  }
  state.query = term;
  await apply("term");
}, "onSearch");
const cancelSearch = /* @__PURE__ */ __name(() => {
  window.clearTimeout(searchtimer);
  if (searchpending) {
    searchpending.resolve();
    searchpending = null;
  }
}, "cancelSearch");
const onSearchInput = /* @__PURE__ */ __name((search) => {
  cancelSearch();
  Listing.paintEraser(root);
  const pending = new Pending("theme_dimensions/listing:search");
  searchpending = pending;
  searchtimer = window.setTimeout(async () => {
    searchpending = null;
    try {
      await onSearch(search.value);
    } finally {
      pending.resolve();
    }
  }, SEARCH_DEBOUNCE);
}, "onSearchInput");
const onSort = /* @__PURE__ */ __name((sort) => {
  if (sort === state.sort) {
    return;
  }
  const was = Listing.isGrouped(state.sort);
  state.sort = sort;
  state.page = 0;
  if (was !== Listing.isGrouped(sort)) {
    shell.leave(Listing.buildUrl(state));
    return;
  }
  apply("filter");
}, "onSort");
const onTree = /* @__PURE__ */ __name((link) => {
  const id = Number(link.dataset.categoryid);
  if (!Number.isInteger(id) || id <= 0) {
    return;
  }
  const cat = id === state.categoryid ? 0 : id;
  if (cat === state.cat) {
    return;
  }
  state.cat = cat;
  Listing.markTree(root, state);
  apply("tree");
}, "onTree");
const setRange = /* @__PURE__ */ __name((datefrom, dateto) => {
  const range = period(datefrom, dateto);
  state.datefrom = range.from;
  state.dateto = range.to;
  Listing.paintDates(root, state);
  apply("period");
}, "setRange");
const onChoose = /* @__PURE__ */ __name((input) => {
  const group = input.closest(SELECTORS.group);
  const name = group && group.dataset.name ? group.dataset.name : input.name;
  if (name === Listing.PARAM.sort) {
    onSort(input.value);
    return;
  }
  if (name === Listing.PARAM.chip) {
    state.chip = input.value;
  } else if (name && name.startsWith(Listing.PARAM.fieldprefix)) {
    const fieldid = name.slice(Listing.PARAM.fieldprefix.length);
    if (Number(input.value) > 0) {
      state.filters[fieldid] = Number(input.value);
    } else {
      delete state.filters[fieldid];
    }
  } else {
    return;
  }
  apply("filter");
}, "onChoose");
const onFav = /* @__PURE__ */ __name(() => {
  state.fav = !state.fav;
  Listing.paintFavToggle(root, state.fav);
  apply("filter");
}, "onFav");
const setView = /* @__PURE__ */ __name((view) => {
  state.view = view;
  state.viewchosen = true;
  Listing.paintView(root, view);
  shell.changed("view");
  Listing.writeUrl(state, true);
  shell.written();
}, "setView");
const focusSearch = /* @__PURE__ */ __name(() => {
  const search = Listing.focusTarget(root);
  if (search) {
    search.focus({ preventScroll: true });
  }
}, "focusSearch");
const onClear = /* @__PURE__ */ __name(() => {
  cancelSearch();
  state.query = "";
  state.chip = "";
  state.fav = false;
  state.filters = {};
  state.datefrom = "";
  state.dateto = "";
  state.cat = 0;
  const notice = root.querySelector(SELECTORS.notice);
  if (notice) {
    notice.hidden = true;
  }
  Listing.paintControls(root, state);
  apply("filter");
  focusSearch();
}, "onClear");
const onChipRemove = /* @__PURE__ */ __name((chip) => {
  if (!Listing.dropCriterion(state, chip.dataset.param || "")) {
    return false;
  }
  shell.normalise(state);
  Listing.paintControls(root, state);
  apply("filter");
  focusSearch();
  return true;
}, "onChipRemove");
const dropTerm = /* @__PURE__ */ __name(() => {
  cancelSearch();
  state.query = "";
  const notice = root.querySelector(SELECTORS.notice);
  if (notice) {
    notice.hidden = true;
  }
  Listing.paintEraser(root);
}, "dropTerm");
const onErase = /* @__PURE__ */ __name(async () => {
  cancelSearch();
  const search = root.querySelector(SELECTORS.search);
  if (search && !search.closest(SELECTORS.drawnline)) {
    search.value = "";
    search.focus({ preventScroll: true });
  }
  Listing.paintEraser(root);
  await onSearch("");
}, "onErase");
const onPopState = /* @__PURE__ */ __name(() => {
  Listing.readUrl(state);
  shell.normalise(state);
  Listing.paintControls(root, state);
  Listing.syncFold();
  shell.written();
  shell.changed("history");
  requestRows(0, false);
}, "onPopState");
const listen = /* @__PURE__ */ __name(() => {
  const form = root.querySelector(SELECTORS.toolbar);
  if (form) {
    form.addEventListener("submit", (event) => event.preventDefault());
    form.addEventListener("input", (event) => {
      const search = Listing.closestOf(event, SELECTORS.search);
      if (search) {
        onSearchInput(search);
      }
    });
  }
  root.addEventListener("change", (event) => {
    const input = event.target;
    if (input instanceof HTMLInputElement && input.type === "radio") {
      onChoose(input);
    }
  });
  root.addEventListener("click", (event) => {
    const clear = Listing.closestOf(event, SELECTORS.clearaction);
    if (clear) {
      event.preventDefault();
      onClear();
      return;
    }
    const chip = Listing.closestOf(event, SELECTORS.chipremove);
    if (chip && onChipRemove(chip)) {
      event.preventDefault();
      return;
    }
    if (Listing.closestOf(event, SELECTORS.eraser)) {
      event.preventDefault();
      onErase().catch(() => null);
      return;
    }
    const quick = Listing.closestOf(event, SELECTORS.quick);
    if (quick) {
      event.preventDefault();
      setRange(quick.dataset.from || "", quick.dataset.to || "");
      return;
    }
    if (Listing.closestOf(event, SELECTORS.periodclear)) {
      event.preventDefault();
      if (state.datefrom !== "" || state.dateto !== "") {
        setRange("", "");
      }
      return;
    }
    if (Listing.closestOf(event, SELECTORS.fav)) {
      event.preventDefault();
      onFav();
      return;
    }
    const view = Listing.closestOf(event, SELECTORS.viewbutton);
    if (view) {
      event.preventDefault();
      setView(view.value === "list" ? "list" : "cards");
      return;
    }
    const branch = Listing.closestOf(event, SELECTORS.treelink);
    if (branch) {
      event.preventDefault();
      onTree(branch);
      return;
    }
    const more = Listing.closestOf(event, SELECTORS.loadmore);
    if (more) {
      event.preventDefault();
      requestRows(lastpage + 1, true);
    }
  });
}, "listen");
const init = /* @__PURE__ */ __name((given, categoryid) => {
  const found = document.querySelector(SELECTORS.root);
  if (!found || !Number.isInteger(categoryid) || categoryid < 0) {
    return false;
  }
  root = found;
  shell = given;
  let payload = {};
  try {
    payload = JSON.parse(root.dataset.vitrine || "{}");
  } catch (error) {
    payload = {};
  }
  const params = new URL(window.location.href).searchParams;
  const range = Listing.periodOf(params);
  const defaultsort = typeof payload.defaultsort === "string" ? payload.defaultsort : "";
  const remembered = payload.remembered === true;
  const sort = remembered && typeof payload.sort === "string" ? payload.sort : Listing.sortOf(params, defaultsort);
  state = {
    categoryid,
    page: Number(payload.page) || 0,
    total: Number(payload.total) || 0,
    hasmore: Boolean(payload.hasmore),
    query: typeof payload.query === "string" ? payload.query : "",
    chip: typeof payload.chip === "string" ? payload.chip : "",
    // The server read an older address's favourites chip as the toggle already.
    fav: payload.fav === true,
    filters: payload.filters && typeof payload.filters === "object" ? Object.assign({}, payload.filters) : {},
    // The organisation, the period and the tree's category are the address's: the toolbar is a GET form.
    defaultsort,
    sort,
    // Whether every address the store writes names its organisation (the shell's changed() sets it).
    remembered,
    datefrom: range.from,
    dateto: range.to,
    cat: Listing.catOf(params, sort),
    view: payload.view === "list" ? "list" : "cards",
    viewchosen: params.has(Listing.PARAM.view),
    pagesize: Listing.pageSizeOf(params)
  };
  lastpage = state.page;
  shell.normalise(state);
  areas = root.querySelector(SELECTORS.areas);
  const live = !(Listing.isRoot(state) && !root.querySelector(SELECTORS.toolbar));
  if (live) {
    window.removeEventListener("popstate", onPopState);
    window.addEventListener("popstate", onPopState);
  }
  shell.written();
  Listing.mountAreas(areas, shell.areas.props, shell.areas.client);
  if (!live) {
    return false;
  }
  document.body.classList.add(shell.mark);
  markTitles(root.querySelector(SELECTORS.grid), state.query);
  Listing.mountLine(root, dropTerm);
  Listing.mountCalendar(root, state, setRange).catch(shell.unmounted);
  const bar = root.querySelector(SELECTORS.pagerbar);
  if (bar) {
    bar.hidden = true;
  }
  Listing.armTreebox(root);
  listen();
  return true;
}, "init");
export {
  current,
  init
};
//# sourceMappingURL=store.dev.js.map
