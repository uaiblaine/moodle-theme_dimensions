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
use local_unlistedcourses\access;
use local_unlistedcourses\discoverability;
use theme_dimensions\local\card\boost_union;
use theme_dimensions\local\category\cards;
use theme_dimensions\local\category\classifier;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\population;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landing\publicaccess;
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
 *    trail is therefore offered to an account enrolled in it or with a way to enrol, and to nobody else. Then, on the
 *    same page of candidates, the view's own rule ({@see shown_to()}, stage T3d): of what the gate admits, the trails
 *    this account takes part in, may enrol in, may see as public, or may edit. Both run inside the page, before the
 *    cap counts, so a trail the account is not shown never takes a shown one's place nor says "more".
 * 3. Their structure ({@see trail::many()}) and the steps this account sees ({@see display::many()}, section 6's
 *    account rule: in full or not at all, never a ghost), each batch one statement for every trail.
 * 4. The progress of the trails the account is enrolled in ({@see progress::many()}), over the steps drawn.
 * 5. The rows: the trails with a step drawn whose period meets the window, narrowed to one trail or to the account's
 *    own when asked. The period is the landing page's: from the first start to the last end over the steps drawn, left
 *    open by a step that starts and never ends.
 * 6. The image of each step's course, the popover's cover (the course card contract, section 9): one batch over the
 *    courses of the steps drawn ({@see cards::images()}, the showcase's reader, with the renderer's cover for a course
 *    without one), and none while Boost Union's card view draws no image ({@see boost_union::show_images()}).
 *
 * `options` lists every trail of step 2 that has a step drawn, whatever its period: the view's "Trilha" choice. A
 * trail is never named to an account the gate or the view's rule refuses, so a shared address naming one shows nothing
 * of it. "Only mine" keeps the trails the account is actively enrolled in, which the rule always shows.
 *
 * The statements do not grow with the trails up to the cap: one for the candidates, the gate's, the enrolments, the
 * public batch and the plugin's next-action batch (each once a page of candidates, and only over the trails still
 * undecided), one for the steps' referenced courses and their gate, at most one on {course_completions}, and at most
 * one on {files} for the steps' images.
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
     * @var string[] The relationships that keep a trail shown: the ones local_unlistedcourses keeps an unlisted course
     *     discoverable for (its private DISCOVERABLE_RELATIONSHIPS; keep the two in step). Suspended and expired keep nothing.
     */
    private const KEPT_RELATIONSHIPS = [
        access::RELATIONSHIP_ENROLLED,
        access::RELATIONSHIP_SCHEDULED,
        access::RELATIONSHIP_PENDING,
        access::RELATIONSHIP_WAITLISTED,
    ];

    /**
     * @var string[] The next actions that are a route into the trail: open (any method that would take the viewer now),
     *     guest access (free or behind a key) and conditional. The plugin's private ENROLABLE_ACTIONS; keep the two in step.
     *     Blocked and none are no route.
     */
    private const ROUTES = [access::NEXT_OPEN, access::NEXT_GUEST, access::NEXT_CONDITIONAL];

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
        global $USER;

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
        // One viewer for the whole rule: the plugin's batch and the capability read the current user, so a scope built
        // for anybody else is answered nothing rather than judged half as one viewer and half as another.
        if ($scope->viewerid !== (int) $USER->id) {
            return $answer;
        }
        // Without mod_subcourse trail::many() reads no trail, so nothing below is drawn or offered.
        $field = landingfields::get_field(landingfields::SHORTNAME_MODEL);
        if (!$field) {
            return $answer;
        }

        // Pages of the cap's size, each judged by the showcase's gate and then by the view's rule, until the cap is
        // passed: the cap counts the trails this account is shown, so a trail it is not shown neither takes a shown one's
        // place nor says "more". The rule's batches are asked once a page, over at most CAP ids.
        $root = population::scope(new criteria(0));
        // Read once, by the first page the gate admits anything of, for the rule and for the progress below.
        $enrolments = null;
        $courses = [];
        $offset = 0;
        do {
            $rows = self::candidates($scope->tree, (int) $field->get('id'), $offset);
            $courses += self::kept($rows, $root, $enrolments);
            $offset += self::CAP;
        } while (count($rows) === self::CAP && count($courses) <= self::CAP);
        $answer['truncated'] = count($courses) > self::CAP;
        $courses = array_slice($courses, 0, self::CAP, true);
        if (!$courses) {
            return $answer;
        }

        $structures = trail::many($courses);
        $drawn = display::many($structures, $courses);
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
        // The steps' courses, the rows display::many() read: the shape cards::images() reads.
        $refrows = [];
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
                $refrows[(int) $step->refcourseid] = $ref;
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
                    'image' => '',
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
        $images = $refrows && boost_union::show_images(boost_union::VIEW_CARD) ? cards::images($refrows, false, true) : [];
        foreach ($built as &$entry) {
            $entry['categoryid'] = isset($categories[$entry['categoryid']]) ? $entry['categoryid'] : 0;
            foreach ($entry['steps'] as &$step) {
                $step['categoryid'] = isset($categories[$step['categoryid']]) ? $step['categoryid'] : 0;
                $step['image'] = self::image($images[$step['courseid']] ?? null);
            }
            unset($step);
        }
        unset($entry);
        $answer['trails'] = $built;
        $answer['categories'] = array_values($categories);
        return $answer;
    }

    /**
     * A step's image as the answer carries it: an uploaded image's address, the renderer's answer for a course without
     * one (a data URI, or Boost Union's fallback image), or '' for none.
     *
     * @param \moodle_url|string|null $image What {@see cards::images()} answered for the step's course.
     * @return string
     */
    private static function image(\moodle_url|string|null $image): string {
        return $image instanceof \moodle_url ? $image->out(false) : (string) $image;
    }

    /**
     * Whether the Trilhas view would list this trail course to the current account: the landing page's "Ver no
     * calendário".
     *
     * The answer's own path over the one course, never a copy of it: the candidates' statement narrowed to the id (so a
     * course of another model is no trail), the showcase's gate and the view's rule ({@see kept()}), then the steps the
     * view would draw for this account ({@see display::many()}): {@see answer()} lists a trail with none of them nowhere,
     * so a learner of a trail whose every course is withheld from them is offered no link to an empty view. No category
     * narrowing: the link's address names a month, so the calendar reads no saved scope for it. A visitor is told no.
     *
     * @param int $courseid The trail course.
     * @return bool
     */
    public static function offers(int $courseid): bool {
        if ($courseid <= 0 || display::visitor()) {
            return false;
        }
        $field = landingfields::get_field(landingfields::SHORTNAME_MODEL);
        if (!$field) {
            return false;
        }
        $rows = self::candidates(null, (int) $field->get('id'), 0, $courseid);
        if (!$rows) {
            return false;
        }
        $enrolments = null;
        $kept = self::kept($rows, population::scope(new criteria(0)), $enrolments);
        if (!isset($kept[$courseid])) {
            return false;
        }
        return !empty(display::many(trail::many($kept), $kept)[$courseid]);
    }

    /**
     * The candidates of one page the account is shown: the showcase's gate first, then the view's rule over what it
     * admits ({@see shown_to()}). The one way to the rule, so no caller can skip the gate.
     *
     * @param \stdClass[] $rows Candidates, keyed by course id ({@see candidates()}).
     * @param \theme_dimensions\local\category\scope $root The showcase's root scope.
     * @param array|null $enrolments The viewer's {@see classifier::enrolments()} map, read here the first time the gate
     *     admits anything and handed back for the caller's progress.
     * @return \stdClass[] The rows kept, same keys, same order.
     */
    private static function kept(array $rows, \theme_dimensions\local\category\scope $root, ?array &$enrolments): array {
        global $USER;

        $admitted = $rows ? population::admitted($root, $rows) : [];
        if (!$admitted) {
            return [];
        }
        $enrolments ??= classifier::enrolments((int) $USER->id);
        $shown = self::shown_to(array_keys($admitted), $enrolments);
        $kept = [];
        foreach ($admitted as $courseid => $row) {
            if (isset($shown[(int) $courseid])) {
                $kept[(int) $courseid] = $row;
            }
        }
        return $kept;
    }

    /**
     * Which of these trail courses the Trilhas view shows the current account: the view's rule (stage T3d).
     *
     * A trail is shown to an account that takes part in it, may join it, may see it as public, or may edit it. In
     * order, each cheaper than the next, and a trail decided by one step is asked nothing further:
     *
     * 1. A relationship that counts as enrolled or on the way: active, scheduled (starts later), an application
     *    awaiting a decision, a waiting-list place. Read as the classifier folds local_unlistedcourses'
     *    {@see access::classify_enrolment()} into `type` ({@see classifier::enrolments()}), never re-derived. A
     *    suspended or expired enrolment counts for nothing here: such a trail goes on to the next steps.
     * 2. Staff: whoever may edit the trail course, the owner's "who can edit", read as `moodle/course:update` at the
     *    trail course's own context (a site administrator passes; the capability in another course does not), so a
     *    manager can check the view. The contexts are preloaded with the candidates.
     * 3. A public trail ({@see publicaccess::are_public()}, the landing page's own predicate), shown to every account,
     *    asked once over the trails still undecided, so a public trail costs no next-action read.
     * 4. A route in: the plugin's next action ({@see access::get_next_actions()}, asked once over the trails still
     *    undecided, never per trail) is open, guest access (free or behind a key) or conditional. Blocked and none
     *    are no route. The batch may answer open where a method would refuse (it drops the self enrolment capability
     *    and enrol_autoenrol's rule), never the reverse; it decides only which of the trails the showcase's gate has
     *    already admitted are shown, never whether a course may be named.
     *
     * The steps are not judged here: a public trail's unlisted step stays hidden from an account with no tie to it
     * ({@see display::many()}). Private: the ids are what the showcase's gate admitted ({@see kept()}), never a
     * caller's, and every read is the current user's, the one viewer the plugin's batch can answer for.
     *
     * @param int[] $courseids Trail course ids the gate admitted, at most {@see CAP}: the batches take one IN list each.
     * @param array $enrolments The current user's {@see classifier::enrolments()} map.
     * @return array Map of course id => true for every trail shown, in the order given.
     */
    private static function shown_to(array $courseids, array $enrolments): array {
        global $USER;

        $shown = [];
        $undecided = [];
        foreach ($courseids as $courseid) {
            $courseid = (int) $courseid;
            $type = isset($enrolments[$courseid]) ? $enrolments[$courseid]->type : access::RELATIONSHIP_NONE;
            if (in_array($type, self::KEPT_RELATIONSHIPS, true)) {
                $shown[$courseid] = true;
            } else if (has_capability('moodle/course:update', \core\context\course::instance($courseid), $USER)) {
                $shown[$courseid] = true;
            } else {
                $undecided[$courseid] = $courseid;
            }
        }
        if ($undecided) {
            foreach (publicaccess::are_public(array_values($undecided)) as $courseid) {
                $shown[(int) $courseid] = true;
                unset($undecided[(int) $courseid]);
            }
        }
        if ($undecided) {
            foreach (access::get_next_actions(array_values($undecided)) as $courseid => $next) {
                if (in_array($next['type'], self::ROUTES, true)) {
                    $shown[(int) $courseid] = true;
                }
            }
        }
        return array_intersect_key(array_fill_keys(array_map('intval', $courseids), true), $shown);
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
     * @param int[]|null $tree The calendar scope's category narrowing, null for none.
     * @param int $fieldid The model field.
     * @param int $offset How many candidates earlier pages read.
     * @param int $courseid One course to read, 0 for every candidate.
     * @return \stdClass[] Map of course id => row (scope::COURSE_FIELDS, sortorder, liststate and the context columns).
     */
    private static function candidates(?array $tree, int $fieldid, int $offset, int $courseid = 0): array {
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
        if ($tree !== null) {
            [$catsql, $catparams] = $DB->get_in_or_equal($tree, SQL_PARAMS_NAMED, 'tree');
            $where .= " AND c.category {$catsql}";
            $params += $catparams;
        }
        if ($courseid > 0) {
            $where .= ' AND c.id = :onlyid';
            $params['onlyid'] = $courseid;
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
