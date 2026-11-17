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
    /**
     * @var string[] The only top-level selector the file may have: the drawer. Its dark mode is the family tokens' own,
     *      which every token of the drawer reads.
     */
    private const SCOPES = ['.dimensions-drawer'];

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
     * Every rule is nested under the drawer's own class.
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
     * The body of one rule nested one level under the drawer, by its exact selector.
     *
     * @param string $scss The stylesheet, comments removed.
     * @param string $selector The selector.
     * @return string The declarations up to the rule's first nested block or its end.
     */
    private function rule(string $scss, string $selector): string {
        $found = preg_match('~\n    ' . preg_quote($selector, '~') . ' \{([^}]*)~', $scss, $matches);
        $this->assertSame(1, $found, $selector);

        return $matches[1];
    }

    /**
     * A row is the side layer's (D69): 40px, 44px under a coarse pointer, at 0.875rem, 12px between its parts, padding
     * 4px 8px, its glyph in the muted ink; the "Go to" link of a sub-panel stays a 44px target.
     *
     * @return void
     */
    public function test_a_row_is_the_side_layers_row(): void {
        $scss = $this->stylesheet();

        $row = $this->rule($scss, '.dimensions-drawer-item');
        $this->assertStringContainsString('min-height: 2.5rem;', $row);
        $this->assertStringContainsString('font-size: .875rem;', $row);
        $this->assertStringContainsString('gap: var(--dim-ds-space-sm);', $row);
        $this->assertStringContainsString('padding: var(--dim-ds-space-xxs) var(--dim-ds-space-xs);', $row);
        $this->assertMatchesRegularExpression(
            '~\n    \.dimensions-drawer-item \{[^}]*@media \(pointer: coarse\) \{\s*min-height: 2\.75rem;~',
            $scss
        );
        $this->assertStringContainsString('color: var(--dim-drawer-muted);', $this->rule($scss, '.dimensions-drawer-icon'));
        $this->assertStringContainsString('--dim-drawer-muted: var(--dimensions-ink-muted, var(--bs-secondary-color));', $scss);
        $this->assertStringContainsString('min-height: 2.75rem;', $this->rule($scss, '.dimensions-drawer-openparent'));
    }

    /**
     * The current row is the brand's tint and ink at 600, its glyph in the ink, with no 3px mark on its edge (D72).
     *
     * @return void
     */
    public function test_the_current_row_is_tinted_without_a_mark(): void {
        $scss = $this->stylesheet();

        $current = $this->rule($scss, '.dimensions-drawer-item.is-current');
        $this->assertStringContainsString('background-color: var(--dim-drawer-current);', $current);
        $this->assertStringContainsString('color: var(--dim-drawer-current-ink);', $current);
        $this->assertStringContainsString('font-weight: 600;', $current);
        $this->assertStringContainsString(
            '--dim-drawer-current: var(--dimensions-brand-tint, var(--bs-primary-bg-subtle));',
            $scss
        );
        $this->assertStringContainsString(
            '--dim-drawer-current-ink: var(--dimensions-brand-ink, var(--bs-primary-text-emphasis));',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '~\.dimensions-drawer-item\.is-current \{[^}]*\.dimensions-drawer-icon,[^{]*\{'
                . '\s*color: var\(--dim-drawer-current-ink\);~',
            $scss
        );
        $this->assertStringNotContainsString('box-shadow', $scss, 'No inset mark, in either colour mode.');
        $this->assertStringNotContainsString('--dim-drawer-mark', $scss);
    }

    /**
     * A sub-panel's head is the side layer's sub-view header: a 32px back icon button in the muted ink and the 1rem / 600
     * title, a line under them; no close button of its own, Boost's band carries one.
     *
     * @return void
     */
    public function test_a_sub_panels_head_is_the_sub_view_header(): void {
        $scss = $this->stylesheet();

        $head = $this->rule($scss, '.dimensions-drawer-sub');
        $this->assertStringContainsString('display: flex;', $head);
        $this->assertStringContainsString('gap: var(--dim-ds-space-xs);', $head);
        $this->assertStringContainsString('padding: var(--dim-ds-space-sm) var(--dim-ds-space-xs);', $head);
        $this->assertStringContainsString('border-bottom: 1px solid var(--dim-drawer-line);', $head);

        $back = $this->rule($scss, '.dimensions-drawer-back');
        $this->assertStringContainsString('width: 2rem;', $back);
        $this->assertStringContainsString('height: 2rem;', $back);
        $this->assertStringContainsString('color: var(--dim-drawer-muted);', $back);

        $title = $this->rule($scss, '.dimensions-drawer-title');
        $this->assertStringContainsString('font-size: 1rem;', $title);
        $this->assertStringContainsString('font-weight: 600;', $title);
    }

    /**
     * The drawer draws nothing about the person (D72): no identity, no colour mode switch.
     *
     * @return void
     */
    public function test_nothing_about_the_person_is_styled(): void {
        $scss = $this->stylesheet();
        $this->assertStringContainsString('.dimensions-drawer-resume', $scss, 'Control: the card is styled.');
        $gone = ['.dimensions-drawer-who', '.dimensions-drawer-name', '.dimensions-drawer-role', '.dimensions-drawer-mode'];
        foreach ($gone as $selector) {
            $this->assertStringNotContainsString($selector, $scss, $selector);
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
