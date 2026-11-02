var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The logged-in calendar's door to core's own calendar editing: core's event form, its delete confirmation, its move
 * service and its events, reached through the bridge.
 *
 * Nothing is written here: core's form (core_calendar/modal_event_form, core_calendar_submit_create_update_form),
 * core's confirmation and delete (core_calendar/crud's registerRemove(), core_calendar_delete_calendar_events) and
 * core's move (core_calendar/repository's updateEventStartDay()) do the work, each checking the viewer again. The page
 * is told when core says something changed, through core's own events (core_calendar/events), which core triggers
 * with jQuery on the body: a native listener never hears them, so they are listened to with jQuery.
 *
 * The visitor's calendar never loads this module (tests/local/category/categorypublic_source_test.php): the AMD
 * modules it adds to the bridge's type are named here, and the bridge names none of them.
 *
 * @module     theme_dimensions/calendar/editing
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { getString } from "@moodle/lms/core/stringUtils";
import { load, loadMany } from "../amd";
const nothing = /* @__PURE__ */ __name(() => void 0, "nothing");
let changed = nothing;
let listening = null;
let form = null;
const onChange = /* @__PURE__ */ __name((callback) => {
  changed = callback;
}, "onChange");
const listen = /* @__PURE__ */ __name(() => {
  if (!listening) {
    listening = loadMany(["jquery", "core_calendar/events"]).then(([jquery, events]) => {
      const body = jquery("body");
      body.on(events.created, () => changed("created"));
      body.on(events.updated, () => changed("updated"));
      body.on(events.deleted, () => changed("deleted"));
      return void 0;
    }).catch((failure) => {
      listening = null;
      throw failure;
    });
  }
  return listening;
}, "listen");
const openForm = /* @__PURE__ */ __name(async (request) => {
  await listen();
  if (!form) {
    form = load("core_calendar/modal_event_form").then((modal2) => modal2.create());
    form.catch(() => {
      form = null;
    });
  }
  const modal = await form;
  if (request.event !== null) {
    modal.setEventId(request.event);
  } else {
    modal.setCourseId(request.course);
    if (request.start !== null) {
      modal.setStartTime(request.start);
    }
  }
  modal.setContextId(request.context);
  modal.show();
}, "openForm");
const armDelete = /* @__PURE__ */ __name(async (element) => {
  await listen();
  const [jquery, crud] = await loadMany(["jquery", "core_calendar/crud"]);
  crud.registerRemove(jquery(element));
}, "armDelete");
const moveStart = /* @__PURE__ */ __name((eventid, daytimestamp) => load("core_calendar/repository").then((repository) => repository.updateEventStartDay(eventid, daytimestamp)), "moveStart");
const refuse = /* @__PURE__ */ __name(async (message) => {
  const [title, notification] = await Promise.all([getString("errorinvaliddate", "calendar"), load("core/notification")]);
  await notification.exception({ name: title, message: message || title });
}, "refuse");
const forget = /* @__PURE__ */ __name(() => {
  changed = nothing;
  listening = null;
  form = null;
}, "forget");
export {
  armDelete,
  forget,
  moveStart,
  onChange,
  openForm,
  refuse
};
//# sourceMappingURL=editing.dev.js.map
