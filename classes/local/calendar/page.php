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

use theme_dimensions\local\blockless;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\trail\trail;
use theme_dimensions\output\calendar_page;
use theme_dimensions\output\opengraph;

/**
 * The calendar page's answer: a redirect, or the page with its first range answer embedded.
 *
 * Both entries call {@see handle()} and decide nothing themselves: the route
 * ({@see \theme_dimensions\route\controller\calendar}) and the script `calendar.php`, which answers 303 to the
 * route while the router is configured. The order is the decision:
 *
 * 1. A visitor goes to the login page, with this page as the place to come back to, before anything is looked up,
 *    unless the public calendar is switched on ({@see visitor::enabled()}): then the visitor is served the public
 *    calendar ({@see visitor()}), never through require_login().
 * 2. The guest account goes to core's calendar, which is the guest's (core's primary navigation links it there).
 * 3. require_login() for a real account: the site policy, a forced password change and the rest of core's gate.
 * 4. A calendar type other than the Gregorian goes to core's calendar: the range refuses it ({@see window}).
 * 5. The view, the month or the week, and the state are read, every value outside its vocabulary dropped ({@see state},
 *    {@see span()}); a category the range refuses (gone, hidden, unlisted) is dropped like any other value, so a stale
 *    saved category or a shared link to a withheld one shows the whole calendar and says nothing about the id. The
 *    open day ({@see PARAM_DAY}) is kept only inside the shown window ({@see day_from_request()}). The Trilhas view
 *    ({@see VIEW_TRAIL}) is read only for an account while trails are available, its trail and "Só as minhas" as
 *    navigation ({@see trail_from_request()}, {@see mine_from_request()}), and so is its timeline's zoom
 *    ({@see zoom_from_request()}): written to the address, never to the state.
 * 6. The window (the month grid's, or the week's seven days), the range's answer ({@see range::answer()}) and the
 *    category tree are computed once and handed to the renderable, which embeds them: the first paint costs no request.
 *    In the Trilhas view the range asks no source and the trails of the month and the next are the answer
 *    ({@see trails::answer()}).
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

    /**
     * @var string The address parameter of the view: {@see VIEW_WEEK}, {@see VIEW_DAY}, or absent for the month.
     *
     * Navigation like the open day, never state: an address naming only the view and the week (or the day) is still bare.
     */
    public const PARAM_VIEW = 'view';

    /** @var string The address parameter of the week shown, a day of it, YYYY-MM-DD; written as the week's first day. */
    public const PARAM_WEEK = 'week';

    /** @var string The month view, the page's default: never written into an address. */
    public const VIEW_MONTH = 'month';

    /** @var string The week view. */
    public const VIEW_WEEK = 'week';

    /**
     * @var string The day view, a phone's: its day is {@see PARAM_DAY}.
     *
     * The server cannot know the width, so it answers the day's month grid ({@see span()}): the phone's client draws the
     * day from it and a desktop's shows the month with the day's drawer open, never a desktop day view.
     */
    public const VIEW_DAY = 'day';

    /**
     * @var string The Trilhas view: the trails of the month, an account's only and only
     *   while trails are available ({@see trail::available()}); for anyone else the address reads as the month.
     */
    public const VIEW_TRAIL = 'trail';

    /** @var string The address parameter of the one trail the Trilhas view keeps, its course id; navigation, never state. */
    public const PARAM_TRAIL = 'trail';

    /** @var string The address parameter of the Trilhas view's "Só as minhas", exactly '1' when on; navigation. */
    public const PARAM_MINE = 'mine';

    /**
     * @var string The address parameter of the Trilhas timeline's zoom, {@see ZOOM_WEEK} or {@see ZOOM_MONTH}; absent for
     *   days. Navigation, like the trail: never state, never saved.
     */
    public const PARAM_ZOOM = 'zoom';

    /** @var string The Trilhas timeline by weeks. */
    public const ZOOM_WEEK = 'week';

    /** @var string The Trilhas timeline by months. */
    public const ZOOM_MONTH = 'month';

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
        $month = self::scalar_param(state::PARAM_MONTH);
        if (
            preg_match('/^(\d{4})-(\d{2})$/D', $month, $parts)
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
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $day, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    /**
     * The day the request opens, kept only inside the shown window; anything else is dropped, never refused.
     *
     * The window is the one the answer embedded for the client covers: the month grid's ({@see grid()}), where a day of
     * the neighbours' weeks shown around the month is kept and a day of any other month is not, or in the week view the
     * week's seven days ({@see week()}). So the client is never asked to open a day it holds no answer for.
     *
     * @param string $month The month shown, YYYY-MM.
     * @param int $firstday The weekday a week begins on, 0 for Sunday.
     * @param string $week The week shown, its first day, YYYY-MM-DD; '' for the month view.
     * @return string The day, YYYY-MM-DD, or '' for none.
     */
    public static function day_from_request(string $month, int $firstday, string $week = ''): string {
        $day = self::scalar_param(self::PARAM_DAY);
        if (!self::is_day($day)) {
            return '';
        }
        [$from, $to] = $week !== '' ? self::week($week) : self::grid($month, $firstday);
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
     * A request parameter as one value, or '' when it is absent or a list.
     *
     * optional_param() throws for a list (core\param::clean()), which on the public calendar would answer a visitor's
     * `view[]=week` with an error page; the page drops a value outside its vocabulary instead.
     *
     * @param string $name The parameter.
     * @return string
     */
    public static function scalar_param(string $name): string {
        $raw = $_POST[$name] ?? $_GET[$name] ?? '';
        return is_scalar($raw) ? (string) $raw : '';
    }

    /**
     * The view the request names: the week for exactly {@see VIEW_WEEK}, the day for exactly {@see VIEW_DAY}, the
     * Trilhas view for exactly {@see VIEW_TRAIL} where {@see trails_offered()}, the month for anything else, never
     * refused.
     *
     * @return string {@see VIEW_MONTH}, {@see VIEW_WEEK}, {@see VIEW_DAY} or {@see VIEW_TRAIL}.
     */
    public static function view_from_request(): string {
        if (self::scalar_param(self::PARAM_VIEW) === self::VIEW_TRAIL) {
            return self::trails_offered() ? self::VIEW_TRAIL : self::VIEW_MONTH;
        }
        if (self::scalar_param(self::PARAM_VIEW) === self::VIEW_DAY) {
            return self::VIEW_DAY;
        }
        return self::scalar_param(self::PARAM_VIEW) === self::VIEW_WEEK ? self::VIEW_WEEK : self::VIEW_MONTH;
    }

    /**
     * Whether the page offers the Trilhas view: to a real account, while trails are available.
     *
     * A visitor's public calendar has no such view (the calendar asks an account to log in for it),
     * and without mod_subcourse there are no trails.
     *
     * @return bool
     */
    public static function trails_offered(): bool {
        return isloggedin() && !isguestuser() && trail::available();
    }

    /**
     * The one trail the request keeps, a course id; anything that is not a positive integer is 0, every trail.
     *
     * Not judged here: a trail the viewer may not discover is simply not in the answer ({@see trails::answer()}).
     *
     * @return int
     */
    public static function trail_from_request(): int {
        $trail = self::scalar_param(self::PARAM_TRAIL);
        return ctype_digit($trail) && strlen($trail) <= 10 ? (int) $trail : 0;
    }

    /**
     * Whether the request keeps only the viewer's own trails: exactly '1'.
     *
     * @return bool
     */
    public static function mine_from_request(): bool {
        return self::scalar_param(self::PARAM_MINE) === '1';
    }

    /**
     * The Trilhas timeline's zoom the request names: exactly {@see ZOOM_WEEK} or {@see ZOOM_MONTH}, '' (days) for
     * anything else, a list included.
     *
     * @return string
     */
    public static function zoom_from_request(): string {
        $zoom = self::scalar_param(self::PARAM_ZOOM);
        return in_array($zoom, [self::ZOOM_WEEK, self::ZOOM_MONTH], true) ? $zoom : '';
    }

    /**
     * The first day of the week holding a day, on the viewer's first weekday; calendar days counted in UTC, as grid() does.
     *
     * @param string $day A real day, YYYY-MM-DD ({@see is_day()}).
     * @param int $firstday The weekday a week begins on, 0 for Sunday.
     * @return string YYYY-MM-DD.
     */
    public static function week_start(string $day, int $firstday): string {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $day, new \DateTimeZone('UTC'));
        $lead = ((int) $date->format('w') - $firstday + 7) % 7;
        return $date->modify('-' . $lead . ' days')->format('Y-m-d');
    }

    /**
     * A week's first and last day.
     *
     * @param string $start The week's first day, YYYY-MM-DD.
     * @return string[] [from, to], YYYY-MM-DD: seven days, one window.
     */
    public static function week(string $start): array {
        $first = \DateTimeImmutable::createFromFormat('!Y-m-d', $start, new \DateTimeZone('UTC'));
        return [$start, $first->modify('+6 days')->format('Y-m-d')];
    }

    /**
     * The day a month opens on: today when the month holds it, else its 1st.
     *
     * @param string $month The month, YYYY-MM.
     * @return string YYYY-MM-DD.
     */
    public static function opening_day(string $month): string {
        $today = self::today()->format('Y-m-d');
        return str_starts_with($today, $month) ? $today : $month . '-01';
    }

    /**
     * The week the request names, as its first day; anything else is the week holding the month's opening day.
     *
     * A real day names the week holding it, written back as that week's first day on the viewer's first weekday: a link
     * shared by a reader whose week begins on another day still opens the week it meant. A malformed or impossible
     * value, or one outside the years a month may name ({@see month_from_request()}), is dropped, never refused.
     *
     * @param string $month The month the request names, YYYY-MM ({@see month_from_request()}).
     * @param int $firstday The weekday a week begins on, 0 for Sunday.
     * @return string The week's first day, YYYY-MM-DD.
     */
    public static function week_from_request(string $month, int $firstday): string {
        $week = self::scalar_param(self::PARAM_WEEK);
        // The month's own bounds: a week of year 1 would start in year 0, which the range's window cannot spell.
        if (!self::is_day($week) || (int) $week < 1900 || (int) $week > 2999) {
            $week = self::opening_day($month);
        }
        return self::week_start($week, $firstday);
    }

    /**
     * The day the day view shows: a real day of the years a month may name, else the month's opening day.
     *
     * Dropped, never refused, as {@see week_from_request()} drops a week: a malformed, impossible or out-of-years
     * value (a list included, {@see scalar_param()}) shows the day the month opens on.
     *
     * @param string $month The month the request names, YYYY-MM ({@see month_from_request()}).
     * @return string YYYY-MM-DD.
     */
    public static function view_day_from_request(string $month): string {
        $day = self::scalar_param(self::PARAM_DAY);
        if (!self::is_day($day) || (int) $day < 1900 || (int) $day > 2999) {
            return self::opening_day($month);
        }
        return $day;
    }

    /**
     * What the request shows: the view, the month, the week, the open day and the window the range is asked for.
     *
     * In the week view the month is the open day's, else the week's first day's: the month a switch back to the month
     * view opens. The day view (a phone's) shows its day as the open day over the day's month grid: the server cannot
     * know the width, and a desktop shows that month with the day's drawer open.
     *
     * @param int $firstday The weekday a week begins on, 0 for Sunday.
     * @return array [view, month, week ('' in the month view), open day ('' for none), from, to].
     */
    public static function span(int $firstday): array {
        $month = self::month_from_request();
        if (self::view_from_request() === self::VIEW_TRAIL) {
            // The month's grid: the answer the page embeds for a switch back to the month, every key off.
            [$from, $to] = self::grid($month, $firstday);
            return [self::VIEW_TRAIL, $month, '', '', $from, $to];
        }
        if (self::view_from_request() === self::VIEW_DAY) {
            $day = self::view_day_from_request($month);
            [$from, $to] = self::grid(substr($day, 0, 7), $firstday);
            return [self::VIEW_DAY, substr($day, 0, 7), '', $day, $from, $to];
        }
        if (self::view_from_request() !== self::VIEW_WEEK) {
            $day = self::day_from_request($month, $firstday);
            [$from, $to] = self::grid($month, $firstday);
            return [self::VIEW_MONTH, $month, '', $day, $from, $to];
        }
        $week = self::week_from_request($month, $firstday);
        $day = self::day_from_request($month, $firstday, $week);
        [$from, $to] = self::week($week);
        return [self::VIEW_WEEK, substr($day !== '' ? $day : $week, 0, 7), $week, $day, $from, $to];
    }

    /**
     * The page's own address parameters for what it shows, the state's or the visitor's keys' aside.
     *
     * @param string $view {@see VIEW_MONTH}, {@see VIEW_WEEK}, {@see VIEW_DAY} or {@see VIEW_TRAIL}.
     * @param string $month The month, YYYY-MM.
     * @param string $week The week's first day, YYYY-MM-DD, in the week view.
     * @param string $day The open day, or ''; in the day view its day.
     * @return array name => value; in the Trilhas view its trail, "Só as minhas" and the zoom too, when set.
     */
    public static function span_params(string $view, string $month, string $week, string $day): array {
        if ($view === self::VIEW_TRAIL) {
            return self::trail_params($month, self::trail_from_request(), self::mine_from_request(), self::zoom_from_request());
        }
        if ($view === self::VIEW_DAY) {
            return [self::PARAM_VIEW => self::VIEW_DAY, self::PARAM_DAY => $day];
        }
        $params = $view === self::VIEW_WEEK
            ? [self::PARAM_VIEW => self::VIEW_WEEK, self::PARAM_WEEK => $week]
            : [state::PARAM_MONTH => $month];
        return $day !== '' ? $params + [self::PARAM_DAY => $day] : $params;
    }

    /**
     * The address parameters of the Trilhas view: the view, the month, and the trail, "Só as minhas" and the zoom when
     * set.
     *
     * The one spelling of a Trilhas address, the landing page's "Ver no calendário" included.
     *
     * @param string $month The month, YYYY-MM.
     * @param int $trail The one trail, 0 for every trail.
     * @param bool $mine Whether only the viewer's own trails are kept.
     * @param string $zoom The timeline's zoom, {@see ZOOM_WEEK} or {@see ZOOM_MONTH}; anything else is days, unwritten.
     * @return array name => value.
     */
    public static function trail_params(string $month, int $trail = 0, bool $mine = false, string $zoom = ''): array {
        $params = [self::PARAM_VIEW => self::VIEW_TRAIL, state::PARAM_MONTH => $month];
        if ($trail > 0) {
            $params[self::PARAM_TRAIL] = $trail;
        }
        if ($mine) {
            $params[self::PARAM_MINE] = 1;
        }
        if (in_array($zoom, [self::ZOOM_WEEK, self::ZOOM_MONTH], true)) {
            $params[self::PARAM_ZOOM] = $zoom;
        }
        return $params;
    }

    /**
     * The state parameters of the current request a redirect to the route must carry, the month, the open day (the day
     * view's day), the view and the week.
     *
     * The day and the week are forwarded when each is a real day, without judging the day against the window or
     * snapping the week to its first day: both need the viewer's first weekday, and the page behind the route judges
     * them ({@see span()}), as it judges the category forwarded here as written.
     *
     * @return array name => value.
     */
    public static function forwarded_params(): array {
        $params = [];
        $month = self::scalar_param(state::PARAM_MONTH);
        if ($month !== '') {
            $params[state::PARAM_MONTH] = self::month_from_request();
        }
        $day = self::scalar_param(self::PARAM_DAY);
        if (self::is_day($day)) {
            $params[self::PARAM_DAY] = $day;
        }
        if (self::view_from_request() === self::VIEW_WEEK) {
            $params[self::PARAM_VIEW] = self::VIEW_WEEK;
        }
        if (self::view_from_request() === self::VIEW_DAY) {
            $params[self::PARAM_VIEW] = self::VIEW_DAY;
        }
        // Forwarded as written: the page behind the route decides whether the reader is offered the view.
        if (self::scalar_param(self::PARAM_VIEW) === self::VIEW_TRAIL) {
            $params[self::PARAM_VIEW] = self::VIEW_TRAIL;
            if (self::trail_from_request() > 0) {
                $params[self::PARAM_TRAIL] = self::trail_from_request();
            }
            if (self::mine_from_request()) {
                $params[self::PARAM_MINE] = 1;
            }
            if (self::zoom_from_request() !== '') {
                $params[self::PARAM_ZOOM] = self::zoom_from_request();
            }
        }
        $week = self::scalar_param(self::PARAM_WEEK);
        if (self::is_day($week)) {
            $params[self::PARAM_WEEK] = $week;
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
     * The visitor's one refusal: the login page, with this page's address as the place to come back to.
     *
     * @return self
     */
    private static function to_login(): self {
        global $SESSION;

        $SESSION->wantsurl = self::url(self::forwarded_params())->out(false);
        return new self(new \core\url(get_login_url()), null);
    }

    /**
     * The public calendar for a visitor who is not logged in: the same page in its visitor mode.
     *
     * No require_login(): forcelogin is a set of explicit reads in core, none of which is made here (the public
     * category page's rule). A calendar type other than the Gregorian is the one refusal, the login page, as the range
     * refuses it. The keys come from the address alone ({@see visitor::on_from_request()}): nothing is saved and
     * nothing of a session is read. The answer is the visitor's ({@see publicrange::answer()}), and the head tags are
     * armed ({@see opengraph::for_calendar()}): the canonical address, and noindex unless the site is open to search
     * engines.
     *
     * @return self The page, or the login page.
     */
    private static function visitor(): self {
        global $PAGE;

        try {
            window::require_gregorian();
        } catch (refusal $refusal) {
            return self::to_login();
        }

        $on = visitor::on_from_request();
        $firstday = (int) calendar_get_starting_weekday();
        [$view, $month, $week, $day, $from, $to] = self::span($firstday);
        $window = window::from_iso($from, $to);
        $answer = publicrange::answer($window, $on);

        $PAGE->set_context(\core\context\system::instance());
        $PAGE->set_pagetype(self::PAGETYPE);
        $PAGE->set_pagelayout('standard');
        $PAGE->set_url(self::url(self::span_params($view, $month, $week, $day) + visitor::query($on)));
        $PAGE->set_title(get_string('calendar_title', 'theme_dimensions'));
        $PAGE->set_heading('');
        $PAGE->add_body_class(self::BODY_CLASS);
        blockless::apply($PAGE);
        $PAGE->requires->strings_for_js(calendar_page::STRINGS, 'theme_dimensions');
        opengraph::set(opengraph::for_calendar(self::url()));

        return new self(null, new calendar_page(
            $month,
            self::today()->format('Y-m-d'),
            $firstday,
            \core_date::get_user_timezone(),
            $window,
            $answer,
            visitor::state($on),
            [],
            $day,
            true,
            $view,
            $week
        ));
    }

    /**
     * Decide the page.
     *
     * @return self The answer: a redirect or a page.
     */
    public static function handle(): self {
        global $CFG, $PAGE;

        require_once($CFG->dirroot . '/calendar/lib.php');
        if (!isloggedin()) {
            if (visitor::enabled()) {
                return self::visitor();
            }
            return self::to_login();
        }
        if (isguestuser()) {
            return new self(self::core_calendar(), null);
        }
        require_login(null, false);
        try {
            window::require_gregorian();
        } catch (refusal $refusal) {
            return new self(self::core_calendar(), null);
        }

        [$scope, $state] = self::scope_for(state::for_request());
        $root = $state->categoryid ? scope::for_viewer() : $scope;

        $firstday = (int) calendar_get_starting_weekday();
        [$view, $month, $week, $day, $from, $to] = self::span($firstday);
        $window = window::from_iso($from, $to);
        $trailsanswer = null;
        if ($view === self::VIEW_TRAIL) {
            // The Trilhas view draws no event: the month's answer is its days at zero, for a switch back to the month. Its
            // own answer is the trails of the month and the next, the two months its timeline opens on.
            $answer = range::answer($window, $scope, [], $state->filters());
            $trailsanswer = trails::answer(
                trails::month_window($month, trails::MONTHS),
                $scope,
                self::trail_from_request(),
                self::mine_from_request(),
                time()
            );
        } else {
            // Every key off asks no source: the answer is the window's days, each at zero (the service reads [] as all).
            $answer = range::answer($window, $scope, $state->on(), $state->filters());
        }

        $context = \core\context\system::instance();
        $PAGE->set_context($context);
        $PAGE->set_pagetype(self::PAGETYPE);
        $PAGE->set_pagelayout('standard');
        $PAGE->set_url(self::url(self::span_params($view, $month, $week, $day) + $state->query()));
        $PAGE->set_title(get_string('calendar_title', 'theme_dimensions'));
        $PAGE->set_heading('');
        $PAGE->add_body_class(self::BODY_CLASS);
        blockless::apply($PAGE);
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
            $day,
            false,
            $view,
            $week,
            $trailsanswer,
            self::trail_from_request(),
            self::mine_from_request(),
            self::zoom_from_request()
        ));
    }
}
