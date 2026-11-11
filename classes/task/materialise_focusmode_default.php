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
 * Theme Dimensions - Adhoc task writing the previous focus mode default into courses
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\task;

use theme_dimensions\local\focusmodefields;

/**
 * Brings the focus mode default for new courses in line with the setting.
 *
 * Queued by focusmodefields::sync_default() on a site with more courses than its inline
 * limit, or when it could not take the provisioning lock, so that changing the default
 * does not carry thousands of writes inside the administrator's own request. It carries no
 * data and decides from the setting and the field as they are when it runs: the setting may
 * have been flipped back since it was queued. It materialises the field's current answer
 * first and moves the default afterwards, in that order, because checkbydefault is also
 * what every course without a row reads.
 *
 * A refused lock throws, so that core retries the task with a back-off.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class materialise_focusmode_default extends \core\task\adhoc_task {
    /**
     * The name shown in the task administration screens.
     *
     * @return string The localised task name.
     */
    public function get_name(): string {
        return get_string('task_materialise_focusmode_default', focusmodefields::COMPONENT);
    }

    /**
     * Reconcile the field's default with the setting.
     *
     * @return void
     * @throws \moodle_exception When the provisioning lock is refused, so that the task is retried.
     */
    public function execute(): void {
        $written = focusmodefields::reconcile_default();
        if ($written === null) {
            throw new \moodle_exception('locktimeout');
        }
        mtrace('Focus mode: the default for new courses follows the setting; wrote ' . $written . ' course(s).');
    }
}
