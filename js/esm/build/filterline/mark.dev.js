var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * A term marked in a text, in the text's own spelling: the rule of amd/src/categorymark.js and of the
 * suggestions of amd/src/categoryline.js, as runs a component draws rather than nodes it writes.
 *
 * The match ignores case and accents, as the server's search does: each character is folded on its own
 * and every folded character remembers the character it came from, so a match found in the folded text is
 * cut from the original one - "Incêndio" is marked whole for "incendio", accent and capital included.
 *
 * The card titles stay marked by categorymark, in AMD, on both shells: the cards are the server's markup,
 * which no component draws until the listing's store (stage R4 of docs/vitrine-react/PLAN.md). The filter
 * line marks its suggestions with this module.
 *
 * @module     theme_dimensions/filterline/mark
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const fold = /* @__PURE__ */ __name((text) => String(text).normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase(), "fold");
const normalise = /* @__PURE__ */ __name((text) => fold(text || "").trim(), "normalise");
const runs = /* @__PURE__ */ __name((text, needle) => {
  if (needle === "") {
    return [];
  }
  let folded = "";
  const source = [];
  for (let index = 0; index < text.length; index++) {
    const piece = fold(text.charAt(index));
    for (let at = 0; at < piece.length; at++) {
      folded += piece.charAt(at);
      source.push(index);
    }
  }
  const found = [];
  let from = folded.indexOf(needle);
  while (from !== -1) {
    found.push([source[from], source[from + needle.length - 1] + 1]);
    from = folded.indexOf(needle, from + needle.length);
  }
  return found;
}, "runs");
const pieces = /* @__PURE__ */ __name((text, term, all = false) => {
  const found = runs(text, term);
  const chosen = all ? found : found.slice(0, 1);
  if (!chosen.length) {
    return [{ text, marked: false }];
  }
  const result = [];
  let last = 0;
  chosen.forEach(([start, end]) => {
    if (start > last) {
      result.push({ text: text.slice(last, start), marked: false });
    }
    result.push({ text: text.slice(start, end), marked: true });
    last = end;
  });
  if (last < text.length) {
    result.push({ text: text.slice(last), marked: false });
  }
  return result;
}, "pieces");
export {
  fold,
  normalise,
  pieces,
  runs
};
//# sourceMappingURL=mark.dev.js.map
