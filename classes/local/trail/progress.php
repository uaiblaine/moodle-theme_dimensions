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
 * Theme Dimensions - How far the current reader has gone along a trail.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\trail;

/**
 * The state of each drawn step for the current reader.
 *
 * Asked only for a reader enrolled in the trail course, over the steps {@see display::steps()} drew in full: an
 * account is never shown a ghost, so those are every step the reader sees. A step is done when its activity is
 * complete for the reader, or, where the activity tracks no completion, when the referenced course is. Core's
 * answer for the activity wins wherever it has one: an activity that tracks completion and is not complete is
 * not done, whatever the referenced course says, because the trail's author chose the activity's rule.
 *
 * Then, for a step not done: locked while core's availability refuses it to the reader (the reason is core's own
 * text); otherwise it is open. An open step whose end has passed is late; otherwise the first open step is current
 * and any later one upcoming. So exactly one step, the first open one, is the reader's next (the one the landing
 * page's "Continue" leads to), and it is current or late.
 *
 * Statements: core's completion data for the whole trail course in one read (the 'core/completion' cache, which the
 * course page fills too; filling it asks each activity's own completion rules, mod_subcourse's a row per activity),
 * and one on {course_completions} only when a drawn step's activity tracks no completion, whatever the number of trails
 * asked together ({@see many()}).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class progress {
    /** @var string The step is complete. */
    public const DONE = 'done';

    /** @var string The reader's next step, its end not passed. */
    public const CURRENT = 'current';

    /** @var string An open step whose end has passed. */
    public const LATE = 'late';

    /** @var string A step core's availability refuses to the reader for now. */
    public const LOCKED = 'locked';

    /** @var string An open step after the reader's next one. */
    public const UPCOMING = 'upcoming';

    /**
     * The state of each step, in the steps' order, and which one is next.
     *
     * @param \stdClass $course The trail course.
     * @param step[] $steps The steps drawn in full, in the trail's order.
     * @param int $now The moment "passed" is measured from.
     * @return array ['states' => map of cmid => ['state' => one of the constants, 'completed' => the time core
     *   recorded the step's completion, 0 for a step not done, 'reason' => core's availability text for a locked
     *   step in the plain spelling, or null], 'next' => the next step's cmid or null, 'done' => how many steps are
     *   done].
     */
    public static function of(\stdClass $course, array $steps, int $now): array {
        return self::many([(int) $course->id => [$course, $steps]], $now)[(int) $course->id];
    }

    /**
     * The progress of several trails, with one statement on {course_completions} for all of them: the calendar's view.
     *
     * Each trail's answer is {@see of()}'s; the referenced courses whose activity tracks no completion are read for
     * every trail at once.
     *
     * @param array $trails Map of trail course id => [the trail course, its steps drawn in full], as of() takes them.
     * @param int $now The moment "passed" is measured from.
     * @return array[] Map of trail course id => the answer of {@see of()}.
     */
    public static function many(array $trails, int $now): array {
        $readers = [];
        $untracked = [];
        foreach ($trails as $courseid => [$course, $steps]) {
            $modinfo = get_fast_modinfo($course);
            $completion = new \completion_info($course);
            $readers[$courseid] = [$modinfo, $completion];
            foreach ($steps as $step) {
                if ($completion->is_enabled($modinfo->get_cm($step->cmid)) == COMPLETION_TRACKING_NONE) {
                    $untracked[] = $step->refcourseid;
                }
            }
        }
        $coursedone = self::courses_completed($untracked);

        $answers = [];
        foreach ($trails as $courseid => [$course, $steps]) {
            [$modinfo, $completion] = $readers[$courseid];
            $answers[$courseid] = self::states($course, $steps, $now, $modinfo, $completion, $coursedone);
        }
        return $answers;
    }

    /**
     * One trail's states, from its readers and the completed referenced courses.
     *
     * @param \stdClass $course The trail course.
     * @param step[] $steps The steps drawn in full, in the trail's order.
     * @param int $now The moment "passed" is measured from.
     * @param \core_course\modinfo $modinfo The trail course's modinfo for the reader.
     * @param \completion_info $completion The trail course's completion.
     * @param int[] $coursedone Map of referenced course id => time the reader completed it.
     * @return array The answer of {@see of()}.
     */
    private static function states(
        \stdClass $course,
        array $steps,
        int $now,
        \core_course\modinfo $modinfo,
        \completion_info $completion,
        array $coursedone
    ): array {
        $states = [];
        $next = null;
        $done = 0;
        foreach ($steps as $step) {
            $cm = $modinfo->get_cm($step->cmid);
            $isdone = false;
            $completed = 0;
            if ($completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                $data = $completion->get_data($cm, true);
                $isdone = in_array((int) $data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
                $completed = (int) $data->timemodified;
            } else if (isset($coursedone[$step->refcourseid])) {
                $isdone = true;
                $completed = $coursedone[$step->refcourseid];
            }

            $reason = null;
            if ($isdone) {
                $state = self::DONE;
                $done++;
            } else if (!$cm->available) {
                $state = self::LOCKED;
                $reason = self::reason($cm, $course);
            } else if ($step->end > 0 && $step->end < $now) {
                $state = self::LATE;
            } else if ($next === null) {
                $state = self::CURRENT;
            } else {
                $state = self::UPCOMING;
            }
            if ($next === null && ($state === self::CURRENT || $state === self::LATE)) {
                $next = $step->cmid;
            }
            $states[$step->cmid] = ['state' => $state, 'completed' => $completed, 'reason' => $reason];
        }
        return ['states' => $states, 'next' => $next, 'done' => $done];
    }

    /**
     * When the reader completed each of these courses, one statement.
     *
     * @param int[] $courseids The referenced courses whose activity tracks no completion.
     * @return int[] Map of course id => time completed, for the completed ones only.
     */
    private static function courses_completed(array $courseids): array {
        global $DB, $USER;

        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_values(array_unique($courseids)), SQL_PARAMS_NAMED, 'cc');
        $records = $DB->get_records_sql(
            "SELECT course, timecompleted
               FROM {course_completions}
              WHERE userid = :userid AND course {$insql} AND timecompleted IS NOT NULL",
            $params + ['userid' => $USER->id]
        );
        $completed = [];
        foreach ($records as $record) {
            $completed[(int) $record->course] = (int) $record->timecompleted;
        }
        return $completed;
    }

    /**
     * Core's text for why a step is locked, in the plain spelling, or null when core gives none.
     *
     * @param \cm_info $cm The step's activity, as the reader's modinfo has it.
     * @param \stdClass $course The trail course.
     * @return string|null The reason, whitespace folded.
     */
    private static function reason(\cm_info $cm, \stdClass $course): ?string {
        if (empty($cm->availableinfo)) {
            return null;
        }
        $html = \core_availability\info::format_info($cm->availableinfo, $course);
        // Not html_to_text(): it upper-cases <strong>, which is how core sets the names in this text. A block tag
        // is a word break first, so the conditions of a list do not run together.
        $spaced = (string) preg_replace('~<(/?(?:li|ul|ol|p|div|br)\b)~i', ' <$1', $html);
        $text = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        return $text === '' ? null : $text;
    }
}
