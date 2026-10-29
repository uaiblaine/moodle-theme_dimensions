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
 * Theme Dimensions - Scheduled task putting the look cohorts back in step with the choices
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\task;

use theme_dimensions\local\lookcohorts;

/**
 * Provisions the look cohorts and puts their membership back in step with the people's choices.
 *
 * Core's cohort web services ignore the component stamp, so a membership can be changed, or a look
 * cohort deleted, behind the theme's back; this run undoes it from the preferences, which are the record.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile_look_cohorts extends \core\task\scheduled_task {
    /**
     * The name shown in the task administration screens.
     *
     * @return string The localised task name.
     */
    public function get_name(): string {
        return get_string('task_reconcile_look_cohorts', lookcohorts::COMPONENT);
    }

    /**
     * Provision, then reconcile every listed look's cohort.
     *
     * @return void
     */
    public function execute(): void {
        lookcohorts::reconcile();
    }
}
