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
 * Theme Dimensions - Which steps of a trail the current reader sees, and how.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\trail;

use core\context_helper;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\population;
use theme_dimensions\local\landing\publicaccess;

/**
 * The decision of docs/trail/PLAN.md, section 6 (owner's decision Q4), for the current reader.
 *
 * {@see trail::many()} reads the same structure for everybody; this decides, step by step, what this
 * reader may be shown. In order:
 *
 * 1. The activity's restriction for this reader: one that hides it entirely (no text to show,
 *    {@see \cm_info::$availableinfo} empty while {@see \cm_info::$available} is false) takes the step away, one
 *    whose text is shown keeps it (the page draws it locked). Availability, not core's course-page visibility:
 *    the reader of a landing page is mostly not enrolled in the trail course, and core hides every activity of a
 *    course from a reader who lacks the module's view capability there. Where core does give this reader the
 *    activity ({@see \cm_info::$uservisible}, staff under any restriction), the step stays.
 * 2. An account sees in full exactly the courses the showcase would list to it, by the showcase's own gate
 *    ({@see population::admitted()} over the root scope: core's course and category visibility, then
 *    local_unlistedcourses' terms), as the calendar's open events do. An unlisted course is therefore drawn
 *    to an account enrolled in it or with a way to enrol, and to nobody else. Anything the gate refuses is
 *    not drawn: an account is never shown a ghost, which would tell of a course it is not meant to know.
 * 3. A visitor (nobody logged in, or the guest account) sees a public course in full
 *    ({@see publicaccess::are_public()}), and as a ghost, a card that names nothing, a course the catalogue
 *    lists without serving it to the public: visible, in a category the visitor can see, and kept unlisted
 *    neither by its own state nor by a category on its path. Anything else is not drawn.
 *
 * The statements are bounded by the trail, never by the site: one for the referenced courses' rows, then the
 * showcase gate (an account) or the public batch and the plugin's memoised unlisted categories (a visitor).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class display {
    /** @var string A step drawn in full: its name, its course's cover, category, hours and dates, and a link. */
    public const FULL = 'full';

    /** @var string A step drawn as a ghost: its place in the trail and nothing read from its course. */
    public const GHOST = 'ghost';

    /**
     * The steps this reader sees, each with how, in the trail's order; the steps they do not see are absent.
     *
     * @param trail $trail The trail.
     * @return array List of ['step' => step, 'kind' => FULL|GHOST, 'row' => \stdClass|null], where row is the
     *   referenced course's row (id, category, sortorder, visible, fullname, startdate, enddate, liststate and the
     *   context preload columns) for a full step, the shape {@see \theme_dimensions\local\category\cards::images()}
     *   reads; null for a ghost.
     */
    public static function steps(trail $trail): array {
        $modinfo = get_fast_modinfo($trail->courseid);
        // Each activity's availability and visibility read its context: the course's contexts in one statement.
        context_helper::preload_course($trail->courseid);
        $shown = [];
        foreach ($trail->steps as $step) {
            $cm = $modinfo->get_cm($step->cmid);
            if ($cm->uservisible || $cm->available || !empty($cm->availableinfo)) {
                $shown[] = $step;
            }
        }
        if (!$shown) {
            return [];
        }

        $rows = self::rows(array_map(static fn(step $step): int => $step->refcourseid, $shown));
        $visitor = !isloggedin() || isguestuser();
        $ghosts = [];
        if ($visitor) {
            $full = array_flip(publicaccess::are_public(array_keys($rows)));
            $ghosts = self::catalogued(array_diff_key($rows, $full));
        } else {
            $admitted = population::admitted(population::scope(new criteria(0)), $rows);
            $full = array_flip(array_map('intval', array_keys($admitted)));
        }

        $steps = [];
        foreach ($shown as $step) {
            $refid = $step->refcourseid;
            if (isset($full[$refid])) {
                $steps[] = ['step' => $step, 'kind' => self::FULL, 'row' => $rows[$refid]];
            } else if (isset($ghosts[$refid])) {
                $steps[] = ['step' => $step, 'kind' => self::GHOST, 'row' => null];
            }
        }
        return $steps;
    }

    /**
     * The referenced courses' rows, in the shape the showcase's gate and its images read, one statement.
     *
     * @param int[] $courseids The referenced course ids.
     * @return \stdClass[] Map of course id => row.
     */
    private static function rows(array $courseids): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(array_values(array_unique($courseids)), SQL_PARAMS_NAMED, 'ref');
        $state = discoverability::state_sql('c');
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.category, c.sortorder, c.visible, c.fullname, c.startdate, c.enddate,
                    {$state['column']} AS liststate, {$ctxselect}
               FROM {course} c
               JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse
                    {$state['join']}
              WHERE c.id {$insql}",
            $params + ['contextcourse' => CONTEXT_COURSE]
        );
        $keyed = [];
        foreach ($rows as $row) {
            $keyed[(int) $row->id] = $row;
        }
        return $keyed;
    }

    /**
     * The rows a visitor sees as ghosts: courses the catalogue lists, which is to say visible, every category on
     * their path visible, and kept unlisted neither by their own state nor by a category on their path.
     *
     * Read from the records' own flags, not from what the current reader may open: whether the catalogue lists a
     * course is the question, and core_course_category::get() also asks the reader's capability to view the list.
     * get_many() reads the categories whatever their visibility, from core's cache.
     *
     * @param \stdClass[] $rows The rows of the courses that are not public, keyed by course id.
     * @return \stdClass[] The rows kept, same keys.
     */
    private static function catalogued(array $rows): array {
        if (!$rows) {
            return [];
        }
        $unlisted = array_flip(category_discoverability::unlisted_ids());
        $paths = [];
        foreach ($rows as $row) {
            $paths[(int) $row->category] = [];
        }
        foreach (\core_course_category::get_many(array_keys($paths)) as $category) {
            $paths[(int) $category->id] = array_map('intval', explode('/', trim($category->path, '/')));
        }
        $onpaths = $paths ? array_values(array_unique(array_merge(...array_values($paths)))) : [];
        $categories = $onpaths ? \core_course_category::get_many($onpaths) : [];

        $kept = [];
        foreach ($rows as $courseid => $row) {
            $path = $paths[(int) $row->category];
            if (!$path || !$row->visible || (int) $row->liststate === discoverability::STATE_UNLISTED) {
                continue;
            }
            if (array_intersect_key(array_flip($path), $unlisted)) {
                continue;
            }
            foreach ($path as $categoryid) {
                if (empty($categories[$categoryid]) || !$categories[$categoryid]->visible) {
                    continue 2;
                }
            }
            $kept[$courseid] = $row;
        }
        return $kept;
    }
}
