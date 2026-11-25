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
 * Theme Dimensions - The class distribution of a course's competencies, kept in a shared cache under revisions
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

/**
 * Where the course competencies page's class distribution is kept, and when it stops being the answer.
 *
 * The figures ({@see coursecompetencies::aggregate()}, one statement) are the same for every staff viewer of a course, so
 * they are kept in the MUC application cache `coursecompetencyclass`, shared by every web node, keyed by the course id.
 * The value carries what it was built for: the course's revision and the site's (both kept in the MUC application cache
 * `coursecompetencyrev`, as the public calendar keeps its tokens in `publiccalendarrev`), the course's competency ids,
 * and the time it was built. A read answers from the cache only when all four still hold, and otherwise rebuilds from
 * the database and writes the new value; so a cold node, a fresh cron container or a purged cache answers the same, one
 * statement later. Nothing is node-local and nothing static is the source of truth.
 *
 * - The course's revision is minted anew by every event that changes the figures of that course ({@see course_changed()},
 *   {@see role_changed()} in the course's context): a rating in the course, an evidence in the
 *   course or one of its activities (which is how a completion rates: core's completion observers add the evidence), an
 *   enrolment created or deleted, a role or a capability override in the course, a reset of the course's ratings.
 * - The site's revision is minted by what changes the enrolled set of every course at once ({@see site_changed()},
 *   {@see role_changed()} above the course): a role or capability changed in a category or the system context, a role
 *   deleted, a user deleted.
 * - A course competency added or removed fires no event in core: the competency ids in the value are compared with the
 *   course's own on every read instead (the recorded fact, one cheap statement).
 * - A value older than {@see ROTATION} is rebuilt anyway, for the writes no event announces (a direct SQL update, an
 *   import that bypasses the APIs).
 *
 * The revisions are read BEFORE the figures are built and stored with them, so a bump that lands while a request
 * builds makes that request's value stale at once. They are random, as the public calendar's: MUC has no atomic
 * increment, and a revision needs only to differ from the last. Every observer runs after the write commits
 * (`'internal' => false` in db/events.php), does one cache write and nothing else, and never throws into core's event.
 *
 * The cache holds counts per competency and nothing else: no user id, no name. It is not personal data.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class courseclass {
    /** @var string The site's revision key. */
    public const SITE = 'site';

    /** @var int How long a value stands without an event, in seconds. */
    public const ROTATION = HOURSECS;

    /** @var string The MUC definition of the figures. */
    private const COUNTS = 'coursecompetencyclass';

    /** @var string The MUC definition of the revisions. */
    private const REVISIONS = 'coursecompetencyrev';

    /**
     * The class distribution of a course: from the shared cache while it holds, rebuilt otherwise.
     *
     * @param int $courseid The course, never the site course.
     * @return array competency id => ['learners', 'rated', 'proficient'], as {@see coursecompetencies::aggregate()}.
     */
    public static function counts(int $courseid): array {
        global $DB;

        $now = \core\di::get(\core\clock::class)->time();
        $competencies = $DB->get_fieldset_select('competency_coursecomp', 'competencyid', 'courseid = ?', [$courseid]);
        $competencies = array_map('intval', $competencies);
        sort($competencies);
        $competencies = implode(',', $competencies);
        // Read before the build: a bump landing while the figures are built makes this value stale at once.
        $course = self::revision(self::course_key($courseid));
        $site = self::revision(self::SITE);

        $cache = \cache::make('theme_dimensions', self::COUNTS);
        $stored = $cache->get($courseid);
        $fresh = is_array($stored)
            && ($stored['course'] ?? null) === $course
            && ($stored['site'] ?? null) === $site
            && ($stored['competencies'] ?? null) === $competencies
            && $now - (int) ($stored['built'] ?? 0) < self::ROTATION
            && is_array($stored['counts'] ?? null);
        if ($fresh) {
            return $stored['counts'];
        }

        $counts = coursecompetencies::aggregate($courseid);
        $cache->set($courseid, [
            'course' => $course,
            'site' => $site,
            'competencies' => $competencies,
            'built' => $now,
            'counts' => $counts,
        ]);

        return $counts;
    }

    /**
     * A revision: the one stored, or a new one when there is none (a cold cache, a purge).
     *
     * @param string $key {@see SITE} or a course's key.
     * @return string Eight lower-case hexadecimal characters.
     */
    public static function revision(string $key): string {
        $stored = \cache::make('theme_dimensions', self::REVISIONS)->get($key);
        if (is_string($stored) && $stored !== '') {
            return $stored;
        }
        return self::bump($key);
    }

    /**
     * Mint a new revision: every value built under the old one is stale from now on.
     *
     * @param string $key {@see SITE} or a course's key.
     * @return string The new revision.
     */
    public static function bump(string $key): string {
        $revision = bin2hex(random_bytes(4));
        \cache::make('theme_dimensions', self::REVISIONS)->set($key, $revision);
        return $revision;
    }

    /**
     * A course's revision key: letters and digits, as MUC's simple keys want.
     *
     * @param int $courseid The course.
     * @return string
     */
    public static function course_key(int $courseid): string {
        return 'c' . $courseid;
    }

    /**
     * A rating in the course, an evidence, an enrolment created or deleted, or the course's ratings reset: the course's
     * figures.
     *
     * An evidence in the course's context or one of its activities' may have rated the learner there (core's
     * `api::add_evidence()` writes `competency_usercompcourse` for those two levels only); an evidence anywhere else (a
     * plan's, in the user's context) carries no course, and bumps nothing.
     *
     * @param \core\event\base $event competency_user_competency_rated_in_course, competency_evidence_created,
     *     user_enrolment_created, user_enrolment_deleted or course_reset_ended.
     * @return void
     */
    public static function course_changed(\core\event\base $event): void {
        try {
            if ((int) $event->courseid > 0) {
                self::bump(self::course_key((int) $event->courseid));
            }
        } catch (\Throwable $exception) {
            self::report($event, $exception);
        }
    }

    /**
     * A role assigned or unassigned, or a capability overridden or reset: in the course, the course's figures; in a
     * category or the system context (a role's definition included), every course's; below the course (an activity, a
     * block, a user), nothing, since the learners are judged in the course's context.
     *
     * @param \core\event\base $event role_assigned, role_unassigned, capability_assigned or capability_unassigned.
     * @return void
     */
    public static function role_changed(\core\event\base $event): void {
        try {
            $level = (int) $event->contextlevel;
            if ($level === CONTEXT_COURSE && (int) $event->courseid > 0) {
                self::bump(self::course_key((int) $event->courseid));
            } else if ($level === CONTEXT_SYSTEM || $level === CONTEXT_COURSECAT) {
                self::bump(self::SITE);
            }
        } catch (\Throwable $exception) {
            self::report($event, $exception);
        }
    }

    /**
     * A user deleted or a role deleted: every course's figures.
     *
     * @param \core\event\base $event user_deleted or role_deleted.
     * @return void
     */
    public static function site_changed(\core\event\base $event): void {
        try {
            self::bump(self::SITE);
        } catch (\Throwable $exception) {
            self::report($event, $exception);
        }
    }

    /**
     * Say that a revision could not be minted: a stale figure must never stop core's write.
     *
     * @param \core\event\base $event The event observed.
     * @param \Throwable $exception What went wrong.
     * @return void
     */
    private static function report(\core\event\base $event, \Throwable $exception): void {
        debugging(
            'theme_dimensions: the class distribution of the course competencies page could not be marked stale after '
                . $event->eventname . ': ' . $exception->getMessage(),
            DEBUG_DEVELOPER
        );
    }
}
