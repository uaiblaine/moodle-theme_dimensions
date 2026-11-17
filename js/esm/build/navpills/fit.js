/**
 * Which units of the category navigation stay on the row and which move into "More": arithmetic only.
 *
 * The row module measures once ({@see ./row measure()}) and hands the numbers here, so the decision reads no layout
 * and is tested in Jest on its own. Units move from the row's end, the page itself ("Category") last of all (D83);
 * "More" is shown when anything moved or when it holds nodes of its own (D89), and its width is then paid on the
 * row, with the gap before it: the width it takes selected, its dot included, once it holds the unit with the
 * current page (D82).
 *
 * @module     theme_dimensions/navpills/fit
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const h=.5,p=(e,n,u,m)=>{const c=n.length+(m>0?1:0),o=n.reduce((b,i)=>b+e[i],0)+m;return c>0?o+u*(c-1):0},w=e=>{const{widths:n,gap:u,more:m}=e,c=Number.isFinite(e.room)?Math.max(0,e.room):0,o=e.last??-1,b=e.current??-1,i=e.selected??m,a=!!e.own,r=n.map((t,l)=>l);if(!a&&p(n,r,u,0)<=c+.5)return{keep:r,move:[],more:!1};const d=r.filter(t=>t!==o).reverse();o>=0&&o<n.length&&d.push(o);const s=new Set;for(const t of d){const l=r.filter(f=>!s.has(f));if(p(n,l,u,s.has(b)?i:m)<=c+.5)break;s.add(t)}return{keep:r.filter(t=>!s.has(t)),move:r.filter(t=>s.has(t)),more:a||s.size>0}};export{h as TOLERANCE,w as fit};
