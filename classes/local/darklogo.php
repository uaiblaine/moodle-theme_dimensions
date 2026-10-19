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
 * Theme Dimensions - The logo variants for dark surfaces
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * The site's logo variants for dark surfaces: where their files live, when they apply, and the stylesheet that paints them.
 *
 * Neither core nor Boost Union has a dark logo, so the theme pairs two file settings of its own with Boost Union's
 * two: {@see self::FULL} with `theme_boost_union | logo`, {@see self::COMPACT} with `theme_boost_union | logocompact`.
 * A dark file is a variant, never a logo of its own: without its light partner it is not used, and the page shows
 * what Boost Union shows today (the site name, for the compact one).
 *
 * The browser decides which surface is dark, by the nearest `data-bs-theme` (scss/post.scss); this class decides
 * whether the rules that paint the variant are armed, which takes two facts recorded in two different places:
 *
 * - {@see self::url()}: the file, its light partner and an address {@see styleurl::safe()} accepts. Config and file
 *   settings only, never the page, because the extra SCSS callback asks it while the stylesheet is compiled, which
 *   also happens on the command line ({@see self::scss()}).
 * - {@see self::site_logo_in_force()}: the logo the page shows is the site's own, the way Boost Union's renderer
 *   resolves it (get_logo_url() and get_compact_logo_url()): an applicable flavour's look_logo or look_logocompact
 *   first, then the multi-tenancy extension's override, then the site setting. A page shows the flavour's logo while
 *   the site's dark file would describe another image, so the rules stay disarmed there. Each variant is decided on
 *   its own field: a flavour that replaces only the compact logo leaves the full one the site's.
 *
 * The renderer joins the two in {@see self::body_classes()}; the stylesheet's consumers are scoped under those classes.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class darklogo {
    /** @var string The full logo's dark variant: its setting and its file area, the name being the same. */
    public const FULL = 'logodark';

    /** @var string The compact logo's dark variant: its setting and its file area. */
    public const COMPACT = 'logocompactdark';

    /** @var string[] Both variants, which are also the file areas theme_dimensions_pluginfile() serves for them. */
    public const AREAS = [self::FULL, self::COMPACT];

    /** @var array Each variant => its light partner, a setting of theme_boost_union. */
    private const LIGHT = [self::FULL => 'logo', self::COMPACT => 'logocompact'];

    /** @var array Each variant => the flavour's field that replaces its light partner (theme_boost_union_flavours). */
    private const FLAVOUR_FIELD = [self::FULL => 'look_logo', self::COMPACT => 'look_logocompact'];

    /**
     * @var array Each variant => the image the multi-tenancy extension overrides it with, as Boost Union's renderer asks for
     *      it: 'loginlogo' in get_logo_url(), 'headerlogo' in get_compact_logo_url().
     */
    private const MWP_IMAGE = [self::FULL => 'loginlogo', self::COMPACT => 'headerlogo'];

    /** @var array Each variant => the body class that arms its rules. */
    public const BODY_CLASSES = [self::FULL => 'dimensions-darklogo-full', self::COMPACT => 'dimensions-darklogo-compact'];

    /** @var array Each variant => the stem of the custom properties scss/post.scss reads for it. */
    private const PROPERTY = [self::FULL => '--dimensions-logo', self::COMPACT => '--dimensions-logo-compact'];

    /**
     * @var string How far the light image is moved out of its own box on a dark surface.
     *
     * The light image is moved rather than faded: an <img> with opacity 0 hides its own background as well, which is
     * where the dark file is painted. Any length larger than the widest box works; the replaced content beyond the
     * content box is clipped.
     */
    private const SHIFT = '10000px';

    /**
     * The dark variant's address for a stylesheet, or null when it must not be painted.
     *
     * Null without the dark file, without its light partner in Boost Union, and for an address
     * {@see styleurl::safe()} refuses. The address is the theme's own file route, carrying the theme revision, as core
     * addresses every theme setting file; it is made absolute, because core answers it protocol-relative.
     *
     * @param string $variant {@see self::FULL} or {@see self::COMPACT}.
     * @param \core\output\theme_config $theme This theme's configuration: the renderer's page theme, or the one the SCSS
     *        callback is handed. Loaded by name when it is another theme's.
     * @return string|null
     */
    public static function url(string $variant, \core\output\theme_config $theme): ?string {
        global $CFG;

        if (empty(get_config('theme_boost_union', self::LIGHT[$variant]))) {
            return null;
        }
        if ($theme->name !== 'dimensions') {
            $theme = \core\output\theme_config::load('dimensions');
        }
        $url = $theme->setting_file_url($variant, $variant);
        if ($url === null) {
            return null;
        }
        if (str_starts_with($url, '//')) {
            $url = parse_url($CFG->wwwroot, PHP_URL_SCHEME) . ':' . $url;
        }

        // A stylesheet context like the inline styles styleurl was written for: a quote would close url("..."), and
        // PARAM_URL already refuses the braces Sass would read as an interpolation.
        $url = styleurl::safe($url);

        return ($url === '') ? null : $url;
    }

    /**
     * Whether theme_dimensions_pluginfile() serves this request as a dark logo: one of the two areas, in the system
     * context, where the settings keep their files.
     *
     * No capability: the logos are public, as Boost Union's are, because every visitor's navbar and login page draw
     * them.
     *
     * @param \core\context $context The context the address names.
     * @param string $filearea The file area it names.
     * @return bool
     */
    public static function serves(\core\context $context, string $filearea): bool {
        return $context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, self::AREAS, true);
    }

    /**
     * Whether the logo of this variant on the current page is the site's own, as Boost Union's renderer resolves it.
     *
     * @param string $variant {@see self::FULL} or {@see self::COMPACT}.
     * @param \stdClass|null $flavour The Boost Union flavour that applies to the page, from {@see self::applied_flavour()}.
     * @return bool False when the flavour or the multi-tenancy extension replaces the logo.
     */
    public static function site_logo_in_force(string $variant, ?\stdClass $flavour): bool {
        if ($flavour !== null && !empty($flavour->{self::FLAVOUR_FIELD[$variant]})) {
            return false;
        }

        $branding = '\\local_boost_union_mwp\\local\\branding';
        if (
            \theme_boost_union\local\mwp::extension_present()
            && class_exists($branding)
            && method_exists($branding, 'get_overridden_image')
        ) {
            [$overridden] = $branding::get_overridden_image(self::MWP_IMAGE[$variant]);
            if ($overridden) {
                return false;
            }
        }

        return true;
    }

    /**
     * The Boost Union flavour that applies to the current page, or null.
     *
     * Boost Union's own lookup, the one its renderer and its body classes use. It reads the page, so only a renderer
     * may ask: never the SCSS callbacks, which also run on the command line.
     *
     * @return \stdClass|null The flavour's record.
     */
    public static function applied_flavour(): ?\stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/theme/boost_union/flavours/flavourslib.php');

        $flavour = theme_boost_union_get_flavour_which_applies();

        return ($flavour instanceof \stdClass) ? $flavour : null;
    }

    /**
     * The body classes that arm the dark variants on the current page.
     *
     * A variant is armed only while its address exists and the logo in force is the site's own; the address is asked
     * first, so a site without a dark file pays one config read per variant.
     *
     * @param \core\output\theme_config $theme This theme's configuration.
     * @param \stdClass|null $flavour The flavour that applies to the page.
     * @return string[]
     */
    public static function body_classes(\core\output\theme_config $theme, ?\stdClass $flavour): array {
        $classes = [];
        foreach (self::AREAS as $variant) {
            if (self::url($variant, $theme) !== null && self::site_logo_in_force($variant, $flavour)) {
                $classes[] = self::BODY_CLASSES[$variant];
            }
        }

        return $classes;
    }

    /**
     * The settings-driven half of the stylesheet: each available variant's address, declared on the root.
     *
     * Two properties per variant, the address and the shift that moves the light image out of its box, emitted together
     * and only with an address: a stylesheet compiled before the file existed leaves the light image where it is.
     * scss/post.scss hands them to the dark surfaces and its consumers read them under the body classes.
     *
     * @param \core\output\theme_config $theme This theme's configuration, as the SCSS callback is handed it.
     * @return string SCSS, '' when no variant is available.
     */
    public static function scss(\core\output\theme_config $theme): string {
        $declarations = '';
        foreach (self::AREAS as $variant) {
            $url = self::url($variant, $theme);
            if ($url === null) {
                continue;
            }
            $declarations .= '    ' . self::PROPERTY[$variant] . '-dark-src: url("' . $url . '");' . "\n";
            $declarations .= '    ' . self::PROPERTY[$variant] . '-dark-shift: ' . self::SHIFT . ';' . "\n";
        }
        if ($declarations === '') {
            return '';
        }

        return "\n:root {\n" . $declarations . "}\n";
    }
}
