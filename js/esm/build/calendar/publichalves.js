/**
 * The public calendar's two halves: the halves asked for the keys on, their addresses and their merge.
 *
 * The visitor's answer is two halves that share no item, each a complete answer of one key under an address of its own
 * that a CDN may keep: "events" answers the key "category" (and carries the category events' descriptions), "courses"
 * the key "open" (and carries the course dictionary). {@see merge()} is the answer of both, and must stay in step with
 * classes/local/calendar/publicrange.php merge(), which the server's first paint is: one half is returned as is; two
 * are concatenated, the items sorted by start, source and id.
 *
 * Pure: nothing here fetches, reads the page or keeps state.
 *
 * @module     theme_dimensions/calendar/publichalves
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const d=["events","courses"],u={events:"category",courses:"open"},m=e=>d.filter(t=>e.includes(u[t])),g=(e,t,n)=>`${e}/${t}/${n}`,p=(e,t)=>e===t?0:e<t?-1:1,f=(e,t)=>e.timestart-t.timestart||p(e.source,t.source)||e.id-t.id,y=e=>{if(e.length===1)return e[0];const t=new Map;e.forEach(r=>r.categories.forEach(s=>t.set(s.id,s)));const n=e[0];return{from:n.from,to:n.to,truncated:e.some(r=>r.truncated),events:e.flatMap(r=>r.events).sort(f),courses:e.flatMap(r=>r.courses),categories:[...t.values()].sort((r,s)=>r.id-s.id),days:n.days.map((r,s)=>{const o={};return e.forEach(i=>Object.entries(i.days[s]?.counts||{}).forEach(([c,a])=>{o[c]=(o[c]||0)+a})),{date:r.date,counts:o}}),editing:[],descriptions:e.flatMap(r=>r.descriptions)}};export{d as HALVES,u as KEY_OF,g as halfUrl,m as halvesOn,f as itemOrder,y as merge};
