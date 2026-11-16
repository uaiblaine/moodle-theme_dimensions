var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The filter line of the showcase, on both shells: the fleet Design System's compact bar, its folding, the
 * search that opens from its button in place or over the line, its suggestions, its floating layers, its
 * tooltips and the row that scrolls sideways.
 *
 * Drawn on both shells; on the visitor's no star is drawn and the scope may be absent: it draws what the server
 * drew. block_compass's FilterLine implements the same contract on its own; nothing is shared.
 *
 * Who owns what. The mount node is the server's line itself (dim-line), which the listing's
 * mountLine() names for core's react_autoinit, so the line keeps its place, its classes and the sibling
 * rules the stylesheet writes against it. A mount replaces its markup, so the first render draws the
 * markup the server drew, read off it ({@see ./model read()}), with the same names inside the same GET
 * form - the no-JavaScript submits, the listing's form reading and every Behat step keep working. React
 * draws the line's own controls: the scope, the result count, the star, the three summaries (Filters and its
 * badge, the date button and its dot, Organise), the View menu, the search with its button and its eraser,
 * the close button and the listbox, in the cluster and the two capsules the template draws them in. Four
 * frames it draws hold server markup it adopts whole on the first commit and never diffs inside: the
 * Filters panel (More statuses, the custom fields), the date layer (the quick dates, the period's calendar,
 * its foot), the Organise menu and the managers' menu. The listing's painters keep painting those four, and paint
 * nothing React draws: they tell the line instead, through events on it ({@see EVENTS}), which
 * js/esm/src/listing/events.ts spells:
 *
 * - `dim-line:state`, detail {query, chip, fav, view} or any part of it: the listing's own state, after a
 *   press it handled, Back, a clear or a removed chip;
 * - `dim-line:answer`, detail {total, scope, favourites, filtercount, filtercountlabel, filtergroups, period}: an
 *   answer's numbers, whether the Filters panel still holds a group, and its period chip's sentence;
 * - `dim-line:dropterm`, sent by the line before it presses the control a suggestion names, so the one
 *   request that control causes carries no term;
 * - `dim-line:mounted`, sent once the line is drawn, after it marked itself drawn (data-line-drawn), which
 *   is what the painters read;
 * - `dim-line:failed`, sent instead where the markup is not a line this component can draw, so the listing
 *   releases what it holds for the mount: the line stays the server's.
 *
 * A second mount draws the line again. Core's react_autoinit unmounts a mounted node that leaves the document
 * and mounts it when it comes back, so a line moved after the hand-over - or under an ancestor that moved -
 * is mounted a second time onto a node React has just emptied. Unmounting therefore gives the adopted frames'
 * nodes back to the server's frames and keeps them, with the line's last state, for that node
 * ({@see KEPT}); a later mount draws from them instead of reading markup that is no longer there.
 *
 * The four layers of the capsule - the Filters panel, the date layer, the Organise menu and the View menu - are
 * native disclosures, and opening one closes the others. The Filters panel and the date layer are popovers under
 * the line's end on one line and bottom sheets at the two-line step; Escape closes the layer it is pressed in and
 * gives its summary the focus, and a press outside an open one closes it. The date layer also closes on its own
 * "Show". The two menus (menu.md) keep one Tab stop, their chosen item; an arrow key on the button opens one on
 * it, the arrows, Home and End move through its items, Tab leaves it closed, and choosing an item closes it and
 * gives its button the focus.
 *
 * The search is its button at rest at every width (the server drew the field open, for a page without this
 * component). A press, or the slash key, opens the field: in place where the fold leaves room for it beside the
 * controls, over the line where it does not. It stays open while it holds the focus, and in place while it holds
 * a text; Escape, or the focus leaving it empty, folds it back to its button, and the eraser empties it and keeps
 * it open. Escape also puts the line's tooltips off until the pointer or the focus comes to another control.
 *
 * The step is measured once per content on the line's own width with every scope cell drawn
 * ({@see ./measure stepWidths()}), and taken again from a width alone on every ResizeObserver entry. The
 * first measure is the observer's first entry's, after the browser's own layout and before the paint, so the
 * line is folded before it is painted and the commit forces no layout. Every request is the listing's:
 * nothing here calls a web service, reads the session or composes a word.
 *
 * @module     theme_dimensions/filterline/FilterLine
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Fragment as Fragment2, useLayoutEffect, useReducer, useRef, useState } from "react";
import { flushSync } from "react-dom";
import Pending from "@moodle/lms/core/pending";
import { normalise, pieces } from "./mark";
import { importPreview } from "./lazy";
import { contentWidth, fades, itemOf, place, placeList, reveal, shown, stepWidths } from "./measure";
import { SELECTORS, TIP, answered, read, stated } from "./model";
import { MIN_TERM, offers, remember, suggest } from "./suggest";
import { CLASSES as STEP_CLASSES, TWO_LINES, choose, morphs, paint, same, settled } from "./steps";
import { LINE, LINE_DRAWN } from "../listing/events";
const EVENTS = LINE;
const DRAWN = LINE_DRAWN;
const TAB_INTENT = 1e3;
const HOOKS = {
  listing: '[data-region="dim-vitrine"]',
  chips: '[data-region="dim-line-chips"]',
  show: '[data-action="dim-filters-show"]',
  periodshow: '[data-action="dim-period-show"]',
  option: '[role="option"]',
  // A menu of the capsule (Organise, View) and its items (menu.md).
  menu: '[role="menu"]',
  menuitem: '[role="menuitemradio"]',
  // The controls of the open search that keep it open when the focus moves to them.
  searchpart: '[data-action="dim-search-clear"], [data-action="dim-search-close"]',
  tip: `[${TIP}]`
};
const TIPSOFF = "dim-line--tipsoff";
const SHEET = "dim-toolbar--sheet";
const ACTIVE = "dim-line__option--active";
const KEPT = /* @__PURE__ */ new WeakMap();
const still = /* @__PURE__ */ __name(() => window.matchMedia("(prefers-reduced-motion: reduce)").matches, "still");
const adopt = /* @__PURE__ */ __name((source, target) => {
  if (source && target && source !== target) {
    target.append(...Array.from(source.childNodes));
  }
}, "adopt");
const counterpart = /* @__PURE__ */ __name((line, was) => {
  if (was.id) {
    const found = line.querySelector(`#${CSS.escape(was.id)}`);
    if (found) {
      return found;
    }
  }
  const action = was.getAttribute("data-action");
  if (action) {
    const value = was.getAttribute("value");
    const selector = `[data-action="${CSS.escape(action)}"]` + (value === null ? "" : `[value="${CSS.escape(value)}"]`);
    return line.querySelector(selector);
  }
  const summary = was.closest("details");
  const region = summary ? summary.getAttribute("data-region") : null;
  return region ? line.querySelector(`[data-region="${region}"] > summary`) : null;
}, "counterpart");
const FilterLine = /* @__PURE__ */ __name(({ form }) => {
  const [setup] = useState(() => {
    const toolbar2 = document.getElementById(form);
    const line2 = toolbar2 ? toolbar2.querySelector(SELECTORS.line) : null;
    const active = document.activeElement;
    const kept = line2 ? KEPT.get(line2) : void 0;
    if (line2 && kept) {
      KEPT.delete(line2);
      return {
        toolbar: toolbar2,
        line: line2,
        first: kept.model,
        panel: kept.panel,
        layer: kept.layer,
        menu: kept.menu,
        manage: kept.manage,
        focused: null
      };
    }
    return {
      toolbar: toolbar2,
      line: line2,
      first: read(line2),
      panel: line2 ? line2.querySelector(SELECTORS.panel) : null,
      layer: line2 ? line2.querySelector(SELECTORS.datelayer) : null,
      menu: line2 ? line2.querySelector(SELECTORS.menu) : null,
      manage: line2 ? line2.querySelector(SELECTORS.manage) : null,
      focused: line2 && active && line2.contains(active) ? active : null
    };
  });
  const [model, setModel] = useState(setup.first);
  const latest = useRef(model);
  latest.current = model;
  const [, rerender] = useReducer((count) => count + 1, 0);
  const [answers, setAnswers] = useState(0);
  const [relist, setRelist] = useState(0);
  const live = useRef({
    fold: null,
    widths: [],
    width: -1,
    searching: false,
    open: false,
    items: [],
    active: -1,
    from: null,
    quiet: false,
    intent: false,
    tabat: 0,
    refocus: null,
    measuredOnce: false,
    veiled: null,
    tiphost: null
  });
  const rest = useRef(/* @__PURE__ */ new WeakMap());
  const preview = useRef(null);
  const previewing = useRef(null);
  const field = useRef(null);
  const opener = useRef(null);
  const closer = useRef(null);
  const searchcap = useRef(null);
  const scope = useRef(null);
  const filters = useRef(null);
  const panel = useRef(null);
  const period = useRef(null);
  const layer = useRef(null);
  const organise = useRef(null);
  const menu = useRef(null);
  const viewmenu = useRef(null);
  const viewlayer = useRef(null);
  const manage = useRef(null);
  const listbox = useRef(null);
  const placed = useRef("");
  const drawn = useRef(false);
  const api = useRef({});
  const line = setup.line;
  const toolbar = setup.toolbar;
  const st = live.current;
  const twolines = /* @__PURE__ */ __name(() => st.fold !== null && st.fold.step === TWO_LINES, "twolines");
  const sheeted = /* @__PURE__ */ __name(() => twolines() && Boolean(filters.current && filters.current.open || period.current && period.current.open), "sheeted");
  const quietly = /* @__PURE__ */ __name((element) => {
    if (!(element instanceof HTMLElement)) {
      return;
    }
    st.quiet = true;
    try {
      element.focus({ preventScroll: true });
    } finally {
      st.quiet = false;
    }
  }, "quietly");
  const loadPreview = /* @__PURE__ */ __name(() => {
    if (previewing.current === null) {
      const pending = new Pending("theme_dimensions/filterline:preview");
      previewing.current = importPreview().then((module) => {
        const listing = toolbar ? toolbar.closest(HOOKS.listing) : null;
        if (toolbar && listing) {
          module.arm(listing, toolbar, { controlOf: controlOfOption });
        }
        preview.current = module;
        return module;
      }).finally(() => pending.resolve());
    }
    return previewing.current;
  }, "loadPreview");
  const controlOfOption = /* @__PURE__ */ __name((option) => {
    const item = live.current.items[Number(option.dataset.index)];
    return item ? item.control : null;
  }, "controlOfOption");
  const previewShow = /* @__PURE__ */ __name((control) => {
    if (preview.current) {
      preview.current.show(control);
    } else if (control) {
      loadPreview().then((module) => {
        const active = live.current;
        module.show(active.open && active.active >= 0 ? active.items[active.active].control : null);
        return module;
      }).catch(() => null);
    }
  }, "previewShow");
  const previewClear = /* @__PURE__ */ __name(() => {
    preview.current?.clear();
  }, "previewClear");
  const relistNow = /* @__PURE__ */ __name(() => {
    if (!st.open || !toolbar || !field.current) {
      return;
    }
    if (st.active >= 0) {
      previewClear();
    }
    st.items = suggest(offers(toolbar, rest.current), field.current.value);
    st.active = -1;
    rerender();
  }, "relistNow");
  const closeList = /* @__PURE__ */ __name(() => {
    if (st.active >= 0) {
      previewClear();
    }
    st.open = false;
    st.items = [];
    st.active = -1;
    rerender();
  }, "closeList");
  const inSearch = /* @__PURE__ */ __name((element) => element !== null && (searchcap.current !== null && searchcap.current.contains(element) || element === closer.current), "inSearch");
  const keepsOpen = /* @__PURE__ */ __name(() => !morphs(st.fold) && field.current !== null && field.current.value !== "", "keepsOpen");
  const drawSearch = /* @__PURE__ */ __name(() => {
    const over = morphs(st.fold);
    line?.classList.toggle(STEP_CLASSES.open, st.searching && !over);
    line?.classList.toggle(STEP_CLASSES.searching, st.searching && over);
  }, "drawSearch");
  const setSearching = /* @__PURE__ */ __name((on) => {
    st.searching = on;
    if (on) {
      const toggle = manage.current?.querySelector('[aria-expanded="true"]');
      toggle?.click();
    }
    drawSearch();
    rerender();
  }, "setSearching");
  const settleSearch = /* @__PURE__ */ __name(() => {
    if (!inSearch(document.activeElement)) {
      const keep = keepsOpen();
      if (st.searching !== keep) {
        st.searching = keep;
        st.from = null;
      }
    }
    drawSearch();
  }, "settleSearch");
  const openSearch = /* @__PURE__ */ __name((from) => {
    if (!st.searching) {
      st.from = from || opener.current;
      setSearching(true);
    }
    st.open = true;
    if (document.activeElement !== field.current) {
      quietly(field.current);
    }
    relistNow();
  }, "openSearch");
  const closeSearch = /* @__PURE__ */ __name((back) => {
    closeList();
    if (!st.searching) {
      return;
    }
    setSearching(false);
    const from = st.from;
    st.from = null;
    if (back) {
      quietly([from, field.current, opener.current].find((control) => shown(control)) || null);
    }
  }, "closeSearch");
  const tipsOff = /* @__PURE__ */ __name(() => {
    if (!line) {
      return;
    }
    const active = document.activeElement;
    const focused = active && line.contains(active) ? active.closest(HOOKS.tip) : null;
    st.tiphost = focused || line.querySelector(`${HOOKS.tip}:hover`);
    line.classList.add(TIPSOFF);
  }, "tipsOff");
  const tipsBack = /* @__PURE__ */ __name((event) => {
    if (!line || !line.classList.contains(TIPSOFF) || !(event.target instanceof Element)) {
      return;
    }
    const host = event.target.closest(HOOKS.tip);
    if (host && host !== st.tiphost) {
      line.classList.remove(TIPSOFF);
      st.tiphost = null;
    }
  }, "tipsBack");
  const activate = /* @__PURE__ */ __name((index) => {
    st.active = index >= 0 && index < st.items.length ? index : -1;
    rerender();
    previewShow(st.active >= 0 ? st.items[st.active].control : null);
  }, "activate");
  const pick = /* @__PURE__ */ __name((index) => {
    const item = st.items[index];
    if (!item || !item.control.isConnected || !line) {
      return;
    }
    setModel((current) => current ? stated(current, { query: "" }) : current);
    if (field.current) {
      field.current.value = "";
    }
    line.dispatchEvent(new CustomEvent(EVENTS.dropterm, { bubbles: true }));
    item.control.click();
    setRelist((count) => count + 1);
  }, "pick");
  const refold = /* @__PURE__ */ __name(() => {
    if (!line) {
      return;
    }
    const next = choose(st.widths, st.width);
    if (same(next, st.fold)) {
      return;
    }
    const scopeel = scope.current;
    st.refocus = scopeel && scopeel.contains(document.activeElement) ? document.activeElement : null;
    st.fold = next;
    settleSearch();
    rerender();
  }, "refold");
  const remeasure = /* @__PURE__ */ __name(() => {
    if (!line || !line.isConnected) {
      return;
    }
    st.width = contentWidth(line);
    st.widths = stepWidths(line, st.fold, st.width);
    refold();
  }, "remeasure");
  const unveil = /* @__PURE__ */ __name(() => {
    if (line && st.veiled !== null) {
      line.style.visibility = st.veiled;
      st.veiled = null;
    }
  }, "unveil");
  const measureFirst = /* @__PURE__ */ __name((width) => {
    if (!line) {
      return;
    }
    st.measuredOnce = true;
    try {
      st.width = width === void 0 ? contentWidth(line) : width;
      st.widths = stepWidths(line, st.fold, st.width);
      refold();
    } catch (error) {
      st.widths = [];
    }
    unveil();
    rerender();
  }, "measureFirst");
  const onFieldKey = /* @__PURE__ */ __name((event) => {
    const count = st.items.length;
    if (event.key === "ArrowDown") {
      event.preventDefault();
      if (!st.open) {
        openSearch(st.from || field.current);
        return;
      }
      activate(count ? (st.active + 1) % count : -1);
    } else if (event.key === "ArrowUp" && st.open) {
      event.preventDefault();
      activate(st.active <= 0 ? -1 : st.active - 1);
    } else if ((event.key === "Enter" || event.key === " ") && st.active >= 0) {
      event.preventDefault();
      pick(st.active);
    } else if (event.key === "Escape" && (st.open || st.searching)) {
      event.preventDefault();
      closeSearch(true);
    }
  }, "onFieldKey");
  const onFieldFocus = /* @__PURE__ */ __name(() => {
    const intent = st.intent || Date.now() - st.tabat < TAB_INTENT;
    st.intent = false;
    if (st.quiet || !intent) {
      return;
    }
    openSearch(field.current);
  }, "onFieldFocus");
  const onFieldInput = /* @__PURE__ */ __name((value) => {
    setModel((current) => current ? stated(current, { query: value }) : current);
    if (st.open) {
      relistNow();
    } else {
      openSearch(field.current);
    }
  }, "onFieldInput");
  const onErase = /* @__PURE__ */ __name(() => {
    setModel((current) => current ? stated(current, { query: "" }) : current);
    if (field.current) {
      field.current.value = "";
    }
    quietly(field.current);
    if (st.open) {
      relistNow();
    }
  }, "onErase");
  const onCell = /* @__PURE__ */ __name((value) => {
    setModel((current) => current ? stated(current, { chip: value }) : current);
  }, "onCell");
  const onLineKey = /* @__PURE__ */ __name((event) => {
    if (event.defaultPrevented || !(event.target instanceof Element) || !line) {
      return;
    }
    if (event.key !== "Escape") {
      onMenuKey(event, event.target);
      return;
    }
    if (st.searching && event.target !== field.current) {
      event.preventDefault();
      closeSearch(true);
      return;
    }
    const details = event.target.closest("details[open]");
    if (details && line.contains(details)) {
      event.preventDefault();
      details.open = false;
      quietly(details.querySelector("summary"));
    }
  }, "onLineKey");
  const itemsOf = /* @__PURE__ */ __name((menu2) => menu2 ? Array.from(menu2.querySelectorAll(HOOKS.menuitem)) : [], "itemsOf");
  const rove = /* @__PURE__ */ __name((menu2, current) => {
    const items = itemsOf(menu2);
    const stop = current && items.find((item) => item === current) || items.find((item) => item.getAttribute("aria-checked") === "true") || items[0] || null;
    items.forEach((item) => {
      item.tabIndex = item === stop ? 0 : -1;
    });
    return stop;
  }, "rove");
  const onMenuKey = /* @__PURE__ */ __name((event, target) => {
    const details = target.closest("details");
    const layer2 = details ? details.querySelector(`:scope > ${HOOKS.menu}`) : null;
    if (!details || !layer2 || !line || !line.contains(details)) {
      return;
    }
    const moves = ["ArrowDown", "ArrowUp", "Home", "End"];
    if (target.tagName === "SUMMARY" && target.parentElement === details) {
      if (event.key !== "ArrowDown" && event.key !== "ArrowUp") {
        return;
      }
      event.preventDefault();
      details.open = true;
      rove(layer2, null)?.focus({ preventScroll: true });
      return;
    }
    const item = target.closest(HOOKS.menuitem);
    if (!item || !layer2.contains(item)) {
      return;
    }
    if (event.key === "Tab") {
      details.open = false;
      return;
    }
    if (moves.indexOf(event.key) < 0) {
      return;
    }
    event.preventDefault();
    const items = itemsOf(layer2);
    const at = items.indexOf(item);
    let next = at;
    if (event.key === "Home") {
      next = 0;
    } else if (event.key === "End") {
      next = items.length - 1;
    } else {
      next = (at + (event.key === "ArrowDown" ? 1 : -1) + items.length) % items.length;
    }
    rove(layer2, items[next])?.focus({ preventScroll: true });
  }, "onMenuKey");
  const onLineBlur = /* @__PURE__ */ __name((event) => {
    const next = event.relatedTarget instanceof Element ? event.relatedTarget : null;
    if (!line || next === field.current || next && listbox.current && listbox.current.contains(next)) {
      return;
    }
    closeList();
    if (next && line.contains(next) && next.closest(HOOKS.searchpart)) {
      return;
    }
    if (!keepsOpen()) {
      closeSearch(false);
    }
  }, "onLineBlur");
  const onToggle = /* @__PURE__ */ __name((event) => {
    if (!line) {
      return;
    }
    const layers = [filters.current, period.current, organise.current, viewmenu.current];
    const opened = layers.find((one) => one !== null && one === event.target && one.open);
    if (opened) {
      layers.forEach((one) => {
        if (one && one !== opened && one.open) {
          one.open = false;
        }
      });
    }
    toolbar?.classList.toggle(SHEET, sheeted());
    if (event.target === filters.current) {
      place(line, panel.current, twolines(), twolines());
    } else if (event.target === period.current) {
      place(line, layer.current, twolines(), twolines());
    } else if (event.target === organise.current) {
      place(line, menu.current, twolines(), false);
      rove(menu.current, null);
    } else if (event.target === viewmenu.current) {
      place(line, viewlayer.current, twolines(), false);
      rove(viewlayer.current, null);
    }
  }, "onToggle");
  const onLineClick = /* @__PURE__ */ __name((event) => {
    const target = event.target instanceof Element ? event.target : null;
    let details = null;
    const item = target ? target.closest(HOOKS.menuitem) : null;
    if (target && target.closest(HOOKS.show)) {
      details = filters.current;
    } else if (target && target.closest(HOOKS.periodshow)) {
      details = period.current;
    } else if (item) {
      details = [organise.current, viewmenu.current].find((one) => one !== null && one.contains(item)) || null;
    }
    if (details) {
      details.open = false;
      quietly(details.querySelector("summary"));
    }
  }, "onLineClick");
  const onDocumentPress = /* @__PURE__ */ __name((event) => {
    const target = event.target instanceof Node ? event.target : null;
    if (!target || !line) {
      return;
    }
    if ((st.open || st.searching) && !line.contains(target)) {
      closeList();
      if (!keepsOpen()) {
        closeSearch(false);
      }
    }
    [filters.current, period.current, organise.current, viewmenu.current].forEach((layer2) => {
      if (layer2 && layer2.open && !layer2.contains(target)) {
        layer2.open = false;
      }
    });
  }, "onDocumentPress");
  const onDocumentKey = /* @__PURE__ */ __name((event) => {
    if (event.key === "Tab") {
      st.tabat = Date.now();
      return;
    }
    if (event.key === "Escape") {
      tipsOff();
      return;
    }
    if (event.key !== "/" || event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented || !line) {
      return;
    }
    const active = document.activeElement;
    if (active instanceof HTMLElement && (active.isContentEditable || active.closest("input, textarea, select"))) {
      return;
    }
    const rect = line.getBoundingClientRect();
    if (rect.bottom <= 0 || rect.top >= window.innerHeight || !rect.width) {
      return;
    }
    event.preventDefault();
    openSearch(st.searching ? field.current : opener.current);
  }, "onDocumentKey");
  const onPreviewIntent = /* @__PURE__ */ __name((event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target || preview.current) {
      return;
    }
    loadPreview().then(() => {
      if (event.type === "pointerover" && target.matches(":hover")) {
        const pointer = event;
        target.dispatchEvent(new PointerEvent("pointerover", {
          bubbles: true,
          pointerType: pointer.pointerType,
          isPrimary: pointer.isPrimary
        }));
      } else if (event.type === "focusin" && document.activeElement === target) {
        target.dispatchEvent(new FocusEvent("focusin", { bubbles: true }));
      }
      return null;
    }).catch(() => null);
  }, "onPreviewIntent");
  const onState = /* @__PURE__ */ __name((event) => {
    const told = event.detail || {};
    setModel((current) => current ? stated(current, told) : current);
  }, "onState");
  const onAnswer = /* @__PURE__ */ __name((event) => {
    const answer = event.detail || {};
    setModel((current) => current ? answered(current, answer) : current);
    if (answer.filtergroups === false && filters.current) {
      filters.current.open = false;
    }
    setAnswers((count) => count + 1);
  }, "onAnswer");
  const onFormChange = /* @__PURE__ */ __name((event) => {
    const input = event.target;
    const current = model;
    if (!(input instanceof HTMLInputElement) || input.type !== "radio" || !current || !current.scope || input.name !== current.scope.name || !toolbar) {
      return;
    }
    const checked = toolbar.querySelector(`input[type="radio"][name="${input.name}"]:checked`);
    setModel((now) => now ? stated(now, { chip: checked ? checked.value : null }) : now);
  }, "onFormChange");
  api.current = {
    onLineKey,
    onLineBlur,
    onToggle,
    onLineClick,
    onDocumentPress,
    onDocumentKey,
    onPreviewIntent,
    onState,
    onAnswer,
    onFormChange,
    remeasure,
    measureFirst,
    tipsBack
  };
  useLayoutEffect(() => {
    if (!line || !toolbar || !setup.first) {
      line?.dispatchEvent(new CustomEvent(EVENTS.failed, { bubbles: true }));
      return void 0;
    }
    adopt(setup.panel, panel.current);
    adopt(setup.layer, layer.current);
    adopt(setup.menu, menu.current);
    adopt(setup.manage, manage.current);
    line.setAttribute(DRAWN, "true");
    const to = /* @__PURE__ */ __name((name) => (event) => api.current[name](event), "to");
    const listeners = [
      [line, "keydown", to("onLineKey"), false],
      [line, "focusout", to("onLineBlur"), false],
      [line, "toggle", to("onToggle"), true],
      [line, "click", to("onLineClick"), false],
      [line, EVENTS.state, to("onState"), false],
      [line, EVENTS.answer, to("onAnswer"), false],
      [line, "pointerover", to("tipsBack"), false],
      [line, "focusin", to("tipsBack"), false],
      [toolbar, "change", to("onFormChange"), false],
      [toolbar, "pointerover", to("onPreviewIntent"), true],
      [toolbar, "focusin", to("onPreviewIntent"), true],
      [document, "pointerdown", to("onDocumentPress"), false],
      [document, "keydown", to("onDocumentKey"), false]
    ];
    const rows = [scope.current, toolbar.querySelector(HOOKS.chips)].filter(
      (row) => row !== null
    );
    rows.forEach((row) => {
      listeners.push([row, "scroll", () => fades(row), { passive: true }]);
      listeners.push([row, "focusin", (event) => reveal(row, itemOf(toolbar, event.target), still()), false]);
      listeners.push([row, "click", (event) => reveal(row, itemOf(toolbar, event.target), still()), false]);
    });
    listeners.forEach(([target, type, listener, options]) => target.addEventListener(type, listener, options));
    remember(toolbar, field.current ? field.current.value : "", rest.current);
    if (typeof ResizeObserver === "undefined") {
      measureFirst();
    } else if (!setup.focused && !line.contains(document.activeElement)) {
      st.veiled = line.style.visibility;
      line.style.visibility = "hidden";
    }
    const observer = typeof ResizeObserver === "undefined" ? null : new ResizeObserver((entries) => {
      const width = entries[0].contentRect.width;
      if (!live.current.measuredOnce) {
        flushSync(() => api.current.measureFirst(width));
        return;
      }
      if (Math.round(width) === Math.round(live.current.width)) {
        return;
      }
      live.current.width = width;
      flushSync(() => api.current.remeasureWidth());
    });
    observer?.observe(line);
    document.fonts?.ready.then(() => {
      if (line.isConnected && live.current.measuredOnce) {
        flushSync(() => api.current.remeasure());
      }
      return null;
    }).catch(() => null);
    drawn.current = true;
    if (setup.focused) {
      quietly(setup.focused.isConnected ? setup.focused : counterpart(line, setup.focused));
    }
    line.dispatchEvent(new CustomEvent(EVENTS.mounted, { bubbles: true }));
    return () => {
      observer?.disconnect();
      unveil();
      listeners.forEach(([target, type, listener, options]) => target.removeEventListener(type, listener, options));
      line.removeAttribute(DRAWN);
      line.classList.remove(STEP_CLASSES.open, STEP_CLASSES.searching, TIPSOFF);
      adopt(panel.current, setup.panel);
      adopt(layer.current, setup.layer);
      adopt(menu.current, setup.menu);
      adopt(manage.current, setup.manage);
      if (latest.current) {
        KEPT.set(line, {
          model: latest.current,
          panel: setup.panel,
          layer: setup.layer,
          menu: setup.menu,
          manage: setup.manage
        });
      }
    };
  }, []);
  api.current.remeasureWidth = (() => settled(st.widths, st.width) ? refold() : remeasure());
  const signature = model ? JSON.stringify([
    model.scope ? model.scope.cells.map((cell) => [cell.label, String(cell.count).length, cell.hascount]) : null,
    model.total.length,
    [model.filters.hascount, String(model.filters.count).length]
  ]) : "";
  const measured = useRef(signature);
  useLayoutEffect(() => {
    if (!drawn.current || measured.current === signature) {
      return;
    }
    measured.current = signature;
    if (st.measuredOnce) {
      remeasure();
    }
  }, [signature]);
  useLayoutEffect(() => {
    if (drawn.current && st.measuredOnce) {
      settleSearch();
      rerender();
    }
  }, [model ? model.search.value : ""]);
  useLayoutEffect(() => {
    if (!drawn.current || !toolbar || answers === 0) {
      return;
    }
    remember(toolbar, field.current ? field.current.value : "", rest.current);
    relistNow();
    preview.current?.refresh();
  }, [answers]);
  useLayoutEffect(() => {
    if (drawn.current && relist > 0) {
      relistNow();
    }
  }, [relist]);
  useLayoutEffect(() => {
    if (!line || !toolbar) {
      return;
    }
    paint(line, st.fold);
    drawSearch();
    toolbar.classList.toggle(SHEET, sheeted());
    if (st.refocus) {
      const refocus = st.refocus;
      st.refocus = null;
      if (refocus.isConnected && document.activeElement !== refocus) {
        quietly(refocus);
      }
    }
    if (listbox.current && !listbox.current.hidden) {
      const fold = st.fold ? `${st.fold.step}${st.fold.over ? "o" : ""}` : "";
      const key = [st.measuredOnce, fold, st.searching, Math.round(st.width)].join("|");
      if (placed.current !== key) {
        placed.current = key;
        placeList(line, listbox.current, !morphs(st.fold), twolines());
      }
      const option = st.active >= 0 ? listbox.current.querySelectorAll(HOOKS.option)[st.active] : null;
      option?.scrollIntoView?.({ block: "nearest" });
    } else {
      placed.current = "";
    }
  });
  const foldkey = (st.measuredOnce ? "m" : "") + (st.fold ? `${st.fold.step}${st.fold.over ? "o" : ""}` : "");
  useLayoutEffect(() => {
    if (!line || !drawn.current || !st.measuredOnce) {
      return;
    }
    place(line, panel.current, twolines(), twolines());
    place(line, layer.current, twolines(), twolines());
    place(line, menu.current, twolines(), false);
    place(line, viewlayer.current, twolines(), false);
    fades(scope.current);
    fades(toolbar ? toolbar.querySelector(HOOKS.chips) : null);
  }, [foldkey]);
  if (!model || !line) {
    return null;
  }
  const listed = st.open && st.items.length > 0;
  const scopenode = drawScope(model, scope, onCell);
  const scopedown = twolines();
  const search = model.search;
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    scopedown ? null : scopenode,
    /* @__PURE__ */ jsxDEV("span", { className: "dim-line__spacer", "aria-hidden": "true" }, "spacer", false, {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 1312,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "dim-line__cluster", "data-region": "dim-line-cluster", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "dim-line__total", "data-region": "dim-line-total", "aria-hidden": "true", children: model.total }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1314,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "dim-line__capsule", "data-region": "dim-line-capsule", children: [
        drawStar(model),
        drawFilters(model, filters, panel),
        drawDate(model, period, layer),
        /* @__PURE__ */ jsxDEV("details", { ref: organise, className: "dim-line__organise", "data-region": "dim-organise", children: [
          /* @__PURE__ */ jsxDEV("summary", { className: "dim-line__iconbtn dim-line__organisetoggle", "data-dim-tip": model.organiselabel, children: [
            /* @__PURE__ */ jsxDEV("i", { className: "fa fa-arrow-down-wide-short", "aria-hidden": "true" }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
              lineNumber: 1321,
              columnNumber: 29
            }),
            /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: model.organiselabel }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
              lineNumber: 1322,
              columnNumber: 29
            })
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1320,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV(
            "div",
            {
              ref: menu,
              className: "dim-line__menu dim-menu",
              role: "menu",
              "aria-label": model.organiselabel,
              "data-region": "dim-organise-menu"
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
              lineNumber: 1324,
              columnNumber: 25
            }
          )
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1319,
          columnNumber: 21
        }),
        drawView(model, viewmenu, viewlayer)
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1315,
        columnNumber: 17
      })
    ] }, "cluster", true, {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 1313,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        ref: searchcap,
        className: "dim-line__capsule dim-line__searchcap",
        "data-region": "dim-line-searchcap",
        children: [
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              ref: opener,
              type: "button",
              className: "dim-line__iconbtn dim-line__searchopen",
              "data-action": "dim-search-open",
              "aria-controls": search.id,
              "aria-expanded": st.searching ? "true" : "false",
              "data-dim-tip": model.openlabel,
              onClick: () => openSearch(opener.current),
              children: [
                /* @__PURE__ */ jsxDEV("i", { className: "fa fa-magnifying-glass", "aria-hidden": "true" }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                  lineNumber: 1351,
                  columnNumber: 21
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: model.openlabel }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                  lineNumber: 1352,
                  columnNumber: 21
                })
              ]
            },
            void 0,
            true,
            {
              fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
              lineNumber: 1341,
              columnNumber: 17
            }
          ),
          /* @__PURE__ */ jsxDEV("div", { className: "dim-toolbar__search dim-line__search", "data-region": "dim-line-search", children: [
            /* @__PURE__ */ jsxDEV("label", { className: "visually-hidden", htmlFor: search.id, children: search.label }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
              lineNumber: 1355,
              columnNumber: 21
            }),
            /* @__PURE__ */ jsxDEV("i", { className: "fa fa-magnifying-glass dim-toolbar__searchicon", "aria-hidden": "true" }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
              lineNumber: 1356,
              columnNumber: 21
            }),
            /* @__PURE__ */ jsxDEV(
              "input",
              {
                ref: field,
                className: "form-control dim-toolbar__input",
                type: "search",
                id: search.id,
                name: search.name,
                value: search.value,
                placeholder: search.placeholder,
                autoComplete: "off",
                role: "combobox",
                "aria-autocomplete": "list",
                "aria-expanded": listed ? "true" : "false",
                "aria-controls": model.suggest.id,
                "aria-activedescendant": listed && st.active >= 0 ? `${model.suggest.id}-${st.active}` : void 0,
                onPointerDown: () => {
                  st.intent = true;
                },
                onFocus: onFieldFocus,
                onClick: () => {
                  if (!st.open) {
                    openSearch(st.from || opener.current);
                  }
                },
                onChange: (event) => onFieldInput(event.target.value),
                onKeyDown: onFieldKey
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1357,
                columnNumber: 21
              }
            ),
            /* @__PURE__ */ jsxDEV(
              "button",
              {
                type: "button",
                className: "dim-line__eraser dim-live-only",
                "data-action": "dim-search-clear",
                "aria-label": search.clearlabel,
                "data-dim-tip": search.clearlabel,
                hidden: search.value === "",
                onClick: onErase,
                children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-eraser", "aria-hidden": "true" }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                  lineNumber: 1391,
                  columnNumber: 22
                })
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1383,
                columnNumber: 21
              }
            )
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1354,
            columnNumber: 17
          }),
          model.manage ? /* @__PURE__ */ jsxDEV("div", { ref: manage, className: "dim-toolbar__manage", "data-region": "dim-line-manage" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1394,
            columnNumber: 21
          }) : null,
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              ref: closer,
              type: "button",
              className: "dim-line__iconbtn dim-line__searchclose",
              "data-action": "dim-search-close",
              "aria-label": model.closelabel,
              "data-dim-tip": model.closelabel,
              onClick: () => closeSearch(true),
              children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-xmark", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1404,
                columnNumber: 18
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
              lineNumber: 1396,
              columnNumber: 17
            }
          )
        ]
      },
      "searchcap",
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1335,
        columnNumber: 13
      }
    ),
    scopedown ? scopenode : null,
    drawList(model, listbox, listed ? st.items : [], st.active, pick)
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
    lineNumber: 1310,
    columnNumber: 9
  });
}, "FilterLine");
const drawScope = /* @__PURE__ */ __name((model, ref, onCell) => {
  const scope = model.scope;
  if (!scope) {
    return null;
  }
  return /* @__PURE__ */ jsxDEV(
    "fieldset",
    {
      ref,
      className: "dim-toolbar__group dim-line__scope",
      "data-region": "dim-vitrine-group",
      "data-name": scope.name,
      "data-role": "scope",
      children: [
        /* @__PURE__ */ jsxDEV("legend", { className: "visually-hidden", children: scope.legend }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1435,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "dim-line__cells", "data-region": "dim-pill-track", children: scope.cells.map((cell) => /* @__PURE__ */ jsxDEV(Fragment2, { children: [
          /* @__PURE__ */ jsxDEV(
            "input",
            {
              className: "dim-pill__input visually-hidden",
              type: "radio",
              name: scope.name,
              id: cell.id,
              value: cell.value,
              checked: cell.checked,
              hidden: cell.absent,
              onChange: () => onCell(cell.value)
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
              lineNumber: 1439,
              columnNumber: 25
            }
          ),
          /* @__PURE__ */ jsxDEV("label", { className: "dim-pill", htmlFor: cell.id, hidden: cell.absent, children: [
            `${cell.label} `,
            /* @__PURE__ */ jsxDEV("span", { className: "dim-pill__count", "data-region": "dim-pill-count", hidden: !cell.hascount, children: cell.count }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
              lineNumber: 1451,
              columnNumber: 29
            })
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1449,
            columnNumber: 25
          })
        ] }, cell.value, true, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1438,
          columnNumber: 21
        })) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1436,
          columnNumber: 13
        })
      ]
    },
    "scope",
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 1427,
      columnNumber: 9
    }
  );
}, "drawScope");
const drawStar = /* @__PURE__ */ __name((model) => {
  const star = model.star;
  if (!star) {
    return null;
  }
  return /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "submit",
      className: "dim-toolbar__star dim-line__iconbtn",
      name: star.name,
      value: star.pressed ? "0" : "1",
      "aria-pressed": star.pressed ? "true" : "false",
      "data-dim-tip": star.label,
      "data-action": "dim-fav",
      "data-region": "dim-line-star",
      children: [
        /* @__PURE__ */ jsxDEV("i", { className: `${star.pressed ? "fa-solid" : "fa-regular"} fa-star`, "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1485,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: [
          `${star.label} `,
          /* @__PURE__ */ jsxDEV("span", { "data-region": "dim-fav-count", children: star.count }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1486,
            columnNumber: 65
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1486,
          columnNumber: 13
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 1475,
      columnNumber: 9
    }
  );
}, "drawStar");
const drawFilters = /* @__PURE__ */ __name((model, ref, panel) => /* @__PURE__ */ jsxDEV(
  "details",
  {
    ref,
    className: "dim-toolbar__filters dim-line__filters",
    "data-region": "dim-filters",
    hidden: !model.filters.available,
    children: [
      /* @__PURE__ */ jsxDEV("summary", { className: "dim-toolbar__filterstoggle dim-line__iconbtn", "data-dim-tip": model.filters.label, children: [
        /* @__PURE__ */ jsxDEV("i", { className: "fa fa-sliders", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1513,
          columnNumber: 13
        }),
        " ",
        /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden dim-line__label dim-toolbar__filterslabel", children: model.filters.label }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1515,
          columnNumber: 13
        }),
        " ",
        /* @__PURE__ */ jsxDEV("span", { className: "dim-toolbar__filtercount", "data-region": "dim-filtercount", hidden: !model.filters.hascount, children: [
          /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", "data-region": "dim-filtercount-number", children: model.filters.count }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1518,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", "data-region": "dim-filtercount-label", children: model.filters.countlabel }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1519,
            columnNumber: 17
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1517,
          columnNumber: 13
        })
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1512,
        columnNumber: 9
      }),
      /* @__PURE__ */ jsxDEV("div", { ref: panel, className: "dim-toolbar__panel", "data-region": "dim-filters-panel" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1522,
        columnNumber: 9
      })
    ]
  },
  void 0,
  true,
  {
    fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
    lineNumber: 1506,
    columnNumber: 5
  }
), "drawFilters");
const drawDate = /* @__PURE__ */ __name((model, ref, layer) => {
  const date = model.date;
  if (!date) {
    return null;
  }
  return /* @__PURE__ */ jsxDEV("details", { ref, className: "dim-line__date", "data-region": "dim-period", children: [
    /* @__PURE__ */ jsxDEV(
      "summary",
      {
        className: "dim-line__iconbtn dim-line__datetoggle",
        "aria-label": date.name,
        "data-dim-tip": date.label,
        "data-dim-template": date.template,
        "data-region": "dim-period-button",
        children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-calendar-days", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1554,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { className: "dim-line__dot", "data-region": "dim-period-dot", "aria-hidden": "true", hidden: !date.set }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1555,
            columnNumber: 17
          })
        ]
      },
      void 0,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1547,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV("div", { ref: layer, className: "dim-datelayer", "data-region": "dim-period-layer" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 1557,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
    lineNumber: 1546,
    columnNumber: 9
  });
}, "drawDate");
const drawView = /* @__PURE__ */ __name((model, ref, layer) => {
  const current = model.views.find((view) => view.checked) || model.views[model.views.length - 1];
  return /* @__PURE__ */ jsxDEV("details", { ref, className: "dim-line__viewmenu", "data-region": "dim-view", children: [
    /* @__PURE__ */ jsxDEV("summary", { className: "dim-line__iconbtn dim-line__viewtoggle", "data-dim-tip": model.viewlabel, children: [
      /* @__PURE__ */ jsxDEV("i", { className: `fa ${current ? current.icon : ""}`, "aria-hidden": "true", "data-region": "dim-view-icon" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1577,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-down dim-line__chevron", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1578,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: model.viewlabel }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1579,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 1576,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        ref: layer,
        className: "dim-line__menu dim-menu",
        role: "menu",
        "aria-label": model.viewlabel,
        "data-region": "dim-view-menu",
        children: [
          /* @__PURE__ */ jsxDEV("div", { className: "dim-menu__head", id: model.viewheadid, children: model.viewheading }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1588,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("div", { role: "group", "aria-labelledby": model.viewheadid, children: model.views.map((view) => /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "submit",
              className: "dim-menu__item",
              role: "menuitemradio",
              "data-action": "dim-view",
              name: model.viewname,
              value: view.value,
              "aria-checked": view.checked ? "true" : "false",
              children: [
                /* @__PURE__ */ jsxDEV("i", { className: `fa ${view.icon} dim-menu__icon`, "aria-hidden": "true" }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                  lineNumber: 1601,
                  columnNumber: 29
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "dim-menu__label", children: view.label }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                  lineNumber: 1602,
                  columnNumber: 29
                }),
                /* @__PURE__ */ jsxDEV("i", { className: "fa fa-check dim-menu__tick", "aria-hidden": "true" }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                  lineNumber: 1603,
                  columnNumber: 29
                })
              ]
            },
            view.value,
            true,
            {
              fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
              lineNumber: 1591,
              columnNumber: 25
            }
          )) }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1589,
            columnNumber: 17
          })
        ]
      },
      void 0,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1581,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
    lineNumber: 1575,
    columnNumber: 9
  });
}, "drawView");
const drawList = /* @__PURE__ */ __name((model, ref, items, active, pick) => {
  const typed = normalise(model.search.value).length >= MIN_TERM;
  const term = typed ? normalise(model.search.value) : "";
  const id = model.suggest.id;
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      ref,
      className: "dim-line__suggest",
      id,
      "data-region": "dim-line-suggest",
      "data-label-filters": model.suggest.filters,
      "data-label-start": model.suggest.start,
      role: "listbox",
      "aria-label": model.search.label,
      hidden: items.length === 0,
      onMouseDown: (event) => event.preventDefault(),
      children: items.length ? /* @__PURE__ */ jsxDEV("div", { role: "group", "aria-labelledby": `${id}-heading`, children: [
        /* @__PURE__ */ jsxDEV("div", { id: `${id}-heading`, className: "dim-line__suggesthead", role: "presentation", children: typed ? model.suggest.filters : model.suggest.start }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1650,
          columnNumber: 21
        }),
        items.map((item, index) => /* @__PURE__ */ jsxDEV(
          "div",
          {
            id: `${id}-${index}`,
            className: `dim-line__option${index === active ? ` ${ACTIVE}` : ""}`,
            role: "option",
            "aria-selected": index === active ? "true" : "false",
            "data-index": String(index),
            onClick: () => pick(index),
            children: [
              /* @__PURE__ */ jsxDEV("i", { className: "fa fa-plus dim-line__optionplus", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1663,
                columnNumber: 29
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "dim-line__optionlabel", children: pieces(item.label, term).map((piece, at) => piece.marked ? /* @__PURE__ */ jsxDEV("mark", { className: "dim-mark", children: piece.text }, at, false, {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1666,
                columnNumber: 39
              }) : /* @__PURE__ */ jsxDEV(Fragment2, { children: piece.text }, at, false, {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1667,
                columnNumber: 39
              })) }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1664,
                columnNumber: 29
              }),
              item.group !== "" ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
                " ",
                /* @__PURE__ */ jsxDEV("span", { className: "dim-line__optiongroup", children: item.group }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                  lineNumber: 1669,
                  columnNumber: 57
                })
              ] }, void 0, true, {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1669,
                columnNumber: 50
              }) : null,
              item.count !== null ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
                " ",
                /* @__PURE__ */ jsxDEV("span", { className: "dim-line__optioncount", children: item.count }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                  lineNumber: 1670,
                  columnNumber: 59
                })
              ] }, void 0, true, {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1670,
                columnNumber: 52
              }) : null
            ]
          },
          index,
          true,
          {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1654,
            columnNumber: 25
          }
        ))
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1649,
        columnNumber: 17
      }) : null
    },
    "suggest",
    false,
    {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 1635,
      columnNumber: 9
    }
  );
}, "drawList");
var FilterLine_default = FilterLine;
export {
  DRAWN,
  EVENTS,
  FilterLine_default as default
};
//# sourceMappingURL=FilterLine.dev.js.map
