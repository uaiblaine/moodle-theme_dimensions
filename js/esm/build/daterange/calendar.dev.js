var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The calendar arithmetic of the showcase's period control, with no timezone in it.
 *
 * Every boundary of the period filter is resolved by PHP in the site's timezone; what the client
 * computes is the calendar itself - the weekday of a date, the length of a month, the day either
 * side of one - on the {y, m, d} of an ISO string through Date.UTC. UTC has no offset and no daylight
 * saving, so a day added is a day added whatever the reader's own timezone; a local-time Date would
 * shift a calendar square by that offset. The browser's clock is never read: the day the site calls
 * today arrives from the server.
 *
 * {@see period()} mirrors criteria's reading of a period; both shells read the period of an address through it.
 *
 * @module     theme_dimensions/daterange/calendar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const WEEK = 7;
const NAME_WEEK = "2026-01-04";
const monthLength = /* @__PURE__ */ __name((y, m) => new Date(Date.UTC(y, m, 0)).getUTCDate(), "monthLength");
const parse = /* @__PURE__ */ __name((iso) => {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || ""));
  if (!match) {
    return null;
  }
  const parts = { y: Number(match[1]), m: Number(match[2]), d: Number(match[3]) };
  if (parts.m < 1 || parts.m > 12 || parts.d < 1 || parts.d > monthLength(parts.y, parts.m)) {
    return null;
  }
  return parts;
}, "parse");
const format = /* @__PURE__ */ __name((parts) => String(parts.y).padStart(4, "0") + "-" + String(parts.m).padStart(2, "0") + "-" + String(parts.d).padStart(2, "0"), "format");
const utc = /* @__PURE__ */ __name((parts) => new Date(Date.UTC(parts.y, parts.m - 1, parts.d)), "utc");
const shift = /* @__PURE__ */ __name((iso, days) => {
  const parts = parse(iso);
  if (!parts) {
    return "";
  }
  const moved = utc(parts);
  moved.setUTCDate(moved.getUTCDate() + days);
  return format({ y: moved.getUTCFullYear(), m: moved.getUTCMonth() + 1, d: moved.getUTCDate() });
}, "shift");
const shiftMonth = /* @__PURE__ */ __name((iso, months) => {
  const parts = parse(iso);
  if (!parts) {
    return "";
  }
  const moved = utc({ y: parts.y, m: parts.m, d: 1 });
  moved.setUTCMonth(moved.getUTCMonth() + months);
  const y = moved.getUTCFullYear();
  const m = moved.getUTCMonth() + 1;
  return format({ y, m, d: Math.min(parts.d, monthLength(y, m)) });
}, "shiftMonth");
const period = /* @__PURE__ */ __name((start, end) => {
  const first = parse(start) ? String(start) : "";
  const last = parse(end) ? String(end) : "";
  if (first !== "" && last !== "" && last < first) {
    return { from: last, to: first };
  }
  return { from: first, to: last };
}, "period");
const FORMATTERS = /* @__PURE__ */ new Map();
const formatter = /* @__PURE__ */ __name((options, language) => {
  const key = JSON.stringify([language ?? null, options]);
  let made = FORMATTERS.get(key);
  if (!made) {
    made = new Intl.DateTimeFormat(language, { timeZone: "UTC", ...options });
    FORMATTERS.set(key, made);
  }
  return made;
}, "formatter");
const say = /* @__PURE__ */ __name((iso, options, language) => {
  const parts = parse(iso);
  if (!parts) {
    return "";
  }
  try {
    return formatter(options, language).format(utc(parts));
  } catch (error) {
    return iso;
  }
}, "say");
const monthOf = /* @__PURE__ */ __name((iso) => {
  const parts = parse(iso);
  return { y: parts.y, m: parts.m };
}, "monthOf");
const firstOf = /* @__PURE__ */ __name((month) => format({ y: month.y, m: month.m, d: 1 }), "firstOf");
const monthAt = /* @__PURE__ */ __name((cursor, offset) => monthOf(shiftMonth(firstOf(cursor), offset)), "monthAt");
const onScreen = /* @__PURE__ */ __name((cursor, count, iso) => {
  const last = monthAt(cursor, count - 1);
  return iso >= firstOf(cursor) && iso <= format({ y: last.y, m: last.m, d: monthLength(last.y, last.m) });
}, "onScreen");
const bringIntoView = /* @__PURE__ */ __name((cursor, count, iso) => {
  if (!parse(iso) || onScreen(cursor, count, iso)) {
    return cursor;
  }
  return iso < firstOf(cursor) ? monthOf(iso) : monthAt(monthOf(iso), 1 - count);
}, "bringIntoView");
const weekdays = /* @__PURE__ */ __name((firstday) => Array.from({ length: WEEK }, (unused, index) => shift(NAME_WEEK, (firstday + index) % WEEK)), "weekdays");
const weeks = /* @__PURE__ */ __name((month, firstday) => {
  const lead = (utc({ y: month.y, m: month.m, d: 1 }).getUTCDay() - firstday + WEEK) % WEEK;
  const length = monthLength(month.y, month.m);
  const slots = Math.ceil((lead + length) / WEEK) * WEEK;
  const rows = [];
  for (let slot = 0; slot < slots; slot++) {
    if (slot % WEEK === 0) {
      rows.push([]);
    }
    const d = slot - lead + 1;
    rows[rows.length - 1].push(d >= 1 && d <= length ? format({ y: month.y, m: month.m, d }) : "");
  }
  return rows;
}, "weeks");
export {
  WEEK,
  bringIntoView,
  firstOf,
  format,
  monthAt,
  monthLength,
  monthOf,
  onScreen,
  parse,
  period,
  say,
  shift,
  shiftMonth,
  utc,
  weekdays,
  weeks
};
//# sourceMappingURL=calendar.dev.js.map
