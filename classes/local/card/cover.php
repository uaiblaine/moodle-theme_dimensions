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
 * Theme Dimensions - The image of a course with none: its category's cover
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\card;

use theme_dimensions\local\category\branding;

/**
 * The cover of a course with no image of its own, as an SVG data URI (D14, D3 of the course card contract).
 *
 * The colour is the course category's band: its own branding pair, else its nearest ancestor's
 * ({@see branding::colours()}), and where no category on the path has a pair, the theme's primary as the page's
 * stylesheet compiles it ({@see primary()}), never {@see branding::DEFAULT_BG}. The contract's cover overlay, a
 * 135 degree gradient from white at 16 % to black at 34 %, is baked into the image, because an image gets no CSS
 * overlay; the kind's watermark stays the card's own markup.
 *
 * The colour reaches the markup only as base64 inside the URI, after {@see branding::hex()}, so the answer is a
 * string of [A-Za-z0-9+/=] after a fixed prefix whatever was stored.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cover {
    /** @var string Boost's $primary without a brand colour: core 5.3's $mds-color-blue-500, which $blue is. */
    public const BOOST_PRIMARY = '#0f6cbf';

    /** @var string What every answer of {@see data_uri()} starts with. */
    public const PREFIX = 'data:image/svg+xml;base64,';

    /** @var int The image's width, the card's cover at its widest grid column; the SVG stretches to any box. */
    private const WIDTH = 320;

    /** @var int The image's height, the contract's 150 px cover. */
    private const HEIGHT = 150;

    /** @var string The overlay's first stop: white at 16 % (the contract's cover-overlay token). */
    private const OVERLAY_FROM = '<stop offset="0" stop-color="#ffffff" stop-opacity="0.16"/>';

    /** @var string The overlay's last stop: black at 34 %. */
    private const OVERLAY_TO = '<stop offset="1" stop-color="#000000" stop-opacity="0.34"/>';

    /**
     * The SVG document of a cover in one colour.
     *
     * The gradient runs from the top left corner to the bottom right one, the 135 degrees of the CSS overlay it
     * stands for; preserveAspectRatio="none" lets the image fill a box of any shape, as the cover's object-fit does.
     *
     * @param string $colour The band's colour, as {@see branding::hex()} spells it; anything else reads as the primary.
     * @return string
     */
    public static function svg(string $colour): string {
        $fill = branding::hex($colour) ?? self::BOOST_PRIMARY;
        $w = self::WIDTH;
        $h = self::HEIGHT;
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h
            . '" preserveAspectRatio="none"><defs><linearGradient id="o" x1="0" y1="0" x2="1" y2="1">'
            . self::OVERLAY_FROM . self::OVERLAY_TO . '</linearGradient></defs>'
            . '<rect width="' . $w . '" height="' . $h . '" fill="' . $fill . '"/>'
            . '<rect width="' . $w . '" height="' . $h . '" fill="url(#o)"/></svg>';
    }

    /**
     * The cover in one colour, as the data URI an image's src takes.
     *
     * @param string $colour The band's colour ({@see svg()}).
     * @return string
     */
    public static function data_uri(string $colour): string {
        return self::PREFIX . base64_encode(self::svg($colour));
    }

    /**
     * The theme's primary, as the page's stylesheet compiles it: the flavour's brand colour, else Boost Union's.
     *
     * Boost Union assigns $primary from its brandcolor setting (or a Moodle Workplace tenant's), and a flavour
     * that applies replaces it with its look_brandcolor (Boost Union's pre SCSS callback); without either, Boost's
     * own blue. A value that is not a hex colour (the colour picker takes any CSS colour) is passed over, since the
     * SVG is written here and never compiled. Config reads only, from the config cache.
     *
     * @param \stdClass|null $flavour The Boost Union flavour that applies to the page, or null.
     * @return string Lower-case #rrggbb.
     */
    public static function primary(?\stdClass $flavour): string {
        if ($flavour !== null) {
            $flavourcolour = branding::hex((string) ($flavour->look_brandcolor ?? ''));
            if ($flavourcolour !== null) {
                return $flavourcolour;
            }
        }
        $setting = (string) get_config(boost_union::COMPONENT, 'brandcolor');
        $branding = '\\local_boost_union_mwp\\local\\branding';
        if (
            class_exists('\\theme_boost_union\\local\\mwp')
            && \theme_boost_union\local\mwp::extension_present()
            && class_exists($branding)
            && method_exists($branding, 'get_pre_scss_configurable_value')
        ) {
            $setting = (string) $branding::get_pre_scss_configurable_value('brandcolor', $setting);
        }
        return branding::hex($setting) ?? self::BOOST_PRIMARY;
    }

    /**
     * The band colour of each category: its pair's background, inherited, else the primary.
     *
     * Through the branding cache ({@see branding::for_categories()}): no statement while warm; cold, seven per
     * category missing from it and one for all of them together, the entries the hero and the posters read after.
     *
     * @param int[] $categoryids The categories.
     * @param string $primary The colour of a category with no pair on its path ({@see primary()}).
     * @return array categoryid => lower-case #rrggbb.
     */
    public static function colours(array $categoryids, string $primary): array {
        $wanted = [];
        foreach ($categoryids as $categoryid) {
            if ((int) $categoryid > 0) {
                $wanted[(int) $categoryid] = (int) $categoryid;
            }
        }
        $colours = [];
        if (!$wanted) {
            return $colours;
        }
        foreach (branding::for_categories(array_values($wanted)) as $categoryid => $payload) {
            $own = (int) ($payload['colourfrom'] ?? 0) > 0 ? branding::hex((string) $payload['bgcolour']) : null;
            $colours[(int) $categoryid] = $own ?? $primary;
        }
        return $colours;
    }
}
