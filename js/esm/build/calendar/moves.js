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
 */const s=720,l=t=>new Map((t.editing||[]).map(e=>[e.id,e])),c=(t,e)=>e.source==="core"&&t.get(e.id)||null,d=(t,e)=>t.minday!==""&&e<t.minday?"min":t.maxday!==""&&e>t.maxday?"max":null,a=t=>{const e={year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit",second:"2-digit",hourCycle:"h23"};try{return new Intl.DateTimeFormat("en-GB",{...e,timeZone:t})}catch{return new Intl.DateTimeFormat("en-GB",{...e,timeZone:"UTC"})}},i=(t,e)=>{const n=Object.fromEntries(t.formatToParts(new Date(e*1e3)).map(r=>[r.type,r.value]));return Date.UTC(Number(n.year),Number(n.month)-1,Number(n.day),Number(n.hour)%24,Number(n.minute),Number(n.second))/1e3},u=(t,e,n)=>{const r=a(n),m=Date.UTC(Number(t.slice(0,4)),Number(t.slice(5,7))-1,Number(t.slice(8,10)),Math.floor(e/60),e%60)/1e3,o=m-(i(r,m)-m);return m-(i(r,o)-o)},b=(t,e,n,r,m)=>t.includes(e)||!t.length?null:u(t[0],r.minutes(n),m),p=(t,e,n,r)=>u(t,n.minutes(e),r);export{s as MOVE_MINUTE,p as dayStart,d as dropRefusal,c as editOf,l as editsOf,u as instantOf,b as toolbarStart};
