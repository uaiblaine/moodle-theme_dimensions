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
 * The same rules as amd/src/daterange.js, whose period() both listing clients still read an address
 * through (docs/vitrine-react/PLAN.md, R4a): {@see period()} is criteria's reading of a period in both.
 *
 * @module     theme_dimensions/daterange/calendar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const x=7,h="2026-01-04",i=(t,r)=>new Date(Date.UTC(t,r,0)).getUTCDate(),s=t=>{const r=/^(\d{4})-(\d{2})-(\d{2})$/.exec(String(t||""));if(!r)return null;const n={y:Number(r[1]),m:Number(r[2]),d:Number(r[3])};return n.m<1||n.m>12||n.d<1||n.d>i(n.y,n.m)?null:n},u=t=>String(t.y).padStart(4,"0")+"-"+String(t.m).padStart(2,"0")+"-"+String(t.d).padStart(2,"0"),c=t=>new Date(Date.UTC(t.y,t.m-1,t.d)),l=(t,r)=>{const n=s(t);if(!n)return"";const e=c(n);return e.setUTCDate(e.getUTCDate()+r),u({y:e.getUTCFullYear(),m:e.getUTCMonth()+1,d:e.getUTCDate()})},f=(t,r)=>{const n=s(t);if(!n)return"";const e=c({y:n.y,m:n.m,d:1});e.setUTCMonth(e.getUTCMonth()+r);const m=e.getUTCFullYear(),o=e.getUTCMonth()+1;return u({y:m,m:o,d:Math.min(n.d,i(m,o))})},E=(t,r)=>{const n=s(t)?String(t):"",e=s(r)?String(r):"";return n!==""&&e!==""&&e<n?{from:e,to:n}:{from:n,to:e}},D=(t,r,n)=>{const e=s(t);if(!e)return"";try{return new Intl.DateTimeFormat(n,{timeZone:"UTC",...r}).format(c(e))}catch{return t}},y=t=>{const r=s(t);return{y:r.y,m:r.m}},p=t=>u({y:t.y,m:t.m,d:1}),d=(t,r)=>y(f(p(t),r)),b=(t,r,n)=>{const e=d(t,r-1);return n>=p(t)&&n<=u({y:e.y,m:e.m,d:i(e.y,e.m)})},M=(t,r,n)=>!s(n)||b(t,r,n)?t:n<p(t)?y(n):d(y(n),1-r),T=t=>Array.from({length:7},(r,n)=>l(h,(t+n)%7)),C=(t,r)=>{const n=(c({y:t.y,m:t.m,d:1}).getUTCDay()-r+7)%7,e=i(t.y,t.m),m=Math.ceil((n+e)/7)*7,o=[];for(let a=0;a<m;a++){a%7===0&&o.push([]);const g=a-n+1;o[o.length-1].push(g>=1&&g<=e?u({y:t.y,m:t.m,d:g}):"")}return o};export{x as WEEK,M as bringIntoView,p as firstOf,u as format,d as monthAt,i as monthLength,y as monthOf,b as onScreen,s as parse,E as period,D as say,l as shift,f as shiftMonth,c as utc,T as weekdays,C as weeks};
