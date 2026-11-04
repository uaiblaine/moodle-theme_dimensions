var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * An event's description, read lazily from core and once per page life.
 *
 * The range answers no description: the popover asks core's
 * core_calendar_get_calendar_event_by_id when it opens on one of core's events, which runs core's own visibility
 * check (the event vault, then calendar_view_event_allowed()) and answers the event exporter's description, already
 * through format_text() (core's exporter formats a property that has a format beside it), or null. A refusal, a
 * failure and an empty description all read as '' here: the popover draws nothing for them.
 *
 * One request per event per page life: the answer, a refusal included, is kept by event id until the page goes.
 * Which popover's answer is shown is the popover's decision (a ticket, EventPopover.tsx); this module only reads.
 *
 * @module     theme_dimensions/calendar/description
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fetchOne } from "@moodle/lms/core/ajax";
const EVENT_METHOD = "core_calendar_get_calendar_event_by_id";
const cache = /* @__PURE__ */ new Map();
const description = /* @__PURE__ */ __name((id) => {
  let known = cache.get(id);
  if (known === void 0) {
    known = fetchOne({ methodname: EVENT_METHOD, args: { eventid: id } }).then((answer) => String(answer?.event?.description || "")).catch(() => "");
    cache.set(id, known);
  }
  return known;
}, "description");
const forget = /* @__PURE__ */ __name(() => cache.clear(), "forget");
export {
  EVENT_METHOD,
  description,
  forget
};
//# sourceMappingURL=description.dev.js.map
