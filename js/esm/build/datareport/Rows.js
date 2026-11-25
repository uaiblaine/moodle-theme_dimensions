import{CardsSkeleton as g}from"./Cards";import{GridSkeleton as u}from"./Grid";import{Fragment as R,jsx as o,jsxs as n}from"react/jsx-runtime";/**
 * The rows' region: what it holds while the rows load, fail, are none, or are there.
 *
 * The first load draws a skeleton of the grid or of the cards, with aria-busy and a status for
 * assistive technology; later loads keep the old rows, dimmed, under a 2 px progress line. A failed
 * read says so with a retry, above the old rows when there are some. No rows is the empty state, worded
 * for a filtered view or an empty one. The rows themselves (the view's grid or cards) are the children.
 * Copied from local_evidencehub's datareport/Rows.tsx without the dock.
 *
 * Strings read from the view's labels: caption, loading, loaderror, retry, empty, nomatch, nomatchhelp,
 * scrollhint.
 *
 * @module     theme_dimensions/datareport/Rows
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const w=({labels:e,loaded:i,error:t,total:s,filtered:r,cards:a,narrow:l,skeleton:c,children:d,onRetry:p})=>{const m=n("p",{className:"dim-comp-rview-error",children:[e.loaderror," ",o("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:p,children:e.retry})]});return!i&&t?m:i?s===0?n("div",{className:"dim-comp-rview-empty",children:[o("p",{className:"dim-comp-rview-empty-title",children:r?e.nomatch:e.empty}),r?o("p",{className:"dim-comp-rview-empty-text",children:e.nomatchhelp}):null]}):n("div",{className:"dim-comp-rview-dim",children:[t?m:null,d,a?null:o("p",{className:"dim-comp-rview-hint",children:e.scrollhint})]}):n(R,{children:[a?o(g,{single:l}):o(u,{...c}),o("span",{className:"visually-hidden",role:"status",children:e.loading})]})},b=e=>n("section",{className:"dim-comp-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"dim-comp-rview-rows",children:[e.loading&&e.loaded?o("div",{className:"dim-comp-rview-progress"}):null,o(w,{...e})]});export{b as RowsRegion};
