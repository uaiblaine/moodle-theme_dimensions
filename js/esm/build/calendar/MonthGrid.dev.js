var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The month grid and its keyboard (docs/calendar/PROPOSAL.md section 11.3).
 *
 * role="grid" labelled by the month's caption, a columnheader row of weekdays, then a row per week whose seven
 * gridcells each hold one day button. One day button is in the Tab order, the selected day's (roving tabindex):
 *
 * - ArrowLeft and ArrowRight move a day, ArrowUp and ArrowDown a week, Home and End to the week's first and last
 *   day; a move past the grid's days goes to that day's month (the page asks the range for it);
 * - PageUp and PageDown go to the previous and next month, on the same day number or the month's last;
 * - Enter and Space press the day button, which opens the day: the drawer beside the grid, or on a phone the sheet
 *   under it (Calendar.tsx);
 * - A click anywhere on a day but its controls (onGround handed) opens it as its button does, a convenience for the
 *   pointer: the button stays the keyboard's way in. A double-click there opens core's form for a new event on that
 *   day while the page offers "New event" (onCreate handed); the keyboard's way is the drawer's "New event";
 * - F2 on a day that has items moves into them: the first item of the day takes the focus, Tab and Shift+Tab cycle
 *   the day's items (its bars, its own items, its "+N"), Escape goes back to the day. The items are links and
 *   buttons out of the Tab order, so Tab from a day leaves the grid as from any other control.
 *
 * ONE ResizeObserver measures the grid, never per cell and never read on the mount path (its first entries are the
 * first measures): the rows' container, whose height decides the density (until then the stylesheet's height stands,
 * layout.ROW_DEFAULT), and the frame the grid shares with the drawer, whose width decides the phone layout
 * ({@see PHONE}). The frame, not the grid: the open drawer narrows the grid, and the grid's own width would flip the
 * layout to the phone's the moment the drawer opened.
 *
 * @module     theme_dimensions/calendar/MonthGrid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";
