var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * One day of the grid: its ground, its day button, the bars that start in it, and its own items at their density.
 *
 * The cell is the row's gridcell and lends its children to the week row's grid (display: contents), so a bar it
 * holds can span the following days' columns. The day button is the grid's one Tab stop when the day is the
 * selected one (roving tabindex); its label is the long date, the count of every item touching the day and "today"
 * when it is. The content draws one of three densities (calendar/layout.ts): a card, lines, or a summary ending in
 * "+N" with a dot per key of what was left out, a button that opens the day and names it. On a phone the content is
 * a dot per key with items instead (layout.ts dayDots()), hidden from the screen reader: the day button's label
 * says the count, and the day's sheet lists the items.
 *
 * @module     theme_dimensions/calendar/DayCell
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { say } from "../daterange/calendar";
import { itemHref } from "./model";
import { colourStyle, keyClass, placeOf, whenOf } from "./look";
import { fill } from "./strings";
const LONG_DAY = { weekday: "long", day: "numeric", month: "long", year: "numeric" };
const SHORT_DAY = { day: "numeric", month: "long" };
const countText = /* @__PURE__ */ __name((look, count) => {
  if (count === 0) {
    return look.strings.calendar_dayitems_none;
  }
  return count === 1 ? look.strings.calendar_dayitems_one : fill(look.strings.calendar_dayitems_many, count);
}, "countText");
const dayLabel = /* @__PURE__ */ __name((look, iso, count, istoday) => {
  const label = fill(look.strings.calendar_daylabel, { date: say(iso, LONG_DAY, look.language), count: countText(look, count) });
  return istoday ? `${label}, ${look.strings.calendar_daytoday}` : label;
}, "dayLabel");
const line = /* @__PURE__ */ __name((entry, iso, look) => /* @__PURE__ */ jsxDEV(
  "a",
  {
    className: `dim-cal__line ${keyClass(entry.item)}${entry.item.overdue ? " dim-cal__line--overdue" : ""}`,
    href: itemHref(entry.item, look.courses, look.home),
    style: colourStyle(entry.item, look),
    tabIndex: -1,
    "data-cal-item": "",
    "data-cal-pop": "",
    "data-source": entry.item.source,
    "data-id": entry.item.id,
    "data-first": iso,
    "data-last": iso,
    children: [
      /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__dot", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
        lineNumber: 112,
        columnNumber: 9
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__time", children: look.zone.time(entry.item.timestart) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
        lineNumber: 113,
        columnNumber: 9
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__name", children: entry.item.name }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
        lineNumber: 114,
        columnNumber: 9
      })
    ]
  },
  `${entry.item.source}-${entry.item.id}`,
  true,
  {
    fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
    lineNumber: 99,
    columnNumber: 5
  }
), "line");
const DayCell = /* @__PURE__ */ __name(({ iso, column, month, today, selected, count, plan, contentRow, look, bars, dots, onOpen }) => {
  const other = !iso.startsWith(month);
  const istoday = iso === today;
  const ground = ["dim-cal__ground"];
  if (other) {
    ground.push("dim-cal__ground--other");
  }
  const numclasses = ["dim-cal__daynum"];
  if (other) {
    numclasses.push("dim-cal__daynum--other");
  }
  if (istoday) {
    numclasses.push("dim-cal__daynum--today");
  }
  const card = plan.density === "card" ? plan.shown[0].item : null;
  const place = card ? placeOf(card, look) : "";
  return /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__day", role: "gridcell", "data-day": iso, children: [
    /* @__PURE__ */ jsxDEV("div", { className: ground.join(" "), style: { gridColumn: column + 1, gridRow: "1 / -1" }, "aria-hidden": "true" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
      lineNumber: 142,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: numclasses.join(" "),
        style: { gridColumn: column + 1, gridRow: 1 },
        tabIndex: selected ? 0 : -1,
        "aria-label": dayLabel(look, iso, count, istoday),
        "aria-current": istoday ? "date" : void 0,
        "data-role": "day",
        "data-day": iso,
        onClick: () => onOpen(iso),
        children: Number(iso.slice(8, 10))
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
        lineNumber: 143,
        columnNumber: 13
      }
    ),
    bars,
    dots ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__daydots", style: { gridColumn: column + 1, gridRow: 2 }, "aria-hidden": "true", children: [
      dots.keys.map((key) => /* @__PURE__ */ jsxDEV("span", { className: `dim-cal__daydot dim-cal-key--${key}` }, key, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
        lineNumber: 157,
        columnNumber: 45
      })),
      dots.more ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__daydotmore", children: "+" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
        lineNumber: 158,
        columnNumber: 34
      }) : null
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
      lineNumber: 156,
      columnNumber: 17
    }) : null,
    dots || plan.density === "none" ? null : /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__content", style: { gridColumn: column + 1, gridRow: contentRow }, children: [
      card ? /* @__PURE__ */ jsxDEV(
        "a",
        {
          className: `dim-cal__card ${keyClass(card)}`,
          href: itemHref(card, look.courses, look.home),
          style: colourStyle(card, look),
          tabIndex: -1,
          "data-cal-item": "",
          "data-cal-pop": "",
          "data-source": card.source,
          "data-id": card.id,
          "data-first": iso,
          "data-last": iso,
          children: [
            /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__cardmeta", children: whenOf(card, look) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
              lineNumber: 176,
              columnNumber: 29
            }),
            /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__cardtitle", children: card.name }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
              lineNumber: 177,
              columnNumber: 29
            }),
            place !== "" ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__cardmeta", children: place }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
              lineNumber: 178,
              columnNumber: 45
            }) : null
          ]
        },
        void 0,
        true,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
          lineNumber: 164,
          columnNumber: 25
        }
      ) : plan.shown.map((entry) => line(entry, iso, look)),
      plan.more ? /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "dim-cal__overflow",
          tabIndex: -1,
          "aria-label": fill(look.strings.calendar_moreaction, {
            count: plan.more,
            date: say(iso, SHORT_DAY, look.language)
          }),
          "data-cal-item": "",
          "data-first": iso,
          "data-last": iso,
          onClick: () => onOpen(iso),
          children: [
            fill(look.strings.calendar_morelabel, plan.more),
            /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__moredots", "aria-hidden": "true", children: plan.dots.map((key, index) => /* @__PURE__ */ jsxDEV("span", { className: `dim-cal-key--${key}` }, index, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
              lineNumber: 197,
              columnNumber: 64
            })) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
              lineNumber: 196,
              columnNumber: 29
            })
          ]
        },
        void 0,
        true,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
          lineNumber: 182,
          columnNumber: 25
        }
      ) : null
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
      lineNumber: 162,
      columnNumber: 17
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/DayCell.tsx",
    lineNumber: 141,
    columnNumber: 9
  });
}, "DayCell");
var DayCell_default = DayCell;
export {
  dayLabel,
  DayCell_default as default
};
//# sourceMappingURL=DayCell.dev.js.map
