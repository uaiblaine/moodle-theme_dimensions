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
 * Theme Dimensions - One month grid of the calendar
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use theme_dimensions\local\calendar\filters;
use theme_dimensions\local\calendar\keys;
use theme_dimensions\local\calendar\range;
use theme_dimensions\local\calendar\refusal;
use theme_dimensions\local\calendar\scope;
use theme_dimensions\local\calendar\window;

/**
 * The calendar's events, event courses and day counts over a window of at most six weeks, in one answer.
 *
 * The order of the checks:
 *
 * 1. validate_parameters();
 * 2. the shape of the request, which says nothing about the site: the window parsed in UTC (a real
 *    YYYY-MM-DD pair, in order, at most window::MAX_DAYS days) and the keys against their vocabulary;
 * 3. require_login(), then the guest account refused with the same refusal as every other miss;
 * 4. validate_context() with the SYSTEM context. The answer spans the viewer's courses and categories, so
 *    there is no one course context to validate, and a course's would run require_login() against it, which
 *    throws for anybody not enrolled - the reader of the "open events" layer. Every text is formatted in
 *    its own context ({@see \theme_dimensions\local\calendar\source\core_events}), never in the page's;
 * 5. the viewer's calendar type (Gregorian only), the window in the viewer's timezone, the narrowing
 *    ({@see scope::for_viewer()}, which refuses a course or category the viewer may not narrow to), then
 *    {@see range::answer()}.
 *
 * The answer also carries core's own editing answers for the events it lists (`editing`,
 * {@see \theme_dimensions\local\calendar\editing::entries()}): what core's month view would draw Edit, Delete and drag for.
 * They decide what the page offers, never what is allowed: core's form and services check again.
 *
 * Core has no capability for viewing a calendar, and none is invented: what each viewer sees is what
 * core's month view shows them, plus the event courses the showcase would list to them. A read raises no
 * event. The session is read-only: nothing here writes $SESSION (scope builds core's sets without the
 * session-only key bitmask, see its docblock), which readonly_session_test holds.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_calendar_range extends external_api {
    /**
     * The parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'from' => new external_value(PARAM_RAW, 'The first day, YYYY-MM-DD, in the viewer\'s timezone.'),
            'to' => new external_value(PARAM_RAW, 'The last day, YYYY-MM-DD, inclusive; at most 42 days after from.'),
            'keys' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'A key: site, category, course, group, user, activity, other or open.'),
                'The keys that are on; empty for every key.',
                VALUE_DEFAULT,
                []
            ),
            'categoryid' => new external_value(PARAM_INT, 'Narrow to a category, 0 for none.', VALUE_DEFAULT, 0),
            'recursive' => new external_value(
                PARAM_BOOL,
                'With a category: its whole subtree (true) or its direct children only, as core.',
                VALUE_DEFAULT,
                true
            ),
            'courseid' => new external_value(
                PARAM_INT,
                'Narrow to one of the viewer\'s calendar courses, 0 for none; wins over the category.',
                VALUE_DEFAULT,
                0
            ),
            'model' => new external_multiple_structure(
                new external_value(PARAM_INT, 'A course model option.'),
                'Course-bound items of these models only; empty for every model.',
                VALUE_DEFAULT,
                []
            ),
            'modality' => new external_multiple_structure(
                new external_value(PARAM_INT, 'A modality option.'),
                'Course-bound items of these modalities only; empty for every modality.',
                VALUE_DEFAULT,
                []
            ),
            'city' => new external_value(
                PARAM_TEXT,
                'Course-bound items of this city only (exact, trimmed); empty for any.',
                VALUE_DEFAULT,
                ''
            ),
            'actiononly' => new external_value(PARAM_BOOL, 'Only actionable action events.', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * One window of the calendar.
     *
     * @param string $from The first day.
     * @param string $to The last day.
     * @param array $keys The keys that are on.
     * @param int $categoryid The category narrowing.
     * @param bool $recursive Whether the category's whole subtree counts.
     * @param int $courseid The course narrowing.
     * @param array $model The models to keep.
     * @param array $modality The modalities to keep.
     * @param string $city The city to keep.
     * @param bool $actiononly Whether only things to do stay.
     * @return array The answer of range::answer().
     * @throws refusal On every miss, the same for each.
     */
    public static function execute(
        string $from,
        string $to,
        array $keys = [],
        int $categoryid = 0,
        bool $recursive = true,
        int $courseid = 0,
        array $model = [],
        array $modality = [],
        string $city = '',
        bool $actiononly = false
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'from' => $from,
            'to' => $to,
            'keys' => $keys,
            'categoryid' => $categoryid,
            'recursive' => $recursive,
            'courseid' => $courseid,
            'model' => $model,
            'modality' => $modality,
            'city' => $city,
            'actiononly' => $actiononly,
        ]);

        // The shape, before login: it says nothing about the site.
        window::from_iso($params['from'], $params['to'], new \DateTimeZone('UTC'));
        $on = keys::parse($params['keys']);

        require_login(null, false);
        if (isguestuser()) {
            throw new refusal();
        }
        self::validate_context(\core\context\system::instance());

        window::require_gregorian();
        $window = window::from_iso($params['from'], $params['to']);
        $scope = scope::for_viewer($params['categoryid'], $params['recursive'], $params['courseid']);
        $filters = new filters($params['model'], $params['modality'], $params['city'], $params['actiononly']);

        return range::answer($window, $scope, $on, $filters);
    }

    /**
     * What the function returns: an allowlist, key for key.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $counts = [];
        foreach (keys::ALL as $key) {
            $counts[$key] = new external_value(PARAM_INT, 'Items of this key touching the day.');
        }
        return new external_single_structure([
            'from' => new external_value(PARAM_ALPHANUMEXT, 'The first day answered.'),
            'to' => new external_value(PARAM_ALPHANUMEXT, 'The last day answered.'),
            'truncated' => new external_value(PARAM_BOOL, 'Whether open event courses were left out past the cap.'),
            'events' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The event id (source core) or the course id (source course).'),
                'source' => new external_value(PARAM_ALPHA, 'core or course.'),
                'key' => new external_value(PARAM_ALPHA, 'The key.'),
                'name' => new external_value(PARAM_RAW, 'The name, plain spelling.'),
                'timestart' => new external_value(PARAM_INT, 'The start.'),
                'timeduration' => new external_value(PARAM_INT, 'The duration in seconds.'),
                'courseid' => new external_value(PARAM_INT, 'The course, 0 for none.'),
                'categoryid' => new external_value(PARAM_INT, 'The category of a category event, else 0.'),
                'groupid' => new external_value(PARAM_INT, 'The group, 0 for none.'),
                'modulename' => new external_value(PARAM_PLUGIN, 'The module of an activity event, else empty.'),
                'instance' => new external_value(PARAM_INT, 'The module instance, else 0.'),
                'component' => new external_value(PARAM_COMPONENT, 'The component, else empty.'),
                'eventtype' => new external_value(PARAM_ALPHANUMEXT, 'Core\'s eventtype, empty for an event course.'),
                'location' => new external_value(PARAM_RAW, 'The location, plain spelling.'),
                'url' => new external_value(PARAM_URL, 'The item\'s own page; empty for its course\'s page (courses[].url).'),
                'actionevent' => new external_value(PARAM_BOOL, 'Whether the row is an action event, its action done or not.'),
                'action' => new external_single_structure([
                    'name' => new external_value(PARAM_RAW, 'The action\'s name, plain.'),
                    'url' => new external_value(PARAM_URL, 'Where it is done.'),
                    'itemcount' => new external_value(PARAM_INT, 'How many items it concerns.'),
                    'actionable' => new external_value(PARAM_BOOL, 'Whether it can be done now.'),
                ], 'The action core offers, null when none.', VALUE_DEFAULT, null, NULL_ALLOWED),
                'overdue' => new external_value(PARAM_BOOL, 'An actionable action whose date has passed.'),
                'held' => new external_value(PARAM_BOOL, 'An event course whose end date has passed.'),
            ])),
            'courses' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The course id.'),
                'fullname' => new external_value(PARAM_RAW, 'The full name, plain spelling.'),
                'shortname' => new external_value(PARAM_RAW, 'The short name, plain spelling.'),
                'categoryid' => new external_value(PARAM_INT, 'Its category, 0 when the viewer may not be told.'),
                'url' => new external_value(PARAM_URL, 'The course\'s page.'),
                'image' => new external_value(PARAM_RAW, 'The course image address, empty for none.'),
                'model' => new external_value(PARAM_INT, 'The landing page model, 0 for none.'),
                'modality' => new external_value(PARAM_INT, 'The modality, 0 for none.'),
                'city' => new external_value(PARAM_RAW, 'The city, plain.'),
                'venue' => new external_value(PARAM_RAW, 'The venue, plain.'),
                'landingurl' => new external_value(PARAM_RAW, 'The landing page, empty without a model.'),
                'enrolled' => new external_value(PARAM_BOOL, 'Whether the viewer is actively enrolled.'),
                'participants' => new external_value(
                    PARAM_INT,
                    'Active enrolments of an event course, null otherwise.',
                    VALUE_DEFAULT,
                    null,
                    NULL_ALLOWED
                ),
                'join' => new external_value(PARAM_ALPHA, 'open, free or key for an open event course, else empty.'),
            ])),
            'categories' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The category id.'),
                'name' => new external_value(PARAM_RAW, 'The name, plain spelling.'),
                'bg' => new external_value(PARAM_RAW, 'The branding background, #rrggbb.'),
                'ink' => new external_value(PARAM_RAW, 'The branding ink, #rrggbb, always beside its background.'),
            ])),
            'days' => new external_multiple_structure(new external_single_structure([
                'date' => new external_value(PARAM_ALPHANUMEXT, 'The day, YYYY-MM-DD.'),
                'counts' => new external_single_structure($counts),
            ])),
            'editing' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'A core event the viewer may edit (core\'s calendar_edit_event_allowed()).'),
                'edit' => new external_value(PARAM_BOOL, 'Whether core\'s event form edits it: not an activity\'s event.'),
                'delete' => new external_value(PARAM_BOOL, 'Core\'s candelete.'),
                'drag' => new external_value(PARAM_BOOL, 'Core\'s draggable: whether its start day may be moved.'),
                'count' => new external_value(PARAM_INT, 'The events of its series, 1 for a single event.'),
                'minday' => new external_value(PARAM_ALPHANUMEXT, 'The earliest day it may move to, YYYY-MM-DD, empty for none.'),
                'maxday' => new external_value(PARAM_ALPHANUMEXT, 'The latest day it may move to, YYYY-MM-DD, empty for none.'),
                'minerror' => new external_value(PARAM_TEXT, 'Core\'s message for a day before minday, plain.'),
                'maxerror' => new external_value(PARAM_TEXT, 'Core\'s message for a day after maxday, plain.'),
            ]), 'Core\'s editing answers, one per event the viewer may edit; empty for everything else.'),
        ]);
    }
}
