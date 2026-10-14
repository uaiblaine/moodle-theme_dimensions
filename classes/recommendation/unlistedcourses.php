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
 * Theme Dimensions - Recommendation: the local_unlistedcourses dependency
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\recommendation;

use core\url;
use theme_boost_union\recommendation\recommendation;

/**
 * Warns when local_unlistedcourses is missing or has an upgrade waiting.
 *
 * The plugin has no settings of its own, so its state is the one thing to check. The theme calls its
 * classes directly in the listing, the guard and the renderers; only the two anonymous-page adapters
 * and the enrolment forwarding survive its absence, the first failing closed and the second open.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unlistedcourses extends base {
    /** @var string The component this theme depends on. */
    private const PLUGIN = 'local_unlistedcourses';

    /**
     * Return the recommendation id.
     *
     * @return string
     */
    public function get_id(): string {
        return self::ID_PREFIX . 'unlistedcourses';
    }

    /**
     * Return the recommendation title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('recommendation_unlistedcourses_title', self::COMPONENT);
    }

    /**
     * Return the recommendation summary.
     *
     * @return string
     */
    public function get_summary(): string {
        return get_string(
            $this->get_status() === recommendation::OK
                ? 'recommendation_unlistedcourses_summary_ok'
                : 'recommendation_unlistedcourses_summary_behind',
            self::COMPONENT
        );
    }

    /**
     * Return the recommendation description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('recommendation_unlistedcourses_description', self::COMPONENT);
    }

    /**
     * Decide the status: a warning unless the plugin is installed, upgraded and its classes load.
     *
     * Not upgraded covers an older copy too: the plugin's database version is compared with its code,
     * and Moodle itself refuses to upgrade this theme while the version it requires is not installed,
     * so no site reaches this page with the plugin installed, upgraded and older than required.
     *
     * @return string
     */
    protected function evaluate(): string {
        $plugin = \core_plugin_manager::instance()->get_plugin_info(self::PLUGIN);
        if ($plugin === null || !$plugin->is_installed_and_upgraded()) {
            return recommendation::WARNING;
        }
        if (!class_exists(\local_unlistedcourses\discoverability::class)) {
            return recommendation::WARNING;
        }
        return recommendation::OK;
    }

    /**
     * Return the plugin overview, where a missing or pending plugin is dealt with.
     *
     * @return \moodle_url|null
     */
    public function get_action_url(): ?\moodle_url {
        return new url('/admin/plugins.php');
    }

    /**
     * Return the recommendation category.
     *
     * @return string
     */
    public function get_category(): string {
        return recommendation::CATEGORY_THIRDPARTY;
    }
}
