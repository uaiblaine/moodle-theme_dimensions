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
 * Theme Dimensions - Who may reach another user's profile
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use context_course;
use context_helper;
use context_user;
use core_user;
use stdClass;

/**
 * Narrows Moodle's profile visibility to people the viewer actually shares a course with.
 *
 * Core already refuses most strangers: user_can_view_profile() (user/lib.php) ends by asking for
 * moodle/user:viewdetails in each of the subject's courses, a capability an unrelated viewer holds
 * in none of them. The gap is has_coursecontact_role() (lib/accesslib.php), a bare existence check
 * against {role_assignments} with no context, no course and no reference to the viewer: anyone
 * holding a $CFG->coursecontact role in any course has a profile every authenticated user can open
 * from /user/profile.php, which turns the teaching staff into a directory.
 *
 * This class only ever PREVENTs, never force-allows. Core runs the profile callbacks before
 * has_coursecontact_role(), and user_process_profile_callbacks() returns PREVENT at once whatever
 * any other plugin said, so the effective rule is core's own answer AND this one: the only
 * profiles that change are those core would have opened for a course contact role held somewhere
 * else. Core consults the callbacks only while $CFG->forceloginforprofiles is on (the default).
 *
 * The relationship that opens a profile is a shared course, tested with
 * moodle/course:viewparticipants rather than core's moodle/user:viewdetails, so that "may this
 * person see who else is in the room" and "may this person open their profile" have one answer:
 * a course that withholds its participant list withholds the profiles with it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profileaccess {
    /**
     * @var array Decisions already taken this request, keyed by viewer id, subject id and course id.
     */
    private static $memo = [];

    /**
     * Forget the decisions memoised for this request.
     *
     * For tests, which change enrolments and roles between assertions inside one request.
     *
     * @return void
     */
    public static function reset_caches(): void {
        self::$memo = [];
    }

    /**
     * Decide whether this viewer must be kept away from this profile.
     *
     * Memoised per request because the callback is not called once per page: core_user::search()
     * runs user_can_view_profile() on every row it is about to return, up to 100 of them
     * (lib/classes/user.php:392), and the message member-info service does the same over the ids
     * it was given (message/externallib.php:3436). Without the memo one message search would cost
     * one enrolment query per candidate.
     *
     * @param stdClass $user The user whose profile is being asked for.
     * @param stdClass|null $course The course whose page the request came from, if any.
     * @param mixed $usercontext The user context, as core passes it. Deliberately unused.
     * @return int One of the core_user::VIEWPROFILE_* constants.
     */
    public static function control_view_profile($user, $course = null, $usercontext = null): int {
        global $USER;

        // Nothing to decide before there is a session to decide about. Core has its own answers
        // for the logged-out and guest cases, and forceloginforprofiles is where they belong.
        if (!isloggedin() || isguestuser() || empty($user->id)) {
            return core_user::VIEWPROFILE_DO_NOT_PREVENT;
        }

        // Own profile. Core returns before ever calling this, but other callers of the callback
        // machinery need not, and a veto on one's own profile would be a bug worth not writing.
        if ((int) $USER->id === (int) $user->id) {
            return core_user::VIEWPROFILE_DO_NOT_PREVENT;
        }

        // The viewer is part of the key: $USER changes within one request whenever
        // \core\session\manager::set_user() runs (CLI scripts, adhoc tasks acting as somebody,
        // "log in as"), and a key without it would answer one viewer with another viewer's
        // decision. Pinned by test_the_memo_distinguishes_the_viewer().
        $key = (int) $USER->id . ':' . (int) $user->id . ':' . (int) ($course->id ?? 0);
        if (!array_key_exists($key, self::$memo)) {
            self::$memo[$key] = self::decide($user, $course);
        }

        return self::$memo[$key];
    }

    /**
     * The decision itself, without the memo.
     *
     * The order is the cost order: everything that needs no query runs before anything that does,
     * and the query that only applies to an in-course request runs before the one that always
     * applies.
     *
     * @param stdClass $user The user whose profile is being asked for.
     * @param stdClass|null $course The course whose page the request came from, if any.
     * @return int One of the core_user::VIEWPROFILE_* constants.
     */
    private static function decide($user, $course): int {
        // Staff escape first: a manager, a support role or anybody else holding
        // moodle/user:viewdetails or :viewalldetails at the subject's own user context is who core
        // means by "may see everyone", and is answered before any relationship is looked for.
        //
        // The site administrator needs no check of their own: has_capability() grants a site admin
        // every capability before it looks at any role (its doanything branch in
        // lib/accesslib.php), so the check below already answers for them.
        $usercontext = context_user::instance($user->id);
        if (
            has_capability('moodle/user:viewdetails', $usercontext)
            || has_capability('moodle/user:viewalldetails', $usercontext)
        ) {
            return core_user::VIEWPROFILE_DO_NOT_PREVENT;
        }

        // The course contact exception, kept but scoped. A teacher reached through the course
        // they teach - /user/view.php?id=..&course=.. - is being asked about in the one context
        // where being a course contact means something, so the profile opens. The same teacher
        // reached through /user/profile.php with no course, or through a course where they are
        // nobody in particular, is a stranger like anyone else.
        if ($course !== null && self::is_course_contact_of($user, $course)) {
            return core_user::VIEWPROFILE_DO_NOT_PREVENT;
        }

        // The relationship that actually opens a profile: a course both of them are in, where the
        // viewer is allowed to see who is in it.
        if (self::shares_a_course_with_viewer($user)) {
            return core_user::VIEWPROFILE_DO_NOT_PREVENT;
        }

        return core_user::VIEWPROFILE_PREVENT;
    }

    /**
     * Is this user a course contact of this course?
     *
     * Reads $CFG->coursecontact, the same setting has_coursecontact_role() reads, so the roles
     * that count here are the roles the site already calls course contacts. Roles assigned above
     * the course - at the category, or at the system - count too, which is how a coordinator who
     * teaches by category assignment keeps a reachable profile inside their own courses.
     *
     * @param stdClass $user The user whose profile is being asked for.
     * @param stdClass $course The course the request came from.
     * @return bool True when the user holds a course contact role in that course.
     */
    private static function is_course_contact_of($user, $course): bool {
        global $CFG;

        if (empty($CFG->coursecontact) || empty($course->id)) {
            return false;
        }

        $contactroles = array_filter(array_map('intval', explode(',', $CFG->coursecontact)));
        if (empty($contactroles)) {
            return false;
        }

        $coursecontext = context_course::instance($course->id, IGNORE_MISSING);
        if (!$coursecontext) {
            return false;
        }

        foreach (get_user_roles($coursecontext, $user->id, true) as $assignment) {
            if (in_array((int) $assignment->roleid, $contactroles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is there a course holding both of them, where the viewer may see the participants?
     *
     * Built the way core builds the same question in user_can_view_profile(): enumerate the
     * SUBJECT's courses and ask for a capability in each, rather than intersecting the two
     * enrolment sets. The difference matters - enrol_get_shared_courses() requires an active
     * enrolment row on both sides, so it cannot see a teacher who holds their course by role
     * assignment alone, and that teacher would lose the students they teach.
     *
     * Left as permissive as core's own leg on purpose. This class exists to close one specific
     * hole, and every extra strictness here would silently overrule a core decision that has
     * nothing to do with it.
     *
     * @param stdClass $user The user whose profile is being asked for.
     * @return bool True when such a course exists.
     */
    private static function shares_a_course_with_viewer($user): bool {
        $courses = enrol_get_all_users_courses($user->id);
        foreach ($courses as $course) {
            context_helper::preload_from_record($course);
            $coursecontext = context_course::instance($course->id, IGNORE_MISSING);
            if ($coursecontext && has_capability('moodle/course:viewparticipants', $coursecontext)) {
                return true;
            }
        }

        return false;
    }
}
