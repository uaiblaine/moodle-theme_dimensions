import{fetchOne as o}from"@moodle/lms/core/ajax";/**
 * An event's description, read lazily from core and once per page life.
 *
 * The range answers no description (PROPOSAL.md section 5.5): the popover asks core's
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
 */const c="core_calendar_get_calendar_event_by_id",t=new Map,s=n=>{let e=t.get(n);return e===void 0&&(e=o({methodname:c,args:{eventid:n}}).then(r=>String(r?.event?.description||"")).catch(()=>""),t.set(n,e)),e},a=()=>t.clear();export{c as EVENT_METHOD,s as description,a as forget};
