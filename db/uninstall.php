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
 * Theme Dimensions - Uninstall script
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove what is the theme's own and not user-authored: its seven user preferences, the class report's columns per
 * course and its report persistents; release the look cohorts.
 *
 * Core's uninstall_plugin() already drops the theme's table and every file of the theme's
 * component, the category hero images and the site-wide fallback among them. The landing page
 * course custom fields hold what people typed about their courses and are left in place on
 * purpose. Favourites are core's rows, owned by core_favourites.
 *
 * @return bool
 */
function xmldb_theme_dimensions_uninstall() {
    global $DB;

    $preferences = [
        \theme_dimensions\local\focusmode::PREFERENCE_EXPANDED,
        \theme_dimensions\output\category_listing::PREFERENCE_VIEW,
        \theme_dimensions\local\category\remembered::PREFERENCE,
        \theme_dimensions\output\category_hero::PREFERENCE_FOLDED,
        \theme_dimensions\output\course_notice::PREFERENCE_COLLAPSED,
        \theme_dimensions\local\flavourchoice::PREFERENCE,
        \theme_dimensions\local\calendar\state::PREFERENCE,
    ];
    foreach ($preferences as $name) {
        $DB->delete_records('user_preferences', ['name' => $name]);
    }

    // The class report's persistents, with every reader's stored filter values (core's uninstall leaves them).
    \theme_dimensions\local\competency\reportpersistents::delete_all();

    // Every reader's columns on the class report of every course: one preference per course, named by pattern.
    \theme_dimensions\local\competency\classreportpreference::delete_all();

    // Released, not deleted: a smart menu restricted by a look cohort keeps its audience.
    \theme_dimensions\local\lookcohorts::release();

    return true;
}
