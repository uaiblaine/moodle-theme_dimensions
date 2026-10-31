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
 * Theme Dimensions - Tests for the reading bar's stylesheet
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards what nothing else in the pipeline reads about scss/readingbar.scss and the band in scss/post.scss: where the
 * rules reach, that the motion is reduced-motion aware, and that every offset auto-hide moves reads one property.
 *
 * The file is concatenated into the theme's main SCSS and reaches every page of the site, so a rule escaping its
 * scopes would restyle, or hide, the navbar of pages that never asked for any of it.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class readingbar_scss_test extends \basic_testcase {
    /** @var string The auto-hide scope: the body class, standing down beside Boost Union's menu bar and flush drawers. */
    private const AUTOHIDE = 'body.dimensions-autohide:not(.dimensions-focusmode):not(:has(.navbar.boost-union-menubar))'
        . ':not(:where(#{$dimensions-bu-flush}))';

    /**
     * @var string[] The only top-level selectors the file may have: the reading pages, the phone slide's clip, the layer
     *      and its swap (a class only the template or the module writes, under the navbar's own), and auto-hide.
     */
    private const SCOPES = [
        'body.dimensions-readingbar',
        '@keyframes dimensions-ctxbar-clip',
        '.navbar.fixed-top .dimensions-ctxbar',
        '.navbar.fixed-top .dimensions-ctxbar-text',
        '.navbar.fixed-top .dimensions-ctxbar-title',
        '.navbar.fixed-top .dimensions-ctxbar-sub',
        '.navbar.fixed-top .dimensions-ctxbar-index',
        '.navbar.fixed-top .dimensions-ctxbar-focus',
        '.navbar.fixed-top .dimensions-ctxbar-status',
        '.navbar.fixed-top .dimensions-ctxbar-edit',
        '.navbar.fixed-top.dimensions-ctx-on',
        self::AUTOHIDE,
    ];

    /**
     * A stylesheet of the theme, comments removed.
     *
     * @param string $file The file under scss/.
     * @return string
     */
    private function stylesheet(string $file = 'readingbar.scss'): string {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/' . $file);
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        return (string) preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * Every selector at the root of a stylesheet; a Sass variable declaration is not one.
     *
     * @param string $scss The stylesheet, comments removed.
     * @return array The selectors, whitespace collapsed.
     */
    private function top_level_selectors(string $scss): array {
        $selectors = [];
        $depth = 0;
        $buffer = '';
        $length = strlen($scss);
        for ($i = 0; $i < $length; $i++) {
            $char = $scss[$i];
            if ($char === '{' && $depth === 0 && substr($scss, $i - 1, 1) === '#') {
                // A Sass interpolation at the root, inside a variable's value: not a block.
                $buffer .= $char;
                $depth = -1;
            } else if ($char === '}' && $depth === -1) {
                $buffer .= $char;
                $depth = 0;
            } else if ($char === '{') {
                $selector = trim((string) preg_replace('~\s+~', ' ', $buffer));
                // A Sass function is not a rule: it emits nothing.
                if ($depth === 0 && !str_starts_with($selector, '@function ')) {
                    $selectors[] = $selector;
                }
                $depth++;
                $buffer = '';
            } else if ($char === '}') {
                $depth--;
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
     * Every rule is scoped: a reading body class, auto-hide's, or a class of the layer under the navbar's own.
     *
     * @return void
     */
    public function test_every_rule_is_scoped(): void {
        $selectors = $this->top_level_selectors($this->stylesheet());

        $this->assertNotEmpty($selectors);
        $this->assertSame(self::SCOPES, array_values(array_unique($selectors)));
    }

    /**
     * The scope check sees an unscoped selector, past a Sass variable whose value interpolates.
     *
     * @return void
     */
    public function test_the_scope_check_sees_an_unscoped_selector(): void {
        $fixture = "\$x: \"calc(#{\$a} - 1px)\";\n"
            . "body.dimensions-readingbar .y { color: red; }\n"
            . ".navbar.fixed-top { opacity: 0; }\n";

        $this->assertSame(['body.dimensions-readingbar .y', '.navbar.fixed-top'], $this->top_level_selectors($fixture));
    }

    /**
     * No literal colour: the track is the band's own colour, the status the family's success pair.
     *
     * @return void
     */
    public function test_no_literal_colour(): void {
        $scss = $this->stylesheet();

        $this->assertDoesNotMatchRegularExpression('~#[0-9a-fA-F]{3,8}\b~', $scss);
        $this->assertDoesNotMatchRegularExpression('~\b(rgba?|hsla?)\(~', $scss);
        $this->assertDoesNotMatchRegularExpression('~:\s*(white|black)\b~', $scss);
        $this->assertStringContainsString(
            'background: var(--dimensions-navbar-band);',
            $scss,
            'Control: the track reads the band.'
        );
        $this->assertStringContainsString('color: var(--bs-navbar-brand-color, var(--bs-body-color));', $scss);
    }

    /**
     * Every transition is inside a prefers-reduced-motion: no-preference query, so a reader who asked for less gets the
     * swap and the slide with no animation.
     *
     * @return void
     */
    public function test_the_motion_is_reduced_motion_aware(): void {
        $scss = $this->stylesheet();

        $this->assertSame(3, substr_count($scss, 'transition:'));
        $this->assertSame(3, preg_match_all(
            '~@media \(prefers-reduced-motion: no-preference\) \{[^{}]*\{\s*transition:~',
            $scss
        ), 'Each transition is the only rule of its own reduced-motion query.');
        $this->assertSame(1, substr_count($scss, 'animation:'));
        $this->assertMatchesRegularExpression(
            '~@media \(prefers-reduced-motion: no-preference\) \{\s*\.navbar\.fixed-top:not\(\.dimensions-ctx-on\) \{\s*'
                . 'animation: dimensions-ctxbar-clip var\(--dim-ds-motion-base\) backwards;\s*\}\s*\}~',
            $scss,
            'The clip of the slide back is motion too.'
        );
    }

    /**
     * The swap hides the global bar's items in place and shows the layer, and the layer is hidden otherwise.
     *
     * @return void
     */
    public function test_the_swap_hides_in_place_and_shows_the_layer(): void {
        $scss = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '~\.navbar\.fixed-top \.dimensions-ctxbar \{(?:#\{[^}]*\}|[^}])*position: absolute;[^}]*visibility: hidden;\s*'
                . 'opacity: 0;~',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '~\.navbar\.fixed-top\.dimensions-ctx-on \{\s*\.dimensions-ctxbar \{\s*visibility: visible;\s*opacity: 1;\s*\}\s*'
                . '> \.container-fluid > :not\(#usernavigation\),\s*#usernavigation > :not\(\.dimensions-ctxbar\) \{\s*'
                . 'visibility: hidden;\s*opacity: 0;~',
            $scss
        );
    }

    /**
     * Below md the swap is Fandom's slide: the layer comes down from above, the global items go down by the bar's
     * height, both clipped by the nav while the layer is in, and by the keyframes' overflow on the way back.
     *
     * @return void
     */
    public function test_below_md_the_swap_is_a_clipped_slide(): void {
        $scss = $this->stylesheet();
        $at = strpos($scss, '@include media-breakpoint-down(md) {');
        $this->assertNotFalse($at);
        $this->assertLessThan(strpos($scss, '@keyframes dimensions-ctxbar-clip'), $at, 'Inside the reading pages\' block.');
        $block = substr($scss, $at);

        $this->assertMatchesRegularExpression(
            '~^@include media-breakpoint-down\(md\) \{\s*\.navbar\.fixed-top \.dimensions-ctxbar \{\s*opacity: 1;\s*'
                . 'transform: translateY\(-100%\);\s*\}\s*\.navbar\.fixed-top\.dimensions-ctx-on \{\s*overflow: hidden;\s*'
                . '\.dimensions-ctxbar \{\s*transform: none;\s*\}\s*> \.container-fluid > :not\(#usernavigation\),\s*'
                . '#usernavigation > :not\(\.dimensions-ctxbar\) \{\s*opacity: 1;\s*'
                . 'transform: translateY\(#\{\$navbar-height\}\);~',
            $block
        );
        $this->assertMatchesRegularExpression(
            '~@keyframes dimensions-ctxbar-clip \{\s*0%,\s*100% \{\s*overflow: hidden;\s*\}\s*\}~',
            $scss
        );
    }

    /**
     * The layer paints the derived pair: its ground, and its ink through the navbar's brand colour property that every
     * part of it reads; the pills and the ring take the same ink.
     *
     * @return void
     */
    public function test_the_layer_paints_the_derived_pair(): void {
        $scss = $this->stylesheet();
        $this->assertSame(
            1,
            preg_match('~\n\.navbar\.fixed-top \.dimensions-ctxbar \{((?:#\{[^}]*\}|[^}])*)\}~', $scss, $matches)
        );

        $this->assertStringContainsString('--dim-ctxbar-ground: #{$dimensions-ctxbar-ground};', $matches[1]);
        $this->assertStringContainsString('--dim-ctxbar-line: #{transparentize($dimensions-ctxbar-ink, .55)};', $matches[1]);
        $this->assertStringContainsString('--bs-navbar-brand-color: #{$dimensions-ctxbar-ink};', $matches[1]);
        $this->assertStringContainsString('--dim-ds-focus-ring: var(--bs-navbar-brand-color);', $matches[1]);
        $this->assertStringContainsString('background-color: var(--dim-ctxbar-ground);', $matches[1]);
        $this->assertStringContainsString(
            '$dimensions-ctxbar-ground: dimensions-ctxbar-ground($dimensions-ctxbar-color, $primary);',
            $scss
        );
        $this->assertStringNotContainsString('opacity: .82', $scss, 'The sub line keeps the contrast the ground was chosen for.');
    }

    /**
     * The band is the reading progress: scaled from the left by one property, full when it is unset.
     *
     * @return void
     */
    public function test_the_band_scales_from_the_left(): void {
        $post = $this->stylesheet('post.scss');

        // The navbar's own declarations (its border) may come before the band's block.
        $this->assertSame(1, preg_match('~\.navbar\.fixed-top \{[^{}]*?&::after \{([^}]*)\}~', $post, $matches));
        $this->assertStringContainsString('transform: scaleX(var(--dimensions-band-progress, 1));', $matches[1]);
        $this->assertStringContainsString('transform-origin: left center;', $matches[1]);
        $this->assertStringContainsString('background: var(--dimensions-navbar-band);', $matches[1]);
    }

    /**
     * Auto-hide stands down on exactly the pages where Boost Union pins the drawers flush with !important.
     *
     * The condition is a copy of Boost Union's, so it is read from Boost Union's own stylesheet: the four
     * block regions and the limited width, in its order. A Boost Union release that changes them reddens this.
     *
     * @return void
     */
    public function test_autohide_stands_down_where_boost_union_pins_the_drawers(): void {
        global $CFG;
        $regions = '#theme-block-region-outside-left, #theme-block-region-outside-right, '
            . '#theme-block-region-outside-top, #theme-block-region-header';

        $scss = $this->stylesheet();
        $this->assertSame(1, preg_match('~\$dimensions-bu-flush: "([^"]*)"\s*\+\s*"([^"]*)";~', $scss, $matches));
        $this->assertSame('.limitedwidth:has(' . $regions . ')', $matches[1] . $matches[2]);
        $this->assertNotFalse(strpos($scss, self::AUTOHIDE . ' {'));

        $bu = (string) file_get_contents($CFG->dirroot . '/theme/boost_union/scss/boost_union/post.scss');
        $bu = (string) preg_replace('~\s+~', ' ', $bu);
        $this->assertStringContainsString('body.limitedwidth:has(' . $regions . ') {', $bu);
    }

    /**
     * Auto-hide: one offset, 60px at rest and none while away; every moved offset reads it; the bar keeps its band.
     *
     * @return void
     */
    public function test_autohide_moves_every_offset_with_one_property(): void {
        $scss = $this->stylesheet();
        $at = strpos($scss, self::AUTOHIDE . ' {');
        $this->assertNotFalse($at);
        // Past the selector, whose interpolation carries braces of its own.
        $block = substr($scss, $at + strlen(self::AUTOHIDE));

        $this->assertMatchesRegularExpression('~^[^{]*\{\s*--dimensions-navbar-offset: #\{\$navbar-height\};~', $block);
        $this->assertMatchesRegularExpression(
            '~&\.dimensions-navbar-away \{\s*--dimensions-navbar-offset: calc\(#\{\$navbar-height\} \* 0\);\s*'
                . '\.navbar\.fixed-top \{\s*transform: translateY\(calc\(3px - 100%\)\);~',
            $block
        );
        foreach (
            [
                '\[data-region="right-hand-drawer"\]\.drawer',
                '#quiz-timer-wrapper',
                '&\.path-mod-assign \.gradingtable thead tr',
                '\.drawer',
                '\.drawer-toggles \.drawer-toggler',
                '\.drawer-left,\s*\.drawer-right',
                '\.drawer\.drawer-left,\s*\.drawer\.drawer-right',
            ] as $selector
        ) {
            $this->assertMatchesRegularExpression(
                '~' . $selector . ' \{\s*top: [^;]*(var\(--dimensions-navbar-offset\)|\$dimensions-anchored-top)~',
                $block,
                "{$selector} reads the offset."
            );
        }
        $this->assertStringContainsString('scroll-margin-top: calc(var(--dimensions-navbar-offset) + 10px);', $block);
        $this->assertStringContainsString(
            '$dimensions-anchored-top: "calc(var(--dimensions-navbar-offset) + #{$dimensions-anchored-gap})";',
            $scss
        );
        $this->assertStringNotContainsString('#page', $block, 'The page keeps its margin: the content must not jump.');
    }
}
