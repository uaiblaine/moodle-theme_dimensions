var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The event popover: one item's facts and actions, anchored to the bar or line that opened it, a bottom sheet on a
 * phone (docs/calendar/PROPOSAL.md sections 13.2 and 13.3).
 *
 * One at a time. role="dialog" labelled by the item's name, not modal: the focus moves to its heading on open;
 * Escape, the close button or a press outside close it and give the focus back to the item; Tab past its last
 * control closes it and gives the focus back to the item too, from which the next Tab moves on.
 *
 * - The time is the viewer's timezone's (the zone reader, as the grid places items), "all day" for a whole day, the
 *   span for a multi-day item. The place is the item's location, or an event course's venue and city.
 * - The overlap warning is text naming the other timed items the item intersects (calendar/overlap.ts), never
 *   colour alone.
 * - The description is read lazily, for core's events alone, through the `describe` it is handed: the logged-in page
 *   hands core's own formatted description, the one feeder of the trusted sink below. The latest popover wins: a
 *   ticket is taken per opening and checked when the answer comes, so an answer for an item no longer shown, or
 *   for a popover already closed, is dropped; a Pending token (theme_dimensions/calendar:description) spans it.
 *   The visitor's page hands no `describe` and a `plain` text instead (decision V2), drawn as text.
 * - The actions: the primary is model.ts primaryOf()'s; "Open" stays beside it when the primary is something else
 *   and the item has a page of its own; the course's page comes third. An item with no page of its own (a personal
 *   event, a site event) has neither: the popover never leads to the site's home (PROPOSAL.md section 27.2).
 * - An event course (source `course`) opens with its cover: the course's image, an <img> with an empty alt (never a
 *   url in a style), else its category's branding pair (look.ts coverStyle()), else its key's colours, and a date tile
 *   (the month's abbreviation and the day), hidden from the screen reader, which reads the date in the facts.
 *
 * The position is measured when the popover opens, never on the page's mount: the opener's box and the popover's
 * own, and the fixed navbar's bottom, read once in a layout effect, and {@see anchor()} keeps it inside the viewport
 * below the navbar.
 *
 * @module     theme_dimensions/calendar/EventPopover
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import Pending from "@moodle/lms/core/pending";
import { say } from "../daterange/calendar";
import { keyLabel } from "./EventTypes";
import { colourStyle, coverStyle, keyClass, placeOf, whenOf } from "./look";
import { itemHref, opens, primaryOf, safeHref } from "./model";
import { isAllDay } from "./overlap";
import { fill } from "./strings";
const TITLE = "dim-cal-pop-title";
const GAP = 8;
const DAY = { weekday: "long", day: "numeric", month: "long" };
const SPAN_DAY = { day: "numeric", month: "long" };
const TILE_MONTH = { month: "short" };
const anchor = /* @__PURE__ */ __name((opener, size, viewport, reserved = 0) => {
  const least = reserved + GAP;
  const most = viewport.height - GAP;
  const below = opener.bottom + GAP;
  const above = opener.top - GAP - size.height;
  let top = below;
  if (below + size.height > most) {
    top = above >= least || opener.top - GAP - least > most - below ? above : below;
  }
  const height = Math.min(size.height, most - least);
  top = Math.max(least, Math.min(top, most - height));
  const left = Math.max(GAP, Math.min(opener.left, viewport.width - GAP - size.width));
  return size.height > height ? { top, left, maxHeight: height } : { top, left };
}, "anchor");
const navbarBottom = /* @__PURE__ */ __name(() => {
  const bar = document.querySelector(".navbar.fixed-top");
  return bar ? Math.max(0, bar.getBoundingClientRect().bottom) : 0;
}, "navbarBottom");
const whenText = /* @__PURE__ */ __name((entry, look) => {
  if (entry.first !== entry.last) {
    return fill(look.strings.calendar_span, {
      from: say(entry.first, SPAN_DAY, look.language),
      to: say(entry.last, SPAN_DAY, look.language)
    });
  }
  const day = say(entry.first, DAY, look.language);
  return `${day} \xB7 ${isAllDay(entry.item, look.zone) ? look.strings.calendar_allday : whenOf(entry.item, look)}`;
}, "whenText");
const primaryLabel = /* @__PURE__ */ __name((primary, look) => {
  switch (primary.kind) {
    case "action":
      return primary.name;
    case "join":
      switch (primary.join) {
        case "free":
          return look.strings.calendar_join_free;
        case "key":
          return look.strings.calendar_join_key;
        default:
          return look.strings.calendar_join_open;
      }
    default:
      return look.strings.calendar_open;
  }
}, "primaryLabel");
const cover = /* @__PURE__ */ __name((entry, look) => {
  const course = entry.item.source === "course" ? look.courses.get(entry.item.courseid) : void 0;
  if (!course) {
    return null;
  }
  const image = safeHref(course.image, "");
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      className: `dim-cal__popcover ${keyClass(entry.item)}`,
      style: coverStyle(course, look),
      "data-region": "dim-cal-popcover",
      children: [
        image !== "" ? /* @__PURE__ */ jsxDEV("img", { className: "dim-cal__popcoverimg", src: image, alt: "" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
          lineNumber: 202,
          columnNumber: 29
        }) : null,
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__poptile", "aria-hidden": "true", children: [
          /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__poptilemonth", children: say(entry.first, TILE_MONTH, look.language) }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
            lineNumber: 204,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__poptileday", children: Number(entry.first.slice(8, 10)) }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
            lineNumber: 205,
            columnNumber: 17
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
          lineNumber: 203,
          columnNumber: 13
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
      lineNumber: 197,
      columnNumber: 9
    }
  );
}, "cover");
const factsOf = /* @__PURE__ */ __name((item, look, modalities) => {
  const course = look.courses.get(item.courseid) || null;
  const eventcourse = course !== null && item.source === "course";
  return {
    place: placeOf(item, look),
    modality: eventcourse ? modalities.find((option) => option.value === course.modality)?.label || "" : "",
    course,
    participants: eventcourse ? course.participants : null
  };
}, "factsOf");
const PopActions = /* @__PURE__ */ __name(({ item, course, look, actions }) => {
  const primary = primaryOf(item, look.courses, look.home);
  const open = opens(item, look.courses) ? itemHref(item, look.courses, look.home) : "";
  const coursepage = course && course.url !== course.landingurl ? safeHref(course.url, "") : "";
  return /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__popactions", children: [
    primary.kind !== "none" ? /* @__PURE__ */ jsxDEV("a", { className: "btn btn-primary", href: primary.url, "data-action": `dim-cal-${primary.kind}`, children: primaryLabel(primary, look) }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
      lineNumber: 248,
      columnNumber: 17
    }) : null,
    primary.kind !== "open" && open !== "" ? /* @__PURE__ */ jsxDEV("a", { className: "btn btn-secondary", href: open, "data-action": "dim-cal-open", children: look.strings.calendar_open }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
      lineNumber: 253,
      columnNumber: 17
    }) : null,
    coursepage !== "" ? /* @__PURE__ */ jsxDEV("a", { className: "btn btn-link", href: coursepage, "data-action": "dim-cal-course", children: look.strings.calendar_coursepage }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
      lineNumber: 256,
      columnNumber: 17
    }) : null,
    actions
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
    lineNumber: 246,
    columnNumber: 9
  });
}, "PopActions");
const EventPopover = /* @__PURE__ */ __name((props) => {
  const { look, entry, overlaps, opener, sheet, modalities, describe, plain, actions, onClose } = props;
  const { item } = entry;
  const dialog = useRef(null);
  const heading = useRef(null);
  const ticket = useRef(0);
  const focused = useRef("");
  const [text, setText] = useState(null);
  const [at, setAt] = useState(null);
  useEffect(() => {
    const turn = ++ticket.current;
    if (item.source !== "core" || describe === null) {
      setText("");
      return void 0;
    }
    setText(null);
    const pending = new Pending("theme_dimensions/calendar:description");
    describe(item.id).then((html) => {
      if (turn === ticket.current) {
        setText(html);
      }
      return html;
    }).finally(() => pending.resolve());
    return () => {
      ticket.current++;
    };
  }, [item.source, item.id, describe]);
  useLayoutEffect(() => {
    if (sheet || !dialog.current) {
      setAt(null);
      return;
    }
    const box = opener.getBoundingClientRect();
    const own = dialog.current.getBoundingClientRect();
    setAt(anchor(
      box,
      { width: own.width, height: own.height },
      { width: window.innerWidth, height: window.innerHeight },
      navbarBottom()
    ));
  }, [item.source, item.id, opener, sheet]);
  useLayoutEffect(() => {
    const key = `${item.source}-${item.id}`;
    if ((sheet || at !== null) && focused.current !== key) {
      focused.current = key;
      heading.current?.focus();
    }
  }, [sheet, at, item.source, item.id]);
  useEffect(() => {
    const outside = /* @__PURE__ */ __name((event) => {
      const target = event.target;
      const over = target instanceof Element && target.closest('[role="dialog"]') !== null;
      if (target && !over && !dialog.current?.contains(target) && !opener.contains(target)) {
        onClose(true);
      }
    }, "outside");
    document.addEventListener("mousedown", outside);
    return () => document.removeEventListener("mousedown", outside);
  }, [opener, onClose]);
  const onKeyDown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      event.stopPropagation();
      onClose(true);
      return;
    }
    if (event.key === "Tab" && !event.shiftKey) {
      const controls = Array.from(dialog.current?.querySelectorAll("a[href], button") || []);
      if (controls.length && event.target === controls[controls.length - 1]) {
        event.preventDefault();
        onClose(true);
      }
    }
  }, "onKeyDown");
  const { place, modality, course, participants } = factsOf(item, look, modalities);
  const style = { ...colourStyle(item, look) };
  if (at) {
    style.top = `${at.top}px`;
    style.left = `${at.left}px`;
    if (at.maxHeight !== void 0) {
      style.maxHeight = `${at.maxHeight}px`;
      style.overflowY = "auto";
    }
  }
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      ref: dialog,
      className: `dim-cal__pop${sheet ? " dim-cal__pop--sheet" : ""}${!sheet && !at ? " dim-cal__pop--measuring" : ""}`,
      role: "dialog",
      "aria-labelledby": TITLE,
      "data-region": "dim-cal-popover",
      "data-source": item.source,
      "data-id": item.id,
      style,
      onKeyDown,
      children: [
        cover(entry, look),
        /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__pophead", children: [
          /* @__PURE__ */ jsxDEV("span", { className: `dim-cal__kind ${keyClass(item)}`, children: [
            /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__swatch", "aria-hidden": "true" }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
              lineNumber: 398,
              columnNumber: 21
            }),
            keyLabel(look.strings, item.key)
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
            lineNumber: 397,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "btn btn-icon dim-cal__close",
              "aria-label": look.strings.calendar_close,
              "data-action": "dim-cal-pop-close",
              onClick: () => onClose(true),
              children: /* @__PURE__ */ jsxDEV("i", { className: "fa fa-xmark", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
                lineNumber: 408,
                columnNumber: 21
              })
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
              lineNumber: 401,
              columnNumber: 17
            }
          )
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
          lineNumber: 396,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("h2", { ref: heading, className: "dim-cal__poptitle", id: TITLE, tabIndex: -1, children: item.name }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
          lineNumber: 411,
          columnNumber: 13
        }),
        /* @__PURE__ */ jsxDEV("dl", { className: "dim-cal__popfacts", children: [
          /* @__PURE__ */ jsxDEV("dt", { children: look.strings.calendar_when }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
            lineNumber: 413,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("dd", { children: whenText(entry, look) }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
            lineNumber: 414,
            columnNumber: 17
          }),
          place !== "" ? /* @__PURE__ */ jsxDEV("dt", { children: look.strings.calendar_place }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
            lineNumber: 415,
            columnNumber: 33
          }) : null,
          place !== "" ? /* @__PURE__ */ jsxDEV("dd", { children: place }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
            lineNumber: 416,
            columnNumber: 33
          }) : null,
          modality !== "" ? /* @__PURE__ */ jsxDEV("dt", { children: look.strings.calendar_modality }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
            lineNumber: 417,
            columnNumber: 36
          }) : null,
          modality !== "" ? /* @__PURE__ */ jsxDEV("dd", { children: modality }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
            lineNumber: 418,
            columnNumber: 36
          }) : null,
          course ? /* @__PURE__ */ jsxDEV("dt", { children: look.strings.calendar_course }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
            lineNumber: 419,
            columnNumber: 27
          }) : null,
          course ? /* @__PURE__ */ jsxDEV("dd", { children: course.fullname }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
            lineNumber: 420,
            columnNumber: 27
          }) : null
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
          lineNumber: 412,
          columnNumber: 13
        }),
        participants !== null ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__popnote", children: participants === 1 ? look.strings.calendar_participants_one : fill(look.strings.calendar_participants_many, participants) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
          lineNumber: 423,
          columnNumber: 17
        }) : null,
        overlaps.length ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__overlapwarn", "data-region": "dim-cal-overlap", children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-triangle-exclamation", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
            lineNumber: 430,
            columnNumber: 21
          }),
          fill(look.strings.calendar_overlaps, overlaps.map((other) => other.item.name).join(", "))
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
          lineNumber: 429,
          columnNumber: 17
        }) : null,
        text === null ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__poploading", children: look.strings.calendar_descriptionloading }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
          lineNumber: 434,
          columnNumber: 30
        }) : null,
        plain !== "" ? /* @__PURE__ */ jsxDEV("p", { className: "dim-cal__popdesc dim-cal__popdesc--text", "data-region": "dim-cal-description", children: plain }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
          lineNumber: 436,
          columnNumber: 17
        }) : null,
        text ? (
          // Core's own formatted HTML: core's event service's description, which core's event exporter ran
          // through format_text() for this viewer, handed in as describe by the logged-in page alone; nothing
          // else may reach it. The one trusted sink of the calendar.
          /* @__PURE__ */ jsxDEV(
            "div",
            {
              className: "dim-cal__popdesc",
              "data-region": "dim-cal-description",
              dangerouslySetInnerHTML: { __html: text }
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
              lineNumber: 442,
              columnNumber: 17
            }
          )
        ) : null,
        /* @__PURE__ */ jsxDEV(PopActions, { item, course, look, actions }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
          lineNumber: 448,
          columnNumber: 13
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/EventPopover.tsx",
      lineNumber: 384,
      columnNumber: 9
    }
  );
}, "EventPopover");
var EventPopover_default = EventPopover;
export {
  TITLE,
  anchor,
  EventPopover_default as default,
  factsOf,
  primaryLabel,
  whenText
};
//# sourceMappingURL=EventPopover.dev.js.map
