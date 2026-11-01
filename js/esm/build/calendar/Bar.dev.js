var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * One week's piece of a spanning bar: a link to the item, in the slot the row gave it.
 *
 * The bar sits in the week row's own grid (grid-column: start / span n, grid-row: its slot under the day numbers),
 * with open ends where the item goes on before or after the week. Its label is the item's name and its WHOLE span,
 * whichever week's piece the reader meets. It is out of the Tab order: the grid's keyboard reaches it from a day it
 * covers (F2, then Tab).
 *
 * @module     theme_dimensions/calendar/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { itemHref } from "./model";
import { barLabel, colourStyle, keyClass, placeOf } from "./look";
const Bar = /* @__PURE__ */ __name(({ segment, week, look }) => {
  const { item, first, last } = segment.placed;
  const span = segment.end - segment.start + 1;
  const place = span >= 3 ? placeOf(item, look) : "";
  const classes = ["dim-cal__bar", keyClass(item)];
  if (segment.clipstart) {
    classes.push("dim-cal__bar--clipstart");
  }
  if (segment.clipend) {
    classes.push("dim-cal__bar--clipend");
  }
  const style = {
    ...colourStyle(item, look),
    gridColumn: `${segment.start + 1} / span ${span}`,
    gridRow: segment.slot + 2
  };
  return /* @__PURE__ */ jsxDEV(
    "a",
    {
      className: classes.join(" "),
      href: itemHref(item, look.courses, look.home),
      style,
      tabIndex: -1,
      "aria-label": barLabel(item, first, last, look),
      "data-cal-item": "",
      "data-first": week[segment.start],
      "data-last": week[segment.end],
      "data-source": item.source,
      "data-id": item.id,
      children: [
        segment.clipstart ? /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: "\u2039 " }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Bar.tsx",
          lineNumber: 77,
          columnNumber: 34
        }) : null,
        /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__name", children: [
          item.name,
          place !== "" ? ` \xB7 ${place}` : ""
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Bar.tsx",
          lineNumber: 78,
          columnNumber: 13
        }),
        segment.clipend ? /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: " \u203A" }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/Bar.tsx",
          lineNumber: 79,
          columnNumber: 32
        }) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/Bar.tsx",
      lineNumber: 65,
      columnNumber: 9
    }
  );
}, "Bar");
var Bar_default = Bar;
export {
  Bar_default as default
};
//# sourceMappingURL=Bar.dev.js.map
