/**
 * The calendar arithmetic of the showcase's period control, with no timezone in it.
 *
 * Every boundary of the period filter is resolved by PHP in the site's timezone; what the client
 * computes is the calendar itself - the weekday of a date, the length of a month, the day either
 * side of one - on the {y, m, d} of an ISO string through Date.UTC. UTC has no offset and no daylight
 * saving, so a day added is a day added whatever the reader's own timezone; a local-time Date would
 * shift a calendar square by that offset. The browser's clock is never read: the day the site calls
 * today arrives from the server.
 *
 * {@see period()} is criteria's reading of a period, the one both shells read an address through (the listing's
 * store and its shells).
 *
 * @module     theme_dimensions/daterange/calendar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const D=7,f="2026-01-04",u=(t,e)=>new Date(Date.UTC(t,e,0)).getUTCDate(),s=t=>{const e=/^(\d{4})-(\d{2})-(\d{2})$/.exec(String(t||""));if(!e)return null;const n={y:Number(e[1]),m:Number(e[2]),d:Number(e[3])};return n.m<1||n.m>12||n.d<1||n.d>u(n.y,n.m)?null:n},i=t=>String(t.y).padStart(4,"0")+"-"+String(t.m).padStart(2,"0")+"-"+String(t.d).padStart(2,"0"),c=t=>new Date(Date.UTC(t.y,t.m-1,t.d)),h=(t,e)=>{const n=s(t);if(!n)return"";const r=c(n);return r.setUTCDate(r.getUTCDate()+e),i({y:r.getUTCFullYear(),m:r.getUTCMonth()+1,d:r.getUTCDate()})},T=(t,e)=>{const n=s(t);if(!n)return"";const r=c({y:n.y,m:n.m,d:1});r.setUTCMonth(r.getUTCMonth()+e);const m=r.getUTCFullYear(),o=r.getUTCMonth()+1;return i({y:m,m:o,d:Math.min(n.d,u(m,o))})},E=(t,e)=>{const n=s(t)?String(t):"",r=s(e)?String(e):"";return n!==""&&r!==""&&r<n?{from:r,to:n}:{from:n,to:r}},p=new Map,b=(t,e)=>{const n=JSON.stringify([e??null,t]);let r=p.get(n);return r||(r=new Intl.DateTimeFormat(e,{timeZone:"UTC",...t}),p.set(n,r)),r},M=(t,e,n)=>{const r=s(t);if(!r)return"";try{return b(e,n).format(c(r))}catch{return t}},y=t=>{const e=s(t);return{y:e.y,m:e.m}},d=t=>i({y:t.y,m:t.m,d:1}),l=(t,e)=>y(T(d(t),e)),x=(t,e,n)=>{const r=l(t,e-1);return n>=d(t)&&n<=i({y:r.y,m:r.m,d:u(r.y,r.m)})},C=(t,e,n)=>!s(n)||x(t,e,n)?t:n<d(t)?y(n):l(y(n),1-e),U=t=>Array.from({length:7},(e,n)=>h(f,(t+n)%7)),S=(t,e)=>{const n=(c({y:t.y,m:t.m,d:1}).getUTCDay()-e+7)%7,r=u(t.y,t.m),m=Math.ceil((n+r)/7)*7,o=[];for(let a=0;a<m;a++){a%7===0&&o.push([]);const g=a-n+1;o[o.length-1].push(g>=1&&g<=r?i({y:t.y,m:t.m,d:g}):"")}return o};export{D as WEEK,C as bringIntoView,d as firstOf,i as format,l as monthAt,u as monthLength,y as monthOf,x as onScreen,s as parse,E as period,M as say,h as shift,T as shiftMonth,c as utc,U as weekdays,S as weeks};
