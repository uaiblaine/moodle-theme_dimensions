import{fetchOne as R}from"@moodle/lms/core/ajax";import l from"@moodle/lms/core/config";import{getString as S}from"@moodle/lms/core/stringUtils";import{replaceKeeping as A}from"./history";import*as o from"./listing";import{current as g,init as E}from"./store";/**
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
 */const i={areas:'[data-region="dim-areas"]',area:'[data-region="dim-area"]',control:'[data-region="dim-poster-control"]',notice:'[data-region="dim-toolbar-notice"]'},P="theme_dimensions_get_public_category_rows",q="theme_dimensions",O="category_publicfallback",v="dim-public-js",k="dim-panel",u=8,N=120,T={links:!0,quiet:N,autoopen:!1},d="name",f={recent:"recent",name:"name"},c=o.PARAM,_=e=>{e.chip="",e.fav=!1,o.rootOnly(e)},y=e=>R({methodname:P,args:e},{loginrequired:!1}),M=e=>{const t=g();return o.isRoot(t)?{categoryid:0,query:t.query,lang:l.language}:{categoryid:t.categoryid,page:e,query:t.query,perpage:t.pagesize,filters:o.filterArgs(t),sort:t.sort,datefrom:t.datefrom,dateto:t.dateto,cat:t.cat,lang:l.language}},w=(e,t)=>{const r=o.buildUrl(g());return r.searchParams.set(c.open,String(e)),t===d?r.searchParams.set(c.psort,d):r.searchParams.delete(c.psort),r.hash=k,r},p=()=>{const e=document.querySelector(i.areas);e&&e.querySelectorAll(i.area).forEach(t=>{const r=t.querySelector(i.control);!r||r.tagName!=="A"||(r.href=w(Number(t.dataset.categoryid),"").toString())})},h=e=>{const t=e.toString(),r=new URL(t);r.hash="";const a=new URL(window.location.href);if(a.hash="",r.toString()===a.toString()){A(t),window.location.reload();return}window.location.assign(t)},U=e=>{e.hash="",h(e)},L=async e=>{try{const t=await S(O,q),r=document.querySelector(i.notice);r&&(r.textContent=t,r.hidden=!1)}catch{}h(e)},H=(e,t)=>{const r=o.buildUrl(g());return t&&r.searchParams.set(c.page,String(e)),r.hash="",r},b=(e,t)=>{const r=Number(e.dataset.categoryid),a=t===d?f.name:f.recent,s=g(),n={categoryid:s.categoryid,view:s.view,viewchosen:s.viewchosen};if(o.readUrl(n),o.isRoot(n))return{categoryid:r,page:0,perpage:u,sort:a,query:n.query,withcategories:!0,lang:l.language};const m={query:n.query,filters:o.filterArgs(n),datefrom:n.datefrom,dateto:n.dateto,lang:l.language};return e.dataset.titleurl===""?Object.assign({categoryid:s.categoryid,slice:r,sort:a},m):Object.assign({categoryid:r,page:0,perpage:u,sort:a,withcategories:!0},m)},C=(e,t)=>y(b(e,t)),j=(e,t)=>JSON.stringify(b(e,t)),x=(e,t)=>(L(w(Number(e.dataset.categoryid),t)),!0),B=e=>y(M(e)),D=(e,t)=>{L(H(e,t))},F={mark:v,normalise:_,rows:B,failed:D,written:p,leave:U,answered:p,areas:{props:T,client:{ask:C,question:j,unanswered:x}}},Y=e=>{E(F,Number(e))};export{Y as start};
