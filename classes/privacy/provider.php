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
 * Theme Dimensions - Privacy provider
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 *             based on code by Daniel Poggenpohl and Alexander Bias
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\writer;
use theme_dimensions\local\category\remembered;
use theme_dimensions\local\focusmode;
use theme_dimensions\output\category_hero;
use theme_dimensions\output\category_listing;
use theme_dimensions\output\course_notice;

/**
 * Privacy Subsystem implementation for theme_dimensions.
 *
 * The theme owns five pieces of personal data that are user preferences: the reading column
 * width a person chose in focus mode, the view (cards or list) they chose on a category page,
 * the organisation, view and filters they left each of their most recent category pages in,
 * whether they folded the header of the category pages, and whether they folded the course
 * notice of the course pages. Its one table holds the branding of
 * categories - a layout, two colours, a tagline, public texts - and its only user-linked column
 * is usermodified, the id of the user who last saved a category's branding. That id is kept as an
 * authorship stamp, and it is declared in {@see get_metadata()} for that reason. Deleting the
 * account leaves core's stub row in the user table, so the stamp then names nobody: the
 * anonymisation is that of core's deletion, and the provider does not export or erase the
 * institutional content the stamp sits on. Everything else the theme shows about a person - the
 * rank, the war name, what a course says about itself, the courses they starred (core's own
 * favourite rows, which core_favourites describes) - belongs to core's own subsystems, which
 * describe and delete it themselves.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 *             based on code by Daniel Poggenpohl and Alexander Bias
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, user_preference_provider {
    /**
     * Describe the user preferences this theme stores and the one user id its table keeps.
     *
     * @param collection $collection The collection of metadata items to add to.
     * @return collection The same collection, with this theme's items added.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'theme_dimensions_cat',
            ['usermodified' => 'privacy:metadata:cat:usermodified'],
            'privacy:metadata:cat'
        );
        $collection->add_user_preference(
            focusmode::PREFERENCE_EXPANDED,
            'privacy:metadata:preference:focus_expanded'
        );
        $collection->add_user_preference(
            category_listing::PREFERENCE_VIEW,
            'privacy:metadata:preference:category_view'
        );
        $collection->add_user_preference(
            category_hero::PREFERENCE_FOLDED,
            'privacy:metadata:preference:category_herofolded'
        );
        $collection->add_user_preference(
            remembered::PREFERENCE,
            'privacy:metadata:preference:category_state'
        );
        $collection->add_user_preference(
            course_notice::PREFERENCE_COLLAPSED,
            'privacy:metadata:preference:coursenotice_collapsed'
        );

        return $collection;
    }

    /**
     * Export the user preferences this theme stores for one person.
     *
     * @param int $userid The id of the user whose data is to be exported.
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        $expanded = get_user_preferences(focusmode::PREFERENCE_EXPANDED, null, $userid);
        if ($expanded !== null) {
            writer::export_user_preference(
                'theme_dimensions',
                focusmode::PREFERENCE_EXPANDED,
                $expanded ? get_string('yes') : get_string('no'),
                get_string('privacy:metadata:preference:focus_expanded', 'theme_dimensions')
            );
        }

        $view = get_user_preferences(category_listing::PREFERENCE_VIEW, null, $userid);
        if ($view !== null) {
            writer::export_user_preference(
                'theme_dimensions',
                category_listing::PREFERENCE_VIEW,
                $view,
                get_string('privacy:metadata:preference:category_view', 'theme_dimensions')
            );
        }

        /* The stored value as it is - a JSON object keyed by category id, each entry naming the
           organisation, the view and the filters (see remembered) - because the export is of what
           is stored, not of what the page would still accept of it. */
        $state = get_user_preferences(remembered::PREFERENCE, null, $userid);
        if ($state !== null) {
            writer::export_user_preference(
                'theme_dimensions',
                remembered::PREFERENCE,
                $state,
                get_string('privacy:metadata:preference:category_state', 'theme_dimensions')
            );
        }

        $folded = get_user_preferences(category_hero::PREFERENCE_FOLDED, null, $userid);
        if ($folded !== null) {
            writer::export_user_preference(
                'theme_dimensions',
                category_hero::PREFERENCE_FOLDED,
                $folded ? get_string('yes') : get_string('no'),
                get_string('privacy:metadata:preference:category_herofolded', 'theme_dimensions')
            );
        }

        $collapsed = get_user_preferences(course_notice::PREFERENCE_COLLAPSED, null, $userid);
        if ($collapsed !== null) {
            writer::export_user_preference(
                'theme_dimensions',
                course_notice::PREFERENCE_COLLAPSED,
                $collapsed ? get_string('yes') : get_string('no'),
                get_string('privacy:metadata:preference:coursenotice_collapsed', 'theme_dimensions')
            );
        }
    }
}
