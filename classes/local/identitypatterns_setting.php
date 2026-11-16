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
 * Theme Dimensions - The display patterns setting, validated line by line.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * The display patterns of the identity fields: a text area that refuses a line the mask could not apply.
 *
 * {@see identitypattern::errors()} decides what is refused (a line without "=", an unknown field, a pattern with
 * no slot, a field named twice); this class only words the first refusal with its line number. The field
 * vocabulary is read when a value is saved, never while settings.php loads.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class identitypatterns_setting extends \admin_setting_configtextarea {
    /**
     * Refuse a value holding a line the mask could not apply.
     *
     * @param string $data The submitted value.
     * @return bool|string True, or the first refused line's error.
     */
    #[\Override]
    public function validate($data) {
        $parent = parent::validate($data);
        if ($parent !== true) {
            return $parent;
        }
        $errors = identitypattern::errors((string) $data, identity::fields());
        if (!$errors) {
            return true;
        }
        $first = reset($errors);
        // Plain text: core's setting template escapes the error.
        $a = (object) ['line' => $first['line'], 'text' => $first['text']];

        return match ($first['error']) {
            identitypattern::ERROR_NOEQUALS => get_string('identitypatterns_noequals', identity::COMPONENT, $a),
            identitypattern::ERROR_UNKNOWNFIELD => get_string('identitypatterns_unknownfield', identity::COMPONENT, $a),
            identitypattern::ERROR_NOSLOT => get_string('identitypatterns_noslot', identity::COMPONENT, $a),
            default => get_string('identitypatterns_duplicate', identity::COMPONENT, $a),
        };
    }
}
