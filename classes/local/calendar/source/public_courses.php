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
 * Theme Dimensions - The public event courses, for a visitor.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar\source;

use local_unlistedcourses\discoverability;
use theme_dimensions\local\calendar\keys;
use theme_dimensions\local\calendar\range;
use theme_dimensions\local\calendar\visitor;
use theme_dimensions\local\calendar\window;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landing\publicaccess;
use theme_dimensions\local\landingfields;

/**
 * The window's event courses whose landing page a visitor may open: one bar each, key "open".
 *
 * The visitor's version of {@see event_courses}' open layer, with no viewer in it. An event course is a course of the
 * event model (`dimensions_model` = {@see landingfields::MODEL_EVENT}, a stored row, as there) whose dates meet the
 * window. Who may be shown one is the landing page's own gate, {@see publicaccess::are_public()}: the course's own
 * state public, the course visible, every category on its path visible and none unlisted. The statement reads only
 * the courses whose own state row says public (the cheap half of that gate, so withheld courses cost no place among
 * the candidates), at most {@see event_courses::OPEN_CANDIDATES}, earliest start first; the gate decides on them, and
 * at most {@see event_courses::OPEN_CAP} are answered. {@see truncated()} says when more may have been left out.
 *
 * The showcase's switches ({@see \theme_dimensions\local\category\publicaccess::enabled()}) do not apply here, on
 * purpose: they close the public category pages, and the category events with them, while a public course's landing
 * page stays open to visitors whatever they say. A bar names only what that page already shows.
 *
 * Each item leads to the landing page ({@see links::page()}), whose enrolment is the way in; the name is
 * {@see visitor::name()} in the course's context: core's multilang filter alone.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class public_courses {
    /** @var string The source name items carry, as the logged-in layer's. */
    public const SOURCE = event_courses::SOURCE;

    /** @var bool Whether public event courses may have been left out past the cap or the candidate bound. */
    private bool $truncated = false;

    /**
     * Whether the last call left public event courses out.
     *
     * @return bool
     */
    public function truncated(): bool {
        return $this->truncated;
    }

    /**
     * The window's public event courses, as items.
     *
     * @param window $window The days asked for.
     * @return array[] Items with exactly the keys of range::ITEM_KEYS, earliest first.
     */
    public function items(window $window): array {
        global $DB;

        $this->truncated = false;
        $field = landingfields::get_field(landingfields::SHORTNAME_MODEL);
        if (!$field) {
            return [];
        }
        $state = discoverability::state_sql('c');
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.startdate, c.enddate
               FROM {course} c
               JOIN {customfield_data} d ON d.instanceid = c.id
                    {$state['join']}
              WHERE d.fieldid = :modelfield AND d.component = :modelcomponent AND d.area = :modelarea AND d.itemid = 0
                    AND d.intvalue = :eventmodel AND c.id <> :siteid AND c.startdate > 0 AND c.startdate <= :windowend
                    AND (CASE WHEN c.enddate > c.startdate THEN c.enddate ELSE c.startdate END) >= :windowstart
                    AND {$state['column']} = :publicstate
           ORDER BY c.startdate, c.id",
            [
                'modelfield' => (int) $field->get('id'),
                'modelcomponent' => 'core_course',
                'modelarea' => 'course',
                'eventmodel' => landingfields::MODEL_EVENT,
                'siteid' => SITEID,
                'windowend' => $window->tend,
                'windowstart' => $window->tstart,
                'publicstate' => discoverability::STATE_PUBLIC,
            ],
            0,
            event_courses::OPEN_CANDIDATES + 1
        );
        $bound = count($rows) > event_courses::OPEN_CANDIDATES;
        if ($bound) {
            $rows = array_slice($rows, 0, event_courses::OPEN_CANDIDATES, true);
        }

        $public = array_flip(publicaccess::are_public(array_keys($rows)));
        $kept = array_values(array_filter($rows, static fn(\stdClass $row): bool => isset($public[(int) $row->id])));
        $surplus = count($kept) > event_courses::OPEN_CAP;
        if ($surplus) {
            $kept = array_slice($kept, 0, event_courses::OPEN_CAP);
        }
        $this->truncated = $surplus || $bound;
        return array_map([self::class, 'item'], $kept);
    }

    /**
     * One public event course as an item.
     *
     * @param \stdClass $row The course row.
     * @return array An item with exactly the keys of range::ITEM_KEYS.
     */
    private static function item(\stdClass $row): array {
        $courseid = (int) $row->id;
        $start = (int) $row->startdate;
        $end = (int) $row->enddate;
        return range::item([
            'id' => $courseid,
            'source' => self::SOURCE,
            'key' => keys::OPEN,
            'name' => visitor::name((string) $row->fullname, \core\context\course::instance($courseid)),
            'timestart' => $start,
            'timeduration' => max(0, $end - $start),
            'courseid' => $courseid,
            'categoryid' => 0,
            'groupid' => 0,
            'modulename' => '',
            'instance' => 0,
            'component' => '',
            'eventtype' => '',
            'location' => '',
            'url' => links::page($courseid)->out(false),
            'actionevent' => false,
            'action' => null,
            'overdue' => false,
            'held' => $end > 0 && $end < time(),
        ]);
    }
}
