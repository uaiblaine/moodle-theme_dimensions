import{WEEK as d,format as p,shift as u,weeks as y}from"../daterange/calendar";/**
 * The calendar page's days: the month grid, and the day and the time an instant falls on for the viewer.
 *
 * The grid is the showcase's timezone-free date core (daterange/calendar.ts, imported, never copied): weeks() lays the
 * month out on the site's first weekday, and the blanks it leaves are filled with the neighbouring months' days, the
 * same whole weeks local\calendar\page::grid() asks the range for. The browser's clock and its own timezone are never
 * read: today arrives from the server, and an instant is read in the VIEWER's timezone, which the server names, through
 * Intl's own zone data (a formatter, no arithmetic of ours); a zone Intl does not know reads as UTC.
 *
 * @module     theme_dimensions/calendar/days
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const f=t=>({y:Number(t.slice(0,4)),m:Number(t.slice(5,7))}),b=(t,r)=>{const e=f(t),n=e.y*12+(e.m-1)+r;return p({y:Math.floor(n/12),m:n%12+1,d:1}).slice(0,7)},h=(t,r)=>{const e=y(f(t),r),n=`${t}-01`,i=e[0].indexOf(n),s=u(n,-i);return e.map((m,o)=>Array.from({length:d},(a,c)=>u(s,o*d+c)))},g=(t,r,e)=>{try{return new Intl.DateTimeFormat(t,{...r,timeZone:e})}catch{return new Intl.DateTimeFormat(t,{...r,timeZone:"UTC"})}},w=(t,r)=>{const e=g("en-CA",{year:"numeric",month:"2-digit",day:"2-digit"},t),n=g(r,{hour:"2-digit",minute:"2-digit"},t),i=new Map;return{day:s=>{const m=Math.floor(s);let o=i.get(m);if(o===void 0){const a=Object.fromEntries(e.formatToParts(new Date(m*1e3)).map(c=>[c.type,c.value]));o=`${a.year}-${a.month}-${a.day}`,i.set(m,o)}return o},time:s=>n.format(new Date(s*1e3))}};export{h as gridWeeks,b as monthAfter,f as monthParts,w as zone};
