var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The class report's cells: the width policy, a cell's HTML by key and the learner's lead.
 *
 * Every cell is the report's formatted HTML (class_ratings' formatters, format_row()): the learner's name
 * linked to their breakdown, the identity fields, and per competency the rating in its status label, linked to
 * core's page of the learner's competency, with the grading dialogue's hooks for a reader who may rate
 * (theme_dimensions/classratings opens the dialogue from them). Nothing is built here from a value: the HTML is
 * inserted as it came, the one sink of the view, and every label the view writes is text.
 *
 * @module     theme_dimensions/classreport/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { makeSizing } from "../datareport/sizing";
import { plain } from "../datareport/str";
const LEAD = "learner";
const WIDTHS = { learner: 260 };
const DEFAULT_WIDTH = 168;
const MINIMUMS = { learner: 160 };
const MINIMUM = 80;
const MAXIMUM = 800;
const SIZING = makeSizing(WIDTHS, DEFAULT_WIDTH, MINIMUMS, MINIMUM, MAXIMUM);
const cellHtml = /* @__PURE__ */ __name((row, key) => row.cells.find((cell) => cell.key === key)?.html ?? "", "cellHtml");
const IdentityLines = /* @__PURE__ */ __name(({ row, identity }) => /* @__PURE__ */ jsxDEV(Fragment, { children: identity.map((column) => {
  const html = cellHtml(row, column.key);
  return html === "" ? null : /* @__PURE__ */ jsxDEV(
    "span",
    {
      className: "dim-comp-rview-sub",
      title: plain(html),
      dangerouslySetInnerHTML: { __html: html }
    },
    column.key,
    false,
    {
      fileName: "public/theme/dimensions/js/esm/src/classreport/cells.tsx",
      lineNumber: 76,
      columnNumber: 17
    }
  );
}) }, void 0, false, {
  fileName: "public/theme/dimensions/js/esm/src/classreport/cells.tsx",
  lineNumber: 72,
  columnNumber: 5
}), "IdentityLines");
const Learner = /* @__PURE__ */ __name(({ row, identity }) => {
  const html = cellHtml(row, LEAD);
  return /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-who", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-c", title: plain(html), dangerouslySetInnerHTML: { __html: html } }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/cells.tsx",
      lineNumber: 98,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(IdentityLines, { row, identity }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/cells.tsx",
      lineNumber: 99,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/classreport/cells.tsx",
    lineNumber: 97,
    columnNumber: 9
  });
}, "Learner");
export {
  IdentityLines,
  LEAD,
  Learner,
  SIZING,
  cellHtml
};
//# sourceMappingURL=cells.dev.js.map
