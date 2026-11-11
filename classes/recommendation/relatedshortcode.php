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
 * Theme Dimensions - Recommendation: filter_shortcodes for the related courses shortcode
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\recommendation;

use core\url;
use theme_boost_union\recommendation\recommendation;
use theme_dimensions\local\related\shortcode;

/**
 * Notes when the `[relatedcourses]` shortcode cannot run: filter_shortcodes is not installed, or is installed and disabled.
 *
 * Without the plugin the theme's db/shortcodes.php is never read; with the filter disabled a text holding the shortcode
 * shows it as written. A notice and not a warning: the landing pages' band needs neither.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class relatedshortcode extends base {
    /** @var string The filter the shortcode needs, as filter_is_enabled() names it. */
    private const FILTER = 'shortcodes';

    /**
     * Return the recommendation id.
     *
     * @return string
     */
    public function get_id(): string {
        return self::ID_PREFIX . 'relatedshortcode';
    }

    /**
     * Return the recommendation title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('recommendation_relatedshortcode_title', self::COMPONENT);
    }

    /**
     * Return the recommendation summary: which of the three states the site is in, from the status this instance
     * decided, so the summary never disagrees with it.
     *
     * @return string
     */
    public function get_summary(): string {
        $key = match (true) {
            $this->get_status() === recommendation::OK => 'recommendation_relatedshortcode_summary_ok',
            self::installed() => 'recommendation_relatedshortcode_summary_off',
            default => 'recommendation_relatedshortcode_summary_missing',
        };
        return get_string($key, self::COMPONENT, shortcode::NAME);
    }

    /**
     * Return the recommendation description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('recommendation_relatedshortcode_description', self::COMPONENT, shortcode::NAME);
    }

    /**
     * Decide the status: OK while the filter is installed and enabled (on, or off but available), a notice otherwise.
     *
     * @return string
     */
    protected function evaluate(): string {
        return self::installed() && self::enabled() ? recommendation::OK : recommendation::NOTICE;
    }

    /**
     * Return the filters' administration page, where the filter is turned on.
     *
     * @return \moodle_url|null
     */
    public function get_action_url(): ?\moodle_url {
        return self::installed() ? new url('/admin/filters.php') : null;
    }

    /**
     * Return the recommendation category.
     *
     * @return string
     */
    public function get_category(): string {
        return recommendation::CATEGORY_THIRDPARTY;
    }

    /**
     * Whether filter_shortcodes is installed: its code is there and its version recorded.
     *
     * @return bool
     */
    private static function installed(): bool {
        return \core_component::get_component_directory('filter_' . self::FILTER) !== null
            && get_config('filter_' . self::FILTER, 'version') !== false;
    }

    /**
     * Whether the filter is on or off-but-available on the site, so courses may use it.
     *
     * @return bool
     */
    private static function enabled(): bool {
        global $CFG;
        require_once($CFG->libdir . '/filterlib.php');
        return filter_is_enabled(self::FILTER);
    }
}
