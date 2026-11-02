import{WEEK as g,format as l,shift as b,weeks as p}from"../daterange/calendar";/**
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
 */const f=t=>({y:Number(t.slice(0,4)),m:Number(t.slice(5,7))}),h=(t,e)=>{const r=f(t),n=r.y*12+(r.m-1)+e;return l({y:Math.floor(n/12),m:n%12+1,d:1}).slice(0,7)},w=(t,e)=>{const r=p(f(t),e),n=`${t}-01`,u=r[0].indexOf(n),a=b(n,-u);return r.map((s,o)=>Array.from({length:g},(m,i)=>b(a,o*g+i)))},D=t=>{const e=new Date(Date.UTC(Number(t.slice(0,4)),Number(t.slice(5,7))-1,Number(t.slice(8,10)))).getUTCDay();return e===0||e===6},c=(t,e,r)=>{try{return new Intl.DateTimeFormat(t,{...e,timeZone:r})}catch{return new Intl.DateTimeFormat(t,{...e,timeZone:"UTC"})}},T=(t,e)=>{const r=c("en-CA",{year:"numeric",month:"2-digit",day:"2-digit"},t),n=c(e,{hour:"2-digit",minute:"2-digit"},t),u=c("en-GB",{hour:"2-digit",minute:"2-digit",hourCycle:"h23"},t),a=new Map;return{day:s=>{const o=Math.floor(s);let m=a.get(o);if(m===void 0){const i=Object.fromEntries(r.formatToParts(new Date(o*1e3)).map(d=>[d.type,d.value]));m=`${i.year}-${i.month}-${i.day}`,a.set(o,m)}return m},time:s=>n.format(new Date(s*1e3)),minutes:s=>{const o=Object.fromEntries(u.formatToParts(new Date(Math.floor(s)*1e3)).map(m=>[m.type,m.value]));return Number(o.hour)%24*60+Number(o.minute)}}};export{w as gridWeeks,D as isWeekend,h as monthAfter,f as monthParts,T as zone};
