import{makeSizing as s}from"../datareport/sizing";import{plain as m}from"../datareport/str";import{Fragment as M,jsx as r,jsxs as w}from"react/jsx-runtime";/**
 * The class report's cells: the width policy, a cell's HTML by key and the learner's lead.
 *
 * Every cell is the report's formatted HTML (class_ratings' formatters, format_row()): the learner's name
 * linked to their breakdown, the identity fields, and per competency the rating in its status label, linked to
 * core's page of the learner's competency, with the grading dialogue's hooks for a reader who may rate
 * (theme_dimensions/classratings opens the dialogue from them). Nothing is built here from a value: the HTML is
 * inserted as it came, the one sink of the view, and every label the view writes is text.
 *
 * @module     theme_dimensions/classreport/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const l="learner",c={learner:260},a=168,p={learner:160},d=80,y=800,R=s(c,a,p,d,y),i=(t,n)=>t.cells.find(e=>e.key===n)?.html??"",I=({row:t,identity:n})=>r(M,{children:n.map(e=>{const o=i(t,e.key);return o===""?null:r("span",{className:"dim-comp-rview-sub",title:m(o),dangerouslySetInnerHTML:{__html:o}},e.key)})}),g=({row:t,identity:n})=>{const e=i(t,l);return w("div",{className:"dim-comp-rview-who",children:[r("span",{className:"dim-comp-rview-c",title:m(e),dangerouslySetInnerHTML:{__html:e}}),r(I,{row:t,identity:n})]})};export{I as IdentityLines,l as LEAD,g as Learner,R as SIZING,i as cellHtml};
