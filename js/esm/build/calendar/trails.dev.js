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
 *   month and the next (local\calendar\trails::OPENING), each further load is one call of one month, or by months of up to
 *   {@see BATCH_MONTHS} (local\calendar\trails::MONTHS, {@see batchOf()}), appended or prepended, and the
 *   answers are merged by trail id ({@see mergedOf()}), a trail kept while its period meets what is held.
 * - A bar runs from its step's start to its end, clipped at the edges of what is held: a step that began earlier is
 *   marked `before`, one that ends later `after`. A step with a start and no end is `open`: it is drawn as its first
 *   {@see OPEN_DAYS} days, followed by a decorative chip ({@see chipLeft()}) saying it has no end;
 *   an expected completion date is an end, so such a step is not open. A step with an end and no start is a milestone on
 *   its end; an expected date drawn as the bar's end is a milestone too.
 * - On a desktop the days have a fixed width and only the timeline scrolls, the trail column held still beside it. The
 *   scroller's geometry is read from the scroller alone ({@see trackOf()}: its content width is the trail column and the
 *   days, TRACK, in rem, which scss/calendar.scss declares with the same numbers), so the position of today, a month, a
 *   course, a page of days and the map's thumb are pure arithmetic over three numbers the scroll handlers read.
 * - Consecutive steps are joined in the trail's order ({@see linksOf()}), never by what their enrolment needs: the
 *   path is chosen from the dates alone, and a step with no date breaks the chain.
 * - The desktop timeline has a zoom ({@see Zoom}): a day's width is TRACK's for it ({@see dayWidth()}), and every
 *   position (the bars' columns, the connectors, today, the scroll arithmetic) follows it from there. The zoom is
 *   navigation, like the trail (`zoom=week|month`, absent for days: local\calendar\page::zoom_from_request()), and a
 *   switch keeps the day at the start of the view ({@see zoomedLeft()}).
 *
 * @module     theme_dimensions/calendar/trails
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { monthLength, say } from "../daterange/calendar";
import { monthAfter } from "./days";
const TRAIL_PARAM = "trail";
const MINE_PARAM = "mine";
const ZOOM_PARAM = "zoom";
const ZOOMS = ["day", "week", "month"];
const OPEN_DAYS = 30;
const daysAfter = /* @__PURE__ */ __name((day, count) => new Date(Date.parse(`${day}T00:00:00Z`) + count * 864e5).toISOString().slice(0, 10), "daysAfter");
const openEnd = /* @__PURE__ */ __name((start, until) => until !== null && until > start ? until : daysAfter(start, OPEN_DAYS - 1), "openEnd");
const openTrailEnd = /* @__PURE__ */ __name((trail, zone) => {
  let latest = null;
  trail.steps.forEach((step) => {
    let day = null;
    if (step.end > 0) {
      day = zone.day(step.end);
    } else if (step.start > 0) {
      day = daysAfter(zone.day(step.start), OPEN_DAYS - 1);
    }
    if (day !== null && (latest === null || day > latest)) {
      latest = day;
    }
  });
  return latest;
}, "openTrailEnd");
const monthDays = /* @__PURE__ */ __name((month) => {
  const length = monthLength(Number(month.slice(0, 4)), Number(month.slice(5, 7)));
  return Array.from({ length }, (unused, index) => `${month}-${String(index + 1).padStart(2, "0")}`);
}, "monthDays");
const barOf = /* @__PURE__ */ __name((step, days, zone) => spanOf(step.start, step.end, step.expected, days, zone), "barOf");
const trailBarOf = /* @__PURE__ */ __name((trail, days, zone) => spanOf(trail.first, trail.last, false, days, zone, trail.first > 0 && trail.last <= 0 ? openTrailEnd(trail, zone) : null), "trailBarOf");
const spanOf = /* @__PURE__ */ __name((begins, ends, expected, days, zone, until = null) => {
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
  const open = start !== null && end === null;
  if (open) {
    end = openEnd(String(start), until);
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
  return placedOf(start, end, open, expected, days);
}, "spanOf");
const placedOf = /* @__PURE__ */ __name((start, end, open, expected, days) => {
  const first = days[0];
  const last = days[days.length - 1];
  const before = start < first;
  const after = end === null || end > last;
  const from = before ? 0 : days.indexOf(start);
  return {
    from,
    to: Math.max(from, after ? days.length - 1 : days.indexOf(String(end))),
    before,
    after,
    open,
    milestone: !open && expected && end !== null && end <= last
  };
}, "placedOf");
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
const withTrail = /* @__PURE__ */ __name((href, filter, zoom = "day") => {
  const url = new URL(href, window.location.href);
  url.searchParams.delete(TRAIL_PARAM);
  url.searchParams.delete(MINE_PARAM);
  url.searchParams.delete(ZOOM_PARAM);
  if (filter && filter.trail > 0) {
    url.searchParams.set(TRAIL_PARAM, String(filter.trail));
  }
  if (filter && filter.mine) {
    url.searchParams.set(MINE_PARAM, "1");
  }
  if (filter && zoom !== "day") {
    url.searchParams.set(ZOOM_PARAM, zoom);
  }
  return url.toString();
}, "withTrail");
const zoomOf = /* @__PURE__ */ __name((value) => value === "week" || value === "month" ? value : "day", "zoomOf");
const readZoom = /* @__PURE__ */ __name((href) => zoomOf(new URL(href, window.location.href).searchParams.get(ZOOM_PARAM)), "readZoom");
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
const OPENING_MONTHS = 2;
const BATCH_MONTHS = 3;
const LOADED_CAP = 6;
const MONTH_CAP = 12;
const capOf = /* @__PURE__ */ __name((zoom) => zoom === "month" ? MONTH_CAP : LOADED_CAP, "capOf");
const batchOf = /* @__PURE__ */ __name((zoom, held) => zoom === "month" ? Math.max(1, Math.min(BATCH_MONTHS, capOf(zoom) - held)) : 1, "batchOf");
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
const extendLoaded = /* @__PURE__ */ __name((loaded, answer, cap = capOf("day")) => {
  const added = monthsOf(answer.from, answer.to);
  const months = [...loaded.months];
  const answers = [...loaded.answers];
  let shift = 0;
  if (added[0] === monthAfter(months[months.length - 1], 1)) {
    months.push(...added);
    answers.push(...added.map(() => answer));
    while (months.length > cap) {
      shift -= monthDays(String(months.shift())).length;
      answers.shift();
    }
  } else if (added[added.length - 1] === monthAfter(months[0], -1)) {
    months.unshift(...added);
    answers.unshift(...added.map(() => answer));
    shift = added.reduce((days, month) => days + monthDays(month).length, 0);
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
const WEEK_MONTH = { month: "short" };
const weekdayOf = /* @__PURE__ */ __name((iso) => new Date(Date.UTC(Number(iso.slice(0, 4)), Number(iso.slice(5, 7)) - 1, Number(iso.slice(8, 10)))).getUTCDay(), "weekdayOf");
const weekCells = /* @__PURE__ */ __name((days, firstday) => {
  const cells = [];
  days.forEach((iso, index) => {
    if (index === 0 || weekdayOf(iso) === firstday) {
      cells.push({ at: index, length: 1, first: iso, last: iso });
    } else {
      const cell = cells[cells.length - 1];
      cell.length++;
      cell.last = iso;
    }
  });
  return cells;
}, "weekCells");
const weekLabel = /* @__PURE__ */ __name((cell, language) => {
  const first = Number(cell.first.slice(8, 10));
  const last = Number(cell.last.slice(8, 10));
  const kicker = say(cell.first, WEEK_MONTH, language);
  if (cell.length <= 1) {
    return { kicker, days: String(first) };
  }
  const crosses = cell.first.slice(0, 7) !== cell.last.slice(0, 7);
  return { kicker, days: crosses ? `${first}\u2013${last} ${say(cell.last, WEEK_MONTH, language)}` : `${first}\u2013${last}` };
}, "weekLabel");
const WEEK_LABEL_LEAST = 2.5;
const weekLabelled = /* @__PURE__ */ __name((cell, zoom) => zoom === "week" && cell.length * dayWidth(zoom) >= WEEK_LABEL_LEAST, "weekLabelled");
const SHORT_DATE = { day: "numeric", month: "short", year: "numeric" };
const separates = /* @__PURE__ */ __name((parts, index) => parts[index - 1]?.source === "startRange" && parts[index + 1]?.source === "endRange", "separates");
const compactParts = /* @__PURE__ */ __name((parts) => parts.map((part, index) => {
  if (part.type !== "literal") {
    return part.value;
  }
  if (separates(parts, index)) {
    const tight = parts[index - 1].type === "day" && parts[index + 1].type === "day" && part.value.includes("\u2013");
    return tight ? "\u2013" : part.value;
  }
  return /\p{L}/u.test(part.value) ? " " : part.value;
}).join("").replace(/\s+/g, " ").trim(), "compactParts");
const shortDate = /* @__PURE__ */ __name((iso, language) => {
  try {
    return compactParts(new Intl.DateTimeFormat(language, { ...SHORT_DATE, timeZone: "UTC" }).formatToParts(/* @__PURE__ */ new Date(`${iso}T00:00:00Z`)));
  } catch (error) {
    return say(iso, SHORT_DATE, language);
  }
}, "shortDate");
const datesOf = /* @__PURE__ */ __name((start, end, zone, language) => {
  const from = start > 0 ? zone.day(start) : "";
  const to = end > 0 ? zone.day(end) : "";
  if (from !== "" && to !== "") {
    const last = to < from ? from : to;
    try {
      return { kind: "range", text: compactParts(new Intl.DateTimeFormat(language, { ...SHORT_DATE, timeZone: "UTC" }).formatRangeToParts(/* @__PURE__ */ new Date(`${from}T00:00:00Z`), /* @__PURE__ */ new Date(`${last}T00:00:00Z`))) };
    } catch (error) {
      return { kind: "range", text: from === last ? shortDate(from, language) : `${shortDate(from, language)} \u2013 ${shortDate(last, language)}` };
    }
  }
  if (from !== "") {
    return { kind: "from", text: shortDate(from, language) };
  }
  return to !== "" ? { kind: "until", text: shortDate(to, language) } : { kind: "none", text: "" };
}, "datesOf");
const TRACK = { label: 21.25, day: 3, week: 1, month: 0.25, row: 3.5, bar: 1.5 };
const NAMED_BAR = 3.5;
const dayWidth = /* @__PURE__ */ __name((zoom) => TRACK[zoom], "dayWidth");
const namedBar = /* @__PURE__ */ __name((bar, zoom) => {
  const least = zoom === "day" ? TRACK.day : NAMED_BAR;
  return (bar.to - bar.from + 1) * dayWidth(zoom) >= least;
}, "namedBar");
const CHIP_GAP = 0.375;
const chipLeft = /* @__PURE__ */ __name((bar, count, zoom) => bar.open && bar.to + 1 < count ? Math.round(((bar.to + 1) * dayWidth(zoom) + CHIP_GAP) * 1e4) / 1e4 : null, "chipLeft");
const zoomedLeft = /* @__PURE__ */ __name((left, from, to) => Math.max(0, Math.round(left * dayWidth(to) / dayWidth(from))), "zoomedLeft");
const trackOf = /* @__PURE__ */ __name((left, total, width, days, day = TRACK.day) => {
  const pitch = days > 0 ? total / (days + TRACK.label / day) : 0;
  const max = Math.max(0, total - width);
  if (pitch <= 0 || max < 1) {
    return { pitch, view: Math.max(0, width), max: 0, left: 0, start: 0, span: 1, back: false, ahead: false };
  }
  const view = Math.max(0, width - pitch * TRACK.label / day);
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
const LINK = { line: 0.125, stub: 0.625, tight: 0.1875, least: 0.125, detour: 0.5625, head: 0.375, half: 0.25, dot: 0.375 };
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
  return { from, to: step.end <= 0 ? from + OPEN_DAYS - 1 : Math.max(from, daysBetween(first, zone.day(step.end))) };
}, "reachOf");
const clipped = /* @__PURE__ */ __name((piece, width) => {
  if (piece.kind !== "horizontal") {
    return piece.left >= 0 && piece.left + piece.width <= width ? piece : null;
  }
  const left = Math.max(0, piece.left);
  const right = Math.min(width, piece.left + piece.width);
  return right > left ? { ...piece, left, width: right - left } : null;
}, "clipped");
const pathOf = /* @__PURE__ */ __name((a, b, row, day) => {
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
    const elbow = start2 + Math.max(LINK.tight, Math.min(LINK.stub, (end2 - start2) / 2));
    return [
      across(amid, start2, elbow),
      down(elbow, amid - LINK.line / 2, bmid + LINK.line / 2),
      across(bmid, elbow, Math.max(elbow, end2 - LINK.head)),
      right(Math.max(end2, elbow + LINK.head), bmid)
    ];
  }
  if (b.from >= a.from) {
    const x = b.from * day + Math.min(LINK.stub, Math.max(LINK.least, day / 2));
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
  const turn = end - Math.max(LINK.tight, Math.min(LINK.detour, (start - end) / 2, end - LINK.line / 2));
  return [
    across(amid, turn, start),
    down(turn, amid - LINK.line / 2, bmid + LINK.line / 2),
    across(bmid, turn, Math.max(turn, end - LINK.head)),
    right(Math.max(end, turn + LINK.head), bmid)
  ];
}, "pathOf");
const tidy = /* @__PURE__ */ __name((value) => Math.round(value * 1e4) / 1e4, "tidy");
const linksOf = /* @__PURE__ */ __name((steps, days, zone, shown, zoom = "day") => {
  if (!shown || days.length === 0) {
    return [];
  }
  const day = dayWidth(zoom);
  const width = days.length * day;
  const reaches = steps.map((step) => reachOf(step, days[0], zone));
  const pieces = [];
  reaches.forEach((reach, index) => {
    const next = index + 1 < reaches.length ? reaches[index + 1] : null;
    if (reach === null || next === null) {
      return;
    }
    pathOf(reach, next, index, day).forEach((piece) => {
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
const arrivedOf = /* @__PURE__ */ __name((was, ids) => {
  const fresh = ids.filter((id) => !(id in was));
  if (fresh.length === 0) {
    return was;
  }
  const alone = ids.length <= 1;
  const next = { ...was };
  fresh.forEach((id) => {
    next[id] = alone;
  });
  return next;
}, "arrivedOf");
const openOf = /* @__PURE__ */ __name((id, chosen, arrived) => chosen[id] ?? arrived[id] ?? true, "openOf");
const foldedAll = /* @__PURE__ */ __name((chosen, ids, allopen) => {
  const next = { ...chosen };
  ids.forEach((id) => {
    next[id] = !allopen;
  });
  return next;
}, "foldedAll");
const freshFolds = /* @__PURE__ */ __name((chosen, answer) => {
  const trails = answer ? answer.trails : [];
  if (trails.length !== 1 || !(trails[0].id in chosen)) {
    return chosen;
  }
  const next = { ...chosen };
  delete next[trails[0].id];
  return next;
}, "freshFolds");
export {
  BATCH_MONTHS,
  CHIP_GAP,
  LOADED_CAP,
  LOAD_AHEAD,
  MINE_PARAM,
  MONTH_CAP,
  NAMED_BAR,
  OPENING_MONTHS,
  OPEN_DAYS,
  SKELETON_DAYS,
  STEP_INSET,
  TRACK,
  TRAIL_PARAM,
  WEEK_LABEL_LEAST,
  ZOOMS,
  ZOOM_PARAM,
  arrivedOf,
  barOf,
  batchOf,
  capOf,
  chipLeft,
  datesOf,
  dayLeft,
  dayWidth,
  emptyTrails,
  extendLoaded,
  filterOf,
  foldedAll,
  freshFolds,
  keptLeft,
  linksOf,
  loadedOf,
  meets,
  mergedOf,
  monthDays,
  monthStep,
  monthsOf,
  namedBar,
  nearEnd,
  openOf,
  openingLeft,
  pageLeft,
  rangeLabel,
  reachOf,
  readTrail,
  readZoom,
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
  weekCells,
  weekLabel,
  weekLabelled,
  windowDays,
  withTrail,
  zoomOf,
  zoomedLeft
};
//# sourceMappingURL=trails.dev.js.map
