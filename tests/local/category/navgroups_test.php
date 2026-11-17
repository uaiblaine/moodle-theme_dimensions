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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the map from the category's secondary navigation to the row of nav pills.
 *
 * Every tree is built with navigation_node::create(), with the keys core and the plugins really add:
 * CI installs neither the tool_mu plugins nor local_dimensions nor local_evidencehub, so a test over
 * the live tree could not run there. The tree's order is core's (the plugins' load order) wherever it
 * differs from the row's, so an order that merely followed the tree would be seen.
 *
 * A unit is read through shape(): a plain unit is its key, a group is "group[key,key]" and More is
 * "more[key,key]", the keys in the order the menu lists them.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(navgroups::class)]
final class navgroups_test extends \advanced_testcase {
    /**
     * A secondary navigation's root, which is no destination itself.
     *
     * @return navigation_node
     */
    private static function root(): navigation_node {
        return navigation_node::create('Secondary', null, navigation_node::TYPE_ROOTNODE, null, 'secondary');
    }

    /**
     * Add a node under a parent, as a settings navigation callback would.
     *
     * The address is distinct per text unless one is given, so no two nodes collide by accident. A
     * node is never current unless the test says so: the constructor's own active check compares
     * with whatever page address an earlier test left behind.
     *
     * @param navigation_node $parent The parent.
     * @param int|string|null $key The key; null lets core key it by position.
     * @param string $text The text the viewer reads.
     * @param string|null $path The local path of its address, or null for a node without one; '' derives one.
     * @param pix_icon|null $icon The icon it declares, or null for none.
     * @param bool $current Whether it is the current page.
     * @return navigation_node The added node.
     */
    private static function add(
        navigation_node $parent,
        int|string|null $key,
        string $text,
        ?string $path = '',
        ?pix_icon $icon = null,
        bool $current = false
    ): navigation_node {
        if ($path === '') {
            $path = '/nav/' . md5($text) . '.php';
        }
        $action = $path === null ? null : new url($path, ['categoryid' => 7]);
        $node = navigation_node::create($text, $action, navigation_node::TYPE_SETTING, null, $key, $icon);
        $node->isactive = $current;
        return $parent->add_node($node);
    }

    /**
     * The units as short strings: a plain unit's key, "group[keys]" and "more[keys]".
     *
     * @param array $units What rows() returned.
     * @return array One string per unit.
     */
    private static function shape(array $units): array {
        return array_map(function (array $unit): string {
            if ($unit['kind'] === navgroups::KIND_ITEM) {
                return (string) $unit['key'];
            }
            $keys = array_map(fn(array $item): string => (string) $item['key'], $unit['items']);
            $name = $unit['kind'] === navgroups::KIND_MORE ? 'more' : $unit['key'];
            return $name . '[' . implode(',', $keys) . ']';
        }, $units);
    }

    /**
     * What a tree looks like to its other readers: every node's key, flags, classes and children.
     *
     * @param navigation_node $node The node whose children are read.
     * @return array The snapshot.
     */
    private static function snapshot(navigation_node $node): array {
        $out = [];
        foreach ($node->children as $child) {
            $out[] = [
                'key' => $child->key,
                'isactive' => $child->isactive,
                'forceopen' => $child->forceopen,
                'forceintomoremenu' => $child->forceintomoremenu,
                'showinsecondarynavigation' => $child->showinsecondarynavigation,
                'classes' => $child->classes,
                'children' => self::snapshot($child),
            ];
        }
        return $out;
    }

