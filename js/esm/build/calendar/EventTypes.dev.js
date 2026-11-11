var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The event types' filter: one toolbar button and its dropdown of checkboxes, one per key it is handed, each with its
 * key's swatch and the count of the range on screen.
 *
 * The button names the filter and says how much of it is on: "All" while every key is, else a badge "N of M". The
 * dropdown is popover.ts's disclosure (a press outside closes it; Escape closes it from anywhere while it is open,
 * through a document listener, because the list re-renders under a press of "Select all" and the focus can fall
 * outside the dropdown, where no handler on it would see the key; the focus goes back to the button) holding real
 * checkboxes with their labels and a "Select all" button. A key unchecked is a key the range is not asked for, so it
 * carries no count: the server never counted it. What it writes is the page state's `off` list of keys.
 *
 * @module     theme_dimensions/calendar/EventTypes
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect } from "react";
import { usePopover } from "./popover";
import { fill } from "./strings";
const keyLabel = /* @__PURE__ */ __name((strings, key) => {
  switch (key) {
    case "site":
      return strings.calendar_key_site;
    case "category":
      return strings.calendar_key_category;
    case "course":
      return strings.calendar_key_course;
    case "group":
      return strings.calendar_key_group;
    case "user":
      return strings.calendar_key_user;
    case "activity":
      return strings.calendar_key_activity;
    case "open":
      return strings.calendar_key_open;
    default:
      return strings.calendar_key_other;
  }
}, "keyLabel");
const toggledOff = /* @__PURE__ */ __name((keys, off, key, on) => keys.filter((other) => other === key ? !on : off.includes(other)), "toggledOff");
const ClearFilters = /* @__PURE__ */ __name(({ shown, label, onClear }) => shown ? /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-link dim-cal__clear", "data-action": "dim-cal-clear", onClick: onClear, children: label }, void 0, false, {
  fileName: "public/theme/dimensions/js/esm/src/calendar/EventTypes.tsx",
  lineNumber: 94,
  columnNumber: 5
}) : null, "ClearFilters");
const EventTypes = /* @__PURE__ */ __name(({ strings, keys, off, counts, onChange }) => {
  const popover = usePopover();
  const panelid = "dim-cal-typespanel";
  const on = keys.filter((key) => !off.includes(key)).length;
  const all = on === keys.length;
  const { open, shut } = popover;
  useEffect(() => {
    if (!open) {
      return void 0;
    }
    const escape = /* @__PURE__ */ __name((event) => {
      if (event.key === "Escape") {
        shut(true);
      }
    }, "escape");
    document.addEventListener("keydown", escape);
    return () => document.removeEventListener("keydown", escape);
  }, [open, shut]);
  const some = fill(strings.calendar_eventtypes_some, { on, total: keys.length });
  return /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__picker", "data-region": "dim-cal-types", children: [
    /* @__PURE__ */ jsxDEV(
      "button",
      {
        ref: popover.button,
        type: "button",
        className: "btn btn-secondary dim-cal__pickerbtn",
        "aria-expanded": popover.open,
        "aria-controls": panelid,
        "data-action": "dim-cal-types",
        onClick: popover.toggle,
        children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-tags", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventTypes.tsx",
            lineNumber: 141,
            columnNumber: 17
          }),
          strings.calendar_eventtypes,
          all ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__typesall", children: strings.calendar_eventtypes_all }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventTypes.tsx",
            lineNumber: 143,
            columnNumber: 24
          }) : /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__badge", "data-region": "dim-cal-types-count", children: some }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/EventTypes.tsx",
            lineNumber: 144,
            columnNumber: 21
          })
        ]
      },
      void 0,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/EventTypes.tsx",
        lineNumber: 132,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        ref: popover.panel,
        className: "dim-cal__popover dim-cal__typespanel",
        id: panelid,
        role: "group",
        "aria-label": strings.calendar_eventtypes,
        hidden: !popover.open,
        onKeyDown: popover.onKeyDown,
        children: [
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "btn btn-link dim-cal__selectall",
              "data-action": "dim-cal-types-all",
              disabled: all,
              onClick: () => onChange([]),
              children: strings.calendar_selectall
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/EventTypes.tsx",
              lineNumber: 156,
              columnNumber: 17
            }
          ),
          keys.map((key) => {
            const checked = !off.includes(key);
            return /* @__PURE__ */ jsxDEV("label", { className: `dim-cal__check dim-cal__typecheck dim-cal-key--${key}`, children: [
              /* @__PURE__ */ jsxDEV(
                "input",
                {
                  type: "checkbox",
                  name: "dim-cal-type",
                  value: key,
                  checked,
                  "data-key": key,
                  onChange: (event) => onChange(toggledOff(keys, off, key, event.target.checked))
                },
                void 0,
                false,
                {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/EventTypes.tsx",
                  lineNumber: 167,
                  columnNumber: 29
                }
              ),
              /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__swatch", "aria-hidden": "true" }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/EventTypes.tsx",
                lineNumber: 175,
                columnNumber: 29
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__chiplabel", children: keyLabel(strings, key) }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/EventTypes.tsx",
                lineNumber: 176,
                columnNumber: 29
              }),
              checked ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__chipcount", children: counts[key] || 0 }, void 0, false, {
                fileName: "public/theme/dimensions/js/esm/src/calendar/EventTypes.tsx",
                lineNumber: 177,
                columnNumber: 40
              }) : null
            ] }, key, true, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/EventTypes.tsx",
              lineNumber: 166,
              columnNumber: 25
            });
          })
        ]
      },
      void 0,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/EventTypes.tsx",
        lineNumber: 147,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/EventTypes.tsx",
    lineNumber: 131,
    columnNumber: 9
  });
}, "EventTypes");
var EventTypes_default = EventTypes;
export {
  ClearFilters,
  EventTypes_default as default,
  keyLabel,
  toggledOff
};
//# sourceMappingURL=EventTypes.dev.js.map
