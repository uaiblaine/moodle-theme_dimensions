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
 * Theme Dimensions - Tests for the guest entry's branch of the landing page request
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

use theme_dimensions\local\landingfields;
use theme_dimensions\output\landing_guestentry;
use theme_dimensions\output\landing_page;
use theme_dimensions\output\opengraph;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The guest entry inside request::handle(): the interstitial, the form's login and their refusals.
 *
 * The request is called directly, with the router off, one request per test: the route's own
 * passing of guest=1 is landing_test's. Every refusal has the case it refuses beside it in this
 * file - the same course and the same reader with the one condition put back. Every test sets
 * autologinguests: guestaccess::available() withdraws the entry from a site that admits the guest
 * account neither way, and guestloginbutton is off by default.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(request::class)]
#[CoversClass(landing_guestentry::class)]
final class request_guest_test extends \advanced_testcase {
    /**
     * Forget what local_unlistedcourses memoised for the last test, and start with the router off.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        \local_unlistedcourses\discoverability::reset_caches();
        \local_unlistedcourses\access::reset_caches();
        \local_unlistedcourses\category_access::reset_caches();
        $CFG->routerconfigured = false;
        \core\di::reset_container();
    }

    /**
     * Leave no head tags, posted fields or router behind.
     */
    protected function tearDown(): void {
        opengraph::set(null);
        unset($_POST[guestaccess::ENTER_PARAM], $_POST['sesskey']);
        \core\di::reset_container();
        parent::tearDown();
    }

