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
 * Theme Dimensions - Tests for the mobile drawer's decision and template data
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the mobile drawer's decision and template data.
 *
 * Every clause is tested by a case that fails it beside a control that passes it in the same test, as
 * guestnav_test does: a "false" asserted alone stays green after the predicate is replaced by
 * "return false".
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(drawernav::class)]
final class drawernav_test extends \advanced_testcase {
    /**
     * Put the user agent back: core_useragent is a singleton.
     *
     * @return void
     */
    protected function tearDown(): void {
        \core_useragent::instance(true);
        parent::tearDown();
    }

    /**
     * A page of the given layout, as the renderer would ask about it.
     *
     * @param string $layout The page layout.
     * @return \moodle_page
     */
    private function page(string $layout = 'standard'): \moodle_page {
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->set_url('/my/courses.php');
        $page->set_pagelayout($layout);

        return $page;
    }

    /**
     * The template data for the given nodes.
     *
     * @param array $nodes The mobile primary navigation.
     * @return array
     */
    private function context(array $nodes): array {
        return drawernav::context($nodes, $this->page());
    }

    /**
     * A smart menu in submenu mode as Boost Union builds it: an object carrying its record, children as arrays.
     *
     * @param string $title The menu's title.
     * @param array $children Its items.
     * @return \stdClass
     */
    private function smartmenu(string $title, array $children): \stdClass {
        return (object) [
            'menudata' => (object) ['id' => 1, 'title' => $title],
            'title' => '',
            'url' => null,
            'text' => $title,
            'key' => 1,
            'haschildren' => true,
            'children' => $children,
            'sort' => 'abc',
        ];
    }

    /**
     * A smart menu item as Boost Union builds it.
     *
     * @param string $title The item's title.
     * @param string $url Its address.
     * @param array $children Its children.
     * @param string $menuicon Its own icon, empty for none.
     * @return array
     */
    private function smartitem(string $title, string $url, array $children = [], string $menuicon = ''): array {
        $text = '<div class="d-flex flex-column"><span class="boost-union-smartmenu-firstline">' . $title . '</span></div>';
        if ($menuicon !== '') {
            $text = '<i class="icon fa fa-star" title="' . $title . '"></i>' . $text;
        }

        return [
            'itemdata' => (object) ['id' => 7, 'menuicon' => $menuicon, 'classes' => []],
            'url' => $url,
            'key' => 'item-7',
            'text' => $text,
            'title' => '',
            'tooltip' => '',
            'haschildren' => $children ? 1 : 0,
            'children' => $children,
            'attributes' => [['name' => 'target', 'value' => '__blank'], ['name' => 'onclick', 'value' => 'alert(1)']],
        ];
    }

    /**
     * A logged-in account gets the drawer; a visitor and the guest account do not.
     *
     * @return void
     */
    public function test_it_applies_to_a_logged_in_account_and_not_to_a_visitor_or_the_guest(): void {
        $this->resetAfterTest();
        $page = $this->page();

        $this->setUser(self::getDataGenerator()->create_user());
        $this->assertTrue(drawernav::applies($page), 'Control: a real account.');

        $this->setUser(null);
        $this->assertFalse(isloggedin(), 'Precondition: nobody is logged in.');
        $this->assertFalse(drawernav::applies($page));

        $this->setGuestUser();
        $this->assertFalse(drawernav::applies($page));
    }

    /**
     * An administrator logged in as somebody holds a real account, and keeps the drawer.
     *
     * @return void
     */
    public function test_a_login_as_session_keeps_the_drawer(): void {
        $this->resetAfterTest();
        $student = self::getDataGenerator()->create_user();
        $this->setAdminUser();
        \core\session\manager::loginas((int) $student->id, \context_system::instance());
        $this->assertTrue(\core\session\manager::is_loggedinas(), 'Precondition: a login-as session.');

        $this->assertTrue(drawernav::applies($this->page()));
    }

