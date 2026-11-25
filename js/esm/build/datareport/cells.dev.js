var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * A cell drawn from the server's formatted HTML, one fact per line.
 *
 * A data report's cells are formatted on the server, where the masking and the escaping ran, so a cell
 * is inserted as HTML and never built here: the one HTML sink of the view. The first line is the cell's
 * line and the others its sub-lines. Every line ends in an ellipsis, its whole text in its title.
 *
 * @module     theme_dimensions/datareport/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { plain } from "./str";
const Lines = /* @__PURE__ */ __name(({ parts }) => /* @__PURE__ */ jsxDEV(Fragment, { children: parts.map((part, index) => /* @__PURE__ */ jsxDEV(
  "span",
  {
    className: index === 0 ? "dim-comp-rview-c" : "dim-comp-rview-sub",
    title: plain(part),
    dangerouslySetInnerHTML: { __html: part }
  },
  index,
  false,
  {
    fileName: "public/theme/dimensions/js/esm/src/datareport/cells.tsx",
    lineNumber: 40,
    columnNumber: 13
  }
)) }, void 0, false, {
  fileName: "public/theme/dimensions/js/esm/src/datareport/cells.tsx",
  lineNumber: 38,
  columnNumber: 5
}), "Lines");
export {
  Lines
};
//# sourceMappingURL=cells.dev.js.map
