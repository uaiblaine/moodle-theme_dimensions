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
 * Theme Dimensions - Which sign-in steps of other components stand on the loginsteps shell
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use moodle_page;
use stdClass;

/**
 * The login-layout pages that are drawn on auth_loginsteps' shell instead of Boost Union's login page.
 *
 * A sign-in through auth_loginsteps stands on its shell (band, round icon, column); the steps
 * after it that other components own - tool_mfa's second factor, tool_musudo's privileged
 * session - use the 'login' pagelayout, which this theme draws as Boost Union's split login
 * page. For those pages, and only those, layout/login.php draws the shell around the page's
 * own content (\auth_loginsteps\output\core_page_shell), so the whole door reads as one.
 * Every other login-layout page, every page on a site without auth_loginsteps and every page
 * on a site whose loginsteps shell is switched off keeps Boost Union's layout.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class loginshell {
    /** @var array Pagetype => [screen, title string, subtitle string, core icon]. */
    private const PAGES = [
        'admin-tool-mfa-auth' => ['mfa', 'loginshell_mfa_title', 'loginshell_mfa_subtitle', 'i/lock'],
        'admin-tool-musudo-sudo_start' => ['musudo', 'loginshell_musudo_title', 'loginshell_musudo_subtitle', 'i/permissions'],
    ];

    /**
     * How a page is drawn on the shell, or null when it keeps Boost Union's login page.
     *
     * @param moodle_page $page The page being laid out.
     * @return stdClass|null With screen, title, subtitle (plain) and glyph; null for no shell.
     */
    public static function for_page(moodle_page $page): ?stdClass {
        $entry = self::PAGES[(string) $page->pagetype] ?? null;
        if ($entry === null || !self::plugin_shell_available()) {
            return null;
        }
        [$screen, $title, $subtitle, $glyph] = $entry;

        return (object) [
            'screen' => $screen,
            'title' => get_string($title, 'theme_dimensions'),
            'subtitle' => get_string($subtitle, 'theme_dimensions'),
            'glyph' => $glyph,
        ];
    }

    /**
     * Whether auth_loginsteps is here, new enough, and has its shell switched on as the site's door.
     *
     * The plugin is optional and is not in version.php: asked by class name, and its switch is its
     * own decision ({@see \auth_loginsteps\output\core_page_shell::available()}), so a site that
     * keeps core's login page keeps the login layout for the second factor as well.
     *
     * @return bool
     */
    private static function plugin_shell_available(): bool {
        $class = \auth_loginsteps\output\core_page_shell::class;

        return class_exists($class) && method_exists($class, 'available') && $class::available();
    }

    /**
     * The pagetypes drawn on the shell.
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return array_keys(self::PAGES);
    }
}
