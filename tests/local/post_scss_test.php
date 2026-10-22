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
 * Theme Dimensions - Tests for the text colours of the theme's main stylesheet
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards the contrast and colour-mode rules of scss/post.scss that no linter reads.
 *
 * The subtle grey token reads 2.96:1 on a white card, under the 4.5:1 that text needs at any
 * size, so it may colour a rule or an icon and never a label. The backdrop token, the notifications popover's
 * light surface and the navbar's dark half are held here too.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class post_scss_test extends \basic_testcase {
    /**
     * No declaration of the main stylesheet paints text with the subtle grey.
     *
     * The control is the muted token, which the stylesheet does use for text: a pattern that matched
     * no colour declaration at all would pass for the wrong reason.
     *
     * @return void
     */
    public function test_no_text_is_painted_with_the_subtle_grey(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertGreaterThan(
            0,
            preg_match_all('/(?<![-\w])color:\s*var\(--dim-ds-text-muted\)/', $scss),
            'Control: the muted token colours text somewhere.'
        );
        $this->assertSame(
            0,
            preg_match_all('/(?<![-\w])color:\s*var\(--dim-ds-text-subtle\)/', $scss, $found),
            'The subtle grey colours text in: ' . implode(', ', $found[0] ?? [])
        );
    }


    /**
     * The scrim token is a neutral black in both modes, heavier on the dark page, read through a fallback chain.
     *
     * The design system's shadow tint is navy (7, 0, 147): at the scrim's alpha it turns the whole page purple, so
     * no declaration of the token may read it. The control is that the tint still exists for the faint shadows.
     *
     * @return void
     */
    public function test_the_backdrop_token_is_a_neutral_black_in_both_modes(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertStringContainsString('--dimensions-shadow: rgba(var(--dim-ds-shadow-rgb), .1);', $scss, 'Control.');

        preg_match_all('/(?<![-\w])--dimensions-backdrop:\s*([^;]+);/', $scss, $found);
        $this->assertSame(
            ['rgba(var(--bs-black-rgb, 0, 0, 0), .5)', 'rgba(var(--bs-black-rgb, 0, 0, 0), .7)'],
            $found[1],
            'Declared once for the light page and once for the dark one, in that order.'
        );
        $dark = strpos($scss, ':root[data-bs-theme="dark"] {');
        $this->assertNotFalse($dark, 'Precondition: the backdrop has a block of its own on the dark root.');
        $this->assertGreaterThan($dark, strpos($scss, $found[0][1]), 'The heavier veil is the dark root\'s.');
        $this->assertLessThan($dark, strpos($scss, $found[0][0]), 'The lighter one is the light root\'s.');
    }

    /**
     * The notifications popover is one light surface inside the dark navbar.
     *
     * message_popup renders it inside the navbar, so it reads whatever colour mode reaches it there, and core's
     * white container turns grey in the dark mode while half of the light palette stays: the popover must read as
     * one light surface. The colour mode tokens it reads are re-pointed on the container to the design system's
     * light ones, and the ink is set from them.
     *
     * @return void
     */
    public function test_the_popover_is_one_light_surface_inside_the_navbar(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertSame(1, substr_count($scss, "\n.navbar .popover-region-container {"), 'One rule, on the container.');
        preg_match('/\n\.navbar \.popover-region-container \{(.*?)\n\}/s', $scss, $rule);
        $body = $rule[1] ?? '';

        $expected = [
            '--bs-white' => 'var(--dim-ds-surface-card)',
            '--bs-body-bg' => 'var(--dim-ds-surface-card)',
            '--bs-body-color' => 'var(--dim-ds-text-body)',
            '--bs-heading-color' => 'var(--dim-ds-text-body)',
            '--bs-tertiary-bg' => 'var(--dim-ds-surface-subtle)',
            '--bs-link-color' => 'var(--dim-ds-text-link)',
            '--bs-link-hover-color' => 'var(--dim-ds-text-link-hover)',
            'color' => 'var(--bs-body-color)',
            'color-scheme' => 'light',
        ];
        foreach ($expected as $property => $value) {
            $this->assertStringContainsString("{$property}: {$value};", $body, "{$property} on the popover.");
        }
        $this->assertStringNotContainsString('!important', $body);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $body, 'No literal colour.');
    }

    /**
     * The navy dropdowns answer Boost Union's hover properties on the menu, or a hover is navy on navy.
     *
     * Boost Union sets the menus' hover pair from --bu-navbar-bg and --bu-navbar-dropdown-hover-color with a
     * selector this theme does not outrank; the two are inherited from the navbar, whose --bu-navbar-bg is the bar's
     * own navy. The control is the menu's own navy surface in the same rule.
     *
     * @return void
     */
    public function test_the_navy_dropdowns_answer_boost_unions_hover_properties(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertSame(1, preg_match('/\n\.navbar \.dropdown-menu \{(.*?)\n\}/s', $scss, $rule));
        $this->assertStringContainsString('--bs-dropdown-bg: var(--dim-ds-surface-inverse-deep);', $rule[1], 'Control.');
        $this->assertStringContainsString('--bu-navbar-bg: rgba(255, 255, 255, .12);', $rule[1]);
        $this->assertStringContainsString('--bu-navbar-dropdown-hover-color: var(--dim-ds-text-inverse);', $rule[1]);
    }

    /**
     * The primary drawer covers the navbar by stepping the navbar down, never by raising the drawer.
     *
     * Core sizes the drawers' shared backdrop from the first drawer's z-index once, so a raised primary drawer would
     * put the backdrop over the course index drawer. The navbar steps down to $zindex-sticky only while the primary
     * drawer is open, and the drawer reaches the top of the screen.
     *
     * @return void
     */
    public function test_the_primary_drawer_opens_over_the_navbar_without_raising_itself(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertSame(1, preg_match('/\n#theme_boost-drawers-primary\.drawer-primary \{([^}]*)\}/', $scss, $drawer));
        $this->assertStringContainsString('top: 0;', $drawer[1]);
        $this->assertStringContainsString('height: 100vh;', $drawer[1]);
        $this->assertStringNotContainsString('z-index', $drawer[1], 'Raising the drawer raises core\'s backdrop.');
        $this->assertMatchesRegularExpression(
            '/\nbody:has\(#theme_boost-drawers-primary\.show\) \.navbar\.fixed-top \{\s*z-index: \$zindex-sticky;\s*\}/',
            $scss
        );
    }

    /**
     * The drawer's header is painted by the navbar's own classes, and its logo takes the navbar's dark variant.
     *
     * The band is an element of the drawer template carrying navbarcolorclasses; the control is that the template
     * draws it inside the header block, before the logo's link.
     *
     * @return void
     */
    public function test_the_drawer_header_is_the_navbars_band(): void {
        $root = dirname(__DIR__, 2);
        $template = file_get_contents($root . '/templates/theme_boost/primary-drawer-mobile.mustache');
        $this->assertMatchesRegularExpression(
            '~\{\{\$drawerheading\}\}\s*<span class="navbar \{\{navbarcolorclasses\}\} dimensions-drawer-band" aria-hidden="true">'
                . '</span>\s*<a~',
            $template
        );

        $scss = file_get_contents($root . '/scss/post.scss');
        $this->assertMatchesRegularExpression(
            '/\n\.drawer-primary \.drawerheader \{\s*position: relative;\s*z-index: 0;\s*\.dimensions-drawer-band \{'
                . '\s*position: absolute;\s*inset: 0;\s*z-index: -1;/',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '/\.drawer-primary \.drawerheader:has\(\.dimensions-drawer-band\.navbar-dark\) \{\s*--dimensions-logo-dark:/',
            $scss
        );
    }

    /**
     * The navbar's dividers are a quiet rule on a dark bar, and the colour mode menu leaves the collapsed navbar where the
     * drawer carries the appearance switch.
     *
     * The divider's property is declared on the divider itself, where Boost Union's own declarations read it. The colour
     * mode menu is hidden only while the drawer holds its twin (.dimensions-drawer-modes): a visitor's drawer has none.
     *
     * @return void
     */
    public function test_the_phone_navbar_is_quiet_and_carries_no_twin_control(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertMatchesRegularExpression(
            '/\n\.navbar\.navbar-dark \.divider \{\s*--bu-navbar-divider-color: rgba\(255, 255, 255, \.15\);\s*\}/',
            $scss
        );
        foreach (
            [
                '@include media-breakpoint-down(md) {' . "\n" . '    body:has(.dimensions-drawer-modes) #usernavigation {',
                "\nbody.dimensions-nav-compact:has(.dimensions-drawer-modes) #usernavigation {",
            ] as $scope
        ) {
            $at = strpos($scss, $scope);
            $this->assertNotFalse($at, 'Missing scope: ' . $scope);
            $this->assertMatchesRegularExpression(
                '/^[^{]*\{\s*\.colourmodemenu,\s*\.colourmodemenu \+ \.divider \{\s*display: none;/',
                substr($scss, $at + strlen($scope) - 1)
            );
        }
    }

    /**
     * The navbar's dark half keys on Boost Union's .navbar-dark, never on a colour mode the navbar no longer carries.
     *
     * Boost Union's upgrade-503 stopped writing data-bs-theme on the navbar, so a rule keyed on it matches nothing
     * and the pills and the dark logo fall back to their light values on the navy bar, silently.
     *
     * @return void
     */
    public function test_the_navbar_rules_key_on_navbar_dark(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertStringNotContainsString('.navbar[data-bs-theme', $scss);
        $this->assertMatchesRegularExpression('/\n\.navbar\.navbar-dark \{\s*--dimensions-pill-border:/', $scss);
        $this->assertMatchesRegularExpression(
            '/\n\[data-bs-theme="dark"\],\s*\.navbar\.navbar-dark,[^{]*\{\s*--dimensions-logo-dark:/',
            $scss
        );
    }
}
