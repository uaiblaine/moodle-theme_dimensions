var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The posters of the areas and the panel between their rows, on both shells of the showcase.
 *
 * The visitor's differences are the mount's props ({@see ./panel Options}): `{links: true, quiet, autoopen:
 * false}` on the visitor's page, nothing on the logged-in one. The rules live in the modules of this tree
 * (`panel.ts` for the panel, `posters.ts` for the posters' result layer, `rail.ts`, `model.ts`).
 *
 * The mount node is an empty, hidden child that mountAreas() (js/esm/src/listing/mounts.ts) appends to the
 * areas section, so the mount draws no box and the first paint stays the server's. The component is the only
 * writer of:
 *
 * - the panel's frame: the one list item of the grid (`panel.ts`), whose content is category_panel
 *   rendered by core/templates and inserted whole, never diffed;
 * - the posters' result layer (`posters.ts`): pills, counts and shells on the articles, the poster's
 *   control (a button that opens, or the link to its page), the open state, the items' hidden state, the
 *   reveal past the slice, the "nothing anywhere" box and its "search all" link, the hint, the live status;
 * - the "nothing in N areas" summary, which React draws: its sentence and its list, through portals into
 *   the server's `<summary>` and `<ul>`, read off them in the first render and drawn back hook for hook.
 *
 * Nothing else writes into those, except the address of a visitor's poster that stays a link, which the
 * visitor's shell points at the area's panel. The listing speaks to the component through events
 * ({@see EVENTS}): the listing client's `dim-vitrine:pending`, `:criteria` and `:failed` and the listing's
 * `:view` on the document; the component answers `dim-panel:autoopened` there. On the areas section,
 * mountAreas() hears, for the page's client, `dim-areas:ask` (the client fills the detail's `answer` with
 * its web service's promise: the component calls no service and reads no session), `dim-areas:question`
 * and `dim-areas:unanswered` (the visitor's names its question and takes a failure over),
 * `dim-areas:painted` (the logged-in shell paints what an account adds to the rows) and
 * `dim-areas:mounted`, which ends its pending.
 *
 * @module     theme_dimensions/areas/Areas
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Fragment, useLayoutEffect, useState } from "react";
import { createPortal, flushSync } from "react-dom";
import { PHONE, SELECTORS, WIDE, summaryLabel } from "./model";
import { EVENTS, createPanel, drawnOpen, optionsOf } from "./panel";
import { isFiltered, setHidden } from "./posters";
const readSummary = /* @__PURE__ */ __name((areas) => {
  const box = areas.querySelector(SELECTORS.nomatch);
  const list = box ? box.querySelector(SELECTORS.nomatchlist) : null;
  const entries = list ? Array.from(list.children).map((item) => ({
    id: Number(item.dataset.categoryid),
    name: item.textContent || "",
    url: item.querySelector("a")?.getAttribute("href") || ""
  })) : [];
  const active = document.activeElement;
  return {
    box,
    label: box ? box.querySelector(SELECTORS.nomatchsummary) : null,
    list,
    summary: { entries, all: box?.dataset.labelAll || "", less: box?.dataset.labelLess || "" },
    focused: list && active && list.contains(active) ? active : null
  };
}, "readSummary");
const synchronously = /* @__PURE__ */ __name((update) => flushSync(update), "synchronously");
const Areas = /* @__PURE__ */ __name((props) => {
  const [setup] = useState(() => {
    const areas = document.querySelector(SELECTORS.areas);
    const panel2 = areas ? areas.querySelector(SELECTORS.panel) : null;
    const nodes = areas && panel2 ? { areas, panel: panel2, status: areas.querySelector(SELECTORS.status) } : null;
    return {
      nodes,
      drawn: areas ? readSummary(areas) : null,
      open: nodes ? drawnOpen(nodes) : 0,
      filtered: areas ? isFiltered(areas) : false
    };
  });
  const [summary, setSummary] = useState(setup.drawn ? setup.drawn.summary : { entries: [], all: "", less: "" });
  const [opened, setOpened] = useState(setup.open);
  const [filtered, setFiltered] = useState(setup.filtered);
  const [adopted, setAdopted] = useState(false);
  const [panel] = useState(() => setup.nodes ? createPanel(setup.nodes, {
    summary: setSummary,
    opened: setOpened,
    filtered: setFiltered,
    sync: synchronously
  }, optionsOf(props || {})) : null);
  useLayoutEffect(() => {
    if (!setup.nodes || !panel) {
      return void 0;
    }
    const { areas, panel: frame } = setup.nodes;
    const drawn2 = setup.drawn;
    if (drawn2 && drawn2.label && drawn2.list) {
      drawn2.label.textContent = "";
      drawn2.list.replaceChildren();
      setAdopted(true);
    }
    try {
      panel.adopt();
    } catch (error) {
    }
    const listeners = [
      [areas, "click", panel.onAreasClick, false],
      [frame, "click", panel.onDragClick, true],
      [frame, "click", panel.onPanelClick, false],
      [frame, "scroll", panel.onScroll, true],
      [frame, "pointerdown", panel.onPointerDown, false],
      [frame, "dragstart", panel.onDragStart, false],
      [areas, "toggle", panel.onToggle, true],
      [document, "keydown", panel.onKeydown, false],
      [window, "popstate", panel.onPopState, false],
      [document, EVENTS.view, panel.relayout, false],
      [document, EVENTS.pending, (event) => panel.onPending(event.detail || {}), false],
      [document, EVENTS.failed, panel.onFailed, false],
      [document, EVENTS.criteria, (event) => panel.criteria(event.detail || {}), false]
    ];
    listeners.forEach(([target, type, listener, capture]) => target.addEventListener(type, listener, capture));
    const queries = [WIDE, PHONE].map((query) => window.matchMedia(query));
    queries.forEach((query) => query.addEventListener("change", panel.relayout));
    areas.dispatchEvent(new CustomEvent(EVENTS.mounted, { bubbles: true }));
    return () => {
      listeners.forEach(([target, type, listener, capture]) => target.removeEventListener(type, listener, capture));
      queries.forEach((query) => query.removeEventListener("change", panel.relayout));
      panel.disconnect();
    };
  }, []);
  const label = summaryLabel(summary, opened);
  useLayoutEffect(() => {
    const drawn2 = setup.drawn;
    if (!adopted || !drawn2 || !drawn2.box) {
      return;
    }
    setHidden(drawn2.box, !filtered || label === "");
    if (drawn2.focused instanceof HTMLAnchorElement && !drawn2.focused.isConnected && drawn2.list) {
      const href = drawn2.focused.getAttribute("href");
      drawn2.list.querySelector(`a[href="${CSS.escape(href ?? "")}"]`)?.focus({ preventScroll: true });
      drawn2.focused = null;
    }
  }, [adopted, filtered, label]);
  const drawn = setup.drawn;
  if (!adopted || !drawn || !drawn.label || !drawn.list) {
    return null;
  }
  const entries = summary.entries.map((entry) => /* @__PURE__ */ jsxDEV("li", { "data-categoryid": entry.id, hidden: entry.id === opened, children: entry.url ? /* @__PURE__ */ jsxDEV("a", { href: entry.url, children: entry.name }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/areas/Areas.tsx",
    lineNumber: 200,
    columnNumber: 26
  }) : entry.name }, entry.id, false, {
    fileName: "public/theme/dimensions/js/esm/src/areas/Areas.tsx",
    lineNumber: 199,
    columnNumber: 9
  }));
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    createPortal(label, drawn.label),
    createPortal(entries, drawn.list)
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/areas/Areas.tsx",
    lineNumber: 204,
    columnNumber: 9
  });
}, "Areas");
var Areas_default = Areas;
export {
  EVENTS,
  Areas_default as default
};
//# sourceMappingURL=Areas.dev.js.map
