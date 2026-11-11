var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The phone's Day view: one day at the frame's whole width.
 *
 * A region labelled by the toolbar's caption, which names the day (its long date) and whose arrows and "Today" move
 * it; its head holds the "New event" "+" while the page offers it; under it the drawer's own body (DayDrawer.tsx
 * DayBody): the all-day strip, then the whole day's hour column in its scroll box, with the drawer's creation paths
 * (a double-tap or a held touch on an hour row). Items open the popover through the page's delegated listener.
 *
 * Shown only on a phone, so no grid is drawn beside it: its one ResizeObserver watches the frame it is drawn in and
 * reports the frame's width alone, the grids' rule (MonthGrid.tsx PHONE), so the page leaves the Day view when the
 * frame becomes a desktop's. The frame's width does not depend on what it holds. Nothing is measured on the mount
 * path: the observer's entries are the measures.
 *
 * @module     theme_dimensions/calendar/DayView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useRef } from "react";
import { DayBody } from "./DayDrawer";
const DayView = /* @__PURE__ */ __name(({ look, iso, placed, captionid, action, onCreate, onWidth }) => {
  const root = useRef(null);
  const width = useRef(onWidth);
  width.current = onWidth;
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
  return /* @__PURE__ */ jsxDEV(
    "section",
    {
      ref: root,
      className: "dim-cal__drawer dim-cal__drawer--dayview",
      role: "region",
      "aria-labelledby": captionid,
      "data-region": "dim-cal-dayview",
      "data-day": iso,
      children: [
        action ? /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__drawerhead dim-cal__dayviewhead", children: action }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/DayView.tsx",
          lineNumber: 88,
          columnNumber: 23
        }) : null,
        /* @__PURE__ */ jsxDEV(DayBody, { look, iso, placed, list: false, onCreate }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/DayView.tsx",
          lineNumber: 89,
          columnNumber: 13
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/DayView.tsx",
      lineNumber: 80,
      columnNumber: 9
    }
  );
}, "DayView");
var DayView_default = DayView;
export {
  DayView_default as default
};
//# sourceMappingURL=DayView.dev.js.map
