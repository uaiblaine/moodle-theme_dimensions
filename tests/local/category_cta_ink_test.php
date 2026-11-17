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
 * Theme Dimensions - Tests for the ink on the category showcase's call to action fill
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;
use theme_dimensions\local\category\branding;

/**
 * The call to action fill follows the site's primary, so its text colour is derived from the same primary.
 *
 * The declaration is read from scss/category.scss and compiled over Bootstrap's own functions, so the test breaks
 * when the stylesheet stops deriving the ink and not only when a copy of it does.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class category_cta_ink_test extends \advanced_testcase {
    /**
     * The stylesheet's source.
     *
     * @return string
     */
    private function source(): string {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/scss/category.scss');
    }

    /**
     * Compile the ink declaration for a primary.
     *
     * @param string $primary The site's primary colour.
     * @param string $property The custom property that holds the ink.
     * @return string The ink, six-digit lowercase hex.
     */
    private function ink(string $primary, string $property = '--dim-cta-ink'): string {
        global $CFG;

        $this->assertSame(
            1,
            preg_match('~^\s*(' . preg_quote($property, '~') . ': #\{color-contrast\(\$primary\)\};)~m', $this->source(), $found),
            'The light declaration of ' . $property . ' derives the ink from $primary.'
        );
        $code = '@import "functions"; @import "variables"; $primary: ' . $primary . ';.probe { ' . $found[1] . ' }';
        $compiler = new \core_scss();
        $compiler->setImportPaths([$CFG->root . '/lib/bundles/bootstrap/scss']);
        $css = $compiler->compileString($code)->getCss();
        $this->assertSame(1, preg_match('~' . preg_quote($property, '~') . ':\s*(#[0-9a-f]{3,6})~i', $css, $matches), $css);
        $hex = strtolower(ltrim($matches[1], '#'));

        return '#' . (strlen($hex) === 3 ? $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] : $hex);
    }

    /**
     * A light primary gets a dark ink that reads at 4.5:1 on the checked chip, and white would not.
     */
    public function test_a_light_primary_gets_a_dark_ink_that_reads(): void {
        foreach (['#ffd500', '#a5d6a7', '#f5e663'] as $primary) {
            $ink = $this->ink($primary);
            $this->assertNotSame('#ffffff', $ink, $primary);
            $this->assertGreaterThanOrEqual(4.5, branding::contrast($primary, $ink), $primary);
            $this->assertLessThan(4.5, branding::contrast($primary, '#ffffff'), 'Control: white fails on ' . $primary);
        }
    }

    /**
     * Control: core's own blue and a dark brand colour keep the white ink.
     */
    public function test_a_dark_primary_keeps_white(): void {
        foreach (['#0f6cbf', '#070093', '#7a1f2b'] as $primary) {
            $this->assertSame('#ffffff', $this->ink($primary), $primary);
        }
    }

    /**
     * Each rule's own declarations, without the text of the rules nested in it.
     *
     * @param string $scss A sheet with its comments removed.
     * @return string[] One entry per rule.
     */
    private function own_declarations(string $scss): array {
        $rules = [];
        $stack = [''];
        foreach (str_split($scss) as $char) {
            if ($char === '{') {
                $stack[] = '';
            } else if ($char === '}') {
                $rules[] = array_pop($stack);
                if (!$stack) {
                    $stack = [''];
                }
            } else {
                $stack[count($stack) - 1] .= $char;
            }
        }
        return $rules;
    }

    /**
     * No rule that paints the call to action's fill pins white on it, and the dark mode names its ink.
     */
    public function test_no_rule_pins_white_on_the_fill(): void {
        $scss = $this->source();
        $clean = preg_replace('~/\*.*?\*/|(?<!:)//[^\n]*~s', '', $scss);
        $fill = '~(?<![\w-])(background|--bs-btn-bg|--bs-btn-disabled-bg):\s*var\(--dim-cta\)~';
        $checked = 0;
        foreach ($this->own_declarations($clean) as $block) {
            $fills = preg_match_all($fill, $block);
            if ($fills) {
                $checked += $fills;
                $this->assertDoesNotMatchRegularExpression(
                    '~(?<![\w-])(color|--bs-btn-color|--bs-btn-disabled-color):\s*#fff~',
                    $block,
                    $block
                );
            }
        }
        // Control: every fill declaration of the sheet was read inside its own rule, nested rules included.
        $this->assertSame(preg_match_all($fill, $clean), $checked, 'The scan reaches every rule that paints the fill.');
        $this->assertGreaterThanOrEqual(8, $checked);
        $this->assertMatchesRegularExpression(
            '~--dim-cta:\s*var\(--dim-ds-blue-400, #4456ff\);\s*--dim-cta-ink:\s*#fff;~',
            $scss,
            'The dark mode pairs its fixed blue with white.'
        );
    }
}
