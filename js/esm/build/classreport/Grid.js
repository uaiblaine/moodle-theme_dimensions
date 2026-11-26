import{Lines as w}from"../datareport/cells";import{Grid as y}from"../datareport/Grid";import{LEAD as g,Learner as h,SIZING as u,cellHtml as L}from"./cells";import{jsx as o}from"react/jsx-runtime";/**
 * The class report's grid: the shared bounded grid (datareport/Grid) with the report's cells.
 *
 * The lead column is the learner, their name linked to their breakdown as the report formats it, and the
 * identity fields the reader's report shows under it; every other column is a competency, its cell the
 * report's rating label, which a grader's press opens core's grading dialogue from (classratings.js).
 *
 * @module     theme_dimensions/classreport/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const G=({labels:e,columns:t,identity:i,rows:n,widths:d,sort:s,direction:m,onSort:l,onWidth:a,onWidthCommit:p})=>o(y,{labels:e,columns:t,lead:g,rows:n,widths:d,sizing:u,sort:s,direction:m,renderLead:r=>o(h,{row:r,identity:i}),renderCell:(r,c)=>o(w,{parts:[L(r,c.key)]}),onSort:l,onWidth:a,onWidthCommit:p});export{G as Grid};
