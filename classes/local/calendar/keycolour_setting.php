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
 * Theme Dimensions - The colour picker of an event type, hexadecimal only.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use theme_dimensions\local\category\branding;

/**
 * The colour setting of an event type: a hexadecimal field with a swatch, refusing everything but a hexadecimal colour.
 *
 * Core's picker also accepts colour names, rgb(), rgba(), hsl(), hsla(), transparent, currentColor and inherit, and
 * stores them as typed; the stylesheet derives a fill and an ink from the colour in Sass, so only a hex is accepted,
 * with its hash optional as core's own picker allows, and stored lower-case as #rrggbb ({@see branding::hex()}). An
 * empty value stores the default, as core's does. {@see keycolours::chosen()} parses the stored value again.
 *
 * It is drawn as a plain text field behind a swatch (templates/admin_setting_keycolour.mustache) instead of core's
 * gradient picker: the server draws everything and amd/src/keycolour.js only keeps the swatch and the browser's native
 * colour input in step with the field (section 30.4).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class keycolour_setting extends \admin_setting_configcolourpicker {
    /** @var bool Whether the page's swatch script was already asked for, so nine settings call it once. */
    private static bool $wired = false;

    #[\Override]
    public function output_html($data, $query = '') {
        global $OUTPUT, $PAGE;

        $value = (string) $data;
        $context = [
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'value' => $value,
            'hex' => branding::hex($value) ?? '',
            'label' => get_string('calendarcolourpicker', 'theme_dimensions'),
            'readonly' => $this->is_readonly(),
        ];
        $element = $OUTPUT->render_from_template('theme_dimensions/admin_setting_keycolour', $context);
        if (!self::$wired) {
            self::$wired = true;
            $PAGE->requires->js_call_amd('theme_dimensions/keycolour', 'init');
        }

        return format_admin_setting(
            $this,
            $this->visiblename,
            $element,
            $this->description,
            true,
            '',
            $this->get_defaultsetting(),
            $query
        );
    }

    /**
     * The colour as it is stored, or false to refuse it.
     *
     * @param string $data What was typed.
     * @return string|false Lower-case #rrggbb, or false.
     */
    protected function validate($data) {
        $data = trim((string) $data);
        if ($data === '') {
            return $this->get_defaultsetting();
        }
        return branding::hex(str_starts_with($data, '#') ? $data : '#' . $data) ?? false;
    }
}
