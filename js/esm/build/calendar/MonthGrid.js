import{useEffect as W,useLayoutEffect as q,useMemo as E,useRef as b,useState as A}from"react";import{WEEK as H,say as G,shift as u,shiftMonth as O}from"../daterange/calendar";import{ROW_DEFAULT as U,barSlots as z,dayCounts as I,weekLayout as $}from"./layout";import B from"./WeekRow";import{jsx as m,jsxs as Q}from"react/jsx-runtime";/**
 * The month grid and its keyboard (docs/calendar/PROPOSAL.md section 11.3).
 *
 * role="grid" labelled by the month's caption, a columnheader row of weekdays, then a row per week whose seven
 * gridcells each hold one day button. One day button is in the Tab order, the selected day's (roving tabindex):
 *
 * - ArrowLeft and ArrowRight move a day, ArrowUp and ArrowDown a week, Home and End to the week's first and last
 *   day; a move past the grid's days goes to that day's month (the page asks the range for it);
 * - PageUp and PageDown go to the previous and next month, on the same day number or the month's last;
 * - Enter and Space press the day button, which opens the day (core's day view in C2, the drawer in C3);
 * - F2 on a day that has items moves into them: the first item of the day takes the focus, Tab and Shift+Tab cycle
 *   the day's items (its bars, its own items, its "+N"), Escape goes back to the day. The items are links and
 *   buttons out of the Tab order, so Tab from a day leaves the grid as from any other control.
 *
 * The rows' height is measured by ONE ResizeObserver on the rows' container, never per cell and never read on the
 * mount path (its first entry is the first measure); until then the stylesheet's height stands (layout.ROW_DEFAULT).
 *
 * @module     theme_dimensions/calendar/MonthGrid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const C={weekday:"short"},Y=(f,r,l)=>{switch(f){case"ArrowLeft":return{day:u(r,-1),month:!1};case"ArrowRight":return{day:u(r,1),month:!1};case"ArrowUp":return{day:u(r,-H),month:!1};case"ArrowDown":return{day:u(r,H),month:!1};case"Home":return{day:l[0],month:!1};case"End":return{day:l[l.length-1],month:!1};case"PageUp":return{day:O(r,-1),month:!0};case"PageDown":return{day:O(r,1),month:!0};default:return null}},J=f=>{const{look:r,month:l,weeks:n,placed:i,today:_,selected:y,focusing:v,captionid:K,onSelect:R,onMonth:S,onOpen:P}=f,[M,N]=A(U),[h,g]=A(null),d=b(null),D=b(null),p=b(n.length);p.current=n.length;const w=E(()=>n.flat(),[n]),x=E(()=>I(w,i),[w,i]),L=z(M),F=E(()=>n.map(e=>$(e,i,L)),[n,i,L]);W(()=>{const e=D.current;if(!e||typeof ResizeObserver>"u")return;const t=new ResizeObserver(a=>{const c=a[a.length-1].contentRect;c.height>0&&p.current>0&&N(Math.round(c.height/p.current))});return t.observe(e),()=>t.disconnect()},[]),q(()=>{v&&d.current?.querySelector(`[data-role="day"][data-day="${y}"]`)?.focus()},[v]);const T=e=>Array.from(d.current?.querySelectorAll("[data-cal-item]")||[]).filter(t=>(t.dataset.first||"")<=e&&e<=(t.dataset.last||""));return Q("div",{ref:d,className:"dim-cal__grid",role:"grid","aria-labelledby":K,"data-region":"dim-cal-grid",onKeyDown:e=>{const t=e.target;if(h!==null&&t.hasAttribute("data-cal-item")){const o=T(h);if(e.key==="Tab"&&o.length){e.preventDefault();const k=o.indexOf(t);o[(k+(e.shiftKey?o.length-1:1))%o.length].focus()}else if(e.key==="Escape"){e.preventDefault();const k=h;g(null),d.current?.querySelector(`[data-role="day"][data-day="${k}"]`)?.focus()}return}if(t.dataset.role!=="day")return;const a=t.dataset.day||y;if(e.key==="F2"){const o=T(a);o.length&&(e.preventDefault(),g(a),o[0].focus());return}const c=n.find(o=>o.includes(a))||n[0],s=Y(e.key,a,c);!s||s.day===""||(e.preventDefault(),s.month||!w.includes(s.day)?S(s.day):R(s.day))},onBlur:e=>{d.current?.contains(e.relatedTarget)||g(null)},children:[m("div",{className:"dim-cal__weekdays",role:"row",children:n[0].map(e=>m("div",{className:"dim-cal__weekday",role:"columnheader",children:G(e,C,r.language)},e))}),m("div",{ref:D,className:"dim-cal__weeks",role:"rowgroup",children:n.map((e,t)=>m(B,{week:e,layout:F[t],height:M,month:l,today:_,selected:y,counts:x,look:r,onOpen:P},e[0]))})]})};var te=J;export{te as default,Y as stepFrom};
