var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The category navigation's row of nav pills, once a script runs: menus with their keys, and "More" for what does not fit.
 *
 * The server draws the whole row and it works without this module (templates/category_navpills.mustache): a plain
 * pill is a link, a group a native disclosure whose menu holds one link per destination. Armed, the row claims the
 * menu roles only now that the keys they promise work (D91): the summary of each group gets `aria-haspopup`,
 * `aria-controls` and `aria-expanded`, its menu `role="menu"`, its links `role="menuitem"`. One menu is open at a
 * time; ArrowDown, Enter or Space on a pill opens its menu on the first item; inside, the arrows move with wrap,
 * Home and End go to the ends, Escape closes and gives the focus back to the pill, and Tab out closes. A press
 * outside closes without moving the focus. A group's menu that would cross the viewport's end opens end-aligned.
 *
 * The overflow (D83, D89): the armed capsule stops wrapping, and what does not fit moves into "More" from the
 * row's end, "Category" last of all; the arithmetic is {@see ./fit fit()}. A moved group becomes a headed section of
 * More's menu, a separator between sections and after the nodes More holds of its own; a moved plain pill becomes
 * an item, its server-drawn icon shown. Nodes are moved, never cloned: one link per destination and no duplicate id.
 * The row is measured once per pass, with every unit put back in the server's order first, at arming (the first
 * entry of a ResizeObserver on the track), after the fonts are ready and on every change of the track's width; never
 * while a menu is open, which defers the pass to its closing. More takes the selected look when it holds the
 * current page (D82).
 *
 * The row is an account's (the visitor's shell draws none), and nothing a visitor's page loads imports this module.
 *
 * @module     theme_dimensions/navpills/row
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Pending from "@moodle/lms/core/pending";
import { fit } from "./fit";
const MAIN = "categorymain";
const SELECTORS = {
  row: '[data-region="dim-navpills"]',
  more: '[data-region="dim-navpills-more"]',
  menu: ":scope > .dim-navpill-menu",
  summary: ":scope > summary",
  item: ".dim-navpill-menu__item",
  menuitem: '[role="menuitem"]',
  label: ".dim-navpill__label"
};
const CLASSES = {
  live: "dim-navpills--live",
  pill: "dim-navpill",
  item: "dim-navpill-menu__item",
  head: "dim-navpill-menu__head",
  section: "dim-navpill-menu__section",
  separator: "dim-navpill-menu__sep",
  end: "dim-navpill-menu--end",
  selected: "is-selected",
  dot: "dim-navpill__dot"
};
const OPENERS = ["ArrowDown", "Enter", " "];
const px = /* @__PURE__ */ __name((value) => parseFloat(value) || 0, "px");
const summaryOf = /* @__PURE__ */ __name((details) => details.querySelector(SELECTORS.summary), "summaryOf");
const menuOf = /* @__PURE__ */ __name((details) => details.querySelector(SELECTORS.menu), "menuOf");
const itemsOf = /* @__PURE__ */ __name((details) => {
  const menu = menuOf(details);
  return menu ? Array.from(menu.querySelectorAll(SELECTORS.menuitem)) : [];
}, "itemsOf");
const disclosures = /* @__PURE__ */ __name((row) => {
  const groups = row.units.map((unit) => unit.group).filter((group) => group !== null);
  return [...groups, row.more];
}, "disclosures");
const step = /* @__PURE__ */ __name((key, index, count) => {
  if (count === 0) {
    return null;
  }
  if (key === "ArrowDown") {
    return (index + 1) % count;
  }
  if (key === "ArrowUp") {
    return index <= 0 ? count - 1 : index - 1;
  }
  if (key === "Home") {
    return 0;
  }
  if (key === "End") {
    return count - 1;
  }
  return null;
}, "step");
const collect = /* @__PURE__ */ __name((nav) => {
  const more = nav.querySelector(SELECTORS.more);
  const moremenu = more ? menuOf(more) : null;
  const track = nav.parentElement;
  if (!more || !moremenu || !track) {
    return null;
  }
  const elements = Array.from(nav.children).filter((child) => child instanceof HTMLElement);
  const units = elements.filter((element) => element !== more).map((element) => {
    const group = element instanceof HTMLDetailsElement ? element : null;
    const menu = group ? menuOf(group) : null;
    return {
      element,
      group,
      menu,
      items: menu ? Array.from(menu.querySelectorAll(SELECTORS.item)) : [],
      main: element.dataset.key === MAIN
    };
  });
  const dot = document.createElement("span");
  dot.className = CLASSES.dot;
  dot.setAttribute("aria-hidden", "true");
  return {
    nav,
    track,
    units,
    more,
    moremenu,
    own: Array.from(moremenu.querySelectorAll(SELECTORS.item)),
    servercurrent: Boolean(summaryOf(more)?.classList.contains(CLASSES.selected)),
    dot,
    moved: [],
    added: [],
    open: null,
    stale: false,
    width: -1
  };
}, "collect");
const itemise = /* @__PURE__ */ __name((link, inmenu, swap) => {
  if (swap) {
    link.classList.toggle(CLASSES.pill, !inmenu);
    link.classList.toggle(CLASSES.item, inmenu);
  }
  if (inmenu) {
    link.setAttribute("role", "menuitem");
    link.tabIndex = -1;
  } else {
    link.removeAttribute("role");
    link.removeAttribute("tabindex");
  }
}, "itemise");
const addRoles = /* @__PURE__ */ __name((row) => {
  disclosures(row).forEach((details) => {
    const summary = summaryOf(details);
    const menu = menuOf(details);
    if (!summary || !menu) {
      return;
    }
    summary.setAttribute("aria-haspopup", "menu");
    summary.setAttribute("aria-controls", menu.id);
    summary.setAttribute("aria-expanded", details.open ? "true" : "false");
    menu.setAttribute("role", "menu");
    menu.querySelectorAll(SELECTORS.item).forEach((link) => itemise(link, true, false));
  });
}, "addRoles");
const place = /* @__PURE__ */ __name((row, details) => {
  const menu = menuOf(details);
  if (!menu || details === row.more) {
    return;
  }
  menu.classList.remove(CLASSES.end);
  const rect = menu.getBoundingClientRect();
  const rtl = window.getComputedStyle(row.nav).direction === "rtl";
  const end = document.documentElement.clientWidth;
  menu.classList.toggle(CLASSES.end, rtl ? rect.left < 0 : end > 0 && rect.right > end);
}, "place");
const opened = /* @__PURE__ */ __name((row, details) => {
  if (row.open === details) {
    return;
  }
  row.open = details;
  disclosures(row).forEach((other) => {
    if (other !== details && other.open) {
      close(row, other, false);
    }
  });
  summaryOf(details)?.setAttribute("aria-expanded", "true");
  place(row, details);
}, "opened");
const closed = /* @__PURE__ */ __name((row, details) => {
  summaryOf(details)?.setAttribute("aria-expanded", "false");
  menuOf(details)?.classList.remove(CLASSES.end);
  if (row.open !== details) {
    return;
  }
  row.open = null;
  if (row.stale) {
    measure(row);
  }
}, "closed");
const open = /* @__PURE__ */ __name((row, details, focus) => {
  details.open = true;
  opened(row, details);
  if (focus) {
    itemsOf(details)[0]?.focus();
  }
}, "open");
const close = /* @__PURE__ */ __name((row, details, focus) => {
  details.open = false;
  closed(row, details);
  if (focus) {
    const pill = details.hidden ? summaryOf(row.more) : summaryOf(details);
    pill?.focus();
  }
}, "close");
const paintMore = /* @__PURE__ */ __name((row) => {
  const summary = summaryOf(row.more);
  if (row.servercurrent || !summary) {
    return;
  }
  const current = row.moved.some((unit) => [unit.element, ...unit.items].some((link) => link.getAttribute("aria-current") === "page"));
  summary.classList.toggle(CLASSES.selected, current);
  if (current) {
    summary.setAttribute("aria-current", "page");
    summary.prepend(row.dot);
  } else {
    summary.removeAttribute("aria-current");
    row.dot.remove();
  }
}, "paintMore");
const restore = /* @__PURE__ */ __name((row) => {
  row.moved.forEach((unit) => {
    if (unit.group && unit.menu) {
      unit.menu.append(...unit.items);
      unit.group.hidden = false;
    } else {
      itemise(unit.element, false, true);
    }
  });
  row.added.forEach((element) => element.remove());
  row.added = [];
  row.moved = [];
  let anchor = row.more;
  for (let index = row.units.length - 1; index >= 0; index--) {
    const element = row.units[index].element;
    if (element.parentElement !== row.nav || element.nextElementSibling !== anchor) {
      row.nav.insertBefore(element, anchor);
    }
    anchor = element;
  }
  row.more.hidden = row.own.length === 0;
  paintMore(row);
}, "restore");
const make = /* @__PURE__ */ __name((row, tag, name) => {
  const element = document.createElement(tag);
  element.className = name;
  row.moremenu.append(element);
  row.added.push(element);
  return element;
}, "make");
const apply = /* @__PURE__ */ __name((row, fitted) => {
  let above = row.own.length > 0;
  let plain = false;
  fitted.move.forEach((index) => {
    const unit = row.units[index];
    if (unit.group && unit.menu) {
      if (above) {
        make(row, "div", CLASSES.separator).setAttribute("role", "separator");
      }
      const head = make(row, "div", CLASSES.head);
      head.id = unit.menu.id + "-head";
      head.textContent = unit.group.querySelector(SELECTORS.label)?.textContent ?? "";
      const section = make(row, "div", CLASSES.section);
      section.setAttribute("role", "group");
      section.setAttribute("aria-labelledby", head.id);
      section.append(...unit.items);
      unit.group.hidden = true;
      plain = false;
    } else {
      if (above && !plain) {
        make(row, "div", CLASSES.separator).setAttribute("role", "separator");
      }
      itemise(unit.element, true, true);
      row.moremenu.append(unit.element);
      plain = true;
    }
    above = true;
    row.moved.push(unit);
  });
  row.more.hidden = !fitted.more;
  paintMore(row);
}, "apply");
const measure = /* @__PURE__ */ __name((row) => {
  if (row.open) {
    row.stale = true;
    return;
  }
  row.stale = false;
  restore(row);
  const hidden = row.more.hidden;
  row.more.hidden = false;
  const track = row.track.clientWidth;
  if (track <= 0) {
    row.more.hidden = hidden;
    return;
  }
  const widths = row.units.map((unit) => unit.element.getBoundingClientRect().width);
  const more = row.more.getBoundingClientRect().width;
  const capsule = window.getComputedStyle(row.nav);
  const trackstyle = window.getComputedStyle(row.track);
  const room = track - px(trackstyle.paddingLeft) - px(trackstyle.paddingRight) - px(capsule.paddingLeft) - px(capsule.paddingRight) - px(capsule.borderLeftWidth) - px(capsule.borderRightWidth);
  apply(row, fit({
    widths,
    gap: px(capsule.columnGap),
    room,
    more,
    last: row.units.findIndex((unit) => unit.main),
    own: row.own.length > 0
  }));
}, "measure");
const pillKey = /* @__PURE__ */ __name((row, details, event) => {
  if (event.key === "Escape") {
    if (details.open) {
      event.preventDefault();
      close(row, details, false);
    }
    return;
  }
  if (!OPENERS.includes(event.key)) {
    return;
  }
  event.preventDefault();
  if (details.open && event.key !== "ArrowDown") {
    close(row, details, false);
    return;
  }
  open(row, details, true);
}, "pillKey");
const menuKey = /* @__PURE__ */ __name((row, details, event) => {
  if (event.key === "Escape") {
    event.preventDefault();
    event.stopPropagation();
    close(row, details, true);
    return;
  }
  const items = itemsOf(details);
  const next = step(event.key, items.indexOf(event.target), items.length);
  if (next === null) {
    return;
  }
  event.preventDefault();
  items[next].focus();
}, "menuKey");
const onKey = /* @__PURE__ */ __name((row, event) => {
  const target = event.target;
  if (!(target instanceof HTMLElement)) {
    return;
  }
  const details = target.closest("details");
  if (!details || !row.nav.contains(details)) {
    return;
  }
  if (target === summaryOf(details)) {
    pillKey(row, details, event);
  } else if (menuOf(details)?.contains(target)) {
    menuKey(row, details, event);
  }
}, "onKey");
const onKeyUp = /* @__PURE__ */ __name((row, event) => {
  const target = event.target;
  if (event.key === " " && target instanceof HTMLElement && target.matches("summary") && row.nav.contains(target)) {
    event.preventDefault();
  }
}, "onKeyUp");
const onToggle = /* @__PURE__ */ __name((row, event) => {
  const details = event.target;
  if (!(details instanceof HTMLDetailsElement) || !disclosures(row).includes(details)) {
    return;
  }
  if (details.open) {
    opened(row, details);
  } else {
    closed(row, details);
  }
}, "onToggle");
const onFocusOut = /* @__PURE__ */ __name((row, event) => {
  const next = event.relatedTarget;
  if (row.open && next instanceof Node && !row.open.contains(next)) {
    close(row, row.open, false);
  }
}, "onFocusOut");
const onPress = /* @__PURE__ */ __name((row, event) => {
  const target = event.target;
  if (row.open && target instanceof Node && !row.open.contains(target)) {
    close(row, row.open, false);
  }
}, "onPress");
const listen = /* @__PURE__ */ __name((row) => {
  row.nav.addEventListener("toggle", (event) => onToggle(row, event), true);
  row.nav.addEventListener("keydown", (event) => onKey(row, event));
  row.nav.addEventListener("keyup", (event) => onKeyUp(row, event));
  row.nav.addEventListener("focusout", (event) => onFocusOut(row, event));
  document.addEventListener("pointerdown", (event) => onPress(row, event), true);
}, "listen");
const observe = /* @__PURE__ */ __name((row, pending) => {
  if (typeof ResizeObserver === "undefined") {
    try {
      measure(row);
    } finally {
      pending.resolve();
    }
    return;
  }
  let first = true;
  const observer = new ResizeObserver((entries) => {
    const width = entries[entries.length - 1].contentRect.width;
    try {
      if (first || width !== row.width) {
        row.width = width;
        measure(row);
      }
    } finally {
      if (first) {
        first = false;
        pending.resolve();
      }
    }
  });
  observer.observe(row.track);
}, "observe");
const afterFonts = /* @__PURE__ */ __name(async (row) => {
  if (!document.fonts) {
    return;
  }
  const pending = new Pending("theme_dimensions/navpills:fonts");
  try {
    await document.fonts.ready;
    measure(row);
  } finally {
    pending.resolve();
  }
}, "afterFonts");
const arm = /* @__PURE__ */ __name((nav) => {
  if (nav.classList.contains(CLASSES.live)) {
    return null;
  }
  const row = collect(nav);
  if (!row) {
    return null;
  }
  const pending = new Pending("theme_dimensions/navpills:arm");
  nav.classList.add(CLASSES.live);
  addRoles(row);
  listen(row);
  observe(row, pending);
  afterFonts(row);
  return row;
}, "arm");
const init = /* @__PURE__ */ __name((uniqid) => {
  const nav = document.getElementById(uniqid);
  if (nav instanceof HTMLElement && nav.matches(SELECTORS.row)) {
    arm(nav);
  }
}, "init");
export {
  CLASSES,
  MAIN,
  arm,
  close,
  init,
  measure,
  open,
  step
};
//# sourceMappingURL=row.dev.js.map
