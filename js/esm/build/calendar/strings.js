import{getStrings as l}from"@moodle/lms/core/stringUtils";/**
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
 */const d="theme_dimensions",c=["calendar_actiononly","calendar_allday","calendar_barlabel","calendar_category","calendar_categoryall","calendar_categorycourses","calendar_categorynone","calendar_categorysearch","calendar_categorysearchplaceholder","calendar_categorytree","calendar_city","calendar_cityany","calendar_classic","calendar_classicday","calendar_clearfilters","calendar_close","calendar_colourby","calendar_colourbycategory","calendar_colourbykey","calendar_course","calendar_coursepage","calendar_dayempty","calendar_dayitems_many","calendar_dayitems_none","calendar_dayitems_one","calendar_daylabel","calendar_daytoday","calendar_descriptionloading","calendar_filtercount","calendar_join_free","calendar_join_key","calendar_join_open","calendar_key_activity","calendar_key_category","calendar_key_course","calendar_key_group","calendar_key_open","calendar_key_other","calendar_key_site","calendar_key_user","calendar_keys","calendar_modality","calendar_model","calendar_moreaction","calendar_morefilters","calendar_morelabel","calendar_nextmonth","calendar_nextweek","calendar_open","calendar_overdue","calendar_overlapmark","calendar_overlaps","calendar_participants_many","calendar_participants_one","calendar_place","calendar_prevmonth","calendar_prevweek","calendar_recursive","calendar_span","calendar_timed","calendar_title","calendar_today","calendar_truncated","calendar_until","calendar_view","calendar_viewmonth","calendar_viewweek","calendar_weekempty","calendar_when","calendar_withsub"],t=()=>l(c.map(a=>({key:a,component:d}))).then(a=>Object.fromEntries(c.map((e,r)=>[e,a[r]]))),o=(a,e)=>typeof e!="object"?a.split("{$a}").join(String(e)):a.replace(/\{\$a->([a-z0-9_]+)\}/gi,(r,n)=>n in e?String(e[n]):r);export{c as KEYS,o as fill,t as load};
