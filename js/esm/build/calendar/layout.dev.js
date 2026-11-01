var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The month grid's layout, pure: the spanning bars' slots, the density of each day, the day counts.
 *
 * docs/calendar/PROPOSAL.md sections 4 and 5.2, ported from the C0 canvas:
 *
 * - An item that touches more than one day, and every event course, is a BAR; any other item sits in its day's
 *   content. A bar is clipped to each week row it crosses; the segments of a row are sorted by start, then longer
 *   first, then name, and each takes the lowest slot free on all its columns, so a bar keeps one slot across its
 *   row and never zigzags. Slots past the row's capacity ({@see barSlots()}) are hidden and counted per day.
 * - The density of a day comes from its row's height (measured by the grid, one ResizeObserver per grid) and from
 *   what the bars left: one item on a day with room is a CARD, a few that fit are LINES, more than fit is a SUMMARY
 *   (the lines that fit, then "+N" for the rest and the hidden bars, with a dot per key of what was left out).
 * - The count of a day is every item touching it, bars included: the day button's label says it.
 * - On a phone the month draws a dot row per day instead ({@see dayDots()}): a dot per key with items, from the
 *   answer's per-day counts, at most {@see DOTS}, then a "+".
 *
 * @module     theme_dimensions/calendar/layout
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { KEYS } from "./model";
const HEADER = 30;
const SLOT = 24;
const LINE = 22;
const PAD = 8;
const CARD_MIN = 78;
const MAX_BARS = 3;
const ROW_DEFAULT = 148;
const DOTS = 4;
const place = /* @__PURE__ */ __name((items, zone) => items.map((item) => {
  const first = zone.day(item.timestart);
  const last = zone.day(item.timestart + Math.max(0, item.timeduration - 1));
  return { item, first, last, bar: first !== last || item.source === "course" };
}), "place");
const barSlots = /* @__PURE__ */ __name((height) => Math.max(1, Math.min(MAX_BARS, Math.floor((height - HEADER - PAD - LINE) / SLOT))), "barSlots");
const bySpan = /* @__PURE__ */ __name((a, b) => a.start - b.start || b.end - b.start - (a.end - a.start) || a.placed.item.name.localeCompare(b.placed.item.name) || a.placed.item.id - b.placed.item.id, "bySpan");
const weekLayout = /* @__PURE__ */ __name((week, placed, slots) => {
  const first = week[0];
  const last = week[week.length - 1];
  const segments = [];
  const timed = week.map(() => []);
  placed.forEach((entry) => {
    if (entry.last < first || entry.first > last) {
      return;
    }
    if (!entry.bar) {
      const column = week.indexOf(entry.first);
      if (column >= 0) {
        timed[column].push(entry);
      }
      return;
    }
    const start = entry.first < first ? 0 : week.indexOf(entry.first);
    const end = entry.last > last ? week.length - 1 : week.indexOf(entry.last);
    segments.push({
      placed: entry,
      start,
      end,
      clipstart: entry.first < first,
      clipend: entry.last > last,
      slot: 0
    });
  });
  segments.sort(bySpan);
  const taken = [];
  const hidden = week.map(() => 0);
  const bars = [];
  let used = 0;
  segments.forEach((segment) => {
    let slot = 0;
    for (; ; ) {
      taken[slot] = taken[slot] || week.map(() => false);
      let free = true;
      for (let column = segment.start; column <= segment.end; column++) {
        free = free && !taken[slot][column];
      }
      if (free) {
        break;
      }
      slot++;
    }
    for (let column = segment.start; column <= segment.end; column++) {
      taken[slot][column] = true;
    }
    if (slot >= slots) {
      for (let column = segment.start; column <= segment.end; column++) {
        hidden[column]++;
      }
      return;
    }
    used = Math.max(used, slot + 1);
    bars.push({ ...segment, slot });
  });
  timed.forEach((entries) => entries.sort((a, b) => a.item.timestart - b.item.timestart || a.item.name.localeCompare(b.item.name) || a.item.id - b.item.id));
  return { bars, hidden, used, timed };
}, "weekLayout");
const cellPlan = /* @__PURE__ */ __name((timed, hidden, used, height) => {
  if (!timed.length && !hidden) {
    return { density: "none", shown: [], more: 0, dots: [] };
  }
  const room = height - HEADER - used * SLOT - PAD;
  const fits = Math.max(1, Math.floor(room / LINE));
  if (timed.length === 1 && !hidden && room >= CARD_MIN) {
    return { density: "card", shown: timed, more: 0, dots: [] };
  }
  if (timed.length <= fits && !hidden) {
    return { density: "lines", shown: timed, more: 0, dots: [] };
  }
  const shown = timed.slice(0, Math.max(0, fits - 1));
  const rest = timed.slice(shown.length);
  return {
    density: "summary",
    shown,
    more: rest.length + hidden,
    dots: rest.slice(0, DOTS).map((entry) => entry.item.key)
  };
}, "cellPlan");
const dayCounts = /* @__PURE__ */ __name((days, placed) => {
  const counts = new Map(days.map((day) => [day, 0]));
  placed.forEach((entry) => {
    days.forEach((day) => {
      if (day >= entry.first && day <= entry.last) {
        counts.set(day, (counts.get(day) || 0) + 1);
      }
    });
  });
  return counts;
}, "dayCounts");
const dayDots = /* @__PURE__ */ __name((counts) => {
  const keys = KEYS.filter((key) => (counts?.[key] || 0) > 0);
  return { keys: keys.slice(0, DOTS), more: keys.length > DOTS };
}, "dayDots");
export {
  CARD_MIN,
  DOTS,
  HEADER,
  LINE,
  MAX_BARS,
  PAD,
  ROW_DEFAULT,
  SLOT,
  barSlots,
  cellPlan,
  dayCounts,
  dayDots,
  place,
  weekLayout
};
//# sourceMappingURL=layout.dev.js.map
