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
 * Theme Dimensions - What a card of the showcase learns once it is on screen
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use local_unlistedcourses\access;
use theme_dimensions\local\card\boost_union;

/**
 * The one method behind theme_dimensions_get_card_details.
 *
 * Completion progress is the one thing a card cannot carry from the server:
 * core has no bulk progress API, so a page of forty-eight cards would pay for
 * forty-eight progress reads before anybody had scrolled. The client asks for
 * it in batches of at most {@see MAX_COURSES} as cards come into view.
 *
 * The ids are filtered, not validated. An id this viewer may not see is
 * dropped from the answer and never named: reporting "you may not see 41"
 * answers "does 41 exist". So this method refuses nothing and may return
 * fewer rows than it was asked about, which also lets a batch carry a stale
 * id from a page that has since changed.
 *
 * The gate is discoverability, not enrolment, because a category page lists
 * mostly courses the viewer is not enrolled in: core's visibility pass first,
 * the plugin's predicate after it.
 *
 * The course records are read in one statement for the batch; the
 * viewer's course completions in one more ({@see completed()}); the
 * activity completion reads after that are per course.
 *
 * Where Boost Union's course progress setting hides the progress
 * ({@see boost_union::show_progress()}), no percentage is read or returned:
 * progress is null and there is no progresslabel, so no card draws a progress
 * row. hascompletion still says whether completion is on in the course, a fact
 * about the course that the completed seal rests on and the setting leaves
 * drawn (D13 of the course card contract), and so does completed.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrichment {
    /** @var string Component name for get_string calls. */
    private const COMPONENT = 'theme_dimensions';

    /** @var int The most courses one batch may ask about; the client batches to the same number. */
    public const MAX_COURSES = 24;

    /**
     * What the cards of this batch can say once they are on screen.
     *
     * @param array $courseids The course ids of the batch.
     * @return array One row per id the viewer may see: id (int), hascompletion (bool), completed (bool),
     *   progress (int|null) and, when there is a number, progresslabel and progresspct (string).
     */
    public static function details(array $courseids): array {
        global $CFG, $DB, $USER;

        // The legacy completion_info class is not autoloadable and a web-service request loads
        // nothing that includes it; the PHPUnit bootstrap does, so tests pass without this line.
        // Pinned by get_card_details_test.
        require_once($CFG->libdir . '/completionlib.php');

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        if (!$courseids) {
            return [];
        }

        // One statement for the batch; nothing below reads a course record again.
        $records = $DB->get_records_list('course', 'id', $courseids);

        $visible = [];
        foreach ($records as $courseid => $course) {
            if (\core_course_category::can_view_course_info($course)) {
                $visible[(int) $courseid] = $course;
            }
        }
        $kept = access::filter_courses($visible);
        // Read after validate_context() in the web service, which sets the theme of the request.
        $showprogress = boost_union::show_progress();

        $enabled = [];
        foreach ($kept as $courseid => $course) {
            if ((new \completion_info($course))->is_enabled()) {
                $enabled[] = (int) $courseid;
            }
        }
        // Whatever the progress setting says: the completed seal stays when the row is off (D13).
        $done = self::completed($enabled, (int) $USER->id);

        $details = [];
        foreach ($courseids as $courseid) {
            if (!isset($kept[$courseid])) {
                continue;
            }
            $course = $kept[$courseid];
            $progress = $showprogress
                ? \core_completion\progress::get_course_progress_percentage($course, (int) $USER->id)
                : null;
            $row = [
                'id' => $courseid,
                'hascompletion' => in_array($courseid, $enabled, true),
                'completed' => isset($done[$courseid]),
                'progress' => $progress === null ? null : (int) round($progress),
            ];
            if ($row['progress'] !== null) {
                $row['progresslabel'] = get_string('category_progress', self::COMPONENT, $row['progress']);
                $row['progresspct'] = get_string('category_progress_pct', self::COMPONENT, $row['progress']);
            }
            $details[] = $row;
        }
        return $details;
    }

    /**
     * Which of these courses the user completed: one statement for the batch, whatever its size.
     *
     * It mirrors {@see \completion_info::is_course_complete()}, which reads one course_completions row per course: a
     * course counts only while completion is on in it (the caller passes those alone) and only once that row carries a
     * completion time, which is what {@see \completion_completion::is_complete()} asks. A course that turned
     * completion off keeps its rows, and they say nothing any more.
     *
     * @param array $courseids The ids of the courses with completion on, at most {@see MAX_COURSES}.
     * @param int $userid The user.
     * @return array courseid => true for every course the user completed; nothing is read for no course.
     */
    public static function completed(array $courseids, int $userid): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $params['userid'] = $userid;
        $courses = $DB->get_fieldset_select(
            'course_completions',
            'course',
            "userid = :userid AND course {$insql} AND timecompleted > 0",
            $params
        );
        $done = [];
        foreach ($courses as $courseid) {
            $done[(int) $courseid] = true;
        }
        return $done;
    }
}
