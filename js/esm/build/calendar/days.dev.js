var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The calendar page's days: the month grid, and the day and the time an instant falls on for the viewer.
 *
 * The grid is the showcase's timezone-free date core (daterange/calendar.ts, imported, never copied): weeks() lays the
 * month out on the viewer's first weekday (core's calendar_get_starting_weekday(), handed by the server), and the blanks
 * it leaves are filled with the neighbouring months' days, the same whole weeks local\calendar\page::grid() asks the
 * range for. The browser's clock and its own timezone are never
 * read: today arrives from the server, and an instant is read in the VIEWER's timezone, which the server names, through
 * Intl's own zone data (a formatter, no arithmetic of ours); a zone Intl does not know reads as UTC.
 *
 * @module     theme_dimensions/calendar/days
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { WEEK, format, shift, weeks } from "../daterange/calendar";
const GRID_HEIGHT = "--dim-cal-gridheight";
const frameStyle = /* @__PURE__ */ __name((height) => ({ [GRID_HEIGHT]: height > 0 ? `${height}px` : void 0 }), "frameStyle");
const monthParts = /* @__PURE__ */ __name((month) => ({ y: Number(month.slice(0, 4)), m: Number(month.slice(5, 7)) }), "monthParts");
const monthAfter = /* @__PURE__ */ __name((month, offset) => {
  const parts = monthParts(month);
  const index = parts.y * 12 + (parts.m - 1) + offset;
  return format({ y: Math.floor(index / 12), m: index % 12 + 1, d: 1 }).slice(0, 7);
}, "monthAfter");
const gridWeeks = /* @__PURE__ */ __name((month, firstday) => {
  const rows = weeks(monthParts(month), firstday);
  const first = `${month}-01`;
  const lead = rows[0].indexOf(first);
  const start = shift(first, -lead);
  return rows.map((row, index) => Array.from({ length: WEEK }, (unused, slot) => shift(start, index * WEEK + slot)));
}, "gridWeeks");
const isWeekend = /* @__PURE__ */ __name((iso) => {
  const weekday = new Date(Date.UTC(Number(iso.slice(0, 4)), Number(iso.slice(5, 7)) - 1, Number(iso.slice(8, 10)))).getUTCDay();
  return weekday === 0 || weekday === 6;
}, "isWeekend");
const formatterIn = /* @__PURE__ */ __name((language, options, timezone) => {
  try {
    return new Intl.DateTimeFormat(language, { ...options, timeZone: timezone });
  } catch (error) {
    return new Intl.DateTimeFormat(language, { ...options, timeZone: "UTC" });
  }
}, "formatterIn");
const zone = /* @__PURE__ */ __name((timezone, language) => {
  const days = formatterIn("en-CA", { year: "numeric", month: "2-digit", day: "2-digit" }, timezone);
  const times = formatterIn(language, { hour: "2-digit", minute: "2-digit" }, timezone);
  const clock = formatterIn("en-GB", { hour: "2-digit", minute: "2-digit", hourCycle: "h23" }, timezone);
  const memo = /* @__PURE__ */ new Map();
  return {
    /**
     * The day an instant falls on in the zone.
     *
     * @param {number} timestamp The instant, in seconds.
     * @returns {string} YYYY-MM-DD.
     */
    day: /* @__PURE__ */ __name((timestamp) => {
      const second = Math.floor(timestamp);
      let made = memo.get(second);
      if (made === void 0) {
        const parts = Object.fromEntries(days.formatToParts(new Date(second * 1e3)).map((part) => [part.type, part.value]));
        made = `${parts.year}-${parts.month}-${parts.day}`;
        memo.set(second, made);
      }
      return made;
    }, "day"),
    /**
     * The time of an instant in the zone, as the language writes it.
     *
     * @param {number} timestamp The instant, in seconds.
     * @returns {string} The time.
     */
    time: /* @__PURE__ */ __name((timestamp) => times.format(new Date(timestamp * 1e3)), "time"),
    /**
     * The minutes since the day's midnight an instant falls on in the zone, as the wall clock reads them: the
     * hour column's position (a day of a daylight-saving change keeps 24 rows; its clock decides the row).
     *
     * @param {number} timestamp The instant, in seconds.
     * @returns {number} 0 to 1439.
     */
    minutes: /* @__PURE__ */ __name((timestamp) => {
      const parts = Object.fromEntries(clock.formatToParts(new Date(Math.floor(timestamp) * 1e3)).map((part) => [part.type, part.value]));
      return Number(parts.hour) % 24 * 60 + Number(parts.minute);
    }, "minutes")
  };
}, "zone");
export {
  GRID_HEIGHT,
  frameStyle,
  gridWeeks,
  isWeekend,
  monthAfter,
  monthParts,
  zone
};
//# sourceMappingURL=days.dev.js.map
