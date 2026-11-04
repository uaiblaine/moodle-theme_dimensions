var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The Trilhas view (docs/trail/PLAN.md section 7): the month's trails, one row per trail and its steps under it.
 *
 * - On a desktop, a grid of the month's days: each step a bar from its start to its end (trails.ts barOf()), a
 *   diamond on an expected completion date, a bar that passes the month's end continued by a chip in the last column
 *   ("Dec →"), one that began earlier marked at the left edge, an open one fading out; the trail's own row carries
 *   its span (trails.ts trailBarOf()). Weekends are hatched and today is a line down the rows. R3 (PROPOSAL.md section
 *   33): the days have a fixed width and only they scroll, in TrailScroll.tsx, the trail column and the last column held
 *   still; a trail's chevron folds its steps away (the view's own state, never saved); the legend follows the grid.
 * - On a phone, an agenda: each trail in a card, a learner's progress as one segment per step, the steps in order with
 *   their dates, a "Today" line between the steps behind and the steps ahead (trails.ts todayIndex()).
 * - For a learner of the trail each step carries its state (done, current, late, locked, upcoming) as a class and
 *   data-state from that fixed vocabulary, and the trail's row counts the steps done; for anyone else the bars are the
 *   trail colour, the schedule alone. The event types' colours never reach this view.
 * - A step opens its popover (TrailPopover.tsx) on a press, Enter or Space: the bars and the agenda's rows are buttons
 *   named by the step; the last column's chip repeats its bar for the pointer and is out of the tab order and the tree.
 *
 * Its ONE ResizeObserver watches the frame it is drawn in and reports the frame's width alone, the grids' rule
 * (MonthGrid.tsx PHONE): the frame's width does not depend on what it holds, so the phone decision cannot feed back
 * into the measure. Nothing is measured here, and no layout is read to place a bar: the bars, the weekends and today
 * are grid columns or multiples of the day's width the stylesheet declares; the scroller's reads are TrailScroll's.
 *
 * @module     theme_dimensions/calendar/TrailView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Fragment as Fragment2, useCallback, useEffect, useRef, useState } from "react";
