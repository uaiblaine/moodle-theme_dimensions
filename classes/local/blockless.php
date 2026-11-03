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
 * Theme Dimensions - A page of the theme's own that offers no block region
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * Leave a page without any block: no block content, no block drawer, no "Add a block" button.
 *
 * Its callers are the calendar page ({@see calendar\page}) and the course landing page
 * ({@see landing\request}), each before the header. Two switches of core, both needed.
 * {@see \block_manager::show_only_fake_blocks()} loads no block instance and creates none that the theme
 * requires, so no region has content; it does not touch the add-block control, which core's renderer draws
 * in edit mode from the page's regions alone. {@see \moodle_page::force_lock_all_blocks()} makes
 * user_can_edit_blocks() false for everyone, which empties that control. The layout (drawers.php) draws
 * the block drawer and its toggle only when a region holds block markup or the add-block control is not
 * empty, so with both the drawer is not drawn, in edit mode as out of it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class blockless {
    /**
     * Switch the page's blocks off.
     *
     * @param \moodle_page $page The page, before its header is printed.
     * @return void
     */
    public static function apply(\moodle_page $page): void {
        $page->blocks->show_only_fake_blocks(true);
        $page->force_lock_all_blocks();
    }
}
