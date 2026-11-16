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
 * Theme Dimensions - Tests for the profile layer's decision and template data
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the profile layer's decision and template data.
 *
 * Every clause is tested by a case that fails it beside a control that passes it in the same test, as drawernav_test
 * does. A person is the complete record core puts in the session (get_complete_user_data()), because the generator's
 * row carries no profile.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(profilelayer::class)]
final class profilelayer_test extends \advanced_testcase {
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
     * A person as the session holds them, logged in.
     *
     * @param array $record The user's columns.
     * @return \stdClass The complete user record, which is also $USER.
     */
    private function person(array $record = []): \stdClass {
        // Under a sink, so no user_created observer of another plugin rewrites the names set here.
        $sink = $this->redirectEvents();
        $user = self::getDataGenerator()->create_user($record);
        $sink->close();
        $complete = get_complete_user_data('id', $user->id);
        $this->setUser($complete);

        return $complete;
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
        $page->set_url('/my/');
        $page->set_pagelayout($layout);

        return $page;
    }

    /**
     * The template data for a user menu.
     *
     * @param array $usermenu The user menu.
     * @param array|null $lookmenu flavourchoice::menu()'s answer.
     * @return array
     */
    private function context(array $usermenu, ?array $lookmenu = null): array {
        global $PAGE;

        return profilelayer::context($usermenu, $this->page(), $PAGE->get_renderer('core'), $lookmenu);
    }

    /**
     * A link item of core's user menu.
     *
     * @param string $title The item's title.
     * @param string $url Its address, local to the site.
     * @param string $identifier Its title identifier.
     * @return \stdClass
     */
    private function item(string $title, string $url, string $identifier = ''): \stdClass {
        return (object) [
            'itemtype' => 'link',
            'link' => true,
            'url' => new \moodle_url($url),
            'title' => $title,
            'titleidentifier' => $identifier,
        ];
    }

    /**
     * A user menu as core and Boost Union build it, each part named so a test can shuffle them.
     *
     * @return array items (by name) and submenus.
     */
    private function usermenu(): array {
        $items = [
            'profile' => $this->item('Profile', '/user/profile.php', 'profile,moodle'),
            'grades' => $this->item('Grades', '/grade/report/mygrades.php', 'grades,grades'),
            'calendar' => $this->item('Calendar', '/calendar/view.php?view=month', 'calendar,core_calendar'),
            'files' => $this->item('Private files', '/user/files.php', 'privatefiles,moodle'),
            'external' => (object) ['itemtype' => 'link', 'url' => new \moodle_url('https://elsewhere.example.org/help'),
                'title' => 'Help desk', 'titleidentifier' => 'Help-desk'],
            'divider' => (object) ['itemtype' => 'divider', 'divider' => true],
            'invalid' => (object) ['itemtype' => 'invalid', 'url' => null, 'title' => 'Broken'],
            'preferences' => $this->item('Preferences', '/user/preferences.php', 'preferences,moodle'),
            'switchrole' => $this->item('Switch role to...', '/course/switchrole.php?id=1&switchrole=-1', 'switchroleto,moodle'),
            'language' => (object) ['itemtype' => 'submenu-link', 'submenuid' => 'lang1', 'title' => 'Language',
                'submenulink' => true],
            'smartmenu' => (object) ['link' => false, 'submenulink' => true, 'submenuid' => 'sm1', 'title' => 'Library',
                'text' => 'Library'],
            'logout' => $this->item('Log out', '/login/logout.php?sesskey=x', 'logout,moodle'),
        ];
        $submenus = [
            (object) ['id' => 'lang1', 'title' => get_string('languageselector'), 'items' => []],
            (object) ['id' => 'sm1', 'title' => 'Library', 'items' => [
                ['title' => '', 'text' => '<span class="boost-union-smartmenu-firstline">Catalogue</span>',
                    'url' => 'https://example.com/catalogue', 'itemtype' => 'link'],
                ['itemtype' => 'divider', 'divider' => true],
                ['title' => 'Archive', 'url' => '', 'submenuid' => 'sm2'],
            ]],
            (object) ['id' => 'sm2', 'returnid' => 'sm1', 'title' => 'Archive', 'items' => [
                ['title' => 'Old issues', 'url' => 'https://example.com/old'],
            ]],
        ];

        return ['items' => $items, 'submenus' => $submenus];
    }

