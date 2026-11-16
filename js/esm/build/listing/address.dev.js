var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The showcase listing's state as its address spells it: read from the address the way the server reads
 * it (criteria), and written back the way the server's own links spell it.
 *
 * Every function is handed the calling client's state ({@see ListingState}) and keeps nothing of its own.
 * Imported by the visitor's page: nothing here may read the session.
 *
 * @module     theme_dimensions/listing/address
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { period as calendarPeriod } from "../daterange/calendar";
import { SELECTORS } from "./events";
const PARAM = {
  query: "q",
  chip: "chip",
  fav: "fav",
  page: "page",
  perpage: "perpage",
  view: "view",
  sort: "sort",
  group: "group",
  from: "from",
  to: "to",
  cat: "cat",
  fieldprefix: "cf_",
  open: "open",
  psort: "psort"
};
const FOLD_PARAMS = [
  PARAM.query,
  PARAM.chip,
  PARAM.fav,
  PARAM.from,
  PARAM.to,
  PARAM.cat,
  PARAM.sort,
  PARAM.perpage,
  PARAM.open,
  PARAM.psort
];
const SCOPE = ["", "enrolled", "open", "scheduled"];
const CHIP_PERIOD = "period";
const LEGACY_FAVOURITES = "favourites";
const GROUPED_SORTS = ["", "category"];
const SORTS = ["", "category", "name", "recent"];
const GROUPS = ["category", "none"];
const ORDERS = ["name", "recent"];
const ORDER_NAME = "name";
const organisationOf = /* @__PURE__ */ __name((params, defaultsort) => {
  const group = params.get(PARAM.group) || "";
  const named = params.get(PARAM.sort) || "";
  let psort = params.get(PARAM.psort) === ORDER_NAME ? ORDER_NAME : "";
  let sort = SORTS.indexOf(named) > 0 ? named : "";
  if (GROUPS.indexOf(group) >= 0) {
    let order = psort === ORDER_NAME ? ORDER_NAME : "recent";
    if (ORDERS.indexOf(named) >= 0) {
      order = named;
    }
    sort = group === "none" ? order : "category";
    psort = group === "none" || order !== ORDER_NAME ? "" : ORDER_NAME;
  }
  sort = sort || defaultsort || "";
  return { sort, psort: GROUPED_SORTS.indexOf(sort) >= 0 ? psort : "" };
}, "organisationOf");
const groupOf = /* @__PURE__ */ __name((state) => GROUPED_SORTS.indexOf(state.sort) >= 0 ? "category" : "none", "groupOf");
const orderOf = /* @__PURE__ */ __name((state) => {
  if (GROUPED_SORTS.indexOf(state.sort) >= 0) {
    return state.psort === ORDER_NAME ? ORDER_NAME : "recent";
  }
  return state.sort;
}, "orderOf");
const organise = /* @__PURE__ */ __name((state, group, order) => {
  if (group === "none") {
    state.sort = order;
    state.psort = "";
    return;
  }
  state.sort = isGrouped(state.sort) ? state.sort : "category";
  state.psort = order === ORDER_NAME ? ORDER_NAME : "";
}, "organise");
const isRoot = /* @__PURE__ */ __name((state) => state.categoryid === 0, "isRoot");
const rootOnly = /* @__PURE__ */ __name((state) => {
  if (!isRoot(state)) {
    return;
  }
  state.chip = "";
  state.fav = false;
  state.filters = {};
  state.sort = "";
  state.datefrom = "";
  state.dateto = "";
  state.cat = 0;
  state.page = 0;
  state.pagesize = 0;
}, "rootOnly");
const pageSizeOf = /* @__PURE__ */ __name((params) => {
  const size = Number(params.get(PARAM.perpage));
  return Number.isInteger(size) && size > 0 ? size : 0;
}, "pageSizeOf");
const sortOf = /* @__PURE__ */ __name((params, defaultsort) => organisationOf(params, defaultsort).sort, "sortOf");
const isGrouped = /* @__PURE__ */ __name((sort) => GROUPED_SORTS.indexOf(sort) >= 0, "isGrouped");
const catOf = /* @__PURE__ */ __name((params, sort) => {
  const cat = Number(params.get(PARAM.cat));
  return isGrouped(sort) || !Number.isInteger(cat) || cat <= 0 ? 0 : cat;
}, "catOf");
const periodOf = /* @__PURE__ */ __name((params) => calendarPeriod(params.get(PARAM.from), params.get(PARAM.to)), "periodOf");
const chipOf = /* @__PURE__ */ __name((params) => {
  const chip = params.get(PARAM.chip) || "";
  return chip === LEGACY_FAVOURITES ? "" : chip;
}, "chipOf");
const favOf = /* @__PURE__ */ __name((params) => params.get(PARAM.fav) === "1" || params.get(PARAM.chip) === LEGACY_FAVOURITES, "favOf");
const buildUrl = /* @__PURE__ */ __name((state) => {
  const url = new URL(window.location.href);
  const params = url.searchParams;
  Array.from(params.keys()).filter((key) => key.startsWith(PARAM.fieldprefix)).forEach((key) => params.delete(key));
  [PARAM.query, PARAM.chip, PARAM.fav, PARAM.page, PARAM.view, PARAM.sort, PARAM.group, PARAM.from, PARAM.to, PARAM.cat].forEach((key) => params.delete(key));
  if (state.query !== "") {
    params.set(PARAM.query, state.query);
  }
  if (state.chip !== "") {
    params.set(PARAM.chip, state.chip);
  }
  if (state.fav) {
    params.set(PARAM.fav, "1");
  }
  if (state.remembered) {
    params.set(PARAM.sort, state.sort === "" ? "category" : state.sort);
  }
  if (state.sort !== "" && state.sort !== (state.defaultsort || "")) {
    params.set(PARAM.sort, state.sort);
  }
  if (state.datefrom !== "") {
    params.set(PARAM.from, state.datefrom);
  }
  if (state.dateto !== "") {
    params.set(PARAM.to, state.dateto);
  }
  if (state.cat > 0 && !isGrouped(state.sort)) {
    params.set(PARAM.cat, String(state.cat));
  }
  Object.keys(state.filters).forEach((fieldid) => {
    if (state.filters[fieldid]) {
      params.set(PARAM.fieldprefix + fieldid, String(state.filters[fieldid]));
    }
  });
  if (state.page > 0) {
    params.set(PARAM.page, String(state.page));
  }
  if (state.viewchosen) {
    params.set(PARAM.view, state.view);
  }
  if (!isGrouped(state.sort)) {
    params.delete(PARAM.open);
    params.delete(PARAM.psort);
  } else if (state.psort === ORDER_NAME) {
    params.set(PARAM.psort, ORDER_NAME);
  } else {
    params.delete(PARAM.psort);
  }
  return url;
}, "buildUrl");
const syncFold = /* @__PURE__ */ __name(() => {
  const form = document.querySelector(SELECTORS.fold);
  if (!form) {
    return;
  }
  const carried = /* @__PURE__ */ __name((name) => FOLD_PARAMS.indexOf(name) >= 0 || name.startsWith(PARAM.fieldprefix), "carried");
  form.querySelectorAll('input[type="hidden"]').forEach((input) => {
    if (carried(input.name)) {
      input.remove();
    }
  });
  const before = form.querySelector("button");
  new URL(window.location.href).searchParams.forEach((value, name) => {
    if (!carried(name)) {
      return;
    }
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = name;
    input.value = value;
    if (name === PARAM.open) {
      input.dataset.region = "dim-open-field";
    } else if (name === PARAM.psort) {
      input.dataset.region = "dim-psort-field";
    }
    form.insertBefore(input, before);
  });
}, "syncFold");
const withPanel = /* @__PURE__ */ __name((href) => {
  const target = new URL(href, window.location.href);
  const current = new URL(window.location.href).searchParams;
  [PARAM.open, PARAM.psort].forEach((name) => {
    if (current.has(name)) {
      target.searchParams.set(name, current.get(name) || "");
    } else {
      target.searchParams.delete(name);
    }
  });
  return target.toString();
}, "withPanel");
const readUrl = /* @__PURE__ */ __name((state) => {
  const params = new URL(window.location.href).searchParams;
  state.query = params.get(PARAM.query) || "";
  state.chip = chipOf(params);
  state.fav = favOf(params);
  const organisation = organisationOf(params, state.defaultsort);
  state.sort = organisation.sort;
  state.psort = organisation.psort;
  const period = periodOf(params);
  state.datefrom = period.from;
  state.dateto = period.to;
  state.cat = catOf(params, state.sort);
  state.filters = {};
  params.forEach((value, key) => {
    if (key.startsWith(PARAM.fieldprefix) && Number(value) > 0) {
      state.filters[key.slice(PARAM.fieldprefix.length)] = Number(value);
    }
  });
  state.page = 0;
  const view = params.get(PARAM.view);
  if (view === "cards" || view === "list") {
    state.view = view;
    state.viewchosen = true;
  }
  state.pagesize = pageSizeOf(params);
  rootOnly(state);
}, "readUrl");
const narrows = /* @__PURE__ */ __name((state) => state.query !== "" || state.chip !== "" || Boolean(state.fav) || Object.keys(state.filters).length > 0 || state.datefrom !== "" || state.dateto !== "", "narrows");
const filterArgs = /* @__PURE__ */ __name((state) => Object.keys(state.filters).map((fieldid) => ({
  fieldid: Number(fieldid),
  value: Number(state.filters[fieldid])
})), "filterArgs");
const dropCriterion = /* @__PURE__ */ __name((state, param) => {
  if (param === PARAM.query) {
    state.query = "";
  } else if (param === PARAM.chip) {
    state.chip = "";
  } else if (param === PARAM.cat) {
    state.cat = 0;
  } else if (param === CHIP_PERIOD) {
    state.datefrom = "";
    state.dateto = "";
  } else if (param && param.startsWith(PARAM.fieldprefix)) {
    delete state.filters[param.slice(PARAM.fieldprefix.length)];
  } else {
    return false;
  }
  state.page = 0;
  return true;
}, "dropCriterion");
export {
  CHIP_PERIOD,
  GROUPED_SORTS,
  GROUPS,
  ORDERS,
  PARAM,
  SCOPE,
  buildUrl,
  catOf,
  chipOf,
  dropCriterion,
  favOf,
  filterArgs,
  groupOf,
  isGrouped,
  isRoot,
  narrows,
  orderOf,
  organisationOf,
  organise,
  pageSizeOf,
  periodOf,
  readUrl,
  rootOnly,
  sortOf,
  syncFold,
  withPanel
};
//# sourceMappingURL=address.dev.js.map
