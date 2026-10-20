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
 * size, so it may colour a rule or an icon and never a label. The backdrop token and the notifications popover's
 * light surface are held here too.
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
     * message_popup renders it inside the navbar, so Bootstrap's dark colour mode repaints its container while the
     * ink stays the page's, and Boost Union's own rules for it (!important) draw dark icons, a white footer and a
     * blue link: the popover must read as a light surface. The colour mode tokens it reads are re-pointed on the
     * container to the design system's light ones, and the ink is set from them.
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
}
