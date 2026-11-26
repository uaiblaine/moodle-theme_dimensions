/**
 * The bar's menus (the Design System's menu.md): the keys that move between items.
 *
 * @module     theme_dimensions/datareport/menus
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const o=r=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(r.key))return;const e=Array.from(r.currentTarget.querySelectorAll('[role^="menuitem"]'));if(e.length===0)return;r.preventDefault();const n=e.indexOf(document.activeElement);let t=0;r.key==="End"?t=e.length-1:r.key==="ArrowDown"?t=(n+1)%e.length:r.key==="ArrowUp"&&(t=(n-1+e.length)%e.length),e[t].focus()};export{o as menuKeys};
