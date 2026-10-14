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
 * Theme Dimensions - The admitted subtree of one request
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

/**
 * The subtree one request may draw from, after the category-level gates: which categories are open,
 * which are clamped to the viewer's own courses, and what the viewer's enrolments are.
 *
 * Built by {@see population::scope()} at the start of every
 * {@see population::resolve()} and handed to each later step, so that the
 * classifier, the facets and the cards never re-derive who is asking or what
 * they may see. Plain data; the decisions are made where it is filled.
 *
 * A clamped category is one the viewer may not browse as such but may still
 * hold courses of their own in: core hides a category from a viewer without
 * moodle/category:viewhiddencategories, and local_unlistedcourses withholds an
 * unlisted one, yet both keep a course on the listing of somebody who is
 * enrolled in it (core: {@see \core_course_category::get_course_records()}'s
 * $mycourses bypass; the plugin: the relationship rescue of
 * {@see \local_unlistedcourses\access::filter_courses()}). So a clamped
 * category still enters the population statement, and each of its courses is
 * kept only when the rescue that matches the clamp applies.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope {
    /** @var int A core clamp: the category is hidden from this viewer, or they lack viewcourselist at it. */
    public const CLAMP_CORE = 1;

    /** @var int A plugin clamp: local_unlistedcourses withholds the category from this viewer. */
    public const CLAMP_PLUGIN = 2;

    /** @var int The category the request names. */
    public int $rootid;

    /** @var bool Whether the anonymous predicate applies (public courses only, no capability at all). */
    public bool $anonymous = false;

    /** @var int The viewer's id, 0 for a visitor. */
    public int $viewerid = 0;

    /**
     * @var \stdClass[] Every category of the subtree, root included, keyed by id: id, name, parent,
     *   sortorder, visible, path, depth and the context preload columns.
     */
    public array $categories = [];

    /** @var int[] The ids of the categories this viewer may browse outright. */
    public array $open = [];

    /** @var array The clamped categories, id => bitmask of the CLAMP_* constants. */
    public array $clamps = [];

    /** @var int[] The direct children of the root, in tree order, before any gate. */
    public array $children = [];

    /**
     * @var \stdClass[] The viewer's enrolments keyed by course id, in the shape of
     *   {@see classifier::enrolments()}. Empty for a visitor.
     */
    public array $enrolments = [];

    /**
     * The category ids the population statement lists: the open ones and the clamped ones.
     *
     * @return int[]
     */
    public function population_categories(): array {
        return array_values(array_unique(array_merge($this->open, array_map('intval', array_keys($this->clamps)))));
    }

    /**
     * Whether a category is open to this viewer.
     *
     * @param int $categoryid The category id.
     * @return bool
     */
    public function is_open(int $categoryid): bool {
        return in_array($categoryid, $this->open, true);
    }
}
