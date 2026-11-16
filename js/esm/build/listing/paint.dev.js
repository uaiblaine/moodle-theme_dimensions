var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The showcase listing's painters: how both clients put a web service's answer onto the page the server drew.
 *
 * Every word and count is the answer's; a row is rendered through the server's card template into list items
 * never diffed inside; a painter of the line's controls asks drawnByLine() and tells the component instead.
 * The two counters of the document live here, one listing per page. Imported by the visitor's page: nothing
 * here may call a web service, read the session or write a mark on the body.
 *
 * @module     theme_dimensions/listing/paint
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Pending from "@moodle/lms/core/pending";
import { load } from "../amd";
import { markTitles } from "../areas/mark";
import { CHIP_PERIOD, PARAM, SCOPE, groupOf, orderOf, withPanel } from "./address";
import { LINE, LISTING, SELECTORS } from "./events";
import { drawnByLine, filterGroupsShown, tellCalendar, tellLine } from "./mounts";
const CARD_TEMPLATE = "theme_dimensions/category_card";
const PAGE_WINDOW = 10;
const TREE_PHONE = "(max-width: 767.98px)";
let appended = 0;
const SHOW_NUMBER = "{n}";
const PERIOD_SENTENCE = "{period}";
let announcements = 0;
const closestOf = /* @__PURE__ */ __name((event, selector) => {
  const target = event.target;
  if (!(target instanceof Element)) {
    return null;
  }
  return target.closest(selector);
}, "closestOf");
const VIEW_ICONS = { cards: "fa-border-all", list: "fa-list" };
const paintView = /* @__PURE__ */ __name((root, view) => {
  root.classList.toggle("dim-vitrine--cards", view === "cards");
  root.classList.toggle("dim-vitrine--list", view === "list");
  root.querySelectorAll(SELECTORS.viewbutton).forEach((button) => {
    if (!drawnByLine(button)) {
      button.setAttribute("aria-checked", button.value === view ? "true" : "false");
    }
  });
  const icon = root.querySelector(SELECTORS.viewicon);
  if (icon && !drawnByLine(icon)) {
    icon.className = `fa ${VIEW_ICONS[view] || VIEW_ICONS.cards}`;
  }
  tellLine(root, LINE.state, { view });
  document.dispatchEvent(new CustomEvent(LISTING.view, { detail: { view } }));
}, "paintView");
const markTree = /* @__PURE__ */ __name((root, state) => {
  root.querySelectorAll(SELECTORS.treelink).forEach((link) => {
    const id = Number(link.dataset.categoryid);
    const on = state.cat === 0 ? id === state.categoryid : id === state.cat;
    link.classList.toggle("dim-tree__link--on", on);
    if (on) {
      link.setAttribute("aria-current", "true");
    } else {
      link.removeAttribute("aria-current");
    }
  });
}, "markTree");
const paintDates = /* @__PURE__ */ __name((root, state) => {
  tellCalendar(root.querySelector(SELECTORS.daterange), state);
  const fields = root.querySelector(SELECTORS.dateinputs);
  if (!fields) {
    return;
  }
  const inputs = fields.querySelectorAll('input[type="date"]');
  inputs.forEach((input) => {
    if (input.name === PARAM.from) {
      input.value = state.datefrom;
    } else if (input.name === PARAM.to) {
      input.value = state.dateto;
    }
  });
}, "paintDates");
const paintFavToggle = /* @__PURE__ */ __name((root, on) => {
  const field = root.querySelector(SELECTORS.favfield);
  if (field) {
    field.disabled = !on;
  }
  const star = root.querySelector(SELECTORS.star);
  if (!star || drawnByLine(star)) {
    tellLine(root, LINE.state, { fav: Boolean(on) });
    return;
  }
  star.setAttribute("aria-pressed", on ? "true" : "false");
  star.value = on ? "0" : "1";
  const glyph = star.querySelector(".fa-star");
  if (glyph) {
    glyph.classList.toggle("fa-solid", on);
    glyph.classList.toggle("fa-regular", !on);
  }
}, "paintFavToggle");
const paintEraser = /* @__PURE__ */ __name((root) => {
  const search = root.querySelector(SELECTORS.search);
  const eraser = root.querySelector(SELECTORS.eraser);
  if (search && eraser && !drawnByLine(eraser)) {
    eraser.hidden = search.value === "";
  }
}, "paintEraser");
const paintOrganise = /* @__PURE__ */ __name((root, state) => {
  const chosen = { [PARAM.group]: groupOf(state), [PARAM.sort]: orderOf(state) };
  root.querySelectorAll(SELECTORS.organiseitem).forEach((item) => {
    item.setAttribute("aria-checked", chosen[item.name] === item.value ? "true" : "false");
  });
  root.querySelectorAll(SELECTORS.organisefield).forEach((field) => {
    field.value = chosen[field.name] || "";
  });
}, "paintOrganise");
const paintControls = /* @__PURE__ */ __name((root, state) => {
  tellLine(root, LINE.state, { query: state.query, chip: state.chip, fav: Boolean(state.fav), view: state.view });
  const search = root.querySelector(SELECTORS.search);
  if (search && !drawnByLine(search)) {
    search.value = state.query;
  }
  paintEraser(root);
  root.querySelectorAll(SELECTORS.group).forEach((fieldset) => {
    if (drawnByLine(fieldset)) {
      return;
    }
    const name = fieldset.dataset.name || "";
    const current = name === PARAM.chip ? state.chip : String(state.filters[name.slice(PARAM.fieldprefix.length)] || 0);
    fieldset.querySelectorAll('input[type="radio"]').forEach((input) => {
      input.checked = input.value === current;
    });
  });
  paintOrganise(root, state);
  paintFavToggle(root, state.fav);
  paintDates(root, state);
  markTree(root, state);
  paintView(root, state.view);
}, "paintControls");
const paintFavourites = /* @__PURE__ */ __name((root, response) => {
  const toggle = response.favourites;
  if (!toggle || !toggle.available) {
    return;
  }
  paintFavToggle(root, Boolean(toggle.pressed));
  const count = root.querySelector(SELECTORS.favcount);
  if (count && !drawnByLine(count)) {
    count.textContent = String(Number(toggle.count) || 0);
  }
}, "paintFavourites");
const paintFilterBadge = /* @__PURE__ */ __name((root, response) => {
  const badge = root.querySelector(SELECTORS.filterbadge);
  if (!badge || drawnByLine(badge)) {
    return;
  }
  const count = Number(response.filtercount || 0);
  const number = badge.querySelector(SELECTORS.filterbadgenumber);
  if (number) {
    number.textContent = String(count);
  }
  const label = badge.querySelector(SELECTORS.filterbadgelabel);
  if (label) {
    label.textContent = response.filtercountlabel || "";
  }
  badge.hidden = count === 0;
}, "paintFilterBadge");
const paintCount = /* @__PURE__ */ __name((root, response) => {
  const number = response.countnumber || "";
  const total = root.querySelector(SELECTORS.total);
  if (total && !drawnByLine(total)) {
    total.textContent = number;
  }
  root.querySelectorAll(`${SELECTORS.filtersshow}, ${SELECTORS.periodshow}`).forEach((show) => {
    const template = show.dataset.dimTemplate || "";
    if (template.indexOf(SHOW_NUMBER) >= 0) {
      show.textContent = template.split(SHOW_NUMBER).join(number);
    }
  });
  const url = response.seeallurl || "";
  const line = root.querySelector(SELECTORS.count);
  if (!line) {
    return;
  }
  line.classList.toggle("visually-hidden", url === "");
  const bold = line.querySelector(SELECTORS.countnumber);
  if (bold) {
    bold.textContent = number;
  }
  const text = line.querySelector(SELECTORS.counttext);
  if (text) {
    announcements += 1;
    const marker = document.createElement("span");
    marker.className = "visually-hidden";
    marker.textContent = "\u200B".repeat(announcements % 3 + 1);
    text.replaceChildren(document.createTextNode(response.countline || ""), marker);
  }
  const seeall = root.querySelector(SELECTORS.seeall);
  if (seeall) {
    const link = seeall.querySelector("a");
    if (link && url !== "") {
      link.href = url;
    }
    seeall.hidden = url === "";
  }
  const note = root.querySelector(SELECTORS.countnote);
  if (note) {
    note.hidden = true;
  }
}, "paintCount");
const createPill = /* @__PURE__ */ __name((track, name, option) => {
  const input = document.createElement("input");
  input.type = "radio";
  input.className = "dim-pill__input visually-hidden";
  input.name = name;
  input.id = option.id;
  input.value = String(option.value);
  const label = document.createElement("label");
  label.className = "dim-pill";
  label.htmlFor = option.id;
  label.append(document.createTextNode(option.label + " "));
  const count = document.createElement("span");
  count.className = "dim-pill__count";
  count.dataset.region = "dim-pill-count";
  label.append(count);
  track.append(input, label);
  return { input, label };
}, "createPill");
const paintGroup = /* @__PURE__ */ __name((fieldset, name, options) => {
  if (!fieldset || !options.length) {
    return;
  }
  const track = fieldset.querySelector(SELECTORS.pilltrack) || fieldset;
  const inputs = /* @__PURE__ */ new Map();
  const labels = /* @__PURE__ */ new Map();
  track.querySelectorAll('input[type="radio"]').forEach((input) => inputs.set(input.value, input));
  track.querySelectorAll("label").forEach((label) => labels.set(label.htmlFor, label));
  const seen = /* @__PURE__ */ new Set();
  const ordered = [];
  options.forEach((option) => {
    const value = String(option.value);
    seen.add(value);
    let input = inputs.get(value);
    let label = input ? labels.get(input.id) : void 0;
    if (!input || !label) {
      const made = createPill(track, name, option);
      input = made.input;
      label = made.label;
    }
    input.hidden = false;
    input.checked = Boolean(option.checked);
    label.hidden = false;
    ordered.push(input, label);
    const count = label.querySelector(SELECTORS.pillcount);
    if (count) {
      count.textContent = option.hascount ? String(option.count) : "";
      count.hidden = !option.hascount;
    }
  });
  let previous = null;
  const head = Array.from(track.children).find((child) => child.matches('input[type="radio"], label'));
  let expected = head || null;
  ordered.forEach((node) => {
    expected = previous ? previous.nextSibling : expected;
    if (node !== expected) {
      track.insertBefore(node, expected);
    }
    previous = node;
  });
  inputs.forEach((input, value) => {
    if (seen.has(value)) {
      return;
    }
    input.hidden = true;
    input.checked = false;
    const label = labels.get(input.id);
    if (label) {
      label.hidden = true;
    }
  });
}, "paintGroup");
const paintStatus = /* @__PURE__ */ __name((scope, more, options) => {
  if (!options.length) {
    return;
  }
  const isScope = /* @__PURE__ */ __name((option) => SCOPE.indexOf(String(option.value)) >= 0, "isScope");
  const rank = /* @__PURE__ */ __name((option) => SCOPE.indexOf(String(option.value)), "rank");
  paintGroup(scope, PARAM.chip, options.filter(isScope).sort((a, b) => rank(a) - rank(b)));
  if (!more) {
    return;
  }
  const others = options.filter((option) => !isScope(option));
  if (others.length) {
    paintGroup(more, PARAM.chip, others);
  } else {
    more.querySelectorAll('input[type="radio"]').forEach((input) => {
      input.hidden = true;
      input.checked = false;
      const label = more.querySelector('label[for="' + input.id + '"]');
      if (label) {
        label.hidden = true;
      }
    });
  }
  more.hidden = others.length === 0;
}, "paintStatus");
const paintGroups = /* @__PURE__ */ __name((root, response) => {
  const groups = /* @__PURE__ */ new Map();
  root.querySelectorAll(SELECTORS.group).forEach((fieldset) => {
    if (drawnByLine(fieldset)) {
      return;
    }
    const name = fieldset.dataset.name || "";
    groups.set(name === PARAM.chip ? name + ":" + (fieldset.dataset.role || "") : name, fieldset);
  });
  if (response.statusgroup) {
    paintStatus(
      groups.get(PARAM.chip + ":scope") || null,
      groups.get(PARAM.chip + ":more") || null,
      response.statusgroup.options || []
    );
  }
  (response.facets || []).forEach((facet) => {
    const any = {
      id: facet.anyid,
      value: 0,
      label: facet.anylabel,
      count: null,
      hascount: false,
      checked: Boolean(facet.anychecked)
    };
    paintGroup(groups.get(facet.name) || null, facet.name, [any].concat(facet.options || []));
  });
}, "paintGroups");
const paintFiltersControl = /* @__PURE__ */ __name((root) => {
  const control = root.querySelector(SELECTORS.filters);
  if (!control || drawnByLine(control)) {
    return;
  }
  const shown = filterGroupsShown(root);
  control.hidden = !shown;
  if (!shown) {
    control.open = false;
  }
}, "paintFiltersControl");
const createChip = /* @__PURE__ */ __name((chip) => {
  const link = document.createElement("a");
  link.className = "dim-chip";
  link.href = withPanel(chip.url);
  link.dataset.action = "dim-chip-remove";
  link.dataset.param = chip.param;
  link.append(document.createTextNode(chip.label + " "));
  const cross = document.createElement("i");
  cross.className = "fa fa-xmark";
  cross.setAttribute("aria-hidden", "true");
  const name = document.createElement("span");
  name.className = "visually-hidden";
  name.textContent = chip.removelabel || "";
  link.append(cross, name);
  return link;
}, "createChip");
const paintDateButton = /* @__PURE__ */ __name((root, chip) => {
  const button = root.querySelector(SELECTORS.periodbutton);
  if (button && !drawnByLine(button)) {
    const template = button.dataset.dimTemplate || "";
    const name = chip && template.indexOf(PERIOD_SENTENCE) >= 0 ? template.split(PERIOD_SENTENCE).join(chip.label) : button.getAttribute("data-dim-tip") || "";
    button.setAttribute("aria-label", name);
    const dot = button.querySelector(SELECTORS.perioddot);
    if (dot) {
      dot.hidden = chip === null;
    }
  }
  const clear = root.querySelector(SELECTORS.periodclear);
  if (clear && chip) {
    clear.href = withPanel(chip.url);
  }
}, "paintDateButton");
const paintChips = /* @__PURE__ */ __name((root, response) => {
  const row = root.querySelector(SELECTORS.chiprow);
  if (!row) {
    return;
  }
  const narrowed = Boolean(response.hasfilters);
  row.classList.toggle("dim-line__chips--rest", !narrowed);
  const panelclear = root.querySelector(SELECTORS.filtersclear);
  if (panelclear) {
    panelclear.hidden = !narrowed;
  }
  const chips = response.chips || [];
  const list = row.querySelector(SELECTORS.chiplist);
  if (list) {
    list.replaceChildren(...chips.filter((chip) => chip.param !== CHIP_PERIOD).map(createChip));
  }
  const periods = chips.filter((chip) => chip.param === CHIP_PERIOD);
  const period = row.querySelector(SELECTORS.rangepill);
  if (period) {
    period.replaceChildren(...periods.map(createChip));
  }
  const clear = row.querySelector(SELECTORS.clear);
  if (clear) {
    clear.hidden = !response.clearall;
  }
  paintDateButton(root, periods.length ? periods[0] : null);
}, "paintChips");
const reveal = /* @__PURE__ */ __name((items) => {
  const pending = new Pending("theme_dimensions/listing:reveal");
  window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
    items.forEach((item) => item.classList.remove("dim-vitrine__item--enter"));
    const fade = items.length ? parseFloat(window.getComputedStyle(items[0]).transitionDuration) * 1e3 : 0;
    window.setTimeout(() => pending.resolve(), fade || 0);
  }));
}, "reveal");
const renderCards = /* @__PURE__ */ __name(async (cards) => {
  const Templates = await load("core/templates");
  const rendered = await Promise.all(cards.map((card) => Templates.renderForPromise(CARD_TEMPLATE, card)));
  if (!rendered.length) {
    return [];
  }
  const holder = document.createElement("ul");
  const html = rendered.map((result) => `<li class="dim-vitrine__item dim-vitrine__item--enter">${result.html}</li>`);
  const js = rendered.map((result) => result.js).filter((one) => one.trim() !== "");
  Templates.appendNodeContents(holder, html.join(""), js.join(";\n"));
  return Array.from(holder.children);
}, "renderCards");
const restoreMore = /* @__PURE__ */ __name((more) => {
  if (!more) {
    return;
  }
  more.disabled = false;
  more.textContent = more.dataset.label || more.textContent;
}, "restoreMore");
const focusAfterAppend = /* @__PURE__ */ __name((more, next, first) => {
  if (more && !more.hidden) {
    restoreMore(more);
    more.focus();
    return;
  }
  if (next && !next.hidden) {
    next.focus();
    return;
  }
  if (first) {
    const title = first.querySelector(SELECTORS.cardtitle);
    if (title) {
      title.focus();
      return;
    }
    first.tabIndex = -1;
    first.focus();
  }
}, "focusAfterAppend");
const paintPager = /* @__PURE__ */ __name((root, response, append, first) => {
  const bar = root.querySelector(SELECTORS.pagerbar);
  if (bar) {
    bar.hidden = true;
  }
  const more = root.querySelector(SELECTORS.loadmore);
  const next = root.querySelector(SELECTORS.nextpage);
  const closed = appended >= PAGE_WINDOW;
  if (next) {
    if (response.nexturl) {
      next.href = response.nexturl;
    }
    next.hidden = !(response.hasmore && closed);
  }
  if (more) {
    more.hidden = !response.hasmore || closed;
  }
  if (append) {
    focusAfterAppend(more, next, first);
  }
}, "paintPager");
const paintGrid = /* @__PURE__ */ __name((root, state, grid, items, append, discard) => {
  if (append) {
    appended += 1;
    grid.append(...items);
  } else {
    appended = 0;
    if (discard) {
      grid.querySelectorAll(SELECTORS.card).forEach((card) => discard(card));
    }
    grid.replaceChildren(...items);
  }
  reveal(items);
  items.forEach((item) => markTitles(item, state.query));
  const empty = root.querySelector(SELECTORS.empty);
  grid.hidden = grid.childElementCount === 0;
  if (empty) {
    empty.hidden = !grid.hidden;
  }
}, "paintGrid");
const paintDirect = /* @__PURE__ */ __name((root, response) => {
  const direct = root.querySelector(SELECTORS.direct);
  if (!direct || !root.querySelector(SELECTORS.area)) {
    return;
  }
  const none = Number(response.total) === 0;
  direct.hidden = none;
  const skip = root.querySelector(SELECTORS.directskip);
  if (skip) {
    skip.hidden = none;
  }
}, "paintDirect");
const armTreebox = /* @__PURE__ */ __name((root) => {
  const box = root.querySelector(SELECTORS.treebox);
  if (!box) {
    return;
  }
  const phone = window.matchMedia(TREE_PHONE);
  box.open = !phone.matches;
  phone.addEventListener("change", (event) => {
    box.open = !event.matches;
  });
}, "armTreebox");
export {
  armTreebox,
  closestOf,
  markTree,
  paintChips,
  paintControls,
  paintCount,
  paintDateButton,
  paintDates,
  paintDirect,
  paintEraser,
  paintFavToggle,
  paintFavourites,
  paintFilterBadge,
  paintFiltersControl,
  paintGrid,
  paintGroups,
  paintOrganise,
  paintPager,
  paintView,
  renderCards,
  restoreMore
};
//# sourceMappingURL=paint.dev.js.map