    /**
     * A public course with a landing page model and, unless told otherwise, an open guest access instance.
     *
     * @param bool $guestopen Whether the course's guest access instance is enabled without a password.
     * @param bool $public Whether the course is in local_unlistedcourses' public state.
     * @return \stdClass The course.
     */
    private function make_course(bool $guestopen = true, bool $public = true): \stdClass {
        global $DB;
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Gestão de Riscos & Barragens']);
        \core_course\customfield\course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
        ]);
        if ($public) {
            \local_unlistedcourses\discoverability::set_state(
                (int) $course->id,
                \local_unlistedcourses\discoverability::STATE_PUBLIC,
                (int) get_admin()->id
            );
        }
        if ($guestopen) {
            $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $course->id, 'enrol' => 'guest']);
        }
        return $course;
    }

    /**
     * Post the interstitial's form fields, as its button or its module does.
     *
     * @param string|null $sesskey The session key to post, or null to post none.
     * @return void
     */
    private function post_entry(?string $sesskey): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST[guestaccess::ENTER_PARAM] = '1';
        if ($sesskey !== null) {
            $_POST['sesskey'] = $sesskey;
        }
    }

    /**
     * The AMD calls the page registered so far.
     *
     * @return string The registered calls, joined.
     */
    private function amd_calls(): string {
        global $PAGE;
        $property = new \ReflectionProperty($PAGE->requires, 'amdjscode');
        return implode("\n", $property->getValue($PAGE->requires));
    }

    /**
     * An anonymous visitor asking for the entry gets the interstitial, with the landing page's own head tags.
     */
    public function test_an_anonymous_visitor_gets_the_interstitial_with_the_landings_head_tags(): void {
        global $PAGE;
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $course = $this->make_course();
        $this->setUser(null);

        $answer = request::handle((int) $course->id, true);

        $this->assertNull($answer->redirect);
        $this->assertInstanceOf(landing_guestentry::class, $answer->page);
        $this->assertFalse(isloggedin(), 'Asking for the page logs nobody in.');
        $this->assertSame('embedded', $PAGE->pagelayout);
        $tags = opengraph::current();
        $this->assertNotNull($tags, 'A scraper reads the landing page\'s card here.');
        $this->assertSame(links::page((int) $course->id)->out(false), $tags->get_url(), 'og:url and canonical: the landing page.');
        $this->assertStringContainsString('theme_dimensions/landingguest', $this->amd_calls());

        $html = $PAGE->get_renderer('core')->render($answer->page);
        $this->assertStringContainsString('action="' . s(links::page((int) $course->id)->out(false)) . '"', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('name="' . guestaccess::ENTER_PARAM . '" value="1"', $html);
        $this->assertStringContainsString('name="sesskey" value="' . sesskey() . '"', $html);
        $this->assertStringContainsString('Gestão de Riscos &amp; Barragens', $html, 'Escaped once.');
    }

    /**
     * Where the course offers no guest entry, asking for it is the plain landing page.
     */
    public function test_the_entry_is_ignored_where_the_course_does_not_offer_it(): void {
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $course = $this->make_course(false);
        $this->setUser(null);

        $answer = request::handle((int) $course->id, true);

        $this->assertNull($answer->redirect);
        $this->assertInstanceOf(landing_page::class, $answer->page);
        $this->assertFalse(isloggedin());
    }

    /**
     * A visitor asking for the entry of a course that is not public is refused first, with the login page.
     */
    public function test_a_course_that_is_not_public_still_sends_a_visitor_to_the_login(): void {
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $course = $this->make_course(true, false);
        $this->setUser(null);

        $answer = request::handle((int) $course->id, true);

        $this->assertSame(get_login_url(), $answer->redirect->out(false));
        $this->assertNull(opengraph::current());
        $this->assertFalse(isloggedin());
    }

    /**
     * A reader already logged in goes straight to the course, without the interstitial.
     */
    public function test_a_logged_in_reader_goes_straight_to_the_course(): void {
        global $USER;
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $course = $this->make_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $answer = request::handle((int) $course->id, true);

        $this->assertSame(guestaccess::target((int) $course->id)->out(false), $answer->redirect->out(false));
        $this->assertSame((int) $user->id, (int) $USER->id);
    }

    /**
     * A logged-in reader asking for a hidden course with a landing page model is sent to the ghost page.
     *
     * It is not told "coursehidden", which would tell a hidden course from a missing id. The control is a teacher
     * who may see the course hidden: no redirect to the ghost page, the landing page is answered.
     */
    public function test_a_logged_in_reader_asking_for_a_hidden_course_is_ghosted(): void {
        $this->resetAfterTest();
        $course = $this->make_course(false);
        $course->visible = 0;
        update_course($course);
        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($generator->create_user());
        \local_unlistedcourses\discoverability::reset_caches();
        \local_unlistedcourses\access::reset_caches();

        $answer = request::handle((int) $course->id);

        $this->assertNotNull($answer->redirect);
        $this->assertSame(redirector::ghost_url()->out(false), $answer->redirect->out(false));

        $this->setUser($teacher);
        $seen = request::handle((int) $course->id);
        $this->assertTrue(
            $seen->redirect === null || $seen->redirect->out(false) !== redirector::ghost_url()->out(false),
            'Control: a teacher is not sent to the ghost page.'
        );
    }

    /**
     * The guest account, already logged in, goes straight to the course as well.
     */
    public function test_the_guest_account_goes_straight_to_the_course(): void {
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $course = $this->make_course();
        $this->setGuestUser();

        $answer = request::handle((int) $course->id, true);

        $this->assertSame(guestaccess::target((int) $course->id)->out(false), $answer->redirect->out(false));
    }

    /**
     * The interstitial's form, with the session key, logs an anonymous visitor in as the guest and opens the course.
     */
    public function test_the_entry_form_logs_an_anonymous_visitor_in_as_the_guest(): void {
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $course = $this->make_course();
        $this->setUser(null);
        $this->post_entry(sesskey());

        // Hide session header errors, as core's own complete_user_login() tests do: PHPUnit has no active session to regenerate.
        $answer = @request::handle((int) $course->id);

        $this->assertSame(guestaccess::target((int) $course->id)->out(false), $answer->redirect->out(false));
        $this->assertTrue(isguestuser());
    }

    /**
     * The form without a session key logs nobody in, and the visitor gets the landing page.
     */
    public function test_the_entry_form_without_the_session_key_logs_nobody_in(): void {
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $course = $this->make_course();
        $this->setUser(null);
        $this->post_entry(null);

        $answer = request::handle((int) $course->id);

        $this->assertNull($answer->redirect);
        $this->assertInstanceOf(landing_page::class, $answer->page);
        $this->assertFalse(isloggedin());
    }

    /**
     * The same fields on a GET, session key and all, log nobody in: the entry is only ever a POST.
     */
    public function test_a_get_with_a_valid_session_key_logs_nobody_in(): void {
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $course = $this->make_course();
        $this->setUser(null);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET[guestaccess::ENTER_PARAM] = '1';
        $_GET['sesskey'] = sesskey();

        $answer = request::handle((int) $course->id);

        $this->assertNull($answer->redirect);
        $this->assertInstanceOf(landing_page::class, $answer->page);
        $this->assertFalse(isloggedin());
    }

    /**
     * The form with another session's key logs nobody in, and the visitor gets the landing page.
     */
    public function test_the_entry_form_with_a_foreign_session_key_logs_nobody_in(): void {
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $course = $this->make_course();
        $this->setUser(null);
        $this->post_entry(sesskey() . 'x');

        $answer = request::handle((int) $course->id);

        $this->assertInstanceOf(landing_page::class, $answer->page);
        $this->assertFalse(isloggedin());
    }

    /**
     * The form never replaces a session somebody is logged into: the reader goes to the course as themselves.
     */
    public function test_the_entry_form_never_replaces_a_logged_in_session(): void {
        global $USER;
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $course = $this->make_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->post_entry(sesskey());

        $answer = request::handle((int) $course->id);

        $this->assertSame(guestaccess::target((int) $course->id)->out(false), $answer->redirect->out(false));
        $this->assertSame((int) $user->id, (int) $USER->id);
        $this->assertFalse(isguestuser());
    }

    /**
     * Where the course offers no guest entry, the form logs nobody in, session key and all.
     */
    public function test_the_entry_form_logs_nobody_in_where_the_course_does_not_offer_it(): void {
        $this->resetAfterTest();
        set_config('autologinguests', 1);
        $course = $this->make_course(false);
        $this->setUser(null);
        $this->post_entry(sesskey());

        $answer = request::handle((int) $course->id);

        $this->assertInstanceOf(landing_page::class, $answer->page);
        $this->assertFalse(isloggedin());
    }
}
