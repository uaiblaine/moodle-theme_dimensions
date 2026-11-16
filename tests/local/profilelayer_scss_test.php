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
 * Theme Dimensions - Tests for the profile layer's stylesheet
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards scss/profilelayer.scss, the profile layer: every selector is one of the layer's own classes, the theme's main
 * SCSS includes it, it compiles over Bootstrap, the phone's sheet sits under the sm step, every colour is read through
 * a token post.scss declares, the motion follows prefers-reduced-motion and the focus ring is the theme's.
 *
 * The file is concatenated into the theme's main SCSS and reaches every page of the site, so a rule escaping the
 * layer's classes would restyle core's pages.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class profilelayer_scss_test extends \advanced_testcase {
    /** @var string The class every top-level selector starts with. */
    private const ROOT = '.dimensions-layer';

    /**
     * The stylesheet, comments removed.
     *
     * @return string
     */
    private function stylesheet(): string {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/profilelayer.scss');
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        return (string) preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * The stylesheet compiled over Bootstrap's functions, variables and mixins, as the theme's main SCSS compiles it.
     *
     * @return string CSS.
     */
    private function compiled(): string {
        global $CFG;

        $compiler = new \core_scss();
        $compiler->setImportPaths([$CFG->root . '/lib/bundles/bootstrap/scss']);
        $code = '@import "functions"; @import "variables"; @import "variables-dark"; @import "maps"; @import "mixins";'
            . $this->stylesheet();

        return $compiler->compileString($code)->getCss();
    }

    /**
     * The theme's main SCSS includes the sheet, after the tokens it reads.
     *
     * @return void
     */
    public function test_the_main_scss_includes_the_sheet_after_the_tokens(): void {
        $lib = (string) file_get_contents(dirname(__DIR__, 2) . '/lib.php');

        $sheet = strpos($lib, "'/theme/dimensions/scss/profilelayer.scss'");
        $post = strpos($lib, "'/theme/dimensions/scss/post.scss'");
        $this->assertNotFalse($sheet, 'The sheet is included.');
        $this->assertNotFalse($post, 'Precondition: the tokens are included.');
        $this->assertGreaterThan($post, $sheet);
    }

    /**
     * Every selector of the compiled sheet starts with one of the layer's own classes.
     *
     * @return void
     */
    public function test_every_selector_is_the_layers_own(): void {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $this->compiled());
        preg_match_all('~([^{};]+)\{~', $css, $matches);
        $checked = 0;
        foreach ($matches[1] as $prelude) {
            $prelude = trim($prelude);
            // An at-rule, or a stop of the layer's own keyframes, is no selector.
            if ($prelude === '' || $prelude[0] === '@' || preg_match('~^(from|to|\d+(\.\d+)?%)$~', $prelude)) {
                continue;
            }
            foreach (explode(',', $prelude) as $selector) {
                $this->assertStringStartsWith(self::ROOT, trim($selector), 'A selector outside the layer: ' . $selector);
                $checked++;
            }
        }
        $this->assertGreaterThan(40, $checked, 'Precondition: the sheet was read.');
    }

    /**
     * The phone's sheet is the sm step, the motion follows the person's setting, and nothing is !important.
     *
     * @return void
     */
    public function test_the_sheet_step_the_motion_and_no_important(): void {
        $scss = $this->stylesheet();
        $css = $this->compiled();

        $this->assertStringNotContainsString('!important', $scss);
        $this->assertMatchesRegularExpression(
            '~@media \(max-width: 575\.98px\)\s*\{\s*\.dimensions-layer\.dimensions-layer--end\s*\{[^}]*top: auto;'
                . '[^}]*max-height: 88vh;[^}]*border-radius: var\(--dim-layer-radius-xl\) var\(--dim-layer-radius-xl\) 0 0;~',
            $css,
            'Below sm the layer is a bottom sheet.'
        );
        // Every transition sits under prefers-reduced-motion: no-preference.
        preg_match_all('~transition\s*:~', $scss, $all);
        preg_match_all('~@media \(prefers-reduced-motion: no-preference\)\s*\{\s*transition\s*:~', $scss, $guarded);
        $this->assertNotEmpty($all[0], 'Precondition: the sheet has a transition.');
        $this->assertCount(count($all[0]), $guarded[0]);
        // The opening: the backdrop and the layer animate only while shown and only under the same media query, in the
        // theme's base motion token, and every keyframes rule is the layer's own.
        preg_match_all('~animation\s*:~', $scss, $animations);
        preg_match_all(
            '~@media \(prefers-reduced-motion: no-preference\)\s*\{\s*&:not\(\[hidden\]\)\s*\{\s*animation\s*:~',
            $scss,
            $shown
        );
        $this->assertCount(2, $animations[0], 'The backdrop\'s fade and the layer\'s slide.');
        $this->assertCount(2, $shown[0]);
        $this->assertStringContainsString(
            'animation: dimensions-layer-in var(--dim-layer-motion-base) var(--dim-layer-motion-ease);',
            $scss
        );
        $this->assertStringContainsString('--dim-layer-motion-base: var(--dimensions-motion-base, 250ms);', $scss);
        preg_match_all('~@keyframes ([a-z-]+)~', $scss, $keyframes);
        $this->assertSame(['dimensions-layer-in', 'dimensions-layer-fade'], $keyframes[1]);
        $this->assertMatchesRegularExpression(
            '~@media \(max-width: 575\.98px\)\s*\{\s*\.dimensions-layer\.dimensions-layer--end\s*\{\s*'
                . '--dim-layer-from: translateY\(100%\);~',
            $css,
            'The sheet rises from the bottom.'
        );
        $this->assertMatchesRegularExpression(
            '~@media \(min-width: 768px\)\s*\{\s*\.dimensions-layer \.dimensions-layer-phone\s*\{\s*display: none;~',
            $css,
            'The phone\'s entries are a phone\'s group alone (D75).'
        );
        $this->assertMatchesRegularExpression(
            '~\.dimensions-layer :focus-visible\s*\{\s*outline: var\(--dim-layer-ring-width\) solid var\(--dim-layer-ring\);'
                . '\s*outline-offset: -2px;~',
            $css,
            'The theme\'s ring, inside the region that clips.'
        );
    }

    /**
     * Every colour is a token: the layer's own, which reads a family token post.scss declares, with Bootstrap's after it.
     *
     * @return void
     */
    public function test_every_colour_is_read_through_a_declared_token(): void {
        $scss = $this->stylesheet();
        $post = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');

        preg_match_all('~var\((--dimensions-[a-z0-9-]+)~', $scss, $family);
        $this->assertNotEmpty($family[1], 'Precondition: the sheet reads the family.');
        foreach (array_unique($family[1]) as $token) {
            $this->assertMatchesRegularExpression('~\s' . preg_quote($token, '~') . ':~', $post, $token . ' is declared.');
        }
        preg_match_all('~var\((--dim-layer-[a-z0-9-]+)\)~', $scss, $own);
        foreach (array_unique($own[1]) as $token) {
            $this->assertStringContainsString($token . ':', $scss, $token . ' is declared on the layer.');
        }
        preg_match_all('~^\s*(color|background|background-color|border[a-z-]*|box-shadow|outline)\s*:\s*([^;]+);~m', $scss, $paint);
        foreach ($paint[2] as $index => $value) {
            $this->assertDoesNotMatchRegularExpression(
                '~#[0-9a-f]{3,8}\b|rgba?\((?!var)~i',
                (string) preg_replace('~var\([^;]*~', '', $value),
                $paint[1][$index] . ': ' . $value . ' paints a literal colour.'
            );
        }
    }
}
