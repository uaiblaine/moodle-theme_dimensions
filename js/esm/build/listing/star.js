import{fetchOne as n}from"@moodle/lms/core/ajax";import{load as l}from"../amd";/**
 * The favourite star of the category showcase's course cards, wherever a card is drawn.
 *
 * A card's star lives on the listing's grid, inside a panel between the posters and on the root's panels; the
 * account's shell arms it on every page of the logged-in showcase, the root's directory included ({@see ./account
 * start}). One delegated listener on the document, never one per card, so a card painted into a panel or appended
 * by "load more" behaves like a card the server drew; arm() is idempotent.
 *
 * The body class it writes is its own mark and shows the star; one module's mark never stands for another's
 * presence. The visitor's page never loads it (categorypublic_source_test).
 *
 * @module     theme_dimensions/listing/star
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const c={star:'[data-action="dim-star"]',starlabel:'[data-region="dim-star-label"]'},d="dim-star-js",m="theme_dimensions_toggle_wishlist";let o=!1;const i=(e,a)=>{e.setAttribute("aria-pressed",a?"true":"false");const t=e.querySelector("i");t&&(t.classList.toggle("fa-solid",a),t.classList.toggle("fa-regular",!a));const s=e.querySelector(c.starlabel),r=a?e.dataset.starRemove:e.dataset.starAdd;s&&r&&(s.textContent=r)},f=async e=>{if(e.dataset.busy==="1")return;e.dataset.busy="1";const a=Number(e.dataset.courseid),t=e.getAttribute("aria-pressed")!=="true";i(e,t);try{const s=await n({methodname:m,args:{courseid:a,state:t}});i(e,!!s.favourite)}catch(s){i(e,!t),(await l("core/notification")).exception(s)}finally{e.dataset.busy="0"}},L=()=>{document.body.classList.add(d),!o&&(o=!0,document.addEventListener("click",e=>{const a=e.target;if(!(a instanceof Element))return;const t=a.closest(c.star);t&&(e.preventDefault(),f(t))}))};export{L as arm};
