import{jsx as a,jsxs as w}from"react/jsx-runtime";/**
 * The toolbar's view switch, Month and Week (docs/calendar/PROPOSAL.md section 21), on both shells.
 *
 * A group of two toggle buttons, the view shown pressed. Pressing the other one is the shell's: it keeps the selected
 * day and shows the week holding it, or its month.
 *
 * @module     theme_dimensions/calendar/ViewSwitch
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const o=({strings:e,view:i,onView:t})=>w("div",{className:"dim-cal__views",role:"group","aria-label":e.calendar_view,"data-region":"dim-cal-views",children:[a("button",{type:"button",className:"dim-cal__view","aria-pressed":i==="month","data-action":"dim-cal-view-month",onClick:()=>t("month"),children:e.calendar_viewmonth}),a("button",{type:"button",className:"dim-cal__view","aria-pressed":i==="week","data-action":"dim-cal-view-week",onClick:()=>t("week"),children:e.calendar_viewweek})]});var n=o;export{n as default};
