/**
 * One day's items, pure: the all-day strip, the hour column in columns, and who overlaps whom
 * (docs/calendar/PROPOSAL.md section 13.2).
 *
 * - The ALL-DAY strip holds every item touching the day that is a bar of the month grid (it spans more than one day,
 *   or it is an event course) or that fills whole days ({@see isAllDay()}).
 * - The HOUR column holds the day's other items. Those with a duration are laid out in columns: sorted by start,
 *   then longer first, an item takes the first column whose last item ended at or before its start, and a cluster
 *   of items that overlap one another (directly or through each other) shares one column count. A point item (a
 *   deadline, duration 0) sits in the column at its time, alone in its own cluster.
 * - Two items OVERLAP when both have a duration and each starts before the other ends: touching ends do not, a
 *   point item never does, and an all-day or multi-day item never counts.
 *
 * @module     theme_dimensions/calendar/overlap
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const p=t=>`${t.source}-${t.id}`,h=(t,e)=>{if(t.timeduration<=0)return!1;const a=t.timestart+t.timeduration;return e.minutes(t.timestart)===0&&e.minutes(a)===0&&e.day(t.timestart)!==e.day(a)},x=(t,e)=>t.timeduration>0&&e.timeduration>0&&t.timestart<e.timestart+e.timeduration&&e.timestart<t.timestart+t.timeduration,f=(t,e)=>t.item.timestart-e.item.timestart||e.item.timeduration-t.item.timeduration||t.item.name.localeCompare(e.item.name)||t.item.id-e.item.id,P=t=>{const e=[...t].sort(f),a=[];let i=[],o=[],r=-1/0;const m=()=>{i.forEach(n=>{n.columns=o.length}),i=[],o=[]};return e.forEach(n=>{if(n.item.timeduration<=0){a.push({entry:n,column:0,columns:1,overlaps:[]});return}const d=n.item.timestart,c=d+n.item.timeduration;d>=r&&m();let s=o.findIndex(l=>l<=d);s<0?(s=o.length,o.push(c)):o[s]=c;const u={entry:n,column:s,columns:1,overlaps:e.filter(l=>l!==n&&x(n.item,l.item))};i.push(u),a.push(u),r=Math.max(r,c)}),m(),a},y=(t,e,a)=>{const i=[],o=[];return e.forEach(r=>{r.first>t||r.last<t||(r.bar||h(r.item,a)?i.push(r):o.push(r))}),i.sort(f),{allday:i,hours:P(o)}},I=4,M=(t,e)=>{if(!t.length)return{from:0,to:24};let a=1440,i=0;t.forEach(m=>{const n=e.minutes(m.entry.item.timestart);a=Math.min(a,n),i=Math.max(i,Math.min(1440,n+Math.max(m.entry.item.timeduration/60,0)))});let o=Math.max(0,Math.floor(a/60)-1),r=Math.min(24,Math.ceil(i/60)+1);return r-o<4&&(r=Math.min(24,o+4),o=Math.max(0,r-4)),{from:o,to:r}},W=(t,e,a)=>{const i=p(t.item),o=y(t.first,e,a).hours.find(r=>p(r.entry.item)===i);return o?o.overlaps:[]};export{I as MIN_WINDOW,P as columns,y as dayPlan,M as hourWindow,x as intersects,h as isAllDay,p as itemKey,W as overlapsOf};
