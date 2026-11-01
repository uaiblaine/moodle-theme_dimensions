/**
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
 *
 * @module     theme_dimensions/calendar/layout
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const x=30,b=24,g=22,E=8,P=78,S=3,M=148,A=4,D=(t,o)=>t.map(a=>{const l=o.day(a.timestart),s=o.day(a.timestart+Math.max(0,a.timeduration-1));return{item:a,first:l,last:s,bar:l!==s||a.source==="course"}}),y=t=>Math.max(1,Math.min(3,Math.floor((t-30-8-22)/24))),h=(t,o)=>t.start-o.start||o.end-o.start-(t.end-t.start)||t.placed.item.name.localeCompare(o.placed.item.name)||t.placed.item.id-o.placed.item.id,L=(t,o,a)=>{const l=t[0],s=t[t.length-1],m=[],i=t.map(()=>[]);o.forEach(e=>{if(e.last<l||e.first>s)return;if(!e.bar){const p=t.indexOf(e.first);p>=0&&i[p].push(e);return}const n=e.first<l?0:t.indexOf(e.first),r=e.last>s?t.length-1:t.indexOf(e.last);m.push({placed:e,start:n,end:r,clipstart:e.first<l,clipend:e.last>s,slot:0})}),m.sort(h);const c=[],u=t.map(()=>0),f=[];let d=0;return m.forEach(e=>{let n=0;for(;;){c[n]=c[n]||t.map(()=>!1);let r=!0;for(let p=e.start;p<=e.end;p++)r=r&&!c[n][p];if(r)break;n++}for(let r=e.start;r<=e.end;r++)c[n][r]=!0;if(n>=a){for(let r=e.start;r<=e.end;r++)u[r]++;return}d=Math.max(d,n+1),f.push({...e,slot:n})}),i.forEach(e=>e.sort((n,r)=>n.item.timestart-r.item.timestart||n.item.name.localeCompare(r.item.name)||n.item.id-r.item.id)),{bars:f,hidden:u,used:d,timed:i}},O=(t,o,a,l)=>{if(!t.length&&!o)return{density:"none",shown:[],more:0,dots:[]};const s=l-30-a*24-8,m=Math.max(1,Math.floor(s/22));if(t.length===1&&!o&&s>=78)return{density:"card",shown:t,more:0,dots:[]};if(t.length<=m&&!o)return{density:"lines",shown:t,more:0,dots:[]};const i=t.slice(0,Math.max(0,m-1)),c=t.slice(i.length);return{density:"summary",shown:i,more:c.length+o,dots:c.slice(0,4).map(u=>u.item.key)}},I=(t,o)=>{const a=new Map(t.map(l=>[l,0]));return o.forEach(l=>{t.forEach(s=>{s>=l.first&&s<=l.last&&a.set(s,(a.get(s)||0)+1)})}),a};export{P as CARD_MIN,A as DOTS,x as HEADER,g as LINE,S as MAX_BARS,E as PAD,M as ROW_DEFAULT,b as SLOT,y as barSlots,O as cellPlan,I as dayCounts,D as place,L as weekLayout};
