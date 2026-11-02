var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The calendar page's client: the toolbar, the key chips, the category picker, "More filters", the month grid or the
 * week (WeekGrid.tsx, stage C4), the day drawer (the agenda sheet on a phone) and the event popover.
 *
 * Mounted by core's react_autoinit on the node calendar_page.mustache draws, never moved (a node that leaves the
 * document is unmounted). The props carry the range's first answer, so the first paint asks for nothing; once the
 * strings are read (from core's cache, where the page put them) the component draws and hides the server's layer
 * in the same frame.
 *
 * - The latest request wins: a ticket is taken per request and checked after the await; a superseded answer is
 *   dropped. A Pending token (theme_dimensions/calendar:range) spans the request to the painted grid.
 * - What is shown is a span, a month or a week (calendar/weeks.ts), with its answer: one range call either way. The
 *   view switch keeps the selected day (the week holding it, or its month), and the open day when the new span holds
 *   it. The view and the week are navigation, in the address and never in the remembered state.
 * - The address has one writer (calendar/history.ts): a month or a week moved and a view switched push an entry, a
 *   change of state replaces it; Back and Forward read the address again (popstate) and ask for what it names, writing
 *   nothing.
 * - A change of keys or filters is remembered after a quiet ({@see QUIET}), never on every press, and an unchanged
 *   state is not sent again; a Pending token (theme_dimensions/calendar:remember) spans the quiet and the request.
 * - Every key off asks the range nothing: the answer is the window's days at zero.
 * - A day opens the drawer, or on a phone the sheet (DayDrawer.tsx); the open day is `day=` in the address, written
 *   with replaceState (Back leaves the month rather than walking the days) and taken out when it closes. Moving the
 *   selected day while it is open moves it; a month or week change closes it and the popover.
 * - An item opens the popover (EventPopover.tsx) on a press, Enter or Space, through one delegated listener on the
 *   elements marked data-cal-pop; a modified press and a middle click follow the item's href.
 * - The phone layout is the frame's width, measured by the shown grid's one ResizeObserver (MonthGrid.tsx PHONE).
 *
 * @module     theme_dimensions/calendar/Calendar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";
import { fetchOne } from "@moodle/lms/core/ajax";
import Pending from "@moodle/lms/core/pending";
import { load } from "../amd";
import { parse } from "../daterange/calendar";
import { RANGE_METHOD, REMEMBER_METHOD } from "./account";
import CategoryPicker from "./CategoryPicker";
import DayDrawer from "./DayDrawer";
import { gridWeeks, zone as zoneOf } from "./days";
import { description } from "./description";
import EventPopover from "./EventPopover";
import { writeAddress } from "./history";
import KeyChips from "./KeyChips";
import { dayDots, place } from "./layout";
import {
  KEYS,
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
import ViewSwitch from "./ViewSwitch";
import WeekGrid from "./WeekGrid";
import {
  dayIn,
  inSpan,
  monthOfSpan,
  openingOf,
  readSpan,
  selectedIn,
  spanDays,
  spanKey,
  spanLabels,
  spanOf,
  stepSpan
} from "./weeks";
const QUIET = 800;
const CAPTION = "dim-cal-caption";
const report = /* @__PURE__ */ __name((failure) => load("core/notification").then((notification) => notification.exception(failure)), "report");
const openingDay = /* @__PURE__ */ __name((month, today) => today.startsWith(month) ? today : `${month}-01`, "openingDay");
const openableDay = /* @__PURE__ */ __name((day, days) => day && days.includes(day) ? day : null, "openableDay");
const propsSpan = /* @__PURE__ */ __name((props) => props.view === "week" && parse(props.week) ? { view: "week", week: String(props.week) } : { view: "month", month: props.month }, "propsSpan");
const Calendar = /* @__PURE__ */ __name((props) => {
  const language = document.documentElement.lang || void 0;
  const firstday = Number(props.firstday) || 0;
  const [strings, setStrings] = useState(null);
  const [shown, setShown] = useState(() => ({ span: propsSpan(props), answer: props.answer }));
  const [state, setState] = useState(() => normalise(props.state));
  const [openday, setOpenday] = useState(() => openableDay(props.day, spanDays(propsSpan(props), firstday)));
  const [selected, setSelected] = useState(() => openday || openingOf(propsSpan(props), props.today));
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
  const showing = useRef(shown.span);
  showing.current = shown.span;
  const zone = useMemo(() => zoneOf(props.timezone, language), [props.timezone, language]);
  const month = monthOfSpan(shown.span, selected);
  const weeks = useMemo(() => gridWeeks(month, firstday), [month, firstday]);
  const days = useMemo(() => spanDays(shown.span, firstday), [shown.span, firstday]);
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
      const span = readSpan(window.location.href, firstday, read.month || props.month);
      const day = openableDay(new URL(window.location.href).searchParams.get(DAY_PARAM), spanDays(span, firstday));
      request(span, read.state || normalise(props.state), "none", day || openingOf(span, props.today), false);
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
  const request = /* @__PURE__ */ __name(async (span, next, write, select, focus) => {
    const seq = ++ticket.current;
    const days2 = spanDays(span, firstday);
    const moved = spanKey(span) !== spanKey(showing.current);
    const switched = span.view !== showing.current.view;
    const kept = opened.current !== null && (!moved || switched && days2.includes(opened.current)) ? opened.current : "";
    if (moved) {
      setPopped(null);
      if (kept === "") {
        setOpenday(null);
      }
    }
    if (write !== "none") {
      writeAddress(inSpan(address(props.urls.page, monthOfSpan(span, select), next, kept), span), write === "push");
    }
    const pending = new Pending("theme_dimensions/calendar:range");
    try {
      const answer = keysOn(next).length ? await fetchOne({ methodname: RANGE_METHOD, args: rangeArgs(days2[0], days2[days2.length - 1], next) }) : emptyAnswer(days2);
      if (seq !== ticket.current) {
        pending.resolve();
        return;
      }
      painting.current?.resolve();
      painting.current = pending;
      setShown({ span, answer });
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
    request(shown.span, next, "replace", selected, false);
    remember(next);
  }, "change");
  const toDay = /* @__PURE__ */ __name((iso, focus) => {
    request(spanOf(shown.span.view, iso, firstday), state, "push", iso, focus);
  }, "toDay");
  const toView = /* @__PURE__ */ __name((view) => {
    if (view !== shown.span.view) {
      request(spanOf(view, selected, firstday), state, "push", selected, false);
    }
  }, "toView");
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
  const week = shown.span.view === "week";
  const labels = spanLabels(shown.span, days, strings, language);
  const dots = phone ? new Map(shown.answer.days.map((entry) => [entry.date, dayDots(entry.counts)])) : null;
  const poppedentry = popped ? placed.find((entry) => itemKey(entry.item) === popped.key) || null : null;
  const drawn = dayIn(openday, days);
  const step = /* @__PURE__ */ __name((offset) => toDay(stepSpan(shown.span, parse(selected) ? selected : days[0], offset), false), "step");
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
            lineNumber: 514,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-secondary dim-cal__today", onClick: () => toDay(props.today, false), children: strings.calendar_today }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 515,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "btn btn-secondary dim-cal__step",
              "data-action": "dim-cal-prev",
              "aria-label": labels.previous,
              onClick: () => step(-1),
              children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-left", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
                lineNumber: 525,
                columnNumber: 21
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
              lineNumber: 518,
              columnNumber: 17
            }
          ),
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "btn btn-secondary dim-cal__step",
              "data-action": "dim-cal-next",
              "aria-label": labels.next,
              onClick: () => step(1),
              children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-chevron-right", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
                lineNumber: 534,
                columnNumber: 21
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
              lineNumber: 527,
              columnNumber: 17
            }
          ),
          /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__caption", id: CAPTION, "aria-live": "polite", "data-region": "dim-cal-caption", children: labels.caption }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 536,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__spacer" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 537,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV(ViewSwitch, { strings, view: shown.span.view, onView: toView }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 538,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("a", { className: "dim-cal__classic", href: monthHref(props.urls.calendar, month), children: strings.calendar_classic }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 539,
            columnNumber: 17
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
          lineNumber: 513,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__toolbar", children: [
          /* @__PURE__ */ jsxDEV(
            KeyChips,
            {
              strings,
              keys: KEYS,
              off: state.off,
              counts,
              onToggle: (key) => change({ off: state.off.includes(key) ? state.off.filter((other) => other !== key) : [...state.off, key] })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
              lineNumber: 542,
              columnNumber: 17
            }
          ),
          /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__spacer" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 550,
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
              lineNumber: 551,
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
              lineNumber: 558,
              columnNumber: 17
            }
          )
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
          lineNumber: 541,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV(
          "div",
          {
            className: `dim-cal__body${drawn !== null && !phone ? " dim-cal__body--drawer" : ""}`,
            "data-region": "dim-cal-body",
            children: [
              week ? /* @__PURE__ */ jsxDEV(
                WeekGrid,
                {
                  look,
                  days,
                  placed,
                  today: props.today,
                  now: props.now,
                  selected: selectedIn(selected, shown.span, days, props.today),
                  focusing,
                  captionid: CAPTION,
                  phone,
                  onSelect: selectDay,
                  onWeek: (iso) => toDay(iso, true),
                  onOpen: openDay,
                  onWidth: (width) => setPhone(width < PHONE)
                },
                void 0,
                false,
                {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
                  lineNumber: 570,
                  columnNumber: 21
                }
              ) : /* @__PURE__ */ jsxDEV(
                MonthGrid,
                {
                  look,
                  month,
                  weeks,
                  placed,
                  today: props.today,
                  selected: weeks.flat().includes(selected) ? selected : openingDay(month, props.today),
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
                  lineNumber: 586,
                  columnNumber: 21
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
                  lineNumber: 603,
                  columnNumber: 21
                }
              ) : null
            ]
          },
          void 0,
          true,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 565,
            columnNumber: 13
          }
        ),
        shown.answer.truncated ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__status", children: strings.calendar_truncated }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
          lineNumber: 614,
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
            describe: description,
            plain: "",
            onClose: closePopover
          },
          void 0,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
            lineNumber: 616,
            columnNumber: 17
          }
        ) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/Calendar.tsx",
      lineNumber: 506,
      columnNumber: 9
    }
  );
}, "Calendar");
var Calendar_default = Calendar;
export {
  QUIET,
  Calendar_default as default,
  openableDay,
  openingDay,
  propsSpan
};
//# sourceMappingURL=Calendar.dev.js.map
