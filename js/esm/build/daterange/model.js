import{bringIntoView as p,firstOf as h,monthAt as x,monthOf as v,onScreen as d,parse as a,period as M}from"./calendar";/**
 * What the period control holds and every way it changes, as pure functions of that state.
 *
 * Two boxes, a start and an end, over the months; the active box is the bound the next press of a day sets
 * (the mode). There is no draft: every press, a box's own clear and "Clear period" change the period in
 * force at once ({@see press()}, {@see drop()}), and the component tells the listing whenever
 * {@see apply()} says it really changed, so the list is refreshed while the layer stays open. The
 * component only renders this state.
 *
 * @module     theme_dimensions/daterange/model
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const c="from",s="to",g=(o,r)=>o!==""&&r===""?s:c,y=(o,r,n)=>{const t=M(o,r),e=t.from||t.to||n;return{from:t.from,to:t.to,mode:g(t.from,t.to),cursor:v(e),focused:e,hover:""}},B=(o,r,n,t)=>{const e=r===s?s:c,u=e===s?o.to:o.from,f=e===s?o.from:o.to,i=u||f||n;return{...o,mode:e,focused:i,cursor:p(o.cursor,t,i)}},m=(o,r,n)=>{const t=r!==o.from||n!==o.to;return{model:{...o,from:r,to:n},changed:t}},O=(o,r)=>{let{from:n,to:t}=o,e=o.mode===s?c:s;return o.mode===c?(n=r,t!==""&&t<r&&(t="")):n!==""&&r<n?(n=r,t="",e=s):t=r,m({...o,mode:e,focused:r,hover:""},n,t)},b=(o,r)=>{const n=r===s?o.from:"",t=r===c?o.to:"";return m({...o,mode:r===s?s:c,hover:""},n,t)},A=o=>{const r=o.hover;return r!==""&&o.mode===s&&o.from!==""&&r>o.from?{from:o.from,to:r}:r!==""&&o.mode===c&&o.to!==""&&r<o.to?{from:r,to:o.to}:{from:o.from,to:o.to}},k=(o,r)=>o.hover===r?o:{...o,hover:r},l=(o,r)=>{const n=x(o.cursor,r);return{...o,cursor:n,focused:h(n)}},F=(o,r,n)=>{if(!a(r))return null;const t=d(o.cursor,n,r)?o.cursor:p(o.cursor,n,r);return{...o,focused:r,cursor:t}},T=(o,r)=>({...o,cursor:p(o.cursor,r,o.focused)}),R=(o,r,n,t)=>{const e=M(r,n);if(e.from===o.from&&e.to===o.to)return o;const u=e.from||e.to,f={...o,from:e.from,to:e.to,mode:g(e.from,e.to),hover:""};return u===""||d(o.cursor,t,u)?f:{...f,focused:u,cursor:p(o.cursor,t,u)}};export{c as FROM,s as TO,m as apply,A as drawn,b as drop,k as hover,y as initial,g as modeFor,F as moveTo,O as press,R as set,B as setMode,l as step,T as widen};
