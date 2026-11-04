var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The Trilhas view's model: the answer's shape, its address, the months it holds and where each step's bar and each
 * connector goes on them. Pure: no layout is read, the bars are grid columns and the connectors rem.
 *
 * - The view's trail and "Só as minhas" are navigation, like the view: in the address (`trail=`, `mine=1`), never in
 *   the remembered state (local\calendar\page::trail_from_request(), mine_from_request()).
 * - A step's days are read in the viewer's timezone (days.ts zone()), as every item of the calendar.
 * - The view holds contiguous whole months ({@see Loaded}), at most {@see LOADED_CAP}: the first paint's answer is the
 *   month and the next (local\calendar\trails::MONTHS), each further month is one call, appended or prepended, and the
 *   answers are merged by trail id ({@see mergedOf()}), a trail kept while its period meets what is held.
 * - A bar runs from its step's start to its end, clipped at the edges of what is held: a step that began earlier is
 *   marked `before`, one that ends later `after`, and one with no end `open` (it fades at the edge). A step with an end
 *   and no start is a milestone on its end; an expected date drawn as the bar's end is a milestone too.
 * - On a desktop the days have a fixed width and only the timeline scrolls, the trail column held still beside it. The
 *   scroller's geometry is read from the scroller alone ({@see trackOf()}: its content width is the trail column and the
 *   days, TRACK, in rem, which scss/calendar.scss declares with the same numbers), so the position of today, a month, a
 *   course, a page of days and the map's thumb are pure arithmetic over three numbers the scroll handlers read.
 * - Consecutive steps are joined in the trail's order ({@see linksOf()}), never by what their enrolment needs: the
 *   path is chosen from the dates alone, and a step with no date breaks the chain.
 *
 * @module     theme_dimensions/calendar/trails
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { monthLength, say } from "../daterange/calendar";
import { monthAfter } from "./days";
const TRAIL_PARAM = "trail";
const MINE_PARAM = "mine";
const monthDays = /* @__PURE__ */ __name((month) => {
  const length = monthLength(Number(month.slice(0, 4)), Number(month.slice(5, 7)));
  return Array.from({ length }, (unused, index) => `${month}-${String(index + 1).padStart(2, "0")}`);
}, "monthDays");
const barOf = /* @__PURE__ */ __name((step, days, zone) => spanOf(step.start, step.end, step.expected, days, zone), "barOf");
const trailBarOf = /* @__PURE__ */ __name((trail, days, zone) => spanOf(trail.first, trail.last, false, days, zone), "trailBarOf");
const spanOf = /* @__PURE__ */ __name((begins, ends, expected, days, zone) => {
  const first = days[0];
  const last = days[days.length - 1];
  const start = begins > 0 ? zone.day(begins) : null;
  let end = ends > 0 ? zone.day(ends) : null;
  if (start === null && end === null) {
    return null;
  }
  if (start !== null && end !== null && end < start) {
    end = start;
  }
  if (start !== null && start > last || end !== null && end < first) {
    return null;
  }
  if (start === null) {
    const at = days.indexOf(String(end));
    if (at < 0) {
      return null;
    }
    return { from: at, to: at, before: false, after: false, open: false, milestone: true };
  }
  const before = start < first;
  const after = end === null || end > last;
  const from = before ? 0 : days.indexOf(start);
  return {
    from,
    to: Math.max(from, after ? days.length - 1 : days.indexOf(String(end))),
    before,
    after,
    open: end === null,
    milestone: expected && end !== null && end <= last
  };
}, "spanOf");
const todayIndex = /* @__PURE__ */ __name((steps, now) => {
  const index = steps.findIndex((step) => step.state !== "done" && (step.end === 0 || step.end >= now));
  return index < 0 ? steps.length : index;
}, "todayIndex");
const trailArgs = /* @__PURE__ */ __name((month, filter, categoryid, recursive, months = 1) => ({
  month,
  trail: filter.trail,
  mine: filter.mine,
  categoryid,
  recursive,
  months
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
const ANSWER_MONTHS = 2;
const LOADED_CAP = 6;
const LOAD_AHEAD = 7;
const SKELETON_DAYS = 3;
const STEP_INSET = 2;
const monthsOf = /* @__PURE__ */ __name((from, to) => {
  const months = [from.slice(0, 7)];
  const last = to.slice(0, 7);
  while (months[months.length - 1] < last && months.length < 24) {
    months.push(monthAfter(months[months.length - 1], 1));
  }
  return months;
}, "monthsOf");
const windowDays = /* @__PURE__ */ __name((months) => months.flatMap((month) => monthDays(month)), "windowDays");
const loadedOf = /* @__PURE__ */ __name((answer) => {
  const months = monthsOf(answer.from, answer.to);
  return { months, answers: months.map(() => answer) };
}, "loadedOf");
const meets = /* @__PURE__ */ __name((trail, from, to, zone) => (trail.first <= 0 || zone.day(trail.first) <= to) && (trail.last <= 0 || zone.day(trail.last) >= from), "meets");
const byName = /* @__PURE__ */ __name((a, b) => {
  const one = a.toLowerCase();
  const other = b.toLowerCase();
  return one < other ? -1 : Number(one > other);
}, "byName");
const mergedOf = /* @__PURE__ */ __name((loaded, zone) => {
  const days = windowDays(loaded.months);
  const from = days[0];
  const to = days[days.length - 1];
  const trails = /* @__PURE__ */ new Map();
  const options = /* @__PURE__ */ new Map();
  const categories = /* @__PURE__ */ new Map();
  let truncated = false;
  Array.from(new Set(loaded.answers)).forEach((answer) => {
    truncated = truncated || answer.truncated;
    answer.trails.forEach((trail) => trails.set(trail.id, trails.get(trail.id) ?? trail));
    answer.options.forEach((option) => options.set(option.id, options.get(option.id) ?? option));
    answer.categories.forEach((category) => categories.set(category.id, categories.get(category.id) ?? category));
  });
  return {
    from,
    to,
    truncated,
    options: Array.from(options.values()).sort((a, b) => byName(a.name, b.name) || a.id - b.id),
    trails: Array.from(trails.values()).filter((trail) => meets(trail, from, to, zone)).sort((a, b) => a.first - b.first || byName(a.name, b.name) || a.id - b.id),
    categories: Array.from(categories.values())
  };
}, "mergedOf");
const extendLoaded = /* @__PURE__ */ __name((loaded, answer, cap = LOADED_CAP) => {
  const month = answer.from.slice(0, 7);
  const months = [...loaded.months];
  const answers = [...loaded.answers];
  let shift = 0;
  if (month === monthAfter(months[months.length - 1], 1)) {
    months.push(month);
    answers.push(answer);
    while (months.length > cap) {
      shift -= monthDays(String(months.shift())).length;
      answers.shift();
    }
  } else if (month === monthAfter(months[0], -1)) {
    months.unshift(month);
    answers.unshift(answer);
    shift = monthDays(month).length;
    while (months.length > cap) {
      months.pop();
      answers.pop();
    }
  } else {
    return null;
  }
  return { loaded: { months, answers }, shift };
}, "extendLoaded");
const keptLeft = /* @__PURE__ */ __name((left, shift, pitch) => Math.max(0, left + shift * pitch), "keptLeft");
const monthStep = /* @__PURE__ */ __name((first, direction) => {
  if (direction > 0) {
    return monthAfter(first.slice(0, 7), 1);
  }
  return first.endsWith("-01") ? monthAfter(first.slice(0, 7), -1) : first.slice(0, 7);
}, "monthStep");
const MONTH_YEAR = { month: "long", year: "numeric" };
const rangeLabel = /* @__PURE__ */ __name((first, last, language) => {
  const from = `${first.slice(0, 7)}-01`;
  const to = `${last.slice(0, 7)}-01`;
  if (from >= to) {
    return say(from, MONTH_YEAR, language);
  }
  try {
    return new Intl.DateTimeFormat(language, { ...MONTH_YEAR, timeZone: "UTC" }).formatRange(/* @__PURE__ */ new Date(`${from}T00:00:00Z`), /* @__PURE__ */ new Date(`${to}T00:00:00Z`));
  } catch (error) {
    return `${say(from, MONTH_YEAR, language)} \u2013 ${say(to, MONTH_YEAR, language)}`;
  }
}, "rangeLabel");
const TRACK = { label: 21.25, day: 3, row: 3.25, bar: 1.5 };
const trackOf = /* @__PURE__ */ __name((left, total, width, days) => {
  const pitch = days > 0 ? total / (days + TRACK.label / TRACK.day) : 0;
  const max = Math.max(0, total - width);
  if (pitch <= 0 || max < 1) {
    return { pitch, view: Math.max(0, width), max: 0, left: 0, start: 0, span: 1, back: false, ahead: false };
  }
  const view = Math.max(0, width - pitch * TRACK.label / TRACK.day);
  const span = Math.min(1, view / (days * pitch));
  const at = Math.max(0, Math.min(left, max));
  return {
    pitch,
    view,
    max,
    left: at,
    start: Math.min(1 - span, at / (days * pitch)),
    span,
    back: at > 1,
    ahead: at < max - 1
  };
}, "trackOf");
const dayLeft = /* @__PURE__ */ __name((index, track) => track.max <= 0 || track.pitch <= 0 ? 0 : Math.max(0, Math.min(track.max, Math.round(index * track.pitch))), "dayLeft");
const todayLeft = /* @__PURE__ */ __name((todayat, monthat, track) => {
  if (todayat < 0 || track.max <= 0) {
    return dayLeft(Math.max(0, monthat), track);
  }
  const far = (todayat + 2) * track.pitch;
  const base = Math.max(0, monthat) * track.pitch;
  return far - base <= track.view ? dayLeft(monthat, track) : Math.min(track.max, Math.round(far - track.view));
}, "todayLeft");
const openingLeft = /* @__PURE__ */ __name((todayat, track) => todayat < 0 ? 0 : todayLeft(todayat, 0, track), "openingLeft");
const shiftedTarget = /* @__PURE__ */ __name((target, shift) => target.kind === "today" ? { kind: "today", index: target.index < 0 ? target.index : target.index + shift, month: target.month + shift } : { kind: target.kind, index: target.index + shift }, "shiftedTarget");
const targetLeft = /* @__PURE__ */ __name((target, track) => {
  if (target.kind === "today") {
    return todayLeft(target.index, target.month, track);
  }
  return dayLeft(target.kind === "step" ? target.index - STEP_INSET : target.index, track);
}, "targetLeft");
const shownDays = /* @__PURE__ */ __name((track, count) => {
  if (track.pitch <= 0 || count <= 0) {
    return { first: 0, last: Math.max(0, count - 1) };
  }
  const first = Math.max(0, Math.min(count - 1, Math.floor(track.left / track.pitch + 1e-3)));
  const last = Math.min(count - 1, Math.ceil((track.left + track.view) / track.pitch - 1e-3) - 1);
  return { first, last: Math.max(first, last) };
}, "shownDays");
const nearEnd = /* @__PURE__ */ __name((track, count) => track.pitch > 0 && track.left + track.view >= (count - LOAD_AHEAD) * track.pitch, "nearEnd");
const pageLeft = /* @__PURE__ */ __name((left, direction, track) => {
  if (track.max <= 0 || track.pitch <= 0) {
    return 0;
  }
  const page = Math.max(1, Math.floor(track.view / track.pitch) - 1) * track.pitch;
  const landed = Math.round((left + direction * page) / track.pitch) * track.pitch;
  return Math.max(0, Math.min(track.max, landed));
}, "pageLeft");
const scrubLeft = /* @__PURE__ */ __name((at, grab, track, days) => {
  const start = Math.max(0, Math.min(1 - track.span, at - grab));
  return Math.max(0, Math.min(track.max, start * days * track.pitch));
}, "scrubLeft");
const LINK = { line: 0.125, stub: 0.625, room: 0.75, detour: 0.5625, head: 0.375, half: 0.25, dot: 0.375 };
const daysBetween = /* @__PURE__ */ __name((from, to) => Math.round((Date.parse(`${to}T00:00:00Z`) - Date.parse(`${from}T00:00:00Z`)) / 864e5), "daysBetween");
const reachOf = /* @__PURE__ */ __name((step, first, zone) => {
  if (step.start <= 0 && step.end <= 0) {
    return null;
  }
  if (step.start <= 0) {
    const at = daysBetween(first, zone.day(step.end));
    return { from: at, to: at };
  }
  const from = daysBetween(first, zone.day(step.start));
  return { from, to: step.end <= 0 ? Infinity : Math.max(from, daysBetween(first, zone.day(step.end))) };
}, "reachOf");
const clipped = /* @__PURE__ */ __name((piece, width) => {
  if (piece.kind !== "horizontal") {
    return piece.left >= 0 && piece.left + piece.width <= width ? piece : null;
  }
  const left = Math.max(0, piece.left);
  const right = Math.min(width, piece.left + piece.width);
  return right > left ? { ...piece, left, width: right - left } : null;
}, "clipped");
const pathOf = /* @__PURE__ */ __name((a, b, row) => {
  const day = TRACK.day;
  const amid = row * TRACK.row + TRACK.row / 2;
  const bmid = amid + TRACK.row;
  const across = /* @__PURE__ */ __name((y, x1, x2) => ({
    kind: "horizontal",
    left: Math.min(x1, x2),
    top: y - LINK.line / 2,
    width: Math.abs(x2 - x1),
    height: LINK.line
  }), "across");
  const down = /* @__PURE__ */ __name((x, y1, y2) => ({
    kind: "vertical",
    left: x - LINK.line / 2,
    top: y1,
    width: LINK.line,
    height: y2 - y1
  }), "down");
  const right = /* @__PURE__ */ __name((x, y) => ({
    kind: "right",
    left: x - LINK.head,
    top: y - LINK.half,
    width: LINK.head,
    height: 2 * LINK.half
  }), "right");
  if (b.from > a.to) {
    const start2 = (a.to + 1) * day;
    const end2 = b.from * day;
    const elbow = Math.min(start2 + LINK.stub, end2 - LINK.room);
    return [
      across(amid, start2, elbow),
      down(elbow, amid - LINK.line / 2, bmid + LINK.line / 2),
      across(bmid, elbow, end2 - LINK.head),
      right(end2, bmid)
    ];
  }
  if (b.from >= a.from) {
    const x = b.from * day + LINK.stub;
    const base = amid + TRACK.bar / 2;
    const head = bmid - TRACK.bar / 2;
    return [
      { kind: "dot", left: x - LINK.dot / 2, top: base - LINK.dot / 2, width: LINK.dot, height: LINK.dot },
      down(x, base, head - LINK.head),
      { kind: "down", left: x - LINK.half, top: head - LINK.head, width: 2 * LINK.half, height: LINK.head }
    ];
  }
  const start = a.from * day;
  const end = b.from * day;
  const turn = end - LINK.detour;
  return [
    across(amid, turn, start),
    down(turn, amid - LINK.line / 2, bmid + LINK.line / 2),
    across(bmid, turn, end - LINK.head),
    right(end, bmid)
  ];
}, "pathOf");
const tidy = /* @__PURE__ */ __name((value) => Math.round(value * 1e4) / 1e4, "tidy");
const linksOf = /* @__PURE__ */ __name((steps, days, zone, shown) => {
  if (!shown || days.length === 0) {
    return [];
  }
  const width = days.length * TRACK.day;
  const reaches = steps.map((step) => reachOf(step, days[0], zone));
  const pieces = [];
  reaches.forEach((reach, index) => {
    const next = index + 1 < reaches.length ? reaches[index + 1] : null;
    if (reach === null || next === null) {
      return;
    }
    pathOf(reach, next, index).forEach((piece) => {
      const kept = clipped(piece, width);
      if (kept) {
        pieces.push({
          kind: kept.kind,
          left: tidy(kept.left),
          top: tidy(kept.top),
          width: tidy(kept.width),
          height: tidy(kept.height)
        });
      }
    });
  });
  return pieces;
}, "linksOf");
export {
  ANSWER_MONTHS,
  LOADED_CAP,
  LOAD_AHEAD,
  MINE_PARAM,
  SKELETON_DAYS,
  STEP_INSET,
  TRACK,
  TRAIL_PARAM,
  barOf,
  dayLeft,
  emptyTrails,
  extendLoaded,
  filterOf,
  keptLeft,
  linksOf,
  loadedOf,
  meets,
  mergedOf,
  monthDays,
  monthStep,
  monthsOf,
  nearEnd,
  openingLeft,
  pageLeft,
  rangeLabel,
  reachOf,
  readTrail,
  scrubLeft,
  shiftedTarget,
  shownDays,
  targetLeft,
  todayIndex,
  todayLeft,
  trackOf,
  trailArgs,
  trailBarOf,
  trailFiltered,
  windowDays,
  withTrail
};
//# sourceMappingURL=trails.dev.js.map