import { say, shiftMonth } from "../daterange/calendar";
import { isWeekend } from "./days";
import { safeHref } from "./model";
import { fill } from "./strings";
import TrailPopover, { TrailIcon, stateLabel, stateLine, stepWhen } from "./TrailPopover";
import TrailScroll from "./TrailScroll";
import { barOf, beyondOf, monthDays, todayIndex, trailBarOf } from "./trails";
const NEXT_MONTH = { month: "short" };
const LONG_MONTH = { month: "long", year: "numeric" };
const WEEKDAY = { weekday: "narrow" };
const SHORT_DAY = { day: "numeric", month: "short" };
const LEGEND = ["done", "current", "upcoming", "locked", "late"];
const stepLabel = /* @__PURE__ */ __name((step, look, now) => [step.name, stepWhen(step, look), stateLine(step, look, now)].filter((part) => part !== "").join(", "), "stepLabel");
const progressOf = /* @__PURE__ */ __name((trail, look) => trail.enrolled ? fill(look.strings.calendar_trailprogress, { done: trail.done, count: trail.steps.length }) : "", "progressOf");
const coursesOf = /* @__PURE__ */ __name((strings, count) => count === 1 ? strings.landing_trail_onecourse : fill(strings.landing_trail_coursecount, count), "coursesOf");
const metaOf = /* @__PURE__ */ __name((trail, look) => fill(look.strings.landing_trail_whenjoin, trail.enrolled ? { a: look.strings.calendar_trailenrolled, b: progressOf(trail, look) } : { a: look.strings.calendar_trailnotenrolled, b: coursesOf(look.strings, trail.steps.length) }), "metaOf");
const countOf = /* @__PURE__ */ __name((strings, answer) => fill(strings.landing_trail_whenjoin, {
  a: answer.trails.length === 1 ? strings.calendar_trailcountone : fill(strings.calendar_trailcountmany, answer.trails.length),
  b: coursesOf(strings, answer.trails.reduce((sum, trail) => sum + trail.steps.length, 0))
}), "countOf");
const compact = /* @__PURE__ */ __name((parts) => parts.map((part) => part.type === "literal" && /\p{L}/u.test(part.value) ? " " : part.value).join("").replace(/\s+/g, " ").trim(), "compact");
const shortOf = /* @__PURE__ */ __name((iso, language) => {
  try {
    return compact(new Intl.DateTimeFormat(language, { ...SHORT_DAY, timeZone: "UTC" }).formatToParts(/* @__PURE__ */ new Date(`${iso}T00:00:00Z`)));
  } catch (error) {
    return say(iso, SHORT_DAY, language);
  }
}, "shortOf");
const shortRange = /* @__PURE__ */ __name((from, to, language) => {
  try {
    return compact(new Intl.DateTimeFormat(language, { ...SHORT_DAY, timeZone: "UTC" }).formatRangeToParts(/* @__PURE__ */ new Date(`${from}T00:00:00Z`), /* @__PURE__ */ new Date(`${to}T00:00:00Z`)));
  } catch (error) {
    return `${shortOf(from, language)} \u2013 ${shortOf(to, language)}`;
  }
}, "shortRange");
const TrailFilters = /* @__PURE__ */ __name(({ strings, filter, answer, onChange, children }) => /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__toolbar dim-cal__trailtools", "data-region": "dim-cal-trailfilters", children: [
  /* @__PURE__ */ jsxDEV("label", { className: `dim-cal__trailpick${filter.trail > 0 ? " dim-cal__trailpick--chosen" : ""}`, children: [
    /* @__PURE__ */ jsxDEV(TrailIcon, {}, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 207,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: strings.calendar_trail }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 208,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "select",
      {
        className: "dim-cal__trailselect",
        "data-action": "dim-cal-trail",
        value: String(filter.trail),
        onChange: (event) => onChange({ trail: Number(event.currentTarget.value) }),
        children: [
          /* @__PURE__ */ jsxDEV("option", { value: "0", children: strings.calendar_trailall }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 215,
            columnNumber: 17
          }),
          (answer ? answer.options : []).map((option) => /* @__PURE__ */ jsxDEV("option", { value: String(option.id), children: option.name }, option.id, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 217,
            columnNumber: 21
          }))
        ]
      },
      void 0,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 209,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-down dim-cal__trailcaret", "aria-hidden": "true" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 220,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 206,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className: "btn btn-secondary dim-cal__trailmine",
      "data-action": "dim-cal-trailmine",
      "aria-pressed": filter.mine,
      onClick: () => onChange({ mine: !filter.mine }),
      children: [
        /* @__PURE__ */ jsxDEV("i", { className: "fa fa-user", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 229,
          columnNumber: 13
        }),
        strings.calendar_trailmine
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 222,
      columnNumber: 9
    }
  ),
  children,
  /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__spacer" }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 233,
    columnNumber: 9
  }),
  answer && answer.trails.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailcount", "data-region": "dim-cal-trailcount", children: countOf(strings, answer) }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 235,
    columnNumber: 13
  }) : null
] }, void 0, true, {
  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
  lineNumber: 205,
  columnNumber: 5
}), "TrailFilters");
const TrailView = /* @__PURE__ */ __name(({ look, month, answer, today, now, phone, captionid, chosen = 0, onWidth }) => {
  const root = useRef(null);
  const width = useRef(onWidth);
  width.current = onWidth;
  const [popped, setPopped] = useState(null);
  const [folded, setFolded] = useState([]);
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
  const fold = /* @__PURE__ */ __name((id) => {
    setFolded((now2) => now2.includes(id) ? now2.filter((one) => one !== id) : [...now2, id]);
  }, "fold");
  const days = monthDays(month);
  const next = shiftMonth(days[0], 1);
  const todayat = days.indexOf(today);
  const columns = { "--dim-cal-trail-days": String(days.length) };
  const poppedtrail = popped ? answer.trails.find((trail) => trail.id === popped.trail) : void 0;
  const poppedstep = poppedtrail?.steps.find((step) => step.cmid === popped?.cmid);
  const learner = answer.trails.some((trail) => trail.enrolled);
  const shortDay = /* @__PURE__ */ __name((timestamp) => shortOf(look.zone.day(timestamp), look.language), "shortDay");
  const stepDay = /* @__PURE__ */ __name((step) => {
    const at = step.end > 0 ? step.end : step.start;
    return at > 0 ? shortDay(at) : "";
  }, "stepDay");
  const opener = /* @__PURE__ */ __name((trail, step, className, style, content, quiet = false) => /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className,
      style,
      "aria-label": quiet ? void 0 : stepLabel(step, look, now),
      "aria-expanded": quiet ? void 0 : popped?.cmid === step.cmid,
      "aria-hidden": quiet ? "true" : void 0,
      tabIndex: quiet ? -1 : void 0,
      "data-region": quiet ? "dim-cal-trailchip" : "dim-cal-trailstep",
      "data-cmid": step.cmid,
      "data-state": step.state || void 0,
      onClick: (event) => setPopped({ trail: trail.id, cmid: step.cmid, opener: event.currentTarget }),
      children: content
    },
    void 0,
    false,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 333,
      columnNumber: 9
    }
  ), "opener");
  const stepRow = /* @__PURE__ */ __name((trail, step) => {
    const bar = barOf(step, days, look.zone);
    const beyond = beyondOf(step, days, look.zone);
    const tone = step.state ? `--${step.state}` : "";
    let drawn = null;
    let diamond = null;
    if (bar) {
      const continued = bar.after && !bar.open;
      const classes = "dim-cal__trailbar" + (tone ? ` dim-cal__trailbar${tone}` : "") + (bar.before ? " dim-cal__trailbar--before" : "") + (continued ? " dim-cal__trailbar--after" : "") + (bar.open ? " dim-cal__trailbar--open" : "") + (bar.milestone ? " dim-cal__trailbar--milestone" : "") + (step.next ? " dim-cal__trailbar--next" : "");
      drawn = opener(trail, step, classes, { gridColumn: `${bar.from + 2} / ${bar.to + 3}`, gridRow: "1" }, step.name);
      if (bar.milestone) {
        diamond = /* @__PURE__ */ jsxDEV(
          "span",
          {
            className: "dim-cal__traildiamond" + (tone ? ` dim-cal__traildiamond${tone}` : ""),
            style: { gridColumn: String(bar.to + 2), gridRow: "1" },
            "aria-hidden": "true"
          },
          void 0,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 372,
            columnNumber: 21
          }
        );
      }
    }
    let chip = "";
    if (beyond === "continues") {
      chip = `\u2192 ${shortDay(step.end)}`;
    } else if (beyond === "after") {
      chip = shortRange(look.zone.day(step.start), look.zone.day(step.end), look.language);
    }
    return /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailrow dim-cal__trailrow--step", "data-cmid": step.cmid, children: [
      /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traillabel dim-cal__trailsteplabel", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailnum" + (tone ? ` dim-cal__trailnum${tone}` : ""), "aria-hidden": "true", children: step.position }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 389,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailname", children: step.name }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 392,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 388,
        columnNumber: 17
      }),
      drawn,
      diamond,
      /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailspill", children: chip !== "" ? opener(
        trail,
        step,
        "dim-cal__trailchip" + (tone ? ` dim-cal__trailchip${tone}` : ""),
        void 0,
        chip,
        true
      ) : null }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 396,
        columnNumber: 17
      })
    ] }, step.cmid, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 387,
      columnNumber: 13
    });
  }, "stepRow");
  const trailRows = /* @__PURE__ */ __name((trail) => {
    const open = !folded.includes(trail.id);
    const span = trailBarOf(trail, days, look.zone);
    const steps = `dim-cal-trailsteps-${trail.id}`;
    return /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trail", role: "group", "aria-label": trail.name, "data-trail": trail.id, children: [
      /* @__PURE__ */ jsxDEV(
        "div",
        {
          className: "dim-cal__trailrow dim-cal__trailrow--trail" + (chosen === trail.id ? " dim-cal__trailrow--chosen" : ""),
          children: [
            /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traillabel dim-cal__trailcaption", children: [
              /* @__PURE__ */ jsxDEV(
                "button",
                {
                  type: "button",
                  className: "dim-cal__trailfold",
                  "data-action": "dim-cal-trailfold",
                  "aria-expanded": open,
                  "aria-controls": steps,
                  "aria-label": fill(look.strings.calendar_trailtoggle, trail.name),
                  onClick: () => fold(trail.id),
                  children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-right", "aria-hidden": "true" }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                    lineNumber: 432,
                    columnNumber: 29
                  })
                },
                void 0,
                false,
                {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 423,
                  columnNumber: 25
                }
              ),
              /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailnames", children: [
                /* @__PURE__ */ jsxDEV("a", { className: "dim-cal__trailname dim-cal__trailname--trail", href: safeHref(trail.url, look.home), children: trail.name }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 435,
                  columnNumber: 29
                }),
                /* @__PURE__ */ jsxDEV("small", { className: "dim-cal__trailmeta", "data-region": "dim-cal-trailmeta", children: metaOf(trail, look) }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 438,
                  columnNumber: 29
                })
              ] }, void 0, true, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 434,
                columnNumber: 25
              })
            ] }, void 0, true, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 422,
              columnNumber: 21
            }),
            span ? /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: "dim-cal__trailspan" + (span.before ? " dim-cal__trailspan--before" : "") + (span.after ? " dim-cal__trailspan--after" : ""),
                style: { gridColumn: `${span.from + 2} / ${span.to + 3}`, gridRow: "1" },
                "aria-hidden": "true"
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 442,
                columnNumber: 25
              }
            ) : null,
            span && !span.after ? /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: "dim-cal__traildiamond dim-cal__traildiamond--trail",
                style: { gridColumn: String(span.to + 2), gridRow: "1" },
                "aria-hidden": "true"
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 450,
                columnNumber: 25
              }
            ) : null,
            /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailspill dim-cal__trailspill--trail", children: span && span.after && !span.open ? fill(look.strings.calendar_until, shortDay(trail.last)) : "" }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 456,
              columnNumber: 21
            })
          ]
        },
        void 0,
        true,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 418,
          columnNumber: 17
        }
      ),
      /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailsteprows", id: steps, hidden: !open, children: trail.steps.map((step) => stepRow(trail, step)) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 460,
        columnNumber: 17
      })
    ] }, trail.id, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 417,
      columnNumber: 13
    });
  }, "trailRows");
  const desk = /* @__PURE__ */ __name(() => /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV(
      TrailScroll,
      {
        days: days.length,
        todayat,
        opening: `${month}:${answer.trails.map((trail) => trail.id).join(",")}`,
        strings: look.strings,
        children: /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailgrid", "data-region": "dim-cal-trailgrid", style: columns, children: [
          /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailrow dim-cal__trailrow--head", "aria-hidden": "true", children: [
            /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traillabel dim-cal__trailheading", children: look.strings.calendar_trailheading }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 482,
              columnNumber: 25
            }),
            days.map((iso, index) => /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: `dim-cal__trailday${isWeekend(iso) ? " dim-cal__trailday--weekend" : ""}${index === todayat ? " dim-cal__trailday--today" : ""}`,
                "data-day": iso,
                children: [
                  /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailweekday", children: say(iso, WEEKDAY, look.language) }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                    lineNumber: 490,
                    columnNumber: 33
                  }),
                  /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traildaynum", children: Number(iso.slice(8, 10)) }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                    lineNumber: 491,
                    columnNumber: 33
                  })
                ]
              },
              iso,
              true,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 484,
                columnNumber: 29
              }
            )),
            /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: "dim-cal__trailspill dim-cal__trailday dim-cal__trailday--next",
                title: fill(look.strings.calendar_trailnext, say(next, LONG_MONTH, look.language)),
                children: `${say(next, NEXT_MONTH, look.language)} \u2192`
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 494,
                columnNumber: 25
              }
            )
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 481,
            columnNumber: 21
          }),
          /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailground", "aria-hidden": "true", children: [
            days.map((iso, index) => isWeekend(iso) ? /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: "dim-cal__trailweekend",
                style: { "--dim-cal-trail-at": String(index) }
              },
              iso,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 503,
                columnNumber: 29
              }
            ) : null),
            todayat >= 0 ? /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: "dim-cal__trailnow",
                style: { "--dim-cal-trail-at": String(todayat) }
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 510,
                columnNumber: 29
              }
            ) : null
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 501,
            columnNumber: 21
          }),
          answer.trails.map((trail) => trailRows(trail))
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 480,
          columnNumber: 17
        })
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 474,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV("ul", { className: "dim-cal__traillegend", "data-region": "dim-cal-traillegend", children: [
      learner ? LEGEND.map((state) => /* @__PURE__ */ jsxDEV("li", { children: [
        /* @__PURE__ */ jsxDEV("span", { className: `dim-cal__trailswatch dim-cal__trailswatch--${state}`, "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 522,
          columnNumber: 25
        }),
        stateLabel(look.strings, state)
      ] }, state, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 521,
        columnNumber: 21
      })) : null,
      /* @__PURE__ */ jsxDEV("li", { children: [
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailswatch", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 527,
          columnNumber: 21
        }),
        look.strings.calendar_trailnotenrolled
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 526,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("li", { children: [
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traildiamond dim-cal__traildiamond--legend", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 531,
          columnNumber: 21
        }),
        look.strings.calendar_trailexpected
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 530,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("li", { children: [
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traillegendnow", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 535,
          columnNumber: 21
        }),
        look.strings.calendar_today
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 534,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 519,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 473,
    columnNumber: 9
  }), "desk");
  const todayLine = /* @__PURE__ */ __name(() => /* @__PURE__ */ jsxDEV("li", { className: "dim-cal__trailtoday", "data-region": "dim-cal-trailtoday", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailtodayword", children: look.strings.calendar_today }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 549,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailtodayline", "aria-hidden": "true" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 550,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailtodaydate", children: shortOf(today, look.language) }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 551,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 548,
    columnNumber: 9
  }), "todayLine");
  const agenda = /* @__PURE__ */ __name(() => /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailagenda", "data-region": "dim-cal-trailagenda", children: answer.trails.map((trail) => {
    const at = todayIndex(trail.steps, now);
    return /* @__PURE__ */ jsxDEV(
      "section",
      {
        className: "dim-cal__trail dim-cal__trailsheet",
        "aria-label": trail.name,
        "data-trail": trail.id,
        children: [
          /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailsheethead", children: [
            /* @__PURE__ */ jsxDEV("h3", { className: "dim-cal__trailtitle", children: /* @__PURE__ */ jsxDEV("a", { href: safeHref(trail.url, look.home), children: trail.name }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 573,
              columnNumber: 33
            }) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 572,
              columnNumber: 29
            }),
            /* @__PURE__ */ jsxDEV("small", { className: "dim-cal__trailprogress", children: trail.enrolled ? progressOf(trail, look) : coursesOf(look.strings, trail.steps.length) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 575,
              columnNumber: 29
            })
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 571,
            columnNumber: 25
          }),
          trail.enrolled ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailsegments", "aria-hidden": "true", children: trail.steps.map((step) => /* @__PURE__ */ jsxDEV(
            "span",
            {
              className: "dim-cal__trailsegment" + (step.state ? ` dim-cal__trailsegment--${step.state}` : "")
            },
            step.cmid,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 582,
              columnNumber: 37
            }
          )) }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 580,
            columnNumber: 29
          }) : null,
          /* @__PURE__ */ jsxDEV("ol", { className: "dim-cal__trailsteps", children: [
            trail.steps.map((step, index) => /* @__PURE__ */ jsxDEV(Fragment2, { children: [
              index === at ? todayLine() : null,
              /* @__PURE__ */ jsxDEV("li", { className: `dim-cal__trailstep${step.state ? ` dim-cal__trailstep--${step.state}` : ""}`, children: opener(trail, step, "dim-cal__trailstepname", void 0, /* @__PURE__ */ jsxDEV(Fragment, { children: [
                /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailstepdate", children: stepDay(step) }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 597,
                  columnNumber: 49
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailstepbar", "aria-hidden": "true" }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 598,
                  columnNumber: 49
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailsteptext", children: [
                  /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailstepline", children: `${step.position}. ${step.name}` }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                    lineNumber: 600,
                    columnNumber: 53
                  }),
                  /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailwhen", children: stepWhen(step, look) }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                    lineNumber: 603,
                    columnNumber: 53
                  }),
                  step.state ? /* @__PURE__ */ jsxDEV("span", { className: `dim-cal__trailstate dim-cal__trailstate--${step.state}`, children: stateLine(step, look, now) }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                    lineNumber: 605,
                    columnNumber: 57
                  }) : null
                ] }, void 0, true, {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 599,
                  columnNumber: 49
                })
              ] }, void 0, true, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 596,
                columnNumber: 45
              })) }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 594,
                columnNumber: 37
              })
            ] }, step.cmid, true, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 592,
              columnNumber: 33
            })),
            at === trail.steps.length ? todayLine() : null
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 590,
            columnNumber: 25
          })
        ]
      },
      trail.id,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 565,
        columnNumber: 21
      }
    );
  }) }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 561,
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
          lineNumber: 631,
          columnNumber: 33
        }) : null,
        answer.trails.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__empty", "data-region": "dim-cal-trailsempty", children: look.strings.calendar_trailsempty }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 633,
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
            lineNumber: 638,
            columnNumber: 17
          }
        ) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 624,
      columnNumber: 9
    }
  );
}, "TrailView");
var TrailView_default = TrailView;
export {
  TrailFilters,
  countOf,
  TrailView_default as default
};
//# sourceMappingURL=TrailView.dev.js.map
