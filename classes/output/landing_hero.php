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
 * Theme Dimensions - The full width banner of a course landing page
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

/**
 * The landing page's banner drawn as the page header, edge to edge: {@see \theme_dimensions\local\landing\heroformat}.
 *
 * Armed by {@see \theme_dimensions\local\landing\request::handle()} before the header is printed and drawn by the
 * core renderer's full_header(), as the category showcase's hero is ({@see category_hero}). It carries the page it
 * belongs to and exports that page's context, which the page computes once, so the banner in the header and the
 * page below it are drawn from the same values and the page leaves its own banner out. The registry is static: a
 * test that serves a landing page resets it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class landing_hero implements \core\output\named_templatable, \core\output\renderable {
    /** @var self|null The banner armed for the page being rendered. */
    private static ?self $current = null;

    /**
     * Constructor.
     *
     * @param landing_page $page The landing page the banner belongs to.
     */
    public function __construct(
        /** @var landing_page The landing page the banner belongs to. */
        private readonly landing_page $page,
    ) {
    }

    /**
     * Arm a banner for the page being rendered.
     *
     * @param self $hero The banner.
     * @return void
     */
    public static function arm(self $hero): void {
        self::$current = $hero;
    }

    /**
     * The banner armed for the page being rendered, or null.
     *
     * @return self|null
     */
    public static function current(): ?self {
        return self::$current;
    }

    /**
     * Disarm the registry.
     *
     * @return void
     */
    public static function reset(): void {
        self::$current = null;
    }

    /**
     * The template rendering this renderable.
     *
     * @param \core\output\renderer_base $renderer The renderer.
     * @return string The template name.
     */
    public function get_template_name(\core\output\renderer_base $renderer): string {
        return 'theme_dimensions/landing_header';
    }

    /**
     * Export the banner's context: the page's own, with the header's actions and extras.
     *
     * @param \core\output\renderer_base $output The renderer.
     * @return \stdClass The template context.
     */
    public function export_for_template(\core\output\renderer_base $output): \stdClass {
        global $PAGE;

        $context = clone $this->page->export_for_template($output);
        $context->headeractions = $PAGE->get_header_actions();
        $context->headerextras = $PAGE->get_header_extras();
        $context->hasheaderextras = !empty($context->headerextras);
        return $context;
    }
}
