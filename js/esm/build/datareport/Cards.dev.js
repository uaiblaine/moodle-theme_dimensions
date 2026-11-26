var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The card view (AR-D9): the same rows, page and order as the grid, one article each.
 *
 * A card is a first line (who, and the row's action), the view's fixed lines, then the other chosen
 * fields in a list, at once on a wide screen (a grid of 17.5rem cards) and behind "More" on a narrow
 * one (one column). What each part holds is the view's (`renderCard`). Copied from local_evidencehub's
 * datareport/Cards.tsx without the selection variant.
 *
 * Strings read from the view's labels: more, less.
 *
 * @module     theme_dimensions/datareport/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useState } from "react";
const Card = /* @__PURE__ */ __name(({ labels, row, single, renderCard }) => {
  const [more, setMore] = useState(false);
  const content = renderCard(row);
  const shown = !single || more;
  return /* @__PURE__ */ jsxDEV("article", { className: "dim-comp-rview-card", role: "listitem", "data-record": row.id, children: [
    /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-card-l1", children: content.head }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
      lineNumber: 69,
      columnNumber: 13
    }),
    content.lines,
    content.fields.length > 0 ? /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-card-more", children: [
      single ? /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "btn btn-link btn-sm dim-comp-rview-linkbtn",
          "aria-expanded": more,
          onClick: () => setMore(!more),
          children: more ? labels.less : labels.more
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
          lineNumber: 74,
          columnNumber: 25
        }
      ) : null,
      shown ? /* @__PURE__ */ jsxDEV("dl", { className: "dim-comp-rview-card-list", children: content.fields.map((field) => /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-card-item", children: [
        /* @__PURE__ */ jsxDEV("dt", { children: field.title }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
          lineNumber: 87,
          columnNumber: 37
        }),
        /* @__PURE__ */ jsxDEV("dd", { children: field.content }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
          lineNumber: 88,
          columnNumber: 37
        })
      ] }, field.key, true, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
        lineNumber: 86,
        columnNumber: 33
      })) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
        lineNumber: 84,
        columnNumber: 25
      }) : null
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
      lineNumber: 72,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
    lineNumber: 68,
    columnNumber: 9
  });
}, "Card");
const Cards = /* @__PURE__ */ __name(({ labels, rows, single, renderCard }) => /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-cards" + (single ? " dim-comp-rview-cards--single" : ""), role: "list", children: rows.map((row) => /* @__PURE__ */ jsxDEV(Card, { labels, row, single, renderCard }, row.id, false, {
  fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
  lineNumber: 108,
  columnNumber: 13
})) }, void 0, false, {
  fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
  lineNumber: 106,
  columnNumber: 5
}), "Cards");
const CardsSkeleton = /* @__PURE__ */ __name(({ single }) => /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-cards" + (single ? " dim-comp-rview-cards--single" : ""), "aria-hidden": "true", children: Array.from({ length: single ? 4 : 6 }, (_, i) => /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-card dim-comp-rview-sk", children: [
  /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-skb dim-comp-rview-skb--w60" }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
    lineNumber: 123,
    columnNumber: 17
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-skb dim-comp-rview-skb--w80" }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
    lineNumber: 124,
    columnNumber: 17
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-skb dim-comp-rview-skb--w60" }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
    lineNumber: 125,
    columnNumber: 17
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-skb dim-comp-rview-skb--w40" }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
    lineNumber: 126,
    columnNumber: 17
  })
] }, i, true, {
  fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
  lineNumber: 122,
  columnNumber: 13
})) }, void 0, false, {
  fileName: "public/theme/dimensions/js/esm/src/datareport/Cards.tsx",
  lineNumber: 120,
  columnNumber: 5
}), "CardsSkeleton");
export {
  Cards,
  CardsSkeleton
};
//# sourceMappingURL=Cards.dev.js.map
