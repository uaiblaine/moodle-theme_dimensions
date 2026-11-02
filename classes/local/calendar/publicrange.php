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
 * Theme Dimensions - One month grid of the public calendar, for a visitor.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use theme_dimensions\local\calendar\source\public_courses;
use theme_dimensions\local\calendar\source\public_events;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\publicaccess;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landingfields;

/**
 * The visitor's twin of {@see range::answer()}: the same shape, key for key, from the two visitor sources alone.
 *
 * Nothing here has a viewer: no scope, no enrolment, no capability, no $USER. The categories are the effective public
 * ones ({@see publicaccess::public_ids()}), asked once per answer; the courses are the public event courses
 * ({@see public_courses}). The grid, the drawer and the popover draw the answer unchanged, so the dictionaries keep
 * range's keys with the visitor's values:
 *
 * - a course's `url` and `landingurl` are both its landing page, the one course page a visitor can open; `image` is
 *   '' (core's course image is served from `course/overviewfiles`, which asks for a login under forcelogin);
 *   `participants` is null (how many people take part is not told to the internet); `enrolled` is false; `join` is
 *   'open', so the popover's primary action is the landing page's "Inscrever-se";
 * - a category is named only when it is one of the public ones, with its branding pair; a public course in another
 *   category keeps categoryid 0 in the dictionary;
 * - `descriptions` carries the category events' text (decision V2), which the logged-in answer leaves to core's own
 *   service.
 *
 * Nothing is cached across requests (the theme's D39, OD-5): every answer reads the plugin's current states.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class publicrange {
    /**
     * The whole answer for one window and the visitor's keys.
     *
     * @param window $window The days.
     * @param string[] $on The keys that are on; only the visitor's two ({@see visitor::KEYS}) ask anything.
     * @return array ['from', 'to', 'truncated', 'events', 'courses', 'categories', 'days', 'editing', 'descriptions'];
     *     `editing` is always empty: a visitor edits nothing, and the shape stays the range's.
     */
    public static function answer(window $window, array $on): array {
        $items = [];
        $descriptions = [];
        $truncated = false;
        $publicids = [];

        if (in_array(keys::CATEGORY, $on, true)) {
            $publicids = publicaccess::public_ids();
            $events = new public_events();
            $items = array_merge($items, $events->items($window, $publicids));
            $descriptions = $events->descriptions();
        }
        if (in_array(keys::OPEN, $on, true)) {
            $courses = new public_courses();
            $items = array_merge($items, $courses->items($window));
            $truncated = $courses->truncated();
            if (!$publicids) {
                $publicids = publicaccess::public_ids();
            }
        }
        usort($items, static fn(array $a, array $b): int => [$a['timestart'], $a['source'], $a['id']]
            <=> [$b['timestart'], $b['source'], $b['id']]);

        $public = array_flip($publicids);
        $courseids = [];
        $categoryids = [];
        foreach ($items as $item) {
            if ($item['source'] === public_courses::SOURCE) {
                $courseids[] = $item['courseid'];
            } else if (isset($public[$item['categoryid']])) {
                $categoryids[$item['categoryid']] = true;
            }
        }
        $courses = self::courses($courseids, $public);
        foreach ($courses as $course) {
            if ($course['categoryid']) {
                $categoryids[$course['categoryid']] = true;
            }
        }

        $texts = [];
        foreach ($items as $item) {
            if (isset($descriptions[$item['id']]) && $item['source'] === public_events::SOURCE) {
                $texts[] = ['id' => $item['id'], 'text' => $descriptions[$item['id']]];
            }
        }

        return [
            'from' => $window->from,
            'to' => $window->to,
            'truncated' => $truncated,
            'events' => $items,
            'courses' => $courses,
            'categories' => self::categories(array_keys($categoryids)),
            'days' => range::days($window, $items),
            'editing' => [],
            'descriptions' => $texts,
        ];
    }

    /**
     * The visitor's course dictionary: each public event course once, in one statement plus the custom fields'.
     *
     * @param int[] $courseids The courses the items name.
     * @param array $public The public category ids, as keys.
     * @return array[] The entries in range's course shape, in course id order.
     */
    private static function courses(array $courseids, array $public): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        $rows = $DB->get_records_list(
            'course',
            'id',
            array_values(array_unique($courseids)),
            'id',
            'id, category, fullname, shortname'
        );
        $values = landingfields::values_many(array_keys($rows), false, [
            landingfields::SHORTNAME_MODEL,
            landingfields::SHORTNAME_MODALITY,
            landingfields::SHORTNAME_CITY,
            landingfields::SHORTNAME_VENUE,
        ]);
        $courses = [];
        foreach ($rows as $row) {
            $courseid = (int) $row->id;
            $options = ['context' => \core\context\course::instance($courseid), 'escape' => false, 'filter' => false];
            $facts = $values[$courseid];
            $landing = links::page($courseid)->out(false);
            $categoryid = (int) $row->category;
            $courses[] = [
                'id' => $courseid,
                'fullname' => format_string((string) $row->fullname, true, $options),
                'shortname' => format_string((string) $row->shortname, true, $options),
                'categoryid' => isset($public[$categoryid]) ? $categoryid : 0,
                'url' => $landing,
                'image' => '',
                'model' => (int) $facts->model,
                'modality' => (int) $facts->modality,
                'city' => (string) ($facts->city ?? ''),
                'venue' => (string) ($facts->venue ?? ''),
                'landingurl' => $landing,
                'enrolled' => false,
                'participants' => null,
                'join' => 'open',
            ];
        }
        return $courses;
    }

    /**
     * The public categories the answer names, each with its branding pair.
     *
     * Read with alwaysreturnhidden: their visibility was decided by the plugin's predicate, and user id 0 holds no
     * capability under forcelogin.
     *
     * @param int[] $categoryids Public category ids.
     * @return array[] ['id', 'name', 'bg', 'ink'] each, in id order.
     */
    private static function categories(array $categoryids): array {
        if (!$categoryids) {
            return [];
        }
        sort($categoryids);
        $colours = branding::colours($categoryids);
        $categories = [];
        foreach ($categoryids as $categoryid) {
            $category = \core_course_category::get($categoryid, IGNORE_MISSING, true);
            if (!$category) {
                continue;
            }
            $categories[] = [
                'id' => (int) $categoryid,
                'name' => format_string($category->name, true, [
                    'context' => $category->get_context(),
                    'escape' => false,
                    'filter' => false,
                ]),
                'bg' => $colours[$categoryid]['bgcolour'],
                'ink' => $colours[$categoryid]['inkcolour'],
            ];
        }
        return $categories;
    }
}
