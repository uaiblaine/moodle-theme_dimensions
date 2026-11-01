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
 * Theme Dimensions - A source of calendar range items.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

/**
 * One source of the calendar range: core's events, the event courses, later a learning plan's trail.
 *
 * A source answers items in one shape ({@see range::ITEM_KEYS}), each with its key; the range asks a source
 * only when one of the keys it can produce is on, and drops any item whose key is off. A source decides
 * what the viewer may see itself, from the scope it is handed: the range adds no rule of visibility.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface source {
    /**
     * The keys this source's items can carry.
     *
     * @return string[] Keys of {@see keys::ALL}.
     */
    public function keys(): array;

    /**
     * The items this viewer may see in the window, for the keys that are on.
     *
     * @param window $window The days asked for.
     * @param scope $scope The viewer's calendar sets.
     * @param string[] $on The keys that are on.
     * @return array[] Items with exactly the keys of range::ITEM_KEYS.
     */
    public function items(window $window, scope $scope, array $on): array;
}
