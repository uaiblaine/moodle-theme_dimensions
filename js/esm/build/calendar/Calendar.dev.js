var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The calendar page's client: the toolbar, the key chips, the category picker, "More filters", the month grid, the
 * day drawer (the agenda sheet on a phone) and the event popover.
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
 * - A day opens the drawer, or on a phone the sheet (DayDrawer.tsx); the open day is `day=` in the address, written
 *   with replaceState (Back leaves the month rather than walking the days) and taken out when it closes. Moving the
 *   selected day while it is open moves it; a month change closes it and the popover.
 * - An item opens the popover (EventPopover.tsx) on a press, Enter or Space, through one delegated listener on the
 *   elements marked data-cal-pop; a modified press and a middle click follow the item's href.
 * - The phone layout is the frame's width, measured by the grid's one ResizeObserver (MonthGrid.tsx PHONE).
 *
 * @module     theme_dimensions/calendar/Calendar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";
import { fetchOne } from "@moodle/lms/core/ajax";
import Pending from "@moodle/lms/core/pending";
import { load } from "../amd";
import { parse, say, shiftMonth } from "../daterange/calendar";
import CategoryPicker from "./CategoryPicker";
import DayDrawer from "./DayDrawer";
import { gridWeeks, zone as zoneOf } from "./days";
import EventPopover from "./EventPopover";
import { writeAddress } from "./history";
import KeyChips from "./KeyChips";
import { dayDots, place } from "./layout";
import {
  KEYS,
  RANGE_METHOD,
  REMEMBER_METHOD,
  DAY_PARAM,
  address,
  emptyAnswer,
  keysOn,
  monthHref,
  normalise,
  rangeArgs,
  readAddress,
  sameState,
  withDay
} from "./model";
import MonthGrid, { PHONE } from "./MonthGrid";
import { itemKey, overlapsOf } from "./overlap";
import MoreFilters from "./MoreFilters";
import { load as loadStrings } from "./strings";
const QUIET = 800;
const CAPTION = "dim-cal-caption";
const report = /* @__PURE__ */ __name((failure) => load("core/notification").then((notification) => notification.exception(failure)), "report");
const openingDay = /* @__PURE__ */ __name((month, today) => today.startsWith(month) ? today : `${month}-01`, "openingDay");
const openableDay = /* @__PURE__ */ __name((day, month, firstday) => day && gridWeeks(month, firstday).flat().includes(day) ? day : null, "openableDay");
const Calendar = /* @__PURE__ */ __name((props) => {
  const language = document.documentElement.lang || void 0;
  const firstday = Number(props.firstday) || 0;
  const [strings, setStrings] = useState(null);
  const [shown, setShown] = useState({ month: props.month, answer: props.answer });
  const [state, setState] = useState(() => normalise(props.state));
  const [openday, setOpenday] = useState(() => openableDay(props.day, props.month, firstday));
  const [selected, setSelected] = useState(() => openday || openingDay(props.month, props.today));
  const [focusing, setFocusing] = useState(0);
  const [dayfocus, setDayfocus] = useState(0);
  const [popped, setPopped] = useState(null);
  const [phone, setPhone] = useState(false);
  const root = useRef(null);
  const ticket = useRef(0);
  const painting = useRef(null);
  const mounting = useRef(null);
  const remembered = useRef(normalise(props.state));
  const quiet = useRef(null);
  const holding = useRef(null);
  const latest = useRef(normalise(props.state));
  const opened = useRef(openday);
  opened.current = openday;
  const showing = useRef(shown.month);
  showing.current = shown.month;
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
      const day = openableDay(new URL(window.location.href).searchParams.get(DAY_PARAM), month, firstday);
      request(month, read.state || normalise(props.state), "none", day || openingDay(month, props.today), false);
      setOpenday(day);
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
    const moved = month !== showing.current;
    if (moved) {
      setOpenday(null);
      setPopped(null);
    }
    if (write !== "none") {
      writeAddress(address(props.urls.page, month, next, moved ? "" : opened.current || ""), write === "push");
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
  const openDay = /* @__PURE__ */ __name((iso) => {
    setSelected(iso);
    setOpenday(iso);
    setDayfocus((tick) => tick + 1);
    writeAddress(withDay(window.location.href, iso), false);
  }, "openDay");
  const closeDay = /* @__PURE__ */ __name(() => {
    setOpenday(null);
    setFocusing((tick) => tick + 1);
    writeAddress(withDay(window.location.href, ""), false);
  }, "closeDay");
  const selectDay = /* @__PURE__ */ __name((iso) => {
    setSelected(iso);
    setFocusing((tick) => tick + 1);
    if (opened.current !== null) {
      setOpenday(iso);
      writeAddress(withDay(window.location.href, iso), false);
    }
  }, "selectDay");
  const closePopover = useCallback((refocus) => {
    setPopped((was) => {
      if (was && refocus) {
        was.opener.focus();
      }
      return null;
    });
  }, []);
  const popTarget = /* @__PURE__ */ __name((target) => {
    const element = target instanceof Element ? target.closest("[data-cal-pop]") : null;
    return element && root.current?.contains(element) ? element : null;
  }, "popTarget");
  const popFrom = /* @__PURE__ */ __name((element) => {
    setPopped({ key: `${element.dataset.source || ""}-${element.dataset.id || ""}`, opener: element });
  }, "popFrom");
  const onPress = /* @__PURE__ */ __name((event) => {
    const element = popTarget(event.target);
    if (!element || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    event.preventDefault();
    popFrom(element);
  }, "onPress");
  const onItemKey = /* @__PURE__ */ __name((event) => {
    const element = popTarget(event.target);
    if (!element || event.defaultPrevented || event.key !== "Enter" && event.key !== " ") {
      return;
    }
    event.preventDefault();
    popFrom(element);
  }, "onItemKey");
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
  const dots = phone ? new Map(shown.answer.days.map((entry) => [entry.date, dayDots(entry.counts)])) : null;
  const poppedentry = popped ? placed.find((entry) => itemKey(entry.item) === popped.key) || null : null;
  const drawn = openday !== null && weeks.flat().includes(openday) ? openday : null;
  const step = /* @__PURE__ */ __name((offset) => toDay(shiftMonth(parse(selected) ? selected : `${shown.month}-01`, offset), false), "step");
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      ref: root,
      className: `dim-cal__app${phone ? " dim-cal__app--phone" : ""}`,
      "data-region": "dim-cal-app",
      onClick: onPress,
      onKeyDown: onItemKey,
      children: [
        /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__toolbar", children: [
          /* @__PURE__ */ jsxDEV("h1", { className: "dim-cal__title", children: strings.calendar_title }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 468,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-secondary dim-cal__today", onClick: () => toDay(props.today, false), children: strings.calendar_today }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 469,
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
                lineNumber: 479,
                columnNumber: 21
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
              lineNumber: 472,
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
                lineNumber: 488,
                columnNumber: 21
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
              lineNumber: 481,
              columnNumber: 17
            }
          ),
          /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__caption", id: CAPTION, "aria-live": "polite", "data-region": "dim-cal-caption", children: caption }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 490,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__spacer" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 491,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__views", role: "group", "aria-label": strings.calendar_view, children: /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__view dim-cal__view--active", "aria-current": "true", children: strings.calendar_viewmonth }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 493,
            columnNumber: 21
          }) }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 492,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("a", { className: "dim-cal__classic", href: monthHref(props.urls.calendar, shown.month), children: strings.calendar_classic }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 495,
            columnNumber: 17
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
          lineNumber: 467,
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
              lineNumber: 498,
              columnNumber: 17
            }
          ),
          /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__spacer" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 505,
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
              lineNumber: 506,
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
              lineNumber: 513,
              columnNumber: 17
            }
          )
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
          lineNumber: 497,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV(
          "div",
          {
            className: `dim-cal__body${drawn !== null && !phone ? " dim-cal__body--drawer" : ""}`,
            "data-region": "dim-cal-body",
            children: [
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
                  dots,
                  onSelect: selectDay,
                  onMonth: (iso) => toDay(iso, true),
                  onOpen: openDay,
                  onWidth: (width) => setPhone(width < PHONE)
                },
                void 0,
                false,
                {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
                  lineNumber: 524,
                  columnNumber: 17
                }
              ),
              drawn !== null ? /* @__PURE__ */ jsxDEV(
                DayDrawer,
                {
                  look,
                  iso: drawn,
                  placed,
                  variant: phone ? "sheet" : "drawer",
                  calendar: props.urls.calendar,
                  focusing: dayfocus,
                  onClose: closeDay
                },
                void 0,
                false,
                {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
                  lineNumber: 540,
                  columnNumber: 21
                }
              ) : null
            ]
          },
          void 0,
          true,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 520,
            columnNumber: 13
          }
        ),
        shown.answer.truncated ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__status", children: strings.calendar_truncated }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
          lineNumber: 551,
          columnNumber: 39
        }) : null,
        popped && poppedentry ? /* @__PURE__ */ jsxDEV(
          EventPopover,
          {
            look,
            entry: poppedentry,
            overlaps: overlapsOf(poppedentry, placed, zone),
            opener: popped.opener,
            sheet: phone,
            modalities: props.options.modality,
            onClose: closePopover
          },
          void 0,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 553,
            columnNumber: 17
          }
        ) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
      lineNumber: 460,
      columnNumber: 9
    }
  );
}, "Calendar");
var Calendar_default = Calendar;
export {
  QUIET,
  Calendar_default as default,
  openableDay,
  openingDay
};
//# sourceMappingURL=Calendar.dev.js.map
