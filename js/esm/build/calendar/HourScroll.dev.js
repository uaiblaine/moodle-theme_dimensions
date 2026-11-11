var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The scroll box of an hour column, the drawer's and the week's.
 *
 * The column inside it is the whole day (overlap.ts FULL_DAY); the box starts where overlap.ts boxTopOf() puts it,
 * once per opening (the `opening` key: a day, or a week, and its band), from the box's one layout read, never on the
 * page's mount path, and again when the grid hands a new `height` (the drawer is capped to the grid's height only once
 * the grid's observer has reported, which shrinks the box after the first placing) until the reader's first wheel,
 * touch or key on the box; a new opening starts over. The look is block_compass's calendar strip: no native scrollbar,
 * its overscroll kept from the page, and a fade at the top or the bottom only while the box can still scroll that way
 * (overlap.ts edgesOf(), read on scroll), the fades taking no pointer and hidden from the screen reader. Handed
 * `focusable` (it holds no focusable item), the box is a Tab stop with the label it is handed, so the arrows and the
 * Page keys scroll it.
 *
 * @module     theme_dimensions/calendar/HourScroll
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useLayoutEffect, useRef, useState } from "react";
import { FULL_DAY, edgesOf, boxTopOf } from "./overlap";
const moved = /* @__PURE__ */ __name((a, b) => a.up !== b.up || a.down !== b.down, "moved");
const HourScroll = /* @__PURE__ */ __name(({ band, busy, opening, height = 0, label, focusable, role, variant, children }) => {
  const box = useRef(null);
  const [edges, setEdges] = useState({ up: false, down: false });
  const placed = useRef("");
  const touched = useRef(false);
  useLayoutEffect(() => {
    const element = box.current;
    if (!element) {
      return;
    }
    if (placed.current !== opening) {
      placed.current = opening;
      touched.current = false;
    } else if (touched.current) {
      return;
    }
    const hours = FULL_DAY.to - FULL_DAY.from;
    element.scrollTop = boxTopOf(band, busy, element.scrollHeight / hours, element.clientHeight);
    setEdges(edgesOf(element.scrollTop, element.scrollHeight, element.clientHeight));
  }, [opening, height]);
  const take = /* @__PURE__ */ __name(() => {
    touched.current = true;
  }, "take");
  const onScroll = /* @__PURE__ */ __name((event) => {
    const element = event.currentTarget;
    const next = edgesOf(element.scrollTop, element.scrollHeight, element.clientHeight);
    setEdges((now) => moved(now, next) ? next : now);
  }, "onScroll");
  return /* @__PURE__ */ jsxDEV("div", { className: `dim-cal__hourbox dim-cal__hourbox--${variant}`, role: "presentation", children: [
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        ref: box,
        className: "dim-cal__hourscroll",
        "data-region": "dim-cal-hourscroll",
        role,
        tabIndex: focusable ? 0 : void 0,
        "aria-label": focusable || role ? label : void 0,
        onScroll,
        onWheel: take,
        onTouchStart: take,
        onKeyDown: take,
        children
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/HourScroll.tsx",
        lineNumber: 114,
        columnNumber: 13
      }
    ),
    edges.up ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__hourfade dim-cal__hourfade--up", "aria-hidden": "true" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/HourScroll.tsx",
      lineNumber: 128,
      columnNumber: 25
    }) : null,
    edges.down ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__hourfade dim-cal__hourfade--down", "aria-hidden": "true" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/HourScroll.tsx",
      lineNumber: 129,
      columnNumber: 27
    }) : null
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/HourScroll.tsx",
    lineNumber: 113,
    columnNumber: 9
  });
}, "HourScroll");
var HourScroll_default = HourScroll;
export {
  HourScroll_default as default
};
//# sourceMappingURL=HourScroll.dev.js.map