    /**
     * The administrator's tree on a site with every plugin of the map, in core's order (the live dump).
     *
     * @return array [the root, the upload node's key].
     */
    private static function administrators_tree(): array {
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        self::add($root, 'edit', 'Settings');
        $permissions = self::add($root, 'permissions', 'Permissions');
        self::add($permissions, 'roles', 'Assign roles');
        self::add($permissions, 'rolecheck', 'Check permissions');
        self::add($root, 'cohort', 'Cohorts');
        self::add($root, 'filters', 'Filters');
        self::add($root, 'restorecourse', 'Restore course');
        self::add($root, 'tool_dynamic_cohorts_rules', 'Dynamic cohort rules');
        self::add($root, 'learningplantemplates', 'Learning plan templates');
        self::add($root, 'competencyframeworks', 'Competency frameworks');
        self::add($root, 'tool_mucatalog_management', 'Section management');
        self::add($root, 'tool_mucertify_certifications', 'Certifications');
        self::add($root, 'tool_mucohort_index', 'Cohort management');
        self::add($root, 'tool_muhome_management', 'Home pages management');
        self::add($root, 'tool_muprog_programs', 'Programs');
        $upload = self::add($root, null, 'Upload courses', '/admin/tool/uploadcourse/index.php');
        self::add($root, 'categoryappearance', 'Category appearance');
        self::add($root, 'local_dimensions_central', 'Competency hub');
        self::add($root, 'local_evidencehub_hub', 'Evidence hub');
        self::add($root, 'local_page_pages', 'Custom pages');
        self::add($root, 'contentbank', 'Content bank');
        self::add($root, 'unlistedcoursescatstate', 'Discoverability');
        self::add($root, 'contextlocking', 'Freeze this context');
        self::add($root, 'tool_recyclebin', 'Recycle bin');
        self::add($root, 'insights', 'Insights');
        return [$root, $upload->key];
    }

    /**
     * Every key of the map lands in its group, each group alphabetical, the groups in the row's order.
     */
    public function test_every_key_of_the_map_lands_in_its_group(): void {
        [$root, $upload] = self::administrators_tree();
        $this->assertIsInt($upload);

        $this->assertSame([
            'categorymain',
            'settings[categoryappearance,local_page_pages,unlistedcoursescatstate,contextlocking,tool_muhome_management,edit]',
            'access[roles,rolecheck,tool_mucohort_index,cohort,tool_dynamic_cohorts_rules,permissions]',
            'courses[tool_mucertify_certifications,contentbank,filters,tool_muprog_programs,tool_recyclebin,restorecourse,'
                . 'tool_mucatalog_management,' . $upload . ']',
            'competencies[competencyframeworks,local_dimensions_central,local_evidencehub_hub,learningplantemplates]',
            'more[insights]',
        ], self::shape(navgroups::rows($root)));
    }

    /**
     * A key the map does not know is an item of More, which is the last unit; a known key never is.
     */
    public function test_an_unknown_key_is_an_item_of_more_and_more_is_last(): void {
        $root = self::root();
        self::add($root, 'insights', 'Insights');
        self::add($root, 'categorymain', 'Category');
        self::add($root, 'local_reports_category', 'Accessibility reports');
        self::add($root, 'filters', 'Filters');
        self::add($root, 'contentbank', 'Content bank');

        $units = navgroups::rows($root);

        $this->assertSame(
            ['categorymain', 'courses[contentbank,filters]', 'more[local_reports_category,insights]'],
            self::shape($units)
        );
        $more = end($units);
        $this->assertSame(navgroups::KIND_MORE, $more['kind']);
        $this->assertSame(navgroups::OTHER, $more['key']);
        $this->assertSame(navgroups::OTHER, navgroups::group_of('insights', null));
        // Control: a known key is answered with its group, so the bucket is not everybody's answer.
        $this->assertSame('courses', navgroups::group_of('filters', null));
    }

    /**
     * A lone unknown node is still an item of More, never a plain unit; a lone known one is plain.
     */
    public function test_a_lone_unknown_node_is_not_a_plain_unit(): void {
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        self::add($root, 'insights', 'Insights');

        $units = navgroups::rows($root);
        $this->assertSame(['categorymain', 'more[insights]'], self::shape($units));
        $this->assertSame(navgroups::KIND_MORE, $units[1]['kind']);

        // Control: a group of one known destination is that destination's plain unit.
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        self::add($root, 'filters', 'Filters');
        $units = navgroups::rows($root);
        $this->assertSame(['categorymain', 'filters'], self::shape($units));
        $this->assertSame(navgroups::KIND_ITEM, $units[1]['kind']);
    }

