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
 * Theme Dimensions - The mobile drawer of a logged-in account
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * Whether the mobile primary drawer is the theme's own, and the template data it is drawn from.
 *
 * The drawer is theme_boost/primary-drawer-mobile, which the theme overrides; the renderer writes
 * {@see context()} into the layout context as dimensionsdrawer while {@see applies()}, and the override
 * draws theme_dimensions/drawer_nav instead of Boost Union's list. Nothing here renders: the builder
 * reads only the primary navigation core and Boost Union already put in the layout context
 * (mobileprimarynav) and the page, so every clause is testable alone.
 *
 * The drawer is where to go, and holds nothing about the person (moodle-dev/docs/design-system/components/
 * side-layer.md, D72): the identity, the colour mode, the Look and the language are the profile layer's
 * ({@see profilelayer}). What it shows, top to bottom:
 *
 * - The "Continue where you left off" card: a skeleton the client fills from a deferred call
 *   ({@see drawersummary}) when the drawer is first shown, never with the page.
 * - The sections: core's primary nodes and the custom menu as "Browse"; each Boost Union smart menu in
 *   submenu mode as a section of its own, labelled with the menu's title, its items as the rows. Inline
 *   smart menus are already merged into the top level by Boost Union and stay in "Browse".
 *
 * A row with children opens a sub-panel. Every panel is rendered flat, after the root, as a section with
 * an anchor of its own: without the client module, the row is a link to that section and its back control
 * a link to the parent's, so every level stays reachable; amd/src/drawer.js turns the same markup into
 * sliding panels.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class drawernav {
    /** @var string Component name, used for every config read and string. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string The feature's setting, an admin_setting_configcheckbox that ships on. */
    public const SETTING = 'navbardrawer';

    /** @var string The id of the root panel; amd/src/drawer.js starts there. */
    public const ROOT = 'dimensions-drawer-panel-root';

    /** @var string The prefix of every other panel's id, followed by a counter. */
    public const PANEL_PREFIX = 'dimensions-drawer-panel-';

    /** @var string The prefix of a section label's id, followed by the section's position. */
    public const SECTION_PREFIX = 'dimensions-drawer-section-';

    /**
     * @var array The Font Awesome classes of core's primary nodes, by navigation key.
     *
     * The keys are the ones core\navigation\views\primary adds. home, myhome and siteadminnode take the
     * glyph core's own pix icon maps to (i/home, i/dashboard, i/settings); mycourses has no icon in core
     * and takes the one core maps i/course to.
     */
    public const ICONS = [
        'home' => 'fa fa-house',
        'myhome' => 'fa fa-gauge',
        'mycourses' => 'fa fa-graduation-cap',
        'siteadminnode' => 'fa fa-gear',
        'calendar' => 'fa-regular fa-calendar',
    ];

    /** @var string The glyph of a row that has neither a mapped key nor an icon of its own. */
    public const DEFAULT_ICON = 'fa fa-circle-dot';

    /** @var string[] The link attributes a node may carry onto its row. Boost Union's smart menu items set target. */
    private const ATTRIBUTES = ['target', 'rel'];

    /** @var string The navigation key of the calendar: core's guest node, and the row the drawer adds for an account. */
    public const CALENDAR = 'calendar';

    /**
     * The theme's calendar page, where the drawer's calendar row leads.
     *
     * @return \core\url
     */
    public static function calendar_url(): \core\url {
        return \theme_dimensions\local\calendar\page::url();
    }

    /**
     * Browse with the calendar row: core adds its calendar node for the guest account alone, so an account's drawer
     * gains one after core's own nodes (My courses, else Dashboard, else Home), unless a node already carries the key,
     * Boost Union's "hide nodes in the primary navigation" setting names the calendar, or none of core's nodes is
     * there to stand beside (a navigation the site emptied gets nothing added).
     *
     * @param array $browse The Browse rows, each with the node's key under 'key'.
     * @param \moodle_page $page The page being rendered, whose theme carries Boost Union's removed nodes.
     * @return array The rows.
     */
    private static function with_calendar(array $browse, \moodle_page $page): array {
        $keys = array_column($browse, 'key');
        if (in_array(self::CALENDAR, $keys, true)) {
            return $browse;
        }
        if (in_array(self::CALENDAR, (array) ($page->theme->removedprimarynavitems ?? []), true)) {
            return $browse;
        }
        $row = [
            'text' => get_string('calendar', 'calendar'),
            'url' => self::calendar_url()->out(),
            'icon' => self::ICONS[self::CALENDAR],
            'iscurrent' => (string) $page->pagetype === \theme_dimensions\local\calendar\page::PAGETYPE,
            'haschildren' => false,
            'panelid' => '',
            'tooltip' => '',
            'attributes' => [],
            'key' => self::CALENDAR,
        ];
        foreach (['mycourses', 'myhome', 'home'] as $anchor) {
            $after = array_search($anchor, $keys, true);
            if ($after !== false) {
                array_splice($browse, $after + 1, 0, [$row]);
                return $browse;
            }
        }
        return $browse;
    }

    /**
     * Whether the feature is switched on.
     *
     * A default-on checkbox reads only an explicit '0' as off, so a site that never saved the setting
     * (false) has the feature without an upgrade step writing it.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (string) get_config(self::COMPONENT, self::SETTING) !== '0';
    }

    /**
     * Whether the page being rendered gets the theme's drawer in place of Boost Union's list.
     *
     * A logged-in real account only: a visitor and the guest account keep Boost Union's list with the
     * "New here?" card ({@see guestnav}), and an administrator logged in as somebody holds a real account.
     * The layouts are guestnav's, for the same reason; the Moodle app is left alone, as there.
     *
     * @param \moodle_page $page The page being rendered.
     * @return bool
     */
    public static function applies(\moodle_page $page): bool {
        if (!self::enabled()) {
            return false;
        }
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        if (in_array((string) $page->pagelayout, guestnav::EXCLUDED_LAYOUTS, true)) {
            return false;
        }
        if (\core_useragent::is_moodle_app()) {
            return false;
        }

        return true;
    }

    /**
     * The template data of theme_dimensions/drawer_nav.
     *
     * @param array $nodes The layout context's mobileprimarynav: core's arrays, the custom menu's and Boost
     *      Union's objects.
     * @param \moodle_page $page The page being rendered, for the calendar row.
     * @return array rootid, sections and panels.
     */
    public static function context(array $nodes, \moodle_page $page): array {
        $panels = [];
        $counter = 0;
        $rootlabel = get_string('navigation');

        $browse = [];
        $sections = [];
        foreach ($nodes as $node) {
            $node = (array) $node;
            if (array_key_exists('menudata', $node)) {
                $label = self::plain((string) ($node['text'] ?? ''));
                $items = self::items((array) ($node['children'] ?? []), self::ROOT, $rootlabel, 0, $panels, $counter);
                if ($items) {
                    $sections[] = ['id' => '', 'label' => $label, 'items' => $items];
                }
                continue;
            }
            if ((string) ($node['key'] ?? '') === self::CALENDAR) {
                $node['url'] = self::calendar_url();
            }
            $item = self::item($node, self::ROOT, $rootlabel, 0, $panels, $counter, true);
            if ($item !== null) {
                $browse[] = $item + ['key' => (string) ($node['key'] ?? '')];
            }
        }
        $browse = self::with_calendar($browse, $page);
        $browse = array_map(static function (array $item): array {
            unset($item['key']);
            return $item;
        }, $browse);
        if ($browse) {
            array_unshift($sections, ['id' => '', 'label' => get_string('drawer_browse', self::COMPONENT), 'items' => $browse]);
        }
        // A section's label names its list (aria-labelledby), and one render holds one {{uniqid}}.
        foreach (array_keys($sections) as $index) {
            $sections[$index]['id'] = self::SECTION_PREFIX . ($index + 1);
        }

        return [
            'rootid' => self::ROOT,
            'sections' => $sections,
            'panels' => $panels,
        ];
    }

    /**
     * The rows of a list of nodes, dividers dropped.
     *
     * @param array $nodes The nodes.
     * @param string $parentid The id of the panel the rows are drawn in.
     * @param string $parentlabel The plain name of that panel, for the name of a sub-panel's back control.
     * @param int $level The level of that panel, 0 for the root.
     * @param array $panels The panels collected so far, appended to.
     * @param int $counter The last panel number handed out, advanced.
     * @return array The rows.
     */
    private static function items(
        array $nodes,
        string $parentid,
        string $parentlabel,
        int $level,
        array &$panels,
        int &$counter
    ): array {
        $items = [];
        foreach ($nodes as $node) {
            $item = self::item((array) $node, $parentid, $parentlabel, $level, $panels, $counter, $level === 0);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * One row, and the sub-panel of its children when it has any.
     *
     * Every key is always set: the template's context stack would otherwise resolve a missing one from the
     * layout context around the drawer.
     *
     * The text is printed raw, as Boost Union's and core's drawers print it: core's node text, the custom
     * menu's escaped label, or Boost Union's smart menu markup (which carries the item's own icon).
     *
     * @param array $node The node.
     * @param string $parentid The id of the panel the row is drawn in.
     * @param string $parentlabel The plain name of that panel.
     * @param int $level The level of that panel.
     * @param array $panels The panels collected so far, appended to.
     * @param int $counter The last panel number handed out, advanced.
     * @param bool $withicon Whether the row carries a leading glyph: the root's rows do, a sub-panel's do not.
     * @return array|null The row, or null for a divider.
     */
    private static function item(
        array $node,
        string $parentid,
        string $parentlabel,
        int $level,
        array &$panels,
        int &$counter,
        bool $withicon
    ): ?array {
        if (!empty($node['divider'])) {
            return null;
        }

        $text = (string) ($node['text'] ?? '');
        $children = array_values((array) ($node['children'] ?? []));
        $haschildren = !empty($node['haschildren']) && $children !== [];
        $ownicon = !empty($node['itemdata']->menuicon);

        $item = [
            'text' => $text,
            'url' => self::url($node['url'] ?? null),
            'icon' => ($withicon && !$ownicon) ? (self::ICONS[(string) ($node['key'] ?? '')] ?? self::DEFAULT_ICON) : '',
            'iscurrent' => !$haschildren && !empty($node['isactive']),
            'haschildren' => $haschildren,
            'panelid' => '',
            'tooltip' => (string) ($node['tooltip'] ?? ''),
            'attributes' => self::attributes((array) ($node['attributes'] ?? [])),
        ];

        if ($haschildren) {
            $item['panelid'] = self::panel($node, $children, $parentid, $parentlabel, $level + 1, $panels, $counter);
        }

        return $item;
    }

    /**
     * Collect the sub-panel of a node with children, and its own sub-panels after it.
     *
     * The panel's slot is taken before its rows are built, so the flat list keeps a parent before its
     * children: the reading order of the drawer without the client module.
     *
     * @param array $node The node.
     * @param array $children Its children.
     * @param string $parentid The id of the panel the node's row is drawn in.
     * @param string $parentlabel The plain name of that panel.
     * @param int $level The level of the new panel.
     * @param array $panels The panels collected so far, appended to.
     * @param int $counter The last panel number handed out, advanced.
     * @return string The new panel's id.
     */
    private static function panel(
        array $node,
        array $children,
        string $parentid,
        string $parentlabel,
        int $level,
        array &$panels,
        int &$counter
    ): string {
        $counter++;
        $id = self::PANEL_PREFIX . $counter;
        $slot = count($panels);
        $panels[] = [];

        $title = self::plain((string) ($node['text'] ?? ''));
        if ($title === '') {
            $title = self::plain((string) ($node['tooltip'] ?? ''));
        }
        $url = self::url($node['url'] ?? null);
        $items = self::items($children, $id, $title, $level, $panels, $counter);

        $panels[$slot] = [
            'id' => $id,
            'parentid' => $parentid,
            'level' => $level,
            'title' => $title,
            'backaria' => get_string('drawer_back', self::COMPONENT, $parentlabel),
            'items' => $items,
            'hasurl' => $url !== '' && $url !== '#',
            'url' => $url,
            'openlabel' => get_string('drawer_openparent', self::COMPONENT, $title),
        ];

        return $id;
    }

    /**
     * A node's address as its drawer prints it.
     *
     * The template prints the address raw, as Boost Union's drawer does, so a moodle_url is spelled the
     * way its __toString() would (out(), escaped) and a string - the custom menu's, already escaped, and
     * Boost Union's - is left as it came.
     *
     * @param mixed $url The node's url: a moodle_url, a string or null.
     * @return string
     */
    private static function url($url): string {
        if ($url instanceof \moodle_url) {
            return $url->out();
        }
        if (is_scalar($url)) {
            return (string) $url;
        }

        return '';
    }

    /**
     * The link attributes a row keeps: the ones of {@see self::ATTRIBUTES}, in Boost Union's shape.
     *
     * @param array $attributes The node's attributes, each with name and value.
     * @return array The kept ones, each with name and value.
     */
    private static function attributes(array $attributes): array {
        $kept = [];
        foreach ($attributes as $attribute) {
            $attribute = (array) $attribute;
            $name = (string) ($attribute['name'] ?? '');
            if (in_array($name, self::ATTRIBUTES, true)) {
                $kept[] = ['name' => $name, 'value' => (string) ($attribute['value'] ?? '')];
            }
        }

        return $kept;
    }

    /**
     * The plain text of a node's label: tags dropped, entities decoded, whitespace collapsed.
     *
     * For the places that need a name rather than markup - a section's label, a panel's heading, the back
     * control - all printed escaped by the template.
     *
     * @param string $html The label.
     * @return string
     */
    public static function plain(string $html): string {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('~\s+~u', ' ', $text));
    }
}
