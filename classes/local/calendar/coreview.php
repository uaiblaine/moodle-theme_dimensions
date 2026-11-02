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
 * Theme Dimensions - Where core's month and day views lead to
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

/**
 * The decision to send a request for core's month or day view to the theme's calendar page, and the address it goes to.
 *
 * Pure: the request, the setting, the account and the calendar type arrive as arguments, so every branch is
 * PHPUnit's, and the callback ({@see \theme_dimensions\local\hook\after_config}) only reads them. Each refusal is a
 * reason core's page must keep answering; the order is docs/calendar/PROPOSAL.md section 15.1:
 *
 * 1. The setting is off, or the site's theme is not this one (the page is drawn by this theme's CSS and client).
 * 2. The request carries the classic marker ({@see PARAM_CLASSIC}): it is not sent, and for a real account it sets
 *    the session flag to that account's id.
 * 3. The view is neither `month` nor `day` (core's default, `upcoming`, stays core's).
 * 4. The course is not the site, read with core's default (`SITEID`): the page has no course scope, and an explicit
 *    `course=0` is left to core, which refuses it.
 * 5. Nobody real is logged in: the page sends a visitor to login and the guest to core's calendar, so sending either
 *    there would loop. The account is {@see account()}, the same two facts page::handle() bounces on.
 * 6. The calendar type is not the Gregorian: the page sends it back ({@see window::require_gregorian()}).
 * 7. The session flag holds this account's id: the reader chose core's calendar, and its AJAX month arrows and view
 *    switch push addresses without the marker. A flag written for another account (the session survives a login)
 *    does not count.
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

    /** @var string The marker the page's "Classic calendar" links carry. */
    public const PARAM_CLASSIC = 'classic';

    /** @var string The session property that keeps core's calendar answering; navigation, never saved. */
    public const SESSION_FLAG = 'theme_dimensions_calendar_classic';

    /** @var string The theme whose page this is: the site theme must be it, as flavourcss\planner requires. */
    public const THEME = 'dimensions';

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
            self::PARAM_CLASSIC => (int) optional_param(self::PARAM_CLASSIC, 0, PARAM_INT),
        ];
    }

    /**
     * Decide a request for core's calendar view: the page's address, or null to let core answer.
     *
     * @param array $request The request's parameters ({@see request_params()}); absent ones count as unset.
     * @param bool $enabled Whether the setting is on ({@see enabled()}).
     * @param int $account The account making the request, 0 for a visitor or the guest ({@see account()}).
     * @param bool $gregorian Whether the viewer's calendar type is the Gregorian.
     * @param \stdClass $session The session object; the classic marker of a real account writes {@see SESSION_FLAG}.
     * @param \DateTimeZone $timezone The viewer's timezone.
     * @param int $now The current time, for a request that names no day.
     * @return \core\url|null
     */
    public static function target(
        array $request,
        bool $enabled,
        int $account,
        bool $gregorian,
        \stdClass $session,
        \DateTimeZone $timezone,
        int $now
    ): ?\core\url {
        if (!$enabled) {
            return null;
        }
        if (!empty($request[self::PARAM_CLASSIC])) {
            if ($account > 0) {
                $session->{self::SESSION_FLAG} = $account;
            }
            return null;
        }
        $view = (string) ($request['view'] ?? '');
        if ($view !== 'month' && $view !== 'day') {
            return null;
        }
        if ((int) ($request['course'] ?? SITEID) !== (int) SITEID) {
            return null;
        }
        if ($account <= 0 || !$gregorian) {
            return null;
        }
        if (($session->{self::SESSION_FLAG} ?? null) === $account) {
            return null;
        }

        $day = self::day($request, $timezone, $now);
        $params = [state::PARAM_MONTH => substr($day, 0, 7)];
        if ($view === 'day') {
            $params[page::PARAM_DAY] = $day;
        }
        $category = (int) ($request['category'] ?? 0) ?: (int) ($request['categoryid'] ?? 0);
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
