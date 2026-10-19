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
 * Theme Dimensions - Tests for the mobile drawer's stylesheet
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards what nothing else in the pipeline reads about scss/drawer.scss: where its rules reach, which
 * colours they take, and that the theme serves it at all.
 *
 * The file is concatenated into the theme's main SCSS and reaches every page of the site, so a rule
 * escaping the drawer's class would restyle pages the drawer is not on; and a literal colour would ignore
 * the colour mode and a flavour's brand colour, which the family tokens carry.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class drawer_scss_test extends \basic_testcase {
    /** @var string[] The only top-level selectors the file may have: the drawer, and the drawer in dark mode. */
    private const SCOPES = ['.dimensions-drawer', ':root[data-bs-theme="dark"] .dimensions-drawer'];

    /**
     * The stylesheet, comments removed.
     *
     * @return string
     */
    private function stylesheet(): string {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/drawer.scss');
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        return (string) preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * Every selector at the root of the file.
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
            if ($char === '{') {
                if ($depth === 0) {
                    $selectors[] = trim((string) preg_replace('~\s+~', ' ', $buffer));
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
     * Every rule is nested under the drawer's own class, the dark half under core's colour-mode signal.
     *
     * @return void
     */
    public function test_every_rule_is_scoped_to_the_drawer(): void {
        $selectors = $this->top_level_selectors($this->stylesheet());

        $this->assertNotEmpty($selectors);
        $this->assertSame(self::SCOPES, array_values(array_unique($selectors)));
    }

    /**
     * No literal colour: every colour is a token, so the colour mode and a flavour move it.
     *
     * @return void
     */
    public function test_no_literal_colour(): void {
        $scss = $this->stylesheet();

        $this->assertDoesNotMatchRegularExpression('~#[0-9a-fA-F]{3,8}\b~', $scss);
        $this->assertDoesNotMatchRegularExpression('~\b(rgba?|hsla?)\(~', $scss);
        $this->assertDoesNotMatchRegularExpression('~\b(white|black)\b~', $scss);
        $this->assertStringContainsString('var(--dimensions-surface, var(--bs-body-bg))', $scss, 'Control: tokens are read.');
    }

    /**
     * The motion exists only for a reader who did not ask for less, and the ring is the theme's.
     *
     * @return void
     */
    public function test_motion_is_reduced_motion_aware_and_the_ring_is_the_themes(): void {
        $scss = $this->stylesheet();

        $media = strpos($scss, '@media (prefers-reduced-motion: no-preference)');
        $this->assertNotFalse($media);
        $this->assertGreaterThan($media, strpos($scss, 'transition:'), 'No transition before the media query.');
        $this->assertGreaterThan($media, strpos($scss, 'animation:'));
        $this->assertStringContainsString('outline: var(--dim-ds-focus-ring-width) solid var(--dim-drawer-ring);', $scss);
        $this->assertStringContainsString('outline-offset: 2px;', $scss);
        $this->assertStringContainsString('--dim-drawer-ring: var(--dim-ds-focus-ring);', $scss);
    }

    /**
     * A hidden panel is hidden and moved away; the card is shown only once the module arms the drawer.
     *
     * @return void
     */
    public function test_the_layers_and_the_card_follow_the_module(): void {
        $scss = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '~&\.dimensions-drawer-js \{.*?\.dimensions-drawer-panel\[data-state="before"\],\s*'
                . '\.dimensions-drawer-panel\[data-state="after"\] \{[^}]*visibility: hidden;~s',
            $scss
        );
        $this->assertMatchesRegularExpression('~\n    \.dimensions-drawer-resume \{\s*display: none;~', $scss);
        $this->assertMatchesRegularExpression(
            '~&\.dimensions-drawer-js \{.*?\.dimensions-drawer-resume \{\s*display: block;~s',
            $scss
        );
    }

    /**
     * Every touch target of the drawer is at least 44px tall.
     *
     * @return void
     */
    public function test_every_control_is_a_44px_target(): void {
        $scss = $this->stylesheet();

        $controls = [
            '.dimensions-drawer-item',
            '.dimensions-drawer-back',
            '.dimensions-drawer-openparent',
            '.dimensions-drawer-mode',
        ];
        foreach ($controls as $control) {
            $found = preg_match('~\n    ' . preg_quote($control, '~') . ' \{([^}]*)~', $scss, $matches);
            $this->assertSame(1, $found, $control);
            $this->assertStringContainsString('min-height: 2.75rem;', $matches[1], $control);
        }
    }

    /**
     * The theme serves the file: lib.php concatenates it into the main SCSS.
     *
     * @return void
     */
    public function test_the_theme_serves_the_file(): void {
        $this->assertStringContainsString(
            "file_get_contents(\$CFG->dirroot . '/theme/dimensions/scss/drawer.scss')",
            (string) file_get_contents(dirname(__DIR__, 2) . '/lib.php')
        );
    }
}
