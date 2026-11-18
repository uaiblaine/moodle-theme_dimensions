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
 * Theme Dimensions - Tests for the mobile drawer as the page draws it
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\drawernav;
use theme_dimensions\local\guestnav;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Renders the real page header - Boost Union's layout, its navbar and this theme's drawer override - and the
 * drawer's template from its own example, because what the renderer's shape guard wires and what the
 * client module addresses are only visible in the output.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(core_renderer::class)]
#[CoversClass(drawernav::class)]
final class drawer_render_test extends \advanced_testcase {
    /** @var string The drawer's root, as the template writes it. */
    private const DRAWER = 'data-region="dimensions-drawer"';

    /** @var string Boost Union's list, which every other session keeps. */
    private const BU_LIST = '<div class="list-group">';

    /**
     * This theme's renderer on a fresh page of the standard layout.
     *
     * @return core_renderer
     */
    private function renderer(): core_renderer {
        global $PAGE, $OUTPUT, $SITE;

        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_course($SITE);
        $PAGE->set_url('/my/courses.php');
        $PAGE->set_pagelayout('standard');
        $PAGE->force_theme('dimensions');
        $PAGE->initialise_theme_and_output();
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertInstanceOf(core_renderer::class, $OUTPUT);
        $PAGE->set_title('My courses');
        $PAGE->set_heading('My courses');

        return $OUTPUT;
    }

    /**
     * The mobile drawer's markup out of the real header.
     *
     * @return string The primary drawer's element and what follows it.
     */
    private function drawer_html(): string {
        $html = $this->renderer()->header();
        $at = strpos($html, 'id="theme_boost-drawers-primary"');
        $this->assertNotFalse($at, 'Precondition: the page draws the primary drawer.');

        return substr($html, $at);
    }

    /**
     * The theme's drawer element alone, out of a page, as the browser would build it.
     *
     * @param string $html The page or a part of it.
     * @return string The drawer's outer markup.
     */
    private function drawer_only(string $html): string {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $drawer = $document->getElementById('dimensions-drawer');
        $this->assertNotNull($drawer, 'Precondition: the page draws the theme\'s drawer.');

        return (string) $document->saveHTML($drawer);
    }

    /**
     * A template's own example context.
     *
     * @param string $name The template's file name in templates/, without the extension.
     * @return array
     */
    private function example_context(string $name): array {
        $source = file_get_contents(dirname(__DIR__, 2) . '/templates/' . $name . '.mustache');
        $found = preg_match('/Example context \(json\):\s*(.*?)\n\}\}/s', $source, $matches);
        $this->assertSame(1, $found, "No example context in {$name}.");
        $context = json_decode($matches[1], true);
        $this->assertIsArray($context, "The example context of {$name} is not JSON.");

        return $context;
    }

    /**
     * A logged-in account gets the theme's drawer in place of Boost Union's list, with its sections.
     *
     * @return void
     */
    public function test_a_logged_in_account_gets_the_theme_drawer(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Souza']));
        // The two nodes core adds for a logged-in account, switched on so the test does not rest on the defaults.
        set_config('enablemycourses', 1);
        set_config('enabledashboard', 1);

        $html = $this->drawer_html();

        $this->assertStringContainsString(self::DRAWER, $html);
        $this->assertStringNotContainsString(self::BU_LIST, $html);
        $this->assertStringContainsString('>' . get_string('drawer_browse', 'theme_dimensions') . '</p>', $html);
        $this->assertMatchesRegularExpression(
            '~<a class="dimensions-drawer-item is-current" href="[^"]*/my/courses\.php" aria-current="page">~',
            $html,
            'The page being shown is marked.'
        );
        $this->assertStringNotContainsString('data-region="dimensions-guestcard"', $html);

        set_config(drawernav::SETTING, '0', drawernav::COMPONENT);
        $off = $this->drawer_html();
        $this->assertStringNotContainsString(self::DRAWER, $off);
        $this->assertStringContainsString(self::BU_LIST, $off, 'Control: Boost Union\'s list with the feature off.');
    }

    /**
     * The drawer holds nothing about the person (D72, D77): no name, no picture, no role line, no colour mode control,
     * no Look and no language. They are the profile layer's.
     *
     * The force is on: an administrator with a name, core's colour modes enabled and a look offered, each of which
     * once drew a part of the drawer; and the page still carries the navbar's own colour mode menu outside it.
     *
     * @return void
     */
    public function test_the_drawer_holds_nothing_about_the_person(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $USER->firstname = 'Ana';
        $USER->lastname = 'Souza';
        set_config('enablecolourmodes', 1, 'theme_boost');
        $flavour = $this->getDataGenerator()->get_plugin_generator('theme_boost_union')->create_flavour(['title' => 'Harbour']);
        set_config(\theme_dimensions\local\flavourchoice::SETTING, (string) $flavour->id, 'theme_dimensions');

        $html = $this->renderer()->header();
        $drawer = $this->drawer_only($html);

        $this->assertStringContainsString('data-action="set-colourmode"', $html, 'Control: the page draws a colour mode control.');
        $this->assertStringContainsString('dimensions-drawer-section', $drawer, 'Control: the drawer was drawn.');
        foreach (
            [
                'Ana Souza',
                'data-region="colourmode-menu"',
                'data-action="set-colourmode"',
                'data-dimensions-flavour',
                '?lang=',
                'dimensions-drawer-role',
                'userpicture',
                'userinitials',
                'Site administrator',
            ] as $absent
        ) {
            $this->assertStringNotContainsString($absent, $drawer, $absent);
        }
    }

