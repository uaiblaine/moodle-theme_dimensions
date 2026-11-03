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
 * Theme Dimensions - Tests for the invariants of the event type colour swatch module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins amd/src/keycolour.js by reading its source, as drawer_source_test does: the module only syncs a control the
 * server drew, in both directions, from listeners delegated once from the document.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class keycolour_source_test extends \basic_testcase {
    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = dirname(__DIR__, 2) . '/amd/src/keycolour.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * Picking writes the hex into the field and dispatches input and change; typing redraws the preview.
     *
     * @return void
     */
    public function test_the_sync_runs_both_ways(): void {
        $source = $this->source();
        $this->assertStringContainsString('field.value = target.value;', $source);
        $this->assertStringContainsString("field.dispatchEvent(new Event('input', {bubbles: true}));", $source);
        $this->assertStringContainsString("field.dispatchEvent(new Event('change', {bubbles: true}));", $source);
        $this->assertStringContainsString("preview.classList.toggle(EMPTY, !colour);", $source);
        $this->assertStringContainsString('native.value = full;', $source);
        $this->assertStringNotContainsString('jquery', strtolower($source));
    }

    /**
     * The module wires the document once and draws nothing of its own.
     *
     * @return void
     */
    public function test_it_wires_once_and_creates_no_element(): void {
        $source = $this->source();
        $this->assertSame(1, substr_count($source, 'document.addEventListener('));
        $this->assertStringContainsString('if (wired) {', $source);
        $this->assertStringNotContainsString('createElement', $source, 'The server draws the control.');
    }

    /**
     * The setting asks for the module once per page, and the template gives the native input no name.
     *
     * @return void
     */
    public function test_the_setting_wires_it_and_the_native_input_is_unnamed(): void {
        $root = dirname(__DIR__, 2);
        $php = (string) file_get_contents($root . '/classes/local/calendar/keycolour_setting.php');
        $this->assertStringContainsString("js_call_amd('theme_dimensions/keycolour', 'init')", $php);
        $this->assertStringContainsString('if (!self::$wired) {', $php);
        $template = (string) file_get_contents($root . '/templates/admin_setting_keycolour.mustache');
        $this->assertSame(1, preg_match('~<input type="color"[^>]*>~', $template, $native));
        $this->assertStringNotContainsString('name=', $native[0]);
    }

    /**
     * The committed build was made from the committed source: Moodle serves amd/build, never amd/src.
     *
     * @return void
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root . '/amd/build/keycolour.min.js', 'The build is missing.');
        $map = json_decode((string) file_get_contents($root . '/amd/build/keycolour.min.js.map'), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');

        $this->assertSame($this->source(), (string) ($map['sourcesContent'][0] ?? ''));
    }
}
