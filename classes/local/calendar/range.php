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
 * Theme Dimensions - One month grid of the calendar, in one answer.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use core\context_helper;
use theme_dimensions\local\calendar\source\core_events;
use theme_dimensions\local\calendar\source\event_courses;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\cards;
use theme_dimensions\local\category\classifier;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landingfields;

/**
 * The orchestration of one range call: the sources, the filters, the dictionaries and the day counts.
 *
 * The answer is normalised: each event once (`events`), each course once (`courses`), each category once
 * (`categories`), and a count per key for every day of the window (`days`), so a ten-day event is one item
 * where core's month view exports it ten times. An item's url is its own page where it has one (an activity, a
 * category, an event course's landing page); an item that links to its course's page carries '' and the course
 * carries the page once (`courses[].url`), the site's home standing for it when there is no course. It holds
 * no HTML, no description and no image data: the course image is an ADDRESS ({@see cards::images()}, core's
 * course_image answer read in bulk), never the generated pattern, which was 19 KB per event in the C0 baseline.
 *
 * The statements are flat in the number of events: everything read per course (the rows, their context,
 * the theme's custom fields, the course images, the participants of the event courses) and per category
 * (the branding pair) is one statement each for the whole answer, driven by the courses and categories the
 * items name. What remains per event is core's own: the event factory and the module callbacks
 * (docs/calendar/PROPOSAL.md section 9.2; range_budget_test measures it).
 *
 * A category is named only where core and local_unlistedcourses let the viewer discover it: a course in a
 * category the viewer may not see keeps categoryid 0 in the dictionary, and a category event (which core
 * shows only in a category the viewer sees) still carries its categoryid, with no entry when the plugin
 * withholds the name.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class range {
    /** @var string[] The keys of every item, in order: the allowlist the web service returns. */
    public const ITEM_KEYS = [
        'id',
        'source',
        'key',
        'name',
        'timestart',
        'timeduration',
        'courseid',
        'categoryid',
        'groupid',
        'modulename',
        'instance',
        'component',
        'eventtype',
        'location',
        'url',
        'actionevent',
        'action',
        'overdue',
        'held',
    ];

    /** @var string[] The custom fields the course dictionary and the filters read. */
    private const COURSE_FIELDS = [
        landingfields::SHORTNAME_MODEL,
        landingfields::SHORTNAME_MODALITY,
        landingfields::SHORTNAME_CITY,
        landingfields::SHORTNAME_VENUE,
    ];

    /**
     * Check an item has exactly the allowlisted keys, in order.
     *
     * @param array $item The item a source built.
     * @return array The same item.
     * @throws \coding_exception When a source adds or forgets a key.
     */
    public static function item(array $item): array {
        if (array_keys($item) !== self::ITEM_KEYS) {
            throw new \coding_exception('A calendar range item must carry exactly range::ITEM_KEYS.');
        }
        return $item;
    }

    /**
     * Whether an item belongs to a course other than the site: the items the course filters apply to.
     *
     * @param array $item A range item.
     * @return bool
     */
    public static function course_bound(array $item): bool {
        return $item['courseid'] > 0 && $item['courseid'] !== (int) SITEID;
    }

    /**
     * The whole answer for one window.
     *
     * @param window $window The days.
     * @param scope $scope The viewer's calendar sets.
     * @param string[] $on The keys that are on ({@see keys::parse()}).
     * @param filters $filters The "More" filters.
     * @param bool $withediting Whether `editing` carries core's editing answers ({@see editing::entries()}): on for the
     *     page and its service, off for the blocks, which draw no editing and are not charged for it.
     * @return array ['from', 'to', 'truncated', 'events', 'courses', 'categories', 'days', 'editing'].
     */
    public static function answer(window $window, scope $scope, array $on, filters $filters, bool $withediting = true): array {
        $enrolments = null;
        $items = [];
        $truncated = false;
        $joins = [];
        $entries = [];

        if (array_intersect(keys::CORE, $on)) {
            $coreevents = new core_events($withediting);
            $items = array_merge($items, $coreevents->items($window, $scope, $on));
            $entries = $coreevents->editing();
        }
        if (array_intersect([keys::COURSE, keys::OPEN], $on)) {
            $enrolments = classifier::enrolments($scope->viewerid);
            $eventcourses = new event_courses($enrolments);
            $items = array_merge($items, $eventcourses->items($window, $scope, $on));
            $truncated = $eventcourses->truncated();
            $joins = $eventcourses->joins();
        }

        // The courses the items name, and their facts: one statement each, whatever the number of events.
        $courseids = [];
        foreach ($items as $item) {
            if (self::course_bound($item)) {
                $courseids[$item['courseid']] = true;
            }
        }
        $rows = self::course_rows(array_keys($courseids));
        $values = $rows ? landingfields::values_many(array_keys($rows), false, self::COURSE_FIELDS) : [];

        $kept = [];
        foreach ($items as $item) {
            if (self::course_bound($item) && !isset($rows[$item['courseid']])) {
                // A course gone between the two statements: nothing to name it by.
                continue;
            }
            if ($filters->keeps($item, $values[$item['courseid']] ?? null)) {
                $kept[] = $item;
            }
        }
        usort($kept, static fn(array $a, array $b): int => [$a['timestart'], $a['source'], $a['id']]
            <=> [$b['timestart'], $b['source'], $b['id']]);

        $referenced = [];
        $eventcourseids = [];
        $categoryids = [];
        foreach ($kept as $item) {
            if (self::course_bound($item)) {
                $referenced[$item['courseid']] = $rows[$item['courseid']];
                if ($item['source'] === event_courses::SOURCE) {
                    $eventcourseids[$item['courseid']] = true;
                }
            }
            if ($item['categoryid']) {
                $categoryids[$item['categoryid']] = true;
            }
        }
        foreach ($referenced as $row) {
            $categoryids[(int) $row->category] = true;
        }
        $categories = self::categories(array_keys($categoryids));
        if ($referenced && $enrolments === null) {
            $enrolments = classifier::enrolments($scope->viewerid);
        }

        return [
            'from' => $window->from,
            'to' => $window->to,
            'truncated' => $truncated,
            'events' => $kept,
            'courses' => self::courses($referenced, $values, $categories, $enrolments ?? [], array_keys($eventcourseids), $joins),
            'categories' => array_values($categories),
            'days' => self::days($window, $kept),
            'editing' => self::kept_entries($entries, $kept),
        ];
    }

    /**
     * Core's editing answers for the core items the filters kept, and no other: an entry names an event by its id.
     *
     * @param array[] $entries The core source's entries.
     * @param array[] $kept The kept items.
     * @return array[] The entries of kept core items, in the source's order.
     */
    private static function kept_entries(array $entries, array $kept): array {
        $ids = [];
        foreach ($kept as $item) {
            if ($item['source'] === core_events::SOURCE) {
                $ids[$item['id']] = true;
            }
        }
        return array_values(array_filter($entries, static fn(array $entry): bool => isset($ids[$entry['id']])));
    }

    /**
     * The course rows the dictionary is built from, their contexts preloaded, in one statement.
     *
     * Rides the primary key of {course}; bounded by the courses the window's items name.
     *
     * @param int[] $courseids The courses.
     * @return \stdClass[] course id => row (id, category, sortorder, visible, fullname, shortname, format, ctx columns).
     */
    private static function course_rows(array $courseids): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'dict');
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.category, c.sortorder, c.visible, c.fullname, c.shortname, c.format, {$ctxselect}
               FROM {course} c
               JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse
              WHERE c.id {$insql}",
            $params + ['contextcourse' => CONTEXT_COURSE]
        );
        $keyed = [];
        foreach ($rows as $row) {
            // From a clone: cards::images() reads the preload columns off the rows again.
            context_helper::preload_from_record(clone $row);
            $keyed[(int) $row->id] = $row;
        }
        return $keyed;
    }

    /**
     * The course dictionary: each course the answer names, once.
     *
     * @param \stdClass[] $rows The referenced course rows, keyed by id.
     * @param \stdClass[] $values Their typed custom field values, keyed by id.
     * @param array $categories The nameable categories, keyed by id.
     * @param array $enrolments The viewer's enrolments, from classifier::enrolments().
     * @param int[] $eventcourseids The courses drawn as event courses, whose participants are counted.
     * @param array $joins What the viewer could do in each open event course.
     * @return array[] The entries, in course id order.
     */
    private static function courses(
        array $rows,
        array $values,
        array $categories,
        array $enrolments,
        array $eventcourseids,
        array $joins
    ): array {
        global $CFG;

        if (!$rows) {
            return [];
        }
        require_once($CFG->dirroot . '/course/lib.php');
        ksort($rows);
        $images = cards::images($rows);
        $participants = self::participants($eventcourseids);
        $courses = [];
        foreach ($rows as $courseid => $row) {
            $context = \core\context\course::instance($courseid);
            $facts = $values[$courseid];
            $categoryid = (int) $row->category;
            $courses[] = [
                'id' => $courseid,
                'fullname' => format_string($row->fullname, true, ['context' => $context, 'escape' => false]),
                'shortname' => format_string($row->shortname, true, ['context' => $context, 'escape' => false]),
                'categoryid' => isset($categories[$categoryid]) ? $categoryid : 0,
                'url' => \course_get_url($row)->out(false),
                'image' => isset($images[$courseid]) ? $images[$courseid]->out(false) : '',
                'model' => (int) $facts->model,
                'modality' => (int) $facts->modality,
                'city' => (string) ($facts->city ?? ''),
                'venue' => (string) ($facts->venue ?? ''),
                'landingurl' => $facts->model > 0 ? links::page($courseid)->out(false) : '',
                'enrolled' => !empty($enrolments[$courseid]->active),
                'participants' => array_key_exists($courseid, $participants) ? $participants[$courseid] : null,
                'join' => $joins[$courseid] ?? '',
            ];
        }
        return $courses;
    }

    /**
     * The active enrolments of each event course, in one grouped statement.
     *
     * Active as core's enrolment check reads it: an enabled instance, an active row, inside its dates, of an
     * account that is neither deleted nor suspended. A number only (decision D3): who they are is not part of the answer.
     *
     * @param int[] $courseids The event courses.
     * @return int[] course id => count, for every id given.
     */
    private static function participants(array $courseids): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        $counts = array_fill_keys($courseids, 0);
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'evc');
        $now = time();
        // Rides the courseid foreign key of {enrol} and the enrolid one of {user_enrolments}.
        $rows = $DB->get_records_sql_menu(
            "SELECT e.courseid, COUNT(DISTINCT ue.userid)
               FROM {enrol} e
               JOIN {user_enrolments} ue ON ue.enrolid = e.id
              JOIN {user} u ON u.id = ue.userid
              WHERE e.courseid {$insql} AND e.status = :instanceenabled AND ue.status = :rowactive
                    AND u.deleted = 0 AND u.suspended = 0
                    AND ue.timestart <= :nowstart AND (ue.timeend = 0 OR ue.timeend > :nowend)
           GROUP BY e.courseid",
            $params + [
                'instanceenabled' => ENROL_INSTANCE_ENABLED,
                'rowactive' => ENROL_USER_ACTIVE,
                'nowstart' => $now,
                'nowend' => $now,
            ]
        );
        foreach ($rows as $courseid => $count) {
            $counts[(int) $courseid] = (int) $count;
        }
        return $counts;
    }

    /**
     * The categories the answer may name, each with its branding pair.
     *
     * Named when core lets the viewer see the category ({@see \core_course_category::get()} without
     * $alwaysreturnhidden answers null otherwise, from its record cache) and local_unlistedcourses does not
     * withhold it. The pair is branding::colours(), one statement for all of them, taken whole. Shared with the
     * Trilhas view's answer ({@see trails::answer()}), which names its trails' and steps' categories by the same rule.
     *
     * @param int[] $categoryids The categories.
     * @return array category id => ['id', 'name', 'bg', 'ink'].
     */
    public static function categories(array $categoryids): array {
        $visible = [];
        foreach ($categoryids as $categoryid) {
            $category = $categoryid > 0 ? \core_course_category::get($categoryid, IGNORE_MISSING) : null;
            if ($category) {
                $visible[(int) $categoryid] = $category;
            }
        }
        $visible = \local_unlistedcourses\category_access::filter_categories($visible);
        if (!$visible) {
            return [];
        }
        ksort($visible);
        $colours = branding::colours(array_keys($visible));
        $categories = [];
        foreach ($visible as $categoryid => $category) {
            $categories[$categoryid] = [
                'id' => $categoryid,
                'name' => format_string($category->name, true, ['context' => $category->get_context(), 'escape' => false]),
                'bg' => $colours[$categoryid]['bgcolour'],
                'ink' => $colours[$categoryid]['inkcolour'],
            ];
        }
        return $categories;
    }

    /**
     * The count per key of every day of the window: the chips' numbers and the phone's dots.
     *
     * An item counts on every day it touches: from its start to its last second (an item of no duration
     * touches its start day alone), clipped to the window.
     *
     * @param window $window The days.
     * @param array[] $items The kept items.
     * @return array[] One entry per day: ['date' => YYYY-MM-DD, 'counts' => key => int, every key present].
     */
    public static function days(window $window, array $items): array {
        $days = [];
        foreach ($window->days as $date) {
            $days[] = ['date' => $date, 'counts' => array_fill_keys(keys::ALL, 0)];
        }
        foreach ($items as $item) {
            $start = $item['timestart'];
            $last = $start + max(0, $item['timeduration'] - 1);
            if ($last < $window->tstart || $start > $window->tend) {
                continue;
            }
            $first = $window->day_index($start);
            $end = $window->day_index($last);
            for ($i = $first; $i <= $end; $i++) {
                $days[$i]['counts'][$item['key']]++;
            }
        }
        return $days;
    }
}
