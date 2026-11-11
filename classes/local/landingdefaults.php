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
 * Theme Dimensions - The landing page defaults for new courses
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use core_customfield\data_controller;
use core_customfield\field_controller;
use theme_dimensions\local\trail\trail;

/**
 * What a new course answers in the page model, the modality and the study format, and the one write of it.
 *
 * The three select fields keep their own default value at '' for good. Core's select
 * data_controller::get_value() returns the field default for every course without a data row,
 * so a moving field default would change every course nobody ever saved; with the field
 * default fixed, an existing course's read path never moves and changing a setting here
 * writes nothing. The default reaches a course once, when it is created
 * ({@see hook\course\after_form_submission}), as an ordinary data row, and the course form
 * of a new course shows it preselected ({@see hook\course\after_form_definition_after_data}).
 *
 * Select values are 1-based indexes into the field's option lines as the field is configured
 * at read time ({@see landingfields::option_lines()}), so a stored setting is clamped against
 * them on every read: an administrator who removes an option line turns a setting that pointed
 * past the end into "none", never into a value the field cannot show.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class landingdefaults {
    /** @var string Setting key of the page model's default. */
    public const SETTING_MODEL = 'landingdefaultmodel';

    /** @var string Setting key of the modality's default. */
    public const SETTING_MODALITY = 'landingdefaultmodality';

    /** @var string Setting key of the study format's default. */
    public const SETTING_FORMAT = 'landingdefaultformat';

    /** @var int No default: the field stays as core leaves it (empty, unless its own default says otherwise). */
    public const NONE = 0;

    /**
     * The fields that take a default, each with its setting key.
     *
     * The single source for settings.php, both hook callbacks and the tests.
     *
     * @return array Map of field shortname => setting key, in the order settings.php lists them.
     */
    public static function settings(): array {
        return [
            landingfields::SHORTNAME_MODEL => self::SETTING_MODEL,
            landingfields::SHORTNAME_MODALITY => self::SETTING_MODALITY,
            landingfields::SHORTNAME_FORMAT => self::SETTING_FORMAT,
        ];
    }

    /**
     * The choices of one field's setting: "none", then the field's options as configured.
     *
     * The plain spelling of each option line: admin_setting_configselect's template escapes.
     * While the field does not resolve (not provisioned yet, during the install that is about
     * to provision it, or deleted by an administrator) the lines the theme provisions it with
     * stand in, so settings.php always has a full list to offer. A line
     * {@see \theme_dimensions\local\trail\trail::hides_option()} withholds is left out.
     *
     * @param string $shortname One of the shortnames of {@see settings()}.
     * @return array Map of stored value => label, NONE first.
     */
    public static function choices(string $shortname): array {
        self::setting($shortname);
        $choices = [self::NONE => get_string('landingdefault_none', landingfields::COMPONENT)];
        $lines = self::options($shortname) ?? self::provisioned_options($shortname);
        foreach ($lines as $index => $line) {
            if (!trail::hides_option($shortname, $index + 1)) {
                $choices[$index + 1] = $line;
            }
        }
        return $choices;
    }

    /**
     * The default a new course gets for one field, clamped to the field as configured.
     *
     * @param string $shortname One of the shortnames of {@see settings()}.
     * @return int A 1-based option index, or NONE: when unset, out of range, withheld by trail::hides_option(),
     *   or the field does not resolve.
     */
    public static function value(string $shortname): int {
        $raw = get_config(landingfields::COMPONENT, self::setting($shortname));
        $value = is_numeric($raw) ? (int) $raw : self::NONE;
        if ($value < 1) {
            return self::NONE;
        }
        $lines = self::options($shortname);
        if ($lines === null || $value > count($lines) || trail::hides_option($shortname, $value)) {
            return self::NONE;
        }
        return $value;
    }

    /**
     * Write the defaults into a course that has just been created.
     *
     * A field the course already holds a data row for was answered (by the creator in the form,
     * by an upload or a web service) and is left alone, an explicit empty answer included: core
     * stores the form's empty choice as intvalue 0.
     * The row is written through core's own data controller, as
     * {@see focusmodefields::materialise_default()} does, so backup, restore, the course
     * form and tool_uploadcourse read it without knowing this code exists.
     *
     * component, area and itemid are not set by hand: data_controller::create() stamps them
     * from the field's category, falling back to the field's handler, which is the course
     * handler here. Copying instance_form_save()'s set_many() from the category instead would
     * write nulls, because landingfields resolves its fields through
     * api::get_categories_with_fields(), whose categories carry no original component.
     *
     * @param int $courseid The id of the course create_course() has just made.
     * @return void
     */
    public static function apply_to_new_course(int $courseid): void {
        global $DB;

        $context = null;
        foreach (array_keys(self::settings()) as $shortname) {
            // NONE whenever the field does not resolve, so past this test the field is there.
            $value = self::value($shortname);
            if ($value === self::NONE) {
                continue;
            }
            $field = self::field($shortname);
            $fieldid = (int) $field->get('id');
            if ($DB->record_exists('customfield_data', ['instanceid' => $courseid, 'fieldid' => $fieldid])) {
                continue;
            }
            $context = $context ?? \core\context\course::instance($courseid);
            $data = data_controller::create(0, (object) [
                'instanceid' => $courseid,
                'fieldid' => $fieldid,
            ], $field);
            $data->set('intvalue', $value);
            $data->set('value', (string) $value);
            $data->set('contextid', $context->id);
            $data->save();
        }
    }

    /**
     * The setting key of one field, refusing a field this class does not cover.
     *
     * @param string $shortname The field shortname.
     * @return string The setting key.
     * @throws \coding_exception For a shortname outside {@see settings()}.
     */
    private static function setting(string $shortname): string {
        $settings = self::settings();
        if (!isset($settings[$shortname])) {
            throw new \coding_exception("No landing page default for the field '{$shortname}'.");
        }
        return $settings[$shortname];
    }

    /**
     * The field, or null while it does not resolve; safe before the customfield tables exist.
     *
     * @param string $shortname The field shortname.
     * @return field_controller|null The field.
     */
    private static function field(string $shortname): ?field_controller {
        global $DB;
        if (!$DB->get_manager()->table_exists('customfield_field')) {
            return null;
        }
        return landingfields::get_field($shortname);
    }

    /**
     * The field's option lines as configured, or null while the field does not resolve.
     *
     * @param string $shortname The field shortname.
     * @return array|null The lines, 0-based; empty for a field configured with no options.
     */
    private static function options(string $shortname): ?array {
        if (!self::field($shortname)) {
            return null;
        }
        $lines = landingfields::option_lines($shortname);
        return $lines === null ? null : array_values(array_filter($lines, static fn(string $line): bool => $line !== ''));
    }

    /**
     * The option lines the theme provisions the field with, in the site's language.
     *
     * @param string $shortname The field shortname.
     * @return array The lines, 0-based.
     */
    private static function provisioned_options(string $shortname): array {
        $options = (string) (landingfields::definitions()[$shortname]['config']['options'] ?? '');
        return preg_split("/\s*\n\s*/", trim($options), -1, PREG_SPLIT_NO_EMPTY);
    }
}
