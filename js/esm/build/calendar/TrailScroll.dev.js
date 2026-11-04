var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The Trilhas view's horizontal scroller, drawn as block_compass's calendar
 * strip is: one native scroller with its scrollbar hidden, its overscroll kept from the page, snapped by the day; round
 * arrows over the ends of the days' header, a fade over a side it can still scroll to, and under the grid the map, a 2px
 * line of the days held whose thumb is the days in view and whose tick is today.
 *
 * - The grid inside it holds its trail column still (position: sticky), so only the days move; their widths are
 *   trails.ts TRACK, and every position is trails.ts arithmetic over the scroller's three numbers.
 * - The layout reads are the scroller's: once per opening (the `opening` key: a new set of months) to bring today into
 *   view (trails.ts openingLeft()) and paint the thumb, never on another render; on a scroll, to paint the thumb and
 *   the arrows and tell the view which days are in view; on an arrow or a target the view asks for, where it is; on a
 *   new zoom, where its arithmetic left it; when the days held change, where it is; on the map, the pointer's place
 *   along it. The thumb follows the scroll by its style, without a render; the arrows and fades re-render only when one
 *   of them changes. Every position is written through one place ({@see place}).
 * - A month added before the first day held moves the content right by its days: the `shift` key moves the position
 *   by the same width (trails.ts keptLeft(), over the last measure: no further read), so the view stays still.
 * - A day's width is the zoom's (trails.ts dayWidth()), in every measure. A new zoom keeps the day at the start of the
 *   view: the position is the last measure's at the new width (trails.ts zoomedLeft(), arithmetic), then the scroller
 *   is measured once more where that left it, as after an arrow, to paint the thumb and the arrows and tell the view.
 * - A scroll settles on `scrollend`, or a quiet after its last event where the browser has no such event: the view is
 *   told the first day in view (its month is the address's), and a target asked for is reached.
 * - The map is aria-hidden and out of the tab order: the bars and the courses' rows are its Tab stops (the browser
 *   scrolls a focused bar into view, past the still column by the scroller's scroll padding), and the arrows are the
 *   named single-pointer way (WCAG 2.5.7). A press on the line jumps there, the thumb drags; snap is off while it does.
 *
 * @module     theme_dimensions/calendar/TrailScroll
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import {
  dayWidth,
  keptLeft,
  nearEnd,
  openingLeft,
  pageLeft,
  scrubLeft,
  shiftedTarget,
  shownDays,
  targetLeft,
  trackOf,
  zoomedLeft
} from "./trails";
const SETTLE_MS = 150;
const ARRIVE_MS = 700;
const percent = /* @__PURE__ */ __name((fraction) => `${(fraction * 100).toFixed(3)}%`, "percent");
const still = /* @__PURE__ */ __name(() => typeof window.matchMedia === "function" && window.matchMedia("(prefers-reduced-motion: reduce)").matches, "still");
const place = /* @__PURE__ */ __name((element, left, smooth) => {
  if (typeof element.scrollTo === "function") {
    element.scrollTo({ left, behavior: smooth && !still() ? "smooth" : "auto" });
  } else {
    element.scrollLeft = left;
  }
}, "place");
const TrailScroll = /* @__PURE__ */ __name(({ days, todayat, openat, opening, shift, zoom, control, onView, onSettle, strings, children }) => {
  const box = useRef(null);
  const thumb = useRef(null);
  const drag = useRef(null);
  const last = useRef(null);
  const quiet = useRef(null);
  const arriving = useRef(null);
  const zoomed = useRef(zoom);
  const reports = useRef({ onView, onSettle, days });
  reports.current = { onView, onSettle, days };
  const [ways, setWays] = useState({ back: false, ahead: false, scrolls: false });
  const [dragging, setDragging] = useState(false);
  const measure = /* @__PURE__ */ __name((element) => trackOf(element.scrollLeft, element.scrollWidth, element.clientWidth, days, dayWidth(zoom)), "measure");
  const paint = /* @__PURE__ */ __name((track, moved) => {
    last.current = track;
    zoomed.current = zoom;
    if (thumb.current) {
      thumb.current.style.left = percent(track.start);
      thumb.current.style.width = percent(track.span);
    }
    const next = { back: track.back, ahead: track.ahead, scrolls: track.max > 0 };
    setWays((now) => now.back === next.back && now.ahead === next.ahead && now.scrolls === next.scrolls ? now : next);
    const shown = shownDays(track, reports.current.days);
    let near = "";
    if (nearEnd(track, reports.current.days)) {
      near = "end";
    } else if (moved < 0 && !track.back) {
      near = "start";
    }
    reports.current.onView(shown.first, shown.last, near);
  }, "paint");
  const arrive = /* @__PURE__ */ __name((reached) => {
    const arrival = arriving.current;
    arriving.current = null;
    if (arrival) {
      if (arrival.timer !== null) {
        clearTimeout(arrival.timer);
      }
      arrival.resolve(reached);
    }
  }, "arrive");
  const wait = /* @__PURE__ */ __name(() => {
    const arrival = arriving.current;
    if (!arrival) {
      return;
    }
    if (arrival.timer !== null) {
      clearTimeout(arrival.timer);
    }
    arrival.timer = setTimeout(() => {
      if (arriving.current === arrival) {
        arrive(true);
      }
    }, ARRIVE_MS);
  }, "wait");
  const tell = /* @__PURE__ */ __name(() => {
    if (last.current) {
      reports.current.onSettle(shownDays(last.current, reports.current.days).first);
    }
  }, "tell");
  const settle = /* @__PURE__ */ __name(() => {
    if (quiet.current !== null) {
      clearTimeout(quiet.current);
      quiet.current = null;
    }
    tell();
    arrive(true);
  }, "settle");
  useLayoutEffect(() => {
    const element = box.current;
    if (!element) {
      return;
    }
    place(element, openingLeft(openat, trackOf(0, element.scrollWidth, element.clientWidth, days, dayWidth(zoom))), false);
    paint(measure(element), 0);
    tell();
    arrive(false);
  }, [opening]);
  useLayoutEffect(() => {
    const element = box.current;
    const was = last.current;
    if (!element || !was || shift.days === 0) {
      return;
    }
    place(element, keptLeft(was.left, shift.days, was.pitch), false);
    last.current = { ...was, left: keptLeft(was.left, shift.days, was.pitch) };
    const arrival = arriving.current;
    if (arrival) {
      arrival.target = shiftedTarget(arrival.target, shift.days);
      place(element, targetLeft(arrival.target, measure(element)), true);
      wait();
    }
  }, [shift.key]);
  useLayoutEffect(() => {
    const element = box.current;
    const was = last.current;
    const from = zoomed.current;
    if (!element || !was || from === zoom) {
      return;
    }
    arrive(false);
    place(element, zoomedLeft(was.left, from, zoom), false);
    paint(measure(element), 0);
  }, [zoom]);
  useLayoutEffect(() => {
    const element = box.current;
    if (!element || !last.current) {
      return;
    }
    paint(measure(element), 0);
  }, [days]);
  useEffect(() => {
    const element = box.current;
    if (!element) {
      return void 0;
    }
    element.addEventListener("scrollend", settle);
    return () => {
      element.removeEventListener("scrollend", settle);
      if (quiet.current !== null) {
        clearTimeout(quiet.current);
      }
      arrive(false);
    };
  }, []);
  const scrolled = /* @__PURE__ */ __name((element) => {
    const track = measure(element);
    paint(track, last.current ? track.left - last.current.left : 0);
    if (quiet.current !== null) {
      clearTimeout(quiet.current);
    }
    quiet.current = setTimeout(settle, SETTLE_MS);
    wait();
  }, "scrolled");
  const go = /* @__PURE__ */ __name((target) => {
    const element = box.current;
    arrive(false);
    if (!element) {
      return Promise.resolve(false);
    }
    const track = measure(element);
    const left = targetLeft(target, track);
    if (Math.abs(left - track.left) < 1 || still()) {
      place(element, left, false);
      return Promise.resolve(true);
    }
    return new Promise((resolve) => {
      arriving.current = { target, resolve, timer: null };
      wait();
      place(element, left, true);
    });
  }, "go");
  control.current = { go };
  const page = /* @__PURE__ */ __name((direction) => {
    const element = box.current;
    if (!element) {
      return;
    }
    const track = measure(element);
    if (direction < 0 && !track.back || direction > 0 && !track.ahead) {
      return;
    }
    place(element, pageLeft(track.left, direction, track), true);
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
    place(element, scrubLeft(at, grip.grab, measure(element), days), false);
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
          onScroll: (event) => scrolled(event.currentTarget),
          children
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
          lineNumber: 462,
          columnNumber: 17
        }
      ),
      ways.back ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailfade dim-cal__trailfade--back", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
        lineNumber: 470,
        columnNumber: 30
      }) : null,
      ways.ahead ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailfade dim-cal__trailfade--ahead", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
        lineNumber: 471,
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
            lineNumber: 481,
            columnNumber: 25
          })
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
          lineNumber: 473,
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
            lineNumber: 493,
            columnNumber: 25
          })
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
          lineNumber: 485,
          columnNumber: 21
        }
      ) : null
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
      lineNumber: 461,
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
            lineNumber: 512,
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
              lineNumber: 514,
              columnNumber: 25
            }
          ) : null
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
          lineNumber: 511,
          columnNumber: 17
        })
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
        lineNumber: 497,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailScroll.tsx",
    lineNumber: 460,
    columnNumber: 9
  });
}, "TrailScroll");
var TrailScroll_default = TrailScroll;
export {
  ARRIVE_MS,
  SETTLE_MS,
  TrailScroll_default as default,
  still
};
//# sourceMappingURL=TrailScroll.dev.js.map
