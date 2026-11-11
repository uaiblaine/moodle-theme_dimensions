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
 * Theme Dimensions - Head tags of the public pages
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\output;

use theme_dimensions\output\opengraph;

/**
 * Renders the head tags of the theme's public pages into the document head.
 *
 * The hook is dispatched from core_renderer::standard_head_html(), so it runs
 * for every page this theme renders; the callback does nothing unless the
 * request of a public page (the landing page, a category page or the calendar) registered tags
 * through {@see opengraph::set()}.
 *
 * It uses this hook rather than before_http_headers, which core_renderer::header() also
 * dispatches, because only this one takes head HTML (add_html()). Neither runs for a
 * request that ends in an immediate redirect(), which sends its 303 before any output.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_standard_head_html_generation {
    /**
     * Append the tags, when the page being rendered is a public page of this theme.
     *
     * The robots directive follows the site's own rule: a site open to search engines
     * ($CFG->opentowebcrawlers, "Open to search engines" under Site security settings) lets
     * its public pages be indexed, and any other site marks them noindex, so that a page
     * exists there to unfurl a shared link and not to be listed. One rule for every public
     * page, decided for the site and never per page.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook The hook.
     * @return void
     */
    public static function callback(\core\hook\output\before_standard_head_html_generation $hook): void {
        global $CFG;

        $tags = opengraph::current();
        if ($tags === null) {
            return;
        }
        /* The robots directive and the canonical link are literal strings here, the way
           core's own standard_head_html() writes its meta tags, because the template lint
           validates a fragment as body content and rejects both there. The Open Graph
           metas, being RDFa, pass the lint and come from the template. One escaping pass
           each: s() for the attribute here, the double stashes in the template. */
        if (empty($CFG->opentowebcrawlers)) {
            $hook->add_html('<meta name="robots" content="noindex">' . "\n");
        }
        $hook->add_html('<link rel="canonical" href="' . s($tags->get_url()) . '">' . "\n");
        $hook->add_html($hook->renderer->render($tags));
    }
}
