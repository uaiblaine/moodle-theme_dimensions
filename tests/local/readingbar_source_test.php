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
 * Theme Dimensions - Tests for the invariants of the reading bar's client module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of amd/src/readingbar.js by reading its source, as drawer_source_test does for the drawer.
 *
 * Plugin CI runs no JavaScript unit tests, and the Behat site switches the scroll off on purpose, so the
 * properties whose loss costs a reader are pinned by spelling, each assertion scoped to the one function it is
 * about: the listener is passive and only books a frame, the decision reads before it writes, nothing changes
 * while the bar is busy, the thresholds go both ways, the Behat site never moves the bar, the motion is the
 * stylesheet's, and the course index control keeps core's toggler attribute only while it can be seen.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class readingbar_source_test extends \basic_testcase {
    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = dirname(__DIR__, 2) . '/amd/src/readingbar.js';
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
        $scroll = $this->body('onScroll');
        $this->assertStringContainsString('requestAnimationFrame', $scroll);
        $this->assertStringNotContainsString('busy()', $scroll, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('busy()', $this->body('decide'));
    }

    /**
     * The scroll and resize listeners are passive, and they only book one animation frame.
     *
     * @return void
     */
    public function test_the_listener_is_passive_and_its_work_runs_in_an_animation_frame(): void {
        $init = $this->body('init');
        $this->assertStringContainsString("window.addEventListener('scroll', onScroll, {passive: true});", $init);
        $this->assertStringContainsString("window.addEventListener('resize', onScroll, {passive: true});", $init);
        $this->assertSame(1, substr_count($this->source(), "addEventListener('scroll'"), 'One scroll listener.');

        $scroll = $this->body('onScroll');
        $this->assertStringContainsString('if (!bar.frame) {', $scroll);
        $this->assertStringContainsString('bar.frame = window.requestAnimationFrame(decide);', $scroll);
        $this->assertStringContainsString('bar.frame = 0;', $this->body('decide'));
    }

    /**
     * The decision reads every piece of layout before it writes a class or the band.
     *
     * @return void
     */
    public function test_the_decision_reads_before_it_writes(): void {
        $decide = $this->body('decide');
        $lastread = max(strpos($decide, 'scrolled()'), strpos($decide, 'busy()'), strpos($decide, 'pastHeader()'));
        $firstwrite = min(strpos($decide, 'setBand('), strpos($decide, 'setAway('), strpos($decide, 'setContext('));
        $this->assertGreaterThan($lastread, $firstwrite);
    }

    /**
     * Nothing changes while the focus is in the bar, one of its menus is open, or a drawer lies over the page.
     *
     * Changes that must make it fail: dropping the focus clause, or letting a busy bar decide.
     *
     * @return void
     */
    public function test_nothing_changes_while_the_bar_is_busy(): void {
        $busy = $this->body('busy');
        $this->assertStringContainsString('if (bar.nav.contains(document.activeElement)) {', $busy);
        $this->assertStringContainsString(
            'bar.nav.querySelector(SELECTORS.EXPANDED) || bar.nav.querySelector(SELECTORS.OPENMENU)',
            $busy
        );
        $this->assertStringContainsString('document.querySelector(SELECTORS.MESSAGES)', $busy);
        $this->assertStringContainsString('overlaid || !DOCKED.includes(drawer.id)', $busy);
        // Docked is core's isSmall() boundary (991px), where its drawers stop lying over the page; isLarge() is 1400px,
        // and between the two an open course index would hold the bar for good.
        $this->assertStringContainsString('const overlaid = isSmall();', $busy);
        $this->assertStringContainsString("import {isSmall} from 'core/pagehelpers';", $this->source());
        $this->assertStringNotContainsString('isLarge', $busy);
        $this->assertStringNotContainsString('import {isLarge', $this->source());

        $decide = $this->body('decide');
        $this->assertMatchesRegularExpression('~if \(held\) \{\s*bar\.travel = 0;\s*return;\s*\}~', $decide);
        $this->assertStringContainsString('const held = moves && busy();', $decide);
    }

    /**
     * The keyboard reaching a hidden bar brings it back.
     *
     * @return void
     */
    public function test_the_keyboard_brings_a_hidden_bar_back(): void {
        $this->assertStringContainsString("nav.addEventListener('focusin', () => setAway(false));", $this->body('init'));
    }

    /**
     * Distance thresholds both ways: a short way down swaps, a longer one hides, a short way up brings the global bar
     * back; above the page header the global bar always stands and no travel is counted, so the swap is a stage of its
     * own after the header has gone (counted from the top, both happened in the frame the header left).
     *
     * @return void
     */
    public function test_the_thresholds_keep_the_bar_from_flickering(): void {
        $decide = $this->body('decide');
        $this->assertMatchesRegularExpression(
            '~if \(!past\) \{\s*bar\.travel = 0;\s*setAway\(false\);\s*setContext\(false\);\s*return;\s*\}\s*'
                . 'bar\.travel = \(delta > 0\) === \(bar\.travel > 0\) \? bar\.travel \+ delta : delta;~',
            $decide,
            'Above the header the travel is reset, before any delta is added.'
        );
        $this->assertStringContainsString('if (bar.travel <= -DISTANCE.BACK) {', $decide);
        $this->assertMatchesRegularExpression('~if \(bar\.travel >= DISTANCE\.SWAP\) \{\s*setContext\(true\);~', $decide);
        $this->assertMatchesRegularExpression('~if \(bar\.travel >= DISTANCE\.HIDE\) \{\s*setAway\(true\);~', $decide);

        $this->assertSame(1, preg_match('~const DISTANCE = \{\s*SWAP: (\d+),\s*HIDE: (\d+),\s*BACK: (\d+),~', $this->source(), $m));
        $this->assertGreaterThan(0, (int) $m[3]);
        $this->assertGreaterThan((int) $m[1], (int) $m[2], 'The bar swaps before it hides.');
    }

    /**
     * The Behat site never swaps or hides with the scroll, whatever the server said.
     *
     * @return void
     */
    public function test_the_behat_site_never_moves_the_bar(): void {
        $init = $this->body('init');
        $this->assertStringContainsString('const live = !config.behat;', $init);
        $this->assertStringContainsString('bar.swaps = live && bar.layer !== null;', $init);
        $this->assertStringContainsString(
            'bar.hides = live && config.autohide && document.querySelector(SELECTORS.MENUBAR) === null;',
            $init
        );

        $this->assertStringContainsString('if (!bar.swaps || bar.context === on) {', $this->body('setContext'));
        $this->assertStringContainsString('if (!bar.hides || bar.away === away) {', $this->body('setAway'));
    }

    /**
     * The motion is the stylesheet's, under prefers-reduced-motion: the module writes classes and one custom property.
     *
     * @return void
     */
    public function test_the_motion_is_left_to_the_stylesheet(): void {
        $source = $this->source();
        $this->assertStringNotContainsString('.style.transition', $source);
        $this->assertStringNotContainsString('.animate(', $source);
        $this->assertSame(1, substr_count($source, '.style.setProperty('), 'The band\'s property is the one style written.');
        $this->assertStringContainsString("bar.nav.classList.toggle(CLASSES.CONTEXT, on);", $this->body('setContext'));
        $this->assertStringContainsString("document.body.classList.toggle(CLASSES.AWAY, away);", $this->body('setAway'));
    }

    /**
     * The course index control carries core's toggler attribute only while the layer shows it.
     *
     * @return void
     */
    public function test_the_index_control_is_a_toggle_only_while_it_can_be_seen(): void {
        $sync = $this->body('syncToggler');
        $this->assertMatchesRegularExpression(
            "~if \(shown\) \{\s*index\.setAttribute\('data-toggler', 'drawers'\);\s*"
                . "\} else \{\s*index\.removeAttribute\('data-toggler'\);~",
            $sync
        );
        $this->assertStringContainsString('syncToggler(on);', $this->body('setContext'));
        $this->assertStringContainsString('syncToggler(!bar.swaps);', $this->body('init'));
    }

    /**
     * The status and the course's progress follow core's own toggle event, and the course page's band is the server's
     * number.
     *
     * The progress moves by one per toggle and is said again in the language string the server printed; the Behat
     * scenario "Marking the activity done in core's footer updates the context layer" is the behaviour, this the shape.
     *
     * @return void
     */
    public function test_the_status_and_the_course_band_follow_the_server(): void {
        $status = $this->body('armStatus');
        $this->assertStringContainsString('document.addEventListener(CourseEvents.manualCompletionToggled', $status);
        $this->assertStringContainsString('status && String(detail.cmid) === status.dataset.cmid', $status);
        $this->assertStringContainsString("status.dataset.state = detail.completed ? 'complete' : 'pending';", $status);
        $this->assertStringContainsString('countProgress(progress, detail.completed ? 1 : -1)', $status);

        $count = $this->body('countProgress');
        $this->assertStringContainsString("await getString('drawer_progress', 'theme_dimensions', {completed, total});", $count);
        $this->assertStringContainsString('Math.min(total, Math.max(0, Number(progress.dataset.completed) + step))', $count);

        $init = $this->body('init');
        $this->assertStringContainsString('setBand(config.progress / 100);', $init);
        $this->assertStringContainsString("bar.tracks = config.kind === 'activity';", $init);
    }

    /**
     * The committed build was made from the committed source: Moodle serves amd/build, never amd/src.
     *
     * @return void
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root . '/amd/build/readingbar.min.js', 'The build is missing.');
        $map = json_decode((string) file_get_contents($root . '/amd/build/readingbar.min.js.map'), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');

        $this->assertSame($this->source(), (string) ($map['sourcesContent'][0] ?? ''));
    }
}
