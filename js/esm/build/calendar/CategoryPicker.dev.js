var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The category picker: a tree of the categories the viewer can see, with a search and "include subcategories".
 *
 * The tree is local\calendar\tree's, flattened in display order: a treeitem per category with aria-level,
 * aria-setsize and aria-posinset, aria-expanded on a category with children and aria-selected on the one in force;
 * "All my categories" heads it. One item is in the Tab order (roving tabindex); ArrowUp and ArrowDown move between
 * the visible items, ArrowRight opens a closed category or moves to its first child, ArrowLeft shuts an open one or
 * moves to its parent, Home and End go to the first and the last, Enter and Space choose. A search shows the
 * categories whose name holds it, accents and case aside, with their ancestors open. Each category's swatch is its
 * branding pair, ground and ink together.
 *
 * @module     theme_dimensions/calendar/CategoryPicker
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useLayoutEffect, useMemo, useRef, useState } from "react";
import { hex } from "./model";
import { usePopover } from "./popover";
import { fill } from "./strings";
const ALL = 0;
const folded = /* @__PURE__ */ __name((text) => text.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase(), "folded");
const ancestors = /* @__PURE__ */ __name((byid, id) => {
  const found = [];
  let node = byid.get(id);
  while (node && node.parent !== 0 && byid.has(node.parent)) {
    found.push(node.parent);
    node = byid.get(node.parent);
  }
  return found;
}, "ancestors");
const visibleNodes = /* @__PURE__ */ __name((tree, expanded, search) => {
  const byid = new Map(tree.map((node) => [node.id, node]));
  if (search !== "") {
    const keep = /* @__PURE__ */ new Set();
    tree.forEach((node) => {
      if (folded(node.name).includes(search)) {
        keep.add(node.id);
        ancestors(byid, node.id).forEach((id) => keep.add(id));
      }
    });
    return tree.filter((node) => keep.has(node.id));
  }
  return tree.filter((node) => ancestors(byid, node.id).every((id) => expanded.has(id)));
}, "visibleNodes");
const verticalTarget = /* @__PURE__ */ __name((key, ids, index) => {
  switch (key) {
    case "ArrowDown":
      return ids[Math.min(ids.length - 1, index + 1)];
    case "ArrowUp":
      return ids[Math.max(0, index - 1)];
    case "Home":
      return ids[0];
    case "End":
      return ids[ids.length - 1];
    default:
      return null;
  }
}, "verticalTarget");
const TreeRow = /* @__PURE__ */ __name(({ node, strings, siblings, haschildren, open, selected, active, onChoose, onOpen }) => {
  const bg = hex(node.bg);
  const ink = hex(node.ink);
  const swatch = bg && ink ? { "--dim-cal-tint": bg, "--dim-cal-ink": ink } : {};
  return /* @__PURE__ */ jsxDEV(
    "li",
    {
      className: "dim-cal__treerow",
      role: "treeitem",
      "aria-level": node.depth + 2,
      "aria-setsize": siblings.length,
      "aria-posinset": siblings.indexOf(node) + 1,
      "aria-selected": selected,
      "aria-expanded": haschildren ? open : void 0,
      tabIndex: active ? 0 : -1,
      "data-id": node.id,
      style: { paddingInlineStart: `${0.5 + node.depth * 1.25}rem` },
      onClick: () => onChoose(node.id),
      children: [
        /* @__PURE__ */ jsxDEV(
          "span",
          {
            className: "dim-cal__treetoggle",
            "aria-hidden": "true",
            onClick: (event) => {
              if (haschildren) {
                event.stopPropagation();
                onOpen(node.id, !open);
              }
            },
            children: haschildren ? /* @__PURE__ */ jsxDEV("i", { className: `fa fa-chevron-${open ? "down" : "right"}` }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
              lineNumber: 170,
              columnNumber: 32
            }) : null
          },
          void 0,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
            lineNumber: 160,
            columnNumber: 13
          }
        ),
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__treeswatch", style: swatch }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
          lineNumber: 172,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("span", { children: node.name }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
          lineNumber: 173,
          columnNumber: 13
        }),
        node.courses ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__treecount", children: fill(strings.calendar_categorycourses, node.courses) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
          lineNumber: 175,
          columnNumber: 17
        }) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
      lineNumber: 147,
      columnNumber: 9
    }
  );
}, "TreeRow");
const CategoryPicker = /* @__PURE__ */ __name(({ strings, tree, categoryid, recursive, onChange }) => {
  const popover = usePopover();
  const byid = useMemo(() => new Map(tree.map((node) => [node.id, node])), [tree]);
  const parents = useMemo(() => new Set(tree.map((node) => node.parent)), [tree]);
  const [expanded, setExpanded] = useState(() => new Set(ancestors(byid, categoryid)));
  const [search, setSearch] = useState("");
  const [active, setActive] = useState(categoryid);
  const [focusing, setFocusing] = useState(0);
  const list = useRef(null);
  const shown = visibleNodes(tree, expanded, folded(search.trim()));
  const ids = [ALL, ...shown.map((node) => node.id)];
  const current = ids.includes(active) ? active : ids[0];
  const selected = byid.get(categoryid);
  useLayoutEffect(() => {
    if (focusing) {
      list.current?.querySelector(`[data-id="${current}"]`)?.focus();
    }
  }, [focusing]);
  const moveTo = /* @__PURE__ */ __name((id) => {
    setActive(id);
    setFocusing((tick) => tick + 1);
  }, "moveTo");
  const setOpen = /* @__PURE__ */ __name((id, open) => {
    setExpanded((was) => {
      const next = new Set(was);
      if (open) {
        next.add(id);
      } else {
        next.delete(id);
      }
      return next;
    });
  }, "setOpen");
  const choose = /* @__PURE__ */ __name((id) => {
    setActive(id);
    if (id !== categoryid) {
      onChange({ categoryid: id });
    }
  }, "choose");
  const onTreeKey = /* @__PURE__ */ __name((event) => {
    const index = ids.indexOf(current);
    const node = byid.get(current);
    const searching = search.trim() !== "";
    const target = verticalTarget(event.key, ids, index);
    const right = /* @__PURE__ */ __name(() => {
      if (!node || !parents.has(node.id)) {
        return;
      }
      if (!expanded.has(node.id) && !searching) {
        setOpen(node.id, true);
      } else if (byid.get(ids[index + 1])?.parent === node.id) {
        moveTo(ids[index + 1]);
      }
    }, "right");
    const left = /* @__PURE__ */ __name(() => {
      if (!node) {
        return;
      }
      if (parents.has(node.id) && expanded.has(node.id) && !searching) {
        setOpen(node.id, false);
      } else {
        moveTo(byid.has(node.parent) ? node.parent : ALL);
      }
    }, "left");
    if (target !== null) {
      moveTo(target);
    } else if (event.key === "ArrowRight") {
      right();
    } else if (event.key === "ArrowLeft") {
      left();
    } else if (event.key === "Enter" || event.key === " ") {
      choose(current);
    } else {
      return;
    }
    event.preventDefault();
  }, "onTreeKey");
  const label = selected ? selected.name : strings.calendar_categoryall;
  const panelid = "dim-cal-categorypanel";
  return /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__picker", "data-region": "dim-cal-category", children: [
    /* @__PURE__ */ jsxDEV(
      "button",
      {
        ref: popover.button,
        type: "button",
        className: "btn btn-secondary dim-cal__pickerbtn",
        "aria-expanded": popover.open,
        "aria-controls": panelid,
        onClick: popover.toggle,
        children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-folder-open", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
            lineNumber: 319,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: [
            strings.calendar_category,
            ": "
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
            lineNumber: 320,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { children: label }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
            lineNumber: 321,
            columnNumber: 17
          }),
          selected && recursive ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__pickernote", children: [
            "\xB7 ",
            strings.calendar_withsub
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
            lineNumber: 322,
            columnNumber: 42
          }) : null,
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-down", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
            lineNumber: 323,
            columnNumber: 17
          })
        ]
      },
      void 0,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
        lineNumber: 311,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        ref: popover.panel,
        className: "dim-cal__popover",
        id: panelid,
        role: "group",
        "aria-label": strings.calendar_category,
        hidden: !popover.open,
        onKeyDown: popover.onKeyDown,
        children: [
          /* @__PURE__ */ jsxDEV("label", { className: "dim-cal__field", children: [
            /* @__PURE__ */ jsxDEV("span", { children: strings.calendar_categorysearch }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
              lineNumber: 335,
              columnNumber: 21
            }),
            /* @__PURE__ */ jsxDEV(
              "input",
              {
                type: "search",
                className: "form-control",
                value: search,
                placeholder: strings.calendar_categorysearchplaceholder,
                onChange: (event) => setSearch(event.target.value)
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
                lineNumber: 336,
                columnNumber: 21
              }
            )
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
            lineNumber: 334,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV(
            "ul",
            {
              ref: list,
              className: "dim-cal__tree",
              role: "tree",
              "aria-label": strings.calendar_categorytree,
              onKeyDown: onTreeKey,
              children: [
                /* @__PURE__ */ jsxDEV(
                  "li",
                  {
                    className: "dim-cal__treerow",
                    role: "treeitem",
                    "aria-level": 1,
                    "aria-selected": categoryid === ALL,
                    tabIndex: current === ALL ? 0 : -1,
                    "data-id": ALL,
                    onClick: () => choose(ALL),
                    children: [
                      /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__treetoggle", "aria-hidden": "true" }, void 0, false, {
                        fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
                        lineNumber: 360,
                        columnNumber: 25
                      }),
                      strings.calendar_categoryall
                    ]
                  },
                  void 0,
                  true,
                  {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
                    lineNumber: 351,
                    columnNumber: 21
                  }
                ),
                shown.map((node) => /* @__PURE__ */ jsxDEV(
                  TreeRow,
                  {
                    node,
                    strings,
                    siblings: shown.filter((other) => other.parent === node.parent),
                    haschildren: parents.has(node.id),
                    open: expanded.has(node.id) || search.trim() !== "",
                    selected: node.id === categoryid,
                    active: current === node.id,
                    onChoose: choose,
                    onOpen: setOpen
                  },
                  node.id,
                  false,
                  {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
                    lineNumber: 364,
                    columnNumber: 25
                  }
                ))
              ]
            },
            void 0,
            true,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
              lineNumber: 344,
              columnNumber: 17
            }
          ),
          !shown.length && search.trim() !== "" ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__status", children: strings.calendar_categorynone }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
            lineNumber: 378,
            columnNumber: 58
          }) : null,
          /* @__PURE__ */ jsxDEV("label", { className: "dim-cal__check", children: [
            /* @__PURE__ */ jsxDEV(
              "input",
              {
                type: "checkbox",
                checked: recursive,
                onChange: (event) => onChange({ recursive: event.target.checked })
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
                lineNumber: 380,
                columnNumber: 21
              }
            ),
            strings.calendar_recursive
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
            lineNumber: 379,
            columnNumber: 17
          })
        ]
      },
      void 0,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
        lineNumber: 325,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/CategoryPicker.tsx",
    lineNumber: 310,
    columnNumber: 9
  });
}, "CategoryPicker");
var CategoryPicker_default = CategoryPicker;
export {
  CategoryPicker_default as default,
  folded,
  verticalTarget,
  visibleNodes
};
//# sourceMappingURL=CategoryPicker.dev.js.map
