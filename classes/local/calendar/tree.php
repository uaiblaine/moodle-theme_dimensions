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
 * Theme Dimensions - The categories the calendar's category picker offers.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use local_unlistedcourses\category_access;
use theme_dimensions\local\category\branding;

/**
 * The viewer's calendar categories and their ancestors, as a tree, each with its name and branding pair.
 *
 * The categories are the ones core's own calendar reads category events from for the site view (the scope's
 * categories: those holding one of the viewer's calendar courses, those the viewer manages, and their parents) and
 * the categories of the viewer's calendar courses. A category is offered only where core lets the viewer see it
 * ({@see \core_course_category::get()} without $alwaysreturnhidden answers null otherwise) and
 * local_unlistedcourses does not withhold it, the two gates the range's narrowing asks ({@see scope::for_viewer()}),
 * so the picker never names a category the range would refuse. A category whose parent is not offered hangs from
 * its nearest offered ancestor, or from the top: the tree shows where a category sits without naming what it may
 * not.
 *
 * The pair is {@see branding::colours()}, one statement for all of them, taken whole.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tree {
    /**
     * The tree, flattened in display order: each node after its parent, siblings in core's sort order.
     *
     * @param scope $scope The viewer's sets, NOT narrowed: the picker offers every category the viewer could narrow to.
     * @return array[] ['id', 'name', 'parent', 'depth', 'bg', 'ink', 'courses'] each; parent 0 for the top.
     */
    public static function for_scope(scope $scope): array {
        $courses = [];
        foreach ($scope->courses as $course) {
            if ((int) $course->id !== (int) SITEID) {
                $courses[(int) $course->category] = ($courses[(int) $course->category] ?? 0) + 1;
            }
        }
        $wanted = array_merge(array_keys($courses), array_map('intval', $scope->categories ?? []));

        $found = [];
        foreach (array_unique($wanted) as $categoryid) {
            if ($categoryid <= 0 || isset($found[$categoryid])) {
                continue;
            }
            $category = \core_course_category::get($categoryid, IGNORE_MISSING);
            if (!$category) {
                continue;
            }
            $found[$categoryid] = $category;
            foreach ($category->get_parents() as $parentid) {
                if (!array_key_exists((int) $parentid, $found)) {
                    $found[(int) $parentid] = \core_course_category::get((int) $parentid, IGNORE_MISSING);
                }
            }
        }
        $found = category_access::filter_categories(array_filter($found));
        if (!$found) {
            return [];
        }

        $colours = branding::colours(array_keys($found));
        $children = [];
        foreach ($found as $categoryid => $category) {
            // The nearest ancestor that is offered, else the top.
            $parent = 0;
            foreach (array_reverse($category->get_parents()) as $ancestorid) {
                if (isset($found[(int) $ancestorid])) {
                    $parent = (int) $ancestorid;
                    break;
                }
            }
            $children[$parent][] = $category;
        }
        foreach ($children as &$siblings) {
            usort($siblings, static fn($a, $b): int => [(int) $a->sortorder, (int) $a->id] <=> [(int) $b->sortorder, (int) $b->id]);
        }
        unset($siblings);

        $nodes = [];
        $walk = static function (int $parent, int $depth) use (&$walk, &$nodes, $children, $colours, $courses): void {
            foreach ($children[$parent] ?? [] as $category) {
                $categoryid = (int) $category->id;
                $nodes[] = [
                    'id' => $categoryid,
                    'name' => format_string($category->name, true, ['context' => $category->get_context(), 'escape' => false]),
                    'parent' => $parent,
                    'depth' => $depth,
                    'bg' => $colours[$categoryid]['bgcolour'],
                    'ink' => $colours[$categoryid]['inkcolour'],
                    'courses' => $courses[$categoryid] ?? 0,
                ];
                $walk($categoryid, $depth + 1);
            }
        };
        $walk(0, 0);
        return $nodes;
    }
}
