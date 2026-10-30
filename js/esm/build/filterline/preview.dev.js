var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The filter line's preview: pointing at a filter lights the cards on screen it would keep.
 *
 * The AMD preview's rules, function for function, for the line's component, which both shells draw since R4a
 * (the AMD module left the tree with R4c). It acts on the server's
 * cards and on the toolbar form, never on anything a component draws, and writes three classes: the
 * listing previewing, a kept card, a card that recedes. The stylesheet does both by remapping the card's
 * own colour tokens, never by opacity.
 *
 * It answers a mouse or a pen pointing ({@see pointing}) and the keyboard's focus ({@see onFocus}), never
 * a finger, which has no hover to preview with. It previews a control that would NARROW the listing
 * ({@see criterionOf}): one already in force, an "All" or the star already pressed would keep every card.
 * What a filter would keep is decided from facts the server wrote on each card and nothing else
 * ({@see factsOf}), with the criteria in force read off the form ({@see inForce}); a card that does not
 * state the fact a filter is decided on makes the whole preview stand down - the page never claims a card
 * would go when it cannot know. Nothing here asks the server anything, reads the session or composes a
 * word, and it animates nothing.
 *
 * @module     theme_dimensions/filterline/preview
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const SELECTORS = {
  card: '[data-region="dim-card"]',
  cardstar: '[data-action="dim-star"]',
  radio: '[data-region="dim-vitrine-group"] input[type="radio"]',
  label: '[data-region="dim-vitrine-group"] label[for]',
  star: '[data-region="dim-line-star"]',
  quick: '[data-action="dim-quick"]',
  option: '[role="option"]'
};
const PARAM = {
  chip: "chip",
  fieldprefix: "cf_",
  from: "from",
  to: "to"
};
const CLASSES = {
  previewing: "dim-vitrine--previewing",
  lit: "dim-card--lit",
  dim: "dim-card--dim"
};
let root = null;
let form = null;
let current = null;
const none = /* @__PURE__ */ __name(() => null, "none");
let hooks = { controlOf: none };
const pointing = /* @__PURE__ */ __name((event) => event.pointerType === "mouse" || event.pointerType === "pen", "pointing");
const controlOf = /* @__PURE__ */ __name((target) => {
  if (!(target instanceof Element) || !form || !form.contains(target)) {
    return null;
  }
  const option = target.closest(SELECTORS.option);
  if (option) {
    return hooks.controlOf(option);
  }
  const label = target.closest(SELECTORS.label);
  if (label) {
    return document.getElementById(label.htmlFor);
  }
  return target.closest(`${SELECTORS.radio}, ${SELECTORS.star}, ${SELECTORS.quick}`);
}, "controlOf");
const criterionOf = /* @__PURE__ */ __name((control) => {
  if (!control || !control.isConnected) {
    return null;
  }
  if (control.matches(SELECTORS.star)) {
    return control.getAttribute("aria-pressed") === "true" ? null : { kind: "fav" };
  }
  if (control.matches(SELECTORS.quick)) {
    return control.hasAttribute("aria-current") ? null : { kind: "period", from: control.dataset.from || "", to: control.dataset.to || "" };
  }
  const input = control;
  if (!control.matches(SELECTORS.radio) || input.checked) {
    return null;
  }
  if (input.name === PARAM.chip) {
    return input.value === "" ? null : { kind: "chip", value: input.value };
  }
  if (input.name.indexOf(PARAM.fieldprefix) === 0 && input.value !== "0") {
    return { kind: "field", id: input.name.slice(PARAM.fieldprefix.length), value: input.value };
  }
  return null;
}, "criterionOf");
const inForce = /* @__PURE__ */ __name((toolbar) => {
  const force = { chip: "", fav: false, fields: {}, from: "", to: "" };
  toolbar.querySelectorAll(`${SELECTORS.radio}:checked`).forEach((input) => {
    if (input.name === PARAM.chip) {
      force.chip = input.value;
    } else if (input.name.indexOf(PARAM.fieldprefix) === 0 && input.value !== "0") {
      force.fields[input.name.slice(PARAM.fieldprefix.length)] = input.value;
    }
  });
  const star = toolbar.querySelector(SELECTORS.star);
  force.fav = Boolean(star) && star?.getAttribute("aria-pressed") === "true";
  const from = toolbar.querySelector(`input[name="${PARAM.from}"]`);
  const to = toolbar.querySelector(`input[name="${PARAM.to}"]`);
  force.from = from ? from.value : "";
  force.to = to ? to.value : "";
  return force;
}, "inForce");
const factsOf = /* @__PURE__ */ __name((card) => {
  const star = card.querySelector(SELECTORS.cardstar);
  let fields = null;
  if (card.dataset.fields) {
    fields = {};
    card.dataset.fields.split(" ").forEach((pair) => {
      const [id, value] = pair.split(":");
      fields[id] = value;
    });
  }
  return {
    chip: card.dataset.chip ? card.dataset.chip : null,
    fav: star ? star.getAttribute("aria-pressed") === "true" : null,
    fields,
    start: card.hasAttribute("data-start") ? card.dataset.start || "" : null
  };
}, "factsOf");
const within = /* @__PURE__ */ __name((start, from, to) => start !== "" && (from === "" || start >= from) && (to === "" || start <= to), "within");
const keeps = /* @__PURE__ */ __name((facts, criterion, force) => {
  const wanted = Object.assign({}, force, { fields: Object.assign({}, force.fields) });
  if (criterion.kind === "chip") {
    wanted.chip = criterion.value;
  } else if (criterion.kind === "fav") {
    wanted.fav = true;
  } else if (criterion.kind === "field") {
    wanted.fields[criterion.id] = criterion.value;
  } else {
    wanted.from = criterion.from;
    wanted.to = criterion.to;
  }
  let needed;
  if (criterion.kind === "field") {
    needed = facts.fields ? facts.fields[criterion.id] : null;
  } else if (criterion.kind === "chip") {
    needed = facts.chip;
  } else if (criterion.kind === "fav") {
    needed = facts.fav;
  } else {
    needed = facts.start;
  }
  if (needed === null || needed === void 0) {
    return null;
  }
  if (wanted.chip !== "" && facts.chip !== null && facts.chip !== wanted.chip) {
    return false;
  }
  if (wanted.fav && facts.fav === false) {
    return false;
  }
  const fields = facts.fields || {};
  const fieldmiss = Object.keys(wanted.fields).some((id) => id in fields && fields[id] !== wanted.fields[id]);
  if (fieldmiss) {
    return false;
  }
  return wanted.from === "" && wanted.to === "" || facts.start === null || within(facts.start, wanted.from, wanted.to);
}, "keeps");
const clear = /* @__PURE__ */ __name(() => {
  current = null;
  if (!root) {
    return;
  }
  root.classList.remove(CLASSES.previewing);
  root.querySelectorAll(`.${CLASSES.lit}, .${CLASSES.dim}`).forEach((card) => {
    card.classList.remove(CLASSES.lit, CLASSES.dim);
  });
}, "clear");
const show = /* @__PURE__ */ __name((control) => {
  const criterion = root ? criterionOf(control) : null;
  const listing = root;
  const cards = criterion && listing ? Array.from(listing.querySelectorAll(SELECTORS.card)) : [];
  if (!criterion || !listing || !form || !cards.length) {
    clear();
    return;
  }
  const force = inForce(form);
  const verdicts = cards.map((card) => keeps(factsOf(card), criterion, force));
  if (verdicts.indexOf(null) >= 0) {
    clear();
    return;
  }
  current = control;
  listing.classList.add(CLASSES.previewing);
  cards.forEach((card, index) => {
    card.classList.toggle(CLASSES.lit, verdicts[index] === true);
    card.classList.toggle(CLASSES.dim, verdicts[index] !== true);
  });
}, "show");
const refresh = /* @__PURE__ */ __name(() => {
  if (current) {
    show(current);
  }
}, "refresh");
const onOver = /* @__PURE__ */ __name((event) => {
  if (!pointing(event)) {
    return;
  }
  const control = controlOf(event.target);
  if (control === current) {
    return;
  }
  if (control) {
    show(control);
  } else {
    clear();
  }
}, "onOver");
const onOut = /* @__PURE__ */ __name((event) => {
  if (!pointing(event)) {
    return;
  }
  const next = event.relatedTarget instanceof Node ? event.relatedTarget : null;
  if (!next || !form || !form.contains(next)) {
    clear();
  }
}, "onOut");
const onFocus = /* @__PURE__ */ __name((event) => {
  const control = controlOf(event.target);
  if (control && event.target instanceof Element && event.target.matches(":focus-visible")) {
    show(control);
  } else if (current) {
    clear();
  }
}, "onFocus");
const onBlur = /* @__PURE__ */ __name((event) => {
  const next = event.relatedTarget instanceof Node ? event.relatedTarget : null;
  if (!next || !form || !form.contains(next)) {
    clear();
  }
}, "onBlur");
const onPress = /* @__PURE__ */ __name((event) => {
  if (controlOf(event.target)) {
    clear();
  }
}, "onPress");
const arm = /* @__PURE__ */ __name((listing, toolbar, options) => {
  if (form === toolbar) {
    return;
  }
  root = listing;
  form = toolbar;
  hooks = Object.assign({ controlOf: none }, options || {});
  form.addEventListener("pointerover", onOver);
  form.addEventListener("pointerout", onOut);
  form.addEventListener("focusin", onFocus);
  form.addEventListener("focusout", onBlur);
  form.addEventListener("change", clear);
  form.addEventListener("click", onPress);
}, "arm");
const reset = /* @__PURE__ */ __name(() => {
  clear();
  root = null;
  form = null;
  hooks = { controlOf: none };
}, "reset");
export {
  CLASSES,
  arm,
  clear,
  controlOf,
  criterionOf,
  factsOf,
  inForce,
  keeps,
  pointing,
  refresh,
  reset,
  show,
  within
};
//# sourceMappingURL=preview.dev.js.map
