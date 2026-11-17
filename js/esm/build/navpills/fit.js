/**
 * Which units of the category navigation stay on the row and which move into "More": arithmetic only.
 *
 * The row module measures once ({@see ./row measure()}) and hands the numbers here, so the decision reads no layout
 * and is tested in Jest on its own. Units move from the row's end, the page itself ("Category") last of all (D83);
 * "More" is shown when anything moved or when it holds nodes of its own (D89), and its width is then paid on the
 * row, with the gap before it.
 *
 * @module     theme_dimensions/navpills/fit
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const d=.5,l=(t,n,u,i)=>{const m=n.length+(i>0?1:0),r=n.reduce((a,e)=>a+t[e],0)+i;return m>0?r+u*(m-1):0},f=t=>{const{widths:n,gap:u,more:i}=t,m=Number.isFinite(t.room)?Math.max(0,t.room):0,r=t.last??-1,a=!!t.own,e=n.map((o,b)=>b);if(!a&&l(n,e,u,0)<=m+.5)return{keep:e,move:[],more:!1};const c=e.filter(o=>o!==r).reverse();r>=0&&r<n.length&&c.push(r);const s=new Set;for(const o of c){const b=e.filter(p=>!s.has(p));if(l(n,b,u,i)<=m+.5)break;s.add(o)}return{keep:e.filter(o=>!s.has(o)),move:e.filter(o=>s.has(o)),more:a||s.size>0}};export{d as TOLERANCE,f as fit};
