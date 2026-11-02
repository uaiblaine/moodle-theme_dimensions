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
 * Theme Dimensions - The upcoming events block, drawn with the theme's calendar
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
use theme_dimensions\local\calendar\source\event_courses;

/**
 * A short agenda: the first items of core's upcoming window, from the range's answer, then "Full calendar".
 *
 * Drawn by the server alone (theme_dimensions/calendar_block_upcoming); nothing in it moves. Each item links where
 * the page's items lead ({@see blocks::item_url()}). Every text is in the plain spelling (the answer's own), escaped
 * once by the template's double stashes.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar_block_upcoming implements renderable, templatable {
    /** @var string The template. */
    public const TEMPLATE = 'theme_dimensions/calendar_block_upcoming';

    /** @var array[] The items, sorted and capped ({@see blocks::upcoming()}). */
    private array $items;

    /** @var array The answer's courses, keyed by id. */
    private array $courses;

    /** @var int The current time. */
    private int $now;

    /**
     * Hold what the block draws.
     *
     * @param array $items The items, sorted and capped.
     * @param array $courses The answer's courses, keyed by id.
     * @param int $now The current time: an item already running reads "until" its end.
     */
    public function __construct(array $items, array $courses, int $now) {
        $this->items = $items;
        $this->courses = $courses;
        $this->now = $now;
    }

    /**
     * When an item happens, as a line of text: its day and time, its span, or the end of one that already started.
     *
     * @param array $item A range item.
     * @return string
     */
    private function when(array $item): string {
        $timezone = \core_date::get_user_timezone_object();
        $start = $item['timestart'];
        $last = $start + max(0, $item['timeduration'] - 1);
        $day = static fn(int $time): string => (new \DateTimeImmutable('@' . $time))->setTimezone($timezone)->format('Y-m-d');
        $dateformat = get_string('strftimedateshort', 'langconfig');
        if ($day($start) !== $day($last) || $item['source'] === event_courses::SOURCE) {
            if ($start < $this->now) {
                return get_string('calendar_until', 'theme_dimensions', userdate($last, $dateformat));
            }
            return get_string('calendar_span', 'theme_dimensions', (object) [
                'from' => userdate($start, $dateformat),
                'to' => userdate($last, $dateformat),
            ]);
        }
        return get_string('calendarblock_when', 'theme_dimensions', (object) [
            'date' => userdate($start, get_string('strftimedayshort', 'langconfig')),
            'time' => userdate($start, get_string('strftimetime', 'langconfig')),
        ]);
    }

    /**
     * The template context.
     *
     * @param renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $items = [];
        foreach ($this->items as $item) {
            $course = $this->courses[$item['courseid']] ?? null;
            $items[] = [
                'name' => $item['name'],
                'url' => blocks::item_url($item, $this->courses),
                'when' => $this->when($item),
                'key' => $item['overdue'] ? 'overdue' : $item['key'],
                'keylabel' => calendar_page::key_label($item['key']),
                'course' => $course === null ? '' : $course['fullname'],
            ];
        }
        return [
            'items' => $items,
            'empty' => empty($items),
            'pageurl' => page::url()->out(false),
        ];
    }
}
