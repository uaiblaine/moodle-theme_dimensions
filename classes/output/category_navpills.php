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
 * Theme Dimensions - The category navigation as nav pills
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use theme_dimensions\local\category\navgroups;

/**
 * The category's secondary navigation as a row of nav pills in a capsule (the Design System's nav-pill.md).
 *
 * The units are {@see navgroups::rows()}' and every decision about them is that class's: which group a node
 * belongs to, the order, a group of one drawn plain, the current group. This class only turns them into the
 * template's context. Who gets a row at all is the hero's ({@see category_hero}): the anonymous shell, a page
 * without a secondary navigation and a viewer whose only destination is the page itself get none.
 *
 * The page works without JavaScript: a plain unit is a link, a group is a native disclosure whose menu holds one
 * link per destination, so every destination is one press away with no script. "More" is drawn by the server
 * whatever it holds: visible with the nodes the map does not know, hidden and empty otherwise, so a client that
 * moves the groups that do not fit into it composes no word of its own.
 *
 * Every icon is exported as a key and a component, for the template's pix helper with no alternative text: the
 * icon is decorative beside the destination's own text, and a node's alt can repeat that text.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class category_navpills implements renderable, templatable {
    /** @var string The region of the row's nav in its template, which the client and the tests find it by. */
    public const REGION = 'dim-navpills';

    /** @var string The prefix of the row's own id, which every menu's id starts with. */
    private const ID_PREFIX = 'dim-navpills-';

    /** @var string The id of this row on its page. */
    private readonly string $uniqid;

    /**
     * Constructor.
     *
     * @param array $units The units of {@see navgroups::rows()}, in its shapes.
     * @param string|null $uniqid The row's id; a new one when null.
     */
    public function __construct(
        /** @var array The units, in the row's order. */
        private readonly array $units,
        ?string $uniqid = null,
    ) {
        $this->uniqid = $uniqid ?? uniqid(self::ID_PREFIX);
    }

    /**
     * Whether a unit of the row opens a menu: a group's, or More's with nodes of its own.
     *
     * A row of plain pills needs no script; one that opens a menu is what a client arms.
     *
     * @return bool
     */
    public function has_groups(): bool {
        foreach ($this->units as $unit) {
            if ($unit['kind'] !== navgroups::KIND_ITEM) {
                return true;
            }
        }
        return false;
    }

    /**
     * Export the row's context.
     *
     * @param renderer_base $output The renderer.
     * @return array label, uniqid, units (the plain units and the groups, in the row's order), more and hasgroups.
     */
    public function export_for_template(renderer_base $output): array {
        $units = [];
        $more = null;
        foreach ($this->units as $unit) {
            if ($unit['kind'] === navgroups::KIND_MORE) {
                $more = $this->menu($unit);
                continue;
            }
            if ($unit['kind'] === navgroups::KIND_GROUP) {
                $units[] = ['isgroup' => true] + $this->menu($unit);
                continue;
            }
            $units[] = ['isgroup' => false] + self::destination($unit);
        }
        $more ??= $this->menu([
            'key' => navgroups::OTHER,
            'name' => navgroups::name(navgroups::OTHER),
            'current' => false,
            'items' => [],
        ]);

        return [
            'label' => get_string('categorynavigation', 'theme_dimensions'),
            'uniqid' => $this->uniqid,
            'units' => $units,
            'more' => $more,
            'hasgroups' => $this->has_groups(),
        ];
    }

    /**
     * A group's or More's unit as the template draws it.
     *
     * @param array $unit The unit: key, name, current and items.
     * @return array key, name, current, menuid, items and hasitems.
     */
    private function menu(array $unit): array {
        $items = array_map(static fn(array $item): array => self::destination($item), $unit['items']);
        return [
            'key' => (string) $unit['key'],
            'name' => $unit['name'],
            'current' => (bool) $unit['current'],
            'menuid' => $this->uniqid . '-' . $unit['key'],
            'items' => $items,
            'hasitems' => $items !== [],
        ];
    }

    /**
     * One destination as the template draws it.
     *
     * @param array $destination key, text, url (a core\url), icon (key and component) and current.
     * @return array key, text, url, current and icon, every value a string or a bool.
     */
    private static function destination(array $destination): array {
        return [
            'key' => (string) $destination['key'],
            'text' => $destination['text'],
            'url' => $destination['url']->out(false),
            'current' => (bool) $destination['current'],
            'icon' => [
                'key' => $destination['icon']['key'],
                'component' => $destination['icon']['component'],
            ],
        ];
    }
}
