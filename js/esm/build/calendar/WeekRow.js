import y from"./Bar";import f from"./DayCell";import{HEADER as w,SLOT as R,cellPlan as $}from"./layout";import{jsx as p}from"react/jsx-runtime";/**
 * One week row of the grid: seven days, the bars in their slots, each day's content under them.
 *
 * The row is a grid of its own: a 30px row for the day numbers, one row per bar slot it draws, and the rest for the
 * days' content, so a bar never zigzags and a day's content starts under the lowest bar of its row. On a phone
 * (`dots` handed) the row draws no bar and no content: each day its number and its dot row.
 *
 * @module     theme_dimensions/calendar/WeekRow
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const h=({week:l,layout:e,height:i,month:m,today:d,selected:k,counts:c,look:a,dots:r,onOpen:g})=>{const s=r?0:e.used,u=`${w}px${s?` repeat(${s}, ${R}px)`:""} minmax(0, 1fr)`;return p("div",{className:"dim-cal__week",role:"row",style:{gridTemplateRows:u},children:l.map((o,n)=>p(f,{iso:o,column:n,month:m,today:d,selected:o===k,count:c.get(o)||0,plan:$(e.timed[n],e.hidden[n],e.used,i),contentRow:s+2,look:a,dots:r?r.get(o)||{keys:[],more:!1}:null,bars:r?null:e.bars.filter(t=>t.start===n).map(t=>p(y,{segment:t,week:l,look:a},`${t.placed.item.source}-${t.placed.item.id}`)),onOpen:g},o))})};var D=h;export{D as default};
