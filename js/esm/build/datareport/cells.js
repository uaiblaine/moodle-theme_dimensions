import{plain as p}from"./str";import{Fragment as r,jsx as m}from"react/jsx-runtime";/**
 * A cell drawn from the server's formatted HTML, one fact per line.
 *
 * A data report's cells are formatted on the server, where the masking and the escaping ran, so a cell
 * is inserted as HTML and never built here: the one HTML sink of the view. The first line is the cell's
 * line and the others its sub-lines. Every line ends in an ellipsis, its whole text in its title.
 *
 * @module     theme_dimensions/datareport/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const i=({parts:o})=>m(r,{children:o.map((e,t)=>m("span",{className:t===0?"dim-comp-rview-c":"dim-comp-rview-sub",title:p(e),dangerouslySetInnerHTML:{__html:e}},t))});export{i as Lines};
