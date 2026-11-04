var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The one writer of the showcase's history entries in js/esm/src.
 *
 * On two disjoint sets of parameters: the listing's criteria ({@see writeUrl}) and the panel's open area and
 * order, replaced and never pushed ({@see replaceKeeping}). listing_source_test fails a write anywhere else.
 *
 * @module     theme_dimensions/listing/history
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { buildUrl, syncFold } from "./address";
const writeUrl = /* @__PURE__ */ __name((state, push) => {
  const url = buildUrl(state).toString();
  if (push) {
    window.history.pushState({}, "", url);
  } else {
    window.history.replaceState({}, "", url);
  }
  syncFold();
}, "writeUrl");
const replaceKeeping = /* @__PURE__ */ __name((url) => {
  window.history.replaceState(window.history.state, "", url);
}, "replaceKeeping");
export {
  replaceKeeping,
  writeUrl
};
//# sourceMappingURL=history.dev.js.map
