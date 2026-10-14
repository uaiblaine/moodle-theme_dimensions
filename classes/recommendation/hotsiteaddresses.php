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
 * Theme Dimensions - Recommendation: the hotsites' short public addresses
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\recommendation;

use core\url;
use theme_boost_union\recommendation\recommendation;
use theme_dimensions\local\hotsite\links;

/**
 * Notes when Moodle's router is not configured, so every hotsite is shared under its long address.
 *
 * The router is an assertion in config.php and a web server rewrite, not a theme setting, so the
 * recommendation can only report the state and lead to the environment page, where core reports it.
 * A notice and not a warning: the hotsites work either way.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hotsiteaddresses extends base {
    /**
     * Return the recommendation id.
     *
     * @return string
     */
    public function get_id(): string {
        return self::ID_PREFIX . 'hotsiteaddresses';
    }

    /**
     * Return the recommendation title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('recommendation_hotsiteaddresses_title', self::COMPONENT);
    }

    /**
     * Return the recommendation summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string(
            $this->get_status() === recommendation::OK
                ? 'recommendation_hotsiteaddresses_summary_ok'
                : 'recommendation_hotsiteaddresses_summary_off',
            self::COMPONENT
        );
    }

    /**
     * Return the recommendation description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('recommendation_hotsiteaddresses_description', self::COMPONENT);
    }

    /**
     * Decide the status: OK while the router is configured, a notice while it is not.
     *
     * @return string
     */
    protected function evaluate(): string {
        return links::routing_enabled() ? recommendation::OK : recommendation::NOTICE;
    }

    /**
     * Return the environment page, where core reports the router.
     *
     * @return \moodle_url|null
     */
    public function get_action_url(): ?\moodle_url {
        return new url('/admin/environment.php');
    }

    /**
     * Return the recommendation category.
     *
     * @return string
     */
    public function get_category(): string {
        return recommendation::CATEGORY_MOODLECORE;
    }
}
