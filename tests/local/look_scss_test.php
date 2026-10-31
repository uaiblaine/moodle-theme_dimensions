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
 * Theme Dimensions - Tests for the look menus' stylesheet
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards what nothing else in the pipeline reads about scss/look.scss: the choice page's radios stack, one per line,
 * on that page alone.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class look_scss_test extends \basic_testcase {
    /** @var string The choice page's radio group, scoped to that page's body id. */
    private const GROUP = '#page-theme-dimensions-flavour #fgroup_id_flavourgroup';

    /**
     * The stylesheet, comments removed.
     *
     * @return string
     */
    private function stylesheet(): string {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/look.scss');

        return (string) preg_replace('~/\*.*?\*/~s', '', $source);
    }

    /**
     * The group's row becomes a column, and each radio keeps to the start against core's centring utility.
     *
     * @return void
     */
    public function test_the_choice_pages_radios_stack_at_the_start(): void {
        $scss = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '~' . preg_quote(self::GROUP, '~') . ' \.d-flex \{\s*flex-direction: column;\s*\}~',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '~' . preg_quote(self::GROUP, '~') . ' \.form-check-inline \{\s*align-self: flex-start;\s*\}~',
            $scss
        );
    }

    /**
     * Nothing else in the file names the form group: its id is core's spelling for any form's group of that name.
     *
     * @return void
     */
    public function test_the_group_is_reached_on_the_choice_page_only(): void {
        $scss = $this->stylesheet();

        $this->assertSame(2, substr_count($scss, '#fgroup_id_flavourgroup'), 'Control: the two rules name it.');
        $this->assertSame(2, substr_count($scss, self::GROUP));
    }
}
