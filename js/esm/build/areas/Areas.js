import{Fragment as q,useLayoutEffect as S,useState as c}from"react";import{createPortal as g,flushSync as A}from"react-dom";import{PHONE as P,SELECTORS as d,WIDE as k,summaryLabel as x}from"./model";import{EVENTS as f,createPanel as D,drawnOpen as F}from"./panel";import{isFiltered as N,setHidden as O}from"./posters";import{jsx as T,jsxs as V}from"react/jsx-runtime";/**
 * The posters of the areas and the panel between their rows, on the logged-in showcase.
 *
 * Stage R3 of docs/vitrine-react/PLAN.md. The visitor's shell keeps amd/src/categoryareapanel.js and
 * categoryposters.js until R4, so this component and those modules are two implementations of one panel:
 * a rule changes in both, and in the module of this tree that carries it (`panel.ts` for the panel,
 * `posters.ts` for the posters' result layer, `rail.ts`, `model.ts`), named after the AMD functions.
 *
 * WHO OWNS WHAT. The mount node is an empty, hidden child amd/src/categorypanel.js appends to the areas
 * section, so the mount draws no box and the first paint stays the server's, to the pixel. From it the
 * component owns, and is the only writer of, on this shell:
 *
 * - the panel's frame: the one list item of the grid (`panel.ts`), whose content is category_panel
 *   rendered by core/templates and inserted whole, never diffed;
 * - the posters' result layer (`posters.ts`): pills, counts and shells on the articles, the poster's
 *   control (a button that opens, or the link to its page), the open state, the items' hidden state, the
 *   reveal past the slice, the "nothing anywhere" box and its "search all" link, the hint, the live status;
 * - the "nothing in N areas" summary, which React draws: its sentence and its list, through portals into
 *   the server's `<summary>` and `<ul>`, read off them in the first render and drawn back hook for hook.
 *
 * Nothing of the AMD side writes into those. It speaks to the component through events
 * ({@see EVENTS}): the listing's `dim-vitrine:pending`, `:criteria`, `:failed` (categoryvitrine) and
 * `:view` (categorylisting) on the document; the component answers `dim-panel:autoopened` there. On the
 * areas section, categorypanel hears `dim-areas:ask` (it fills the detail's `answer` with the web
 * service's promise: the component calls no service and reads no session), `dim-areas:painted` (it paints
 * the progress of the panel's enrolled courses) and `dim-areas:mounted`, which ends its pending.
 *
 * @module     theme_dimensions/areas/Areas
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const I=n=>{const r=n.querySelector(d.nomatch),s=r?r.querySelector(d.nomatchlist):null,E=s?Array.from(s.children).map(i=>({id:Number(i.dataset.categoryid),name:i.textContent||"",url:i.querySelector("a")?.getAttribute("href")||""})):[],u=document.activeElement;return{box:r,label:r?r.querySelector(d.nomatchsummary):null,list:s,summary:{entries:E,all:r?.dataset.labelAll||"",less:r?.dataset.labelLess||""},focused:s&&u&&s.contains(u)?u:null}},K=n=>A(n),R=()=>{const[n]=c(()=>{const e=document.querySelector(d.areas),a=e?e.querySelector(d.panel):null,o=e&&a?{areas:e,panel:a,status:e.querySelector(d.status)}:null;return{nodes:o,drawn:e?I(e):null,open:o?F(o):0,filtered:e?N(e):!1}}),[r,s]=c(n.drawn?n.drawn.summary:{entries:[],all:"",less:""}),[E,u]=c(n.open),[i,M]=c(n.filtered),[p,H]=c(!1),[t]=c(()=>n.nodes?D(n.nodes,{summary:s,opened:u,filtered:M,sync:K}):null);S(()=>{if(!n.nodes||!t)return;const{areas:e,panel:a}=n.nodes,o=n.drawn;o&&o.label&&o.list&&(o.label.textContent="",o.list.replaceChildren(),H(!0)),t.adopt();const v=[[e,"click",t.onAreasClick,!1],[a,"click",t.onDragClick,!0],[a,"click",t.onPanelClick,!1],[a,"scroll",t.onScroll,!0],[a,"pointerdown",t.onPointerDown,!1],[a,"dragstart",t.onDragStart,!1],[e,"toggle",t.onToggle,!0],[document,"keydown",t.onKeydown,!1],[window,"popstate",t.onPopState,!1],[document,f.view,t.relayout,!1],[document,f.pending,l=>t.onPending(l.detail||{}),!1],[document,f.failed,t.onFailed,!1],[document,f.criteria,l=>t.criteria(l.detail||{}),!1]];v.forEach(([l,b,L,h])=>l.addEventListener(b,L,h));const w=[k,P].map(l=>window.matchMedia(l));return w.forEach(l=>l.addEventListener("change",t.relayout)),e.dispatchEvent(new CustomEvent(f.mounted,{bubbles:!0})),()=>{v.forEach(([l,b,L,h])=>l.removeEventListener(b,L,h)),w.forEach(l=>l.removeEventListener("change",t.relayout)),t.disconnect()}},[]);const y=x(r,E);S(()=>{const e=n.drawn;if(!(!p||!e||!e.box)&&(O(e.box,!i||y===""),e.focused instanceof HTMLAnchorElement&&!e.focused.isConnected&&e.list)){const a=e.focused.getAttribute("href");e.list.querySelector(`a[href="${a}"]`)?.focus({preventScroll:!0}),e.focused=null}},[p,i,y]);const m=n.drawn;if(!p||!m||!m.label||!m.list)return null;const C=r.entries.map(e=>T("li",{"data-categoryid":e.id,hidden:e.id===E,children:e.url?T("a",{href:e.url,children:e.name}):e.name},e.id));return V(q,{children:[g(y,m.label),g(C,m.list)]})};var J=R;export{f as EVENTS,J as default};
