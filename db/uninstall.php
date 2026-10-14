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
 * Remove what is the theme's own and not user-authored: its five user preferences.
 *
 * Core's uninstall_plugin() already drops the theme's table through XMLDB and every file
 * of the theme's component - the category hero images and the site-wide fallback among
 * them. Those images are institutional content, and deleting them with the theme is
 * correct; this theme's other precedent (the hotsite custom fields, which hold what people
 * typed about their courses) points the other way and is left alone on purpose. Favourites
 * are core's rows and core_favourites owns them.
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
    ];
    foreach ($preferences as $name) {
        $DB->delete_records('user_preferences', ['name' => $name]);
    }

    return true;
}
