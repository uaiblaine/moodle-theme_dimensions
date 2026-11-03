import{viewsOf as o}from"./weeks";import{jsx as t}from"react/jsx-runtime";/**
 * The toolbar's view switch, Month and Week (docs/calendar/PROPOSAL.md section 21), and Day on a phone (section 31), on
 * both shells.
 *
 * A group of toggle buttons, the view shown pressed. Pressing another one is the shell's: it keeps the selected day and
 * shows the week holding it, its month, or the day itself. The choices are weeks.ts viewsOf(): the day only while the
 * frame is a phone's.
 *
 * @module     theme_dimensions/calendar/ViewSwitch
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const d=(e,a)=>a==="day"?e.calendar_viewday:a==="week"?e.calendar_viewweek:e.calendar_viewmonth,n=({strings:e,view:a,phone:r,onView:w})=>t("div",{className:"dim-cal__views",role:"group","aria-label":e.calendar_view,"data-region":"dim-cal-views",children:o(r).map(i=>t("button",{type:"button",className:"dim-cal__view","aria-pressed":a===i,"data-action":`dim-cal-view-${i}`,onClick:()=>w(i),children:d(e,i)},i))});var l=n;export{l as default};