    /**
     * Only an explicit '0' switches it off; a site that never saved the setting has it on.
     *
     * @return void
     */
    public function test_only_an_explicit_zero_switches_it_off(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $page = $this->page();

        unset_config(drawernav::SETTING, drawernav::COMPONENT);
        $this->assertFalse(get_config(drawernav::COMPONENT, drawernav::SETTING), 'Precondition: never written.');
        $this->assertTrue(drawernav::applies($page));

        set_config(drawernav::SETTING, '0', drawernav::COMPONENT);
        $this->assertFalse(drawernav::enabled());
        $this->assertFalse(drawernav::applies($page));

        set_config(drawernav::SETTING, '1', drawernav::COMPONENT);
        $this->assertTrue(drawernav::applies($page));
    }

    /**
     * The layouts without the navbar, and the Moodle app, are left alone.
     *
     * @return void
     */
    public function test_the_layouts_without_the_navbar_and_the_app_are_left_alone(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $this->assertTrue(drawernav::applies($this->page('standard')), 'Control: an ordinary layout.');
        $this->assertFalse(drawernav::applies($this->page('login')));
        $this->assertFalse(drawernav::applies($this->page('embedded')));

        \core_useragent::instance(true, 'Mozilla/5.0 (Linux; Android 10) MoodleMobile 4.5.0');
        $this->assertTrue(\core_useragent::is_moodle_app(), 'Precondition: the user agent is the app.');
        $this->assertFalse(drawernav::applies($this->page('standard')));
    }

    /**
     * Core's nodes go to "Browse" with the mapped glyph, the current one marked, a node with no map the default.
     *
     * @return void
     */
    public function test_core_nodes_are_browse_with_their_glyphs_and_the_current_one_marked(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $context = $this->context([
            ['key' => 'home', 'text' => 'Home', 'url' => new \moodle_url('/'), 'isactive' => false, 'haschildren' => 0,
                'children' => []],
            ['key' => 'mycourses', 'text' => 'My courses', 'url' => new \moodle_url('/my/courses.php', ['a' => 1, 'b' => 2]),
                'isactive' => true, 'haschildren' => 0, 'children' => []],
            ['divider' => true],
            (object) ['sort' => 3, 'text' => 'Custom', 'url' => 'https://example.com/x', 'isactive' => false,
                'haschildren' => 0, 'children' => []],
        ]);

        $this->assertCount(1, $context['sections']);
        $browse = $context['sections'][0];
        $this->assertSame(get_string('drawer_browse', 'theme_dimensions'), $browse['label']);
        $this->assertSame(drawernav::SECTION_PREFIX . '1', $browse['id']);
        $this->assertCount(4, $browse['items'], 'The divider is dropped and the calendar row added.');
        [$home, $mycourses, $calendar, $custom] = $browse['items'];
        $this->assertSame('fa-regular fa-calendar', $calendar['icon']);
        $this->assertSame('fa fa-house', $home['icon']);
        $this->assertFalse($home['iscurrent']);
        $this->assertSame('fa fa-graduation-cap', $mycourses['icon']);
        $this->assertTrue($mycourses['iscurrent']);
        $this->assertSame(
            (new \moodle_url('/my/courses.php', ['a' => 1, 'b' => 2]))->out(),
            $mycourses['url'],
            'A moodle_url is spelled escaped, as the template prints it raw.'
        );
        $this->assertSame(drawernav::DEFAULT_ICON, $custom['icon']);
        $this->assertSame([], $context['panels']);
    }

    /**
     * A smart menu is a section of its own, its items the rows, an item's own icon left in its text.
     *
     * @return void
     */
    public function test_a_smart_menu_is_a_section_of_its_own(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $context = $this->context([
            ['key' => 'home', 'text' => 'Home', 'url' => new \moodle_url('/'), 'haschildren' => 0, 'children' => []],
            $this->smartmenu('Help &amp; support', [
                $this->smartitem('FAQ', 'https://example.com/faq', [], 'core:i/star'),
                $this->smartitem('Contact', 'https://example.com/contact'),
                ['divider' => true],
            ]),
        ]);

        $this->assertCount(2, $context['sections']);
        $this->assertSame(get_string('drawer_browse', 'theme_dimensions'), $context['sections'][0]['label'], 'Browse first.');
        $help = $context['sections'][1];
        $this->assertSame('Help & support', $help['label'], 'The label is plain; the template escapes it.');
        $this->assertSame(drawernav::SECTION_PREFIX . '2', $help['id']);
        $this->assertCount(2, $help['items']);
        [$faq, $contact] = $help['items'];
        $this->assertSame('', $faq['icon'], 'An item with its own icon carries it in its text.');
        $this->assertStringContainsString('fa-star', $faq['text']);
        $this->assertSame(drawernav::DEFAULT_ICON, $contact['icon'], 'Control: an item without one gets the default.');
        $this->assertSame([['name' => 'target', 'value' => '__blank']], $contact['attributes'], 'Only the allowlisted attributes.');
    }

