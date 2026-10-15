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
 * Theme Dimensions - Recommendation: the profile rule needs core's login requirement
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\recommendation;

use core\url;
use theme_boost_union\recommendation\recommendation;

/**
 * Warns when core's "Force users to log in for profiles" is off, which makes the theme's profile rule inert.
 *
 * The rule ({@see \theme_dimensions\local\profileaccess}) is a profile callback, and
 * \core\user::can_view_profile() answers true for everybody before it consults any callback while
 * the core setting is off. The setting is unset on a site that has not stored it, and core's default
 * is on, hence the ?? 1.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profileveto extends base {
    /**
     * Return the recommendation id.
     *
     * @return string
     */
    public function get_id(): string {
        return self::ID_PREFIX . 'profileveto';
    }

    /**
     * Return the recommendation title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('recommendation_profileveto_title', self::COMPONENT);
    }

    /**
     * Return the recommendation summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string(
            $this->get_status() === recommendation::OK
                ? 'recommendation_profileveto_summary_ok'
                : 'recommendation_profileveto_summary_off',
            self::COMPONENT
        );
    }

    /**
     * Return the recommendation description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('recommendation_profileveto_description', self::COMPONENT);
    }

    /**
     * Decide the status: a warning while core opens every profile, fine otherwise.
     *
     * @return string
     */
    protected function evaluate(): string {
        global $CFG;

        return empty($CFG->forceloginforprofiles ?? 1) ? recommendation::WARNING : recommendation::OK;
    }

    /**
     * Return the page of core's site security settings that holds the option.
     *
     * @return \moodle_url|null
     */
    public function get_action_url(): ?\moodle_url {
        return new url('/admin/settings.php', ['section' => 'sitepolicies'], 'admin-forceloginforprofiles');
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
