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
 * Theme Dimensions - Adhoc task emptying the cohort of a look no longer offered
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\task;

use theme_dimensions\local\flavourchoice;
use theme_dimensions\local\lookcohorts;

/**
 * Empties a look cohort too large to empty inside the administrator's request.
 *
 * Queued by lookcohorts::empty_cohort(). The cohort is kept: a smart menu restricted by it then shows
 * to nobody, where a deleted cohort would leave the menu's rule empty and show it to everyone.
 *
 * It never throws: core would retry a failed adhoc task, and the daily reconcile task empties the
 * cohort again if this run stopped half way.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class empty_look_cohort extends \core\task\adhoc_task {
    /**
     * The name shown in the task administration screens.
     *
     * @return string The localised task name.
     */
    public function get_name(): string {
        return get_string('task_empty_look_cohort', lookcohorts::COMPONENT);
    }

    /**
     * Remove every member of the cohort named in the custom data, if it is still a look cohort and its look is still off the list.
     *
     * @return void
     */
    public function execute(): void {
        $raw = (string) $this->get_custom_data_as_string();
        $data = $raw === '' ? [] : (array) json_decode($raw);
        $cohortid = (int) ($data['cohortid'] ?? 0);
        $ours = array_filter(lookcohorts::cohorts(), fn($cohort) => (int) $cohort->id === $cohortid);
        if (!$ours) {
            mtrace('Look cohorts: cohort ' . $cohortid . ' is not a look cohort - nothing removed.');
            return;
        }
        // The task was queued when the look left the list; the list is read again now, because an
        // administrator who offered the look since would otherwise see everyone stripped from its menus.
        if (array_key_exists((int) array_key_first($ours), flavourchoice::offered())) {
            mtrace('Look cohorts: cohort ' . $cohortid . ' is offered again - nothing removed.');
            return;
        }

        try {
            lookcohorts::empty_members($cohortid);
            mtrace('Look cohorts: emptied cohort ' . $cohortid . '.');
        } catch (\Throwable $e) {
            // Never throw: core would retry the task, and the daily reconcile finishes the work.
            mtrace('Look cohorts: emptying cohort ' . $cohortid . ' failed - ' . $e->getMessage());
        }
    }
}
