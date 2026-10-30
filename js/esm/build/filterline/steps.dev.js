var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The filter line's folding steps, as a rule over numbers.
 *
 * The fleet contract's section 2 (moodle-dev/docs/filter-line/CONTRACT.md), as the AMD line's fit() read it
 * before R4a: the line takes the first step at which its controls fit
 * on its OWN width - full; Filters without its word; the search as an icon; two lines, the scope on the
 * second and the view toggle in the Organise menu - and the last one whatever it measures.
 *
 * The AMD module tries each step on the page and reads whether a control is drawn past an edge, on every
 * width change. This port measures, once per content, how wide the line must be for each step
 * ({@see ./measure}), and then takes a step from a width alone: a width change (a ResizeObserver entry)
 * forces no layout. The widths a step needs are the line's breakpoints for that content and language.
 *
 * @module     theme_dimensions/filterline/steps
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const STEPS = ["s1", "s2", "s3", "s4"];
const TRIES = [
  { step: "s1", folded: false },
  { step: "s2", folded: false },
  { step: "s3", folded: true },
  { step: "s4", folded: false },
  { step: "s4", folded: true }
];
const MORPH_STEPS = ["s3", "s4"];
const TWO_LINES = "s4";
const TOLERANCE = 1;
const CLASSES = {
  step: "dim-line--",
  measuring: "dim-line--measuring",
  searching: "dim-line--searching",
  folded: "dim-line--folded"
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
const morphs = /* @__PURE__ */ __name((fold) => fold !== null && MORPH_STEPS.indexOf(fold.step) >= 0, "morphs");
const same = /* @__PURE__ */ __name((one, two) => one === two || one !== null && two !== null && one.step === two.step && one.folded === two.folded, "same");
const paint = /* @__PURE__ */ __name((line, fold) => {
  STEPS.forEach((name) => line.classList.toggle(CLASSES.step + name, fold !== null && fold.step === name));
  line.classList.toggle(CLASSES.folded, fold !== null && fold.folded);
}, "paint");
export {
  CLASSES,
  MORPH_STEPS,
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
