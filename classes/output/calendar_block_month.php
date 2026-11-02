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
 * Theme Dimensions - The month calendar block, drawn with the theme's calendar
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use theme_dimensions\local\calendar\blocks;
use theme_dimensions\local\calendar\page;
use theme_dimensions\local\calendar\state;

/**
 * A mini month from the range's answer: the days with a dot per key, today, a link per day to the page's drawer.
 *
 * Drawn whole by the server (theme_dimensions/calendar_block_month around the partial
 * theme_dimensions/calendar_block_month_body), so it works without JavaScript: the arrows and the caption are links
 * to the page's months, a day is a link to the page with that day open. With JavaScript, js/esm/src/calendar/block.ts
 * moves the month through the range service and paints the same partial from a context it builds the same way
 * ({@see body()}). Every link is bare (the month and the day alone): the page reads the saved state for a bare address,
 * which is the state this block drew.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar_block_month implements renderable, templatable {
    /** @var string The template. */
    public const TEMPLATE = 'theme_dimensions/calendar_block_month';

    /** @var string The month, YYYY-MM. */
    private string $month;

    /** @var string Today in the viewer's timezone, YYYY-MM-DD. */
    private string $today;

    /** @var int The weekday a week begins on, 0 for Sunday. */
    private int $firstday;

    /** @var state What the block shows. */
    private state $state;

    /** @var array The range's answer for the month's grid. */
    private array $answer;

    /**
     * Hold what the block draws; {@see blocks::month()} computes every part.
     *
     * @param string $month The month, YYYY-MM.
     * @param string $today Today in the viewer's timezone, YYYY-MM-DD.
     * @param int $firstday The weekday a week begins on, 0 for Sunday.
     * @param state $state What the block shows.
     * @param array $answer The range's answer for the month's grid ({@see page::grid()}).
     */
    public function __construct(string $month, string $today, int $firstday, state $state, array $answer) {
        $this->month = $month;
        $this->today = $today;
        $this->firstday = $firstday;
        $this->state = $state;
        $this->answer = $answer;
    }

    /**
     * The page's address of a month, and of a day of it.
     *
     * @param string $month YYYY-MM.
     * @param string $day YYYY-MM-DD, or '' for the month alone.
     * @return string
     */
    public static function page_url(string $month, string $day = ''): string {
        $params = [state::PARAM_MONTH => $month];
        if ($day !== '') {
            $params[page::PARAM_DAY] = $day;
        }
        return page::url($params)->out(false);
    }

    /**
     * The weekday names, starting on the week's first day: the table's head.
     *
     * @return array[] ['short', 'long'] each.
     */
    private function weekdays(): array {
        $names = \core_calendar\type_factory::get_calendar_instance()->get_weekdays();
        $weekdays = [];
        for ($i = 0; $i < 7; $i++) {
            $name = $names[($this->firstday + $i) % 7];
            $weekdays[] = ['short' => $name['shortname'], 'long' => $name['fullname']];
        }
        return $weekdays;
    }

    /**
     * The partial's context: the caption, the arrows, the weeks and their days.
     *
     * @return array
     */
    public function body(): array {
        $timezone = \core_date::get_user_timezone_object();
        $counts = [];
        foreach ($this->answer['days'] as $day) {
            $counts[$day['date']] = $day['counts'];
        }
        [$from, $to] = page::grid($this->month, $this->firstday);
        $utc = new \DateTimeZone('UTC');
        $cursor = \DateTimeImmutable::createFromFormat('!Y-m-d', $from, $utc);
        $weeks = [];
        $days = [];
        while ($cursor->format('Y-m-d') <= $to) {
            $date = $cursor->format('Y-m-d');
            $count = array_sum($counts[$date] ?? []);
            $dots = blocks::dots($counts[$date] ?? []);
            $midnight = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone)->getTimestamp();
            $label = get_string('calendar_daylabel', 'theme_dimensions', (object) [
                'date' => userdate($midnight, get_string('strftimedaydate', 'langconfig')),
                'count' => match (true) {
                    $count === 0 => get_string('calendar_dayitems_none', 'theme_dimensions'),
                    $count === 1 => get_string('calendar_dayitems_one', 'theme_dimensions'),
                    default => get_string('calendar_dayitems_many', 'theme_dimensions', $count),
                },
            ]);
            $istoday = $date === $this->today;
            $days[] = [
                'date' => $date,
                'number' => (int) $cursor->format('j'),
                'url' => self::page_url(substr($date, 0, 7), $date),
                'label' => $istoday ? $label . ', ' . get_string('calendar_daytoday', 'theme_dimensions') : $label,
                'outside' => !str_starts_with($date, $this->month),
                'today' => $istoday,
                'dots' => array_map(static fn(string $key): array => ['key' => $key], $dots['keys']),
                'more' => $dots['more'],
            ];
            if (count($days) === 7) {
                $weeks[] = ['days' => $days];
                $days = [];
            }
            $cursor = $cursor->modify('+1 day');
        }
        $first = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->month . '-01', $timezone)->getTimestamp();
        $prev = calendar_page::month_at($this->month, -1);
        $next = calendar_page::month_at($this->month, 1);
        return [
            'caption' => userdate($first, get_string('strftimemonthyear', 'langconfig')),
            'monthurl' => self::page_url($this->month),
            'prevmonth' => $prev,
            'prevurl' => self::page_url($prev),
            'nextmonth' => $next,
            'nexturl' => self::page_url($next),
            'weekdays' => $this->weekdays(),
            'weeks' => $weeks,
        ];
    }

    /**
     * The template context: the partial's, and what the client reads off the root.
     *
     * @param renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return $this->body() + [
            'month' => $this->month,
            'today' => $this->today,
            'firstday' => $this->firstday,
            'pageurl' => page::url()->out(false),
            'state' => json_encode($this->state->to_array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }
}
