var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The report's filters as core stores them, read and rewritten for the panel and the chips.
 *
 * Core keeps every filter value of a report for a user in one store, keyed "<filter>_<field>", and
 * core_reportbuilder_set_filters REPLACES that whole set. So a change to one filter is written as every
 * stored value, with that filter's own keys changed, and a removal as every other filter's keys. Every
 * filter the panel draws is a competency's rating (the rows answer names its facet "choice"): the theme's
 * reportbuilder\local\filters\rating, core's select, which stores "<filter>_operator" and "<filter>_value" and
 * reads EQUAL_TO with one value, "Not rated" being the value 0. The learner's name and the identity fields are
 * set in core's table view and appear here as chips that remove them. Copied from local_evidencehub's
 * hub/filters.ts without the hub's own filter names.
 *
 * @module     theme_dimensions/classreport/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const ANY_VALUE = 0;
const EQUAL_TO = 1;
const choosable = /* @__PURE__ */ __name((filter) => filter.facet !== "" && filter.options.length > 0, "choosable");
const ownValues = /* @__PURE__ */ __name((filter) => {
  try {
    const parsed = JSON.parse(filter.values || "{}");
    return parsed !== null && typeof parsed === "object" ? parsed : {};
  } catch {
    return {};
  }
}, "ownValues");
const allValues = /* @__PURE__ */ __name((filters) => filters.reduce((all, filter) => ({ ...all, ...ownValues(filter) }), {}), "allValues");
const chosen = /* @__PURE__ */ __name((filter) => {
  if (!filter.applied || filter.facet === "") {
    return "";
  }
  const own = ownValues(filter);
  const value = own[`${filter.name}_value`];
  if (value === void 0 || value === null || Number(own[`${filter.name}_operator`] ?? ANY_VALUE) !== EQUAL_TO) {
    return "";
  }
  return String(value);
}, "chosen");
const without = /* @__PURE__ */ __name((filters, filter) => {
  const values = allValues(filters);
  for (const key of Object.keys(values)) {
    if (key.startsWith(`${filter.name}_`)) {
      delete values[key];
    }
  }
  return values;
}, "without");
const withChoice = /* @__PURE__ */ __name((filters, filter, value) => {
  const values = without(filters, filter);
  values[`${filter.name}_operator`] = value === "" ? ANY_VALUE : EQUAL_TO;
  values[`${filter.name}_value`] = value;
  return values;
}, "withChoice");
const chosenLabel = /* @__PURE__ */ __name((filter) => {
  const value = chosen(filter);
  if (value === "") {
    return "";
  }
  return filter.options.find((option) => option.value === value)?.label ?? "";
}, "chosenLabel");
export {
  allValues,
  choosable,
  chosen,
  chosenLabel,
  ownValues,
  withChoice,
  without
};
//# sourceMappingURL=filters.dev.js.map
