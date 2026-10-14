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
 * Theme Dimensions - Tests for the course deletion hook callback
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\course;

use theme_dimensions\local\hotsite\links;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the course deletion hook callback.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(before_course_deleted::class)]
final class before_course_deleted_test extends \advanced_testcase {
    /**
     * Leave no router built under this test's setting behind.
     */
    protected function tearDown(): void {
        \core\di::reset_container();
        parent::tearDown();
    }

    /**
     * Two courses with a short address each.
     *
     * @return array The two course records.
     */
    private function two_courses_with_addresses(): array {
        global $CFG;
        $CFG->routerconfigured = true;
        \core\di::reset_container();
        $this->setAdminUser();
        $kept = $this->getDataGenerator()->create_course();
        $gone = $this->getDataGenerator()->create_course();
        links::share((int) $kept->id);
        links::share((int) $gone->id);
        $this->assertNotNull(links::existing_code((int) $gone->id));
        return [$kept, $gone];
    }

    /**
     * Deleting a course through core forgets its short address and no other course's.
     */
    public function test_deleting_a_course_forgets_its_short_address_and_no_other(): void {
        $this->resetAfterTest();
        [$kept, $gone] = $this->two_courses_with_addresses();

        delete_course($gone->id, false);

        $this->assertNull(links::existing_code((int) $gone->id));
        $this->assertNotNull(links::existing_code((int) $kept->id));
    }

    /**
     * The callback itself does the forgetting, scoped to the hook's course.
     */
    public function test_the_callback_forgets_the_hooks_course(): void {
        $this->resetAfterTest();
        [$kept, $gone] = $this->two_courses_with_addresses();

        before_course_deleted::callback(new \core_course\hook\before_course_deleted($gone));

        $this->assertNull(links::existing_code((int) $gone->id));
        $this->assertNotNull(links::existing_code((int) $kept->id));
    }
}
