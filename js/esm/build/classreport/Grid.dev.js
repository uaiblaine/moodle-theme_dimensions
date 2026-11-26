var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The class report's grid: the shared bounded grid (datareport/Grid) with the report's cells.
 *
 * The lead column is the learner, their name linked to their breakdown as the report formats it, and the
 * identity fields the reader's report shows under it; every other column is a competency, its cell the
 * report's rating label, which a grader's press opens core's grading dialogue from (classratings.js).
 *
 * @module     theme_dimensions/classreport/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Lines } from "../datareport/cells";
import { Grid as DataGrid } from "../datareport/Grid";
import { LEAD, Learner, SIZING, cellHtml } from "./cells";
const Grid = /* @__PURE__ */ __name(({
  labels,
  columns,
  identity,
  rows,
  widths,
  sort,
  direction,
  onSort,
  onWidth,
  onWidthCommit
}) => /* @__PURE__ */ jsxDEV(
  DataGrid,
  {
    labels,
    columns,
    lead: LEAD,
    rows,
    widths,
    sizing: SIZING,
    sort,
    direction,
    renderLead: (row) => /* @__PURE__ */ jsxDEV(Learner, { row, identity }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Grid.tsx",
      lineNumber: 64,
      columnNumber: 30
    }),
    renderCell: (row, column) => /* @__PURE__ */ jsxDEV(Lines, { parts: [cellHtml(row, column.key)] }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Grid.tsx",
      lineNumber: 65,
      columnNumber: 38
    }),
    onSort,
    onWidth,
    onWidthCommit
  },
  void 0,
  false,
  {
    fileName: "public/theme/dimensions/js/esm/src/classreport/Grid.tsx",
    lineNumber: 55,
    columnNumber: 5
  }
), "Grid");
export {
  Grid
};
//# sourceMappingURL=Grid.dev.js.map
