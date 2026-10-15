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
 * Theme Dimensions - Tests for the public shortlink route reaching the landing page
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

use theme_dimensions\local\landingfields;
use theme_dimensions\shortlink_handler;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Core's /p/{shortcode} route, end to end: the route, core's manager, this theme's handler.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(shortlink_handler::class)]
#[CoversClass(links::class)]
final class shortlink_route_test extends \core\tests\router\route_testcase {
    /**
     * A visitor who is not logged in, on a site that forces login, is sent from the short address to the page.
     *
     * The page then applies its own rules; the route asks nothing of the visitor.
     */
    public function test_the_public_route_sends_a_visitor_to_the_page(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $CFG->routerconfigured = true;
        $CFG->forcelogin = 1;
        $course = $this->getDataGenerator()->create_course();
        /* The row is written directly, not through links::share(): spelling the address builds a
           router, and the harness then builds a second one over the same shared route collector, so
           every route is mapped twice and the request fails with FastRoute's "Cannot register two
           routes matching" (a 500). Only the harness may build a router here. */
        $DB->insert_record('shortlink', (object) [
            'shortcode' => 'batepo',
            'userid' => 0,
            'component' => landingfields::COMPONENT,
            'linktype' => links::LINKTYPE,
            'identifier' => (string) $course->id,
        ]);
        $this->assertSame('batepo', links::existing_code((int) $course->id));
        $this->setUser(null);

        $response = $this->process_request('GET', '/p/batepo', '');

        $this->assertSame(302, $response->getStatusCode(), 'The route answered: ' . substr((string) $response->getBody(), 0, 3000));
        $this->assertSame(links::page((int) $course->id)->out(false), $response->getHeaderLine('Location'));
    }

    /**
     * A code nobody minted is not found.
     */
    public function test_an_unknown_code_is_not_found(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->routerconfigured = true;
        $response = $this->process_request('GET', '/p/tamebo', '');
        $this->assert_valid_response($response, 404);
    }
}
