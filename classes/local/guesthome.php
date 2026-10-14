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
 * Theme Dimensions - The guest's home page
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * Where the guest account's home page is, when the site's home page is a URL.
 *
 * Core's get_home_page() (lib/moodlelib.php) keeps the guest away from the dashboard, My courses
 * and the user's own choice, and answers HOMEPAGE_URL for the guest as for anybody once
 * defaulthomepage is a URL - so index.php sends the guest to a page written for accounts, which
 * then refuses it. The logo, the primary navigation's Home and the breadcrumb root all link to
 * '/', so that one redirect is every way home. This class overwrites $CFG->defaulthomepage in
 * memory, for the guest's request alone and never in the database: with the site home page by
 * default, or with the URL the guest home page setting names.
 *
 * A URL home page is told apart from the HOMEPAGE_* constants the way
 * get_default_home_page_url() does it: the value starts with '/'. A custom URL is accepted only in
 * the shape that function accepts (root-relative and a local URL once prefixed with wwwroot), so
 * core's own reader turns it back into an address; anything else is the site home page. The site
 * root ('/', '/index.php', with or without a query string) is refused too: core's index.php would
 * redirect the guest to it again, for ever.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class guesthome {
    /** @var string The setting choosing the guest's home page. */
    public const SETTING = 'guesthomepage';

    /** @var string The setting holding the custom URL. */
    public const SETTING_URL = 'guesthomepageurl';

    /** @var string The site home page, the default. */
    public const OPTION_SITE = 'site';

    /** @var string A custom URL. */
    public const OPTION_URL = 'url';

    /** @var string This theme's component, for its settings. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * Overwrite this request's defaulthomepage for the guest account, when the rule applies.
     *
     * @return void
     */
    public static function apply(): void {
        global $CFG;
        $replacement = self::replacement();
        if ($replacement !== null) {
            $CFG->defaulthomepage = $replacement;
        }
    }

    /**
     * The value defaulthomepage takes for this request, or null to leave the site's own.
     *
     * @return int|string|null HOMEPAGE_SITE, a root-relative URL, or null.
     */
    public static function replacement(): int|string|null {
        global $CFG;

        if (!isguestuser()) {
            return null;
        }
        if (!self::is_url((string) ($CFG->defaulthomepage ?? ''))) {
            return null;
        }
        if (get_config(self::COMPONENT, self::SETTING) === self::OPTION_URL) {
            $url = trim((string) get_config(self::COMPONENT, self::SETTING_URL));
            if (self::is_url($url) && !self::is_root_path($url) && clean_param($CFG->wwwroot . $url, PARAM_LOCALURL) !== '') {
                return $url;
            }
        }
        return HOMEPAGE_SITE;
    }

    /**
     * Whether a root-relative URL points at the site root, which core's index.php redirects back to.
     *
     * @param string $url A root-relative URL.
     * @return bool True when its path is '/' or '/index.php'.
     */
    private static function is_root_path(string $url): bool {
        return in_array(parse_url($url, PHP_URL_PATH), ['/', '/index.php'], true);
    }

    /**
     * Whether a defaulthomepage value is a URL rather than one of the HOMEPAGE_* constants.
     *
     * @param string $value The value.
     * @return bool True for a root-relative URL.
     */
    private static function is_url(string $value): bool {
        return str_starts_with($value, '/');
    }
}
