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
 * Theme Dimensions - Tests for the landing page's guest entry
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the landing page's guest entry: who is offered it, and the login it performs.
 *
 * Each condition of available() is broken alone on a course where the other conditions hold, and the
 * course where they all hold is asserted first in the same test, so a refusal is never the fixture's.
 * Tests that expect the entry set autologinguests: available() withdraws it from a site that admits the
 * guest account neither way, and guestloginbutton is off by default.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(guestaccess::class)]
final class guestaccess_test extends \advanced_testcase {
    /**
     * A course with its default guest access instance enabled and without a password.
     *
     * @return array The course and its guest access instance, in that order.
     */
    private function open_course(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'guest'], '*', MUST_EXIST);
        $DB->update_record('enrol', (object) ['id' => $instance->id, 'status' => ENROL_INSTANCE_ENABLED, 'password' => '']);
        return [$course, $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST)];
    }

    /**
     * An enabled, passwordless guest access instance offers the entry; a disabled one does not.
     */
    public function test_an_enabled_passwordless_instance_offers_the_entry(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        [$course, $instance] = $this->open_course();

        $this->assertTrue(guestaccess::available($course));

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $instance->id]);
        $this->assertFalse(guestaccess::available($course));
    }

    /**
     * A password on the instance withdraws the entry: the course page would ask for it.
     */
    public function test_a_password_withdraws_the_entry(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        [$course, $instance] = $this->open_course();
        $this->assertTrue(guestaccess::available($course));

        $DB->set_field('enrol', 'password', 'secret', ['id' => $instance->id]);
        $this->assertFalse(guestaccess::available($course));
    }

    /**
     * Guest access switched off for the site withdraws the entry, whatever the course's instance says.
     */
    public function test_guest_access_disabled_for_the_site_withdraws_the_entry(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        [$course] = $this->open_course();
        $this->assertTrue(guestaccess::available($course));

        $enabled = array_diff(explode(',', $CFG->enrol_plugins_enabled), ['guest']);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
        $this->assertFalse(guestaccess::available($course));
    }

    /**
     * A site that closed both ways into the guest account withdraws the entry; either one keeps it.
     */
    public function test_a_site_with_guest_login_closed_withdraws_the_entry(): void {
        $this->resetAfterTest();
        [$course] = $this->open_course();

        set_config('guestloginbutton', 0);
        set_config('autologinguests', 0);
        $this->assertFalse(guestaccess::available($course));

        set_config('autologinguests', 1);
        $this->assertTrue(guestaccess::available($course), 'Auto-login of guests alone keeps the entry.');

        set_config('autologinguests', 0);
        set_config('guestloginbutton', 1);
        $this->assertTrue(guestaccess::available($course), 'The login page guest button alone keeps the entry.');
    }

    /**
     * Another course's instance does not count, and the site course is never offered the entry.
     */
    public function test_only_the_courses_own_instance_counts(): void {
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        [$open] = $this->open_course();
        $closed = $this->getDataGenerator()->create_course();

        $this->assertTrue(guestaccess::available($open));
        $this->assertFalse(guestaccess::available($closed), 'Its default instance is disabled.');
        $this->assertFalse(guestaccess::available(get_site()));
        $this->assertFalse(guestaccess::available(new \stdClass()), 'A course with no id.');
    }

    /**
     * The entry leads to the course page.
     */
    public function test_the_target_is_the_course_page(): void {
        $this->assertSame(
            (new \core\url('/course/view.php', ['id' => 42]))->out(false),
            guestaccess::target(42)->out(false)
        );
    }

    /**
     * An anonymous visitor is logged in as the guest account, keeping the language they browsed in.
     */
    public function test_an_anonymous_visitor_is_logged_in_as_the_guest(): void {
        global $SESSION;
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $this->setUser(null);
        $SESSION->lang = 'en';
        $this->assertFalse(isloggedin());

        // Hide session header errors, as core's own complete_user_login() tests do: PHPUnit has no active session to regenerate.
        $this->assertTrue(@guestaccess::log_in());

        $this->assertTrue(isloggedin());
        $this->assertTrue(isguestuser());
        $this->assertSame('en', $SESSION->lang);
    }

    /**
     * A session somebody is logged into is never replaced: not a real account's, not the guest's.
     */
    public function test_a_logged_in_session_is_never_replaced(): void {
        global $USER;
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertFalse(guestaccess::log_in());
        $this->assertSame((int) $user->id, (int) $USER->id);

        $this->setGuestUser();
        $guestid = (int) $USER->id;
        $this->assertFalse(guestaccess::log_in());
        $this->assertSame($guestid, (int) $USER->id);
    }
}
