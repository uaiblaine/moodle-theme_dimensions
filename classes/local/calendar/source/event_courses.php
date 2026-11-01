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
 * Theme Dimensions - The event courses of the window as range items.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar\source;

use core\context_helper;
use local_unlistedcourses\access;
use local_unlistedcourses\discoverability;
use theme_dimensions\local\calendar\keys;
use theme_dimensions\local\calendar\range;
use theme_dimensions\local\calendar\scope;
use theme_dimensions\local\calendar\source;
use theme_dimensions\local\calendar\window;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\population;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landingfields;

/**
 * Courses of the event model whose dates meet the window: one bar each, from the course start to its end.
 *
 * An event course has no {event} row: its dates are the course's own, `startdate` to `enddate` (an end of 0
 * is the start day alone), so nothing is duplicated and nothing is written. The model is the custom field
 * `dimensions_model` holding {@see landingfields::MODEL_EVENT}; the field's own default stays empty for good
 * (the "landing page defaults" section of the theme's CLAUDE.md), so only a course with a stored row is an
 * event course and the statements join the row. Two halves:
 *
 * - key "course": the event courses the viewer is actively enrolled in, among their calendar courses after
 *   the narrowing ({@see scope::course_ids()}), always. Driven by those ids.
 * - key "open": the event courses the viewer holds no relationship with and could join now. Nothing here
 *   decides who may see one: the candidates go through the showcase's own decision layer,
 *   {@see population::admitted()} over the root scope (core's course and category visibility, then
 *   local_unlistedcourses' category and course terms), and the plugin's own batch,
 *   {@see access::get_next_actions()}, says what the viewer could do, page-sized as it asks. Joinable is what
 *   the showcase calls open: a route (self, apply, fee, autoenrol) or guest access, free or behind a key
 *   ({@see \theme_dimensions\local\category\classifier::RANK} group 1). A course with a row of the viewer's
 *   (scheduled, pending, waiting, suspended, ended) is never an open event: the showcase tells them about
 *   that row instead. At most {@see OPEN_CANDIDATES} candidates are read, earliest start first, and they go through
 *   the gate and the batch in pages of {@see OPEN_CAP}, so hidden, withheld or closed courses cost a read and
 *   never a place; at most {@see OPEN_CAP} joinable ones are answered, and {@see truncated()} says when more
 *   joinable ones were found or the read stopped at its bound, for the page to say "more in the catalogue".
 *   With a course narrowing there is no open layer: the narrowing is one of the viewer's own courses.
 *
 * Items carry the course's dates, `held` when its end date has passed (landing_page::event_held()'s rule),
 * and the landing page's address (an event course always has a landing page model).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_courses implements source {
    /** @var string The source name items carry. */
    public const SOURCE = 'course';

    /** @var int The most open event courses one window answers: a page of the plugin's batch. */
    public const OPEN_CAP = 50;

    /** @var int The most candidates one window reads before the gate: withheld ones must not push joinable ones out. */
    public const OPEN_CANDIDATES = 200;

    /** @var array The viewer's enrolments, in the shape of classifier::enrolments(). */
    private array $enrolments;

    /** @var bool Whether joinable open courses may have been left out past the cap or the candidate bound. */
    private bool $truncated = false;

    /** @var array The joinable answer per open course: course id => 'open', 'free' or 'key'. */
    private array $joins = [];

    /**
     * Build the source over the viewer's enrolments, read once per range.
     *
     * @param array $enrolments The viewer's enrolments, from classifier::enrolments().
     */
    public function __construct(array $enrolments) {
        $this->enrolments = $enrolments;
    }

    /**
     * The keys this source's items can carry.
     *
     * @return string[]
     */
    public function keys(): array {
        return [keys::COURSE, keys::OPEN];
    }

    /**
     * Whether open event courses were left out past the cap in the last call.
     *
     * @return bool
     */
    public function truncated(): bool {
        return $this->truncated;
    }

    /**
     * What the viewer could do in each open event course of the last call.
     *
     * @return array course id => 'open' (an enrolment route), 'free' or 'key' (guest access).
     */
    public function joins(): array {
        return $this->joins;
    }

    /**
     * The window's event courses this viewer is enrolled in or could join, for the keys that are on.
     *
     * @param window $window The days asked for.
     * @param scope $scope The viewer's calendar sets.
     * @param string[] $on The keys that are on.
     * @return array[] Items with exactly the keys of range::ITEM_KEYS.
     */
    public function items(window $window, scope $scope, array $on): array {
        $this->truncated = false;
        $this->joins = [];
        $field = landingfields::get_field(landingfields::SHORTNAME_MODEL);
        if (!$field) {
            return [];
        }
        $fieldid = (int) $field->get('id');
        $items = [];
        if (in_array(keys::COURSE, $on, true)) {
            foreach ($this->enrolled($window, $scope, $fieldid) as $row) {
                $items[] = self::item($row, keys::COURSE);
            }
        }
        if (in_array(keys::OPEN, $on, true) && $scope->courseid === 0) {
            foreach ($this->open($window, $scope, $fieldid) as $row) {
                $items[] = self::item($row, keys::OPEN);
            }
        }
        return $items;
    }

    /**
     * The event courses of the window the viewer is actively enrolled in, among their narrowed calendar courses.
     *
     * Rides the primary key of {course} and the instanceid-fieldid index of {customfield_data}; driven by the
     * viewer's own course ids, which already carry core's visibility: {@see scope::for_viewer()} takes them from
     * calendar_get_default_courses(), whose enrol_get_users_courses() drops a hidden course for a viewer without
     * viewhiddencourses. The showcase's gate is not asked again here: it would admit every one of them, because
     * an active enrolment lifts both core's and local_unlistedcourses' category clamps and the plugin's course
     * term rescues a related course. The mutation sweep of 2026-11-01 proved the second gate held nothing.
     *
     * @param window $window The days.
     * @param scope $scope The viewer's calendar sets.
     * @param int $fieldid The model field.
     * @return \stdClass[] Course rows.
     */
    private function enrolled(window $window, scope $scope, int $fieldid): array {
        global $DB;

        $ids = [];
        foreach ($scope->course_ids() as $courseid) {
            if (!empty($this->enrolments[$courseid]->active)) {
                $ids[] = $courseid;
            }
        }
        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'mine');
        [$where, $windowparams] = self::window_sql($window, $fieldid);
        return $DB->get_records_sql(
            "SELECT c.id, c.category, c.fullname, c.startdate, c.enddate
               FROM {course} c
               JOIN {customfield_data} d ON d.instanceid = c.id
              WHERE c.id {$insql} AND {$where}
           ORDER BY c.startdate, c.id",
            $params + $windowparams
        );
    }

    /**
     * The event courses of the window the viewer holds no relationship with, admitted by the showcase, joinable now.
     *
     * One statement for the candidates (capped, earliest first), then the showcase's gate over the root scope
     * and the plugin's batch over what passed it. Rides the startdate of {course} and the field's rows.
     *
     * @param window $window The days.
     * @param scope $scope The viewer's calendar sets, whose category narrowing applies.
     * @param int $fieldid The model field.
     * @return \stdClass[] Course rows.
     */
    private function open(window $window, scope $scope, int $fieldid): array {
        global $DB;

        [$where, $params] = self::window_sql($window, $fieldid);
        $related = [];
        foreach ($this->enrolments as $courseid => $mine) {
            if ($mine->active || $mine->inactive || $mine->type !== access::RELATIONSHIP_NONE) {
                $related[] = (int) $courseid;
            }
        }
        if ($related) {
            [$notsql, $notparams] = $DB->get_in_or_equal($related, SQL_PARAMS_NAMED, 'rel', false);
            $where .= " AND c.id {$notsql}";
            $params += $notparams;
        }
        if ($scope->tree !== null) {
            [$catsql, $catparams] = $DB->get_in_or_equal($scope->tree, SQL_PARAMS_NAMED, 'tree');
            $where .= " AND c.category {$catsql}";
            $params += $catparams;
        }
        $state = discoverability::state_sql('c');
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.category, c.sortorder, c.visible, c.fullname, c.startdate, c.enddate,
                    {$state['column']} AS liststate, {$ctxselect}
               FROM {course} c
               JOIN {customfield_data} d ON d.instanceid = c.id
               JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse
                    {$state['join']}
              WHERE {$where}
           ORDER BY c.startdate, c.id",
            $params + ['contextcourse' => CONTEXT_COURSE],
            0,
            self::OPEN_CANDIDATES + 1
        );
        $bound = count($rows) > self::OPEN_CANDIDATES;
        if ($bound) {
            $rows = array_slice($rows, 0, self::OPEN_CANDIDATES, true);
        }

        // Pages of the cap's size: the plugin's batch takes a page, never a population. Stops at the first joinable
        // course past the cap, which is all "truncated" needs to know.
        $root = population::scope(new criteria(0));
        $open = [];
        $joins = [];
        foreach (array_chunk($rows, self::OPEN_CAP, true) as $page) {
            // The showcase's own decision: would core and local_unlistedcourses list this course to this viewer.
            $admitted = population::admitted($root, $page);
            if (!$admitted) {
                continue;
            }
            $nexts = access::get_next_actions(array_keys($admitted));
            foreach ($admitted as $courseid => $row) {
                $join = isset($nexts[$courseid]) ? self::join($nexts[$courseid]) : '';
                if ($join !== '') {
                    $joins[(int) $courseid] = $join;
                    $open[(int) $courseid] = $row;
                }
            }
            if (count($open) > self::OPEN_CAP) {
                break;
            }
        }
        $surplus = count($open) > self::OPEN_CAP;
        if ($surplus) {
            $open = array_slice($open, 0, self::OPEN_CAP, true);
        }
        $this->truncated = $surplus || $bound;
        $this->joins = array_intersect_key($joins, $open);
        return $open;
    }

    /**
     * What a next action of local_unlistedcourses lets the viewer do now, in the showcase's vocabulary.
     *
     * @param array $next One answer of access::get_next_actions().
     * @return string 'open' for a route, 'free' or 'key' for guest access, '' for anything that is not a way in now.
     */
    private static function join(array $next): string {
        if ($next['type'] === access::NEXT_OPEN) {
            return 'open';
        }
        if ($next['type'] === access::NEXT_GUEST) {
            return $next['guest'] === access::GUEST_KEY ? 'key' : 'free';
        }
        return '';
    }

    /**
     * The predicate both statements share: an event course, not the site, whose dates meet the window.
     *
     * An end date of 0, or one before the start, is the start day alone. A start of 0 is no date at all.
     *
     * @param window $window The days.
     * @param int $fieldid The model field.
     * @return array [sql, params]; the statement joins {customfield_data} as d.
     */
    private static function window_sql(window $window, int $fieldid): array {
        $sql = "d.fieldid = :modelfield AND d.component = :modelcomponent AND d.area = :modelarea AND d.itemid = 0
                AND d.intvalue = :eventmodel AND c.id <> :siteid AND c.startdate > 0 AND c.startdate <= :windowend
                AND (CASE WHEN c.enddate > c.startdate THEN c.enddate ELSE c.startdate END) >= :windowstart";
        return [$sql, [
            'modelfield' => $fieldid,
            'modelcomponent' => 'core_course',
            'modelarea' => 'course',
            'eventmodel' => landingfields::MODEL_EVENT,
            'siteid' => SITEID,
            'windowend' => $window->tend,
            'windowstart' => $window->tstart,
        ]];
    }

    /**
     * One event course as a range item.
     *
     * @param \stdClass $row The course row.
     * @param string $key Its key.
     * @return array An item with exactly the keys of range::ITEM_KEYS.
     */
    private static function item(\stdClass $row, string $key): array {
        $start = (int) $row->startdate;
        $end = (int) $row->enddate;
        $courseid = (int) $row->id;
        return range::item([
            'id' => $courseid,
            'source' => self::SOURCE,
            'key' => $key,
            'name' => format_string($row->fullname, true, [
                'context' => \core\context\course::instance($courseid),
                'escape' => false,
            ]),
            'timestart' => $start,
            'timeduration' => max(0, $end - $start),
            'courseid' => $courseid,
            // A course-bound item names its category through the course dictionary, as core's course events do.
            'categoryid' => 0,
            'groupid' => 0,
            'modulename' => '',
            'instance' => 0,
            'component' => '',
            'eventtype' => '',
            'location' => '',
            'url' => links::page($courseid)->out(false),
            'actionevent' => false,
            'action' => null,
            'overdue' => false,
            'held' => $end > 0 && $end < time(),
        ]);
    }
}
