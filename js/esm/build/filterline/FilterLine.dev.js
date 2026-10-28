var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The filter line of the logged-in showcase: its folding, the search that grows over it and its
 * suggestions, its floating layers and its rows that scroll sideways.
 *
 * Stage R2 of docs/vitrine-react/PLAN.md. The visitor's shell keeps amd/src/categoryline.js (with
 * categorypreview and categorymark) until R4, so this component and that module are two implementations
 * of one control and of the fleet contract (moodle-dev/docs/filter-line/CONTRACT.md): a rule changes in
 * both. block_compass's FilterLine implements the same contract on its own; nothing is shared.
 *
 * WHO OWNS WHAT. The mount node is the server's line itself (dim-line), which categorylisting's
 * mountLine() names for core's react_autoinit, so the line keeps its place, its classes and the sibling
 * rules the stylesheet writes against it. A mount replaces its markup, so the first render draws the
 * markup the server drew, read off it ({@see ./model read()}), with the same names inside the same GET
 * form - the no-JavaScript submits, the listing's form reading and every Behat step keep working. React
 * draws the line's own controls: the scope, the search with its eraser and its icon, the star, the two
 * summaries (Filters and its badge, Organise), the view toggle, the close button and the listbox. Three
 * frames it draws hold server markup it adopts whole on the first commit and never diffs inside: the
 * Filters panel (More statuses, the custom fields, the period with the calendar of R1, the quick dates),
 * the Organise menu and the managers' menu. The listing's painters keep painting those three, and paint
 * nothing React draws: they tell the line instead, through events on it ({@see EVENTS}), which
 * amd/src/categorylisting.js spells too:
 *
 * - `dim-line:state`, detail {query, chip, fav, view} or any part of it: the listing's own state, after a
 *   press it handled, Back, a clear or a removed chip;
 * - `dim-line:answer`, detail {scope, favourites, filtercount, filtercountlabel}: an answer's numbers;
 * - `dim-line:dropterm`, sent by the line before it presses the control a suggestion names, so the one
 *   request that control causes carries no term;
 * - `dim-line:mounted`, sent once the line is drawn, after it marked itself drawn (data-line-drawn), which
 *   is what the painters read.
 *
 * The step is measured once per content on the line's own width with every scope cell drawn
 * ({@see ./measure stepWidths()}), and taken again from a width alone on every ResizeObserver entry; the
 * first commit folds the line before it is painted. Every request is the listing's: nothing here calls a
 * web service, reads the session or composes a word.
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
import { SELECTORS, answered, read, stated } from "./model";
import { MIN_TERM, offers, remember, suggest } from "./suggest";
import { CLASSES as STEP_CLASSES, TWO_LINES, choose, morphs, paint, same } from "./steps";
const EVENTS = {
  mounted: "dim-line:mounted",
  state: "dim-line:state",
  answer: "dim-line:answer",
  dropterm: "dim-line:dropterm"
};
const DRAWN = "data-line-drawn";
const TAB_INTENT = 1e3;
const HOOKS = {
  listing: '[data-region="dim-vitrine"]',
  chips: '[data-region="dim-line-chips"]',
  showas: '[data-region="dim-showas"] [data-action="dim-view"]',
  daterange: '[data-region="dim-daterange"]',
  option: '[role="option"]'
};
const SHEET = "dim-toolbar--sheet";
const ACTIVE = "dim-line__option--active";
const still = /* @__PURE__ */ __name(() => window.matchMedia("(prefers-reduced-motion: reduce)").matches, "still");
const adopt = /* @__PURE__ */ __name((source, target) => {
  if (source && target && source !== target) {
    target.append(...Array.from(source.childNodes));
  }
}, "adopt");
const counterpart = /* @__PURE__ */ __name((line, was) => {
  if (was.id) {
    const found = line.querySelector(`[id="${was.id}"]`);
    if (found) {
      return found;
    }
  }
  const action = was.getAttribute("data-action");
  if (action) {
    const value = was.getAttribute("value");
    const selector = `[data-action="${action}"]` + (value === null ? "" : `[value="${value}"]`);
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
    return {
      toolbar: toolbar2,
      line: line2,
      first: read(line2),
      panel: line2 ? line2.querySelector(SELECTORS.panel) : null,
      menu: line2 ? line2.querySelector(SELECTORS.menu) : null,
      manage: line2 ? line2.querySelector(SELECTORS.manage) : null,
      focused: line2 && active && line2.contains(active) ? active : null
    };
  });
  const [model, setModel] = useState(setup.first);
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
    refocus: null
  });
  const rest = useRef(/* @__PURE__ */ new WeakMap());
  const preview = useRef(null);
  const previewing = useRef(null);
  const field = useRef(null);
  const opener = useRef(null);
  const scope = useRef(null);
  const filters = useRef(null);
  const panel = useRef(null);
  const organise = useRef(null);
  const menu = useRef(null);
  const manage = useRef(null);
  const listbox = useRef(null);
  const drawn = useRef(false);
  const api = useRef({});
  const line = setup.line;
  const toolbar = setup.toolbar;
  const st = live.current;
  const twolines = /* @__PURE__ */ __name(() => st.fold !== null && st.fold.step === TWO_LINES, "twolines");
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
  const searchOver = /* @__PURE__ */ __name((on) => {
    st.searching = on;
    line?.classList.toggle(STEP_CLASSES.searching, on);
    rerender();
  }, "searchOver");
  const openSearch = /* @__PURE__ */ __name((from) => {
    if (morphs(st.fold) && !st.searching) {
      st.from = from || field.current;
      searchOver(true);
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
    searchOver(false);
    const from = st.from;
    st.from = null;
    if (back) {
      quietly([from, field.current, opener.current].find((control) => shown(control)) || null);
    }
  }, "closeSearch");
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
    if (st.searching && !morphs(next)) {
      st.searching = false;
      line.classList.remove(STEP_CLASSES.searching);
    }
    rerender();
  }, "refold");
  const remeasure = /* @__PURE__ */ __name(() => {
    if (!line || !line.isConnected) {
      return;
    }
    st.widths = stepWidths(line, st.fold);
    st.width = contentWidth(line);
    refold();
  }, "remeasure");
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
    if (event.key !== "Escape" || event.defaultPrevented || !(event.target instanceof Element) || !line) {
      return;
    }
    if (st.searching && event.target !== field.current) {
      event.preventDefault();
      closeSearch(true);
      return;
    }
    const details = event.target.closest("details[open]");
    if (details && line.contains(details) && !event.target.closest(HOOKS.daterange)) {
      event.preventDefault();
      details.open = false;
      quietly(details.querySelector("summary"));
    }
  }, "onLineKey");
  const onLineBlur = /* @__PURE__ */ __name((event) => {
    const next = event.relatedTarget instanceof Node ? event.relatedTarget : null;
    if (!line || next === field.current || next && listbox.current && listbox.current.contains(next)) {
      return;
    }
    if (next && line.contains(next)) {
      closeList();
      return;
    }
    closeSearch(false);
  }, "onLineBlur");
  const onToggle = /* @__PURE__ */ __name((event) => {
    if (!line) {
      return;
    }
    if (event.target === filters.current && filters.current) {
      toolbar?.classList.toggle(SHEET, twolines() && filters.current.open);
      place(line, panel.current, twolines(), twolines());
    } else if (event.target === organise.current) {
      place(line, menu.current, twolines(), false);
    }
  }, "onToggle");
  const onLineClick = /* @__PURE__ */ __name((event) => {
    const view = event.target instanceof Element ? event.target.closest(HOOKS.showas) : null;
    if (view && organise.current) {
      organise.current.open = false;
      quietly(organise.current.querySelector("summary"));
    }
  }, "onLineClick");
  const onDocumentPress = /* @__PURE__ */ __name((event) => {
    const target = event.target instanceof Node ? event.target : null;
    if (!target || !line) {
      return;
    }
    if ((st.open || st.searching) && !line.contains(target)) {
      closeSearch(false);
    }
    if (twolines() && filters.current && filters.current.open && !filters.current.contains(target)) {
      filters.current.open = false;
    }
  }, "onDocumentPress");
  const onDocumentKey = /* @__PURE__ */ __name((event) => {
    if (event.key === "Tab") {
      st.tabat = Date.now();
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
    openSearch(st.fold !== null && st.fold.folded && !st.searching ? opener.current : field.current);
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
    remeasure
  };
  useLayoutEffect(() => {
    if (!line || !toolbar || !setup.first) {
      return void 0;
    }
    adopt(setup.panel, panel.current);
    adopt(setup.menu, menu.current);
    adopt(setup.manage, manage.current);
    const to = /* @__PURE__ */ __name((name) => (event) => api.current[name](event), "to");
    const listeners = [
      [line, "keydown", to("onLineKey"), false],
      [line, "focusout", to("onLineBlur"), false],
      [line, "toggle", to("onToggle"), true],
      [line, "click", to("onLineClick"), false],
      [line, EVENTS.state, to("onState"), false],
      [line, EVENTS.answer, to("onAnswer"), false],
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
    st.widths = stepWidths(line, null);
    st.width = contentWidth(line);
    refold();
    const observer = typeof ResizeObserver === "undefined" ? null : new ResizeObserver((entries) => {
      const width = entries[0].contentRect.width;
      if (Math.round(width) === Math.round(live.current.width)) {
        return;
      }
      live.current.width = width;
      flushSync(() => api.current.remeasureWidth());
    });
    observer?.observe(line);
    document.fonts?.ready.then(() => {
      if (line.isConnected) {
        flushSync(() => api.current.remeasure());
      }
      return null;
    }).catch(() => null);
    line.setAttribute(DRAWN, "true");
    drawn.current = true;
    if (setup.focused) {
      quietly(setup.focused.isConnected ? setup.focused : counterpart(line, setup.focused));
    }
    line.dispatchEvent(new CustomEvent(EVENTS.mounted, { bubbles: true }));
    return () => {
      observer?.disconnect();
      listeners.forEach(([target, type, listener, options]) => target.removeEventListener(type, listener, options));
      line.removeAttribute(DRAWN);
    };
  }, []);
  api.current.remeasureWidth = (() => refold());
  const signature = model ? JSON.stringify([
    model.scope ? model.scope.cells.map((cell) => [cell.label, cell.count, cell.hascount]) : null,
    model.star ? model.star.count : null,
    [model.filters.hascount, model.filters.count]
  ]) : "";
  const measured = useRef(signature);
  useLayoutEffect(() => {
    if (!drawn.current || measured.current === signature) {
      return;
    }
    measured.current = signature;
    remeasure();
  }, [signature]);
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
    line.classList.toggle(STEP_CLASSES.searching, st.searching);
    toolbar.classList.toggle(SHEET, twolines() && Boolean(filters.current && filters.current.open));
    if (st.refocus) {
      const refocus = st.refocus;
      st.refocus = null;
      if (refocus.isConnected && document.activeElement !== refocus) {
        quietly(refocus);
      }
    }
    if (listbox.current && !listbox.current.hidden) {
      placeList(line, listbox.current, st.fold === null || st.fold.step === "s1" || st.fold.step === "s2", twolines());
      const option = st.active >= 0 ? listbox.current.querySelectorAll(HOOKS.option)[st.active] : null;
      option?.scrollIntoView?.({ block: "nearest" });
    }
  });
  const foldkey = st.fold ? `${st.fold.step}${st.fold.folded ? "f" : ""}` : "";
  useLayoutEffect(() => {
    if (!line || !drawn.current) {
      return;
    }
    place(line, panel.current, twolines(), twolines());
    place(line, menu.current, twolines(), false);
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
    /* @__PURE__ */ jsxDEV("div", { className: "dim-toolbar__search dim-line__search", "data-region": "dim-line-search", children: [
      /* @__PURE__ */ jsxDEV("label", { className: "visually-hidden", htmlFor: search.id, children: search.label }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 918,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("i", { className: "fa fa-magnifying-glass dim-toolbar__searchicon", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 919,
        columnNumber: 17
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
              openSearch(st.from || field.current);
            }
          },
          onChange: (event) => onFieldInput(event.target.value),
          onKeyDown: onFieldKey
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 920,
          columnNumber: 17
        }
      ),
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "dim-line__eraser dim-live-only",
          "data-action": "dim-search-clear",
          "aria-label": search.clearlabel,
          title: search.clearlabel,
          hidden: search.value === "",
          onClick: onErase,
          children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-eraser", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 954,
            columnNumber: 18
          })
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 946,
          columnNumber: 17
        }
      )
    ] }, "search", true, {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 917,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "button",
      {
        ref: opener,
        type: "button",
        className: "dim-line__iconbtn dim-line__searchopen",
        "data-action": "dim-search-open",
        "aria-controls": search.id,
        "aria-expanded": st.searching ? "true" : "false",
        title: model.openlabel,
        onClick: () => openSearch(opener.current),
        children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-magnifying-glass", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 967,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: model.openlabel }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 968,
            columnNumber: 17
          })
        ]
      },
      "opener",
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 956,
        columnNumber: 13
      }
    ),
    drawStar(model),
    drawFilters(model, filters, panel),
    /* @__PURE__ */ jsxDEV("details", { ref: organise, className: "dim-line__organise", "data-region": "dim-organise", children: [
      /* @__PURE__ */ jsxDEV("summary", { className: "dim-line__iconbtn dim-line__organisetoggle", title: model.organiselabel, children: [
        /* @__PURE__ */ jsxDEV("i", { className: "fa fa-arrow-down-wide-short", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 974,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: model.organiselabel }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 975,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 973,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { ref: menu, className: "dim-line__menu", "data-region": "dim-organise-menu" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 977,
        columnNumber: 17
      })
    ] }, "organise", true, {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 972,
      columnNumber: 13
    }),
    drawViews(model),
    model.manage ? /* @__PURE__ */ jsxDEV("div", { ref: manage, className: "dim-toolbar__manage", "data-region": "dim-line-manage" }, "manage", false, {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 981,
      columnNumber: 17
    }) : null,
    /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: "dim-line__iconbtn dim-line__searchclose",
        "data-action": "dim-search-close",
        "aria-label": model.closelabel,
        title: model.closelabel,
        onClick: () => closeSearch(true),
        children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-xmark", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 991,
          columnNumber: 14
        })
      },
      "closer",
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 983,
        columnNumber: 13
      }
    ),
    scopedown ? scopenode : null,
    drawList(model, listbox, listed ? st.items : [], st.active, pick)
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
    lineNumber: 915,
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
          lineNumber: 1021,
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
              lineNumber: 1025,
              columnNumber: 25
            }
          ),
          /* @__PURE__ */ jsxDEV("label", { className: "dim-pill", htmlFor: cell.id, hidden: cell.absent, children: [
            `${cell.label} `,
            /* @__PURE__ */ jsxDEV("span", { className: "dim-pill__count", "data-region": "dim-pill-count", hidden: !cell.hascount, children: cell.count }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
              lineNumber: 1037,
              columnNumber: 29
            })
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1035,
            columnNumber: 25
          })
        ] }, cell.value, true, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1024,
          columnNumber: 21
        })) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1022,
          columnNumber: 13
        })
      ]
    },
    "scope",
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 1013,
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
      title: star.label,
      "data-action": "dim-fav",
      "data-region": "dim-line-star",
      children: [
        /* @__PURE__ */ jsxDEV("i", { className: `${star.pressed ? "fa-solid" : "fa-regular"} fa-star`, "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1072,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: [
          `${star.label} `,
          /* @__PURE__ */ jsxDEV("span", { "data-region": "dim-fav-count", children: star.count }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1073,
            columnNumber: 65
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
          lineNumber: 1073,
          columnNumber: 13
        })
      ]
    },
    "star",
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 1061,
      columnNumber: 9
    }
  );
}, "drawStar");
const drawFilters = /* @__PURE__ */ __name((model, ref, panel) => /* @__PURE__ */ jsxDEV("details", { ref, className: "dim-toolbar__filters dim-line__filters", "data-region": "dim-filters", children: [
  /* @__PURE__ */ jsxDEV("summary", { className: "dim-toolbar__filterstoggle", children: [
    /* @__PURE__ */ jsxDEV("i", { className: "fa fa-sliders", "aria-hidden": "true" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 1094,
      columnNumber: 13
    }),
    " ",
    /* @__PURE__ */ jsxDEV("span", { className: "dim-line__label dim-toolbar__filterslabel", children: model.filters.label }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 1096,
      columnNumber: 13
    }),
    " ",
    /* @__PURE__ */ jsxDEV("span", { className: "dim-toolbar__filtercount", "data-region": "dim-filtercount", hidden: !model.filters.hascount, children: [
      /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", "data-region": "dim-filtercount-number", children: model.filters.count }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1099,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", "data-region": "dim-filtercount-label", children: model.filters.countlabel }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1100,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 1098,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
    lineNumber: 1093,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { ref: panel, className: "dim-toolbar__panel", "data-region": "dim-filters-panel" }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
    lineNumber: 1103,
    columnNumber: 9
  })
] }, "filters", true, {
  fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
  lineNumber: 1092,
  columnNumber: 5
}), "drawFilters");
const drawViews = /* @__PURE__ */ __name((model) => /* @__PURE__ */ jsxDEV(
  "div",
  {
    className: "dim-toolbar__view dim-line__view",
    role: "group",
    "aria-label": model.viewlabel,
    "data-region": "dim-view-row",
    children: model.views.map((view) => /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "submit",
        className: "dim-toolbar__viewbtn",
        "data-action": "dim-view",
        name: model.viewname,
        value: view.value,
        "aria-pressed": view.pressed ? "true" : "false",
        title: view.label,
        children: [
          /* @__PURE__ */ jsxDEV("i", { className: `fa ${view.icon}`, "aria-hidden": "true" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1131,
            columnNumber: 14
          }),
          /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: view.label }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1131,
            columnNumber: 70
          })
        ]
      },
      view.value,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1122,
        columnNumber: 13
      }
    ))
  },
  "view",
  false,
  {
    fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
    lineNumber: 1114,
    columnNumber: 5
  }
), "drawViews");
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
          lineNumber: 1174,
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
                lineNumber: 1187,
                columnNumber: 29
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "dim-line__optionlabel", children: pieces(item.label, term).map((piece, at) => piece.marked ? /* @__PURE__ */ jsxDEV("mark", { className: "dim-mark", children: piece.text }, at, false, {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1190,
                columnNumber: 39
              }) : /* @__PURE__ */ jsxDEV(Fragment2, { children: piece.text }, at, false, {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1191,
                columnNumber: 39
              })) }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1188,
                columnNumber: 29
              }),
              item.group !== "" ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
                " ",
                /* @__PURE__ */ jsxDEV("span", { className: "dim-line__optiongroup", children: item.group }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                  lineNumber: 1193,
                  columnNumber: 57
                })
              ] }, void 0, true, {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1193,
                columnNumber: 50
              }) : null,
              item.count !== null ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
                " ",
                /* @__PURE__ */ jsxDEV("span", { className: "dim-line__optioncount", children: item.count }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                  lineNumber: 1194,
                  columnNumber: 59
                })
              ] }, void 0, true, {
                fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
                lineNumber: 1194,
                columnNumber: 52
              }) : null
            ]
          },
          index,
          true,
          {
            fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
            lineNumber: 1178,
            columnNumber: 25
          }
        ))
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
        lineNumber: 1173,
        columnNumber: 17
      }) : null
    },
    "suggest",
    false,
    {
      fileName: "public/theme/dimensions/js/esm/src/filterline/FilterLine.tsx",
      lineNumber: 1159,
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
