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
 * Theme Dimensions - Tests for the Look submenu of the user menu, the drawer's Look panel and the choice form
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use theme_dimensions\local\drawernav;
use theme_dimensions\local\flavourchoice;

/**
 * The Look controls as a page draws them: the real header through Boost Union's layout.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(core_renderer::class)]
#[CoversClass(drawernav::class)]
#[CoversClass(\theme_dimensions\form\flavourchoice::class)]
#[CoversFunction('theme_dimensions_extend_navigation_user_settings')]
final class look_menu_test extends \advanced_testcase {
    /**
     * A Boost Union flavour, made by Boost Union's own generator.
     *
     * @param string $title The flavour's title.
     * @return \stdClass The flavour.
     */
    private function flavour(string $title): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('theme_boost_union')->create_flavour(['title' => $title]);
    }

    /**
     * Offer exactly these flavours in the setting.
     *
     * @param \stdClass ...$flavours The flavours.
     * @return void
     */
    private function offer(\stdClass ...$flavours): void {
        set_config(flavourchoice::SETTING, implode(',', array_map(fn($f) => $f->id, $flavours)), 'theme_dimensions');
    }

    /**
     * Render the real header of the dashboard for the current user, on a fresh page.
     *
     * @return string The header.
     */
    private function header(): string {
        global $PAGE, $OUTPUT, $SITE;

        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_course($SITE);
        $PAGE->set_url('/my/');
        $PAGE->set_pagelayout('standard');
        $PAGE->set_title('Dashboard');
        $PAGE->set_heading('Dashboard');
        $PAGE->force_theme('dimensions');
        $PAGE->initialise_theme_and_output();
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);

        return $OUTPUT->header();
    }

    /**
     * The AMD calls the page has queued.
     *
     * @return string Every queued call, concatenated.
     */
    private function collected_amd(): string {
        global $PAGE;

        $property = new \ReflectionProperty($PAGE->requires, 'amdjscode');
        return implode("\n", (array) $property->getValue($PAGE->requires));
    }

    /**
     * The user menu's carousel item of the Look submenu, alone.
     *
     * @param string $html The header.
     * @return string The submenu's markup, or '' when there is none.
     */
    private function look_submenu(string $html): string {
        if (!preg_match('~<div id="carousel-item-dimensions-look".*?</div>\s*</div>\s*</div>~s', $html, $match)) {
            return '';
        }

        return $match[0];
    }

    /**
     * The user menu offers the site default and each offered flavour, the current one marked, after Preferences.
     *
     * @return void
     */
    public function test_the_user_menu_carries_the_look_submenu(): void {
        $this->resetAfterTest();
        $harbour = $this->flavour('Harbour & Co');
        $campus = $this->flavour('Campus');
        $this->offer($harbour, $campus);
        $this->setUser($this->getDataGenerator()->create_user());
        set_user_preference(flavourchoice::PREFERENCE, $campus->id);

        $html = $this->header();

        $this->assertStringContainsString('data-carousel-target-id="carousel-item-dimensions-look"', $html);
        $submenu = $this->look_submenu($html);
        $this->assertNotSame('', $submenu, 'The submenu is drawn.');
        foreach ([flavourchoice::SITE_DEFAULT, (int) $harbour->id, (int) $campus->id] as $id) {
            $this->assertStringContainsString('data-dimensions-flavour="' . $id . '"', $submenu);
        }
        $this->assertMatchesRegularExpression(
            '~aria-current="true"[^>]*data-dimensions-flavour="' . $campus->id . '"~s',
            $submenu,
            'The flavour in force is the current item.'
        );
        $this->assertStringContainsString('Harbour &amp; Co', $submenu);
        $this->assertStringNotContainsString('&amp;amp;', $submenu, 'The title is escaped once.');

        $preferences = strpos($html, '/user/preferences.php');
        $look = strpos($html, 'data-carousel-target-id="carousel-item-dimensions-look"');
        $logout = strpos($html, '/login/logout.php');
        $this->assertTrue($preferences < $look && $look < $logout, 'Between Preferences and Log out.');

        $this->assertStringContainsString('theme_dimensions/flavourchoice', $this->collected_amd());
    }

    /**
     * Nothing is added while the setting offers nothing, or for the guest; a real account with a list is the control.
     *
     * @return void
     */
    public function test_no_look_submenu_without_a_list_or_for_the_guest(): void {
        $this->resetAfterTest();
        $harbour = $this->flavour('Harbour');
        $this->setUser($this->getDataGenerator()->create_user());

        $html = $this->header();
        $this->assertStringNotContainsString('carousel-item-dimensions-look', $html, 'Nothing offered.');
        $this->assertStringNotContainsString('theme_dimensions/flavourchoice', $this->collected_amd());

        $this->offer($harbour);
        $this->setGuestUser();
        $this->assertStringNotContainsString('carousel-item-dimensions-look', $this->header(), 'The guest.');

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertStringContainsString('carousel-item-dimensions-look', $this->header(), 'Control: an account.');
    }

    /**
     * The drawer has a Look row and panel: the current look is plain text, every other one a link carrying its id.
     *
     * @return void
     */
    public function test_the_drawer_carries_the_look_panel(): void {
        $this->resetAfterTest();
        $harbour = $this->flavour('Harbour & Co');
        $this->offer($harbour);
        $this->setUser($this->getDataGenerator()->create_user());

        $html = $this->header();

        $this->assertStringContainsString('id="' . drawernav::LOOK . '"', $html);
        $this->assertStringContainsString('data-target="' . drawernav::LOOK . '"', $html);
        $this->assertMatchesRegularExpression(
            '~<a class="dimensions-drawer-item" href="[^"]*flavour\.php[^"]*" data-dimensions-flavour="' . $harbour->id . '">~',
            $html
        );
        $this->assertMatchesRegularExpression(
            '~<span class="dimensions-drawer-item is-current" aria-current="true">\s*<span class="dimensions-drawer-text">'
                . preg_quote(get_string('flavoursitedefault', 'theme_dimensions'), '~') . '</span>~',
            $html
        );

        $this->assertMatchesRegularExpression(
            '~data-dimensions-flavour="' . $harbour->id . '">\\s*<span class="dimensions-drawer-text">Harbour &amp; Co</span>~',
            $html,
            'The drawer escapes the plain title once.'
        );
        $this->assertStringNotContainsString('&amp;amp;', $html);

        set_config(flavourchoice::SETTING, '', 'theme_dimensions');
        $this->assertStringNotContainsString(drawernav::LOOK, $this->header(), 'Nothing offered, no row.');
    }

    /**
     * The user settings navigation as core hands it to the callback, after the callback ran.
     *
     * @param string $url The page's url.
     * @param \stdClass $user The user whose settings are shown.
     * @param string $theme The theme the page is drawn in.
     * @return \navigation_node|false The Look node, or false when the callback added none.
     */
    private function look_node(string $url, \stdClass $user, string $theme = 'dimensions'): \navigation_node|false {
        global $CFG, $PAGE, $SITE;
        require_once($CFG->dirroot . '/theme/dimensions/lib.php');

        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_course($SITE);
        $PAGE->set_url($url);
        $PAGE->force_theme($theme);

        $root = \navigation_node::create('usersettings');
        $root->add('Account', null, \navigation_node::TYPE_CONTAINER, null, 'useraccount');
        theme_dimensions_extend_navigation_user_settings(
            $root,
            $user,
            \context_user::instance($user->id),
            $SITE,
            \context_course::instance($SITE->id)
        );

        return $root->find('dimensionslook', \navigation_node::TYPE_SETTING);
    }

    /**
     * The preferences page links to the choice page, for the person's own preferences only.
     *
     * Each refusal has the same account and offered list as the control that gets the link, so each one
     * is that guard's alone: another page, another user's preferences, another theme, nothing offered.
     *
     * @return void
     */
    public function test_the_preferences_page_links_to_the_choice_page(): void {
        $this->resetAfterTest();
        $harbour = $this->flavour('Harbour');
        $this->offer($harbour);
        $me = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($me);

        $node = $this->look_node('/user/preferences.php', $me);
        $this->assertNotFalse($node, 'Control: my own preferences page.');
        $this->assertSame(get_string('lookmenu', 'theme_dimensions'), $node->get_content());
        $this->assertStringEndsWith('/theme/dimensions/flavour.php', $node->action->get_path(false));

        $this->assertFalse($this->look_node('/user/edit.php', $me), 'Another page.');
        $this->assertFalse($this->look_node('/user/preferences.php', $other), 'Someone else\'s preferences.');
        $this->assertFalse($this->look_node('/user/preferences.php', $me, 'boost'), 'A page drawn in Boost.');

        set_config(flavourchoice::SETTING, '', 'theme_dimensions');
        $this->assertFalse($this->look_node('/user/preferences.php', $me), 'Nothing offered.');
    }

    /**
     * The choice form refuses a look it did not offer, and accepts one it did.
     *
     * @return void
     */
    public function test_the_choice_form_refuses_a_look_it_did_not_offer(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $form = new \theme_dimensions\form\flavourchoice(null, ['options' => [0 => 'Site default', 7 => 'Harbour & Co']]);

        // Core prints a radio's text raw, so the plain title must arrive escaped once.
        $html = $form->render();
        $this->assertStringContainsString('Harbour &amp; Co', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);

        $this->assertArrayHasKey('flavourgroup', $form->validation(['flavour' => 9], []));
        $this->assertSame([], $form->validation(['flavour' => 7], []));
        $this->assertSame([], $form->validation(['flavour' => 0], []));
    }

    /**
     * Two offered looks with the marks switched on: Harbour & Co with a favicon, Campus with a brand colour alone.
     *
     * @return \stdClass[] harbour and campus.
     */
    private function marked_looks(): array {
        global $DB;

        $harbour = $this->flavour('Harbour & Co');
        $campus = $this->flavour('Campus');
        $DB->set_field('theme_boost_union_flavours', 'look_favicon', 'harbour.png', ['id' => $harbour->id]);
        $DB->set_field('theme_boost_union_flavours', 'look_brandcolor', '#7a1f2b', ['id' => $campus->id]);
        $this->offer($harbour, $campus);

        return [$harbour, $campus];
    }

    /**
     * With the setting on, the user menu's submenu puts each look's mark before its escaped title; off, none.
     *
     * @return void
     */
    public function test_the_user_menu_marks_each_look_while_the_setting_is_on(): void {
        $this->resetAfterTest();
        [$harbour] = $this->marked_looks();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertStringNotContainsString('dimensions-look-mark', $this->look_submenu($this->header()), 'Off: no mark.');

        set_config(flavourchoice::ICONS_SETTING, 1, 'theme_dimensions');
        $submenu = $this->look_submenu($this->header());

        $favicon = s(flavourchoice::flavour_favicon((int) $harbour->id, 'harbour.png')->out(false));
        $this->assertMatchesRegularExpression(
            '~<img class="dimensions-look-favicon" src="' . preg_quote($favicon, '~') . '"[^>]*loading="lazy">\s*</span>'
                . '\s*Harbour &amp; Co~',
            $submenu,
            'The favicon, then the title escaped once.'
        );
        $this->assertStringContainsString('<span class="dimensions-look-dot" style="background-color: #7a1f2b;"></span>', $submenu);
        $this->assertStringContainsString(
            'src="' . s(flavourchoice::site_favicon()->out(false)) . '"',
            $submenu,
            'The site default shows the site\'s favicon.'
        );
        $this->assertStringNotContainsString('&amp;amp;', $submenu);
    }

    /**
     * The drawer's Look panel draws the same marks before each name.
     *
     * @return void
     */
    public function test_the_drawer_marks_each_look_while_the_setting_is_on(): void {
        $this->resetAfterTest();
        [$harbour, $campus] = $this->marked_looks();
        $this->setUser($this->getDataGenerator()->create_user());
        $link = '~data-dimensions-flavour="' . $campus->id . '">\s*<span class="dimensions-look-mark"~';

        $this->assertDoesNotMatchRegularExpression($link, $this->header(), 'Off: no mark.');

        set_config(flavourchoice::ICONS_SETTING, 1, 'theme_dimensions');
        $html = $this->header();

        $this->assertMatchesRegularExpression($link, $html);
        $this->assertMatchesRegularExpression(
            '~data-dimensions-flavour="' . $harbour->id . '">\s*<span class="dimensions-look-mark" aria-hidden="true">'
                . '<img class="dimensions-look-favicon"[^>]*>\s*</span>\s*'
                . '<span class="dimensions-drawer-text">Harbour &amp; Co</span>~',
            $html
        );
    }

    /**
     * The choice page's radios carry the mark it is handed before each escaped label, and none without one.
     *
     * @return void
     */
    public function test_the_choice_form_prints_each_looks_mark_before_its_label(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $mark = '<span class="dimensions-look-mark" aria-hidden="true"></span>';
        $form = new \theme_dimensions\form\flavourchoice(
            null,
            ['options' => [0 => 'Site default', 7 => 'Harbour & Co'], 'marks' => [7 => $mark]]
        );

        $html = $form->render();

        $this->assertStringContainsString($mark . 'Harbour &amp; Co', $html);
        $this->assertSame(1, substr_count($html, 'dimensions-look-mark'), 'Only the look handed a mark has one.');
    }
}