    /**
     * The labels of a group's rows, by the group's key.
     *
     * @param array $context The template data.
     * @return array key => labels.
     */
    private function labels(array $context): array {
        $groups = [];
        foreach ($context['groups'] as $group) {
            $groups[$group['key']] = array_column($group['rows'], 'label');
        }

        return $groups;
    }

    /**
     * A logged-in account gets the layer; a visitor and the guest account do not, and log in as keeps it.
     *
     * @return void
     */
    public function test_it_applies_to_a_logged_in_account_and_not_to_a_visitor_or_the_guest(): void {
        $this->resetAfterTest();
        $page = $this->page();

        $student = $this->person();
        $this->assertTrue(profilelayer::applies($page), 'Control: a real account.');

        $this->setUser(null);
        $this->assertFalse(isloggedin(), 'Precondition: nobody is logged in.');
        $this->assertFalse(profilelayer::applies($page));

        $this->setGuestUser();
        $this->assertTrue(isloggedin(), 'Precondition: the guest account is logged in.');
        $this->assertFalse(profilelayer::applies($page));

        $this->setAdminUser();
        \core\session\manager::loginas((int) $student->id, \context_system::instance(), false);
        $this->assertTrue(\core\session\manager::is_loggedinas(), 'Precondition: a login-as session.');
        $this->assertTrue(profilelayer::applies($page), 'Log in as holds a real account.');
    }

    /**
     * Only an explicit '0' switches it off; a site that never saved the setting has it on.
     *
     * @return void
     */
    public function test_only_an_explicit_zero_switches_it_off(): void {
        $this->resetAfterTest();
        $this->person();
        $page = $this->page();

        unset_config(profilelayer::SETTING, profilelayer::COMPONENT);
        $this->assertFalse(get_config(profilelayer::COMPONENT, profilelayer::SETTING), 'Precondition: never written.');
        $this->assertTrue(profilelayer::applies($page));

        set_config(profilelayer::SETTING, '0', profilelayer::COMPONENT);
        $this->assertFalse(profilelayer::enabled());
        $this->assertFalse(profilelayer::applies($page));

        set_config(profilelayer::SETTING, '1', profilelayer::COMPONENT);
        $this->assertTrue(profilelayer::applies($page));
    }

    /**
     * The layouts without the navbar, and the Moodle app, are left alone.
     *
     * @return void
     */
    public function test_the_layouts_without_the_navbar_and_the_app_are_left_alone(): void {
        $this->resetAfterTest();
        $this->person();

        $this->assertTrue(profilelayer::applies($this->page('standard')), 'Control: an ordinary layout.');
        $this->assertFalse(profilelayer::applies($this->page('login')));
        $this->assertFalse(profilelayer::applies($this->page('embedded')));
        $this->assertFalse(profilelayer::applies($this->page('popup')));

        \core_useragent::instance(true, 'Mozilla/5.0 (Linux; Android 10) MoodleMobile 4.5.0');
        $this->assertTrue(\core_useragent::is_moodle_app(), 'Precondition: the user agent is the app.');
        $this->assertFalse(profilelayer::applies($this->page('standard')));
    }

