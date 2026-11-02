var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The toolbar's view switch, Month and Week (docs/calendar/PROPOSAL.md section 21), on both shells.
 *
 * A group of two toggle buttons, the view shown pressed. Pressing the other one is the shell's: it keeps the selected
 * day and shows the week holding it, or its month.
 *
 * @module     theme_dimensions/calendar/ViewSwitch
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const ViewSwitch = /* @__PURE__ */ __name(({ strings, view, onView }) => /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__views", role: "group", "aria-label": strings.calendar_view, "data-region": "dim-cal-views", children: [
  /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className: "dim-cal__view",
      "aria-pressed": view === "month",
      "data-action": "dim-cal-view-month",
      onClick: () => onView("month"),
      children: strings.calendar_viewmonth
    },
    void 0,
    false,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/ViewSwitch.tsx",
      lineNumber: 45,
      columnNumber: 9
    }
  ),
  /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className: "dim-cal__view",
      "aria-pressed": view === "week",
      "data-action": "dim-cal-view-week",
      onClick: () => onView("week"),
      children: strings.calendar_viewweek
    },
    void 0,
    false,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/ViewSwitch.tsx",
      lineNumber: 52,
      columnNumber: 9
    }
  )
] }, void 0, true, {
  fileName: "public/theme/dimensions/js/esm/src/calendar/ViewSwitch.tsx",
  lineNumber: 44,
  columnNumber: 5
}), "ViewSwitch");
var ViewSwitch_default = ViewSwitch;
export {
  ViewSwitch_default as default
};
//# sourceMappingURL=ViewSwitch.dev.js.map
