/**
 * The logged-in calendar's two services, kept out of the modules the visitor's calendar shares.
 *
 * The page's shell and the blocks' client import them; nothing a visitor's page loads may (the visitor's import walk,
 * tests/local/category/categorypublic_source_test.php, holds that).
 *
 * @module     theme_dimensions/calendar/account
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const e="theme_dimensions_get_calendar_range",_="theme_dimensions_remember_calendar_state",t="theme_dimensions_get_calendar_trails";export{e as RANGE_METHOD,_ as REMEMBER_METHOD,t as TRAILS_METHOD};
