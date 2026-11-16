import{Fragment as q,useLayoutEffect as g,useState as i}from"react";import{createPortal as T,flushSync as A}from"react-dom";import{PHONE as k,SELECTORS as d,WIDE as x,summaryLabel as D}from"./model";import{EVENTS as c,createPanel as O,drawnOpen as F,optionsOf as N}from"./panel";import{isFiltered as I,setHidden as K}from"./posters";import{jsx as M,jsxs as $}from"react/jsx-runtime";/**
 * The posters of the areas and the panel between their rows, on both shells of the showcase.
 *
 * The visitor's differences are the mount's props ({@see ./panel Options}): `{links: true, quiet, autoopen:
 * false}` on the visitor's page, nothing on the logged-in one. The rules live in the modules of this tree
 * (`panel.ts` for the panel, `posters.ts` for the posters' result layer, `rail.ts`, `model.ts`).
 *
 * The mount node is an empty, hidden child that mountAreas() (js/esm/src/listing/mounts.ts) appends to the
 * areas section, so the mount draws no box and the first paint stays the server's. The component is the only
 * writer of:
 *
 * - the panel's frame: the one list item of the grid (`panel.ts`), whose content is category_panel
 *   rendered by core/templates and inserted whole, never diffed;
 * - the posters' result layer (`posters.ts`): pills, counts and shells on the articles, the poster's
 *   control (a button that opens, or the link to its page), the open state, the items' hidden state, the
 *   reveal past the slice, the "nothing anywhere" box and its "search all" link, the hint, the live status;
 * - the "nothing in N areas" summary, which React draws: its sentence and its list, through portals into
 *   the server's `<summary>` and `<ul>`, read off them in the first render and drawn back hook for hook.
 *
 * Nothing else writes into those, except the address of a visitor's poster that stays a link, which the
 * visitor's shell points at the area's panel. The listing speaks to the component through events
 * ({@see EVENTS}): the listing client's `dim-vitrine:pending`, `:criteria` and `:failed` and the listing's
 * `:view` on the document; the component answers `dim-panel:autoopened` there. On the areas section,
 * mountAreas() hears, for the page's client, `dim-areas:ask` (the client fills the detail's `answer` with
 * its web service's promise: the component calls no service and reads no session), `dim-areas:question`
 * and `dim-areas:unanswered` (the visitor's names its question and takes a failure over),
 * `dim-areas:painted` (the logged-in shell paints what an account adds to the rows) and
 * `dim-areas:mounted`, which ends its pending.
 *
 * @module     theme_dimensions/areas/Areas
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const R=u=>{const t=u.querySelector(d.nomatch),a=t?t.querySelector(d.nomatchlist):null,p=a?Array.from(a.children).map(m=>({id:Number(m.dataset.categoryid),name:m.textContent||"",url:m.querySelector("a")?.getAttribute("href")||""})):[],s=document.activeElement;return{box:t,label:t?t.querySelector(d.nomatchsummary):null,list:a,summary:{entries:p,all:t?.dataset.labelAll||"",less:t?.dataset.labelLess||""},focused:a&&s&&a.contains(s)?s:null}},V=u=>A(u),W=u=>{const[t]=i(()=>{const e=document.querySelector(d.areas),l=e?e.querySelector(d.panel):null,o=e&&l?{areas:e,panel:l,status:e.querySelector(d.status)}:null;return{nodes:o,drawn:e?R(e):null,open:o?F(o):0,filtered:e?I(e):!1}}),[a,p]=i(t.drawn?t.drawn.summary:{entries:[],all:"",less:""}),[s,m]=i(t.open),[S,C]=i(t.filtered),[E,H]=i(!1),[n]=i(()=>t.nodes?O(t.nodes,{summary:p,opened:m,filtered:C,sync:V},N(u||{})):null);g(()=>{if(!t.nodes||!n)return;const{areas:e,panel:l}=t.nodes,o=t.drawn;o&&o.label&&o.list&&(o.label.textContent="",o.list.replaceChildren(),H(!0));try{n.adopt()}catch{}const h=[[e,"click",n.onAreasClick,!1],[l,"click",n.onDragClick,!0],[l,"click",n.onPanelClick,!1],[l,"scroll",n.onScroll,!0],[l,"pointerdown",n.onPointerDown,!1],[l,"dragstart",n.onDragStart,!1],[e,"toggle",n.onToggle,!0],[document,"keydown",n.onKeydown,!1],[window,"popstate",n.onPopState,!1],[document,c.view,n.relayout,!1],[document,c.pending,r=>n.onPending(r.detail||{}),!1],[document,c.failed,n.onFailed,!1],[document,c.criteria,r=>n.criteria(r.detail||{}),!1],[e,c.order,r=>n.onOrder(r.detail||{}),!1]];h.forEach(([r,b,v,L])=>r.addEventListener(b,v,L));const w=[x,k].map(r=>window.matchMedia(r));return w.forEach(r=>r.addEventListener("change",n.relayout)),e.dispatchEvent(new CustomEvent(c.mounted,{bubbles:!0})),()=>{h.forEach(([r,b,v,L])=>r.removeEventListener(b,v,L)),w.forEach(r=>r.removeEventListener("change",n.relayout)),n.disconnect()}},[]);const y=D(a,s);g(()=>{const e=t.drawn;if(!(!E||!e||!e.box)&&(K(e.box,!S||y===""),e.focused instanceof HTMLAnchorElement&&!e.focused.isConnected&&e.list)){const l=e.focused.getAttribute("href");e.list.querySelector(`a[href="${CSS.escape(l??"")}"]`)?.focus({preventScroll:!0}),e.focused=null}},[E,S,y]);const f=t.drawn;if(!E||!f||!f.label||!f.list)return null;const P=a.entries.map(e=>M("li",{"data-categoryid":e.id,hidden:e.id===s,children:e.url?M("a",{href:e.url,children:e.name}):e.name},e.id));return $(q,{children:[T(y,f.label),T(P,f.list)]})};var U=W;export{c as EVENTS,U as default};
