var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The one door from the theme's ES modules to Moodle's AMD modules.
 *
 * A component cannot import an AMD module: the import map Moodle serves resolves `@moodle/lms/`,
 * react and react-dom, and nothing of RequireJS, so `import Templates from 'core/templates'` in a
 * .tsx fails to resolve in the browser and the component never mounts. AMD is reached through
 * core's loader (`@moodle/lms/core/amd`) instead, and only from this file.
 *
 * Every AMD module the theme's ESM tree may load is a key of `Modules`, so a name outside it is a
 * type error under the tsc gate, and the visitor's import walk
 * ({@see tests/local/category/categorypublic_source_test.php}) reads every AMD edge of the tree
 * from the call sites' literal names. Strings and the pending counter are not here: 5.3 ships them
 * as ES modules (`@moodle/lms/core/stringUtils`, `@moodle/lms/core/pending`), and `core/str` is
 * itself a shim over the first.
 *
 * @module     theme_dimensions/amd
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { requireAsync, requireManyAsync } from "@moodle/lms/core/amd";
const load = /* @__PURE__ */ __name((name) => requireAsync(name), "load");
const loadMany = /* @__PURE__ */ __name((names) => requireManyAsync(names), "loadMany");
export {
  load,
  loadMany
};
//# sourceMappingURL=amd.dev.js.map
