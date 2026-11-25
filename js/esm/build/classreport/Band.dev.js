var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The class report's band: the figures of the listed learners over the shown competency columns.
 *
 * Four tiles - the learners; the ratings given, of all the cells (learners times shown columns), with the
 * percentage as the sub-line; the proficient cells, with theirs; the learners with no rating in any shown
 * column - and one panel, "By competency": a line per shown competency in the columns' order, its name,
 * "{proficient} of {learners} proficient" and a meter of that share (progress-bar.md). No tile is a link and no
 * line a filter. A figure with no value reads "–": a share of no cell, the figures over no competency column,
 * a competency's line over no learner (which draws no meter, never "0 %").
 *
 * Every number is the server's (classreportengine::summary()), over the rows the rows call lists: the group,
 * the search and the stored filters included. Shaped after local_evidencehub's hub/Band.tsx.
 *
 * @module     theme_dimensions/classreport/Band
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fill, fillObject, num } from "../datareport/str";
const NONE = "\u2013";
const percent = /* @__PURE__ */ __name((part, whole) => whole > 0 ? Math.round(Math.min(1, Math.max(0, part / whole)) * 100) : null, "percent");
const Tile = /* @__PURE__ */ __name(({ label, value, sub, name }) => /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-tile", "data-tile": name, children: [
  /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-tile-label", children: label }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
    lineNumber: 67,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-tile-value", children: value }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
    lineNumber: 68,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-tile-sub", children: sub }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
    lineNumber: 69,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
  lineNumber: 66,
  columnNumber: 5
}), "Tile");
const CompetencyLine = /* @__PURE__ */ __name(({ labels, figures, learners }) => {
  const share = percent(figures.proficient, learners);
  return /* @__PURE__ */ jsxDEV("li", { className: "dim-comp-rview-hitem", "data-competency": figures.competencyid, children: [
    /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-hrow-label", title: figures.title, children: figures.title }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 85,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-hrow-n", children: share === null ? NONE : fillObject(labels.proficientof, {
      proficient: num(figures.proficient),
      learners: num(learners)
    }) }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 86,
      columnNumber: 13
    }),
    share === null ? null : /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-meter", children: [
      /* @__PURE__ */ jsxDEV(
        "span",
        {
          className: "dim-comp-rview-meter-track",
          role: "progressbar",
          "aria-valuenow": share,
          "aria-valuemin": 0,
          "aria-valuemax": 100,
          "aria-label": fillObject(labels.meterlabel, { title: figures.title, percent: num(share) }),
          children: /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-meter-fill", style: { width: `${share}%` } }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
            lineNumber: 102,
            columnNumber: 25
          })
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
          lineNumber: 94,
          columnNumber: 21
        }
      ),
      /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-meter-pct", "aria-hidden": "true", children: fill(labels.percent, num(share)) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
        lineNumber: 104,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 93,
      columnNumber: 17
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
    lineNumber: 84,
    columnNumber: 9
  });
}, "CompetencyLine");
const BandSkeleton = /* @__PURE__ */ __name(() => /* @__PURE__ */ jsxDEV("div", { "aria-hidden": "true", children: [
  /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-band", children: Array.from({ length: 4 }, (_, i) => /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-tile dim-comp-rview-sk", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-skb dim-comp-rview-skb--w60" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 121,
      columnNumber: 21
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-skb dim-comp-rview-skb--big" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 122,
      columnNumber: 21
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-skb dim-comp-rview-skb--w80" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 123,
      columnNumber: 21
    })
  ] }, i, true, {
    fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
    lineNumber: 120,
    columnNumber: 17
  })) }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
    lineNumber: 118,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-dist", children: /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-panel dim-comp-rview-sk", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-skb dim-comp-rview-skb--w40" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 129,
      columnNumber: 17
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-skb" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 130,
      columnNumber: 17
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-skb" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 131,
      columnNumber: 17
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "dim-comp-rview-skb" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 132,
      columnNumber: 17
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
    lineNumber: 128,
    columnNumber: 13
  }) }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
    lineNumber: 127,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
  lineNumber: 117,
  columnNumber: 5
}), "BandSkeleton");
const tiles = /* @__PURE__ */ __name((labels, summary) => {
  const columns = summary.competencies.length;
  const rated = percent(summary.rated, summary.cells);
  const proficient = percent(summary.proficient, summary.cells);
  return [
    {
      name: "learners",
      label: labels.learners,
      value: num(summary.learners),
      sub: fill(columns === 1 ? labels.learnerssub_one : labels.learnerssub, num(columns))
    },
    {
      name: "rated",
      label: labels.ratingsgiven,
      value: rated === null ? NONE : num(summary.rated),
      sub: rated === null ? NONE : fillObject(labels.ofcells, { percent: num(rated), cells: num(summary.cells) })
    },
    {
      name: "proficient",
      label: labels.proficienttile,
      value: proficient === null ? NONE : num(summary.proficient),
      sub: proficient === null ? NONE : fillObject(labels.ofcells, { percent: num(proficient), cells: num(summary.cells) })
    },
    {
      name: "unrated",
      label: labels.unratedtile,
      value: columns === 0 ? NONE : num(summary.unrated),
      sub: labels.unratedsub
    }
  ];
}, "tiles");
const Band = /* @__PURE__ */ __name(({ labels, summary, loading, error, onRetry }) => {
  const retry = /* @__PURE__ */ jsxDEV("p", { className: "dim-comp-rview-error", children: [
    labels.loaderror,
    " ",
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: onRetry, children: labels.retry }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 189,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
    lineNumber: 187,
    columnNumber: 9
  });
  let body;
  if (summary === null && error) {
    body = retry;
  } else if (summary === null) {
    body = /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV(BandSkeleton, {}, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
        lineNumber: 198,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", role: "status", children: labels.loadband }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
        lineNumber: 199,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 197,
      columnNumber: 13
    });
  } else {
    body = /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-dim", children: [
      error ? retry : null,
      /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-band", children: tiles(labels, summary).map((tile) => /* @__PURE__ */ jsxDEV(Tile, { name: tile.name, label: tile.label, value: tile.value, sub: tile.sub }, tile.name, false, {
        fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
        lineNumber: 208,
        columnNumber: 25
      })) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
        lineNumber: 206,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-dist", children: /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-panel", children: [
        /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-panel-title", children: /* @__PURE__ */ jsxDEV("span", { children: labels.bycompetency }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
          lineNumber: 213,
          columnNumber: 69
        }) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
          lineNumber: 213,
          columnNumber: 25
        }),
        summary.competencies.length > 0 ? /* @__PURE__ */ jsxDEV("ul", { className: "dim-comp-rview-hlist", children: summary.competencies.map((figures) => /* @__PURE__ */ jsxDEV(
          CompetencyLine,
          {
            labels,
            figures,
            learners: summary.learners
          },
          figures.key,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
            lineNumber: 217,
            columnNumber: 37
          }
        )) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
          lineNumber: 215,
          columnNumber: 29
        }) : /* @__PURE__ */ jsxDEV("p", { className: "dim-comp-rview-note", children: labels.nocompetencies }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
          lineNumber: 225,
          columnNumber: 29
        })
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
        lineNumber: 212,
        columnNumber: 21
      }) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
        lineNumber: 211,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 204,
      columnNumber: 13
    });
  }
  return /* @__PURE__ */ jsxDEV(
    "section",
    {
      className: "dim-comp-rview-region dim-comp-rview-bandregion",
      "aria-label": labels.bandlabel,
      "aria-busy": loading,
      "data-region": "dim-comp-rview-band",
      children: [
        loading && summary !== null ? /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-progress" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
          lineNumber: 239,
          columnNumber: 44
        }) : null,
        body
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Band.tsx",
      lineNumber: 233,
      columnNumber: 9
    }
  );
}, "Band");
export {
  Band,
  NONE,
  percent,
  tiles
};
//# sourceMappingURL=Band.dev.js.map
