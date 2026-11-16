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
 * Theme Dimensions - Tests for the compact navbar's stylesheet
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards what nothing else in the pipeline reads about scss/compactnav.scss: where its rules reach, the range they
 * live in, and how the primary navigation is hidden.
 *
 * The file is concatenated into the theme's main SCSS and reaches every page of the site, so a rule escaping the
 * armed body class would hide the primary navigation of pages that never asked for any of it.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class compactnav_scss_test extends \basic_testcase {
    /**
     * A stylesheet of the theme, comments removed.
     *
     * @param string $file The file under scss/.
     * @return string
     */
    private function stylesheet(string $file = 'compactnav.scss'): string {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/' . $file);
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        return (string) preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * The blocks directly inside a block: each header with its body.
     *
     * A Sass interpolation is a value, never a block, so each one is read as one word first.
     *
     * @param string $scss A block's body (or a whole stylesheet).
     * @return array Header => body, whitespace in the header collapsed; a header met twice keeps its bodies joined.
     */
    private function children(string $scss): array {
        $scss = (string) preg_replace('~#\{[^{}]*\}~', 'INTERPOLATION', $scss);
        $children = [];
        $depth = 0;
        $buffer = '';
        $header = '';
        $start = 0;
        $length = strlen($scss);
        for ($i = 0; $i < $length; $i++) {
            $char = $scss[$i];
            if ($char === '{') {
                if ($depth === 0) {
                    $header = trim((string) preg_replace('~\s+~', ' ', $buffer));
                    $start = $i + 1;
                }
                $depth++;
            } else if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $children[$header] = ($children[$header] ?? '') . substr($scss, $start, $i - $start);
                    $buffer = '';
                }
            } else if ($depth === 0) {
                $buffer = $char === ';' ? '' : $buffer . $char;
            }
        }

        return $children;
    }

    /**
     * Every rule is nested under the armed body class the server writes, and the class is the server's.
     *
     * @return void
     */
    public function test_every_rule_is_under_the_armed_body_class(): void {
        $this->assertSame(['body.' . compactnav::BODY_ARMED], array_keys($this->children($this->stylesheet())));
    }

    /**
     * The scope check sees an unscoped selector.
     *
     * @return void
     */
    public function test_the_scope_check_sees_an_unscoped_selector(): void {
        $fixture = "body.dimensions-navcompact-armed { .a { color: red; } }\n.primary-navigation { visibility: hidden; }\n";

        $this->assertSame(
            ['body.dimensions-navcompact-armed', '.primary-navigation'],
            array_keys($this->children($fixture))
        );
    }

    /**
     * Every rule sits inside the md to xl range, the one the module's RANGE spells; only the keyframes are outside it.
     *
     * @return void
     */
    public function test_every_rule_lives_between_md_and_xl(): void {
        $armed = $this->children($this->stylesheet())['body.' . compactnav::BODY_ARMED];

        $this->assertSame(
            ['@include media-breakpoint-between(md, xl)', '@keyframes dimensions-navcompact-failopen'],
            array_keys($this->children($armed))
        );
    }

    /**
     * Until the module has measured, and only with JavaScript, the primary navigation is held back in place and comes
     * back by itself should the module never run; compact hides it in place too.
     *
     * @return void
     */
    public function test_the_navigation_is_hidden_in_place_and_fails_open(): void {
        $range = $this->children($this->children($this->stylesheet())['body.' . compactnav::BODY_ARMED]);
        $rules = $this->children($range['@include media-breakpoint-between(md, xl)']);

        $hold = $rules['&.jsenabled:not(.dimensions-navcompact-measured) .primary-navigation'] ?? null;
        $this->assertNotNull($hold, 'The hold is scoped to core\'s jsenabled and lifted by the module\'s class.');
        $this->assertStringContainsString('visibility: hidden;', $hold);
        $this->assertStringContainsString('animation: dimensions-navcompact-failopen 100ms 2s forwards;', $hold);
        $this->assertStringNotContainsString('display', $hold, 'A hidden box keeps the size core\'s navigation measures.');

        $compact = $this->children($rules['&.dimensions-nav-compact']);
        $this->assertSame('visibility: hidden;', trim($compact['.primary-navigation']));
    }

    /**
     * The toggler's d-md-none is !important, so the stylesheet never tries to show it: the module takes the class off.
     *
     * @return void
     */
    public function test_the_stylesheet_never_shows_the_toggler_itself(): void {
        $scss = $this->stylesheet();
        $this->assertDoesNotMatchRegularExpression('~navbar-toggler\s*\{[^}]*display~', $scss);
        $this->assertStringNotContainsString('!important', $scss);
    }

    /**
     * At tablet width the drawer stacks above the docked drawers, with its own shadow as the scrim wherever core draws
     * no backdrop.
     *
     * Core 5.3 shows its drawers' backdrop below core/pagehelpers' Sizes.medium (991px), inside the compact range: the
     * shadow must stand down while that backdrop is on the page, or the page is dimmed twice.
     *
     * @return void
     */
    public function test_the_drawer_has_a_scrim_and_a_stack_of_its_own(): void {
        $scss = $this->stylesheet();
        $this->assertMatchesRegularExpression('~\.drawer-primary \{\s*z-index: \$zindex-sticky \+ 2;\s*\}~', $scss);
        $this->assertMatchesRegularExpression(
            '~\.drawer-primary\.show \{\s*box-shadow: 0 0 0 100vmax'
                . ' var\(--dimensions-backdrop, rgba\(var\(--bs-black-rgb, 0, 0, 0\), \.5\)\);\s*\}~',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '~&:has\(\.modal-backdrop\.show\) \.drawer-primary\.show \{\s*box-shadow: none;\s*\}~',
            $scss,
            'One scrim: the shadow stands down where core shows its backdrop.'
        );
        $this->assertDoesNotMatchRegularExpression('~#[0-9a-fA-F]{3,6}\b~', $scss, 'No literal colour.');
        $this->assertStringNotContainsString('--dim-ds-shadow-rgb', $scss, 'The navy shadow tint is not a scrim.');
    }

    /**
     * Core anchors every .drawer-left from lg up (zero width, an anchored right offset, clipped), and the primary drawer
     * is one: in compact mode it must be the plain overlay drawer again, under a selector the anchored ones cannot outrank,
     * over the navbar and the full height of the screen as below md, and as wide as there: the theme's
     * $dimensions-drawer-width (post_scss_test::test_the_navigation_drawer_is_the_side_layers_width).
     *
     * @return void
     */
    public function test_the_primary_drawer_is_a_plain_overlay_drawer_where_core_anchors_every_left_drawer(): void {
        $scss = $this->stylesheet();
        $this->assertSame(1, preg_match(
            '~&\.dimensions-nav-compact \{.*?\n\s+#theme_boost-drawers-primary\.drawer-primary\.drawer-left \{'
                . '(.*?)&\.show \{(.*?)\}\s*\}~s',
            $scss,
            $found
        ), 'The id and the two drawer classes sit in the compact scope and outrank core\'s anchored selectors.');
        [, $drawer, $shown] = $found;

        foreach (
            [
                'left: calc(-#{$dimensions-drawer-width} + -10px);',
                'right: auto;',
                'top: 0;',
                'width: $dimensions-drawer-width;',
                'max-width: $dimensions-drawer-width;',
                'height: 100vh;',
                'max-height: 100vh;',
                'overflow: visible;',
                '@include border-radius(0);',
            ] as $declaration
        ) {
            $this->assertStringContainsString($declaration, $drawer);
        }

        $this->assertStringContainsString('left: 0;', $shown, 'Open, against the left edge.');
        $this->assertStringContainsString('right: auto;', $shown);
        $this->assertStringContainsString('width: $dimensions-drawer-width;', $shown);
    }

    /**
     * lib.php serves the file: a sheet not listed there is never compiled.
     *
     * @return void
     */
    public function test_the_stylesheet_is_served(): void {
        $lib = (string) file_get_contents(dirname(__DIR__, 2) . '/lib.php');
        $this->assertStringContainsString("'/theme/dimensions/scss/compactnav.scss'", $lib);
    }
}
