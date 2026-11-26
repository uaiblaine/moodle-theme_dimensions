import{useId as k}from"react";import{fillObject as f,num as n}from"./str";import{jsx as a,jsxs as b}from"react/jsx-runtime";/**
 * The foot of the rows: which rows these are, the pages and the page size (AR-D14: 30, 50 or 100).
 *
 * The server pages (one data query and one count whatever the size); the client never holds more
 * than one page. Narrow, the pages read "Page 2 of 9" between Previous and Next.
 *
 * @module     theme_dimensions/datareport/Pager
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const P=(e,r)=>[...new Set([1,e-1,e,e+1,r])].filter(t=>t>=1&&t<=r).sort((t,u)=>t-u),R=({labels:e,page:r,perpage:t,perpages:u,total:s,shown:w,narrow:h,onPage:l,onPerpage:y})=>{const g=k(),i=Math.max(1,Math.ceil(s/t)),p=r+1,d=s>0?r*t+1:0,N=s>0?d+w-1:0;let c=[];if(h)c=[a("span",{className:"dim-comp-rview-pager-gap",children:f(e.pageof,{page:n(p),pages:n(i)})},"of")];else{const o=P(p,i);o.forEach((m,v)=>{v>0&&m-o[v-1]>1&&c.push(a("span",{className:"dim-comp-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${m}`)),c.push(a("button",{type:"button","aria-current":m===p?"page":void 0,onClick:()=>l(m-1),children:n(m)},m))})}return b("div",{className:"dim-comp-rview-foot",children:[a("span",{className:"dim-comp-rview-num","data-region":"dim-comp-rview-range",children:f(e.range,{from:n(d),to:n(N),total:n(s)})}),i>1?b("nav",{className:"dim-comp-rview-pager","aria-label":e.pages,children:[a("button",{type:"button",disabled:p===1,onClick:()=>l(r-1),children:e.previous}),c,a("button",{type:"button",disabled:p===i,onClick:()=>l(r+1),children:e.next})]}):null,b("span",{className:"dim-comp-rview-perpage",children:[a("label",{htmlFor:g,children:e.perpage}),a("select",{id:g,className:"form-select form-select-sm",value:t,onChange:o=>y(Number(o.target.value)),children:u.map(o=>a("option",{value:o,children:n(o)},o))})]})]})};export{R as Pager};
