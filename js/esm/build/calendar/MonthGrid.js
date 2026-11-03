import{useEffect as U,useLayoutEffect as q,useMemo as v,useRef as c,useState as A}from"react";import{WEEK as P,say as z,shift as h,shiftMonth as K}from"../daterange/calendar";import{ROW_DEFAULT as I,barSlots as B,dayCounts as Y,weekLayout as J}from"./layout";import{isWeekend as Q}from"./days";import V from"./WeekRow";import{jsx as f,jsxs as ne}from"react/jsx-runtime";/**
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
 */const X=576,Z=16,le=()=>typeof window<"u"&&typeof window.matchMedia=="function"?window.matchMedia(`(max-width: ${X+Z-1}px)`).matches:!1,j={weekday:"short"},ee=(a,s,u)=>{switch(a){case"ArrowLeft":return{day:h(s,-1),month:!1};case"ArrowRight":return{day:h(s,1),month:!1};case"ArrowUp":return{day:h(s,-P),month:!1};case"ArrowDown":return{day:h(s,P),month:!1};case"Home":return{day:u[0],month:!1};case"End":return{day:u[u.length-1],month:!1};case"PageUp":return{day:K(s,-1),month:!0};case"PageDown":return{day:K(s,1),month:!0};default:return null}},te=a=>{const{look:s,month:u,weeks:r,placed:m,today:R,selected:g,focusing:E,captionid:S,dots:M,onSelect:W,onMonth:G,onOpen:N}=a,[D,$]=A(I),[y,p]=A(null),i=c(null),H=c(null),w=c(r.length);w.current=r.length;const _=c(a.onWidth);_.current=a.onWidth;const L=c(a.onHeight);L.current=a.onHeight;const T=c(0),b=v(()=>r.flat(),[r]),C=v(()=>Y(b,m),[b,m]),O=B(D),F=v(()=>r.map(e=>J(e,m,O)),[r,m,O]);U(()=>{const e=H.current;if(!e||typeof ResizeObserver>"u")return;const n=i.current?.parentElement||null,d=new ResizeObserver(k=>{k.forEach(o=>{const t=o.contentRect;o.target===e&&t.height>0&&w.current>0&&$(Math.round(t.height/w.current)),o.target===n&&t.width>0&&_.current(t.width);const l=Math.round(t.height);o.target===i.current&&n&&l>0&&l!==T.current&&(T.current=l,L.current?.(l))})});return d.observe(e),i.current&&d.observe(i.current),n&&d.observe(n),()=>d.disconnect()},[]),q(()=>{E&&i.current?.querySelector(`[data-role="day"][data-day="${g}"]`)?.focus()},[E]);const x=e=>Array.from(i.current?.querySelectorAll("[data-cal-item]")||[]).filter(n=>(n.dataset.first||"")<=e&&e<=(n.dataset.last||""));return ne("div",{ref:i,className:`dim-cal__grid${M?" dim-cal__grid--phone":""}`,role:"grid","aria-labelledby":S,"data-region":"dim-cal-grid",onKeyDown:e=>{const n=e.target;if(y!==null&&n.hasAttribute("data-cal-item")){const t=x(y);if(e.key==="Tab"&&t.length){e.preventDefault();const l=t.indexOf(n);t[(l+(e.shiftKey?t.length-1:1))%t.length].focus()}else if(e.key==="Escape"){e.preventDefault();const l=y;p(null),i.current?.querySelector(`[data-role="day"][data-day="${l}"]`)?.focus()}return}if(n.dataset.role!=="day")return;const d=n.dataset.day||g;if(e.key==="F2"){const t=x(d);t.length&&(e.preventDefault(),p(d),t[0].focus());return}const k=r.find(t=>t.includes(d))||r[0],o=ee(e.key,d,k);!o||o.day===""||(e.preventDefault(),o.month||!b.includes(o.day)?G(o.day):W(o.day))},onBlur:e=>{i.current?.contains(e.relatedTarget)||p(null)},children:[f("div",{className:"dim-cal__weekdays",role:"row",children:r[0].map(e=>f("div",{className:`dim-cal__weekday${Q(e)?" dim-cal__weekday--weekend":""}`,role:"columnheader",children:z(e,j,s.language)},e))}),f("div",{ref:H,className:"dim-cal__weeks",role:"rowgroup",children:r.map((e,n)=>f(V,{week:e,layout:F[n],height:D,month:u,today:R,selected:g,counts:C,look:s,dots:M,onOpen:N,onGround:a.onGround,onCreate:a.onCreate},e[0]))})]})};var ce=te;export{X as PHONE,Z as PHONE_GUTTERS,ce as default,le as guessPhone,ee as stepFrom};
