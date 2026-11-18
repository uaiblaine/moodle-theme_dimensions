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
 * Theme Dimensions - What the mobile drawer learns once it is shown
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * The deferred half of the mobile drawer ({@see drawernav}): the "Continue where you left off" card.
 *
 * It costs statements per course, which is why the page never pays them: the drawer asks for it through
 * theme_dimensions_get_drawer_summary the first time it is shown. Everything here is about the session's
 * own user and reads nothing of anybody else. The drawer counts nothing about the person (D77): the
 * summary carries the card alone.
 *
 * The card's rule:
 *
 * 1. The most recent item of block_recentlyaccesseditems, while that block is installed and enabled - its
 *    helper is the record of what this person opened. The helper already drops what the reader may not see,
 *    and the card decides again on the activity it is about to link ({@see self::from_recent_items()}): the
 *    course module as get_fast_modinfo() answers it now, uservisible, in a course the reader can access.
 *    The helper records nothing under "log in as", so that session falls through to step 2.
 * 2. Otherwise the course this person entered last (core's course_get_recent_courses(), its visible and
 *    enrolment rules).
 * 3. Otherwise no card.
 *
 * Either way the course's completion is added as "X of Y completed" while completion is enabled for the
 * course and this person is tracked in it - the counts core's own progress percentage is computed from
 * ({@see \core_completion\progress::get_course_progress_percentage()}).
 *
 * The card also says which kind of course it leads into (course, event or trail: the page model the course's
 * landing fields store), so the drawer draws that kind's glyph on the card's tile.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class drawersummary {
    /** @var string Component name, for the strings. */
    private const COMPONENT = 'theme_dimensions';

    /** @var string The block whose record of opened activities the card reads. */
    public const RECENT_BLOCK = 'recentlyaccesseditems';

    /** @var int How many of the helper's items the card looks through for one it may link. */
    public const RECENT_LIMIT = 5;

    /** @var array The card's kind for each page model; a course with no model reads as a course. */
    public const KINDS = [
        landingfields::MODEL_COURSE => 'course',
        landingfields::MODEL_EVENT => 'event',
        landingfields::MODEL_TRAIL => 'trail',
    ];

    /** @var string The kind of a course whose page model is unset or unknown. */
    public const KIND_DEFAULT = 'course';

    /**
     * The whole summary of the current user.
     *
     * @return array resume (see {@see self::empty_resume()}).
     */
    public static function build(): array {
        return [
            'resume' => self::resume(),
        ];
    }

    /**
     * The card: the most recent activity, else the most recent course, else nothing.
     *
     * @return array See {@see self::empty_resume()}.
     */
    public static function resume(): array {
        $activity = self::from_recent_items(self::recent_items());
        if ($activity !== null) {
            return $activity;
        }
        $course = self::recent_course();
        if ($course !== null) {
            return $course;
        }

        return self::empty_resume();
    }

    /**
     * The answer with no card.
     *
     * @return array hasitem (false), name, url, detail, kind, hascompletion, completed, total, percent.
     */
    public static function empty_resume(): array {
        return [
            'hasitem' => false,
            'name' => '',
            'url' => '',
            'detail' => '',
            'kind' => '',
            'hascompletion' => false,
            'completed' => 0,
            'total' => 0,
            'percent' => 0,
        ];
    }

    /**
     * The helper's most recent items, while block_recentlyaccesseditems is installed and enabled.
     *
     * Enabled is the block's own switch, {block}.visible, the list core's plugin information answers.
     *
     * @return array The helper's records, most recent first.
     */
    public static function recent_items(): array {
        if (!class_exists(\block_recentlyaccesseditems\helper::class)) {
            return [];
        }
        $enabled = \core\plugininfo\block::get_enabled_plugins();
        if (!isset($enabled[self::RECENT_BLOCK])) {
            return [];
        }

        return array_values(\block_recentlyaccesseditems\helper::get_recent_items(self::RECENT_LIMIT));
    }

    /**
     * The card of the first item the reader may open now, or null.
     *
     * Decided on the course module this card links, not on the helper's say-so: the helper filtered its
     * list when it was built, and the card asks again of the cm it is about to print.
     *
     * @param array $items Records with courseid and cmid, the helper's shape.
     * @return array|null See {@see self::empty_resume()}.
     */
    public static function from_recent_items(array $items): ?array {
        global $USER;

        foreach ($items as $item) {
            $modinfo = get_fast_modinfo((int) $item->courseid);
            $cm = $modinfo->get_cms()[(int) $item->cmid] ?? null;
            if ($cm === null || !$cm->uservisible || $cm->url === null) {
                continue;
            }
            $course = $modinfo->get_course();
            if (!can_access_course($course, null, '', true)) {
                continue;
            }
            $coursename = self::course_name($course);
            $completion = self::completion($course, (int) $USER->id);

            return self::card(
                format_string($cm->name, true, ['context' => $cm->context, 'escape' => false]),
                $cm->url->out(false),
                [$coursename],
                $completion,
                self::kind((int) $course->id)
            );
        }

        return null;
    }

    /**
     * The card of the course this person entered last, or null.
     *
     * @return array|null See {@see self::empty_resume()}.
     */
    public static function recent_course(): ?array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/course/lib.php');

        $courses = course_get_recent_courses((int) $USER->id, 1);
        $course = reset($courses);
        if (!$course) {
            return null;
        }
        $course = get_course((int) $course->id);
        $completion = self::completion($course, (int) $USER->id);

        return self::card(
            self::course_name($course),
            (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            [],
            $completion,
            self::kind((int) $course->id)
        );
    }

    /**
     * The completed and total activities of a course for one person, or null when there is no number.
     *
     * Null while completion is off for the course or the person is not tracked in it, and for a course with
     * no activity they can complete. The counts are the ones core's progress percentage divides.
     *
     * @param \stdClass $course The course record.
     * @param int $userid The person.
     * @return array|null completed and total.
     */
    public static function completion(\stdClass $course, int $userid): ?array {
        global $CFG;
        // A web service request loads no completion library of its own.
        require_once($CFG->libdir . '/completionlib.php');

        $info = new \completion_info($course);
        if (!$info->is_enabled() || !$info->is_tracked_user($userid)) {
            return null;
        }
        $activities = $info->get_user_activities_with_completion($userid);
        if (!$activities) {
            return null;
        }

        return [
            'completed' => $info->count_modules_completed($userid, array_keys($activities)),
            'total' => count($activities),
        ];
    }

    /**
     * The kind of a course, for the glyph on the card's tile: one of {@see self::KINDS}' values.
     *
     * @param int $courseid The course.
     * @return string course, event or trail.
     */
    public static function kind(int $courseid): string {
        return self::KINDS[landingfields::model($courseid)] ?? self::KIND_DEFAULT;
    }

    /**
     * A course's name as the card prints it, in the plain spelling.
     *
     * @param \stdClass $course The course record.
     * @return string
     */
    private static function course_name(\stdClass $course): string {
        return format_string(
            get_course_display_name_for_list($course),
            true,
            ['context' => \context_course::instance($course->id), 'escape' => false]
        );
    }

    /**
     * Assemble a card.
     *
     * The line under the title is composed here, never in the browser: the course's name (for an
     * activity) and the completion sentence, each in the plain spelling for the client's textContent.
     *
     * @param string $name The title, plain.
     * @param string $url The address, plain.
     * @param string[] $parts What the line under the title starts with.
     * @param array|null $completion completed and total, or null.
     * @param string $kind The course's kind ({@see self::kind()}).
     * @return array See {@see self::empty_resume()}.
     */
    private static function card(string $name, string $url, array $parts, ?array $completion, string $kind): array {
        $card = self::empty_resume();
        $card['hasitem'] = true;
        $card['kind'] = $kind;
        $card['name'] = $name;
        $card['url'] = $url;
        if ($completion !== null) {
            $card['hascompletion'] = true;
            $card['completed'] = (int) $completion['completed'];
            $card['total'] = (int) $completion['total'];
            // Floored, never rounded: a course one activity short of the end must not read as 100%.
            $card['percent'] = intdiv(100 * $card['completed'], $card['total']);
            $parts[] = get_string('drawer_progress', self::COMPONENT, (object) [
                'completed' => $card['completed'],
                'total' => $card['total'],
            ]);
        }
        $card['detail'] = implode(' · ', array_filter($parts, fn(string $part): bool => $part !== ''));

        return $card;
    }
}
