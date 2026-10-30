var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The panel's rail: its arrows, its phone counter and the drag of a mouse.
 *
 * Stage R3 of docs/vitrine-react/PLAN.md, the rail of the AMD panel it replaced (removed in R4c). The rail
 * itself is the panel template's markup; this module reads its scroll and writes its arrows' state and its
 * counter. {@see railState} is the one place layout is read, and Areas.tsx calls it
 * only where the layout is already clean: from a ResizeObserver entry (after layout, before paint) and
 * from the rail's own scroll and the reader's own press.
 *
 * @module     theme_dimensions/areas/rail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { CLASSES, DRAG_THRESHOLD, RAIL_GAP, SELECTORS } from "./model";
import { setClass } from "./posters";
const railFrom = /* @__PURE__ */ __name((left, max, step, count) => ({
  prevoff: left <= 2,
  nextoff: left >= max - 2,
  at: count && step > 0 ? Math.min(count, Math.round(left / step) + 1) : 0,
  count
}), "railFrom");
const railState = /* @__PURE__ */ __name((rail) => {
  const cards = rail.querySelectorAll(SELECTORS.card);
  const first = cards.length ? cards[0].parentElement : null;
  return railFrom(rail.scrollLeft, rail.scrollWidth - rail.clientWidth, first ? first.offsetWidth + RAIL_GAP : 0, cards.length);
}, "railState");
const paintRail = /* @__PURE__ */ __name((panel, state) => {
  const prev = panel.querySelector(SELECTORS.prev);
  const next = panel.querySelector(SELECTORS.next);
  if (prev) {
    setClass(prev, CLASSES.arrowoff, state.prevoff);
  }
  if (next) {
    setClass(next, CLASSES.arrowoff, state.nextoff);
  }
  const counter = panel.querySelector(SELECTORS.counter);
  const text = state.at + " / " + state.count;
  if (counter && state.count && counter.textContent !== text) {
    counter.textContent = text;
  }
}, "paintRail");
const scrollRail = /* @__PURE__ */ __name((panel, direction, still) => {
  const rail = panel.querySelector(SELECTORS.rail);
  const first = rail ? rail.firstElementChild : null;
  if (!rail || !first) {
    return;
  }
  rail.scrollBy({ left: direction * (first.offsetWidth + RAIL_GAP), behavior: still ? "auto" : "smooth" });
}, "scrollRail");
const dragRail = /* @__PURE__ */ __name((event, dragged) => {
  if (event.pointerType !== "mouse" || event.button !== 0 || !(event.target instanceof Element)) {
    return;
  }
  const rail = event.target.closest(SELECTORS.rail);
  if (!rail || rail.scrollWidth <= rail.clientWidth + 2) {
    return;
  }
  const startx = event.clientX;
  const startleft = rail.scrollLeft;
  let moved = false;
  const move = /* @__PURE__ */ __name((step) => {
    const dx = step.clientX - startx;
    if (!moved && Math.abs(dx) > DRAG_THRESHOLD) {
      moved = true;
      rail.classList.add(CLASSES.dragging);
    }
    if (moved) {
      rail.scrollLeft = startleft - dx;
    }
  }, "move");
  const up = /* @__PURE__ */ __name(() => {
    window.removeEventListener("pointermove", move);
    window.removeEventListener("pointerup", up);
    if (moved) {
      rail.classList.remove(CLASSES.dragging);
      dragged();
    }
  }, "up");
  window.addEventListener("pointermove", move);
  window.addEventListener("pointerup", up);
}, "dragRail");
export {
  dragRail,
  paintRail,
  railFrom,
  railState,
  scrollRail
};
//# sourceMappingURL=rail.dev.js.map
