import{say as c}from"../daterange/calendar";import{hex as i}from"./model";import{fill as a}from"./strings";/**
 * How an item looks and reads: its colour class or its category's pair, its place, its time, its labels.
 *
 * Shared by the bars and the day cells, so a bar and a line of the same item never disagree.
 *
 * @module     theme_dimensions/calendar/look
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const u={day:"numeric",month:"long"},p=e=>`dim-cal-key--${e.overdue?"overdue":e.key}`,d=(e,t)=>{if(t.colour!=="category"||e.overdue)return{};const o=e.categoryid||t.courses.get(e.courseid)?.categoryid||0,r=t.categories.get(o),n=r?i(r.bg):null,s=r?i(r.ink):null;return!n||!s?{}:{"--dim-cal-tint":n,"--dim-cal-edge":s,"--dim-cal-ink":s}},y=(e,t)=>{const o=t.categories.get(e.categoryid),r=o?i(o.bg):null,n=o?i(o.ink):null;return r&&n?{"--dim-cal-cover-bg":r,"--dim-cal-cover-ink":n}:{}},f=(e,t,o=!0)=>{if(e.location!=="")return e.location;const r=t.courses.get(e.courseid);if(!r)return"";const n=[r.city,r.venue].filter(s=>s!=="").join(" \xB7 ");return n!==""||!o?n:r.fullname},S=(e,t)=>{const o=t.zone.time(e.timestart);return e.timeduration>0?`${o}\u2013${t.zone.time(e.timestart+e.timeduration)}`:e.overdue?`${t.strings.calendar_overdue} \xB7 ${o}`:a(t.strings.calendar_until,o)},b=(e,t,o,r)=>{const n=c(t,u,r.language),s=t===o?n:a(r.strings.calendar_span,{from:n,to:c(o,u,r.language)});return a(r.strings.calendar_barlabel,{name:e.name,span:s})};export{b as barLabel,d as colourStyle,y as coverStyle,p as keyClass,f as placeOf,S as whenOf};
