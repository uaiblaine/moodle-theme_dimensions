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
 * Theme Dimensions - Source checks of the evidence page's module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * What PHPUnit can hold of amd/src/competencyevidence.js: the latest request wins, every wait is pending, core's
 * handlers stay the only ones that act on an evidence, and values reach the page as text.
 *
 * Behat (theme_dimensions_competency_evidence.feature) is the gate that runs the module against core's.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class competencyevidence_source_test extends \basic_testcase {
    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = dirname(__DIR__, 2) . '/amd/src/competencyevidence.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * The request holds a Pending token from before it starts to after the answer is written, released in a finally.
     *
     * @return void
     */
    public function test_the_feed_is_pending_until_written(): void {
        $source = $this->source();
        $pending = strpos($source, "const pending = new Pending('theme_dimensions/competencyevidence:feed');");
        $request = strpos($source, 'await Ajax.call([{methodname: FEED, args}])[0];');
        $this->assertNotFalse($pending);
        $this->assertNotFalse($request);
        $this->assertGreaterThan($pending, $request, 'The token is taken before the request.');
        $this->assertMatchesRegularExpression('~await paint\(current\);.*?\} finally \{\s*pending\.resolve\(\);~s', $source);
        $this->assertStringContainsString("const FEED = 'theme_dimensions_get_user_evidence_feed';", $source);
    }

    /**
     * Only the latest request writes: the ticket is compared after the service's answer and again after the template
     * and the folded lines, before anything is kept.
     *
     * @return void
     */
    public function test_only_the_latest_answer_writes(): void {
        $source = $this->source();
        $this->assertStringContainsString('const ticket = ++state.ticket;', $source);
        $check = "if (ticket !== state.ticket) {\n            return;\n        }";
        $await = strpos($source, 'await Ajax.call(');
        $first = strpos($source, $check);
        $render = strpos($source, 'await Templates.renderForPromise(RECORDS');
        $second = strpos($source, $check, $first + 1);
        $write = strpos($source, 'state.nodes.set(');
        $this->assertNotFalse($first, 'No ticket check.');
        $this->assertNotFalse($second, 'No second ticket check.');
        $this->assertGreaterThan($await, $first, 'The ticket is compared after the request.');
        $this->assertGreaterThan($render, $second, 'The ticket is compared again after the render.');
        $this->assertGreaterThan($second, $write, 'The ticket is compared before anything is kept.');
    }

    /**
     * The first paint resets the kept records; a re-render keeps them, paints them back and asks again.
     *
     * @return void
     */
    public function test_the_two_runs_differ(): void {
        $source = $this->source();
        $this->assertMatchesRegularExpression(
            "~if \(root\.hasAttribute\('data-dim-firstpaint'\)\) \{\s*" .
            "state\.platform = root\.getAttribute\('data-dim-platform'\) === '1';\s*state\.nodes\.clear\(\);~",
            $source
        );
        $this->assertMatchesRegularExpression('~paint\(root\);\s*fetchPage\(true\);\s*\};\s*$~', $source);
    }

    /**
     * A detail's button presses core's own menu entry of the evidence it shows, one of core's three actions, and
     * nothing else: no handler of the theme's acts on an evidence.
     *
     * @return void
     */
    public function test_the_detail_presses_cores_entry_only(): void {
        $source = $this->source();
        $this->assertStringContainsString(
            "const PROXIES = ['link-competency', 'send-competencies-review', 'user-evidence-delete'];",
            $source
        );
        $this->assertStringContainsString("if (!PROXIES.includes(action) || !state.selected.startsWith('p')) {", $source);
        $this->assertStringContainsString('entry.click();', $source);
        $this->assertStringNotContainsString('core_competency_delete_user_evidence', $source);
        $this->assertStringNotContainsString('tool_lp/user_evidence_actions', $source);
    }

    /**
     * Values reach the page as text and attributes are read as text; the module uses no jQuery and opens no dialogue of
     * its own on a failure.
     *
     * @return void
     */
    public function test_values_are_text(): void {
        $source = $this->source();
        $this->assertStringNotContainsString('jquery', strtolower($source));
        $this->assertStringNotContainsString('.dataset.dimKey', $source);
        $this->assertStringNotContainsString('core/notification', $source);
        // The one markup sink is the records template rendered here from the service's plain values.
        $this->assertSame(1, substr_count($source, '.innerHTML ='));
        $this->assertStringContainsString('holder.innerHTML = html;', $source);
        $this->assertStringContainsString('window.console.error(error);', $source);
    }

    /**
     * The committed build was made from the committed source: Moodle serves amd/build, never amd/src.
     *
     * @return void
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root . '/amd/build/competencyevidence.min.js', 'The build is missing.');
        $map = json_decode((string) file_get_contents($root . '/amd/build/competencyevidence.min.js.map'), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');

        $this->assertSame($this->source(), (string) ($map['sourcesContent'][0] ?? ''));
    }
}
