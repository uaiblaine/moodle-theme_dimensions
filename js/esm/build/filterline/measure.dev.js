var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * What the filter line reads off the page: the width each step needs, and where its floating layers go.
 *
 * Most functions here read layout, and each says when it forces one. The steps' widths are measured
 * once per content - the words, the counts, the slots - with the line under the measuring class (one
 * row that never wraps, no transition, only the search shrinking, down to its step's floor) and every
 * cell of the scope drawn, a cell the counts hide shown for the measure and hidden again before anything
 * is painted: so a step follows the width and the language and never the numbers of the moment. A width change
 * then needs no measure at all ({@see ./steps choose()}).
 *
 * @module     theme_dimensions/filterline/measure
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { CLASSES, TOLERANCE, TRIES, paint } from "./steps";
const GAP = 6;
const REVEAL_PAD = 16;
const MIN_LIST_WIDTH = 288;
const SEARCH = '[data-region="dim-line-search"]';
const LISTBOX = '[data-region="dim-line-suggest"]';
const SCOPE = '[data-region="dim-vitrine-group"][data-role="scope"]';
const shown = /* @__PURE__ */ __name((element) => Boolean(element) && element.getClientRects().length > 0, "shown");
const contentWidth = /* @__PURE__ */ __name((line) => {
  const style = window.getComputedStyle(line);
  return line.clientWidth - (parseFloat(style.paddingLeft) || 0) - (parseFloat(style.paddingRight) || 0);
}, "contentWidth");
const requiredWidth = /* @__PURE__ */ __name((line) => {
  let start = Infinity;
  let end = -Infinity;
  let slack = 0;
  Array.from(line.children).forEach((child) => {
    if (child.matches(LISTBOX)) {
      return;
    }
    const rect = child.getBoundingClientRect();
    if (rect.width <= 0) {
      return;
    }
    start = Math.min(start, rect.left);
    end = Math.max(end, rect.right);
    if (child.matches(SEARCH)) {
      const floor = parseFloat(window.getComputedStyle(child).minWidth) || 0;
      slack = Math.max(0, rect.width - floor);
    }
  });
  return end > start ? end - start - slack : 0;
}, "requiredWidth");
const stepWidths = /* @__PURE__ */ __name((line, drawn, width) => {
  const searching = line.classList.contains(CLASSES.searching);
  const scope = line.querySelector(SCOPE);
  const absent = scope ? Array.from(scope.querySelectorAll("label[hidden]")) : [];
  line.classList.add(CLASSES.measuring);
  line.classList.remove(CLASSES.searching);
  absent.forEach((label) => {
    label.hidden = false;
  });
  const widths = [];
  for (const fold of TRIES) {
    paint(line, fold);
    widths.push(requiredWidth(line));
    if (width !== void 0 && widths[widths.length - 1] <= width + TOLERANCE) {
      break;
    }
  }
  absent.forEach((label) => {
    label.hidden = true;
  });
  paint(line, drawn);
  line.classList.toggle(CLASSES.searching, searching);
  void line.offsetWidth;
  line.classList.remove(CLASSES.measuring);
  return widths;
}, "stepWidths");
const lineBottom = /* @__PURE__ */ __name((line, twolines) => {
  let bottom = 0;
  Array.from(line.children).forEach((child) => {
    if (child.matches(LISTBOX) || twolines && child.matches(SCOPE)) {
      return;
    }
    const rect = child.getBoundingClientRect();
    if (rect.width > 0 && rect.height > 0) {
      bottom = Math.max(bottom, rect.bottom);
    }
  });
  return bottom || line.getBoundingClientRect().bottom;
}, "lineBottom");
const place = /* @__PURE__ */ __name((line, layer, twolines, sheet) => {
  if (!layer) {
    return;
  }
  if (sheet) {
    layer.style.top = "";
    return;
  }
  const parent = layer.offsetParent;
  if (!parent || !shown(layer)) {
    return;
  }
  const top = parent.getBoundingClientRect().top + parent.clientTop;
  layer.style.top = Math.round(lineBottom(line, twolines) - top + GAP) + "px";
}, "place");
const placeList = /* @__PURE__ */ __name((line, listbox, anchored, twolines) => {
  const search = line.querySelector(SEARCH);
  listbox.classList.toggle("dim-line__suggest--anchored", anchored);
  if (anchored && search) {
    const base = line.getBoundingClientRect();
    const box = search.getBoundingClientRect();
    const width = Math.min(base.width, Math.max(box.width, MIN_LIST_WIDTH));
    listbox.style.left = Math.round(Math.min(box.left - base.left, base.width - width)) + "px";
    listbox.style.width = Math.round(width) + "px";
  } else {
    listbox.style.left = "";
    listbox.style.width = "";
  }
  place(line, listbox, twolines, false);
}, "placeList");
const fades = /* @__PURE__ */ __name((scroller) => {
  if (!scroller) {
    return;
  }
  const max = scroller.scrollWidth - scroller.clientWidth;
  const x = scroller.scrollLeft;
  const rtl = window.getComputedStyle(scroller).direction === "rtl";
  const left = rtl ? max + x : x;
  const right = rtl ? -x : max - x;
  scroller.classList.toggle("dim-scroll--fade-left", max > 1 && left > 2);
  scroller.classList.toggle("dim-scroll--fade-right", max > 1 && right > 2);
}, "fades");
const reveal = /* @__PURE__ */ __name((scroller, item, still) => {
  if (!scroller || !item || !scroller.contains(item) || scroller.scrollWidth <= scroller.clientWidth) {
    return;
  }
  const box = scroller.getBoundingClientRect();
  const rect = item.getBoundingClientRect();
  let dx = 0;
  if (rect.left < box.left + REVEAL_PAD) {
    dx = rect.left - box.left - REVEAL_PAD;
  } else if (rect.right > box.right - REVEAL_PAD) {
    dx = rect.right - box.right + REVEAL_PAD;
  }
  if (dx !== 0) {
    scroller.scrollBy({ left: dx, behavior: still ? "auto" : "smooth" });
  }
}, "reveal");
const itemOf = /* @__PURE__ */ __name((form, target) => {
  if (!(target instanceof Element)) {
    return null;
  }
  if (target.matches('input[type="radio"]')) {
    return form.querySelector(`label[for="${target.id}"]`);
  }
  return target.closest("label, a, button");
}, "itemOf");
export {
  GAP,
  MIN_LIST_WIDTH,
  REVEAL_PAD,
  contentWidth,
  fades,
  itemOf,
  lineBottom,
  place,
  placeList,
  requiredWidth,
  reveal,
  shown,
  stepWidths
};
//# sourceMappingURL=measure.dev.js.map
