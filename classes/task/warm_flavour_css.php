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
 * Theme Dimensions - Scheduled task compiling the flavour stylesheets no visitor has asked for yet
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\task;

use theme_dimensions\local\flavourcss\planner;

/**
 * Builds every missing Boost Union flavour stylesheet, each flavour in a process of its own.
 *
 * Every minute, because nothing announces a theme cache reset: theme_reset_all_caches() has no hook or event,
 * and Boost Union's flavour editor, the theme settings, the cache purge and every upgrade call it. While every
 * sheet is built the run is one query and a file check per flavour.
 *
 * A run that takes longer than a minute is harmless: core runs one instance of a scheduled task at a time, and
 * the next run finds what this one built.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class warm_flavour_css extends \core\task\scheduled_task {
    /** @var planner|null The planner a test hands in; null for the real one. */
    private ?planner $planner;

    /**
     * Core builds the task with no argument; tests hand in a planner.
     *
     * @param planner|null $planner The planner to run.
     */
    public function __construct(?planner $planner = null) {
        $this->planner = $planner;
    }

    /**
     * The task's name on the scheduled tasks page.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_warm_flavour_css', 'theme_dimensions');
    }

    /**
     * Build what is missing; the planner logs each sheet through mtrace().
     *
     * @return void
     */
    public function execute(): void {
        ($this->planner ?? new planner())->run();
    }
}
