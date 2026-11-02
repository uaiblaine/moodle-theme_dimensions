var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * What the page may offer to edit, and the instants core's calendar services are handed: pure, no request.
 *
 * Every decision here is core's, read from the answer (`editing`, local\calendar\editing::entries()): an item is
 * edited, deleted or dragged only when core's month view would. What is computed here is the guard of a drop (the
 * limits core gave, as core's month_view_drag_drop checks them before it asks) and the instants core's form and
 * core_calendar_update_event_start_day take, which are timestamps: the day the reader picked, in the VIEWER's
 * timezone (Intl's zone data, as days.ts reads instants), never the browser's.
 *
 * @module     theme_dimensions/calendar/moves
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const MOVE_MINUTE = 12 * 60;
const editsOf = /* @__PURE__ */ __name((answer) => new Map((answer.editing || []).map((entry) => [entry.id, entry])), "editsOf");
const editOf = /* @__PURE__ */ __name((edits, item) => item.source === "core" ? edits.get(item.id) || null : null, "editOf");
const dropRefusal = /* @__PURE__ */ __name((edit, day) => {
  if (edit.minday !== "" && day < edit.minday) {
    return "min";
  }
  if (edit.maxday !== "" && day > edit.maxday) {
    return "max";
  }
  return null;
}, "dropRefusal");
const clockIn = /* @__PURE__ */ __name((timezone) => {
  const options = {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hourCycle: "h23"
  };
  try {
    return new Intl.DateTimeFormat("en-GB", { ...options, timeZone: timezone });
  } catch (error) {
    return new Intl.DateTimeFormat("en-GB", { ...options, timeZone: "UTC" });
  }
}, "clockIn");
const wallOf = /* @__PURE__ */ __name((clock, at) => {
  const parts = Object.fromEntries(clock.formatToParts(new Date(at * 1e3)).map((part) => [part.type, part.value]));
  return Date.UTC(
    Number(parts.year),
    Number(parts.month) - 1,
    Number(parts.day),
    Number(parts.hour) % 24,
    Number(parts.minute),
    Number(parts.second)
  ) / 1e3;
}, "wallOf");
const instantOf = /* @__PURE__ */ __name((iso, minute, timezone) => {
  const clock = clockIn(timezone);
  const wall = Date.UTC(
    Number(iso.slice(0, 4)),
    Number(iso.slice(5, 7)) - 1,
    Number(iso.slice(8, 10)),
    Math.floor(minute / 60),
    minute % 60
  ) / 1e3;
  const first = wall - (wallOf(clock, wall) - wall);
  return wall - (wallOf(clock, first) - first);
}, "instantOf");
const toolbarStart = /* @__PURE__ */ __name((own, today, now, zone, timezone) => own.includes(today) || !own.length ? null : instantOf(own[0], zone.minutes(now), timezone), "toolbarStart");
const dayStart = /* @__PURE__ */ __name((day, now, zone, timezone) => instantOf(day, zone.minutes(now), timezone), "dayStart");
const hourStart = /* @__PURE__ */ __name((day, hour, timezone) => instantOf(day, hour * 60, timezone), "hourStart");
const createStart = /* @__PURE__ */ __name((day, hour, now, zone, timezone) => hour === null ? dayStart(day, now, zone, timezone) : hourStart(day, hour, timezone), "createStart");
export {
  MOVE_MINUTE,
  createStart,
  dayStart,
  dropRefusal,
  editOf,
  editsOf,
  hourStart,
  instantOf,
  toolbarStart
};
//# sourceMappingURL=moves.dev.js.map
