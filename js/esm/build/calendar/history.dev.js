var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The one writer of the calendar page's address.
 *
 * The listing's own writer (listing/history.ts) keeps the showcase's forms in step after each write, which this page
 * has none of; so the calendar has its own, and nothing else of calendar/ touches the history
 * (listing_source_test::test_only_the_history_module_writes_the_address names the two). A month moved pushes an
 * entry, so Back returns to the month before; a change of keys or filters replaces it, so Back leaves the page rather
 * than undoing every chip.
 *
 * @module     theme_dimensions/calendar/history
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const writeAddress = /* @__PURE__ */ __name((url, push) => {
  if (url === window.location.href) {
    return;
  }
  if (push) {
    window.history.pushState({}, "", url);
  } else {
    window.history.replaceState({}, "", url);
  }
}, "writeAddress");
export {
  writeAddress
};
//# sourceMappingURL=history.dev.js.map
