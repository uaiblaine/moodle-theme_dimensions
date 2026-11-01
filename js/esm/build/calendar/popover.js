import{useEffect as c,useRef as s,useState as i}from"react";/**
 * The behaviour the category picker and the "More filters" panel share: a disclosure button and its panel.
 *
 * The button carries aria-expanded and aria-controls; Escape inside the panel shuts it and gives the focus back to
 * the button; a press outside both shuts it. The listener is on the document only while the panel is open.
 *
 * @module     theme_dimensions/calendar/popover
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const p=()=>{const[o,t]=i(!1),n=s(null),r=s(null);c(()=>{if(!o)return;const e=a=>{const u=a.target;u&&!r.current?.contains(u)&&!n.current?.contains(u)&&t(!1)};return document.addEventListener("mousedown",e),()=>document.removeEventListener("mousedown",e)},[o]);const l=e=>{t(!1),e&&n.current?.focus()};return{open:o,toggle:()=>t(e=>!e),shut:l,button:n,panel:r,onKeyDown:e=>{e.key==="Escape"&&(e.preventDefault(),e.stopPropagation(),l(!0))}}};export{p as usePopover};
