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
 * Theme Dimensions - Core's calendar blocks drawn with the theme's calendar
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use theme_dimensions\local\calendar\source\core_events;
use theme_dimensions\local\calendar\source\event_courses;
use theme_dimensions\local\category\classifier;

/**
 * Which of core's calendar block calls the theme answers, and what it answers them with.
 *
 * Both blocks render through the core_calendar renderer after calendar_get_view()
 * ({@see \theme_dimensions\output\core_calendar_renderer}): block_calendar_month the template {@see TEMPLATE_MONTH} with
 * `iscalendarblock` set (core's own month page renders the same template without it), block_calendar_upcoming
 * {@see TEMPLATE_UPCOMING}. The decision is pure, every input an argument, as {@see coreview::target()} is; each
 * refusal is a reason core's output must stay:
 *
 * 1. the call is not one of the two blocks ({@see kind()});
 * 2. the setting {@see SETTING} is off ({@see enabled()});
 * 3. the page's course is not the site: a course's block shows that course's events, and the range has no course scope;
 * 4. the viewer is a visitor or the guest ({@see coreview::account()});
 * 5. the calendar type is not the Gregorian, which the range refuses ({@see coreview::gregorian()}).
 *
 * What the theme answers is the range's ({@see range::answer()}), over the state the page reads for a bare address
 * ({@see state::saved()}, its category through {@see page::scope_for()}), so the blocks never show what the range
 * drops. One exception, a measured one: the month block counts the month's core events from the events core already
 * put in its context ({@see month_from_core()}), because block_calendar_month calls calendar_get_view() before the
 * renderer is reached and the range would fetch the same events again, core's module callbacks included (the
 * performance audit of 2026-11-20: the dashboard spent 54 % of its time in core's half and 27 % in the theme's). It
 * applies the range's own rules to them, and only where core and the range ask the same question.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class blocks {
    /** @var string The component the setting lives in. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string The setting; a checkbox that ships on and is off only when an explicit '0' is stored. */
    public const SETTING = 'calendarblocks';

    /** @var string The template block_calendar_month renders (calendar_get_view()'s for the view `monthblock`). */
    public const TEMPLATE_MONTH = 'core_calendar/calendar_month';

    /** @var string The template block_calendar_upcoming renders (calendar_get_view()'s for `upcoming_mini`). */
    public const TEMPLATE_UPCOMING = 'core_calendar/calendar_upcoming_mini';

    /** @var string The month block. */
    public const MONTH = 'month';

    /** @var string The upcoming block. */
    public const UPCOMING = 'upcoming';

    /** @var string The upcoming block's `data-block` attribute, its name without the `block_` prefix. */
    public const BLOCK_UPCOMING = 'calendar_upcoming';

    /**
     * @var int The days of the agenda's first piece; each next piece doubles, up to window::MAX_DAYS. A range answer
     * costs per item (core's module callbacks), so a busy week answers the count without reading the whole lookahead.
     */
    public const FIRST_PIECE = 7;

    /** @var int The most key dots a day draws before "+" (layout.ts DOTS). */
    public const DOTS = 4;

    /** @var string[] The strings the month block's client reads and its partial prints, handed to it with the page. */
    public const STRINGS = [
        'calendar_dayitems_many',
        'calendar_dayitems_none',
        'calendar_dayitems_one',
        'calendar_daylabel',
        'calendar_daytoday',
        'calendar_nextmonth',
        'calendar_prevmonth',
    ];

    /**
     * Whether the setting is on: unless an explicit '0' was stored, so no upgrade step writes it.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (string) get_config(self::COMPONENT, self::SETTING) !== '0';
    }

    /**
     * Which block a render call is, or null for any other call (core's own month page included).
     *
     * @param string $template The template name the call asks for.
     * @param mixed $context The call's context: core passes the exporter's stdClass.
     * @return string|null {@see MONTH}, {@see UPCOMING} or null.
     */
    public static function kind(string $template, $context): ?string {
        if ($template === self::TEMPLATE_UPCOMING) {
            return self::UPCOMING;
        }
        if ($template !== self::TEMPLATE_MONTH) {
            return null;
        }
        $flag = is_object($context) ? ($context->iscalendarblock ?? false) : ($context['iscalendarblock'] ?? false);
        return empty($flag) ? null : self::MONTH;
    }

    /**
     * Whether the theme answers a block call.
     *
     * @param bool $enabled Whether the setting is on ({@see enabled()}).
     * @param int $courseid The page's course.
     * @param int $account The viewer, 0 for a visitor or the guest ({@see coreview::account()}).
     * @param bool $gregorian Whether the viewer's calendar type is the Gregorian ({@see coreview::gregorian()}).
     * @return bool
     */
    public static function applies(bool $enabled, int $courseid, int $account, bool $gregorian): bool {
        if (!$enabled) {
            return false;
        }
        if ($courseid !== (int) SITEID) {
            return false;
        }
        if ($account <= 0) {
            return false;
        }
        return $gregorian;
    }

    /**
     * Whether a block's footer goes: core's "Go to calendar..." under the upcoming block whose content the theme
     * answers, which already ends with the theme's own "Full calendar".
     *
     * block_calendar_upcoming writes that link into its content's footer, outside the template the theme replaces, so
     * {@see \theme_dimensions\output\core_renderer::block()} empties it. Every other block, and the upcoming block on a
     * page core answers, keep theirs.
     *
     * @param string $block The block's `data-block` attribute.
     * @param bool $applies Whether the theme answers the page's calendar blocks ({@see applies()}).
     * @return bool
     */
    public static function drops_footer(string $block, bool $applies): bool {
        return $applies && $block === self::BLOCK_UPCOMING;
    }

    /**
     * The upcoming lookahead in days, read as calendar_get_view() reads it: the reader's preference over the site's
     * setting over core's default.
     *
     * @return int
     */
    public static function lookahead(): int {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $default = isset($CFG->calendar_lookahead) ? intval($CFG->calendar_lookahead) : CALENDAR_DEFAULT_UPCOMING_LOOKAHEAD;
        return (int) get_user_preferences('calendar_lookahead', $default);
    }

    /**
     * The most upcoming items, read as calendar_get_view() reads it: the reader's preference over the site's setting
     * over core's default. 0 or less is no limit, as the event vault reads a limit of 0.
     *
     * @return int
     */
    public static function maxevents(): int {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $default = isset($CFG->calendar_maxevents) ? intval($CFG->calendar_maxevents) : CALENDAR_DEFAULT_UPCOMING_MAXEVENTS;
        return (int) get_user_preferences('calendar_maxevents', $default);
    }

    /**
     * The upcoming window as calendar_get_view() computes it: from the start of the current hour in the viewer's
     * timezone to the lookahead's days later, less a second.
     *
     * @param int $now The current time.
     * @param \DateTimeZone $timezone The viewer's timezone.
     * @param int $lookahead The days.
     * @return int[] [from, to], timestamps; to below from when the lookahead is 0.
     */
    public static function upcoming_window(int $now, \DateTimeZone $timezone, int $lookahead): array {
        $hour = (new \DateTimeImmutable('@' . $now))->setTimezone($timezone);
        $hour = $hour->setTime((int) $hour->format('G'), 0);
        return [$hour->getTimestamp(), $hour->modify('+' . $lookahead . ' days')->getTimestamp() - 1];
    }

    /**
     * Whether an item is in the upcoming window as core's event vault keeps one with its duration: started inside
     * it, or still running at its start.
     *
     * @param array $item A range item.
     * @param int $from The window's first second.
     * @param int $to The window's last second.
     * @return bool
     */
    public static function upcoming_keeps(array $item, int $from, int $to): bool {
        if ($item['timestart'] > $to) {
            return false;
        }
        return $item['timestart'] >= $from || $item['timestart'] + $item['timeduration'] > $from;
    }

    /**
     * The state the blocks draw, and its scope: the page's for a bare address, on every page.
     *
     * A category page included. Core's month block means to narrow to the page's category, but reads
     * `!empty($this->page->category)`, and moodle_page has no __isset(), so that is always false and core's block never
     * narrows (block_calendar_month::get_content()); the upcoming block has no narrowing at all. The blocks keep
     * core's effective behaviour.
     *
     * @return array [scope, state]
     */
    private static function scope(): array {
        return page::scope_for(state::saved());
    }

    /**
     * The month block's data: the month of now in the viewer's timezone, its grid and the range's answer for it.
     *
     * With core's month context ({@see month_from_core()}) the answer holds the grid's days alone, which is all the
     * block draws ({@see \theme_dimensions\output\calendar_block_month::body()}).
     *
     * @param int $now The current time.
     * @param mixed $corecontext The context block_calendar_month handed the renderer, or null.
     * @return array ['month', 'today', 'firstday', 'state', 'answer']
     */
    public static function month(int $now, $corecontext = null): array {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $firstday = (int) calendar_get_starting_weekday();
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone(\core_date::get_user_timezone_object())->format('Y-m-d');
        $month = substr($today, 0, 7);
        [$from, $to] = page::grid($month, $firstday);
        [$scope, $state] = self::scope();
        $window = window::from_iso($from, $to);
        $answer = null;
        if ($corecontext !== null && self::reuses_core($state)) {
            $answer = self::month_from_core($corecontext, $month, $window, $scope, $state->on());
        }
        return [
            'month' => $month,
            'today' => $today,
            'firstday' => $firstday,
            'state' => $state,
            'answer' => $answer ?? range::answer($window, $scope, $state->on(), $state->filters(), false),
        ];
    }

    /**
     * Whether the block's state asks the question core's month block asked: no category, no course filter.
     *
     * Core's block lists the viewer's whole calendar (it never narrows, {@see scope()}); a state narrowed to a
     * category, or filtering by a course fact or by action, asks for less, which core's events cannot answer. Keys
     * switched off are fine: an event of such a key is dropped after it is classified.
     *
     * @param state $state The block's state.
     * @return bool
     */
    public static function reuses_core(state $state): bool {
        $plain = $state->to_array();
        return (int) $plain['categoryid'] === 0 && !$plain['model'] && !$plain['modality']
            && (string) $plain['city'] === '' && !$plain['actiononly'];
    }

    /**
     * The grid's day counts, the month's core events taken from core's month context instead of fetched again.
     *
     * calendar_get_view() fetched them with the arguments and visibility closure {@see core_events::events()} uses
     * (core_events_test compares the two), for the days of the month. So here: the context's events, once each by
     * id, classified by {@see keys::classify_exported()}, those of a key that is off dropped and those of a category
     * the range withholds dropped ({@see core_events::admitted_categories()}); the grid's days outside the month
     * fetched by the range's own source; the course and open keys from {@see event_courses}, as range::answer()
     * takes them; then counted by {@see range::days()}. Null when the context is not this month's days, one by one:
     * the caller then asks the range.
     *
     * @param mixed $corecontext The context block_calendar_month handed the renderer.
     * @param string $month The month, YYYY-MM.
     * @param window $window The grid.
     * @param scope $scope The viewer's calendar sets.
     * @param string[] $on The keys that are on.
     * @return array|null ['from', 'to', 'days' => range::days()'s answer], or null.
     */
    public static function month_from_core($corecontext, string $month, window $window, scope $scope, array $on): ?array {
        $data = json_decode(json_encode($corecontext), true);
        if (!is_array($data) || empty($data['weeks']) || !is_array($data['weeks'])) {
            return null;
        }
        $timezone = \core_date::get_user_timezone_object();
        $dates = [];
        $events = [];
        foreach ($data['weeks'] as $week) {
            foreach ($week['days'] ?? [] as $day) {
                $dates[] = (new \DateTimeImmutable('@' . (int) ($day['timestamp'] ?? 0)))->setTimezone($timezone)->format('Y-m-d');
                foreach ($day['events'] ?? [] as $event) {
                    $events[(int) $event['id']] = $event;
                }
            }
        }
        $first = $month . '-01';
        $last = \DateTimeImmutable::createFromFormat('!Y-m-d', $first, $timezone)->format('Y-m-t');
        $expected = [];
        for ($day = $first; $day <= $last; $day = (new \DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d')) {
            $expected[] = $day;
        }
        if ($dates !== $expected || $window->from > $first || $window->to < $last) {
            return null;
        }

        $items = [];
        $categoryids = [];
        foreach ($events as $id => $event) {
            $key = keys::classify_exported($event);
            if (!in_array($key, $on, true)) {
                continue;
            }
            $categoryid = (int) ($event['categoryid'] ?? 0);
            $items[core_events::SOURCE . ':' . $id] = [
                'key' => $key,
                'timestart' => (int) $event['timestart'],
                'timeduration' => (int) $event['timeduration'],
                'categoryid' => $categoryid,
            ];
            if ($categoryid) {
                $categoryids[$categoryid] = true;
            }
        }
        if ($categoryids) {
            $listed = core_events::admitted_categories(\core_course_category::get_many(array_keys($categoryids)));
            $items = array_filter(
                $items,
                static fn(array $item): bool => !$item['categoryid'] || isset($listed[$item['categoryid']])
            );
        }

        $edges = [];
        if ($window->from < $first) {
            $edges[] = window::from_iso($window->from, (new \DateTimeImmutable($first))->modify('-1 day')->format('Y-m-d'));
        }
        if ($window->to > $last) {
            $edges[] = window::from_iso((new \DateTimeImmutable($last))->modify('+1 day')->format('Y-m-d'), $window->to);
        }
        if (array_intersect(keys::CORE, $on)) {
            foreach ($edges as $edge) {
                foreach ((new core_events(false))->items($edge, $scope, $on) as $item) {
                    $items[core_events::SOURCE . ':' . $item['id']] = $item;
                }
            }
        }
        if (array_intersect([keys::COURSE, keys::OPEN], $on)) {
            $eventcourses = new event_courses(classifier::enrolments($scope->viewerid));
            // Appended as range::answer() appends them: one course may give a course item and an open one.
            foreach ($eventcourses->items($window, $scope, $on) as $item) {
                $items[] = $item;
            }
        }
        return ['from' => $window->from, 'to' => $window->to, 'days' => range::days($window, array_values($items))];
    }

    /**
     * The upcoming block's data: the first items of core's upcoming window, from the range alone.
     *
     * The window is walked in pieces ({@see FIRST_PIECE} days, then twice as many each time, never more than the
     * range's {@see window::MAX_DAYS}), an item seen twice kept once; the walk stops when a piece leaves the count
     * reached, since every later piece adds only items that start after it.
     *
     * @param int $now The current time.
     * @return array ['items' => the items, sorted and capped, 'courses' => course id => entry]
     */
    public static function upcoming(int $now): array {
        $timezone = \core_date::get_user_timezone_object();
        [$from, $to] = self::upcoming_window($now, $timezone, self::lookahead());
        $max = self::maxevents();
        [$scope, $state] = self::scope();
        $on = $state->on();
        $filters = $state->filters();

        $kept = [];
        $courses = [];
        $day = (new \DateTimeImmutable('@' . $from))->setTimezone($timezone)->setTime(0, 0);
        $last = (new \DateTimeImmutable('@' . max($from, $to)))->setTimezone($timezone)->format('Y-m-d');
        $length = self::FIRST_PIECE;
        while ($to >= $from && $day->format('Y-m-d') <= $last) {
            $end = $day->modify('+' . ($length - 1) . ' days');
            $length = min(2 * $length, window::MAX_DAYS);
            $end = $end->format('Y-m-d') > $last ? $last : $end->format('Y-m-d');
            $answer = range::answer(window::from_iso($day->format('Y-m-d'), $end), $scope, $on, $filters, false);
            foreach ($answer['courses'] as $course) {
                $courses[$course['id']] = $course;
            }
            foreach ($answer['events'] as $item) {
                $id = $item['source'] . ':' . $item['id'];
                if (!isset($kept[$id]) && self::upcoming_keeps($item, $from, $to)) {
                    $kept[$id] = $item;
                }
            }
            if ($max > 0 && count($kept) >= $max) {
                break;
            }
            $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $end, $timezone)->modify('+1 day');
        }
        $kept = array_values($kept);
        usort($kept, static fn(array $a, array $b): int => [$a['timestart'], $a['source'], $a['id']]
            <=> [$b['timestart'], $b['source'], $b['id']]);
        return [
            'items' => $max > 0 ? array_slice($kept, 0, $max) : $kept,
            'courses' => $courses,
        ];
    }

    /**
     * A day's dots: the keys with items, in the keys' order, at most {@see DOTS}, and whether more were left.
     *
     * @param array $counts key => count, from the answer's days.
     * @return array ['keys' => string[], 'more' => bool]
     */
    public static function dots(array $counts): array {
        $keys = [];
        foreach (keys::ALL as $key) {
            if (($counts[$key] ?? 0) > 0) {
                $keys[] = $key;
            }
        }
        return ['keys' => array_slice($keys, 0, self::DOTS), 'more' => count($keys) > self::DOTS];
    }

    /**
     * An address from the answer, kept only when it is a web address: what reaches an href (model.ts safeHref()).
     *
     * The server builds every address absolute, so anything but http(s) is refused, a `javascript:` one an activity
     * module put in an action event included.
     *
     * @param string $url The address the answer carries.
     * @param string $fallback What to use instead.
     * @return string
     */
    public static function safe_url(string $url, string $fallback): string {
        return preg_match('~^https?://~i', $url) === 1 ? $url : $fallback;
    }

    /**
     * Where an item leads, as the page's items lead: its own page, else its course's page, else the site's home
     * (calendar_page::item_url(), model.ts itemHref()), each checked by {@see safe_url()}.
     *
     * @param array $item A range item.
     * @param array $courses The answer's courses, keyed by id.
     * @return string
     */
    public static function item_url(array $item, array $courses): string {
        $own = self::safe_url($item['url'], '');
        if ($own !== '') {
            return $own;
        }
        $home = (new \core\url('/'))->out(false);
        return self::safe_url($courses[$item['courseid']]['url'] ?? '', $home);
    }
}
