import{say as c}from"../daterange/calendar";import{hex as i}from"./model";import{fill as a}from"./strings";/**
 * How an item looks and reads: its colour class or its category's pair, its place, its time, its labels.
 *
 * Shared by the bars and the day cells, so a bar and a line of the same item never disagree.
 *
 * @module     theme_dimensions/calendar/look
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const u={day:"numeric",month:"long"},d=e=>`dim-cal-key--${e.overdue?"overdue":e.key}`,y=(e,r)=>{if(r.colour!=="category"||e.overdue)return{};const o=e.categoryid||r.courses.get(e.courseid)?.categoryid||0,t=r.categories.get(o),n=t?i(t.bg):null,s=t?i(t.ink):null;return!n||!s?{}:{"--dim-cal-tint":n,"--dim-cal-edge":s,"--dim-cal-ink":s}},g=(e,r)=>{const o=r.categories.get(e),t=o?i(o.bg):null,n=o?i(o.ink):null;return t&&n?{"--dim-cal-cover-bg":t,"--dim-cal-cover-ink":n}:{}},f=(e,r)=>g(e.categoryid,r),S=(e,r,o=!0)=>{if(e.location!=="")return e.location;const t=r.courses.get(e.courseid);if(!t)return"";const n=[t.city,t.venue].filter(s=>s!=="").join(" \xB7 ");return n!==""||!o?n:t.fullname},b=(e,r)=>{const o=r.zone.time(e.timestart);return e.timeduration>0?`${o}\u2013${r.zone.time(e.timestart+e.timeduration)}`:e.overdue?`${r.strings.calendar_overdue} \xB7 ${o}`:a(r.strings.calendar_until,o)},C=(e,r,o,t)=>{const n=c(r,u,t.language),s=r===o?n:a(t.strings.calendar_span,{from:n,to:c(o,u,t.language)});return a(t.strings.calendar_barlabel,{name:e.name,span:s})};export{C as barLabel,g as categoryCover,y as colourStyle,f as coverStyle,d as keyClass,S as placeOf,b as whenOf};
