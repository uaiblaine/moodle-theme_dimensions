import{getStrings as t}from"@moodle/lms/core/stringUtils";/**
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
 */const l="theme_dimensions",c=["calendar_actiononly","calendar_barlabel","calendar_category","calendar_categoryall","calendar_categorycourses","calendar_categorynone","calendar_categorysearch","calendar_categorysearchplaceholder","calendar_categorytree","calendar_city","calendar_cityany","calendar_classic","calendar_clearfilters","calendar_colourby","calendar_colourbycategory","calendar_colourbykey","calendar_dayitems_many","calendar_dayitems_none","calendar_dayitems_one","calendar_daylabel","calendar_daytoday","calendar_filtercount","calendar_key_activity","calendar_key_category","calendar_key_course","calendar_key_group","calendar_key_open","calendar_key_other","calendar_key_site","calendar_key_user","calendar_keys","calendar_modality","calendar_model","calendar_moreaction","calendar_morefilters","calendar_morelabel","calendar_nextmonth","calendar_overdue","calendar_prevmonth","calendar_recursive","calendar_span","calendar_title","calendar_today","calendar_truncated","calendar_until","calendar_view","calendar_viewmonth","calendar_withsub"],o=()=>t(c.map(a=>({key:a,component:l}))).then(a=>Object.fromEntries(c.map((e,r)=>[e,a[r]]))),_=(a,e)=>typeof e!="object"?a.split("{$a}").join(String(e)):a.replace(/\{\$a->([a-z0-9_]+)\}/gi,(r,n)=>n in e?String(e[n]):r);export{c as KEYS,_ as fill,o as load};
