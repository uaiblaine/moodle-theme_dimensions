var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * What the period control holds and every way it changes, as pure functions of that state.
 *
 * Two boxes, a start and an end, over the months; the active box is the bound the next press of a day sets
 * (the mode). There is no draft: every press, a box's own clear and "Clear period" change the period in
 * force at once ({@see press()}, {@see drop()}), and the component tells the listing whenever
 * {@see apply()} says it really changed, so the list is refreshed while the layer stays open. The
 * component only renders this state.
 *
 * @module     theme_dimensions/daterange/model
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { bringIntoView, firstOf, monthAt, monthOf, onScreen, parse, period } from "./calendar";
const FROM = "from";
const TO = "to";
const modeFor = /* @__PURE__ */ __name((from, to) => from !== "" && to === "" ? TO : FROM, "modeFor");
const initial = /* @__PURE__ */ __name((from, to, today) => {
  const given = period(from, to);
  const focused = given.from || given.to || today;
  return {
    from: given.from,
    to: given.to,
    mode: modeFor(given.from, given.to),
    cursor: monthOf(focused),
    focused,
    hover: ""
  };
}, "initial");
const setMode = /* @__PURE__ */ __name((model, bound, today, count) => {
  const mode = bound === TO ? TO : FROM;
  const own = mode === TO ? model.to : model.from;
  const other = mode === TO ? model.from : model.to;
  const focused = own || other || today;
  return { ...model, mode, focused, cursor: bringIntoView(model.cursor, count, focused) };
}, "setMode");
const apply = /* @__PURE__ */ __name((model, start, end) => {
  const changed = start !== model.from || end !== model.to;
  return { model: { ...model, from: start, to: end }, changed };
}, "apply");
const press = /* @__PURE__ */ __name((model, iso) => {
  let { from, to } = model;
  let mode = model.mode === TO ? FROM : TO;
  if (model.mode === FROM) {
    from = iso;
    if (to !== "" && to < iso) {
      to = "";
    }
  } else if (from !== "" && iso < from) {
    from = iso;
    to = "";
    mode = TO;
  } else {
    to = iso;
  }
  return apply({ ...model, mode, focused: iso, hover: "" }, from, to);
}, "press");
const drop = /* @__PURE__ */ __name((model, bound) => {
  const start = bound === TO ? model.from : "";
  const end = bound === FROM ? model.to : "";
  const mode = bound === TO ? TO : FROM;
  return apply({ ...model, mode, hover: "" }, start, end);
}, "drop");
const drawn = /* @__PURE__ */ __name((model) => {
  const hover2 = model.hover;
  if (hover2 !== "" && model.mode === TO && model.from !== "" && hover2 > model.from) {
    return { from: model.from, to: hover2 };
  }
  if (hover2 !== "" && model.mode === FROM && model.to !== "" && hover2 < model.to) {
    return { from: hover2, to: model.to };
  }
  return { from: model.from, to: model.to };
}, "drawn");
const hover = /* @__PURE__ */ __name((model, iso) => model.hover === iso ? model : { ...model, hover: iso }, "hover");
const step = /* @__PURE__ */ __name((model, offset) => {
  const cursor = monthAt(model.cursor, offset);
  return { ...model, cursor, focused: firstOf(cursor) };
}, "step");
const moveTo = /* @__PURE__ */ __name((model, iso, count) => {
  if (!parse(iso)) {
    return null;
  }
  const cursor = onScreen(model.cursor, count, iso) ? model.cursor : bringIntoView(model.cursor, count, iso);
  return { ...model, focused: iso, cursor };
}, "moveTo");
const widen = /* @__PURE__ */ __name((model, count) => ({ ...model, cursor: bringIntoView(model.cursor, count, model.focused) }), "widen");
const set = /* @__PURE__ */ __name((model, start, end, count) => {
  const given = period(start, end);
  if (given.from === model.from && given.to === model.to) {
    return model;
  }
  const day = given.from || given.to;
  const next = { ...model, from: given.from, to: given.to, mode: modeFor(given.from, given.to), hover: "" };
  if (day === "" || onScreen(model.cursor, count, day)) {
    return next;
  }
  return { ...next, focused: day, cursor: bringIntoView(model.cursor, count, day) };
}, "set");
export {
  FROM,
  TO,
  apply,
  drawn,
  drop,
  hover,
  initial,
  modeFor,
  moveTo,
  press,
  set,
  setMode,
  step,
  widen
};
//# sourceMappingURL=model.dev.js.map