    /**
     * A visitor keeps Boost Union's list and the "New here?" card, untouched.
     *
     * @return void
     */
    public function test_a_visitor_keeps_boost_unions_list_and_the_card(): void {
        $this->resetAfterTest();

        $this->setUser(null);
        $html = $this->drawer_html();
        $this->assertStringNotContainsString(self::DRAWER, $html);
        $this->assertStringContainsString(self::BU_LIST, $html);
        $this->assertStringContainsString('data-region="dimensions-guestcard"', $html);

        $this->setGuestUser();
        $guest = $this->drawer_html();
        $this->assertStringNotContainsString(self::DRAWER, $guest);
        $this->assertStringContainsString('data-region="dimensions-guestcard"', $guest);
        $this->assertTrue(guestnav::enabled(), 'Precondition: the visitor\'s feature is on.');
    }

    /**
     * The renderer reads the context's shape: a context without the user menu's avatars is left as it came.
     *
     * The same template, rendered for the same logged-in account, with and without usermenu.avatardata: the
     * drawer exists only where core wrote the shape of a logged-in account's layout.
     *
     * @return void
     */
    public function test_the_drawer_is_wired_by_the_shape_of_the_context(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $renderer = $this->renderer();
        $context = [
            'mobileprimarynav' => [
                ['key' => 'home', 'text' => 'Home', 'url' => 'https://example.com/', 'isactive' => false,
                    'haschildren' => 0, 'children' => []],
            ],
            'usermenu' => ['userfullname' => 'Ana', 'avatardata' => [['content' => '', 'classes' => 'current']]],
            'output' => $renderer,
            'config' => ['homeurl' => 'https://example.com/'],
            'sitename' => 'Site',
        ];

        $with = $renderer->render_from_template('theme_boost/primary-drawer-mobile', $context);
        $this->assertStringContainsString(self::DRAWER, $with, 'Control: the shape of a logged-in layout.');

        unset($context['usermenu']['avatardata']);
        $without = $renderer->render_from_template('theme_boost/primary-drawer-mobile', $context);
        $this->assertStringNotContainsString(self::DRAWER, $without);
        $this->assertStringContainsString(self::BU_LIST, $without);
    }

    /**
     * The template, from its own example, carries every hook amd/src/drawer.js addresses.
     *
     * @return void
     */
    public function test_the_template_carries_the_hooks_the_modules_address(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $context = $this->example_context('drawer_nav');

        $html = $OUTPUT->render_from_template('theme_dimensions/drawer_nav', $context);

        // A navigation landmark, named.
        $this->assertStringContainsString(
            '<nav class="dimensions-drawer" id="dimensions-drawer" data-region="dimensions-drawer" aria-label="Site navigation">',
            $html
        );
        // The panels: the root first, then each sub-panel naming its parent.
        $this->assertStringContainsString('id="dimensions-drawer-panel-root" data-region="dimensions-drawer-panel"', $html);
        $this->assertMatchesRegularExpression(
            '~id="dimensions-drawer-panel-1" data-region="dimensions-drawer-panel" data-level="1"'
                . ' data-parent="dimensions-drawer-panel-root"~',
            $html
        );
        // A row with children is a link to its panel's anchor, so the level is reachable without the module.
        $this->assertStringContainsString(
            'href="#dimensions-drawer-panel-1" data-action="dimensions-drawer-open" data-target="dimensions-drawer-panel-1"',
            $html
        );
        $this->assertStringContainsString(
            'href="#dimensions-drawer-panel-root" data-action="dimensions-drawer-back" aria-label="Back to Navigation"',
            $html
        );
        // A sub-panel's head is the side layer's sub-view header: the back control, named for its parent, then the title.
        $this->assertMatchesRegularExpression(
            '~<div class="dimensions-drawer-sub">\s*<a class="dimensions-drawer-back" href="#dimensions-drawer-panel-root"'
                . '[^>]*>\s*<i class="icon fa fa-chevron-left fa-fw" aria-hidden="true"></i>\s*</a>\s*'
                . '<p class="dimensions-drawer-title" id="dimensions-drawer-panel-1-title">Help centre</p>\s*</div>~',
            $html
        );
        // The summary's targets.
        foreach (['resume', 'resume-link', 'resume-title', 'resume-sub', 'resume-bar'] as $region) {
            $this->assertStringContainsString('data-region="dimensions-drawer-' . $region . '"', $html, $region);
        }
        $this->assertStringContainsString('data-region="dimensions-drawer-resume" aria-busy="true"', $html);
        // The current page.
        $this->assertMatchesRegularExpression(
            '~<a class="dimensions-drawer-item is-current" href="https://example.com/my/courses\.php" aria-current="page">~',
            $html
        );
        // A sub-panel's parent address.
        $this->assertStringContainsString('<a class="dimensions-drawer-openparent" href="https://example.com/help">', $html);
        // Nothing about the person, and no colour mode module to load.
        $this->assertStringNotContainsString('colourmode', $html);
        $this->assertStringNotContainsString('dimensions-drawer-role', $html);
    }
}
