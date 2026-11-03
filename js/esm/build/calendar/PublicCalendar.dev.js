var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The public calendar's client: the visitor's shell of the calendar page (docs/calendar/PROPOSAL.md section 19).
 *
 * The same grid, event types' dropdown, drawer and popover as the logged-in page, over the shared modules of
 * calendar/, with what a visitor has: two keys (the public categories' events, and the public event courses), nothing
 * saved, no category and no "More" filters. "Clear filters" is drawn while a key is off and turns both back on.
 * Mounted by core's react_autoinit on the node calendar_page.mustache draws in its visitor mode; the props carry the
 * first answer, so the first paint asks for nothing.
 *
 * - One service, {@see METHOD}, answered without a session: one call site ({@see ask()}), posted with login not
 *   required and no cachekey (a cachekey would turn it into a GET answered with ninety days of public caching), with
 *   the page's language.
 * - The latest request wins: a ticket is taken per request and checked after the await. A Pending token
 *   (theme_dimensions/calendar:range) spans the request to the painted grid.
 * - The month view and the week view (stage C4), as on the logged-in page: what is shown is a span with its answer,
 *   one call of the service either way (calendar/weeks.ts); on a phone the Day view too (DayView.tsx, PROPOSAL.md
 *   section 31), a day span on a desktop asked for and drawn as its month with the day's drawer open, as on the
 *   logged-in page.
 * - The address has one writer (calendar/history.ts): a month or a week moved and a view switched push an entry, a chip
 *   and the open day replace it; Back and Forward read the address again.
 * - A failure loads the address of what was asked for, which the server draws: a visitor is never shown the server's
 *   words.
 * - The description is the answer's own text (decision V2), drawn as text; nothing is asked of core.
 *
 * What it imports is the point: nothing that reads the session or asks a logged-in service
 * (categorypublic_source_test walks its whole graph).
 *
 * @module     theme_dimensions/calendar/PublicCalendar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";
import { fetchOne } from "@moodle/lms/core/ajax";
import Config from "@moodle/lms/core/config";
import { redirect } from "@moodle/lms/core/location";
import Pending from "@moodle/lms/core/pending";
import { parse } from "../daterange/calendar";
import DayDrawer from "./DayDrawer";
import DayView from "./DayView";
import { frameStyle, gridWeeks, zone as zoneOf } from "./days";
import EventPopover from "./EventPopover";
import EventTypes, { ClearFilters } from "./EventTypes";
import { writeAddress } from "./history";
import { dayDots, place } from "./layout";
import { DAY_PARAM, emptyAnswer, isMonth, withDay } from "./model";
import MonthGrid, { PHONE, guessPhone } from "./MonthGrid";
import { itemKey, overlapsOf } from "./overlap";
import { load as loadStrings } from "./strings";
import ViewSwitch from "./ViewSwitch";
import WeekGrid from "./WeekGrid";
import {
  deskOf,
  drawerOf,
  gridOf,
  inSpan,
  monthOfSpan,
  openOf,
  openingOf,
  readSpan,
  selectedIn,
  spanDays,
  spanKey,
  spanLabels,
  spanOf,
  stepSpan
} from "./weeks";
const METHOD = "theme_dimensions_get_public_calendar_range";
const VISITOR_KEYS = ["category", "open"];
const KEYS_PARAM = "keys[]";
const CAPTION = "dim-cal-caption";
const visitorKeys = /* @__PURE__ */ __name((keys) => VISITOR_KEYS.filter((key) => keys.includes(key)), "visitorKeys");
const publicAddress = /* @__PURE__ */ __name((base, month, on, day = "") => {
  const url = new URL(base, window.location.href);
  url.search = "";
  url.searchParams.append("month", month);
  if (day !== "") {
    url.searchParams.append(DAY_PARAM, day);
  }
  const kept = visitorKeys(on);
  if (kept.length !== VISITOR_KEYS.length) {
    (kept.length ? kept : [""]).forEach((key) => url.searchParams.append(KEYS_PARAM, key));
  }
  return url.toString();
}, "publicAddress");
const readPublicAddress = /* @__PURE__ */ __name((href) => {
  const params = new URL(href, window.location.href).searchParams;
  const month = params.get("month");
  return {
    month: isMonth(month) ? month : null,
    on: params.has(KEYS_PARAM) ? visitorKeys(params.getAll(KEYS_PARAM)) : [...VISITOR_KEYS]
  };
}, "readPublicAddress");
const publicArgs = /* @__PURE__ */ __name((from, to, on) => ({
  from,
  to,
  keys: visitorKeys(on),
  lang: Config.language
}), "publicArgs");
const ask = /* @__PURE__ */ __name((args) => {
  return fetchOne({
    methodname: METHOD,
    args
  }, { loginrequired: false });
}, "ask");
const emptyPublicAnswer = /* @__PURE__ */ __name((days) => ({ ...emptyAnswer(days), descriptions: [] }), "emptyPublicAnswer");
const openingDay = /* @__PURE__ */ __name((month, today) => today.startsWith(month) ? today : `${month}-01`, "openingDay");
const propsSpan = /* @__PURE__ */ __name((props) => {
  if (props.view === "day" && parse(props.day)) {
    return { view: "day", day: String(props.day) };
  }
  if (props.view === "week" && parse(props.week)) {
    return { view: "week", week: String(props.week) };
  }
  return { view: "month", month: props.month };
}, "propsSpan");
const PublicCalendar = /* @__PURE__ */ __name((props) => {
  const language = document.documentElement.lang || void 0;
  const firstday = Number(props.firstday) || 0;
  const [strings, setStrings] = useState(null);
  const [shown, setShown] = useState(() => ({ span: propsSpan(props), answer: props.answer }));
  const [on, setOn] = useState(() => visitorKeys(props.keys));
  const [openday, setOpenday] = useState(
    () => openOf(propsSpan(props), props.day, spanDays(propsSpan(props), firstday))
  );
  const [selected, setSelected] = useState(() => openday || openingOf(propsSpan(props), props.today));
  const [focusing, setFocusing] = useState(0);
  const [dayfocus, setDayfocus] = useState(0);
  const [popped, setPopped] = useState(null);
  const [phone, setPhone] = useState(guessPhone);
  const [gridheight, setGridHeight] = useState(0);
  const root = useRef(null);
  const ticket = useRef(0);
  const painting = useRef(null);
  const mounting = useRef(null);
  const opened = useRef(openday);
  opened.current = openday;
  const showing = useRef(shown.span);
  showing.current = shown.span;
  const phoned = useRef(phone);
  const zone = useMemo(() => zoneOf(props.timezone, language), [props.timezone, language]);
  const month = monthOfSpan(shown.span, selected);
  const weeks = useMemo(() => gridWeeks(month, firstday), [month, firstday]);
  const days = useMemo(() => spanDays(shown.span, firstday), [shown.span, firstday]);
  const placed = useMemo(() => place(shown.answer.events, zone), [shown.answer, zone]);
  const texts = useMemo(
    () => new Map((shown.answer.descriptions || []).map((entry) => [entry.id, entry.text])),
    [shown.answer]
  );
  useEffect(() => {
    mounting.current = new Pending("theme_dimensions/calendar:mount");
    loadStrings().then((values) => {
      setStrings(values);
      return values;
    }).catch(() => {
      mounting.current?.resolve();
      mounting.current = null;
    });
    const onPop = /* @__PURE__ */ __name(() => {
      const read = readPublicAddress(window.location.href);
      const span = readSpan(window.location.href, firstday, read.month || props.month, props.today);
      const day = openOf(span, new URL(window.location.href).searchParams.get(DAY_PARAM), spanDays(span, firstday));
      setOn(read.on);
      request(span, read.on, "none", day || openingOf(span, props.today), false);
      setOpenday(day);
    }, "onPop");
    window.addEventListener("popstate", onPop);
    return () => {
      window.removeEventListener("popstate", onPop);
      mounting.current?.resolve();
      painting.current?.resolve();
    };
  }, []);
  useLayoutEffect(() => {
    if (!strings) {
      return;
    }
    const page = root.current?.closest('[data-region="dim-cal"]');
    page?.classList.add("dim-cal--mounted");
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
  const request = /* @__PURE__ */ __name(async (want, keys, write, select, focus) => {
    const seq = ++ticket.current;
    const desk = deskOf(want, phoned.current, firstday);
    const span = desk.span;
    if (desk.open !== null) {
      opened.current = desk.open;
      setOpenday(desk.open);
    }
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
    const target = inSpan(publicAddress(props.urls.page, monthOfSpan(span, select), keys, kept), span);
    if (write !== "none") {
      writeAddress(target, write === "push");
    }
    const pending = new Pending("theme_dimensions/calendar:range");
    try {
      const answer = keys.length ? await ask(publicArgs(days2[0], days2[days2.length - 1], keys)) : emptyPublicAnswer(days2);
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
    } catch {
      pending.resolve();
      if (seq === ticket.current) {
        redirect(target);
      }
    }
  }, "request");
  const keep = /* @__PURE__ */ __name((keys) => {
    const next = visitorKeys(keys);
    if (next.length === on.length && next.every((key) => on.includes(key))) {
      return;
    }
    setOn(next);
    request(shown.span, next, "replace", selected, false);
  }, "keep");
  const pressGround = /* @__PURE__ */ __name((iso, hour, clicks) => {
    if (clicks <= 1) {
      openDay(iso);
    }
  }, "pressGround");
  const clear = /* @__PURE__ */ __name(() => {
    root.current?.querySelector('[data-action="dim-cal-types"]')?.focus();
    keep(VISITOR_KEYS);
  }, "clear");
  const toDay = /* @__PURE__ */ __name((iso, focus) => {
    request(spanOf(shown.span.view, iso, firstday), on, "push", iso, focus);
  }, "toDay");
  const toView = /* @__PURE__ */ __name((view) => {
    if (view !== shown.span.view) {
      request(spanOf(view, selected, firstday), on, "push", selected, false);
    }
  }, "toView");
  const onWidth = /* @__PURE__ */ __name((width) => {
    const narrow = width < PHONE;
    phoned.current = narrow;
    setPhone(narrow);
    const span = showing.current;
    if (!narrow && span.view === "day") {
      request(span, on, "replace", span.day, false);
    }
  }, "onWidth");
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
    colour: "key",
    courses: new Map(shown.answer.courses.map((course) => [course.id, course])),
    categories: new Map(shown.answer.categories.map((category) => [category.id, category])),
    home: props.urls.home
  };
  const counts = Object.fromEntries(VISITOR_KEYS.map((key) => [key, 0]));
  shown.answer.events.forEach((item) => {
    counts[item.key] = (counts[item.key] || 0) + 1;
  });
  const labels = spanLabels(shown.span, days, strings, language);
  const dots = phone ? new Map(shown.answer.days.map((entry) => [entry.date, dayDots(entry.counts)])) : null;
  const poppedentry = popped ? placed.find((entry) => itemKey(entry.item) === popped.key) || null : null;
  const grid = gridOf(shown.span, phone);
  const drawn = drawerOf(shown.span, openday, days, phone);
  const step = /* @__PURE__ */ __name((offset) => toDay(stepSpan(shown.span, parse(selected) ? selected : days[0], offset), false), "step");
  const drawGrid = /* @__PURE__ */ __name(() => {
    if (grid === "day") {
      return /* @__PURE__ */ jsxDEV(DayView, { look, iso: days[0], placed, captionid: CAPTION, onWidth }, void 0, false, {
        fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
        lineNumber: 621,
        columnNumber: 17
      });
    }
    if (grid === "week") {
      return /* @__PURE__ */ jsxDEV(
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
          onGround: pressGround,
          onWidth,
          onHeight: setGridHeight
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
          lineNumber: 626,
          columnNumber: 17
        }
      );
    }
    return /* @__PURE__ */ jsxDEV(
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
        onGround: pressGround,
        onWidth,
        onHeight: setGridHeight
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
        lineNumber: 646,
        columnNumber: 13
      }
    );
  }, "drawGrid");
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      ref: root,
      className: `dim-cal__app dim-cal__app--public${phone ? " dim-cal__app--phone" : ""}`,
      "data-region": "dim-cal-app",
      onClick: onPress,
      onKeyDown: onItemKey,
      children: [
        /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__toolbar", children: [
          /* @__PURE__ */ jsxDEV("h1", { className: "dim-cal__title", children: strings.calendar_title }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 675,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-secondary dim-cal__today", onClick: () => toDay(props.today, false), children: strings.calendar_today }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 676,
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
                fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
                lineNumber: 686,
                columnNumber: 21
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
              lineNumber: 679,
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
                fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
                lineNumber: 695,
                columnNumber: 21
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
              lineNumber: 688,
              columnNumber: 17
            }
          ),
          /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__caption", id: CAPTION, "aria-live": "polite", "data-region": "dim-cal-caption", children: labels.caption }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 697,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__spacer" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 698,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV(ViewSwitch, { strings, view: shown.span.view, phone, onView: toView }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 699,
            columnNumber: 17
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
          lineNumber: 674,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__toolbar", children: [
          /* @__PURE__ */ jsxDEV(
            EventTypes,
            {
              strings,
              keys: VISITOR_KEYS,
              off: VISITOR_KEYS.filter((key) => !on.includes(key)),
              counts,
              onChange: (off) => keep(VISITOR_KEYS.filter((key) => !off.includes(key)))
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
              lineNumber: 702,
              columnNumber: 17
            }
          ),
          /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__spacer" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 709,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV(ClearFilters, { shown: on.length !== VISITOR_KEYS.length, label: strings.calendar_clearfilters, onClear: clear }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 710,
            columnNumber: 17
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
          lineNumber: 701,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV(
          "div",
          {
            className: `dim-cal__body${drawn !== null && !phone ? " dim-cal__body--drawer" : ""}`,
            "data-region": "dim-cal-body",
            style: frameStyle(gridheight),
            children: [
              drawGrid(),
              drawn !== null ? /* @__PURE__ */ jsxDEV(
                DayDrawer,
                {
                  look,
                  iso: drawn,
                  gridheight,
                  placed,
                  variant: phone ? "sheet" : "drawer",
                  focusing: dayfocus,
                  onClose: closeDay
                },
                void 0,
                false,
                {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
                  lineNumber: 719,
                  columnNumber: 21
                }
              ) : null
            ]
          },
          void 0,
          true,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 712,
            columnNumber: 13
          }
        ),
        shown.answer.truncated ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__status", children: strings.calendar_truncatedpublic }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
          lineNumber: 730,
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
            modalities: props.modalities,
            models: props.models || [],
            describe: null,
            plain: poppedentry.item.source === "core" ? texts.get(poppedentry.item.id) || "" : "",
            onClose: closePopover
          },
          void 0,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 732,
            columnNumber: 17
          }
        ) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
      lineNumber: 667,
      columnNumber: 9
    }
  );
}, "PublicCalendar");
var PublicCalendar_default = PublicCalendar;
export {
  METHOD,
  VISITOR_KEYS,
  ask,
  PublicCalendar_default as default,
  emptyPublicAnswer,
  publicAddress,
  publicArgs,
  readPublicAddress,
  visitorKeys
};
//# sourceMappingURL=PublicCalendar.dev.js.map
