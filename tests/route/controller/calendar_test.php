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
 * Theme Dimensions - Tests for the calendar page's route and script
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\route\controller;

use core\router\route_loader_interface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use theme_dimensions\local\calendar\page;
use theme_dimensions\local\calendar\state;
use theme_dimensions\output\calendar_page;

/**
 * The calendar page end to end: core's router, this controller, the shared decision.
 *
 * One request per test (the harness builds a router per request, and a second one registers every route twice);
 * expected addresses are spelled after the request. Each redirect is asserted beside the page another test serves.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(calendar::class)]
#[CoversClass(page::class)]
final class calendar_test extends \core\tests\router\route_testcase {
    /**
     * Forget the request parameters and the plugin's memos.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $CFG->routerconfigured = true;
        \local_unlistedcourses\category_access::reset_caches();
    }

    /**
     * Forget the request parameters a test set.
     *
     * @return void
     */
    protected function tearDown(): void {
        $_GET = [];
        parent::tearDown();
    }

    /**
     * Request the calendar page through the router.
     *
     * @param array $query Query parameters, as a browser sends them.
     * @param string $method The HTTP method.
     * @return ResponseInterface
     */
    private function request(array $query = [], string $method = 'GET'): ResponseInterface {
        $_GET = $query;
        return $this->process_request(
            $method,
            'theme_dimensions/calendar',
            route_loader_interface::ROUTE_GROUP_PAGE,
            ['HTTP_ACCEPT' => 'text/html'],
            null,
            null
        );
    }

    /**
     * The props the page embedded, decoded from its mount node.
     *
     * @param string $body The page.
     * @return array
     */
    private function props(string $body): array {
        $this->assertSame(1, preg_match('~data-react-props="([^"]*)"~', $body, $found), 'The page draws its mount node.');
        return json_decode(html_entity_decode($found[1], ENT_QUOTES | ENT_HTML5), true);
    }

    /**
     * A student gets the page: the mount, the embedded answer, the no-JavaScript agenda of the month.
     *
     * @return void
     */
    public function test_a_student_gets_the_page_with_its_first_answer(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $student = $generator->create_user();
        $course = $generator->create_course(['fullname' => 'Projects & Risk']);
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->setAdminUser();
        $generator->create_event([
            'name' => 'Seminar & workshop',
            'eventtype' => 'course',
            'courseid' => $course->id,
            'timestart' => make_timestamp(2026, 11, 10, 9),
            'timeduration' => 3 * DAYSECS,
        ]);
        $this->setUser($student);

        $response = $this->request(['month' => '2026-11']);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertStringContainsString('data-react-component="' . calendar_page::COMPONENT . '"', $body);
        $props = $this->props($body);
        $this->assertSame('2026-11', $props['month']);
        $this->assertSame(['Seminar & workshop'], array_column($props['answer']['events'], 'name'));
        $this->assertStringContainsString('Seminar &amp; workshop', $body, 'The agenda lists the month, escaped once.');
        $this->assertStringContainsString(page::classic('2026-11')->out(), $body, 'Core\'s calendar stays reachable.');
    }

    /**
     * A visitor is sent to log in, and comes back to the page.
     *
     * @return void
     */
    public function test_a_visitor_is_sent_to_the_login_page(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->setUser(null);

        $response = $this->request(['month' => '2026-11']);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(get_login_url(), $response->getHeaderLine('Location'));
        $this->assertStringContainsString('month=2026-11', $SESSION->wantsurl);
    }

    /**
     * The guest account is core's calendar's, as core's primary navigation links it.
     *
     * @return void
     */
    public function test_the_guest_is_sent_to_cores_calendar(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $response = $this->request();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(page::core_calendar()->out(false), $response->getHeaderLine('Location'));
    }

    /**
     * A viewer on another calendar type is left on core's calendar: the range answers the Gregorian one alone.
     *
     * @return void
     */
    public function test_a_viewer_on_another_calendar_type_is_sent_to_cores_calendar(): void {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/calendar/tests/calendartype_test_example.php');
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $USER->calendartype = 'test_example';

        $response = $this->request();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(page::core_calendar()->out(false), $response->getHeaderLine('Location'));
    }

    /**
     * Every value outside its vocabulary is dropped, never refused; a category the viewer may not narrow to too.
     *
     * @return void
     */
    public function test_the_dropped_vocabulary(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $hidden = self::getDataGenerator()->create_category(['visible' => 0]);

        $response = $this->request([
            'month' => '2026-13',
            'keys' => ['site', 'nosuchkey'],
            'categoryid' => (string) $hidden->id,
            'colour' => 'rainbow',
            'model' => ['99'],
        ]);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $props = $this->props($body);
        $this->assertSame(page::today()->format('Y-m'), $props['month'], 'A malformed month is this month.');
        $this->assertSame(0, $props['state']['categoryid'], 'A hidden category is dropped like a missing one.');
        $this->assertSame(state::COLOUR_KEY, $props['state']['colour']);
        $this->assertSame([], $props['state']['model']);
        $this->assertSame(['category', 'course', 'group', 'user', 'activity', 'other', 'open'], $props['state']['off']);
    }

    /**
     * The open day reaches the client when the month's grid holds it, and the page's address keeps it.
     *
     * @return void
     */
    public function test_the_open_day_inside_the_grid_is_kept(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $response = $this->request(['month' => '2026-11', 'day' => '2026-11-17']);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertSame('2026-11-17', $this->props($body)['day']);
        $this->assertSame('2026-11-17', $PAGE->url->param('day'), 'A reload reopens the drawer.');
    }

    /**
     * An open day of another month is dropped, never refused: the page is served without it.
     *
     * Its control is test_the_open_day_inside_the_grid_is_kept.
     *
     * @return void
     */
    public function test_an_open_day_outside_the_grid_is_dropped(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $response = $this->request(['month' => '2026-11', 'day' => '2027-01-20']);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertSame('', $this->props($body)['day']);
        $this->assertNull($PAGE->url->param('day'));
    }

    /**
     * A POST is refused: every control of the page is a GET form or a link.
     *
     * @return void
     */
    public function test_a_post_is_refused(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $response = $this->request([], 'POST');

        $this->assertSame(405, $response->getStatusCode());
    }

    /**
     * A real account still passes core's own login gate: an unaccepted site policy is not the page.
     *
     * Its control is test_a_student_gets_the_page_with_its_first_answer, the same account with nothing pending.
     *
     * @return void
     */
    public function test_cores_login_gate_runs_for_an_account(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->sitepolicy = 'https://example.com/policy';
        $this->setUser(self::getDataGenerator()->create_user(['policyagreed' => 0]));

        try {
            $response = $this->request();
            $status = $response->getStatusCode();
            $body = (string) $response->getBody();
        } catch (\moodle_exception $exception) {
            $status = 0;
            $body = $exception->getMessage();
        }
        $this->assertNotSame(200, $status, 'The policy is asked for before the page.');
        $this->assertStringNotContainsString(calendar_page::COMPONENT, $body);
    }
}
