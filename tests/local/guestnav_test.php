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
 * Theme Dimensions - Tests for the visitor's navbar decision
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the visitor's navbar decision.
 *
 * Every clause is tested by a case that fails it beside a control that passes it in the same test: a
 * "false" asserted alone stays green after the whole predicate is replaced by "return false".
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(guestnav::class)]
final class guestnav_test extends \advanced_testcase {
    /**
     * Put the user agent back: core_useragent is a singleton.
     *
     * @return void
     */
    protected function tearDown(): void {
        \core_useragent::instance(true);
        parent::tearDown();
    }

    /**
     * A page of the given layout, as the renderer would ask about it.
     *
     * @param string $layout The page layout.
     * @return \moodle_page
     */
    private function page(string $layout = 'standard'): \moodle_page {
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->set_url('/course/index.php');
        $page->set_pagelayout($layout);

        return $page;
    }

    /**
     * A logged-out visitor and the guest account get the actions; a real account does not.
     *
     * @return void
     */
    public function test_it_applies_to_the_visitor_and_the_guest_and_not_to_an_account(): void {
        $this->resetAfterTest();
        $page = $this->page();

        $this->setUser(null);
        $this->assertFalse(isloggedin(), 'Precondition: nobody is logged in.');
        $this->assertTrue(guestnav::applies($page));

        $this->setGuestUser();
        $this->assertTrue(guestnav::applies($page));

        $this->setUser(self::getDataGenerator()->create_user());
        $this->assertFalse(guestnav::applies($page));
    }

    /**
     * An administrator logged in as somebody keeps the user menu: the session holds a real account.
     *
     * The user menu is also where core puts the way back from "log in as", so emptying it here would
     * strand the administrator; the guard reads the live session, never the page's shape alone.
     *
     * @return void
     */
    public function test_a_login_as_session_keeps_the_user_menu(): void {
        $this->resetAfterTest();
        $page = $this->page();
        $student = self::getDataGenerator()->create_user();

        $this->setAdminUser();
        \core\session\manager::loginas((int) $student->id, \context_system::instance());
        $this->assertTrue(\core\session\manager::is_loggedinas(), 'Precondition: the session is a login-as one.');

        $this->assertFalse(guestnav::applies($page));
    }

    /**
     * Only an explicit '0' switches it off; a site that never saved the setting has it on.
     *
     * @return void
     */
    public function test_only_an_explicit_zero_switches_it_off(): void {
        $this->resetAfterTest();
        $page = $this->page();
        $this->setUser(null);

        unset_config(guestnav::SETTING, guestnav::COMPONENT);
        $this->assertFalse(get_config(guestnav::COMPONENT, guestnav::SETTING), 'Precondition: never written.');
        $this->assertTrue(guestnav::enabled());
        $this->assertTrue(guestnav::applies($page));

        set_config(guestnav::SETTING, '0', guestnav::COMPONENT);
        $this->assertFalse(guestnav::enabled());
        $this->assertFalse(guestnav::applies($page));

        set_config(guestnav::SETTING, '1', guestnav::COMPONENT);
        $this->assertTrue(guestnav::applies($page));
    }

    /**
     * The login and maintenance layouts draw no navbar this feature could change.
     *
     * @return void
     */
    public function test_the_layouts_without_the_navbar_are_left_alone(): void {
        $this->resetAfterTest();
        $this->setUser(null);

        $this->assertTrue(guestnav::applies($this->page('standard')), 'Control: an ordinary layout.');
        $this->assertTrue(guestnav::applies($this->page('frontpage')), 'Control: the front page.');
        $this->assertFalse(guestnav::applies($this->page('login')));
        $this->assertFalse(guestnav::applies($this->page('maintenance')));
        $this->assertFalse(guestnav::applies($this->page('embedded')));
        $this->assertFalse(guestnav::applies($this->page('secure')));
    }

    /**
     * The Moodle app logs in on its own and gets core's page.
     *
     * @return void
     */
    public function test_the_moodle_app_is_left_alone(): void {
        $this->resetAfterTest();
        $this->setUser(null);
        $page = $this->page();
        $this->assertTrue(guestnav::applies($page), 'Control: a browser.');

        \core_useragent::instance(true, 'Mozilla/5.0 (Linux; Android 10) MoodleMobile 4.5.0');

        $this->assertTrue(\core_useragent::is_moodle_app(), 'Precondition: the user agent is the app.');
        $this->assertFalse(guestnav::applies($page));
    }

    /**
     * Sign-up follows core's own question: none without a registration plugin, core's page with one.
     *
     * @return void
     */
    public function test_the_signup_url_follows_cores_registration_setting(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->registerauth = '';
        $this->assertNull(guestnav::signup_url());

        $CFG->registerauth = 'email';
        $url = guestnav::signup_url();
        $this->assertInstanceOf(\moodle_url::class, $url);
        $this->assertSame((new \moodle_url('/login/signup.php'))->out(false), $url->out(false));
    }

    /**
     * The template data: plain urls, sign-up only while enabled, and the chip for the guest account alone.
     *
     * @return void
     */
    public function test_the_context_says_guest_only_for_the_guest_account(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->registerauth = 'email';

        $this->setUser(null);
        $visitor = guestnav::context();
        $this->assertFalse($visitor['isguest']);
        $this->assertSame(get_login_url(), $visitor['loginurl']);
        $this->assertTrue($visitor['hassignup']);
        $this->assertSame($CFG->wwwroot . '/login/signup.php', $visitor['signupurl']);

        $this->setGuestUser();
        $this->assertTrue(guestnav::context()['isguest']);

        $CFG->registerauth = '';
        $nosignup = guestnav::context();
        $this->assertFalse($nosignup['hassignup']);
        $this->assertSame('', $nosignup['signupurl']);
    }
}
