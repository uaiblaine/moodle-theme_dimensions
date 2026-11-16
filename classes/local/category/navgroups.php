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
 * Theme Dimensions - The category navigation's groups
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use core\navigation\navigation_node;
use core\output\pix_icon;
use core\url;

/**
 * Regroups the category's secondary navigation into the row of nav pills the hero draws.
 *
 * The row is "Category" (the page itself), then one unit per group of {@see self::GROUPS} in that
 * order, then "More" holding every node this map does not know. A group with one destination for
 * the viewer is that destination's plain unit; a group with none is not on the row. A node the map
 * does not know is always an item of "More", even when it is the only one, never a plain unit.
 * Inside a group, and among More's own items, the destinations are in alphabetical order of the
 * text the viewer reads, through {@see \core_collator}, so a plugin's new node lands in a
 * predictable place whatever order the plugins load in.
 *
 * The input is what core decided to offer on the page: the children of the secondary navigation,
 * after its removals and its active-node scan. Nothing here asks a capability, reads a setting or
 * the database: core's settings navigation already did. The same nodes feed other readers (focus
 * mode's "More" among them), so nothing here changes a node: no make_active(), remove() or
 * set_force_into_more_menu().
 *
 * A unit is an array told apart by its 'kind':
 *
 * - {@see self::KIND_ITEM}, a plain destination: the destination's fields (below);
 * - {@see self::KIND_GROUP}: key (a key of GROUPS), name (the group's string), current (one of its
 *   destinations is the current page) and items (two or more destinations, alphabetical);
 * - {@see self::KIND_MORE}, always last: key ({@see self::OTHER}), name (core's "More"), current
 *   and items (one or more destinations, alphabetical).
 *
 * A destination is key (the node's own key, an int for a node core keyed by position), text (plain,
 * for a double stash), url (a {@see url}), icon (key and component of a pix icon) and current (the
 * node's own isactive).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class navgroups {
    /** @var string The key of the category page's own node: the row's first unit, never a member of a group. */
    public const MAIN = 'categorymain';

    /** @var string The bucket of the nodes the map does not know, which are More's own items. */
    public const OTHER = 'other';

    /** @var string A unit that is one destination, drawn as a plain pill. */
    public const KIND_ITEM = 'item';

    /** @var string A unit that is a group's pill and its menu. */
    public const KIND_GROUP = 'group';

    /** @var string The "More" unit, holding the nodes the map does not know. */
    public const KIND_MORE = 'more';

    /**
     * @var array The groups in the row's order, group => the node keys of its members. The order of the
     *   members is not the menu's: rows() sorts them by their text. The keys are the ones core and the
     *   plugins add to the category's settings navigation; {@see self::PATHS} places the nodes added
     *   with no key.
     */
    public const GROUPS = [
        'settings' => [
            'edit',
            'categoryappearance',
            'unlistedcoursescatstate',
            'local_page_pages',
            'tool_muhome_management',
            'contextlocking',
        ],
        'access' => [
            'permissions',
            'roles',
            'rolecheck',
            'cohort',
            'tool_mucohort_index',
            'tool_dynamic_cohorts_rules',
        ],
        'courses' => [
            'restorecourse',
            'contentbank',
            'filters',
            'tool_muprog_programs',
            'tool_mucertify_certifications',
            'tool_mucatalog_management',
            'tool_recyclebin',
        ],
        'competencies' => [
            'learningplantemplates',
            'competencyframeworks',
            'local_dimensions_central',
            'local_evidencehub_hub',
        ],
    ];

    /**
     * @var array Local paths => group, for the nodes a plugin added with a null key, which core keys by
     *   their position in the collection (tool_uploadcourse_extend_navigation_category_settings()).
     *   Consulted only for an integer key: a string key is the plugin's own name for its node.
     */
    public const PATHS = [
        '/admin/tool/uploadcourse/index.php' => 'courses',
    ];

    /** @var string The icon core gives a node that declared none, which Font Awesome draws as an empty box. */
    public const NO_ICON = 'i/navigationitem';

    /** @var string The core icon drawn for a node that declared none. */
    public const GENERIC_ICON = 'i/link';

    /**
     * The group a node belongs to by its own key, or OTHER when the map does not know it.
     *
     * The main node is not a group's member; rows() takes it before asking.
     *
     * @param int|string $key The node's key.
     * @param url|null $url The node's address, read only for an integer key.
     * @return string A key of GROUPS, or OTHER.
     */
    public static function group_of(int|string $key, ?url $url): string {
        if (is_int($key)) {
            return self::group_of_path($url);
        }
        foreach (self::GROUPS as $group => $keys) {
            if (in_array($key, $keys, true)) {
                return $group;
            }
        }
        return self::OTHER;
    }

    /**
     * The row's units, in order, read from the children of a secondary navigation.
     *
     * Every node is visited depth first and classified by its own key, never its parent's (core nests
     * Assign roles and Check permissions under Permissions). A node without an address is no
     * destination, but its children are still visited. A node whose address was already seen is
     * kept once, where it was first seen.
     *
     * @param navigation_node $nav The secondary navigation (its root is not a destination).
     * @return array The units, in the shapes the class docblock describes.
     */
    public static function rows(navigation_node $nav): array {
        $main = null;
        $groups = array_fill_keys(array_keys(self::GROUPS), []);
        $other = [];
        $seen = [];
        foreach (self::walk($nav) as $node) {
            $url = $node->action();
            if (!($url instanceof url)) {
                continue;
            }
            $address = $url->out(false);
            if (isset($seen[$address])) {
                continue;
            }
            $seen[$address] = true;
            $destination = self::destination($node, $url);
            if ($node->key === self::MAIN) {
                $main = $destination;
                continue;
            }
            $group = self::group_of($node->key, $url);
            if ($group === self::OTHER) {
                $other[] = $destination;
            } else {
                $groups[$group][] = $destination;
            }
        }

        $units = [];
        if ($main !== null) {
            $units[] = ['kind' => self::KIND_ITEM] + $main;
        }
        foreach ($groups as $group => $items) {
            if ($items === []) {
                continue;
            }
            if (count($items) === 1) {
                $units[] = ['kind' => self::KIND_ITEM] + $items[0];
                continue;
            }
            $units[] = self::menu(self::KIND_GROUP, $group, $items);
        }
        if ($other !== []) {
            $units[] = self::menu(self::KIND_MORE, self::OTHER, $other);
        }
        return $units;
    }

    /**
     * Whether a row of these units is drawn at all: not when the page itself is its only destination.
     *
     * A learner's secondary navigation holds "Category" alone, and a pill is never alone on a row.
     *
     * @param array $units The units rows() returned.
     * @return bool True when some unit is not the main node.
     */
    public static function has_row(array $units): bool {
        foreach ($units as $unit) {
            if ($unit['kind'] !== self::KIND_ITEM || $unit['key'] !== self::MAIN) {
                return true;
            }
        }
        return false;
    }

    /**
     * The name a group's pill reads: the theme's string for a group of GROUPS, core's "More" for OTHER.
     *
     * Spelled one literal per group, so the string ids stay greppable.
     *
     * @param string $group A key of GROUPS, or OTHER.
     * @return string The name in the viewer's language.
     * @throws \UnhandledMatchError For a group the map does not have.
     */
    public static function name(string $group): string {
        return match ($group) {
            'settings' => get_string('navgroup_settings', 'theme_dimensions'),
            'access' => get_string('navgroup_access', 'theme_dimensions'),
            'courses' => get_string('navgroup_courses', 'theme_dimensions'),
            'competencies' => get_string('navgroup_competencies', 'theme_dimensions'),
            self::OTHER => get_string('moremenu'),
        };
    }

    /**
     * The group of a node core keyed by its position, from its local path.
     *
     * @param url|null $url The node's address.
     * @return string A key of GROUPS, or OTHER.
     */
    private static function group_of_path(?url $url): string {
        if ($url === null || !$url->is_local_url()) {
            return self::OTHER;
        }
        $path = (new url($url->out_omit_querystring()))->out_as_local_url(false);
        return self::PATHS[$path] ?? self::OTHER;
    }

    /**
     * Every node under a parent, depth first, each before its children.
     *
     * @param navigation_node $parent The node whose descendants are walked.
     * @return \Generator Of navigation_node.
     */
    private static function walk(navigation_node $parent): \Generator {
        foreach ($parent->children as $child) {
            yield $child;
            yield from self::walk($child);
        }
    }

    /**
     * One node as a destination.
     *
     * @param navigation_node $node The node.
     * @param url $url Its address.
     * @return array key, text, url, icon and current.
     */
    private static function destination(navigation_node $node, url $url): array {
        return [
            'key' => $node->key,
            'text' => (string) $node->text,
            'url' => $url,
            'icon' => self::icon($node->icon),
            'current' => (bool) $node->isactive,
        ];
    }

    /**
     * A node's icon as a key and a component, the generic one when the node declared none.
     *
     * The icon's alt text is not kept: the icon is decorative beside the node's text, and some
     * nodes' alt repeats it (the recycle bin's names the plugin).
     *
     * @param mixed $icon The node's icon property, a pix_icon or anything core left there.
     * @return array key and component.
     */
    private static function icon(mixed $icon): array {
        if ($icon instanceof pix_icon && (string) $icon->pix !== '' && $icon->pix !== self::NO_ICON) {
            return ['key' => (string) $icon->pix, 'component' => (string) $icon->component];
        }
        return ['key' => self::GENERIC_ICON, 'component' => 'core'];
    }

    /**
     * A group's or More's unit, its destinations in alphabetical order of their text.
     *
     * @param string $kind KIND_GROUP or KIND_MORE.
     * @param string $key A key of GROUPS, or OTHER.
     * @param array $items Its destinations, in the tree's order.
     * @return array The unit.
     */
    private static function menu(string $kind, string $key, array $items): array {
        \core_collator::asort_array_of_arrays_by_key($items, 'text');
        $items = array_values($items);
        return [
            'kind' => $kind,
            'key' => $key,
            'name' => self::name($key),
            'current' => in_array(true, array_column($items, 'current'), true),
            'items' => $items,
        ];
    }
}
