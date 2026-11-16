/**
 * The filter line's folding steps, as a rule over numbers.
 *
 * The line's controls are the same at every width - the search is its button at rest, Filters has no word - so
 * the fold decides two things only: whether the line is one line or two (the scope on a line of its own after
 * the controls, the Filters panel a bottom sheet), and whether the open search field fits beside the controls
 * (it opens in place) or not (it grows over the line). The line takes the first try that fits on its own width,
 * and the last one whatever it measures.
 *
 * Trying each step on the page and reading whether a control is drawn past an edge would force a layout on every
 * width change. This module measures, once per content, how wide the line must be for each try
 * ({@see ./measure}), and then takes a fold from a width alone: a width change (a ResizeObserver entry)
 * forces no layout. The widths a try needs are the line's breakpoints for that content and language.
 *
 * @module     theme_dimensions/filterline/steps
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const r=["s1","s2"],n=[{step:"s1",over:!1},{step:"s1",over:!0},{step:"s2",over:!0}],p="s2",l=1,s={step:"dim-line--",measuring:"dim-line--measuring",open:"dim-line--open",searching:"dim-line--searching",over:"dim-line--over"},i=(e,t)=>{for(let o=0;o<n.length-1;o++)if(o<e.length&&e[o]<=t+l)return n[o];return n[n.length-1]},u=(e,t)=>e.length>=n.length||e.some(o=>o<=t+l),c=e=>e!==null&&e.over,m=(e,t)=>e===t||e!==null&&t!==null&&e.step===t.step&&e.over===t.over,a=(e,t)=>{r.forEach(o=>e.classList.toggle(s.step+o,t!==null&&t.step===o)),e.classList.toggle(s.over,t!==null&&t.over)};export{s as CLASSES,r as STEPS,l as TOLERANCE,n as TRIES,p as TWO_LINES,i as choose,c as morphs,a as paint,m as same,u as settled};