    /**
     * Children open sub-panels, three levels deep, flat in the order a parent comes before its children.
     *
     * @return void
     */
    public function test_children_open_sub_panels_three_levels_deep(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $leaf = $this->smartitem('Leaf', 'https://example.com/leaf');
        $leaf['isactive'] = true;
        $middle = $this->smartitem('Middle', '#', [$leaf]);
        $top = $this->smartitem('Top', 'https://example.com/top', [$middle, $this->smartitem('Sibling', 'https://example.com/s')]);
        $context = $this->context([$this->smartmenu('Menu', [$top])]);

        $row = $context['sections'][0]['items'][0];
        $this->assertTrue($row['haschildren']);
        $this->assertFalse($row['iscurrent'], 'A row that opens a panel is never the current page.');
        $this->assertCount(2, $context['panels']);
        [$first, $second] = $context['panels'];

        $this->assertSame($row['panelid'], $first['id']);
        $this->assertSame(drawernav::ROOT, $first['parentid']);
        $this->assertSame(1, $first['level']);
        $this->assertSame('Top', $first['title']);
        $this->assertSame(get_string('drawer_back', 'theme_dimensions', get_string('navigation')), $first['backaria']);
        $this->assertTrue($first['hasurl'], 'Top has an address of its own.');
        $this->assertSame(get_string('drawer_openparent', 'theme_dimensions', 'Top'), $first['openlabel']);
        $this->assertCount(2, $first['items']);
        $this->assertSame('', $first['items'][1]['icon'], 'A sub-panel row has no default glyph.');

        $this->assertSame($first['items'][0]['panelid'], $second['id']);
        $this->assertSame($first['id'], $second['parentid']);
        $this->assertSame(2, $second['level']);
        $this->assertSame(get_string('drawer_back', 'theme_dimensions', 'Top'), $second['backaria']);
        $this->assertFalse($second['hasurl'], 'A "#" address is no page to open.');
        $this->assertTrue($second['items'][0]['iscurrent'], 'The current page three levels down is marked.');
    }

    /**
     * The drawer holds nothing about the person (D72, D77): its data is the navigation alone, whoever is logged in and
     * whatever the site lets them choose; the identity, the colour mode, the Look and the language are the profile
     * layer's (profilelayer_test).
     *
     * The force is on: an administrator, with core's colour modes enabled and two languages installed, each of which
     * once gave the drawer a key of its own.
     *
     * @return void
     */
    public function test_the_drawer_holds_nothing_about_the_person(): void {
        global $CFG, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablecolourmodes', 1, 'theme_boost');
        $folder = $CFG->dataroot . '/lang/xx';
        check_dir_exists($folder);
        file_put_contents(
            $folder . '/langconfig.php',
            "<?php\n\$string['thislanguage'] = 'Xx';\n\$string['parentlanguage'] = '';\n"
        );
        get_string_manager()->reset_caches();
        $this->assertNotNull(profilelayer::appearance(), 'Precondition: this person may choose a colour mode.');
        $this->assertNotNull(profilelayer::language($this->page(), $PAGE->get_renderer('core')), 'Precondition.');

        $context = $this->context([['key' => 'home', 'text' => 'Home', 'url' => new \moodle_url('/'), 'isactive' => false,
            'haschildren' => 0, 'children' => []]]);

        $this->assertSame(['rootid', 'sections', 'panels'], array_keys($context));
        $this->assertSame(drawernav::ROOT, $context['rootid']);
        $this->assertNotEmpty($context['sections'], 'Control: the navigation was built.');
    }

