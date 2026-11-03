import{WEEK as p,format as l,shift as g,weeks as b}from"../daterange/calendar";/**
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
 */const y="--dim-cal-gridheight",w=t=>({[y]:t>0?`${t}px`:void 0}),f=t=>({y:Number(t.slice(0,4)),m:Number(t.slice(5,7))}),x=(t,e)=>{const r=f(t),n=r.y*12+(r.m-1)+e;return l({y:Math.floor(n/12),m:n%12+1,d:1}).slice(0,7)},D=(t,e)=>{const r=b(f(t),e),n=`${t}-01`,u=r[0].indexOf(n),a=g(n,-u);return r.map((m,o)=>Array.from({length:p},(s,i)=>g(a,o*p+i)))},T=t=>{const e=new Date(Date.UTC(Number(t.slice(0,4)),Number(t.slice(5,7))-1,Number(t.slice(8,10)))).getUTCDay();return e===0||e===6},c=(t,e,r)=>{try{return new Intl.DateTimeFormat(t,{...e,timeZone:r})}catch{return new Intl.DateTimeFormat(t,{...e,timeZone:"UTC"})}},C=(t,e)=>{const r=c("en-CA",{year:"numeric",month:"2-digit",day:"2-digit"},t),n=c(e,{hour:"2-digit",minute:"2-digit"},t),u=c("en-GB",{hour:"2-digit",minute:"2-digit",hourCycle:"h23"},t),a=new Map;return{day:m=>{const o=Math.floor(m);let s=a.get(o);if(s===void 0){const i=Object.fromEntries(r.formatToParts(new Date(o*1e3)).map(d=>[d.type,d.value]));s=`${i.year}-${i.month}-${i.day}`,a.set(o,s)}return s},time:m=>n.format(new Date(m*1e3)),minutes:m=>{const o=Object.fromEntries(u.formatToParts(new Date(Math.floor(m)*1e3)).map(s=>[s.type,s.value]));return Number(o.hour)%24*60+Number(o.minute)}}};export{y as GRID_HEIGHT,w as frameStyle,D as gridWeeks,T as isWeekend,x as monthAfter,f as monthParts,C as zone};
