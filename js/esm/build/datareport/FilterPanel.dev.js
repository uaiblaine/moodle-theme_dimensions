var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The filter panel (the Design System's filter-panel.md and counter.md): one group per facet.
 *
 * The panel knows facets, not where their values live: it is handed the definitions, the value each is
 * set to ('' for none) and a callback that a view turns into its own write (the class report rewrites
 * core's stored filters). Each option carries the rows it would list under every other filter in force;
 * an option no row would list is hidden unless chosen. A facet with more than eight options is a search
 * over a list capped at fifty, the chosen one pinned on top. Copied from local_evidencehub's
 * datareport/FilterPanel.tsx without the group facet's grouping select.
 *
 * Strings read from the view's labels: any, filters, clearfilters, show, showcount, moreoptions.
 *
 * @module     theme_dimensions/datareport/FilterPanel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useId, useState } from "react";
import { Icon } from "./icons";
import { fill, num } from "./str";
const FEW = 8;
const CAP = 50;
const showLabel = /* @__PURE__ */ __name((labels, total) => total === null ? labels.show : fill(labels.showcount, num(total)), "showLabel");
const OptionCount = /* @__PURE__ */ __name(({ option }) => option.count >= 0 ? /* @__PURE__ */ jsxDEV("small", { className: "dim-comp-rview-optcount", children: num(option.count) }, void 0, false, {
  fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
  lineNumber: 75,
  columnNumber: 25
}) : null, "OptionCount");
const shownOptions = /* @__PURE__ */ __name((facet, value) => facet.options.filter((option) => option.count !== 0 || option.value === value), "shownOptions");
const SearchedList = /* @__PURE__ */ __name(({ labels, facet, shown, value, headid, onChange }) => {
  const [query, setQuery] = useState("");
  const folded = query.trim().toLocaleLowerCase();
  const pinned = shown.filter((option) => option.value === value);
  const rest = shown.filter((option) => option.value !== value && (folded === "" || option.label.toLocaleLowerCase().includes(folded)));
  const searchlabel = fill(facet.find.label, num(facet.options.length));
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-find", children: /* @__PURE__ */ jsxDEV(
      "input",
      {
        type: "search",
        className: "form-control form-control-sm dim-comp-rview-input",
        placeholder: searchlabel,
        "aria-label": searchlabel,
        value: query,
        onChange: (event) => setQuery(event.target.value)
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
        lineNumber: 116,
        columnNumber: 17
      }
    ) }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
      lineNumber: 115,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-optlist", role: "group", "aria-labelledby": headid, children: [
      [...pinned, ...rest.slice(0, CAP)].map((option) => /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "dim-comp-rview-optrow",
          "aria-pressed": option.value === value,
          onClick: () => onChange(facet.name, option.value === value ? "" : option.value),
          children: [
            /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-box", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV(Icon, { name: "tick" }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
              lineNumber: 134,
              columnNumber: 81
            }) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
              lineNumber: 134,
              columnNumber: 25
            }),
            /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-optrow-label", children: option.label }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
              lineNumber: 135,
              columnNumber: 25
            }),
            " ",
            /* @__PURE__ */ jsxDEV(OptionCount, { option }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
              lineNumber: 136,
              columnNumber: 25
            })
          ]
        },
        option.value,
        true,
        {
          fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
          lineNumber: 127,
          columnNumber: 21
        }
      )),
      rest.length > CAP ? /* @__PURE__ */ jsxDEV("p", { className: "dim-comp-rview-note", children: fill(labels.moreoptions, num(rest.length - CAP)) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
        lineNumber: 140,
        columnNumber: 23
      }) : null,
      pinned.length === 0 && rest.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "dim-comp-rview-note", children: facet.find.nomatch }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
        lineNumber: 143,
        columnNumber: 23
      }) : null
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
      lineNumber: 125,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
    lineNumber: 114,
    columnNumber: 9
  });
}, "SearchedList");
const FacetGroup = /* @__PURE__ */ __name(({ labels, facet, value, onChange }) => {
  const headid = useId();
  const shown = shownOptions(facet, value);
  const options = [{ value: "", label: labels.any, count: -1 }, ...shown];
  if (facet.options.length > FEW) {
    return /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-fgroup", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-fpanel-head", id: headid, children: facet.title }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
        lineNumber: 166,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(SearchedList, { labels, facet, shown, value, headid, onChange }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
        lineNumber: 167,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
      lineNumber: 165,
      columnNumber: 13
    });
  }
  const keydown = /* @__PURE__ */ __name((event) => {
    const at = options.findIndex((option) => option.value === value);
    let next = -1;
    if (event.key === "ArrowRight" || event.key === "ArrowDown") {
      next = (at + 1) % options.length;
    } else if (event.key === "ArrowLeft" || event.key === "ArrowUp") {
      next = (at - 1 + options.length) % options.length;
    } else if (event.key === "Home") {
      next = 0;
    } else if (event.key === "End") {
      next = options.length - 1;
    }
    if (next < 0) {
      return;
    }
    event.preventDefault();
    onChange(facet.name, options[next].value);
    event.currentTarget.querySelectorAll('[role="radio"]')[next]?.focus();
  }, "keydown");
  return /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-fgroup", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-fpanel-head", id: headid, children: facet.title }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
      lineNumber: 200,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-fpanel-opts", role: "radiogroup", "aria-labelledby": headid, onKeyDown: keydown, children: options.map((option) => /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: "dim-comp-rview-fpanel-opt",
        role: "radio",
        "aria-checked": option.value === value,
        tabIndex: option.value === value ? 0 : -1,
        onClick: () => onChange(facet.name, option.value),
        children: [
          option.label,
          " ",
          /* @__PURE__ */ jsxDEV(OptionCount, { option }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
            lineNumber: 213,
            columnNumber: 25
          })
        ]
      },
      option.value,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
        lineNumber: 203,
        columnNumber: 21
      }
    )) }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
      lineNumber: 201,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
    lineNumber: 199,
    columnNumber: 9
  });
}, "FacetGroup");
const FilterPanel = /* @__PURE__ */ __name(({
  labels,
  id,
  hidden,
  facets,
  values,
  total,
  note,
  onChange,
  onClear,
  onShow
}) => /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-fpanel", id, role: "dialog", "aria-label": labels.filters, hidden, children: [
  /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-fpanel-body", children: [
    facets.map((facet) => /* @__PURE__ */ jsxDEV(
      FacetGroup,
      {
        labels,
        facet,
        value: values[facet.name] ?? "",
        onChange
      },
      facet.name,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
        lineNumber: 232,
        columnNumber: 17
      }
    )),
    note ?? null
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
    lineNumber: 230,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-fpanel-foot", children: [
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: onClear, children: labels.clearfilters }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
      lineNumber: 243,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-primary", onClick: onShow, children: showLabel(labels, total) }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
      lineNumber: 246,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
    lineNumber: 242,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/theme/dimensions/js/esm/src/datareport/FilterPanel.tsx",
  lineNumber: 229,
  columnNumber: 5
}), "FilterPanel");
export {
  FilterPanel
};
//# sourceMappingURL=FilterPanel.dev.js.map
