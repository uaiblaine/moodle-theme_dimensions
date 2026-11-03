var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * One day's items, pure: the all-day strip, the hour column in columns, and who overlaps whom
 * (docs/calendar/PROPOSAL.md section 13.2).
 *
 * - The ALL-DAY strip holds every item touching the day that is a bar of the month grid (it spans more than one day,
 *   or it is an event course) or that fills whole days ({@see isAllDay()}).
 * - The HOUR column holds the day's other items. Those with a duration are laid out in columns: sorted by start,
 *   then longer first, an item takes the first column whose last item ended at or before its start, and a cluster
 *   of items that overlap one another (directly or through each other) shares one column count. A point item (a
 *   deadline, duration 0) sits in the column at its time, alone in its own cluster.
 * - Two items OVERLAP when both have a duration and each starts before the other ends: touching ends do not, a
 *   point item never does, and an all-day or multi-day item never counts.
 * - The hour column draws the whole day in a scroll box (R1c, PROPOSAL.md section 30.1, reversing C3's windowed column):
 *   the busy band (hourWindow()) or the working hours (EMPTY_WINDOW) is where the box starts ({@see boxTopOf()}),
 *   and a fade marks each end it can still scroll to ({@see edgesOf()}).
 *
 * @module     theme_dimensions/calendar/overlap
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const itemKey = /* @__PURE__ */ __name((item) => `${item.source}-${item.id}`, "itemKey");
const isAllDay = /* @__PURE__ */ __name((item, zone) => {
  if (item.timeduration <= 0) {
    return false;
  }
  const end = item.timestart + item.timeduration;
  return zone.minutes(item.timestart) === 0 && zone.minutes(end) === 0 && zone.day(item.timestart) !== zone.day(end);
}, "isAllDay");
const intersects = /* @__PURE__ */ __name((a, b) => a.timeduration > 0 && b.timeduration > 0 && a.timestart < b.timestart + b.timeduration && b.timestart < a.timestart + a.timeduration, "intersects");
const byStart = /* @__PURE__ */ __name((a, b) => a.item.timestart - b.item.timestart || b.item.timeduration - a.item.timeduration || a.item.name.localeCompare(b.item.name) || a.item.id - b.item.id, "byStart");
const columns = /* @__PURE__ */ __name((entries) => {
  const sorted = [...entries].sort(byStart);
  const laid = [];
  let cluster = [];
  let ends = [];
  let clusterend = -Infinity;
  const close = /* @__PURE__ */ __name(() => {
    cluster.forEach((entry) => {
      entry.columns = ends.length;
    });
    cluster = [];
    ends = [];
  }, "close");
  sorted.forEach((entry) => {
    if (entry.item.timeduration <= 0) {
      laid.push({ entry, column: 0, columns: 1, overlaps: [] });
      return;
    }
    const start = entry.item.timestart;
    const end = start + entry.item.timeduration;
    if (start >= clusterend) {
      close();
    }
    let column = ends.findIndex((last) => last <= start);
    if (column < 0) {
      column = ends.length;
      ends.push(end);
    } else {
      ends[column] = end;
    }
    const placed = {
      entry,
      column,
      columns: 1,
      overlaps: sorted.filter((other) => other !== entry && intersects(entry.item, other.item))
    };
    cluster.push(placed);
    laid.push(placed);
    clusterend = Math.max(clusterend, end);
  });
  close();
  return laid;
}, "columns");
const dayPlan = /* @__PURE__ */ __name((iso, placed, zone) => {
  const allday = [];
  const hours = [];
  placed.forEach((entry) => {
    if (entry.first > iso || entry.last < iso) {
      return;
    }
    if (entry.bar || isAllDay(entry.item, zone)) {
      allday.push(entry);
    } else {
      hours.push(entry);
    }
  });
  allday.sort(byStart);
  return { allday, hours: columns(hours) };
}, "dayPlan");
const MIN_WINDOW = 4;
const FULL_DAY = { from: 0, to: 24 };
const EMPTY_WINDOW = { from: 8, to: 18 };
const hourWindow = /* @__PURE__ */ __name((laid, zone) => {
  if (!laid.length) {
    return { from: 0, to: 24 };
  }
  let earliest = 1440;
  let latest = 0;
  laid.forEach((one) => {
    const start = zone.minutes(one.entry.item.timestart);
    earliest = Math.min(earliest, start);
    latest = Math.max(latest, Math.min(1440, start + Math.max(one.entry.item.timeduration / 60, 0)));
  });
  let from = Math.max(0, Math.floor(earliest / 60) - 1);
  let to = Math.min(24, Math.ceil(latest / 60) + 1);
  if (to - from < MIN_WINDOW) {
    to = Math.min(24, from + MIN_WINDOW);
    from = Math.max(0, to - MIN_WINDOW);
  }
  return { from, to };
}, "hourWindow");
const overlapsOf = /* @__PURE__ */ __name((entry, placed, zone) => {
  const key = itemKey(entry.item);
  const laid = dayPlan(entry.first, placed, zone).hours.find((other) => itemKey(other.entry.item) === key);
  return laid ? laid.overlaps : [];
}, "overlapsOf");
const boxTopOf = /* @__PURE__ */ __name((band, busy, row, viewport) => {
  if (!(row > 0)) {
    return 0;
  }
  const first = band.from * row;
  if (busy) {
    return first;
  }
  const centred = (band.from + band.to) / 2 * row - viewport / 2;
  return Math.max(0, Math.min(centred, first));
}, "boxTopOf");
const edgesOf = /* @__PURE__ */ __name((top, total, viewport) => ({
  up: top > 1,
  down: top + viewport < total - 1
}), "edgesOf");
export {
  EMPTY_WINDOW,
  FULL_DAY,
  MIN_WINDOW,
  boxTopOf,
  columns,
  dayPlan,
  edgesOf,
  hourWindow,
  intersects,
  isAllDay,
  itemKey,
  overlapsOf
};
//# sourceMappingURL=overlap.dev.js.map
