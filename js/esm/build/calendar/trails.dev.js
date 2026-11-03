var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The Trilhas view's model (docs/trail/PLAN.md section 7): the answer's shape, its address, and where each step's bar
 * goes on the month. Pure: no layout is read, the bars are grid columns.
 *
 * - The view's trail and "Só as minhas" are navigation, like the view: in the address (`trail=`, `mine=1`), never in
 *   the remembered state (local\calendar\page::trail_from_request(), mine_from_request()).
 * - A step's days are read in the viewer's timezone (days.ts zone()), as every item of the calendar.
 * - A bar runs from its step's start to its end, clipped at the month's edges: a step that began earlier is marked
 *   `before`, one that ends in a later month `after` (drawn into the "next month" column), and one with no end `open`
 *   (it fades at the month's edge). A step with an end and no start is a milestone on its end; an expected date drawn as
 *   the bar's end is a milestone too.
 *
 * @module     theme_dimensions/calendar/trails
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { monthLength } from "../daterange/calendar";
const TRAIL_PARAM = "trail";
const MINE_PARAM = "mine";
const monthDays = /* @__PURE__ */ __name((month) => {
  const length = monthLength(Number(month.slice(0, 4)), Number(month.slice(5, 7)));
  return Array.from({ length }, (unused, index) => `${month}-${String(index + 1).padStart(2, "0")}`);
}, "monthDays");
const barOf = /* @__PURE__ */ __name((step, days, zone) => {
  const first = days[0];
  const last = days[days.length - 1];
  const start = step.start > 0 ? zone.day(step.start) : null;
  const end = step.end > 0 ? zone.day(step.end) : null;
  if (start === null && end === null) {
    return null;
  }
  if (start !== null && start > last || end !== null && end < first) {
    return null;
  }
  if (start === null) {
    const at = days.indexOf(String(end));
    return { from: at, to: at, before: false, after: false, open: false, milestone: true };
  }
  const before = start < first;
  const after = end === null || end > last;
  const from = before ? 0 : days.indexOf(start);
  return {
    from,
    // An end before the start (a course's dates out of order) is the start day alone.
    to: Math.max(from, after ? days.length - 1 : days.indexOf(String(end))),
    before,
    after,
    open: end === null,
    milestone: step.expected && end !== null && end <= last
  };
}, "barOf");
const todayIndex = /* @__PURE__ */ __name((steps, now) => {
  const index = steps.findIndex((step) => step.state !== "done" && (step.end === 0 || step.end >= now));
  return index < 0 ? steps.length : index;
}, "todayIndex");
const trailArgs = /* @__PURE__ */ __name((month, filter, categoryid, recursive) => ({
  month,
  trail: filter.trail,
  mine: filter.mine,
  categoryid,
  recursive
}), "trailArgs");
const withTrail = /* @__PURE__ */ __name((href, filter) => {
  const url = new URL(href, window.location.href);
  url.searchParams.delete(TRAIL_PARAM);
  url.searchParams.delete(MINE_PARAM);
  if (filter && filter.trail > 0) {
    url.searchParams.set(TRAIL_PARAM, String(filter.trail));
  }
  if (filter && filter.mine) {
    url.searchParams.set(MINE_PARAM, "1");
  }
  return url.toString();
}, "withTrail");
const readTrail = /* @__PURE__ */ __name((href) => {
  const params = new URL(href, window.location.href).searchParams;
  const trail = String(params.get(TRAIL_PARAM) || "");
  return { trail: /^\d{1,10}$/.test(trail) ? Number(trail) : 0, mine: params.get(MINE_PARAM) === "1" };
}, "readTrail");
const emptyTrails = /* @__PURE__ */ __name((month) => {
  const days = monthDays(month);
  return { from: days[0], to: days[days.length - 1], truncated: false, options: [], trails: [], categories: [] };
}, "emptyTrails");
const filterOf = /* @__PURE__ */ __name((props) => ({ trail: props?.trail || 0, mine: props?.mine === true }), "filterOf");
const trailFiltered = /* @__PURE__ */ __name((filter, categoryid) => filter.trail > 0 || filter.mine || categoryid !== 0, "trailFiltered");
export {
  MINE_PARAM,
  TRAIL_PARAM,
  barOf,
  emptyTrails,
  filterOf,
  monthDays,
  readTrail,
  todayIndex,
  trailArgs,
  trailFiltered,
  withTrail
};
//# sourceMappingURL=trails.dev.js.map
