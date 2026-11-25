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
 * Theme Dimensions - The rules of the class report of competency ratings
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use context;
use context_course;
use core_competency\api;
use core_reportbuilder\local\helpers\database;
use report_competency\output\report;

/**
 * Who may read the class report, which learners it lists, and which competencies it shows as columns.
 *
 * The class report replaces core's one-learner breakdown (report/competency/index.php without a `user` parameter)
 * with one row per learner and one column per course competency
 * ({@see \theme_dimensions\reportbuilder\local\systemreports\class_ratings}). Every rule it applies lives here, so
 * the page, the table's own requests and the download read the same answer:
 *
 * - {@see may_view()} repeats core's check, which lived in core's export
 *   (`api::list_user_competencies_in_course()`), the call the class mode no longer makes;
 * - {@see groups()} is the group rule, stricter than core's page on purpose;
 * - {@see learners()} is the base condition built from both.
 *
 * Nothing here writes: core's export inserts a `competency_usercompcourse` row per missing competency on a read,
 * and the class report reads those rows in SQL instead.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class classreport {
    /**
     * The most competency columns one request builds, in the course's order.
     *
     * Each column is two correlated subqueries per row read, and core's table computes every row of the report
     * before it pages. Past the cap the page says how many it shows; a learner's breakdown lists every competency,
     * and the activity filter keeps one activity's competencies.
     */
    public const MAX_COLUMNS = 30;

    /** @var string The parameter of report/competency/index.php naming the learner of core's breakdown. */
    public const PARAM_USER = 'user';

    /** @var string Who is a row: core's capability for a learner rated in a course, as core's page lists them. */
    public const GRADABLE = 'moodle/competency:coursecompetencygradable';

    /** @var string Who may rate in the course, and so gets the grading dialogue's hooks on a cell. */
    public const GRADE = 'moodle/competency:competencygrade';

    /** @var string Who may read the user competencies of the class as a whole, asked at the course context. */
    public const USERVIEW = 'moodle/competency:usercompetencyview';

    /** @var string[] Either of them lets the viewer read the course's competencies, as core asks. */
    public const COURSEVIEW = ['moodle/competency:coursecompetencyview', 'moodle/competency:coursecompetencymanage'];

    /** @var int A search shorter than this many characters searches nothing. */
    public const SEARCH_MIN = 2;

    /** @var int The longest search kept, in characters. */
    public const SEARCH_MAX = 100;

    /**
     * Whether the request asks for the class rather than one learner: report/competency/index.php without a
     * positive `user` parameter.
     *
     * Read from the request, not from the page's URL: index.php fills its URL's `user` with the first gradable
     * learner when none was asked for, so the URL never tells the two modes apart.
     *
     * @return bool True when no learner is named.
     */
    public static function requested(): bool {
        return optional_param(self::PARAM_USER, 0, PARAM_INT) <= 0;
    }

    /**
     * Whether the current user may read the class report of this context.
     *
     * Core's page relies on the check of its export (`api::list_user_competencies_in_course()`): one of
     * {@see COURSEVIEW} in the course, and `user_competency::can_read_user_in_course()` for the learner shown. For
     * a whole class the second is asked once, as its course-level half: {@see USERVIEW} in the course context. A
     * viewer who reads learners only through per-user grants (a parent, a mentor, a plan reviewer) does not pass
     * and keeps core's one-learner page. Self-sufficient, because core's download.php calls only the site-level
     * require_login() before the report's can_view(): the course gate is `can_access_course()` with only active
     * enrolments, as require_login($course) would answer. The site course is no class: every user is enrolled there,
     * so it is refused before anything is read.
     *
     * @param context $context The report's context.
     * @return bool True when the class report may be shown.
     */
    public static function may_view(context $context): bool {
        if (!$context instanceof context_course || !api::is_enabled()) {
            return false;
        }
        if ((int) $context->instanceid === (int) SITEID) {
            return false;
        }
        if (!can_access_course(get_course((int) $context->instanceid), null, '', true)) {
            return false;
        }
        if (!has_any_capability(self::COURSEVIEW, $context)) {
            return false;
        }

        return has_capability(self::USERVIEW, $context);
    }

    /**
     * The groups the current user's class report is narrowed to.
     *
     * - A course without groups: everybody (null).
     * - Visible groups, or a viewer holding `moodle/site:accessallgroups` in the course: the group chosen in the
     *   page's group selector, or everybody when "All participants" is chosen (null).
     * - Separate groups, without `accessallgroups`: the chosen group when it is one of the viewer's own groups, or
     *   all of the viewer's own groups; a viewer in NO group gets no group at all ([]), so no row.
     *
     * The last case is stricter than core's page on purpose: core's active group is 0 for such a viewer, and 0
     * means everybody to core's enrolled-users query. Decided here, before any join is built, because an empty
     * list handed to `get_enrolled_with_capabilities_join()` also means every group.
     *
     * The choice is read from the session (`groups_get_course_group()` without update): the page's selector writes
     * it in index.php, and the table's own requests and the download, which carry no `group` parameter, read it.
     * A report built with the group parameter (`class_ratings::PARAM_GROUP`, which the class report's web services
     * pass) hands it here in place of the session's choice, and it goes through the same rule: a client posts it, so
     * it can only choose among what the rule already admits. The viewer's groups, and the
     * groups a viewer who reads every group may choose, are those of the course's default grouping, as core's selector
     * offers them; any other group lists nobody.
     *
     * @param \stdClass $course The course.
     * @param int|null $requested A group a client asked for (0 for all participants), or null for the session's choice.
     * @return array|null Group ids; null for no narrowing; an empty array for no row.
     */
    public static function groups(\stdClass $course, ?int $requested = null): ?array {
        global $USER;

        $mode = (int) groups_get_course_groupmode($course);
        if ($mode === NOGROUPS) {
            return null;
        }
        $chosen = $requested ?? (int) groups_get_course_group($course, false);
        $context = context_course::instance((int) $course->id);
        if ($mode !== SEPARATEGROUPS || has_capability('moodle/site:accessallgroups', $context)) {
            if ($chosen <= 0) {
                return null;
            }
            $offered = array_map('intval', array_keys(
                groups_get_all_groups((int) $course->id, 0, (int) $course->defaultgroupingid, 'g.id')
            ));

            return in_array($chosen, $offered, true) ? [$chosen] : [];
        }

        $own = array_map('intval', array_keys(
            groups_get_all_groups((int) $course->id, (int) $USER->id, (int) $course->defaultgroupingid, 'g.id')
        ));
        if (!$own) {
            return [];
        }
        if ($chosen > 0) {
            return in_array($chosen, $own, true) ? [$chosen] : [];
        }

        return $own;
    }

    /**
     * The base condition of the class report: the user is a gradable learner of the course, in the viewer's groups.
     *
     * Core's enrolled-with-capability join ({@see GRADABLE}) as a subquery, so a learner enrolled by two methods is
     * still one row; active enrolments only where the viewer reads them so, as core's navigation of the breakdown
     * lists its learners (the grade report's `showonlyactiveenrol`, or a viewer without
     * `moodle/course:viewsuspendedusers`). Core's parameter names are renamed to Report Builder's, which a base
     * condition requires, each once per statement.
     *
     * @param context_course $context The course's context.
     * @param string $useridsql The SQL of the report's user id, the main table's `id`.
     * @param int|null $requested A group a client asked for, or null for the session's choice ({@see groups()}).
     * @return array [the SQL condition, its named parameters].
     */
    public static function learners(context_course $context, string $useridsql, ?int $requested = null): array {
        global $CFG;

        $groups = self::groups(get_course((int) $context->instanceid), $requested);
        if ($groups === []) {
            return ['1 = 0', []];
        }

        $default = !empty($CFG->grade_report_showonlyactiveenrol);
        $onlyactive = get_user_preferences('grade_report_showonlyactiveenrol', $default)
            || !has_capability('moodle/course:viewsuspendedusers', $context);
        $join = get_enrolled_with_capabilities_join($context, 'dimcr', self::GRADABLE, $groups ?? 0, (bool) $onlyactive);
        if ($join->cannotmatchanyrows) {
            return ['1 = 0', []];
        }

        $names = [];
        $rename = static function (string $name) use (&$names): string {
            if (!isset($names[$name])) {
                $names[$name] = database::generate_param_name();
            }
            return $names[$name];
        };
        [$sql, $params] = database::sql_replace_parameters(
            "SELECT dimcru.id FROM {user} dimcru {$join->joins} WHERE {$join->wheres}",
            $join->params,
            $rename
        );

        return ["{$useridsql} IN ({$sql})", $params];
    }

    /**
     * The ids of the learners the class report lists to the current user: the report's own rule, as a list.
     *
     * One statement over {@see learners()}, so the selector of learners beside the report names exactly the rows the
     * report would show. A viewer the group rule leaves no row gets an empty list.
     *
     * @param context_course $context The course's context.
     * @return int[] User ids.
     */
    public static function learner_ids(context_course $context): array {
        global $DB;

        [$where, $params] = self::learners($context, 'dimlu.id');

        return array_map('intval', $DB->get_fieldset_sql("SELECT dimlu.id FROM {user} dimlu WHERE {$where}", $params));
    }

    /**
     * The competencies the class report shows as columns: the viewer's choice, else the first {@see MAX_COLUMNS}.
     *
     * The choice is a list of competency ids ({@see classreportpreference}); only those of the catalogue are kept, in
     * the order chosen, at most {@see MAX_COLUMNS}, so a forged id, one of another course or one the course no
     * longer has matches nothing. A choice of which nothing is left reads as no choice.
     *
     * @param array $catalogue Competency id => record, as {@see catalogue()} returns them.
     * @param array|null $chosen The chosen competency ids, or null for no choice.
     * @return array Competency id => record, in display order.
     */
    public static function chosen(array $catalogue, ?array $chosen): array {
        $shown = [];
        foreach ($chosen ?? [] as $id) {
            if (isset($catalogue[$id]) && !isset($shown[$id])) {
                $shown[$id] = $catalogue[$id];
            }
        }
        if ($shown === []) {
            return array_slice($catalogue, 0, self::MAX_COLUMNS, true);
        }

        return array_slice($shown, 0, self::MAX_COLUMNS, true);
    }

    /**
     * The search a client asked for, as the class report applies it: cleaned, trimmed, at most {@see SEARCH_MAX}
     * characters, and empty when shorter than {@see SEARCH_MIN}.
     *
     * @param string $search The requested search.
     * @return string The search, or ''.
     */
    public static function search(string $search): string {
        $search = \core_text::substr(trim(clean_param($search, PARAM_TEXT)), 0, self::SEARCH_MAX);

        return \core_text::strlen($search) >= self::SEARCH_MIN ? $search : '';
    }

    /**
     * Every competency of the course, in the course's order, and the scale each is rated on.
     *
     * One statement. The scale is the competency's own or, without one, its framework's
     * (`competency::get_scale()`). With an activity, only the competencies linked to it, and only when the
     * activity is the course's (a module id is a parameter a client sends).
     *
     * @param int $courseid The course.
     * @param int $moduleid A course module of the course the current user can see, or 0; any other reads as unknown.
     * @return array Competency id => record (id, shortname, idnumber, scaleid), in the course's order.
     */
    public static function catalogue(int $courseid, int $moduleid): array {
        global $DB;

        $params = ['courseid' => $courseid];
        $modulesql = '';
        if ($moduleid > 0) {
            /* An activity the viewer cannot see, or one the course does not have, is an unknown activity: no
               competency matches, so it adds no column and answers nothing about itself. */
            $cm = get_fast_modinfo($courseid)->cms[$moduleid] ?? null;
            if ($cm === null || !$cm->uservisible) {
                return [];
            }
            $modulesql = "AND cc.competencyid IN (
                              SELECT mc.competencyid
                                FROM {competency_modulecomp} mc
                                JOIN {course_modules} cm ON cm.id = mc.cmid
                               WHERE mc.cmid = :cmid AND cm.course = :cmcourse)";
            $params += ['cmid' => $moduleid, 'cmcourse' => $courseid];
        }
        $records = $DB->get_records_sql(
            "SELECT c.id, c.shortname, c.idnumber, COALESCE(c.scaleid, f.scaleid) AS scaleid
               FROM {competency_coursecomp} cc
               JOIN {competency} c ON c.id = cc.competencyid
               JOIN {competency_framework} f ON f.id = c.competencyframeworkid
              WHERE cc.courseid = :courseid {$modulesql}
           ORDER BY cc.sortorder, cc.id",
            $params
        );

        $competencies = [];
        foreach ($records as $record) {
            $competencies[(int) $record->id] = (object) [
                'id' => (int) $record->id,
                'shortname' => (string) $record->shortname,
                'idnumber' => (string) $record->idnumber,
                'scaleid' => (int) $record->scaleid,
            ];
        }

        return $competencies;
    }

    /**
     * The items of each scale the competencies are rated on, read once for the whole request.
     *
     * One statement for every scale. The items are numbered from 1, as a rating stores them, and trimmed as
     * `grade_scale::load_items()` does; each in two spellings: plain for a template's double stash, escaped for a
     * select option of the filter form, which prints its labels raw.
     *
     * @param array $competencies The competencies, as {@see catalogue()} and {@see chosen()} return them.
     * @param context $context The context the names are formatted in.
     * @return array Scale id => ['plain' => rating => name, 'escaped' => rating => name].
     */
    public static function scales(array $competencies, context $context): array {
        global $DB;

        $ids = array_values(array_unique(array_map(static fn(\stdClass $c): int => $c->scaleid, $competencies)));
        $scales = [];
        if (!$ids) {
            return $scales;
        }
        foreach ($DB->get_records_list('scale', 'id', $ids, '', 'id, scale') as $record) {
            $plain = [];
            $escaped = [];
            foreach (explode(',', (string) $record->scale) as $index => $item) {
                $plain[$index + 1] = format_string(trim($item), true, ['context' => $context, 'escape' => false]);
                $escaped[$index + 1] = format_string(trim($item), true, ['context' => $context]);
            }
            $scales[(int) $record->id] = ['plain' => $plain, 'escaped' => $escaped];
        }

        return $scales;
    }

    /**
     * The course and the activity of core's report renderable.
     *
     * Core keeps them protected and exports them only together with the learner's breakdown, whose export writes
     * rows; they are read from the object being rendered, never from the request.
     *
     * @param report $page Core's renderable.
     * @return array [the course id, the course module id or 0].
     */
    public static function renderable_ids(report $page): array {
        $read = static fn(string $name): int => (int) (new \ReflectionProperty($page, $name))->getValue($page);

        return [$read('courseid'), max(0, $read('moduleid'))];
    }
}
