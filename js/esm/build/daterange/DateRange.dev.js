var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The one calendar of the showcase, on both shells: a start box and an end box over the months, inside the date
 * button's layer.
 *
 * The markup is the template category_daterange's, hook for hook (scss/category.scss and the Behat steps read
 * those hooks); the rules are ./model's and there is no timezone arithmetic (./calendar). The layer around it -
 * the heading, the quick options, the foot with "Clear period" and "Show" - is the server's (category_toolbar),
 * and the layer opens and closes as a native disclosure the filter line places: nothing here opens or shuts.
 *
 * It is mounted by the listing's mountCalendar(), on both shells, as a node APPENDED
 * to the layer's empty dim-daterange region: core's react_autoinit observes added nodes and never
 * attributes. The props are what the server resolved and the client may not compute: id, firstday,
 * today, from, to. It talks to the listing through events on that region, never through a callback:
 *
 * - it sends `dim-daterange:mounted` once it is drawn, and only then does the listing hide the two
 *   native date fields, which stay the form's transport;
 * - it sends `dim-daterange:pick`, detail {from, to}, whenever the period in force changes: on every press
 *   of a day and every clear, so the list is refreshed at once while the layer stays open;
 * - it takes `dim-daterange:set`, detail {from, to}, the period the address says, silently.
 *
 * Without the day the site calls today it draws nothing and says nothing ({@see DateRange}).
 *
 * @module     theme_dimensions/daterange/DateRange
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import Pending from "@moodle/lms/core/pending";
import { getStrings } from "@moodle/lms/core/stringUtils";
import { load } from "../amd";
import { WEEK, firstOf, monthAt, parse, say, shift, shiftMonth, weekdays, weeks } from "./calendar";
import { FROM, TO } from "./model";
import * as State from "./model";
import { CALENDAR } from "../listing/events";
const EVENTS = CALENDAR;
const REGION = '[data-region="dim-daterange"]';
const DAY = '[data-region="dim-daterange-day"]';
const COMPONENT = "theme_dimensions";
const KEYS = [
  "category_daterange",
  "category_datefrom",
  "category_dateto",
  "category_dateclearfrom",
  "category_dateclearto",
  "category_dateprevmonth",
  "category_datenextmonth",
  "category_datechoosefrom",
  "category_datechooseto"
];
const WIDE = "(min-width: 768px)";
const MEDIUM = { dateStyle: "medium" };
const stepOf = /* @__PURE__ */ __name((key, iso) => {
  switch (key) {
    case "ArrowLeft":
      return shift(iso, -1);
    case "ArrowRight":
      return shift(iso, 1);
    case "ArrowUp":
      return shift(iso, -WEEK);
    case "ArrowDown":
      return shift(iso, WEEK);
    case "PageUp":
      return shiftMonth(iso, -1);
    case "PageDown":
      return shiftMonth(iso, 1);
    default:
      return null;
  }
}, "stepOf");
const weekStart = /* @__PURE__ */ __name((value) => {
  const day = Number(value);
  return Number.isInteger(day) && day >= 0 && day < WEEK ? day : 0;
}, "weekStart");
const monthsFor = /* @__PURE__ */ __name((query) => query.matches ? 2 : 1, "monthsFor");
const report = /* @__PURE__ */ __name((failure) => load("core/notification").then((notification) => notification.exception(failure)), "report");
const blank = /* @__PURE__ */ __name((slot) => /* @__PURE__ */ jsxDEV("span", { className: "dim-daterange__day dim-daterange__day--blank", role: "gridcell" }, `blank-${slot}`, false, {
  fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
  lineNumber: 166,
  columnNumber: 5
}), "blank");
const Calendar = /* @__PURE__ */ __name(({ id, firstday, today, from, to }) => {
  const [strings, setStrings] = useState(null);
  const [model, setModel] = useState(() => State.initial(from, to, today));
  const [count, setCount] = useState(() => monthsFor(window.matchMedia(WIDE)));
  const [focusing, setFocusing] = useState(0);
  const state = useRef(model);
  const months = useRef(count);
  const hold = useRef(null);
  const root = useRef(null);
  const pop = useRef(null);
  const fromBox = useRef(null);
  const toBox = useRef(null);
  const weekstart = weekStart(firstday);
  const language = document.documentElement.lang || void 0;
  const update = /* @__PURE__ */ __name((next) => {
    state.current = next;
    setModel(next);
  }, "update");
  const focusDay = /* @__PURE__ */ __name(() => setFocusing((tick) => tick + 1), "focusDay");
  const tell = /* @__PURE__ */ __name((applied) => {
    update(applied.model);
    if (applied.changed && root.current) {
      root.current.dispatchEvent(new CustomEvent(EVENTS.pick, {
        bubbles: true,
        detail: { from: applied.model.from, to: applied.model.to }
      }));
    }
  }, "tell");
  useEffect(() => {
    hold.current = new Pending("theme_dimensions/daterange:mount");
    getStrings(KEYS.map((key) => ({ key, component: COMPONENT }))).then((values) => {
      setStrings(Object.fromEntries(KEYS.map((key, index) => [key, values[index]])));
      return values;
    }).catch((failure) => {
      hold.current?.resolve();
      hold.current = null;
      report(failure);
    });
    return () => {
      hold.current?.resolve();
      hold.current = null;
    };
  }, []);
  useLayoutEffect(() => {
    const element = root.current;
    if (!strings || !element) {
      return void 0;
    }
    const region = element.closest(REGION);
    const onSet = /* @__PURE__ */ __name((event) => {
      const detail = event.detail || {};
      update(State.set(state.current, detail.from, detail.to, months.current));
    }, "onSet");
    region?.addEventListener(EVENTS.set, onSet);
    element.dispatchEvent(new CustomEvent(EVENTS.mounted, { bubbles: true }));
    hold.current?.resolve();
    hold.current = null;
    return () => {
      region?.removeEventListener(EVENTS.set, onSet);
    };
  }, [strings]);
  useEffect(() => {
    const query = window.matchMedia(WIDE);
    const onWidth = /* @__PURE__ */ __name(() => {
      const next = monthsFor(query);
      months.current = next;
      setCount(next);
      update(State.widen(state.current, next));
      if (pop.current && pop.current.contains(document.activeElement)) {
        focusDay();
      }
    }, "onWidth");
    query.addEventListener("change", onWidth);
    return () => query.removeEventListener("change", onWidth);
  }, []);
  useLayoutEffect(() => {
    if (focusing === 0 || !pop.current) {
      return;
    }
    const cell = pop.current.querySelector(`[data-date="${state.current.focused}"]`) || pop.current.querySelector(DAY);
    cell?.focus();
  }, [focusing]);
  if (!strings) {
    return null;
  }
  const onBox = /* @__PURE__ */ __name((bound) => {
    update(State.setMode(state.current, bound, today, months.current));
    focusDay();
  }, "onBox");
  const onDay = /* @__PURE__ */ __name((iso) => {
    tell(State.press(state.current, iso));
    focusDay();
  }, "onDay");
  const onStep = /* @__PURE__ */ __name((offset) => {
    update(State.step(state.current, offset));
    focusDay();
  }, "onStep");
  const onClear = /* @__PURE__ */ __name((bound) => {
    tell(State.drop(state.current, bound));
    (bound === TO ? toBox : fromBox).current?.focus();
  }, "onClear");
  const onKey = /* @__PURE__ */ __name((event) => {
    const current = state.current;
    const target = stepOf(event.key, current.focused);
    const onday = event.target instanceof Element && event.target.closest(DAY);
    if (target === null || !onday) {
      return;
    }
    event.preventDefault();
    const next = State.moveTo(current, target, months.current);
    if (next) {
      update(next);
      focusDay();
    }
  }, "onKey");
  const onHover = /* @__PURE__ */ __name((iso) => {
    const next = State.hover(state.current, iso);
    if (next !== state.current) {
      update(next);
    }
  }, "onHover");
  const popid = `dim-daterange-pop-${id}`;
  const prompt = model.mode === TO ? strings.category_datechooseto : strings.category_datechoosefrom;
  const band = State.drawn(model);
  const banded = band.from !== "" && band.to !== "" && band.from !== band.to;
  const box = /* @__PURE__ */ __name((bound, label, clear) => {
    const value = bound === TO ? model.to : model.from;
    const active = bound === model.mode;
    return /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__box", children: [
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          ref: bound === TO ? toBox : fromBox,
          className: `dim-daterange__field${active ? " dim-daterange__field--active" : ""}`,
          type: "button",
          id: `dim-daterange-${id}-${bound}`,
          "data-action": "dim-daterange-open",
          "data-bound": bound,
          "aria-pressed": active ? "true" : "false",
          "aria-controls": popid,
          onClick: () => onBox(bound),
          children: [
            /* @__PURE__ */ jsxDEV("i", { className: "fa fa-calendar-days dim-daterange__icon", "aria-hidden": "true" }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
              lineNumber: 425,
              columnNumber: 21
            }),
            /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: `dim-daterange__label${value === "" ? "" : " visually-hidden"}`,
                "data-region": "dim-daterange-label",
                children: label
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
                lineNumber: 426,
                columnNumber: 21
              }
            ),
            /* @__PURE__ */ jsxDEV("span", { className: "dim-daterange__value", "data-region": "dim-daterange-value", children: say(value, MEDIUM, language) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
              lineNumber: 430,
              columnNumber: 21
            })
          ]
        },
        void 0,
        true,
        {
          fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
          lineNumber: 414,
          columnNumber: 17
        }
      ),
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          className: "dim-daterange__clear",
          type: "button",
          "data-action": "dim-daterange-clear",
          "data-bound": bound,
          hidden: value === "",
          onClick: () => onClear(bound),
          children: [
            /* @__PURE__ */ jsxDEV("i", { className: "fa fa-xmark", "aria-hidden": "true" }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
              lineNumber: 440,
              columnNumber: 21
            }),
            /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: clear }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
              lineNumber: 440,
              columnNumber: 71
            })
          ]
        },
        void 0,
        true,
        {
          fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
          lineNumber: 432,
          columnNumber: 17
        }
      )
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
      lineNumber: 413,
      columnNumber: 13
    });
  }, "box");
  const day = /* @__PURE__ */ __name((iso) => {
    const classes = ["dim-daterange__day"];
    if (banded && iso > band.from && iso < band.to) {
      classes.push("dim-daterange__day--between");
    }
    if (iso === band.from || iso === band.to) {
      classes.push("dim-daterange__day--end");
    }
    const inforce = iso === model.from || iso === model.to || model.from !== "" && model.to !== "" && iso > model.from && iso < model.to;
    return /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: classes.join(" "),
        role: "gridcell",
        "data-region": "dim-daterange-day",
        "data-date": iso,
        "aria-label": say(iso, { dateStyle: "long" }, language),
        tabIndex: iso === model.focused ? 0 : -1,
        "aria-selected": inforce ? "true" : "false",
        "aria-current": iso === today ? "date" : void 0,
        onClick: () => onDay(iso),
        onPointerEnter: () => onHover(iso),
        children: String(Number(iso.slice(8)))
      },
      iso,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
        lineNumber: 468,
        columnNumber: 13
      }
    );
  }, "day");
  const month = /* @__PURE__ */ __name((index) => {
    const hidden = index >= count;
    const shown = monthAt(model.cursor, index);
    const captionid = `${popid}-month-${index}`;
    return /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__monthblock", "data-region": "dim-daterange-monthblock", hidden, children: [
      /* @__PURE__ */ jsxDEV("span", { className: "dim-daterange__month", id: captionid, "data-region": "dim-daterange-month", "aria-live": "polite", children: hidden ? "" : say(firstOf(shown), { year: "numeric", month: "long" }, language) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
        lineNumber: 499,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(
        "div",
        {
          className: "dim-daterange__grid",
          "data-region": "dim-daterange-grid",
          role: hidden ? void 0 : "grid",
          "aria-labelledby": hidden ? void 0 : captionid,
          children: [
            hidden ? null : /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__row dim-daterange__row--head", role: "row", children: weekdays(weekstart).map((name) => /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: "dim-daterange__dow",
                role: "columnheader",
                "aria-label": say(name, { weekday: "long" }, language),
                children: say(name, { weekday: "narrow" }, language)
              },
              name,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
                lineNumber: 511,
                columnNumber: 33
              }
            )) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
              lineNumber: 509,
              columnNumber: 25
            }),
            hidden ? null : weeks(shown, weekstart).map((row, rowindex) => /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__row", role: "row", children: row.map((iso, slot) => iso === "" ? blank(slot) : day(iso)) }, `${firstOf(shown)}-${rowindex}`, false, {
              fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
              lineNumber: 521,
              columnNumber: 25
            }))
          ]
        },
        void 0,
        true,
        {
          fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
          lineNumber: 502,
          columnNumber: 17
        }
      )
    ] }, index, true, {
      fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
      lineNumber: 498,
      columnNumber: 13
    });
  }, "month");
  const set = model.from !== "" || model.to !== "" ? " dim-daterange--set" : "";
  return /* @__PURE__ */ jsxDEV("div", { ref: root, className: `dim-daterange${set}`, "data-region": "dim-daterange-field", onKeyDown: onKey, children: [
    /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__boxes", role: "group", "aria-label": strings.category_daterange, children: [
      box(FROM, strings.category_datefrom, strings.category_dateclearfrom),
      box(TO, strings.category_dateto, strings.category_dateclearto)
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
      lineNumber: 533,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { ref: pop, className: "dim-daterange__pop", id: popid, "data-region": "dim-daterange-pop", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__nav", children: [
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            className: "dim-daterange__step",
            type: "button",
            "data-action": "dim-daterange-prev",
            onClick: () => onStep(-1),
            children: [
              /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-left", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
                lineNumber: 545,
                columnNumber: 25
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: strings.category_dateprevmonth }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
                lineNumber: 546,
                columnNumber: 25
              })
            ]
          },
          void 0,
          true,
          {
            fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
            lineNumber: 539,
            columnNumber: 21
          }
        ),
        /* @__PURE__ */ jsxDEV("p", { className: "dim-daterange__prompt", "data-region": "dim-daterange-prompt", "aria-live": "polite", children: prompt }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
          lineNumber: 548,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            className: "dim-daterange__step",
            type: "button",
            "data-action": "dim-daterange-next",
            onClick: () => onStep(1),
            children: [
              /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-right", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
                lineNumber: 555,
                columnNumber: 25
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: strings.category_datenextmonth }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
                lineNumber: 556,
                columnNumber: 25
              })
            ]
          },
          void 0,
          true,
          {
            fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
            lineNumber: 549,
            columnNumber: 21
          }
        )
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
        lineNumber: 538,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__months", onPointerLeave: () => onHover(""), children: [
        month(0),
        month(1)
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
        lineNumber: 559,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
      lineNumber: 537,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
    lineNumber: 532,
    columnNumber: 9
  });
}, "Calendar");
const DateRange = /* @__PURE__ */ __name((props) => parse(props.today) ? /* @__PURE__ */ jsxDEV(Calendar, { ...props }, void 0, false, {
  fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
  lineNumber: 577,
  columnNumber: 68
}) : null, "DateRange");
var DateRange_default = DateRange;
export {
  EVENTS,
  DateRange_default as default
};
//# sourceMappingURL=DateRange.dev.js.map
