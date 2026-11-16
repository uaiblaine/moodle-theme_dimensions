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
     * The notifications popover is one surface in the page's colour mode, inside the navbar.
     *
     * message_popup renders it inside the navbar, so it reads whatever colour mode reaches it there, and core's
     * white container turns grey in the dark mode while half of the light palette stays: the popover must read as
     * one surface. The colour mode tokens it reads are re-pointed on the container to the design system's semantic
     * ones, which turn with the page (tests/local/dark_tokens_test.php), and the ink is set from them. It forces no
     * colour scheme: on a dark page its controls and scrollbar are the page's.
     *
     * @return void
     */
    public function test_the_popover_is_one_surface_in_the_pages_mode(): void {
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
        ];
        foreach ($expected as $property => $value) {
            $this->assertStringContainsString("{$property}: {$value};", $body, "{$property} on the popover.");
        }
        $this->assertStringNotContainsString('!important', $body);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $body, 'No literal colour.');
        $this->assertStringNotContainsString('color-scheme', $body, 'The popover follows the page\'s mode.');
    }

    /**
     * The navbar has no border under its band.
     *
     * Core draws a 1px border-bottom on .navbar.fixed-top and Boost Union only recolours it, so without this rule it
     * shows as a line under the band. The control is the band itself, in the same rule.
     *
     * @return void
     */
    public function test_the_navbar_has_no_border_under_its_band(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertSame(1, preg_match('/\n\.navbar\.fixed-top \{(.*?)\n\}/s', $scss, $rule));
        $this->assertStringContainsString('background: var(--dimensions-navbar-band);', $rule[1], 'Control: the band.');
        $this->assertMatchesRegularExpression('/^\s*border-bottom: 0;/', $rule[1]);
    }

    /**
     * The navbar's dropdowns answer Boost Union's hover properties on the menu with the theme's own pill.
     *
     * Boost Union sets the menus' hover pair from --bu-navbar-bg and --bu-navbar-dropdown-hover-color with a
     * selector this theme does not outrank; the two are inherited from the navbar, whose --bu-navbar-bg is the bar's
     * own colour. The control is the menu's own dark surface in the same rule. The pair itself is measured in
     * navbar_menu_colour_test.
     *
     * @return void
     */
    public function test_the_navbar_dropdowns_answer_boost_unions_hover_properties(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertSame(1, preg_match('/\n\.navbar \.dropdown-menu \{(.*?)\n\}/s', $scss, $rule));
        $this->assertStringContainsString('--bs-dropdown-bg: #{$dimensions-menu-surface};', $rule[1], 'Control.');
        foreach (['--bu-navbar-bg', '--bs-dropdown-link-hover-bg', '--bs-dropdown-link-active-bg'] as $ground) {
            $this->assertStringContainsString("{$ground}: #{\$dimensions-menu-highlight};", $rule[1], $ground);
        }
        $inks = ['--bu-navbar-dropdown-hover-color', '--bs-dropdown-link-hover-color', '--bs-dropdown-link-active-color'];
        foreach ($inks as $ink) {
            $this->assertStringContainsString("{$ink}: #{\$dimensions-menu-highlight-ink};", $rule[1], $ink);
        }
    }

    /**
     * A submenu's title row reads the menu's ink, through the channel core's .text-body reads.
     *
     * .text-body is !important, so a plain colour on the back button changes nothing; the row re-points
     * --bs-body-color-rgb, and the hover re-points it again to the pill's ink. The control is the row's own colour.
     *
     * @return void
     */
    public function test_a_submenus_title_row_reads_the_menus_ink(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertSame(1, preg_match('/\n\.navbar \.dropdown-menu \.submenu \.header \{(.*?)\n\}/s', $scss, $rule));
        $this->assertStringContainsString('color: var(--bs-dropdown-color);', $rule[1], 'Control.');
        $this->assertStringContainsString('--bs-body-color-rgb: #{to-rgb($dim-ds-white)};', $rule[1]);
        $this->assertStringContainsString('--bs-body-color-rgb: #{to-rgb($dimensions-menu-highlight-ink)};', $rule[1]);
        $this->assertMatchesRegularExpression(
            '/\.btn-icon \.icon \{\s*display: block;\s*filter: invert\(1\);\s*\}/',
            $rule[1],
            'The arrow is an image: only a filter makes it the menu\'s white.'
        );
        $this->assertMatchesRegularExpression(
            '/a\.carousel-navigation-link \.btn-icon \{\s*--bs-btn-hover-bg: transparent;\s*--bs-btn-active-bg: transparent;\s*\}/',
            $rule[1],
            'Inside Boost Union\'s link the link is the row: one pill, not a second one on the button.'
        );
        $this->assertStringNotContainsString('!important', $rule[1]);
    }

    /**
     * The navbar's menu rows are pills apart, and a submenu collapsed into "More" is one of them.
     *
     * Two pills touching (the chosen row and the hovered one) read as one block; core rings a focused
     * a.dropdown-toggle with a square box-shadow on a mouse click and takes the keyboard's outline away; and core
     * paints the rows of a submenu expanded inside "More" gray-100, under the menu's white ink, at their content's
     * width. The control is the menu rule's own surface.
     *
     * @return void
     */
    public function test_the_navbar_menu_rows_are_pills_apart(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertSame(1, preg_match('/\n\.navbar \.dropdown-menu \{(.*?)\n\}/s', $scss, $rule));
        $this->assertStringContainsString('--bs-dropdown-bg: #{$dimensions-menu-surface};', $rule[1], 'Control.');
        $this->assertMatchesRegularExpression(
            '/\.dropdown-item \+ \.dropdown-item \{\s*margin-top: var\(--dim-ds-space-3xs\);\s*\}/',
            $rule[1],
            'A gap between two rows.'
        );
        $this->assertMatchesRegularExpression('/\.dropdown-item:focus \{\s*box-shadow: none;\s*\}/', $rule[1]);
        $this->assertMatchesRegularExpression(
            '/\.dropdown-item:focus-visible \{\s*outline: var\(--dim-ds-focus-ring-width\) solid var\(--dim-ds-focus-ring\);\s*\}/',
            $rule[1],
            'The keyboard keeps the theme\'s ring.'
        );

        $submenu = '\.navbar \.dropdownmoremenu > \.dropdown-menu \.dropdown-submenu > \.dropdown-menu';
        $this->assertSame(1, preg_match('/\n' . $submenu . ' \{(.*?)\n\}/s', $scss, $nested));
        $this->assertMatchesRegularExpression('/^\s*width: auto;/', $nested[1], 'As wide as its toggle.');
        $this->assertMatchesRegularExpression(
            '/\.dropdown-item \{\s*background-color: transparent;/',
            $nested[1],
            'The menu\'s ground, not core\'s gray-100.'
        );
        $this->assertStringContainsString('background-color: var(--bs-dropdown-link-hover-bg);', $nested[1]);
        $this->assertStringNotContainsString('!important', $rule[1] . $nested[1]);
    }

    /**
     * The current page's row in "More" is in the menu's ink, and its pill under the pointer is the menu's pill.
     *
     * Core's moremenu.scss paints that row in --mds-text-default (the React mount) or $dropdown-link-color (the
     * legacy markup), dark inks on the theme's dark surface, and hovers it in Bootstrap's compiled pair. The control
     * is core's own rule, read from core's source: if core stops painting the row, this pin is the one to revisit.
     *
     * @return void
     */
    public function test_the_current_row_in_more_reads_on_the_dark_menu(): void {
        global $CFG;

        $core = file_get_contents($CFG->dirroot . '/theme/boost/scss/moodle/moremenu.scss');
        $this->assertNotFalse($core, 'Precondition: core\'s stylesheet is readable.');
        $this->assertMatchesRegularExpression(
            '/\.more-nav \{\s*\.dropdown-item\[aria-current="page"\],\s*\.dropdown-item\.active \{'
                . '[^}]*color: var\(--mds-text-default\);/',
            $core,
            'Control: core paints the React row in a dark ink.'
        );

        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertSame(
            1,
            preg_match('/\n\.navbar \.more-nav,\n\.navbar \.moremenu \{(.*?)\n\}/s', $scss, $rule),
            'One rule for the React and the legacy markup, under .navbar.'
        );
        $this->assertMatchesRegularExpression(
            '/\.dropdown-item\[aria-current="page"\],\s*\.dropdown-item\[aria-current="true"\],\s*\.dropdown-item\.active \{\s*'
                . 'color: var\(--bs-dropdown-link-color\);/',
            $rule[1],
            'The resting row, and so its tick, in the menu\'s ink.'
        );
        $this->assertMatchesRegularExpression(
            '/&:hover,\s*&:focus,\s*&:focus-within \{\s*color: var\(--bs-dropdown-link-hover-color\);\s*'
                . 'background-color: var\(--bs-dropdown-link-hover-bg\);/',
            $rule[1],
            'Under the pointer or the keyboard, the pill every other row takes.'
        );
        $this->assertStringNotContainsString('!important', $rule[1]);
    }

    /**
     * An open navbar menu's toggle is a rounded pill, never a square in the ink's colour.
     *
     * Bootstrap's open .btn reads --bs-btn-active-border-color, which Moodle's .btn never sets, so the border falls
     * back to currentColor round a toggle core squares at the bar's full height. The pill is the ::before, in the
     * navbar pills' hover tint, and the keyboard's ring goes round it. The control is the three menus in the
     * selector.
     *
     * @return void
     */
    public function test_an_open_navbar_menu_toggle_is_a_rounded_pill(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertSame(
            1,
            preg_match('/\n\.navbar \.usermenu,\n\.navbar \.langmenu,\n\.navbar \.colourmodemenu \{(.*?)\n\}/s', $scss, $rule),
            'Control: one rule for the three toggles.'
        );
        $this->assertStringContainsString('> .dropdown > .dropdown-toggle {', $rule[1]);
        $this->assertStringContainsString('--bs-btn-active-border-color: transparent;', $rule[1], 'No square border.');
        $this->assertMatchesRegularExpression(
            '/&::before \{[^}]*border-radius: var\(--dim-ds-radius-md\);[^}]*content: "";/s',
            $rule[1],
            'The rounded pill.'
        );
        $this->assertMatchesRegularExpression(
            '/&\.show::before \{\s*background-color: var\(--dimensions-pill-hover, var\(--dim-ds-surface-subtle\)\);/',
            $rule[1],
            'Painted while the menu is open.'
        );
        $this->assertMatchesRegularExpression(
            '/&::before \{\s*outline: var\(--dim-ds-focus-ring-width\) solid var\(--dim-ds-focus-ring\);\s*outline-offset: 2px;/',
            $rule[1],
            'The keyboard\'s ring round the pill.'
        );
        $this->assertStringNotContainsString('!important', $rule[1]);
    }

    /**
     * The user menu's "Log out" row is found by its address and carries core's logout glyph at its end.
     *
     * Core prints the row with no class of its own, so the address is the only hook. Its grounds sit on the row,
     * both at rest and under the pointer, so the menu's brand pill never reaches it; the colours themselves are
     * measured in navbar_menu_colour_test. The control is the selector itself.
     *
     * @return void
     */
    public function test_the_logout_row_is_a_red_pill_with_its_glyph(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $selector = '\.navbar \.usermenu \.dropdown-menu a\.dropdown-item\[href\*="\/login\/logout\.php"\]';
        $this->assertSame(1, preg_match('/\n' . $selector . ' \{(.*?)\n\}/s', $scss, $rule), 'Control: one rule.');
        $this->assertMatchesRegularExpression('/^\s*--bs-dropdown-link-hover-bg: #\{\$dimensions-menu-logout-hover\};/m', $rule[1]);
        $this->assertMatchesRegularExpression('/^    background-color: \$dimensions-menu-logout;/m', $rule[1], 'Red at rest.');
        $this->assertMatchesRegularExpression(
            '/&:hover,\s*&:focus \{\s*background-color: \$dimensions-menu-logout-hover;/',
            $rule[1],
            'The brighter red under the pointer and the keyboard.'
        );
        $this->assertMatchesRegularExpression(
            '/&::after \{\s*font: var\(--fa-font-solid\);\s*content: fa-content\(\$fa-var-right-from-bracket\);\s*\}/',
            $rule[1],
            'Core\'s logout glyph.'
        );
        $this->assertStringContainsString('justify-content: space-between;', $rule[1], 'The glyph at the row\'s end.');
        $this->assertStringNotContainsString('!important', $rule[1]);
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
     * The navbar's dividers are a quiet rule on a dark bar, and the colour mode menu leaves the navbar at every width
     * where the armed profile layer carries it.
     *
     * The divider's property is declared on the divider itself, where Boost Union's own declarations read it. The colour
     * mode menu is hidden only while amd/src/profilelayer.js has written dimensions-profilelayer-modes on the body, which
     * it does only once it has armed a layer that holds the control (profilelayer_source_test): with the module not
     * running, the menu stays. The navigation drawer carries no colour mode control (D72), so no rule keys on it: one
     * would hide the navbar's menu with no twin anywhere.
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
        $scope = "\nbody.dimensions-profilelayer-modes #usernavigation {";
        $at = strpos($scss, $scope);
        $this->assertNotFalse($at, 'Missing scope: ' . $scope);
        $this->assertMatchesRegularExpression(
            '/^[^{]*\{\s*\.colourmodemenu,\s*\.colourmodemenu \+ \.divider \{\s*display: none;/',
            substr($scss, $at + strlen($scope) - 1)
        );
        $this->assertSame(1, substr_count($scss, '.colourmodemenu + .divider'), 'The layer\'s rule is the only one.');
        $this->assertStringNotContainsString('dimensions-drawer-modes', $scss);
    }

    /**
     * The navigation drawer is the side layer's 320px (20rem), at most 85% of a phone's width, from the theme's own
     * variable on Boost's primary drawer below md; the compact navbar's copy of the geometry reads the same variable,
     * and neither redefines core's $drawer-left-width, which places the course index too.
     *
     * @return void
     */
    public function test_the_navigation_drawer_is_the_side_layers_width(): void {
        $root = dirname(__DIR__, 2);
        $scss = (string) file_get_contents($root . '/scss/post.scss');
        $this->assertStringContainsString("\n\$dimensions-drawer-width: 20rem;\n", $scss);
        $this->assertSame(1, preg_match(
            '~@include media-breakpoint-down\(md\) \{\s*#theme_boost-drawers-primary\.drawer-primary\.drawer-left \{'
                . '(.*?)&\.show \{([^}]*)\}~s',
            $scss,
            $found
        ), 'The id and the two drawer classes, below md.');
        [, $drawer, $shown] = $found;
        $this->assertStringContainsString('left: calc(-#{$dimensions-drawer-width} + -10px);', $drawer, 'Off screen, closed.');
        $this->assertStringContainsString('width: $dimensions-drawer-width;', $drawer);
        $this->assertStringContainsString('max-width: 85vw;', $drawer);
        $this->assertStringContainsString('left: 0;', $shown);

        $compact = (string) file_get_contents($root . '/scss/compactnav.scss');
        $this->assertSame(3, substr_count($compact, 'width: $dimensions-drawer-width;'), 'width, max-width and the open width.');
        foreach ([$scss, $compact] as $sheet) {
            $this->assertDoesNotMatchRegularExpression('~\$drawer-left-width\s*:~', $sheet, 'Core\'s variable is never set.');
            $this->assertStringNotContainsString('width: $drawer-left-width;', $sheet);
        }
    }

    /**
     * The logo keeps 8px of the bar around it, in the navbar and in the drawer's header, from one height.
     *
     * The drawer lies over the navbar, so its logo and the navbar's are one size; core's own is 52px of a 60px bar.
     *
     * @return void
     */
    public function test_the_logo_keeps_a_margin_of_the_bar_in_both_places(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertStringContainsString(
            '$dimensions-logo-height: calc(#{$navbar-height} - 2 * var(--dim-ds-space-xs));',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '/\n\.navbar\.fixed-top \.navbar-brand \.logo \{\s*height: \$dimensions-logo-height;'
                . '\s*max-height: \$dimensions-logo-height;/',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '/\n    \.logo \{\s*height: \$dimensions-logo-height;\s*max-height: \$dimensions-logo-height;\s*\}/',
            $scss,
            'The drawer header\'s logo reads the same height.'
        );
    }

    /**
     * A logo whose width Boost Union caps keeps its ratio inside the navbar's fixed height.
     *
     * Boost Union's "Maximal width of logo in navbar" writes max-width and height: auto on the brand's logo; the
     * theme's height outranks that height, so without object-fit the image is squeezed sideways into the capped
     * box. Boost Union's generator is read as well, so a change of its selector or of its height shows here.
     *
     * @return void
     */
    public function test_a_logo_capped_by_boost_union_keeps_its_ratio(): void {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertNotFalse($scss, 'Precondition: the stylesheet is readable.');
        $this->assertMatchesRegularExpression(
            '/\n\.navbar\.fixed-top \.navbar-brand \.logo \{\s*height: \$dimensions-logo-height;'
                . '\s*max-height: \$dimensions-logo-height;\s*object-fit: contain;\s*\}/',
            $scss
        );

        $locallib = \core_component::get_plugin_directory('theme', 'boost_union') . '/locallib.php';
        $source = file_get_contents($locallib);
        $this->assertNotFalse($source, 'Precondition: Boost Union\'s locallib.php is readable.');
        $this->assertMatchesRegularExpression(
            "/'\\.navbar-brand, \\.navbar-brand \\.logo \\{\\s*max-width: ' \\. get_config\\('theme_boost_union', "
                . "'maxlogowidth'\\) \\. ';\\s*height: auto;/",
            $source,
            'Boost Union still caps the brand\'s logo by width and asks for height: auto.'
        );
    }

    /**
     * The navbar's dark half keys on Boost Union's .navbar-dark, never on a colour mode the navbar no longer carries.
     *
     * Boost Union's 5.3 navbar carries no data-bs-theme, so a rule keyed on it matches nothing and the pills and the
     * dark logo fall back to their light values on the navy bar, silently.
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
