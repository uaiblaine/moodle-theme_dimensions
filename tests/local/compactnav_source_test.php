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
 * Theme Dimensions - Tests for the invariants of the compact navbar's client module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of amd/src/compactnav.js by reading its source, as readingbar_source_test does for the reading
 * bar: plugin CI runs no JavaScript unit tests, and what the module measures is only visible in a browser.
 *
 * Each assertion is scoped to the one function it is about: the module refuses to run without the server's armed
 * class (the setting off, the Moodle app, focus mode), it observes with a ResizeObserver and a MutationObserver and
 * never polls, the decision runs in an animation frame and reads before it writes, the count is the navigation's own,
 * the mode lives between md and xl as the stylesheet's range does, and the toggler loses only d-md-none.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class compactnav_source_test extends \basic_testcase {
    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = dirname(__DIR__, 2) . '/amd/src/compactnav.js';
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
        $schedule = $this->body('schedule');
        $this->assertStringContainsString('requestAnimationFrame', $schedule);
        $this->assertStringNotContainsString('setCompact(', $schedule, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('setCompact(', $this->body('decide'));
    }

    /**
     * Without the server's armed class the module does nothing: the setting off, the Moodle app and focus mode.
     *
     * @return void
     */
    public function test_the_module_refuses_to_run_where_the_server_did_not_arm_it(): void {
        $init = $this->body('init');
        $this->assertMatchesRegularExpression(
            '~^\s*// [^\n]*\n\s*if \(!document\.body\.classList\.contains\(CLASSES\.ARMED\)\) \{\s*return;\s*\}~',
            $init,
            'The guard is the first statement of init().'
        );
        $this->assertStringContainsString("ARMED: '" . compactnav::BODY_ARMED . "',", $this->source(), 'The server\'s class.');
    }

    /**
     * A ResizeObserver and a MutationObserver book the decision; nothing polls.
     *
     * @return void
     */
    public function test_it_observes_and_never_polls(): void {
        $source = $this->source();
        $init = $this->body('init');
        $this->assertStringContainsString('new ResizeObserver(schedule).observe(container);', $init);
        $this->assertStringContainsString('new MutationObserver(schedule).observe(mount, {', $init);
        $this->assertStringContainsString("bar.range.addEventListener('change', schedule);", $init);

        $this->assertStringNotContainsString('setTimeout', $source);
        $this->assertStringNotContainsString('setInterval', $source);
        $this->assertStringNotContainsString("addEventListener('resize'", $source, 'The observer, not the window\'s resize.');
    }

    /**
     * The decision runs once per animation frame, under core's pending tracker, and reads before it writes.
     *
     * @return void
     */
    public function test_the_decision_runs_in_an_animation_frame_and_reads_first(): void {
        $schedule = $this->body('schedule');
        $this->assertMatchesRegularExpression('~if \(bar\.frame\) \{\s*return;\s*\}~', $schedule);
        $this->assertStringContainsString('bar.frame = window.requestAnimationFrame(() => {', $schedule);
        $this->assertStringContainsString("new Pending('theme_dimensions/compactnav:decide')", $schedule);

        $decide = $this->body('decide');
        $this->assertStringContainsString('bar.frame = 0;', $decide);
        $lastread = max(strpos($decide, 'hidden()'), strpos($decide, 'bar.range.matches'));
        $firstwrite = min(strpos($decide, 'setCompact('), strpos($decide, 'classList.add('));
        $this->assertGreaterThan($lastread, $firstwrite);
        $this->assertMatchesRegularExpression(
            '~if \(count === null\) \{\s*return;\s*\}~',
            $decide,
            'Nothing is decided, and the hold stays, until the navigation has settled.'
        );
    }

    /**
     * Compact means more than maxhidden items in "More", inside the range; the count is the navigation's own.
     *
     * @return void
     */
    public function test_compact_means_more_than_maxhidden_items_inside_the_range(): void {
        $this->assertStringContainsString('setCompact(inrange && count > bar.maxhidden);', $this->body('decide'));
        $this->assertStringContainsString('bar.maxhidden = config.maxhidden;', $this->body('init'));

        $hidden = $this->body('hidden');
        $this->assertMatchesRegularExpression(
            '~if \(!bar\.mount\.classList\.contains\(bar\.measuredclass\)\) \{\s*return null;\s*\}~',
            $hidden,
            'Read only once the navigation has settled.'
        );
        $this->assertStringContainsString(
            'return Math.max(bar.candidates - menu.querySelectorAll(SELECTORS.PILLS).length, 0);',
            $hidden
        );
        $this->assertStringContainsString("PILLS: ':scope > li:not(.dropdownmoremenu)',", $this->source());
        $this->assertStringContainsString(
            'props.items.filter((item) => item && !item.divider && !item.forceintomoremenu).length',
            $this->body('readProps'),
            'The same candidates as compactnav::candidates().'
        );
    }

    /**
     * The range is the stylesheet's: media-breakpoint-between(md, xl) in scss/compactnav.scss.
     *
     * @return void
     */
    public function test_the_range_is_the_stylesheets(): void {
        $this->assertStringContainsString(
            "const RANGE = '(min-width: 768px) and (max-width: 1199.98px)';",
            $this->source()
        );
        $scss = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/compactnav.scss');
        $this->assertStringContainsString('@include media-breakpoint-between(md, xl) {', $scss);
    }

    /**
     * The toggler loses d-md-none while compact and gets it back after; leaving the mode releases the drawer.
     *
     * @return void
     */
    public function test_the_toggler_and_the_drawer_follow_the_mode(): void {
        $set = $this->body('setCompact');
        $this->assertStringContainsString('document.body.classList.toggle(CLASSES.COMPACT, on);', $set);
        $this->assertStringContainsString('bar.toggler.classList.toggle(CLASSES.TOGGLERHIDDEN, !on);', $set);
        $this->assertMatchesRegularExpression('~if \(!on\) \{\s*release\(\);\s*\}~', $set);
        $this->assertStringContainsString("TOGGLERHIDDEN: 'd-md-none',", $this->source());
        $this->assertStringContainsString("COMPACT: 'dimensions-nav-compact',", $this->source());

        $release = $this->body('release');
        $this->assertStringContainsString('if (bar.drawer.contains(document.activeElement)) {', $release);
    }

    /**
     * A press outside the open drawer at tablet width closes it, and on the page goes no further.
     *
     * @return void
     */
    public function test_a_press_outside_the_drawer_closes_it(): void {
        $this->assertStringContainsString("document.addEventListener('click', onPress, true);", $this->body('init'));

        $press = $this->body('onPress');
        $this->assertMatchesRegularExpression('~if \(!drawerOverPage\(\)\) \{\s*return;\s*\}~', $press);
        $this->assertStringContainsString('bar.drawer.contains(target) || target.closest(SELECTORS.BUTTONS)', $press);
        $this->assertMatchesRegularExpression(
            '~event\.preventDefault\(\);\s*event\.stopPropagation\(\);\s*instance\.closeDrawer\(\);~',
            $press
        );
        $this->assertStringContainsString(
            'const drawerOverPage = () => bar.compact && bar.drawer !== null && bar.drawer.classList.contains(CLASSES.SHOW)'
                . ' && !isSmall();',
            $this->source(),
            'Only at a width core gives the drawer no backdrop: below md core\'s own does it.'
        );
    }

    /**
     * The committed build was made from the committed source: Moodle serves amd/build, never amd/src.
     *
     * @return void
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root . '/amd/build/compactnav.min.js', 'The build is missing.');
        $map = json_decode((string) file_get_contents($root . '/amd/build/compactnav.min.js.map'), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');

        $this->assertSame($this->source(), (string) ($map['sourcesContent'][0] ?? ''));
    }
}
