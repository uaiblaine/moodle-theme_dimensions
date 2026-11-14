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
 * Theme Dimensions - The course list of a user profile, without the courses the viewer may not discover
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use core_user\output\myprofile\tree;

/**
 * Decides what the "Course profiles" node of a user profile lists to the current viewer.
 *
 * Core builds that node (courseprofiles, in core_myprofile_navigation() of lib/myprofilelib.php) from
 * enrol_get_all_users_courses() of the profile's owner and withholds only a hidden course from a viewer
 * who may not see hidden courses. So it names, to anybody who may open the profile, a course
 * local_unlistedcourses would not list to them. The tree cannot be pruned and a node has no setter, so
 * the theme's profile renderer asks here what to print in the node's place.
 *
 * The list is built again from core's own query, with the plugin's listing predicate applied before
 * the "View more" cut, rather than by taking links out of core's HTML. Taking links out would leave
 * a list cut at $CFG->navcourselimit short by exactly the number of courses withheld, which is a count
 * of them, and would read the course id back from an address. The price is a copy of core's loop:
 * {@see replacement()} hands core's own content back whenever nothing is withheld, so the copy prints
 * only on a profile that names a withheld course, and the parity test in profilecourses_test fails
 * when core's loop and this one stop agreeing.
 *
 * The inputs (whose profile, inside which course) are the ones core hands every myprofile_navigation
 * callback; {@see record()} keeps them per tree, from the theme's callback in lib.php. A tree nobody
 * recorded is answered closed: its node is withheld, because nothing here can say whose courses it names.
 *
 * Without local_unlistedcourses' predicate class core's content stands (the per-viewer rules of this
 * theme fail open on a missing dependency, {@see \theme_dimensions\local\landing\redirector}).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profilecourses {
    /** @var string The node core lists the profile owner's courses in. */
    public const NODE = 'courseprofiles';

    /** @var string local_unlistedcourses' per-viewer predicate, asked by name. */
    public const PREDICATE = '\\local_unlistedcourses\\access';

    /** @var \WeakMap|null The inputs of each tree built in this request: tree => [userid, courseid or null]. */
    private static ?\WeakMap $inputs = null;

    /**
     * Keep what core handed the profile callbacks for this tree.
     *
     * Reads nothing: get_plugins_with_function() calls the theme's callback under any theme.
     *
     * @param tree $tree The tree being built.
     * @param \stdClass $user The user whose profile it is.
     * @param \stdClass|null $course The course the profile is shown inside, if any.
     * @return void
     */
    public static function record(tree $tree, \stdClass $user, ?\stdClass $course): void {
        self::$inputs ??= new \WeakMap();
        self::$inputs[$tree] = [(int) $user->id, empty($course->id) ? null : (int) $course->id];
    }

    /**
     * What to print in place of core's course list, if anything.
     *
     * @param tree|null $tree The tree the node belongs to; null when the node is rendered on its own.
     * @param string|null $predicate The predicate class; tests pass a nonexistent one to pin the fail-open answer.
     * @return array|null Null when core's content stands. Otherwise the context of
     *     theme_dimensions/core_user/myprofile_courses: 'courses' (name, url or false, dimmed) and
     *     'viewmore' (url or false). An empty 'courses' means the node must not print at all.
     */
    public static function replacement(?tree $tree, ?string $predicate = null): ?array {
        global $CFG;

        $predicate ??= self::PREDICATE;
        if (!class_exists($predicate)) {
            // Fail open: without the plugin no course is unlisted, and the page is core's.
            return null;
        }
        $inputs = ($tree !== null && self::$inputs !== null) ? (self::$inputs[$tree] ?? null) : null;
        if ($inputs === null) {
            return ['courses' => [], 'viewmore' => false];
        }
        [$userid, $courseid] = $inputs;

        // The query core's profile callback runs, with the arguments it passes.
        $mycourses = enrol_get_all_users_courses($userid, true, null);
        $listed = [];
        foreach ($mycourses as $mycourse) {
            if (!$mycourse->category) {
                continue;
            }
            \context_helper::preload_from_record($mycourse);
            // Core prints the page's own course without asking whether it is hidden.
            $iscurrent = ($courseid !== null && (int) $mycourse->id === $courseid);
            if (
                !$iscurrent
                && !$mycourse->visible
                && !has_capability('moodle/course:viewhiddencourses', \context_course::instance($mycourse->id))
            ) {
                continue;
            }
            $listed[(int) $mycourse->id] = $mycourse;
        }

        $discoverable = $predicate::filter_courses($listed);
        if (count($discoverable) === count($listed)) {
            return null;
        }

        $showall = optional_param('showallcourses', 0, PARAM_INT);
        $courses = [];
        $viewmore = false;
        $shown = 0;
        // Core's loop over what is left. Its cut comes after the Nth course whatever follows, and so does this one.
        foreach ($listed as $id => $mycourse) {
            if (!isset($discoverable[$id])) {
                continue;
            }
            $iscurrent = ($id === $courseid);
            $url = false;
            if (!$iscurrent) {
                $params = ['id' => $userid, 'course' => $id];
                if ($showall) {
                    $params['showallcourses'] = 1;
                }
                $url = (new \core\url('/user/view.php', $params))->out(false);
            }
            $courses[] = [
                'name' => \context_course::instance($id)->get_context_name(false),
                'url' => $url,
                'dimmed' => !$iscurrent && !$mycourse->visible,
            ];
            $shown++;
            if (!$showall && $shown == $CFG->navcourselimit) {
                $more = ($courseid !== null)
                    ? new \core\url('/user/view.php', ['id' => $userid, 'course' => $courseid, 'showallcourses' => 1])
                    : new \core\url('/user/profile.php', ['id' => $userid, 'showallcourses' => 1]);
                $viewmore = $more->out(false);
                break;
            }
        }

        return ['courses' => $courses, 'viewmore' => $viewmore];
    }
}
