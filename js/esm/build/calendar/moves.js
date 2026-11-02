/**
 * What the page may offer to edit, and the instants core's calendar services are handed: pure, no request.
 *
 * Every decision here is core's, read from the answer (`editing`, local\calendar\editing::entries()): an item is
 * edited, deleted or dragged only when core's month view would. What is computed here is the guard of a drop (the
 * limits core gave, as core's month_view_drag_drop checks them before it asks) and the instants core's form and
 * core_calendar_update_event_start_day take, which are timestamps: the day the reader picked, in the VIEWER's
 * timezone (Intl's zone data, as days.ts reads instants), never the browser's.
 *
 * @module     theme_dimensions/calendar/moves
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const c=720,b=t=>new Map((t.editing||[]).map(n=>[n.id,n])),d=(t,n)=>n.source==="core"&&t.get(n.id)||null,p=(t,n)=>t.minday!==""&&n<t.minday?"min":t.maxday!==""&&n>t.maxday?"max":null,s=t=>{const n={year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit",second:"2-digit",hourCycle:"h23"};try{return new Intl.DateTimeFormat("en-GB",{...n,timeZone:t})}catch{return new Intl.DateTimeFormat("en-GB",{...n,timeZone:"UTC"})}},u=(t,n)=>{const e=Object.fromEntries(t.formatToParts(new Date(n*1e3)).map(r=>[r.type,r.value]));return Date.UTC(Number(e.year),Number(e.month)-1,Number(e.day),Number(e.hour)%24,Number(e.minute),Number(e.second))/1e3},o=(t,n,e)=>{const r=s(e),m=Date.UTC(Number(t.slice(0,4)),Number(t.slice(5,7))-1,Number(t.slice(8,10)),Math.floor(n/60),n%60)/1e3,i=m-(u(r,m)-m);return m-(u(r,i)-i)},g=(t,n,e,r,m)=>t.includes(n)||!t.length?null:o(t[0],r.minutes(e),m),a=(t,n,e,r)=>o(t,e.minutes(n),r),l=(t,n,e)=>o(t,n*60,e),f=(t,n,e,r,m)=>n===null?a(t,e,r,m):l(t,n,m);export{c as MOVE_MINUTE,f as createStart,a as dayStart,p as dropRefusal,d as editOf,b as editsOf,l as hourStart,o as instantOf,g as toolbarStart};
