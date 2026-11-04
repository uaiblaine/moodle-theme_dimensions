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
 * Theme Dimensions - The calendar's Trilhas view: the trails of a month, or of the month and the next, in one answer.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use core\context_helper;
use local_unlistedcourses\discoverability;
use theme_dimensions\local\category\classifier;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\population;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landingfields;
use theme_dimensions\local\trail\display;
use theme_dimensions\local\trail\progress;
use theme_dimensions\local\trail\trail;

/**
 * The answer of the Trilhas view: the trails whose period meets a window of whole months, and under each
 * the steps this account sees, with their state for a learner of the trail.
 *
 * In order:
 *
 * 1. The candidates: the courses whose page model is the trail ({@see landingfields::MODEL_TRAIL}), inside the
 *    category narrowing of the calendar's scope, read in pages of {@see CAP} (one statement a page) until the gate has
 *    admitted more than the cap, which is then kept and the answer said truncated. A visitor is answered nothing.
 * 2. The trails this account may discover, by the parent course's rule: the showcase's own gate,
 *    {@see population::admitted()} over the root scope, as the calendar's open events and the steps use it. An unlisted
 *    trail is therefore offered to an account enrolled in it or with a way to enrol, and to nobody else.
 * 3. Their structure ({@see trail::many()}) and the steps this account sees ({@see display::many()}, section 6's
 *    account rule: in full or not at all, never a ghost), each batch one statement for every trail.
 * 4. The progress of the trails the account is enrolled in ({@see progress::many()}), over the steps drawn.
 * 5. The rows: the trails with a step drawn whose period meets the window, narrowed to one trail or to the account's
 *    own when asked. The period is the landing page's: from the first start to the last end over the steps drawn, left
 *    open by a step that starts and never ends.
 *
 * `options` lists every trail of step 2 that has a step drawn, whatever its period: the view's "Trilha" choice. A
 * trail is never named to an account the gate refuses, so a shared address naming one shows nothing of it.
 *
 * The statements do not grow with the trails up to the cap: one for the candidates, the gate's, one for the steps' referenced
 * courses and their gate, the enrolments, and at most one on {course_completions}.
 * Each trail course's modinfo and completion data are core's caches (trails_budget_test measures a warm page).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class trails {
    /** @var int The most trail courses one answer reads; past it, the answer says it was truncated. */
    public const CAP = 100;

    /** @var int The most months one answer covers: the batch the view asks by months ({@see window::MAX_MONTHS}). */
    public const MONTHS = window::MAX_MONTHS;

    /** @var int The months of the view's first paint: the month and the next. */
    public const OPENING = 2;

    /**
     * The answer for a window of whole months.
     *
     * @param window $window The months' own days, the first's 1st to the last's last day ({@see month_window()}).
     * @param scope $scope The viewer's calendar scope, whose category narrowing applies to the trail course.
     * @param int $trailid One trail to keep, 0 for every trail.
     * @param bool $mine Whether only the trails the viewer is enrolled in are kept.
     * @param int $now The moment a step's end is judged passed from.
     * @return array ['from', 'to', 'truncated', 'options', 'trails', 'categories'].
     */
    public static function answer(window $window, scope $scope, int $trailid, bool $mine, int $now): array {
        $answer = [
            'from' => $window->from,
            'to' => $window->to,
            'truncated' => false,
            'options' => [],
            'trails' => [],
            'categories' => [],
        ];
        // The view is an account's: a visitor's answer would take the ghost path of the steps, which this one never draws.
        if (display::visitor()) {
            return $answer;
        }
        // Without mod_subcourse trail::many() reads no trail, so nothing below is drawn or offered.
        $field = landingfields::get_field(landingfields::SHORTNAME_MODEL);
        if (!$field) {
            return $answer;
        }

        // Pages of the cap's size, each judged by the showcase's gate, until the cap is passed: the cap counts the trails
        // this account may discover, so a trail it may not see neither takes an admitted one's place nor says "more".
        $root = population::scope(new criteria(0));
        $courses = [];
        $offset = 0;
        do {
            $rows = self::candidates($scope, (int) $field->get('id'), $offset);
            foreach ($rows ? population::admitted($root, $rows) : [] as $courseid => $row) {
                $courses[(int) $courseid] = $row;
            }
            $offset += self::CAP;
        } while (count($rows) === self::CAP && count($courses) <= self::CAP);
        $answer['truncated'] = count($courses) > self::CAP;
        $courses = array_slice($courses, 0, self::CAP, true);
        if (!$courses) {
            return $answer;
        }

        $structures = trail::many($courses);
        $drawn = display::many($structures, $courses);
        $enrolments = classifier::enrolments($scope->viewerid);
        $learning = [];
        foreach ($drawn as $courseid => $entries) {
            if ($entries && !empty($enrolments[$courseid]->active)) {
                $learning[$courseid] = [
                    get_fast_modinfo($courses[$courseid])->get_course(),
                    array_map(static fn(array $entry) => $entry['step'], $entries),
                ];
            }
        }
        $progress = $learning ? progress::many($learning, $now) : [];

        $categoryids = [];
        $built = [];
        foreach ($drawn as $courseid => $entries) {
            if (!$entries) {
                continue;
            }
            $row = $courses[$courseid];
            $context = \core\context\course::instance($courseid);
            $name = format_string($row->fullname, true, ['context' => $context, 'escape' => false]);
            $answer['options'][] = ['id' => $courseid, 'name' => $name];
            [$first, $last, $open] = self::period($entries);
            if (!self::meets($first, $last, $open, $window)) {
                continue;
            }
            $enrolled = isset($progress[$courseid]);
            if (($trailid !== 0 && $trailid !== $courseid) || ($mine && !$enrolled)) {
                continue;
            }
            $categoryids[(int) $row->category] = true;
            $steps = [];
            foreach ($entries as $index => $entry) {
                $step = $entry['step'];
                $ref = $entry['row'];
                $categoryids[(int) $ref->category] = true;
                $state = $enrolled ? $progress[$courseid]['states'][$step->cmid] : null;
                $steps[] = [
                    'cmid' => $step->cmid,
                    'position' => $index + 1,
                    'name' => format_string($step->name, true, [
                        'context' => \core\context\module::instance($step->cmid),
                        'escape' => false,
                    ]),
                    'courseid' => $step->refcourseid,
                    'coursename' => format_string($ref->fullname, true, [
                        'context' => \core\context\course::instance($step->refcourseid),
                        'escape' => false,
                    ]),
                    'categoryid' => (int) $ref->category,
                    'start' => $step->start,
                    'end' => $step->end,
                    'expected' => $step->expected,
                    'state' => $state === null ? '' : $state['state'],
                    'reason' => $state === null ? '' : (string) $state['reason'],
                    'completed' => $state === null ? 0 : $state['completed'],
                    'url' => links::page($step->refcourseid)->out(false),
                    'next' => $enrolled && $progress[$courseid]['next'] === $step->cmid,
                ];
            }
            $built[] = [
                'id' => $courseid,
                'name' => $name,
                'url' => links::page($courseid)->out(false),
                'categoryid' => (int) $row->category,
                'enrolled' => $enrolled,
                'done' => $enrolled ? $progress[$courseid]['done'] : 0,
                'first' => $first,
                'last' => $open ? 0 : $last,
                'steps' => $steps,
            ];
        }
        usort($built, static fn(array $a, array $b): int => [$a['first'], \core_text::strtolower($a['name']), $a['id']]
            <=> [$b['first'], \core_text::strtolower($b['name']), $b['id']]);
        usort($answer['options'], static fn(array $a, array $b): int => strcmp(
            \core_text::strtolower($a['name']),
            \core_text::strtolower($b['name'])
        ) ?: $a['id'] <=> $b['id']);

        $categories = range::categories(array_keys($categoryids));
        foreach ($built as &$entry) {
            $entry['categoryid'] = isset($categories[$entry['categoryid']]) ? $entry['categoryid'] : 0;
            foreach ($entry['steps'] as &$step) {
                $step['categoryid'] = isset($categories[$step['categoryid']]) ? $step['categoryid'] : 0;
            }
            unset($step);
        }
        unset($entry);
        $answer['trails'] = $built;
        $answer['categories'] = array_values($categories);
        return $answer;
    }

    /**
     * The days of a month, or of the month and the next ones, as a window: the first's 1st to the last's last day, in
     * the viewer's timezone.
     *
     * @param string $month The first month, YYYY-MM.
     * @param int $months How many months, 1 to {@see MONTHS}.
     * @return window
     * @throws refusal On a malformed month or a count outside 1 to MONTHS.
     */
    public static function month_window(string $month, int $months = 1): window {
        return window::whole_months($month, $months);
    }

    /**
     * One page of the trail courses in the scope's narrowing, as the showcase's gate and get_fast_modinfo() read them.
     *
     * One statement over the model field's rows, ordered by the course index's own order, {@see CAP} rows from an offset.
     *
     * @param scope $scope The viewer's calendar scope.
     * @param int $fieldid The model field.
     * @param int $offset How many candidates earlier pages read.
     * @return \stdClass[] Map of course id => row (scope::COURSE_FIELDS, sortorder, liststate and the context columns).
     */
    private static function candidates(scope $scope, int $fieldid, int $offset): array {
        global $DB;

        $where = "d.fieldid = :modelfield AND d.component = :modelcomponent AND d.area = :modelarea AND d.itemid = 0
                  AND d.intvalue = :trailmodel AND c.id <> :siteid";
        $params = [
            'modelfield' => $fieldid,
            'modelcomponent' => 'core_course',
            'modelarea' => 'course',
            'trailmodel' => landingfields::MODEL_TRAIL,
            'siteid' => SITEID,
        ];
        if ($scope->tree !== null) {
            [$catsql, $catparams] = $DB->get_in_or_equal($scope->tree, SQL_PARAMS_NAMED, 'tree');
            $where .= " AND c.category {$catsql}";
            $params += $catparams;
        }
        $columns = [];
        foreach (explode(',', scope::COURSE_FIELDS) as $column) {
            $columns[] = 'c.' . trim($column);
        }
        $columns = implode(', ', $columns);
        $state = discoverability::state_sql('c');
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $rows = $DB->get_records_sql(
            "SELECT {$columns}, c.sortorder, {$state['column']} AS liststate, {$ctxselect}
               FROM {course} c
               JOIN {customfield_data} d ON d.instanceid = c.id
               JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse
                    {$state['join']}
              WHERE {$where}
           ORDER BY c.sortorder, c.id",
            $params + ['contextcourse' => CONTEXT_COURSE],
            $offset,
            self::CAP
        );
        $keyed = [];
        foreach ($rows as $row) {
            // From a clone: the gate reads the preload columns off the rows again.
            context_helper::preload_from_record(clone $row);
            $keyed[(int) $row->id] = $row;
        }
        return $keyed;
    }

    /**
     * A trail's period over the steps drawn: the landing page's rule ({@see \theme_dimensions\output\landing_page}).
     *
     * @param array $entries The steps drawn, as {@see display::many()} lists them.
     * @return array [first start or 0, last end or 0, whether a step that starts never ends].
     */
    private static function period(array $entries): array {
        $first = 0;
        $last = 0;
        $open = false;
        foreach ($entries as $entry) {
            $step = $entry['step'];
            if ($step->start > 0) {
                $first = $first > 0 ? min($first, $step->start) : $step->start;
                $open = $open || $step->end <= 0;
            }
            $last = max($last, $step->end);
        }
        return [$first, $last, $open];
    }

    /**
     * Whether a period meets a window: it starts before the window ends and does not end before it starts.
     *
     * A period with no date at all meets no window: with no first start and no last end, nothing reaches it.
     *
     * @param int $first The first start, 0 for none.
     * @param int $last The last end, 0 for none.
     * @param bool $open Whether the period never ends.
     * @param window $window The months.
     * @return bool
     */
    private static function meets(int $first, int $last, bool $open, window $window): bool {
        return ($first === 0 || $first <= $window->tend) && ($open || $last >= $window->tstart);
    }
}
