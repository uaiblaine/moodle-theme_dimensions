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
 * Theme Dimensions - A trail course and its steps.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\trail;

use theme_dimensions\local\landingfields;

/**
 * A trail: a course whose page model is {@see landingfields::MODEL_TRAIL}, and its steps, in the order the course shows them.
 *
 * The one reader of a trail's structure, for the landing page and the calendar's Trilhas view alike. Deciding that a
 * course IS a trail is the caller's, from its model; this class reads the steps of whatever course it is given. The
 * theme stores nothing of its own: the steps are the course's Subcourse activities, read from core's modinfo, and the
 * courses they refer to come from one statement for any number of trails.
 *
 * What comes back is structure, not an answer to any reader: it is the same for everybody, and it names
 * referenced courses whatever their visibility. A page that draws it first establishes that the reader may
 * see the trail course, then decides each step ({@see display}) - the referenced course's
 * own discoverability for this reader (never {@see step::$refvisible} alone), and core's per-user
 * visibility of the activity, so that a step whose restriction hides it entirely stays hidden - and
 * formats the stored name on output.
 *
 * mod_subcourse is an optional dependency: without it installed and enabled there are no
 * trails ({@see available()}), and the trail line of the model select is not offered
 * ({@see hides_option()}).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class trail {
    /** @var string The activity module whose instances are a trail's steps. */
    public const MODNAME = 'subcourse';

    /**
     * Build a trail; use {@see of()} or {@see many()}.
     *
     * @param int $courseid The trail course.
     * @param step[] $steps Its steps, in course order.
     */
    private function __construct(
        /** @var int The trail course. */
        public readonly int $courseid,
        /** @var step[] Its steps, in course order. */
        public readonly array $steps
    ) {
    }

    /**
     * Whether trails exist on this site: mod_subcourse is installed and enabled.
     *
     * An activity module is installed when core knows its directory and enabled when its row in
     * {modules} is visible; the second is one statement, read through the plugin manager.
     *
     * @return bool
     */
    public static function available(): bool {
        if (\core_component::get_component_directory('mod_' . self::MODNAME) === null) {
            return false;
        }
        $enabled = \core_plugin_manager::instance()->get_enabled_plugins('mod');
        return isset($enabled[self::MODNAME]);
    }

    /**
     * Whether a line of one of the theme's select fields is withheld from the choices offered on this site.
     *
     * Only the trail line of the page model, and only while trails are not {@see available()}. The line stays
     * in the field's configuration, because a select stores the position of its line; the places that offer
     * the lines leave it out: the course form, the default for new courses and the calendar's filters.
     *
     * @param string $shortname The select field's shortname.
     * @param int $value The line's stored value (1-based position).
     * @return bool
     */
    public static function hides_option(string $shortname, int $value): bool {
        return $shortname === landingfields::SHORTNAME_MODEL
            && $value === landingfields::MODEL_TRAIL
            && !self::available();
    }

    /**
     * The trail of one course.
     *
     * @param \stdClass $course The trail course (any record get_fast_modinfo() accepts).
     * @return self|null The trail, or null while trails are not available.
     */
    public static function of(\stdClass $course): ?self {
        return self::many([$course])[(int) $course->id] ?? null;
    }

    /**
     * The trails of several courses, with one statement for all of their steps.
     *
     * A step is a Subcourse activity that is visible, shown on the course page and not being deleted, in the
     * order get_fast_modinfo() lists the course's activities (section, then position). Its availability to a
     * given learner is a state the page draws, never a filter here. An activity whose referenced course is
     * unset or gone is no step: there is nothing to lead to. The referenced courses are read in the one
     * statement over {subcourse} joined to {course}; the modinfo is core's cache.
     *
     * @param \stdClass[] $courses The trail courses.
     * @return self[] Map of course id => trail, in the order given; empty while trails are not available.
     */
    public static function many(array $courses): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        if (!$courses || !self::available()) {
            return [];
        }

        $activities = [];
        $tracked = [];
        $instanceids = [];
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            $modinfo = get_fast_modinfo($course);
            $tracked[$courseid] = (new \completion_info($modinfo->get_course()))->is_enabled();
            $activities[$courseid] = [];
            foreach ($modinfo->get_cms() as $cm) {
                if ($cm->modname !== self::MODNAME || $cm->deletioninprogress || !$cm->visible || !$cm->visibleoncoursepage) {
                    continue;
                }
                $activities[$courseid][] = $cm;
                $instanceids[] = (int) $cm->instance;
            }
        }

        $refs = [];
        if ($instanceids) {
            [$insql, $params] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);
            $refs = $DB->get_records_sql(
                "SELECT s.id, s.refcourse, c.category, c.visible, c.startdate, c.enddate
                   FROM {subcourse} s
                   JOIN {course} c ON c.id = s.refcourse
                  WHERE s.id $insql",
                $params
            );
        }

        $trails = [];
        foreach ($activities as $courseid => $cms) {
            $steps = [];
            foreach ($cms as $cm) {
                $ref = $refs[$cm->instance] ?? null;
                if (!$ref) {
                    continue;
                }
                $expected = $tracked[$courseid] ? (int) $cm->completionexpected : 0;
                $steps[] = new step(
                    (int) $cm->id,
                    (string) $cm->name,
                    (int) $ref->refcourse,
                    (int) $ref->category,
                    (bool) $ref->visible,
                    (int) $ref->startdate,
                    $expected > 0 ? $expected : (int) $ref->enddate,
                    $expected > 0
                );
            }
            $trails[$courseid] = new self($courseid, $steps);
        }
        return $trails;
    }
}