    /**
     * Every key of every row is set, so the template's context stack never reaches the layout around it.
     *
     * @return void
     */
    public function test_every_row_key_is_always_set(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $context = $this->context([(object) ['text' => 'Bare']]);
        $this->assertSame(
            ['text', 'url', 'icon', 'iscurrent', 'haschildren', 'panelid', 'tooltip', 'attributes'],
            array_keys($context['sections'][0]['items'][0])
        );
    }

    /**
     * An account's drawer gains the calendar row after core's nodes, leading to the theme's calendar page.
     *
     * Its controls: no row where none of core's nodes stands (a navigation the site emptied), none where Boost Union
     * hides the calendar, and a node already carrying the key (core's guest node) is pointed at the page instead of
     * being doubled.
     *
     * @return void
     */
    public function test_the_calendar_row_leads_to_the_theme_calendar(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $CFG->routerconfigured = false;

        $core = [
            ['key' => 'myhome', 'text' => 'Dashboard', 'url' => new \moodle_url('/my/'), 'isactive' => false,
                'haschildren' => 0, 'children' => []],
            ['key' => 'mycourses', 'text' => 'My courses', 'url' => new \moodle_url('/my/courses.php'),
                'isactive' => false, 'haschildren' => 0, 'children' => []],
            (object) ['sort' => 3, 'text' => 'Custom', 'url' => 'https://example.com/x', 'isactive' => false,
                'haschildren' => 0, 'children' => []],
        ];
        $items = $this->context($core)['sections'][0]['items'];
        $this->assertSame(['Dashboard', 'My courses', get_string('calendar', 'calendar'), 'Custom'], array_column($items, 'text'));
        $this->assertSame((new \moodle_url('/theme/dimensions/calendar.php'))->out(), $items[2]['url']);
        $this->assertFalse($items[2]['iscurrent']);

        // A navigation with none of core's nodes gains nothing.
        $bare = $this->context([(object) ['text' => 'Bare']])['sections'][0]['items'];
        $this->assertSame(['Bare'], array_column($bare, 'text'));

        // Core's own calendar node (the guest's) is pointed at the page, not doubled.
        $withnode = array_merge($core, [['key' => 'calendar', 'text' => 'Calendar', 'isactive' => false, 'haschildren' => 0,
            'children' => [], 'url' => new \moodle_url('/calendar/view.php', ['view' => 'month'])]]);
        $items = $this->context($withnode)['sections'][0]['items'];
        $this->assertCount(1, array_filter($items, static fn(array $item): bool => $item['text'] === 'Calendar'
            || $item['text'] === get_string('calendar', 'calendar')));
        $this->assertContains((new \moodle_url('/theme/dimensions/calendar.php'))->out(), array_column($items, 'url'));
        $this->assertNotContains((new \moodle_url('/calendar/view.php', ['view' => 'month']))->out(), array_column($items, 'url'));

        // Boost Union's hidden nodes win.
        $page = $this->page();
        $page->theme->removedprimarynavitems = ['calendar'];
        $hidden = drawernav::context($core, $page)['sections'][0]['items'];
        $this->assertSame(['Dashboard', 'My courses', 'Custom'], array_column($hidden, 'text'));
    }

    /**
     * The calendar row is the current one on the calendar page itself.
     *
     * @return void
     */
    public function test_the_calendar_row_is_current_on_the_calendar_page(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $page = $this->page();
        $page->set_pagetype(\theme_dimensions\local\calendar\page::PAGETYPE);
        $core = [['key' => 'mycourses', 'text' => 'My courses', 'url' => new \moodle_url('/my/courses.php'), 'isactive' => false,
            'haschildren' => 0, 'children' => []]];
        $items = drawernav::context($core, $page)['sections'][0]['items'];
        $this->assertTrue($items[1]['iscurrent']);
        $this->assertFalse($items[0]['iscurrent']);
    }
}
