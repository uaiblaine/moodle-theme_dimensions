var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * A step's popover in the Trilhas view (docs/trail/PLAN.md section 7): its category, its name, "Course 3 of 5 · trail",
 * its dates and where they come from, its state and core's availability text for a learner, "View trail" and "Open
 * course".
 *
 * The event popover's behaviour (EventPopover.tsx), for a step: role="dialog" labelled by the name, not modal; anchored
 * to the bar that opened it by the same rule ({@see anchor}), a bottom sheet on a phone; the focus on its heading once
 * shown; Escape, the close button, a press outside, or Tab past its last control close it and give the focus back to
 * the bar. Its two layout reads are the opener's box and its own, on an opening.
 *
 * @module     theme_dimensions/calendar/TrailPopover
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { say } from "../daterange/calendar";
import { anchor } from "./EventPopover";
import { safeHref } from "./model";
import { fill } from "./strings";
const TITLE = "dim-cal-trailpop-title";
const DAY = { day: "numeric", month: "long", year: "numeric" };
const stateLabel = /* @__PURE__ */ __name((strings, state) => {
  switch (state) {
    case "done":
      return strings.landing_trail_state_done;
    case "current":
      return strings.landing_trail_state_current;
    case "late":
      return strings.landing_trail_state_late;
    case "locked":
      return strings.landing_trail_state_locked;
    case "upcoming":
      return strings.landing_trail_state_upcoming;
    default:
      return "";
  }
}, "stateLabel");
const stepWhen = /* @__PURE__ */ __name((step, look) => {
  const start = step.start > 0 ? say(look.zone.day(step.start), DAY, look.language) : "";
  const end = step.end > 0 ? say(look.zone.day(step.end), DAY, look.language) : "";
  if (start !== "" && end !== "") {
    return fill(look.strings.calendar_span, { from: start, to: end });
  }
  if (start !== "") {
    return fill(look.strings.calendar_trailfrom, start);
  }
  return end !== "" ? fill(look.strings.calendar_until, end) : "";
}, "stepWhen");
const stateLine = /* @__PURE__ */ __name((step, look, now) => {
  if (step.state === "current" && step.start > now) {
    return fill(look.strings.landing_trail_startson, say(look.zone.day(step.start), DAY, look.language));
  }
  return stateLabel(look.strings, step.state);
}, "stateLine");
const TrailPopover = /* @__PURE__ */ __name(({ look, trail, step, opener, sheet, now, onClose }) => {
  const dialog = useRef(null);
  const heading = useRef(null);
  const focused = useRef(0);
  const [at, setAt] = useState(null);
  useLayoutEffect(() => {
    if (sheet || !dialog.current) {
      setAt(null);
      return;
    }
    const box = opener.getBoundingClientRect();
    const own = dialog.current.getBoundingClientRect();
    const bar = document.querySelector(".navbar.fixed-top");
    setAt(anchor(
      box,
      { width: own.width, height: own.height },
      { width: window.innerWidth, height: window.innerHeight },
      bar ? Math.max(0, bar.getBoundingClientRect().bottom) : 0
    ));
  }, [step.cmid, opener, sheet]);
  useLayoutEffect(() => {
    if ((sheet || at !== null) && focused.current !== step.cmid) {
      focused.current = step.cmid;
      heading.current?.focus();
    }
  }, [sheet, at, step.cmid]);
  useEffect(() => {
    const outside = /* @__PURE__ */ __name((event) => {
      const target = event.target;
      if (target && !dialog.current?.contains(target) && !opener.contains(target)) {
        onClose(true);
      }
    }, "outside");
    document.addEventListener("mousedown", outside);
    return () => document.removeEventListener("mousedown", outside);
  }, [opener, onClose]);
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      event.stopPropagation();
      onClose(true);
      return;
    }
    if (event.key === "Tab" && !event.shiftKey) {
      const controls = Array.from(dialog.current?.querySelectorAll("a[href], button") || []);
      if (controls.length && event.target === controls[controls.length - 1]) {
        event.preventDefault();
        onClose(true);
      }
    }
  }, "onKeyDown");
  const category = look.categories.get(step.categoryid);
  const state = stateLine(step, look, now);
  const style = {};
  if (at) {
    style.top = `${at.top}px`;
    style.left = `${at.left}px`;
    if (at.maxHeight !== void 0) {
      style.maxHeight = `${at.maxHeight}px`;
      style.overflowY = "auto";
    }
  }
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      ref: dialog,
      className: "dim-cal__pop dim-cal__trailpop" + (sheet ? " dim-cal__pop--sheet" : "") + (!sheet && !at ? " dim-cal__pop--measuring" : ""),
      role: "dialog",
      "aria-labelledby": TITLE,
      "data-region": "dim-cal-trailpopover",
      "data-cmid": step.cmid,
      style,
      onKeyDown,
      children: [
        /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__pophead", children: [
          category ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__kind dim-cal__trailcategory", children: category.name }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 221,
            columnNumber: 29
          }) : /* @__PURE__ */ jsxDEV("span", {}, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 221,
            columnNumber: 109
          }),
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "btn btn-icon dim-cal__close",
              "aria-label": look.strings.calendar_close,
              "data-action": "dim-cal-pop-close",
              onClick: () => onClose(true),
              children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-xmark", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
                lineNumber: 229,
                columnNumber: 21
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
              lineNumber: 222,
              columnNumber: 17
            }
          )
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 220,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("h2", { ref: heading, className: "dim-cal__poptitle", id: TITLE, tabIndex: -1, children: step.name }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 232,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__popnote", "data-region": "dim-cal-trailposition", children: fill(look.strings.calendar_trailposition, {
          position: step.position,
          count: trail.steps.length,
          trail: trail.name
        }) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 233,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("dl", { className: "dim-cal__popfacts", children: [
          /* @__PURE__ */ jsxDEV("dt", { children: look.strings.calendar_when }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 241,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("dd", { children: [
            stepWhen(step, look),
            /* @__PURE__ */ jsxDEV("small", { className: "dim-cal__trailsource", children: step.expected ? look.strings.calendar_trailexpected : look.strings.calendar_trailcoursedates }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
              lineNumber: 244,
              columnNumber: 21
            })
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 242,
            columnNumber: 17
          }),
          step.coursename !== step.name ? /* @__PURE__ */ jsxDEV("dt", { children: look.strings.calendar_course }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 248,
            columnNumber: 50
          }) : null,
          step.coursename !== step.name ? /* @__PURE__ */ jsxDEV("dd", { children: step.coursename }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 249,
            columnNumber: 50
          }) : null
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 240,
          columnNumber: 13
        }),
        state !== "" ? /* @__PURE__ */ jsxDEV("p", { className: `dim-cal__trailstate dim-cal__trailstate--${step.state}`, "data-region": "dim-cal-trailstate", children: state }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 252,
          columnNumber: 17
        }) : null,
        step.state === "locked" && step.reason !== "" ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__popnote", "data-region": "dim-cal-trailreason", children: step.reason }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 257,
          columnNumber: 17
        }) : null,
        /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__popactions", children: [
          /* @__PURE__ */ jsxDEV("a", { className: "btn btn-primary", href: safeHref(step.url, look.home), children: look.strings.calendar_trailcourse }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 260,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("a", { className: "btn btn-secondary", href: safeHref(trail.url, look.home), children: look.strings.calendar_trailpage }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 261,
            columnNumber: 17
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 259,
          columnNumber: 13
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
      lineNumber: 209,
      columnNumber: 9
    }
  );
}, "TrailPopover");
var TrailPopover_default = TrailPopover;
export {
  TrailPopover_default as default,
  stateLabel,
  stateLine,
  stepWhen
};
//# sourceMappingURL=TrailPopover.dev.js.map
