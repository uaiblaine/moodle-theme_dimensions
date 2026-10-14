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
 * Theme Dimensions - Tests for the invariants of the course dialogue's client module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of amd/src/categorycoursemodal.js by reading its source.
 *
 * Same shape and reasoning as {@see covervideo_source_test}: plugin CI runs no JavaScript unit test,
 * so what this module must keep doing is read out of its source, each assertion scoped to the
 * function it is about, and the build-currency assertion at the foot is what says the browser is
 * served this source. Behat is the gate that executes it (the client feature's dialogue scenario).
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class categorycoursemodal_source_test extends \basic_testcase {
    /**
     * Absolute path to the theme root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 3);
    }

    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = $this->plugin_root() . '/amd/src/categorycoursemodal.js';
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /**
     * The source the committed build was actually made from.
     *
     * @return string
     */
    private function built_from(): string {
        $path = $this->plugin_root() . '/amd/build/categorycoursemodal.min.js.map';
        $this->assertFileExists($path, 'The source map is missing; Moodle serves the build beside it.');
        $map = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');
        $this->assertArrayHasKey('sourcesContent', $map);
        return (string) ($map['sourcesContent'][0] ?? '');
    }

    /**
     * The body of one top-level arrow function or object literal of the module.
     *
     * @param string $name The name it is bound to.
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
     * The extractor really extracts one function and not the file around it.
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $click = $this->body('onClick');
        $this->assertStringContainsString('event.preventDefault();', $click);
        $this->assertStringNotContainsString('Modal.create', $click, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('Modal.create', $this->body('open'));
    }

    /**
     * A press on a card's summary keeps the disclosure shut and opens the dialogue, and only on a card that carries one.
     *
     * The summary toggles its details as the click's default action - from the keyboard as well - so preventing it
     * is the one line between the dialogue and the panel opening behind it. A card with no dialogue returns BEFORE
     * that line: its disclosure is the only way to its details.
     */
    public function test_a_press_on_the_summary_opens_the_dialogue_instead_of_the_disclosure(): void {
        $click = $this->body('onClick');
        $guard = strpos($click, 'if (!parts) {');
        $prevent = strpos($click, 'event.preventDefault();');
        $this->assertNotFalse($guard, 'A card with no dialogue is left to its disclosure.');
        $this->assertNotFalse($prevent, 'The press must not also toggle the disclosure.');
        $this->assertLessThan($prevent, $guard, 'And it is left alone before anything is prevented.');
        $this->assertStringContainsString('open(summary, parts)', $click);
        $this->assertStringContainsString("document.addEventListener('click', onClick);", $this->body('init'));
        $this->assertStringContainsString("summary: '[data-region=\"dim-card-details\"] > summary',", $this->source());
    }

    /**
     * The dialogue is core/modal: removed on close, the focus given back to the summary, the plugin's class on its root.
     *
     * The class is what every rule of the stylesheet is qualified by and the token root it reads; core/modal
     * appends the root to the body, outside every element of the listing that declares a token.
     */
    public function test_the_dialogue_is_core_modal_with_the_plugins_root_class_and_the_focus_returned(): void {
        $source = $this->source();
        $this->assertStringContainsString("import Modal from 'core/modal';", $source);
        $this->assertStringContainsString("const TEMPLATE = 'theme_dimensions/category_course_modal';", $source);
        $this->assertStringContainsString("const ROOT_CLASS = 'dim-cmodal';", $source);
        $open = $this->body('open');
        $this->assertStringContainsString('template: TEMPLATE,', $open);
        $this->assertStringContainsString('removeOnClose: true,', $open);
        $this->assertStringContainsString('returnElement: summary,', $open);
        $this->assertStringContainsString('root.classList.add(ROOT_CLASS);', $open);
        $this->assertStringContainsString('body.append(sheet);', $open);
        $this->assertStringContainsString('footer.append(actions);', $open);
    }

    /**
     * The dialogue is labelled by the title the reader sees, the frame's hidden placeholder removed.
     */
    public function test_the_dialogue_is_labelled_by_the_title_on_the_cover(): void {
        $open = $this->body('open');
        $this->assertStringContainsString("const labelledby = root.getAttribute('aria-labelledby');", $open);
        $this->assertStringContainsString('placeholder.remove();', $open);
        $this->assertStringContainsString('title.id = labelledby;', $open);
        $this->assertStringContainsString("title: '[data-region=\"title\"]',", $this->source(), 'Core\'s own title hook.');
    }

    /**
     * The footer's shadow follows the scroll of the sheet, and is set once the dialogue is on screen.
     */
    public function test_the_footer_is_raised_while_the_sheet_has_more_to_scroll(): void {
        $this->assertStringContainsString(
            'scroller.scrollTop + scroller.clientHeight < scroller.scrollHeight - SCROLL_SLACK',
            $this->body('raise')
        );
        $this->assertStringContainsString('footer.classList.toggle(RAISED_CLASS, more);', $this->body('raise'));
        $open = $this->body('open');
        $this->assertStringContainsString("body.addEventListener('scroll', () => raise(body, footer), {passive: true});", $open);
        $this->assertLessThan(strrpos($open, 'raise(body, footer);'), strpos($open, 'await modal.show();'));
    }

    /**
     * The module asks the server for nothing and reads data attributes only.
     *
     * It runs on the anonymous shell, where the showcase's web services refuse every reader; and a class selector
     * is a hook a stylesheet rename can silently break.
     */
    public function test_it_calls_no_service_and_addresses_hooks_only(): void {
        $source = $this->source();
        foreach (['core/ajax', 'fetch(', 'XMLHttpRequest'] as $call) {
            $this->assertStringNotContainsString($call, $source, 'The dialogue asks the server for nothing: ' . $call);
        }
        $selectors = $this->body('SELECTORS');
        preg_match_all("~: '([^']+)',~", $selectors, $matches);
        $this->assertCount(6, $matches[1]);
        foreach ($matches[1] as $selector) {
            $this->assertMatchesRegularExpression(
                '~^(template)?\[data-region="[a-z-]+"\]~',
                $selector,
                'A class selector here is a hook a stylesheet rename can silently break: ' . $selector
            );
        }
    }

    /**
     * Both shells arm the module, for everybody: the logged-in one outside the listing client's guard.
     *
     * The indentation is what makes the assertion bite: at eight spaces the call is a statement of the method, and
     * inside the listing client's `if` it would be at twelve - which would leave the guest's dialogue unarmed.
     */
    public function test_both_shells_arm_the_module(): void {
        $renderer = (string) file_get_contents($this->plugin_root() . '/classes/output/core/course_renderer.php');
        $this->assertMatchesRegularExpression(
            "~^        \\\$this->page->requires->js_call_amd\\('theme_dimensions/categorycoursemodal', 'init'\\);$~m",
            $renderer,
            'The logged-in shell arms it outside the listing client\'s guard.'
        );
        $request = (string) file_get_contents($this->plugin_root() . '/classes/local/category/request.php');
        $this->assertMatchesRegularExpression(
            "~^        \\\$PAGE->requires->js_call_amd\\('theme_dimensions/categorycoursemodal', 'init'\\);$~m",
            $request,
            'A visitor\'s card opens the same dialogue.'
        );
    }

    /**
     * The committed build was made from the source that is committed beside it - exactly.
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $this->assertSame(
            $this->source(),
            $this->built_from(),
            'amd/build is not this source: rebuild it with grunt amd and commit both files.'
        );
        $build = (string) file_get_contents($this->plugin_root() . '/amd/build/categorycoursemodal.min.js');
        $this->assertStringContainsString('dim-card-modal', $build);
        $this->assertStringContainsString('theme_dimensions/category_course_modal', $build);
    }
}
