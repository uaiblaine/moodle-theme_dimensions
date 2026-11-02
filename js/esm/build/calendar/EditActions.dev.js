var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The event popover's Edit and Delete, for an event core lets the viewer edit (stage C5, PROPOSAL.md section 23).
 *
 * Edit opens core's event form for the event; Delete is core's own: its wrapper carries the markers core's crud reads
 * (data-type="event", data-event-id, data-event-title, data-event-count, as core's summary modal carries them) and
 * core's registerRemove() listens on it, so the confirmation, the series question and the delete are core's. The
 * parent keys this component by the item: jQuery caches what it read of the markers per element, and a wrapper reused
 * for another event would hand core the first event's id.
 *
 * @module     theme_dimensions/calendar/EditActions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useRef } from "react";
import Pending from "@moodle/lms/core/pending";
import { armDelete } from "./editing";
const EditActions = /* @__PURE__ */ __name(({ item, edit, strings, onEdit, onFailure }) => {
  const root = useRef(null);
  useEffect(() => {
    if (!edit.delete || !root.current) {
      return;
    }
    const pending = new Pending("theme_dimensions/calendar:delete");
    armDelete(root.current).catch(onFailure).finally(() => pending.resolve());
  }, [edit.delete]);
  if (!edit.edit && !edit.delete) {
    return null;
  }
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      ref: root,
      className: "dim-cal__popedit",
      "data-region": "dim-cal-edit",
      "data-type": "event",
      "data-event-id": item.id,
      "data-event-title": item.name,
      "data-event-count": edit.count,
      children: [
        edit.edit ? /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-secondary", "data-action": "dim-cal-edit", onClick: onEdit, children: strings.calendar_edit }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/EditActions.tsx",
          lineNumber: 77,
          columnNumber: 17
        }) : null,
        edit.delete ? /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-outline-danger", "data-action": "delete", children: strings.calendar_delete }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/calendar/EditActions.tsx",
          lineNumber: 82,
          columnNumber: 17
        }) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/theme/dimensions/js/esm/src/calendar/EditActions.tsx",
      lineNumber: 67,
      columnNumber: 9
    }
  );
}, "EditActions");
var EditActions_default = EditActions;
export {
  EditActions_default as default
};
//# sourceMappingURL=EditActions.dev.js.map