    /**
     * A node core keyed by position is placed by its path, and only such a node.
     */
    public function test_an_integer_key_is_placed_by_its_upload_path_alone(): void {
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        self::add($root, 'restorecourse', 'Restore course');
        $upload = self::add($root, null, 'Upload courses', '/admin/tool/uploadcourse/index.php');
        $elsewhere = self::add($root, null, 'Another plugin', '/admin/tool/another/index.php');
        $this->assertIsInt($upload->key);
        $this->assertIsInt($elsewhere->key);

        $this->assertSame(
            ['categorymain', 'courses[restorecourse,' . $upload->key . ']', 'more[' . $elsewhere->key . ']'],
            self::shape(navgroups::rows($root))
        );
        // The query string is not part of the path: the upload page carries the category id.
        $this->assertSame(
            'courses',
            navgroups::group_of(3, new url('/admin/tool/uploadcourse/index.php', ['categoryid' => 9]))
        );
        // A string key is the plugin's own name for its node, so the path is not read for it.
        $this->assertSame(
            navgroups::OTHER,
            navgroups::group_of('tool_uploadcourse_other', new url('/admin/tool/uploadcourse/index.php'))
        );
        // An integer key without an address, or with a foreign one, is not placed.
        $this->assertSame(navgroups::OTHER, navgroups::group_of(3, null));
        $foreign = new url('https://example.org/admin/tool/uploadcourse/index.php');
        $this->assertSame(navgroups::OTHER, navgroups::group_of(3, $foreign));
    }

    /**
     * A group of one destination is that destination's plain unit; a group of none is not on the row.
     */
    public function test_a_group_of_one_is_a_plain_unit_and_an_empty_group_is_absent(): void {
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        self::add($root, 'edit', 'Settings');
        self::add($root, 'learningplantemplates', 'Learning plan templates');
        self::add($root, 'competencyframeworks', 'Competency frameworks');

        $units = navgroups::rows($root);
        $this->assertSame(
            ['categorymain', 'edit', 'competencies[competencyframeworks,learningplantemplates]'],
            self::shape($units)
        );
        $this->assertSame(navgroups::KIND_ITEM, $units[1]['kind']);
        $this->assertSame('Settings', $units[1]['text']);
        $this->assertNotContains('access', array_column($units, 'key'));
        $this->assertNotContains('courses', array_column($units, 'key'));

        // Control: with a second destination, the same group is a group, and one destination of an empty group draws it.
        self::add($root, 'categoryappearance', 'Category appearance');
        self::add($root, 'cohort', 'Cohorts');
        $this->assertSame(
            [
                'categorymain',
                'settings[categoryappearance,edit]',
                'cohort',
                'competencies[competencyframeworks,learningplantemplates]',
            ],
            self::shape(navgroups::rows($root))
        );
    }

    /**
     * A child is classified by its own key, never its parent's, and a parent with an address is a destination.
     */
    public function test_children_are_classified_by_their_own_key(): void {
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        $permissions = self::add($root, 'permissions', 'Permissions');
        self::add($permissions, 'rolecheck', 'Check permissions');
        self::add($permissions, 'roles', 'Assign roles');
        self::add($permissions, 'filters', 'Filters');
        self::add($permissions, 'local_rolereport_view', 'Role report');

        $this->assertSame(
            ['categorymain', 'access[roles,rolecheck,permissions]', 'filters', 'more[local_rolereport_view]'],
            self::shape(navgroups::rows($root))
        );
    }

    /**
     * Core marks a page's node and its parent active alike: the row's current destination is the page's own.
     */
    public function test_an_active_parent_yields_to_its_active_child(): void {
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        $permissions = self::add($root, 'permissions', 'Permissions');
        $roles = self::add($permissions, 'roles', 'Assign roles');
        self::add($permissions, 'rolecheck', 'Check permissions');

        // The control: alone, the parent is the current destination.
        $permissions->isactive = true;
        $this->assertSame(['permissions'], self::current(navgroups::rows($root)));

        $roles->isactive = true;
        $this->assertSame(['roles'], self::current(navgroups::rows($root)));
    }

    /**
     * The keys of the current destinations of a row, plain units and menu items alike.
     *
     * @param array $units What rows() returned.
     * @return array The keys, in the row's order.
     */
    private static function current(array $units): array {
        $keys = [];
        foreach ($units as $unit) {
            foreach ($unit['items'] ?? [$unit] as $destination) {
                if ($destination['current']) {
                    $keys[] = $destination['key'];
                }
            }
        }
        return $keys;
    }

