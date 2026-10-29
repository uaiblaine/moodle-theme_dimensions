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
 * Theme Dimensions - Tests for the scheduled task compiling the flavour stylesheets
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\task;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\flavourcss\builder;
use theme_dimensions\local\flavourcss\planner;

/**
 * The task runs every minute and hands the planner its work; the planner's own tests hold what it builds.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(warm_flavour_css::class)]
final class warm_flavour_css_test extends \advanced_testcase {
    /**
     * db/tasks.php schedules the task every minute: a cache reset has no event, so a run is the soonest a
     * missing sheet is seen.
     *
     * Read from the file, not from the installed row, which only a PHPUnit initialisation writes.
     *
     * @return void
     */
    public function test_it_is_scheduled_every_minute(): void {
        $tasks = \core\task\manager::load_default_scheduled_tasks_for_component('theme_dimensions');
        $tasks = array_values(array_filter($tasks, fn($task): bool => $task instanceof warm_flavour_css));

        $this->assertCount(1, $tasks);
        $this->assertSame(['*', '*', '*', '*', '*'], [
            $tasks[0]->get_minute(),
            $tasks[0]->get_hour(),
            $tasks[0]->get_day(),
            $tasks[0]->get_month(),
            $tasks[0]->get_day_of_week(),
        ]);
        $this->assertNotEmpty($tasks[0]->get_name());
    }

    /**
     * A run builds a missing sheet and, once it exists, builds nothing.
     *
     * @return void
     */
    public function test_a_run_builds_what_is_missing_and_nothing_else(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        require_once("{$CFG->libdir}/filelib.php");
        set_config('theme', 'dimensions');
        set_config('themerev', 3000);
        set_config('themerev', 11, 'theme_dimensions');
        fulldelete("{$CFG->localcachedir}/theme");
        $id = (int) $DB->insert_record('theme_boost_union_flavours', (object) [
            'title' => 'Probe',
            'sort' => 1,
            'applytocohorts' => 0,
            'applytocategories' => 1,
            'applytocategories_ids' => '["1"]',
        ]);
        $calls = [];
        $runner = function (int $flavourid, string $direction) use (&$calls): int {
            $calls[] = [$flavourid, $direction];
            return builder::BUILT;
        };
        $quiet = function (string $line): void {
            // The test reads the calls, not the log.
        };
        $task = new warm_flavour_css(new planner($runner, $quiet));

        $task->execute();
        $this->assertSame([[$id, 'ltr']], $calls);

        $path = builder::sheet_path(3000, 11, $id, 'ltr');
        make_writable_directory(dirname($path));
        file_put_contents($path, 'x');
        $task->execute();
        $this->assertSame([[$id, 'ltr']], $calls);

        fulldelete("{$CFG->localcachedir}/theme");
    }
}
