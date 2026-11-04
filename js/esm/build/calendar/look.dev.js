var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * How an item looks and reads: its colour class or its category's pair, its place, its time, its labels.
 *
 * Shared by the bars and the day cells, so a bar and a line of the same item never disagree.
 *
 * @module     theme_dimensions/calendar/look
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { say } from "../daterange/calendar";
import { hex } from "./model";
import { fill } from "./strings";
const SPAN_DAY = { day: "numeric", month: "long" };
const keyClass = /* @__PURE__ */ __name((item) => `dim-cal-key--${item.overdue ? "overdue" : item.key}`, "keyClass");
const colourStyle = /* @__PURE__ */ __name((item, look) => {
  if (look.colour !== "category" || item.overdue) {
    return {};
  }
  const categoryid = item.categoryid || look.courses.get(item.courseid)?.categoryid || 0;
  const category = look.categories.get(categoryid);
  const bg = category ? hex(category.bg) : null;
  const ink = category ? hex(category.ink) : null;
  if (!bg || !ink) {
    return {};
  }
  return { "--dim-cal-tint": bg, "--dim-cal-edge": ink, "--dim-cal-ink": ink };
}, "colourStyle");
const coverStyle = /* @__PURE__ */ __name((course, look) => {
  const category = look.categories.get(course.categoryid);
  const bg = category ? hex(category.bg) : null;
  const ink = category ? hex(category.ink) : null;
  return bg && ink ? { "--dim-cal-cover-bg": bg, "--dim-cal-cover-ink": ink } : {};
}, "coverStyle");
const placeOf = /* @__PURE__ */ __name((item, look, fallback = true) => {
  if (item.location !== "") {
    return item.location;
  }
  const course = look.courses.get(item.courseid);
  if (!course) {
    return "";
  }
  const place = [course.city, course.venue].filter((part) => part !== "").join(" \xB7 ");
  return place !== "" || !fallback ? place : course.fullname;
}, "placeOf");
const whenOf = /* @__PURE__ */ __name((item, look) => {
  const start = look.zone.time(item.timestart);
  if (item.timeduration > 0) {
    return `${start}\u2013${look.zone.time(item.timestart + item.timeduration)}`;
  }
  return item.overdue ? `${look.strings.calendar_overdue} \xB7 ${start}` : fill(look.strings.calendar_until, start);
}, "whenOf");
const barLabel = /* @__PURE__ */ __name((item, first, last, look) => {
  const from = say(first, SPAN_DAY, look.language);
  const span = first === last ? from : fill(look.strings.calendar_span, { from, to: say(last, SPAN_DAY, look.language) });
  return fill(look.strings.calendar_barlabel, { name: item.name, span });
}, "barLabel");
export {
  barLabel,
  colourStyle,
  coverStyle,
  keyClass,
  placeOf,
  whenOf
};
//# sourceMappingURL=look.dev.js.map
