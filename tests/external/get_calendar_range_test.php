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
 * Theme Dimensions - The calendar range web service
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/calendar/fixture.php');

/**
 * The service answers a logged-in account through core's own call path, and every miss with one refusal.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_calendar_range::class)]
final class get_calendar_range_test extends \advanced_testcase {
    use \theme_dimensions\local\calendar\fixture;

    /** @var string The service. */
    private const NAME = 'theme_dimensions_get_calendar_range';

    /**
     * resetAfterTest() does not restore superglobals: drop the sesskey the calls plant.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        self::forget_calendar();
        parent::tearDown();
    }

    /**
     * Call the service as the client does.
     *
     * @param array $args The arguments.
     * @return array call_external_function()'s answer.
     */
    private static function call(array $args): array {
        self::forget_calendar();
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::NAME, $args, true);
    }

    /**
     * Next month's arguments.
     *
     * @param array $more Further arguments.
     * @return array
     */
    private static function month(array $more = []): array {
        $start = self::month_start();
        return $more + ['from' => $start->format('Y-m-d'), 'to' => $start->modify('last day of this month')->format('Y-m-d')];
    }

    /**
     * A student with a course whose name and event name carry markup, and an event course they are enrolled in.
     *
     * @return array [student, course, event id, event course id]
     */
    private function seed(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $student = $generator->create_user();
        $course = $generator->create_course(['fullname' => 'Fire & <b>rescue</b>']);
        $generator->enrol_user($student->id, $course->id, 'student');
        $eventcourse = self::event_course((int) $course->category, 4, 6);
        $generator->enrol_user($student->id, $eventcourse->id, 'student');
        $event = $generator->create_event([
            'eventtype' => 'course',
            'courseid' => $course->id,
            'name' => 'Drill <i>night</i>',
            'timestart' => self::at(3),
        ]);
        return [$student, $course, (int) $event->id, (int) $eventcourse->id];
    }

    /**
     * The answer comes through core's call path: the events, the courses, the days, and nothing of markup or image data.
     *
     * @return void
     */
    public function test_the_answer(): void {
        [$student, $course, $eventid, $eventcourseid] = $this->seed();
        $this->setUser($student);

        $result = self::call(self::month());

        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $data = $result['data'];
        $this->assertSame(self::month()['from'], $data['from']);
        $this->assertContains($eventid, array_column($data['events'], 'id'));
        $this->assertContains($eventcourseid, array_column($data['events'], 'id'));
        $courses = array_column($data['courses'], null, 'id');
        $this->assertSame('Fire & rescue', $courses[(int) $course->id]['fullname']);
        $this->assertSame(landingfields::MODEL_EVENT, $courses[$eventcourseid]['model']);
        $this->assertSame(1, $courses[$eventcourseid]['participants']);
        $this->assertCount(count(self::month_window()->days), $data['days']);
        $json = json_encode($data);
        $this->assertStringNotContainsString('data:', $json);
        $this->assertStringNotContainsString('<', $json);
        $this->assertStringNotContainsString('>', $json);
    }

    /**
     * Every miss, of the request's shape or of the site, is the same refusal.
     *
     * @return void
     */
    public function test_every_miss_is_the_same_refusal(): void {
        [$student, $course] = $this->seed();
        $generator = self::getDataGenerator();
        $foreign = $generator->create_course();
        $hidden = $generator->create_category(['visible' => 0]);
        $this->setUser($student);
        $start = self::month_start();

        $cases = [
            'forty-three days' => ['from' => $start->format('Y-m-d'), 'to' => $start->modify('+42 days')->format('Y-m-d')],
            'reversed' => ['from' => $start->modify('+2 days')->format('Y-m-d'), 'to' => $start->format('Y-m-d')],
            'malformed' => ['from' => 'yesterday', 'to' => $start->format('Y-m-d')],
            'unknown key' => self::month(['keys' => ['site', 'holidays']]),
            'not my course' => self::month(['courseid' => (int) $foreign->id]),
            'no such course' => self::month(['courseid' => 987654]),
            'hidden category' => self::month(['categoryid' => (int) $hidden->id]),
            'no such category' => self::month(['categoryid' => 987654]),
        ];
        foreach ($cases as $name => $args) {
            $result = self::call($args);
            $this->assertTrue($result['error'], $name);
            $this->assertSame('calendarrangeunavailable', $result['exception']->errorcode, $name);
        }
        // The controls: the viewer's own course and the forty-two days answer.
        $this->assertFalse(self::call(self::month(['courseid' => (int) $course->id]))['error']);
        $this->assertFalse(self::call([
            'from' => $start->format('Y-m-d'),
            'to' => $start->modify('+41 days')->format('Y-m-d'),
        ])['error']);
    }

    /**
     * The guest account gets the same refusal; nobody logged in is refused by require_login().
     *
     * @return void
     */
    public function test_the_guest_and_nobody_are_refused(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        $guest = self::call(self::month());
        $this->assertTrue($guest['error']);
        $this->assertSame('calendarrangeunavailable', $guest['exception']->errorcode);

        $this->setUser(null);
        $nobody = self::call(self::month());
        $this->assertTrue($nobody['error']);
        $this->assertNotSame('calendarrangeunavailable', $nobody['exception']->errorcode);

        // The control: a real account is answered.
        $this->setUser(self::getDataGenerator()->create_user());
        $this->assertFalse(self::call(self::month())['error']);
    }
}
