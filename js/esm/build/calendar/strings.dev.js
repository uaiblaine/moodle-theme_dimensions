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
  "calendar_allday",
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
  "calendar_clearfilters",
  "calendar_close",
  "calendar_colourby",
  "calendar_colourbycategory",
  "calendar_colourbykey",
  "calendar_course",
  "calendar_coursepage",
  "calendar_dayempty",
  "calendar_dayitems_many",
  "calendar_dayitems_none",
  "calendar_dayitems_one",
  "calendar_daylabel",
  "calendar_daytoday",
  "calendar_delete",
  "calendar_descriptionloading",
  "calendar_edit",
  "calendar_eventpage",
  "calendar_eventtypes",
  "calendar_eventtypes_all",
  "calendar_eventtypes_some",
  "calendar_filtercount",
  "calendar_hoursbox",
  "calendar_join_free",
  "calendar_join_key",
  "calendar_join_open",
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
  "calendar_modelpage",
  "calendar_moreaction",
  "calendar_morefilters",
  "calendar_morelabel",
  "calendar_newevent",
  "calendar_neweventon",
  "calendar_nextday",
  "calendar_nextmonth",
  "calendar_nextweek",
  "calendar_open",
  "calendar_overdue",
  "calendar_overlapmark",
  "calendar_overlaps",
  "calendar_participants_many",
  "calendar_participants_one",
  "calendar_place",
  "calendar_prevday",
  "calendar_prevmonth",
  "calendar_prevweek",
  "calendar_recursive",
  "calendar_selectall",
  "calendar_span",
  "calendar_timed",
  "calendar_title",
  "calendar_today",
  "calendar_trail",
  "calendar_trailall",
  "calendar_trailcountmany",
  "calendar_trailcountone",
  "calendar_trailcourse",
  "calendar_trailcoursedates",
  "calendar_trailearlier",
  "calendar_trailenrolled",
  "calendar_trailexpected",
  "calendar_trailfrom",
  "calendar_trailheading",
  "calendar_traillater",
  "calendar_trailmine",
  "calendar_trailmonths",
  "calendar_trailnodates",
  "calendar_trailnotenrolled",
  "calendar_trailpage",
  "calendar_trailposition",
  "calendar_trailprogress",
  "calendar_trailsempty",
  "calendar_trailshow",
  "calendar_trailstruncated",
  "calendar_trailtoggle",
  "calendar_truncated",
  "calendar_truncatedpublic",
  "calendar_until",
  "calendar_view",
  "calendar_viewday",
  "calendar_viewmonth",
  "calendar_viewtrail",
  "calendar_viewweek",
  "calendar_weekempty",
  "calendar_when",
  "calendar_withsub",
  "landing_trail_coursecount",
  "landing_trail_onecourse",
  "landing_trail_startson",
  "landing_trail_state_current",
  "landing_trail_state_done",
  "landing_trail_state_late",
  "landing_trail_state_locked",
  "landing_trail_state_upcoming",
  "landing_trail_whenjoin"
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