    /**
     * The rows go to their group by address and title identifier, in core's order, whatever the order of the menu.
     *
     * @return void
     */
    public function test_the_rows_are_grouped_by_address_never_by_position(): void {
        $this->resetAfterTest();
        $this->person();
        $menu = $this->usermenu();

        // Core and Boost Union put Log out last, so every order keeps it there; the rest is shuffled.
        $others = array_values(array_diff(array_keys($menu['items']), ['logout']));
        $orders = [
            array_keys($menu['items']),
            array_merge(array_reverse($others), ['logout']),
            ['smartmenu', 'preferences', 'divider', 'grades', 'profile', 'language', 'switchrole', 'files',
                'invalid', 'external', 'calendar', 'logout'],
        ];
        foreach ($orders as $order) {
            $items = array_map(fn(string $name) => $menu['items'][$name], $order);
            $context = $this->context(['items' => $items, 'submenus' => $menu['submenus']]);
            $groups = $this->labels($context);
            $label = implode(',', $order);

            $myarea = array_values(array_intersect(
                array_map(fn(string $name) => (string) ($menu['items'][$name]->title ?? ''), $order),
                ['Grades', 'Calendar', 'Private files', 'Help desk', 'Library']
            ));
            $this->assertSame([profilelayer::GROUP_MYAREA, profilelayer::GROUP_ACCOUNT], array_keys($groups), $label);
            $this->assertSame($myarea, $groups[profilelayer::GROUP_MYAREA], 'My area keeps the menu\'s order: ' . $label);
            $this->assertEqualsCanonicalizing(['Preferences', 'Switch role to...'], $groups[profilelayer::GROUP_ACCOUNT]);
            $this->assertSame('Log out', $context['foot']['label'], $label);
            $this->assertStringContainsString('/login/logout.php', $context['foot']['url']);
            $this->assertTrue($context['groups'][0]['first']);
            $this->assertFalse($context['groups'][1]['first']);
        }

        // The glyphs: core's own for the default destinations, the default for anything else.
        $rows = array_column($context['groups'][0]['rows'], null, 'label');
        $this->assertSame('fa fa-clipboard-check', $rows['Grades']['icon']);
        $this->assertSame('fa-regular fa-calendar', $rows['Calendar']['icon']);
        $this->assertSame('fa fa-file-circle-minus', $rows['Private files']['icon']);
        $this->assertSame(profilelayer::DEFAULT_ICON, $rows['Help desk']['icon'], 'Another site\'s address maps to nothing.');
        $account = array_column($context['groups'][1]['rows'], null, 'label');
        $this->assertSame(profilelayer::ICON_PREFERENCES, $account['Preferences']['icon']);
        $this->assertSame(profilelayer::ICON_SWITCHROLE, $account['Switch role to...']['icon']);
    }

    /**
     * An item core identifies as Preferences is Account's even at another address; the same address unidentified is not.
     *
     * @return void
     */
    public function test_an_account_item_is_known_by_its_identifier_too(): void {
        $this->resetAfterTest();
        $this->person();

        $context = $this->context(['items' => [
            $this->item('Preferences', '/local/prefs/index.php', 'preferences,moodle'),
            $this->item('Settings', '/local/prefs/other.php', 'Settings'),
        ], 'submenus' => []]);
        $groups = $this->labels($context);

        $this->assertSame(['Preferences'], $groups[profilelayer::GROUP_ACCOUNT]);
        $this->assertSame(['Settings'], $groups[profilelayer::GROUP_MYAREA], 'Control: the same kind of address, unidentified.');
    }

    /**
     * Profile leaves the list: the header's "View profile" carries the person's own profile.
     *
     * @return void
     */
    public function test_the_profile_is_lifted_out_to_the_header(): void {
        $this->resetAfterTest();
        $user = $this->person();
        $menu = $this->usermenu();

        $context = $this->context(['items' => array_values($menu['items']), 'submenus' => $menu['submenus']]);

        foreach ($context['groups'] as $group) {
            $this->assertNotContains('Profile', array_column($group['rows'], 'label'), $group['key']);
        }
        $this->assertSame((new \moodle_url('/user/profile.php', ['id' => $user->id]))->out(false), $context['profileurl']);
        $this->assertContains('Grades', $this->labels($context)[profilelayer::GROUP_MYAREA], 'Control: its neighbour stays.');
    }

    /**
     * Only the session user's own profile address is lifted: another user's, from a custom item, stays a row.
     *
     * @return void
     */
    public function test_a_profile_item_of_another_user_stays_a_row(): void {
        $this->resetAfterTest();
        $user = $this->person();
        $other = (int) $user->id + 1;

        $context = $this->context(['items' => [
            $this->item('Profile of the dean', '/user/profile.php?id=' . $other, 'Profile of the dean'),
            $this->item('Profile', '/user/profile.php?id=' . $user->id, 'profile,moodle'),
            $this->item('Grades', '/grade/report/mygrades.php', 'grades,grades'),
        ], 'submenus' => []]);

        $this->assertSame(['Profile of the dean', 'Grades'], $this->labels($context)[profilelayer::GROUP_MYAREA]);
    }

