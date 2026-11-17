var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Which units of the category navigation stay on the row and which move into "More": arithmetic only.
 *
 * The row module measures once ({@see ./row measure()}) and hands the numbers here, so the decision reads no layout
 * and is tested in Jest on its own. Units move from the row's end, the page itself ("Category") last of all (D83);
 * "More" is shown when anything moved or when it holds nodes of its own (D89), and its width is then paid on the
 * row, with the gap before it.
 *
 * @module     theme_dimensions/navpills/fit
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const TOLERANCE = 0.5;
const span = /* @__PURE__ */ __name((widths, kept, gap, more) => {
  const count = kept.length + (more > 0 ? 1 : 0);
  const sum = kept.reduce((total, index) => total + widths[index], 0) + more;
  return count > 0 ? sum + gap * (count - 1) : 0;
}, "span");
const fit = /* @__PURE__ */ __name((measures) => {
  const { widths, gap, more } = measures;
  const room = Number.isFinite(measures.room) ? Math.max(0, measures.room) : 0;
  const last = measures.last ?? -1;
  const own = Boolean(measures.own);
  const all = widths.map((width, index) => index);
  if (!own && span(widths, all, gap, 0) <= room + TOLERANCE) {
    return { keep: all, move: [], more: false };
  }
  const order = all.filter((index) => index !== last).reverse();
  if (last >= 0 && last < widths.length) {
    order.push(last);
  }
  const moved = /* @__PURE__ */ new Set();
  for (const index of order) {
    const kept = all.filter((candidate) => !moved.has(candidate));
    if (span(widths, kept, gap, more) <= room + TOLERANCE) {
      break;
    }
    moved.add(index);
  }
  return {
    keep: all.filter((index) => !moved.has(index)),
    move: all.filter((index) => moved.has(index)),
    more: own || moved.size > 0
  };
}, "fit");
export {
  TOLERANCE,
  fit
};
//# sourceMappingURL=fit.dev.js.map
