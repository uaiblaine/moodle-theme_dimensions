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
 * Theme Dimensions - Tests for the competency framework's stylesheet
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards the properties of scss/competency.scss that nothing else in the pipeline reads.
 *
 * The file is concatenated into the theme's main SCSS and reaches every page of the site, so its
 * scope is the first property: every top-level selector names a competency page's body class, the
 * learning plans block or the theme's own dim-comp- prefix, and the body classes are checked against
 * the ones core really derives for those scripts. The second is the colour vocabulary: only the
 * family tokens and Bootstrap's own, never a literal, so a flavour's brand colour and the colour mode
 * move every colour of these pages. The third is the dark pairing: every rule that paints a ground
 * states its ink, and the one pairing that is not the same token in both modes, the progress bar's
 * fill, is restated at the foot.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class competency_scss_test extends \advanced_testcase {
    /**
     * @var array The scopes a top-level selector may start with.
     *
     * The body classes are what moodle_page derives from the script's path; .block_lp is the class
     * core gives the block's root; dim-comp- is a prefix only the theme's copies of core's templates
     * write, so a selector wearing it cannot reach markup this theme did not draw.
     */
    private const SCOPES = [
        'body.path-admin-tool-lp',
        'body.path-report-competency',
        'body.path-blocks-lp',
        '.block_lp',
        '.dim-comp-',
    ];

    /** @var string The one dark prefix: Moodle 5.3 writes the colour mode on the html element. */
    private const DARK = ':root[data-bs-theme="dark"]';

    /** @var array The token families a value may read. Bootstrap's and Font Awesome's are core's. */
    private const FAMILIES = [
        '--dimensions-',
        '--bs-',
        '--fa-',
        '--dim-ds-radius-',
        '--dim-ds-space-',
        '--dim-ds-leading-',
    ];

    /** @var array The properties that carry a colour, which may read only the family tokens and Bootstrap's. */
    private const COLOUR_PROPERTIES = [
        'color', 'background', 'background-color', 'border', 'border-color', 'border-top', 'border-bottom',
        'border-inline-start', 'box-shadow', 'outline', 'outline-color', 'fill', 'stroke',
    ];

    /**
     * Absolute path to the theme root.
     *
     * @return string The plugin directory, without a trailing separator.
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * Remove block and line comments from a stylesheet.
     *
     * @param string $source The stylesheet source.
     * @return string The source without its comments.
     */
    private function strip_comments(string $source): string {
        $source = preg_replace('~/\*.*?\*/~s', '', $source);
        return preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * The competency stylesheet, with its comments removed.
     *
     * @return string The stylesheet source.
     */
    private function stylesheet(): string {
        return $this->strip_comments(file_get_contents($this->plugin_root() . '/scss/competency.scss'));
    }

    /**
     * Every selector of a stylesheet which is not inside another rule (an at-rule at the root is looked into).
     *
     * @param string $scss The stylesheet source, comments already removed.
     * @return array The selector strings, whitespace collapsed.
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
                $underatrule = $depth === 1 && str_starts_with($stack[0], '@');
                if (!str_starts_with($selector, '@') && ($depth === 0 || $underatrule)) {
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
     * Every rule of a stylesheet with its own declarations, nested rules flattened into a chain.
     *
     * A nested block contributes nothing to its parent's declarations, so a property declared inside
     * a media query or a pseudo-element is never read as the parent's.
     *
     * @param string $scss The stylesheet source, comments already removed.
     * @return array Each entry [the selector chain joined by " | ", the rule's own declarations].
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
                $rules[] = [implode(' | ', $chain), $frame['own']];
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        return $rules;
    }

    /**
     * The declarations of a block as property => value.
     *
     * @param string $own The declarations, one per line, each ending in a semicolon.
     * @return array Property => value.
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
     * Whether a selector part starts with an accepted scope, at a word boundary for a whole class.
     *
     * @param string $part One selector of a list.
     * @return bool True when scoped.
     */
    private function is_scoped(string $part): bool {
        if (str_starts_with($part, self::DARK . ' ')) {
            $part = trim(substr($part, strlen(self::DARK)));
        }
        foreach (self::SCOPES as $scope) {
            if (!str_starts_with($part, $scope)) {
                continue;
            }
            if (str_ends_with($scope, '-')) {
                return true;
            }
            $next = substr($part, strlen($scope), 1);
            if ($next === '' || !preg_match('~[\w-]~', $next)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The literal colours a value holds: hex, the functional notations and the named colours.
     *
     * @param string $value A declaration's value.
     * @return array The literals found.
     */
    private function literal_colours(string $value): array {
        $pattern = '~#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\(|' .
            '\b(?:white|black|red|green|blue|gray|grey|yellow|orange|purple|navy|silver|pink|brown|cyan|magenta|teal)\b~i';
        preg_match_all($pattern, $value, $matches);
        return $matches[0];
    }

    /**
     * The stylesheet is served: lib.php concatenates it into the main SCSS.
     *
     * Every sheet is named there by hand, and a file added to scss/ and not listed is never served.
     *
     * @return void
     */
    public function test_lib_serves_the_stylesheet(): void {
        $lib = file_get_contents($this->plugin_root() . '/lib.php');
        $this->assertSame(1, substr_count($lib, "'/theme/dimensions/scss/competency.scss'"));
    }

    /**
     * Every rule of the stylesheet is scoped to a competency page, the block or the theme's own classes.
     *
     * @return void
     */
    public function test_every_top_level_selector_is_scoped(): void {
        $selectors = $this->top_level_selectors($this->stylesheet());
        $this->assertGreaterThan(20, count($selectors), 'The walker saw almost nothing, so this proves nothing.');

        foreach ($selectors as $selector) {
            foreach (explode(',', $selector) as $part) {
                $part = trim($part);
                $this->assertTrue($this->is_scoped($part), 'Unscoped selector, it would reach every page: ' . $part);
            }
        }
    }

    /**
     * The scope check sees an unscoped selector, a look-alike class and a bare dark prefix.
     *
     * @return void
     */
    public function test_the_scope_check_sees_an_unscoped_selector(): void {
        $fixture = "body.path-admin-tool-lp .x { color: red; }\n.badge { color: red; }\n" .
            "body.path-admin-tool-lpx .x { color: red; }\n:root[data-bs-theme=\"dark\"] .badge { color: red; }\n" .
            ".block_lpx { color: red; }\n:root[data-bs-theme=\"dark\"] .dim-comp-pill { color: red; }\n";

        $selectors = $this->top_level_selectors($fixture);

        $verdicts = [];
        foreach ($selectors as $selector) {
            $verdicts[] = $this->is_scoped($selector);
        }
        $this->assertSame([true, false, false, false, false, true], $verdicts);
    }

    /**
     * The body classes the stylesheet is scoped to are the ones core derives for the competency scripts.
     *
     * None of tool_lp's, report_competency's or block_lp's scripts sets its page type, so moodle_page
     * derives it from the script's path and writes a path- class per prefix. A scope naming a class no
     * page carries would leave these pages unstyled and fail nothing else.
     *
     * @return void
     */
    public function test_the_scopes_are_the_classes_core_derives(): void {
        global $CFG, $SITE;
        $this->resetAfterTest();
        $scripts = [
            'admin/tool/lp/plan.php' => 'path-admin-tool-lp',
            'admin/tool/lp/user_competency_in_plan.php' => 'path-admin-tool-lp',
            'report/competency/index.php' => 'path-report-competency',
            'blocks/lp/plans_to_review.php' => 'path-blocks-lp',
        ];
        foreach ($scripts as $script => $class) {
            $this->assertFileExists($CFG->dirroot . '/' . $script);
            $page = new \moodle_page();
            $page->set_course($SITE);
            (new \ReflectionMethod(\moodle_page::class, 'initialise_default_pagetype'))->invoke($page, $script);
            (new \ReflectionMethod(\moodle_page::class, 'initialise_standard_body_classes'))->invoke($page);

            $this->assertContains($class, explode(' ', $page->bodyclasses), "{$script} carries no {$class}.");
            $this->assertContains('body.' . $class, self::SCOPES);
        }
    }

    /**
     * No literal colour: the family tokens and Bootstrap's own, which follow the flavour and the mode.
     *
     * @return void
     */
    public function test_no_literal_colour(): void {
        $checked = 0;
        foreach ($this->rules($this->stylesheet()) as [$chain, $own]) {
            foreach ($this->declarations($own) as $property => $value) {
                $this->assertSame([], $this->literal_colours($value), "{$chain} {$property}: {$value} holds a literal colour.");
                $checked++;
            }
        }
        $this->assertGreaterThan(100, $checked, 'Almost no declaration was read, so this proves nothing.');
    }

    /**
     * The literal-colour check sees each spelling it claims to.
     *
     * @return void
     */
    public function test_the_colour_check_sees_a_literal(): void {
        foreach (['#fff', '1px solid #070093', 'rgb(0 0 0)', 'rgba(7, 0, 147, .1)', 'hsl(0 0% 0%)', 'white', 'Black'] as $value) {
            $this->assertNotSame([], $this->literal_colours($value), "{$value} was not seen.");
        }
        foreach (['var(--dimensions-ink)', 'currentcolor', 'transparent', '0 1px 2px var(--dimensions-shadow)'] as $value) {
            $this->assertSame([], $this->literal_colours($value), "{$value} was taken for a literal.");
        }
    }

    /**
     * Every token read is of an accepted family, a colour is read only from the family or Bootstrap,
     * and every token of the theme's that is read is declared.
     *
     * An unresolved var() is not a fallback: the whole declaration is invalid at computed-value time.
     *
     * @return void
     */
    public function test_every_token_is_a_family_or_bootstrap_token_and_declared(): void {
        $post = $this->strip_comments(file_get_contents($this->plugin_root() . '/scss/post.scss'));
        preg_match_all('~(--[a-z0-9-]+)\s*:~i', $post, $declared);

        $read = 0;
        foreach ($this->rules($this->stylesheet()) as [$chain, $own]) {
            foreach ($this->declarations($own) as $property => $value) {
                preg_match_all('~var\(\s*(--[a-z0-9-]+)~i', $value, $tokens);
                foreach ($tokens[1] as $token) {
                    $read++;
                    $family = array_filter(self::FAMILIES, static fn(string $prefix): bool => str_starts_with($token, $prefix));
                    $this->assertNotEmpty($family, "{$chain} reads {$token}, which is of no accepted family.");
                    if (in_array($property, self::COLOUR_PROPERTIES, true)) {
                        $message = "{$chain} {$property} takes a colour from {$token}.";
                        $this->assertMatchesRegularExpression('~^--(dimensions|bs)-~', $token, $message);
                    }
                    if (!str_starts_with($token, '--bs-') && !str_starts_with($token, '--fa-')) {
                        $this->assertContains($token, $declared[1], "{$token} is read and declared nowhere it can see.");
                    }
                }
            }
        }
        $this->assertGreaterThan(50, $read, 'Almost no token was read, so this proves nothing.');
    }

    /**
     * Every rule that paints a ground states the ink that goes on it, in the same rule.
     *
     * A ground with an inherited ink is right in one mode and wrong in the other the day the ground's
     * token and the inherited colour stop flipping together. The progress element's own track and
     * value hold no text and are the only exception.
     *
     * @return void
     */
    public function test_every_surface_states_its_ink(): void {
        $grounds = 0;
        foreach ($this->rules($this->stylesheet()) as [$chain, $own]) {
            $declarations = $this->declarations($own);
            if (!isset($declarations['background']) && !isset($declarations['background-color'])) {
                continue;
            }
            if (preg_match('~::-(webkit|moz)-progress~', $chain)) {
                continue;
            }
            $grounds++;
            $this->assertArrayHasKey('color', $declarations, "{$chain} paints a ground and states no ink.");
        }
        $this->assertGreaterThan(10, $grounds, 'Almost no ground was found, so this proves nothing.');
    }

    /**
     * The dark half is at the foot, scoped, and restates the progress bar's fill with the brand ink.
     *
     * The brand fill is Bootstrap's primary, which is the same colour in both modes and can all but vanish
     * on the dark track; the light bar keeps it and the dark bar takes the brand ink on the value of both
     * engines.
     *
     * @return void
     */
    public function test_the_dark_half_pairs_the_progress_bar(): void {
        $stylesheet = $this->stylesheet();
        $selectors = $this->top_level_selectors($stylesheet);
        $first = null;
        foreach ($selectors as $index => $selector) {
            if (str_starts_with($selector, self::DARK)) {
                $first = $first ?? $index;
            } else {
                $this->assertNull($first, "A light rule follows the dark half: {$selector}");
            }
        }
        $this->assertNotNull($first, 'There is no dark half.');

        $rules = [];
        foreach ($this->rules($stylesheet) as [$chain, $own]) {
            $rules[$chain][] = $this->declarations($own);
        }
        $light = '.dim-comp-progress | .dim-comp-progress__bar';
        $dark = self::DARK . ' .dim-comp-progress .dim-comp-progress__bar';
        $this->assertCount(1, $rules[$light]);
        $this->assertSame('var(--dimensions-brand-fill)', $rules[$light][0]['color']);
        $this->assertSame('var(--dimensions-brand-fill)', $rules[$light . ' | &::-webkit-progress-value'][0]['background-color']);
        $this->assertSame('var(--dimensions-brand-fill)', $rules[$light . ' | &::-moz-progress-bar'][0]['background-color']);
        $this->assertCount(1, $rules[$dark]);
        $this->assertSame('var(--dimensions-brand-ink)', $rules[$dark][0]['color']);
        $this->assertSame('var(--dimensions-brand-ink)', $rules[$dark . ' | &::-webkit-progress-value'][0]['background-color']);
        $this->assertSame('var(--dimensions-brand-ink)', $rules[$dark . ' | &::-moz-progress-bar'][0]['background-color']);
    }

    /**
     * Core's proficiency badges, which the theme does not re-template, get an icon per state.
     *
     * Bootstrap's bg-* utilities are !important, so the stylesheet cannot recolour them, and the one
     * thing it can add so that the state is not carried by colour alone is the glyph before the text.
     *
     * @return void
     */
    public function test_cores_proficiency_badges_get_an_icon_per_state(): void {
        $rules = [];
        foreach ($this->rules($this->stylesheet()) as [$chain, $own]) {
            $rules[$chain] = $this->declarations($own);
        }
        $icons = [];
        foreach (['bg-success' => 'circle-check', 'bg-danger' => 'circle-exclamation'] as $badge => $glyph) {
            $found = 0;
            foreach ($rules as $chain => $declarations) {
                $target = str_contains($chain, "[data-region=\"competency-summary\"] .badge.{$badge}::before");
                if ($target && ($declarations['content'] ?? '') === "fa-content(\$fa-var-{$glyph})") {
                    $found++;
                }
            }
            $this->assertSame(1, $found, "No icon for core's {$badge} badge.");
            $icons[] = $glyph;
        }
        $this->assertCount(2, array_unique($icons));
    }

    /**
     * The path clears core's floated "Path:" label, so the next row of the page starts below it.
     *
     * Without the clearing pseudo-element, core's "Upon course completion" selector on the course
     * competencies page wraps beside the label.
     *
     * @return void
     */
    public function test_the_path_clears_cores_floated_label(): void {
        $found = 0;
        foreach ($this->rules($this->stylesheet()) as [$chain, $own]) {
            if (str_ends_with($chain, '.dim-comp-path | &::after')) {
                $declarations = $this->declarations($own);
                $this->assertSame('both', $declarations['clear'] ?? null, 'The path must clear the float.');
                $this->assertSame('block', $declarations['display'] ?? null, 'A clearing box must be a block.');
                $this->assertSame('""', $declarations['content'] ?? null, 'The pseudo-element needs content.');
                $found++;
            }
        }
        $this->assertSame(1, $found, 'The path has exactly one clearing pseudo-element.');
    }

    /**
     * The stylesheet wins on specificity and order, never on force.
     *
     * @return void
     */
    public function test_the_stylesheet_uses_no_important(): void {
        $this->assertStringNotContainsString('!important', $this->stylesheet());
    }
}
