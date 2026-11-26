var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The class report's cards: the shared card view (datareport/Cards) with the report's cells.
 *
 * A card is the learner (their name and identity fields), one fixed line saying how many of the shown
 * competencies they are proficient in and how many are rated (from the row's typed ratings, the rows
 * answer's own figures), then a line per shown competency, its title and its rating label, behind "More" on
 * a narrow container.
 *
 * @module     theme_dimensions/classreport/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Cards as DataCards } from "../datareport/Cards";
import { Lines } from "../datareport/cells";
import { fillObject, num } from "../datareport/str";
import { Learner, cellHtml } from "./cells";
const content = /* @__PURE__ */ __name((labels, columns, identity, row) => {
  const rated = row.ratings.filter((rating) => rating.grade !== null).length;
  const proficient = row.ratings.filter((rating) => rating.proficient).length;
  return {
    head: /* @__PURE__ */ jsxDEV(Learner, { row, identity }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Cards.tsx",
      lineNumber: 59,
      columnNumber: 15
    }),
    lines: row.ratings.length > 0 ? /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-card-line", children: fillObject(labels.cardline, {
      proficient: num(proficient),
      rated: num(rated),
      shown: num(row.ratings.length)
    }) }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Cards.tsx",
      lineNumber: 61,
      columnNumber: 13
    }) : null,
    fields: columns.map((column) => ({
      key: column.key,
      title: column.title,
      content: /* @__PURE__ */ jsxDEV(Lines, { parts: [cellHtml(row, column.key)] }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/classreport/Cards.tsx",
        lineNumber: 72,
        columnNumber: 22
      })
    }))
  };
}, "content");
const Cards = /* @__PURE__ */ __name(({ labels, columns, identity, rows, single }) => /* @__PURE__ */ jsxDEV(
  DataCards,
  {
    labels,
    rows,
    single,
    renderCard: (row) => content(labels, columns, identity, row)
  },
  void 0,
  false,
  {
    fileName: "public/theme/dimensions/js/esm/src/classreport/Cards.tsx",
    lineNumber: 84,
    columnNumber: 5
  }
), "Cards");
export {
  Cards
};
//# sourceMappingURL=Cards.dev.js.map