    /**
     * A node without an address is no destination, and its children still are.
     */
    public function test_a_node_without_an_address_is_skipped_and_its_children_are_not(): void {
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        $container = self::add($root, 'local_container', 'A container', null);
        self::add($container, 'filters', 'Filters');
        self::add($container, 'contentbank', 'Content bank');
        self::add($root, 'edit', 'Settings', null);
        self::add($root, 'categoryappearance', 'Category appearance');

        $this->assertSame(
            ['categorymain', 'categoryappearance', 'courses[contentbank,filters]'],
            self::shape(navgroups::rows($root))
        );

        // Control: the same nodes with an address are destinations.
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        $container = self::add($root, 'local_container', 'A container');
        self::add($container, 'filters', 'Filters');
        self::add($container, 'contentbank', 'Content bank');
        self::add($root, 'edit', 'Settings');
        self::add($root, 'categoryappearance', 'Category appearance');
        $this->assertSame(
            ['categorymain', 'settings[categoryappearance,edit]', 'courses[contentbank,filters]', 'more[local_container]'],
            self::shape(navgroups::rows($root))
        );
    }

    /**
     * A node seen twice by address is kept once, where it was first seen.
     */
    public function test_a_node_seen_twice_is_kept_once(): void {
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        self::add($root, 'cohort', 'Cohorts', '/cohort/index.php');
        $permissions = self::add($root, 'permissions', 'Permissions');
        self::add($permissions, 'cohort', 'Cohorts', '/cohort/index.php');

        $units = navgroups::rows($root);
        $this->assertSame(['categorymain', 'access[cohort,permissions]'], self::shape($units));

        // Control: two nodes at two addresses are two destinations, whatever their keys.
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        self::add($root, 'cohort', 'Cohorts', '/cohort/index.php');
        $permissions = self::add($root, 'permissions', 'Permissions');
        self::add($permissions, 'cohort', 'Cohorts', '/cohort/edit.php');
        $this->assertSame(['categorymain', 'access[cohort,cohort,permissions]'], self::shape(navgroups::rows($root)));
    }

    /**
     * Inside a group the destinations follow the text the viewer reads, so a second language reorders them.
     *
     * The tree is the same in both languages; only the nodes' text differs, as core would build it for a
     * pt_br viewer. The More case orders three Portuguese words whose byte order differs from their
     * alphabetical one (an accented capital sorts after Z in bytes), so it tells the collator from strcmp().
     */
    public function test_destinations_are_alphabetical_in_the_viewers_language(): void {
        $texts = [
            'en' => [
                'restorecourse' => 'Restore course',
                'contentbank' => 'Content bank',
                'filters' => 'Filters',
                'upload' => 'Upload courses',
            ],
            'pt_br' => [
                'restorecourse' => 'Restaurar curso',
                'contentbank' => 'Banco de conteúdo',
                'filters' => 'Filtros',
                'upload' => 'Enviar cursos',
            ],
        ];
        $orders = [];
        foreach ($texts as $language => $text) {
            $root = self::root();
            self::add($root, 'categorymain', 'Categoria');
            self::add($root, 'restorecourse', $text['restorecourse']);
            self::add($root, 'contentbank', $text['contentbank']);
            self::add($root, 'filters', $text['filters']);
            $upload = self::add($root, null, $text['upload'], '/admin/tool/uploadcourse/index.php');
            $units = navgroups::rows($root);
            $orders[$language] = array_map(
                fn(array $item): string => $item['key'] === $upload->key ? 'upload' : (string) $item['key'],
                $units[1]['items']
            );
        }

        $this->assertSame(['contentbank', 'filters', 'restorecourse', 'upload'], $orders['en']);
        $this->assertSame(['contentbank', 'upload', 'filters', 'restorecourse'], $orders['pt_br']);

        $root = self::root();
        self::add($root, 'categorymain', 'Categoria');
        self::add($root, 'local_b', 'Jornadas');
        self::add($root, 'local_c', 'Índice de cursos');
        self::add($root, 'local_a', 'Histórico');
        $this->assertSame(['categorymain', 'more[local_a,local_c,local_b]'], self::shape(navgroups::rows($root)));
    }

