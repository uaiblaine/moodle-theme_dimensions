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

/**
 * Which of core's calendar block calls the theme answers, and what it answers them with.
 *
 * Both blocks render through the core_calendar renderer after calendar_get_view()
 * ({@see \theme_dimensions\output\core_calendar_renderer}): block_calendar_month the template {@see TEMPLATE_MONTH} with
 * `iscalendarblock` set (core's own month page renders the same template without it), block_calendar_upcoming
 * {@see TEMPLATE_UPCOMING}. The decision is pure, every input an argument, as {@see coreview::target()} is; each
 * refusal is a reason core's output must stay (docs/calendar/PROPOSAL.md section 17.1):
 *
 * 1. the call is not one of the two blocks ({@see kind()});
 * 2. the setting {@see SETTING} is off ({@see enabled()});
 * 3. the page's course is not the site: a course's block shows that course's events, and the range has no course scope;
 * 4. the viewer is a visitor or the guest ({@see coreview::account()});
 * 5. the calendar type is not the Gregorian, which the range refuses ({@see coreview::gregorian()}).
 *
 * What the theme answers is the range's alone ({@see range::answer()}), over the state the page reads for a bare
 * address ({@see state::saved()}, its category through {@see page::scope_for()}): nothing of core's data reaches the
 * theme's markup, so the blocks never show what the range drops.
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
     * @param int $now The current time.
     * @return array ['month', 'today', 'firstday', 'state', 'answer']
     */
    public static function month(int $now): array {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $firstday = (int) calendar_get_starting_weekday();
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone(\core_date::get_user_timezone_object())->format('Y-m-d');
        $month = substr($today, 0, 7);
        [$from, $to] = page::grid($month, $firstday);
        [$scope, $state] = self::scope();
        return [
            'month' => $month,
            'today' => $today,
            'firstday' => $firstday,
            'state' => $state,
            'answer' => range::answer(window::from_iso($from, $to), $scope, $state->on(), $state->filters()),
        ];
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
            $answer = range::answer(window::from_iso($day->format('Y-m-d'), $end), $scope, $on, $filters);
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
