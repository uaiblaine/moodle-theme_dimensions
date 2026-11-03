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
 * Theme Dimensions - Tests for the landing page's brand ground
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use theme_dimensions\local\category\branding;

/**
 * Compiles scss/landing.scss against Bootstrap with a given $primary and reads the ground it declares.
 *
 * The decision is the compiler's, so it is tested on compiled CSS: a brand colour that keeps white
 * text at 4.5:1 on its lightest stop becomes the gradient of branding::stops(), any other is shaded
 * until it does and stays in its own hue, and both roots that paint the ground, the landing page and
 * the guest entry, carry the same three stops. The panel's ink is that colour, lifted on a dark card.
 * Boost Union compiles the same sheet once per flavour with the flavour's brand colour as $primary,
 * which is the $primary set here.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class landing_scss_test extends \advanced_testcase {
    /** @var array The two roots that paint the ground. */
    private const ROOTS = ['.theme_dimensions-landing', '.theme_dimensions-guestentry'];

    /** @var string The raised surface core's colour mode paints a card with ($dark-surface-raised, $gray-800). */
    private const DARK_CARD = '#343a40';

    /**
     * Compile the landing sheet with a brand colour.
     *
     * @param string $primary The brand colour, as #rrggbb.
     * @return string The compiled CSS.
     */
    private function compile(string $primary): string {
        global $CFG;
        $compiler = new \core_scss();
        $compiler->setImportPaths([$CFG->root . '/lib/bundles/bootstrap/scss']);
        $compiler->append_raw_scss(
            '@import "functions"; @import "variables"; @import "variables-dark"; @import "maps"; @import "mixins";'
            . "\n\$primary: {$primary};\n"
            . file_get_contents(dirname(__DIR__, 2) . '/scss/landing.scss')
        );
        return $compiler->to_css();
    }

    /**
     * The declarations of the first rule whose selector is exactly the one given.
     *
     * @param string $css The compiled CSS.
     * @param string $selector The selector, as the compiler prints it.
     * @return string The declaration block, without its braces.
     */
    private function rule(string $css, string $selector): string {
        $found = preg_match('~(?:^|[}\s])' . preg_quote($selector, '~') . '\s*\{([^}]*)\}~', $css, $matches);
        $this->assertSame(1, $found, "No rule for {$selector}.");
        return $matches[1];
    }

    /**
     * The three stops a root declares.
     *
     * @param string $css The compiled CSS.
     * @param string $root The root's selector.
     * @return array Keys from, mid and to, each the declared value.
     */
    private function stops(string $css, string $root): array {
        $block = $this->rule($css, $root);
        $stops = [];
        foreach (['from', 'mid', 'to'] as $stop) {
            $found = preg_match('~--dim-landing-' . $stop . ':\s*([^;]+);~', $block, $matches);
            $this->assertSame(1, $found, "{$root} has no {$stop}.");
            $stops[$stop] = trim($matches[1]);
        }
        return $stops;
    }

    /**
     * Brand colours on whose lightest stop white text reads at 4.5:1 or more.
     *
     * @return array Named cases of one colour.
     */
    public static function branded_provider(): array {
        return [
            'burgundy' => ['#7a1f2b'],
            'the design system navy as a brand' => ['#070093'],
        ];
    }

    /**
     * A brand colour dark enough for white text becomes the ground, with branding::stops()'s stops.
     *
     * @param string $primary The brand colour.
     */
    #[DataProvider('branded_provider')]
    public function test_a_dark_brand_colour_is_the_ground(string $primary): void {
        $css = $this->compile($primary);
        foreach (self::ROOTS as $root) {
            $this->assertSame(branding::stops($primary), array_map(
                fn(string $value): ?string => branding::hex($value),
                $this->stops($css, $root)
            ), $root);
        }
    }

    /**
     * Brand colours on whose lightest stop white text reads under 4.5:1.
     *
     * Boost's default blue is the case that matters: a site with no brand colour gets it, one step darker.
     * Its lightest stop reads about 4.0:1, so a lowered threshold reddens it.
     *
     * @return array Named cases of one colour.
     */
    public static function light_provider(): array {
        return [
            'Boost default blue' => ['#0f6cbf'],
            'amber' => ['#f0b400'],
        ];
    }

    /**
     * The first 4% step of shade of a colour whose lightest stop carries white text at the floor, as the sheet finds it.
     *
     * @param string $primary The brand colour.
     * @return array The step and the shaded colour's channels.
     */
    private static function expected_base(string $primary): array {
        $channels = array_map('hexdec', str_split(ltrim($primary, '#'), 2));
        for ($step = 0; $step <= 25; $step++) {
            $shaded = array_map(fn(int $c): int => (int) round($c * (1 - $step * 0.04)), $channels);
            $lightest = branding::stops(vsprintf('#%02x%02x%02x', $shaded))['to'];
            if (branding::contrast($lightest, '#ffffff') >= branding::FLOOR) {
                return [$step, $shaded];
            }
        }
        return [25, $shaded];
    }

    /**
     * A brand colour too light for white text is shaded until white reads on its lightest stop, and keeps its hue.
     *
     * The control is the colour's own stops, which fail the floor: they are what a missing guard would have declared.
     *
     * @param string $primary The brand colour.
     */
    #[DataProvider('light_provider')]
    public function test_a_light_brand_colour_is_shaded_until_white_reads(string $primary): void {
        $this->assertLessThan(branding::FLOOR, branding::contrast(branding::stops($primary)['to'], '#ffffff'));
        [$step, $base] = self::expected_base($primary);
        $this->assertGreaterThan(0, $step);
        $css = $this->compile($primary);
        foreach (self::ROOTS as $root) {
            $stops = $this->stops($css, $root);
            $this->assertGreaterThanOrEqual(branding::FLOOR, branding::contrast($stops['to'], '#ffffff'), $root);
            $mid = array_map('hexdec', str_split(ltrim((string) branding::hex($stops['mid']), '#'), 2));
            foreach ($base as $i => $channel) {
                $this->assertEqualsWithDelta($channel, $mid[$i], 1, "{$root}: the mid stop is the brand colour, shaded.");
            }
        }
    }

    /**
     * The panel's ink (section titles, checks) is the base on the white card and reads at the floor on the dark one.
     *
     * @param string $primary The brand colour.
     */
    #[DataProvider('light_provider')]
    #[DataProvider('branded_provider')]
    public function test_the_panel_ink_reads_on_the_card_in_both_modes(string $primary): void {
        $css = $this->compile($primary);
        $light = $this->rule($css, '.theme_dimensions-landing');
        $this->assertSame(1, preg_match('~--dim-landing-ink:\s*([^;]+);~', $light, $matches));
        $this->assertSame($this->stops($css, '.theme_dimensions-landing')['mid'], trim($matches[1]));
        $this->assertGreaterThanOrEqual(branding::FLOOR, branding::contrast(trim($matches[1]), '#ffffff'));

        $dark = $this->rule($css, ':root[data-bs-theme="dark"] .theme_dimensions-landing');
        $this->assertSame(1, preg_match('~--dim-landing-ink:\s*([^;]+);~', $dark, $matches));
        $this->assertGreaterThanOrEqual(branding::FLOOR, branding::contrast(trim($matches[1]), self::DARK_CARD));
    }

    /**
     * The summary and the panel take the hero's inset, and the panel's titles and checks read the ink.
     */
    public function test_the_text_lines_up_with_the_title_and_the_panel_reads_the_ink(): void {
        $css = $this->compile('#0f6cbf');
        $this->assertStringContainsString(
            'padding: 2.5rem var(--dim-landing-inset) 3.25rem',
            $this->rule($css, '.theme_dimensions-landing .dimensions-landing__hero')
        );
        $this->assertStringContainsString(
            'padding-inline: var(--dim-landing-inset)',
            $this->rule($css, '.theme_dimensions-landing .dimensions-landing__section--summary')
        );
        $panel = $this->rule($css, '.theme_dimensions-landing .dimensions-landing__panel');
        $this->assertStringContainsString('var(--dim-landing-inset)', $panel);
        $this->assertStringContainsString('border: 1px solid var(--dim-landing-soft)', $panel);
        $this->assertStringContainsString(
            'color: var(--dim-landing-ink)',
            $this->rule($css, '.theme_dimensions-landing .dimensions-landing__sectiontitle')
        );
        $this->assertStringContainsString(
            'background: var(--dim-landing-ink)',
            $this->rule($css, '.theme_dimensions-landing .dimensions-landing__panel ul > li::before')
        );
        $this->assertStringContainsString(
            'list-style: none',
            $this->rule($css, '.theme_dimensions-landing .dimensions-landing__panel ul')
        );
    }

    /**
     * The hero, the guest entry's card and its button read the ground, not the navy tokens.
     */
    public function test_the_painted_rules_read_the_ground(): void {
        $css = $this->compile('#7a1f2b');
        $gradient = 'linear-gradient(135deg, var(--dim-landing-from), var(--dim-landing-mid) 55%, var(--dim-landing-to))';
        $this->assertStringContainsString(
            $gradient,
            $this->rule($css, '.theme_dimensions-landing .dimensions-landing__hero')
        );
        $this->assertStringContainsString(
            $gradient,
            $this->rule($css, '.theme_dimensions-guestentry .dimensions-guestentry__card')
        );
        $this->assertMatchesRegularExpression(
            '~(^|;)\s*color:\s*var\(--dim-landing-mid\);~',
            $this->rule($css, '.theme_dimensions-guestentry .dimensions-guestentry__button')
        );
    }
}
