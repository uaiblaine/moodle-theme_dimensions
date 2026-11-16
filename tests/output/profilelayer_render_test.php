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
 * Theme Dimensions - Tests for the profile layer as the page prints it
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\identity;
use theme_dimensions\local\profilelayer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Renders a real page - Boost Union's layout with its navbar and its footer - and the layer's template from its own
 * example, because where the renderer prints the layer, and how often, is only visible in the output.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(core_renderer::class)]
#[CoversClass(profilelayer::class)]
final class profilelayer_render_test extends \advanced_testcase {
    /** @var string The layer's dialog, as the template writes it. */
    private const LAYER = 'data-region="dimensions-profile"';

    /** @var string Boost Union's user menu toggle, the layer's fallback, which stays on the page. */
    private const TOGGLE = 'id="user-menu-toggle"';

    /** @var string The layer's client module, as the page's AMD calls name it. */
    private const MODULE = 'theme_dimensions/profilelayer';

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
        $PAGE->set_url('/my/');
        $PAGE->set_pagelayout('standard');
        $PAGE->force_theme('dimensions');
        $PAGE->initialise_theme_and_output();
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertInstanceOf(core_renderer::class, $OUTPUT);
        $PAGE->set_title('Dashboard');
        $PAGE->set_heading('Dashboard');

