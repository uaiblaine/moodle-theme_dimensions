import{jsx as r,jsxs as p}from"react/jsx-runtime";/**
 * The key chips: one toggle button per key it is handed, in its key's colours, with the count of the range on screen.
 *
 * A chip is a button with aria-pressed. A chip off is a key the range is not asked for, so it carries no count:
 * the server never counted it.
 *
 * @module     theme_dimensions/calendar/KeyChips
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const l=(e,c)=>{switch(c){case"site":return e.calendar_key_site;case"category":return e.calendar_key_category;case"course":return e.calendar_key_course;case"group":return e.calendar_key_group;case"user":return e.calendar_key_user;case"activity":return e.calendar_key_activity;case"open":return e.calendar_key_open;default:return e.calendar_key_other}},i=({strings:e,keys:c,off:t,counts:o,onToggle:s})=>r("div",{className:"dim-cal__chiprow",role:"group","aria-label":e.calendar_keys,"data-region":"dim-cal-chips",children:c.map(a=>{const n=!t.includes(a);return p("button",{type:"button",className:`dim-cal__chip dim-cal-key--${a}`,"aria-pressed":n,"data-key":a,onClick:()=>s(a),children:[r("span",{className:"dim-cal__swatch","aria-hidden":"true"}),r("span",{className:"dim-cal__chiplabel",children:l(e,a)}),n?r("span",{className:"dim-cal__chipcount",children:o[a]||0}):null]},a)})});var d=i;export{d as default,l as keyLabel};
