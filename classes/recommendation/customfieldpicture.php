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
 * Theme Dimensions - Recommendation: the picture custom fields
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\recommendation;

use core\plugininfo\customfield;
use core\url;
use theme_boost_union\recommendation\recommendation;
use theme_dimensions\local\hotsitefields;

/**
 * Warns when the picture field type is unavailable or one of the theme's course fields is missing.
 *
 * The hotsite badges, the share picture and the rest of the hotsite are course custom fields that
 * {@see hotsitefields::ensure_fields()} provisions. The check only reads: the fields resolve through
 * {@see hotsitefields::get_field()}, after its request cache is dropped so a field deleted since
 * the page began is seen. Creating the missing fields is safe to offer as a fix, because the
 * provisioner is idempotent and serialised under a lock; enabling a field type is a decision of the
 * administrator's and is not offered.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customfieldpicture extends base {
    /** @var bool|null Whether the picture type is installed, decided with the status. */
    private ?bool $installed = null;

    /** @var bool|null Whether the picture type is enabled, decided with the status. */
    private ?bool $enabled = null;

    /** @var string[]|null The shortnames of the theme's fields that do not resolve, decided with the status. */
    private ?array $missing = null;

    /**
     * Return the recommendation id.
     *
     * @return string
     */
    public function get_id(): string {
        return self::ID_PREFIX . 'customfieldpicture';
    }

    /**
     * Return the recommendation title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string('recommendation_customfieldpicture_title', self::COMPONENT);
    }

    /**
     * Return the recommendation summary.
     *
     * @return string
     */
    public function get_summary(): string {
        if ($this->get_status() === recommendation::OK) {
            return get_string('recommendation_customfieldpicture_summary_ok', self::COMPONENT);
        }
        if (!$this->installed || !$this->enabled) {
            return get_string('recommendation_customfieldpicture_summary_type', self::COMPONENT);
        }
        return get_string('recommendation_customfieldpicture_summary_missing', self::COMPONENT, implode(', ', $this->missing));
    }

    /**
     * Return the recommendation description.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('recommendation_customfieldpicture_description', self::COMPONENT);
    }

    /**
     * Decide the status: a warning when the picture type is unavailable or any field of the theme is missing.
     *
     * @return string
     */
    protected function evaluate(): string {
        $this->installed = \core_component::get_plugin_directory('customfield', hotsitefields::BRASAO_TYPE) !== null;
        $this->enabled = array_key_exists(hotsitefields::BRASAO_TYPE, customfield::get_enabled_plugins());

        hotsitefields::reset_field_cache();
        $this->missing = [];
        foreach (array_keys(hotsitefields::definitions()) as $shortname) {
            if (hotsitefields::get_field($shortname) === null) {
                $this->missing[] = $shortname;
            }
        }

        $ok = $this->installed && $this->enabled && $this->missing === [];
        return $ok ? recommendation::OK : recommendation::WARNING;
    }

    /**
     * Return where the problem is dealt with: the field types, or the course fields.
     *
     * @return \moodle_url|null
     */
    public function get_action_url(): ?\moodle_url {
        if ($this->get_status() !== recommendation::OK && (!$this->installed || !$this->enabled)) {
            return new url('/admin/settings.php', ['section' => 'managecustomfields']);
        }
        return new url('/customfield/edit.php', ['component' => 'core_course', 'area' => 'course', 'itemid' => 0]);
    }

    /**
     * Return the recommendation category.
     *
     * @return string
     */
    public function get_category(): string {
        return recommendation::CATEGORY_MOODLECORE;
    }

    /**
     * Whether the fix applies: the picture type is usable and fields are missing.
     *
     * A disabled type hides its fields from the course field list, so they all read as missing; creating
     * them again would duplicate fields that are only hidden.
     *
     * @return bool
     */
    public function supports_autofix(): bool {
        $this->get_status();
        return $this->installed && $this->enabled && $this->missing !== [];
    }

    /**
     * Create the missing fields.
     *
     * @return void
     */
    public function autofix(): void {
        hotsitefields::ensure_fields();
        $this->forget_status();
    }
}