    /**
     * Core's Log out is the foot because it is the menu's last item at the address; another item at the same
     * address, or at the way back's, is an ordinary row of "My area".
     *
     * @return void
     */
    public function test_a_second_logout_path_item_stays_a_row_while_cores_is_the_foot(): void {
        $this->resetAfterTest();
        $this->person();

        $context = $this->context(['items' => [
            $this->item('Sign out of the portal', '/login/logout.php?x=1', 'Sign-out'),
            $this->item('Back to my account', '/course/loginas.php', 'Back'),
            $this->item('Grades', '/grade/report/mygrades.php', 'grades,grades'),
            $this->item('Log out', '/login/logout.php?sesskey=x', 'logout,moodle'),
        ], 'submenus' => []]);

        $this->assertSame(
            ['Sign out of the portal', 'Back to my account', 'Grades'],
            $this->labels($context)[profilelayer::GROUP_MYAREA]
        );
        $this->assertSame('Log out', $context['foot']['label']);
        $this->assertStringContainsString('sesskey=x', $context['foot']['url']);
        $this->assertFalse($context['loggedinas'], 'Control: a row at the way back\'s address is no notice.');

        // Control: the last item at the address is the foot, whatever its title.
        $context = $this->context(['items' => [
            $this->item('Grades', '/grade/report/mygrades.php', 'grades,grades'),
            $this->item('Leave', '/login/logout.php?sesskey=y', 'logout,moodle'),
        ], 'submenus' => []]);
        $this->assertSame('Leave', $context['foot']['label']);

        // And an unrecognised last item leaves no foot: position alone decides nothing.
        $context = $this->context(['items' => [
            $this->item('Log out', '/login/logout.php?sesskey=y', 'logout,moodle'),
            $this->item('Grades', '/grade/report/mygrades.php', 'grades,grades'),
        ], 'submenus' => []]);
        $this->assertFalse($context['foot']);
    }

    /**
     * A Boost Union smart menu with children is a row that opens a sub-view of its links, its third level in place.
     *
     * @return void
     */
    public function test_a_smart_menu_opens_a_sub_view_of_its_links(): void {
        $this->resetAfterTest();
        $this->person();
        $menu = $this->usermenu();

        $context = $this->context(['items' => array_values($menu['items']), 'submenus' => $menu['submenus']]);

        $rows = array_column($context['groups'][0]['rows'], null, 'label');
        $this->assertSame(profilelayer::VIEW_MENU_PREFIX . '1', $rows['Library']['subview']);
        $this->assertSame('', $rows['Grades']['subview'], 'Control: a link opens no sub-view.');
        $views = array_column($context['subviews'], null, 'key');
        $this->assertArrayHasKey(profilelayer::VIEW_MENU_PREFIX . '1', $views);
        $library = $views[profilelayer::VIEW_MENU_PREFIX . '1'];
        $this->assertFalse($library['radio'], 'A smart menu is links, not a choice.');
        $this->assertSame(['Catalogue', 'Old issues'], array_column($library['options'], 'label'));
        // Core's language submenu is not a smart menu: the language comes from its own builder.
        $this->assertArrayNotHasKey(profilelayer::VIEW_LANGUAGE, $views, 'English alone offers no language sub-view.');
    }

    /**
     * Under "log in as" the foot is core's way back and the notice links it, labelled with the real user's name.
     *
     * @return void
     */
    public function test_the_notice_names_the_real_user_under_log_in_as(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $student = $this->person(['firstname' => 'Ana', 'lastname' => 'Ribeiro']);
        $PAGE = $this->page();
        $usermenu = (new \core\navigation\output\primary($PAGE))->get_user_menu($PAGE->get_renderer('core'));

        $context = profilelayer::context($usermenu, $PAGE, $PAGE->get_renderer('core'), null);
        $this->assertFalse($context['loggedinas'], 'Control: no notice outside log in as.');
        $this->assertStringContainsString('/login/logout.php', $context['foot']['url']);

        $this->setAdminUser();
        $admin = fullname($USER);
        \core\session\manager::loginas((int) $student->id, \context_system::instance(), false);
        $usermenu = (new \core\navigation\output\primary($PAGE))->get_user_menu($PAGE->get_renderer('core'));

        $context = profilelayer::context($usermenu, $PAGE, $PAGE->get_renderer('core'), null);
        $this->assertSame(get_string('profilelayer_loggedinas', 'theme_dimensions'), $context['loggedinas']['text']);
        $this->assertSame(get_string('returntooriginaluser', 'moodle', $admin), $context['loggedinas']['returnlabel']);
        $this->assertStringContainsString('/course/loginas.php', $context['loggedinas']['returnurl']);
        $this->assertSame($context['loggedinas']['returnurl'], $context['foot']['url'], 'The foot is the way back too.');
        $this->assertSame(get_string('logout'), $context['foot']['label'], 'Core titles the way back Log out.');
        $this->assertSame('Ana Ribeiro', $context['title'], 'The identity is the account logged in as.');
    }

