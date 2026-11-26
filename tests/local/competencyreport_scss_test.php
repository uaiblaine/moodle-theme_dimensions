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
 * Theme Dimensions - Tests for the class competency report's stylesheet
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards the properties of scss/competencyreport.scss that nothing else in the pipeline reads.
 *
 * The file reaches every page of the site, so every rule sits under the shell's root class and every animation is
 * named with its prefix. Its colours are the family tokens, read directly, so the flavour and the colour mode move
 * them; it uses no !important, no :has(), no clamp()/min()/max() and no transition under 100 ms (the fleet's stylelint
 * rules, which the gate reads in plugin CSS but this SCSS reaches only compiled). The progress line runs the other
 * way in a right-to-left page, which RTLCSS does not do for a transform.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class competencyreport_scss_test extends \basic_testcase {
    /** @var string The shell's root class, the only top-level selector. */
    private const ROOT = '.dim-comp-rview';

    /** @var array The properties that carry a colour. */
    private const COLOUR_PROPERTIES = [
        'color', 'background', 'background-color', 'border', 'border-color', 'border-top', 'border-bottom',
        'border-top-color', 'box-shadow', 'outline', 'outline-color', 'fill', 'stroke',
    ];

    /**
     * Absolute path to the theme root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * The stylesheet without its comments.
     *
     * @return string
     */
    private function stylesheet(): string {
        $source = (string) file_get_contents($this->plugin_root() . '/scss/competencyreport.scss');
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        return (string) preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * Every block of a stylesheet with its own declarations, nested blocks flattened into a chain.
     *
     * @param string $scss The source, comments removed.
     * @return array Each entry [the selector chain joined by " | ", the chain's depth, the block's own declarations].
     */
    private function rules(string $scss): array {
        $rules = [];
        $stack = [];
        $buffer = '';
        $length = strlen($scss);
        for ($i = 0; $i < $length; $i++) {
            $char = $scss[$i];
            if ($char === '{') {
                $stack[] = ['selector' => trim((string) preg_replace('~\s+~', ' ', $buffer)), 'own' => ''];
                $buffer = '';
            } else if ($char === ';') {
                if ($stack) {
                    $stack[count($stack) - 1]['own'] .= trim($buffer) . ";\n";
                }
                $buffer = '';
            } else if ($char === '}') {
                $frame = array_pop($stack);
                if (trim($buffer) !== '') {
                    $frame['own'] .= trim($buffer) . ";\n";
                }
                $chain = array_merge(array_column($stack, 'selector'), [$frame['selector']]);
                $rules[] = [implode(' | ', $chain), count($chain), $frame['own']];
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }

        return $rules;
    }

    /**
     * A block's declarations as property => value.
     *
     * @param string $own The declarations, one per line.
     * @return array
     */
    private function declarations(string $own): array {
        $result = [];
        foreach (explode(";\n", $own) as $line) {
            $line = trim($line, " ;\n\t");
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $line, 2));
            $result[strtolower($property)] = $value;
        }

        return $result;
    }

    /**
     * The stylesheet is served, once, after the competency pages' own.
     *
     * @return void
     */
    public function test_lib_serves_the_stylesheet_after_the_competency_pages(): void {
        $lib = (string) file_get_contents($this->plugin_root() . '/lib.php');
        $this->assertSame(1, substr_count($lib, "'/theme/dimensions/scss/competencyreport.scss'"));
        $this->assertGreaterThan(
            strpos($lib, "'/theme/dimensions/scss/competency.scss'"),
            strpos($lib, "'/theme/dimensions/scss/competencyreport.scss'")
        );
    }

    /**
     * Every top-level block is the root class or one of its own animations; the root holds every rule.
     *
     * @return void
     */
    public function test_every_rule_is_under_the_root(): void {
        $tops = array_filter($this->rules($this->stylesheet()), static fn(array $rule): bool => $rule[1] === 1);
        $this->assertGreaterThan(3, count($tops));
        $roots = 0;
        foreach ($tops as [$selector]) {
            if ($selector === self::ROOT) {
                $roots++;
                continue;
            }
            $this->assertMatchesRegularExpression('~^@keyframes dim-comp-rview-[a-z-]+$~', $selector, "Unscoped: {$selector}");
        }
        $this->assertSame(1, $roots, 'One root block holds every rule.');
        $nested = array_filter($this->rules($this->stylesheet()), static fn(array $rule): bool =>
            str_starts_with($rule[0], self::ROOT . ' | '));
        $this->assertGreaterThan(100, count($nested), 'Almost nothing is nested, so this proves nothing.');
    }

    /**
     * Colours come from the family tokens, which the theme declares; no literal colour anywhere.
     *
     * @return void
     */
    public function test_colours_are_family_tokens(): void {
        $post = (string) file_get_contents($this->plugin_root() . '/scss/post.scss');
        preg_match_all('~(--dimensions-[a-z0-9-]+)\s*:~', $post, $declared);
        $own = $this->stylesheet();
        preg_match_all('~(--dim-comp-rview-[a-z0-9-]+)\s*:~', $own, $owndeclared);
        $literal = '~#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\(|' .
            '\b(?:white|black|red|green|blue|gray|grey|yellow|orange|purple|navy|silver|pink|brown|cyan|magenta|teal)\b~i';
        $read = 0;
        foreach ($this->rules($own) as [$chain, , $block]) {
            foreach ($this->declarations($block) as $property => $value) {
                $this->assertDoesNotMatchRegularExpression($literal, $value, "{$chain} {$property}: a literal colour.");
                preg_match_all('~var\(\s*(--[a-z0-9-]+)~i', $value, $tokens);
                foreach ($tokens[1] as $token) {
                    $read++;
                    if (in_array($property, self::COLOUR_PROPERTIES, true)) {
                        $message = "{$chain} {$property} takes a colour from {$token}.";
                        $this->assertStringStartsWith('--dimensions-', $token, $message);
                    }
                    $known = in_array($token, $declared[1], true) || in_array($token, $owndeclared[1], true);
                    $this->assertTrue($known, "{$chain} reads {$token}, declared nowhere.");
                }
            }
        }
        $this->assertGreaterThan(100, $read, 'Almost no token was read, so this proves nothing.');
    }

    /**
     * The fleet's stylelint rules: no !important, no :has(), no clamp()/min()/max() in a value, no transition under
     * 100 ms, no container query.
     *
     * @return void
     */
    public function test_the_fleet_stylelint_rules(): void {
        $source = $this->stylesheet();
        $this->assertStringNotContainsString('!important', $source);
        $this->assertStringNotContainsString(':has(', $source);
        $this->assertStringNotContainsString('@container', $source);
        $this->assertDoesNotMatchRegularExpression('~(?<![a-z-])(?:clamp|min|max)\(~', $source);
        foreach ($this->rules($source) as [$chain, , $block]) {
            foreach ($this->declarations($block) as $property => $value) {
                if (!str_starts_with($property, 'transition')) {
                    continue;
                }
                preg_match_all('~(?<![\w.-])(\d+(?:\.\d+)?)(ms|s)\b~', $value, $times, PREG_SET_ORDER);
                foreach ($times as [, $number, $unit]) {
                    $ms = $unit === 's' ? (float) $number * 1000 : (float) $number;
                    $this->assertGreaterThanOrEqual(100, $ms, "{$chain} {$property}: {$value}");
                }
            }
        }
    }

    /**
     * The progress line travels the other way in a right-to-left page, through its own animation.
     *
     * @return void
     */
    public function test_the_progress_line_runs_backwards_in_rtl(): void {
        $found = false;
        foreach ($this->rules($this->stylesheet()) as [$chain, , $block]) {
            if (str_ends_with($chain, '.dir-rtl & .dim-comp-rview-progress::before')) {
                $this->assertSame('dim-comp-rview-indeterminate-rtl', $this->declarations($block)['animation-name'] ?? null);
                $found = true;
            }
        }
        $this->assertTrue($found, 'No right-to-left rule for the progress line.');
        $this->assertMatchesRegularExpression(
            '~@keyframes dim-comp-rview-indeterminate-rtl\s*\{\s*from\s*\{\s*transform: translateX\(100%\);~',
            $this->stylesheet()
        );
    }
}
