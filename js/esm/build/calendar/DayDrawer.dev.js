var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * One day's items: the drawer beside the grid on a desktop, the agenda sheet under it on a phone
 * (docs/calendar/PROPOSAL.md sections 13.2 and 13.3).
 *
 * A region labelled by its heading, the long date, which is aria-live so moving the selected day with the keyboard
 * while it is open says the new day. Not modal: the grid stays usable beside it. Enter on a day opens it with the
 * focus on its heading; Escape inside it, or its close button, closes it and gives the focus back to the day.
 *
 * - The drawer: an all-day strip (the bars covering the day, whole-day items, event courses), then the hour column
 *   with the overlap layout (calendar/overlap.ts). The column spans only the day's busy hours (overlap.ts
 *   hourWindow()) and is never a scroll box of its own.
 * - The sheet: the same content as a list, no hour column.
 *
 * An overlapping item carries a visible label as well as its column. Every item is a link to its own page that
 * opens the popover on a press (the page's delegated listener), as the grid's items do. Handed onCreate (the page
 * offers "New event"), a double-click on an hour row of the column opens core's form at that hour; the "New event"
 * "+" button in its head is the keyboard's way. On a day with nothing on it the column is still drawn while creation
 * is offered (the working hours, EMPTY_WINDOW), under the "nothing on this day" line.
 *
 * @module     theme_dimensions/calendar/DayDrawer
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useLayoutEffect, useMemo, useRef } from "react";
import { say } from "../daterange/calendar";
import { colourStyle, keyClass, placeOf, whenOf } from "./look";
import { itemHref } from "./model";
import { dayPlan, hourWindow, itemKey } from "./overlap";
const LONG_DAY = { weekday: "long", day: "numeric", month: "long", year: "numeric" };
const SPAN_DAY = { day: "numeric", month: "short" };
const EMPTY_WINDOW = { from: 8, to: 18 };
const HEADING = "dim-cal-day-heading";
const hourLabels = /* @__PURE__ */ __name((language) => {
  const format = new Intl.DateTimeFormat(language, { hour: "2-digit", minute: "2-digit", timeZone: "UTC" });
  return Array.from({ length: 24 }, (unused, hour) => format.format(new Date(Date.UTC(1970, 0, 1, hour))));
}, "hourLabels");
const rows = /* @__PURE__ */ __name((span) => ({ "--dim-cal-hourcount": String(span.to - span.from) }), "rows");
const position = /* @__PURE__ */ __name((laid, look, span) => {
  const from = span.from * 60;
  const total = (span.to - span.from) * 60;
  const length = Math.max(laid.entry.item.timeduration / 60, 0);
  const start = Math.max(from, look.zone.minutes(laid.entry.item.timestart));
  const end = Math.min(span.to * 60, look.zone.minutes(laid.entry.item.timestart) + length);
  return {
    "--dim-cal-top": `${(start - from) / total * 100}%`,
    "--dim-cal-height": `${Math.max(end - start, 0) / total * 100}%`,
    "--dim-cal-share-left": String(laid.column / laid.columns),
    "--dim-cal-share-width": String(1 / laid.columns)
  };
}, "position");
const link = /* @__PURE__ */ __name((entry, when, overlapping, look, extra, style = {}) => {
  const place = placeOf(entry.item, look);
  return /* @__PURE__ */ jsxDEV(
    "a",
    {
      className: `dim-cal__dayitem ${keyClass(entry.item)} ${extra}${overlapping ? " dim-cal__dayitem--overlap" : ""}`,
      href: itemHref(entry.item, look.courses, look.home),
      style: { ...colourStyle(entry.item, look), ...style },
      "data-cal-pop": "",
      "data-source": entry.item.source,
      "data-id": entry.item.id,
      children: [
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__daywhen", children: when }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
          lineNumber: 143,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__dayname", children: entry.item.name }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
          lineNumber: 144,
          columnNumber: 13
        }),
        place !== "" ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__dayplace", children: place }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
          lineNumber: 145,
          columnNumber: 29
        }) : null,
        overlapping ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__overlapmark", children: look.strings.calendar_overlapmark }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
          lineNumber: 146,
          columnNumber: 28
        }) : null
      ]
    },
    itemKey(entry.item),
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
      lineNumber: 134,
      columnNumber: 9
    }
  );
}, "link");
const DayDrawer = /* @__PURE__ */ __name(({ look, iso, placed, variant, focusing, action, onCreate, onClose }) => {
  const heading = useRef(null);
  const plan = useMemo(() => dayPlan(iso, placed, look.zone), [iso, placed, look.zone]);
  const labels = useMemo(() => hourLabels(look.language), [look.language]);
  const span = useMemo(
    () => plan.hours.length ? hourWindow(plan.hours, look.zone) : EMPTY_WINDOW,
    [plan, look.zone]
  );
  useLayoutEffect(() => {
    if (focusing) {
      heading.current?.focus();
    }
  }, [focusing]);
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Escape" && !event.defaultPrevented) {
      event.preventDefault();
      onClose();
    }
  }, "onKeyDown");
  const onDoubleClick = /* @__PURE__ */ __name((event) => {
    const row = event.target instanceof Element ? event.target.closest("[data-hour]") : null;
    if (onCreate && row) {
      event.preventDefault();
      onCreate(Number(row.dataset.hour));
    }
  }, "onDoubleClick");
  const empty = !plan.allday.length && !plan.hours.length;
  const allday = /* @__PURE__ */ __name((entry) => {
    if (entry.first === entry.last) {
      return look.strings.calendar_allday;
    }
    return `${say(entry.first, SPAN_DAY, look.language)}\u2013${say(entry.last, SPAN_DAY, look.language)}`;
  }, "allday");
  return /* @__PURE__ */ jsxDEV(
    "section",
    {
      className: `dim-cal__drawer dim-cal__drawer--${variant}`,
      role: "region",
      "aria-labelledby": HEADING,
      "data-region": "dim-cal-day",
      "data-day": iso,
      onKeyDown,
      children: [
        /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__drawerhead", children: [
          /* @__PURE__ */ jsxDEV("h2", { ref: heading, className: "dim-cal__drawertitle", id: HEADING, tabIndex: -1, "aria-live": "polite", children: say(iso, LONG_DAY, look.language) }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
            lineNumber: 223,
            columnNumber: 17
          }),
          action,
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "btn btn-icon dim-cal__close",
              "aria-label": look.strings.calendar_close,
              "data-action": "dim-cal-day-close",
              onClick: onClose,
              children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-xmark", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
                lineNumber: 234,
                columnNumber: 21
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
              lineNumber: 227,
              columnNumber: 17
            }
          )
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
          lineNumber: 222,
          columnNumber: 13
        }),
        empty ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__dayempty", children: look.strings.calendar_dayempty }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
          lineNumber: 237,
          columnNumber: 22
        }) : null,
        plan.allday.length ? /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__allday", "data-region": "dim-cal-allday", children: plan.allday.map((entry) => link(entry, allday(entry), false, look, "dim-cal__dayitem--allday")) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
          lineNumber: 239,
          columnNumber: 17
        }) : null,
        variant === "drawer" && (plan.hours.length || onCreate) ? /* @__PURE__ */ jsxDEV(
          "div",
          {
            className: "dim-cal__hours",
            "data-region": "dim-cal-hours",
            style: rows(span),
            onDoubleClick: onCreate ? onDoubleClick : void 0,
            children: [
              labels.slice(span.from, span.to).map((label, hour) => /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__hour", "data-hour": span.from + hour, "aria-hidden": "true", children: label }, span.from + hour, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
                lineNumber: 251,
                columnNumber: 25
              })),
              plan.hours.map((laid) => link(
                laid.entry,
                whenOf(laid.entry.item, look),
                laid.overlaps.length > 0,
                look,
                laid.entry.item.timeduration > 0 ? "dim-cal__dayitem--timed" : "dim-cal__dayitem--point",
                position(laid, look, span)
              ))
            ]
          },
          void 0,
          true,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
            lineNumber: 244,
            columnNumber: 17
          }
        ) : null,
        variant === "sheet" && plan.hours.length ? /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__daylist", "data-region": "dim-cal-hours", children: plan.hours.map((laid) => link(
          laid.entry,
          whenOf(laid.entry.item, look),
          laid.overlaps.length > 0,
          look,
          "dim-cal__dayitem--line"
        )) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
          lineNumber: 261,
          columnNumber: 17
        }) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/DayDrawer.tsx",
      lineNumber: 214,
      columnNumber: 9
    }
  );
}, "DayDrawer");
var DayDrawer_default = DayDrawer;
export {
  EMPTY_WINDOW,
  HEADING,
  DayDrawer_default as default
};
//# sourceMappingURL=DayDrawer.dev.js.map
