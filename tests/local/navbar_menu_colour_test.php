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
 * Theme Dimensions - Tests for the colours of the navbar's dropdowns
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The navbar dropdowns' surface and the pill of the row under the pointer, for any brand colour.
 *
 * The Sass is compiled as it is written in scss/post.scss, over Bootstrap's functions and variables and core's
 * dark-readable(), and its answer is measured with a WCAG implementation of this test's own, so the two cannot agree
 * by sharing a mistake.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class navbar_menu_colour_test extends \advanced_testcase {
    /** @var float The contrast the stylesheet promises between the pill and its label. */
    private const LABEL = 4.6;

    /** @var float The contrast the stylesheet promises between the pill and the menu's surface. */
    private const PILL = 3.0;

    /**
     * The relative luminance of a six-digit hex colour, as WCAG 2 defines it.
     *
     * @param string $hex A colour such as #0f6cbf.
     * @return float
     */
    private function luminance(string $hex): float {
        $channels = array_map(
            static function (string $pair): float {
                $value = hexdec($pair) / 255;
                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($hex, '#'), 2)
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * The contrast ratio of two six-digit hex colours.
     *
     * @param string $a One colour.
     * @param string $b The other.
     * @return float
     */
    private function contrast(string $a, string $b): float {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * The text of a source between two markers, the last one included.
     *
     * @param string $source The file's text.
     * @param string $first Where the block starts.
     * @param string $last The block's last text.
     * @return string
     */
    private function block(string $source, string $first, string $last): string {
        $start = strpos($source, $first);
        $end = strpos($source, $last, (int) $start);
        $this->assertNotFalse($start, "The block starts at {$first}");
        $this->assertNotFalse($end, "The block ends at {$last}");

        return substr($source, $start, $end - $start + strlen($last));
    }

    /**
     * Core's colour mode helpers, from the top of the file to the end of dark-readable().
     *
     * The whole head and not the function alone: core 5.3 has since given dark-readable() a cache and helpers
     * declared above it, and the function's own text no longer compiles by itself.
     *
     * @param string $core The text of theme/boost/scss/moodle/colour-mode.scss.
     * @return string
     */
    private function core_helpers(string $core): string {
        $function = strpos($core, '@function dark-readable(');
        $this->assertNotFalse($function, 'Core still declares dark-readable().');
        $end = strpos($core, "\n}\n", $function);
        $this->assertNotFalse($end, 'The function ends.');

        return substr($core, 0, $end + 3);
    }

    /**
     * The menu's Sass, from its surface to the line that decides the pill, whatever that line says.
     *
     * @param string $post The text of scss/post.scss.
     * @return string
     */
    private function menu_block(string $post): string {
        $pattern = '/\$dimensions-menu-surface:.*?\n\$dimensions-menu-highlight:[^\n]*\n/s';
        $this->assertSame(1, preg_match($pattern, $post, $matches), 'The block ends where the pill is decided.');

        return $matches[0];
    }

    /**
     * Compile the stylesheet's menu block for a brand colour.
     *
     * @param string $primary The brand colour, $primary.
     * @return array The surface, the pill and the pill's ink, six-digit lowercase hex.
     */
    private function colours(string $primary): array {
        global $CFG;

        $post = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $core = (string) file_get_contents($CFG->dirroot . '/theme/boost/scss/moodle/colour-mode.scss');
        $code = '@import "functions"; @import "variables"; $primary: ' . $primary . ';'
            . $this->block($post, '$dim-ds-blue-050:', '$dim-ds-red-800: #51140d;')
            . $this->core_helpers($core)
            . $this->menu_block($post)
            . '.probe { s: #{$dimensions-menu-surface}; g: #{$dimensions-menu-highlight};'
            . ' i: #{$dimensions-menu-highlight-ink}; }';
        $compiler = new \core_scss();
        $compiler->setImportPaths([$CFG->root . '/lib/bundles/bootstrap/scss']);
        $css = $compiler->compileString($code)->getCss();
        $pattern = '~s:\s*(#[0-9a-f]{3,6});\s*g:\s*(#[0-9a-f]{3,6});\s*i:\s*(#[0-9a-f]{3,6});~i';
        $this->assertSame(1, preg_match($pattern, $css, $matches), $css);

        return array_map(
            static function (string $hex): string {
                $hex = strtolower(ltrim($hex, '#'));
                return '#' . (strlen($hex) === 3 ? $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] : $hex);
            },
            [$matches[1], $matches[2], $matches[3]]
        );
    }

    /**
     * Brand colours across the range: core's blue, the design system's navy, a dark red, a light yellow, a mid grey.
     *
     * @return array
     */
    public static function brand_colours(): array {
        return [
            'core blue' => ['#0f6cbf'],
            'navy' => ['#070093'],
            'dark red' => ['#7a1f2b'],
            'light yellow' => ['#ffd54a'],
            'mid grey' => ['#777777'],
        ];
    }

    /**
     * The pill's label reads on it, and the pill is seen on the menu's surface.
     *
     * @param string $primary The brand colour.
     * @return void
     */
    #[DataProvider('brand_colours')]
    public function test_the_pill_reads_and_stands_out_from_the_surface(string $primary): void {
        [$surface, $pill, $ink] = $this->colours($primary);

        $this->assertGreaterThanOrEqual(self::LABEL, $this->contrast($pill, $ink), "$ink on $pill");
        $this->assertGreaterThanOrEqual(self::PILL, $this->contrast($pill, $surface), "$pill on $surface");
        $this->assertContains($ink, ['#ffffff', '#1f1f1f'], 'White or the darkest grey, never a third ink.');
        $this->assertGreaterThanOrEqual(self::LABEL, $this->contrast($surface, '#ffffff'), 'The rows\' white label.');
    }

    /**
     * The pill is the brand colour, moved only as far as the contrast needs: a colour that already stands out is kept.
     *
     * The control is the navy, which the dark surface would swallow, and which is therefore lifted.
     *
     * @return void
     */
    public function test_the_pill_is_the_brand_colour_where_it_already_reads(): void {
        $this->assertSame('#ffd54a', $this->colours('#ffd54a')[1]);
        $this->assertSame('#1f1f1f', $this->colours('#ffd54a')[2]);
        $this->assertNotSame('#070093', $this->colours('#070093')[1], 'Control: the navy is lifted.');
        $this->assertSame('#ffffff', $this->colours('#070093')[2]);
    }

    /**
     * The surface does not move with the brand colour: one dark menu for every flavour.
     *
     * @return void
     */
    public function test_the_surface_is_the_same_for_every_brand_colour(): void {
        $this->assertSame($this->colours('#070093')[0], $this->colours('#ffd54a')[0]);
    }
}
