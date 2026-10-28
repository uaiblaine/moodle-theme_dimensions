/**
 * The filter line's folding steps, as a rule over numbers.
 *
 * The fleet contract's section 2 (moodle-dev/docs/filter-line/CONTRACT.md) and amd/src/categoryline.js's
 * fit(), which the visitor's shell keeps until R4: the line takes the first step at which its controls fit
 * on its OWN width - full; Filters without its word; the search as an icon; two lines, the scope on the
 * second and the view toggle in the Organise menu - and the last one whatever it measures.
 *
 * The AMD module tries each step on the page and reads whether a control is drawn past an edge, on every
 * width change. This port measures, once per content, how wide the line must be for each step
 * ({@see ./measure}), and then takes a step from a width alone: a width change (a ResizeObserver entry)
 * forces no layout. The widths a step needs are the line's breakpoints for that content and language.
 *
 * @module     theme_dimensions/filterline/steps
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const n=["s1","s2","s3","s4"],l=[{step:"s1",folded:!1},{step:"s2",folded:!1},{step:"s3",folded:!0},{step:"s4",folded:!1},{step:"s4",folded:!0}],d=["s3","s4"],r="s4",p=1,o={step:"dim-line--",measuring:"dim-line--measuring",searching:"dim-line--searching",folded:"dim-line--folded"},i=(e,s)=>{for(let t=0;t<l.length-1;t++)if(t<e.length&&e[t]<=s+p)return l[t];return l[l.length-1]},c=e=>e!==null&&d.indexOf(e.step)>=0,u=(e,s)=>e===s||e!==null&&s!==null&&e.step===s.step&&e.folded===s.folded,f=(e,s)=>{n.forEach(t=>e.classList.toggle(o.step+t,s!==null&&s.step===t)),e.classList.toggle(o.folded,s!==null&&s.folded)};export{o as CLASSES,d as MORPH_STEPS,n as STEPS,p as TOLERANCE,l as TRIES,r as TWO_LINES,i as choose,c as morphs,f as paint,u as same};
