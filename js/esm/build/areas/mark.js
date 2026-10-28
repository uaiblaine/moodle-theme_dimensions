import{fold as i,runs as m}from"../filterline/mark";/**
 * The searched term marked on the titles of a panel's cards: amd/src/categorymark.js's mark() for the
 * logged-in panel, over the filter line's matching ({@see ../filterline/mark runs}).
 *
 * On the TEXT of the title's heading (data-region="dim-card-title"), never on a link's hook: a card with no
 * page to open draws its title as a plain span. It unmarks before it marks, so it is idempotent.
 *
 * @module     theme_dimensions/areas/mark
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const u='[data-region="dim-card-title"]',d="dim-mark",f=2,g=n=>{const c=String(n||"").trim().replace(/\s+/g," ");return c.length<f?"":i(c)},p=(n,c)=>{const e=n.nodeValue||"",a=m(e,c);if(!a.length)return;const r=document.createDocumentFragment();let t=0;a.forEach(([l,o])=>{l>t&&r.append(document.createTextNode(e.slice(t,l)));const s=document.createElement("mark");s.className=d,s.textContent=e.slice(l,o),r.append(s),t=o}),t<e.length&&r.append(document.createTextNode(e.slice(t))),n.replaceWith(r)},h=(n,c)=>{if(!n)return;const e=g(c);n.querySelectorAll(u).forEach(a=>{const r=a.querySelectorAll(`mark.${d}`);if(r.forEach(o=>o.replaceWith(document.createTextNode(o.textContent||""))),r.length&&a.normalize(),e==="")return;const t=document.createTreeWalker(a,NodeFilter.SHOW_TEXT),l=[];for(;t.nextNode();)l.push(t.currentNode);l.forEach(o=>p(o,e))})};export{h as markTitles};
