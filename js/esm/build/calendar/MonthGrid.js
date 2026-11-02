import{useEffect as F,useLayoutEffect as $,useMemo as E,useRef as u,useState as O}from"react";import{WEEK as A,say as q,shift as m,shiftMonth as K}from"../daterange/calendar";import{ROW_DEFAULT as U,barSlots as z,dayCounts as I,weekLayout as B}from"./layout";import{isWeekend as Y}from"./days";import J from"./WeekRow";import{jsx as f,jsxs as Z}from"react/jsx-runtime";/**
 * The month grid and its keyboard (docs/calendar/PROPOSAL.md section 11.3).
 *
 * role="grid" labelled by the month's caption, a columnheader row of weekdays, then a row per week whose seven
 * gridcells each hold one day button. One day button is in the Tab order, the selected day's (roving tabindex):
 *
 * - ArrowLeft and ArrowRight move a day, ArrowUp and ArrowDown a week, Home and End to the week's first and last
 *   day; a move past the grid's days goes to that day's month (the page asks the range for it);
 * - PageUp and PageDown go to the previous and next month, on the same day number or the month's last;
 * - Enter and Space press the day button, which opens the day: the drawer beside the grid, or on a phone the sheet
 *   under it (Calendar.tsx);
 * - A click anywhere on a day but its controls (onGround handed) opens it as its button does, a convenience for the
 *   pointer: the button stays the keyboard's way in. A double-click there opens core's form for a new event on that
 *   day while the page offers "New event" (onCreate handed); the keyboard's way is the drawer's "New event";
 * - F2 on a day that has items moves into them: the first item of the day takes the focus, Tab and Shift+Tab cycle
 *   the day's items (its bars, its own items, its "+N"), Escape goes back to the day. The items are links and
 *   buttons out of the Tab order, so Tab from a day leaves the grid as from any other control.
 *
 * ONE ResizeObserver measures the grid, never per cell and never read on the mount path (its first entries are the
 * first measures): the rows' container, whose height decides the density (until then the stylesheet's height stands,
 * layout.ROW_DEFAULT), and the frame the grid shares with the drawer, whose width decides the phone layout
 * ({@see PHONE}). The frame, not the grid: the open drawer narrows the grid, and the grid's own width would flip the
 * layout to the phone's the moment the drawer opened.
 *
 * @module     theme_dimensions/calendar/MonthGrid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const ae=576,Q={weekday:"short"},V=(i,a,d)=>{switch(i){case"ArrowLeft":return{day:m(a,-1),month:!1};case"ArrowRight":return{day:m(a,1),month:!1};case"ArrowUp":return{day:m(a,-A),month:!1};case"ArrowDown":return{day:m(a,A),month:!1};case"Home":return{day:d[0],month:!1};case"End":return{day:d[d.length-1],month:!1};case"PageUp":return{day:K(a,-1),month:!0};case"PageDown":return{day:K(a,1),month:!0};default:return null}},X=i=>{const{look:a,month:d,weeks:o,placed:c,today:W,selected:y,focusing:v,captionid:P,dots:D,onSelect:R,onMonth:S,onOpen:G}=i,[M,N]=O(U),[h,g]=O(null),l=u(null),L=u(null),p=u(o.length);p.current=o.length;const _=u(i.onWidth);_.current=i.onWidth;const w=E(()=>o.flat(),[o]),x=E(()=>I(w,c),[w,c]),T=z(M),C=E(()=>o.map(e=>B(e,c,T)),[o,c,T]);F(()=>{const e=L.current;if(!e||typeof ResizeObserver>"u")return;const n=l.current?.parentElement||null,s=new ResizeObserver(k=>{k.forEach(r=>{const t=r.contentRect;r.target===e&&t.height>0&&p.current>0&&N(Math.round(t.height/p.current)),r.target===n&&t.width>0&&_.current(t.width)})});return s.observe(e),n&&s.observe(n),()=>s.disconnect()},[]),$(()=>{v&&l.current?.querySelector(`[data-role="day"][data-day="${y}"]`)?.focus()},[v]);const H=e=>Array.from(l.current?.querySelectorAll("[data-cal-item]")||[]).filter(n=>(n.dataset.first||"")<=e&&e<=(n.dataset.last||""));return Z("div",{ref:l,className:`dim-cal__grid${D?" dim-cal__grid--phone":""}`,role:"grid","aria-labelledby":P,"data-region":"dim-cal-grid",onKeyDown:e=>{const n=e.target;if(h!==null&&n.hasAttribute("data-cal-item")){const t=H(h);if(e.key==="Tab"&&t.length){e.preventDefault();const b=t.indexOf(n);t[(b+(e.shiftKey?t.length-1:1))%t.length].focus()}else if(e.key==="Escape"){e.preventDefault();const b=h;g(null),l.current?.querySelector(`[data-role="day"][data-day="${b}"]`)?.focus()}return}if(n.dataset.role!=="day")return;const s=n.dataset.day||y;if(e.key==="F2"){const t=H(s);t.length&&(e.preventDefault(),g(s),t[0].focus());return}const k=o.find(t=>t.includes(s))||o[0],r=V(e.key,s,k);!r||r.day===""||(e.preventDefault(),r.month||!w.includes(r.day)?S(r.day):R(r.day))},onBlur:e=>{l.current?.contains(e.relatedTarget)||g(null)},children:[f("div",{className:"dim-cal__weekdays",role:"row",children:o[0].map(e=>f("div",{className:`dim-cal__weekday${Y(e)?" dim-cal__weekday--weekend":""}`,role:"columnheader",children:q(e,Q,a.language)},e))}),f("div",{ref:L,className:"dim-cal__weeks",role:"rowgroup",children:o.map((e,n)=>f(J,{week:e,layout:C[n],height:M,month:d,today:W,selected:y,counts:x,look:a,dots:D,onOpen:G,onGround:i.onGround,onCreate:i.onCreate},e[0]))})]})};var se=X;export{ae as PHONE,se as default,V as stepFrom};
