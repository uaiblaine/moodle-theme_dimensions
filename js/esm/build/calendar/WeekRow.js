import w from"./Bar";import f from"./DayCell";import{HEADER as g,SLOT as y,cellPlan as u}from"./layout";import{jsx as p}from"react/jsx-runtime";/**
 * One week row of the grid: seven days, the bars in their slots, each day's content under them.
 *
 * The row is a grid of its own: a 30px row for the day numbers, one row per bar slot it draws, and the rest for the
 * days' content, so a bar never zigzags and a day's content starts under the lowest bar of its row.
 *
 * @module     theme_dimensions/calendar/WeekRow
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const R=({week:s,layout:e,height:n,month:d,today:m,selected:a,counts:k,look:i,onOpen:l})=>{const c=`${g}px${e.used?` repeat(${e.used}, ${y}px)`:""} minmax(0, 1fr)`;return p("div",{className:"dim-cal__week",role:"row",style:{gridTemplateRows:c},children:s.map((t,r)=>p(f,{iso:t,column:r,month:d,today:m,selected:t===a,count:k.get(t)||0,plan:u(e.timed[r],e.hidden[r],e.used,n),contentRow:e.used+2,look:i,bars:e.bars.filter(o=>o.start===r).map(o=>p(w,{segment:o,week:s,look:i},`${o.placed.item.source}-${o.placed.item.id}`)),onOpen:l},t))})};var W=R;export{W as default};
