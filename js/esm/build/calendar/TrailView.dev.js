var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The Trilhas view (docs/trail/PLAN.md section 7): the month's trails, one row per trail and its steps under it.
 *
 * - On a desktop, a grid of the month's days: each step a bar from its start to its end (trails.ts barOf()), a
 *   milestone at an expected date, a bar that passes the month's end continued in the last column ("Dec →"), one that
 *   began earlier marked at the left edge, an open one fading out. Today's column is marked down the rows.
 * - On a phone, an agenda: each trail and its steps in order, with their dates, a "Today" line between the steps
 *   behind and the steps ahead (trails.ts todayIndex()).
 * - For a learner of the trail each step carries its state (done, current, late, locked, upcoming) as a class and
 *   data-state from that fixed vocabulary, and the trail's row counts the steps done; for anyone else the bars are the
 *   trail colour, the schedule alone. The event types' colours never reach this view.
 * - A step opens its popover (TrailPopover.tsx) on a press, Enter or Space: the bars and the agenda's names are
 *   buttons.
 *
 * Its ONE ResizeObserver watches the frame it is drawn in and reports the frame's width alone, the grids' rule
 * (MonthGrid.tsx PHONE): the frame's width does not depend on what it holds, so the phone decision cannot feed back
 * into the measure. Nothing is measured on the mount path, and no layout is read to place a bar: the bars are grid
 * columns.
 *
 * @module     theme_dimensions/calendar/TrailView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Fragment, useCallback, useEffect, useRef, useState } from "react";
