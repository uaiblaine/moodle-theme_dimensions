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
 * Guards the one contrast rule of scss/post.scss that no linter reads.
 *
 * The subtle grey token reads 2.96:1 on a white card, under the 4.5:1 that text needs at any
 * size, so it may colour a rule or an icon and never a label.
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
            preg_match_all('/(?<![-\w])color:\s*var\(--text-muted\)/', $scss),
            'Control: the muted token colours text somewhere.'
        );
        $this->assertSame(
            0,
            preg_match_all('/(?<![-\w])color:\s*var\(--text-subtle\)/', $scss, $found),
            'The subtle grey colours text in: ' . implode(', ', $found[0] ?? [])
        );
    }
}
