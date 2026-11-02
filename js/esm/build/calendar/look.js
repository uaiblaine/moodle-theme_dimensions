import{say as c}from"../daterange/calendar";import{hex as i}from"./model";import{fill as a}from"./strings";/**
 * How an item looks and reads: its colour class or its category's pair, its place, its time, its labels.
 *
 * Shared by the bars and the day cells, so a bar and a line of the same item never disagree.
 *
 * @module     theme_dimensions/calendar/look
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const g={day:"numeric",month:"long"},p=e=>`dim-cal-key--${e.overdue?"overdue":e.key}`,d=(e,t)=>{if(t.colour!=="category"||e.overdue)return{};const r=e.categoryid||t.courses.get(e.courseid)?.categoryid||0,o=t.categories.get(r),n=o?i(o.bg):null,s=o?i(o.ink):null;return!n||!s?{}:{"--dim-cal-tint":n,"--dim-cal-edge":s,"--dim-cal-ink":s}},y=(e,t)=>{const r=t.categories.get(e.categoryid),o=r?i(r.bg):null,n=r?i(r.ink):null;return o&&n?{"--dim-cal-cover-bg":o,"--dim-cal-cover-ink":n}:{}},f=(e,t)=>{if(e.location!=="")return e.location;const r=t.courses.get(e.courseid);return r?[r.city,r.venue].filter(o=>o!=="").join(" \xB7 ")||r.fullname:""},S=(e,t)=>{const r=t.zone.time(e.timestart);return e.timeduration>0?`${r}\u2013${t.zone.time(e.timestart+e.timeduration)}`:e.overdue?`${t.strings.calendar_overdue} \xB7 ${r}`:a(t.strings.calendar_until,r)},b=(e,t,r,o)=>{const n=c(t,g,o.language),s=t===r?n:a(o.strings.calendar_span,{from:n,to:c(r,g,o.language)});return a(o.strings.calendar_barlabel,{name:e.name,span:s})};export{b as barLabel,d as colourStyle,y as coverStyle,p as keyClass,f as placeOf,S as whenOf};
