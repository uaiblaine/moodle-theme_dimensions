var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * One week row of the grid: seven days, the bars in their slots, each day's content under them.
 *
 * The row is a grid of its own: a 30px row for the day numbers, one row per bar slot it draws, and the rest for the
 * days' content, so a bar never zigzags and a day's content starts under the lowest bar of its row. On a phone
 * (`dots` handed) the row draws no bar and no content: each day its number and its dot row.
 *
 * @module     theme_dimensions/calendar/WeekRow
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Bar from "./Bar";
import DayCell from "./DayCell";
import { HEADER, SLOT, cellPlan } from "./layout";
const WeekRow = /* @__PURE__ */ __name((props) => {
  const { week, layout, height, month, today, selected, counts, look, dots, onOpen, onGround, onCreate } = props;
  const used = dots ? 0 : layout.used;
  const rows = `${HEADER}px${used ? ` repeat(${used}, ${SLOT}px)` : ""} minmax(0, 1fr)`;
  return /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__week", role: "row", style: { gridTemplateRows: rows }, children: week.map((iso, column) => /* @__PURE__ */ jsxDEV(
    DayCell,
    {
      iso,
      column,
      month,
      today,
      selected: iso === selected,
      count: counts.get(iso) || 0,
      plan: cellPlan(layout.timed[column], layout.hidden[column], layout.used, height),
      contentRow: used + 2,
      look,
      dots: dots ? dots.get(iso) || { keys: [], more: false } : null,
      bars: dots ? null : layout.bars.filter((segment) => segment.start === column).map((segment) => /* @__PURE__ */ jsxDEV(
        Bar,
        {
          segment,
          week,
          look
        },
        `${segment.placed.item.source}-${segment.placed.item.id}`,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/WeekRow.tsx",
          lineNumber: 76,
          columnNumber: 25
        }
      )),
      onOpen,
      onGround,
      onCreate
    },
    iso,
    false,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/WeekRow.tsx",
      lineNumber: 63,
      columnNumber: 17
    }
  )) }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/WeekRow.tsx",
    lineNumber: 61,
    columnNumber: 9
  });
}, "WeekRow");
var WeekRow_default = WeekRow;
export {
  WeekRow_default as default
};
//# sourceMappingURL=WeekRow.dev.js.map
