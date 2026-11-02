import{getString as c}from"@moodle/lms/core/stringUtils";import{load as a,loadMany as i}from"../amd";/**
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
 */const l=()=>{};let d=l,n=null,r=null;const p=e=>{d=e},s=()=>(n||(n=i(["jquery","core_calendar/events"]).then(([e,t])=>{const o=e("body");o.on(t.created,()=>d("created")),o.on(t.updated,()=>d("updated")),o.on(t.deleted,()=>d("deleted"))}).catch(e=>{throw n=null,e})),n),v=async e=>{await s(),r||(r=a("core_calendar/modal_event_form").then(o=>o.create()),r.catch(()=>{r=null}));const t=await r;e.event!==null?t.setEventId(e.event):(t.setCourseId(e.course),e.start!==null&&t.setStartTime(e.start)),t.setContextId(e.context),t.show()},y=async e=>{await s();const[t,o]=await i(["jquery","core_calendar/crud"]);o.registerRemove(t(e))},g=(e,t)=>a("core_calendar/repository").then(o=>o.updateEventStartDay(e,t)),h=async e=>{const[t,o]=await Promise.all([c("errorinvaliddate","calendar"),a("core/notification")]);await o.exception({name:t,message:e||t})},f=()=>{d=l,n=null,r=null};export{y as armDelete,f as forget,g as moveStart,p as onChange,v as openForm,h as refuse};
