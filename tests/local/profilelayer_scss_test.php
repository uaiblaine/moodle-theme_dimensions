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
 * Theme Dimensions - Tests for the profile layer's stylesheet
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards scss/profilelayer.scss, the profile layer: every selector is one of the layer's own classes, the theme's main
 * SCSS includes it, it compiles over Bootstrap, the phone's sheet sits under the sm step, every colour is read through
 * a token post.scss declares, the motion follows prefers-reduced-motion and the focus ring is the theme's.
 *
 * The file is concatenated into the theme's main SCSS and reaches every page of the site, so a rule escaping the
 * layer's classes would restyle core's pages.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class profilelayer_scss_test extends \advanced_testcase {
    /** @var string The class every top-level selector starts with. */
    private const ROOT = '.dimensions-layer';

    /**
     * The stylesheet, comments removed.
     *
     * @return string
     */
    private function stylesheet(): string {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/profilelayer.scss');
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        return (string) preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * The stylesheet compiled over Bootstrap's functions, variables and mixins, as the theme's main SCSS compiles it.
     *
     * @return string CSS.
     */
    private function compiled(): string {
        global $CFG;

        $compiler = new \core_scss();
        $compiler->setImportPaths([$CFG->root . '/lib/bundles/bootstrap/scss']);
        $code = '@import "functions"; @import "variables"; @import "variables-dark"; @import "maps"; @import "mixins";'
            . $this->stylesheet();

        return $compiler->compileString($code)->getCss();
    }

    /**
     * The theme's main SCSS includes the sheet, after the tokens it reads.
     *
     * @return void
     */
    public function test_the_main_scss_includes_the_sheet_after_the_tokens(): void {
        $lib = (string) file_get_contents(dirname(__DIR__, 2) . '/lib.php');

        $sheet = strpos($lib, "'/theme/dimensions/scss/profilelayer.scss'");
        $post = strpos($lib, "'/theme/dimensions/scss/post.scss'");
        $this->assertNotFalse($sheet, 'The sheet is included.');
        $this->assertNotFalse($post, 'Precondition: the tokens are included.');
        $this->assertGreaterThan($post, $sheet);
    }

    /**
     * Every selector of the compiled sheet starts with one of the layer's own classes.
     *
     * @return void
     */
    public function test_every_selector_is_the_layers_own(): void {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $this->compiled());
        preg_match_all('~([^{};]+)\{~', $css, $matches);
        $checked = 0;
        foreach ($matches[1] as $prelude) {
            $prelude = trim($prelude);
            // An at-rule, or a stop of the layer's own keyframes, is no selector.
            if ($prelude === '' || $prelude[0] === '@' || preg_match('~^(from|to|\d+(\.\d+)?%)$~', $prelude)) {
                continue;
            }
            foreach (explode(',', $prelude) as $selector) {
                $this->assertStringStartsWith(self::ROOT, trim($selector), 'A selector outside the layer: ' . $selector);
                $checked++;
            }
        }
        $this->assertGreaterThan(40, $checked, 'Precondition: the sheet was read.');
    }

    /**
     * The phone's sheet is the sm step, the motion follows the person's setting, and nothing is !important.
     *
     * @return void
     */
    public function test_the_sheet_step_the_motion_and_no_important(): void {
        $scss = $this->stylesheet();
        $css = $this->compiled();

        $this->assertStringNotContainsString('!important', $scss);
        $this->assertMatchesRegularExpression(
            '~@media \(max-width: 575\.98px\)\s*\{\s*\.dimensions-layer\.dimensions-layer--end\s*\{[^}]*top: auto;'
                . '[^}]*max-height: 88vh;[^}]*border-radius: var\(--dim-layer-radius-xl\) var\(--dim-layer-radius-xl\) 0 0;~',
            $css,
            'Below sm the layer is a bottom sheet.'
        );
        // Every transition sits under prefers-reduced-motion: no-preference; the reduced-motion block's none is none.
        preg_match_all('~transition\s*:(?!\s*none;)~', $scss, $all);
        preg_match_all('~@media \(prefers-reduced-motion: no-preference\)\s*\{\s*transition\s*:~', $scss, $guarded);
        $this->assertNotEmpty($all[0], 'Precondition: the sheet has a transition.');
        $this->assertCount(count($all[0]), $guarded[0]);
        // The opening: the backdrop and the layer animate only while shown and only under the same media query, in the
        // theme's base motion token, and every keyframes rule is the layer's own.
        preg_match_all('~animation\s*:~', $scss, $animations);
        preg_match_all(
            '~@media \(prefers-reduced-motion: no-preference\)\s*\{\s*&:not\(\[hidden\]\)\s*\{\s*animation\s*:~',
            $scss,
            $shown
        );
        $this->assertCount(2, $animations[0], 'The backdrop\'s fade and the layer\'s slide.');
        $this->assertCount(2, $shown[0]);
        $this->assertStringContainsString(
            'animation: dimensions-layer-in var(--dim-layer-motion-base) var(--dim-layer-motion-ease);',
            $scss
        );
        $this->assertStringContainsString('--dim-layer-motion-base: var(--dimensions-motion-base, 250ms);', $scss);
        preg_match_all('~@keyframes ([a-z-]+)~', $scss, $keyframes);
        $this->assertSame(['dimensions-layer-in', 'dimensions-layer-fade'], $keyframes[1]);
        $this->assertMatchesRegularExpression(
            '~@media \(max-width: 575\.98px\)\s*\{\s*\.dimensions-layer\.dimensions-layer--end\s*\{\s*'
                . '--dim-layer-from: translateY\(100%\);~',
            $css,
            'The sheet rises from the bottom.'
        );
        $this->assertMatchesRegularExpression(
            '~@media \(min-width: 768px\)\s*\{\s*\.dimensions-layer \.dimensions-layer-phone\s*\{\s*display: none;~',
            $css,
            'The phone\'s entries are a phone\'s group alone (D75).'
        );
        $this->assertMatchesRegularExpression(
            '~\.dimensions-layer :focus-visible\s*\{\s*outline: var\(--dim-layer-ring-width\) solid var\(--dim-layer-ring\);'
                . '\s*outline-offset: -2px;~',
            $css,
            'The theme\'s ring, inside the region that clips.'
        );
    }

    /**
     * Every colour is a token: the layer's own, which reads a family token post.scss declares, with Bootstrap's after it.
     *
     * @return void
     */
    public function test_every_colour_is_read_through_a_declared_token(): void {
        $scss = $this->stylesheet();
        $post = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');

        preg_match_all('~var\((--dimensions-[a-z0-9-]+)~', $scss, $family);
        $this->assertNotEmpty($family[1], 'Precondition: the sheet reads the family.');
        foreach (array_unique($family[1]) as $token) {
            $this->assertMatchesRegularExpression('~\s' . preg_quote($token, '~') . ':~', $post, $token . ' is declared.');
        }
        preg_match_all('~var\((--dim-layer-[a-z0-9-]+)\)~', $scss, $own);
        foreach (array_unique($own[1]) as $token) {
            $this->assertStringContainsString($token . ':', $scss, $token . ' is declared on the layer.');
        }
        preg_match_all('~^\s*(color|background|background-color|border[a-z-]*|box-shadow|outline)\s*:\s*([^;]+);~m', $scss, $paint);
        foreach ($paint[2] as $index => $value) {
            $this->assertDoesNotMatchRegularExpression(
                '~#[0-9a-f]{3,8}\b|rgba?\((?!var)~i',
                (string) preg_replace('~var\([^;]*~', '', $value),
                $paint[1][$index] . ': ' . $value . ' paints a literal colour.'
            );
        }
    }

    /**
     * The compiled sheet's rules in source order, each with the at-rules around it.
     *
     * @return array A list of ['at' => string, 'selectors' => string[], 'body' => string].
     */
    private function compiled_rules(): array {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $this->compiled());
        $rules = [];
        $stack = [];
        $buffer = '';
        $length = strlen($css);
        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];
            if ($char === '{') {
                $stack[] = trim((string) preg_replace('~\s+~', ' ', $buffer));
                $buffer = '';
            } else if ($char === '}') {
                $prelude = (string) array_pop($stack);
                if ($prelude !== '' && $prelude[0] !== '@') {
                    $rules[] = [
                        'at' => implode(' ', $stack),
                        'selectors' => array_map('trim', explode(',', $prelude)),
                        'body' => $buffer,
                    ];
                }
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        return $rules;
    }

    /**
     * The declarations of every compiled rule naming a selector, under one at-rule (none for '').
     *
     * @param string $selector The selector, as the compiler writes it.
     * @param string $at The at-rule around it, e.g. '@media (prefers-reduced-motion: reduce)'.
     * @return string The bodies, joined.
     */
    private function declarations(string $selector, string $at = ''): string {
        $bodies = [];
        foreach ($this->compiled_rules() as $rule) {
            if ($rule['at'] === $at && in_array($selector, $rule['selectors'], true)) {
                $bodies[] = $rule['body'];
            }
        }
        $this->assertNotEmpty($bodies, "No rule {$selector} under '{$at}'.");
        return implode("\n", $bodies);
    }

    /**
     * A hovered row answers with the family's hover (hover.md, D103): the hover ground and the brand's ink, the leading
     * icon moved 2px and a sub-view's chevron 3px towards the end, the pressed ground on :active; the transitions name
     * background-color, color and transform, never all, at the base step at rest and the fast step hovered.
     *
     * @return void
     */
    public function test_a_hovered_row_answers_with_the_familys_hover(): void {
        $motion = '@media (prefers-reduced-motion: no-preference)';
        $row = '.dimensions-layer .dimensions-layer-row';
        $icon = $row . ' > .icon:first-child';
        $chevron = $row . ' > .dimensions-layer-value > .icon';

        $hover = $this->declarations($row . ':hover');
        $this->assertMatchesRegularExpression('~background-color:\s*var\(--dim-layer-hover\);~', $hover);
        $this->assertMatchesRegularExpression('~color:\s*var\(--dim-layer-brand-ink\);~', $hover);
        $this->assertMatchesRegularExpression(
            '~background-color:\s*var\(--dim-layer-pressed\);~',
            $this->declarations($row . ':active')
        );
        $this->assertMatchesRegularExpression(
            '~color:\s*var\(--dim-layer-brand-ink\);\s*transform:\s*translateX\(var\(--dim-layer-nudge-icon\)\);~',
            $this->declarations($row . ':hover > .icon:first-child')
        );
        $this->assertMatchesRegularExpression(
            '~transform:\s*translateX\(var\(--dim-layer-nudge-arrow\)\);~',
            $this->declarations($row . ':hover > .dimensions-layer-value > .icon')
        );

        // The properties: the row's ground and ink, its icons' ink and move; never all.
        $named = [];
        foreach ([$row, $icon, $chevron] as $selector) {
            $rest = $this->declarations($selector, $motion);
            $this->assertSame(1, preg_match('~transition:\s*([^;]+);~', $rest, $transition), $selector);
            $this->assertDoesNotMatchRegularExpression('~\ball\b~', $transition[1], $selector . ' transitions all.');
            preg_match_all(
                '~(?:^|,)\s*([a-z-]+) var\(--dim-layer-motion-base\) var\(--dim-layer-motion-ease\)~',
                $transition[1],
                $parts
            );
            $this->assertNotEmpty($parts[1], $selector . ' leaves at the base step.');
            $named = array_merge($named, $parts[1]);
        }
        $this->assertEqualsCanonicalizing(['background-color', 'color', 'transform'], array_values(array_unique($named)));
        // The hovered states arrive at the fast step: a transition takes the duration of the state it enters.
        $hovered = [$row . ':hover', $row . ':hover > .icon:first-child', $row . ':hover > .dimensions-layer-value > .icon'];
        foreach ($hovered as $selector) {
            $this->assertMatchesRegularExpression(
                '~transition-duration:\s*var\(--dim-layer-motion-fast\);~',
                $this->declarations($selector, $motion),
                $selector . ' arrives at the fast step.'
            );
        }
        // The tick of a chosen option never moves.
        $this->assertDoesNotMatchRegularExpression('~:last-child[^{]*\{[^}]*transform~', $this->compiled());

        $scss = $this->stylesheet();
        $this->assertStringContainsString('--dim-layer-hover: var(--dimensions-hover, var(--bs-tertiary-bg));', $scss);
        $this->assertStringContainsString('--dim-layer-pressed: var(--dimensions-pressed, var(--bs-secondary-bg));', $scss);
        $this->assertStringNotContainsString('surface-alt', $scss, 'A hover is never the tertiary ground: it may be the surface.');
        $this->assertStringContainsString('--dim-layer-nudge-icon: .125rem;', $scss);
        $this->assertStringContainsString('--dim-layer-nudge-arrow: .1875rem;', $scss);
        // A right-to-left page moves them towards its end, the left.
        $this->assertMatchesRegularExpression(
            '~&:dir\(rtl\) \{[^}]*--dim-layer-nudge-icon: -\.125rem;\s*--dim-layer-nudge-arrow: -\.1875rem;~',
            $scss
        );
        // Log out keeps its red, its icon too.
        $this->assertMatchesRegularExpression(
            '~color:\s*var\(--dim-layer-danger-ink\);~',
            $this->declarations('.dimensions-layer .dimensions-layer-row--danger:hover > .icon:first-child')
        );
    }

    /**
     * The icon buttons and the two profile buttons take the same hover, the profile buttons on the surface with the
     * brand's fill as their edge, through the properties core's button reads.
     *
     * @return void
     */
    public function test_the_buttons_take_the_familys_hover(): void {
        $motion = '@media (prefers-reduced-motion: no-preference)';
        $iconbtn = '.dimensions-layer .dimensions-layer-iconbtn';
        $hover = $this->declarations($iconbtn . ':hover');
        $this->assertMatchesRegularExpression(
            '~background-color:\s*var\(--dim-layer-hover\);\s*color:\s*var\(--dim-layer-brand-ink\);~',
            $hover
        );
        $this->assertMatchesRegularExpression(
            '~background-color:\s*var\(--dim-layer-pressed\);~',
            $this->declarations($iconbtn . ':active')
        );
        $this->assertMatchesRegularExpression(
            '~transition-duration:\s*var\(--dim-layer-motion-fast\);~',
            $this->declarations($iconbtn . ':hover', $motion)
        );

        $button = '.dimensions-layer .dimensions-layer-actions > .btn';
        $rest = $this->declarations($button);
        foreach (
            [
                '--bs-btn-bg' => 'surface',
                '--bs-btn-hover-bg' => 'hover',
                '--bs-btn-hover-color' => 'brand-ink',
                '--bs-btn-hover-border-color' => 'brand-fill',
                '--bs-btn-active-bg' => 'pressed',
            ] as $property => $token
        ) {
            $this->assertStringContainsString("{$property}: var(--dim-layer-{$token});", $rest, $property);
        }
        $this->assertMatchesRegularExpression(
            '~transition-duration:\s*var\(--dim-layer-motion-fast\);~',
            $this->declarations($button . ':hover', $motion)
        );
    }

    /**
     * Under prefers-reduced-motion: reduce the colours change at once and nothing moves: the reduced-motion block drops
     * every transition of the hover and the moves, at the weight of the rules that move and after them.
     *
     * @return void
     */
    public function test_reduced_motion_drops_the_transitions_and_the_moves(): void {
        $reduce = '@media (prefers-reduced-motion: reduce)';
        foreach (
            [
                '.dimensions-layer .dimensions-layer-row',
                '.dimensions-layer .dimensions-layer-row > .icon',
                '.dimensions-layer .dimensions-layer-value > .icon',
                '.dimensions-layer .dimensions-layer-iconbtn',
                '.dimensions-layer .dimensions-layer-actions > .btn',
                '.dimensions-layer .dimensions-layer-notice-back',
            ] as $selector
        ) {
            $this->assertMatchesRegularExpression('~transition:\s*none;~', $this->declarations($selector, $reduce), $selector);
        }
        $moved = [
            '.dimensions-layer .dimensions-layer-row:hover > .icon:first-child',
            '.dimensions-layer .dimensions-layer-row:hover > .dimensions-layer-value > .icon',
        ];
        $rules = $this->compiled_rules();
        foreach ($moved as $selector) {
            $this->assertMatchesRegularExpression('~transform:\s*none;~', $this->declarations($selector, $reduce), $selector);
            $at = ['move' => null, 'still' => null];
            foreach ($rules as $index => $rule) {
                if (in_array($selector, $rule['selectors'], true) && str_contains($rule['body'], 'transform')) {
                    $at[$rule['at'] === $reduce ? 'still' : 'move'] ??= $index;
                }
            }
            $this->assertNotNull($at['move'], 'Precondition: ' . $selector . ' moves.');
            $this->assertGreaterThan($at['move'], $at['still'], $selector . ' stands still after it moves.');
        }
    }

    /**
     * The identity header is the sparkle ground (D104): the gradient from the warm stop through the surface to the
     * brand's tint, its content above three sparkles that the template draws as inline SVG hidden from assistive
     * technology and never focused, so the stylesheet carries no SVG; the avatar is ringed off the gradient.
     *
     * @return void
     */
    public function test_the_identity_header_is_the_sparkle_ground(): void {
        $top = $this->declarations('.dimensions-layer .dimensions-layer-top');
        $this->assertMatchesRegularExpression(
            '~background:\s*linear-gradient\(\s*135deg,\s*var\(--dim-layer-ground-warm\) 0%,\s*var\(--dim-layer-surface\) 60%,'
                . '\s*var\(--dim-layer-brand-tint\) 100%\s*\);~',
            $top
        );
        $this->assertMatchesRegularExpression('~overflow:\s*hidden;~', $top);
        $this->assertMatchesRegularExpression(
            '~position:\s*relative;~',
            $this->declarations('.dimensions-layer .dimensions-layer-top > :not(.dimensions-layer-spark)'),
            'The content paints above the sparkles.'
        );
        $spark = $this->declarations('.dimensions-layer .dimensions-layer-spark');
        $this->assertMatchesRegularExpression('~position:\s*absolute;\s*opacity:\s*0?\.35;~', $spark);
        $this->assertMatchesRegularExpression('~color:\s*var\(--dim-layer-spark\);\s*pointer-events:\s*none;~', $spark);
        $this->assertMatchesRegularExpression(
            '~color:\s*var\(--dim-layer-ground-sand\);~',
            $this->declarations('.dimensions-layer .dimensions-layer-spark--b')
        );
        $this->assertMatchesRegularExpression(
            '~box-shadow:\s*0 0 0 2px var\(--dim-layer-surface\), 0 0 0 3px var\(--dim-layer-brand-tint\);~',
            $this->declarations('.dimensions-layer .dimensions-layer-avatar img')
        );
        $scss = $this->stylesheet();
        $this->assertStringContainsString('--dim-layer-ground-warm: var(--dimensions-ground-warm,', $scss);
        $this->assertStringContainsString('--dim-layer-spark: var(--dimensions-ground-spark,', $scss);
        $this->assertDoesNotMatchRegularExpression('~svg|url\(|data:~i', $scss, 'The sheet carries no SVG data.');

        $template = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/profile_layer.mustache');
        preg_match_all('~<svg\b[^>]*>~', $template, $svgs);
        $this->assertCount(3, $svgs[0], 'Three sparkles.');
        foreach (['a', 'b', 'c'] as $i => $place) {
            $this->assertMatchesRegularExpression(
                '~^<svg class="dimensions-layer-spark dimensions-layer-spark--' . $place
                    . '"[^>]* aria-hidden="true" focusable="false">$~',
                $svgs[0][$i]
            );
        }
        $this->assertMatchesRegularExpression(
            '~<div class="dimensions-layer-top">\s*(<svg [^>]*><path [^>]*/></svg>\s*){3}<div class="dimensions-layer-id">~',
            $template,
            'The sparkles come first, under the content.'
        );
    }

    /**
     * The "logged in as" notice is two parts (D105): the sentence, a flex line whose icon stays at its first line, then
     * the way back across the notice's width on the surface, in the notice's own tone and its hover.
     *
     * @return void
     */
    public function test_the_notice_is_the_sentence_then_the_way_back(): void {
        $this->assertMatchesRegularExpression(
            '~display:\s*flex;\s*align-items:\s*flex-start;\s*gap:\s*0?\.5rem;\s*margin:\s*0 0 0?\.5rem;~',
            $this->declarations('.dimensions-layer .dimensions-layer-notice-text')
        );
        $back = $this->declarations('.dimensions-layer .dimensions-layer-notice-back');
        foreach (
            [
                '--bs-btn-bg: var(--dim-layer-surface);',
                '--bs-btn-color: var(--dim-layer-info-ink);',
                '--bs-btn-border-color: var(--dim-layer-info-edge);',
                '--bs-btn-hover-bg: var(--dim-layer-hover);',
                '--bs-btn-active-bg: var(--dim-layer-pressed);',
                'width: 100%;',
                'font-weight: 600;',
                'white-space: normal;',
            ] as $declaration
        ) {
            $this->assertStringContainsString($declaration, $back);
        }
        $this->assertDoesNotMatchRegularExpression(
            '~\.dimensions-layer-notice[^{]*\{[^}]*margin-inline-start: auto~',
            $this->stylesheet(),
            'No link pushed to the end of the sentence\'s line.'
        );
    }
}
