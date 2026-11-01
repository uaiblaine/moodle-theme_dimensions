import{KEYS as P}from"./model";/**
 * The month grid's layout, pure: the spanning bars' slots, the density of each day, the day counts.
 *
 * docs/calendar/PROPOSAL.md sections 4 and 5.2, ported from the C0 canvas:
 *
 * - An item that touches more than one day, and every event course, is a BAR; any other item sits in its day's
 *   content. A bar is clipped to each week row it crosses; the segments of a row are sorted by start, then longer
 *   first, then name, and each takes the lowest slot free on all its columns, so a bar keeps one slot across its
 *   row and never zigzags. Slots past the row's capacity ({@see barSlots()}) are hidden and counted per day.
 * - The density of a day comes from its row's height (measured by the grid, one ResizeObserver per grid) and from
 *   what the bars left: one item on a day with room is a CARD, a few that fit are LINES, more than fit is a SUMMARY
 *   (the lines that fit, then "+N" for the rest and the hidden bars, with a dot per key of what was left out).
 * - The count of a day is every item touching it, bars included: the day button's label says it.
 * - On a phone the month draws a dot row per day instead ({@see dayDots()}): a dot per key with items, from the
 *   answer's per-day counts, at most {@see DOTS}, then a "+".
 *
 * @module     theme_dimensions/calendar/layout
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const x=30,b=24,g=22,y=8,M=78,S=3,A=148,f=4,C=(t,o)=>t.map(s=>{const l=o.day(s.timestart),n=o.day(s.timestart+Math.max(0,s.timeduration-1));return{item:s,first:l,last:n,bar:l!==n||s.source==="course"}}),L=t=>Math.max(1,Math.min(S,Math.floor((t-x-y-g)/b))),D=(t,o)=>t.start-o.start||o.end-o.start-(t.end-t.start)||t.placed.item.name.localeCompare(o.placed.item.name)||t.placed.item.id-o.placed.item.id,O=(t,o,s)=>{const l=t[0],n=t[t.length-1],m=[],i=t.map(()=>[]);o.forEach(e=>{if(e.last<l||e.first>n)return;if(!e.bar){const p=t.indexOf(e.first);p>=0&&i[p].push(e);return}const a=e.first<l?0:t.indexOf(e.first),r=e.last>n?t.length-1:t.indexOf(e.last);m.push({placed:e,start:a,end:r,clipstart:e.first<l,clipend:e.last>n,slot:0})}),m.sort(D);const c=[],d=t.map(()=>0),h=[];let u=0;return m.forEach(e=>{let a=0;for(;;){c[a]=c[a]||t.map(()=>!1);let r=!0;for(let p=e.start;p<=e.end;p++)r=r&&!c[a][p];if(r)break;a++}for(let r=e.start;r<=e.end;r++)c[a][r]=!0;if(a>=s){for(let r=e.start;r<=e.end;r++)d[r]++;return}u=Math.max(u,a+1),h.push({...e,slot:a})}),i.forEach(e=>e.sort((a,r)=>a.item.timestart-r.item.timestart||a.item.name.localeCompare(r.item.name)||a.item.id-r.item.id)),{bars:h,hidden:d,used:u,timed:i}},I=(t,o,s,l)=>{if(!t.length&&!o)return{density:"none",shown:[],more:0,dots:[]};const n=l-x-s*b-y,m=Math.max(1,Math.floor(n/g));if(t.length===1&&!o&&n>=M)return{density:"card",shown:t,more:0,dots:[]};if(t.length<=m&&!o)return{density:"lines",shown:t,more:0,dots:[]};const i=t.slice(0,Math.max(0,m-1)),c=t.slice(i.length);return{density:"summary",shown:i,more:c.length+o,dots:c.slice(0,f).map(d=>d.item.key)}},R=(t,o)=>{const s=new Map(t.map(l=>[l,0]));return o.forEach(l=>{t.forEach(n=>{n>=l.first&&n<=l.last&&s.set(n,(s.get(n)||0)+1)})}),s},K=t=>{const o=P.filter(s=>(t?.[s]||0)>0);return{keys:o.slice(0,f),more:o.length>f}};export{M as CARD_MIN,f as DOTS,x as HEADER,g as LINE,S as MAX_BARS,y as PAD,A as ROW_DEFAULT,b as SLOT,L as barSlots,I as cellPlan,R as dayCounts,K as dayDots,C as place,O as weekLayout};
