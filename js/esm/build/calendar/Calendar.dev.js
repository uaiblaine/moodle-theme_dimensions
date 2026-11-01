var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The calendar page's client: the toolbar, the key chips, the category picker, "More filters" and the month grid.
 *
 * Mounted by core's react_autoinit on the node calendar_page.mustache draws, never moved (a node that leaves the
 * document is unmounted). The props carry the range's first answer, so the first paint asks for nothing; once the
 * strings are read (from core's cache, where the page put them) the component draws and hides the server's layer
 * in the same frame.
 *
 * - The latest request wins: a ticket is taken per request and checked after the await; a superseded answer is
 *   dropped. A Pending token (theme_dimensions/calendar:range) spans the request to the painted grid.
 * - The address has one writer (calendar/history.ts): a month moved pushes an entry, a change of state replaces it;
 *   Back and Forward read the address again (popstate) and ask for what it names, writing nothing.
 * - A change of keys or filters is remembered after a quiet ({@see QUIET}), never on every press, and an unchanged
 *   state is not sent again; a Pending token (theme_dimensions/calendar:remember) spans the quiet and the request.
 * - Every key off asks the range nothing: the answer is the window's days at zero.
 *
 * @module     theme_dimensions/calendar/Calendar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";
import { fetchOne } from "@moodle/lms/core/ajax";
import Pending from "@moodle/lms/core/pending";
import { redirect } from "@moodle/lms/core/location";
import { load } from "../amd";
import { parse, say, shiftMonth } from "../daterange/calendar";
import CategoryPicker from "./CategoryPicker";
import { gridWeeks, zone as zoneOf } from "./days";
import { writeAddress } from "./history";
import KeyChips from "./KeyChips";
import { place } from "./layout";
import {
  KEYS,
  RANGE_METHOD,
  REMEMBER_METHOD,
  address,
  dayHref,
  emptyAnswer,
  keysOn,
  monthHref,
  normalise,
  rangeArgs,
  readAddress,
  sameState
} from "./model";
import MonthGrid from "./MonthGrid";
import MoreFilters from "./MoreFilters";
import { load as loadStrings } from "./strings";
const QUIET = 800;
const CAPTION = "dim-cal-caption";
const report = /* @__PURE__ */ __name((failure) => load("core/notification").then((notification) => notification.exception(failure)), "report");
const openingDay = /* @__PURE__ */ __name((month, today) => today.startsWith(month) ? today : `${month}-01`, "openingDay");
const Calendar = /* @__PURE__ */ __name((props) => {
  const language = document.documentElement.lang || void 0;
  const firstday = Number(props.firstday) || 0;
  const [strings, setStrings] = useState(null);
  const [shown, setShown] = useState({ month: props.month, answer: props.answer });
  const [state, setState] = useState(() => normalise(props.state));
  const [selected, setSelected] = useState(() => openingDay(props.month, props.today));
  const [focusing, setFocusing] = useState(0);
  const root = useRef(null);
  const ticket = useRef(0);
  const painting = useRef(null);
  const mounting = useRef(null);
  const remembered = useRef(normalise(props.state));
  const quiet = useRef(null);
  const holding = useRef(null);
  const latest = useRef(normalise(props.state));
  const zone = useMemo(() => zoneOf(props.timezone, language), [props.timezone, language]);
  const weeks = useMemo(() => gridWeeks(shown.month, firstday), [shown.month, firstday]);
  const placed = useMemo(() => place(shown.answer.events, zone), [shown.answer, zone]);
  useEffect(() => {
    mounting.current = new Pending("theme_dimensions/calendar:mount");
    loadStrings().then((values) => {
      setStrings(values);
      return values;
    }).catch((failure) => {
      mounting.current?.resolve();
      mounting.current = null;
      report(failure);
    });
    const onPop = /* @__PURE__ */ __name(() => {
      const read = readAddress(window.location.href);
      const month = read.month || props.month;
      request(month, read.state || normalise(props.state), "none", openingDay(month, props.today), false);
    }, "onPop");
    window.addEventListener("popstate", onPop);
    return () => {
      window.removeEventListener("popstate", onPop);
      mounting.current?.resolve();
      painting.current?.resolve();
      holding.current?.resolve();
      if (quiet.current !== null) {
        clearTimeout(quiet.current);
      }
    };
  }, []);
  useLayoutEffect(() => {
    if (!strings) {
      return;
    }
    const page = root.current?.closest('[data-region="dim-cal"]');
    const server = page?.querySelector('[data-region="dim-cal-server"]');
    if (server) {
      server.hidden = true;
    }
    mounting.current?.resolve();
    mounting.current = null;
  }, [strings]);
  useEffect(() => {
    painting.current?.resolve();
    painting.current = null;
  }, [shown]);
  const request = /* @__PURE__ */ __name(async (month, next, write, select, focus) => {
    const seq = ++ticket.current;
    if (write !== "none") {
      writeAddress(address(props.urls.page, month, next), write === "push");
    }
    const pending = new Pending("theme_dimensions/calendar:range");
    const days = gridWeeks(month, firstday).flat();
    try {
      const answer = keysOn(next).length ? await fetchOne({ methodname: RANGE_METHOD, args: rangeArgs(days[0], days[days.length - 1], next) }) : emptyAnswer(days);
      if (seq !== ticket.current) {
        pending.resolve();
        return;
      }
      painting.current?.resolve();
      painting.current = pending;
      setShown({ month, answer });
      setSelected(select);
      if (focus) {
        setFocusing((tick) => tick + 1);
      }
    } catch (failure) {
      pending.resolve();
      if (seq === ticket.current) {
        report(failure);
      }
    }
  }, "request");
  const remember = /* @__PURE__ */ __name((next) => {
    latest.current = next;
    if (quiet.current !== null) {
      clearTimeout(quiet.current);
    }
    if (!holding.current) {
      holding.current = new Pending("theme_dimensions/calendar:remember");
    }
    quiet.current = setTimeout(() => {
      quiet.current = null;
      const hold = holding.current;
      holding.current = null;
      const sent = latest.current;
      if (sameState(sent, remembered.current)) {
        hold?.resolve();
        return;
      }
      fetchOne({ methodname: REMEMBER_METHOD, args: { ...sent } }).then((stored) => {
        remembered.current = sent;
        return stored;
      }).catch(report).finally(() => hold?.resolve());
    }, QUIET);
  }, "remember");
  const change = /* @__PURE__ */ __name((fields) => {
    const next = normalise({ ...state, ...fields });
    if (sameState(next, state)) {
      return;
    }
    setState(next);
    request(shown.month, next, "replace", selected, false);
    remember(next);
  }, "change");
  const toDay = /* @__PURE__ */ __name((iso, focus) => {
    request(iso.slice(0, 7), state, "push", iso, focus);
  }, "toDay");
  const openDay = /* @__PURE__ */ __name((iso) => redirect(dayHref(props.urls.calendar, iso)), "openDay");
  if (!strings) {
    return null;
  }
  const look = {
    strings,
    zone,
    language,
    colour: state.colour,
    courses: new Map(shown.answer.courses.map((course) => [course.id, course])),
    categories: new Map(shown.answer.categories.map((category) => [category.id, category])),
    home: props.urls.home
  };
  const counts = Object.fromEntries(KEYS.map((key) => [key, 0]));
  shown.answer.events.forEach((item) => {
    counts[item.key] = (counts[item.key] || 0) + 1;
  });
  const cities = [.../* @__PURE__ */ new Set([...props.options.city, ...shown.answer.courses.map((course) => course.city)])].filter((city) => city !== "").sort((a, b) => a.localeCompare(b, language));
  const caption = say(`${shown.month}-01`, { month: "long", year: "numeric" }, language);
  const step = /* @__PURE__ */ __name((offset) => toDay(shiftMonth(parse(selected) ? selected : `${shown.month}-01`, offset), false), "step");
  return /* @__PURE__ */ jsxDEV("div", { ref: root, className: "dim-cal__app", "data-region": "dim-cal-app", children: [
    /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__toolbar", children: [
      /* @__PURE__ */ jsxDEV("h1", { className: "dim-cal__title", children: strings.calendar_title }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
        lineNumber: 320,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-secondary dim-cal__today", onClick: () => toDay(props.today, false), children: strings.calendar_today }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
        lineNumber: 321,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "btn btn-secondary dim-cal__step",
          "data-action": "dim-cal-prev",
          "aria-label": strings.calendar_prevmonth,
          onClick: () => step(-1),
          children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-left", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 331,
            columnNumber: 21
          })
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
          lineNumber: 324,
          columnNumber: 17
        }
      ),
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "btn btn-secondary dim-cal__step",
          "data-action": "dim-cal-next",
          "aria-label": strings.calendar_nextmonth,
          onClick: () => step(1),
          children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-right", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 340,
            columnNumber: 21
          })
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
          lineNumber: 333,
          columnNumber: 17
        }
      ),
      /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__caption", id: CAPTION, "aria-live": "polite", "data-region": "dim-cal-caption", children: caption }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
        lineNumber: 342,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__spacer" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
        lineNumber: 343,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__views", role: "group", "aria-label": strings.calendar_view, children: /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__view dim-cal__view--active", "aria-current": "true", children: strings.calendar_viewmonth }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
        lineNumber: 345,
        columnNumber: 21
      }) }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
        lineNumber: 344,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("a", { className: "dim-cal__classic", href: monthHref(props.urls.calendar, shown.month), children: strings.calendar_classic }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
        lineNumber: 347,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
      lineNumber: 319,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__toolbar", children: [
      /* @__PURE__ */ jsxDEV(
        KeyChips,
        {
          strings,
          off: state.off,
          counts,
          onToggle: (key) => change({ off: state.off.includes(key) ? state.off.filter((other) => other !== key) : [...state.off, key] })
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
          lineNumber: 350,
          columnNumber: 17
        }
      ),
      /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__spacer" }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
        lineNumber: 357,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(
        CategoryPicker,
        {
          strings,
          tree: props.tree,
          categoryid: state.categoryid,
          recursive: state.recursive,
          onChange: change
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
          lineNumber: 358,
          columnNumber: 17
        }
      ),
      /* @__PURE__ */ jsxDEV(
        MoreFilters,
        {
          strings,
          state,
          options: { model: props.options.model, modality: props.options.modality, city: cities },
          onChange: change
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
          lineNumber: 365,
          columnNumber: 17
        }
      )
    ] }, void 0, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
      lineNumber: 349,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      MonthGrid,
      {
        look,
        month: shown.month,
        weeks,
        placed,
        today: props.today,
        selected: weeks.flat().includes(selected) ? selected : openingDay(shown.month, props.today),
        focusing,
        captionid: CAPTION,
        onSelect: (iso) => {
          setSelected(iso);
          setFocusing((tick) => tick + 1);
        },
        onMonth: (iso) => toDay(iso, true),
        onOpen: openDay
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
        lineNumber: 372,
        columnNumber: 13
      }
    ),
    shown.answer.truncated ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__status", children: strings.calendar_truncated }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
      lineNumber: 388,
      columnNumber: 39
    }) : null
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
    lineNumber: 318,
    columnNumber: 9
  });
}, "Calendar");
var Calendar_default = Calendar;
export {
  QUIET,
  Calendar_default as default,
  openingDay
};
//# sourceMappingURL=Calendar.dev.js.map
