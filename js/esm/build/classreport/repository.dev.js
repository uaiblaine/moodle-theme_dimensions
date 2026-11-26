var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
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
 */
import { fetchOne } from "@moodle/lms/core/ajax";
const getRows = /* @__PURE__ */ __name((target, query, countfacets) => fetchOne({
  methodname: "theme_dimensions_class_report_rows",
  args: {
    ...target,
    search: query.search,
    page: query.page,
    perpage: query.perpage,
    sort: query.sort,
    dir: query.direction,
    countfacets
  }
}), "getRows");
const getSummary = /* @__PURE__ */ __name((target, search) => fetchOne({ methodname: "theme_dimensions_class_report_summary", args: { ...target, search } }), "getSummary");
const setPreference = /* @__PURE__ */ __name((courseid, preference) => fetchOne({
  methodname: "theme_dimensions_set_class_report_preference",
  args: {
    courseid,
    usedefault: preference.usedefault,
    columns: preference.columns,
    widths: preference.widths
  }
}), "setPreference");
const reportParameters = /* @__PURE__ */ __name((target) => JSON.stringify({ moduleid: target.moduleid, groupid: target.groupid }), "reportParameters");
const setFilters = /* @__PURE__ */ __name((reportid, target, values) => fetchOne({
  methodname: "core_reportbuilder_set_filters",
  args: { reportid, parameters: reportParameters(target), values: JSON.stringify(values) }
}), "setFilters");
const resetFilters = /* @__PURE__ */ __name((reportid, target) => fetchOne({
  methodname: "core_reportbuilder_filters_reset",
  args: { reportid, parameters: reportParameters(target) }
}), "resetFilters");
export {
  getRows,
  getSummary,
  reportParameters,
  resetFilters,
  setFilters,
  setPreference
};
//# sourceMappingURL=repository.dev.js.map
