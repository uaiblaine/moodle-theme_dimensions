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
 * Core already refuses most strangers: \core\user::can_view_profile() ends by asking for
 * moodle/user:viewdetails in each of the subject's courses, a capability an unrelated viewer holds
 * in none of them. The gap is has_coursecontact_role() (lib/accesslib.php), a bare existence check
 * against {role_assignments} with no context, no course and no reference to the viewer: anyone
 * holding a $CFG->coursecontact role in any course has a profile every authenticated user can open
 * from /user/profile.php, which turns the teaching staff into a directory.
 *
 * This class only ever PREVENTs, never force-allows. Core runs the profile callbacks before
 * has_coursecontact_role(), and \core\user::process_profile_callbacks() returns PREVENT at once whatever
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
     * runs can_view_profile() on every row it reads, and core_message_external::get_member_info()
     * does the same over up to 100 ids. Without the memo one search would cost one enrolment query
     * per candidate.
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

        // Own profile. Core returns before calling this, but another caller of the callback
        // machinery need not.
        if ((int) $USER->id === (int) $user->id) {
            return core_user::VIEWPROFILE_DO_NOT_PREVENT;
        }

        // The viewer is part of the key: $USER changes within one request whenever
        // \core\session\manager::set_user() runs (CLI scripts, adhoc tasks acting as somebody,
        // "log in as"), and a key without it would answer one viewer with another viewer's
        // decision.
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
     * Built the way core builds the same question in \core\user::can_view_profile(): enumerate the
     * SUBJECT's courses and ask for a capability in each, rather than intersecting the two
     * enrolment sets. The difference matters - enrol_get_shared_courses() requires an active
     * enrolment row on both sides, so it cannot see a teacher who holds their course by role
     * assignment alone, and that teacher would lose the students they teach.
     *
     * No group check, unlike core's own leg: a "do not prevent" hands the decision back to core,
     * which applies its own checks (groups included) after the callbacks, while any extra strictness
     * here would overrule core decisions unrelated to the hole this class closes. The capability
     * tested is viewparticipants rather than core's viewdetails; see the class docblock.
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

    /**
     * The answer {@see control_view_profile()} gives the current user, for many users and one course at once.
     *
     * For a caller judging a page of people, such as enrol_apply's approval queue: core's callback
     * contract is one user at a time, and through it this rule costs about three statements per
     * person. Here it is three statements for the whole list: the user contexts, the course contact
     * roles (only with a course and $CFG->coursecontact set) and every subject's courses with their
     * contexts. The capability checks read the viewer's access data, which the request already holds.
     *
     * It takes the decision's steps in its order and must stay clause for clause with them
     * ({@see decide()}, {@see is_course_contact_of()}, {@see shares_a_course_with_viewer()});
     * profileaccess_many_test compares the two answers over a fixture that holds both of them.
     * It neither reads nor fills the per-request memo, so a wrong answer here cannot reach the callback.
     *
     * One deliberate difference: an id no account has (so no user context; delete_user() keeps
     * the context of a deleted account) is answered PREVENT, where the callback throws on
     * context_user::instance().
     *
     * Meant for a page of people, not a population: get_in_or_equal() never chunks, and the
     * context cache holds a few thousand entries.
     *
     * @param array $userids The ids of the users whose profiles are asked for (integers or numeric strings).
     * @param stdClass|null $course The course whose page the request came from, if any; only id is read.
     * @return array User id => one of the core_user::VIEWPROFILE_* constants, for every id given.
     */
    public static function control_view_profile_many(array $userids, ?stdClass $course = null): array {
        global $DB, $USER;

        $answers = [];
        $pending = [];
        foreach ($userids as $id) {
            $id = (int) $id;
            $answers[$id] = core_user::VIEWPROFILE_DO_NOT_PREVENT;
            if (!empty($id)) {
                $pending[$id] = $id;
            }
        }

        // The callback's first two returns: no session to decide about, and the own profile.
        if (!isloggedin() || isguestuser()) {
            return $answers;
        }
        unset($pending[(int) $USER->id]);
        if (!$pending) {
            return $answers;
        }

        // Statement one: the subjects' user contexts, preloaded so context_user::instance() reads none.
        [$insql, $params] = $DB->get_in_or_equal(array_values($pending), SQL_PARAMS_NAMED, 'pau');
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $records = $DB->get_records_sql(
            "SELECT ctx.instanceid AS subjectid, $ctxselect
               FROM {context} ctx
              WHERE ctx.contextlevel = :userlevel AND ctx.instanceid $insql",
            $params + ['userlevel' => CONTEXT_USER]
        );
        $withcontext = [];
        foreach ($records as $record) {
            $withcontext[(int) $record->subjectid] = true;
            context_helper::preload_from_record($record);
        }

        // The staff escape, decide()'s first step.
        foreach ($pending as $id) {
            if (empty($withcontext[$id])) {
                $answers[$id] = core_user::VIEWPROFILE_PREVENT;
                unset($pending[$id]);
                continue;
            }
            $subjectcontext = context_user::instance($id);
            if (
                has_capability('moodle/user:viewdetails', $subjectcontext)
                || has_capability('moodle/user:viewalldetails', $subjectcontext)
            ) {
                unset($pending[$id]);
            }
        }

        // Statement two: the course contacts of the course asked about, is_course_contact_of() for the list.
        if ($pending && $course !== null) {
            foreach (self::course_contacts_among($pending, $course) as $id) {
                unset($pending[$id]);
            }
        }

        // Statement three: shares_a_course_with_viewer() for the list.
        if ($pending) {
            foreach (self::sharing_a_course_with_viewer($pending) as $id) {
                unset($pending[$id]);
            }
        }

        foreach ($pending as $id) {
            $answers[$id] = core_user::VIEWPROFILE_PREVENT;
        }

        return $answers;
    }

    /**
     * Which of these users hold a course contact role in this course: {@see is_course_contact_of()} for a list.
     *
     * Reads what get_user_roles($coursecontext, $userid, true) reads for each of them, the course
     * context and its parents, joined on {role} as it joins.
     *
     * @param array $userids User ids, as keys and values.
     * @param stdClass $course The course the request came from.
     * @return array The ids among them who are a course contact there.
     */
    private static function course_contacts_among(array $userids, stdClass $course): array {
        global $CFG, $DB;

        if (empty($CFG->coursecontact) || empty($course->id)) {
            return [];
        }
        $contactroles = array_filter(array_map('intval', explode(',', $CFG->coursecontact)));
        if (empty($contactroles)) {
            return [];
        }
        $coursecontext = context_course::instance($course->id, IGNORE_MISSING);
        if (!$coursecontext) {
            return [];
        }

        $contextids = $coursecontext->get_parent_context_ids();
        $contextids[] = $coursecontext->id;
        [$usersql, $params] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED, 'pcu');
        [$ctxsql, $ctxparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'pcc');
        [$rolesql, $roleparams] = $DB->get_in_or_equal(array_values($contactroles), SQL_PARAMS_NAMED, 'pcr');

        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT ra.userid
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid $usersql AND ra.contextid $ctxsql AND ra.roleid $rolesql",
            $params + $ctxparams + $roleparams
        ));
    }

    /**
     * Which of these users share a course with the viewer where the viewer may see the participants.
     *
     * {@see shares_a_course_with_viewer()} for a list: the courses are enrol_get_all_users_courses()'s
     * for each of them (any enrolment, any status, any method, the site course left out, nothing for
     * the guest account), read in one statement with their contexts, and the capability is asked once
     * per distinct course.
     *
     * @param array $userids User ids, as keys and values.
     * @return array The ids among them who share such a course with the viewer.
     */
    private static function sharing_a_course_with_viewer(array $userids): array {
        global $CFG, $DB;

        // The guest account: enrol_get_all_users_courses() answers it no course.
        unset($userids[(int) ($CFG->siteguest ?? 0)]);
        if (!$userids) {
            return [];
        }

        [$usersql, $params] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED, 'psu');
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $rs = $DB->get_recordset_sql(
            "SELECT DISTINCT en.userid AS subjectid, c.id AS courseid, $ctxselect
               FROM {course} c
               JOIN (SELECT DISTINCT ue.userid, e.courseid
                       FROM {enrol} e
                       JOIN {user_enrolments} ue ON ue.enrolid = e.id
                      WHERE ue.userid $usersql
                    ) en ON en.courseid = c.id
          LEFT JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :courselevel
              WHERE c.id <> :siteid",
            $params + ['courselevel' => CONTEXT_COURSE, 'siteid' => SITEID]
        );

        $viewable = [];
        $sharing = [];
        foreach ($rs as $record) {
            $courseid = (int) $record->courseid;
            $subjectid = (int) $record->subjectid;
            if (!array_key_exists($courseid, $viewable)) {
                context_helper::preload_from_record($record);
                $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
                $viewable[$courseid] = $coursecontext
                    && has_capability('moodle/course:viewparticipants', $coursecontext);
            }
            if ($viewable[$courseid]) {
                $sharing[$subjectid] = $subjectid;
            }
        }
        $rs->close();

        return array_values($sharing);
    }
}
