import{say as i}from"../daterange/calendar";import{hex as c}from"./model";import{fill as a}from"./strings";/**
 * How an item looks and reads: its colour class or its category's pair, its place, its time, its labels.
 *
 * Shared by the bars and the day cells, so a bar and a line of the same item never disagree.
 *
 * @module     theme_dimensions/calendar/look
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const u={day:"numeric",month:"long"},p=r=>`dim-cal-key--${r.overdue?"overdue":r.key}`,d=(r,e)=>{if(e.colour!=="category"||r.overdue)return{};const t=r.categoryid||e.courses.get(r.courseid)?.categoryid||0,o=e.categories.get(t),n=o?c(o.bg):null,s=o?c(o.ink):null;return!n||!s?{}:{"--dim-cal-tint":n,"--dim-cal-edge":s,"--dim-cal-ink":s}},y=(r,e)=>{if(r.location!=="")return r.location;const t=e.courses.get(r.courseid);return t?[t.city,t.venue].filter(o=>o!=="").join(" \xB7 ")||t.fullname:""},f=(r,e)=>{const t=e.zone.time(r.timestart);return r.timeduration>0?`${t}\u2013${e.zone.time(r.timestart+r.timeduration)}`:r.overdue?`${e.strings.calendar_overdue} \xB7 ${t}`:a(e.strings.calendar_until,t)},b=(r,e,t,o)=>{const n=i(e,u,o.language),s=e===t?n:a(o.strings.calendar_span,{from:n,to:i(t,u,o.language)});return a(o.strings.calendar_barlabel,{name:r.name,span:s})};export{b as barLabel,d as colourStyle,p as keyClass,y as placeOf,f as whenOf};
