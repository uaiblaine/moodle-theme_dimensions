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
 * Theme Dimensions - Tests for the invariants of the mobile drawer's client module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of amd/src/drawer.js by reading its source, as the showcase's source tests do.
 *
 * Plugin CI runs no JavaScript unit tests, so the properties whose loss costs a reader rather than a
 * screenshot are pinned by spelling, each assertion scoped to the one function it is about: a hidden
 * panel is inert, the focus follows the panels both ways, Escape goes back one level, and the summary is
 * asked for when the primary drawer is shown and never before. The Behat feature
 * theme_dimensions_drawer.feature is what runs the module. The build-currency assertion at the foot is
 * the only one about what a browser is served.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class drawer_source_test extends \basic_testcase {
    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = dirname(__DIR__, 2) . '/amd/src/drawer.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * The body of one top-level arrow function of the module.
     *
     * Every function of the file is a top-level `const <name> = (…) => {` closed by a `};` in the first
     * column, which is what makes the extraction unambiguous.
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
        $show = $this->body('show');
        $this->assertStringContainsString('toggleAttribute', $show);
        $this->assertStringNotContainsString('openers.push', $show, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('openers.push', $this->body('enter'));
    }

    /**
     * Every panel but the current one is inert, so neither the keyboard nor a screen reader reaches it.
     *
     * Changes that must make it fail: dropping the inert toggle, or toggling it on the current panel.
     *
     * @return void
     */
    public function test_every_hidden_panel_is_inert(): void {
        $show = $this->body('show');

        $this->assertStringContainsString('const hidden = candidate !== panel;', $show);
        $this->assertStringContainsString("candidate.toggleAttribute('inert', hidden);", $show);
        $this->assertStringContainsString("candidate.dataset.state = 'current';", $show);
    }

    /**
     * The focus goes to the new panel's back control, and back to the row that opened it on the way out.
     *
     * The way in focuses without scrolling: the panel is still off to the right, and a scrolling focus moves the
     * viewport, which leaves the panel drawn to the left of it (theme_dimensions_drawer.feature runs that). The
     * way back scrolls, so a row far down the parent panel is brought into view; the parent comes in from the
     * left, where the viewport cannot scroll.
     *
     * @return void
     */
    public function test_the_focus_follows_the_panels_both_ways(): void {
        $enter = $this->body('enter');
        $this->assertStringContainsString('openers.push(opener);', $enter);
        $this->assertStringContainsString('panel.querySelector(SELECTORS.BACK)?.focus({preventScroll: true});', $enter);

        $leave = $this->body('leave');
        $this->assertStringContainsString('const opener = openers.pop();', $leave);
        $this->assertStringContainsString('opener.focus();', $leave);
    }

    /**
     * Escape inside a sub-panel goes back one level, and is not handed on; at the root it is left alone.
     *
     * @return void
     */
    public function test_escape_goes_back_one_level(): void {
        $key = $this->body('onKey');

        $this->assertStringContainsString("if (event.key === 'Escape') {", $key);
        $this->assertStringContainsString('if (panel && panel.dataset.parent) {', $key);
        $this->assertStringContainsString('event.stopPropagation();', $key);
        $this->assertStringContainsString('leave(root);', $key);
    }

    /**
     * The summary is asked for when Boost's primary drawer is shown, once, and never on page load.
     *
     * Changes that must make it fail: calling load() from init(), answering another drawer's event, or
     * listening on after the first answer.
     *
     * @return void
     */
    public function test_the_summary_loads_when_the_primary_drawer_is_shown_and_only_then(): void {
        $arm = $this->body('armSummary');

        $this->assertStringContainsString('document.addEventListener(Drawers.eventTypes.drawerShown, onShown);', $arm);
        $this->assertStringContainsString('if (!event.target || event.target.id !== DRAWER) {', $arm);
        $this->assertStringContainsString('document.removeEventListener(Drawers.eventTypes.drawerShown, onShown);', $arm);
        $this->assertSame(1, substr_count($this->source(), 'load(root, card);'), 'One call of load(), the listener\'s.');
        $this->assertStringContainsString('load(root, card);', $arm);
        $this->assertStringNotContainsString('load(', $this->body('init'));
        $this->assertStringContainsString("const DRAWER = 'theme_boost-drawers-primary';", $this->source());
    }

    /**
     * A failed summary takes the card away; the card is painted as text, never as markup.
     *
     * @return void
     */
    public function test_a_failure_removes_the_card_and_nothing_is_painted_as_markup(): void {
        $load = $this->body('load');
        $this->assertMatchesRegularExpression('~\} catch \(error\) \{\s*card\.remove\(\);~', $load);
        $this->assertStringContainsString('pending.resolve();', $load);

        $paint = $this->body('paintResume');
        $this->assertStringContainsString('card.remove();', $paint, 'Nothing to continue: no card.');
        $this->assertStringContainsString('.textContent = resume.name;', $paint);
        $this->assertStringNotContainsString('innerHTML', $this->source());
    }

    /**
     * The arrow keys move the appearance switch by pressing core's option, which is what applies the mode.
     *
     * @return void
     */
    public function test_the_arrows_press_cores_option(): void {
        $step = $this->body('stepMode');

        $this->assertStringContainsString('options[next].focus();', $step);
        $this->assertStringContainsString('options[next].click();', $step);
    }

    /**
     * The committed build was made from the committed source: Moodle serves amd/build, never amd/src.
     *
     * @return void
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root . '/amd/build/drawer.min.js', 'The build is missing.');
        $map = json_decode((string) file_get_contents($root . '/amd/build/drawer.min.js.map'), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');

        $this->assertSame($this->source(), (string) ($map['sourcesContent'][0] ?? ''));
    }
}
