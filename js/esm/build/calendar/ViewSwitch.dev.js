var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The toolbar's view switch, Month and Week (docs/calendar/PROPOSAL.md section 21), Day on a phone (section 31), on
 * both shells, and Trilhas on the account's where the page offers it (docs/trail/PLAN.md section 7).
 *
 * A group of toggle buttons, the view shown pressed. Pressing another one is the shell's: it keeps the selected day and
 * shows the week holding it, its month, or the day itself. The choices are weeks.ts viewsOf(): the day only while the
 * frame is a phone's.
 *
 * @module     theme_dimensions/calendar/ViewSwitch
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { viewsOf } from "./weeks";
const nameOf = /* @__PURE__ */ __name((strings, view) => {
  if (view === "day") {
    return strings.calendar_viewday;
  }
  if (view === "trail") {
    return strings.calendar_viewtrail;
  }
  return view === "week" ? strings.calendar_viewweek : strings.calendar_viewmonth;
}, "nameOf");
const ViewButton = /* @__PURE__ */ __name(({ strings, one, view, onView }) => /* @__PURE__ */ jsxDEV(
  "button",
  {
    type: "button",
    className: "dim-cal__view",
    "aria-pressed": view === one,
    "data-action": `dim-cal-view-${one}`,
    onClick: () => onView(one),
    children: nameOf(strings, one)
  },
  void 0,
  false,
  {
    fileName: "public/theme/dimensions/js/esm/src/calendar/ViewSwitch.tsx",
    lineNumber: 69,
    columnNumber: 5
  }
), "ViewButton");
const ViewSwitch = /* @__PURE__ */ __name(({ strings, view, phone, trails = false, onView }) => /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__views", role: "group", "aria-label": strings.calendar_view, "data-region": "dim-cal-views", children: [
  viewsOf(phone).map((one) => /* @__PURE__ */ jsxDEV(ViewButton, { strings, one, view, onView }, one, false, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/ViewSwitch.tsx",
    lineNumber: 87,
    columnNumber: 13
  })),
  trails ? /* @__PURE__ */ jsxDEV(ViewButton, { strings, one: "trail", view, onView }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/ViewSwitch.tsx",
    lineNumber: 89,
    columnNumber: 19
  }) : null
] }, void 0, true, {
  fileName: "public/theme/dimensions/js/esm/src/calendar/ViewSwitch.tsx",
  lineNumber: 85,
  columnNumber: 5
}), "ViewSwitch");
var ViewSwitch_default = ViewSwitch;
export {
  ViewSwitch_default as default
};
//# sourceMappingURL=ViewSwitch.dev.js.map
