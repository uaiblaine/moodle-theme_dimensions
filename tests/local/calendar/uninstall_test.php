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
 * Theme Dimensions - Tests of what uninstalling removes of the calendar page's state
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use PHPUnit\Framework\Attributes\CoversFunction;

/**
 * The remembered calendar state is the theme's own preference and goes with it; core's preferences stay.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('xmldb_theme_dimensions_uninstall')]
final class uninstall_test extends \advanced_testcase {
    /**
     * Uninstalling deletes the calendar preference of every user and leaves a core preference of the same user.
     *
     * @return void
     */
    public function test_uninstall_deletes_the_calendar_preference_and_keeps_core_ones(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        $users = [self::getDataGenerator()->create_user(), self::getDataGenerator()->create_user()];
        foreach ($users as $user) {
            set_user_preference(state::PREFERENCE, '{"off":["site"]}', $user);
            set_user_preference('htmleditor', 'textarea', $user);
        }
        $this->assertSame(2, $DB->count_records('user_preferences', ['name' => state::PREFERENCE]));

        require_once($CFG->dirroot . '/theme/dimensions/db/uninstall.php');
        xmldb_theme_dimensions_uninstall();

        $this->assertSame(0, $DB->count_records('user_preferences', ['name' => state::PREFERENCE]));
        $this->assertSame(2, $DB->count_records('user_preferences', ['name' => 'htmleditor']), 'A core preference stays.');
    }
}
