var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The one calendar of the showcase, on both shells: a start box and an end box over one popover.
 *
 * The markup is the template category_daterange's, hook for hook (scss/category.scss and the Behat steps read
 * those hooks); the rules are ./model's and there is no timezone arithmetic (./calendar).
 *
 * It is mounted by the listing's mountCalendar(), on both shells, as a node APPENDED
 * to the toolbar's empty dim-daterange region: core's react_autoinit observes added nodes and never
 * attributes. The props are what the server resolved and the client may not compute: id, firstday,
 * today, from, to. It talks to the listing through events on that region, never through a callback:
 *
 * - it sends `dim-daterange:mounted` once it is drawn, and only then does the listing hide the two
 *   native date fields, which stay the form's transport;
 * - it sends `dim-daterange:pick`, detail {from, to}, whenever the period in force changes;
 * - it takes `dim-daterange:set`, detail {from, to}, the period the address says, silently.
 *
 * Without the day the site calls today it draws nothing and says nothing, so the native fields stay
 * exactly as the server drew them: the browser's clock is the one answer this control may not use.
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
  "category_dateclear",
  "category_datedone",
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
  lineNumber: 165,
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
  const restore = /* @__PURE__ */ __name((bound) => {
    (bound === TO ? toBox : fromBox).current?.focus();
  }, "restore");
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
      update(State.set(state.current, detail.from, detail.to));
    }, "onSet");
    const onPointer = /* @__PURE__ */ __name((event) => {
      if (state.current.open && event.target instanceof Node && !element.contains(event.target)) {
        tell(State.commit(state.current));
      }
    }, "onPointer");
    region?.addEventListener(EVENTS.set, onSet);
    document.addEventListener("pointerdown", onPointer);
    element.dispatchEvent(new CustomEvent(EVENTS.mounted, { bubbles: true }));
    hold.current?.resolve();
    hold.current = null;
    return () => {
      region?.removeEventListener(EVENTS.set, onSet);
      document.removeEventListener("pointerdown", onPointer);
    };
  }, [strings]);
  useEffect(() => {
    const query = window.matchMedia(WIDE);
    const onWidth = /* @__PURE__ */ __name(() => {
      const next = monthsFor(query);
      months.current = next;
      setCount(next);
      if (state.current.open) {
        update(State.widen(state.current, next));
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
    const current = state.current;
    update(current.open ? State.setMode(current, bound, today, months.current) : State.open(current, bound, today, months.current));
    focusDay();
  }, "onBox");
  const onDay = /* @__PURE__ */ __name((iso) => {
    update(State.press(state.current, iso));
    focusDay();
  }, "onDay");
  const onStep = /* @__PURE__ */ __name((offset) => {
    update(State.step(state.current, offset));
    focusDay();
  }, "onStep");
  const onDone = /* @__PURE__ */ __name(() => {
    const current = state.current;
    restore(current.field);
    tell(State.commit(current));
  }, "onDone");
  const onClear = /* @__PURE__ */ __name((bound) => {
    tell(State.drop(state.current, bound));
    if (bound === FROM || bound === TO) {
      restore(bound);
    }
  }, "onClear");
  const onKey = /* @__PURE__ */ __name((event) => {
    const current = state.current;
    if (event.key === "Escape") {
      event.preventDefault();
      if (current.open) {
        update(State.close(current));
        restore(current.field);
      }
      return;
    }
    const target = stepOf(event.key, current.focused);
    const onday = event.target instanceof Element && event.target.closest(DAY);
    if (target === null || !current.open || !onday) {
      return;
    }
    event.preventDefault();
    const next = State.moveTo(current, target, months.current);
    if (next) {
      update(next);
      focusDay();
    }
  }, "onKey");
  const onBlur = /* @__PURE__ */ __name(() => {
    window.setTimeout(() => {
      const element = root.current;
      if (element && !element.contains(document.activeElement)) {
        update(State.close(state.current));
      }
    }, 0);
  }, "onBlur");
  const popid = `dim-daterange-pop-${id}`;
  const both = model.draftfrom !== "" && model.draftto !== "";
  let prompt = "";
  if (model.opened) {
    prompt = model.mode === TO ? strings.category_datechooseto : strings.category_datechoosefrom;
  }
  const box = /* @__PURE__ */ __name((bound, label, clear) => {
    const value = bound === TO ? model.draftto : model.draftfrom;
    const active = model.open && bound === model.mode ? " dim-daterange__field--active" : "";
    return /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__box", children: [
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          ref: bound === TO ? toBox : fromBox,
          className: `form-control dim-daterange__field${active}`,
          type: "button",
          id: `dim-daterange-${id}-${bound}`,
          "data-action": "dim-daterange-open",
          "data-bound": bound,
          "aria-expanded": model.open ? "true" : "false",
          "aria-controls": popid,
          onClick: () => onBox(bound),
          children: [
            /* @__PURE__ */ jsxDEV("i", { className: "fa fa-calendar-days dim-daterange__icon", "aria-hidden": "true" }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
              lineNumber: 481,
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
                lineNumber: 482,
                columnNumber: 21
              }
            ),
            /* @__PURE__ */ jsxDEV("span", { className: "dim-daterange__value", "data-region": "dim-daterange-value", children: say(value, MEDIUM, language) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
              lineNumber: 486,
              columnNumber: 21
            })
          ]
        },
        void 0,
        true,
        {
          fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
          lineNumber: 470,
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
              lineNumber: 496,
              columnNumber: 21
            }),
            /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: clear }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
              lineNumber: 496,
              columnNumber: 71
            })
          ]
        },
        void 0,
        true,
        {
          fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
          lineNumber: 488,
          columnNumber: 17
        }
      )
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
      lineNumber: 469,
      columnNumber: 13
    });
  }, "box");
  const day = /* @__PURE__ */ __name((iso) => {
    const edge = iso === model.draftfrom || iso === model.draftto;
    const inside = both && iso > model.draftfrom && iso < model.draftto;
    const classes = ["dim-daterange__day"];
    if (inside) {
      classes.push("dim-daterange__day--in");
    }
    if (edge) {
      classes.push("dim-daterange__day--edge");
    }
    if (both && model.draftfrom !== model.draftto && iso === model.draftfrom) {
      classes.push("dim-daterange__day--start");
    }
    if (both && model.draftfrom !== model.draftto && iso === model.draftto) {
      classes.push("dim-daterange__day--end");
    }
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
        "aria-selected": edge || inside ? "true" : "false",
        "aria-current": iso === today ? "date" : void 0,
        onClick: () => onDay(iso),
        children: String(Number(iso.slice(8)))
      },
      iso,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
        lineNumber: 529,
        columnNumber: 13
      }
    );
  }, "day");
  const month = /* @__PURE__ */ __name((index) => {
    const hidden = index >= count;
    const painted = model.opened && !hidden;
    const shown = monthAt(model.cursor, index);
    const captionid = `${popid}-month-${index}`;
    return /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__monthblock", "data-region": "dim-daterange-monthblock", hidden, children: [
      /* @__PURE__ */ jsxDEV("span", { className: "dim-daterange__month", id: captionid, "data-region": "dim-daterange-month", "aria-live": "polite", children: painted ? say(firstOf(shown), { year: "numeric", month: "long" }, language) : "" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
        lineNumber: 560,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(
        "div",
        {
          className: "dim-daterange__grid",
          "data-region": "dim-daterange-grid",
          role: painted ? "grid" : void 0,
          "aria-labelledby": painted ? captionid : void 0,
          children: [
            painted ? /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__row dim-daterange__row--head", role: "row", children: weekdays(weekstart).map((name) => /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: "dim-daterange__dow",
                role: "columnheader",
                "aria-label": say(name, { weekday: "long" }, language),
                children: say(name, { weekday: "short" }, language)
              },
              name,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
                lineNumber: 572,
                columnNumber: 33
              }
            )) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
              lineNumber: 570,
              columnNumber: 25
            }) : null,
            painted ? weeks(shown, weekstart).map((row, rowindex) => /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__row", role: "row", children: row.map((iso, slot) => iso === "" ? blank(slot) : day(iso)) }, `${firstOf(shown)}-${rowindex}`, false, {
              fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
              lineNumber: 582,
              columnNumber: 25
            })) : null
          ]
        },
        void 0,
        true,
        {
          fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
          lineNumber: 563,
          columnNumber: 17
        }
      )
    ] }, index, true, {
      fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
      lineNumber: 559,
      columnNumber: 13
    });
  }, "month");
  const set = model.draftfrom !== "" || model.draftto !== "" ? " dim-daterange--set" : "";
  return /* @__PURE__ */ jsxDEV("div", { ref: root, className: `dim-daterange${set}`, "data-region": "dim-daterange-field", onKeyDown: onKey, onBlur, children: [
    /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__boxes", role: "group", "aria-label": strings.category_daterange, children: [
      box(FROM, strings.category_datefrom, strings.category_dateclearfrom),
      box(TO, strings.category_dateto, strings.category_dateclearto)
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
      lineNumber: 594,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { ref: pop, className: "dim-daterange__pop", id: popid, "data-region": "dim-daterange-pop", hidden: !model.open, children: [
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
                lineNumber: 606,
                columnNumber: 25
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: strings.category_dateprevmonth }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
                lineNumber: 607,
                columnNumber: 25
              })
            ]
          },
          void 0,
          true,
          {
            fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
            lineNumber: 600,
            columnNumber: 21
          }
        ),
        /* @__PURE__ */ jsxDEV("p", { className: "dim-daterange__prompt", "data-region": "dim-daterange-prompt", "aria-live": "polite", children: prompt }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
          lineNumber: 609,
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
                lineNumber: 616,
                columnNumber: 25
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: strings.category_datenextmonth }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
                lineNumber: 617,
                columnNumber: 25
              })
            ]
          },
          void 0,
          true,
          {
            fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
            lineNumber: 610,
            columnNumber: 21
          }
        )
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
        lineNumber: 599,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__months", children: [
        month(0),
        month(1)
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
        lineNumber: 620,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "dim-daterange__footer", children: [
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            className: "btn btn-link btn-sm dim-daterange__footerclear",
            type: "button",
            "data-action": "dim-daterange-clear",
            onClick: () => onClear(""),
            children: strings.category_dateclear
          },
          void 0,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
            lineNumber: 625,
            columnNumber: 21
          }
        ),
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            className: "btn btn-primary btn-sm dim-daterange__done",
            type: "button",
            "data-action": "dim-daterange-done",
            onClick: onDone,
            children: strings.category_datedone
          },
          void 0,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
            lineNumber: 631,
            columnNumber: 21
          }
        )
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
        lineNumber: 624,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
      lineNumber: 598,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
    lineNumber: 593,
    columnNumber: 9
  });
}, "Calendar");
const DateRange = /* @__PURE__ */ __name((props) => parse(props.today) ? /* @__PURE__ */ jsxDEV(Calendar, { ...props }, void 0, false, {
  fileName: "public/theme/dimensions/js/esm/src/daterange/DateRange.tsx",
  lineNumber: 652,
  columnNumber: 68
}) : null, "DateRange");
var DateRange_default = DateRange;
export {
  EVENTS,
  DateRange_default as default
};
//# sourceMappingURL=DateRange.dev.js.map
