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
 * Theme Dimensions - One resolved page of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

/**
 * One resolved page: the rows to draw, the numbers to print, the chips to offer.
 *
 * Plain data, filled by {@see population::resolve()} and read by whatever
 * renders it. Every number in it is post-filter - counted after the core
 * visibility gate and after local_unlistedcourses - so a withheld course moves
 * no number, on any surface.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class page_result {
    /**
     * @var \stdClass[] The page slice, in display order, keyed by course id. Each row carries id,
     *   category, sortorder, visible, fullname, startdate, liststate (the discoverability state)
     *   and - for a logged-in viewer - state (a classifier constant), favourite (bool), startsat
     *   and endsat (int). The context preload columns are kept on the row so a per-page reader can
     *   rebuild the course context without a statement.
     */
    public array $rows = [];

    /**
     * @var int How many courses this page lists, every filter applied: what the count line prints
     *   and what the pager walks. In the grouped organisation that is the courses sitting
     *   directly in the listed category ({@see population::partitions()}), which is fewer than
     *   $counts['total'] whenever a child holds one; in a flat organisation, and on a request
     *   that asked for the direct courses outright, the two are the same number. At the root,
     *   which lists no course, it is the whole subtree's count.
     */
    public int $total = 0;

    /** @var int The zero-based page this holds. */
    public int $page = 0;

    /** @var int The page size the slice was cut with. */
    public int $pagesize = 48;

    /** @var bool Whether a page follows this one, computed post-filter. */
    public bool $hasmore = false;

    /**
     * @var array Histogram of the population before the state chip is applied: 'total' and, for a
     *   logged-in viewer with the state sort on, one entry per classifier state plus 'favourites'.
     *   Always the whole subtree, grouped or not: the hero's total and the status pills must not
     *   move when the reader changes how the page is organised, which is why they are counted
     *   here and never taken from $total.
     */
    public array $counts = [];

    /** @var array Facet groups: fieldid => ['name' => string, 'options' => [value => ['label' => string, 'count' => int]]]. */
    public array $facets = [];

    /**
     * @var array The subcategories this viewer may be offered, depth-first, each entry followed by
     *   its own descendants: [['id' => int, 'name' => string, 'depth' => int, 'linkable' => bool], ...].
     *   'depth' is 1 for a direct child of the root and is what the navigation tree indents by; the
     *   walk stops at {@see population::tree_depth()} levels, which bounds the navigation and never
     *   the listing.
     */
    public array $subcategories = [];

    /**
     * @var array The accordion of the grouped organisation: one entry per direct child of the listed
     *   category, each ['id' => int, 'name' => string, 'count' => int, 'url' => string,
     *   'linkable' => bool] - the set the posters are drawn from. Empty on a flat organisation (a
     *   panel's own request among them), at the root at rest and on a request that asked for the
     *   direct courses. At the root under a search it is one entry per poster of
     *   {@see $directory}, each counting that area's matches. The count spans the child's whole
     *   subtree, post-filter, and carries the population's probe cap, so the entries and $total
     *   partition the subtree between them. The set does not follow a criterion: for a visitor it
     *   is the children holding a public course at rest ({@see population::accordion_tree()}), so
     *   a child the criterion misses is an entry counting 0 rather than a missing one.
     */
    public array $accordion = [];

    /**
     * @var array The root's directory: one entry per admitted top-level category, in the shape of
     *   {@see population::directory()} - the visual a poster draws. Filled at the root of the
     *   course index, which is a directory rather than a listing, and empty on every other page.
     *   At rest no entry carries a number: a poster names a category and says nothing about how
     *   much is inside it. Under a search each entry carries 'count', how many matches its area
     *   holds, which is the pill it then wears.
     */
    public array $directory = [];

    /**
     * @var array The posters of the grouped page: one entry per accordion entry, in the
     *   accordion's order, each the directory card's visual ({@see population::directory()}) plus
     *   count (the accordion's post-filter subtree count), linkable, istext (an anonymous visitor's
     *   merely listed child, drawn as text: no cover, no film, no crest, no watermark) and
     *   expandable (whether the poster opens a panel rather than linking to the area's page). Empty
     *   on a flat organisation, at the root (whose posters are {@see $directory}), and on any request
     *   that did not ask for them ({@see criteria::$withposters}).
     */
    public array $posters = [];

    /**
     * @var array The lean strip of a panel request: one entry per admitted direct child of
     *   the listed category, each ['id' => int, 'name' => plain string, 'depth' => 1, 'linkable' => bool,
     *   'url' => string ('' where there is no page to open), 'style' => '--dim-dot: #rrggbb;']. Filled
     *   only for a request carrying {@see criteria::$withcategories}, from rows already in hand, so it
     *   costs no statement per subarea. Under a criterion it holds only the subareas with a result,
     *   each url carrying the request's narrowing parameters.
     */
    public array $subareas = [];

    /**
     * @var page_result|null The listing of the open panel, resolved on the server for a page whose
     *   {@see criteria::$open} survived admission; null when no panel is open. At most one per request.
     */
    public ?page_result $panel = null;

    /**
     * @var array|null The poster entry the open panel belongs to (one of {@see $posters}, or of
     *   {@see $directory} at the root); null when no panel is open.
     */
    public ?array $panelentry = null;

    /**
     * @var string[] categoryid => plain name, for every category a row of the page belongs to.
     *   Filled from the scope's own rows, so a card can print its subcategory without a statement.
     */
    public array $categorynames = [];

    /**
     * @var int How many courses of the population are hidden from students - only a viewer the
     *   population admitted hidden courses to has any; 0 in the SQL-cut mode, which reads no
     *   population row. The hero's third number.
     */
    public int $hidden = 0;

    /** @var bool Whether the rows are grouped by state first (the categorystatesort setting) or by core order alone. */
    public bool $statesort = true;

    /** @var bool Whether the anonymous predicate produced this page. */
    public bool $anonymous = false;

    /**
     * The course ids of the page, in display order.
     *
     * @return int[]
     */
    public function ids(): array {
        return array_map('intval', array_keys($this->rows));
    }
}
