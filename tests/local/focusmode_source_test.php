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
 * Theme Dimensions - Tests for the invariants of focus mode's client module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of amd/src/focusmode.js by reading its source, as readingbar_source_test does.
 *
 * Plugin CI runs no JavaScript unit tests, and Behat sees a switch only as the end state, so the
 * properties whose loss costs a reader are pinned by spelling, each assertion scoped to the one function
 * it is about: the switch is the body class and nothing is fetched to make it, the header actions are moved
 * and never copied, the region main settings menu stays in the page header, the menus close first, the
 * media hear the reflow, the focus follows the pressed control and the new mode is said, the choice is
 * recorded under core/pending and undone when the server refuses, a page the back-forward cache restores
 * is brought to the tab's last choice, and the forms are intercepted only on their own submit buttons.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class focusmode_source_test extends \basic_testcase {
    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = dirname(__DIR__, 2) . '/amd/src/focusmode.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * The body of one top-level arrow function of the module.
     *
     * Every function of the file is a top-level `const <name> = (…) => {` closed by a `};` in the first column.
     *
     * @param string $name The function's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->source(), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in the module.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");

        return $matches[1];
    }

    /**
     * The extractor returns one function and not the file around it.
     *
     * @return void
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $paint = $this->body('paint');
        $this->assertStringContainsString('moveHeaderActions(active);', $paint);
        $this->assertStringNotContainsString('appendChild', $paint, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('appendChild', $this->body('moveHeaderActions'));
    }

    /**
     * A switch paints the body class, closes the menus first, moves the actions and tells the media.
     *
     * @return void
     */
    public function test_a_switch_is_the_body_class_after_the_menus_close(): void {
        $paint = $this->body('paint');
        $this->assertStringContainsString("document.body.classList.toggle(FOCUSCLASS, active);", $paint);
        $this->assertLessThan(
            strpos($paint, 'FOCUSCLASS'),
            strpos($paint, 'closeMenus();'),
            'The menus close before the chrome goes.'
        );
        $this->assertStringContainsString('announceResize();', $paint);
        $this->assertStringContainsString("window.dispatchEvent(new Event('resize'));", $this->body('announceResize'));
        $this->assertSame("const FOCUSCLASS = 'dimensions-focusmode';", $this->line('const FOCUSCLASS'));
    }

    /**
     * The header actions are moved between the two places, never cloned, and the settings menu stays.
     *
     * @return void
     */
    public function test_the_header_actions_are_moved_and_the_settings_menu_stays(): void {
        $move = $this->body('moveHeaderActions');
        $this->assertStringContainsString('if (action.querySelector(SELECTORS.settingsmenu)) {', $move);
        $this->assertStringContainsString('bar.appendChild(action);', $move);
        $this->assertStringContainsString('header.insertBefore(action, anchor);', $move);
        $this->assertStringNotContainsString('cloneNode', $this->source());
    }

    /**
     * The choice is recorded under core/pending, and the page is put back when the server refuses.
     *
     * @return void
     */
    public function test_the_choice_is_recorded_under_pending_and_undone_on_a_refusal(): void {
        $toggle = $this->body('toggle');
        $this->assertStringContainsString("new Pending('theme_dimensions/focusmode:toggle')", $toggle);
        $this->assertStringContainsString("methodname: 'theme_dimensions_set_focus_mode'", $toggle);
        $this->assertStringContainsString('paint(!active);', $toggle);
        $this->assertStringContainsString('pending.resolve();', $toggle);
        $this->assertLessThan(strpos($toggle, 'Ajax.call'), strpos($toggle, 'paint(active);'), 'The page switches first.');
    }

    /**
     * The focus goes to the counterpart control, and the new mode is said in the live region.
     *
     * @return void
     */
    public function test_the_focus_follows_and_the_mode_is_said(): void {
        $settle = $this->body('settleFocus');
        $this->assertStringContainsString('document.querySelector(active ? SELECTORS.barexit : SELECTORS.enter)', $settle);
        $this->assertStringContainsString('target.focus();', $settle);
        $this->assertStringContainsString("'focusmode_status_on'", $settle);
        $this->assertStringContainsString('status.textContent = text;', $settle);
    }

    /**
     * Only the two submit buttons of the theme's forms are intercepted.
     *
     * @return void
     */
    public function test_only_the_two_forms_are_intercepted(): void {
        $submit = $this->body('onSubmit');
        $this->assertStringContainsString('submitter.matches(SELECTORS.enter)', $submit);
        $this->assertStringContainsString('submitter.matches(SELECTORS.leave)', $submit);
        $this->assertSame(2, substr_count($submit, 'event.preventDefault();'));
        $this->assertStringContainsString("document.addEventListener('submit', onSubmit);", $this->body('init'));
    }

    /**
     * A page the back-forward cache restores is brought to the tab's last choice, and only then.
     *
     * @return void
     */
    public function test_a_restored_page_follows_the_last_choice(): void {
        $show = $this->body('onPageShow');
        $this->assertStringContainsString('if (!event.persisted) {', $show);
        $this->assertStringContainsString('paint(chosen);', $show);
        $this->assertStringNotContainsString('Ajax', $show, 'A restored page records nothing.');
        $this->assertStringContainsString("window.addEventListener('pageshow', onPageShow);", $this->body('init'));
    }

    /**
     * The two other modules that look for the navbar never find focus mode's bar, printed before it.
     *
     * @return void
     */
    public function test_the_navbar_modules_skip_the_focus_bar(): void {
        foreach (['readingbar', 'compactnav'] as $module) {
            $source = (string) file_get_contents(dirname(__DIR__, 2) . '/amd/src/' . $module . '.js');
            $this->assertStringContainsString(
                "const NAVBAR = 'nav.navbar.fixed-top:not(.boost-union-menubar):not(.dimensions-focusbar)';",
                $source,
                $module
            );
        }
    }

    /**
     * The committed build was made from the committed source: Moodle serves amd/build, never amd/src.
     *
     * @return void
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root . '/amd/build/focusmode.min.js', 'The build is missing.');
        $map = json_decode((string) file_get_contents($root . '/amd/build/focusmode.min.js.map'), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');

        $this->assertSame($this->source(), (string) ($map['sourcesContent'][0] ?? ''));
    }

    /**
     * The one line of the source that starts with a given text.
     *
     * @param string $start The beginning of the line.
     * @return string The whole line.
     */
    private function line(string $start): string {
        foreach (explode("\n", $this->source()) as $line) {
            if (str_starts_with($line, $start)) {
                return $line;
            }
        }
        $this->fail("No line starts with {$start}.");
    }
}
