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
 * Theme Dimensions - Tests for the family tokens the sibling plugins read
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards the theme's half of the --dimensions-* contract with local_dimensions, block_dimensions and block_compass.
 *
 * The contract is a list of names, read by other repositories that no test here can run: a name
 * renamed, dropped or declared on one mode only is a plugin quietly falling back to its own look, and
 * nothing in this pipeline would notice. So the list is written down here, and the stylesheet
 * (scss/post.scss) and the public document (docs/tokens.md) are each read against it.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class family_tokens_test extends \basic_testcase {
    /** @var string[] The suffixes both plugins read, spelled as in their shared token block. */
    private const SHARED = [
        'surface', 'surface-alt', 'surface-inset', 'line', 'ink', 'ink-strong', 'ink-muted', 'ink-faint', 'accent',
        'accent-hover', 'brand-fill', 'brand-ink', 'brand-tint', 'brand-edge', 'on-brand-fill', 'focus-ring',
        'success-tint', 'success-edge', 'success-ink', 'warning-tint', 'warning-edge', 'warning-ink', 'danger-tint',
        'danger-edge', 'danger-ink', 'info-tint', 'info-edge', 'info-ink', 'neutral-tint', 'neutral-edge', 'neutral-ink',
        'shadow', 'scrim',
    ];

    /**
     * @var string[] The course card's suffixes after "card-", read by every plugin that draws a course card (the theme,
     *      block_compass, local_dimensions), each through its own names.
     */
    private const CARD = [
        'radius', 'border-width', 'border', 'surface', 'ink', 'ink-muted', 'title-hover', 'cover-height',
        'cover-fallback', 'cover-overlay', 'chip-bg', 'chip-ink', 'extra-bg', 'extra-icon', 'track', 'fill',
        'track-height', 'done', 'done-bg', 'done-ink', 'done-edge', 'shadow', 'shadow-hover', 'focus-ring',
        'focus-width', 'locked-opacity', 'close-bg', 'close-bg-hover', 'close-edge', 'transition',
    ];

    /** @var string[] The card suffixes whose value is a colour: a token, or a token's channels at an alpha. */
    private const CARD_COLOURS = [
        'border', 'surface', 'ink', 'ink-muted', 'title-hover', 'cover-fallback', 'chip-bg', 'chip-ink', 'extra-bg',
        'extra-icon', 'track', 'fill', 'done', 'done-bg', 'done-ink', 'focus-ring', 'close-bg', 'close-bg-hover',
        'close-edge',
    ];

    /** @var string[] The motion suffixes local_dimensions alone reads; its state suffixes come from STATES. */
    private const MOTION = ['motion-fast', 'motion-base', 'motion-ease'];

    /**
     * @var array The fleet Design System's scales, suffix => the design system token the light page reads: the control
     *      line, the ring's width, the radius steps and the elevations, read by every plugin of the family.
     */
    private const SYSTEM = [
        'control-line' => '--bs-gray-500',
        'focus-width' => '--dim-ds-focus-ring-width',
        'radius-xs' => '--dim-ds-radius-xs',
        'radius-sm' => '--dim-ds-radius-sm',
        'radius-md' => '--dim-ds-radius-md',
        'radius-lg' => '--dim-ds-radius-lg',
        'radius-xl' => '--dim-ds-radius-xl',
        'elevation-sm' => '--dim-ds-shadow-sm',
        'elevation-md' => '--dim-ds-shadow-md',
        'elevation-lg' => '--dim-ds-shadow-lg',
    ];

    /** @var int[] The radius scale in pixels, xs to xl: Moodle Design System 5.3.0's steps. */
    private const RADIUS_SCALE = [4, 6, 8, 12, 16];

    /** @var float The least contrast of a state's edge with its own ground: Bootstrap's subtle border on its subtle fill. */
    private const EDGE_FLOOR = 1.25;

    /** @var array The enrolment states local_dimensions reads => the showcase's badge state wearing the same pair. */
    private const STATES = [
        'enrolled' => 'enrolled',
        'guest' => 'free',
        'neutral' => 'none',
        'open' => 'open',
        'pending' => 'pending',
        'scheduled' => 'scheduled',
    ];

    /**
     * @var string[] The theme's own names under the same prefix, outside the contract: the navbar's band and pills, the
     *      logo variants for dark surfaces (their sources, which the extra SCSS callback overrides, and what a surface reads),
     *      and the reading bar's band progress and navbar offset (scss/readingbar.scss).
     */
    private const OWN = [
        '--dimensions-navbar-band',
        '--dimensions-backdrop',
        '--dimensions-pill-border',
        '--dimensions-pill-hover',
        '--dimensions-pill-filled-ink',
        '--dimensions-logo-dark-src',
        '--dimensions-logo-dark-shift',
        '--dimensions-logo-compact-dark-src',
        '--dimensions-logo-compact-dark-shift',
        '--dimensions-logo-dark',
        '--dimensions-logo-shift',
        '--dimensions-logo-compact-dark',
        '--dimensions-logo-compact-shift',
        '--dimensions-band-progress',
        '--dimensions-navbar-offset',
    ];

    /** @var string[] The three roots of the light block, in order. */
    private const LIGHT_ROOTS = [':root', 'body', '.modal'];

    /** @var string[] The three roots of the dark block, in order. */
    private const DARK_ROOTS = [
        ':root[data-bs-theme="dark"]',
        ':root[data-bs-theme="dark"] body',
        ':root[data-bs-theme="dark"] .modal',
    ];

    /**
     * The local-only suffixes: the three of motion and three of each state.
     *
     * @return string[]
     */
    private static function local_only(): array {
        $suffixes = self::MOTION;
        foreach (array_keys(self::STATES) as $state) {
            foreach (['bg', 'edge', 'ink'] as $part) {
                $suffixes[] = "state-{$state}-{$part}";
            }
        }
        return $suffixes;
    }

    /**
     * The course card's names, with their prefix.
     *
     * @return string[]
     */
    private static function card(): array {
        return array_map(static fn(string $suffix): string => '--dimensions-card-' . $suffix, self::CARD);
    }

    /**
     * The Design System's names, with their prefix.
     *
     * @return string[]
     */
    private static function system(): array {
        return array_map(static fn(string $suffix): string => '--dimensions-' . $suffix, array_keys(self::SYSTEM));
    }

    /**
     * Every name of the contract, shared first, the card's last.
     *
     * @return string[] The custom property names, with their prefix.
     */
    private static function names(): array {
        return array_merge(
            array_map(
                static fn(string $suffix): string => '--dimensions-' . $suffix,
                array_merge(self::SHARED, self::local_only())
            ),
            self::system(),
            self::card()
        );
    }

    /**
     * The relative luminance of a colour, as WCAG 2 defines it.
     *
     * @param string $hex Six hexadecimal digits after a hash.
     * @return float
     */
    private static function luminance(string $hex): float {
        $luminance = 0.0;
        foreach ([0.2126, 0.7152, 0.0722] as $i => $weight) {
            $channel = hexdec(substr($hex, 1 + 2 * $i, 2)) / 255;
            $luminance += $weight * ($channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4);
        }
        return $luminance;
    }

    /**
     * A state's edge as a colour, and the pair it was mixed from when it is a mix.
     *
     * An edge is a palette token, or Sass's mix() of two colours at a weight, each a palette variable or a literal.
     *
     * @param string $value The declared value.
     * @param array $palette The palette, from palette().
     * @return array ['hex' => string, 'ink' => ?string, 'ground' => ?string], the last two null for a token.
     */
    private function edge(string $value, array $palette): array {
        $colour = '(\$dim-ds-[a-z0-9-]+|#[0-9a-f]{6})';
        if (!preg_match('~^#\{mix\(' . $colour . ', ' . $colour . ', (\d+)%\)\}$~', $value, $mix)) {
            return ['hex' => $this->hex($value, $palette), 'ink' => null, 'ground' => null];
        }
        $resolve = static fn(string $one): string => $one[0] === '$' ? $palette['--' . substr($one, 1)] : $one;
        [$ink, $ground, $weight] = [$resolve($mix[1]), $resolve($mix[2]), (int) $mix[3] / 100];
        $hex = '#';
        foreach ([1, 3, 5] as $at) {
            $hex .= sprintf('%02x', (int) round(
                hexdec(substr($ink, $at, 2)) * $weight + hexdec(substr($ground, $at, 2)) * (1 - $weight)
            ));
        }
        return ['hex' => $hex, 'ink' => $ink, 'ground' => $ground];
    }

    /**
     * A stylesheet of the theme with its comments removed.
     *
     * @param string $file The file name under scss/.
     * @return string The source.
     */
    private function stylesheet(string $file): string {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scss/' . $file);
        $this->assertNotFalse($source, "Precondition: scss/{$file} is readable.");
        $source = preg_replace('~/\*.*?\*/~s', '', $source);
        return preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * The rules at the root of a stylesheet: each one's selectors and its body.
     *
     * Walks the braces, because a regular expression cannot tell a nested rule from a top-level one.
     *
     * @param string $scss The source, comments removed.
     * @return array A list of ['selectors' => string[], 'body' => string].
     */
    private function root_rules(string $scss): array {
        $rules = [];
        $depth = 0;
        $buffer = '';
        $selector = '';
        $length = strlen($scss);
        for ($i = 0; $i < $length; $i++) {
            $char = $scss[$i];
            if ($char === '{') {
                if ($depth === 0) {
                    $selector = $buffer;
                    $buffer = '';
                } else {
                    $buffer .= $char;
                }
                $depth++;
            } else if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $selectors = array_map(
                        static fn(string $one): string => trim((string) preg_replace('~\s+~', ' ', $one)),
                        explode(',', $selector)
                    );
                    $rules[] = ['selectors' => $selectors, 'body' => $buffer];
                    $buffer = '';
                } else {
                    $buffer .= $char;
                }
            } else if ($char === ';' && $depth === 0) {
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        return $rules;
    }

    /**
     * The --dimensions-* declarations of a rule body, the theme's own names aside (OWN).
     *
     * @param string $body The body.
     * @return array A list of [name, value] in source order.
     */
    private function family_declarations(string $body): array {
        preg_match_all('~(?<![-\w])(--dimensions-[a-z0-9-]+)\s*:\s*([^;]+);~', $body, $matches, PREG_SET_ORDER);
        $declarations = [];
        foreach ($matches as $match) {
            if (!in_array($match[1], self::OWN, true)) {
                $declarations[] = [$match[1], trim($match[2])];
            }
        }
        return $declarations;
    }

    /**
     * The light and the dark block of post.scss: the root rules declaring any family name, by mode.
     *
     * @return array ['light' => rules, 'dark' => rules], each rule as root_rules() gives it.
     */
    private function blocks(): array {
        $blocks = ['light' => [], 'dark' => []];
        foreach ($this->root_rules($this->stylesheet('post.scss')) as $rule) {
            if (!$this->family_declarations($rule['body'])) {
                continue;
            }
            $dark = str_contains(implode(',', $rule['selectors']), 'data-bs-theme="dark"');
            $blocks[$dark ? 'dark' : 'light'][] = $rule;
        }
        return $blocks;
    }

    /**
     * The design system's tokens of post.scss as the light page has them: name => value.
     *
     * A primitive is written once, as a Sass variable, and declared through it, so its interpolation is resolved here;
     * a semantic alias is declared again for the dark page further down, so the first declaration of a name is kept.
     *
     * @return array
     */
    private function palette(): array {
        $scss = $this->stylesheet('post.scss');
        preg_match_all('~^\$(dim-ds-[a-z0-9-]+):\s*(#[0-9a-f]{3,6});$~m', $scss, $sass);
        $variables = array_combine($sass[1], $sass[2]);
        preg_match_all('~(?<![-\w])(--dim-ds-[a-z0-9-]+)\s*:\s*([^;]+);~', $scss, $matches);
        $palette = [];
        foreach ($matches[1] as $i => $name) {
            $value = preg_replace_callback(
                '~^#\{\$(dim-ds-[a-z0-9-]+)\}$~',
                fn($found) => $variables[$found[1]] ?? $found[0],
                trim($matches[2][$i])
            );
            $palette[$name] ??= $value;
        }
        return $palette;
    }

    /**
     * A value as a colour the design system would print: a palette token is resolved, a literal kept, both lower case.
     *
     * @param string $value A declared value.
     * @param array $palette The palette, from palette().
     * @return string The hex, or the value unchanged when it is neither.
     */
    private function hex(string $value, array $palette): string {
        if (preg_match('~^var\((--dim-ds-[a-z0-9-]+)(?:,[^)]*)?\)$~', $value, $match) && isset($palette[$match[1]])) {
            return $this->hex($palette[$match[1]], $palette);
        }
        return strtolower($value);
    }

    /**
     * The list holds 33 shared names, 21 local-only ones, the Design System's 10 and the card's 30, none twice.
     *
     * Every other test reads the stylesheet and the document against these constants, so a typo
     * here would be copied into both and pass.
     */
    public function test_the_contract_is_thirty_three_shared_twenty_one_local_and_thirty_card_names(): void {
        $this->assertCount(33, self::SHARED);
        $this->assertCount(21, self::local_only());
        $this->assertCount(30, array_unique(self::card()));
        $this->assertSame([], array_values(array_diff(self::CARD_COLOURS, self::CARD)), 'Every colour is a card suffix.');
        $this->assertCount(10, self::SYSTEM);
        $this->assertCount(94, array_unique(self::names()));
    }

    /**
     * Every name is declared exactly once for the light page and once for the dark one, and nothing else is.
     *
     * Two declarations in one mode would leave the later winning in silence; none in the dark mode would
     * leave a token at its light value on a dark page, which for the chained tokens still reads right and
     * for the pills does not. Every other stylesheet of the theme declares no family name at all.
     */
    public function test_every_name_is_declared_once_for_light_and_once_for_dark(): void {
        $blocks = $this->blocks();
        foreach (['light', 'dark'] as $mode) {
            $declared = [];
            foreach ($blocks[$mode] as $rule) {
                foreach ($this->family_declarations($rule['body']) as [$name]) {
                    $declared[] = $name;
                }
            }
            $counts = array_count_values($declared);
            foreach (self::names() as $name) {
                $this->assertSame(1, $counts[$name] ?? 0, "{$name} is declared once in the {$mode} block.");
            }
            $this->assertSame([], array_values(array_diff($declared, self::names())), "The {$mode} block declares nothing else.");
        }

        foreach (glob(dirname(__DIR__, 2) . '/scss/*.scss') as $path) {
            $file = basename($path);
            if ($file === 'post.scss') {
                continue;
            }
            $this->assertSame(
                0,
                count($this->family_declarations($this->stylesheet($file))),
                "scss/{$file} declares a family name, which only post.scss does."
            );
        }
    }

    /**
     * The light block sits on :root, body and the dialogue's root, and the dark block on the same three under the html's mode.
     *
     * A token chained to a --bs-* property is computed where it is declared, so a root left out is a
     * place a plugin reads a value computed somewhere else; and the dark mode is read on the html element
     * alone, or an element carrying its own data-bs-theme would turn every plugin inside it dark.
     */
    public function test_the_declarations_sit_on_the_three_roots(): void {
        $blocks = $this->blocks();
        $this->assertCount(1, $blocks['light'], 'One light rule.');
        $this->assertCount(1, $blocks['dark'], 'One dark rule.');
        $this->assertSame(self::LIGHT_ROOTS, $blocks['light'][0]['selectors']);
        $this->assertSame(self::DARK_ROOTS, $blocks['dark'][0]['selectors']);
    }

    /**
     * No value is a colour literal the design system has a token for, and every token it names exists.
     *
     * The theme's rule is one place per colour: a value is a Bootstrap property, a design system token
     * of post.scss, another family name, or a Bootstrap or design system channel set at an alpha. The one
     * literal allowed is a dark pill's colour that the palette does not hold, which the next test ties
     * to the showcase's badge.
     */
    public function test_no_value_is_a_literal_the_palette_has_a_token_for(): void {
        $palette = $this->palette();
        $blocks = $this->blocks();
        $literals = 0;
        $tokens = 0;
        foreach (['light', 'dark'] as $mode) {
            $declarations = $this->family_declarations($blocks[$mode][0]['body']);
            $names = array_column($declarations, 0);
            foreach ($declarations as [$name, $value]) {
                if (in_array($name, self::card(), true) || in_array($name, self::system(), true)) {
                    // The card's and the scales' lengths, shadows and gradients have tests of their own.
                    continue;
                }
                if (str_ends_with($name, '-edge') && str_starts_with($name, '--dimensions-state-')) {
                    // A state's edge is derived from its own pair, and measured against it in a test of its own.
                    continue;
                }
                if (preg_match('~^#[0-9a-f]{6}$~', $value)) {
                    $literals++;
                    $this->assertSame('dark', $mode, "{$name}: a literal on the light page.");
                    $this->assertMatchesRegularExpression('~^--dimensions-state-[a-z]+-(bg|ink)$~', $name, "{$name}: only a pill.");
                    $this->assertNotContains($value, $palette, "{$name}: {$value} is a palette colour; name its token.");
                    continue;
                }
                $this->assertMatchesRegularExpression(
                    '~^(var\((--[a-z0-9-]+)\)|rgba\(var\((--[a-z0-9-]+-rgb)\), \.\d+\))$~',
                    $value,
                    "{$name}: {$value} is neither a token nor a token's channels at an alpha."
                );
                preg_match('~var\((--[a-z0-9-]+)~', $value, $reference);
                $read = $reference[1];
                if (str_starts_with($read, '--dim-ds-')) {
                    $tokens++;
                    $this->assertArrayHasKey($read, $palette, "{$name} reads {$read}, which post.scss does not declare.");
                } else if (str_starts_with($read, '--dimensions-')) {
                    $this->assertContains($read, $names, "{$name} reads {$read}, which its own block does not declare.");
                } else {
                    $this->assertStringStartsWith('--bs-', $read, "{$name} reads {$read}, which nothing declares.");
                }
            }
        }
        $this->assertGreaterThan(0, $literals, 'Control: the dark pills do carry their own colours.');
        $this->assertGreaterThan(0, $tokens, 'Control: some value reads the design system.');

        $this->assertSame(
            '#070093',
            $palette['--dim-ds-blue-600'],
            'Precondition: the elevation tint is the design system\'s navy.'
        );
        $this->assertSame(
            '7, 0, 147',
            $palette['--dim-ds-shadow-rgb'],
            'The shadow channels are the navy\'s, the tint of every elevation token.'
        );
    }

    /**
     * The card's values hold no colour literal: a colour is a token or a token's channels, the rest a measure.
     *
     * A card token is read by three plugins, and a colour written here as a literal is one no flavour and no colour mode
     * moves. A colour suffix is a token or a token's channels at an alpha, as the shared names are; a measure (a length,
     * a duration, an opacity) may be a literal; a shadow or a gradient draws its colours from channels. The focus ring is
     * the family's ring in both modes, never a colour of its own.
     */
    public function test_the_card_values_are_tokens_or_measures(): void {
        $palette = $this->palette();
        $blocks = $this->blocks();
        $colours = array_map(static fn(string $suffix): string => '--dimensions-card-' . $suffix, self::CARD_COLOURS);
        foreach (['light', 'dark'] as $mode) {
            $declarations = $this->family_declarations($blocks[$mode][0]['body']);
            $names = array_column($declarations, 0);
            $values = array_column($declarations, 1, 0);
            $checked = 0;
            foreach (self::card() as $name) {
                $value = $values[$name];
                $checked++;
                $this->assertDoesNotMatchRegularExpression('~#[0-9a-fA-F]{3,8}\b~', $value, "{$name}: a colour literal.");
                preg_match_all('~rgba?\(([^()]*(?:\([^()]*\))?[^()]*)\)~', $value, $alphas);
                foreach ($alphas[1] as $inside) {
                    $this->assertMatchesRegularExpression(
                        '~^var\(--[a-z0-9-]+-rgb\), \.\d+$~',
                        $inside,
                        "{$name}: a colour at an alpha reads a token's channels."
                    );
                }
                if (in_array($name, $colours, true)) {
                    $this->assertMatchesRegularExpression(
                        '~^(var\((--[a-z0-9-]+)\)|rgba\(var\((--[a-z0-9-]+-rgb)\), \.\d+\))$~',
                        $value,
                        "{$name}: {$value} is neither a token nor a token's channels at an alpha."
                    );
                } else {
                    $this->assertMatchesRegularExpression(
                        '~^(var\(--[a-z0-9-]+\)|\.?\d+(px|ms)?|0 \d+px \d+px rgba\(.*\)|linear-gradient\(.*\))$~',
                        $value,
                        "{$name}: {$value} is no token, measure, shadow or gradient."
                    );
                }
                preg_match_all('~var\((--[a-z0-9-]+)~', $value, $reads);
                foreach ($reads[1] as $read) {
                    if (str_starts_with($read, '--dim-ds-')) {
                        $this->assertArrayHasKey($read, $palette, "{$name} reads {$read}, which post.scss does not declare.");
                    } else if (str_starts_with($read, '--dimensions-')) {
                        $this->assertContains($read, $names, "{$name} reads {$read}, which its own block does not declare.");
                    } else {
                        $this->assertStringStartsWith('--bs-', $read, "{$name} reads {$read}, which nothing declares.");
                    }
                }
            }
            $this->assertSame(30, $checked, "Control: the {$mode} block's 30 card values were read.");
            $this->assertSame('var(--dimensions-focus-ring)', $values['--dimensions-card-focus-ring'], "The {$mode} ring.");
        }
    }

    /**
     * Each enrolment state wears the showcase's badge of the same family, in both modes.
     *
     * The light half names the same design system token as the badge; the dark half resolves to the badge's
     * colour, through the palette where the palette holds it. Read from scss/category.scss itself, so a pill
     * the showcase re-colours and this block does not is a red test rather than two products.
     */
    public function test_the_states_are_the_showcases_badges_in_both_modes(): void {
        $palette = $this->palette();
        $category = $this->stylesheet('category.scss');
        $split = strpos($category, ':root[data-bs-theme="dark"]');
        $this->assertNotFalse($split, 'Precondition: the showcase has a dark half.');
        $lighthalf = substr($category, 0, $split);
        $this->assertSame(
            1,
            preg_match('~:root\[data-bs-theme="dark"\][^{]*\{([^}]*--dim-badge-enrolled-bg[^}]*)\}~', $category, $darkbadges),
            'Precondition: the dark badges are one block.'
        );

        $blocks = $this->blocks();
        $light = array_column($this->family_declarations($blocks['light'][0]['body']), 1, 0);
        $dark = array_column($this->family_declarations($blocks['dark'][0]['body']), 1, 0);
        foreach (self::STATES as $state => $badge) {
            foreach (['bg' => 'bg', 'ink' => 'fg'] as $part => $badgepart) {
                $name = "--dimensions-state-{$state}-{$part}";
                $this->assertSame(
                    1,
                    preg_match(
                        '~--dim-badge-' . $badge . '-' . $badgepart . ':\s*var\((--dim-ds-[a-z0-9-]+),~',
                        $lighthalf,
                        $token
                    ),
                    "Precondition: the light {$badge} badge's {$badgepart} reads a design system token."
                );
                $this->assertSame("var({$token[1]})", $light[$name], "{$name}: the light {$badge} badge's token.");

                $this->assertSame(
                    1,
                    preg_match('~--dim-badge-' . $badge . '-' . $badgepart . ':\s*(#[0-9a-f]{3,6});~', $darkbadges[1], $colour),
                    "Precondition: the dark {$badge} badge's {$badgepart} is a colour."
                );
                $this->assertSame(
                    $this->hex($colour[1], $palette),
                    $this->hex($dark[$name], $palette),
                    "{$name}: the dark {$badge} badge's colour."
                );
            }
        }
    }

    /**
     * Every state's edge shows as a one pixel line against its own ground, in both modes.
     *
     * A status label draws its state's edge around its state's ground, so an edge equal to the ground, or one step
     * from it, is a label with no edge. Each edge is darker than its ground on the light page and lighter on the
     * dark one (a darker line there melts into the page), at no less than the contrast of Bootstrap's subtle border
     * on its subtle fill; and an edge mixed from a pair is mixed from its own state's ink and ground, so a pill
     * the showcase re-colours takes its edge along.
     */
    public function test_every_state_edge_shows_against_its_ground(): void {
        $palette = $this->palette();
        $blocks = $this->blocks();
        $mixed = 0;
        foreach (['light', 'dark'] as $mode) {
            $values = array_column($this->family_declarations($blocks[$mode][0]['body']), 1, 0);
            foreach (array_keys(self::STATES) as $state) {
                $ground = $this->hex($values["--dimensions-state-{$state}-bg"], $palette);
                $ink = $this->hex($values["--dimensions-state-{$state}-ink"], $palette);
                $edge = $this->edge($values["--dimensions-state-{$state}-edge"], $palette);
                $label = "The {$mode} {$state} edge";
                $this->assertMatchesRegularExpression('~^#[0-9a-f]{6}$~', $ground, "Precondition: {$label}'s ground is a colour.");
                $this->assertMatchesRegularExpression('~^#[0-9a-f]{6}$~', $edge['hex'], "{$label} is a colour.");
                if ($edge['ink'] !== null) {
                    $mixed++;
                    $this->assertSame($ink, $edge['ink'], "{$label} is mixed from its own ink.");
                    $this->assertSame($ground, $edge['ground'], "{$label} is mixed into its own ground.");
                }
                [$edgelight, $groundlight] = [self::luminance($edge['hex']), self::luminance($ground)];
                $contrast = (max($edgelight, $groundlight) + 0.05) / (min($edgelight, $groundlight) + 0.05);
                $this->assertGreaterThanOrEqual(
                    self::EDGE_FLOOR,
                    $contrast,
                    "{$label} ({$edge['hex']}) reads " . round($contrast, 2) . ':1 on its ground (' . $ground . ').'
                );
                if ($mode === 'light') {
                    $this->assertLessThan($groundlight, $edgelight, "{$label} is darker than its ground.");
                } else {
                    $this->assertGreaterThan($groundlight, $edgelight, "{$label} is lighter than its ground.");
                }
            }
        }
        $this->assertSame(10, $mixed, 'Control: the five tints carry a mixed edge in each mode.');
        $this->assertEqualsWithDelta(
            1.263253,
            (self::luminance('#888888') + 0.05) / (self::luminance('#777777') + 0.05),
            0.0001,
            'Control: the contrast maths, on a pair of tests/fixtures/contrast_pairs.json.'
        );
    }

    /**
     * The Design System's scales read the theme's own steps, the same in both modes but for the elevations.
     *
     * A plugin's fallback is the fleet's literal for each step (4 to 16 px, a 2 px ring, the navy elevations), so a
     * name missing here or pointing at another step is the theme looking unlike what the Design System documents.
     * The dark page keeps each elevation's offset and blur and draws it in black, because a navy tint cannot draw
     * on a dark ground; the resting step is the dark course card's shadow.
     */
    public function test_the_design_system_scales_read_the_themes_own_steps(): void {
        $palette = $this->palette();
        $blocks = $this->blocks();
        $light = array_column($this->family_declarations($blocks['light'][0]['body']), 1, 0);
        $dark = array_column($this->family_declarations($blocks['dark'][0]['body']), 1, 0);

        foreach (self::SYSTEM as $suffix => $token) {
            $name = '--dimensions-' . $suffix;
            $this->assertSame("var({$token})", $light[$name], "{$name} on the light page.");
            if (str_starts_with($token, '--dim-ds-')) {
                $this->assertArrayHasKey($token, $palette, "{$name} reads {$token}, which post.scss does not declare.");
            }
            if (!str_starts_with($suffix, 'elevation-')) {
                $this->assertSame($light[$name], $dark[$name], "{$name} is the same step on the dark page.");
            }
        }

        foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $i => $step) {
            $this->assertSame(self::RADIUS_SCALE[$i] . 'px', $palette["--dim-ds-radius-{$step}"], "The {$step} radius.");
        }
        $this->assertSame('3px', $palette['--dim-ds-focus-ring-width'], 'The ring is the theme\'s 3 px.');

        $drawn = 0;
        foreach (['sm', 'md', 'lg'] as $step) {
            $navy = $palette["--dim-ds-shadow-{$step}"];
            $this->assertSame(
                1,
                preg_match('~^(0 \d+px \d+px) rgba\(7, 0, 147, \.\d+\)~', $navy, $lightshadow),
                "Precondition: the light {$step} elevation is a navy shadow."
            );
            $black = $dark["--dimensions-elevation-{$step}"];
            $this->assertSame(
                1,
                preg_match('~^(0 \d+px \d+px) rgba\(var\(--bs-black-rgb\), \.\d+\)$~', $black, $darkshadow),
                "The dark {$step} elevation is one black shadow: {$black}."
            );
            $this->assertSame($lightshadow[1], $darkshadow[1], "The dark {$step} elevation keeps the light offset and blur.");
            $drawn++;
        }
        $this->assertSame(3, $drawn, 'Control: the three elevations were read.');
        $this->assertSame(
            $dark['--dimensions-card-shadow'],
            $dark['--dimensions-elevation-sm'],
            'The resting elevation of the dark page is the dark card\'s shadow.'
        );
    }

    /**
     * Bootstrap's radius variables sit on the Design System's scale, and a control's is the 8 px step.
     *
     * Bootstrap carries three steps and a dropdown's, compiled before the tokens exist, so they are literals in
     * scss/pre.scss: a button's radius must be Boost's own 8 px, the scale's md, or an action button has one shape
     * under this theme and another under Boost.
     */
    public function test_bootstraps_radius_variables_sit_on_the_scale(): void {
        $pre = $this->stylesheet('pre.scss');
        preg_match_all('~^\$([a-z-]*border-radius[a-z-]*):\s*([^;]+);$~m', $pre, $matches);
        $radii = array_combine($matches[1], $matches[2]);
        $this->assertSame(
            [
                'border-radius-sm' => '4px',
                'border-radius' => '8px',
                'border-radius-lg' => '12px',
                'dropdown-border-radius' => '8px',
            ],
            $radii,
            'The four radius variables of scss/pre.scss.'
        );
        foreach ($radii as $variable => $value) {
            $this->assertContains((int) $value, self::RADIUS_SCALE, "\${$variable}: {$value} is off the scale.");
        }
        $this->assertSame($this->palette()['--dim-ds-radius-md'], $radii['border-radius'], 'A control\'s radius is the md step.');
    }

    /**
     * The public document lists exactly the declared names, the shared, the local-only, the scales and the card's apart.
     *
     * docs/ is left out of the release archive (.gitattributes), so a site installed from it has no
     * document to read and the test skips there; in the repository and in CI the folder is present, and
     * a missing file inside it fails.
     */
    public function test_the_document_lists_exactly_the_declared_names(): void {
        $docs = dirname(__DIR__, 2) . '/docs';
        if (!is_dir($docs)) {
            $this->markTestSkipped('docs/ is not shipped in the release archive.');
        }
        $this->assertFileExists($docs . '/tokens.md', 'docs/tokens.md is the public half of the contract.');
        $document = file_get_contents($docs . '/tokens.md');

        $sections = [];
        foreach (preg_split('~^## ~m', $document) as $section) {
            if (preg_match('~^(Shared tokens|Local-only tokens|Design system tokens|Card tokens) \((\d+)\)~', $section, $heading)) {
                preg_match_all('~^\| \x60(--dimensions-[a-z0-9-]+)\x60 \| [^|\n]*\S[^|\n]* \|$~m', $section, $rows);
                $sections[$heading[1]] = ['count' => (int) $heading[2], 'names' => $rows[1]];
            }
        }
        $this->assertSame(
            ['Shared tokens', 'Local-only tokens', 'Design system tokens', 'Card tokens'],
            array_keys($sections),
            'The four sections, shared first, the card\'s last.'
        );

        $declared = array_column($this->family_declarations($this->blocks()['light'][0]['body']), 0);
        $shared = array_map(static fn(string $suffix): string => '--dimensions-' . $suffix, self::SHARED);
        $expected = [
            'Shared tokens' => $shared,
            'Local-only tokens' => array_values(array_diff($declared, $shared, self::system(), self::card())),
            'Design system tokens' => array_values(array_intersect($declared, self::system())),
            'Card tokens' => array_values(array_intersect($declared, self::card())),
        ];
        foreach ($expected as $title => $names) {
            $listed = $sections[$title]['names'];
            $this->assertSame(count($listed), count(array_unique($listed)), "{$title}: no name twice.");
            $this->assertEqualsCanonicalizing($names, $listed, "{$title}: exactly the names post.scss declares.");
            $this->assertSame(count($names), $sections[$title]['count'], "{$title}: the heading's count.");
        }
        $this->assertCount(21, $sections['Local-only tokens']['names'], 'Control: the local-only names are the 21.');
        $this->assertCount(10, $sections['Design system tokens']['names'], 'Control: the Design System names are the 10.');
        $this->assertCount(30, $sections['Card tokens']['names'], 'Control: the card names are the 30.');
    }
}
