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
 * Theme Dimensions - Tests for the landing page route
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\route\controller;

use core\router\route_loader_interface;
use theme_dimensions\local\landing\guestaccess;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landing\redirector;
use theme_dimensions\local\landing\request;
use theme_dimensions\local\landingfields;
use theme_dimensions\output\opengraph;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;

/**
 * The landing page route, end to end: core's router, this controller, the shared request.
 *
 * The harness builds a router per process_request(), and a second router built in the same
 * test - by a second request, or by spelling a route address before the request - registers
 * every route twice and the request fails. So each test makes one request, and the expected
 * addresses are built afterwards, through the same router.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(landing::class)]
#[CoversClass(request::class)]
final class landing_test extends \core\tests\router\route_testcase {
    /**
     * Forget what local_unlistedcourses memoised for the last test.
     *
     * Its predicates memoise per course id in static arrays, and PHPUnit restores the
     * sequences between tests, so the first course of every test has the same id: without
     * this the state of one test's course answers for the next test's.
     */
    protected function setUp(): void {
        parent::setUp();
        \local_unlistedcourses\discoverability::reset_caches();
        \local_unlistedcourses\access::reset_caches();
        \local_unlistedcourses\category_access::reset_caches();
    }

    /**
     * The head tag registry is static: leave nothing behind for the next page.
     */
    protected function tearDown(): void {
        opengraph::set(null);
        parent::tearDown();
    }

    /**
     * Declare the router configured and force login site-wide, the production shape.
     *
     * @return void
     */
    private function production_shape(): void {
        global $CFG;
        $CFG->routerconfigured = true;
        $CFG->forcelogin = 1;
    }

    /**
     * A course, optionally with a landing page model and a discoverability state.
     *
     * @param int $model The page model (0 for none).
     * @param string|null $state 'public', 'unlisted' or null for the default.
     * @param array $options Extra course options.
     * @return \stdClass The course.
     */
    private function make_course(int $model, ?string $state = null, array $options = []): \stdClass {
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course($options);
        if ($model) {
            \core_course\customfield\course_handler::create()->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => $model,
                'customfield_' . landingfields::SHORTNAME_PUBLICDESCRIPTION => 'Curso aberto & gratuito',
            ]);
        }
        if ($state !== null) {
            $states = [
                'public' => \local_unlistedcourses\discoverability::STATE_PUBLIC,
                'unlisted' => \local_unlistedcourses\discoverability::STATE_UNLISTED,
            ];
            \local_unlistedcourses\discoverability::set_state((int) $course->id, $states[$state], (int) get_admin()->id);
        }
        return $course;
    }

    /**
     * Request the landing page of a course id through the router.
     *
     * @param string $course The path segment: a course id, or anything else.
     * @param string $method GET or POST.
     * @return ResponseInterface The response.
     */
    private function request(string $course, string $method = 'GET'): ResponseInterface {
        return $this->process_request(
            $method,
            'theme_dimensions/landing/' . $course,
            route_loader_interface::ROUTE_GROUP_PAGE,
            ['HTTP_ACCEPT' => 'text/html'],
            null,
            null
        );
    }

    /**
     * A visitor gets the page of a public course, with its head tags naming the route as canonical.
     *
     * Under PHPUnit the header renders nothing, so the tags are read from the registry the
     * shared request armed for the head hook; the hook's own rendering is pinned in opengraph_test.
     */
    public function test_a_visitor_gets_a_public_course_with_the_route_as_canonical(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->production_shape();
        $course = $this->make_course(landingfields::MODEL_COURSE, 'public', ['fullname' => 'Resgate & Salvamento']);
        $this->setUser(null);

        $response = $this->request((string) $course->id);

        $this->assertSame(200, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertStringContainsString('Salvamento', (string) $response->getBody());
        $canonical = links::page((int) $course->id)->out(false);
        $this->assertSame($CFG->wwwroot . '/theme_dimensions/landing/' . $course->id, $canonical);
        $this->assertStringNotContainsString('landing.php', $canonical);
        $tags = opengraph::current();
        $this->assertNotNull($tags, 'A public course arms its head tags.');
        $this->assertSame($canonical, $tags->get_url());
        $this->assertSame('Resgate & Salvamento', $tags->export_for_template($this->renderer())->title);
    }

    /**
     * The route accepts a POST, the way the enrolment widgets post to the page's address.
     */
    public function test_the_route_answers_a_post(): void {
        $this->resetAfterTest();
        $this->production_shape();
        $course = $this->make_course(landingfields::MODEL_COURSE, 'public');
        $this->setUser(null);

        $response = $this->request((string) $course->id, 'POST');

        $this->assertSame(200, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertNotNull(opengraph::current());
    }

    /**
     * A text filter running over a textarea field finds a page context already set.
     *
     * A real request reaches the shared request with an empty $PAGE; filter_emoticon reads
     * $PAGE->theme while it formats the text, and a page with no context makes that a
     * debugging call, which the router turns into a failed response under developer debugging.
     */
    public function test_a_filtered_textarea_is_read_with_the_page_context_set(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->make_course(landingfields::MODEL_COURSE, 'public');
        \core_course\customfield\course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_AUDIENCE . '_editor' => [
                'text' => '<p>Open to everyone :-)</p>',
                'format' => FORMAT_HTML,
            ],
        ]);
        filter_set_global_state('emoticon', TEXTFILTER_ON);
        $this->setUser(null);
        // A fresh page, as a request starts: nothing has set a context yet.
        $PAGE = new \moodle_page();

        $request = request::handle((int) $course->id);

        $this->assertDebuggingNotCalled();
        $this->assertNull($request->redirect);
        $this->assertNotNull($request->page);
    }

    /**
     * A visitor is sent to the login page for a course that is not public, with the route to come back to.
     */
    public function test_a_visitor_is_refused_for_a_course_that_is_not_public(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->production_shape();
        $course = $this->make_course(landingfields::MODEL_COURSE);
        $this->setUser(null);

        $response = $this->request((string) $course->id);

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(get_login_url(), $response->getHeaderLine('Location'));
        $this->assertSame(links::page((int) $course->id)->out(false), $SESSION->wantsurl);
        $this->assertNull(opengraph::current());
    }

    /**
     * A visitor gets the same answer for a course that does not exist: nothing tells an anonymous
     * client which ids exist.
     *
     * A test of its own, beside the refusal above, because each test may make one request (see the
     * class docblock); the two assert the same status, Location, wantsurl, empty body and absent head tags.
     */
    public function test_a_visitor_is_refused_the_same_way_for_a_course_that_does_not_exist(): void {
        global $DB, $SESSION;
        $this->resetAfterTest();
        $this->production_shape();
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course}') + 1000;
        $this->setUser(null);

        $response = $this->request((string) $missing);

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(get_login_url(), $response->getHeaderLine('Location'));
        $this->assertSame(links::page($missing)->out(false), $SESSION->wantsurl);
        $this->assertSame('', (string) $response->getBody(), 'A refusal is a redirect and no document.');
        $this->assertNull(opengraph::current(), 'Nothing about the course reaches the head of a refusal.');
    }

    /**
     * A path that is not a course id is not found before the controller runs.
     */
    public function test_a_non_numeric_id_is_not_found(): void {
        $this->resetAfterTest();
        $this->production_shape();

        $response = $this->request('abc');

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * A logged-in user of a course without a page model keeps the standard enrolment page.
     */
    public function test_a_course_without_a_model_forwards_to_the_standard_enrolment_page(): void {
        $this->resetAfterTest();
        $this->production_shape();
        $course = $this->make_course(0);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->request((string) $course->id);

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(
            (new \core\url('/enrol/index.php', ['id' => $course->id]))->out(false),
            $response->getHeaderLine('Location')
        );
    }

    /**
     * A logged-in user who must not learn that an unlisted course exists gets the ghost page.
     */
    public function test_a_ghosted_user_gets_the_unavailable_page(): void {
        $this->resetAfterTest();
        $this->production_shape();
        $course = $this->make_course(landingfields::MODEL_COURSE, 'unlisted');
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->request((string) $course->id);

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(redirector::ghost_url()->out(false), $response->getHeaderLine('Location'));
    }

    /**
     * An enrolled user gets the page of a course that is not public, and no head tags with it.
     */
    public function test_an_enrolled_user_gets_a_non_public_course_without_head_tags(): void {
        $this->resetAfterTest();
        $this->production_shape();
        $course = $this->make_course(landingfields::MODEL_COURSE, null, ['fullname' => 'Curso Interno']);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $response = $this->request((string) $course->id);

        $this->assertSame(
            200,
            $response->getStatusCode(),
            'Answered ' . $response->getStatusCode() . ' to ' . $response->getHeaderLine('Location')
                . ': ' . substr((string) $response->getBody(), 0, 2000)
        );
        $this->assertStringContainsString('Curso Interno', (string) $response->getBody());
        $this->assertNull(opengraph::current(), 'A course that is not public arms no head tags.');
    }

    /**
     * The route passes guest=1 through to the request: an anonymous visitor gets the guest entry's interstitial.
     *
     * Core's validator strips every query parameter a route does not declare, so this is the test that
     * the declaration is there. The interstitial carries the landing page's head tags, its canonical the
     * route without the parameter, and its form posts back to the route.
     */
    public function test_the_route_passes_the_guest_entry_through(): void {
        global $DB;
        $this->resetAfterTest();
        // The site must admit the guest account, or the entry is withdrawn (guestaccess::available()).
        set_config('autologinguests', 1);
        $this->production_shape();
        $course = $this->make_course(landingfields::MODEL_COURSE, 'public', ['fullname' => 'Curso Aberto']);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $course->id, 'enrol' => 'guest']);
        $this->setUser(null);

        $response = $this->request($course->id . '?' . links::GUEST_PARAM . '=1');

        $this->assertSame(200, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $body = (string) $response->getBody();
        $this->assertStringContainsString('name="' . guestaccess::ENTER_PARAM . '"', $body);
        $this->assertFalse(isloggedin(), 'The page logs nobody in; only its form does.');
        $canonical = links::page((int) $course->id)->out(false);
        $this->assertStringContainsString('action="' . s($canonical) . '"', $body);
        $tags = opengraph::current();
        $this->assertNotNull($tags);
        $this->assertSame($canonical, $tags->get_url());
    }

    /**
     * Through the route, a reader already logged in who asks for the guest entry is sent to the course.
     */
    public function test_the_route_sends_a_logged_in_reader_of_the_guest_entry_to_the_course(): void {
        global $DB;
        $this->resetAfterTest();
        // The site must admit the guest account, or the entry is withdrawn (guestaccess::available()).
        set_config('autologinguests', 1);
        $this->production_shape();
        $course = $this->make_course(landingfields::MODEL_COURSE, 'public');
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $course->id, 'enrol' => 'guest']);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->request($course->id . '?' . links::GUEST_PARAM . '=1');

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(guestaccess::target((int) $course->id)->out(false), $response->getHeaderLine('Location'));
    }

    /**
     * A renderer for exporting the head tags.
     *
     * @return \renderer_base The renderer.
     */
    private function renderer(): \renderer_base {
        global $PAGE;
        return $PAGE->get_renderer('core');
    }
}
