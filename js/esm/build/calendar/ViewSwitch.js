import{viewsOf as l}from"./weeks";import{jsx as o,jsxs as c}from"react/jsx-runtime";/**
 * The toolbar's view switch, Month and Week, Day on a phone, on
 * both shells, and Trilhas on the account's where the page offers it.
 *
 * A group of toggle buttons, the view shown pressed. Pressing another one is the shell's: it keeps the selected day and
 * shows the week holding it, its month, or the day itself. The choices are weeks.ts viewsOf(): the day only while the
 * frame is a phone's.
 *
 * @module     theme_dimensions/calendar/ViewSwitch
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const s=(i,e)=>e==="day"?i.calendar_viewday:e==="trail"?i.calendar_viewtrail:e==="week"?i.calendar_viewweek:i.calendar_viewmonth,n=({strings:i,one:e,view:t,onView:a})=>o("button",{type:"button",className:"dim-cal__view","aria-pressed":t===e,"data-action":`dim-cal-view-${e}`,onClick:()=>a(e),children:s(i,e)}),d=({strings:i,view:e,phone:t,trails:a=!1,onView:r})=>c("div",{className:"dim-cal__views",role:"group","aria-label":i.calendar_view,"data-region":"dim-cal-views",children:[l(t).map(w=>o(n,{strings:i,one:w,view:e,onView:r},w)),a?o(n,{strings:i,one:"trail",view:e,onView:r}):null]});var v=d;export{v as default};
