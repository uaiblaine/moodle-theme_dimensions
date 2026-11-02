var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The key chips: one toggle button per key it is handed, in its key's colours, with the count of the range on screen.
 *
 * A chip is a button with aria-pressed. A chip off is a key the range is not asked for, so it carries no count:
 * the server never counted it.
 *
 * @module     theme_dimensions/calendar/KeyChips
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
const KeyChips = /* @__PURE__ */ __name(({ strings, keys, off, counts, onToggle }) => /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__chiprow", role: "group", "aria-label": strings.calendar_keys, "data-region": "dim-cal-chips", children: keys.map((key) => {
  const on = !off.includes(key);
  return /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className: `dim-cal__chip dim-cal-key--${key}`,
      "aria-pressed": on,
      "data-key": key,
      onClick: () => onToggle(key),
      children: [
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__swatch", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/KeyChips.tsx",
          lineNumber: 86,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__chiplabel", children: keyLabel(strings, key) }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/KeyChips.tsx",
          lineNumber: 87,
          columnNumber: 21
        }),
        on ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__chipcount", children: counts[key] || 0 }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/KeyChips.tsx",
          lineNumber: 88,
          columnNumber: 27
        }) : null
      ]
    },
    key,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/KeyChips.tsx",
      lineNumber: 78,
      columnNumber: 17
    }
  );
}) }, void 0, false, {
  fileName: "public/theme/dimensions/js/esm/src/calendar/KeyChips.tsx",
  lineNumber: 74,
  columnNumber: 5
}), "KeyChips");
var KeyChips_default = KeyChips;
export {
  KeyChips_default as default,
  keyLabel
};
//# sourceMappingURL=KeyChips.dev.js.map
