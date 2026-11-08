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
 * Theme Dimensions - The courses related to a set of competencies, for the current reader.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\related;

use core_competency\course_competency;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\population;
use theme_dimensions\local\landing\publicaccess;
use theme_dimensions\local\trail\display;

/**
 * The related contents' one reader: which courses relate to a set of competencies, in what order, and which of them
 * the current reader may be shown.
 *
 * The plan is docs/related/PLAN.md. A candidate is a course, other than the ones excluded, linked to at least one
 * competency of the set ({competency_coursecomp}). {@see rank()} orders them, purely: in automatic mode by tier
 * (identical set, then a course that grants a shared competency, then any course sharing one) and overlap, in manual
 * mode by how many of the chosen competencies a course covers; then the nearest start date and the name. Only then
 * {@see find()} asks the reader's gate, slice by slice of the ranking, so its statements are bounded by the number of
 * cards asked for, never by the site: the trail's gate without its ghosts ({@see display}), since a list of
 * suggestions has no position for a ghost to keep, and an account is not shown the courses it is enrolled in or has
 * completed.
 *
 * Nothing is stored and nothing is cached: one statement for the set, one for the candidates, then per slice the
 * rows, the account's own courses and the gate.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class finder {
    /** @var string The automatic mode: the parent's whole set, ranked by tier. */
    public const MODE_AUTO = 'auto';

    /** @var string The manual mode: chosen competencies, ranked by how many a course covers. */
    public const MODE_MANUAL = 'manual';

    /** @var string Manual mode: a course linked to any of the chosen competencies. */
    public const MATCH_ANY = 'any';

    /** @var string Manual mode: a course linked to all of the chosen competencies. */
    public const MATCH_ALL = 'all';

    /** @var int Tier 1: the course's set is the parent's set. */
    public const TIER_PARITY = 1;

    /** @var int Tier 2: the course shares a competency and completing it completes one it shares. */
    public const TIER_GRANTS = 2;

    /** @var int Tier 3: the course shares a competency. */
    public const TIER_SHARES = 3;

    /** @var int The most cards one block may ask for. */
    public const MAX_LIMIT = 12;

    /** @var int The least candidates one slice of the ranking puts to the gate. */
    private const POOL_LEAST = 12;

    /** @var int How many slices find() gates before it settles for fewer cards than asked. */
    private const ROUNDS = 3;

    /**
     * Whether related contents exist on this site: core's competencies are enabled.
     *
     * @return bool
     */
    public static function available(): bool {
        return \core_competency\api::is_enabled();
    }

    /**
     * The competencies linked to these courses, one statement: a course's set, or a trail's with its steps' courses.
     *
     * @param int[] $courseids The courses: the parent, and for a trail its steps' referenced courses.
     * @return int[] The competency ids, ascending, each once; none while competencies are disabled.
     */
    public static function competencies_of(array $courseids): array {
        global $DB;

        $courseids = self::ids($courseids);
        if (!$courseids || !self::available()) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'crs');
        $ids = array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT competencyid FROM {competency_coursecomp} WHERE courseid {$insql}",
            $params
        ));
        sort($ids);
        return $ids;
    }

    /**
     * The competencies a manual choice names, by the framework's idnumber and theirs, one statement.
     *
     * A competency idnumber is unique only inside its framework, a framework idnumber on the whole site, so a choice
     * names one framework (D9). With a parent course, a competency not linked to it is not found: a manual choice
     * picks among the parent's competencies (D4 allows any only where there is no parent).
     *
     * @param string $framework The framework's idnumber.
     * @param string[] $idnumbers The competencies' idnumbers, inside that framework.
     * @param int $parentid The parent course, 0 for none.
     * @return array ['ids' => int[] the competencies found, in the order named, each once,
     *   'missing' => string[] the idnumbers not found, in the order named]
     */
    public static function resolve(string $framework, array $idnumbers, int $parentid = 0): array {
        global $DB;

        $framework = trim($framework);
        $named = [];
        foreach ($idnumbers as $idnumber) {
            $idnumber = trim((string) $idnumber);
            if ($idnumber !== '') {
                $named[$idnumber] = $idnumber;
            }
        }
        if ($framework === '' || !$named || !self::available()) {
            return ['ids' => [], 'missing' => array_values($named)];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_values($named), SQL_PARAMS_NAMED, 'idn');
        $params['framework'] = $framework;
        $join = '';
        if ($parentid > 0) {
            $join = 'JOIN {competency_coursecomp} cc ON cc.competencyid = c.id AND cc.courseid = :parentid';
            $params['parentid'] = $parentid;
        }
        $found = $DB->get_records_sql_menu(
            "SELECT c.idnumber, c.id
               FROM {competency} c
               JOIN {competency_framework} f ON f.id = c.competencyframeworkid
                    {$join}
              WHERE f.idnumber = :framework AND c.idnumber {$insql}",
            $params
        );

        $ids = [];
        $missing = [];
        foreach ($named as $idnumber) {
            if (isset($found[$idnumber])) {
                $ids[(int) $found[$idnumber]] = (int) $found[$idnumber];
            } else {
                $missing[] = $idnumber;
            }
        }
        return ['ids' => array_values($ids), 'missing' => $missing];
    }

    /**
     * The courses related to a set of competencies that the current reader may be shown, best first.
     *
     * @param int[] $set The competencies: the parent's set (automatic) or the chosen ones (manual).
     * @param int[] $excluded Courses never related: the parent, a trail's steps.
     * @param string $mode MODE_AUTO or MODE_MANUAL.
     * @param string $match Manual mode: MATCH_ANY or MATCH_ALL; ignored in automatic mode.
     * @param int $limit How many, clamped to 1..MAX_LIMIT.
     * @param int|null $now The time the start dates are compared with; now when null.
     * @return array List of ['candidate' => candidate, 'row' => \stdClass], the row in {@see display::rows()}'s shape.
     */
    public static function find(
        array $set,
        array $excluded,
        string $mode = self::MODE_AUTO,
        string $match = self::MATCH_ANY,
        int $limit = 4,
        ?int $now = null
    ): array {
        $set = self::ids($set);
        if (!$set || !self::available()) {
            return [];
        }
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $ranked = self::rank($set, self::candidates($set, $excluded), $mode, $match, $now ?? time());

        $pool = max(self::POOL_LEAST, 3 * $limit);
        $found = [];
        for ($round = 0; $round < self::ROUNDS && count($found) < $limit; $round++) {
            $slice = array_slice($ranked, $round * $pool, $pool);
            if (!$slice) {
                break;
            }
            $rows = self::admitted(array_map(static fn(candidate $c): int => $c->courseid, $slice));
            foreach ($slice as $candidate) {
                if (isset($rows[$candidate->courseid])) {
                    $found[] = ['candidate' => $candidate, 'row' => $rows[$candidate->courseid]];
                    if (count($found) === $limit) {
                        break;
                    }
                }
            }
        }
        return $found;
    }

    /**
     * Order the candidates, purely: the whole ranking rule, with no database and no reader.
     *
     * Automatic mode: tier (parity, grants, shares), then overlap. Manual mode: how many of the chosen competencies a
     * course covers, then whether it grants one; MATCH_ALL keeps only the courses covering all of them. Then, in both,
     * a course starting at or after $now first, the nearest first, then the ones that started, the latest first, then
     * those with no start date; then the name, then the id, so the order never depends on the database's.
     *
     * @param int[] $set The competencies, each once.
     * @param \stdClass[] $rows The candidates' aggregate rows: courseid, shared, total, grants, startdate, fullname.
     * @param string $mode MODE_AUTO or MODE_MANUAL.
     * @param string $match MATCH_ANY or MATCH_ALL.
     * @param int $now The time the start dates are compared with.
     * @return candidate[] The candidates, best first.
     */
    public static function rank(array $set, array $rows, string $mode, string $match, int $now): array {
        $size = count($set);
        $manual = $mode === self::MODE_MANUAL;
        $candidates = [];
        foreach ($rows as $row) {
            $shared = (int) $row->shared;
            $total = (int) $row->total;
            if ($shared < 1 || ($manual && $match === self::MATCH_ALL && $shared < $size)) {
                continue;
            }
            $grants = (bool) (int) $row->grants;
            if ($shared === $size && $total === $size) {
                $tier = self::TIER_PARITY;
            } else if ($grants) {
                $tier = self::TIER_GRANTS;
            } else {
                $tier = self::TIER_SHARES;
            }
            $candidates[] = new candidate(
                (int) $row->courseid,
                $tier,
                $shared,
                $total,
                $grants,
                $shared / ($size + $total - $shared),
                (int) $row->startdate,
                (string) $row->fullname
            );
        }

        usort($candidates, static function (candidate $a, candidate $b) use ($manual, $now): int {
            if ($manual) {
                $order = [$b->shared <=> $a->shared, (int) $b->grants <=> (int) $a->grants];
            } else {
                $order = [$a->tier <=> $b->tier, $b->overlap <=> $a->overlap];
            }
            $order[] = self::start_key($a, $now) <=> self::start_key($b, $now);
            $order[] = strcmp(\core_text::strtolower($a->fullname), \core_text::strtolower($b->fullname));
            $order[] = $a->courseid <=> $b->courseid;
            foreach ($order as $comparison) {
                if ($comparison !== 0) {
                    return $comparison;
                }
            }
            return 0;
        });
        return $candidates;
    }

    /**
     * Where a start date sorts: upcoming nearest first, then started latest first, then unset.
     *
     * @param candidate $candidate The candidate.
     * @param int $now The time compared with.
     * @return array [group, value], compared element by element.
     */
    private static function start_key(candidate $candidate, int $now): array {
        if ($candidate->startdate <= 0) {
            return [2, 0];
        }
        if ($candidate->startdate >= $now) {
            return [0, $candidate->startdate];
        }
        return [1, -$candidate->startdate];
    }

    /**
     * Every course linked to a competency of the set, with how it relates to the set, one statement.
     *
     * @param int[] $set The competencies, each once.
     * @param int[] $excluded Courses left out; the site course always is.
     * @return \stdClass[] Rows courseid, shared, total, grants, startdate, fullname, keyed by course id.
     */
    private static function candidates(array $set, array $excluded): array {
        global $DB;

        // A placeholder appears once per statement, so the set is spelled three times under three prefixes.
        [$sharedsql, $sharedparams] = $DB->get_in_or_equal($set, SQL_PARAMS_NAMED, 'shr');
        [$grantsql, $grantparams] = $DB->get_in_or_equal($set, SQL_PARAMS_NAMED, 'grt');
        [$anysql, $anyparams] = $DB->get_in_or_equal($set, SQL_PARAMS_NAMED, 'any');
        [$outsql, $outparams] = $DB->get_in_or_equal(
            array_merge([SITEID], self::ids($excluded)),
            SQL_PARAMS_NAMED,
            'out',
            false
        );
        return $DB->get_records_sql(
            "SELECT cc.courseid,
                    SUM(CASE WHEN cc.competencyid {$sharedsql} THEN 1 ELSE 0 END) AS shared,
                    COUNT(1) AS total,
                    MAX(CASE WHEN cc.competencyid {$grantsql} AND cc.ruleoutcome = :complete THEN 1 ELSE 0 END) AS grants,
                    c.startdate, c.fullname
               FROM {competency_coursecomp} cc
               JOIN {course} c ON c.id = cc.courseid
              WHERE cc.courseid {$outsql}
                AND cc.courseid IN (SELECT s.courseid FROM {competency_coursecomp} s WHERE s.competencyid {$anysql})
           GROUP BY cc.courseid, c.startdate, c.fullname",
            $sharedparams + $grantparams + $anyparams + $outparams
                + ['complete' => course_competency::OUTCOME_COMPLETE]
        );
    }

    /**
     * The rows of these courses the current reader may be shown as related contents.
     *
     * A visitor (nobody logged in, or the guest account): the public courses. An account: what the showcase would list
     * to it ({@see population::admitted()} over the root scope, the trail's gate), less the courses it is enrolled in
     * or has completed (D5). Never a ghost.
     *
     * @param int[] $courseids The slice of the ranking.
     * @return \stdClass[] The rows admitted, keyed by course id.
     */
    private static function admitted(array $courseids): array {
        $rows = display::rows($courseids);
        if (!$rows) {
            return [];
        }
        if (display::visitor()) {
            return array_intersect_key($rows, array_flip(publicaccess::are_public(array_keys($rows))));
        }
        $rows = array_diff_key($rows, array_flip(self::mine(array_keys($rows))));
        if (!$rows) {
            return [];
        }
        $admitted = [];
        foreach (population::admitted(population::scope(new criteria(0)), $rows) as $courseid => $row) {
            $admitted[(int) $courseid] = $row;
        }
        return $admitted;
    }

    /**
     * Which of these courses the current account is enrolled in, in any state, or has completed, one statement.
     *
     * Completion is read on its own because an account may have completed a course it is no longer enrolled in.
     *
     * @param int[] $courseids The courses.
     * @return int[] The ones the account holds.
     */
    private static function mine(array $courseids): array {
        global $DB, $USER;

        [$enrolsql, $enrolparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'enr');
        [$donesql, $doneparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'don');
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT e.courseid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :enroluser AND e.courseid {$enrolsql}
              UNION
             SELECT cc.course
               FROM {course_completions} cc
              WHERE cc.userid = :doneuser AND cc.timecompleted IS NOT NULL AND cc.course {$donesql}",
            $enrolparams + $doneparams + ['enroluser' => (int) $USER->id, 'doneuser' => (int) $USER->id]
        ));
    }

    /**
     * Positive integer ids, each once, in the order given.
     *
     * @param array $ids The ids.
     * @return int[]
     */
    private static function ids(array $ids): array {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }
        return array_values($clean);
    }
}
