var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * What the period control holds and every way it changes, as pure functions of that state.
 *
 * Two boxes, a start and an end, open one popover, and the box that opened it is the mode: the bound
 * the next press of a day sets. The presses write a draft; "Done" or a press outside the control
 * applies it ({@see commit()}), and Escape or the keyboard walking out discards it ({@see close()}),
 * leaving the period that was in force. Each clearing control applies at once ({@see drop()}). The
 * component only renders this state and tells the listing when {@see apply()} says the period in force changed.
 *
 * @module     theme_dimensions/daterange/model
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { bringIntoView, firstOf, monthAt, monthOf, onScreen, parse, period } from "./calendar";
const FROM = "from";
const TO = "to";
const initial = /* @__PURE__ */ __name((from, to, today) => {
  const given = period(from, to);
  return {
    from: given.from,
    to: given.to,
    draftfrom: given.from,
    draftto: given.to,
    open: false,
    mode: FROM,
    field: FROM,
    cursor: monthOf(given.from || given.to || today),
    focused: "",
    opened: false
  };
}, "initial");
const setMode = /* @__PURE__ */ __name((model, bound, today, count) => {
  const mode = bound === TO ? TO : FROM;
  const own = mode === TO ? model.draftto : model.draftfrom;
  const other = mode === TO ? model.draftfrom : model.draftto;
  const focused = own || other || today;
  return { ...model, mode, field: mode, focused, cursor: bringIntoView(model.cursor, count, focused) };
}, "setMode");
const open = /* @__PURE__ */ __name((model, bound, today, count) => {
  const opened = {
    ...model,
    draftfrom: model.from,
    draftto: model.to,
    cursor: monthOf(model.from || model.to || today),
    open: true,
    opened: true
  };
  return setMode(opened, bound, today, count);
}, "open");
const close = /* @__PURE__ */ __name((model) => ({ ...model, open: false, draftfrom: model.from, draftto: model.to }), "close");
const apply = /* @__PURE__ */ __name((model, start, end) => {
  const changed = start !== model.from || end !== model.to;
  return { model: { ...model, from: start, to: end, draftfrom: start, draftto: end }, changed };
}, "apply");
const press = /* @__PURE__ */ __name((model, iso) => {
  let { draftfrom, draftto, mode } = model;
  if (mode === FROM) {
    draftfrom = iso;
    if (draftto !== "" && draftto < iso) {
      draftto = "";
    }
    mode = TO;
  } else if (draftfrom !== "" && iso < draftfrom) {
    draftfrom = iso;
    draftto = "";
  } else {
    draftto = iso;
  }
  return { ...model, draftfrom, draftto, mode, field: mode, focused: iso };
}, "press");
const commit = /* @__PURE__ */ __name((model) => apply(close(model), model.draftfrom, model.draftto), "commit");
const drop = /* @__PURE__ */ __name((model, bound) => {
  const start = bound === TO ? model.draftfrom : "";
  const end = bound === FROM ? model.draftto : "";
  return apply(bound === "" ? { ...model, mode: FROM } : model, start, end);
}, "drop");
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
const set = /* @__PURE__ */ __name((model, start, end) => {
  const given = period(start, end);
  return { ...model, from: given.from, to: given.to, draftfrom: given.from, draftto: given.to };
}, "set");
export {
  FROM,
  TO,
  apply,
  close,
  commit,
  drop,
  initial,
  moveTo,
  open,
  press,
  set,
  setMode,
  step,
  widen
};
//# sourceMappingURL=model.dev.js.map
