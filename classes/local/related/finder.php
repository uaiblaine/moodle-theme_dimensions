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
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\population;
use theme_dimensions\local\landing\publicaccess;
use theme_dimensions\local\trail\display;

/**
 * The related contents' one reader: which courses relate to a set of competencies, in what order, and which of them
 * the current reader may be shown.
 *
 * A candidate is a course, other than the ones excluded, linked to at least one
 * competency of the set ({competency_coursecomp}). {@see rank()} orders them, purely: in automatic mode by tier
 * (identical set, then a course that grants a shared competency, then any course sharing one) and overlap, in manual
 * mode by how many of the chosen competencies a course covers; then the nearest start date and the name.
 *
 * Who is shown what is decided in two places, so a block is never left empty by refusals at the head of the ranking.
 * The candidates' statement already leaves out every course the gate would refuse for a reason the database can
 * tell: a hidden course, a frozen one while context freezing is on, for a visitor any course that is not
 * public by its own state and its category path, for an account the courses it is enrolled in or has completed.
 * Then {@see find()} asks the reader's gate, the authority, slice by slice of the ranking, so its statements are
 * bounded by the number of cards asked for, never by the site: the trail's gate without its ghosts ({@see display}),
 * since a list of suggestions has no position for a ghost to keep. What the gate still refuses after the statement
 * is what depends on the account (a category closed to it, an unlisted course it has no way into), and the slices
 * reach past those.
 *
 * Nothing is stored and nothing is cached across requests: one statement for the set, one for the candidates,
 * then per slice the rows and the gate.
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
     * names one framework. With a parent course, a competency not linked to it is not found: a manual choice
     * picks among the parent's competencies. Without one, any competency of the framework is found.
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
     * Every course linked to a competency of the set that the current reader could be shown, with how it relates to
     * the set, one statement.
     *
     * The reader's refusals the database can tell are terms of this statement, so the ranking holds no course the
     * gate would refuse for them and a block is never emptied by a refused head ({@see refusals()}).
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
        $refusals = self::refusals();
        return $DB->get_records_sql(
            "SELECT cc.courseid,
                    SUM(CASE WHEN cc.competencyid {$sharedsql} THEN 1 ELSE 0 END) AS shared,
                    COUNT(1) AS total,
                    MAX(CASE WHEN cc.competencyid {$grantsql} AND cc.ruleoutcome = :complete THEN 1 ELSE 0 END) AS grants,
                    c.startdate, c.fullname
               FROM {competency_coursecomp} cc
               JOIN {course} c ON c.id = cc.courseid
               JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :courselevel
                    {$refusals['join']}
              WHERE cc.courseid {$outsql}
                AND cc.courseid IN (SELECT s.courseid FROM {competency_coursecomp} s WHERE s.competencyid {$anysql})
                AND c.visible = 1
                    {$refusals['where']}
           GROUP BY cc.courseid, c.startdate, c.fullname",
            $sharedparams + $grantparams + $anyparams + $outparams + $refusals['params'] + [
                'complete' => course_competency::OUTCOME_COMPLETE,
                'courselevel' => CONTEXT_COURSE,
            ]
        );
    }

    /**
     * The terms of the candidates' statement that leave out what the reader's gate would refuse anyway.
     *
     * Each term only narrows: the gate in {@see admitted()} stays the authority, so a term that admitted too much would
     * cost a card, never show one, while a term that refused too much would hide a course the reader may see. Hence
     * only refusals that hold for every reader of the kind:
     *
     * - beside the statement's own `c.visible = 1` (a hidden course is no suggestion, not even to a reader who
     *   may open it), a frozen course while core's context freezing is on, its own context or any above it locked:
     *   core refuses every write capability there, self-enrolment included ({@see \core\context::is_locked()});
     * - a visitor: a course public by its own state, in a category path holding no hidden and no unlisted category,
     *   the terms of {@see discoverability::are_public()}; the plugin's answer is still asked of every card, and it
     *   fails closed where these terms cannot (a missing ancestor);
     * - an account: none it is enrolled in, in any state, or has completed, read on its own because an account may
     *   have completed a course it is no longer enrolled in.
     *
     * @return array ['join' => string, 'where' => string, 'params' => array], over the aliases c and ctx.
     */
    private static function refusals(): array {
        global $CFG, $DB, $USER;

        $join = '';
        $where = '';
        $params = [];
        if (!empty($CFG->contextlocking)) {
            // Core's own spelling of "below a context" (core\check\access\riskxss_result): sql_like() takes a bound
            // pattern and calls a literal one an injection.
            $below = 'ctx.path LIKE ' . $DB->sql_concat('lck.path', "'/%'");
            $where .= " AND NOT EXISTS (SELECT 1 FROM {context} lck
                                         WHERE lck.locked = 1 AND (lck.id = ctx.id OR {$below}))";
        }

        if (display::visitor()) {
            $state = discoverability::state_sql('c', 'rls');
            $closed = 'wcat.visible = 0';
            $unlisted = category_discoverability::unlisted_ids();
            if ($unlisted) {
                [$unlistedsql, $unlistedparams] = $DB->get_in_or_equal($unlisted, SQL_PARAMS_NAMED, 'unl');
                $closed .= " OR wcat.id {$unlistedsql}";
                $params += $unlistedparams;
            }
            $under = 'cat.path LIKE ' . $DB->sql_concat('wcat.path', "'/%'");
            $join .= " {$state['join']} JOIN {course_categories} cat ON cat.id = c.category";
            $where .= " AND {$state['column']} = :publicstate
                        AND NOT EXISTS (SELECT 1 FROM {course_categories} wcat
                                         WHERE ({$closed}) AND (wcat.id = cat.id OR {$under}))";
            $params['publicstate'] = discoverability::STATE_PUBLIC;
            return ['join' => $join, 'where' => $where, 'params' => $params];
        }

        $where .= " AND NOT EXISTS (SELECT 1 FROM {user_enrolments} mue JOIN {enrol} me ON me.id = mue.enrolid
                                     WHERE me.courseid = c.id AND mue.userid = :enroluser)
                    AND NOT EXISTS (SELECT 1 FROM {course_completions} mcc
                                     WHERE mcc.course = c.id AND mcc.userid = :doneuser AND mcc.timecompleted IS NOT NULL)";
        $params['enroluser'] = (int) $USER->id;
        $params['doneuser'] = (int) $USER->id;
        return ['join' => $join, 'where' => $where, 'params' => $params];
    }

    /**
     * The rows of these courses the current reader may be shown as related contents.
     *
     * A visitor (nobody logged in, or the guest account): the public courses. An account: what the showcase would list
     * to it ({@see population::admitted()} over the root scope, the trail's gate); its own courses never reach here
     * ({@see refusals()}). Never a ghost.
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
        $admitted = [];
        foreach (population::admitted(population::scope(new criteria(0)), $rows) as $courseid => $row) {
            $admitted[(int) $courseid] = $row;
        }
        return $admitted;
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
