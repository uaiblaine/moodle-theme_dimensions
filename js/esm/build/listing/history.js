import{buildUrl as i,syncFold as r}from"./address";/**
 * The one writer of the showcase's history entries in js/esm/src (docs/vitrine-react/R4b.md, 2.3).
 *
 * On two disjoint sets of parameters: the listing's criteria ({@see writeUrl}) and the panel's open area and
 * order, replaced and never pushed ({@see replaceKeeping}). listing_source_test fails a write anywhere else.
 *
 * @module     theme_dimensions/listing/history
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const n=(t,e)=>{const o=i(t).toString();e?window.history.pushState({},"",o):window.history.replaceState({},"",o),r()},a=t=>{window.history.replaceState(window.history.state,"",t)};export{a as replaceKeeping,n as writeUrl};
