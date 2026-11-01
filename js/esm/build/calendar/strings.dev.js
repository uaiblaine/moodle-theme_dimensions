var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The calendar page's strings: read once, then filled in place.
 *
 * The page hands every one of them to core's string cache with itself (calendar_page::STRINGS, through
 * strings_for_js), so {@see load()} resolves without a request. A label with a count or a date is filled here from
 * the string read once ({@see fill()}, core's own placeholders, {$a} and {$a->name}), rather than asked of
 * stringUtils again for each of the forty-two days of a grid. calendar_component_source_test holds the list against
 * the server's.
 *
 * @module     theme_dimensions/calendar/strings
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { getStrings } from "@moodle/lms/core/stringUtils";
const COMPONENT = "theme_dimensions";
const KEYS = [
  "calendar_actiononly",
  "calendar_barlabel",
  "calendar_category",
  "calendar_categoryall",
  "calendar_categorycourses",
  "calendar_categorynone",
  "calendar_categorysearch",
  "calendar_categorysearchplaceholder",
  "calendar_categorytree",
  "calendar_city",
  "calendar_cityany",
  "calendar_classic",
  "calendar_clearfilters",
  "calendar_colourby",
  "calendar_colourbycategory",
  "calendar_colourbykey",
  "calendar_dayitems_many",
  "calendar_dayitems_none",
  "calendar_dayitems_one",
  "calendar_daylabel",
  "calendar_daytoday",
  "calendar_filtercount",
  "calendar_key_activity",
  "calendar_key_category",
  "calendar_key_course",
  "calendar_key_group",
  "calendar_key_open",
  "calendar_key_other",
  "calendar_key_site",
  "calendar_key_user",
  "calendar_keys",
  "calendar_modality",
  "calendar_model",
  "calendar_moreaction",
  "calendar_morefilters",
  "calendar_morelabel",
  "calendar_nextmonth",
  "calendar_overdue",
  "calendar_prevmonth",
  "calendar_recursive",
  "calendar_span",
  "calendar_title",
  "calendar_today",
  "calendar_truncated",
  "calendar_until",
  "calendar_view",
  "calendar_viewmonth",
  "calendar_withsub"
];
const load = /* @__PURE__ */ __name(() => getStrings(KEYS.map((key) => ({ key, component: COMPONENT }))).then((values) => Object.fromEntries(KEYS.map((key, index) => [key, values[index]]))), "load");
const fill = /* @__PURE__ */ __name((template, values) => {
  if (typeof values !== "object") {
    return template.split("{$a}").join(String(values));
  }
  return template.replace(/\{\$a->([a-z0-9_]+)\}/gi, (whole, name) => name in values ? String(values[name]) : whole);
}, "fill");
export {
  KEYS,
  fill,
  load
};
//# sourceMappingURL=strings.dev.js.map
