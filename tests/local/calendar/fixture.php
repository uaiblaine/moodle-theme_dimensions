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
 * Theme Dimensions - Shared fixture helpers of the calendar range tests
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use core_course\customfield\course_handler;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../category/fixture.php');

/**
 * What every calendar test needs: next month's days in the viewer's timezone, the caches a new request starts without.
 *
 * Not a test case (no _test suffix, so PHPUnit never collects it); the test classes require_once this file
 * and use the trait. It brings the showcase's fixture along (local_unlistedcourses' memos, the meter).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait fixture {
    use \theme_dimensions\local\category\fixture;

    /**
     * Leave no memo behind, core's calendar and modinfo statics included.
     *
     * @return void
     */
    protected function tearDown(): void {
        self::forget_calendar();
        parent::tearDown();
    }

    /**
     * Forget every per-request memo a calendar read leaves: the showcase's, the event container's, modinfo's.
     *
     * @return void
     */
    protected static function forget_calendar(): void {
        self::forget();
        \core_calendar\local\event\container::reset_caches();
        \course_modinfo::clear_instance_cache();
    }

    /**
     * Log in as somebody, as a new request would start.
     *
     * @param \stdClass $user The user.
     * @return void
     */
    protected function as_viewer(\stdClass $user): void {
        $this->setUser($user);
        self::forget_calendar();
    }

    /**
     * What the layout's block drawer would be drawn from on a page: the markup of its block region and the add-block control.
     *
     * Both are what Boost Union's drawers.php reads to decide on the drawer and its toggle (a block in the region, or a
     * non-empty add-block control in edit mode).
     *
     * @param \moodle_page $page A page whose layout, context and page type are set.
     * @return string[] [the region's block markup, the add-block button].
     */
    protected static function block_drawer_inputs(\moodle_page $page): array {
        // The header does this on a real request, before the layout runs.
        $page->blocks->load_blocks();
        $output = $page->get_renderer('core');
        return [$output->blocks('side-pre'), $output->addblockbutton()];
    }

    /**
     * A page as the calendar page is, but with core's own block handling: the control of {@see block_drawer_inputs()}.
     *
     * @return \moodle_page
     */
    protected static function page_with_blocks(): \moodle_page {
        $page = new \moodle_page();
        $page->set_context(\core\context\system::instance());
        $page->set_pagetype(page::PAGETYPE);
        $page->set_pagelayout('standard');
        $page->set_url('/theme/dimensions/calendar.php');
        return $page;
    }

    /**
     * Midnight of the first day of next month, in the current user's timezone: a month wholly in the future.
     *
     * @return \DateTimeImmutable
     */
    protected static function month_start(): \DateTimeImmutable {
        return new \DateTimeImmutable('first day of next month midnight', \core_date::get_user_timezone_object());
    }

    /**
     * An instant of next month, in the current user's timezone.
     *
     * @param int $day The day of the month.
     * @param int $hour The hour.
     * @return int
     */
    protected static function at(int $day, int $hour = 9): int {
        return self::month_start()->modify('+' . ($day - 1) . ' days')->setTime($hour, 0)->getTimestamp();
    }

    /**
     * Next month's whole window.
     *
     * @return window
     */
    protected static function month_window(): window {
        $start = self::month_start();
        return window::from_iso($start->format('Y-m-d'), $start->modify('last day of this month')->format('Y-m-d'));
    }

    /**
     * Give a course a landing page model, and optionally its modality and city, as the course form saves them.
     *
     * @param int $courseid The course.
     * @param int $model The model.
     * @param array $more Shortname => value of further landing fields.
     * @return void
     */
    protected static function set_model(int $courseid, int $model, array $more = []): void {
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $data = ['id' => $courseid, 'customfield_' . landingfields::SHORTNAME_MODEL => $model];
        foreach ($more as $shortname => $value) {
            $data['customfield_' . $shortname] = $value;
        }
        course_handler::create()->instance_form_save((object) $data);
    }

    /**
     * A course of the event model, from one day of next month to another.
     *
     * @param int $categoryid Its category.
     * @param int $startday The first day of next month it runs on.
     * @param int $endday The last day, 0 for no end date.
     * @param array $record Further course fields.
     * @return \stdClass The course.
     */
    protected static function event_course(int $categoryid, int $startday, int $endday, array $record = []): \stdClass {
        $course = self::getDataGenerator()->create_course($record + [
            'category' => $categoryid,
            'startdate' => self::at($startday, 0),
            'enddate' => $endday ? self::at($endday, 23) : 0,
        ]);
        self::set_model((int) $course->id, landingfields::MODEL_EVENT);
        return $course;
    }

    /**
     * The ids of the items of one source, sorted.
     *
     * @param array[] $items Range items.
     * @param string $source The source.
     * @return int[]
     */
    protected static function item_ids(array $items, string $source = 'core'): array {
        $ids = [];
        foreach ($items as $item) {
            if ($item['source'] === $source) {
                $ids[] = (int) $item['id'];
            }
        }
        sort($ids);
        return array_values(array_unique($ids));
    }

    /**
     * The ids core's own month view shows the current user for next month.
     *
     * calendar_get_view() over calendar_information::create(), read from the exported weeks, days and events as
     * core's month template reads them.
     *
     * @param int $categoryid The category core's view is narrowed to, 0 for the site.
     * @return int[] Sorted, unique.
     */
    protected static function core_month_ids(int $categoryid = 0): array {
        global $CFG;

        require_once($CFG->dirroot . '/calendar/lib.php');
        $calendar = \calendar_information::create(self::at(2), SITEID, $categoryid ?: null);
        [$data] = calendar_get_view($calendar, 'month');
        $ids = [];
        foreach ($data->weeks as $week) {
            foreach ($week->days as $day) {
                foreach ($day->events as $event) {
                    $ids[] = (int) $event->id;
                }
            }
        }
        sort($ids);
        return array_values(array_unique($ids));
    }
}
