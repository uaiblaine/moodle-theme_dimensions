<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Theme Dimensions - Where core's calendar view leads to
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

/**
 * The decision to send a request for core's calendar view to the theme's calendar page, and the address it goes to.
 *
 * Pure: the request, the setting, the account and the calendar type arrive as arguments, and the one lookup (a
 * course's category) through a closure, so every branch is PHPUnit's, and the callback
 * ({@see \theme_dimensions\local\hook\after_config}) only reads them. Every view of core's calendar is sent
 * (docs/calendar/PROPOSAL.md section 27.1); each refusal left is a reason core's page must keep answering:
 *
 * 1. The setting is off, or the site's theme is not this one (the page is drawn by this theme's CSS and client).
 * 2. Nobody real is logged in: the page sends a visitor to login and the guest to core's calendar, so sending either
 *    there would loop. The account is {@see account()}, the same two facts page::handle() bounces on.
 * 3. The calendar type is not the Gregorian: the page sends it back ({@see window::require_gregorian()}).
 *
 * What is sent: the day view opens the month of its day with that day's drawer; every other view (month, upcoming, none,
 * anything) opens the month of the day the address names. A course other than the site narrows the page to the
 * course's category when the viewer may open the course ({@see course_category()}), else nothing; without a course, the
 * category the address names is forwarded. The page drops a category the range refuses, as it drops every value.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coreview {
    /** @var string The component the setting lives in. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string The setting; a checkbox that ships on and is off only when an explicit '0' is stored. */
    public const SETTING = 'calendarcoreview';

    /** @var string The theme whose page this is: the site theme must be it, as flavourcss\planner requires. */
    public const THEME = 'dimensions';

    /** @var string The one view whose address opens a day's drawer. */
    public const VIEW_DAY = 'day';

    /**
     * Whether core's views are sent to the page: the site runs this theme, and the setting is on unless an explicit
     * '0' was stored, so no upgrade step writes it.
     *
     * @return bool
     */
    public static function enabled(): bool {
        global $CFG;

        return ($CFG->theme ?? '') === self::THEME && (string) get_config(self::COMPONENT, self::SETTING) !== '0';
    }

    /**
     * The account making the request when it is one the page serves, else 0.
     *
     * The complement of page::handle()'s first two bounces (a visitor to login, the guest to core's calendar): an
     * account this returns is never sent back, so the two redirects cannot loop.
     *
     * @return int The user id, or 0 for a visitor and the guest.
     */
    public static function account(): int {
        global $USER;

        return isloggedin() && !isguestuser() ? (int) $USER->id : 0;
    }

    /**
     * Whether the viewer's calendar type is the Gregorian: the same test as window::require_gregorian().
     *
     * @return bool
     */
    public static function gregorian(): bool {
        return \core_calendar\type_factory::get_calendar_instance() instanceof \calendartype_gregorian\structure;
    }

    /**
     * The parameters of the current request this decision reads, as core's calendar/view.php reads them.
     *
     * @return array name => value.
     */
    public static function request_params(): array {
        return [
            'view' => (string) optional_param('view', '', PARAM_ALPHANUMEXT),
            'course' => (int) optional_param('course', SITEID, PARAM_INT),
            'cal_d' => (int) optional_param('cal_d', 0, PARAM_INT),
            'cal_m' => (int) optional_param('cal_m', 0, PARAM_INT),
            'cal_y' => (int) optional_param('cal_y', 0, PARAM_INT),
            'time' => (int) optional_param('time', 0, PARAM_INT),
            'category' => (int) optional_param('category', 0, PARAM_INT),
            'categoryid' => (int) optional_param('categoryid', 0, PARAM_INT),
        ];
    }

    /**
     * The category of a course the account may open, else 0: the scope a course calendar's address is sent with.
     *
     * The rule is core's own for a course calendar's export (calendar/export_execute.php, MDL-74915): the course exists
     * and can_access_course() lets the viewer in, which a manager who is not enrolled passes too. A course the viewer
     * may not open names no category, so the redirect tells nobody where a course sits; nor does a course in a category
     * the page would refuse as a scope ({@see scope::accepts_category()}: hidden by core, or withheld by
     * local_unlistedcourses), whose id would otherwise land in the address.
     *
     * @param int $courseid The course the address names, neither 0 nor the site.
     * @return int The category id, or 0.
     */
    public static function course_category(int $courseid): int {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id, category, visible');
        if (!$course || !can_access_course($course)) {
            return 0;
        }
        // Only a category the page would accept as a scope: the rule of scope::for_viewer(), not a copy of it.
        $category = \core_course_category::get((int) $course->category, IGNORE_MISSING);
        return scope::accepts_category($category ?: null) ? (int) $category->id : 0;
    }

    /**
     * Decide a request for core's calendar view: the page's address, or null to let core answer.
     *
     * @param array $request The request's parameters ({@see request_params()}); absent ones count as unset.
     * @param bool $enabled Whether the setting is on ({@see enabled()}).
     * @param int $account The account making the request, 0 for a visitor or the guest ({@see account()}).
     * @param bool $gregorian Whether the viewer's calendar type is the Gregorian.
     * @param \DateTimeZone $timezone The viewer's timezone.
     * @param int $now The current time, for a request that names no day.
     * @param \Closure $categoryof The category of a course the viewer may open, 0 otherwise ({@see course_category()}),
     *     asked only for an address naming a course.
     * @return \core\url|null
     */
    public static function target(
        array $request,
        bool $enabled,
        int $account,
        bool $gregorian,
        \DateTimeZone $timezone,
        int $now,
        \Closure $categoryof
    ): ?\core\url {
        if (!$enabled) {
            return null;
        }
        if ($account <= 0 || !$gregorian) {
            return null;
        }

        $day = self::day($request, $timezone, $now);
        $params = [state::PARAM_MONTH => substr($day, 0, 7)];
        if ((string) ($request['view'] ?? '') === self::VIEW_DAY) {
            $params[page::PARAM_DAY] = $day;
        }
        $courseid = (int) ($request['course'] ?? SITEID);
        if ($courseid > 0 && $courseid !== (int) SITEID) {
            $category = (int) $categoryof($courseid);
        } else {
            $category = (int) ($request['category'] ?? 0) ?: (int) ($request['categoryid'] ?? 0);
        }
        if ($category > 0) {
            $params[state::PARAM_CATEGORY] = $category;
        }
        return page::url($params);
    }

    /**
     * The day a request names, YYYY-MM-DD.
     *
     * Core's own rule: `cal_d`, `cal_m` and `cal_y` when they form a real date (read as the Gregorian) of a four-digit
     * year, which the page's month parameter can spell, else `time` (the current time when absent), whose date is
     * the one in the viewer's timezone, not UTC's.
     *
     * @param array $request The request's parameters.
     * @param \DateTimeZone $timezone The viewer's timezone.
     * @param int $now The current time.
     * @return string
     */
    private static function day(array $request, \DateTimeZone $timezone, int $now): string {
        $d = (int) ($request['cal_d'] ?? 0);
        $m = (int) ($request['cal_m'] ?? 0);
        $y = (int) ($request['cal_y'] ?? 0);
        if ($y <= 9999 && checkdate($m, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
        $time = (int) ($request['time'] ?? 0) ?: $now;
        return (new \DateTimeImmutable('@' . $time))->setTimezone($timezone)->format('Y-m-d');
    }
}
