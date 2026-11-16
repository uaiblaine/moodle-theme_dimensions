var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The filter line's folding steps, as a rule over numbers.
 *
 * The line's controls are the same at every width - the search is its button at rest, Filters has no word - so
 * the fold decides two things only: whether the line is one line or two (the scope on a line of its own after
 * the controls, the Filters panel a bottom sheet), and whether the open search field fits beside the controls
 * (it opens in place) or not (it grows over the line). The line takes the first try that fits on its own width,
 * and the last one whatever it measures.
 *
 * Trying each step on the page and reading whether a control is drawn past an edge would force a layout on every
 * width change. This module measures, once per content, how wide the line must be for each try
 * ({@see ./measure}), and then takes a fold from a width alone: a width change (a ResizeObserver entry)
 * forces no layout. The widths a try needs are the line's breakpoints for that content and language.
 *
 * @module     theme_dimensions/filterline/steps
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const STEPS = ["s1", "s2"];
const TRIES = [
  { step: "s1", over: false },
  { step: "s1", over: true },
  { step: "s2", over: true }
];
const TWO_LINES = "s2";
const TOLERANCE = 1;
const CLASSES = {
  step: "dim-line--",
  measuring: "dim-line--measuring",
  // The search is open in place, the field in its button's place.
  open: "dim-line--open",
  // The search is open over the line.
  searching: "dim-line--searching",
  // The fold opens the search over the line.
  over: "dim-line--over"
};
const choose = /* @__PURE__ */ __name((required, width) => {
  for (let index = 0; index < TRIES.length - 1; index++) {
    if (index < required.length && required[index] <= width + TOLERANCE) {
      return TRIES[index];
    }
  }
  return TRIES[TRIES.length - 1];
}, "choose");
const settled = /* @__PURE__ */ __name((required, width) => required.length >= TRIES.length || required.some((need) => need <= width + TOLERANCE), "settled");
const morphs = /* @__PURE__ */ __name((fold) => fold !== null && fold.over, "morphs");
const same = /* @__PURE__ */ __name((one, two) => one === two || one !== null && two !== null && one.step === two.step && one.over === two.over, "same");
const paint = /* @__PURE__ */ __name((line, fold) => {
  STEPS.forEach((name) => line.classList.toggle(CLASSES.step + name, fold !== null && fold.step === name));
  line.classList.toggle(CLASSES.over, fold !== null && fold.over);
}, "paint");
export {
  CLASSES,
  STEPS,
  TOLERANCE,
  TRIES,
  TWO_LINES,
  choose,
  morphs,
  paint,
  same,
  settled
};
//# sourceMappingURL=steps.dev.js.map
