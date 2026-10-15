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
 * Guards the theme's half of the --dimensions-* contract with local_dimensions and block_dimensions.
 *
 * The contract is a list of names, read by two other repositories that no test here can run: a name
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

    /** @var string[] The motion suffixes local_dimensions alone reads; its state suffixes come from STATES. */
    private const MOTION = ['motion-fast', 'motion-base', 'motion-ease'];

    /** @var array The enrolment states local_dimensions reads => the showcase's badge state wearing the same pair. */
    private const STATES = [
        'enrolled' => 'enrolled',
        'guest' => 'free',
        'neutral' => 'none',
        'open' => 'open',
        'pending' => 'pending',
        'scheduled' => 'scheduled',
    ];

    /** @var string[] The theme's own names under the same prefix, outside the contract: the navbar's band and pill. */
    private const OWN = ['--dimensions-navbar-band', '--dimensions-pill-border', '--dimensions-pill-hover'];

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
     * Every name of the contract, shared first.
     *
     * @return string[] The custom property names, with their prefix.
     */
    private static function names(): array {
        return array_map(
            static fn(string $suffix): string => '--dimensions-' . $suffix,
            array_merge(self::SHARED, self::local_only())
        );
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
     * The design system's tokens of post.scss: name => value.
     *
     * @return array
     */
    private function palette(): array {
        preg_match_all('~(?<![-\w])(--dim-ds-[a-z0-9-]+)\s*:\s*([^;]+);~', $this->stylesheet('post.scss'), $matches);
        return array_combine($matches[1], array_map('trim', $matches[2]));
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
     * The list holds 33 shared names and 21 local-only ones, none twice.
     *
     * Every other test reads the stylesheet and the document against these constants, so a typo
     * here would be copied into both and pass.
     */
    public function test_the_contract_is_thirty_three_shared_names_and_twenty_one_local_ones(): void {
        $this->assertCount(33, self::SHARED);
        $this->assertCount(21, self::local_only());
        $this->assertCount(54, array_unique(self::names()));
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
     * alone, or Boost Union's dark navbar would turn every plugin inside it dark.
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
     * Each enrolment state wears the showcase's badge of the same family, in both modes, its edge its own ground.
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
            foreach (['light' => $light, 'dark' => $dark] as $mode => $values) {
                $this->assertSame(
                    "var(--dimensions-state-{$state}-bg)",
                    $values["--dimensions-state-{$state}-edge"],
                    "The {$mode} {$state} edge is its own ground: the showcase's badges draw none."
                );
            }
        }
    }

    /**
     * The public document lists exactly the declared names, the shared ones and the local-only ones apart.
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
            if (preg_match('~^(Shared tokens|Local-only tokens) \((\d+)\)~', $section, $heading)) {
                preg_match_all('~^\| \x60(--dimensions-[a-z0-9-]+)\x60 \| [^|\n]*\S[^|\n]* \|$~m', $section, $rows);
                $sections[$heading[1]] = ['count' => (int) $heading[2], 'names' => $rows[1]];
            }
        }
        $this->assertSame(['Shared tokens', 'Local-only tokens'], array_keys($sections), 'The two sections, shared first.');

        $declared = array_column($this->family_declarations($this->blocks()['light'][0]['body']), 0);
        $shared = array_map(static fn(string $suffix): string => '--dimensions-' . $suffix, self::SHARED);
        $expected = [
            'Shared tokens' => $shared,
            'Local-only tokens' => array_values(array_diff($declared, $shared)),
        ];
        foreach ($expected as $title => $names) {
            $listed = $sections[$title]['names'];
            $this->assertSame(count($listed), count(array_unique($listed)), "{$title}: no name twice.");
            $this->assertEqualsCanonicalizing($names, $listed, "{$title}: exactly the names post.scss declares.");
            $this->assertSame(count($names), $sections[$title]['count'], "{$title}: the heading's count.");
        }
        $this->assertCount(21, $sections['Local-only tokens']['names'], 'Control: the local-only names are the 21.');
    }
}
