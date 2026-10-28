import{CLASSES as s,DRAG_THRESHOLD as p,RAIL_GAP as d,SELECTORS as l}from"./model";import{setClass as m}from"./posters";/**
 * The panel's rail: its arrows, its phone counter and the drag of a mouse.
 *
 * Stage R3 of docs/vitrine-react/PLAN.md, the rail of amd/src/categoryareapanel.js for the logged-in
 * shell. The rail itself is the panel template's markup; this module reads its scroll and writes its
 * arrows' state and its counter. {@see railState} is the one place layout is read, and Areas.tsx calls it
 * only where the layout is already clean: from a ResizeObserver entry (after layout, before paint) and
 * from the rail's own scroll and the reader's own press.
 *
 * @module     theme_dimensions/areas/rail
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const v=(t,o,e,n)=>({prevoff:t<=2,nextoff:t>=o-2,at:n&&e>0?Math.min(n,Math.round(t/e)+1):0,count:n}),x=t=>{const o=t.querySelectorAll(l.card),e=o.length?o[0].parentElement:null;return v(t.scrollLeft,t.scrollWidth-t.clientWidth,e?e.offsetWidth+d:0,o.length)},S=(t,o)=>{const e=t.querySelector(l.prev),n=t.querySelector(l.next);e&&m(e,s.arrowoff,o.prevoff),n&&m(n,s.arrowoff,o.nextoff);const r=t.querySelector(l.counter),i=o.at+" / "+o.count;r&&o.count&&r.textContent!==i&&(r.textContent=i)},h=(t,o,e)=>{const n=t.querySelector(l.rail),r=n?n.firstElementChild:null;!n||!r||n.scrollBy({left:o*(r.offsetWidth+d),behavior:e?"auto":"smooth"})},b=(t,o)=>{if(t.pointerType!=="mouse"||t.button!==0||!(t.target instanceof Element))return;const e=t.target.closest(l.rail);if(!e||e.scrollWidth<=e.clientWidth+2)return;const n=t.clientX,r=e.scrollLeft;let i=!1;const c=u=>{const f=u.clientX-n;!i&&Math.abs(f)>p&&(i=!0,e.classList.add(s.dragging)),i&&(e.scrollLeft=r-f)},a=()=>{window.removeEventListener("pointermove",c),window.removeEventListener("pointerup",a),i&&(e.classList.remove(s.dragging),o())};window.addEventListener("pointermove",c),window.addEventListener("pointerup",a)};export{b as dragRail,S as paintRail,v as railFrom,x as railState,h as scrollRail};
