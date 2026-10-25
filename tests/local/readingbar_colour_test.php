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
 * Theme Dimensions - Tests for the context bar's colour
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The context bar's ground and ink: the setting's reader, its way into the SCSS, and the Sass that derives the pair.
 *
 * The Sass is compiled as it is written in scss/readingbar.scss, over Bootstrap's own functions and variables, and its
 * answer is measured with a WCAG implementation of this test's own, so the two cannot agree by sharing a mistake.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(readingbar::class)]
final class readingbar_colour_test extends \advanced_testcase {
    /** @var float The contrast the stylesheet promises between the ground and its ink. */
    private const MIN = 4.6;

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
     * Compile the stylesheet's colour block over Bootstrap's functions and variables, for a brand colour and a chosen one.
     *
     * @param string $primary The brand colour, $primary.
     * @param string|null $chosen The administrator's colour, as the pre SCSS callback writes it; null for none.
     * @return array The ground and the ink, six-digit lowercase hex.
     */
    private function pair(string $primary, ?string $chosen): array {
        global $CFG;

        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/readingbar.scss');
        $first = '$dimensions-ctxbar-color: null !default;';
        $last = '$dimensions-ctxbar-ink: dimensions-ctxbar-ink($dimensions-ctxbar-ground);';
        $start = strpos($source, $first);
        $end = strpos($source, $last);
        $this->assertNotFalse($start, 'The block starts where the setting is read.');
        $this->assertNotFalse($end, 'The block ends where the ink is decided.');

        $code = '@import "functions"; @import "variables"; $primary: ' . $primary . ';'
            . ($chosen === null ? '' : '$dimensions-ctxbar-color: ' . $chosen . ';')
            . substr($source, $start, $end - $start) . $last
            . '.probe { g: #{$dimensions-ctxbar-ground}; i: #{$dimensions-ctxbar-ink}; }';
        $compiler = new \core_scss();
        $compiler->setImportPaths([$CFG->root . '/lib/bundles/bootstrap/scss']);
        $css = $compiler->compileString($code)->getCss();
        $this->assertSame(1, preg_match('~g:\s*(#[0-9a-f]{3,6});\s*i:\s*(#[0-9a-f]{3,6});~i', $css, $matches), $css);

        return array_map(
            static function (string $hex): string {
                $hex = strtolower(ltrim($hex, '#'));
                return '#' . (strlen($hex) === 3 ? $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] : $hex);
            },
            [$matches[1], $matches[2]]
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
     * With no colour chosen, the ground is a step of the brand colour that reads with its ink and is seen to differ from
     * the brand colour the navbar is painted with.
     *
     * @param string $primary The brand colour.
     * @return void
     */
    #[DataProvider('brand_colours')]
    public function test_the_derived_ground_reads_and_differs_from_the_brand_colour(string $primary): void {
        [$ground, $ink] = $this->pair($primary, null);

        $this->assertGreaterThanOrEqual(self::MIN, $this->contrast($ground, $ink), "$ground under $ink");
        $this->assertGreaterThanOrEqual(1.3, $this->contrast($ground, $primary), "$ground beside $primary");
        $this->assertContains($ink, ['#ffffff', '#212529'], 'White or the darkest grey, never a third ink.');
    }

    /**
     * The ink is the one that reads better on the ground: white on a dark ground, the dark grey on a light one.
     *
     * @return void
     */
    public function test_the_ink_follows_the_ground(): void {
        $this->assertSame('#ffffff', $this->pair('#070093', null)[1]);
        $this->assertSame('#212529', $this->pair('#ffd54a', null)[1]);
    }

    /**
     * An administrator's colour that reads with one of the two inks is used as it is.
     *
     * @return void
     */
    public function test_a_chosen_colour_that_reads_is_kept(): void {
        $this->assertSame(['#ffd54a', '#212529'], $this->pair('#0f6cbf', '#ffd54a'));
        $this->assertSame(['#1d4f91', '#ffffff'], $this->pair('#0f6cbf', '#1d4f91'));
    }

    /**
     * An administrator's colour neither ink reads on is moved, away from its ink, only as far as the contrast needs.
     *
     * @return void
     */
    public function test_a_chosen_colour_that_does_not_read_is_moved_until_it_does(): void {
        $this->assertLessThan(self::MIN, $this->contrast('#777777', '#ffffff'), 'Precondition: grey under white is short.');

        [$ground, $ink] = $this->pair('#0f6cbf', '#777777');

        $this->assertSame('#ffffff', $ink);
        $this->assertNotSame('#777777', $ground);
        $this->assertGreaterThanOrEqual(self::MIN, $this->contrast($ground, $ink));
        $this->assertLessThan(1.15, $this->contrast($ground, '#777777'), 'One step, not a different colour.');
    }

    /**
     * Only a hexadecimal colour reaches the stylesheet, with its hash and in lower case; anything else reads as unset.
     *
     * @return void
     */
    public function test_only_a_hexadecimal_colour_is_read(): void {
        $this->resetAfterTest();

        $this->assertNull(readingbar::context_colour(), 'Unset.');
        foreach (['#1D4F91' => '#1d4f91', '1d4f91' => '#1d4f91', '#abc' => '#abc', ' #abc ' => '#abc'] as $stored => $read) {
            set_config(readingbar::SETTING_COLOUR, $stored, readingbar::COMPONENT);
            $this->assertSame($read, readingbar::context_colour(), $stored);
        }
        foreach (['', 'red', 'rgb(1, 2, 3)', '#abcd', '#12345g', '#fff;} body {display:none', '#abc #def'] as $stored) {
            set_config(readingbar::SETTING_COLOUR, $stored, readingbar::COMPONENT);
            $this->assertNull(readingbar::context_colour(), $stored);
        }
    }

    /**
     * The pre SCSS callback writes the chosen colour as the variable the stylesheet reads, and nothing while none is.
     *
     * @return void
     */
    public function test_the_pre_scss_carries_the_chosen_colour(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/theme/dimensions/lib.php');
        $theme = \core\output\theme_config::load('dimensions');

        $this->assertStringNotContainsString('$dimensions-ctxbar-color', theme_dimensions_get_pre_scss($theme));

        set_config(readingbar::SETTING_COLOUR, '#1D4F91', readingbar::COMPONENT);
        $this->assertStringContainsString(
            "\n\$dimensions-ctxbar-color: #1d4f91 !default;\n",
            theme_dimensions_get_pre_scss($theme),
            'A default, so a flavour\'s raw initial SCSS, compiled before it, keeps its own colour.'
        );

        set_config(readingbar::SETTING_COLOUR, 'red', readingbar::COMPONENT);
        $this->assertStringNotContainsString('$dimensions-ctxbar-color', theme_dimensions_get_pre_scss($theme));
    }
}
