var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The week: a time grid on a desktop, a seven-day agenda on a phone (docs/calendar/PROPOSAL.md sections 21.2, 21.3).
 *
 * The grid is role="grid" labelled by the caption, in three rows: the day headers (seven columnheaders, each holding
 * the day's button), the all-day strip (a rowheader, then seven gridcells holding the bars that start on each day, laid
 * out by layout.ts weekLayout() so a bar spans its columns) and the hour body (a rowheader, then a gridcell per day
 * with its timed items in the drawer's columns, over the week's hour window: no scroll box of its own). The layout is
 * weeks.ts weekPlan(), pure.
 *
 * - One Tab stop, the selected day's header (roving tabindex). On a header the arrows move a day (past the week's ends
 *   the shell fetches the adjacent week), Home and End go to the week's ends, PageUp and PageDown a week; Enter opens
 *   the day (the shell's drawer); ArrowDown or F2 enters the day's items. On an item ArrowUp and ArrowDown walk the
 *   day's items, ArrowLeft and ArrowRight the neighbouring day's nearest in time, Escape goes back to the header, and
 *   Enter or Space is left to the shell's delegated listener, which opens the popover (weeks.ts moveInWeek()).
 * - Every item is a link to its own page marked data-cal-pop, as the month's are, out of the Tab order.
 * - The "now" line marks today's column at the server's time plus the minutes since the paint (the browser's clock is
 *   read only as time elapsed), aria-hidden.
 * - ONE ResizeObserver, on the frame the week shares with the drawer, never read on the mount path: below
 *   MonthGrid.PHONE the shell draws the agenda instead, seven sections whose header opens the day's sheet.
 *
 * @module     theme_dimensions/calendar/WeekGrid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";
import { say } from "../daterange/calendar";
import Bar from "./Bar";
import { dayLabel } from "./DayCell";
import { dayCounts } from "./layout";
import { colourStyle, keyClass, whenOf } from "./look";
import { itemHref } from "./model";
import { itemKey } from "./overlap";
import { hourBox, moveInWeek, nowLine, stepWeekFrom, weekPlan, weekStops } from "./weeks";
const TICK = 6e4;
const WEEKDAY = { weekday: "short" };
const LONG_DAY = { weekday: "long", day: "numeric", month: "long" };
const SPAN_DAY = { day: "numeric", month: "short" };
const hourLabels = /* @__PURE__ */ __name((language) => {
  const format = new Intl.DateTimeFormat(language, { hour: "2-digit", minute: "2-digit", timeZone: "UTC" });
  return Array.from({ length: 24 }, (unused, hour) => format.format(new Date(Date.UTC(1970, 0, 1, hour))));
}, "hourLabels");
const alldayWhen = /* @__PURE__ */ __name((entry, look) => entry.first === entry.last ? look.strings.calendar_allday : `${say(entry.first, SPAN_DAY, look.language)}\u2013${say(entry.last, SPAN_DAY, look.language)}`, "alldayWhen");
const WeekGrid = /* @__PURE__ */ __name((props) => {
  const { look, days, placed, today, now, selected, focusing, captionid, phone, onSelect, onWeek, onOpen } = props;
  const [elapsed, setElapsed] = useState(0);
  const root = useRef(null);
  const cursor = useRef(null);
  const width = useRef(props.onWidth);
  width.current = props.onWidth;
  const plan = useMemo(() => weekPlan(days, placed, look.zone), [days, placed, look.zone]);
  const stops = useMemo(() => weekStops(plan, look.zone), [plan, look.zone]);
  const counts = useMemo(() => dayCounts(days, placed), [days, placed]);
  const labels = useMemo(() => hourLabels(look.language), [look.language]);
  const line = nowLine(days, (Number(now) || 0) + elapsed, look.zone, plan.window);
  useEffect(() => {
    const frame = root.current?.parentElement || null;
    if (!frame || typeof ResizeObserver === "undefined") {
      return void 0;
    }
    const observer = new ResizeObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.contentRect.width > 0) {
          width.current(entry.contentRect.width);
        }
      });
    });
    observer.observe(frame);
    return () => observer.disconnect();
  }, []);
  useEffect(() => {
    const started = Date.now();
    const timer = window.setInterval(() => setElapsed(Math.floor((Date.now() - started) / 1e3)), TICK);
    return () => window.clearInterval(timer);
  }, []);
  useLayoutEffect(() => {
    if (focusing) {
      root.current?.querySelector(`[data-role="day"][data-day="${selected}"]`)?.focus();
    }
  }, [focusing]);
  const itemElement = /* @__PURE__ */ __name((key) => {
    const [source, id] = [key.slice(0, key.lastIndexOf("-")), key.slice(key.lastIndexOf("-") + 1)];
    return root.current?.querySelector(
      `[data-region="dim-cal-weekgrid"] [data-cal-pop][data-source="${source}"][data-id="${id}"]`
    ) || null;
  }, "itemElement");
  const go = /* @__PURE__ */ __name((next) => {
    if (next.index < 0) {
      cursor.current = null;
      onSelect(days[next.column]);
      return;
    }
    cursor.current = next;
    itemElement(stops[next.column][next.index].key)?.focus();
  }, "go");
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    const target = event.target;
    if (event.defaultPrevented) {
      return;
    }
    if (target.dataset.role === "day") {
      const iso = target.dataset.day || selected;
      const into = moveInWeek(event.key, { column: days.indexOf(iso), index: -1 }, stops);
      if (into) {
        event.preventDefault();
        go(into);
        return;
      }
      const day = stepWeekFrom(event.key, iso, days);
      if (!day) {
        return;
      }
      event.preventDefault();
      if (days.includes(day)) {
        onSelect(day);
      } else {
        onWeek(day);
      }
      return;
    }
    const element = target.closest("[data-cal-pop]");
    if (!element) {
      return;
    }
    const key = `${element.dataset.source || ""}-${element.dataset.id || ""}`;
    let at = cursor.current;
    if (!at || stops[at.column]?.[at.index]?.key !== key) {
      const column = Math.max(0, days.indexOf(element.dataset.day || element.dataset.first || ""));
      at = { column, index: stops[column].findIndex((stop) => stop.key === key) };
    }
    const next = at.index < 0 ? null : moveInWeek(event.key, at, stops);
    if (!next) {
      return;
    }
    event.preventDefault();
    go(next);
  }, "onKeyDown");
  const header = /* @__PURE__ */ __name((iso, roving, content) => /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className: `dim-cal__weekhead${iso === today ? " dim-cal__weekhead--today" : ""}`,
      tabIndex: roving && iso !== selected ? -1 : 0,
      "aria-label": dayLabel(look, iso, counts.get(iso) || 0, iso === today),
      "aria-current": iso === today ? "date" : void 0,
      "data-role": "day",
      "data-day": iso,
      onClick: () => onOpen(iso),
      children: content
    },
    void 0,
    false,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
      lineNumber: 243,
      columnNumber: 9
    }
  ), "header");
  const timed = /* @__PURE__ */ __name((laid, iso) => {
    const item = laid.entry.item;
    const box = plan.window ? hourBox(laid, look.zone, plan.window) : { top: 0, height: 0, left: 0, width: 1 };
    const style = {
      ...colourStyle(item, look),
      "--dim-cal-top": `${box.top * 100}%`,
      "--dim-cal-height": `${box.height * 100}%`,
      "--dim-cal-share-left": String(box.left),
      "--dim-cal-share-width": String(box.width)
    };
    const overlapping = laid.overlaps.length > 0;
    return /* @__PURE__ */ jsxDEV(
      "a",
      {
        className: `dim-cal__weekitem ${keyClass(item)}${item.timeduration > 0 ? "" : " dim-cal__weekitem--point"}${overlapping ? " dim-cal__dayitem--overlap" : ""}`,
        href: itemHref(item, look.courses, look.home),
        style,
        tabIndex: -1,
        "data-cal-pop": "",
        "data-source": item.source,
        "data-id": item.id,
        "data-day": iso,
        "data-column": laid.column,
        "data-columns": laid.columns,
        children: [
          overlapping ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__overlapmark", children: look.strings.calendar_overlapmark }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
            lineNumber: 288,
            columnNumber: 32
          }) : null,
          /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__weekname", children: item.name }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
            lineNumber: 289,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__weekwhen", children: whenOf(item, look) }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
            lineNumber: 290,
            columnNumber: 17
          })
        ]
      },
      itemKey(item),
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
        lineNumber: 274,
        columnNumber: 13
      }
    );
  }, "timed");
  const agendaItem = /* @__PURE__ */ __name((entry, when, overlapping) => /* @__PURE__ */ jsxDEV("li", { children: /* @__PURE__ */ jsxDEV(
    "a",
    {
      className: `dim-cal__dayitem dim-cal__dayitem--line ${keyClass(entry.item)}${overlapping ? " dim-cal__dayitem--overlap" : ""}`,
      href: itemHref(entry.item, look.courses, look.home),
      style: colourStyle(entry.item, look),
      "data-cal-pop": "",
      "data-source": entry.item.source,
      "data-id": entry.item.id,
      children: [
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__daywhen", children: when }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
          lineNumber: 314,
          columnNumber: 17
        }),
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__dayname", children: entry.item.name }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
          lineNumber: 315,
          columnNumber: 17
        }),
        overlapping ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__overlapmark", children: look.strings.calendar_overlapmark }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
          lineNumber: 316,
          columnNumber: 32
        }) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
      lineNumber: 305,
      columnNumber: 13
    }
  ) }, itemKey(entry.item), false, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
    lineNumber: 304,
    columnNumber: 9
  }), "agendaItem");
  const agendaDay = /* @__PURE__ */ __name((iso, column) => {
    const strip = plan.strip.bars.filter((segment) => segment.start <= column && column <= segment.end).sort((a, b) => a.slot - b.slot);
    const empty = !strip.length && !plan.columns[column].length;
    return /* @__PURE__ */ jsxDEV("section", { className: "dim-cal__weekagendaday", "data-day": iso, children: [
      /* @__PURE__ */ jsxDEV("h3", { className: "dim-cal__weekagendatitle", children: header(iso, false, /* @__PURE__ */ jsxDEV("span", { children: say(iso, LONG_DAY, look.language) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
        lineNumber: 335,
        columnNumber: 41
      })) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
        lineNumber: 334,
        columnNumber: 17
      }),
      empty ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__dayempty", children: look.strings.calendar_dayempty }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
        lineNumber: 337,
        columnNumber: 26
      }) : /* @__PURE__ */ jsxDEV("ul", { className: "dim-cal__weekagendalist", children: [
        strip.map((segment) => agendaItem(segment.placed, alldayWhen(segment.placed, look), false)),
        plan.columns[column].map((laid) => agendaItem(
          laid.entry,
          whenOf(laid.entry.item, look),
          laid.overlaps.length > 0
        ))
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
        lineNumber: 338,
        columnNumber: 21
      })
    ] }, iso, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
      lineNumber: 333,
      columnNumber: 13
    });
  }, "agendaDay");
  if (phone) {
    return /* @__PURE__ */ jsxDEV("div", { ref: root, className: "dim-cal__weekview", "data-region": "dim-cal-week", children: /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__weekagenda", "data-region": "dim-cal-weekagenda", "aria-labelledby": captionid, role: "group", children: days.map(agendaDay) }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
      lineNumber: 351,
      columnNumber: 17
    }) }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
      lineNumber: 350,
      columnNumber: 13
    });
  }
  const used = plan.strip.used;
  return /* @__PURE__ */ jsxDEV("div", { ref: root, className: "dim-cal__weekview", "data-region": "dim-cal-week", children: [
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        className: "dim-cal__weekgrid",
        role: "grid",
        "aria-labelledby": captionid,
        "data-region": "dim-cal-weekgrid",
        onKeyDown,
        onBlur: (event) => {
          if (!root.current?.contains(event.relatedTarget)) {
            cursor.current = null;
          }
        },
        children: [
          /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__weekrow dim-cal__weekheads", role: "row", children: [
            /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__weekgutter", role: "presentation" }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
              lineNumber: 374,
              columnNumber: 21
            }),
            days.map((iso) => /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__weekheadcell", role: "columnheader", children: header(iso, true, /* @__PURE__ */ jsxDEV(Fragment, { children: [
              /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__weekdayname", "aria-hidden": "true", children: say(iso, WEEKDAY, look.language) }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
                lineNumber: 379,
                columnNumber: 37
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__weekdaynum", "aria-hidden": "true", children: Number(iso.slice(8, 10)) }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
                lineNumber: 382,
                columnNumber: 37
              })
            ] }, void 0, true, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
              lineNumber: 378,
              columnNumber: 33
            })) }, iso, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
              lineNumber: 376,
              columnNumber: 25
            }))
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
            lineNumber: 373,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__weekrow dim-cal__weekstrip", role: "row", "data-region": "dim-cal-weekstrip", children: [
            /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__weekgutter dim-cal__weekgutter--strip", role: "rowheader", children: look.strings.calendar_allday }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
              lineNumber: 389,
              columnNumber: 21
            }),
            /* @__PURE__ */ jsxDEV(
              "div",
              {
                className: "dim-cal__weekstripgrid",
                role: "presentation",
                style: { gridTemplateRows: `0 repeat(${used}, 1.5rem)` },
                children: days.map((iso, column) => /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__day", role: "gridcell", "data-day": iso, children: [
                  /* @__PURE__ */ jsxDEV(
                    "div",
                    {
                      className: `dim-cal__ground${iso === selected ? " dim-cal__ground--selected" : ""}`,
                      style: { gridColumn: column + 1, gridRow: "1 / -1" },
                      "aria-hidden": "true"
                    },
                    void 0,
                    false,
                    {
                      fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
                      lineNumber: 399,
                      columnNumber: 33
                    }
                  ),
                  plan.strip.bars.filter((segment) => segment.start === column).map((segment) => /* @__PURE__ */ jsxDEV(Bar, { segment, week: days, look }, itemKey(segment.placed.item), false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
                    lineNumber: 405,
                    columnNumber: 37
                  }))
                ] }, iso, true, {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
                  lineNumber: 398,
                  columnNumber: 29
                }))
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
                lineNumber: 392,
                columnNumber: 21
              }
            )
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
            lineNumber: 388,
            columnNumber: 17
          }),
          plan.window ? /* @__PURE__ */ jsxDEV(
            "div",
            {
              className: "dim-cal__weekrow dim-cal__weekhours",
              role: "row",
              "data-region": "dim-cal-weekhours",
              style: { "--dim-cal-hourcount": String(plan.window.to - plan.window.from) },
              children: [
                /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__weekgutter dim-cal__weekgutter--hours", role: "rowheader", children: [
                  /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: look.strings.calendar_timed }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
                    lineNumber: 419,
                    columnNumber: 29
                  }),
                  labels.slice(plan.window.from, plan.window.to).map((label, hour) => /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__hour", "aria-hidden": "true", children: label }, hour, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
                    lineNumber: 421,
                    columnNumber: 33
                  }))
                ] }, void 0, true, {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
                  lineNumber: 418,
                  columnNumber: 25
                }),
                days.map((iso, column) => /* @__PURE__ */ jsxDEV(
                  "div",
                  {
                    className: `dim-cal__weekcol${iso === selected ? " dim-cal__weekcol--selected" : ""}`,
                    role: "gridcell",
                    "data-region": "dim-cal-weekcol",
                    "data-day": iso,
                    children: [
                      line !== null && line.column === column ? /* @__PURE__ */ jsxDEV(
                        "span",
                        {
                          className: "dim-cal__now",
                          style: { "--dim-cal-top": `${line.share * 100}%` },
                          "aria-hidden": "true",
                          "data-region": "dim-cal-now"
                        },
                        void 0,
                        false,
                        {
                          fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
                          lineNumber: 433,
                          columnNumber: 37
                        }
                      ) : null,
                      plan.columns[column].map((laid) => timed(laid, iso))
                    ]
                  },
                  iso,
                  true,
                  {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
                    lineNumber: 425,
                    columnNumber: 29
                  }
                ))
              ]
            },
            void 0,
            true,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
              lineNumber: 412,
              columnNumber: 21
            }
          ) : null
        ]
      },
      void 0,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
        lineNumber: 361,
        columnNumber: 13
      }
    ),
    !plan.window && !plan.strip.bars.length ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__dayempty", "data-region": "dim-cal-weekempty", children: look.strings.calendar_weekempty }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
      lineNumber: 447,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/WeekGrid.tsx",
    lineNumber: 360,
    columnNumber: 9
  });
}, "WeekGrid");
var WeekGrid_default = WeekGrid;
export {
  TICK,
  WeekGrid_default as default
};
//# sourceMappingURL=WeekGrid.dev.js.map
