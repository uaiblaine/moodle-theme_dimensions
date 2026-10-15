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
 * Theme Dimensions - Tests for the profile access rule
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use context_course;
use core_user;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the profile access rule.
 *
 * Every test that asserts a profile is withheld also proves the force that would have shown it
 * was switched on - otherwise the assertion passes by doing nothing and keeps passing after the
 * rule it guards is deleted. For the course contact case that control is
 * has_coursecontact_role(), which is precisely what core would have opened the profile on.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(profileaccess::class)]
final class profileaccess_test extends \advanced_testcase {
    /**
     * Reset the request memo between tests, since one request runs all of them.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        profileaccess::reset_caches();
    }

    /**
     * An authenticated user with no course in common is kept away from a stranger's profile.
     *
     * @return void
     */
    public function test_a_stranger_is_prevented(): void {
        $viewer = $this->getDataGenerator()->create_user();
        $subject = $this->getDataGenerator()->create_user();
        $this->setUser($viewer);

        $this->assertSame(
            core_user::VIEWPROFILE_PREVENT,
            profileaccess::control_view_profile($subject)
        );
    }

    /**
     * The control for the test above: the same pair, once they share a course, is not prevented.
     *
     * Without this the "prevented" assertion would also pass against a rule that prevents
     * everything, which is the failure mode a negative test cannot see on its own.
     *
     * @return void
     */
    public function test_a_fellow_participant_is_not_prevented(): void {
        $course = $this->getDataGenerator()->create_course();
        $viewer = $this->getDataGenerator()->create_user();
        $subject = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($viewer->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($subject->id, $course->id, 'student');
        $this->setUser($viewer);

        $this->assertSame(
            core_user::VIEWPROFILE_DO_NOT_PREVENT,
            profileaccess::control_view_profile($subject)
        );
    }

    /**
     * A course contact of some other course is a stranger like anyone else.
     *
     * This is the whole point of the class, so it carries two controls: that the subject really
     * does hold a course contact role, and that core really would have opened the profile on the
     * strength of it.
     *
     * @return void
     */
    public function test_a_course_contact_of_another_course_is_prevented(): void {
        global $CFG;

        $editingteacher = $this->role_id('editingteacher');
        set_config('coursecontact', $editingteacher);
        $CFG->coursecontact = $editingteacher;

        $theircourse = $this->getDataGenerator()->create_course();
        $subject = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($subject->id, $theircourse->id, 'editingteacher');

        $viewer = $this->getDataGenerator()->create_user();
        $this->setUser($viewer);

        // Control one: the subject is a course contact, so the core rule this class narrows is
        // armed. If this fails, the test below proves nothing.
        $this->assertTrue(has_coursecontact_role($subject->id));

        $this->assertSame(
            core_user::VIEWPROFILE_PREVENT,
            profileaccess::control_view_profile($subject)
        );

        // Control two, and the one that matters: end to end through core, which is also the only
        // assertion that fails if the callback in lib.php is renamed, moved or never registered.
        $this->assertFalse(\core\user::can_view_profile($subject));
    }

    /**
     * The same course contact, reached through the course they teach, keeps a reachable profile.
     *
     * @return void
     */
    public function test_a_course_contact_is_allowed_inside_their_own_course(): void {
        global $CFG;

        $editingteacher = $this->role_id('editingteacher');
        set_config('coursecontact', $editingteacher);
        $CFG->coursecontact = $editingteacher;

        $course = $this->getDataGenerator()->create_course();
        $subject = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($subject->id, $course->id, 'editingteacher');

        $viewer = $this->getDataGenerator()->create_user();
        $this->setUser($viewer);

        $this->assertSame(
            core_user::VIEWPROFILE_DO_NOT_PREVENT,
            profileaccess::control_view_profile($subject, $course)
        );
    }

    /**
     * A course contact reached through a course they have nothing to do with is still prevented.
     *
     * The in-course URL is not a skeleton key: passing any course id must not open a profile.
     *
     * @return void
     */
    public function test_a_course_contact_is_prevented_through_an_unrelated_course(): void {
        global $CFG;

        $editingteacher = $this->role_id('editingteacher');
        set_config('coursecontact', $editingteacher);
        $CFG->coursecontact = $editingteacher;

        $theircourse = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $subject = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($subject->id, $theircourse->id, 'editingteacher');

        $viewer = $this->getDataGenerator()->create_user();
        $this->setUser($viewer);

        // Control: they are a contact somewhere, just not here.
        $this->assertSame(
            core_user::VIEWPROFILE_DO_NOT_PREVENT,
            profileaccess::control_view_profile($subject, $theircourse)
        );
        profileaccess::reset_caches();

        $this->assertSame(
            core_user::VIEWPROFILE_PREVENT,
            profileaccess::control_view_profile($subject, $othercourse)
        );
    }

    /**
     * A shared course whose participant list is withheld withholds the profiles with it.
     *
     * This is the one place the rule deliberately differs from core, which asks for
     * moodle/user:viewdetails here. Removing the capability from students in that course must
     * close the profile.
     *
     * @return void
     */
    public function test_a_shared_course_without_viewparticipants_is_prevented(): void {
        $course = $this->getDataGenerator()->create_course();
        $viewer = $this->getDataGenerator()->create_user();
        $subject = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($viewer->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($subject->id, $course->id, 'student');
        $this->setUser($viewer);

        // Control: with the capability in place the profile is open, so the assertion below is
        // about the capability and not about the enrolment.
        $this->assertSame(
            core_user::VIEWPROFILE_DO_NOT_PREVENT,
            profileaccess::control_view_profile($subject)
        );

        $coursecontext = context_course::instance($course->id);
        assign_capability(
            'moodle/course:viewparticipants',
            CAP_PREVENT,
            $this->role_id('student'),
            $coursecontext->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        profileaccess::reset_caches();

        $this->assertSame(
            core_user::VIEWPROFILE_PREVENT,
            profileaccess::control_view_profile($subject)
        );
    }

    /**
     * A manager keeps seeing everybody, and so does the site administrator.
     *
     * A privacy rule without a staff escape hides content from the people who administer the site
     * while every other test stays green, so the escape has a test of its own.
     *
     * @return void
     */
    public function test_staff_are_never_prevented(): void {
        $subject = $this->getDataGenerator()->create_user();

        $manager = $this->getDataGenerator()->create_user();
        role_assign($this->role_id('manager'), $manager->id, \context_system::instance()->id);
        $this->setUser($manager);
        $this->assertSame(
            core_user::VIEWPROFILE_DO_NOT_PREVENT,
            profileaccess::control_view_profile($subject)
        );

        profileaccess::reset_caches();
        $this->setAdminUser();
        $this->assertSame(
            core_user::VIEWPROFILE_DO_NOT_PREVENT,
            profileaccess::control_view_profile($subject)
        );
    }

    /**
     * Nobody is ever kept away from their own profile.
     *
     * @return void
     */
    public function test_the_own_profile_is_never_prevented(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertSame(
            core_user::VIEWPROFILE_DO_NOT_PREVENT,
            profileaccess::control_view_profile($user)
        );
    }

    /**
     * The logged-out and guest cases are core's to answer, through forceloginforprofiles.
     *
     * Vetoing them here would be a second, quieter answer to a question the site setting already
     * decides, and the two would disagree the day the setting changed.
     *
     * @return void
     */
    public function test_the_guest_and_logged_out_cases_are_left_to_core(): void {
        $subject = $this->getDataGenerator()->create_user();

        $this->setGuestUser();
        $this->assertSame(
            core_user::VIEWPROFILE_DO_NOT_PREVENT,
            profileaccess::control_view_profile($subject)
        );

        profileaccess::reset_caches();
        $this->setUser(null);
        $this->assertSame(
            core_user::VIEWPROFILE_DO_NOT_PREVENT,
            profileaccess::control_view_profile($subject)
        );
    }

    /**
     * The memo answers per subject and per course, not once for the whole request.
     *
     * A memo keyed only by the subject would answer the site profile's question on the in-course
     * page, which is exactly the distinction this class exists to draw.
     *
     * @return void
     */
    public function test_the_memo_distinguishes_the_course(): void {
        global $CFG;

        $editingteacher = $this->role_id('editingteacher');
        set_config('coursecontact', $editingteacher);
        $CFG->coursecontact = $editingteacher;

        $course = $this->getDataGenerator()->create_course();
        $subject = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($subject->id, $course->id, 'editingteacher');

        $viewer = $this->getDataGenerator()->create_user();
        $this->setUser($viewer);

        // Ask the site question first, so that a subject-only memo would answer the next one.
        $this->assertSame(
            core_user::VIEWPROFILE_PREVENT,
            profileaccess::control_view_profile($subject)
        );
        $this->assertSame(
            core_user::VIEWPROFILE_DO_NOT_PREVENT,
            profileaccess::control_view_profile($subject, $course)
        );
    }

    /**
     * The memo answers per viewer too, not once per subject for the whole request.
     *
     * $USER is not constant for a request: \core\session\manager::set_user() switches it in CLI
     * scripts, in adhoc tasks that act as somebody, and behind "log in as". A memo without the
     * viewer in its key hands one person's answer to the next one, and it fails in the dangerous
     * direction - deny, then wrongly deny again for somebody entitled. The test deliberately does
     * not call reset_caches() between the two viewers.
     *
     * @return void
     */
    public function test_the_memo_distinguishes_the_viewer(): void {
        $course = $this->getDataGenerator()->create_course();
        $subject = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($subject->id, $course->id, 'student');

        $stranger = $this->getDataGenerator()->create_user();
        $classmate = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($classmate->id, $course->id, 'student');

        $this->setUser($stranger);
        $this->assertSame(
            core_user::VIEWPROFILE_PREVENT,
            profileaccess::control_view_profile($subject)
        );

        $this->setUser($classmate);
        $this->assertSame(
            core_user::VIEWPROFILE_DO_NOT_PREVENT,
            profileaccess::control_view_profile($subject)
        );
    }

    /**
     * Look up a role id by shortname.
     *
     * @param string $shortname The role shortname.
     * @return int The role id.
     */
    private function role_id(string $shortname): int {
        global $DB;

        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }
}
