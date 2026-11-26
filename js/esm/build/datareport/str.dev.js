var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Words and numbers: filling the strings the props carry (copied from local_evidencehub's datareport/str.ts).
 *
 * The strings arrive in the props, translated with their placeholders ("{$a} learners"), so the first paint
 * waits for no string request; only the client knows the value.
 *
 * @module     theme_dimensions/datareport/str
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const fill = /* @__PURE__ */ __name((template, value) => (template || "").split("{$a}").join(value), "fill");
const fillObject = /* @__PURE__ */ __name((template, values) => Object.entries(values).reduce((text, [name, value]) => text.split(`{$a->${name}}`).join(value), template || ""), "fillObject");
const fillCount = /* @__PURE__ */ __name((labels, key, count, shown) => fill(count === 1 && labels[`${key}_one`] ? labels[`${key}_one`] : labels[key], shown), "fillCount");
const num = /* @__PURE__ */ __name((value) => {
  const lang = document.documentElement.lang || void 0;
  try {
    return new Intl.NumberFormat(lang).format(value);
  } catch {
    return String(value);
  }
}, "num");
const plain = /* @__PURE__ */ __name((html) => {
  const template = document.createElement("template");
  template.innerHTML = html;
  return (template.content.textContent || "").replace(/\s+/g, " ").trim();
}, "plain");
export {
  fill,
  fillCount,
  fillObject,
  num,
  plain
};
//# sourceMappingURL=str.dev.js.map
