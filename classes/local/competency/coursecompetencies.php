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
 * Theme Dimensions - What the course competencies page adds to core's
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use core_competency\course_module_competency;
use core_competency\user_competency;

/**
 * The extras of the course competencies page (admin/tool/lp/coursecompetencies.php), for the first paint and the web
 * service alike.
 *
 * Both doors go through {@see for_viewer()}: the first paint
 * ({@see \theme_dimensions\output\tool_lp_renderer::render_course_competencies_page()}, through {@see decorate()}) and
 * the refill after each of core's re-renders ({@see \theme_dimensions\external\get_course_competency_extras}). Who sees
 * which extra is decided here, from facts recomputed on the server for the session user:
 *
 * - every viewer: each linked activity's own outcome (`competency_modulecomp.ruleoutcome`), for the activities core's
 *   page lists to that viewer ({@see \core_competency\api::list_course_modules_using_competency()} keeps the ones
 *   `cm_info::uservisible` admits; so does {@see outcomes()}), one statement for the course;
 * - a viewer who may see the whole class ({@see sees_class()}: {@see MANAGE} in the course, or {@see GRADE} where the
 *   course is not in separate groups mode or the viewer holds `moodle/site:accessallgroups`): the class distribution
 *   per competency, {@see courseclass::counts()}, counts only; a grader limited to their groups gets none;
 * - a learner, the session user when core's page treats them as gradable (`is_enrolled()` with {@see GRADABLE}, core's
 *   `$gradable`) and may read their own course competencies (`user_competency::can_read_user_in_course()`, the rule
 *   `api::list_user_competencies_in_course()` applies before core's page inserts its rows): the state of each listed
 *   activity for them ({@see activities()}) and their evidence count and latest date per competency in this course
 *   ({@see evidence()}), never anybody else's.
 *
 * Nothing here calls `api::list_user_competencies_in_course()`, which inserts a `competency_usercompcourse` row per
 * missing competency on a read: the learner's own rows are core's page's business, and the class figures read the
 * table as it is. The site course gets no extras: core never links to this page there, and the enrolled join treats
 * every user as enrolled on it.
 *
 * Every text is plain, built here once (counts, dates, core's outcome names), so the first paint and the web service
 * carry the same strings and each template escapes them once.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coursecompetencies {
    /** @var string Core's capability of a learner whose competencies are rated in a course (its statistics and grading). */
    public const GRADABLE = 'moodle/competency:coursecompetencygradable';

    /** @var string Core's capability behind the page's `cangradecompetencies`. */
    public const GRADE = 'moodle/competency:competencygrade';

    /** @var string Core's capability behind the page's `canmanagecoursecompetencies`. */
    public const MANAGE = 'moodle/competency:coursecompetencymanage';

    /** @var array The status label tone of each activity outcome, outcome => tone (the dim-comp-pill modifiers). */
    public const TONES = [
        course_module_competency::OUTCOME_NONE => 'neutral',
        course_module_competency::OUTCOME_EVIDENCE => 'rated',
        course_module_competency::OUTCOME_RECOMMEND => 'status',
        course_module_competency::OUTCOME_COMPLETE => 'proficient',
    ];

    /** @var string An activity the learner completed. */
    public const STATE_DONE = 'done';

    /** @var string An activity with completion tracking the learner has not completed. */
    public const STATE_TODO = 'todo';

    /** @var string An activity without completion tracking: no state is shown. */
    public const STATE_NONE = 'none';

    /**
     * Whether the viewer may be shown the class distribution: the figures of the whole enrolled class.
     *
     * The figures are course-wide, so they go only to a viewer who may see the whole class: whoever holds
     * {@see MANAGE} in the course, or {@see GRADE} there and either a course whose effective group mode is not
     * separate groups or `moodle/site:accessallgroups`. A grader limited to their own groups sees only those groups'
     * learners everywhere else in Moodle, and a count over the whole class (small, in a small class) would name a
     * person outside them; such a grader gets no figures at all, not a partial one. Core's page shows no counts.
     *
     * Activity-level group restrictions are not read: the figures are per competency, over the enrolled set.
     *
     * @param \stdClass $course The course, as the database holds it (its `groupmode` is read).
     * @param \core\context\course $context The course's context.
     * @return bool
     */
    public static function sees_class(\stdClass $course, \core\context\course $context): bool {
        if (has_capability(self::MANAGE, $context)) {
            return true;
        }
        if (!has_capability(self::GRADE, $context)) {
            return false;
        }
        return (int) groups_get_course_groupmode($course) !== SEPARATEGROUPS
            || has_capability('moodle/site:accessallgroups', $context);
    }

    /**
     * Whether core's page treats the session user as a learner rated in the course, who may read their own figures.
     *
     * @param \core\context\course $context The course's context.
     * @return bool
     */
    public static function is_learner(\core\context\course $context): bool {
        global $USER;
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        return is_enrolled($context, $USER, self::GRADABLE)
            && user_competency::can_read_user_in_course((int) $USER->id, (int) $context->instanceid);
    }

    /**
     * The extras of the page for the session user.
     *
     * @param \stdClass $course The course.
     * @param int[] $competencyids The competencies to answer for: the page's, in its order.
     * @return array ['staff' => bool, 'learner' => bool, 'competencies' => list of the entries
     *     {@see entry()} builds, in the order given].
     */
    public static function for_viewer(\stdClass $course, array $competencyids): array {
        global $USER;

        $courseid = (int) $course->id;
        $competencyids = array_values(array_unique(array_map('intval', $competencyids)));
        $result = ['staff' => false, 'learner' => false, 'competencies' => []];
        if ($courseid === SITEID || $competencyids === []) {
            return $result;
        }

        $context = \core\context\course::instance($courseid);
        $staff = self::sees_class($course, $context);
        $learner = self::is_learner($context);
        $outcomes = self::outcomes($course);
        $counts = $staff ? courseclass::counts($courseid) : [];
        $evidence = $learner ? self::evidence($courseid, (int) $USER->id, $competencyids) : [];
        $cmids = [];
        foreach ($outcomes as $byactivity) {
            $cmids = array_merge($cmids, array_keys($byactivity));
        }
        $states = $learner ? self::activities($course, array_values(array_unique($cmids))) : [];

        foreach ($competencyids as $competencyid) {
            $result['competencies'][] = self::entry(
                $competencyid,
                $staff ? ($counts[$competencyid] ?? ['learners' => self::learners($counts), 'rated' => 0, 'proficient' => 0])
                    : null,
                $learner ? ($evidence[$competencyid] ?? ['count' => 0, 'latest' => 0]) : null,
                $outcomes[$competencyid] ?? [],
                $states
            );
        }
        $result['staff'] = $staff;
        $result['learner'] = $learner;

        return $result;
    }

    /**
     * One competency's entry: the context of templates/competency_course_extra.mustache, and its activities, each the
     * context of templates/competency_course_cm.mustache.
     *
     * @param int $competencyid The competency.
     * @param array|null $counts learners, rated and proficient, or null when the viewer is not staff.
     * @param array|null $evidence count and latest, or null when the viewer is not a learner of the course.
     * @param array $outcomes The competency's listed activities, cmid => outcome.
     * @param array $states The learner's state per activity, cmid => [state, time]; empty for anybody else.
     * @return array
     */
    private static function entry(int $competencyid, ?array $counts, ?array $evidence, array $outcomes, array $states): array {
        $entry = [
            'competencyid' => $competencyid,
            'hasdistribution' => $counts !== null,
            'haslearners' => false,
            'progresstextvalue' => '',
            'percentagevalue' => 0,
            'percentlabelvalue' => '',
            'ratedtext' => '',
            'notratedtext' => '',
            'haslearner' => $evidence !== null,
            'evidencetext' => '',
            'activities' => [],
        ];
        if ($counts !== null) {
            $learners = max(0, (int) $counts['learners']);
            $proficient = min($learners, max(0, (int) $counts['proficient']));
            $rated = min($learners, max($proficient, (int) $counts['rated']));
            $percent = $learners > 0 ? (int) round(100 * $proficient / $learners) : 0;
            $entry['haslearners'] = $learners > 0;
            $entry['percentagevalue'] = max(0, min(100, $percent));
            $entry['percentlabelvalue'] = get_string('percents', 'moodle', $entry['percentagevalue']);
            $entry['progresstextvalue'] = get_string(
                'competency_courseclassproficient',
                'theme_dimensions',
                (object) ['proficient' => $proficient, 'learners' => $learners]
            );
            $entry['ratedtext'] = get_string('competency_courseclassrated', 'theme_dimensions', $rated - $proficient);
            $entry['notratedtext'] = get_string('competency_courseclassnotrated', 'theme_dimensions', $learners - $rated);
        }
        if ($evidence !== null) {
            $entry['evidencetext'] = self::evidence_text((int) $evidence['count'], (int) $evidence['latest']);
        }
        foreach ($outcomes as $cmid => $outcome) {
            $entry['activities'][] = self::activity((int) $cmid, (int) $outcome, $states[$cmid] ?? null);
        }

        return $entry;
    }

    /**
     * One activity's entry: its outcome as core names it, and the learner's state when there is one.
     *
     * @param int $cmid The course module.
     * @param int $outcome Its outcome for the competency, a course_module_competency::OUTCOME_* constant.
     * @param array|null $state [state, time] for a learner, null otherwise.
     * @return array
     */
    private static function activity(int $cmid, int $outcome, ?array $state): array {
        $tone = self::TONES[$outcome] ?? self::TONES[course_module_competency::OUTCOME_NONE];
        $name = isset(self::TONES[$outcome]) ? (string) course_module_competency::get_ruleoutcome_name($outcome) : '';
        $entry = [
            'cmid' => $cmid,
            'outcome' => $outcome,
            'outcomename' => trim(clean_param($name, PARAM_TEXT)),
            'outcometone' => $tone,
            'hasstate' => false,
            'isdone' => false,
            'istodo' => false,
            'statetext' => '',
        ];
        if ($state !== null && $state[0] !== self::STATE_NONE) {
            $entry['hasstate'] = true;
            $entry['isdone'] = $state[0] === self::STATE_DONE;
            $entry['istodo'] = $state[0] === self::STATE_TODO;
            $entry['statetext'] = $entry['isdone']
                ? get_string('competency_coursecmdone', 'theme_dimensions', self::date((int) $state[1]))
                : get_string('competency_coursecmtodo', 'theme_dimensions');
        }

        return $entry;
    }

    /**
     * The learner's evidence line.
     *
     * @param int $count The evidence in this course for the competency.
     * @param int $latest The latest one's time, 0 for none.
     * @return string Plain text.
     */
    private static function evidence_text(int $count, int $latest): string {
        if ($count <= 0) {
            return get_string('competency_courseevidencenone', 'theme_dimensions');
        }
        if ($count === 1) {
            return get_string('competency_courseevidenceone', 'theme_dimensions', self::date($latest));
        }
        return get_string(
            'competency_courseevidencemany',
            'theme_dimensions',
            (object) ['count' => $count, 'date' => self::date($latest)]
        );
    }

    /**
     * A day in the reader's language and time zone, plain.
     *
     * @param int $time The time.
     * @return string
     */
    private static function date(int $time): string {
        return userdate($time, get_string('strftimedate', 'langconfig'));
    }

    /**
     * The learners counted when a competency has no row of its own in the aggregate: every competency's row counts the
     * same enrolled set, so any row says how many there are; none means there are none.
     *
     * @param array $counts What {@see courseclass::counts()} returned.
     * @return int
     */
    private static function learners(array $counts): int {
        $first = reset($counts);
        return $first ? (int) $first['learners'] : 0;
    }

    /**
     * The outcome of each linked activity the viewer may see, per competency: one statement for the course.
     *
     * An activity is listed under the same rule core's page lists it (`cm_info::uservisible`, which
     * `api::validate_course_module()` reads), so the extras never name an activity id core's page withholds.
     *
     * @param \stdClass $course The course.
     * @return array competency id => [cmid => outcome].
     */
    public static function outcomes(\stdClass $course): array {
        global $DB;

        $sql = "SELECT mc.id, mc.cmid, mc.competencyid, mc.ruleoutcome
                  FROM {competency_modulecomp} mc
                  JOIN {course_modules} cm ON cm.id = mc.cmid
                 WHERE cm.course = :courseid";
        $rows = $DB->get_records_sql($sql, ['courseid' => (int) $course->id]);
        if (!$rows) {
            return [];
        }
        $modinfo = get_fast_modinfo($course);
        $outcomes = [];
        foreach ($rows as $row) {
            $cm = $modinfo->cms[(int) $row->cmid] ?? null;
            if (!$cm || !$cm->uservisible) {
                continue;
            }
            $outcomes[(int) $row->competencyid][(int) $row->cmid] = (int) $row->ruleoutcome;
        }

        return $outcomes;
    }

    /**
     * The session user's state of each activity, from completion_info for the course once.
     *
     * Only activities {@see outcomes()} listed reach here, so each is one the user may see.
     *
     * @param \stdClass $course The course.
     * @param int[] $cmids The activities.
     * @return array cmid => [state, time]: {@see STATE_DONE} with the completion's time, {@see STATE_TODO} or
     *     {@see STATE_NONE} with 0.
     */
    public static function activities(\stdClass $course, array $cmids): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        if ($cmids === []) {
            return [];
        }
        $modinfo = get_fast_modinfo($course);
        $completion = new \completion_info($course);
        $states = [];
        foreach ($cmids as $cmid) {
            $cm = $modinfo->cms[(int) $cmid] ?? null;
            if (!$cm || !$cm->uservisible) {
                continue;
            }
            if (!$completion->is_enabled($cm)) {
                $states[(int) $cmid] = [self::STATE_NONE, 0];
                continue;
            }
            $data = $completion->get_data($cm, true);
            $done = in_array((int) $data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
            $states[(int) $cmid] = $done ? [self::STATE_DONE, (int) $data->timemodified] : [self::STATE_TODO, 0];
        }

        return $states;
    }

    /**
     * A learner's evidence in this course per competency: how many, and the latest one's time. One grouped statement.
     *
     * Evidence "in this course" is evidence recorded in the course's context or one of its activities' (a rating in
     * the course, a completed activity or the course's completion): `competency_evidence.contextid` under the course's
     * context path. The caller passes the session user's id only.
     *
     * @param int $courseid The course.
     * @param int $userid The learner: the session user.
     * @param int[] $competencyids The competencies.
     * @return array competency id => ['count' => int, 'latest' => int].
     */
    public static function evidence(int $courseid, int $userid, array $competencyids): array {
        global $DB;

        if ($competencyids === []) {
            return [];
        }
        $context = \core\context\course::instance($courseid);
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $competencyids), SQL_PARAMS_NAMED, 'comp');
        $pathlike = $DB->sql_like('ctx.path', ':pathprefix', false, false);
        $sql = "SELECT uc.competencyid, COUNT(e.id) AS evidencecount, MAX(e.timecreated) AS latest
                  FROM {competency_usercomp} uc
                  JOIN {competency_evidence} e ON e.usercompetencyid = uc.id
                  JOIN {context} ctx ON ctx.id = e.contextid
                 WHERE uc.userid = :userid
                   AND uc.competencyid {$insql}
                   AND (ctx.id = :coursecontextid OR {$pathlike})
              GROUP BY uc.competencyid";
        $params += [
            'userid' => $userid,
            'coursecontextid' => (int) $context->id,
            'pathprefix' => $context->path . '/%',
        ];
        $evidence = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $evidence[(int) $row->competencyid] = ['count' => (int) $row->evidencecount, 'latest' => (int) $row->latest];
        }

        return $evidence;
    }

    /**
     * The class distribution of a course: how many enrolled learners are proficient and rated, per competency. ONE
     * statement; {@see courseclass::counts()} keeps its answer.
     *
     * The learners are the users enrolled in the course with core's {@see GRADABLE} capability there, through
     * `get_enrolled_with_capabilities_join()` (the set core's report_competency and its grading read through
     * `get_enrolled_users()` and `is_enrolled()`), suspended enrolments included as core includes them (its default
     * `$onlyactive = false`). A `competency_usercompcourse` row of anybody else, a user no longer enrolled among them,
     * is not counted, where core's own statistics count every row of the course. A user enrolled twice is one learner
     * (COUNT DISTINCT). A rated learner is one whose row has a grade; a proficient one has a grade and proficiency.
     *
     * The site course is refused: every user is enrolled on it (`get_enrolled_join()` skips the enrolment tables there).
     *
     * @param int $courseid The course, never the site course.
     * @return array competency id => ['learners' => int, 'rated' => int, 'proficient' => int], one entry per course
     *     competency while the course has a learner, none otherwise. Counts only: no user id leaves this method.
     * @throws \coding_exception For the site course.
     */
    public static function aggregate(int $courseid): array {
        global $DB;

        if ($courseid === SITEID) {
            throw new \coding_exception('The site course has no class distribution.');
        }
        $context = \core\context\course::instance($courseid);
        $enrolled = get_enrolled_with_capabilities_join($context, '', self::GRADABLE);
        if ($enrolled->cannotmatchanyrows) {
            return [];
        }
        $sql = "SELECT cc.competencyid,
                       COUNT(DISTINCT u.id) AS learners,
                       COUNT(DISTINCT CASE WHEN ucc.grade IS NOT NULL THEN u.id END) AS rated,
                       COUNT(DISTINCT CASE WHEN ucc.grade IS NOT NULL AND ucc.proficiency = 1 THEN u.id END) AS proficient
                  FROM {user} u
                       {$enrolled->joins}
                  JOIN {competency_coursecomp} cc ON cc.courseid = :courseid
             LEFT JOIN {competency_usercompcourse} ucc
                       ON ucc.userid = u.id AND ucc.courseid = cc.courseid AND ucc.competencyid = cc.competencyid
                 WHERE {$enrolled->wheres}
              GROUP BY cc.competencyid";
        $params = $enrolled->params + ['courseid' => $courseid];
        $counts = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $counts[(int) $row->competencyid] = [
                'learners' => (int) $row->learners,
                'rated' => (int) $row->rated,
                'proficient' => (int) $row->proficient,
            ];
        }

        return $counts;
    }

    /**
     * Write the first paint's extras onto core's exported page.
     *
     * Only keys of the theme's own are written (`dimfirstpaint`, per competency `dimextra`, per listed activity
     * `dimcm`); core's keys are left as they were, so the template still renders from core's context alone when the
     * client re-renders it without them.
     *
     * @param \stdClass $data What {@see \tool_lp\output\course_competencies_page::export_for_template()} returned.
     * @param array $extras What {@see for_viewer()} returned.
     * @return \stdClass The same object, decorated.
     */
    public static function decorate(\stdClass $data, array $extras): \stdClass {
        $data->dimfirstpaint = true;
        $byid = [];
        foreach ($extras['competencies'] as $entry) {
            $byid[(int) $entry['competencyid']] = $entry;
        }
        foreach ($data->competencies as $index => $row) {
            $competencyid = (int) $row['competency']->id;
            $entry = $byid[$competencyid] ?? null;
            if ($entry === null) {
                continue;
            }
            $activities = array_column($entry['activities'], null, 'cmid');
            unset($entry['activities']);
            $row['dimextra'] = $entry;
            foreach ($row['coursemodules'] as $module) {
                $module->dimcm = $activities[(int) $module->id] ?? null;
            }
            $data->competencies[$index] = $row;
        }

        return $data;
    }
}
