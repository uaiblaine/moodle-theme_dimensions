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
 * Theme Dimensions - The viewer's calendar sets, built as core builds them.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use local_unlistedcourses\category_access;

/**
 * The users, groups, courses and categories one viewer's calendar reads events from, narrowed by a category or a course.
 *
 * The sets are core's own: this class mirrors {@see \calendar_information::create()} and its
 * {@see \calendar_information::set_sources()} line for line, and must be re-read against them on every core
 * upgrade (scope_test compares the two set for set, for the site, a category and a course). Three differences,
 * all deliberate:
 *
 * - The filters are built with calendar_set_filters($courses, true). Core calls it without the flag, which
 *   reads calendar_show_event_type() and WRITES $SESSION->calendarshoweventtype on the first call of a
 *   session (core asks it once even with the flag, so {@see filters()} takes the key out again). That
 *   bitmask is core's session-only key filter; nothing in core writes anything but its default
 *   (calendar_set_event_type_display() has no caller), and for a logged-in account the flag gives exactly
 *   the default's sets. The theme's keys replace it ({@see keys}), and the range service keeps a read-only
 *   session.
 * - A category narrows the courses to its WHOLE subtree when $recursive is on (the default), through
 *   get_all_children_ids(). Core keeps the category and its direct children only (get_children(), the
 *   "lib.php:1146" gap of docs/calendar/PROPOSAL.md section 2.5), while its category events already use the
 *   whole subtree; $recursive off is core's reading, kept for the parity test.
 * - The site view's category cache (calendar_categories, MODE_SESSION) is read and never set: a set would write
 *   $SESSION under the read-only session the range service declares.
 *
 * The narrowing refuses with the one {@see refusal}: a course that is not one of the viewer's calendar
 * courses (whether it exists or not), a category that does not exist, that core does not let the viewer
 * see or that local_unlistedcourses withholds from them. A course wins over a category, as in core's create().
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope {
    /**
     * @var string The course columns read with the viewer's calendar courses: what core reads, the columns modinfo
     *   keeps in its cache (so a record primes get_fast_modinfo() without a statement) and the event courses' dates.
     */
    public const COURSE_FIELDS = 'id, category, groupmode, groupmodeforce, shortname, fullname, format, enablecompletion, '
        . 'cacherev, visible, startdate, enddate';

    /** @var int The viewer. */
    public readonly int $viewerid;

    /** @var \stdClass[] The viewer's calendar courses after the narrowing, keyed by id: the rows of COURSE_FIELDS. */
    public readonly array $courses;

    /** @var array|null The users argument of api::get_events(), mapped as calendar_get_view() maps it. */
    public readonly ?array $users;

    /** @var array|null The groups argument: null means every group (calendar_adminseesall). */
    public readonly ?array $groups;

    /** @var array|null The courses argument, SITEID last as calendar_set_filters() puts it. */
    public readonly ?array $courseids;

    /** @var array|null The categories argument. */
    public readonly ?array $categories;

    /** @var int[]|null The categories an event course must sit in for the narrowing, null without a category. */
    public readonly ?array $tree;

    /** @var int The course narrowed to, 0 for none. */
    public readonly int $courseid;

    /**
     * Hold the sets; use {@see for_viewer()}.
     *
     * @param int $viewerid The viewer.
     * @param array $courses The calendar courses.
     * @param array $sets [users, groups, courses, categories] as calendar_get_view() maps them.
     * @param array|null $tree The narrowing's categories, or null.
     * @param int $courseid The course narrowed to, or 0.
     */
    private function __construct(int $viewerid, array $courses, array $sets, ?array $tree, int $courseid) {
        $this->viewerid = $viewerid;
        $this->courses = $courses;
        [$this->users, $this->groups, $this->courseids, $this->categories] = $sets;
        $this->tree = $tree;
        $this->courseid = $courseid;
    }

    /**
     * Whether the current viewer may narrow the calendar to a category: the one rule of {@see for_viewer()}.
     *
     * The category exists, core lets the viewer see it and local_unlistedcourses does not withhold it. Shared with
     * the redirect that sends a course calendar's address with its category ({@see coreview::course_category()}),
     * so an address never names a category the page would refuse.
     *
     * @param \core_course_category|null $category The category, null when it does not exist.
     * @return bool
     */
    public static function accepts_category(?\core_course_category $category): bool {
        return $category !== null
            && $category->is_uservisible()
            && (bool) category_access::filter_categories([(int) $category->id => $category]);
    }

    /**
     * The current user's calendar sets, narrowed by a course, else by a category.
     *
     * @param int $categoryid The category to narrow to, 0 for none.
     * @param bool $recursive Whether the category's whole subtree counts (true) or its direct children only (core).
     * @param int $courseid The course to narrow to, 0 for none; it must be one of the viewer's calendar courses.
     * @return self
     * @throws refusal On a course or category the viewer may not narrow to.
     */
    public static function for_viewer(int $categoryid = 0, bool $recursive = true, int $courseid = 0): self {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/calendar/lib.php');
        $courses = calendar_get_default_courses(null, self::COURSE_FIELDS);
        $tree = null;
        if ($courseid !== 0) {
            // One refusal for a course that does not exist, one that is hidden and one the viewer is not in.
            if (!isset($courses[$courseid])) {
                throw new refusal();
            }
            $courses = [$courseid => $courses[$courseid]];
            // Core throws for a category row that is gone; the viewer's own course still gets the one refusal.
            $category = \core_course_category::get((int) $courses[$courseid]->category, IGNORE_MISSING, true);
            if (!$category) {
                throw new refusal();
            }
            // Core's course branch of set_sources(): the course's category and its parents, never its children.
            $categories = $category->get_parents();
            $categories[] = $category->id;
        } else if ($categoryid !== 0) {
            $category = \core_course_category::get($categoryid, IGNORE_MISSING);
            // A category local_unlistedcourses withholds is refused like a nonexistent one: no existence oracle.
            if (!self::accepts_category($category)) {
                throw new refusal();
            }
            $tree = [(int) $category->id];
            if ($recursive) {
                $tree = array_merge($tree, array_map('intval', $category->get_all_children_ids()));
            } else {
                // Core's create(): the category and its direct children, as get_children() lists them.
                $tree = array_merge($tree, array_map('intval', array_keys($category->get_children())));
            }
            $courses = array_filter($courses, static fn($course): bool => in_array((int) $course->category, $tree, true));
            // Core's category branch of set_sources(): parents, the category, its whole subtree.
            $categories = $category->get_parents();
            $categories[] = $category->id;
            $categories = array_merge($categories, $category->get_all_children_ids());
        } else {
            $categories = self::site_categories($courses);
        }

        [$courseids, $groups, $users] = self::filters($courses);
        $sets = array_map(static function ($param) {
            // The normalisation calendar_get_view() applies before api::get_events(), verbatim.
            if ($param === true) {
                return null;
            }
            if ($param === false) {
                return [];
            }
            if (!is_array($param)) {
                return [$param];
            }
            return $param;
        }, [$users, $groups, $courseids, $categories]);

        return new self((int) $USER->id, $courses, $sets, $tree, $courseid);
    }

    /**
     * calendar_set_filters() with its $ignorefilters flag, leaving $SESSION as it found it.
     *
     * Even with the flag, core asks calendar_show_event_type() for the group key before it reads the flag, and
     * that call writes the default bitmask into $SESSION->calendarshoweventtype when the session has none (or
     * holds null). The value read is the default, and the flag wins over it anyway, so the key is put back as
     * it was, or removed when this call added it: the range keeps a read-only session.
     *
     * @param \stdClass[] $courses The viewer's calendar courses.
     * @return array [courses, groups, users] as calendar_set_filters() returns them.
     */
    private static function filters(array $courses): array {
        global $SESSION;

        // A key that was there holding null is put back as null, so this asks property_exists() and not isset().
        $had = property_exists($SESSION, 'calendarshoweventtype');
        $before = $had ? $SESSION->calendarshoweventtype : null;
        $filters = calendar_set_filters($courses, true);
        if ($had) {
            $SESSION->calendarshoweventtype = $before;
        } else {
            unset($SESSION->calendarshoweventtype);
        }
        return $filters;
    }

    /**
     * The categories of the site view: those holding one of the viewer's courses, those they manage, and their parents.
     *
     * The site branch of {@see \calendar_information::set_sources()}, reading its session cache (core's cache, key and
     * value, so a viewer who opened core's calendar first pays nothing here) but never writing it: the cache is
     * MODE_SESSION, and a set would write $SESSION under the read-only session the range service declares. A miss
     * is computed again by the next call.
     *
     * @param \stdClass[] $courses The viewer's calendar courses.
     * @return int[]
     */
    private static function site_categories(array $courses): array {
        global $USER;

        $coursecategories = array_flip(array_map(static fn($course) => $course->category, $courses));
        $cache = \cache::make('core', 'calendar_categories');
        $categories = $cache->get('site');
        if ($categories === false) {
            $found = [];
            foreach (\core_course_category::get_all() as $category) {
                if (
                    isset($coursecategories[$category->id])
                    || has_capability('moodle/category:manage', $category->get_context(), $USER, false)
                ) {
                    $found[$category->id] = true;
                    foreach ($category->get_parents() as $parentid) {
                        $found[$parentid] = true;
                    }
                }
            }
            $categories = array_keys($found);
        }
        return $categories;
    }

    /**
     * The viewer's calendar course ids, the site course left out.
     *
     * @return int[]
     */
    public function course_ids(): array {
        return array_values(array_diff(array_map('intval', array_keys($this->courses)), [(int) SITEID]));
    }
}
