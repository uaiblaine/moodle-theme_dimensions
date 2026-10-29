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
 * Theme Dimensions - A lock factory that never grants a lock, for the look cohort tests
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\tests;

/**
 * Answers every lock request as if another process held the lock until the timeout ran out.
 *
 * Set as $CFG->lock_factory. A lock held by the same process does not stand in: PostgreSQL's advisory
 * locks are re-entrant within one session, so a second request from the test would be granted.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class refusing_lock_factory implements \core\lock\lock_factory {
    /**
     * Build the factory.
     *
     * @param string $type The lock type.
     */
    public function __construct($type) {
    }

    /**
     * Whether a timeout is honoured.
     *
     * @return bool
     */
    public function supports_timeout() {
        return true;
    }

    /**
     * Whether a lock is released when the process ends.
     *
     * @return bool
     */
    public function supports_auto_release() {
        return true;
    }

    /**
     * Whether the factory can be used.
     *
     * @return bool
     */
    public function is_available() {
        return true;
    }

    /**
     * Refuse the lock.
     *
     * @param string $resource The resource.
     * @param int $timeout The timeout.
     * @param int $maxlifetime The longest a lock may be held.
     * @return false
     */
    public function get_lock($resource, $timeout, $maxlifetime = 86400) {
        return false;
    }

    /**
     * Release a lock, of which this factory grants none.
     *
     * @param \core\lock\lock $lock The lock.
     * @return bool
     */
    public function release_lock(\core\lock\lock $lock) {
        return true;
    }
}