    /**
     * The groups keep the row's order and "Category" comes first, whatever order the tree holds them in.
     */
    public function test_the_groups_keep_the_rows_order_whatever_the_tree_says(): void {
        $root = self::root();
        self::add($root, 'insights', 'Insights');
        self::add($root, 'learningplantemplates', 'Learning plan templates');
        self::add($root, 'competencyframeworks', 'Competency frameworks');
        self::add($root, 'cohort', 'Cohorts');
        self::add($root, 'roles', 'Assign roles');
        self::add($root, 'categoryappearance', 'Category appearance');
        self::add($root, 'edit', 'Settings');
        self::add($root, 'categorymain', 'Category');

        $this->assertSame([
            'categorymain',
            'settings[categoryappearance,edit]',
            'access[roles,cohort]',
            'competencies[competencyframeworks,learningplantemplates]',
            'more[insights]',
        ], self::shape(navgroups::rows($root)));
    }

    /**
     * The main node is the row's first plain unit and never a member of a group or of More.
     */
    public function test_the_main_node_is_never_in_a_group(): void {
        $root = self::root();
        self::add($root, 'insights', 'Insights');
        self::add($root, 'categorymain', 'Category');

        $units = navgroups::rows($root);
        $this->assertSame(navgroups::KIND_ITEM, $units[0]['kind']);
        $this->assertSame(navgroups::MAIN, $units[0]['key']);
        $this->assertSame(['insights'], array_column(end($units)['items'], 'key'));
    }

    /**
     * A destination's icon is the table's, else the one its node declares, else the generic one (D101).
     *
     * Each order is read on a node of its own: a line with an icon whose node declares another (the table's wins), a
     * line without one whose node declares one (the node's, its component kept), and nodes with neither, or with
     * core's empty box, which Font Awesome draws as nothing (the generic link).
     */
    public function test_an_icon_is_the_tables_then_the_nodes_then_the_generic_one(): void {
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        self::add($root, 'edit', 'Settings', '', new pix_icon('i/edit', ''));
        self::add($root, 'tool_recyclebin', 'Recycle bin', '', new pix_icon('trash', 'Recycle bin', 'tool_recyclebin'));
        self::add($root, 'tool_muprog_programs', 'Programs', '', new pix_icon('icon', 'Programs', 'tool_muprog'));
        self::add($root, 'tool_mucertify_certifications', 'Certifications');
        self::add($root, 'insights', 'Insights', '', new pix_icon('i/navigationitem', ''));

        $icons = [];
        foreach (navgroups::rows($root) as $unit) {
            foreach ($unit['items'] ?? [$unit] as $item) {
                $icons[$item['key']] = $item['icon'];
            }
        }

        // The table's: whatever the node declares, a plugin's own image included.
        $this->assertSame('theme_dimensions:navpill/programs', navgroups::DESTINATIONS['tool_muprog_programs'][1]);
        $this->assertSame(['key' => 'navpill/programs', 'component' => 'theme_dimensions'], $icons['tool_muprog_programs']);
        $this->assertSame(
            ['key' => 'navpill/certifications', 'component' => 'theme_dimensions'],
            $icons['tool_mucertify_certifications']
        );
        // The node's, for a line without an icon (edit) and for a node the table does not know (the recycle bin's
        // line has none either).
        $this->assertArrayNotHasKey(1, navgroups::DESTINATIONS['edit'], 'Precondition: the line names no icon.');
        $this->assertSame(['key' => 'i/edit', 'component' => 'moodle'], $icons['edit']);
        $this->assertSame(['key' => 'trash', 'component' => 'tool_recyclebin'], $icons['tool_recyclebin']);
        // The generic link, for no icon at all and for core's empty box.
        $generic = ['key' => navgroups::GENERIC_ICON, 'component' => 'core'];
        $this->assertSame('i/link', navgroups::GENERIC_ICON);
        $this->assertSame($generic, $icons['categorymain']);
        $this->assertSame($generic, $icons['insights']);
    }

