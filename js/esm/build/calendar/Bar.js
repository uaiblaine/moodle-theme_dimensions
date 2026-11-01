import{itemHref as m}from"./model";import{barLabel as f,colourStyle as u,keyClass as y,placeOf as _}from"./look";import{jsx as o,jsxs as e}from"react/jsx-runtime";/**
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
 */const b=({segment:a,week:p,look:t})=>{const{item:r,first:c,last:d}=a.placed,i=a.end-a.start+1,l=i>=3?_(r,t):"",s=["dim-cal__bar",y(r)];a.clipstart&&s.push("dim-cal__bar--clipstart"),a.clipend&&s.push("dim-cal__bar--clipend");const n={...u(r,t),gridColumn:`${a.start+1} / span ${i}`,gridRow:a.slot+2};return e("a",{className:s.join(" "),href:m(r,t.courses,t.home),style:n,tabIndex:-1,"aria-label":f(r,c,d,t),"data-cal-item":"","data-cal-pop":"","data-first":p[a.start],"data-last":p[a.end],"data-source":r.source,"data-id":r.id,children:[a.clipstart?o("span",{"aria-hidden":"true",children:"\u2039 "}):null,e("span",{className:"dim-cal__name",children:[r.name,l!==""?` \xB7 ${l}`:""]}),a.clipend?o("span",{"aria-hidden":"true",children:" \u203A"}):null]})};var C=b;export{C as default};
