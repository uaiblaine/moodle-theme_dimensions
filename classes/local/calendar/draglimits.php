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
 * Theme Dimensions - The drop limits core's month view gives an event it lets the viewer drag.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use core_calendar\external\calendar_event_exporter;
use core_calendar\local\event\entities\event_interface;

/**
 * Core's own limits for moving one event's start day, through core's own protected methods.
 *
 * {@see calendar_event_exporter::get_other_values()} asks the activity's (or for a course event the course's)
 * core_calendar_get_valid_event_timestart_range callback and turns its answer into `draggable` false or into the
 * earliest and latest days, in methods it keeps protected. A subclass reaches them without a copy, so a change of
 * core's rule is a change here. Core's constructor exports the whole event (the description, the formatted location),
 * which the limits never read: this one sets the two properties they do read, the event and the module instance.
 *
 * Whether the event is editable at all is not asked here: {@see editing::entries()} lists only the events core's
 * calendar_edit_event_allowed() answers true for, which is where core's draggable starts.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class draglimits extends calendar_event_exporter {
    /**
     * Hold the event and the instance of its activity, the two things core's limit methods read.
     *
     * @param event_interface $event The event.
     * @param \stdClass|null $moduleinstance Its activity's instance row, as events_related_objects_cache reads it; null
     *     for an event without an activity.
     */
    public function __construct(event_interface $event, ?\stdClass $moduleinstance) {
        $this->event = $event;
        $this->related = ['moduleinstance' => $moduleinstance];
    }

    /**
     * The limits core's month view gives the event: the branch of calendar_event_exporter::get_other_values() that
     * picks the callback, and nothing else of it.
     *
     * @return array [] for none; ['draggable' => false] when the callback answers that no day is valid; otherwise
     *     mindaytimestamp, mindayerror, maxdaytimestamp and maxdayerror where the callback sets them.
     */
    public function limits(): array {
        $event = $this->event;
        if ($event->get_course_module()) {
            return $this->get_module_timestamp_limits($event);
        }
        $course = $event->get_course();
        $courseid = $course ? (int) $course->get('id') : 0;
        if ($courseid && $courseid !== (int) SITEID && empty($event->get_group())) {
            return $this->get_course_timestamp_limits($event);
        }
        return [];
    }
}
