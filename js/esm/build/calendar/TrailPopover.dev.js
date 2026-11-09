var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * A step's popover in the Trilhas view, the course card's dialog variant (moodle-dev/docs/course-card/CONTRACT.md,
 * section 9): its course's cover, its category over its name, its dates as the meta line, "Course 3 of 5 · trail" in the
 * plugin's slot, its state and core's availability text for a learner, "View trail" and the main action.
 *
 * The event popover's behaviour (EventPopover.tsx), for a step: role="dialog" labelled by the name, not modal; anchored
 * to the bar that opened it by the same rule ({@see anchor}), a bottom sheet on a phone; the focus on its heading once
 * shown; Escape, the close button, a press outside, or Tab past its last control close it and give the focus back to
 * the bar. Its two layout reads are the opener's box and its own, on an opening.
 *
 * - The cover is the step's course image (the answer's `image`, {@see safeImage()}: an address or the theme's SVG
 *   cover), an <img> with an empty alt; the category's band (look.ts categoryCover(), else the state's) is only the
 *   ground under it, which shows when the image is missing or fails to load. The close button sits on the cover where a
 *   card's star would: the course dialogue's dark disc at 32 px.
 * - Boost Union's card view off (`covers` false, calendar_page::props()): no cover; the category and the close button
 *   share a row at the top of the body, the close the calendar's standard icon button, since the disc is made to sit on
 *   an image; the popover keeps a top edge in the category's colour.
 * - A completed step: the state pill is the green seal, the cover gets a green base, and the main action reads "Review"
 *   instead of "Open course". There is no progress bar: the service carries no percentage per step (D11).
 * - The buttons are the calendar's own (D12): "View trail" btn-secondary, the main action btn-primary.
 *
 * @module     theme_dimensions/calendar/TrailPopover
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { say } from "../daterange/calendar";
import { anchor } from "./EventPopover";
import { categoryCover } from "./look";
import { safeHref, safeImage } from "./model";
import { fill } from "./strings";
const TITLE = "dim-cal-trailpop-title";
const DAY = { day: "numeric", month: "long", year: "numeric" };
const TrailIcon = /* @__PURE__ */ __name(({ size = 16 }) => /* @__PURE__ */ jsxDEV(
  "svg",
  {
    className: "dim-cal__trailicon",
    width: size,
    height: size,
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2",
    strokeLinecap: "round",
    "aria-hidden": "true",
    focusable: "false",
    children: [
      /* @__PURE__ */ jsxDEV("circle", { cx: "5", cy: "18", r: "2" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
        lineNumber: 91,
        columnNumber: 9
      }),
      /* @__PURE__ */ jsxDEV("circle", { cx: "19", cy: "6", r: "2" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
        lineNumber: 92,
        columnNumber: 9
      }),
      /* @__PURE__ */ jsxDEV("path", { d: "M7 18h6a4 4 0 0 0 0-8h-2a4 4 0 0 1 0-8h6" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
        lineNumber: 93,
        columnNumber: 9
      })
    ]
  },
  void 0,
  true,
  {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
    lineNumber: 79,
    columnNumber: 5
  }
), "TrailIcon");
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
const stepOpenEnd = /* @__PURE__ */ __name((step, look) => step.start > 0 && step.end <= 0 ? look.strings.calendar_trailopenfull : "", "stepOpenEnd");
const stateLine = /* @__PURE__ */ __name((step, look, now) => {
  if (step.state === "current" && step.start > now) {
    return fill(look.strings.landing_trail_startson, say(look.zone.day(step.start), DAY, look.language));
  }
  return stateLabel(look.strings, step.state);
}, "stateLine");
const mainLabel = /* @__PURE__ */ __name((step, strings) => step.state === "done" ? strings.category_cta_review : strings.calendar_trailcourse, "mainLabel");
const placed = /* @__PURE__ */ __name((at, covers, step, look) => {
  const style = covers ? {} : { ...categoryCover(step.categoryid, look) };
  if (at) {
    style.top = `${at.top}px`;
    style.left = `${at.left}px`;
    if (at.maxHeight !== void 0) {
      style.maxHeight = `${at.maxHeight}px`;
      style.overflowY = "auto";
    }
  }
  return style;
}, "placed");
const popClasses = /* @__PURE__ */ __name((sheet, measuring, covers, done) => "dim-cal__pop dim-cal__trailpop" + (sheet ? " dim-cal__pop--sheet" : "") + (measuring ? " dim-cal__pop--measuring" : "") + (covers ? "" : " dim-cal__trailpop--nocover") + (done ? " dim-cal__trailpop--done" : ""), "popClasses");
const StatePill = /* @__PURE__ */ __name(({ step, state }) => {
  const done = step.state === "done";
  const tone = step.state ? ` dim-cal__trailstate--${step.state}` : "";
  return /* @__PURE__ */ jsxDEV("p", { className: `dim-cal__trailstate${tone}` + (done ? " dim-cal__trailseal" : ""), "data-region": "dim-cal-trailstate", children: [
    done ? /* @__PURE__ */ jsxDEV("i", { className: "fa fa-check", "aria-hidden": "true" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
      lineNumber: 228,
      columnNumber: 21
    }) : null,
    state
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
    lineNumber: 227,
    columnNumber: 9
  });
}, "StatePill");
const Cover = /* @__PURE__ */ __name(({ step, look, image, failed, setFailed, onClose }) => {
  const done = step.state === "done";
  const tone = step.state ? ` dim-cal__trailstate--${step.state}` : "";
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      className: `dim-cal__popcover dim-cal__trailcover${tone}` + (done ? " dim-cal__trailcover--done" : ""),
      style: categoryCover(step.categoryid, look),
      "data-region": "dim-cal-trailcover",
      children: [
        image !== "" && image !== failed ? /* @__PURE__ */ jsxDEV("img", { className: "dim-cal__popcoverimg", src: image, alt: "", onError: () => setFailed(image) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 261,
          columnNumber: 17
        }) : null,
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "dim-cal__coverclose",
            "aria-label": look.strings.calendar_close,
            "data-action": "dim-cal-pop-close",
            onClick: () => onClose(true),
            children: /* @__PURE__ */ jsxDEV("span", { className: "btn-close", "aria-hidden": "true" }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
              lineNumber: 270,
              columnNumber: 17
            })
          },
          void 0,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 263,
            columnNumber: 13
          }
        )
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
      lineNumber: 255,
      columnNumber: 9
    }
  );
}, "Cover");
const TrailPopover = /* @__PURE__ */ __name(({ look, trail, step, opener, sheet, now, covers = true, onClose }) => {
  const dialog = useRef(null);
  const heading = useRef(null);
  const focused = useRef(0);
  const [at, setAt] = useState(null);
  const [failed, setFailed] = useState("");
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
  const image = covers ? safeImage(step.image) : "";
  const names = category ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailcategory", children: category.name }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
    lineNumber: 359,
    columnNumber: 30
  }) : null;
  const when = [stepWhen(step, look), stepOpenEnd(step, look)].filter((part) => part !== "").join(" \xB7 ");
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      ref: dialog,
      className: popClasses(sheet, !sheet && !at, covers, step.state === "done"),
      role: "dialog",
      "aria-labelledby": TITLE,
      "data-region": "dim-cal-trailpopover",
      "data-cmid": step.cmid,
      style: placed(at, covers, step, look),
      onKeyDown,
      children: [
        covers ? /* @__PURE__ */ jsxDEV(Cover, { step, look, image, failed, setFailed, onClose }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 374,
          columnNumber: 17
        }) : /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__traildialogtop", children: [
          names,
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
                lineNumber: 385,
                columnNumber: 25
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
              lineNumber: 378,
              columnNumber: 21
            }
          )
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 376,
          columnNumber: 17
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailpopnames", children: [
          covers ? names : null,
          /* @__PURE__ */ jsxDEV("h2", { ref: heading, className: "dim-cal__poptitle", id: TITLE, tabIndex: -1, children: step.name }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 391,
            columnNumber: 17
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 389,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__trailmeta", "data-region": "dim-cal-trailwhen", children: [
          /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: `${look.strings.calendar_when}: ` }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 394,
            columnNumber: 17
          }),
          when !== "" ? `${when} \xB7 ` : "",
          /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailsource", children: step.expected ? look.strings.calendar_trailexpected : look.strings.calendar_trailcoursedates }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 396,
            columnNumber: 17
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 393,
          columnNumber: 13
        }),
        step.coursename !== step.name ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__trailmeta", "data-region": "dim-cal-trailcoursename", children: [
          /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: `${look.strings.calendar_course}: ` }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 402,
            columnNumber: 21
          }),
          step.coursename
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 401,
          columnNumber: 17
        }) : null,
        /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__poprow dim-cal__trailextra", "data-region": "dim-cal-trailposition", children: [
          /* @__PURE__ */ jsxDEV(TrailIcon, {}, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 407,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { children: fill(look.strings.calendar_trailposition, {
            position: step.position,
            count: trail.steps.length,
            trail: trail.name
          }) }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 408,
            columnNumber: 17
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 406,
          columnNumber: 13
        }),
        state !== "" ? /* @__PURE__ */ jsxDEV(StatePill, { step, state }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 416,
          columnNumber: 29
        }) : null,
        step.state === "locked" && step.reason !== "" ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__popquiet", "data-region": "dim-cal-trailreason", children: step.reason }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 418,
          columnNumber: 17
        }) : null,
        /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__popactions dim-cal__trailpopactions", children: [
          /* @__PURE__ */ jsxDEV("a", { className: "btn btn-secondary", href: safeHref(trail.url, look.home), children: look.strings.calendar_trailpage }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 421,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("a", { className: "btn btn-primary", href: safeHref(step.url, look.home), children: mainLabel(step, look.strings) }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
            lineNumber: 422,
            columnNumber: 17
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
          lineNumber: 420,
          columnNumber: 13
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailPopover.tsx",
      lineNumber: 363,
      columnNumber: 9
    }
  );
}, "TrailPopover");
var TrailPopover_default = TrailPopover;
export {
  TrailIcon,
  TrailPopover_default as default,
  mainLabel,
  stateLabel,
  stateLine,
  stepOpenEnd,
  stepWhen
};
//# sourceMappingURL=TrailPopover.dev.js.map
