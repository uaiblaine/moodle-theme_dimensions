import{normalise as d}from"./mark";/**
 * The filter line's suggestions: which filters a text offers, and in what order.
 *
 * The rules the AMD line had before R4a (it left the tree with R4c). The
 * suggestions are the filters already on the page - every scope cell, the favourites star, every option
 * of the Filters panel and its quick dates - whose label or group matches the typed text, accent- and
 * case-insensitively, word starts first and then by count, each with its group and a count the page has
 * shown for it.
 *
 * That count is the server's last answer given while the field held no term ({@see remember}), so a term
 * typed since - which the client searches as it is typed, and which zeroes the options of a word that
 * names no course - neither changes nor hides a suggestion. A page opened on an address that already
 * carries a term has no such answer and offers the counts it shows, which include the term: a departure
 * from the contract's "the count it would give without the typed text" (section 3), which only the
 * server could answer, with a request per keystroke.
 *
 * @module     theme_dimensions/filterline/suggest
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const T=2,E=5,h=8,c={group:'[data-region="dim-vitrine-group"]',pillcount:'[data-region="dim-pill-count"]',star:'[data-region="dim-line-star"]',favcount:'[data-region="dim-fav-count"]',date:'[data-region="dim-date"]',quick:'[data-action="dim-quick"]'},m=(n,t)=>{let r=n.indexOf(t);if(r<0)return-1;for(;r>=0;){if(r===0||!/[a-z0-9]/.test(n.charAt(r-1)))return 0;r=n.indexOf(t,r+1)}return 1},x=(n,t)=>{const r=m(d(n.label),t),o=m(d(n.group),t);return r===0?0:o===0?1:r===1?2:o===1?3:-1},y=(n,t)=>{const r=d(t),o=e=>e.count===null?-1:e.count;return r.length<T?n.slice().sort((e,u)=>o(u)-o(e)||e.order-u.order).slice(0,E):n.map(e=>({item:e,rank:x(e,r)})).filter(e=>e.rank>=0).sort((e,u)=>e.rank-u.rank||o(u.item)-o(e.item)||e.item.order-u.item.order).slice(0,h).map(e=>e.item)},b=n=>Array.from(n.childNodes).filter(t=>t.nodeType===Node.TEXT_NODE).map(t=>t.textContent||"").join("").trim(),g=n=>{const t=n.querySelector(c.pillcount);return!t||t.hidden||(t.textContent||"").trim()===""?null:Number(t.textContent)},f=n=>{const t=n?n.querySelector("legend"):null;return t?(t.textContent||"").trim():""},p=(n,t)=>{n.querySelectorAll(c.group).forEach(r=>{r.querySelectorAll('input[type="radio"]').forEach(o=>{const e=r.querySelector(`label[for="${o.id}"]`);e&&t(o,e,r)})})},H=(n,t,r)=>{if(d(t)!=="")return;p(n,(u,l,i)=>{r.set(u,{count:g(l),hidden:i.hidden||u.hidden||l.hidden})});const o=n.querySelector(c.star),e=o?o.querySelector(c.favcount):null;o&&e&&r.set(o,{count:Number(e.textContent),hidden:!1})},L=(n,t)=>{const r=[],o=(l,i,a,s)=>{i!==""&&s!==0&&r.push({control:l,label:i,group:a,count:s,order:r.length})};p(n,(l,i,a)=>{if(l.checked||l.value===""||l.value==="0")return;const s=t.get(l)||{count:g(i),hidden:l.hidden||i.hidden};s.hidden||o(l,b(i),f(a),s.count)});const e=n.querySelector(c.star);if(e&&e.getAttribute("aria-pressed")!=="true"){const l=e.querySelector(c.favcount),i=t.get(e)||{count:l?Number(l.textContent):null,hidden:!1};o(e,e.title||"","",i.count)}const u=n.querySelector(c.date);return u&&u.querySelectorAll(c.quick).forEach(l=>{l.hasAttribute("aria-current")||o(l,(l.textContent||"").trim(),f(u),null)}),r};export{h as MATCH_COUNT,T as MIN_TERM,E as START_COUNT,g as countOf,m as matchOf,L as offers,x as rankOf,H as remember,y as suggest,b as wordsOf};
