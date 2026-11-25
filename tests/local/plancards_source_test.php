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
     * The star's change event is the one block_dimensions' star module dispatches, read from the block's own source.
     *
     * @return void
     */
    public function test_the_event_is_the_blocks(): void {
        $source = $this->source();
        $this->assertSame(
            1,
            preg_match("~^const FAVCHANGED = '([^']+)';$~m", $source, $ours),
            'The module names no event.'
        );
        $block = \core_component::get_plugin_directory('block', 'dimensions');
        $star = $block === null ? '' : $block . '/amd/src/favourite_star.js';
        if ($star === '' || !is_readable($star)) {
            $this->markTestSkipped('block_dimensions ships no favourite_star module here.');
        }
        $this->assertSame(
            1,
            preg_match("~export const EVENT = '([^']+)';~", (string) file_get_contents($star), $theirs),
            'The block\'s module exports no EVENT.'
        );
        $this->assertSame($theirs[1], $ours[1]);
    }

    /**
     * The event is listened for once, on the document, which core's re-render never replaces; it updates the meta
     * and paints again.
     *
     * @return void
     */
    public function test_the_event_is_heard_on_the_document_once(): void {
        $source = $this->source();
        $this->assertSame(1, substr_count($source, 'addEventListener(FAVCHANGED'), 'One listener only.');
        $this->assertMatchesRegularExpression(
            '~if \(state\.docwired\) \{\s*return;\s*\}\s*state\.docwired = true;\s*document\.addEventListener\(FAVCHANGED,~',
            $source
        );
        $this->assertMatchesRegularExpression(
            '~meta\.favourite = Boolean\(detail\.isfavourite\);.*?paint\(current\);~s',
            $source
        );
    }

    /**
     * The star module is the one the server names, loaded by that name; the theme calls no toggle service.
     *
     * @return void
     */
    public function test_the_star_module_is_named_by_the_server(): void {
        $source = $this->source();
        $this->assertStringContainsString("state.starmodule = root.getAttribute('data-dim-starmodule') || '';", $source);
        $this->assertStringContainsString('window.require([state.starmodule], resolve, reject)', $source);
        $this->assertStringContainsString('Star.init();', $source);
        $this->assertStringNotContainsString('toggle_favourite', $source);
        $this->assertStringNotContainsString('block_dimensions/favourite_star', $source, 'The name is the server\'s.');
    }

    /**
     * The two predicates of the paint: favourites only keeps a favourite, and every tag group in force must hold the
     * plan's value (OR inside a group, AND across groups).
     *
     * @return void
     */
    public function test_the_paint_applies_the_star_and_the_tags(): void {
        $source = $this->source();
        $this->assertStringContainsString('const faved = !state.favonly || meta.favourite === true;', $source);
        $this->assertStringContainsString(
            'const tagged = [...state.picked].every(([key, values]) => values.has(meta.tags.get(key)));',
            $source
        );
        $this->assertStringContainsString('node.hidden = !(inscope && matches && faved && tagged);', $source);
    }

    /**
     * The sort is applied by every paint, so a re-render, which paints again from the state, keeps it; each render
     * stamps core's order first, the ties' and "Name"'s order.
     *
     * @return void
     */
    public function test_the_sort_is_applied_after_each_render(): void {
        $source = $this->source();
        $this->assertMatchesRegularExpression(
            '~node\.hidden = !\(inscope && matches && faved && tagged\);.*?\}\);\s*reorder\(root\);~s',
            $source
        );
        $this->assertMatchesRegularExpression(
            '~nodes\.forEach\(\(node, index\) => \{\s*node\.dataset\.dimRank = String\(index\);\s*\}\);' .
            '.*?choose\(root, state\.scope, false\);~s',
            $source
        );
        $this->assertStringContainsString("sort: SORTS[0],", $source);
    }

    /**
     * Attributes are read with getAttribute and the tag filters parsed in a try, never through jQuery's data(), and
     * every text the module writes goes in as text.
     *
     * @return void
     */
    public function test_attributes_are_read_as_text(): void {
        $source = $this->source();
        $this->assertStringContainsString("readFilters(root.getAttribute('data-dim-tagfilters'))", $source);
        $this->assertMatchesRegularExpression('~try \{\s*list = JSON\.parse\(source \|\| \'\[\]\'\);~', $source);
        $this->assertStringNotContainsString('.data(', $source);
        $this->assertStringNotContainsString('insertAdjacentHTML', $source);
        $this->assertStringNotContainsString('outerHTML =', $source);
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
