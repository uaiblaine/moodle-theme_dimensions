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
 * Theme Dimensions - What core's calendar lets the viewer create, edit, delete, move and export.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use core_calendar\external\events_related_objects_cache;
use core_calendar\local\event\container;
use core_calendar\local\event\entities\event_interface;

/**
 * Core's decisions about editing the calendar, passed through to the page and never made here.
 *
 * Each answer is the one core's month view on calendar/view.php gives the same viewer, read from core's own functions:
 *
 * - "New event" is core's add_event_button, drawn while month_exporter::get_default_add_context() answers, that is
 *   calendar_user_can_add_event() for the calendar's course, the site's on that page ({@see create()});
 * - an event's Edit, Delete and drag are its exporter's canedit, candelete and draggable
 *   ({@see \core_calendar\external\event_exporter_base::get_other_values()} and
 *   {@see \core_calendar\external\calendar_event_exporter::get_other_values()}), with the drop limits core's
 *   timestart range callbacks set ({@see draglimits});
 * - the iCal export is offered where core's calendar footer leads to it ({@see export_url()}).
 *
 * The server does the work again whatever the page drew: core's form, its delete service and
 * core_calendar_update_event_start_day each check calendar_edit_event_allowed() themselves.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editing {
    /** @var string[] The keys of an entry, in order: the allowlist the range service returns. */
    public const ENTRY_KEYS = ['id', 'edit', 'delete', 'drag', 'count', 'minday', 'maxday', 'minerror', 'maxerror'];

    /**
     * Whether core's calendar would offer this viewer "New event": calendar_user_can_add_event() for the site.
     *
     * False for a visitor and the guest, as core's own function answers.
     *
     * @return bool
     */
    public static function create(): bool {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        return calendar_user_can_add_event(get_site());
    }

    /**
     * The context core's site calendar hands its event form: the site course's, for a new event and for an edit.
     *
     * Core's fragment validates the form in it, which every account passes; the form's own checks decide the rest.
     *
     * @return int The context id.
     */
    public static function form_context(): int {
        return (int) \core\context\course::instance(SITEID)->id;
    }

    /**
     * Core's export page, where core's calendar offers it to this viewer; '' where it does not.
     *
     * calendar/export.php itself refuses nothing but the switch ($CFG->enablecalendarexport). Core's calendar reaches
     * it through the footer's "Manage subscriptions" link, drawn only with that switch on and for a viewer who may add
     * an event ({@see \core_calendar\external\footer_options_exporter}); the page follows the same rule.
     *
     * @return string The address, or ''.
     */
    public static function export_url(): string {
        global $CFG;
        if (empty($CFG->enablecalendarexport) || !self::create()) {
            return '';
        }
        return (new \core\url('/calendar/export.php'))->out(false);
    }

    /**
     * One entry per event core lets this viewer edit, with what core's month view draws for it.
     *
     * An event core's exporter answers canedit false for is not listed: it has no Edit, no Delete and no drag there.
     * The module instances the drop limits read are loaded the way core's month view loads them, one statement per
     * module name, and only for the events listed; the series' counts are one statement for every repeated event.
     *
     * @param event_interface[] $events Core's events, as the range's core source read them.
     * @return array[] Entries with exactly the keys of ENTRY_KEYS: id, edit (the summary's Edit: canedit and no course
     *     module), delete (candelete), drag (draggable), count (the series' events, 1 for a single one), minday and maxday
     *     (the drop limits as YYYY-MM-DD in the viewer's timezone, '' for none) and their error messages.
     */
    public static function entries(array $events): array {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $editable = self::editable(array_values($events));
        if (!$editable) {
            return [];
        }

        $cache = new events_related_objects_cache(array_column($editable, 0));
        $counts = self::series_counts(array_column($editable, 0));
        $timezone = \core_date::get_user_timezone_object();
        $day = static fn(int $timestamp): string => (new \DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)
            ->format('Y-m-d');

        $entries = [];
        foreach ($editable as [$event, $legacy]) {
            $limits = (new draglimits($event, $cache->get_module_instance($event)))->limits();
            $repeats = $event->get_repeats();
            $entries[] = [
                'id' => (int) $event->get_id(),
                'edit' => !$event->get_course_module(),
                'delete' => calendar_delete_event_allowed($legacy),
                'drag' => ($limits['draggable'] ?? true) !== false,
                'count' => $repeats ? max(1, $counts[(int) $repeats->get_id()] ?? 0) : 1,
                'minday' => isset($limits['mindaytimestamp']) ? $day((int) $limits['mindaytimestamp']) : '',
                'maxday' => isset($limits['maxdaytimestamp']) ? $day((int) $limits['maxdaytimestamp']) : '',
                'minerror' => self::plain((string) ($limits['mindayerror'] ?? '')),
                'maxerror' => self::plain((string) ($limits['maxdayerror'] ?? '')),
            ];
        }
        return $entries;
    }

    /**
     * A limit's message as plain text, as the service's PARAM_TEXT return carries it: core's exporter returns the same
     * type, and a callback's string may hold markup (an activity's name), which PARAM_TEXT would reject with the whole
     * response.
     *
     * @param string $message The message a callback gave.
     * @return string The message without markup, unchanged by PARAM_TEXT.
     */
    public static function plain(string $message): string {
        return clean_param($message, PARAM_TEXT);
    }

    /**
     * The events core's calendar_edit_event_allowed() answers true for as a manual edit, with their legacy form.
     *
     * For an activity's event core reads the course's modinfo by the course id, which on a cold static cache reads the
     * course row; the static cache keeps ten courses, and the events come in date order across every course. So the
     * activity events are asked course by course, each course's modinfo built first from a row read with the others
     * in one statement: a cache prime, as core_events::prime() does, which decides nothing.
     *
     * @param event_interface[] $events The events, as a list.
     * @return array[] [event, legacy event] for each editable one, in the events' order.
     */
    private static function editable(array $events): array {
        global $DB;
        $buckets = [];
        foreach ($events as $index => $event) {
            $courseid = $event->get_course_module() ? (int) $event->get_course()->get('id') : 0;
            $buckets[$courseid][] = $index;
        }
        $courseids = array_filter(array_keys($buckets));
        $rows = $courseids ? $DB->get_records_list('course', 'id', $courseids) : [];
        $mapper = container::get_event_mapper();
        $editable = [];
        foreach ($buckets as $courseid => $indexes) {
            if (isset($rows[$courseid])) {
                get_fast_modinfo($rows[$courseid]);
            }
            foreach ($indexes as $index) {
                $legacy = $mapper->from_event_to_legacy_event($events[$index]);
                if (calendar_edit_event_allowed($legacy, true)) {
                    $editable[$index] = [$events[$index], $legacy];
                }
            }
        }
        ksort($editable);
        return array_values($editable);
    }

    /**
     * The events of each series these events belong to, in one statement.
     *
     * Core's exporter counts a series per event ({@see \core_calendar\local\event\entities\repeat_event_collection::get_num()}:
     * the rows naming the parent, the parent's own row included); this is that count for every series at once.
     *
     * @param event_interface[] $events The events.
     * @return int[] Parent event id => the series' events.
     */
    private static function series_counts(array $events): array {
        global $DB;
        $parents = [];
        foreach ($events as $event) {
            if ($repeats = $event->get_repeats()) {
                $parents[(int) $repeats->get_id()] = true;
            }
        }
        if (!$parents) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($parents), SQL_PARAMS_NAMED, 'rp');
        $counts = [];
        $sql = "SELECT repeatid, COUNT(1) AS events FROM {event} WHERE repeatid {$insql} GROUP BY repeatid";
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $counts[(int) $row->repeatid] = (int) $row->events;
        }
        return $counts;
    }
}
