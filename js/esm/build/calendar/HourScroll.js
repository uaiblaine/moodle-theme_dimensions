import{useLayoutEffect as S,useRef as a,useState as x}from"react";import{FULL_DAY as _,edgesOf as b,boxTopOf as y}from"./overlap";import{jsx as c,jsxs as D}from"react/jsx-runtime";/**
 * The scroll box of an hour column, the drawer's and the week's.
 *
 * The column inside it is the whole day (overlap.ts FULL_DAY); the box starts where overlap.ts boxTopOf() puts it,
 * once per opening (the `opening` key: a day, or a week, and its band), from the box's one layout read, never on the
 * page's mount path, and again when the grid hands a new `height` (the drawer is capped to the grid's height only once
 * the grid's observer has reported, which shrinks the box after the first placing) until the reader's first wheel,
 * touch or key on the box; a new opening starts over. The look is block_compass's calendar strip: no native scrollbar,
 * its overscroll kept from the page, and a fade at the top or the bottom only while the box can still scroll that way
 * (overlap.ts edgesOf(), read on scroll), the fades taking no pointer and hidden from the screen reader. Handed
 * `focusable` (it holds no focusable item), the box is a Tab stop with the label it is handed, so the arrows and the
 * Page keys scroll it.
 *
 * @module     theme_dimensions/calendar/HourScroll
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const N=(r,l)=>r.up!==l.up||r.down!==l.down,L=({band:r,busy:l,opening:t,height:v=0,label:H,focusable:d,role:i,variant:E,children:T})=>{const u=a(null),[m,f]=x({up:!1,down:!1}),p=a(""),n=a(!1);S(()=>{const e=u.current;if(!e)return;if(p.current!==t)p.current=t,n.current=!1;else if(n.current)return;const o=_.to-_.from;e.scrollTop=y(r,l,e.scrollHeight/o,e.clientHeight),f(b(e.scrollTop,e.scrollHeight,e.clientHeight))},[t,v]);const s=()=>{n.current=!0},w=e=>{const o=e.currentTarget,h=b(o.scrollTop,o.scrollHeight,o.clientHeight);f(g=>N(g,h)?h:g)};return D("div",{className:`dim-cal__hourbox dim-cal__hourbox--${E}`,role:"presentation",children:[c("div",{ref:u,className:"dim-cal__hourscroll","data-region":"dim-cal-hourscroll",role:i,tabIndex:d?0:void 0,"aria-label":d||i?H:void 0,onScroll:w,onWheel:s,onTouchStart:s,onKeyDown:s,children:T}),m.up?c("span",{className:"dim-cal__hourfade dim-cal__hourfade--up","aria-hidden":"true"}):null,m.down?c("span",{className:"dim-cal__hourfade dim-cal__hourfade--down","aria-hidden":"true"}):null]})};var U=L;export{U as default};
