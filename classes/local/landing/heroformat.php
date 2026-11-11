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
 * Theme Dimensions - The landing page banner's format
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

/**
 * How the banner of every course landing page of the site is drawn: in the content column, or full width.
 *
 * In the column, the banner is a card inside the page's main region. Full width, it is drawn
 * in the page header and runs from edge to edge under the navbar, the geometry of the category showcase's hero
 * (scss/landing.scss keeps its own copy of that geometry, in step with scss/category.scss). One site-wide choice:
 * no course field and no flavour moves it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class heroformat {
    /** @var string The setting's key under theme_dimensions. */
    public const SETTING = 'landingheroformat';

    /** @var string The banner as a card in the content column: the default. */
    public const COLUMN = 'column';

    /** @var string The banner from edge to edge in the page header. */
    public const FULLWIDTH = 'fullwidth';

    /** @var string The body class of a landing page whose banner is full width. */
    public const BODY_CLASS = 'dim-landing-fullwidth';

    /**
     * The setting's choices, in the order the settings page lists them.
     *
     * @return array Map of stored value => label.
     */
    public static function choices(): array {
        return [
            self::COLUMN => get_string('landingheroformat_column', 'theme_dimensions'),
            self::FULLWIDTH => get_string('landingheroformat_fullwidth', 'theme_dimensions'),
        ];
    }

    /**
     * Whether the site draws the banner full width.
     *
     * Only the stored value 'fullwidth' says so: anything else, nothing stored included, is the column, so a site
     * that upgrades keeps the page it had until an administrator chooses otherwise.
     *
     * @return bool
     */
    public static function is_fullwidth(): bool {
        return get_config('theme_dimensions', self::SETTING) === self::FULLWIDTH;
    }
}
