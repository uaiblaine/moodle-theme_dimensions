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
 * Theme Dimensions - The navbar's compact mode on tablets
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * Whether the navbar of the page being rendered may switch to the phone's pattern on a tablet (B5, phase 4).
 *
 * Between the md and xl breakpoints, when the primary navigation would send more than {@see MAXHIDDEN} items into
 * its "More" menu, the navbar shows core's hamburger, the brand and the right cluster, and the mobile drawer holds
 * the navigation. Whether it overflows is a measurement of the page in the browser (amd/src/compactnav.js reads
 * what core's React primary navigation decided); what the server decides here is where the mode may happen at all,
 * so the client is armed, and the primary navigation held back until it has measured, only there:
 *
 * - The setting {@see SETTING}, a checkbox that ships on.
 * - Not in the Moodle app, which draws its own chrome around a plugin's web view.
 * - Not in focus mode, whose bar replaces the navbar ({@see focusmode}).
 * - A layout that draws the navbar ({@see guestnav::EXCLUDED_LAYOUTS}).
 * - A primary navigation that can overflow by more than {@see MAXHIDDEN} items at all: fewer candidates than that
 *   can never cross the line whatever the width, so such a page is decided here, before the first paint, and
 *   nothing of the mode reaches it ({@see candidates()}).
 *
 * Nothing here renders: the renderer splices {@see BODY_ARMED} into the layout's body attributes and arms the
 * module with {@see client_config()}.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class compactnav {
    /** @var string Component name, used for every config read. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string The feature's setting, an admin_setting_configcheckbox that ships on. */
    public const SETTING = 'navbarcompact';

    /** @var int The most items the primary navigation may send into its "More" menu before the navbar goes compact. */
    public const MAXHIDDEN = 2;

    /**
     * @var string The body class the server writes where the mode may happen: it arms the stylesheet's hold on the
     *      primary navigation until the module has measured, and the module refuses to run without it.
     */
    public const BODY_ARMED = 'dimensions-navcompact-armed';

    /**
     * Whether the feature is switched on.
     *
     * A default-on checkbox reads only an explicit '0' as off, so a site that never saved the setting (false) has
     * the feature without an upgrade step writing it.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (string) get_config(self::COMPONENT, self::SETTING) !== '0';
    }

    /**
     * Whether the page being rendered may have the compact navbar, before its primary navigation is looked at.
     *
     * @param \moodle_page $page The page being rendered.
     * @return bool
     */
    public static function applies(\moodle_page $page): bool {
        if (!self::enabled()) {
            return false;
        }
        if (in_array((string) $page->pagelayout, guestnav::EXCLUDED_LAYOUTS, true)) {
            return false;
        }
        if (\core_useragent::is_moodle_app()) {
            return false;
        }
        if (focusmode::applies($page)) {
            return false;
        }

        return true;
    }

    /**
     * The number of top-level items the primary navigation may send into "More" because of the width.
     *
     * Read from the props core's React component is mounted with (the more_menu export's reactprops, which Boost
     * Union's primary navigation builds the same way): the top-level items that are neither a divider nor forced into
     * "More" by the node itself. A forced item is in "More" at every width, so it is no sign of overflow; the client
     * leaves it out of its count the same way.
     *
     * @param array $moremenu The layout context's primarymoremenu.
     * @return int
     */
    public static function candidates(array $moremenu): int {
        $props = json_decode((string) ($moremenu['reactprops'] ?? ''), true);
        if (!is_array($props) || !isset($props['items']) || !is_array($props['items'])) {
            return 0;
        }

        $count = 0;
        foreach ($props['items'] as $item) {
            if (!is_array($item) || !empty($item['divider']) || !empty($item['forceintomoremenu'])) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    /**
     * Whether the page being rendered arms the compact mode: it may have it, and its navigation can overflow enough.
     *
     * @param \moodle_page $page The page being rendered.
     * @param array $moremenu The layout context's primarymoremenu.
     * @return bool
     */
    public static function arms(\moodle_page $page, array $moremenu): bool {
        if (self::candidates($moremenu) <= self::MAXHIDDEN) {
            return false;
        }

        return self::applies($page);
    }

    /**
     * The layout's body attributes with {@see BODY_ARMED} added.
     *
     * Boost Union's layout files build the attribute string with body_attributes() before they export the primary
     * navigation, and hand both to one render_from_template() call, so the class is spliced in there, with the
     * regular expression the theme's login page uses ({@see login::layout_context()}).
     *
     * @param string $attributes The layout's bodyattributes.
     * @return string
     */
    public static function body_attributes(string $attributes): string {
        if (preg_match('/\bclass="([^"]*)"/', $attributes)) {
            return (string) preg_replace('/\bclass="([^"]*)"/', 'class="$1 ' . self::BODY_ARMED . '"', $attributes, 1);
        }

        return $attributes . ' class="' . self::BODY_ARMED . '"';
    }

    /**
     * The arguments of amd/src/compactnav.js's init().
     *
     * @return array maxhidden (int).
     */
    public static function client_config(): array {
        return ['maxhidden' => self::MAXHIDDEN];
    }
}
