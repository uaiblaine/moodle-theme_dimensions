import f from"./Bar";import b from"./DayCell";import{HEADER as R,SLOT as $,cellPlan as v}from"./layout";import{jsx as l}from"react/jsx-runtime";/**
 * One week row of the grid: seven days, the bars in their slots, each day's content under them.
 *
 * The row is a grid of its own: a 30px row for the day numbers, one row per bar slot it draws, and the rest for the
 * days' content, so a bar never zigzags and a day's content starts under the lowest bar of its row. On a phone
 * (`dots` handed) the row draws no bar and no content: each day its number and its dot row.
 *
 * @module     theme_dimensions/calendar/WeekRow
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const L=d=>{const{week:a,layout:o,height:u,month:m,today:p,selected:k,counts:c,look:i,dots:r,onOpen:y,onGround:g,onCreate:w}=d,s=r?0:o.used,h=`${R}px${s?` repeat(${s}, ${$}px)`:""} minmax(0, 1fr)`;return l("div",{className:"dim-cal__week",role:"row",style:{gridTemplateRows:h},children:a.map((e,n)=>l(b,{iso:e,column:n,month:m,today:p,selected:e===k,count:c.get(e)||0,plan:v(o.timed[n],o.hidden[n],o.used,u),contentRow:s+2,look:i,dots:r?r.get(e)||{keys:[],more:!1}:null,bars:r?null:o.bars.filter(t=>t.start===n).map(t=>l(f,{segment:t,week:a,look:i},`${t.placed.item.source}-${t.placed.item.id}`)),onOpen:y,onGround:g,onCreate:w},e))})};var O=L;export{O as default};