import { say, shiftMonth } from "../daterange/calendar";
import { isWeekend } from "./days";
import { safeHref } from "./model";
import { fill } from "./strings";
import TrailPopover, { stateLine, stepWhen } from "./TrailPopover";
import { barOf, monthDays, todayIndex } from "./trails";
const NEXT_MONTH = { month: "short" };
const LONG_MONTH = { month: "long", year: "numeric" };
const stepLabel = /* @__PURE__ */ __name((step, look, now) => [step.name, stepWhen(step, look), stateLine(step, look, now)].filter((part) => part !== "").join(", "), "stepLabel");
const progressOf = /* @__PURE__ */ __name((trail, look) => trail.enrolled ? fill(look.strings.calendar_trailprogress, { done: trail.done, count: trail.steps.length }) : "", "progressOf");
const TrailFilters = /* @__PURE__ */ __name(({ strings, filter, answer, onChange, children }) => /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__toolbar", "data-region": "dim-cal-trailfilters", children: [
  /* @__PURE__ */ jsxDEV("label", { className: "dim-cal__trailpick", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: strings.calendar_trail }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 114,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "select",
      {
        className: "form-select dim-cal__select",
        "data-action": "dim-cal-trail",
        value: String(filter.trail),
        onChange: (event) => onChange({ trail: Number(event.currentTarget.value) }),
        children: [
          /* @__PURE__ */ jsxDEV("option", { value: "0", children: strings.calendar_trailall }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 121,
            columnNumber: 17
          }),
          (answer ? answer.options : []).map((option) => /* @__PURE__ */ jsxDEV("option", { value: String(option.id), children: option.name }, option.id, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 123,
            columnNumber: 21
          }))
        ]
      },
      void 0,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 115,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 113,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("label", { className: "dim-cal__trailmine", children: [
    /* @__PURE__ */ jsxDEV(
      "input",
      {
        type: "checkbox",
        "data-action": "dim-cal-trailmine",
        checked: filter.mine,
        onChange: (event) => onChange({ mine: event.currentTarget.checked })
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 128,
        columnNumber: 13
      }
    ),
    strings.calendar_trailmine
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 127,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__spacer" }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 136,
    columnNumber: 9
  }),
  children
] }, void 0, true, {
  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
  lineNumber: 112,
  columnNumber: 5
}), "TrailFilters");
const TrailView = /* @__PURE__ */ __name(({ look, month, answer, today, now, phone, captionid, onWidth }) => {
  const root = useRef(null);
  const width = useRef(onWidth);
  width.current = onWidth;
  const [popped, setPopped] = useState(null);
  useEffect(() => {
    const frame = root.current?.parentElement || null;
    if (!frame || typeof ResizeObserver === "undefined") {
      return void 0;
    }
    const observer = new ResizeObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.target === frame && entry.contentRect.width > 0) {
          width.current(entry.contentRect.width);
        }
      });
    });
    observer.observe(frame);
    return () => observer.disconnect();
  }, []);
  useEffect(() => setPopped(null), [answer]);
  const close = useCallback((refocus) => {
    setPopped((was) => {
      if (was && refocus) {
        was.opener.focus();
      }
      return null;
    });
  }, []);
  const days = monthDays(month);
  const next = shiftMonth(days[0], 1);
  const todayat = days.indexOf(today);
  const columns = { "--dim-cal-trail-days": String(days.length) };
  const poppedtrail = popped ? answer.trails.find((trail) => trail.id === popped.trail) : void 0;
  const poppedstep = poppedtrail?.steps.find((step) => step.cmid === popped?.cmid);
  const opener = /* @__PURE__ */ __name((trail, step, className, style, content) => /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className,
      style,
      "aria-label": stepLabel(step, look, now),
      "aria-expanded": popped?.cmid === step.cmid,
      "data-region": "dim-cal-trailstep",
      "data-cmid": step.cmid,
      "data-state": step.state || void 0,
      onClick: (event) => setPopped({ trail: trail.id, cmid: step.cmid, opener: event.currentTarget }),
      children: content
    },
    void 0,
    false,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 200,
      columnNumber: 9
    }
  ), "opener");
  const stepRow = /* @__PURE__ */ __name((trail, step) => {
    const bar = barOf(step, days, look.zone);
    const state = step.state ? ` dim-cal__trailbar--${step.state}` : "";
    let drawn = null;
    if (bar) {
      const continued = bar.after && !bar.open;
      const classes = "dim-cal__trailbar" + state + (bar.before ? " dim-cal__trailbar--before" : "") + (continued ? " dim-cal__trailbar--after" : "") + (bar.open ? " dim-cal__trailbar--open" : "") + (bar.milestone ? " dim-cal__trailbar--milestone" : "") + (step.next ? " dim-cal__trailbar--next" : "");
      const place = { gridColumn: `${bar.from + 2} / ${bar.to + 3 + (continued ? 1 : 0)}`, gridRow: "1" };
      drawn = opener(trail, step, classes, place, step.name);
    }
    return /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailrow", "data-cmid": step.cmid, style: columns, children: [
      /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traillabel", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailnum", "aria-hidden": "true", children: step.position }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 238,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailname", children: step.name }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 239,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 237,
        columnNumber: 17
      }),
      todayat >= 0 ? /* @__PURE__ */ jsxDEV(
        "span",
        {
          className: "dim-cal__trailnow",
          style: { gridColumn: String(todayat + 2), gridRow: "1" },
          "aria-hidden": "true"
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 242,
          columnNumber: 21
        }
      ) : null,
      drawn
    ] }, step.cmid, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 236,
      columnNumber: 13
    });
  }, "stepRow");
  const desk = /* @__PURE__ */ __name(() => /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailgrid", "data-region": "dim-cal-trailgrid", children: [
    /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailrow dim-cal__trailrow--head", style: columns, "aria-hidden": "true", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traillabel" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 261,
        columnNumber: 17
      }),
      days.map((iso, index) => /* @__PURE__ */ jsxDEV(
        "span",
        {
          className: `dim-cal__trailday${isWeekend(iso) ? " dim-cal__trailday--weekend" : ""}${index === todayat ? " dim-cal__trailday--today" : ""}`,
          children: Number(iso.slice(8, 10))
        },
        iso,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 263,
          columnNumber: 21
        }
      )),
      /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailday dim-cal__trailday--next", title: fill(
        look.strings.calendar_trailnext,
        say(next, LONG_MONTH, look.language)
      ), children: `${say(next, NEXT_MONTH, look.language)} \u2192` }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 269,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 260,
      columnNumber: 13
    }),
    answer.trails.map((trail) => /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trail", role: "group", "aria-label": trail.name, "data-trail": trail.id, children: [
      /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailrow dim-cal__trailrow--trail", style: columns, children: /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traillabel dim-cal__trailtitle", children: [
        /* @__PURE__ */ jsxDEV("a", { href: safeHref(trail.url, look.home), children: trail.name }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 278,
          columnNumber: 29
        }),
        trail.enrolled ? /* @__PURE__ */ jsxDEV("small", { className: "dim-cal__trailprogress", children: progressOf(trail, look) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 279,
          columnNumber: 47
        }) : null
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 277,
        columnNumber: 25
      }) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 276,
        columnNumber: 21
      }),
      trail.steps.map((step) => stepRow(trail, step))
    ] }, trail.id, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 275,
      columnNumber: 17
    }))
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 259,
    columnNumber: 9
  }), "desk");
  const agenda = /* @__PURE__ */ __name(() => /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailagenda", "data-region": "dim-cal-trailagenda", children: answer.trails.map((trail) => {
    const at = todayIndex(trail.steps, now);
    return /* @__PURE__ */ jsxDEV("section", { className: "dim-cal__trail", "aria-label": trail.name, "data-trail": trail.id, children: [
      /* @__PURE__ */ jsxDEV("h3", { className: "dim-cal__trailtitle", children: [
        /* @__PURE__ */ jsxDEV("a", { href: safeHref(trail.url, look.home), children: trail.name }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 300,
          columnNumber: 29
        }),
        trail.enrolled ? /* @__PURE__ */ jsxDEV("small", { className: "dim-cal__trailprogress", children: progressOf(trail, look) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 301,
          columnNumber: 47
        }) : null
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 299,
        columnNumber: 25
      }),
      /* @__PURE__ */ jsxDEV("ol", { className: "dim-cal__trailsteps", children: [
        trail.steps.map((step, index) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
          index === at ? /* @__PURE__ */ jsxDEV("li", { className: "dim-cal__trailtoday", "data-region": "dim-cal-trailtoday", children: /* @__PURE__ */ jsxDEV("span", { children: look.strings.calendar_today }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 308,
            columnNumber: 45
          }) }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 307,
            columnNumber: 41
          }) : null,
          /* @__PURE__ */ jsxDEV("li", { className: `dim-cal__trailstep${step.state ? ` dim-cal__trailstep--${step.state}` : ""}`, children: [
            /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailnum", "aria-hidden": "true", children: step.position }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 312,
              columnNumber: 41
            }),
            opener(trail, step, "dim-cal__trailstepname", void 0, step.name),
            /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailwhen", children: stepWhen(step, look) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 314,
              columnNumber: 41
            }),
            step.state ? /* @__PURE__ */ jsxDEV("span", { className: `dim-cal__trailstate dim-cal__trailstate--${step.state}`, children: stateLine(step, look, now) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 316,
              columnNumber: 45
            }) : null
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 311,
            columnNumber: 37
          })
        ] }, step.cmid, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 305,
          columnNumber: 33
        })),
        at === trail.steps.length ? /* @__PURE__ */ jsxDEV("li", { className: "dim-cal__trailtoday", "data-region": "dim-cal-trailtoday", children: /* @__PURE__ */ jsxDEV("span", { children: look.strings.calendar_today }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 325,
          columnNumber: 37
        }) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 324,
          columnNumber: 33
        }) : null
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 303,
        columnNumber: 25
      })
    ] }, trail.id, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 298,
      columnNumber: 21
    });
  }) }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 294,
    columnNumber: 9
  }), "agenda");
  return /* @__PURE__ */ jsxDEV(
    "section",
    {
      ref: root,
      className: `dim-cal__trails${phone ? " dim-cal__trails--phone" : ""}`,
      role: "region",
      "aria-labelledby": captionid,
      "data-region": "dim-cal-trails",
      children: [
        answer.truncated ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__status", children: look.strings.calendar_trailstruncated }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 343,
          columnNumber: 33
        }) : null,
        answer.trails.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__empty", "data-region": "dim-cal-trailsempty", children: look.strings.calendar_trailsempty }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 345,
          columnNumber: 17
        }) : null,
        answer.trails.length > 0 && !phone ? desk() : null,
        answer.trails.length > 0 && phone ? agenda() : null,
        popped && poppedtrail && poppedstep ? /* @__PURE__ */ jsxDEV(
          TrailPopover,
          {
            look,
            trail: poppedtrail,
            step: poppedstep,
            opener: popped.opener,
            sheet: phone,
            now,
            onClose: close
          },
          void 0,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 350,
            columnNumber: 17
          }
        ) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 336,
      columnNumber: 9
    }
  );
}, "TrailView");
var TrailView_default = TrailView;
export {
  TrailFilters,
  TrailView_default as default
};
//# sourceMappingURL=TrailView.dev.js.map
