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
 * Theme Dimensions - Tests for the public calendar's route
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\route\controller;

use core\router\route_loader_interface;
use core_external\external_api;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use theme_dimensions\external\get_calendar_range;
use theme_dimensions\local\calendar\page;
use theme_dimensions\local\calendar\publiccache;
use theme_dimensions\local\calendar\publicrange;
use theme_dimensions\local\calendar\visitor;
use theme_dimensions\local\calendar\window;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../local/calendar/fixture.php');

/**
 * The public calendar's one endpoint: what it answers, the headers a cache reads, and its refusals.
 *
 * Every refusal has an answered control in the same test, and every refusal is the same bytes.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(calendar_public::class)]
final class calendar_public_test extends \core\tests\router\route_testcase {
    use \theme_dimensions\local\calendar\fixture;

    /** @var array The fixture's ids, by role. */
    private array $ids = [];

    /**
     * Configure the router and forget local_unlistedcourses' memos.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $CFG->routerconfigured = true;
        self::forget_calendar();
    }

    /**
     * A public category with an event, a listed one with another, a public event course and an internal one.
     *
     * Every non-public name carries ZZ, so one scan of an answer proves none of them travelled.
     *
     * @return void
     */
    private function seed(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->forcelogin = 1;
        $generator = self::getDataGenerator();
        $adminid = (int) get_admin()->id;
        $public = (int) $generator->create_category(['name' => 'Agenda Publica'])->id;
        $listed = (int) $generator->create_category(['name' => 'ZZ Listada'])->id;
        category_discoverability::set_state($public, category_discoverability::STATE_PUBLIC, $adminid);
        $event = $generator->create_event([
            'name' => 'Feira & Mostra',
            'eventtype' => 'category',
            'categoryid' => $public,
            'timestart' => self::at(10),
            'description' => '<p>Entrada <span>franca</span></p>',
            'format' => FORMAT_HTML,
        ]);
        $generator->create_event([
            'name' => 'ZZ Reuniao interna',
            'eventtype' => 'category',
            'categoryid' => $listed,
            'timestart' => self::at(11),
            'description' => 'ZZ segredo',
        ]);
        $course = self::event_course($listed, 3, 5, ['fullname' => 'Congresso Aberto']);
        discoverability::set_state((int) $course->id, discoverability::STATE_PUBLIC, $adminid);
        self::event_course($public, 4, 6, ['fullname' => 'ZZ Curso Interno']);
        set_config(visitor::SETTING, '1', 'theme_dimensions');
        $this->ids = ['public' => $public, 'event' => (int) $event->id, 'course' => (int) $course->id];
        $this->setUser(null);
        self::forget_calendar();
    }

    /**
     * Next month's grid, as the client asks for it.
     *
     * @return string[] [from, to].
     */
    private static function grid(): array {
        require_once($GLOBALS['CFG']->dirroot . '/calendar/lib.php');
        return page::grid(self::month_start()->format('Y-m'), (int) calendar_get_starting_weekday());
    }

    /**
     * Ask the route for a half at the address the client builds: the page's prefix completed with the window.
     *
     * The path is spelled here, not built through the router: a router built before process_request() registers every
     * route twice (the theme's CLAUDE.md, "In a route_testcase"). {@see test_the_prefix_is_the_routes_own()} pins
     * that this spelling is the prefix's, after a request.
     *
     * @param string $half The half.
     * @param array $more Overrides of the address's parts: token, lang, from, to.
     * @return ResponseInterface
     */
    private function ask(string $half, array $more = []): ResponseInterface {
        [$from, $to] = self::grid();
        $parts = $more + ['token' => publiccache::token($half), 'lang' => current_language(), 'from' => $from, 'to' => $to];
        $path = implode('/', ['theme_dimensions/calendar/public', $half, $parts['token'], $parts['lang'], $parts['from'],
            $parts['to']]);
        self::forget_calendar();
        return $this->process_request('GET', $path, route_loader_interface::ROUTE_GROUP_PAGE, [], null, null);
    }

    /**
     * The prefix the page hands the client is this route's address without its window, under the router and without.
     *
     * @return void
     */
    public function test_the_prefix_is_the_routes_own(): void {
        global $CFG;
        $this->seed();
        $this->answered($this->ask(publiccache::EVENTS), publiccache::EVENTS);
        $token = publiccache::token(publiccache::EVENTS);
        $lang = current_language();
        $this->assertSame(
            $CFG->wwwroot . '/theme_dimensions/calendar/public/events/' . $token . '/' . $lang,
            calendar_public::prefix(publiccache::EVENTS, $token, $lang)
        );
        [$from, $to] = self::grid();
        $this->assertSame(
            calendar_public::prefix(publiccache::EVENTS, $token, $lang) . '/' . $from . '/' . $to,
            calendar_public::url(publiccache::EVENTS, $token, $lang, $from, $to)->out(false)
        );
    }

    /**
     * The body of an answer, decoded, after asserting it is a 200 a shared cache may keep for the half's lifetime.
     *
     * @param ResponseInterface $response The response.
     * @param string $half The half asked for.
     * @return array
     */
    private function answered(ResponseInterface $response, string $half): array {
        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            'public, max-age=' . publiccache::BROWSER_LIFETIME . ', s-maxage=' . publiccache::lifetime($half),
            $response->getHeaderLine('Cache-Control')
        );
        return json_decode($body, true);
    }

    /**
     * Assert a response is the one refusal, and give back its bytes and headers to compare.
     *
     * @param ResponseInterface $response The response.
     * @return string The status, the cache header and the body.
     */
    private function refused(ResponseInterface $response): string {
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'), 'A refusal is never kept.');
        return $response->getStatusCode() . '|' . $response->getHeaderLine('Cache-Control') . '|' . $response->getBody();
    }

    /**
     * Every string in a payload, however deep.
     *
     * @param mixed $value The payload or a part of it.
     * @return string[]
     */
    private static function strings(mixed $value): array {
        if (is_string($value)) {
            return [$value];
        }
        if (!is_array($value)) {
            return [];
        }
        $found = [];
        foreach ($value as $part) {
            $found = array_merge($found, self::strings($part));
        }
        return $found;
    }

    /**
     * Each half answers its own items, and nothing that is not public travels; the two merge into the visitor's answer.
     *
     * Every key and type is the logged-in range's allowlist and the descriptions: cleaned through that structure, the
     * answer comes back byte for byte.
     *
     * @return void
     */
    public function test_each_half_is_the_visitors_and_nothing_else_travels(): void {
        $this->seed();
        $events = $this->answered($this->ask(publiccache::EVENTS), publiccache::EVENTS);
        $courses = $this->answered($this->ask(publiccache::COURSES), publiccache::COURSES);

        $this->assertSame([$this->ids['event']], array_column($events['events'], 'id'), 'The events\' half: the event.');
        $this->assertSame([$this->ids['course']], array_column($courses['events'], 'courseid'), 'The courses\' half.');
        $this->assertSame([['id' => $this->ids['event'], 'text' => 'Entrada franca']], $events['descriptions']);
        $strings = implode("\n", array_merge(self::strings($events), self::strings($courses)));
        $this->assertStringNotContainsString('ZZ', $strings, 'Nothing that is not public travels.');
        $this->assertStringContainsString('Feira & Mostra', $strings, 'Control: the public event, plain.');
        $this->assertStringContainsString('Congresso Aberto', $strings, 'Control: the public event course.');
        foreach (['/course/view.php', '/calendar/', 'pluginfile.php'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $strings, 'No address a visitor is refused.');
        }

        $keys = get_calendar_range::execute_returns()->keys;
        $keys['descriptions'] = new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The event.'),
            'text' => new external_value(PARAM_RAW, 'Its text.'),
        ]));
        $structure = new external_single_structure($keys);
        foreach ([$events, $courses] as $half) {
            $this->assertSame(json_encode($half), json_encode(external_api::clean_returnvalue($structure, $half)));
        }

        [$from, $to] = self::grid();
        $window = window::from_iso($from, $to);
        $this->assertSame(
            json_encode(publicrange::answer($window, visitor::KEYS)),
            json_encode(publicrange::merge($window, [$events, $courses])),
            'The two halves are the visitor\'s answer.'
        );
    }

    /**
     * Every reason to refuse is the same empty 404 that no cache keeps, each beside the answered request.
     *
     * @return void
     */
    public function test_every_refusal_is_the_same_bytes_and_never_kept(): void {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/tests/calendartype_test_example.php');
        $this->seed();
        [$from, $to] = self::grid();
        $this->answered($this->ask(publiccache::EVENTS), publiccache::EVENTS);

        $refusals = [];
        $refusals['another half'] = $this->refused($this->ask('rows', ['token' => publiccache::token(publiccache::EVENTS)]));
        $refusals['a language not installed'] = $this->refused($this->ask(publiccache::EVENTS, ['lang' => 'zz']));
        $refusals['a day that is none'] = $this->refused($this->ask(publiccache::EVENTS, ['from' => '2027-02-30']));
        $refusals['days out of order'] = $this->refused($this->ask(publiccache::EVENTS, ['from' => $to, 'to' => $from]));
        $refusals['a window no client asks for'] = $this->refused($this->ask(publiccache::EVENTS, [
            'from' => self::month_window()->from,
            'to' => self::month_window()->to,
        ]));
        $CFG->calendartype = 'test_example';
        $refusals['another calendar type'] = $this->refused($this->ask(publiccache::EVENTS));
        $CFG->calendartype = 'gregorian';
        foreach (['the switch never saved' => null, 'a stored 0' => '0', 'anything but 1' => 'yes'] as $case => $value) {
            if ($value === null) {
                unset_config(visitor::SETTING, 'theme_dimensions');
            } else {
                set_config(visitor::SETTING, $value, 'theme_dimensions');
            }
            $refusals[$case] = $this->refused($this->ask(publiccache::EVENTS));
        }
        set_config(visitor::SETTING, '1', 'theme_dimensions');

        $first = reset($refusals);
        foreach ($refusals as $case => $bytes) {
            $this->assertSame($first, $bytes, $case . ': the same bytes.');
        }
        $this->answered($this->ask(publiccache::COURSES), publiccache::COURSES);
    }

    /**
     * A token that is not the half's current one is sent to the current address, uncached; the current one is answered.
     *
     * @return void
     */
    public function test_a_stale_token_is_sent_to_the_current_one_uncached(): void {
        $this->seed();
        $old = publiccache::token(publiccache::COURSES);
        $this->answered($this->ask(publiccache::COURSES, ['token' => $old]), publiccache::COURSES);
        publiccache::bump(publiccache::COURSES);
        $current = publiccache::token(publiccache::COURSES);
        $this->assertNotSame($old, $current, 'Precondition: the bump renewed the token.');

        $response = $this->ask(publiccache::COURSES, ['token' => $old]);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'), 'The way to the fresh answer is never kept.');
        [$from, $to] = self::grid();
        $this->assertSame(
            calendar_public::url(publiccache::COURSES, $current, current_language(), $from, $to)->out(false),
            $response->getHeaderLine('Location')
        );
        $this->answered($this->ask(publiccache::COURSES, ['token' => $current]), publiccache::COURSES);
    }

    /**
     * A window far from this month is answered, but kept by no cache: neither the shared one nor a CDN.
     *
     * @return void
     */
    public function test_a_distant_window_is_answered_but_not_kept(): void {
        $this->seed();
        $far = self::month_start()->modify('+' . (publiccache::REACH_MONTHS + 2) . ' months')->format('Y-m');
        require_once($GLOBALS['CFG']->dirroot . '/calendar/lib.php');
        [$from, $to] = page::grid($far, (int) calendar_get_starting_weekday());
        $response = $this->ask(publiccache::EVENTS, ['from' => $from, 'to' => $to]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertSame([], json_decode((string) $response->getBody(), true)['events']);
        $window = window::from_iso($from, $to);
        $this->assertGreaterThan(
            0,
            $this->reads(static fn() => publiccache::half(publiccache::EVENTS, $window)),
            'Nor is it in the shared cache: asked again, it is computed.'
        );
        $this->answered($this->ask(publiccache::EVENTS), publiccache::EVENTS);
    }

    /**
     * The route is a GET that reads no session: core's cookie-less bootstrap, so no Set-Cookie can make a cache pass it.
     *
     * Read from the attribute, since a route test does not run the bootstrap middleware that honours it.
     *
     * @return void
     */
    public function test_the_route_is_a_get_that_reads_no_session(): void {
        $attributes = (new \ReflectionMethod(calendar_public::class, 'half'))->getAttributes(\core\router\route::class);
        $this->assertCount(1, $attributes);
        $route = $attributes[0]->newInstance();
        $this->assertFalse($route->cookies, 'No session is read or started.');
        $this->assertSame(['GET'], $route->get_methods());
        // Core's raw Vary header cannot be seen from a test; the call that takes it off is read from the source.
        $source = file_get_contents((new \ReflectionClass(calendar_public::class))->getFileName());
        $this->assertStringContainsString("header_remove('Vary');", $source, 'The language is in the address, not a Vary.');
        $calendar = (new \ReflectionMethod(calendar::class, 'view'))->getAttributes(\core\router\route::class)[0];
        $this->assertTrue($calendar->newInstance()->cookies, 'Control: the page itself reads the session.');
    }

    /**
     * A logged-in browser asking is answered the same public half: nothing in it is anybody's, and nothing is read of a
     * session.
     *
     * @return void
     */
    public function test_a_logged_in_browser_gets_the_same_half(): void {
        $this->seed();
        $visitor = (string) $this->ask(publiccache::EVENTS)->getBody();
        $this->setUser(self::getDataGenerator()->create_user());
        $this->assertSame($visitor, (string) $this->ask(publiccache::EVENTS)->getBody());
    }

    /**
     * The lifetime a shared cache reads is the half's own setting; the browser's never exceeds it.
     *
     * @return void
     */
    public function test_each_half_carries_its_own_lifetime(): void {
        $this->seed();
        set_config(publiccache::SETTING_LIFETIME[publiccache::COURSES], '30', 'theme_dimensions');
        $response = $this->ask(publiccache::COURSES);
        $this->assertSame('public, max-age=30, s-maxage=30', $response->getHeaderLine('Cache-Control'));
        $this->answered($this->ask(publiccache::EVENTS), publiccache::EVENTS);
    }

    /**
     * The language in the address is the one spoken, and the request's own is given back, after an answer and after a
     * refusal alike.
     *
     * @return void
     */
    public function test_the_language_is_spoken_and_given_back(): void {
        global $CFG;
        $this->seed();
        $folder = $CFG->dataroot . '/lang/xx';
        check_dir_exists($folder);
        $config = "<?php\n\$string['thislanguage'] = 'Xx';\n\$string['parentlanguage'] = '';\n";
        file_put_contents($folder . '/langconfig.php', $config);
        get_string_manager()->reset_caches();
        $this->assertTrue(get_string_manager()->translation_exists('xx', false), 'Precondition: the pack is installed.');
        $before = current_language();

        $this->answered($this->ask(publiccache::EVENTS, ['lang' => 'xx']), publiccache::EVENTS);
        $this->assertSame($before, current_language(), 'Given back after an answer.');
        set_config(visitor::SETTING, '0', 'theme_dimensions');
        $this->refused($this->ask(publiccache::EVENTS, ['lang' => 'xx']));
        $this->assertSame($before, current_language(), 'Given back after a refusal.');
    }
}
