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
 * Theme Dimensions - Tests for the service that records a switch of focus mode made in place
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_course\customfield\course_handler;
use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\focusmode;
use theme_dimensions\local\focusmodefields;
use theme_dimensions\local\yesno;

/**
 * Tests of the focus mode service: what it records, for which course, and what it refuses.
 *
 * Called through call_external_function(), the way core/ajax reaches it, so the registration, the
 * parameter validation and the return cleaning are part of every test.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(set_focus_mode::class)]
#[CoversClass(focusmode::class)]
final class set_focus_mode_test extends \advanced_testcase {
    /** @var string The registered name of the function under test. */
    private const FUNCTION = 'theme_dimensions_set_focus_mode';

    /**
     * Start with the master switch on, empty request caches and a session holding no choice.
     *
     * @return void
     */
    protected function setUp(): void {
        global $SESSION;

        parent::setUp();
        $this->resetAfterTest();
        focusmode::reset_caches();
        unset($SESSION->{focusmode::SESSION_KEY}, $SESSION->{focusmode::SESSION_KEY_OUT});
        set_config('focusmode', yesno::YES, focusmode::COMPONENT);
    }

    /**
     * Put the request caches back.
     *
     * @return void
     */
    protected function tearDown(): void {
        focusmode::reset_caches();
        parent::tearDown();
    }

    /**
     * Call the function the way the client does.
     *
     * @param int $cmid The course module.
     * @param bool $active The mode the page switched to.
     * @return array The framework's response.
     */
    private function call(int $cmid, bool $active): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::FUNCTION, ['cmid' => $cmid, 'active' => $active], false);
    }

    /**
     * A course with an activity, whose focus mode switch is in the given state, and a student enrolled in it.
     *
     * @param bool $on Whether the course switched focus mode on.
     * @param array $options Extra options for the course generator.
     * @return array The course record and the activity's cmid.
     */
    private function course(bool $on, array $options = []): array {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course($options);
        focusmodefields::ensure_fields();
        focusmodefields::reset_field_cache();
        course_handler::reset_caches();
        course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . focusmodefields::SHORTNAME => $on ? 1 : 0,
        ]);
        focusmode::reset_caches();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        return [$course, (int) $page->cmid];
    }

    /**
     * Entering records the choice for the activity's course, and leaving replaces it.
     *
     * The control of the leaving half is a course that switched focus mode on, whose next page the
     * exit must open ordinary for the rest of the session.
     *
     * @return void
     */
    public function test_a_switch_is_recorded_for_the_course(): void {
        [$course, $cmid] = $this->course(false);

        $response = $this->call($cmid, true);
        $this->assertFalse($response['error'], $response['exception']->message ?? '');
        $this->assertTrue($response['data']['active']);
        $this->assertTrue(focusmode::in_session((int) $course->id));

        $response = $this->call($cmid, false);
        $this->assertFalse($response['error'], $response['exception']->message ?? '');
        $this->assertFalse($response['data']['active']);
        $this->assertFalse(focusmode::in_session((int) $course->id));

        [$forced, $forcedcmid] = $this->course(true);
        $this->assertTrue(focusmode::chosen((int) $forced->id), 'Control: the course opens focused.');
        $response = $this->call($forcedcmid, false);
        $this->assertFalse($response['error'], $response['exception']->message ?? '');
        $this->assertTrue(focusmode::opted_out((int) $forced->id));
        $this->assertFalse(focusmode::chosen((int) $forced->id));
    }

    /**
     * With the master switch off the service refuses and writes nothing.
     *
     * While it is off nothing of the feature runs.
     *
     * @return void
     */
    public function test_the_master_switch_off_refuses(): void {
        [$course, $cmid] = $this->course(false);
        set_config('focusmode', yesno::NO, focusmode::COMPONENT);

        $response = $this->call($cmid, true);

        $this->assertTrue($response['error']);
        $this->assertSame('focusmode_unavailable', $response['exception']->errorcode);
        $this->assertFalse(focusmode::in_session((int) $course->id));
    }

    /**
     * A reader who may not see the activity is refused before anything is written.
     *
     * The control is the same call by an enrolled student, which the first test makes.
     *
     * @return void
     */
    public function test_a_reader_who_may_not_see_the_activity_is_refused(): void {
        [$course, $cmid] = $this->course(false);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->call($cmid, true);

        $this->assertTrue($response['error']);
        $this->assertFalse(focusmode::in_session((int) $course->id));
    }

    /**
     * A single activity course that switched focus mode on refuses the exit in place; entering is accepted.
     *
     * Such a page has no ordinary skin to go back to, and its exit is a link out of the course.
     *
     * @return void
     */
    public function test_a_forced_single_activity_course_refuses_the_exit_in_place(): void {
        [$course, $cmid] = $this->course(true, ['format' => 'singleactivity', 'activitytype' => 'page']);

        $response = $this->call($cmid, false);
        $this->assertTrue($response['error']);
        $this->assertSame('focusmode_unavailable', $response['exception']->errorcode);
        $this->assertFalse(focusmode::opted_out((int) $course->id));

        $response = $this->call($cmid, true);
        $this->assertFalse($response['error'], 'Control: entering is a no-op it accepts.');
    }

    /**
     * An activity of the site course is refused: the front page has no focus mode.
     *
     * @return void
     */
    public function test_the_site_course_is_refused(): void {
        $this->setAdminUser();
        $page = $this->getDataGenerator()->create_module('page', ['course' => SITEID]);

        $response = $this->call((int) $page->cmid, true);

        $this->assertTrue($response['error']);
        $this->assertSame('focusmode_unavailable', $response['exception']->errorcode);
        $this->assertFalse(focusmode::in_session((int) SITEID));
    }
}