        return $OUTPUT;
    }

    /**
     * The whole page in its two halves, split where the page's own content goes.
     *
     * @return array The header half and the footer half.
     */
    private function halves(): array {
        $renderer = $this->renderer();
        $header = $renderer->header();

        return [$header, $renderer->footer()];
    }

    /**
     * A person as the session holds them, logged in.
     *
     * @param array $record The user's columns.
     * @return \stdClass
     */
    private function person(array $record = []): \stdClass {
        $sink = $this->redirectEvents();
        $user = self::getDataGenerator()->create_user($record);
        $sink->close();
        $complete = get_complete_user_data('id', $user->id);
        $this->setUser($complete);

        return $complete;
    }

    /**
     * How many of the AMD calls the page has queued arm the layer's module.
     *
     * @return int One per js_call_amd(), whose snippet names the module more than once.
     */
    private function armed(): int {
        global $PAGE;

        $property = new \ReflectionProperty($PAGE->requires, 'amdjscode');
        $calls = array_filter(
            (array) $property->getValue($PAGE->requires),
            static fn($code): bool => str_contains((string) $code, "'" . self::MODULE . "'")
        );

        return count($calls);
    }

    /**
     * The layer's markup out of a page: from its backdrop to the end of the page.
     *
     * @param string $html The page.
     * @return string
     */
    private function layer(string $html): string {
        $at = strpos($html, 'data-region="dimensions-layer-backdrop"');
        $this->assertNotFalse($at, 'Precondition: the page prints the layer.');

        return substr($html, $at);
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
     * A logged-in account's page prints the layer once, closed, after the content, outside the navbar and the footer.
     *
     * Boost Union's footer button is switched on, so its footer popover's content, where core and Boost Union print
     * the end-of-body token, is on the page; Boost Union's dropdown is still the user button's, the fallback. In a process
     * of its own, because Boost Union's layouts load includes/footer.php with require_once: only the first page of a
     * process carries the footer button's keys.
     *
     * @return void
     */
    #[RunInSeparateProcess]
    public function test_the_layer_is_printed_once_closed_after_the_content(): void {
        $this->resetAfterTest();
        $this->person(['firstname' => 'Ana', 'lastname' => 'Ribeiro']);
        set_config('enablefooterbutton', THEME_BOOST_UNION_SETTING_ENABLEFOOTER_ALL, 'theme_boost_union');

        [$header, $footer] = $this->halves();

        $this->assertStringContainsString('data-region="footer-content-popover"', $footer, 'Precondition: the popover.');
        $this->assertStringContainsString(self::TOGGLE, $header, 'Boost Union\'s user menu stays.');
        $this->assertStringNotContainsString(self::LAYER, $header, 'Nothing of the layer in the navbar\'s half.');
        $this->assertSame(1, substr_count($footer, self::LAYER), 'Printed exactly once.');
        $this->assertSame(1, substr_count($footer, 'data-region="dimensions-layer-backdrop"'));
        $this->assertMatchesRegularExpression(
            '~<div class="dimensions-layer-backdrop" data-region="dimensions-layer-backdrop" hidden></div>\s*'
                . '<div class="dimensions-layer dimensions-layer--end" id="([^"]+)-profile" role="dialog" aria-modal="true"'
                . ' aria-labelledby="\1-title" data-region="dimensions-profile" hidden>~',
            $footer,
            'The backdrop and the dialog are closed.'
        );
        $layer = $this->layer($footer);
        $this->assertMatchesRegularExpression('~<h2 class="dimensions-layer-title" id="[^"]+-title">Ana Ribeiro</h2>~', $layer);
        // Outside the nav and outside the footer (whose popover content core hides and copies): every one of either
        // opened before the layer is closed before it.
        $before = $header . substr($footer, 0, strpos($footer, 'data-region="dimensions-layer-backdrop"'));
        $this->assertSame(substr_count($before, '<nav'), substr_count($before, '</nav>'), 'Outside the navbar.');
        $this->assertSame(substr_count($before, '<footer'), substr_count($before, '</footer>'), 'Outside the footer.');
        $this->assertGreaterThan(0, substr_count($before, '</footer>'), 'Precondition: the page draws its footer.');
        $this->assertStringNotContainsString('data-region="footer-content-popover"', $layer, 'Not in the footer popover.');
        $this->assertStringContainsString(get_string('viewprofile'), $layer);
        $this->assertStringContainsString('/login/logout.php', $layer);
    }

    /**
     * The renderer prints what it stored once, after core's own output: a second call prints core's output alone.
     *
     * @return void
     */
    public function test_the_layer_is_printed_once_after_cores_output(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->person();
        $CFG->additionalhtmlbottomofbody = '<span id="dimensions-probe-bottom"></span>';
        $renderer = $this->renderer();
        $renderer->header();

        $context = ['usermenu' => ['avatardata' => [['content' => '', 'classes' => 'current']], 'items' => []]];
        $renderer->render_from_template('theme_dimensions/look_mark', $context + ['url' => null, 'colour' => null]);

        $first = $renderer->standard_after_main_region_html();
        $second = $renderer->standard_after_main_region_html();
        $this->assertStringContainsString(self::LAYER, $first);
        $this->assertStringNotContainsString(self::LAYER, $second);
        $this->assertStringContainsString('dimensions-probe-bottom', $second, 'Control: core\'s output is still printed.');
        $this->assertLessThan(
            strpos($first, 'data-region="dimensions-layer-backdrop"'),
            strpos($first, 'dimensions-probe-bottom'),
            'The layer goes after core\'s output.'
        );
    }

    /**
     * The renderer reads the context's shape: a user menu without the avatars stores no layer.
     *
     * @return void
     */
    public function test_the_layer_is_wired_by_the_shape_of_the_context(): void {
        $this->resetAfterTest();
        $this->person();
        $renderer = $this->renderer();
        $renderer->header();
        $renderer->standard_after_main_region_html();
        $context = [
            'usermenu' => ['avatardata' => [['content' => '', 'classes' => 'current']], 'items' => []],
            'url' => null,
            'colour' => null,
        ];

        $renderer->render_from_template('theme_dimensions/look_mark', $context);
        $this->assertStringContainsString(self::LAYER, $renderer->standard_after_main_region_html(), 'Control: the shape.');

        unset($context['usermenu']['avatardata']);
        $renderer->render_from_template('theme_dimensions/look_mark', $context);
        $this->assertStringNotContainsString(self::LAYER, $renderer->standard_after_main_region_html());
    }

    /**
     * A visitor, the guest account and a site that switched the layer off get no layer; a real account is the control.
     *
     * @return void
     */
    public function test_a_visitor_the_guest_and_the_switch_off_get_no_layer(): void {
        $this->resetAfterTest();

        $this->person();
        [, $footer] = $this->halves();
        $this->assertStringContainsString(self::LAYER, $footer, 'Control: a real account.');

        set_config(profilelayer::SETTING, '0', profilelayer::COMPONENT);
        [$header, $footer] = $this->halves();
        $this->assertStringNotContainsString(self::LAYER, $footer, 'Switched off.');
        $this->assertStringContainsString(self::TOGGLE, $header, 'Boost Union\'s user menu is the menu.');
        set_config(profilelayer::SETTING, '1', profilelayer::COMPONENT);

        $this->setUser(null);
        [, $footer] = $this->halves();
        $this->assertStringNotContainsString(self::LAYER, $footer, 'A visitor.');

        $this->setGuestUser();
        [, $footer] = $this->halves();
        $this->assertStringNotContainsString(self::LAYER, $footer, 'The guest account.');
    }

    /**
     * The layer's client module is armed on a page that prints the layer, once, and on no other: a visitor, the guest
     * account and a site that switched the layer off keep Boost Union's dropdown on the user button.
     *
     * @return void
     */
    public function test_the_module_is_armed_only_where_the_layer_is_printed(): void {
        $this->resetAfterTest();

        $this->person();
        [, $footer] = $this->halves();
        $this->assertStringContainsString(self::LAYER, $footer, 'Precondition: the layer is printed.');
        $this->assertSame(1, $this->armed(), 'Armed once, beside the print.');

        set_config(profilelayer::SETTING, '0', profilelayer::COMPONENT);
        $this->halves();
        $this->assertSame(0, $this->armed(), 'Switched off.');
        set_config(profilelayer::SETTING, '1', profilelayer::COMPONENT);

        $this->setUser(null);
        $this->halves();
        $this->assertSame(0, $this->armed(), 'A visitor.');

        $this->setGuestUser();
        $this->halves();
        $this->assertSame(0, $this->armed(), 'The guest account.');
    }

    /**
     * A second print of the region after the main content arms nothing more: the module is armed with the layer.
     *
     * @return void
     */
    public function test_a_second_call_arms_nothing(): void {
        $this->resetAfterTest();
        $this->person();
        $renderer = $this->renderer();
        $renderer->header();
        $before = $this->armed();

        $renderer->standard_after_main_region_html();
        $this->assertSame($before, $this->armed(), 'Nothing stored: nothing armed.');

        $context = ['usermenu' => ['avatardata' => [['content' => '', 'classes' => 'current']], 'items' => []]];
        $renderer->render_from_template('theme_dimensions/look_mark', $context + ['url' => null, 'colour' => null]);
        $this->assertStringContainsString(self::LAYER, $renderer->standard_after_main_region_html());
        $this->assertSame($before + 1, $this->armed(), 'Control: armed with the print.');
    }

    /**
     * A masked identity value never reaches the page whole; an unmasked field beside it does.
     *
     * @return void
     */
    public function test_a_masked_identity_never_reaches_the_page_whole(): void {
        $this->resetAfterTest();
        $this->person(['firstname' => 'Ana', 'lastname' => 'Ribeiro', 'username' => '12345678909', 'email' => 'ana@example.com']);
        set_config(identity::SETTING_TITLE, 'username', identity::COMPONENT);
        set_config(identity::SETTING_LINES, 'email', identity::COMPONENT);

        [$header, $footer] = $this->halves();
        $this->assertStringContainsString('12345678909', $this->layer($footer), 'Control: without a pattern it is the title.');

        set_config(identity::SETTING_PATTERNS, 'username=***.###.###-**', identity::COMPONENT);
        [$header, $footer] = $this->halves();
        $layer = $this->layer($footer);
        $this->assertStringContainsString('<h2 class="dimensions-layer-title" id="', $layer);
        $this->assertMatchesRegularExpression('~-title">•••\.456\.789-••</h2>~', $layer);
        $this->assertMatchesRegularExpression(
            '~<p class="dimensions-layer-line">ana@example\.com</p>~',
            $layer,
            'The unmasked line.'
        );
        $this->assertStringNotContainsString('12345678909', $header . $footer);
        $this->assertStringNotContainsString('123.456', $header . $footer);
    }

    /**
     * A pattern's literal characters are escaped once in the layer, never twice.
     *
     * @return void
     */
    public function test_a_patterns_literals_are_escaped_once_in_the_layer(): void {
        $this->resetAfterTest();
        $this->person(['firstname' => 'Ana', 'lastname' => 'Ribeiro', 'idnumber' => 'ab']);
        set_config(identity::SETTING_TITLE, 'idnumber', identity::COMPONENT);
        set_config(identity::SETTING_PATTERNS, 'idnumber=<#&#>', identity::COMPONENT);

        [, $footer] = $this->halves();

        $this->assertMatchesRegularExpression('~-title">&lt;a&amp;b&gt;</h2>~', $this->layer($footer));
        $this->assertStringNotContainsString('&amp;amp;', $this->layer($footer));
    }

    /**
     * The template, from its own example, carries the dialog's roles and core's colour mode hooks.
     *
     * The main view is headed lists, never a menu; a sub-view of choices is a menu of menuitemradio links with the
     * one in force checked; every view but the main one is hidden.
     *
     * @return void
     */
    public function test_the_template_carries_the_roles_and_cores_hooks(): void {
        global $OUTPUT;
        $this->resetAfterTest();

        $html = $OUTPUT->render_from_template('theme_dimensions/profile_layer', $this->example_context('profile_layer'));

        $main = substr($html, 0, strpos($html, 'data-view="menu-1"'));
        $this->assertStringNotContainsString('role="menu"', $main, 'The main view is lists, not a menu.');
        $this->assertSame(3, substr_count($main, '<ul class="dimensions-layer-list" aria-labelledby="'));
        $this->assertMatchesRegularExpression(
            '~role="radiogroup" aria-labelledby="[^"]+-group-display" data-region="colourmode-menu"'
                . ' data-cookieattributes="; Max-Age=31536000; Path=/; SameSite=Lax"~',
            $html
        );
        $this->assertSame(3, substr_count($html, 'data-action="set-colourmode"'));
        $this->assertMatchesRegularExpression(
            '~class="dimensions-layer-seg-cell active" role="radio" aria-checked="true" data-action="set-colourmode"'
                . ' data-colourmode="auto" data-icon="fa-circle-half-stroke" data-togglelabel="Colour mode: System"~',
            $html
        );
        $this->assertSame(3, substr_count($html, 'data-view="') - 1, 'Three sub-views beside the main one.');
        $this->assertSame(3, preg_match_all('~<div class="dimensions-layer-view" id="[^"]+" data-view="[^"]+" hidden>~', $html));
        $this->assertSame(3, substr_count($html, 'role="menu"'));
        $this->assertMatchesRegularExpression(
            '~role="menuitemradio" href="https://example\.com/theme/dimensions/flavour\.php\?flavour=0" aria-checked="false"'
                . ' data-dimensions-flavour="0">~',
            $html,
            'The site default carries its look id 0.'
        );
        $this->assertMatchesRegularExpression(
            '~role="menuitemradio" href="https://example\.com/my/\?lang=en" aria-checked="true" lang="en">~',
            $html
        );
        $this->assertStringContainsString(
            '<a class="dimensions-layer-row" role="menuitem" href="https://example.com/catalogue">',
            $html
        );
        $this->assertStringContainsString(
            'href="https://example.com/user/edit.php?id=5&amp;returnto=profile"',
            $html,
            'An address is escaped once.'
        );
        $this->assertStringContainsString('data-region="dimensions-layer-loggedinas"', $html);
        $this->assertStringContainsString('Return to Admin User</a>', $html);
        $this->assertStringContainsString('class="dimensions-layer-row dimensions-layer-row--danger"', $html);
        $this->assertMatchesRegularExpression(
            '~<div class="dimensions-layer-scroll">\s*<div class="dimensions-layer-phone" data-region="dimensions-layer-phone"'
                . ' hidden>\s*<ul class="dimensions-layer-list" data-region="dimensions-layer-phone-list"></ul>~',
            $html,
            'The phone\'s group comes first, empty and hidden, for amd/src/phonemenu.js to fill.'
        );

        $context = $this->example_context('profile_layer');
        $context['editurl'] = false;
        $context['loggedinas'] = false;
        $alone = $OUTPUT->render_from_template('theme_dimensions/profile_layer', $context);
        $this->assertSame(1, substr_count($alone, 'class="btn btn-outline-secondary btn-sm"'), 'View profile alone.');
        $this->assertStringNotContainsString('dimensions-layer-loggedinas', $alone);
    }
}
