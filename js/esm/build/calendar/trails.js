import{monthLength as p}from"../daterange/calendar";/**
 * The Trilhas view's model (docs/trail/PLAN.md section 7): the answer's shape, its address, and where each step's bar
 * goes on the month. Pure: no layout is read, the bars are grid columns.
 *
 * - The view's trail and "Só as minhas" are navigation, like the view: in the address (`trail=`, `mine=1`), never in
 *   the remembered state (local\calendar\page::trail_from_request(), mine_from_request()).
 * - A step's days are read in the viewer's timezone (days.ts zone()), as every item of the calendar.
 * - A bar runs from its step's start to its end, clipped at the month's edges: a step that began earlier is marked
 *   `before`, one that ends in a later month `after` (drawn into the "next month" column), and one with no end `open`
 *   (it fades at the month's edge). A step with an end and no start is a milestone on its end; an expected date drawn as
 *   the bar's end is a milestone too.
 *
 * @module     theme_dimensions/calendar/trails
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const l="trail",s="mine",b=r=>{const e=p(Number(r.slice(0,4)),Number(r.slice(5,7)));return Array.from({length:e},(t,n)=>`${r}-${String(n+1).padStart(2,"0")}`)},f=(r,e,t)=>{const n=e[0],i=e[e.length-1],a=r.start>0?t.day(r.start):null,o=r.end>0?t.day(r.end):null;if(a===null&&o===null||a!==null&&a>i||o!==null&&o<n)return null;if(a===null){const d=e.indexOf(String(o));return{from:d,to:d,before:!1,after:!1,open:!1,milestone:!0}}const m=a<n,u=o===null||o>i,c=m?0:e.indexOf(a);return{from:c,to:Math.max(c,u?e.length-1:e.indexOf(String(o))),before:m,after:u,open:o===null,milestone:r.expected&&o!==null&&o<=i}},x=(r,e)=>{const t=r.findIndex(n=>n.state!=="done"&&(n.end===0||n.end>=e));return t<0?r.length:t},T=(r,e,t,n)=>({month:r,trail:e.trail,mine:e.mine,categoryid:t,recursive:n}),h=(r,e)=>{const t=new URL(r,window.location.href);return t.searchParams.delete(l),t.searchParams.delete(s),e&&e.trail>0&&t.searchParams.set(l,String(e.trail)),e&&e.mine&&t.searchParams.set(s,"1"),t.toString()},y=r=>{const e=new URL(r,window.location.href).searchParams,t=String(e.get(l)||"");return{trail:/^\d{1,10}$/.test(t)?Number(t):0,mine:e.get(s)==="1"}},S=r=>{const e=b(r);return{from:e[0],to:e[e.length-1],truncated:!1,options:[],trails:[],categories:[]}},w=r=>({trail:r?.trail||0,mine:r?.mine===!0}),A=(r,e)=>r.trail>0||r.mine||e!==0;export{s as MINE_PARAM,l as TRAIL_PARAM,f as barOf,S as emptyTrails,w as filterOf,b as monthDays,y as readTrail,x as todayIndex,T as trailArgs,A as trailFiltered,h as withTrail};
