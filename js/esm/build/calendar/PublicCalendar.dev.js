var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The public calendar's client: the visitor's shell of the calendar page (docs/calendar/PROPOSAL.md section 19).
 *
 * The same grid, chips, drawer and popover as the logged-in page, over the shared modules of calendar/, with what a
 * visitor has: two keys (the public categories' events, and the public event courses), nothing saved, no category,
 * no "More" filters and no link to core's calendar, which asks a visitor to log in. Mounted by core's react_autoinit
 * on the node calendar_page.mustache draws in its visitor mode; the props carry the first answer, so the first paint
 * asks for nothing.
 *
 * - One service, {@see METHOD}, answered without a session: one call site ({@see ask()}), posted with login not
 *   required and no cachekey (a cachekey would turn it into a GET answered with ninety days of public caching), with
 *   the page's language.
 * - The latest request wins: a ticket is taken per request and checked after the await. A Pending token
 *   (theme_dimensions/calendar:range) spans the request to the painted grid.
 * - The address has one writer (calendar/history.ts): a month moved pushes an entry, a chip and the open day replace
 *   it; Back and Forward read the address again.
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
import { parse, say, shiftMonth } from "../daterange/calendar";
import DayDrawer from "./DayDrawer";
import { gridWeeks, zone as zoneOf } from "./days";
import EventPopover from "./EventPopover";
import { writeAddress } from "./history";
import KeyChips from "./KeyChips";
import { dayDots, place } from "./layout";
import { DAY_PARAM, emptyAnswer, isMonth, withDay } from "./model";
import MonthGrid, { PHONE } from "./MonthGrid";
import { itemKey, overlapsOf } from "./overlap";
import { load as loadStrings } from "./strings";
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
const openableDay = /* @__PURE__ */ __name((day, month, firstday) => day && gridWeeks(month, firstday).flat().includes(day) ? day : null, "openableDay");
const PublicCalendar = /* @__PURE__ */ __name((props) => {
  const language = document.documentElement.lang || void 0;
  const firstday = Number(props.firstday) || 0;
  const [strings, setStrings] = useState(null);
  const [shown, setShown] = useState({ month: props.month, answer: props.answer });
  const [on, setOn] = useState(() => visitorKeys(props.keys));
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
  const opened = useRef(openday);
  opened.current = openday;
  const showing = useRef(shown.month);
  showing.current = shown.month;
  const zone = useMemo(() => zoneOf(props.timezone, language), [props.timezone, language]);
  const weeks = useMemo(() => gridWeeks(shown.month, firstday), [shown.month, firstday]);
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
      const month = read.month || props.month;
      const day = openableDay(new URL(window.location.href).searchParams.get(DAY_PARAM), month, firstday);
      setOn(read.on);
      request(month, read.on, "none", day || openingDay(month, props.today), false);
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
  const request = /* @__PURE__ */ __name(async (month, keys, write, select, focus) => {
    const seq = ++ticket.current;
    const moved = month !== showing.current;
    if (moved) {
      setOpenday(null);
      setPopped(null);
    }
    const target = publicAddress(props.urls.page, month, keys, moved ? "" : opened.current || "");
    if (write !== "none") {
      writeAddress(target, write === "push");
    }
    const pending = new Pending("theme_dimensions/calendar:range");
    const days = gridWeeks(month, firstday).flat();
    try {
      const answer = keys.length ? await ask(publicArgs(days[0], days[days.length - 1], keys)) : emptyPublicAnswer(days);
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
    } catch {
      pending.resolve();
      if (seq === ticket.current) {
        redirect(target);
      }
    }
  }, "request");
  const toggle = /* @__PURE__ */ __name((key) => {
    const next = on.includes(key) ? on.filter((other) => other !== key) : visitorKeys([...on, key]);
    setOn(next);
    request(shown.month, next, "replace", selected, false);
  }, "toggle");
  const toDay = /* @__PURE__ */ __name((iso, focus) => {
    request(iso.slice(0, 7), on, "push", iso, focus);
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
    colour: "key",
    courses: new Map(shown.answer.courses.map((course) => [course.id, course])),
    categories: new Map(shown.answer.categories.map((category) => [category.id, category])),
    home: props.urls.home
  };
  const counts = Object.fromEntries(VISITOR_KEYS.map((key) => [key, 0]));
  shown.answer.events.forEach((item) => {
    counts[item.key] = (counts[item.key] || 0) + 1;
  });
  const caption = say(`${shown.month}-01`, { month: "long", year: "numeric" }, language);
  const dots = phone ? new Map(shown.answer.days.map((entry) => [entry.date, dayDots(entry.counts)])) : null;
  const poppedentry = popped ? placed.find((entry) => itemKey(entry.item) === popped.key) || null : null;
  const drawn = openday !== null && weeks.flat().includes(openday) ? openday : null;
  const step = /* @__PURE__ */ __name((offset) => toDay(shiftMonth(parse(selected) ? selected : `${shown.month}-01`, offset), false), "step");
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
            lineNumber: 508,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-secondary dim-cal__today", onClick: () => toDay(props.today, false), children: strings.calendar_today }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 509,
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
                fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
                lineNumber: 519,
                columnNumber: 21
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
              lineNumber: 512,
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
                fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
                lineNumber: 528,
                columnNumber: 21
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
              lineNumber: 521,
              columnNumber: 17
            }
          ),
          /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__caption", id: CAPTION, "aria-live": "polite", "data-region": "dim-cal-caption", children: caption }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 530,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__spacer" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 531,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__views", role: "group", "aria-label": strings.calendar_view, children: /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__view dim-cal__view--active", "aria-current": "true", children: strings.calendar_viewmonth }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 533,
            columnNumber: 21
          }) }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 532,
            columnNumber: 17
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
          lineNumber: 507,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__toolbar", children: /* @__PURE__ */ jsxDEV(
          KeyChips,
          {
            strings,
            keys: VISITOR_KEYS,
            off: VISITOR_KEYS.filter((key) => !on.includes(key)),
            counts,
            onToggle: toggle
          },
          void 0,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 537,
            columnNumber: 17
          }
        ) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
          lineNumber: 536,
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
                  fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
                  lineNumber: 549,
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
                  calendar: null,
                  focusing: dayfocus,
                  onClose: closeDay
                },
                void 0,
                false,
                {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
                  lineNumber: 565,
                  columnNumber: 21
                }
              ) : null
            ]
          },
          void 0,
          true,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 545,
            columnNumber: 13
          }
        ),
        shown.answer.truncated ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__status", children: strings.calendar_truncated }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
          lineNumber: 576,
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
            describe: null,
            plain: poppedentry.item.source === "core" ? texts.get(poppedentry.item.id) || "" : "",
            onClose: closePopover
          },
          void 0,
          false,
          {
            fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
            lineNumber: 578,
            columnNumber: 17
          }
        ) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/PublicCalendar.tsx",
      lineNumber: 500,
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
