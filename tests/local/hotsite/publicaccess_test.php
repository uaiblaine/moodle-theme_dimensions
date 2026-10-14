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
 * Theme Dimensions - Tests for the public-access adapter
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hotsite;

use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the fail-closed adapter over local_unlistedcourses' public predicate.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(publicaccess::class)]
final class publicaccess_test extends \advanced_testcase {
    /**
     * Drop the plugin's per-request memos: PHPUnit resets the sequences, so the ids of this test
     * repeat the previous test's, and an answer memoised there would answer here.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        \local_unlistedcourses\access::reset_caches();
        \local_unlistedcourses\category_access::reset_caches();
        \local_unlistedcourses\discoverability::reset_caches();
        \local_unlistedcourses\category_discoverability::reset_caches();
    }

    /**
     * A public, visible course is public; a listed or unlisted one is not.
     *
     * @return void
     */
    public function test_only_a_course_in_the_public_state_is_public(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $public = $generator->create_course();
        $listed = $generator->create_course();
        $unlisted = $generator->create_course();
        $this->setAdminUser();
        discoverability::set_state((int) $public->id, discoverability::STATE_PUBLIC);
        discoverability::set_state((int) $unlisted->id, discoverability::STATE_UNLISTED);
        \local_unlistedcourses\access::reset_caches();

        $this->assertTrue(publicaccess::is_public((int) $public->id));
        $this->assertFalse(publicaccess::is_public((int) $listed->id));
        $this->assertFalse(publicaccess::is_public((int) $unlisted->id));
    }

    /**
     * The predicate's visibility rules come through: a hidden course or category is not public.
     *
     * @return void
     */
    public function test_a_hidden_course_or_category_is_not_public(): void {
        global $DB;

        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $this->setAdminUser();
        discoverability::set_state((int) $course->id, discoverability::STATE_PUBLIC);
        $this->assertTrue(publicaccess::is_public((int) $course->id), 'Precondition.');

        // The predicate memoises its answer for the request: a visibility written straight to the
        // table between two reads is only seen once the memo is dropped, as a new request would.
        $DB->set_field('course', 'visible', 0, ['id' => $course->id]);
        discoverability::reset_caches();
        $this->assertFalse(publicaccess::is_public((int) $course->id));
        $DB->set_field('course', 'visible', 1, ['id' => $course->id]);
        $DB->set_field('course_categories', 'visible', 0, ['id' => $category->id]);
        discoverability::reset_caches();
        $this->assertFalse(publicaccess::is_public((int) $course->id));
    }

    /**
     * The site course and ids that cannot be a course are never public.
     *
     * @return void
     */
    public function test_the_site_course_and_bad_ids_are_never_public(): void {
        $this->resetAfterTest();
        $this->assertFalse(publicaccess::is_public(SITEID));
        $this->assertFalse(publicaccess::is_public(0));
        $this->assertFalse(publicaccess::is_public(-5));
        $this->assertFalse(publicaccess::is_public(987654));
    }

    /**
     * Without the predicate class the answer is "not public": the adapter fails closed.
     *
     * The control is the same course through the real predicate, which says
     * yes - so the refusal is the missing class, not the fixture.
     *
     * @return void
     */
    public function test_a_missing_predicate_fails_closed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        discoverability::set_state((int) $course->id, discoverability::STATE_PUBLIC);
        $this->assertTrue(publicaccess::is_public((int) $course->id), 'Control: the real predicate says public.');

        $this->assertFalse(
            publicaccess::is_public((int) $course->id, 'theme_dimensions\\local\\hotsite\\no_such_predicate'),
            'A missing predicate must read as not public.'
        );
    }
}
