var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * What the filter line draws, read off the server's line and moved by what the listing tells it.
 *
 * A React mount replaces the markup it is mounted on (core's createRoot empties the node on its first
 * commit), so the component's first render has to draw the boxes the server drew. It does by reading
 * them: {@see read} takes every word, name, id, count and state of the line's own controls off the
 * server's markup while it is still in the document, during the first render, and the component draws
 * that same markup back (the template category_toolbar's, hook for hook). Nothing is passed twice, and
 * whatever the reader did on the server's line before the mount - a typed term, a radio pressed - is read
 * as it stands.
 *
 * After that the listing tells the line what moved, through two events ({@see answered}, {@see stated});
 * every change here is a function of the model and what it is told, and nothing reads the page again.
 *
 * @module     theme_dimensions/filterline/model
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const SELECTORS = {
  line: '[data-region="dim-line"]',
  total: '[data-region="dim-line-total"]',
  scope: '[data-region="dim-vitrine-group"][data-role="scope"]',
  search: '[data-region="dim-line-search"]',
  field: 'input[type="search"]',
  eraser: '[data-action="dim-search-clear"]',
  opener: '[data-action="dim-search-open"]',
  closer: '[data-action="dim-search-close"]',
  star: '[data-region="dim-line-star"]',
  favcount: '[data-region="dim-fav-count"]',
  filters: '[data-region="dim-filters"]',
  filterslabel: ".dim-toolbar__filterslabel",
  badge: '[data-region="dim-filtercount"]',
  badgenumber: '[data-region="dim-filtercount-number"]',
  badgelabel: '[data-region="dim-filtercount-label"]',
  panel: '[data-region="dim-filters-panel"]',
  organise: '[data-region="dim-organise"]',
  menu: '[data-region="dim-organise-menu"]',
  viewrow: '[data-region="dim-view-row"]',
  view: '[data-action="dim-view"]',
  manage: '[data-region="dim-line-manage"]',
  listbox: '[data-region="dim-line-suggest"]',
  pillcount: '[data-region="dim-pill-count"]'
};
const TIP = "data-dim-tip";
const text = /* @__PURE__ */ __name((element) => element ? (element.textContent || "").trim() : "", "text");
const wordsOf = /* @__PURE__ */ __name((label) => Array.from(label.childNodes).filter((node) => node.nodeType === Node.TEXT_NODE).map((node) => node.textContent || "").join("").trim(), "wordsOf");
const cellsOf = /* @__PURE__ */ __name((fieldset) => Array.from(fieldset.querySelectorAll('input[type="radio"]')).map((input) => {
  const label = fieldset.querySelector(`label[for="${input.id}"]`);
  const count = label ? label.querySelector(SELECTORS.pillcount) : null;
  return {
    id: input.id,
    value: input.value,
    label: label ? wordsOf(label) : "",
    count: text(count),
    hascount: Boolean(count) && !count.hidden,
    checked: input.checked,
    absent: input.hidden || Boolean(label && label.hidden)
  };
}), "cellsOf");
const attrOf = /* @__PURE__ */ __name((root, selector, name) => {
  const element = root.querySelector(selector);
  return element ? element.getAttribute(name) || "" : "";
}, "attrOf");
const scopeOf = /* @__PURE__ */ __name((line) => {
  const scope = line.querySelector(SELECTORS.scope);
  if (!scope) {
    return null;
  }
  return { legend: text(scope.querySelector("legend")), name: scope.dataset.name || "chip", cells: cellsOf(scope) };
}, "scopeOf");
const starOf = /* @__PURE__ */ __name((line) => {
  const star = line.querySelector(SELECTORS.star);
  if (!star) {
    return null;
  }
  return {
    name: star.name,
    pressed: star.getAttribute("aria-pressed") === "true",
    count: text(star.querySelector(SELECTORS.favcount)),
    label: star.getAttribute(TIP) || ""
  };
}, "starOf");
const filtersOf = /* @__PURE__ */ __name((line) => {
  const filters = line.querySelector(SELECTORS.filters);
  const badge = filters ? filters.querySelector(SELECTORS.badge) : null;
  return {
    label: text(filters ? filters.querySelector(SELECTORS.filterslabel) : null),
    count: text(badge ? badge.querySelector(SELECTORS.badgenumber) : null),
    countlabel: text(badge ? badge.querySelector(SELECTORS.badgelabel) : null),
    hascount: badge !== null && !badge.hidden
  };
}, "filtersOf");
const viewsOf = /* @__PURE__ */ __name((row) => (row ? Array.from(row.querySelectorAll(SELECTORS.view)) : []).map((button) => ({
  value: button.value,
  label: button.getAttribute(TIP) || "",
  icon: (button.querySelector("i")?.className || "").replace(/^fa\s+/, ""),
  pressed: button.getAttribute("aria-pressed") === "true"
})), "viewsOf");
const read = /* @__PURE__ */ __name((line) => {
  const search = line ? line.querySelector(SELECTORS.search) : null;
  const field = search ? search.querySelector(SELECTORS.field) : null;
  const listbox = line ? line.querySelector(SELECTORS.listbox) : null;
  if (!line || !search || !field || !listbox) {
    return null;
  }
  const row = line.querySelector(SELECTORS.viewrow);
  return {
    scope: scopeOf(line),
    total: text(line.querySelector(SELECTORS.total)),
    search: {
      id: field.id,
      name: field.name,
      label: text(search.querySelector(`label[for="${field.id}"]`)),
      placeholder: field.placeholder,
      value: field.value,
      clearlabel: attrOf(search, SELECTORS.eraser, "aria-label")
    },
    openlabel: attrOf(line, SELECTORS.opener, TIP),
    star: starOf(line),
    filters: filtersOf(line),
    organiselabel: attrOf(line, `${SELECTORS.organise} > summary`, TIP),
    views: viewsOf(row),
    viewlabel: row ? row.getAttribute("aria-label") || "" : "",
    viewname: row ? attrOf(row, SELECTORS.view, "name") || "view" : "view",
    manage: line.querySelector(SELECTORS.manage) !== null,
    closelabel: attrOf(line, SELECTORS.closer, "aria-label"),
    suggest: { id: listbox.id, filters: listbox.dataset.labelFilters || "", start: listbox.dataset.labelStart || "" }
  };
}, "read");
const scopeAfter = /* @__PURE__ */ __name((cells, options) => {
  const byvalue = new Map(options.map((option) => [String(option.value), option]));
  const known = new Set(cells.map((cell) => cell.value));
  const drawn = cells.map((cell) => {
    const option = byvalue.get(cell.value);
    if (!option) {
      return Object.assign({}, cell, { absent: true, checked: false });
    }
    return {
      id: option.id || cell.id,
      value: cell.value,
      label: option.label === void 0 ? cell.label : option.label,
      count: option.hascount ? String(option.count ?? "") : "",
      hascount: Boolean(option.hascount),
      checked: Boolean(option.checked),
      absent: false
    };
  });
  options.filter((option) => !known.has(String(option.value)) && option.id).forEach((option) => {
    drawn.push({
      id: String(option.id),
      value: String(option.value),
      label: option.label || "",
      count: option.hascount ? String(option.count ?? "") : "",
      hascount: Boolean(option.hascount),
      checked: Boolean(option.checked),
      absent: false
    });
  });
  return drawn;
}, "scopeAfter");
const answered = /* @__PURE__ */ __name((model, answer) => {
  const next = Object.assign({}, model);
  if (typeof answer.total === "string") {
    next.total = answer.total;
  }
  if (model.scope && answer.scope && answer.scope.length) {
    next.scope = Object.assign({}, model.scope, { cells: scopeAfter(model.scope.cells, answer.scope) });
  }
  const toggle = answer.favourites;
  if (model.star && toggle && toggle.available) {
    next.star = Object.assign({}, model.star, { pressed: Boolean(toggle.pressed), count: String(Number(toggle.count) || 0) });
  }
  if (answer.filtercount !== void 0) {
    const count = Number(answer.filtercount || 0);
    next.filters = Object.assign({}, model.filters, {
      count: String(count),
      countlabel: answer.filtercountlabel || "",
      hascount: count !== 0
    });
  }
  return next;
}, "answered");
const stated = /* @__PURE__ */ __name((model, told) => {
  const next = Object.assign({}, model);
  if (typeof told.query === "string") {
    next.search = Object.assign({}, model.search, { value: told.query });
  }
  if (told.chip !== void 0 && model.scope) {
    next.scope = Object.assign({}, model.scope, {
      cells: model.scope.cells.map((cell) => Object.assign({}, cell, { checked: cell.value === told.chip }))
    });
  }
  if (typeof told.fav === "boolean" && model.star) {
    next.star = Object.assign({}, model.star, { pressed: told.fav });
  }
  if (typeof told.view === "string") {
    next.views = model.views.map((view) => Object.assign({}, view, { pressed: view.value === told.view }));
  }
  return next;
}, "stated");
export {
  SELECTORS,
  TIP,
  answered,
  read,
  scopeAfter,
  stated
};
//# sourceMappingURL=model.dev.js.map
