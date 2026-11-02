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
 * Theme Dimensions - Tests for the calendar page's stylesheet
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
 * scss/calendar.scss: scoped under the page's body class or the calendar blocks' root, and every key colour pair
 * computed in both colour modes.
 *
 * The key tokens are compiled from the stylesheet itself (the two token blocks, over the design system's primitives
 * from post.scss and core's colour-mode helpers, as dark_tokens_test compiles the theme's dark half), and each pair
 * is measured: a key's ink on its tint at 4.5:1 (text), its edge on the card surface and on the quiet surface of the
 * neighbouring days at 3:1 (a mark: a border, a dot). The matrix counts the pairs it measured, so a token block the
 * parser stopped finding fails here rather than passing on nothing.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class calendar_scss_test extends \advanced_testcase {
    /** @var string The page's body class. */
    private const SCOPE = 'body.dim-calendar';

    /** @var string The dark token block's selector. */
    private const DARK = ':root[data-bs-theme="dark"] body.dim-calendar';

    /** @var string The calendar blocks' root (local\calendar\blocks), on any page. */
    private const BLOCK = '.dim-calblock';

    /** @var string The dark colour mode over the blocks' root. */
    private const DARK_BLOCK = ':root[data-bs-theme="dark"] .dim-calblock';

    /** @var string The light token block's selector, as written: the page and the blocks declare the same tokens. */
    private const TOKENS = self::SCOPE . ",\n" . self::BLOCK;

    /** @var string The dark token block's selector, as written. */
    private const DARK_TOKENS = self::DARK . ",\n" . self::DARK_BLOCK;

    /** @var string[] The keys that carry a colour triple: the eight keys and the overdue warning. */
    private const KEYS = ['site', 'category', 'course', 'group', 'user', 'activity', 'other', 'open', 'overdue'];

    /**
     * The stylesheet, comments out.
     *
     * @return string
     */
    private function scss(): string {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/calendar.scss');
        $this->assertNotFalse($scss);
        return (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $scss);
    }

    /**
     * The selectors of the top-level rules (and of rules directly under an at-rule).
     *
     * @param string $scss The stylesheet.
     * @return string[]
     */
    private function top_level_selectors(string $scss): array {
        $selectors = [];
        $stack = [];
        $buffer = '';
        $length = strlen($scss);
        for ($i = 0; $i < $length; $i++) {
            $char = $scss[$i];
            if ($char === '{') {
                $selector = trim((string) preg_replace('~\s+~', ' ', $buffer));
                $depth = count($stack);
                if (!str_starts_with($selector, '@') && ($depth === 0 || ($depth === 1 && str_starts_with($stack[0], '@')))) {
                    $selectors[] = $selector;
                }
                $stack[] = $selector;
                $buffer = '';
            } else if ($char === '}') {
                array_pop($stack);
                $buffer = '';
            } else if ($char === ';') {
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        return $selectors;
    }

    /**
     * Every top-level rule is the page's or the blocks': the body class or the blocks' root, or the dark mode over one.
     *
     * @return void
     */
    public function test_every_rule_is_scoped_to_the_calendar_page(): void {
        $selectors = $this->top_level_selectors($this->scss());
        $this->assertGreaterThanOrEqual(4, count($selectors), 'The control: the rules are read.');
        foreach ($selectors as $selector) {
            foreach (explode(',', $selector) as $part) {
                $this->assertContains(
                    trim($part),
                    [self::SCOPE, self::DARK, self::BLOCK, self::DARK_BLOCK],
                    "Unscoped: {$selector}"
                );
            }
        }
        $this->assertContains(self::BLOCK, $selectors, 'The blocks have rules of their own.');
    }

    /**
     * No :has(), no !important, no literal colour outside the two token blocks.
     *
     * @return void
     */
    public function test_no_has_no_important_no_literal_colour(): void {
        $scss = $this->scss();
        $this->assertStringNotContainsString(':has(', $scss);
        $this->assertStringNotContainsString('!important', $scss);
        $rules = substr($scss, strpos($scss, '@each'));
        $this->assertDoesNotMatchRegularExpression('~#[0-9a-fA-F]{3,6}\b~', $rules, 'A colour outside the tokens.');
        $this->assertDoesNotMatchRegularExpression('~\brgba?\(~', $rules);
    }

    /**
     * The body of a rule of the stylesheet, by its selector.
     *
     * @param string $selector The selector.
     * @return string
     */
    private function block(string $selector): string {
        $scss = $this->scss();
        $start = strpos($scss, $selector . ' {');
        $this->assertNotFalse($start, "No rule {$selector}.");
        $end = strpos($scss, "\n}", $start);
        return substr($scss, $start + strlen($selector) + 2, $end - $start - strlen($selector) - 2);
    }

    /**
     * The two token blocks compiled, as name => #rrggbb per mode.
     *
     * @return array ['light' => [...], 'dark' => [...]].
     */
    private function compiled(): array {
        global $CFG;
        $post = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        preg_match_all('/^\$dim-ds-[a-z]+(?:-\d+)?: #[0-9a-f]{3,6};$/m', $post, $primitives);
        $this->assertGreaterThan(20, count($primitives[0]), 'Precondition: the primitives are Sass variables.');

        $compiler = new \core_scss();
        $compiler->setImportPaths([$CFG->dirroot . '/theme/boost/scss']);
        $compiler->append_raw_scss(
            '@import "design-system-tokens"; @import "' . $CFG->root . '/lib/bundles/bootstrap/scss/functions";'
            . "\n\$prefix: bs-;\n\$white: \$mds-color-gray-white;\n\$gray-800: \$mds-color-gray-800;\n"
            . "\$gray-900: \$mds-color-gray-900;\n@import \"moodle/colour-mode\";\n"
            . implode("\n", $primitives[0])
            . "\n.light {" . $this->block(self::TOKENS) . "\n}\n.dark {" . $this->block(self::DARK_TOKENS) . "\n}"
        );
        $css = $compiler->to_css();
        $modes = [];
        foreach (['light', 'dark'] as $mode) {
            $this->assertSame(1, preg_match('~\.' . $mode . ' \{(.*?)\}~s', $css, $body), "The {$mode} block compiles.");
            preg_match_all('/(--dim-cal-[a-z-]+):\s*([^;]+);/', $body[1], $found, PREG_SET_ORDER);
            foreach ($found as [, $name, $value]) {
                $modes[$mode][$name] = self::hex(trim($value));
            }
        }
        return $modes;
    }

    /**
     * A compiled colour as #rrggbb.
     *
     * @param string $value The compiled value.
     * @return string
     */
    private static function hex(string $value): string {
        $value = strtolower($value);
        $named = ['white' => '#ffffff', 'black' => '#000000'];
        if (isset($named[$value])) {
            return $named[$value];
        }
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $short)) {
            return '#' . $short[1] . $short[1] . $short[2] . $short[2] . $short[3] . $short[3];
        }
        return $value;
    }

    /**
     * Every key's pairs read, in both colour modes: ink on tint at 4.5:1, edge on both surfaces at 3:1.
     *
     * @return void
     */
    public function test_every_key_pair_reads_in_both_modes(): void {
        $this->resetAfterTest();
        $modes = $this->compiled();
        $measured = 0;
        foreach ($modes as $mode => $tokens) {
            foreach (self::KEYS as $key) {
                foreach (['tint', 'edge', 'ink'] as $part) {
                    $this->assertMatchesRegularExpression(
                        '/^#[0-9a-f]{6}$/',
                        $tokens["--dim-cal-{$key}-{$part}"] ?? '',
                        "{$mode} {$key}-{$part} compiles to a colour."
                    );
                }
                $tint = $tokens["--dim-cal-{$key}-tint"];
                $ink = $tokens["--dim-cal-{$key}-ink"];
                $edge = $tokens["--dim-cal-{$key}-edge"];
                $this->assertGreaterThanOrEqual(4.5, branding::contrast($tint, $ink), "{$mode} {$key}: ink on tint.");
                foreach (['--dim-cal-surface', '--dim-cal-surface-quiet'] as $surface) {
                    $this->assertGreaterThanOrEqual(
                        3.0,
                        branding::contrast($tokens[$surface], $edge),
                        "{$mode} {$key}: edge on {$surface}."
                    );
                }
                $measured++;
            }
        }
        $this->assertSame(2 * count(self::KEYS), $measured);
    }

    /**
     * The light and the dark block declare the same names: a token missing from one half reads nothing there.
     *
     * @return void
     */
    public function test_both_modes_declare_every_token(): void {
        $this->resetAfterTest();
        $modes = $this->compiled();
        $this->assertSame(array_keys($modes['light']), array_keys($modes['dark']));
        $this->assertCount(count(self::KEYS) * 3 + 2, $modes['light']);
    }

    /**
     * The week's all-day strip spans the seven day tracks of its row: one grid item, it would otherwise take the first
     * track alone and squeeze every bar into a day's width.
     *
     * @return void
     */
    public function test_the_week_strip_spans_the_day_columns(): void {
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__weekstripgrid\s*\{[^}]*grid-column:\s*2\s*/\s*-1;~',
            $this->scss()
        );
    }

    /**
     * The file is compiled into the theme's main SCSS.
     *
     * @return void
     */
    public function test_the_sheet_is_part_of_the_theme(): void {
        $lib = (string) file_get_contents(dirname(__DIR__, 2) . '/lib.php');
        $this->assertStringContainsString("/theme/dimensions/scss/calendar.scss'", $lib);
    }
}
