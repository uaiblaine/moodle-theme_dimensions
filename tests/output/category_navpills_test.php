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
 * Theme Dimensions - Tests of the category navigation's row of nav pills
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core\navigation\navigation_node;
use core\output\pix_icon;
use core\url;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\category\navgroups;

/**
 * Tests of the row of nav pills: its context and its markup, on a real category page and on built trees.
 *
 * Which group a node lands in, and the order, are navgroups_test's; who gets a row at all is the hero's
 * (category_hero_test). This reads what the renderable and its template make of the units: a plain pill
 * per destination, a native disclosure per group, "More" drawn whatever it holds and hidden when it holds
 * nothing, the current page and its group marked, every icon decorative.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(category_navpills::class)]
final class category_navpills_test extends \advanced_testcase {
    /**
     * Set the global page up as a category's page in the logged-in shell, for the current user.
     *
     * @param int $categoryid The category.
     * @param string|null $activetab The secondary navigation's active key, as a page sets it; null leaves it to core.
     * @return \moodle_page The page.
     */
    private function category_page(int $categoryid, ?string $activetab = null): \moodle_page {
        global $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_category_by_id($categoryid);
        $PAGE->set_url('/course/index.php', ['categoryid' => $categoryid]);
        $PAGE->set_pagetype('course-index-category');
        if ($activetab !== null) {
            $PAGE->set_secondary_active_tab($activetab);
        }
        return $PAGE;
    }

    /**
     * The row of a page's secondary navigation, exported and rendered.
     *
     * @param \moodle_page $page The page.
     * @return array [the context, the HTML]
     */
    private function row(\moodle_page $page): array {
        $output = $page->get_renderer('core');
        $context = (new category_navpills(navgroups::rows($page->secondarynav), 'dim-navpills-test'))->export_for_template($output);
        return [$context, $output->render_from_template('theme_dimensions/category_navpills', $context)];
    }

    /**
     * A built secondary navigation: a root and its children, each [key, text, path, icon].
     *
     * @param array $nodes The children, in the tree's order.
     * @return navigation_node The root.
     */
    private static function tree(array $nodes): navigation_node {
        $root = navigation_node::create('Secondary', null, navigation_node::TYPE_ROOTNODE, null, 'secondary');
        foreach ($nodes as [$key, $text, $path, $icon]) {
            $address = new url($path, ['categoryid' => 7]);
            $node = navigation_node::create($text, $address, navigation_node::TYPE_SETTING, null, $key, $icon);
            $node->isactive = false;
            $root->add_node($node);
        }
        return $root;
    }

    /**
     * The units of the exported row by key: a plain unit's own, a group's "key[item,item]".
     *
     * @param array $context The exported context.
     * @return array One string per unit, in the row's order.
     */
    private static function shape(array $context): array {
        return array_map(function (array $unit): string {
            if (!$unit['isgroup']) {
                return $unit['key'];
            }
            return $unit['key'] . '[' . implode(',', array_column($unit['items'], 'key')) . ']';
        }, $context['units']);
    }

    /**
     * A manager's row on the category page: Category first and current, then the groups the manager holds.
     */
    public function test_a_managers_row_is_category_then_the_groups(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $this->setAdminUser();

        [$context, $html] = $this->row($this->category_page($categoryid, navgroups::MAIN));

        $this->assertSame(get_string('categorynavigation', 'theme_dimensions'), $context['label']);
        $this->assertSame('dim-navpills-test', $context['uniqid']);
        $first = $context['units'][0];
        $this->assertSame([navgroups::MAIN, false, true], [$first['key'], $first['isgroup'], $first['current']]);
        $settings = array_values(array_filter($context['units'], fn(array $unit): bool => $unit['key'] === 'settings'));
        $this->assertCount(1, $settings, 'The Settings group is on the row.');
        $this->assertTrue($settings[0]['isgroup']);
        $this->assertSame(navgroups::name('settings'), $settings[0]['name']);
        $this->assertSame('dim-navpills-test-settings', $settings[0]['menuid']);
        $this->assertContains('edit', array_column($settings[0]['items'], 'key'));
        $this->assertContains('categoryappearance', array_column($settings[0]['items'], 'key'));
        $this->assertFalse($settings[0]['current'], 'The current page is Category, in no group.');
        $this->assertTrue($context['hasgroups']);

        $this->assertMatchesRegularExpression(
            '~^<nav class="dim-navpills" id="dim-navpills-test" aria-label="Category navigation" data-region="dim-navpills">~',
            trim($html)
        );
        $this->assertMatchesRegularExpression(
            '~<a class="dim-navpill" href="[^"]*categoryid=' . $categoryid . '" data-key="categorymain" aria-current="page">'
                . '.*?<span class="dim-navpill__dot" aria-hidden="true"></span><span class="dim-navpill__label">'
                . get_string('category') . '</span></a>~',
            $html,
            'Category is a link, current, with its dot.'
        );
        $this->assertMatchesRegularExpression(
            '~<details class="dim-navpill-group" data-group="settings">\s*<summary class="dim-navpill">'
                . '<span class="dim-navpill__label">' . navgroups::name('settings') . '</span>~',
            $html,
            'A group is a native disclosure whose summary is its pill, with no dot while it holds no current page.'
        );
        $this->assertMatchesRegularExpression(
            '~<a class="dim-navpill-menu__item" href="[^"]*" data-key="categoryappearance">~',
            $html,
            'A destination in a menu is a link carrying its node\'s key.'
        );
    }

    /**
     * A viewer granted only the category's management capability gets Settings as a group, and nothing of access.
     */
    public function test_a_category_manager_gets_the_settings_group(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $categoryid = (int) $generator->create_category()->id;
        $user = $generator->create_user();
        $roleid = $generator->create_role();
        $context = \core\context\coursecat::instance($categoryid);
        assign_capability('moodle/category:manage', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);

        [$context] = $this->row($this->category_page($categoryid, navgroups::MAIN));

        $keys = array_column($context['units'], 'key');
        $this->assertSame([navgroups::MAIN, 'settings'], array_slice($keys, 0, 2), 'Category, then Settings.');
        $settings = $context['units'][1];
        $this->assertTrue($settings['isgroup']);
        $items = array_column($settings['items'], 'key');
        $this->assertContains('edit', $items);
        $this->assertContains('categoryappearance', $items);
        $this->assertNotContains('access', $keys, 'No role is assignable, overridable or checkable here.');
        $this->assertNotContains('permissions', $keys);
    }

    /**
     * A page whose active tab is a group's member marks the member in the menu and the group's pill.
     *
     * The showcase's own page is always Category; this is the state a destination page would show (D82),
     * drawn by the same template.
     */
    public function test_the_current_member_marks_its_item_and_its_group(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $this->setAdminUser();

        [$context, $html] = $this->row($this->category_page($categoryid, 'categoryappearance'));

        $this->assertFalse($context['units'][0]['current'], 'Category is not the current page here.');
        $this->assertStringNotContainsString('data-key="categorymain" aria-current', $html);
        $this->assertMatchesRegularExpression(
            '~<details class="dim-navpill-group" data-group="settings">\s*'
                . '<summary class="dim-navpill is-selected" aria-current="page">'
                . '<span class="dim-navpill__dot" aria-hidden="true"></span><span class="dim-navpill__label">~',
            $html,
            'The group holding the current page is drawn selected, with the dot and aria-current.'
        );
        $this->assertMatchesRegularExpression(
            '~<a class="dim-navpill-menu__item" href="[^"]*" data-key="categoryappearance" aria-current="page">'
                . '.*?<span class="dim-navpill__dot" aria-hidden="true"></span></a>~',
            $html,
            'The current item carries aria-current and its dot at the end.'
        );
        $this->assertSame(2, substr_count($html, 'aria-current="page"'), 'The current item and the pill of its group.');
        $this->assertSame(1, substr_count($html, 'is-selected'), 'One group selected.');
    }

    /**
     * "More" is always drawn: hidden and empty without a node the map does not know, shown with it.
     */
    public function test_more_is_drawn_hidden_when_empty_and_shown_with_its_items(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('core');
        $this->resetAfterTest();
        $known = [
            [navgroups::MAIN, 'Category', '/course/index.php', null],
            ['edit', 'Settings', '/course/editcategory.php', new pix_icon('i/edit', '')],
            ['categoryappearance', 'Category appearance', '/theme/dimensions/categoryhero.php', new pix_icon('i/edit', '')],
        ];

        $row = new category_navpills(navgroups::rows(self::tree($known)), 'r1');
        $context = $row->export_for_template($output);
        $this->assertSame([navgroups::MAIN, 'settings[categoryappearance,edit]'], self::shape($context));
        $this->assertFalse($context['more']['hasitems']);
        $this->assertSame([], $context['more']['items']);
        $this->assertSame(get_string('moremenu'), $context['more']['name']);
        $html = $output->render_from_template('theme_dimensions/category_navpills', $context);
        $more = '<details class="dim-navpill-group dim-navpill-group--more" data-group="other" data-region="dim-navpills-more"';
        $this->assertStringContainsString($more . ' hidden>', $html, 'Empty, More is in the page and hidden.');

        $unknown = array_merge($known, [['insights', 'Insights', '/report/insights/insights.php', null]]);
        $context = (new category_navpills(navgroups::rows(self::tree($unknown)), 'r2'))->export_for_template($output);
        $this->assertSame([navgroups::MAIN, 'settings[categoryappearance,edit]'], self::shape($context), 'More is no unit.');
        $this->assertTrue($context['more']['hasitems']);
        $this->assertSame(['insights'], array_column($context['more']['items'], 'key'));
        $html = $output->render_from_template('theme_dimensions/category_navpills', $context);
        $this->assertMatchesRegularExpression(
            '~' . preg_quote($more, '~') . '>\s*<summary class="dim-navpill"><span class="dim-navpill__label">'
                . get_string('moremenu') . '</span>~',
            $html,
            'With an item of its own, More is shown.'
        );
        $this->assertMatchesRegularExpression(
            '~<div class="dim-navpill-menu" id="r2-other">\s*<a [^>]*data-key="insights">~',
            $html
        );
    }

    /**
     * A row of plain pills opens no menu, and says so to whoever arms a client.
     */
    public function test_a_row_of_plain_pills_has_no_groups(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('core');
        $this->resetAfterTest();
        $row = new category_navpills(navgroups::rows(self::tree([
            [navgroups::MAIN, 'Category', '/course/index.php', null],
            ['edit', 'Settings', '/course/editcategory.php', null],
        ])));
        $context = $row->export_for_template($output);
        $this->assertSame([navgroups::MAIN, 'edit'], self::shape($context));
        $this->assertFalse($row->has_groups());
        $this->assertFalse($context['hasgroups']);
        $this->assertStringStartsWith('dim-navpills-', $context['uniqid']);
    }

    /**
     * Every icon is decorative: hidden from assistive technology, no title, and a node's own alt text dropped.
     *
     * The recycle bin's node names its plugin in its icon's alt, which beside the node's text would be read twice.
     */
    public function test_the_icons_are_decorative(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('core');
        $this->resetAfterTest();
        $context = (new category_navpills(navgroups::rows(self::tree([
            [navgroups::MAIN, 'Category', '/course/index.php', null],
            ['restorecourse', 'Restore course', '/backup/restorefile.php', new pix_icon('i/restore', 'Restore alt text')],
            ['tool_recyclebin', 'Recycle bin', '/admin/tool/recyclebin/index.php', new pix_icon('i/delete', 'Bin alt text')],
        ]))))->export_for_template($output);
        $html = $output->render_from_template('theme_dimensions/category_navpills', $context);

        $this->assertStringNotContainsString('alt text', $html, 'No node\'s alt reaches the page.');
        $found = preg_match_all('~<i class="icon[^>]*>~', $html, $icons);
        $this->assertGreaterThanOrEqual(4, $found, 'Precondition: every pill, chevron and item draws an icon.');
        foreach ($icons[0] as $icon) {
            $this->assertStringContainsString('aria-hidden="true"', $icon);
            $this->assertStringNotContainsString('title=', $icon);
            $this->assertStringNotContainsString('aria-label', $icon);
        }
        $this->assertSame(1, substr_count($html, 'aria-label='), 'The nav\'s name is the one label of the row.');
    }

    /**
     * The export changes nothing: the same units give the same context, and the template prints no raw HTML.
     */
    public function test_the_export_has_no_side_effect_and_the_template_no_triple_stash(): void {
        global $CFG, $PAGE;
        $output = $PAGE->get_renderer('core');
        $this->resetAfterTest();
        $row = new category_navpills(navgroups::rows(self::tree([
            [navgroups::MAIN, 'Category & co', '/course/index.php', null],
            ['edit', 'Settings', '/course/editcategory.php', null],
        ])), 'same');
        $this->assertSame($row->export_for_template($output), $row->export_for_template($output));
        $html = $output->render_from_template('theme_dimensions/category_navpills', $row->export_for_template($output));
        $this->assertStringContainsString('Category &amp; co', $html, 'The text is escaped once, by the double stash.');

        $template = file_get_contents($CFG->dirroot . '/theme/dimensions/templates/category_navpills.mustache');
        $this->assertStringNotContainsString('{{{', $template);
        $class = file_get_contents($CFG->dirroot . '/theme/dimensions/classes/output/category_navpills.php');
        $this->assertStringNotContainsString('html_writer', $class);
    }

    /**
     * The disclosure is native: no ARIA menu role on the server markup, and an icon, escaped once, on every menu item.
     *
     * The menu is a list of links (menu.md), so "menu", "menuitem" and "aria-haspopup" would promise a keyboard model
     * that only a script can keep. The More items carry an icon too (D84), a node with none getting the generic one (D94).
     */
    public function test_the_menus_are_plain_links_with_an_icon_and_one_escape(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('core');
        $this->resetAfterTest();
        $root = navigation_node::create('Secondary', null, navigation_node::TYPE_ROOTNODE, null, 'secondary');
        $nodes = [
            [navgroups::MAIN, 'Category', null],
            ['edit', 'Settings', null],
            ['categoryappearance', 'Category appearance', new pix_icon('i/edit', '')],
            ['insights', 'Insights', new pix_icon('i/report', '')],
            ['unknownwithouticon', 'Unknown', null],
        ];
        foreach ($nodes as [$key, $text, $icon]) {
            // One address per node (a second node at an address already seen is dropped), two parameters each.
            $address = new url('/course/index.php', ['categoryid' => 7, 'tab' => 'a', 'node' => $key]);
            $node = navigation_node::create($text, $address, navigation_node::TYPE_SETTING, null, $key, $icon);
            $node->isactive = false;
            $root->add_node($node);
        }
        $context = (new category_navpills(navgroups::rows($root), 'r3'))->export_for_template($output);
        $html = $output->render_from_template('theme_dimensions/category_navpills', $context);

        $this->assertSame(['insights', 'unknownwithouticon'], array_column($context['more']['items'], 'key'), 'Precondition.');
        $this->assertStringNotContainsString('role="menu', $html);
        $this->assertStringNotContainsString('role="menuitem', $html);
        $this->assertStringNotContainsString('aria-haspopup', $html);

        $items = preg_match_all('~<a class="dim-navpill-menu__item"[^>]*>~', $html, $anchors);
        $withicon = preg_match_all('~<a class="dim-navpill-menu__item"[^>]*><i class="icon~', $html);
        $this->assertGreaterThanOrEqual(4, $items, 'Precondition: the settings group and More hold items.');
        $this->assertSame($items, $withicon, 'Every item of every menu, More included, starts with its icon.');

        $this->assertStringContainsString('categoryid=7&amp;tab=a', $html, 'The address is escaped once.');
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }
}
