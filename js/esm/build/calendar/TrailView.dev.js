var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The Trilhas view: the trails of the months it holds, one row per trail and its steps under it.
 *
 * - On a desktop, a timeline of contiguous months (trails.ts Loaded): the month in view and the next on a first paint,
 *   another month asked for when the scroll nears the end of what is held or the arrows go back past its start, at most
 *   trails.ts LOADED_CAP held. Each step a bar from its start to its end (trails.ts barOf()), a diamond on an expected
 *   completion date, one that began earlier marked at the left edge, one that runs on past what is held cut there, an
 *   open one fading out; the trail's own row carries its span (trails.ts trailBarOf()). Above the days a band names each
 *   month held, its name a button that scrolls to its 1st; a month on its way is a quiet skeleton at the end. Weekends
 *   are hatched, a month's first day is a line, and today is a line down the rows. Consecutive steps are joined by
 *   connectors in the trail's order (trails.ts linksOf()), drawn by the same day arithmetic, aria-hidden. The days have
 *   a fixed width and only they scroll, in TrailScroll.tsx, the trail column held still; a trail's chevron (core's
 *   course-section toggle, its icons rendered by the server) folds its steps away, the view's own state, never saved;
 *   the legend follows the grid.
 * - A course's row in the trail column is a button: it scrolls the timeline to the course's bar (asking for its month
 *   first when it is not held), then opens the bar's popover once the scroll has ended; a course with no date opens it
 *   on the row. Under its name a quieter line gives its dates (trails.ts datesOf()), which its accessible name carries
 *   too. A mouse over a row lights its bar and over a bar its row (a touch never does).
 * - The desktop timeline has a zoom (trails.ts Zoom, the page's: `zoom`), days, weeks or months, which sets a day's width
 *   (the stylesheet's --dim-cal-trail-day under the section's --week or --month modifier, trails.ts dayWidth() for the
 *   arithmetic). By days the header is the weekdays and the days, today a pill, the weekends hatched; by weeks it is the
 *   weeks from the viewer's first weekday (trails.ts weekCells()), each a short month over its days, the week holding
 *   today tinted; by months the band alone names the months and the header draws the weeks' lines. Off the days no
 *   weekend is shaded; at every zoom a small "Today" tag tops today's line. A bar too narrow for its name
 *   (trails.ts namedBar()) draws none, and is still named by its step for a screen reader.
 * - On a phone, an agenda: each trail in a card, a learner's progress as one segment per step, the steps in order with
 *   their dates, a "Today" line between the steps behind and the steps ahead (trails.ts todayIndex()).
 * - For a learner of the trail each step carries its state (done, current, late, locked, upcoming) as a class and
 *   data-state from that fixed vocabulary, and the trail's row counts the steps done; for anyone else the bars are the
 *   trail colour, the schedule alone. The event types' colours never reach this view.
 * - A step opens its popover (TrailPopover.tsx) on a press, Enter or Space: the bars and the agenda's rows are buttons
 *   named by the step. The popover is drawn beside the scroller, never inside it, so its overflow cannot cut it.
 * - The toolbar's arrows and "Today" ask the view (`nav`): a month back or ahead of the first day in view, animated,
 *   asking for it when needed; the toolbar's title is the months in view (`onRange`), and the address's month is the
 *   month of the first day in view once a scroll settles (`onMonth`): the page's history writes it, never this view.
 *
 * Its ONE ResizeObserver watches the frame it is drawn in and reports the frame's width alone, the grids' rule
 * (MonthGrid.tsx PHONE): the frame's width does not depend on what it holds, so the phone decision cannot feed back
 * into the measure. Nothing is measured here, and no layout is read to place a bar or a connector: the bars, the
 * weekends and today are grid columns or multiples of the day's width the stylesheet declares; the scroller's reads are
 * TrailScroll's.
 *
 * @module     theme_dimensions/calendar/TrailView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Fragment as Fragment2, useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";
import Pending from "@moodle/lms/core/pending";
import { say } from "../daterange/calendar";
import { isWeekend, monthAfter } from "./days";
import { safeHref } from "./model";
import { fill } from "./strings";
import TrailPopover, { TrailIcon, stateLabel, stateLine, stepWhen } from "./TrailPopover";
import TrailScroll from "./TrailScroll";
import {
  ANSWER_MONTHS,
  LOADED_CAP,
  SKELETON_DAYS,
  ZOOMS,
  barOf,
  datesOf,
  emptyTrails,
  extendLoaded,
  linksOf,
  loadedOf,
  mergedOf,
  monthDays,
  monthStep,
  namedBar,
  todayIndex,
  trailBarOf,
  weekCells,
  weekLabel,
  windowDays
} from "./trails";
const TRIES = 3;
const NO_ICONS = { expanded: "", collapsed: "", collapsedrtl: "" };
const MONTH_YEAR = { month: "long", year: "numeric" };
const MONTH_ONLY = { month: "long" };
const WEEKDAY = { weekday: "narrow" };
const SHORT_DAY = { day: "numeric", month: "short" };
const LEGEND = ["done", "current", "upcoming", "locked", "late"];
const freshWin = /* @__PURE__ */ __name((answer, month, generation, scope) => ({
  loaded: loadedOf(answer ?? emptyTrails(month)),
  generation,
  scope,
  shift: { key: 0, days: 0 },
  loading: false
}), "freshWin");
const dated = /* @__PURE__ */ __name((step) => step.start > 0 || step.end > 0, "dated");
const stepLabel = /* @__PURE__ */ __name((step, look, now) => [step.name, stepWhen(step, look), stateLine(step, look, now)].filter((part) => part !== "").join(", "), "stepLabel");
const datesLine = /* @__PURE__ */ __name((step, look) => {
  const dates = datesOf(step.start, step.end, look.zone, look.language);
  if (dates.kind === "from") {
    return fill(look.strings.calendar_trailfrom, dates.text);
  }
  if (dates.kind === "until") {
    return fill(look.strings.calendar_until, dates.text);
  }
  return dates.kind === "none" ? look.strings.calendar_trailnodates : dates.text;
}, "datesLine");
const zoomLabel = /* @__PURE__ */ __name((strings, zoom) => {
  if (zoom === "week") {
    return strings.calendar_trailzoomweek;
  }
  return zoom === "month" ? strings.calendar_trailzoommonth : strings.calendar_trailzoomday;
}, "zoomLabel");
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
const TrailFilters = /* @__PURE__ */ __name(({ strings, filter, answer, onChange, zoom = "day", onZoom, phone = false, children }) => /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__toolbar dim-cal__trailtools", "data-region": "dim-cal-trailfilters", children: [
  /* @__PURE__ */ jsxDEV("label", { className: `dim-cal__trailpick${filter.trail > 0 ? " dim-cal__trailpick--chosen" : ""}`, children: [
    /* @__PURE__ */ jsxDEV(TrailIcon, {}, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 325,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: strings.calendar_trail }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 326,
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
            lineNumber: 333,
            columnNumber: 17
          }),
          (answer ? answer.options : []).map((option) => /* @__PURE__ */ jsxDEV("option", { value: String(option.id), children: option.name }, option.id, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 335,
            columnNumber: 21
          }))
        ]
      },
      void 0,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 327,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-down dim-cal__trailcaret", "aria-hidden": "true" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 338,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 324,
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
          lineNumber: 347,
          columnNumber: 13
        }),
        strings.calendar_trailmine
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 340,
      columnNumber: 9
    }
  ),
  children,
  /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__spacer" }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 351,
    columnNumber: 9
  }),
  onZoom && !phone ? /* @__PURE__ */ jsxDEV(
    "span",
    {
      className: "dim-cal__trailzoom",
      role: "group",
      "aria-label": strings.calendar_trailzoom,
      "data-region": "dim-cal-trailzoom",
      children: ZOOMS.map((one) => /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "dim-cal__trailzoombtn",
          "data-action": "dim-cal-trailzoom",
          "data-zoom": one,
          "aria-pressed": one === zoom,
          onClick: () => onZoom(one),
          children: zoomLabel(strings, one)
        },
        one,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 360,
          columnNumber: 21
        }
      ))
    },
    void 0,
    false,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 353,
      columnNumber: 13
    }
  ) : null,
  answer && answer.trails.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailcount", "data-region": "dim-cal-trailcount", children: countOf(strings, answer) }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 375,
    columnNumber: 13
  }) : null
] }, void 0, true, {
  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
  lineNumber: 323,
  columnNumber: 5
}), "TrailFilters");
const TrailView = /* @__PURE__ */ __name(({
  look,
  month,
  answer,
  today,
  now,
  phone,
  captionid,
  chosen = 0,
  zoom = "day",
  firstday = 0,
  icons = NO_ICONS,
  fetch,
  scope = "",
  nav,
  onRange,
  onMonth,
  onDrawn,
  onWidth
}) => {
  const root = useRef(null);
  const width = useRef(onWidth);
  width.current = onWidth;
  const [seen, setSeen] = useState(answer);
  const [win, setWin] = useState(() => freshWin(answer, month, 0, scope));
  const [popped, setPopped] = useState(null);
  const [folded, setFolded] = useState([]);
  const [inview, setInview] = useState("");
  const control = useRef(null);
  const viewed = useRef({ first: 0, last: 0 });
  const ranged = useRef("");
  const busy = useRef(false);
  const generation = useRef(win.generation);
  const wanted = useRef(null);
  const finding = useRef(null);
  const asked = useRef(0);
  const failed = useRef("");
  const scoped = useRef(scope);
  scoped.current = scope;
  if (seen !== answer) {
    setSeen(answer);
    setWin((was) => freshWin(answer, month, was.generation + 1, scope));
  }
  generation.current = win.generation;
  const merged = useMemo(() => mergedOf(win.loaded, look.zone), [win.loaded, look.zone]);
  const days = useMemo(() => windowDays(win.loaded.months), [win.loaded]);
  const columns = days.length + (win.loading ? SKELETON_DAYS : 0);
  const timeline = !phone && merged.trails.length > 0;
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
  useEffect(() => {
    if (!timeline) {
      ranged.current = "";
      onRange?.(null);
    }
  }, [timeline]);
  useLayoutEffect(() => {
    root.current?.querySelectorAll("[data-lit]").forEach((element) => element.removeAttribute("data-lit"));
  }, [win.loaded, folded]);
  useLayoutEffect(() => onDrawn?.(merged), [merged]);
  useEffect(() => () => finding.current?.resolve(), []);
  const close = useCallback((refocus) => {
    setPopped((was) => {
      if (was && refocus) {
        was.opener.focus();
      }
      return null;
    });
  }, []);
  const found = /* @__PURE__ */ __name(() => {
    finding.current?.resolve();
    finding.current = null;
  }, "found");
  const extend = /* @__PURE__ */ __name((target) => {
    if (!fetch || busy.current || scoped.current !== win.scope) {
      return;
    }
    busy.current = true;
    const set = generation.current;
    const askedwith = win.scope;
    const ahead = target > win.loaded.months[win.loaded.months.length - 1];
    setWin((was) => ({ ...was, loading: ahead }));
    const pending = new Pending("theme_dimensions/calendar:trailmonth");
    fetch(target, 1).then((got) => {
      setWin((was) => {
        if (was.generation !== set || scoped.current !== askedwith) {
          return was;
        }
        const grown = extendLoaded(was.loaded, got);
        if (!grown) {
          return { ...was, loading: false };
        }
        const shift = grown.shift === 0 ? was.shift : { key: was.shift.key + 1, days: grown.shift };
        return { ...was, loaded: grown.loaded, shift, loading: false };
      });
      return got;
    }).catch(() => {
      failed.current = target;
    }).finally(() => {
      busy.current = false;
      pending.resolve();
      setWin((was) => ({ ...was, loading: false }));
    });
  }, "extend");
  const jump = /* @__PURE__ */ __name((target) => {
    if (!fetch || busy.current || scoped.current !== win.scope) {
      return;
    }
    busy.current = true;
    const set = generation.current;
    const askedwith = win.scope;
    const pending = new Pending("theme_dimensions/calendar:trailmonth");
    fetch(target, ANSWER_MONTHS).then((got) => {
      setWin((was) => was.generation === set && scoped.current === askedwith ? freshWin(got, target, was.generation + 1, askedwith) : was);
      return got;
    }).catch(() => {
      failed.current = target;
    }).finally(() => {
      busy.current = false;
      pending.resolve();
      setWin((was) => ({ ...was, loading: false }));
    });
  }, "jump");
  const reach = /* @__PURE__ */ __name((target) => {
    const months = win.loaded.months;
    if (months.includes(target)) {
      return;
    }
    if (target === monthAfter(months[months.length - 1], 1) || target === monthAfter(months[0], -1)) {
      extend(target);
    } else {
      jump(target);
    }
  }, "reach");
  const openOn = /* @__PURE__ */ __name((trailid, cmid) => {
    const bar = root.current?.querySelector(
      `[data-trail="${trailid}"] [data-region="dim-cal-trailstep"][data-cmid="${cmid}"]`
    );
    if (bar) {
      setPopped({ trail: trailid, cmid, opener: bar });
    }
    found();
  }, "openOn");
  const arrived = /* @__PURE__ */ __name((trailid, cmid, reached) => {
    if (reached) {
      openOn(trailid, cmid);
    } else {
      found();
    }
  }, "arrived");
  const pursue = /* @__PURE__ */ __name(() => {
    const want = wanted.current;
    const scroller = control.current;
    if (!want || !scroller) {
      return;
    }
    if (want.tries > TRIES) {
      wanted.current = null;
      found();
      return;
    }
    if (want.kind === "month") {
      const at = days.indexOf(`${want.month}-01`);
      if (at < 0) {
        want.tries++;
        reach(want.month);
        return;
      }
      wanted.current = null;
      scroller.go({ kind: "day", index: at });
      return;
    }
    if (want.kind === "today") {
      const at = days.indexOf(today);
      if (at < 0) {
        want.tries++;
        reach(today.slice(0, 7));
        return;
      }
      wanted.current = null;
      scroller.go({ kind: "today", index: at, month: days.indexOf(`${today.slice(0, 7)}-01`) });
      return;
    }
    const step = merged.trails.find((trail) => trail.id === want.trail)?.steps.find((one) => one.cmid === want.cmid);
    if (!step) {
      wanted.current = null;
      found();
      return;
    }
    const bar = barOf(step, days, look.zone);
    if (bar) {
      wanted.current = null;
      const ticket = asked.current;
      scroller.go({ kind: "step", index: bar.from }).then((reached) => {
        if (ticket === asked.current) {
          arrived(want.trail, want.cmid, reached);
        }
        return reached;
      }).catch(found);
      return;
    }
    want.tries++;
    reach(look.zone.day(step.start > 0 ? step.start : step.end).slice(0, 7));
  }, "pursue");
  useEffect(() => pursue(), [win]);
  const aim = /* @__PURE__ */ __name((next) => {
    asked.current++;
    failed.current = "";
    found();
    wanted.current = next;
    pursue();
  }, "aim");
  const find = /* @__PURE__ */ __name((trail, step, row) => {
    asked.current++;
    failed.current = "";
    found();
    if (!dated(step)) {
      wanted.current = null;
      setPopped({ trail: trail.id, cmid: step.cmid, opener: row });
      return;
    }
    finding.current = new Pending("theme_dimensions/calendar:trailfind");
    wanted.current = { kind: "step", trail: trail.id, cmid: step.cmid, tries: 0 };
    pursue();
  }, "find");
  if (nav) {
    nav.current = {
      /**
       * A month back or ahead of the first day in view.
       *
       * @param {number} direction -1 back, 1 ahead.
       * @returns {boolean} Whether the timeline took it.
       */
      step: /* @__PURE__ */ __name((direction) => {
        if (!timeline || days.length === 0) {
          return false;
        }
        const first = days[viewed.current.first] ?? days[0];
        aim({ kind: "month", month: monthStep(first, direction), tries: 0 });
        return true;
      }, "step"),
      /**
       * Today, from its month's 1st.
       *
       * @returns {boolean} Whether the timeline took it.
       */
      today: /* @__PURE__ */ __name(() => {
        if (!timeline) {
          return false;
        }
        aim({ kind: "today", tries: 0 });
        return true;
      }, "today")
    };
  }
  const viewing = /* @__PURE__ */ __name((first, last, near) => {
    if (days.length === 0) {
      return;
    }
    const from = days[Math.min(first, days.length - 1)];
    const to = days[Math.min(last, days.length - 1)];
    viewed.current = { first: Math.min(first, days.length - 1), last: Math.min(last, days.length - 1) };
    const range = `${from.slice(0, 7)}:${to.slice(0, 7)}`;
    if (range !== ranged.current) {
      ranged.current = range;
      onRange?.({ first: from, last: to });
    }
    setInview((was) => was === from.slice(0, 7) ? was : from.slice(0, 7));
    const months = win.loaded.months;
    let target = "";
    if (near === "end") {
      target = monthAfter(months[months.length - 1], 1);
    } else if (near === "start") {
      target = monthAfter(months[0], -1);
    }
    const full = near === "end" && months.length >= LOADED_CAP && first === 0;
    if (target !== "" && target !== failed.current && !full) {
      extend(target);
    }
  }, "viewing");
  const settled = /* @__PURE__ */ __name((first) => {
    const day = days[Math.min(first, days.length - 1)];
    if (day) {
      onMonth?.(day.slice(0, 7));
    }
  }, "settled");
  const lit = /* @__PURE__ */ __name((cmid, on) => (event) => {
    if (event.pointerType !== "mouse") {
      return;
    }
    root.current?.querySelectorAll(`[data-pair="${cmid}"]`).forEach((element) => {
      element.toggleAttribute("data-lit", on);
    });
  }, "lit");
  const fold = /* @__PURE__ */ __name((id) => {
    setFolded((was) => was.includes(id) ? was.filter((one) => one !== id) : [...was, id]);
  }, "fold");
  const todayat = days.indexOf(today);
  const openat = today.startsWith(win.loaded.months[0]) ? todayat : -1;
  const grid = { "--dim-cal-trail-days": String(columns) };
  const poppedtrail = popped ? merged.trails.find((trail) => trail.id === popped.trail) : void 0;
  const poppedstep = poppedtrail?.steps.find((step) => step.cmid === popped?.cmid);
  const learner = merged.trails.some((trail) => trail.enrolled);
  const starts = win.loaded.months.map((one) => ({ month: one, at: days.indexOf(`${one}-01`), length: monthDays(one).length }));
  const bydays = zoom === "day";
  const cells = bydays ? [] : weekCells(days, firstday);
  const shortDay = /* @__PURE__ */ __name((timestamp) => shortOf(look.zone.day(timestamp), look.language), "shortDay");
  const stepDay = /* @__PURE__ */ __name((step) => {
    const at = step.end > 0 ? step.end : step.start;
    return at > 0 ? shortDay(at) : "";
  }, "stepDay");
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
      "data-pair": step.cmid,
      "data-state": step.state || void 0,
      onClick: (event) => setPopped({ trail: trail.id, cmid: step.cmid, opener: event.currentTarget }),
      onPointerEnter: lit(step.cmid, true),
      onPointerLeave: lit(step.cmid, false),
      children: content
    },
    void 0,
    false,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 865,
      columnNumber: 9
    }
  ), "opener");
  const stepRow = /* @__PURE__ */ __name((trail, step) => {
    const bar = barOf(step, days, look.zone);
    const tone = step.state ? `--${step.state}` : "";
    let drawn = null;
    let diamond = null;
    if (bar) {
      const continued = bar.after && !bar.open;
      const classes = "dim-cal__trailbar" + (tone ? ` dim-cal__trailbar${tone}` : "") + (bar.before ? " dim-cal__trailbar--before" : "") + (continued ? " dim-cal__trailbar--after" : "") + (bar.open ? " dim-cal__trailbar--open" : "") + (bar.milestone ? " dim-cal__trailbar--milestone" : "") + (step.next ? " dim-cal__trailbar--next" : "");
      drawn = opener(
        trail,
        step,
        classes,
        { gridColumn: `${bar.from + 2} / ${bar.to + 3}`, gridRow: "1" },
        namedBar(bar, zoom) ? step.name : null
      );
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
            lineNumber: 906,
            columnNumber: 21
          }
        );
      }
    }
    const dates = datesLine(step, look);
    return /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailrow dim-cal__trailrow--step", "data-cmid": step.cmid, children: [
      /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traillabel dim-cal__trailsteplabel", children: /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "dim-cal__trailfind",
          "data-region": "dim-cal-trailrow",
          "data-cmid": step.cmid,
          "data-pair": step.cmid,
          "aria-label": dated(step) ? fill(look.strings.calendar_trailshow, `${step.name}, ${dates}`) : void 0,
          "aria-haspopup": "dialog",
          "aria-expanded": popped?.cmid === step.cmid,
          onClick: (event) => find(trail, step, event.currentTarget),
          onPointerEnter: lit(step.cmid, true),
          onPointerLeave: lit(step.cmid, false),
          children: [
            /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailnum" + (tone ? ` dim-cal__trailnum${tone}` : ""), "aria-hidden": "true", children: step.position }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 931,
              columnNumber: 25
            }),
            /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailnames", children: [
              /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailname", children: step.name }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 935,
                columnNumber: 29
              }),
              /* @__PURE__ */ jsxDEV(
                "small",
                {
                  className: `dim-cal__traildates${dated(step) ? "" : " dim-cal__traildates--none"}`,
                  "data-region": "dim-cal-traildates",
                  children: dates
                },
                void 0,
                false,
                {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 936,
                  columnNumber: 29
                }
              )
            ] }, void 0, true, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 934,
              columnNumber: 25
            }),
            dated(step) ? /* @__PURE__ */ jsxDEV("i", { className: "fa fa-crosshairs dim-cal__traillocate", "aria-hidden": "true" }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 942,
              columnNumber: 29
            }) : null
          ]
        },
        void 0,
        true,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 918,
          columnNumber: 21
        }
      ) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 917,
        columnNumber: 17
      }),
      drawn,
      diamond
    ] }, step.cmid, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 916,
      columnNumber: 13
    });
  }, "stepRow");
  const trailRows = /* @__PURE__ */ __name((trail) => {
    const open = !folded.includes(trail.id);
    const span = trailBarOf(trail, days, look.zone);
    const steps = `dim-cal-trailsteps-${trail.id}`;
    const links = linksOf(trail.steps, days, look.zone, open, zoom);
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
                  className: `btn btn-icon icons-collapse-expand dim-cal__trailfold${open ? "" : " collapsed"}`,
                  "data-action": "dim-cal-trailfold",
                  "aria-expanded": open,
                  "aria-controls": steps,
                  "aria-label": fill(look.strings.calendar_trailtoggle, trail.name),
                  onClick: () => fold(trail.id),
                  children: [
                    /* @__PURE__ */ jsxDEV(
                      "span",
                      {
                        className: "expanded-icon icon-no-margin",
                        dangerouslySetInnerHTML: { __html: icons.expanded }
                      },
                      void 0,
                      false,
                      {
                        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                        lineNumber: 980,
                        columnNumber: 29
                      }
                    ),
                    /* @__PURE__ */ jsxDEV("span", { className: "collapsed-icon icon-no-margin", children: [
                      /* @__PURE__ */ jsxDEV("span", { className: "dir-rtl-hide", dangerouslySetInnerHTML: { __html: icons.collapsed } }, void 0, false, {
                        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                        lineNumber: 985,
                        columnNumber: 33
                      }),
                      /* @__PURE__ */ jsxDEV("span", { className: "dir-ltr-hide", dangerouslySetInnerHTML: { __html: icons.collapsedrtl } }, void 0, false, {
                        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                        lineNumber: 986,
                        columnNumber: 33
                      })
                    ] }, void 0, true, {
                      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                      lineNumber: 984,
                      columnNumber: 29
                    })
                  ]
                },
                void 0,
                true,
                {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 971,
                  columnNumber: 25
                }
              ),
              /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailnames", children: [
                /* @__PURE__ */ jsxDEV("a", { className: "dim-cal__trailname dim-cal__trailname--trail", href: safeHref(trail.url, look.home), children: trail.name }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 990,
                  columnNumber: 29
                }),
                /* @__PURE__ */ jsxDEV("small", { className: "dim-cal__trailmeta", "data-region": "dim-cal-trailmeta", children: metaOf(trail, look) }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 993,
                  columnNumber: 29
                })
              ] }, void 0, true, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 989,
                columnNumber: 25
              })
            ] }, void 0, true, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 970,
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
                lineNumber: 997,
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
                lineNumber: 1005,
                columnNumber: 25
              }
            ) : null
          ]
        },
        void 0,
        true,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 966,
          columnNumber: 17
        }
      ),
      /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailsteprows", id: steps, hidden: !open, children: [
        trail.steps.map((step) => stepRow(trail, step)),
        links.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traillinks", "data-region": "dim-cal-traillinks", "aria-hidden": "true", children: links.map((piece, index) => /* @__PURE__ */ jsxDEV(
          "span",
          {
            className: `dim-cal__traillink dim-cal__traillink--${piece.kind}`,
            style: {
              "--dim-cal-link-start": `${piece.left}rem`,
              top: `${piece.top}rem`,
              width: `${piece.width}rem`,
              height: `${piece.height}rem`
            }
          },
          `${index}-${piece.kind}`,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 1017,
            columnNumber: 33
          }
        )) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 1015,
          columnNumber: 25
        }) : null
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 1012,
        columnNumber: 17
      })
    ] }, trail.id, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 965,
      columnNumber: 13
    });
  }, "trailRows");
  const band = /* @__PURE__ */ __name(() => /* @__PURE__ */ jsxDEV(
    "div",
    {
      className: "dim-cal__trailrow dim-cal__trailrow--band",
      role: "group",
      "aria-label": look.strings.calendar_trailmonths,
      "data-region": "dim-cal-trailmonths",
      children: [
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traillabel", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 1048,
          columnNumber: 13
        }),
        starts.map((entry) => /* @__PURE__ */ jsxDEV(
          "span",
          {
            className: `dim-cal__trailmonth${entry.month === inview ? " dim-cal__trailmonth--inview" : ""}`,
            style: { gridColumn: `${entry.at + 2} / ${entry.at + entry.length + 2}`, gridRow: "1" },
            children: /* @__PURE__ */ jsxDEV(
              "button",
              {
                type: "button",
                className: "dim-cal__trailmonthname",
                "data-action": "dim-cal-trailmonth",
                "data-month": entry.month,
                "aria-current": entry.month === inview ? "true" : void 0,
                onClick: () => aim({ kind: "month", month: entry.month, tries: 0 }),
                children: say(`${entry.month}-01`, zoom === "month" ? MONTH_ONLY : MONTH_YEAR, look.language)
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 1055,
                columnNumber: 21
              }
            )
          },
          entry.month,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 1050,
            columnNumber: 17
          }
        )),
        win.loading ? /* @__PURE__ */ jsxDEV(
          "span",
          {
            className: "dim-cal__trailmonth dim-cal__trailmonth--loading",
            style: { gridColumn: `${days.length + 2} / ${columns + 2}`, gridRow: "1" },
            "data-region": "dim-cal-trailloading",
            "aria-hidden": "true",
            children: /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailskeleton" }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 1074,
              columnNumber: 21
            })
          },
          void 0,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 1068,
            columnNumber: 17
          }
        ) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 1042,
      columnNumber: 9
    }
  ), "band");
  const desk = /* @__PURE__ */ __name(() => /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV(
      TrailScroll,
      {
        days: columns,
        todayat,
        openat,
        opening: String(win.generation),
        shift: win.shift,
        zoom,
        control,
        onView: viewing,
        onSettle: settled,
        strings: look.strings,
        children: /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailgrid", "data-region": "dim-cal-trailgrid", style: grid, children: [
          band(),
          /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailrow dim-cal__trailrow--head", "aria-hidden": "true", children: [
            /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traillabel dim-cal__trailheading", children: look.strings.calendar_trailheading }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 1102,
              columnNumber: 25
            }),
            bydays ? days.map((iso, index) => /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: `dim-cal__trailday${isWeekend(iso) ? " dim-cal__trailday--weekend" : ""}${index === todayat ? " dim-cal__trailday--today" : ""}${iso.endsWith("-01") && index > 0 ? " dim-cal__trailday--first" : ""}`,
                "data-day": iso,
                children: [
                  /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailweekday", children: say(iso, WEEKDAY, look.language) }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                    lineNumber: 1111,
                    columnNumber: 33
                  }),
                  /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traildaynum", children: Number(iso.slice(8, 10)) }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                    lineNumber: 1112,
                    columnNumber: 33
                  })
                ]
              },
              iso,
              true,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 1104,
                columnNumber: 29
              }
            )) : cells.map((cell) => /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: "dim-cal__trailweek" + (zoom === "week" && today >= cell.first && today <= cell.last ? " dim-cal__trailweek--today" : "") + (cell.first.endsWith("-01") && cell.at > 0 ? " dim-cal__trailweek--first" : ""),
                style: { gridColumn: `${cell.at + 2} / ${cell.at + cell.length + 2}`, gridRow: "1" },
                "data-region": "dim-cal-trailweek",
                "data-week": cell.first,
                children: zoom === "week" ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
                  /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailweekmonth", children: weekLabel(cell, look.language).kicker }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                    lineNumber: 1127,
                    columnNumber: 41
                  }),
                  /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailweekdays", children: weekLabel(cell, look.language).days }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                    lineNumber: 1128,
                    columnNumber: 41
                  })
                ] }, void 0, true, {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 1126,
                  columnNumber: 37
                }) : null
              },
              cell.first,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 1115,
                columnNumber: 29
              }
            ))
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 1101,
            columnNumber: 21
          }),
          /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailground", "aria-hidden": "true", children: [
            bydays ? days.map((iso, index) => isWeekend(iso) ? /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: "dim-cal__trailweekend",
                style: { "--dim-cal-trail-at": String(index) }
              },
              iso,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 1136,
                columnNumber: 29
              }
            ) : null) : null,
            cells.filter((cell) => cell.at > 0 && !cell.first.endsWith("-01")).map((cell) => /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: "dim-cal__trailweekline",
                style: { "--dim-cal-trail-at": String(cell.at) }
              },
              cell.first,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 1143,
                columnNumber: 29
              }
            )),
            starts.filter((entry) => entry.at > 0).map((entry) => /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: "dim-cal__trailmonthedge",
                style: { "--dim-cal-trail-at": String(entry.at) }
              },
              entry.month,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 1150,
                columnNumber: 29
              }
            )),
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
                lineNumber: 1157,
                columnNumber: 29
              }
            ) : null,
            todayat >= 0 ? /* @__PURE__ */ jsxDEV(
              "span",
              {
                className: "dim-cal__trailnowtag",
                "data-region": "dim-cal-trailnowtag",
                style: { "--dim-cal-trail-at": String(todayat) },
                children: look.strings.calendar_today
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 1163,
                columnNumber: 29
              }
            ) : null
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 1134,
            columnNumber: 21
          }),
          merged.trails.map((trail) => trailRows(trail))
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 1099,
          columnNumber: 17
        })
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 1087,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV("ul", { className: "dim-cal__traillegend", "data-region": "dim-cal-traillegend", children: [
      learner ? LEGEND.map((state) => /* @__PURE__ */ jsxDEV("li", { children: [
        /* @__PURE__ */ jsxDEV("span", { className: `dim-cal__trailswatch dim-cal__trailswatch--${state}`, "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 1176,
          columnNumber: 25
        }),
        stateLabel(look.strings, state)
      ] }, state, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 1175,
        columnNumber: 21
      })) : null,
      /* @__PURE__ */ jsxDEV("li", { children: [
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailswatch", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 1181,
          columnNumber: 21
        }),
        look.strings.calendar_trailnotenrolled
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 1180,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("li", { children: [
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traildiamond dim-cal__traildiamond--legend", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 1185,
          columnNumber: 21
        }),
        look.strings.calendar_trailexpected
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 1184,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("li", { children: [
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__traillegendnow", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 1189,
          columnNumber: 21
        }),
        look.strings.calendar_today
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 1188,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 1173,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 1086,
    columnNumber: 9
  }), "desk");
  const todayLine = /* @__PURE__ */ __name(() => /* @__PURE__ */ jsxDEV("li", { className: "dim-cal__trailtoday", "data-region": "dim-cal-trailtoday", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailtodayword", children: look.strings.calendar_today }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 1203,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailtodayline", "aria-hidden": "true" }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 1204,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailtodaydate", children: shortOf(today, look.language) }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 1205,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 1202,
    columnNumber: 9
  }), "todayLine");
  const agenda = /* @__PURE__ */ __name(() => /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__trailagenda", "data-region": "dim-cal-trailagenda", children: merged.trails.map((trail) => {
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
              lineNumber: 1227,
              columnNumber: 33
            }) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 1226,
              columnNumber: 29
            }),
            /* @__PURE__ */ jsxDEV("small", { className: "dim-cal__trailprogress", children: trail.enrolled ? progressOf(trail, look) : coursesOf(look.strings, trail.steps.length) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 1229,
              columnNumber: 29
            })
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 1225,
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
              lineNumber: 1236,
              columnNumber: 37
            }
          )) }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 1234,
            columnNumber: 29
          }) : null,
          /* @__PURE__ */ jsxDEV("ol", { className: "dim-cal__trailsteps", children: [
            trail.steps.map((step, index) => /* @__PURE__ */ jsxDEV(Fragment2, { children: [
              index === at ? todayLine() : null,
              /* @__PURE__ */ jsxDEV("li", { className: `dim-cal__trailstep${step.state ? ` dim-cal__trailstep--${step.state}` : ""}`, children: opener(trail, step, "dim-cal__trailstepname", void 0, /* @__PURE__ */ jsxDEV(Fragment, { children: [
                /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailstepdate", children: stepDay(step) }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 1251,
                  columnNumber: 49
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailstepbar", "aria-hidden": "true" }, void 0, false, {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 1252,
                  columnNumber: 49
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailsteptext", children: [
                  /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailstepline", children: `${step.position}. ${step.name}` }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                    lineNumber: 1254,
                    columnNumber: 53
                  }),
                  /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__trailwhen", children: stepWhen(step, look) }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                    lineNumber: 1257,
                    columnNumber: 53
                  }),
                  step.state ? /* @__PURE__ */ jsxDEV("span", { className: `dim-cal__trailstate dim-cal__trailstate--${step.state}`, children: stateLine(step, look, now) }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                    lineNumber: 1259,
                    columnNumber: 57
                  }) : null
                ] }, void 0, true, {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                  lineNumber: 1253,
                  columnNumber: 49
                })
              ] }, void 0, true, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 1250,
                columnNumber: 45
              })) }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
                lineNumber: 1248,
                columnNumber: 37
              })
            ] }, step.cmid, true, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
              lineNumber: 1246,
              columnNumber: 33
            })),
            at === trail.steps.length ? todayLine() : null
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
            lineNumber: 1244,
            columnNumber: 25
          })
        ]
      },
      trail.id,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
        lineNumber: 1219,
        columnNumber: 21
      }
    );
  }) }, void 0, false, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
    lineNumber: 1215,
    columnNumber: 9
  }), "agenda");
  return /* @__PURE__ */ jsxDEV(
    "section",
    {
      ref: root,
      className: "dim-cal__trails" + (phone ? " dim-cal__trails--phone" : "") + (!phone && zoom !== "day" ? ` dim-cal__trails--${zoom}` : ""),
      role: "region",
      "aria-labelledby": captionid,
      "data-region": "dim-cal-trails",
      children: [
        merged.truncated ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__status", children: look.strings.calendar_trailstruncated }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 1286,
          columnNumber: 33
        }) : null,
        merged.trails.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__empty", "data-region": "dim-cal-trailsempty", children: look.strings.calendar_trailsempty }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
          lineNumber: 1288,
          columnNumber: 17
        }) : null,
        timeline ? desk() : null,
        merged.trails.length > 0 && phone ? agenda() : null,
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
            lineNumber: 1293,
            columnNumber: 17
          }
        ) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/TrailView.tsx",
      lineNumber: 1278,
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
