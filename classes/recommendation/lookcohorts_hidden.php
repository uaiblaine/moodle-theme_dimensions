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
 * Theme Dimensions - Recommendation: smart menus hidden after a look cohort was deleted
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\recommendation;

use core\url;
use theme_boost_union\recommendation\recommendation;
use theme_dimensions\local\lookcohorts;

/**
 * Warns while smart menus or items stay hidden because the look cohort their rule named was deleted.
 *
 * Core's cohort pages refuse to delete a look cohort, its web services do not, and Boost Union then strips
 * the cohort from the rule, which would show the menu to everyone; the theme hid it first. Only an
 * administrator can tell the menu's new audience, so the warning lasts until each one is shown again.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lookcohorts_hidden extends base {
    /**
     * Return the recommendation id.
     *
     * @return string
     */
    public function get_id(): string {
        return self::ID_PREFIX . 'lookcohorts';
    }

    /**
     * Return the recommendation title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('recommendation_lookcohorts_title', self::COMPONENT);
    }

    /**
     * Return the recommendation summary.
     *
     * @return string
     */
    public function get_summary(): string {
        if ($this->get_status() === recommendation::OK) {
            return get_string('recommendation_lookcohorts_summary_ok', self::COMPONENT);
        }
        $hidden = lookcohorts::hidden();

        return get_string('recommendation_lookcohorts_summary_hidden', self::COMPONENT, (object) [
            'menus' => count($hidden['menus']),
            'items' => count($hidden['items']),
        ]);
    }

    /**
     * Return the recommendation description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('recommendation_lookcohorts_description', self::COMPONENT);
    }

    /**
     * Decide the status: a warning while a hidden menu or item is still recorded.
     *
     * @return string
     */
    protected function evaluate(): string {
        lookcohorts::prune_hidden();
        $hidden = lookcohorts::hidden();

        return ($hidden['menus'] || $hidden['items']) ? recommendation::WARNING : recommendation::OK;
    }

    /**
     * Return Boost Union's smart menus page, where the menus are re-pointed and shown again.
     *
     * @return \moodle_url|null
     */
    public function get_action_url(): ?\moodle_url {
        return new url('/theme/boost_union/smartmenus/menus.php');
    }

    /**
     * Return the recommendation category.
     *
     * @return string
     */
    public function get_category(): string {
        return recommendation::CATEGORY_BOOSTUNION;
    }
}
