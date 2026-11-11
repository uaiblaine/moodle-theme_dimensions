var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The searched term marked on the titles of cards, a panel's and the listing's grid's alike, over the filter line's
 * matching ({@see ../filterline/mark runs}).
 *
 * On the text of the title's heading (data-region="dim-card-title"), never on a link's hook: a card with no
 * page to open draws its title as a plain span. It unmarks before it marks, so it is idempotent.
 *
 * @module     theme_dimensions/areas/mark
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fold, runs } from "../filterline/mark";
const TITLE = '[data-region="dim-card-title"]';
const MARK_CLASS = "dim-mark";
const MIN_LENGTH = 2;
const needleOf = /* @__PURE__ */ __name((term) => {
  const collapsed = String(term || "").trim().replace(/\s+/g, " ");
  return collapsed.length < MIN_LENGTH ? "" : fold(collapsed);
}, "needleOf");
const markNode = /* @__PURE__ */ __name((node, needle) => {
  const text = node.nodeValue || "";
  const found = runs(text, needle);
  if (!found.length) {
    return;
  }
  const fragment = document.createDocumentFragment();
  let last = 0;
  found.forEach(([start, end]) => {
    if (start > last) {
      fragment.append(document.createTextNode(text.slice(last, start)));
    }
    const mark = document.createElement("mark");
    mark.className = MARK_CLASS;
    mark.textContent = text.slice(start, end);
    fragment.append(mark);
    last = end;
  });
  if (last < text.length) {
    fragment.append(document.createTextNode(text.slice(last)));
  }
  node.replaceWith(fragment);
}, "markNode");
const markTitles = /* @__PURE__ */ __name((root, term) => {
  if (!root) {
    return;
  }
  const needle = needleOf(term);
  root.querySelectorAll(TITLE).forEach((title) => {
    const marks = title.querySelectorAll(`mark.${MARK_CLASS}`);
    marks.forEach((mark) => mark.replaceWith(document.createTextNode(mark.textContent || "")));
    if (marks.length) {
      title.normalize();
    }
    if (needle === "") {
      return;
    }
    const walker = document.createTreeWalker(title, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) {
      nodes.push(walker.currentNode);
    }
    nodes.forEach((node) => markNode(node, needle));
  });
}, "markTitles");
export {
  markTitles
};
//# sourceMappingURL=mark.dev.js.map
