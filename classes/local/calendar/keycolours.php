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
 * Theme Dimensions - The event types' colours an administrator chose, as the stylesheet reads them.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use theme_dimensions\local\category\branding;

/**
 * One colour setting per key of the calendar (docs/calendar/PROPOSAL.md section 30.4), and the Sass they hand the pre
 * SCSS callback ({@see theme_dimensions_get_pre_scss()}).
 *
 * Each setting defaults to its key's light edge in scss/calendar.scss (the design system's primitive, from post.scss;
 * keycolours_test reads both files), and only a stored value that is a hexadecimal colour AND differs from that default
 * reaches the compiler, so an untouched site compiles the stylesheet's hand-picked triples unchanged. A value is parsed
 * by {@see branding::hex()} on the way out as well as by the setting on the way in ({@see keycolour_setting}): a value
 * forced in config.php, which no validation saw, is read as unset rather than written into the compiler.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class keycolours {
    /** @var string The component the settings are stored under. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string The heading of the settings on the Calendar tab. */
    public const HEADING = 'calendarcolours';

    /** @var string The settings' name before the key. */
    public const PREFIX = 'calendarcolour';

    /**
     * @var string[] The keys carrying a colour triple (keys::ALL and the overdue warning), each with its default: the
     * light edge scss/calendar.scss draws it with.
     */
    public const DEFAULTS = [
        keys::SITE => '#6a737b',
        keys::CATEGORY => '#00343c',
        keys::COURSE => '#2a31c9',
        keys::GROUP => '#2f6b00',
        keys::USER => '#60451f',
        keys::ACTIVITY => '#4456ff',
        keys::OTHER => '#6a737b',
        keys::OPEN => '#2a31c9',
        'overdue' => '#ca3120',
    ];

    /**
     * A key's setting name, under the component.
     *
     * @param string $key One of the keys of {@see DEFAULTS}.
     * @return string
     */
    public static function setting(string $key): string {
        return self::PREFIX . $key;
    }

    /**
     * A key's setting title and description, one literal string id each.
     *
     * @param string $key One of the keys of {@see DEFAULTS}.
     * @return string[] [title, description].
     * @throws \coding_exception For a key outside {@see DEFAULTS}.
     */
    public static function labels(string $key): array {
        return match ($key) {
            keys::SITE => [
                get_string('calendarcoloursite', self::COMPONENT),
                get_string('calendarcoloursite_desc', self::COMPONENT),
            ],
            keys::CATEGORY => [
                get_string('calendarcolourcategory', self::COMPONENT),
                get_string('calendarcolourcategory_desc', self::COMPONENT),
            ],
            keys::COURSE => [
                get_string('calendarcolourcourse', self::COMPONENT),
                get_string('calendarcolourcourse_desc', self::COMPONENT),
            ],
            keys::GROUP => [
                get_string('calendarcolourgroup', self::COMPONENT),
                get_string('calendarcolourgroup_desc', self::COMPONENT),
            ],
            keys::USER => [
                get_string('calendarcolouruser', self::COMPONENT),
                get_string('calendarcolouruser_desc', self::COMPONENT),
            ],
            keys::ACTIVITY => [
                get_string('calendarcolouractivity', self::COMPONENT),
                get_string('calendarcolouractivity_desc', self::COMPONENT),
            ],
            keys::OTHER => [
                get_string('calendarcolourother', self::COMPONENT),
                get_string('calendarcolourother_desc', self::COMPONENT),
            ],
            keys::OPEN => [
                get_string('calendarcolouropen', self::COMPONENT),
                get_string('calendarcolouropen_desc', self::COMPONENT),
            ],
            'overdue' => [
                get_string('calendarcolouroverdue', self::COMPONENT),
                get_string('calendarcolouroverdue_desc', self::COMPONENT),
            ],
            default => throw new \coding_exception('No calendar colour for the key ' . $key),
        };
    }

    /**
     * The colours chosen: every key whose stored value is a hexadecimal colour other than its default.
     *
     * @return string[] key => lower-case #rrggbb, in the order of {@see DEFAULTS}.
     */
    public static function chosen(): array {
        $chosen = [];
        foreach (self::DEFAULTS as $key => $default) {
            $hex = branding::hex((string) get_config(self::COMPONENT, self::setting($key)));
            if ($hex !== null && $hex !== $default) {
                $chosen[$key] = $hex;
            }
        }
        return $chosen;
    }

    /**
     * The Sass the pre SCSS callback writes: the map scss/calendar.scss derives the chosen keys' triples from, or
     * nothing when no key has a colour of its own. Written with a !default, as the context bar's colour is: a flavour's
     * raw initial SCSS, compiled before the callback, may set its own and must win.
     *
     * @return string
     */
    public static function scss(): string {
        $chosen = self::chosen();
        if (!$chosen) {
            return '';
        }
        $pairs = [];
        foreach ($chosen as $key => $hex) {
            $pairs[] = $key . ': ' . $hex;
        }
        return "\n\$dim-cal-colours: (" . implode(', ', $pairs) . ") !default;\n";
    }
}
