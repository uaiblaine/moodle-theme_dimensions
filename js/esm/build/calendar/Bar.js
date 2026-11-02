import{itemHref as m}from"./model";import{barLabel as u,colourStyle as f,keyClass as b,placeOf as y}from"./look";import{jsx as e,jsxs as o}from"react/jsx-runtime";/**
 * One week's piece of a spanning bar: a link to the item that opens its popover, in the slot the row gave it.
 *
 * The bar sits in the week row's own grid (grid-column: start / span n, grid-row: its slot under the day numbers),
 * with open ends where the item goes on before or after the week. Its label is the item's name and its WHOLE span,
 * whichever week's piece the reader meets. It is out of the Tab order: the grid's keyboard reaches it from a day it
 * covers (F2, then Tab). A press, Enter or Space opens the popover (the page's delegated listener, on data-cal-pop);
 * its href, the item's own page, is what a middle click or a copied link follows.
 *
 * @module     theme_dimensions/calendar/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const _=({segment:a,week:i,look:t})=>{const{item:r,first:d,last:c}=a.placed,l=a.end-a.start+1,p=l>=3?y(r,t):"",s=["dim-cal__bar",b(r)];a.clipstart&&s.push("dim-cal__bar--clipstart"),a.clipend&&s.push("dim-cal__bar--clipend");const n={...f(r,t),gridColumn:`${a.start+1} / span ${l}`,gridRow:a.slot+2};return o("a",{className:s.join(" "),href:m(r,t.courses,t.home),style:n,tabIndex:-1,"aria-label":u(r,d,c,t),draggable:t.movable?.(r)?!0:void 0,"data-cal-item":"","data-cal-pop":"","data-first":i[a.start],"data-last":i[a.end],"data-source":r.source,"data-id":r.id,children:[a.clipstart?e("span",{"aria-hidden":"true",children:"\u2039 "}):null,o("span",{className:"dim-cal__name",children:[r.name,p!==""?` \xB7 ${p}`:""]}),a.clipend?e("span",{"aria-hidden":"true",children:" \u203A"}):null]})};var C=_;export{C as default};
