import{Bar as h}from"../datareport/Bar";import{fill as f,fillObject as w}from"../datareport/str";import{choosable as d,chosen as b,chosenLabel as g}from"./filters";import{Fragment as y,jsx as m,jsxs as u}from"react/jsx-runtime";/**
 * The class report's filter bar: the shared bar (datareport/Bar) with the report's vocabulary.
 *
 * Filters draws one facet per shown competency, its rating: the scale's values and "Not rated", each option
 * with the learners it would list under every other criterion in force; they live in core's store
 * (core_reportbuilder_set_filters, filters.ts). The report's other filters (the learner's name, the identity
 * fields) are set in core's table view, which a note under the facets leads to, and appear here as chips that
 * remove them. Columns lists the learner, always shown, then every competency of the course (or the activity),
 * at most the props' maxcolumns of them shown at once. View is Automatic, Table or Cards, then core's table
 * view. Download is a menu of the site's enabled formats, each a link to core's reportbuilder/download.php with
 * the report's own parameters (the activity, the page's group and the search in force), so the file holds what
 * the list shows; the report applies the stored filters and the columns itself. It is offered once rows have
 * arrived and while there are any. Shaped after local_evidencehub's hub/Bar.tsx.
 *
 * @module     theme_dimensions/classreport/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const v=["auto","table","cards"],F=(e,t)=>({name:t.name,title:t.title,options:t.options,find:{label:e.searchoptions,nomatch:e.nooptionmatch}}),C=(e,t)=>{const o=g(t);return{key:t.name,text:o===""?t.title:w(e.filterchip,{title:t.title,value:o})}},R=(e,t,o,i,r)=>{const a={moduleid:o,groupid:i};r!==""&&(a.search=r);const s=new URLSearchParams({download:t,parameters:JSON.stringify(a)});return`${e}${e.includes("?")?"&":"?"}${s.toString()}`},x=e=>{const{labels:t,filters:o}=e,i=o.filter(d),r=o.filter(n=>!d(n)),a=n=>o.find(l=>l.name===n),s=e.downloadable&&e.downloadurl!==""?e.formats.map(n=>({key:n.value,label:n.label,href:R(e.downloadurl,n.value,e.moduleid,e.groupid,e.appliedsearch)})):[];return m(h,{labels:t,narrow:e.narrow,total:e.total,facets:i.map(n=>F(t,n)),values:Object.fromEntries(i.map(n=>[n.name,b(n)])),panelnote:u(y,{children:[i.length===0?m("p",{className:"dim-comp-rview-note",children:t.nofacets}):null,r.length>0?u("p",{className:"dim-comp-rview-note",children:[t.morefiltershelp," ",m("a",{href:e.tableurl,children:t.morefilters})]}):null]}),chips:o.filter(n=>n.applied).map(n=>C(t,n)),columns:e.columns,limit:{most:e.maxcolumns,note:f(t.columnlimit,String(e.maxcolumns))},usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:v,tableurl:e.tableurl,downloads:s,search:e.search,searchmin:e.searchmin,searching:e.searching,onSearch:e.onSearch,onFacet:(n,l)=>{const c=a(n);c&&e.onFilter(c,l)},onRemoveChip:n=>{const l=a(n);l&&e.onRemoveFilter(l)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};export{x as Bar,R as downloadHref};
