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
 * Theme Dimensions - One window of the public calendar, for a visitor.
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
 * ones ({@see publicaccess::public_ids()}); the courses are the public event courses ({@see public_courses}). The grid,
 * the drawer and the popover draw the answer unchanged, so the dictionaries keep range's keys with the visitor's values:
 *
 * - a course's `url` and `landingurl` are both its landing page, the one course page a visitor can open; `image` is
 *   '' (core's course image is served from `course/overviewfiles`, which asks for a login under forcelogin);
 *   `participants` is null (how many people take part is not told to the internet); `enrolled` is false; `join` is
 *   'open', so the popover's primary action is "Enrol" (`calendar_join_open`), leading to the landing page;
 * - a category is named only when it is one of the public ones, with its branding pair; a public course in another
 *   category keeps categoryid 0 in the dictionary;
 * - `descriptions` carries the category events' text, which the logged-in answer leaves to core's own
 *   service.
 *
 * The answer is two halves that share no item, each answering one key: {@see events()} (the key "category") and
 * {@see courses()} (the key "open"). Each is complete in that shape on its own, so a half is the whole answer of its
 * key, and {@see merge()} is the answer of both, exactly as the client merges the two it fetches
 * (`js/esm/src/calendar/publichalves.ts`, which must stay in step with it). {@see answer()} reads the halves through
 * {@see publiccache}, the shared cache the public route and the page both read.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class publicrange {
    /**
     * The whole answer for one window and the visitor's keys, from the cached halves.
     *
     * @param window $window The days.
     * @param string[] $on The keys that are on; only the visitor's two ({@see visitor::KEYS}) ask anything.
     * @return array ['from', 'to', 'truncated', 'events', 'courses', 'categories', 'days', 'editing', 'descriptions'];
     *     `editing` is always empty: a visitor edits nothing, and the shape stays the range's.
     */
    public static function answer(window $window, array $on): array {
        $halves = [];
        foreach (publiccache::HALVES as $half) {
            if (in_array(publiccache::KEY_OF[$half], $on, true)) {
                $halves[] = publiccache::half($half, $window);
            }
        }
        return self::merge($window, $halves);
    }

    /**
     * The events' half: the window's category events of the public categories, their categories and descriptions.
     *
     * @param window $window The days.
     * @return array The answer's shape, with the key "category" alone counted and no course.
     */
    public static function events(window $window): array {
        $publicids = publicaccess::public_ids();
        $source = new public_events();
        $items = $source->items($window, $publicids);
        $descriptions = $source->descriptions();

        $public = array_flip($publicids);
        $categoryids = [];
        $texts = [];
        foreach ($items as $item) {
            if (isset($public[$item['categoryid']])) {
                $categoryids[$item['categoryid']] = true;
            }
            if (isset($descriptions[$item['id']])) {
                $texts[] = ['id' => $item['id'], 'text' => $descriptions[$item['id']]];
            }
        }
        return self::shape($window, $source->truncated(), $items, [], self::categories(array_keys($categoryids)), $texts);
    }

    /**
     * The courses' half: the window's public event courses, their dictionary and the public categories they sit in.
     *
     * @param window $window The days.
     * @return array The answer's shape, with the key "open" alone counted and no description.
     */
    public static function courses(window $window): array {
        $source = new public_courses();
        $items = $source->items($window);
        $courseids = array_map(static fn(array $item): int => $item['courseid'], $items);
        $public = $courseids ? array_flip(publicaccess::public_ids()) : [];
        $courses = self::courses_of($courseids, $public);
        $named = [];
        $categoryids = [];
        foreach ($courses as $course) {
            $named[$course['id']] = true;
            if ($course['categoryid']) {
                $categoryids[$course['categoryid']] = true;
            }
        }
        // A course gone between the statements has no dictionary entry: drop its items, as range::answer() does.
        $items = array_values(array_filter($items, static fn(array $item): bool => isset($named[$item['courseid']])));
        return self::shape($window, $source->truncated(), $items, $courses, self::categories(array_keys($categoryids)), []);
    }

    /**
     * The answer of the halves given: one half is its own answer; two are merged as the client merges them.
     *
     * Items sorted by start, source and id; the courses, the descriptions and the categories of both (the categories
     * once each, by id); truncated when either is; the days counted again over the merged items, which is each key's
     * count from the half that answers it.
     *
     * @param window $window The days.
     * @param array[] $halves The halves, in {@see publiccache::HALVES} order.
     * @return array The answer.
     */
    public static function merge(window $window, array $halves): array {
        if (count($halves) === 1) {
            return reset($halves);
        }
        $items = [];
        $courses = [];
        $categories = [];
        $texts = [];
        $truncated = false;
        foreach ($halves as $half) {
            $items = array_merge($items, $half['events']);
            $courses = array_merge($courses, $half['courses']);
            $texts = array_merge($texts, $half['descriptions']);
            $truncated = $truncated || $half['truncated'];
            foreach ($half['categories'] as $category) {
                $categories[$category['id']] = $category;
            }
        }
        usort($items, static fn(array $a, array $b): int => [$a['timestart'], $a['source'], $a['id']]
            <=> [$b['timestart'], $b['source'], $b['id']]);
        ksort($categories);
        return self::shape($window, $truncated, $items, $courses, array_values($categories), $texts);
    }

    /**
     * An answer in range's shape.
     *
     * @param window $window The days.
     * @param bool $truncated Whether items were left out.
     * @param array[] $items The items, sorted.
     * @param array[] $courses The course dictionary.
     * @param array[] $categories The category dictionary.
     * @param array[] $texts The descriptions, ['id', 'text'] each.
     * @return array
     */
    private static function shape(
        window $window,
        bool $truncated,
        array $items,
        array $courses,
        array $categories,
        array $texts
    ): array {
        return [
            'from' => $window->from,
            'to' => $window->to,
            'truncated' => $truncated,
            'events' => $items,
            'courses' => $courses,
            'categories' => $categories,
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
    private static function courses_of(array $courseids, array $public): array {
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
            $context = \core\context\course::instance($courseid);
            $facts = $values[$courseid];
            $landing = links::page($courseid)->out(false);
            $categoryid = (int) $row->category;
            $courses[] = [
                'id' => $courseid,
                'fullname' => visitor::name((string) $row->fullname, $context),
                'shortname' => visitor::name((string) $row->shortname, $context),
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
                'name' => visitor::name((string) $category->name, $category->get_context()),
                'bg' => $colours[$categoryid]['bgcolour'],
                'ink' => $colours[$categoryid]['inkcolour'],
            ];
        }
        return $categories;
    }
}
