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
 * Theme Dimensions - Tests of the service that remembers the calendar page's state
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\calendar\state;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/calendar/fixture.php');

/**
 * What the service stores, what it drops, whom it refuses, and that it writes the one preference and nothing else.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(remember_calendar_state::class)]
final class remember_calendar_state_test extends \advanced_testcase {
    use \theme_dimensions\local\calendar\fixture;

    /** @var string The registered name of the function under test. */
    private const FUNCTION = 'theme_dimensions_remember_calendar_state';

    /**
     * The course fields resolve, as on a site the theme was installed on.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
    }

    /**
     * Call the function the way the client does.
     *
     * @param array $args The arguments.
     * @return array The framework's response.
     */
    private function call(array $args): array {
        $_POST['sesskey'] = sesskey();
        self::forget_calendar();
        return external_api::call_external_function(self::FUNCTION, $args, false);
    }

    /**
     * The state is stored; every value outside its vocabulary is dropped beside a value kept.
     *
     * @return void
     */
    public function test_the_state_is_stored_through_its_vocabulary(): void {
        $this->setUser(self::getDataGenerator()->create_user());
        $response = $this->call([
            'off' => ['group', 'nosuchkey'],
            'model' => [landingfields::MODEL_EVENT, 99],
            'modality' => [],
            'city' => 'Recife',
            'actiononly' => true,
            'colour' => 'rainbow',
        ]);
        $this->assertFalse($response['error'], json_encode($response));
        $this->assertSame(['group'], $response['data']['off']);
        $this->assertSame([landingfields::MODEL_EVENT], $response['data']['model']);
        $this->assertSame(state::COLOUR_KEY, $response['data']['colour']);
        $this->assertTrue($response['data']['remembered']);
        $stored = state::saved();
        $this->assertSame(['group'], $stored->off);
        $this->assertSame('Recife', $stored->city);
        $this->assertTrue($stored->actiononly);
    }

    /**
     * A category the viewer may narrow to is kept; a stale one (gone or hidden) is stored as none, never refused.
     *
     * @return void
     */
    public function test_a_stale_category_is_stored_as_none(): void {
        $generator = self::getDataGenerator();
        $open = $generator->create_category();
        $hidden = $generator->create_category(['visible' => 0]);
        $this->setUser($generator->create_user());

        $kept = $this->call(['categoryid' => (int) $open->id, 'recursive' => false]);
        $this->assertFalse($kept['error'], json_encode($kept));
        $this->assertSame((int) $open->id, state::saved()->categoryid);
        $this->assertFalse(state::saved()->recursive);

        foreach ([(int) $hidden->id, 999999] as $categoryid) {
            $response = $this->call(['categoryid' => $categoryid, 'off' => ['site']]);
            $this->assertFalse($response['error'], json_encode($response));
            $this->assertSame(0, $response['data']['categoryid']);
            $this->assertSame(0, state::saved()->categoryid);
            $this->assertSame(['site'], state::saved()->off, 'Only the category goes.');
        }
    }

    /**
     * The default state is no row, and the write touches the one preference and nothing else.
     *
     * @return void
     */
    public function test_it_writes_the_one_preference_and_nothing_else(): void {
        global $DB;
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        set_user_preference('theme_dimensions_categoryview', 'list');
        $before = $DB->get_records_menu('user_preferences', ['userid' => $user->id], 'name', 'name, value');

        $this->call(['off' => ['site']]);
        $after = $DB->get_records_menu('user_preferences', ['userid' => $user->id], 'name', 'name, value');
        $this->assertSame([state::PREFERENCE], array_keys(array_diff_key($after, $before)));
        $this->assertSame($before, array_intersect_key($after, $before));

        $response = $this->call([]);
        $this->assertFalse($response['data']['remembered']);
        $this->assertFalse($DB->record_exists('user_preferences', ['userid' => $user->id, 'name' => state::PREFERENCE]));
    }

    /**
     * The guest account is refused with the range's one refusal, and stores nothing.
     *
     * @return void
     */
    public function test_the_guest_is_refused(): void {
        $this->setGuestUser();
        $response = $this->call(['off' => ['site']]);
        $this->assertTrue($response['error']);
        $this->assertSame('calendarrangeunavailable', $response['exception']->errorcode);
        $this->assertNull(get_user_preferences(state::PREFERENCE, null, (int) guest_user()->id));
    }

    /**
     * Nobody logged in is turned away by the function's own login gate, and nothing is stored.
     *
     * The framework's `loginrequired` flag refuses first through call_external_function(), so the function is
     * called directly here to reach the gate it carries itself; under PHPUnit core's redirect to the login page
     * surfaces as the redirect-detected exception.
     *
     * @return void
     */
    public function test_a_request_without_a_session_is_refused(): void {
        global $DB;
        $this->setUser(0);
        try {
            remember_calendar_state::execute(['site']);
            $this->fail('A request without a session was served.');
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode, 'Core sends the visitor to the login page.');
        }
        $this->assertFalse($DB->record_exists('user_preferences', ['name' => state::PREFERENCE]));
    }

    /**
     * The service answers inside the system context only: a token restricted to a course is refused, and the
     * restriction is what the context check enforces (the login gate and the guest check do not look at it).
     *
     * @return void
     */
    public function test_a_context_restricted_request_is_refused(): void {
        global $DB;
        $course = self::getDataGenerator()->create_course();
        $this->setUser(self::getDataGenerator()->create_user());

        external_api::set_context_restriction(\core\context\course::instance($course->id));
        try {
            $response = $this->call(['off' => ['site']]);
        } finally {
            external_api::set_context_restriction(\core\context\system::instance());
        }
        $this->assertTrue($response['error']);
        $this->assertSame('restrictedcontextexception', $response['exception']->errorcode);
        $this->assertFalse($DB->record_exists('user_preferences', ['name' => state::PREFERENCE]));

        $allowed = $this->call(['off' => ['site']]);
        $this->assertFalse($allowed['error'], json_encode($allowed));
    }
}
