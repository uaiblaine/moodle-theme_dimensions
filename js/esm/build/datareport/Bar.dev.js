var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The filter bar above the rows (the Design System's filter-bar.md, chip.md, menu.md, filter-panel.md).
 *
 * The count, a capsule of icon buttons - Filters with its loud counter, Columns, View and, where a view
 * offers one, Download - and the search by name in a capsule of its own, folded to its button until pressed
 * or `/` is typed; the chips of what is in force on a row of their own, then "Clear all". One layer is open
 * at a time; Escape or a press outside closes it and gives the focus back to its button. Each menu hangs
 * under its own button, the panel under the row of controls (never under the chips); at the narrow step
 * (the view's container under 576 px) the menus hang from the controls' end and the open search takes the
 * line.
 *
 * The open search: Escape closes it whatever it holds, the text staying in force, and gives the focus
 * back to its button; leaving it empty closes it too; the eraser clears the text and keeps it open. The
 * text in force shows as a chip only while the field is closed, so it is never said twice.
 *
 * What differs between views arrives as props: the facets and their values with the callback that writes a
 * change where the view keeps its filters, the chips of the filters in force, the column catalogue and the
 * most of its optional columns shown at once, the View choices and the link to the view's server table, and
 * the Download links (none: no Download button). Columns lists the catalogue, a fixed column always shown;
 * at the limit the unchecked columns are aria-disabled under a line that says why, and the last optional
 * column shown cannot be hidden; it ends with the default columns and "Reset column widths".
 *
 * Copied from local_evidencehub's datareport/Bar.tsx without the scope's segmented control, the Columns
 * menu's sets and the View menu's other pages, which this theme's reports do not have; the column limit is
 * the class report's (classreport::MAX_COLUMNS).
 *
 * Strings read from the view's labels: count (and count_one), filters, filterson, columns, always,
 * defaultcolumns, resetwidths, view, viewauto, viewautohint, table, cards, coretable, download, search,
 * cleartext, removefilter, searchchip, clearall, and FilterPanel's.
 *
 * @module     theme_dimensions/datareport/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useId, useRef, useState } from "react";
import { FilterPanel } from "./FilterPanel";
import { Icon } from "./icons";
import { menuKeys } from "./menus";
import { fill, fillCount, num } from "./str";
const TableLink = /* @__PURE__ */ __name(({ tableurl, label }) => tableurl === "" ? null : /* @__PURE__ */ jsxDEV(Fragment, { children: [
  /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-menu-sep", role: "separator" }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
    lineNumber: 101,
    columnNumber: 13
  }),
  /* @__PURE__ */ jsxDEV("a", { className: "dim-comp-rview-menu-item", role: "menuitem", tabIndex: -1, href: tableurl, children: [
    /* @__PURE__ */ jsxDEV(Icon, { name: "table" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
      lineNumber: 103,
      columnNumber: 17
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-menu-label", children: label }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
      lineNumber: 104,
      columnNumber: 17
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
    lineNumber: 102,
    columnNumber: 13
  })
] }, void 0, true, {
  fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
  lineNumber: 100,
  columnNumber: 9
}), "TableLink");
const ColumnItems = /* @__PURE__ */ __name(({ labels, columns, limit, noteid, onColumn }) => {
  const shown = columns.filter((column) => !column.fixed && column.active).length;
  const full = limit !== null && shown >= limit.most;
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    columns.map((column) => {
      if (column.fixed) {
        return /* @__PURE__ */ jsxDEV(
          "div",
          {
            className: "dim-comp-rview-menu-item",
            role: "menuitemcheckbox",
            "aria-checked": "true",
            "aria-disabled": "true",
            tabIndex: -1,
            children: [
              /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-box", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV(Icon, { name: "tick" }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                lineNumber: 138,
                columnNumber: 33
              }) }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                lineNumber: 137,
                columnNumber: 29
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-menu-label", children: column.title }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                lineNumber: 140,
                columnNumber: 29
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-menu-hint", children: labels.always }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                lineNumber: 141,
                columnNumber: 29
              })
            ]
          },
          column.key,
          true,
          {
            fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
            lineNumber: 129,
            columnNumber: 25
          }
        );
      }
      const locked = column.active ? shown <= 1 : full;
      return /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "dim-comp-rview-menu-item",
          role: "menuitemcheckbox",
          "aria-checked": column.active,
          "aria-disabled": locked,
          "aria-describedby": locked && !column.active ? noteid : void 0,
          tabIndex: -1,
          "data-column": column.key,
          onClick: () => {
            if (!locked) {
              onColumn(column.key, !column.active);
            }
          },
          children: [
            /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-box", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV(Icon, { name: "tick" }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
              lineNumber: 164,
              columnNumber: 29
            }) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
              lineNumber: 163,
              columnNumber: 25
            }),
            /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-menu-label", children: column.title }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
              lineNumber: 166,
              columnNumber: 25
            })
          ]
        },
        column.key,
        true,
        {
          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
          lineNumber: 147,
          columnNumber: 21
        }
      );
    }),
    full && limit !== null ? /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-menu-note", id: noteid, role: "presentation", children: limit.note }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
      lineNumber: 171,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
    lineNumber: 125,
    columnNumber: 9
  });
}, "ColumnItems");
const Bar = /* @__PURE__ */ __name((props) => {
  const { labels, chips, columns } = props;
  const uid = useId();
  const root = useRef(null);
  const searchinput = useRef(null);
  const searchbutton = useRef(null);
  const refocus = useRef(false);
  const [layer, setLayer] = useState(null);
  const [searchopen, setSearchopen] = useState(props.search !== "");
  const triggers = useRef({});
  useEffect(() => {
    if (layer === null) {
      return void 0;
    }
    const outside = /* @__PURE__ */ __name((event) => {
      if (root.current && !root.current.contains(event.target)) {
        setLayer(null);
      }
    }, "outside");
    document.addEventListener("mousedown", outside);
    return () => document.removeEventListener("mousedown", outside);
  }, [layer]);
  useEffect(() => {
    if (layer === null || layer === "filters") {
      return;
    }
    const menu = root.current?.querySelector(`#${CSS.escape(uid + layer)}`);
    const item = menu?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])') ?? menu?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])');
    item?.focus();
  }, [layer, uid]);
  useEffect(() => {
    if (searchopen) {
      searchinput.current?.focus();
    } else if (refocus.current) {
      refocus.current = false;
      searchbutton.current?.focus();
    }
  }, [searchopen]);
  useEffect(() => {
    const slash = /* @__PURE__ */ __name((event) => {
      if (event.key !== "/" || event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented) {
        return;
      }
      const focused = document.activeElement;
      if (focused && (focused.isContentEditable || focused.closest("input, textarea, select"))) {
        return;
      }
      const rect = root.current?.getBoundingClientRect();
      if (!rect || rect.width === 0 || rect.bottom <= 0 || rect.top >= window.innerHeight) {
        return;
      }
      event.preventDefault();
      if (searchopen) {
        searchinput.current?.focus();
      } else {
        setLayer(null);
        setSearchopen(true);
      }
    }, "slash");
    document.addEventListener("keydown", slash);
    return () => document.removeEventListener("keydown", slash);
  }, [searchopen]);
  const toggle = /* @__PURE__ */ __name((which) => setLayer(layer === which ? null : which), "toggle");
  const closeSearch = /* @__PURE__ */ __name((back) => {
    refocus.current = back;
    setSearchopen(false);
  }, "closeSearch");
  const escape = /* @__PURE__ */ __name((event) => {
    if (event.key !== "Escape" || event.defaultPrevented) {
      return;
    }
    if (layer !== null) {
      event.preventDefault();
      const trigger = triggers.current[layer];
      setLayer(null);
      trigger?.focus();
    } else if (searchopen) {
      event.preventDefault();
      closeSearch(true);
    }
  }, "escape");
  const searched = props.search.trim().length >= props.searchmin;
  const searchchip = searched && !searchopen;
  const chipcount = chips.length + (searchchip ? 1 : 0);
  const fullcount = props.total === null ? "\u2026" : fillCount(labels, "count", props.total, num(props.total));
  const countText = props.total !== null && props.narrow ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: num(props.total) }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
      lineNumber: 322,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: fullcount }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
      lineNumber: 323,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
    lineNumber: 321,
    columnNumber: 9
  }) : fullcount;
  const iconbutton = /* @__PURE__ */ __name((which, icon, name, extra) => /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className: "dim-comp-rview-iconbtn",
      "aria-label": name,
      "data-dim-comp-rview-tip": name,
      "aria-haspopup": which === "filters" ? "dialog" : "menu",
      "aria-expanded": layer === which,
      "aria-controls": uid + which,
      ref: (element) => {
        triggers.current[which ?? ""] = element;
      },
      onClick: () => toggle(which),
      children: [
        /* @__PURE__ */ jsxDEV(Icon, { name: icon }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
          lineNumber: 350,
          columnNumber: 13
        }),
        extra
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
      lineNumber: 337,
      columnNumber: 9
    }
  ), "iconbutton");
  const viewicon = { auto: "auto", table: "table", cards: "cards" }[props.view];
  const chevron = /* @__PURE__ */ jsxDEV(Icon, { name: "chevron", className: "dim-comp-rview-chevron" }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
    lineNumber: 356,
    columnNumber: 21
  });
  const filtersname = chips.length > 0 ? fill(labels.filterson, num(chips.length)) : labels.filters;
  const badge = chips.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-badge", "aria-hidden": "true", children: num(chips.length) }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
    lineNumber: 360,
    columnNumber: 11
  }) : null;
  const viewchoices = [
    ["auto", labels.viewauto, labels.viewautohint],
    ["table", labels.table, ""],
    ["cards", labels.cards, ""]
  ].filter(([value]) => props.views.includes(value));
  const barclass = "dim-comp-rview-bar" + (props.narrow ? " dim-comp-rview-bar--narrow" : "") + (searchopen ? " dim-comp-rview-bar--searching" : "");
  return /* @__PURE__ */ jsxDEV("div", { className: barclass, "data-region": "dim-comp-rview-bar", ref: root, onKeyDown: escape, children: [
    /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-bar-spacer" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
      lineNumber: 370,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-bar-controls", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-bar-cluster", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-bar-count", "data-region": "dim-comp-rview-total", "aria-live": "polite", children: countText }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
          lineNumber: 374,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-capsule", children: [
          iconbutton("filters", "filter", filtersname, badge),
          /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-anchor", children: [
            iconbutton("columns", "columns", labels.columns, chevron),
            /* @__PURE__ */ jsxDEV(
              "div",
              {
                className: "dim-comp-rview-menu",
                id: uid + "columns",
                role: "menu",
                "aria-label": labels.columns,
                "data-region": "dim-comp-rview-columns",
                hidden: layer !== "columns",
                onKeyDown: menuKeys,
                children: [
                  /* @__PURE__ */ jsxDEV(
                    ColumnItems,
                    {
                      labels,
                      columns,
                      limit: props.limit,
                      noteid: uid + "limit",
                      onColumn: props.onColumn
                    },
                    void 0,
                    false,
                    {
                      fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                      lineNumber: 390,
                      columnNumber: 33
                    }
                  ),
                  /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-menu-sep", role: "separator" }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                    lineNumber: 397,
                    columnNumber: 33
                  }),
                  /* @__PURE__ */ jsxDEV(
                    "button",
                    {
                      type: "button",
                      className: "dim-comp-rview-menu-item",
                      role: "menuitem",
                      tabIndex: -1,
                      "aria-disabled": props.usedefault,
                      onClick: () => {
                        if (!props.usedefault) {
                          props.onDefaultColumns();
                        }
                      },
                      children: [
                        /* @__PURE__ */ jsxDEV(Icon, { name: "columns" }, void 0, false, {
                          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 410,
                          columnNumber: 37
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-menu-label", children: labels.defaultcolumns }, void 0, false, {
                          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 411,
                          columnNumber: 37
                        })
                      ]
                    },
                    void 0,
                    true,
                    {
                      fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                      lineNumber: 398,
                      columnNumber: 33
                    }
                  ),
                  /* @__PURE__ */ jsxDEV(
                    "button",
                    {
                      type: "button",
                      className: "dim-comp-rview-menu-item",
                      role: "menuitem",
                      tabIndex: -1,
                      "aria-disabled": !props.haswidths,
                      onClick: () => {
                        if (props.haswidths) {
                          props.onResetWidths();
                        }
                      },
                      children: [
                        /* @__PURE__ */ jsxDEV(Icon, { name: "reset" }, void 0, false, {
                          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 425,
                          columnNumber: 37
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-menu-label", children: labels.resetwidths }, void 0, false, {
                          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 426,
                          columnNumber: 37
                        })
                      ]
                    },
                    void 0,
                    true,
                    {
                      fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                      lineNumber: 413,
                      columnNumber: 33
                    }
                  )
                ]
              },
              void 0,
              true,
              {
                fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                lineNumber: 381,
                columnNumber: 29
              }
            )
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
            lineNumber: 379,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-anchor", children: [
            iconbutton("view", viewicon, labels.view, chevron),
            /* @__PURE__ */ jsxDEV(
              "div",
              {
                className: "dim-comp-rview-menu",
                id: uid + "view",
                role: "menu",
                "aria-label": labels.view,
                hidden: layer !== "view",
                onKeyDown: menuKeys,
                children: [
                  viewchoices.map(([value, label, hint]) => /* @__PURE__ */ jsxDEV(
                    "button",
                    {
                      type: "button",
                      className: "dim-comp-rview-menu-item",
                      role: "menuitemradio",
                      "aria-checked": props.view === value,
                      tabIndex: -1,
                      onClick: () => {
                        props.onView(value);
                        setLayer(null);
                        triggers.current.view?.focus();
                      },
                      children: [
                        /* @__PURE__ */ jsxDEV(Icon, { name: value }, void 0, false, {
                          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 454,
                          columnNumber: 41
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-menu-label", children: label }, void 0, false, {
                          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 455,
                          columnNumber: 41
                        }),
                        hint !== "" ? /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-menu-hint", children: hint }, void 0, false, {
                          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 456,
                          columnNumber: 56
                        }) : null,
                        /* @__PURE__ */ jsxDEV(Icon, { name: "tick", className: "dim-comp-rview-tick" }, void 0, false, {
                          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 457,
                          columnNumber: 41
                        })
                      ]
                    },
                    value,
                    true,
                    {
                      fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                      lineNumber: 441,
                      columnNumber: 37
                    }
                  )),
                  /* @__PURE__ */ jsxDEV(TableLink, { tableurl: props.tableurl, label: labels.coretable }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                    lineNumber: 460,
                    columnNumber: 33
                  })
                ]
              },
              void 0,
              true,
              {
                fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                lineNumber: 432,
                columnNumber: 29
              }
            )
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
            lineNumber: 430,
            columnNumber: 25
          }),
          props.downloads.length > 0 ? /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-anchor", children: [
            iconbutton("download", "download", labels.download, chevron),
            /* @__PURE__ */ jsxDEV(
              "div",
              {
                className: "dim-comp-rview-menu",
                id: uid + "download",
                role: "menu",
                "aria-label": labels.download,
                hidden: layer !== "download",
                onKeyDown: menuKeys,
                children: props.downloads.map((link) => /* @__PURE__ */ jsxDEV(
                  "a",
                  {
                    className: "dim-comp-rview-menu-item",
                    role: "menuitem",
                    tabIndex: -1,
                    href: link.href,
                    children: [
                      /* @__PURE__ */ jsxDEV(Icon, { name: "download" }, void 0, false, {
                        fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                        lineNumber: 482,
                        columnNumber: 45
                      }),
                      /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-menu-label", children: link.label }, void 0, false, {
                        fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                        lineNumber: 483,
                        columnNumber: 45
                      })
                    ]
                  },
                  link.key,
                  true,
                  {
                    fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                    lineNumber: 475,
                    columnNumber: 41
                  }
                ))
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                lineNumber: 466,
                columnNumber: 33
              }
            )
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
            lineNumber: 464,
            columnNumber: 29
          }) : null
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
          lineNumber: 377,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
        lineNumber: 373,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-capsule dim-comp-rview-searchcap", children: searchopen ? /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-search", children: [
        props.searching && searched ? /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-spin", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
          lineNumber: 495,
          columnNumber: 35
        }) : /* @__PURE__ */ jsxDEV(Icon, { name: "search" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
          lineNumber: 496,
          columnNumber: 35
        }),
        /* @__PURE__ */ jsxDEV(
          "input",
          {
            ref: searchinput,
            type: "search",
            "aria-label": labels.search,
            placeholder: labels.search,
            autoComplete: "off",
            value: props.search,
            onChange: (event) => props.onSearch(event.target.value),
            onKeyDown: (event) => {
              if (event.key === "Escape") {
                event.preventDefault();
                closeSearch(true);
              }
            },
            onBlur: () => {
              if (props.search === "") {
                closeSearch(false);
              }
            }
          },
          void 0,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
            lineNumber: 497,
            columnNumber: 29
          }
        ),
        props.search !== "" ? (
          // The eraser keeps the field's focus on a press: it clears the text and the
          // field stays open.
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "dim-comp-rview-eraser",
              "aria-label": labels.cleartext,
              "data-dim-comp-rview-tip": labels.cleartext,
              onMouseDown: (event) => event.preventDefault(),
              onClick: () => {
                props.onSearch("");
                searchinput.current?.focus();
              },
              children: /* @__PURE__ */ jsxDEV(Icon, { name: "eraser" }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                lineNumber: 533,
                columnNumber: 37
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
              lineNumber: 522,
              columnNumber: 33
            }
          )
        ) : null
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
        lineNumber: 493,
        columnNumber: 25
      }) : /* @__PURE__ */ jsxDEV(
        "button",
        {
          ref: searchbutton,
          type: "button",
          className: "dim-comp-rview-iconbtn",
          "aria-label": labels.search,
          "data-dim-comp-rview-tip": labels.search,
          onClick: () => {
            setLayer(null);
            setSearchopen(true);
          },
          children: /* @__PURE__ */ jsxDEV(Icon, { name: "search" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
            lineNumber: 549,
            columnNumber: 29
          })
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
          lineNumber: 538,
          columnNumber: 25
        }
      ) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
        lineNumber: 491,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(
        FilterPanel,
        {
          labels,
          id: uid + "filters",
          hidden: layer !== "filters",
          facets: props.facets,
          values: props.values,
          total: props.total,
          note: props.panelnote,
          onChange: props.onFacet,
          onClear: props.onClearFilters,
          onShow: () => {
            setLayer(null);
            triggers.current.filters?.focus();
          }
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
          lineNumber: 554,
          columnNumber: 17
        }
      )
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
      lineNumber: 372,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        className: "dim-comp-rview-chips",
        "data-region": "dim-comp-rview-chips",
        hidden: chipcount === 0 && !props.aside,
        children: [
          chips.map((chip) => /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "dim-comp-rview-chip",
              "aria-label": fill(labels.removefilter, chip.text),
              onClick: () => props.onRemoveChip(chip.key),
              children: [
                /* @__PURE__ */ jsxDEV("span", { children: chip.text }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                  lineNumber: 584,
                  columnNumber: 25
                }),
                /* @__PURE__ */ jsxDEV(Icon, { name: "close" }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                  lineNumber: 585,
                  columnNumber: 25
                })
              ]
            },
            chip.key,
            true,
            {
              fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
              lineNumber: 577,
              columnNumber: 21
            }
          )),
          searchchip ? /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "dim-comp-rview-chip",
              "aria-label": fill(labels.removefilter, fill(labels.searchchip, props.search.trim())),
              onClick: () => props.onSearch(""),
              children: [
                /* @__PURE__ */ jsxDEV("span", { children: fill(labels.searchchip, props.search.trim()) }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                  lineNumber: 595,
                  columnNumber: 25
                }),
                /* @__PURE__ */ jsxDEV(Icon, { name: "close" }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
                  lineNumber: 596,
                  columnNumber: 25
                })
              ]
            },
            void 0,
            true,
            {
              fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
              lineNumber: 589,
              columnNumber: 21
            }
          ) : null,
          chipcount > 1 ? /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "dim-comp-rview-clearall",
              onClick: () => {
                props.onClearAll();
                setSearchopen(false);
              },
              children: labels.clearall
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
              lineNumber: 600,
              columnNumber: 21
            }
          ) : null,
          props.aside ?? null
        ]
      },
      void 0,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
        lineNumber: 571,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/datareport/Bar.tsx",
    lineNumber: 369,
    columnNumber: 9
  });
}, "Bar");
export {
  Bar
};
//# sourceMappingURL=Bar.dev.js.map
