/**
 * The report's filters as core stores them, read and rewritten for the panel and the chips.
 *
 * Core keeps every filter value of a report for a user in one store, keyed "<filter>_<field>", and
 * core_reportbuilder_set_filters REPLACES that whole set. So a change to one filter is written as every
 * stored value, with that filter's own keys changed, and a removal as every other filter's keys. Every
 * filter the panel draws is a competency's rating (the rows answer names its facet "choice"): the theme's
 * reportbuilder\local\filters\rating, core's select, which stores "<filter>_operator" and "<filter>_value" and
 * reads EQUAL_TO with one value, "Not rated" being the value 0. The learner's name and the identity fields are
 * set in core's table view and appear here as chips that remove them. Copied from local_evidencehub's
 * hub/filters.ts without the hub's own filter names.
 *
 * @module     theme_dimensions/classreport/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const u=n=>n.facet!==""&&n.options.length>0,r=n=>{try{const e=JSON.parse(n.values||"{}");return e!==null&&typeof e=="object"?e:{}}catch{return{}}},s=n=>n.reduce((e,t)=>({...e,...r(t)}),{}),c=n=>{if(!n.applied||n.facet==="")return"";const e=r(n),t=e[`${n.name}_value`];return t==null||Number(e[`${n.name}_operator`]??0)!==1?"":String(t)},a=(n,e)=>{const t=s(n);for(const o of Object.keys(t))o.startsWith(`${e.name}_`)&&delete t[o];return t},i=(n,e,t)=>{const o=a(n,e);return o[`${e.name}_operator`]=t===""?0:1,o[`${e.name}_value`]=t,o},l=n=>{const e=c(n);return e===""?"":n.options.find(t=>t.value===e)?.label??""};export{s as allValues,u as choosable,c as chosen,l as chosenLabel,r as ownValues,i as withChoice,a as without};
