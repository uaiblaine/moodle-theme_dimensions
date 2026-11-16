import{fetchOne as S}from"@moodle/lms/core/ajax";import l from"@moodle/lms/core/config";import{getString as A}from"@moodle/lms/core/stringUtils";import{replaceKeeping as E}from"./history";import*as n from"./listing";import{current as g,init as P}from"./store";/**
 * The visitor's shell of the showcase listing: a public category page narrows, orders and pages in place.
 *
 * It asks theme_dimensions_get_public_category_rows, and nothing else, with login not required, which posts
 * to lib/ajax/service-nologin.php: no session is read or started, so the site's forced login never meets the
 * request. Never with a cachekey (service.php answers such a GET with ninety days of public caching, and the
 * service refuses it), one call per request, in the page's own language. Its mark is dim-public-js.
 *
 * The posters stay their links (the component's links option): every link is written to its area's panel
 * under the criteria on screen. A press asks the service for the area's rows - a public area by its own id,
 * a merely listed one as a slice of this page's listing - once per opening, after a quiet; no area opens by
 * itself. A failure is told in the page's own words, never the service's, and the address of the request is
 * loaded: the page a reader without a client gets.
 *
 * What it imports is the point: nothing that reads the session or asks a logged-in service
 * (categorypublic_source_test walks the whole graph from its entry, amd/src/listingvisitor.js).
 *
 * @module     theme_dimensions/listing/visitor
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const i={areas:'[data-region="dim-areas"]',area:'[data-region="dim-area"]',control:'[data-region="dim-poster-control"]',notice:'[data-region="dim-toolbar-notice"]'},O="theme_dimensions_get_public_category_rows",q="theme_dimensions",v="category_publicfallback",k="dim-public-js",N="dim-panel",f=8,T=120,_={links:!0,quiet:T,autoopen:!1},u="name",p={recent:"recent",name:"name"},c=n.PARAM,M=e=>{e.chip="",e.fav=!1,n.rootOnly(e)},h=e=>S({methodname:O,args:e},{loginrequired:!1}),U=e=>{const t=g();return n.isRoot(t)?{categoryid:0,query:t.query,lang:l.language}:{categoryid:t.categoryid,page:e,query:t.query,perpage:t.pagesize,filters:n.filterArgs(t),group:n.groupOf(t),sort:n.orderOf(t),datefrom:t.datefrom,dateto:t.dateto,cat:t.cat,lang:l.language}},w=(e,t)=>{const r=n.buildUrl(g());return r.searchParams.set(c.open,String(e)),t===u?r.searchParams.set(c.psort,u):r.searchParams.delete(c.psort),r.hash=N,r},y=()=>{const e=document.querySelector(i.areas);e&&e.querySelectorAll(i.area).forEach(t=>{const r=t.querySelector(i.control);!r||r.tagName!=="A"||(r.href=w(Number(t.dataset.categoryid),"").toString())})},L=e=>{const t=e.toString(),r=new URL(t);r.hash="";const a=new URL(window.location.href);if(a.hash="",r.toString()===a.toString()){E(t),window.location.reload();return}window.location.assign(t)},H=e=>{e.hash="",L(e)},b=async e=>{try{const t=await A(v,q),r=document.querySelector(i.notice);r&&(r.textContent=t,r.hidden=!1)}catch{}L(e)},C=(e,t)=>{const r=n.buildUrl(g());return t&&r.searchParams.set(c.page,String(e)),r.hash="",r},R=(e,t)=>{const r=Number(e.dataset.categoryid),a=t===u?p.name:p.recent,s=g(),o={categoryid:s.categoryid,view:s.view,viewchosen:s.viewchosen};if(n.readUrl(o),n.isRoot(o))return{categoryid:r,page:0,perpage:f,sort:a,query:o.query,withcategories:!0,lang:l.language};const m={query:o.query,filters:n.filterArgs(o),datefrom:o.datefrom,dateto:o.dateto,lang:l.language};return e.dataset.titleurl===""?Object.assign({categoryid:s.categoryid,slice:r,sort:a},m):Object.assign({categoryid:r,page:0,perpage:f,sort:a,withcategories:!0},m)},j=(e,t)=>h(R(e,t)),x=(e,t)=>JSON.stringify(R(e,t)),B=(e,t)=>(b(w(Number(e.dataset.categoryid),t)),!0),D=e=>h(U(e)),F=(e,t)=>{b(C(e,t))},d=()=>{},I={mark:k,normalise:M,rows:D,failed:F,changed:d,discard:null,painted:d,written:y,leave:H,answered:y,unmounted:d,areas:{props:_,client:{ask:j,question:x,unanswered:B}}},Z=e=>{P(I,Number(e))};export{Z as start};
