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
 * Theme Dimensions - The pages strip of a category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

/**
 * The "Pages of this area" strip: a category's local_page pages the viewer may read, under the hero.
 *
 * local_page is optional and version.php does not declare it: installed, its category pages sit
 * beside the courses; absent, the showcase draws no strip and raises no error. The one coupling is
 * a class name asked through class_exists() and method_exists(), and every rule of what a viewer
 * may read is the plugin's own ({@see \local_page\local\catalogue::for_viewer()}): the visitor's
 * refusal, the category's own pages, live ones in their window, each page's access level. The
 * theme adds two rules:
 *
 *  - no strip at the root (the course index has no category, and the site-wide pages are not
 *    this feature's), and none without a page to name;
 *  - an address outside this site is dropped. The plugin spells its addresses through
 *    \local_page\local\links, so this only keeps a strip of internal pages from ever becoming a
 *    list of outbound links.
 *
 * No try/catch around the call: a throw from the plugin is a real fault and shows as one. The
 * strip is built by the page's two entries (the course renderer's shell and the anonymous request)
 * and never by the listing's export, which the rows web service calls on every search: the strip is
 * outside what a search rewrites, and would cost the catalogue's statement on each keystroke.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class category_pages {
    /** @var string The plugin's read API; tests hand in a stand-in or a class that does not exist. */
    public const CATALOGUE = 'local_page\local\catalogue';

    /** @var string Component name for get_string calls. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * The strip's template context for a category, or null when there is nothing to draw.
     *
     * @param int $categoryid The listed category; 0 is the root.
     * @param string|null $catalogue The catalogue class; tests pass a stand-in.
     * @return array|null Keys label and pages (each name, url, description, hasdescription), or null.
     */
    public static function export(int $categoryid, ?string $catalogue = null): ?array {
        global $CFG;

        if ($categoryid <= 0) {
            return null;
        }
        $catalogue = $catalogue ?? self::CATALOGUE;
        if (!class_exists($catalogue) || !method_exists($catalogue, 'for_viewer')) {
            return null;
        }

        $pages = [];
        foreach ($catalogue::for_viewer($categoryid) as $page) {
            $name = trim((string) ($page['name'] ?? ''));
            $url = (string) ($page['url'] ?? '');
            // This site's own addresses only: the slash matters, "wwwrootevil/..." is another site.
            if ($name === '' || !str_starts_with($url, $CFG->wwwroot . '/')) {
                continue;
            }
            $description = trim((string) ($page['description'] ?? ''));
            $pages[] = [
                'name' => $name,
                'url' => $url,
                'description' => $description,
                'hasdescription' => $description !== '',
            ];
        }
        if (!$pages) {
            return null;
        }
        return [
            'label' => get_string('category_pages', self::COMPONENT),
            'pages' => $pages,
        ];
    }
}
