var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The rows' region: what it holds while the rows load, fail, are none, or are there.
 *
 * The first load draws a skeleton of the grid or of the cards, with aria-busy and a status for
 * assistive technology; later loads keep the old rows, dimmed, under a 2 px progress line. A failed
 * read says so with a retry, above the old rows when there are some. No rows is the empty state, worded
 * for a filtered view or an empty one. The rows themselves (the view's grid or cards) are the children.
 * Copied from local_evidencehub's datareport/Rows.tsx without the dock.
 *
 * Strings read from the view's labels: caption, loading, loaderror, retry, empty, nomatch, nomatchhelp,
 * scrollhint.
 *
 * @module     theme_dimensions/datareport/Rows
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { CardsSkeleton } from "./Cards";
import { GridSkeleton } from "./Grid";
const RowsContent = /* @__PURE__ */ __name(({
  labels,
  loaded,
  error,
  total,
  filtered,
  cards,
  narrow,
  skeleton,
  children,
  onRetry
}) => {
  const retry = /* @__PURE__ */ jsxDEV("p", { className: "dim-comp-rview-error", children: [
    labels.loaderror,
    " ",
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: onRetry, children: labels.retry }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
      lineNumber: 67,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
    lineNumber: 65,
    columnNumber: 9
  });
  if (!loaded && error) {
    return retry;
  }
  if (!loaded) {
    return /* @__PURE__ */ jsxDEV(Fragment, { children: [
      cards ? /* @__PURE__ */ jsxDEV(CardsSkeleton, { single: narrow }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
        lineNumber: 76,
        columnNumber: 26
      }) : /* @__PURE__ */ jsxDEV(GridSkeleton, { ...skeleton }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
        lineNumber: 76,
        columnNumber: 62
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", role: "status", children: labels.loading }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
        lineNumber: 77,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
      lineNumber: 75,
      columnNumber: 13
    });
  }
  if (total === 0) {
    return /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-empty", children: [
      /* @__PURE__ */ jsxDEV("p", { className: "dim-comp-rview-empty-title", children: filtered ? labels.nomatch : labels.empty }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
        lineNumber: 84,
        columnNumber: 17
      }),
      filtered ? /* @__PURE__ */ jsxDEV("p", { className: "dim-comp-rview-empty-text", children: labels.nomatchhelp }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
        lineNumber: 85,
        columnNumber: 29
      }) : null
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
      lineNumber: 83,
      columnNumber: 13
    });
  }
  return /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-dim", children: [
    error ? retry : null,
    children,
    cards ? null : /* @__PURE__ */ jsxDEV("p", { className: "dim-comp-rview-hint", children: labels.scrollhint }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
      lineNumber: 94,
      columnNumber: 29
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
    lineNumber: 91,
    columnNumber: 9
  });
}, "RowsContent");
const RowsRegion = /* @__PURE__ */ __name((props) => /* @__PURE__ */ jsxDEV(
  "section",
  {
    className: "dim-comp-rview-region",
    "aria-label": props.labels.caption,
    "aria-busy": props.loading,
    "data-region": "dim-comp-rview-rows",
    children: [
      props.loading && props.loaded ? /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-progress" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
        lineNumber: 112,
        columnNumber: 42
      }) : null,
      /* @__PURE__ */ jsxDEV(RowsContent, { ...props }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
        lineNumber: 113,
        columnNumber: 9
      })
    ]
  },
  void 0,
  true,
  {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Rows.tsx",
    lineNumber: 106,
    columnNumber: 5
  }
), "RowsRegion");
export {
  RowsRegion
};
//# sourceMappingURL=Rows.dev.js.map
