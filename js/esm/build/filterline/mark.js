/**
 * A term marked in a text, in the text's own spelling, as runs a component draws rather than nodes it writes: the
 * rule of the line's suggestions and of the card titles ({@see ../areas/mark}, which marks the titles with it).
 *
 * The match ignores case and accents, as the server's search does: each character is folded on its own
 * and every folded character remembers the character it came from, so a match found in the folded text is
 * cut from the original one - "Incêndio" is marked whole for "incendio", accent and capital included.
 *
 * The card titles are the server's markup, which no component draws: the listing's store marks them through
 * areas/mark.ts on both shells. The filter line marks its suggestions with this module.
 *
 * @module     theme_dimensions/filterline/mark
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const u=e=>String(e).normalize("NFD").replace(/[̀-ͯ]/g,"").toLowerCase(),f=e=>u(e||"").trim(),a=(e,t)=>{if(t==="")return[];let l="";const s=[];for(let n=0;n<e.length;n++){const i=u(e.charAt(n));for(let o=0;o<i.length;o++)l+=i.charAt(o),s.push(n)}const c=[];let r=l.indexOf(t);for(;r!==-1;)c.push([s[r],s[r+t.length-1]+1]),r=l.indexOf(t,r+t.length);return c},g=(e,t,l=!1)=>{const s=a(e,t),c=l?s:s.slice(0,1);if(!c.length)return[{text:e,marked:!1}];const r=[];let n=0;return c.forEach(([i,o])=>{i>n&&r.push({text:e.slice(n,i),marked:!1}),r.push({text:e.slice(i,o),marked:!0}),n=o}),n<e.length&&r.push({text:e.slice(n),marked:!1}),r};export{u as fold,f as normalise,g as pieces,a as runs};
