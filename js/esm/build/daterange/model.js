import{bringIntoView as d,firstOf as l,monthAt as m,monthOf as p,onScreen as g,parse as x,period as u}from"./calendar";/**
 * What the period control holds and every way it changes, as pure functions of that state.
 *
 * Two boxes, a start and an end, open one popover, and the box that opened it is the mode: the bound
 * the next press of a day sets. The presses write a DRAFT; "Done" or a press outside the control
 * applies it ({@see commit()}), and Escape or the keyboard walking out discards it ({@see close()}),
 * leaving the period that was in force. Each clearing control applies at once ({@see drop()}). The
 * same rules as the AMD calendar it replaced (removed in R4c), function for function; the component only renders this state
 * and tells the listing when {@see apply()} says the period in force changed.
 *
 * @module     theme_dimensions/daterange/model
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const s="from",f="to",y=(o,r,e)=>{const t=u(o,r);return{from:t.from,to:t.to,draftfrom:t.from,draftto:t.to,open:!1,mode:s,field:s,cursor:p(t.from||t.to||e),focused:"",opened:!1}},h=(o,r,e,t)=>{const n=r===f?f:s,i=n===f?o.draftto:o.draftfrom,M=n===f?o.draftfrom:o.draftto,c=i||M||e;return{...o,mode:n,field:n,focused:c,cursor:d(o.cursor,t,c)}},O=(o,r,e,t)=>{const n={...o,draftfrom:o.from,draftto:o.to,cursor:p(o.from||o.to||e),open:!0,opened:!0};return h(n,r,e,t)},b=o=>({...o,open:!1,draftfrom:o.from,draftto:o.to}),a=(o,r,e)=>{const t=r!==o.from||e!==o.to;return{model:{...o,from:r,to:e,draftfrom:r,draftto:e},changed:t}},A=(o,r)=>{let{draftfrom:e,draftto:t,mode:n}=o;return n===s?(e=r,t!==""&&t<r&&(t=""),n=f):e!==""&&r<e?(e=r,t=""):t=r,{...o,draftfrom:e,draftto:t,mode:n,field:n,focused:r}},k=o=>a(b(o),o.draftfrom,o.draftto),B=(o,r)=>{const e=r===f?o.draftfrom:"",t=r===s?o.draftto:"";return a(r===""?{...o,mode:s}:o,e,t)},v=(o,r)=>{const e=m(o.cursor,r);return{...o,cursor:e,focused:l(e)}},T=(o,r,e)=>{if(!x(r))return null;const t=g(o.cursor,e,r)?o.cursor:d(o.cursor,e,r);return{...o,focused:r,cursor:t}},F=(o,r)=>({...o,cursor:d(o.cursor,r,o.focused)}),R=(o,r,e)=>{const t=u(r,e);return{...o,from:t.from,to:t.to,draftfrom:t.from,draftto:t.to}};export{s as FROM,f as TO,a as apply,b as close,k as commit,B as drop,y as initial,T as moveTo,O as open,A as press,R as set,h as setMode,v as step,F as widen};
