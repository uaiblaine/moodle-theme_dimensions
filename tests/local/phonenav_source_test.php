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
 * Theme Dimensions - Tests for the phone's user menu entries: the stylesheet and the client module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards what no other test reads about scss/phonenav.scss and amd/src/phonemenu.js: the two name the same controls,
 * a control is hidden only once the module has put its entry in the menu, and only below md.
 *
 * Behat cannot see any of it: the Behat site's window is wider than md.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class phonenav_source_test extends \basic_testcase {
    /**
     * A file of the theme, comments removed.
     *
     * @param string $file The file under the theme's root.
     * @return string
     */
    private function file(string $file): string {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        return (string) preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * The controls the stylesheet hides, as its Sass map names them.
     *
     * @return array key => selector.
     */
    private function scss_controls(): array {
        $scss = $this->file('scss/phonenav.scss');
        $this->assertSame(1, preg_match('~\$dimensions-phonenav-controls: \((.*?)\);~s', $scss, $map));
        preg_match_all('~"([a-z]+)": "((?:[^"\\\\]|\\\\.)*)"~', $map[1], $pairs, PREG_SET_ORDER);

        $controls = [];
        foreach ($pairs as $pair) {
            $controls[$pair[1]] = stripslashes($pair[2]);
        }

        return $controls;
    }

    /**
     * The stylesheet and the module name the same four controls, by the same selectors, as the direct children of
     * #usernavigation: the context layer's own focus form and edit switch are deeper and stay.
     *
     * @return void
     */
    public function test_the_stylesheet_and_the_module_name_the_same_controls(): void {
        $scss = $this->scss_controls();
        $this->assertSame([phonenav::MESSAGES, phonenav::MAIL, phonenav::FOCUS, phonenav::EDIT], array_keys($scss));

        $js = $this->file('amd/src/phonemenu.js');
        $this->assertSame(1, preg_match('~const CONTROLS = \{(.*?)\};~s', $js, $block));
        foreach ($scss as $key => $selector) {
            $this->assertMatchesRegularExpression(
                "~\\b$key: '#usernavigation > " . preg_quote($selector, '~') . "[ ']~",
                $block[1],
                "The module presses the control the stylesheet hides: $key."
            );
        }
        $this->assertStringContainsString(
            '&.dimensions-phonenav-#{$key} #usernavigation > #{$control} {',
            $this->file('scss/phonenav.scss')
        );
    }

    /**
     * A control is hidden in place and at once, below md only, and the entries go from md up.
     *
     * @return void
     */
    public function test_a_control_is_hidden_below_md_in_place_and_at_once(): void {
        $scss = $this->file('scss/phonenav.scss');

        $this->assertMatchesRegularExpression(
            '~@include media-breakpoint-down\(md\) \{\s*@each \$key, \$control in \$dimensions-phonenav-controls \{\s*'
                . '&\.dimensions-phonenav-#\{\$key\} #usernavigation > #\{\$control\} \{\s*position: absolute;\s*top: 0;\s*'
                . 'left: 0;\s*visibility: hidden;\s*transition: none;\s*\}~',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '~@include media-breakpoint-up\(md\) \{\s*#user-action-menu \.dimensions-phonemenu-item,\s*'
                . '#user-action-menu \.dimensions-phonemenu-divider \{\s*display: none;~',
            $scss
        );
        $this->assertStringNotContainsString(':has(', $scss, 'scssphp compiles a nested :has() with warnings.');
    }

    /**
     * Every top-level selector is the navbar's own class.
     *
     * @return void
     */
    public function test_every_rule_is_under_the_navbar(): void {
        $scss = $this->file('scss/phonenav.scss');
        $depth = 0;
        $buffer = '';
        $roots = [];
        foreach (str_split($scss) as $at => $char) {
            if ($char === '{' && $depth === 0 && ($scss[$at - 1] ?? '') === '#') {
                $buffer .= $char;
                $depth = -1;
            } else if ($char === '}' && $depth === -1) {
                $buffer .= $char;
                $depth = 0;
            } else if ($char === '{') {
                if ($depth === 0) {
                    $roots[] = trim((string) preg_replace('~\s+~', ' ', $buffer));
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

        $this->assertNotEmpty($roots);
        foreach ($roots as $root) {
            $this->assertStringStartsWith('.navbar.fixed-top', $root);
        }
    }

    /**
     * The module marks the nav only after the entries are in the menu or the armed profile layer, so nothing is hidden
     * before its stand-in exists; focus mode is submitted through its own form, so focusmode.js intercepts it; the
     * counts are read on each opening of either.
     *
     * @return void
     */
    public function test_the_module_hides_nothing_before_the_entry_is_in_the_menu(): void {
        $js = $this->file('amd/src/phonemenu.js');

        $moved = strpos($js, 'holder.remove();');
        $placed = strpos($js, 'intoMenu(main, fragment);');
        $marked = strpos($js, "nav.classList.add('dimensions-phonenav-' + entry.dataset.dimensionsPhone);");
        $this->assertNotFalse($moved);
        $this->assertNotFalse($placed);
        $this->assertNotFalse($marked);
        $this->assertGreaterThan($moved, $marked);
        $this->assertGreaterThan($placed, $marked, 'Marked once the entries are in the menu or the layer.');
        $this->assertSame(1, substr_count($js, 'dimensions-phonenav-'), 'One place marks the nav.');
        $this->assertStringContainsString('entries.forEach((entry) => {', $js);
        $this->assertStringContainsString('document.addEventListener(LAYER.SHOWNEVENT, () => {', $js);

        $this->assertStringContainsString('control.form.requestSubmit(control);', $js);
        // Core's message drawer closes on the click that would open it, unless the opening waits for it to finish.
        $this->assertStringContainsString('window.setTimeout(() => control.click());', $js);
        $this->assertStringContainsString("dropdown.addEventListener('show.bs.dropdown', () => {", $js);
        $this->assertStringContainsString("getString('navbarphone_unread', 'theme_dimensions', count)", $js);
    }
}