    /**
     * Every line of the table names a group of the row, and every icon a line names is a glyph the theme maps.
     *
     * A line in a group the row does not have would throw when its pill is named; an icon the icon map does not know
     * would be drawn as an image file the theme does not ship. Core's own check of an icon map (lib/tests/output/
     * plugin_checks_test.php) asks for the map's component and an SVG under pix/, which this repeats for the theme.
     */
    public function test_every_line_of_the_table_names_a_group_and_a_mapped_glyph(): void {
        global $CFG;
        require_once($CFG->dirroot . '/theme/dimensions/lib.php');
        $map = theme_dimensions_get_fontawesome_icon_map();
        $lines = navgroups::DESTINATIONS + navgroups::PATHS;
        $this->assertArrayHasKey('/admin/tool/uploadcourse/index.php', $lines, 'Precondition: the path rules are read.');
        $icons = 0;
        foreach ($lines as $key => $line) {
            $this->assertContains($line[0], navgroups::GROUPS, $key . ' names no group of the row.');
            $this->assertNotSame('', navgroups::name($line[0]));
            $this->assertLessThanOrEqual(2, count($line), $key . ': a line is a group and an icon, nothing else.');
            if (!isset($line[1])) {
                continue;
            }
            $icons++;
            [$component, $pix] = explode(':', $line[1], 2);
            $this->assertSame('theme_dimensions', $component, $key . ': the table names the theme\'s glyphs only.');
            $this->assertArrayHasKey($line[1], $map, $key . ' names an icon the theme does not map.');
            $this->assertStringStartsWith('fa-', $map[$line[1]]);
            $this->assertFileExists($CFG->dirroot . '/theme/dimensions/pix/' . $pix . '.svg');
        }
        $this->assertSame(4, $icons, 'The four destinations that fell to the link have a glyph (D101).');
        foreach (array_keys($map) as $icon) {
            $this->assertStringStartsWith('theme_dimensions:', $icon, 'Core\'s check: the map names its own icons only.');
        }
        $this->assertSame(
            ['fa-rectangle-list', 'fa-award', 'fa-house', 'fa-diagram-project'],
            [
                $map[navgroups::DESTINATIONS['tool_mucatalog_management'][1]],
                $map[navgroups::DESTINATIONS['tool_mucertify_certifications'][1]],
                $map[navgroups::DESTINATIONS['tool_muhome_management'][1]],
                $map[navgroups::DESTINATIONS['tool_muprog_programs'][1]],
            ]
        );
    }

    /**
     * The icon font draws the table's glyphs: the theme's map is merged into core's.
     */
    public function test_the_icon_font_draws_the_tables_glyphs(): void {
        \cache::make('core', 'plugin_functions')->purge();
        \cache::make('core', 'fontawesomeiconmapping')->purge();
        \core\output\icon_system::reset_caches();
        $system = \core\output\icon_system::instance(\core\output\icon_system::FONTAWESOME);
        $map = $system->get_icon_name_map();

        $this->assertSame('fa fa-diagram-project', $map['theme_dimensions:navpill/programs'] ?? null);
        $this->assertSame('fa fa-house', $map['theme_dimensions:navpill/home'] ?? null);
        // Control: core's own entries are in the same map, so the theme's are merged into it, not instead of it.
        $this->assertArrayHasKey('core:i/link', $map);
    }

