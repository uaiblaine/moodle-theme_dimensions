/**
 * Words and numbers: filling the strings the props carry (copied from local_evidencehub's datareport/str.ts).
 *
 * The strings arrive in the props, translated with their placeholders ("{$a} learners"), so the first paint
 * waits for no string request; only the client knows the value.
 *
 * @module     theme_dimensions/datareport/str
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const o=(t,n)=>(t||"").split("{$a}").join(n),s=(t,n)=>Object.entries(n).reduce((e,[r,i])=>e.split(`{$a->${r}}`).join(i),t||""),c=(t,n,e,r)=>o(e===1&&t[`${n}_one`]?t[`${n}_one`]:t[n],r),g=t=>{const n=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(n).format(t)}catch{return String(t)}},m=t=>{const n=document.createElement("template");return n.innerHTML=t,(n.content.textContent||"").replace(/\s+/g," ").trim()};export{o as fill,c as fillCount,s as fillObject,g as num,m as plain};
