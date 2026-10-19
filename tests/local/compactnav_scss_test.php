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
     * At tablet width the drawer stacks above the docked drawers and under the navbar, with its own shadow as the scrim.
     *
     * @return void
     */
    public function test_the_drawer_has_a_scrim_and_a_stack_of_its_own(): void {
        $scss = $this->stylesheet();
        $this->assertMatchesRegularExpression('~\.drawer-primary \{\s*z-index: \$zindex-sticky \+ 2;\s*\}~', $scss);
        $this->assertMatchesRegularExpression(
            '~\.drawer-primary\.show \{\s*box-shadow: 0 0 0 100vmax rgba\(var\(--dim-ds-shadow-rgb\), \.45\);\s*\}~',
            $scss
        );
        $this->assertDoesNotMatchRegularExpression('~#[0-9a-fA-F]{3,6}\b~', $scss, 'No literal colour.');
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
