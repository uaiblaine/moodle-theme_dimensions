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
 * Theme Dimensions - Boost Union's page background image in this theme's stylesheet
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * Boost Union's page background image, as Boost core prints it, for this theme's compile.
 *
 * Boost core writes the rule in theme_boost_get_extra_scss() from the setting of the theme it is handed, and core hands
 * it this theme, which has no such setting. Boost Union makes up for it for its child themes only when
 * theme_boost_union_is_active_childtheme() reads $PAGE as this theme, which the stylesheet's own compile (styles.php)
 * does not, so the image never reached this theme's sheet. This class prints the same rule from Boost Union's
 * setting; Boost Union's extra SCSS still prints the position, the repeat and the attachment, and a flavour's own image.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pagebackground {
    /**
     * The rule, or '' while Boost Union has no page background image.
     *
     * Mirrors theme_boost_get_extra_scss() (theme/boost/lib.php): the image from md up, covering the page. The address is
     * the one theme_config::setting_file_url() builds, made with make_pluginfile_url() instead, which encodes every path
     * segment (setting_file_url() prints the stored name as it is and answers a scheme-relative address), so that
     * {@see styleurl::safe()} can stand guard over the url('...') it is printed in. Config and file settings only, never
     * the page: the compile also runs on the command line.
     *
     * @return string SCSS.
     */
    public static function scss(): string {
        $filepath = (string) get_config('theme_boost_union', 'backgroundimage');
        if (trim($filepath, '/') === '') {
            return '';
        }
        $url = \core\url::make_pluginfile_url(
            \context_system::instance()->id,
            'theme_boost_union',
            'backgroundimage',
            theme_get_revision(),
            '/',
            ltrim($filepath, '/')
        )->out(false);
        $url = styleurl::safe($url);
        if ($url === '') {
            return '';
        }

        return "\n@media (min-width: 768px) {\n    body {\n        background-image: url('{$url}');\n"
            . "        background-size: cover;\n    }\n}\n";
    }
}
