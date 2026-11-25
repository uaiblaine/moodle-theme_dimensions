import{useState as l}from"react";import{jsx as e,jsxs as t}from"react/jsx-runtime";/**
 * The card view (AR-D9): the same rows, page and order as the grid, one article each.
 *
 * A card is a first line (who, and the row's action), the view's fixed lines, then the other chosen
 * fields in a list, at once on a wide screen (a grid of 17.5rem cards) and behind "More" on a narrow
 * one (one column). What each part holds is the view's (`renderCard`). Copied from local_evidencehub's
 * datareport/Cards.tsx without the selection variant.
 *
 * Strings read from the view's labels: more, less.
 *
 * @module     theme_dimensions/datareport/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const p=({labels:r,row:a,single:s,renderCard:o})=>{const[d,c]=l(!1),i=o(a),n=!s||d;return t("article",{className:"dim-comp-rview-card",role:"listitem","data-record":a.id,children:[e("div",{className:"dim-comp-rview-card-l1",children:i.head}),i.lines,i.fields.length>0?t("div",{className:"dim-comp-rview-card-more",children:[s?e("button",{type:"button",className:"btn btn-link btn-sm dim-comp-rview-linkbtn","aria-expanded":d,onClick:()=>c(!d),children:d?r.less:r.more}):null,n?e("dl",{className:"dim-comp-rview-card-list",children:i.fields.map(m=>t("div",{className:"dim-comp-rview-card-item",children:[e("dt",{children:m.title}),e("dd",{children:m.content})]},m.key))}):null]}):null]})},w=({labels:r,rows:a,single:s,renderCard:o})=>e("div",{className:"dim-comp-rview-cards"+(s?" dim-comp-rview-cards--single":""),role:"list",children:a.map(d=>e(p,{labels:r,row:d,single:s,renderCard:o},d.id))}),R=({single:r})=>e("div",{className:"dim-comp-rview-cards"+(r?" dim-comp-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:r?4:6},(a,s)=>t("div",{className:"dim-comp-rview-card dim-comp-rview-sk",children:[e("span",{className:"dim-comp-rview-skb dim-comp-rview-skb--w60"}),e("span",{className:"dim-comp-rview-skb dim-comp-rview-skb--w80"}),e("span",{className:"dim-comp-rview-skb dim-comp-rview-skb--w60"}),e("span",{className:"dim-comp-rview-skb dim-comp-rview-skb--w40"})]},s))});export{w as Cards,R as CardsSkeleton};
