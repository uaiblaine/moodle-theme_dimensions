import{Cards as d}from"../datareport/Cards";import{Lines as m}from"../datareport/cells";import{fillObject as p,num as l}from"../datareport/str";import{Learner as c,cellHtml as C}from"./cells";import{jsx as n}from"react/jsx-runtime";/**
 * The class report's cards: the shared card view (datareport/Cards) with the report's cells.
 *
 * A card is the learner (their name and identity fields), one fixed line saying how many of the shown
 * competencies they are proficient in and how many are rated (from the row's typed ratings, the rows
 * answer's own figures), then a line per shown competency, its title and its rating label, behind "More" on
 * a narrow container.
 *
 * @module     theme_dimensions/classreport/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const g=(r,o,i,e)=>{const a=e.ratings.filter(t=>t.grade!==null).length,s=e.ratings.filter(t=>t.proficient).length;return{head:n(c,{row:e,identity:i}),lines:e.ratings.length>0?n("div",{className:"dim-comp-rview-card-line",children:p(r.cardline,{proficient:l(s),rated:l(a),shown:l(e.ratings.length)})}):null,fields:o.map(t=>({key:t.key,title:t.title,content:n(m,{parts:[C(e,t.key)]})}))}},h=({labels:r,columns:o,identity:i,rows:e,single:a})=>n(d,{labels:r,rows:e,single:a,renderCard:s=>g(r,o,i,s)});export{h as Cards};