    /**
     * The row's one builder is the only reader of rows(): no other file of the theme regroups a navigation itself.
     *
     * The hero and the destination pages both ask category_navpills::for_page(), so a group changed in the table
     * changes on every page (D100). Comments are left out: a docblock may name rows() to say where the units come
     * from.
     */
    public function test_the_builder_is_the_only_reader_of_rows(): void {
        $root = dirname(__DIR__, 3);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $readers = [];
        $scanned = 0;
        foreach ($files as $file) {
            $path = substr($file->getPathname(), strlen($root) + 1);
            if ($file->getExtension() !== 'php' || str_starts_with($path, 'tests/')) {
                continue;
            }
            $scanned++;
            $code = '';
            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= is_array($token) ? $token[1] : $token;
            }
            if (preg_match('~navgroups::rows\s*\(~', $code)) {
                $readers[] = $path;
            }
        }
        $this->assertGreaterThan(100, $scanned, 'Precondition: the scan reads the theme\'s PHP.');
        $this->assertSame(['classes/output/category_navpills.php'], $readers);
    }

    /**
     * The current destination is marked, and so is the group that holds it; nothing else is.
     */
    public function test_the_current_destination_marks_its_group(): void {
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        self::add($root, 'edit', 'Settings');
        self::add($root, 'categoryappearance', 'Category appearance', '', null, true);
        self::add($root, 'cohort', 'Cohorts');
        self::add($root, 'roles', 'Assign roles');

        $units = navgroups::rows($root);
        $this->assertSame(['categorymain', 'settings[categoryappearance,edit]', 'access[roles,cohort]'], self::shape($units));
        $this->assertFalse($units[0]['current']);
        $this->assertTrue($units[1]['current']);
        $this->assertSame([true, false], array_column($units[1]['items'], 'current'));
        // Control: a group without the current page is not marked.
        $this->assertFalse($units[2]['current']);
        $this->assertSame([false, false], array_column($units[2]['items'], 'current'));

        // Control: on the category page itself, only "Category" is current.
        $root = self::root();
        self::add($root, 'categorymain', 'Category', '', null, true);
        self::add($root, 'edit', 'Settings');
        self::add($root, 'categoryappearance', 'Category appearance');
        $units = navgroups::rows($root);
        $this->assertTrue($units[0]['current']);
        $this->assertFalse($units[1]['current']);
    }

    /**
     * A viewer whose only destination is "Category" gets no row; one more destination of any kind draws it.
     */
    public function test_category_alone_draws_no_row(): void {
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        $units = navgroups::rows($root);
        $this->assertSame(['categorymain'], self::shape($units));
        $this->assertFalse(navgroups::has_row($units));
        $this->assertFalse(navgroups::has_row([]));

        // Control: a plain destination beside it, or an item of More, makes a row.
        self::add($root, 'filters', 'Filters');
        $this->assertTrue(navgroups::has_row(navgroups::rows($root)));
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        self::add($root, 'insights', 'Insights');
        $this->assertTrue(navgroups::has_row(navgroups::rows($root)));
    }

    /**
     * Reading the tree changes no node: the same nodes feed core's tabs and focus mode's "More".
     */
    public function test_rows_changes_no_node(): void {
        [$root] = self::administrators_tree();
        $root->get('permissions')->children->get('roles')->isactive = true;
        $root->get('cohort')->set_force_into_more_menu(true);
        $before = self::snapshot($root);

        navgroups::rows($root);

        $this->assertSame($before, self::snapshot($root));
        // Control: the snapshot sees a change to a node's active flag or its forced flag.
        $root->get('filters')->make_active();
        $this->assertNotSame($before, self::snapshot($root));
        [$other] = self::administrators_tree();
        $other->get('permissions')->children->get('roles')->isactive = true;
        $other->get('cohort')->set_force_into_more_menu(true);
        $this->assertSame($before, self::snapshot($other));
        $other->get('filters')->set_force_into_more_menu(true);
        $this->assertNotSame($before, self::snapshot($other));
    }

    /**
     * Each group's pill reads the theme's string for it, and More reads core's own word.
     */
    public function test_the_group_names_are_the_themes_strings_and_more_is_cores(): void {
        $this->assertSame('Settings', navgroups::name('settings'));
        $this->assertSame('User and access', navgroups::name('access'));
        $this->assertSame('Courses and content', navgroups::name('courses'));
        $this->assertSame('Competencies', navgroups::name('competencies'));
        $this->assertSame(get_string('moremenu'), navgroups::name(navgroups::OTHER));
        $this->assertSame(['settings', 'access', 'courses', 'competencies'], navgroups::GROUPS);

        [$root] = self::administrators_tree();
        $names = array_column(array_slice(navgroups::rows($root), 1), 'name');
        $this->assertSame(['Settings', 'User and access', 'Courses and content', 'Competencies', 'More'], $names);
    }

    /**
     * A group the map does not have has no name: "Other" is not a group (D89).
     */
    public function test_a_group_the_map_does_not_have_has_no_name(): void {
        // Control: the valid ids answer, so the throw below is about the id.
        $this->assertSame('Settings', navgroups::name('settings'));
        $this->expectException(\UnhandledMatchError::class);
        navgroups::name('nosuchgroup');
    }

    /**
     * More carries "current" when one of its items is the current page.
     */
    public function test_more_is_current_when_one_of_its_items_is(): void {
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        self::add($root, 'insights', 'Insights', '', null, true);
        self::add($root, 'reports', 'Reports');
        $units = navgroups::rows($root);
        $this->assertSame(['categorymain', 'more[insights,reports]'], self::shape($units));
        $this->assertTrue($units[1]['current']);
        // Control: with no current item More is not marked.
        $root = self::root();
        self::add($root, 'categorymain', 'Category');
        self::add($root, 'insights', 'Insights');
        self::add($root, 'reports', 'Reports');
        $this->assertFalse(navgroups::rows($root)[1]['current']);
    }
}
