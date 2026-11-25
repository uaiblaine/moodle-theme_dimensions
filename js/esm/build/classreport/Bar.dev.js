var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The class report's filter bar: the shared bar (datareport/Bar) with the report's vocabulary.
 *
 * Filters draws one facet per shown competency, its rating: the scale's values and "Not rated", each option
 * with the learners it would list under every other criterion in force; they live in core's store
 * (core_reportbuilder_set_filters, filters.ts). The report's other filters (the learner's name, the identity
 * fields) are set in core's table view, which a note under the facets leads to, and appear here as chips that
 * remove them. Columns lists the learner, always shown, then every competency of the course (or the activity),
 * at most the props' maxcolumns of them shown at once. View is Automatic, Table or Cards, then core's table
 * view. Download is a menu of the site's enabled formats, each a link to core's reportbuilder/download.php with
 * the report's own parameters (the activity, the page's group and the search in force), so the file holds what
 * the list shows; the report applies the stored filters and the columns itself. It is offered once rows have
 * arrived and while there are any. Shaped after local_evidencehub's hub/Bar.tsx.
 *
 * @module     theme_dimensions/classreport/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Bar as DataBar } from "../datareport/Bar";
import { fill, fillObject } from "../datareport/str";
import { choosable, chosen, chosenLabel } from "./filters";
const VIEWS = ["auto", "table", "cards"];
const facet = /* @__PURE__ */ __name((labels, filter) => ({
  name: filter.name,
  title: filter.title,
  options: filter.options,
  find: { label: labels.searchoptions, nomatch: labels.nooptionmatch }
}), "facet");
const chip = /* @__PURE__ */ __name((labels, filter) => {
  const value = chosenLabel(filter);
  return { key: filter.name, text: value === "" ? filter.title : fillObject(labels.filterchip, { title: filter.title, value }) };
}, "chip");
const downloadHref = /* @__PURE__ */ __name((url, format, moduleid, groupid, search) => {
  const parameters = { moduleid, groupid };
  if (search !== "") {
    parameters.search = search;
  }
  const query = new URLSearchParams({ download: format, parameters: JSON.stringify(parameters) });
  return `${url}${url.includes("?") ? "&" : "?"}${query.toString()}`;
}, "downloadHref");
const Bar = /* @__PURE__ */ __name((props) => {
  const { labels, filters } = props;
  const panelfilters = filters.filter(choosable);
  const others = filters.filter((filter) => !choosable(filter));
  const named = /* @__PURE__ */ __name((name) => filters.find((filter) => filter.name === name), "named");
  const downloads = props.downloadable && props.downloadurl !== "" ? props.formats.map((format) => ({
    key: format.value,
    label: format.label,
    href: downloadHref(props.downloadurl, format.value, props.moduleid, props.groupid, props.appliedsearch)
  })) : [];
  return /* @__PURE__ */ jsxDEV(
    DataBar,
    {
      labels,
      narrow: props.narrow,
      total: props.total,
      facets: panelfilters.map((filter) => facet(labels, filter)),
      values: Object.fromEntries(panelfilters.map((filter) => [filter.name, chosen(filter)])),
      panelnote: /* @__PURE__ */ jsxDEV(Fragment, { children: [
        panelfilters.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "dim-comp-rview-note", children: labels.nofacets }, void 0, false, {
          fileName: "public/theme/dimensions/js/esm/src/classreport/Bar.tsx",
          lineNumber: 159,
          columnNumber: 50
        }) : null,
        others.length > 0 ? /* @__PURE__ */ jsxDEV("p", { className: "dim-comp-rview-note", children: [
          labels.morefiltershelp,
          " ",
          /* @__PURE__ */ jsxDEV("a", { href: props.tableurl, children: labels.morefilters }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/classreport/Bar.tsx",
            lineNumber: 163,
            columnNumber: 29
          })
        ] }, void 0, true, {
          fileName: "public/theme/dimensions/js/esm/src/classreport/Bar.tsx",
          lineNumber: 161,
          columnNumber: 25
        }) : null
      ] }, void 0, true, {
        fileName: "public/theme/dimensions/js/esm/src/classreport/Bar.tsx",
        lineNumber: 158,
        columnNumber: 17
      }),
      chips: filters.filter((filter) => filter.applied).map((filter) => chip(labels, filter)),
      columns: props.columns,
      limit: { most: props.maxcolumns, note: fill(labels.columnlimit, String(props.maxcolumns)) },
      usedefault: props.usedefault,
      haswidths: props.haswidths,
      view: props.view,
      views: VIEWS,
      tableurl: props.tableurl,
      downloads,
      search: props.search,
      searchmin: props.searchmin,
      searching: props.searching,
      onSearch: props.onSearch,
      onFacet: (name, value) => {
        const filter = named(name);
        if (filter) {
          props.onFilter(filter, value);
        }
      },
      onRemoveChip: (name) => {
        const filter = named(name);
        if (filter) {
          props.onRemoveFilter(filter);
        }
      },
      onClearFilters: props.onClearFilters,
      onClearAll: props.onClearAll,
      onColumn: props.onColumn,
      onDefaultColumns: props.onDefaultColumns,
      onResetWidths: props.onResetWidths,
      onView: props.onView
    },
    void 0,
    false,
    {
      fileName: "public/theme/dimensions/js/esm/src/classreport/Bar.tsx",
      lineNumber: 151,
      columnNumber: 9
    }
  );
}, "Bar");
export {
  Bar,
  downloadHref
};
//# sourceMappingURL=Bar.dev.js.map
