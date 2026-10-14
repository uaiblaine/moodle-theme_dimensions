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
 * Writes the previous focus mode default into the courses which never stored an answer.
 *
 * Queued by focusmodefields::sync_default() on a site with more courses than its inline
 * limit, so that changing the default for new courses does not carry thousands of writes
 * inside the administrator's own request. The task finishes the sequence: it materialises
 * the previous answer first and moves the default afterwards, in that order, because
 * checkbydefault is also what every course without a row reads.
 *
 * It never throws. A throwing adhoc task is retried for ever, and there is nothing here a
 * retry could fix that mtrace has not already said.
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
     * Write the previous default into the row-less courses, then move the default.
     *
     * @return void
     */
    public function execute(): void {
        /* Read the raw string rather than get_custom_data(): a task built without custom
           data would hand json_decode() a null, which raises a deprecation notice. */
        $raw = (string) $this->get_custom_data_as_string();
        $data = $raw === '' ? [] : (array) json_decode($raw);
        if (!array_key_exists('old', $data)) {
            mtrace('Focus mode: no previous default in the custom data - nothing written.');
            return;
        }

        try {
            $written = focusmodefields::materialise_default((int) $data['old']);
            mtrace('Focus mode: wrote the previous default into ' . $written . ' course(s).');
            if (array_key_exists('new', $data)) {
                focusmodefields::set_default((bool) $data['new']);
                mtrace('Focus mode: the default for new courses is now ' . ($data['new'] ? '1' : '0') . '.');
            }
        } catch (\Throwable $e) {
            // Never throw: an adhoc task which throws is retried for ever.
            mtrace('Focus mode: writing the previous default failed - ' . $e->getMessage());
            return;
        }
    }
}
