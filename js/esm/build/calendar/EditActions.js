import{useEffect as r,useRef as s}from"react";import c from"@moodle/lms/core/pending";import{armDelete as m}from"./editing";import{jsx as i,jsxs as f}from"react/jsx-runtime";/**
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
 */const u=e=>e.replace(/[&<>"']/g,t=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"})[t]),p=({item:e,edit:t,strings:o,onEdit:a,onFailure:l})=>{const n=s(null);return r(()=>{if(!t.delete||!n.current)return;const d=new c("theme_dimensions/calendar:delete");m(n.current).catch(l).finally(()=>d.resolve())},[t.delete]),!t.edit&&!t.delete?null:f("div",{ref:n,className:"dim-cal__popedit","data-region":"dim-cal-edit","data-type":"event","data-event-id":e.id,"data-event-title":u(e.name),"data-event-count":t.count,children:[t.edit?i("button",{type:"button",className:"btn btn-secondary","data-action":"dim-cal-edit",onClick:a,children:o.calendar_edit}):null,t.delete?i("button",{type:"button",className:"btn btn-outline-danger","data-action":"delete",children:o.calendar_delete}):null]})};var y=p;export{y as default};
