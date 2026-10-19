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
 * Theme Dimensions - Tests for the bottom sheets' stylesheet
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards scss/sheets.scss, the notifications popover and the user menu as bottom sheets on phones (D2, D3): every rule
 * is scoped to the navbar and its region and sits under a max-width sm media query, nothing is !important, the motion
 * follows prefers-reduced-motion, and the sheet's own parts (the edge, the handle, the scrim, the 44px rows) are there.
 *
 * The file is concatenated into the theme's main SCSS and reaches every page of the site, so a rule escaping its scope
 * or its breakpoint would turn every dropdown of a desktop navbar into a sheet.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class sheets_scss_test extends \basic_testcase {
    /** @var string The notifications popover's scope: the navbar's class and message_popup's region. */
    private const NOTIFICATIONS = '.navbar.fixed-top .popover-region-notifications';

    /** @var string The user menu's scope: the navbar's class and core's user menu. */
    private const USERMENU = '.navbar.fixed-top .usermenu';

    /** @var string The user menu's keyframes, the one block its scope holds beside the phone block. */
    private const RISE = '@keyframes dimensions-sheet-rise';

    /** @var string The breakpoint every rule sits under: below sm. */
    private const PHONE = '@include media-breakpoint-down(sm)';

    /**
     * The stylesheet, comments removed.
     *
     * @return string
     */
    private function stylesheet(): string {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/sheets.scss');
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
     * The phone block of one scope, after asserting it is the scope's only rule block.
     *
     * @param string $scope One of the two scopes.
     * @param array $allowed Further headers the scope may hold beside the phone block (keyframes).
     * @return string
     */
    private function phone(string $scope, array $allowed = []): string {
        $inner = $this->children($this->children($this->stylesheet())[$scope]);
        $this->assertSame(array_merge([self::PHONE], $allowed), array_keys($inner), "{$scope} holds its phone block alone.");

        return $inner[self::PHONE];
    }

    /**
     * Every top-level selector is one of the two scopes, and the scope check sees an unscoped selector.
     *
     * @return void
     */
    public function test_every_rule_is_scoped(): void {
        $this->assertSame([self::NOTIFICATIONS, self::USERMENU], array_keys($this->children($this->stylesheet())));

        $fixture = self::USERMENU . " { .a { color: red; } }\n.dropdown-menu { position: fixed; }\n";
        $this->assertSame([self::USERMENU, '.dropdown-menu'], array_keys($this->children($fixture)));
    }

    /**
     * Every rule of both sheets sits under the max-width sm media query; nothing is !important.
     *
     * @return void
     */
    public function test_every_rule_sits_below_sm_and_nothing_is_important(): void {
        $this->assertNotSame('', trim($this->phone(self::NOTIFICATIONS)));
        $this->assertNotSame('', trim($this->phone(self::USERMENU, [self::RISE])));
        $this->assertStringNotContainsString('!important', $this->stylesheet());
        $this->assertDoesNotMatchRegularExpression('~#[0-9a-fA-F]{3,6}\b~', $this->stylesheet(), 'No literal colour.');
    }

    /**
     * Both are sheets: fixed to the bottom edge, the full width, rounded top corners, a handle, a scrim of their own
     * shadow, and a height that never reaches over the navbar.
     *
     * @return void
     */
    public function test_both_are_bottom_sheets(): void {
        $sheets = [
            self::NOTIFICATIONS => $this->children($this->phone(self::NOTIFICATIONS))['.popover-region-container'],
            self::USERMENU => $this->children($this->phone(self::USERMENU, [self::RISE]))['.dropdown-menu'],
        ];
        foreach ($sheets as $scope => $sheet) {
            foreach (['position: fixed;', 'top: auto;', 'right: 0;', 'bottom: 0;', 'left: 0;', 'width: auto;'] as $rule) {
                $this->assertStringContainsString($rule, $sheet, "{$scope}: {$rule}");
            }
            $this->assertStringContainsString('max-height: calc(100vh - INTERPOLATION - var(--dim-ds-space-md));', $sheet);
            $this->assertStringContainsString('border-radius: var(--dim-ds-radius-xl) var(--dim-ds-radius-xl) 0 0;', $sheet);
            $this->assertStringContainsString('box-shadow: 0 0 0 100vmax rgba(var(--dim-ds-shadow-rgb), .45);', $sheet);

            $handle = $this->children($sheet)['&::before'] ?? '';
            $this->assertStringContainsString('content: "";', $handle, "{$scope}: the grab handle.");
            $this->assertStringContainsString('width: 2.5rem;', $handle);
        }
        $this->assertStringContainsString('$navbar-height', (string) file_get_contents(dirname(__DIR__, 2) . '/scss/sheets.scss'));

        $open = $this->children($this->phone(self::NOTIFICATIONS))['&:not(.collapsed) .popover-region-container'];
        $this->assertSame('height: 70vh;', trim($open), 'The open popover has a height; core\'s collapsed rule keeps 0.');
    }

    /**
     * The motion follows prefers-reduced-motion: the popover drops core's transition under reduce, and the user menu's
     * rise exists only under no-preference.
     *
     * @return void
     */
    public function test_the_motion_follows_reduced_motion(): void {
        $popover = $this->children($this->phone(self::NOTIFICATIONS));
        $reduce = $this->children($popover['@media (prefers-reduced-motion: reduce)'] ?? '');
        $this->assertSame('transition: none;', trim($reduce['.popover-region-container'] ?? ''));

        $menu = $this->phone(self::USERMENU, [self::RISE]);
        $this->assertSame(1, substr_count($menu, 'animation:'), 'One animation, and it is the one below.');
        $motion = $this->children($this->children($menu)['@media (prefers-reduced-motion: no-preference)'] ?? '');
        $this->assertStringContainsString('animation: dimensions-sheet-rise', $motion['.dropdown-menu.show'] ?? '');
    }

    /**
     * Every control of both sheets is a 44px target.
     *
     * @return void
     */
    public function test_every_row_is_a_touch_target(): void {
        $menu = $this->children($this->children($this->phone(self::USERMENU, [self::RISE]))['.dropdown-menu']);
        $this->assertStringContainsString('min-height: 2.75rem;', $menu['.dropdown-item']);

        $popover = $this->children($this->phone(self::NOTIFICATIONS));
        $this->assertStringContainsString('min-width: 2.75rem;', $popover['.popover-region-header-actions > *']);
        $this->assertStringContainsString('min-height: 2.75rem;', $popover['.popover-region-header-actions > *']);
    }

    /**
     * lib.php serves the file: a sheet not listed there is never compiled.
     *
     * @return void
     */
    public function test_the_stylesheet_is_served(): void {
        $lib = (string) file_get_contents(dirname(__DIR__, 2) . '/lib.php');
        $this->assertStringContainsString("'/theme/dimensions/scss/sheets.scss'", $lib);
    }
}
