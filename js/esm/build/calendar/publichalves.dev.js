var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The public calendar's two halves: the halves asked for the keys on, their addresses and their merge.
 *
 * The visitor's answer is two halves that share no item, each a complete answer of one key under an address of its own
 * that a CDN may keep: "events" answers the key "category" (and carries the category events' descriptions), "courses"
 * the key "open" (and carries the course dictionary). {@see merge()} is the answer of both, and must stay in step with
 * classes/local/calendar/publicrange.php merge(), which the server's first paint is: one half is returned as is; two
 * are concatenated, the items sorted by start, source and id.
 *
 * Pure: nothing here fetches, reads the page or keeps state.
 *
 * @module     theme_dimensions/calendar/publichalves
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const HALVES = ["events", "courses"];
const KEY_OF = { events: "category", courses: "open" };
const halvesOn = /* @__PURE__ */ __name((on) => HALVES.filter((half) => on.includes(KEY_OF[half])), "halvesOn");
const halfUrl = /* @__PURE__ */ __name((prefix, from, to) => `${prefix}/${from}/${to}`, "halfUrl");
const byteOrder = /* @__PURE__ */ __name((a, b) => {
  if (a === b) {
    return 0;
  }
  return a < b ? -1 : 1;
}, "byteOrder");
const itemOrder = /* @__PURE__ */ __name((a, b) => a.timestart - b.timestart || byteOrder(a.source, b.source) || a.id - b.id, "itemOrder");
const merge = /* @__PURE__ */ __name((halves) => {
  if (halves.length === 1) {
    return halves[0];
  }
  const categories = /* @__PURE__ */ new Map();
  halves.forEach((half) => half.categories.forEach((category) => categories.set(category.id, category)));
  const first = halves[0];
  return {
    from: first.from,
    to: first.to,
    truncated: halves.some((half) => half.truncated),
    events: halves.flatMap((half) => half.events).sort(itemOrder),
    courses: halves.flatMap((half) => half.courses),
    categories: [...categories.values()].sort((a, b) => a.id - b.id),
    days: first.days.map((day, index) => {
      const counts = {};
      halves.forEach((half) => Object.entries(half.days[index]?.counts || {}).forEach(([key, count]) => {
        counts[key] = (counts[key] || 0) + count;
      }));
      return { date: day.date, counts };
    }),
    editing: [],
    descriptions: halves.flatMap((half) => half.descriptions)
  };
}, "merge");
export {
  HALVES,
  KEY_OF,
  halfUrl,
  halvesOn,
  itemOrder,
  merge
};
//# sourceMappingURL=publichalves.dev.js.map