import { WEEK, say, shift, shiftMonth } from "../daterange/calendar";
import { ROW_DEFAULT, barSlots, dayCounts, weekLayout } from "./layout";
import { isWeekend } from "./days";
import WeekRow from "./WeekRow";
const PHONE = 576;
const WEEKDAY = { weekday: "short" };
const stepFrom = /* @__PURE__ */ __name((key, iso, week) => {
  switch (key) {
    case "ArrowLeft":
      return { day: shift(iso, -1), month: false };
    case "ArrowRight":
      return { day: shift(iso, 1), month: false };
    case "ArrowUp":
      return { day: shift(iso, -WEEK), month: false };
    case "ArrowDown":
      return { day: shift(iso, WEEK), month: false };
    case "Home":
      return { day: week[0], month: false };
    case "End":
      return { day: week[week.length - 1], month: false };
    case "PageUp":
      return { day: shiftMonth(iso, -1), month: true };
    case "PageDown":
      return { day: shiftMonth(iso, 1), month: true };
    default:
      return null;
  }
}, "stepFrom");
const MonthGrid = /* @__PURE__ */ __name((props) => {
  const { look, month, weeks, placed, today, selected, focusing, captionid, dots, onSelect, onMonth, onOpen } = props;
  const [height, setHeight] = useState(ROW_DEFAULT);
  const [itemsday, setItemsday] = useState(null);
  const grid = useRef(null);
  const rows = useRef(null);
  const count = useRef(weeks.length);
  count.current = weeks.length;
  const width = useRef(props.onWidth);
  width.current = props.onWidth;
  const days = useMemo(() => weeks.flat(), [weeks]);
  const counts = useMemo(() => dayCounts(days, placed), [days, placed]);
  const slots = barSlots(height);
  const layouts = useMemo(() => weeks.map((week) => weekLayout(week, placed, slots)), [weeks, placed, slots]);
  useEffect(() => {
    const element = rows.current;
    if (!element || typeof ResizeObserver === "undefined") {
      return void 0;
    }
    const frame = grid.current?.parentElement || null;
    const observer = new ResizeObserver((entries) => {
      entries.forEach((entry) => {
        const box = entry.contentRect;
        if (entry.target === element && box.height > 0 && count.current > 0) {
          setHeight(Math.round(box.height / count.current));
        }
        if (entry.target === frame && box.width > 0) {
          width.current(box.width);
        }
      });
    });
    observer.observe(element);
    if (frame) {
      observer.observe(frame);
    }
    return () => observer.disconnect();
  }, []);
  useLayoutEffect(() => {
    if (focusing) {
      grid.current?.querySelector(`[data-role="day"][data-day="${selected}"]`)?.focus();
    }
  }, [focusing]);
  const itemsOf = /* @__PURE__ */ __name((iso) => Array.from(grid.current?.querySelectorAll("[data-cal-item]") || []).filter((element) => (element.dataset.first || "") <= iso && iso <= (element.dataset.last || "")), "itemsOf");
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    const target = event.target;
    if (itemsday !== null && target.hasAttribute("data-cal-item")) {
      const items = itemsOf(itemsday);
      if (event.key === "Tab" && items.length) {
        event.preventDefault();
        const index = items.indexOf(target);
        items[(index + (event.shiftKey ? items.length - 1 : 1)) % items.length].focus();
      } else if (event.key === "Escape") {
        event.preventDefault();
        const day = itemsday;
        setItemsday(null);
        grid.current?.querySelector(`[data-role="day"][data-day="${day}"]`)?.focus();
      }
      return;
    }
    if (target.dataset.role !== "day") {
      return;
    }
    const iso = target.dataset.day || selected;
    if (event.key === "F2") {
      const items = itemsOf(iso);
      if (items.length) {
        event.preventDefault();
        setItemsday(iso);
        items[0].focus();
      }
      return;
    }
    const week = weeks.find((row) => row.includes(iso)) || weeks[0];
    const step = stepFrom(event.key, iso, week);
    if (!step || step.day === "") {
      return;
    }
    event.preventDefault();
    if (step.month || !days.includes(step.day)) {
      onMonth(step.day);
    } else {
      onSelect(step.day);
    }
  }, "onKeyDown");
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      ref: grid,
      className: `dim-cal__grid${dots ? " dim-cal__grid--phone" : ""}`,
      role: "grid",
      "aria-labelledby": captionid,
      "data-region": "dim-cal-grid",
      onKeyDown,
      onBlur: (event) => {
        if (!grid.current?.contains(event.relatedTarget)) {
          setItemsday(null);
        }
      },
      children: [
        /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__weekdays", role: "row", children: weeks[0].map((iso) => /* @__PURE__ */ jsxDEV(
          "div",
          {
            className: `dim-cal__weekday${isWeekend(iso) ? " dim-cal__weekday--weekend" : ""}`,
            role: "columnheader",
            children: say(iso, WEEKDAY, look.language)
          },
          iso,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/MonthGrid.tsx",
            lineNumber: 235,
            columnNumber: 21
          }
        )) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/MonthGrid.tsx",
          lineNumber: 233,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("div", { ref: rows, className: "dim-cal__weeks", role: "rowgroup", children: weeks.map((week, index) => /* @__PURE__ */ jsxDEV(
          WeekRow,
          {
            week,
            layout: layouts[index],
            height,
            month,
            today,
            selected,
            counts,
            look,
            dots,
            onOpen,
            onGround: props.onGround,
            onCreate: props.onCreate
          },
          week[0],
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/MonthGrid.tsx",
            lineNumber: 244,
            columnNumber: 21
          }
        )) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/MonthGrid.tsx",
          lineNumber: 242,
          columnNumber: 13
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/MonthGrid.tsx",
      lineNumber: 220,
      columnNumber: 9
    }
  );
}, "MonthGrid");
var MonthGrid_default = MonthGrid;
export {
  PHONE,
  MonthGrid_default as default,
  stepFrom
};
//# sourceMappingURL=MonthGrid.dev.js.map
