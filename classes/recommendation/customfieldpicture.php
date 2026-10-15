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

use core\url;
use theme_boost_union\recommendation\recommendation;
use theme_dimensions\local\landingfields;

/**
 * Recommends the picture field type, and warns when one of the theme's course fields is missing.
 *
 * The landing page badges, the share picture and the rest of the landing page are course custom fields that
 * {@see landingfields::ensure_fields()} provisions. customfield_picture is recommended, not
 * required: without it (or with it disabled) the five picture fields are not provisioned and not
 * drawn, everything else works, and the item is a notice. A missing field of a type the site HAS
 * is a warning. The check only reads: the fields resolve through
 * {@see landingfields::missing_definitions()}, after the request cache is dropped so a field
 * deleted since the page began is seen. Creating the missing fields is safe to offer as a fix,
 * because the provisioner is idempotent and serialised under a lock and skips a type the site does
 * not have; installing or enabling a field type is a decision of the administrator's and is not
 * offered.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customfieldpicture extends base {
    /** @var bool|null Whether the picture type is installed and enabled, decided with the status. */
    private ?bool $usable = null;

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
        if ($this->missing === []) {
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
     * Decide the status: a warning for a missing field, a notice for an unavailable picture type.
     *
     * The missing fields are counted among the types the site has, so a site without the picture type
     * is not told that its five picture fields are missing.
     *
     * @return string
     */
    protected function evaluate(): string {
        $this->usable = landingfields::picture_type_available();

        landingfields::reset_field_cache();
        $this->missing = array_keys(landingfields::missing_definitions());

        if ($this->missing !== []) {
            return recommendation::WARNING;
        }
        return $this->usable ? recommendation::OK : recommendation::NOTICE;
    }

    /**
     * Return where the problem is dealt with: the course fields, or the field types.
     *
     * @return \moodle_url|null
     */
    public function get_action_url(): ?\moodle_url {
        if ($this->get_status() === recommendation::NOTICE) {
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
     * Whether the fix applies: fields of a type the site has are missing.
     *
     * The picture fields are among them only while the type is installed and enabled: a disabled type
     * hides its fields from the course field list, and creating them again would collide with fields
     * that are only hidden.
     *
     * @return bool
     */
    public function supports_autofix(): bool {
        $this->get_status();
        return $this->missing !== [];
    }

    /**
     * Create the missing fields.
     *
     * @return void
     */
    public function autofix(): void {
        landingfields::ensure_fields();
        $this->forget_status();
    }
}
