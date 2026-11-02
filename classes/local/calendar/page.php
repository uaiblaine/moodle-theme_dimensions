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
 * Theme Dimensions - The whole decision of the calendar page, for both its entries.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use theme_dimensions\local\landing\links;
use theme_dimensions\output\calendar_page;

/**
 * The calendar page's answer: a redirect, or the page with its first range answer embedded.
 *
 * Both entries call {@see handle()} and decide nothing themselves: the route
 * ({@see \theme_dimensions\route\controller\calendar}) and the script `calendar.php`, which answers 303 to the
 * route while the router is configured. The order is the decision:
 *
 * 1. A visitor goes to the login page, with this page as the place to come back to.
 * 2. The guest account goes to core's calendar, which is the guest's (core's primary navigation links it there).
 * 3. require_login() for a real account: the site policy, a forced password change and the rest of core's gate.
 *    Then the "classic for this session" flag is cleared ({@see coreview}): opening the page ends the choice.
 * 4. A calendar type other than the Gregorian goes to core's calendar: the range refuses it ({@see window}).
 * 5. The month and the state are read, every value outside its vocabulary dropped ({@see state}); a category the
 *    range refuses (gone, hidden, unlisted) is dropped like any other value, so a stale saved category or a shared
 *    link to a withheld one shows the whole calendar and says nothing about the id. The open day ({@see PARAM_DAY})
 *    is kept only inside the month grid's window ({@see day_from_request()}).
 * 6. The month grid's window, the range's answer ({@see range::answer()}) and the category tree are computed once
 *    and handed to the renderable, which embeds them: the first paint costs no request.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class page {
    /** @var string The page type, spelled out: under the router core would derive it from r.php. */
    public const PAGETYPE = 'theme-dimensions-calendar';

    /** @var string The body class every rule of scss/calendar.scss is scoped under. */
    public const BODY_CLASS = 'dim-calendar';

    /**
     * @var string The address parameter of the day the drawer (or the phone's sheet) shows, YYYY-MM-DD.
     *
     * Navigation like the month, never state: it is not in {@see state::STATE_PARAMS} and never saved, so a reload
     * reopens the drawer and a new visit does not, and an address naming only the month and the day is still bare.
     */
    public const PARAM_DAY = 'day';

    /** @var \core\url|null Where to send the client, or null. */
    public readonly ?\core\url $redirect;

    /** @var calendar_page|null The page to render, or null. */
    public readonly ?calendar_page $page;

    /**
     * Hold an answer; use {@see handle()}.
     *
     * @param \core\url|null $redirect Where to send the client.
     * @param calendar_page|null $page The page to render.
     */
    private function __construct(?\core\url $redirect, ?calendar_page $page) {
        $this->redirect = $redirect;
        $this->page = $page;
    }

    /**
     * The address of the script serving the page.
     *
     * @param array $params Query parameters, name => value.
     * @return \core\url
     */
    public static function legacy(array $params = []): \core\url {
        return new \core\url('/theme/dimensions/calendar.php', $params);
    }

    /**
     * The canonical address of the page: the route while the router is configured, the script otherwise.
     *
     * Never the /r.php/ spelling, which works but is longer than the script's own address.
     *
     * @param array $params Query parameters, name => value (arrays as lists).
     * @return \core\url
     */
    public static function url(array $params = []): \core\url {
        if (!links::routing_enabled()) {
            return self::legacy($params);
        }
        $url = \core\router\util::get_path_for_callable([\theme_dimensions\route\controller\calendar::class, 'view'], []);
        $url->params($params);
        return $url;
    }

    /**
     * Core's own month view of a month: the classic calendar the page keeps reachable (decision D1).
     *
     * Spelled with core's day, month and year parameters, which calendar/view.php turns into a time in the
     * viewer's own timezone (make_timestamp()), so neither this class nor the client computes a timestamp.
     * The classic marker keeps {@see coreview} from sending the reader straight back to this page.
     *
     * @param string $month The month, YYYY-MM, known to be one.
     * @return \core\url
     */
    public static function classic(string $month): \core\url {
        [$year, $number] = explode('-', $month);
        return new \core\url('/calendar/view.php', [
            'view' => 'month',
            'cal_d' => 1,
            'cal_m' => (int) $number,
            'cal_y' => (int) $year,
            coreview::PARAM_CLASSIC => 1,
        ]);
    }

    /**
     * Core's calendar for whoever this page does not serve: the guest and the non-Gregorian viewer.
     *
     * @return \core\url
     */
    public static function core_calendar(): \core\url {
        return new \core\url('/calendar/view.php', ['view' => 'month']);
    }

    /**
     * The month the request names, or the current one in the viewer's timezone; anything malformed is dropped.
     *
     * @return string YYYY-MM.
     */
    public static function month_from_request(): string {
        $month = (string) optional_param(state::PARAM_MONTH, '', PARAM_RAW);
        if (
            preg_match('/^(\d{4})-(\d{2})$/', $month, $parts)
            && (int) $parts[2] >= 1 && (int) $parts[2] <= 12
            && (int) $parts[1] >= 1900 && (int) $parts[1] <= 2999
        ) {
            return $month;
        }
        return self::today()->format('Y-m');
    }

    /**
     * Whether a string is a real day, YYYY-MM-DD: the shape and the calendar both (no 31 November).
     *
     * @param string $day The candidate.
     * @return bool
     */
    public static function is_day(string $day): bool {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $day, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    /**
     * The day the request opens, kept only inside the month grid's window; anything else is dropped, never refused.
     *
     * The window is {@see grid()}'s, the days the answer embedded for the client covers: a day of the neighbours'
     * weeks shown around the month is kept, a day of any other month is not, so the client is never asked to open a
     * day it holds no answer for.
     *
     * @param string $month The month shown, YYYY-MM.
     * @param int $firstday The weekday a week begins on, 0 for Sunday.
     * @return string The day, YYYY-MM-DD, or '' for none.
     */
    public static function day_from_request(string $month, int $firstday): string {
        $day = (string) optional_param(self::PARAM_DAY, '', PARAM_RAW);
        if (!self::is_day($day)) {
            return '';
        }
        [$from, $to] = self::grid($month, $firstday);
        return $day >= $from && $day <= $to ? $day : '';
    }

    /**
     * Now, in the viewer's timezone.
     *
     * @return \DateTimeImmutable
     */
    public static function today(): \DateTimeImmutable {
        return new \DateTimeImmutable('now', \core_date::get_user_timezone_object());
    }

    /**
     * The month grid's first and last day: whole weeks from the one holding the 1st to the one holding the last day.
     *
     * Calendar days counted in UTC, as window does, so no timezone moves a day; the client's grid
     * (`calendar/days.ts`, over `daterange/calendar.ts`'s weeks()) draws the same weeks.
     *
     * @param string $month The month, YYYY-MM.
     * @param int $firstday The weekday a week begins on, 0 for Sunday.
     * @return string[] [from, to], YYYY-MM-DD; at most six weeks, so one window.
     */
    public static function grid(string $month, int $firstday): array {
        $utc = new \DateTimeZone('UTC');
        $first = \DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01', $utc);
        $length = (int) $first->format('t');
        $lead = ((int) $first->format('w') - $firstday + 7) % 7;
        $rows = (int) ceil(($lead + $length) / 7);
        $from = $first->modify('-' . $lead . ' days');
        return [$from->format('Y-m-d'), $from->modify('+' . ($rows * 7 - 1) . ' days')->format('Y-m-d')];
    }

    /**
     * The state parameters of the current request a redirect to the route must carry, the month and the open day.
     *
     * The day is forwarded when it is a real day, without judging it against the month's window: that needs the
     * viewer's first weekday, and the page behind the route judges it ({@see day_from_request()}), as it judges
     * the category forwarded here as written.
     *
     * @return array name => value.
     */
    public static function forwarded_params(): array {
        $params = [];
        $month = optional_param(state::PARAM_MONTH, '', PARAM_RAW);
        if ($month !== '') {
            $params[state::PARAM_MONTH] = self::month_from_request();
        }
        $day = (string) optional_param(self::PARAM_DAY, '', PARAM_RAW);
        if (self::is_day($day)) {
            $params[self::PARAM_DAY] = $day;
        }
        $named = state::from_request();
        return $named === null ? $params : $params + $named->query();
    }

    /**
     * The calendar's scope for a state, the category dropped when the range refuses it.
     *
     * @param state $state The state asked for.
     * @return array [scope, state]: the state with its category kept or dropped.
     */
    public static function scope_for(state $state): array {
        if ($state->categoryid) {
            try {
                return [scope::for_viewer($state->categoryid, $state->recursive), $state];
            } catch (refusal $refusal) {
                $state = $state->with_category(0);
            }
        }
        return [scope::for_viewer(), $state];
    }

    /**
     * Decide the page.
     *
     * @return self The answer: a redirect or a page.
     */
    public static function handle(): self {
        global $CFG, $PAGE, $SESSION;

        require_once($CFG->dirroot . '/calendar/lib.php');
        if (!isloggedin()) {
            $SESSION->wantsurl = self::url(self::forwarded_params())->out(false);
            return new self(new \core\url(get_login_url()), null);
        }
        if (isguestuser()) {
            return new self(self::core_calendar(), null);
        }
        require_login(null, false);
        unset($SESSION->{coreview::SESSION_FLAG});
        try {
            window::require_gregorian();
        } catch (refusal $refusal) {
            return new self(self::core_calendar(), null);
        }

        $month = self::month_from_request();
        [$scope, $state] = self::scope_for(state::for_request());
        $root = $state->categoryid ? scope::for_viewer() : $scope;

        $firstday = (int) calendar_get_starting_weekday();
        $day = self::day_from_request($month, $firstday);
        [$from, $to] = self::grid($month, $firstday);
        $window = window::from_iso($from, $to);
        // Every key off asks no source: the answer is the window's days, each at zero (the service reads [] as all).
        $answer = range::answer($window, $scope, $state->on(), $state->filters());

        $context = \core\context\system::instance();
        $PAGE->set_context($context);
        $PAGE->set_pagetype(self::PAGETYPE);
        $PAGE->set_pagelayout('standard');
        $opened = $day !== '' ? [self::PARAM_DAY => $day] : [];
        $PAGE->set_url(self::url([state::PARAM_MONTH => $month] + $opened + $state->query()));
        $PAGE->set_title(get_string('calendar_title', 'theme_dimensions'));
        $PAGE->set_heading('');
        $PAGE->add_body_class(self::BODY_CLASS);
        $PAGE->requires->strings_for_js(calendar_page::STRINGS, 'theme_dimensions');

        return new self(null, new calendar_page(
            $month,
            self::today()->format('Y-m-d'),
            $firstday,
            \core_date::get_user_timezone(),
            $window,
            $answer,
            $state,
            tree::for_scope($root),
            $day
        ));
    }
}
