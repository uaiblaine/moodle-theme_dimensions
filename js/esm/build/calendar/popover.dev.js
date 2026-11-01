var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The behaviour the category picker and the "More filters" panel share: a disclosure button and its panel.
 *
 * The button carries aria-expanded and aria-controls; Escape inside the panel shuts it and gives the focus back to
 * the button; a press outside both shuts it. The listener is on the document only while the panel is open.
 *
 * @module     theme_dimensions/calendar/popover
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useRef, useState } from "react";
const usePopover = /* @__PURE__ */ __name(() => {
  const [open, setOpen] = useState(false);
  const button = useRef(null);
  const panel = useRef(null);
  useEffect(() => {
    if (!open) {
      return void 0;
    }
    const outside = /* @__PURE__ */ __name((event) => {
      const target = event.target;
      if (target && !panel.current?.contains(target) && !button.current?.contains(target)) {
        setOpen(false);
      }
    }, "outside");
    document.addEventListener("mousedown", outside);
    return () => document.removeEventListener("mousedown", outside);
  }, [open]);
  const shut = /* @__PURE__ */ __name((refocus) => {
    setOpen(false);
    if (refocus) {
      button.current?.focus();
    }
  }, "shut");
  return {
    open,
    /**
     * Open or shut the panel.
     *
     * @returns {void}
     */
    toggle: /* @__PURE__ */ __name(() => setOpen((was) => !was), "toggle"),
    shut,
    button,
    panel,
    /**
     * Escape inside the panel shuts it.
     *
     * @param {object} event The key press.
     * @returns {void}
     */
    onKeyDown: /* @__PURE__ */ __name((event) => {
      if (event.key === "Escape") {
        event.preventDefault();
        event.stopPropagation();
        shut(true);
      }
    }, "onKeyDown")
  };
}, "usePopover");
export {
  usePopover
};
//# sourceMappingURL=popover.dev.js.map