    /**
     * The title and the lines are identity's, masked: the whole value never reaches the template data.
     *
     * @return void
     */
    public function test_the_identity_follows_the_settings_and_its_mask(): void {
        $this->resetAfterTest();
        $this->person(['firstname' => 'Ana', 'lastname' => 'Ribeiro', 'username' => '12345678909', 'email' => 'ana@example.com']);
        $menu = ['items' => [], 'submenus' => []];

        $context = $this->context($menu);
        $this->assertSame('Ana Ribeiro', $context['title'], 'Control: the default is the full name.');
        $this->assertSame([], $context['lines']);

        set_config(identity::SETTING_TITLE, 'username', identity::COMPONENT);
        set_config(identity::SETTING_LINES, 'fullname,email', identity::COMPONENT);
        $context = $this->context($menu);
        $this->assertSame('12345678909', $context['title'], 'Control: unmasked, the username is the title.');

        set_config(identity::SETTING_PATTERNS, 'username=***.###.###-**', identity::COMPONENT);
        $context = $this->context($menu);
        $this->assertSame('•••.456.789-••', $context['title']);
        $this->assertSame([['text' => 'Ana Ribeiro'], ['text' => 'ana@example.com']], $context['lines']);
        $this->assertStringNotContainsString('12345678909', json_encode($context, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Edit profile is where core's profile page sends this person, or absent where that page draws no link.
     *
     * Core's own tree (core_myprofile_navigation(), the site profile of one's own record) is built for the same person
     * and its editprofile node compared: the administrator (editadvanced.php), a student (edit.php through the manual
     * authentication), a student whose role prohibits editing their own profile (none), and a student of another
     * authentication plugin (no core plugin refuses can_edit_profile(), so that branch is core's tree alone).
     *
     * @return void
     */
    public function test_edit_profile_follows_cores_profile_page(): void {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/lib/myprofilelib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $this->resetAfterTest();

        $core = function (): ?string {
            global $USER;
            $tree = new \core_user\output\myprofile\tree();
            core_myprofile_navigation($tree, $USER, true, null);
            $nodes = $tree->nodes;
            return isset($nodes['editprofile']) ? $nodes['editprofile']->url->out(false) : null;
        };
        $ours = function (): ?string {
            global $USER;
            $url = profilelayer::edit_url($USER);
            return $url === null ? null : $url->out(false);
        };

        $this->setAdminUser();
        $this->assertStringContainsString('/user/editadvanced.php', (string) $core(), 'Precondition: core\'s first branch.');
        $this->assertSame($core(), $ours(), 'The administrator.');

        $student = $this->person();
        $this->assertStringContainsString('/user/edit.php', (string) $core(), 'Precondition: core\'s second branch.');
        $this->assertSame($core(), $ours(), 'A student.');

        $roleid = (int) $CFG->defaultuserroleid;
        assign_capability('moodle/user:editownprofile', CAP_PROHIBIT, $roleid, \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser(get_complete_user_data('id', $student->id));
        $this->assertNull($core(), 'Precondition: core draws no link.');
        $this->assertNull($ours(), 'A student who may not edit their own profile.');

        assign_capability('moodle/user:editownprofile', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $nologin = $this->person(['auth' => 'nologin']);
        $this->assertSame($nologin->id, $USER->id);
        $this->assertStringContainsString('/user/edit.php', (string) $core(), 'Precondition: core asks the plugin.');
        $this->assertSame($core(), $ours(), 'Another authentication plugin.');
    }

    /**
     * The link is for the session's own record only: another user's, even for an administrator who could edit them,
     * gets none.
     *
     * @return void
     */
    public function test_edit_profile_is_answered_for_the_sessions_own_record_only(): void {
        global $USER;
        $this->resetAfterTest();
        $other = self::getDataGenerator()->create_user();
        $this->setAdminUser();

        $own = profilelayer::edit_url($USER);
        $this->assertNotNull($own, 'Control: the administrator gets a link for their own record.');
        $this->assertStringContainsString('/user/editadvanced.php', $own->out(false));
        $this->assertNull(profilelayer::edit_url(get_complete_user_data('id', $other->id)), 'Another user\'s record.');
    }

    /**
     * Look, Language and the colour mode are the "Display" group's, from the layer's own builders.
     *
     * @return void
     */
    public function test_display_holds_the_look_and_the_colour_mode(): void {
        $this->resetAfterTest();
        $this->person();
        $lookmenu = [
            'current' => 'Harbour',
            'items' => [
                ['id' => 0, 'text' => 'Site default', 'url' => new \moodle_url('/theme/dimensions/flavour.php'),
                    'iscurrent' => false, 'mark' => null],
                ['id' => 3, 'text' => 'Harbour', 'url' => new \moodle_url('/theme/dimensions/flavour.php', ['flavour' => 3]),
                    'iscurrent' => true, 'mark' => ['url' => null, 'colour' => '#123456']],
            ],
        ];

        set_config('enablecolourmodes', 0, 'theme_boost');
        $context = $this->context(['items' => [], 'submenus' => []]);
        $this->assertSame([], $context['groups'], 'Control: no look, one language and no colour modes leave no group.');

        set_config('enablecolourmodes', 1, 'theme_boost');
        $context = $this->context(['items' => [], 'submenus' => []], $lookmenu);
        $this->assertCount(1, $context['groups']);
        $display = $context['groups'][0];
        $this->assertSame(profilelayer::GROUP_DISPLAY, $display['key']);
        $this->assertSame(['light', 'dark', 'auto'], array_column($display['modes']['options'], 'mode'));
        $this->assertSame(\theme_boost\colour_mode::get_cookie_attributes(), $display['modes']['cookieattributes']);
        $this->assertSame([['label' => get_string('lookmenu', 'theme_dimensions'), 'url' => '', 'icon' => profilelayer::ICON_LOOK,
            'ownicon' => false, 'subview' => profilelayer::VIEW_LOOK, 'value' => 'Harbour']], $display['rows']);
        $look = array_column($context['subviews'], null, 'key')[profilelayer::VIEW_LOOK];
        $this->assertTrue($look['radio']);
        $this->assertSame([false, true], array_column($look['options'], 'iscurrent'));
        $this->assertSame(['0', '3'], array_column($look['options'], 'flavour'));
        $this->assertSame([false, ['url' => null, 'colour' => '#123456']], array_column($look['options'], 'mark'));
    }

    /**
     * The colour mode control exists only while core lets this person choose a colour mode, the mode in force checked.
     *
     * @return void
     */
    public function test_the_colour_mode_control_follows_cores_colour_modes(): void {
        $this->resetAfterTest();
        $this->person();

        set_config('enablecolourmodes', 0, 'theme_boost');
        $this->assertFalse(\theme_boost\colour_mode::can_choose_mode(), 'Precondition: colour modes are off.');
        $this->assertNull(profilelayer::appearance());

        set_config('enablecolourmodes', 1, 'theme_boost');
        set_user_preference(\theme_boost\colour_mode::PREFERENCE, 'dark');
        $appearance = profilelayer::appearance();
        $this->assertNotNull($appearance);
        $this->assertSame(\theme_boost\colour_mode::get_cookie_attributes(), $appearance['cookieattributes']);
        $this->assertSame(['light', 'dark', 'auto'], array_column($appearance['options'], 'mode'));
        $this->assertSame([false, true, false], array_column($appearance['options'], 'isactive'));
        $this->assertSame(get_string('colourmode:auto', 'theme_boost'), $appearance['options'][2]['label']);
        $this->assertSame(
            get_string('colourmodeselected', 'theme_boost', get_string('colourmode:dark', 'theme_boost')),
            $appearance['options'][1]['togglelabel']
        );

        $this->setGuestUser();
        $this->assertNull(profilelayer::appearance(), 'The guest cannot store a preference.');
    }

    /**
     * The language choice exists only while core's language menu offers more than one language, the current one checked
     * and every other one core's own address with its lang attribute.
     *
     * @return void
     */
    public function test_the_language_follows_cores_language_menu(): void {
        global $CFG, $PAGE;
        $this->resetAfterTest();
        $this->person();
        $page = $this->page();
        $output = $PAGE->get_renderer('core');

        $this->assertCount(1, get_string_manager()->get_list_of_translations(), 'Precondition: English alone.');
        $this->assertNull(profilelayer::language($page, $output));

        $folder = $CFG->dataroot . '/lang/xx';
        check_dir_exists($folder);
        file_put_contents(
            $folder . '/langconfig.php',
            "<?php\n\$string['thislanguage'] = 'Xx';\n\$string['parentlanguage'] = '';\n"
        );
        get_string_manager()->reset_caches();
        $this->assertCount(2, get_string_manager()->get_list_of_translations(), 'Precondition: two languages.');

        $language = profilelayer::language($page, $output);
        $this->assertNotNull($language);
        $bylang = array_column($language['items'], null, 'lang');
        $this->assertArrayHasKey('xx', $bylang, 'The other language carries its lang attribute.');
        $this->assertStringStartsWith('Xx', $bylang['xx']['text']);
        $this->assertFalse($bylang['xx']['iscurrent']);
        $this->assertSame((new \moodle_url('/my/', ['lang' => 'xx']))->out(false), $bylang['xx']['url']);
        $current = array_values(array_filter($language['items'], fn($item) => $item['iscurrent']));
        $this->assertCount(1, $current, 'The current language is marked.');
        $this->assertNotSame('', $language['current']);

        $context = $this->context(['items' => [], 'submenus' => []]);
        $views = array_column($context['subviews'], null, 'key');
        $this->assertArrayHasKey(profilelayer::VIEW_LANGUAGE, $views, 'The layer draws it as a sub-view.');
        $this->assertContains('xx', array_column($views[profilelayer::VIEW_LANGUAGE]['options'], 'lang'));

        $CFG->langmenu = 0;
        $this->assertNull(profilelayer::language($page, $output), 'The site switched the language menu off.');
    }

    /**
     * Every key of every row and option is set, so the template's context stack never reaches the page around it.
     *
     * @return void
     */
    public function test_every_row_and_option_key_is_always_set(): void {
        $this->resetAfterTest();
        $this->person();
        $menu = $this->usermenu();

        $context = $this->context(['items' => array_values($menu['items']), 'submenus' => $menu['submenus']]);

        foreach ($context['groups'] as $group) {
            $this->assertSame(['key', 'label', 'first', 'modes', 'hasrows', 'rows'], array_keys($group));
            foreach ($group['rows'] as $row) {
                $this->assertSame(['label', 'url', 'icon', 'ownicon', 'subview', 'value'], array_keys($row));
            }
        }
        foreach ($context['subviews'] as $view) {
            $this->assertSame(['key', 'title', 'radio', 'look', 'options'], array_keys($view));
            foreach ($view['options'] as $option) {
                $this->assertSame(['label', 'url', 'iscurrent', 'lang', 'flavour', 'mark'], array_keys($option));
            }
        }
    }

    /**
     * On a page drawn with the settings at their defaults, the layer's data costs no statement.
     *
     * The page's own header is rendered first, as on a real request, so the user's access data, the user menu and
     * the strings are what that page already paid for; the layer's builder then reads nothing more.
     *
     * @return void
     */
    public function test_a_page_with_default_settings_costs_no_statement(): void {
        global $DB, $PAGE, $OUTPUT, $SITE;
        $this->resetAfterTest();
        $this->person();
        set_config('enablecolourmodes', 1, 'theme_boost');

        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_course($SITE);
        $PAGE->set_url('/my/');
        $PAGE->set_pagelayout('standard');
        $PAGE->force_theme('dimensions');
        $PAGE->initialise_theme_and_output();
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $OUTPUT->header();
        $usermenu = (new \core\navigation\output\primary($PAGE))->get_user_menu($OUTPUT);
        $lookmenu = flavourchoice::menu($PAGE->url);

        $reads = $DB->perf_get_reads();
        $this->assertTrue(profilelayer::applies($PAGE));
        $context = profilelayer::context($usermenu, $PAGE, $OUTPUT, $lookmenu);
        $this->assertSame(0, $DB->perf_get_reads() - $reads);
        $this->assertNotSame('', $context['title'], 'Control: the data was built.');
        $this->assertNotFalse($context['editurl'], 'Control: Edit profile was decided.');
    }
}
