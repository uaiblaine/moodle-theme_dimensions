var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The week view, pure: the span a page shows, the week's days, its layout, its "now" line and its keyboard's moves.
 *
 * - A SPAN is what a page shows: a month (its grid of whole weeks, calendar/days.ts), a week (seven days from the
 *   viewer's first weekday) or, on a phone only, a day. The view, the week and the day view's
 *   day travel in the address as navigation, never in the saved state: `view=week&week=<first day>` and
 *   `view=day&day=<day>` (local\calendar\page::PARAM_VIEW, PARAM_WEEK and PARAM_DAY), the month view writing none.
 * - A DAY SPAN IS A PHONE'S: on a frame at or above MonthGrid PHONE it is drawn, and asked for, as its month with the
 *   day's drawer open ({@see deskOf()}, {@see gridOf()}); the switch offers the day only on a phone ({@see viewsOf()}).
 * - The week's LAYOUT is the month's and the drawer's, reused: the all-day strip is layout.ts weekLayout() over the
 *   items that are bars or fill whole days (overlap.ts isAllDay()), every slot shown; each day's hour items are
 *   overlap.ts dayPlan()'s, in the drawer's columns; and the hour window is overlap.ts hourWindow() over the whole
 *   week's hour items at once: the band the hour body's scroll box opens on, which
 *   draws the whole day.
 * - Days are calendar days counted in UTC (daterange/calendar.ts): a daylight-saving change moves no day, and an
 *   item's row is its wall-clock time in the viewer's timezone (days.ts zone().minutes()).
 *
 * @module     theme_dimensions/calendar/weeks
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { WEEK, parse, say, shift, shiftMonth, utc } from "../daterange/calendar";
import { gridWeeks } from "./days";
import { weekLayout } from "./layout";
import { dayPlan, hourWindow, isAllDay, itemKey } from "./overlap";
import { DAY_PARAM } from "./model";
import { fill } from "./strings";
const VIEW_PARAM = "view";
const WEEK_PARAM = "week";
const MONTH = { month: "long", year: "numeric" };
const FROM_DAY = { day: "numeric", month: "long" };
const TO_DAY = { day: "numeric", month: "long", year: "numeric" };
const LONG_DAY = { weekday: "long", day: "numeric", month: "long", year: "numeric" };
const weekStart = /* @__PURE__ */ __name((iso, firstday) => {
  const parts = parse(iso);
  if (!parts) {
    return "";
  }
  return shift(iso, -((utc(parts).getUTCDay() - firstday + WEEK) % WEEK));
}, "weekStart");
const weekDays = /* @__PURE__ */ __name((start) => Array.from({ length: WEEK }, (unused, index) => shift(start, index)), "weekDays");
const spanOf = /* @__PURE__ */ __name((view, iso, firstday) => {
  if (view === "day") {
    return { view: "day", day: iso };
  }
  if (view === "trail") {
    return { view: "trail", month: iso.slice(0, 7) };
  }
  return view === "week" ? { view: "week", week: weekStart(iso, firstday) } : { view: "month", month: iso.slice(0, 7) };
}, "spanOf");
const spanDays = /* @__PURE__ */ __name((span, firstday) => {
  if (span.view === "day") {
    return [span.day];
  }
  return span.view === "week" ? weekDays(span.week) : gridWeeks(span.month, firstday).flat();
}, "spanDays");
const spanKey = /* @__PURE__ */ __name((span) => {
  if (span.view === "day") {
    return `day:${span.day}`;
  }
  if (span.view === "trail") {
    return `trail:${span.month}`;
  }
  return span.view === "week" ? `week:${span.week}` : `month:${span.month}`;
}, "spanKey");
const monthOfSpan = /* @__PURE__ */ __name((span, selected) => {
  if (span.view === "month" || span.view === "trail") {
    return span.month;
  }
  if (span.view === "day") {
    return span.day.slice(0, 7);
  }
  return weekDays(span.week).includes(selected) ? selected.slice(0, 7) : span.week.slice(0, 7);
}, "monthOfSpan");
const openingOf = /* @__PURE__ */ __name((span, today) => {
  if (span.view === "month" || span.view === "trail") {
    return today.startsWith(span.month) ? today : `${span.month}-01`;
  }
  if (span.view === "day") {
    return span.day;
  }
  return weekDays(span.week).includes(today) ? today : span.week;
}, "openingOf");
const stepSpan = /* @__PURE__ */ __name((span, iso, offset) => {
  if (span.view === "day") {
    return shift(span.day, offset);
  }
  return span.view === "week" ? shift(iso, offset * WEEK) : shiftMonth(iso, offset);
}, "stepSpan");
const inSpan = /* @__PURE__ */ __name((href, span) => {
  const url = new URL(href, window.location.href);
  const rest = [...url.searchParams.entries()].filter(([name]) => name !== "month" && name !== VIEW_PARAM && name !== WEEK_PARAM);
  url.search = "";
  if (span.view === "day") {
    url.searchParams.append(VIEW_PARAM, "day");
    url.searchParams.append(DAY_PARAM, span.day);
    rest.filter(([name]) => name !== DAY_PARAM).forEach(([name, value]) => url.searchParams.append(name, value));
    return url.toString();
  }
  if (span.view === "week") {
    url.searchParams.append(VIEW_PARAM, "week");
    url.searchParams.append(WEEK_PARAM, span.week);
  } else if (span.view === "trail") {
    url.searchParams.append(VIEW_PARAM, "trail");
    url.searchParams.append("month", span.month);
  } else {
    url.searchParams.append("month", span.month);
  }
  rest.forEach(([name, value]) => url.searchParams.append(name, value));
  return url.toString();
}, "inSpan");
const viewDay = /* @__PURE__ */ __name((day, month, today) => {
  const year = Number(day.slice(0, 4));
  return parse(day) !== null && year >= 1900 && year <= 2999 ? day : openingOf({ view: "month", month }, today);
}, "viewDay");
const readSpan = /* @__PURE__ */ __name((href, firstday, month, today, trails = false) => {
  const params = new URL(href, window.location.href).searchParams;
  if (params.get(VIEW_PARAM) === "day") {
    return { view: "day", day: viewDay(String(params.get(DAY_PARAM) || ""), month, today) };
  }
  if (params.get(VIEW_PARAM) === "trail" && trails) {
    return { view: "trail", month };
  }
  if (params.get(VIEW_PARAM) !== "week") {
    return { view: "month", month };
  }
  const week = String(params.get(WEEK_PARAM) || "");
  const year = Number(week.slice(0, 4));
  const valid = parse(week) !== null && year >= 1900 && year <= 2999;
  return { view: "week", week: weekStart(valid ? week : openingOf({ view: "month", month }, today), firstday) };
}, "readSpan");
const weekCaption = /* @__PURE__ */ __name((days, strings, language) => fill(strings.calendar_span, { from: say(days[0], FROM_DAY, language), to: say(days[days.length - 1], TO_DAY, language) }), "weekCaption");
const spanLabels = /* @__PURE__ */ __name((span, days, strings, language) => {
  if (span.view === "day") {
    return {
      caption: say(span.day, LONG_DAY, language),
      previous: strings.calendar_prevday,
      next: strings.calendar_nextday
    };
  }
  if (span.view === "week") {
    return {
      caption: weekCaption(days, strings, language),
      previous: strings.calendar_prevweek,
      next: strings.calendar_nextweek
    };
  }
  return {
    caption: say(`${span.month}-01`, MONTH, language),
    previous: strings.calendar_prevmonth,
    next: strings.calendar_nextmonth
  };
}, "spanLabels");
const dayIn = /* @__PURE__ */ __name((iso, days) => iso !== null && days.includes(iso) ? iso : null, "dayIn");
const viewsOf = /* @__PURE__ */ __name((phone) => phone ? ["month", "week", "day"] : ["month", "week"], "viewsOf");
const gridOf = /* @__PURE__ */ __name((span, phone) => span.view === "day" && !phone ? "month" : span.view, "gridOf");
const deskOf = /* @__PURE__ */ __name((span, phone, firstday) => {
  if (span.view === "day" && !phone) {
    return { span: spanOf("month", span.day, firstday), open: span.day };
  }
  return { span, open: null };
}, "deskOf");
const openOf = /* @__PURE__ */ __name((span, day, days) => span.view === "day" ? null : dayIn(day, days), "openOf");
const drawerOf = /* @__PURE__ */ __name((span, open, days, phone) => {
  if (span.view === "day") {
    return phone ? null : span.day;
  }
  return span.view === "trail" ? null : dayIn(open, days);
}, "drawerOf");
const selectedIn = /* @__PURE__ */ __name((selected, span, days, today) => days.includes(selected) ? selected : openingOf(span, today), "selectedIn");
const weekPlan = /* @__PURE__ */ __name((days, placed, zone) => {
  const strip = placed.filter((entry) => entry.bar || isAllDay(entry.item, zone)).map((entry) => entry.bar ? entry : { ...entry, bar: true });
  const columns = days.map((iso) => dayPlan(iso, placed, zone).hours);
  const timed = columns.flat();
  return {
    strip: weekLayout(days, strip, Math.max(strip.length, 1)),
    columns,
    window: timed.length ? hourWindow(timed, zone) : null
  };
}, "weekPlan");
const hourBox = /* @__PURE__ */ __name((laid, zone, window2) => {
  const from = window2.from * 60;
  const total = (window2.to - window2.from) * 60;
  const begins = zone.minutes(laid.entry.item.timestart);
  const start = Math.max(from, begins);
  const end = Math.min(window2.to * 60, begins + Math.max(laid.entry.item.timeduration / 60, 0));
  return {
    top: (start - from) / total,
    height: Math.max(end - start, 0) / total,
    left: laid.column / laid.columns,
    width: 1 / laid.columns
  };
}, "hourBox");
const nowLine = /* @__PURE__ */ __name((days, now, zone, window2) => {
  if (window2 === null) {
    return null;
  }
  const column = days.indexOf(zone.day(now));
  const minutes = zone.minutes(now);
  if (column < 0 || minutes < window2.from * 60 || minutes >= window2.to * 60) {
    return null;
  }
  return { column, share: (minutes - window2.from * 60) / ((window2.to - window2.from) * 60) };
}, "nowLine");
const weekStops = /* @__PURE__ */ __name((plan, zone) => plan.columns.map((laid, column) => [
  ...plan.strip.bars.filter((segment) => segment.start <= column && column <= segment.end).sort((a, b) => a.slot - b.slot).map((segment) => ({ key: itemKey(segment.placed.item), minutes: -1 })),
  ...laid.map((one) => ({ key: itemKey(one.entry.item), minutes: zone.minutes(one.entry.item.timestart) }))
]), "weekStops");
const stepWeekFrom = /* @__PURE__ */ __name((key, iso, days) => {
  switch (key) {
    case "ArrowLeft":
      return shift(iso, -1);
    case "ArrowRight":
      return shift(iso, 1);
    case "Home":
      return days[0];
    case "End":
      return days[days.length - 1];
    case "PageUp":
      return shift(iso, -WEEK);
    case "PageDown":
      return shift(iso, WEEK);
    default:
      return null;
  }
}, "stepWeekFrom");
const moveInWeek = /* @__PURE__ */ __name((key, at, stops) => {
  const items = stops[at.column] || [];
  if (at.index < 0) {
    return (key === "ArrowDown" || key === "F2") && items.length ? { column: at.column, index: 0 } : null;
  }
  switch (key) {
    case "ArrowDown":
      return at.index + 1 < items.length ? { column: at.column, index: at.index + 1 } : null;
    case "ArrowUp":
      return { column: at.column, index: at.index - 1 };
    case "Home":
      return { column: at.column, index: 0 };
    case "End":
      return { column: at.column, index: items.length - 1 };
    case "Escape":
      return { column: at.column, index: -1 };
    case "ArrowLeft":
    case "ArrowRight": {
      const column = at.column + (key === "ArrowLeft" ? -1 : 1);
      if (column < 0 || column >= stops.length) {
        return null;
      }
      const minutes = items[at.index]?.minutes ?? -1;
      let best = -1;
      stops[column].forEach((stop, index) => {
        if (best < 0 || Math.abs(stop.minutes - minutes) < Math.abs(stops[column][best].minutes - minutes)) {
          best = index;
        }
      });
      return { column, index: best };
    }
    default:
      return null;
  }
}, "moveInWeek");
export {
  VIEW_PARAM,
  WEEK_PARAM,
  dayIn,
  deskOf,
  drawerOf,
  gridOf,
  hourBox,
  inSpan,
  monthOfSpan,
  moveInWeek,
  nowLine,
  openOf,
  openingOf,
  readSpan,
  selectedIn,
  spanDays,
  spanKey,
  spanLabels,
  spanOf,
  stepSpan,
  stepWeekFrom,
  viewDay,
  viewsOf,
  weekCaption,
  weekDays,
  weekPlan,
  weekStart,
  weekStops
};
//# sourceMappingURL=weeks.dev.js.map
