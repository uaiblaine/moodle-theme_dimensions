import{fetchOne as s}from"@moodle/lms/core/ajax";/**
 * The only module of the class report's view that talks to the server's web services.
 *
 * Every call goes through core's ES module @moodle/lms/core/ajax (a component cannot import an AMD module),
 * and every call is ONE request of its own (fetchOne), never a batch: core keeps one instance of a report per
 * user for the whole request, so a rows call batched after a filter or preference write would read the report
 * built before it (the preference's writer never builds the report for that reason,
 * classes/external/set_class_report_preference.php). Core's ajax holds Behat's pending counter while each
 * request is in flight; the view holds it across the render that follows (datareport/pending.ts). Copied from
 * local_evidencehub's hub/repository.ts.
 *
 * The report is named by its course, its activity and the page's group: the group is always the one the page
 * resolved, never -1, so the session's choice is never read on another request than the page's. Core's filter
 * services rebuild the report from its persistent with the same activity and group as its parameters, so the
 * filters they read are the ones the rows show.
 *
 * @module     theme_dimensions/classreport/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const m=(r,e,o)=>s({methodname:"theme_dimensions_class_report_rows",args:{...r,search:e.search,page:e.page,perpage:e.perpage,sort:e.sort,dir:e.direction,countfacets:o}}),a=(r,e)=>s({methodname:"theme_dimensions_class_report_summary",args:{...r,search:e}}),i=(r,e)=>s({methodname:"theme_dimensions_set_class_report_preference",args:{courseid:r,usedefault:e.usedefault,columns:e.columns,widths:e.widths}}),t=r=>JSON.stringify({moduleid:r.moduleid,groupid:r.groupid}),p=(r,e,o)=>s({methodname:"core_reportbuilder_set_filters",args:{reportid:r,parameters:t(e),values:JSON.stringify(o)}}),d=(r,e)=>s({methodname:"core_reportbuilder_filters_reset",args:{reportid:r,parameters:t(e)}});export{m as getRows,a as getSummary,t as reportParameters,d as resetFilters,p as setFilters,i as setPreference};
