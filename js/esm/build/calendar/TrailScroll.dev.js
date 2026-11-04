var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The Trilhas view's horizontal scroller, drawn as block_compass's calendar
 * strip is: one native scroller with its scrollbar hidden, its overscroll kept from the page, snapped by the day; round
 * arrows over the ends of the days' header, a fade over a side it can still scroll to, and under the grid the map, a 2px
 * line of the whole month whose thumb is the days in view and whose tick is today.
 *
 * - The grid inside it holds its trail column and its "next month" column still (position: sticky), so only the days
 *   move; their widths are trails.ts TRACK, and every position is trails.ts arithmetic over the scroller's three numbers.
 * - The layout reads are the scroller's: once per opening (the `opening` key: the month and its trails) to bring today
 *   into view (trails.ts openingLeft()) and paint the thumb, never on another render; on a scroll, to paint the thumb
 *   and the arrows; on an arrow, a page of days; on the map, the pointer's place along it. The thumb follows the scroll
 *   by its style, without a render; the arrows and fades re-render only when one of them changes.
 * - The map is aria-hidden and out of the tab order: the bars inside the scroller are its Tab stops (the browser scrolls
 *   a focused bar into view, past the still columns by the scroller's scroll padding), and the arrows are the named
 *   single-pointer way (WCAG 2.5.7). A press on the line jumps there, the thumb drags; snap is off while it does.
 *
 * @module     theme_dimensions/calendar/TrailScroll
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useLayoutEffect, useRef, useState } from "react";
import { openingLeft, pageLeft, scrubLeft, trackOf } from "./trails";
const percent = /* @__PURE__ */ __name((fraction) => `${(fraction * 100).toFixed(3)}%`, "percent");
const still = /* @__PURE__ */ __name(() => typeof window.matchMedia === "function" && window.matchMedia("(prefers-reduced-motion: reduce)").matches, "still");
const TrailScroll = /* @__PURE__ */ __name(({ days, todayat, opening, strings, children }) => {
  const box = useRef(null);
  const thumb = useRef(null);
  const drag = useRef(null);
  const [ways, setWays] = useState({ back: false, ahead: false, scrolls: false });
  const [dragging, setDragging] = useState(false);
  const measure = /* @__PURE__ */ __name((element) => trackOf(element.scrollLeft, element.scrollWidth, element.clientWidth, days), "measure");
  const paint = /* @__PURE__ */ __name((track) => {
    if (thumb.current) {
      thumb.current.style.left = percent(track.start);
      thumb.current.style.width = percent(track.span);
    }
    const next = { back: track.back, ahead: track.ahead, scrolls: track.max > 0 };
    setWays((now) => now.back === next.back && now.ahead === next.ahead && now.scrolls === next.scrolls ? now : next);
  }, "paint");
  useLayoutEffect(() => {
    const element = box.current;
    if (!element) {
      return;
    }
    element.scrollLeft = openingLeft(todayat, trackOf(0, element.scrollWidth, element.clientWidth, days));
    paint(measure(element));
  }, [opening]);
  const page = /* @__PURE__ */ __name((direction) => {
    const element = box.current;
    if (!element) {
      return;
    }
    const track = measure(element);
    if (direction < 0 && !track.back || direction > 0 && !track.ahead) {
      return;
    }
    const left = pageLeft(element.scrollLeft, direction, track);
    if (typeof element.scrollTo === "function") {
      element.scrollTo({ left, behavior: still() ? "auto" : "smooth" });
    } else {
      element.scrollLeft = left;
    }
  }, "page");
  const mapAt = /* @__PURE__ */ __name((event) => {
    const line = event.currentTarget.getBoundingClientRect();
    return line.width > 0 ? Math.max(0, Math.min(1, (event.clientX - line.left) / line.width)) : 0;
  }, "mapAt");
  const scrubTo = /* @__PURE__ */ __name((at) => {
    const element = box.current;
    const grip = drag.current;
    if (!element || !grip) {
      return;
    }
    element.scrollLeft = scrubLeft(at, grip.grab, measure(element), days);
  }, "scrubTo");
  const mapDown = /* @__PURE__ */ __name((event) => {
    const element = box.current;
    if (!element || event.pointerType === "mouse" && event.button !== 0) {
      return;
    }
    const at = mapAt(event);
    const track = measure(element);
    const onthumb = at >= track.start - 0.01 && at <= track.start + track.span + 0.01;
    drag.current = { grab: onthumb ? at - track.start : track.span / 2, span: track.span };
    try {
      event.currentTarget.setPointerCapture(event.pointerId);
    } catch (error) {
    }
    setDragging(true);
    if (!onthumb) {
      scrubTo(at);
    }
    event.preventDefault();
  }, "mapDown");
  const mapUp = /* @__PURE__ */ __name(() => {
    if (drag.current !== null) {
      drag.current = null;
      setDragging(false);
    }
  }, "mapUp");
  return /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailframe", children: [
    /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailcard", children: [
      /* @__PURE__ */ jsxDEV(
        "div",
        {
          ref: box,
          className: `dim-cal__trailscroll${dragging ? " dim-cal__trailscroll--free" : ""}`,
          "data-region": "dim-cal-trailscroll",
          onScroll: (event) => paint(measure(event.currentTarget)),
          children
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
          lineNumber: 209,
          columnNumber: 17
        }
      ),
      ways.back ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailfade dim-cal__trailfade--back", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
        lineNumber: 217,
        columnNumber: 30
      }) : null,
      ways.ahead ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailfade dim-cal__trailfade--ahead", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
        lineNumber: 218,
        columnNumber: 31
      }) : null,
      ways.scrolls ? /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "dim-cal__trailedge dim-cal__trailedge--back",
          "data-action": "dim-cal-trail-back",
          "aria-label": strings.calendar_trailearlier,
          "aria-disabled": ways.back ? void 0 : "true",
          onClick: () => page(-1),
          children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-left", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
            lineNumber: 228,
            columnNumber: 25
          })
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
          lineNumber: 220,
          columnNumber: 21
        }
      ) : null,
      ways.scrolls ? /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "dim-cal__trailedge dim-cal__trailedge--ahead",
          "data-action": "dim-cal-trail-ahead",
          "aria-label": strings.calendar_traillater,
          "aria-disabled": ways.ahead ? void 0 : "true",
          onClick: () => page(1),
          children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-right", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
            lineNumber: 240,
            columnNumber: 25
          })
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
          lineNumber: 232,
          columnNumber: 21
        }
      ) : null
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
      lineNumber: 208,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        className: "dim-cal__trailmap" + (dragging ? " dim-cal__trailmap--drag" : "") + (ways.scrolls ? "" : " dim-cal__trailmap--whole"),
        "data-region": "dim-cal-trailmap",
        "aria-hidden": "true",
        onPointerDown: mapDown,
        onPointerMove: (event) => {
          if (drag.current !== null) {
            scrubTo(mapAt(event));
          }
        },
        onPointerUp: mapUp,
        onPointerCancel: mapUp,
        children: /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailmapline", children: [
          /* @__PURE__ */ jsxDEV("span", { ref: thumb, className: "dim-cal__trailthumb", "data-region": "dim-cal-trailthumb" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
            lineNumber: 259,
            columnNumber: 21
          }),
          todayat >= 0 ? /* @__PURE__ */ jsxDEV(
            "span",
            {
              className: "dim-cal__trailmaptoday",
              style: { left: percent((todayat + 0.5) / Math.max(1, days)) }
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
              lineNumber: 261,
              columnNumber: 25
            }
          ) : null
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
          lineNumber: 258,
          columnNumber: 17
        })
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
        lineNumber: 244,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
    lineNumber: 207,
    columnNumber: 9
  });
}, "TrailScroll");
var TrailScroll_default = TrailScroll;
export {
  TrailScroll_default as default
};
//# sourceMappingURL=TrailScroll.dev.js.map
