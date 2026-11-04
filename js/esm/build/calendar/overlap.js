/**
 * One day's items, pure: the all-day strip, the hour column in columns, and who overlaps whom.
 *
 * - The ALL-DAY strip holds every item touching the day that is a bar of the month grid (it spans more than one day,
 *   or it is an event course) or that fills whole days ({@see isAllDay()}).
 * - The HOUR column holds the day's other items. Those with a duration are laid out in columns: sorted by start,
 *   then longer first, an item takes the first column whose last item ended at or before its start, and a cluster
 *   of items that overlap one another (directly or through each other) shares one column count. A point item (a
 *   deadline, duration 0) sits in the column at its time, alone in its own cluster.
 * - Two items OVERLAP when both have a duration and each starts before the other ends: touching ends do not, a
 *   point item never does, and an all-day or multi-day item never counts.
 * - The hour column draws the whole day in a scroll box:
 *   the busy band (hourWindow()) or the working hours (EMPTY_WINDOW) is where the box starts ({@see boxTopOf()}),
 *   and a fade marks each end it can still scroll to ({@see edgesOf()}).
 *
 * @module     theme_dimensions/calendar/overlap
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const p=t=>`${t.source}-${t.id}`,h=(t,e)=>{if(t.timeduration<=0)return!1;const o=t.timestart+t.timeduration;return e.minutes(t.timestart)===0&&e.minutes(o)===0&&e.day(t.timestart)!==e.day(o)},x=(t,e)=>t.timeduration>0&&e.timeduration>0&&t.timestart<e.timestart+e.timeduration&&e.timestart<t.timestart+t.timeduration,f=(t,e)=>t.item.timestart-e.item.timestart||e.item.timeduration-t.item.timeduration||t.item.name.localeCompare(e.item.name)||t.item.id-e.item.id,M=t=>{const e=[...t].sort(f),o=[];let i=[],r=[],n=-1/0;const m=()=>{i.forEach(a=>{a.columns=r.length}),i=[],r=[]};return e.forEach(a=>{if(a.item.timeduration<=0){o.push({entry:a,column:0,columns:1,overlaps:[]});return}const u=a.item.timestart,d=u+a.item.timeduration;u>=n&&m();let s=r.findIndex(l=>l<=u);s<0?(s=r.length,r.push(d)):r[s]=d;const c={entry:a,column:s,columns:1,overlaps:e.filter(l=>l!==a&&x(a.item,l.item))};i.push(c),o.push(c),n=Math.max(n,d)}),m(),o},P=(t,e,o)=>{const i=[],r=[];return e.forEach(n=>{n.first>t||n.last<t||(n.bar||h(n.item,o)?i.push(n):r.push(n))}),i.sort(f),{allday:i,hours:M(r)}},y=4,I={from:0,to:24},W={from:8,to:18},b=(t,e)=>{if(!t.length)return{from:0,to:24};let o=1440,i=0;t.forEach(m=>{const a=e.minutes(m.entry.item.timestart);o=Math.min(o,a),i=Math.max(i,Math.min(1440,a+Math.max(m.entry.item.timeduration/60,0)))});let r=Math.max(0,Math.floor(o/60)-1),n=Math.min(24,Math.ceil(i/60)+1);return n-r<4&&(n=Math.min(24,r+4),r=Math.max(0,n-4)),{from:r,to:n}},D=(t,e,o)=>{const i=p(t.item),r=P(t.first,e,o).hours.find(n=>p(n.entry.item)===i);return r?r.overlaps:[]},L=(t,e,o,i)=>{if(!(o>0))return 0;const r=t.from*o;if(e)return r;const n=(t.from+t.to)/2*o-i/2;return Math.max(0,Math.min(n,r))},N=(t,e,o)=>({up:t>1,down:t+o<e-1});export{W as EMPTY_WINDOW,I as FULL_DAY,y as MIN_WINDOW,L as boxTopOf,M as columns,P as dayPlan,N as edgesOf,b as hourWindow,x as intersects,h as isAllDay,p as itemKey,D as overlapsOf};
