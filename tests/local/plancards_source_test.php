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
 * Theme Dimensions - Tests for the learning plans page's module, read from its source
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins amd/src/plancards.js by reading its source, as helpmodal_source_test does: the Pending token around the
 * refill, the ticket that lets only the latest answer write, the cards written as the server's markup and the
 * tooltip as text. The behaviour itself is the Behat scenario's (theme_dimensions_competency.feature).
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class plancards_source_test extends \basic_testcase {
    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = dirname(__DIR__, 2) . '/amd/src/plancards.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * The refill holds a Pending token from before the request to after the answer is written, released in a finally.
     *
     * @return void
     */
    public function test_the_refill_is_pending_until_written(): void {
        $source = $this->source();
        $pending = strpos($source, "const pending = new Pending('theme_dimensions/plancards:refill');");
        $request = strpos($source, 'await Ajax.call([{methodname: CARDS,');
        $this->assertNotFalse($pending);
        $this->assertNotFalse($request);
        $this->assertGreaterThan($pending, $request, 'The token is taken before the request.');
        $this->assertMatchesRegularExpression(
            '~writeCards\(current\);.*?\} finally \{\s*pending\.resolve\(\);~s',
            $source
        );
        $this->assertStringContainsString("const CARDS = 'theme_dimensions_get_plan_cards';", $source);
    }

    /**
     * Only the latest request writes: the ticket is compared after the await, before anything is written.
     *
     * @return void
     */
    public function test_only_the_latest_answer_writes(): void {
        $source = $this->source();
        $this->assertStringContainsString('const ticket = ++state.ticket;', $source);
        $await = strpos($source, 'await Ajax.call(');
        $check = strpos($source, "if (ticket !== state.ticket) {\n            return;\n        }");
        $write = strpos($source, 'answer.cards.forEach(');
        $this->assertNotFalse($check, 'No ticket check.');
        $this->assertGreaterThan($await, $check, 'The ticket is compared after the await.');
        $this->assertGreaterThan($check, $write, 'The ticket is compared before anything is written.');
    }

    /**
     * A failure keeps the cards in place and opens no dialogue; the first paint is remembered, a re-render refilled.
     *
     * @return void
     */
    public function test_a_failure_is_quiet_and_the_two_runs_differ(): void {
        $source = $this->source();
        $this->assertStringNotContainsString('core/notification', $source);
        $this->assertStringContainsString('window.console.error(error);', $source);
        $this->assertMatchesRegularExpression(
            "~if \(root\.hasAttribute\('data-dim-layout'\)\) \{\s*remember\(root\);\s*\} else \{\s*" .
            "writeClasses\(root, '', state\.itemclasses\);\s*writeCards\(root\);\s*refill\(root\);~",
            $source
        );
    }

    /**
     * The plan's name reaches the tooltip as text, never as markup; the module uses no jQuery.
     *
     * @return void
     */
    public function test_the_tooltip_is_text(): void {
        $source = $this->source();
        $this->assertStringContainsString("trigger.setAttribute('data-dim-comp-tip', name.textContent.trim());", $source);
        $this->assertSame(1, substr_count($source, 'innerHTML ='), 'Only the server\'s card markup is written as HTML.');
        $this->assertStringContainsString('extra.innerHTML = html;', $source);
        $this->assertStringNotContainsString('jquery', strtolower($source));
    }

    /**
     * The committed build was made from the committed source: Moodle serves amd/build, never amd/src.
     *
     * @return void
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root . '/amd/build/plancards.min.js', 'The build is missing.');
        $map = json_decode((string) file_get_contents($root . '/amd/build/plancards.min.js.map'), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');

        $this->assertSame($this->source(), (string) ($map['sourcesContent'][0] ?? ''));
    }
}
