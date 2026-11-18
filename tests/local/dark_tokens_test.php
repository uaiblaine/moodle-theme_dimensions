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
 * Theme Dimensions - Tests for the dark half of the design system's semantic aliases
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
 * Every semantic alias of scss/post.scss turns with the page's colour mode, and every dark pair it forms reads.
 *
 * Core 5.3's dark mode turns Bootstrap's surfaces and ink and none of the theme's --dim-ds-* aliases, so an alias
 * left at its light value draws navy ink on a card the page has darkened, or a white card under the ink the page
 * has lightened. The dark half is compiled by core's own dark-readable() and mix(), so its pairs are measured on
 * compiled values, against core's dark surfaces as the default preset sets them (a Boost Union "branded grey
 * tones" site moves those surfaces).
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class dark_tokens_test extends \basic_testcase {
    /** @var string The selector of the dark rule, as post.scss writes it. */
    private const DARK_RULE = ":root[data-bs-theme=\"dark\"],\n:root[data-bs-theme=\"dark\"] body,\n"
        . ":root[data-bs-theme=\"dark\"] .modal {";

    /**
     * @var array The aliases that are the same in both modes, each for a reason: the inverse ones are the navy
     *     chrome, the accents are marks on that chrome or on a band, and the primary fill is one pair with white ink.
     */
    private const SAME_IN_BOTH = [
        '--dim-ds-surface-inverse',
        '--dim-ds-surface-inverse-deep',
        '--dim-ds-text-inverse',
        '--dim-ds-text-inverse-muted',
        '--dim-ds-border-inverse',
        '--dim-ds-interactive-primary',
        '--dim-ds-accent-highlight',
        '--dim-ds-accent-progress',
    ];

    /**
     * The stylesheet, read once.
     *
     * @return string The source of scss/post.scss.
     */
    private function scss(): string {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        return $scss;
    }

    /**
     * The body of the first rule whose selector is the one given.
     *
     * @param string $scss The stylesheet.
     * @param string $opening The selector with its opening brace, exactly as written.
     * @param string $marker A declaration the body must hold, to tell this rule from another with the same selector.
     * @return string The body, between the braces.
     */
    private function body(string $scss, string $opening, string $marker): string {
        $offset = 0;
        while (($start = strpos($scss, $opening, $offset)) !== false) {
            $end = strpos($scss, "\n}", $start);
            $body = substr($scss, $start + strlen($opening), $end - $start - strlen($opening));
            if (str_contains($body, $marker)) {
                return $body;
            }
            $offset = $end;
        }
        $this->fail("No rule {$opening} holding {$marker}.");
    }

    /**
     * The names a body declares, in order, the semantic aliases only.
     *
     * @param string $body A rule's body.
     * @return array The names, with their leading dashes.
     */
    private function aliases(string $body): array {
        preg_match_all('/^\s*(--dim-ds-(?:surface|text|border|interactive|accent|focus-ring)[a-z-]*):/m', $body, $found);
        return array_values(array_diff($found[1], ['--dim-ds-focus-ring-width']));
    }

    /**
     * The dark rule compiled against core's colour mode helpers, as name => value.
     *
     * Only what the dark half reads is imported: the design system's tokens and Bootstrap's functions, the three
     * greys core's default preset takes from those tokens (theme/boost/scss/preset/default.scss, which
     * test_the_greys_are_the_presets_own holds), and colour-mode.scss itself. Importing the whole preset gives the
     * same answer at many times the cost.
     *
     * @return array The compiled declarations.
     */
    private function compiled(): array {
        global $CFG;
        $scss = $this->scss();
        preg_match_all('/^\$dim-ds-[a-z]+(?:-\d+)?: #[0-9a-f]{3,6};$/m', $scss, $primitives);
        $this->assertGreaterThan(20, count($primitives[0]), 'Precondition: the primitives are Sass variables.');

        $compiler = new \core_scss();
        $compiler->setImportPaths([$CFG->dirroot . '/theme/boost/scss']);
        $compiler->append_raw_scss(
            '@import "design-system-tokens"; @import "' . $CFG->root . '/lib/bundles/bootstrap/scss/functions";'
            . "\n\$prefix: bs-;\n\$white: \$mds-color-gray-white;\n\$gray-800: \$mds-color-gray-800;\n"
            . "\$gray-900: \$mds-color-gray-900;\n@import \"moodle/colour-mode\";\n"
            . implode("\n", $primitives[0])
            . "\n.dark {" . $this->body($scss, self::DARK_RULE, '--dim-ds-surface-page') . "\n}"
        );
        preg_match_all('/(--dim-ds-[a-z0-9-]+):\s*([^;]+);/', $compiler->to_css(), $found, PREG_SET_ORDER);
        $values = [];
        foreach ($found as [, $name, $value]) {
            $values[$name] = trim($value);
        }
        return $values;
    }

    /**
     * A compiled value as #rrggbb, through the primitive it names when it names one.
     *
     * @param array $values The compiled declarations.
     * @param string $name The alias.
     * @return string The colour.
     */
    private function hex(array $values, string $name): string {
        $value = $values[$name] ?? '';
        if (preg_match('/^var\(--dim-ds-([a-z]+-\d+)\)$/', $value, $primitive)) {
            preg_match('/^\$dim-ds-' . $primitive[1] . ': (#[0-9a-f]{6});$/m', $this->scss(), $hex);
            $value = $hex[1] ?? '';
        }
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $value, "{$name} compiles to a colour.");
        return $value;
    }

    /**
     * A palette primitive of post.scss as #rrggbb, a three-digit spelling expanded.
     *
     * @param string $name The primitive's name after the prefix, e.g. 'blue-700'.
     * @return string The colour.
     */
    private function primitive(string $name): string {
        preg_match('/^\$dim-ds-' . preg_quote($name, '/') . ': #([0-9a-f]{3}|[0-9a-f]{6});$/m', $this->scss(), $hex);
        $this->assertNotEmpty($hex, "Precondition: \$dim-ds-{$name} is a primitive of post.scss.");
        $digits = $hex[1];
        if (strlen($digits) === 3) {
            $digits = $digits[0] . $digits[0] . $digits[1] . $digits[1] . $digits[2] . $digits[2];
        }
        return '#' . $digits;
    }

    /**
     * Every semantic alias is declared for the dark page, or is named here as the same in both modes.
     *
     * An alias added to the light block and not to the dark one keeps its light value on a dark page, which is the
     * defect this rule exists for; the list of exceptions is checked against the light block too, so a name dropped
     * from the theme cannot stay on it and excuse nothing.
     *
     * @return void
     */
    public function test_every_semantic_alias_has_a_dark_half(): void {
        $scss = $this->scss();
        $light = $this->aliases($this->body($scss, "\n:root {", '--dim-ds-surface-page'));
        $dark = $this->aliases($this->body($scss, self::DARK_RULE, '--dim-ds-surface-page'));

        $this->assertContains('--dim-ds-text-link', $light, 'Control: the light block is the one read.');
        $this->assertSame(array_unique($dark), $dark, 'Each name once in the dark rule.');
        $this->assertSame([], array_diff(self::SAME_IN_BOTH, $light), 'Every exception is a real alias.');
        $this->assertSame([], array_intersect(self::SAME_IN_BOTH, $dark), 'An exception is not also turned.');
        $this->assertSame([], array_diff($dark, $light), 'The dark rule declares no alias the light block lacks.');
        $this->assertSame(
            [],
            array_values(array_diff($light, $dark, self::SAME_IN_BOTH)),
            'Every alias turns with the page, or is named as the same in both modes.'
        );
    }

    /**
     * The dark rule is read on the html element's mode, on the three roots the family tokens use.
     *
     * The attribute is read on the html element alone, so markup under a nested data-bs-theme still gets the
     * page's tokens; the dialogue core/modal appends to body is a root of its own for a value derived from a
     * --bs-* one.
     *
     * @return void
     */
    public function test_the_dark_rule_sits_on_the_three_roots_of_the_html_mode(): void {
        $scss = $this->scss();
        $this->assertSame(1, substr_count($scss, self::DARK_RULE . "\n    --dim-ds-surface-page:"));
        $this->assertSame(2, preg_match_all('/^\s*--dim-ds-text-link:/m', $scss), 'The link ink: the light root and the dark one.');
    }

    /**
     * The pairs the dark half forms, and the contrast each must reach.
     *
     * @return array ink, ground, minimum.
     */
    public static function dark_pairs(): array {
        $pairs = [];
        foreach (['--dim-ds-surface-card', '--dim-ds-surface-page', '--dim-ds-surface-subtle'] as $ground) {
            $short = substr($ground, 17);
            $pairs["muted on {$short}"] = ['--dim-ds-text-muted', $ground, 4.5];
            $pairs["heading on {$short}"] = ['--dim-ds-text-heading', $ground, 4.5];
            $pairs["link on {$short}"] = ['--dim-ds-text-link', $ground, 4.5];
            $pairs["link hover on {$short}"] = ['--dim-ds-text-link-hover', $ground, 7.0];
            $pairs["success on {$short}"] = ['--dim-ds-text-success', $ground, 4.5];
            $pairs["focus ring on {$short}"] = ['--dim-ds-focus-ring', $ground, 3.0];
            $pairs["strong line on {$short}"] = ['--dim-ds-border-strong', $ground, 3.0];
            $pairs["rating star on {$short}"] = ['--dim-ds-accent-rating', $ground, 3.0];
        }
        $pairs['ink on the tint'] = ['--dim-ds-text-on-tint', '--dim-ds-interactive-primary-tint', 4.5];
        $pairs['link on the tint'] = ['--dim-ds-text-link', '--dim-ds-interactive-primary-tint', 4.5];
        return $pairs;
    }

    /**
     * Every ink of the dark half reads on every dark surface it is drawn on.
     *
     * Text 4.5:1 (7:1 for a link's hover, as core asks of its own), a ring or a control's line 3:1 (WCAG 1.4.11).
     *
     * @param string $ink The ink's alias.
     * @param string $ground The surface's alias.
     * @param float $minimum The contrast the pair must reach.
     * @return void
     */
    #[DataProvider('dark_pairs')]
    public function test_every_dark_pair_reads(string $ink, string $ground, float $minimum): void {
        $values = $this->compiled();
        $ratio = branding::contrast($this->hex($values, $ground), $this->hex($values, $ink));
        $this->assertGreaterThanOrEqual($minimum, $ratio, sprintf('%s on %s reads %.2f:1.', $ink, $ground, $ratio));
    }

    /**
     * The surfaces keep their order on the dark page: the page, then the recessed, then the card, then the strong one.
     *
     * The control is the light page, whose order runs the other way: white cards over a light grey page.
     *
     * @return void
     */
    public function test_the_dark_surfaces_keep_their_order(): void {
        $values = $this->compiled();
        $order = ['--dim-ds-surface-page', '--dim-ds-surface-subtle', '--dim-ds-surface-card', '--dim-ds-surface-strong'];
        $lightness = array_map(fn($name) => branding::contrast('#000000', $this->hex($values, $name)), $order);
        $sorted = $lightness;
        sort($sorted);
        $this->assertSame($sorted, $lightness, 'Darkest first: ' . implode(', ', $order) . '.');
        $this->assertNotSame($lightness[2], $lightness[3], 'The strong surface stands out from the card.');
    }

    /**
     * The two aliases added with the dark half keep the light pairs their components drew before.
     *
     * They replaced primitives read directly, so the light page must not move; the pair is measured on the light
     * surfaces they sit on.
     *
     * @return void
     */
    public function test_the_new_aliases_keep_the_light_pairs(): void {
        $scss = $this->scss();
        $light = $this->body($scss, "\n:root {", '--dim-ds-surface-page');
        $this->assertStringContainsString('--dim-ds-text-on-tint: var(--dim-ds-blue-700);', $light);
        $this->assertStringContainsString('--dim-ds-text-success: var(--dim-ds-green-700);', $light);
        // Changes that must make it fail: a lighter blue-700 or green-700, or a darker blue-050 or gray-100.
        $this->assertGreaterThanOrEqual(
            4.5,
            branding::contrast($this->primitive('blue-050'), $this->primitive('blue-700')),
            'On-tint ink on the light tint.'
        );
        $this->assertGreaterThanOrEqual(
            4.5,
            branding::contrast($this->primitive('gray-100'), $this->primitive('green-700')),
            'Success ink on the subtle surface.'
        );
    }

    /**
     * The rating's amber, derived from the palette in the light block, clears 3:1 on the three light surfaces.
     *
     * Its light value is a Sass expression over a primitive, not a primitive or an alias of one, so it is compiled
     * here with the primitives and measured against the light surfaces' own primitives.
     *
     * @return void
     */
    public function test_the_rating_amber_reads_on_the_light_surfaces(): void {
        $scss = $this->scss();
        $light = $this->body($scss, "\n:root {", '--dim-ds-surface-page');
        $this->assertSame(1, preg_match('/^\s*--dim-ds-accent-rating: (#\{[^;]+\});$/m', $light, $declared));
        preg_match_all('/^\$dim-ds-[a-z]+(?:-\d+)?: #[0-9a-f]{3,6};$/m', $scss, $primitives);
        $compiler = new \core_scss();
        $compiler->append_raw_scss(implode("\n", $primitives[0]) . "\n.light { --amber: {$declared[1]}; }");
        $this->assertSame(1, preg_match('/--amber:\s*(#[0-9a-f]{6})/', $compiler->to_css(), $amber));
        $grounds = [
            $this->primitive('white') => 'the card',
            $this->primitive('gray-050') => 'the page',
            $this->primitive('gray-100') => 'the subtle surface',
        ];
        foreach ($grounds as $ground => $name) {
            $ratio = branding::contrast($ground, $amber[1]);
            $this->assertGreaterThanOrEqual(3.0, $ratio, sprintf('The amber %s on %s reads %.2f:1.', $amber[1], $name, $ratio));
        }
    }

    /**
     * No ink of the theme's sheets reads a blue, grey or green primitive directly, but the pairs on a fixed plate.
     *
     * A primitive is the same in both modes, so an ink read from one stays navy on the dark page; the ink a ground of
     * the page needs is a semantic alias, which turns with it. The exceptions sit on a plate they state themselves,
     * the same in both modes: the poster's kind tag on its white plate. The control is that the exception is still
     * found where it is named.
     *
     * @return void
     */
    public function test_no_ink_reads_a_primitive_but_the_fixed_plates(): void {
        $allowed = [
            'category.scss .dim-poster__kind',
        ];
        $found = [];
        foreach (glob(dirname(__DIR__, 2) . '/scss/*.scss') as $path) {
            $lines = file($path, FILE_IGNORE_NEW_LINES);
            foreach ($lines as $i => $line) {
                if (!preg_match('/(?<![-\w])color:\s*var\(--dim-ds-(?:blue|gray|green)-\d+/', $line)) {
                    continue;
                }
                $selector = '?';
                for ($j = $i - 1; $j >= 0; $j--) {
                    if (preg_match('/^\s*([^{};\/]+?)\s*\{\s*$/', $lines[$j], $open)) {
                        $selector = $open[1];
                        break;
                    }
                }
                $found[] = basename($path) . ' ' . $selector;
            }
        }
        $this->assertEqualsCanonicalizing($allowed, $found, 'Inks read from a primitive: ' . implode('; ', $found));
    }

    /**
     * The Sass expression a family colour of post.scss compiles from, in one mode, under core's default preset.
     *
     * A family colour reads a Bootstrap property, whose value is one of Bootstrap's Sass variables in each mode, or a
     * palette primitive of post.scss, which is the same in both.
     *
     * @param string $block The family block of the mode.
     * @param string $name The family name after its prefix, e.g. 'surface'.
     * @param string $mode 'light' or 'dark'.
     * @return string The Sass expression.
     */
    private function family_sass(string $block, string $name, string $mode): string {
        $bootstrap = [
            'light' => [
                '--bs-body-bg' => '$body-bg',
                '--bs-body-color' => '$body-color',
                '--bs-primary-bg-subtle' => '$primary-bg-subtle',
                '--bs-primary-text-emphasis' => '$primary-text-emphasis',
            ],
            'dark' => [
                '--bs-body-bg' => '$body-bg-dark',
                '--bs-body-color' => '$body-color-dark',
                '--bs-primary-bg-subtle' => '$primary-bg-subtle-dark',
            ],
        ];
        $this->assertSame(
            1,
            preg_match('/^\s*--dimensions-' . preg_quote($name, '/') . ': var\((--[a-z0-9-]+)\);$/m', $block, $read),
            "Precondition: the {$mode} {$name} reads one token."
        );
        if (str_starts_with($read[1], '--dim-ds-')) {
            return '$' . substr($read[1], 2);
        }
        $this->assertArrayHasKey(
            $read[1],
            $bootstrap[$mode],
            "The {$mode} {$name} reads {$read[1]}, which this test cannot place."
        );
        return $bootstrap[$mode][$read[1]];
    }

    /**
     * Both inks a hovered row wears read on the hover and on the pressed ground, on the light and on the dark page.
     *
     * A ground is color-mix() of two family colours in sRGB, which is Sass's mix() at the same share, so each is compiled
     * here from the colours core's default preset gives the two: Bootstrap's body and primary tones from core's blue on
     * the light page, Bootstrap's dark body and the family's own dark brand ink on the dark one. The shares are read from
     * post.scss, so a deeper mix that loses an ink fails here (hover.md: 4.5:1 for the ink and the brand's ink).
     *
     * @return void
     */
    public function test_the_hover_grounds_keep_both_inks_readable(): void {
        global $CFG;
        $scss = $this->scss();
        $blocks = [
            'light' => $this->body($scss, "\n:root,\nbody,\n.modal {", '--dimensions-hover'),
            'dark' => $this->body($scss, self::DARK_RULE, '--dimensions-hover'),
        ];
        $probe = [];
        foreach ($blocks as $mode => $block) {
            foreach (['ink', 'brand-ink', 'surface'] as $name) {
                $probe[] = "--{$mode}-{$name}: #{" . $this->family_sass($block, $name, $mode) . '};';
            }
            foreach (['hover', 'pressed'] as $ground) {
                $this->assertSame(
                    1,
                    preg_match(
                        '/^\s*--dimensions-' . $ground . ': color-mix\(in srgb, var\(--dimensions-([a-z-]+)\) (\d+)%, '
                            . 'var\(--dimensions-([a-z-]+)\)\);$/m',
                        $block,
                        $mix
                    ),
                    "Precondition: the {$mode} {$ground} is a mix of two family colours."
                );
                $probe[] = "--{$mode}-{$ground}: #{mix(" . $this->family_sass($block, $mix[1], $mode) . ', '
                    . $this->family_sass($block, $mix[3], $mode) . ", {$mix[2]}%)};";
            }
        }
        preg_match_all('/^\$dim-ds-[a-z]+(?:-\d+)?: #[0-9a-f]{3,6};$/m', $scss, $primitives);
        $bootstrap = $CFG->root . '/lib/bundles/bootstrap/scss/';
        $compiler = new \core_scss();
        $compiler->setImportPaths([$CFG->dirroot . '/theme/boost/scss']);
        $compiler->append_raw_scss(
            '@import "design-system-tokens"; @import "' . $bootstrap . 'functions";'
            . "\n\$white: \$mds-color-gray-white;\n\$gray-100: \$mds-color-gray-100;\n\$gray-300: \$mds-color-gray-300;\n"
            . "\$gray-900: \$mds-color-gray-900;\n\$black: \$mds-color-gray-black;\n\$primary: \$mds-color-blue-500;\n"
            . "\$body-bg: \$gray-100;\n@import \"{$bootstrap}variables\"; @import \"{$bootstrap}variables-dark\";\n"
            . implode("\n", $primitives[0])
            . "\n.probe {\n" . implode("\n", $probe) . "\n}"
        );
        preg_match_all(
            '/--((?:light|dark)-[a-z-]+):\s*#([0-9a-f]{6}|[0-9a-f]{3});/',
            $compiler->to_css(),
            $found,
            PREG_SET_ORDER
        );
        $colours = [];
        foreach ($found as [, $name, $digits]) {
            if (strlen($digits) === 3) {
                $digits = $digits[0] . $digits[0] . $digits[1] . $digits[1] . $digits[2] . $digits[2];
            }
            $colours[$name] = '#' . $digits;
        }
        $this->assertCount(10, $colours, 'Precondition: every probe compiled to a colour.');

        $measured = 0;
        foreach (['light', 'dark'] as $mode) {
            foreach (['hover', 'pressed'] as $ground) {
                $this->assertNotSame(
                    $colours["{$mode}-surface"],
                    $colours["{$mode}-{$ground}"],
                    "The {$mode} {$ground} is no surface."
                );
                foreach (['ink', 'brand-ink'] as $ink) {
                    $ratio = branding::contrast($colours["{$mode}-{$ground}"], $colours["{$mode}-{$ink}"]);
                    $this->assertGreaterThanOrEqual(
                        4.5,
                        $ratio,
                        sprintf('The %s %s on the %s ground reads %.2f:1.', $mode, $ink, $ground, $ratio)
                    );
                    $measured++;
                }
            }
        }
        $this->assertSame(8, $measured, 'Control: two inks on two grounds in two modes.');
    }

    /**
     * The greys the compile above declares are the ones core's default preset declares.
     *
     * The dark surfaces derive from them, so a preset that took them from somewhere else would leave this test
     * measuring a palette no page uses.
     *
     * @return void
     */
    public function test_the_greys_are_the_presets_own(): void {
        global $CFG;
        $preset = file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
        $this->assertNotFalse($preset, 'Precondition: core\'s preset is readable.');
        $lines = ['$white:    $mds-color-gray-white', '$gray-800: $mds-color-gray-800', '$gray-900: $mds-color-gray-900'];
        foreach ($lines as $line) {
            $this->assertStringContainsString($line, $preset);
        }
    }
}
