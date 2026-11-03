import{useEffect as w,useRef as c}from"react";import{DayBody as y}from"./DayDrawer";import{jsx as l,jsxs as b}from"react/jsx-runtime";/**
 * The phone's Day view (docs/calendar/PROPOSAL.md section 31): one day at the frame's whole width.
 *
 * A region labelled by the toolbar's caption, which names the day (its long date) and whose arrows and "Today" move
 * it; its head holds the "New event" "+" while the page offers it; under it the drawer's own body (DayDrawer.tsx
 * DayBody): the all-day strip, then the whole day's hour column in its scroll box, with the drawer's creation paths
 * (a double-tap or a held touch on an hour row). Items open the popover through the page's delegated listener.
 *
 * Shown only on a phone, so no grid is drawn beside it: its ONE ResizeObserver watches the frame it is drawn in and
 * reports the frame's width alone, the grids' rule (MonthGrid.tsx PHONE, PROPOSAL.md section 30.7), so the page leaves
 * the Day view when the frame becomes a desktop's. The frame's width does not depend on what it holds. Nothing is
 * measured on the mount path: the observer's entries are the measures.
 *
 * @module     theme_dimensions/calendar/DayView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const v=({look:s,iso:r,placed:m,captionid:u,action:t,onCreate:f,onWidth:a})=>{const i=c(null),d=c(a);return d.current=a,w(()=>{const e=i.current?.parentElement||null;if(!e||typeof ResizeObserver>"u")return;const n=new ResizeObserver(p=>{p.forEach(o=>{o.target===e&&o.contentRect.width>0&&d.current(o.contentRect.width)})});return n.observe(e),()=>n.disconnect()},[]),b("section",{ref:i,className:"dim-cal__drawer dim-cal__drawer--dayview",role:"region","aria-labelledby":u,"data-region":"dim-cal-dayview","data-day":r,children:[t?l("div",{className:"dim-cal__drawerhead dim-cal__dayviewhead",children:t}):null,l(y,{look:s,iso:r,placed:m,list:!1,onCreate:f})]})};var R=v;export{R as default};
