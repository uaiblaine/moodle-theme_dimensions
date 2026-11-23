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
 * Theme Dimensions - Tests for the help dialogue's module, read from its source
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins amd/src/helpmodal.js by reading its source, as keycolour_source_test does: the guards that keep core's popover
 * for every case the dialogue must not take, and the order that keeps a failure from losing the popover. The behaviour
 * itself is the Behat feature's (theme_dimensions_helpmodal.feature).
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class helpmodal_source_test extends \basic_testcase {
    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = dirname(__DIR__, 2) . '/amd/src/helpmodal.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * A help icon inside a modal is never the module's: no dialogue over a dialogue, the popover stays.
     *
     * @return void
     */
    public function test_an_icon_inside_a_modal_keeps_the_popover(): void {
        $source = $this->source();
        $this->assertStringContainsString("MODAL: '.modal',", $source);
        $this->assertStringContainsString(
            'return trigger && !trigger.closest(SELECTORS.MODAL) ? trigger : null;',
            $source
        );
        $this->assertSame(
            3,
            substr_count($source, 'triggerOf(e.target)'),
            'Every entry point (the start, the insertion and the activation) asks through triggerOf().'
        );
    }

    /**
     * Only a text that overflows the body's cap is swapped, measured where the stylesheet caps it.
     *
     * @return void
     */
    public function test_only_an_overflowing_text_is_swapped(): void {
        $source = $this->source();
        $this->assertStringContainsString(
            'const overflows = (body) => body.scrollHeight > body.clientHeight + 1;',
            $source
        );
        $this->assertStringContainsString(
            "if (!parts || !parts.tip.classList.contains('show') || !overflows(parts.body)) {",
            $source
        );
        $this->assertStringContainsString('if (parts && overflows(parts.body)) {', $source);
    }

    /**
     * Focus alone never opens the dialogue: the tip is hidden only under a press or Enter, and no focus is listened for.
     *
     * @return void
     */
    public function test_focus_alone_keeps_the_popover(): void {
        $source = $this->source();
        $this->assertStringContainsString('if (!trigger || trigger !== activating) {', $source);
        $this->assertStringNotContainsString("'focusin'", $source);
        $this->assertStringNotContainsString("'focus'", $source);
    }

    /**
     * The popover is hidden only once the dialogue exists, and a failure shows it again.
     *
     * @return void
     */
    public function test_the_popover_is_given_up_only_for_a_built_dialogue(): void {
        $source = $this->source();
        $create = strpos($source, 'await Modal.create(');
        $hide = strpos($source, 'popover.hide();');
        $this->assertNotFalse($create);
        $this->assertNotFalse($hide);
        $this->assertGreaterThan($create, $hide, 'The popover is hidden after the dialogue is built.');
        $this->assertMatchesRegularExpression('~\} catch \(error\) \{[^}]*reveal\(tip\);~s', $source);
        $this->assertStringContainsString("if (!tip.classList.contains('show') || !trigger.isConnected) {", $source);
    }

    /**
     * The dialogue's title is the icon's name as text, and the body is the HTML the popover already holds.
     *
     * @return void
     */
    public function test_the_title_is_escaped_and_the_body_is_never_fetched(): void {
        $source = $this->source();
        $this->assertStringContainsString('title: escaped(title),', $source);
        $this->assertStringContainsString('holder.textContent = text;', $source);
        $this->assertStringContainsString(
            "const name = (icon?.getAttribute('alt') || icon?.getAttribute('title') || '').trim();",
            $source
        );
        $this->assertStringContainsString("return name || getString('help', 'core');", $source);
        $this->assertStringContainsString('body: body.innerHTML,', $source);
        $this->assertStringContainsString('returnElement: trigger,', $source);
        $this->assertStringContainsString('removeOnClose: true,', $source);
        $this->assertStringNotContainsString('core/ajax', $source);
        $this->assertStringNotContainsString('jquery', strtolower($source));
    }

    /**
     * The focus the dialogue gives back would show the popover again: that one show is cancelled.
     *
     * @return void
     */
    public function test_the_focus_given_back_shows_no_popover(): void {
        $source = $this->source();
        $this->assertMatchesRegularExpression(
            '~if \(returning && e\.target === returning\) \{\s*returning = null;\s*e\.preventDefault\(\);~',
            $source
        );
        $this->assertStringContainsString("document.addEventListener('show.bs.popover', showing);", $source);
    }

    /**
     * The swap listens on the window, after core's listeners on the document have shown the popover; wired once.
     *
     * @return void
     */
    public function test_the_swap_runs_after_cores_listeners_and_is_wired_once(): void {
        $source = $this->source();
        $this->assertStringContainsString("window.addEventListener('click', activate);", $source);
        $this->assertStringContainsString("window.addEventListener('keydown', activate);", $source);
        $this->assertStringNotContainsString("document.addEventListener('click'", $source);
        $this->assertStringContainsString("document.addEventListener('inserted.bs.popover', inserted);", $source);
        $this->assertStringContainsString('if (wired) {', $source);
        $this->assertStringNotContainsString('querySelectorAll', $source, 'The module scans no page.');
    }

    /**
     * The committed build was made from the committed source: Moodle serves amd/build, never amd/src.
     *
     * @return void
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root . '/amd/build/helpmodal.min.js', 'The build is missing.');
        $map = json_decode((string) file_get_contents($root . '/amd/build/helpmodal.min.js.map'), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');

        $this->assertSame($this->source(), (string) ($map['sourcesContent'][0] ?? ''));
    }
}
