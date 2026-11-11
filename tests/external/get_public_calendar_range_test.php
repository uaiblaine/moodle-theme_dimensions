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
 * Theme Dimensions - Tests for the public calendar's web service
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\calendar\publicrange;
use theme_dimensions\local\calendar\visitor;
use theme_dimensions\local\calendar\window;
use theme_dimensions\local\category\publicaccess;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/calendar/fixture.php');

/**
 * The one function a visitor's calendar asks: its refusal order, its shape checks, its language and its answer.
 *
 * PHPUnit cannot reach what lib/ajax/service-nologin.php adds (the cookie-less session, the headers, a real batch);
 * the order of the checks is pinned here the way get_public_category_rows_test pins its twin's: every refusal is the
 * same bytes, each beside the same call answered once the one reason is gone; the cheap checks read nothing; a
 * check that a later layer would also refuse is told apart by what it costs or by the order it runs in.
 *
 * Every assertion that something is not answered has an answered control in the same test.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_public_calendar_range::class)]
final class get_public_calendar_range_test extends \advanced_testcase {
    use \theme_dimensions\local\calendar\fixture;

    /** @var string The registered name of the function under test. */
    private const FUNCTION = 'theme_dimensions_get_public_calendar_range';

    /** @var array The fixture's ids, by role. */
    private array $ids = [];

    /**
     * Leave nothing for the next test: a cachekey left in $_GET refuses every later call of the process.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_GET['cachekey']);
        self::forget_calendar();
        parent::tearDown();
    }

    /**
     * A public category with an event, a listed one with another, and a public event course; the switch on.
     *
     * Every non-public name carries ZZ, so one scan of an answer proves none of them travelled.
     *
     * @param bool $forcelogin Whether the site forces login.
     * @return void
     */
    private function seed(bool $forcelogin = true): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->forcelogin = $forcelogin ? 1 : 0;
        $CFG->routerconfigured = false;
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
        $internal = self::event_course($public, 4, 6, ['fullname' => 'ZZ Curso Interno']);
        set_config(visitor::SETTING, '1', 'theme_dimensions');
        $this->ids = ['public' => $public, 'listed' => $listed, 'event' => (int) $event->id, 'course' => (int) $course->id,
            'internal' => (int) $internal->id];
        self::forget_calendar();
    }

    /**
     * Next month's window as the service is asked for it.
     *
     * @param array $more Further arguments.
     * @return array
     */
    private static function args(array $more = []): array {
        $window = self::month_window();
        return $more + ['from' => $window->from, 'to' => $window->to];
    }

    /**
     * Call the function the way service-nologin.php does, as a new request.
     *
     * @param array $args The arguments.
     * @return array The framework's response.
     */
    private function call(array $args): array {
        get_public_calendar_range::reset();
        return external_api::call_external_function(self::FUNCTION, $args, true);
    }

    /**
     * Call it as a visitor and assert it answered.
     *
     * @param array $args The arguments.
     * @return array The cleaned payload.
     */
    private function ask(array $args = []): array {
        $this->become(null);
        $response = $this->call(self::args($args));
        $this->assertFalse($response['error'], 'The function refused: ' . ($response['exception']->message ?? ''));
        return $response['data'];
    }

    /**
     * Call it in the session as it stands and assert it refused.
     *
     * @param array $args The arguments.
     * @return \stdClass The exception information the framework returns.
     */
    private function refusal(array $args = []): \stdClass {
        $response = $this->call(self::args($args));
        $this->assertTrue($response['error'], 'The function answered what it had to refuse.');
        return $response['exception'];
    }

    /**
     * The statements one call of execute() reads, as a visitor in a new request, and what it threw.
     *
     * @param array $args The arguments, by name.
     * @param string|null $errorcode Set to the error code of what was thrown, null when it answered.
     * @return int The reads.
     */
    private function reads_of(array $args, ?string &$errorcode = null): int {
        $this->become(null);
        $errorcode = null;
        $GLOBALS['PAGE'] = new \moodle_page();
        get_public_calendar_range::reset();
        return $this->reads(static function () use ($args, &$errorcode): void {
            try {
                get_public_calendar_range::execute(...$args);
            } catch (\moodle_exception $exception) {
                $errorcode = $exception->errorcode;
            }
        });
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
        if (!is_array($value) && !is_object($value)) {
            return [];
        }
        $found = [];
        foreach ((array) $value as $part) {
            $found = array_merge($found, self::strings($part));
        }
        return $found;
    }

    /**
     * Every request it may not answer for who is asking or for the switch is refused with one exception, byte for byte.
     *
     * A logged-in account, the guest, the switch never saved, stored '0' and stored 'yes'. Compared whole with debugging
     * below DEVELOPER, where the framework adds no backtrace that would tell them apart. The control: the visitor, the
     * switch on, answered.
     *
     * @return void
     */
    public function test_every_session_and_switch_refusal_is_the_same_bytes(): void {
        global $CFG;
        $this->seed();
        $CFG->debug = DEBUG_NONE;
        $CFG->debugdeveloper = false;

        $refusals = [];
        $this->setUser(self::getDataGenerator()->create_user());
        self::forget_calendar();
        $refusals['a logged-in account'] = $this->refusal();
        $this->setGuestUser();
        self::forget_calendar();
        $refusals['the guest account'] = $this->refusal();
        foreach (['the switch never saved' => null, 'a stored 0' => '0', 'anything but 1' => 'yes'] as $case => $value) {
            if ($value === null) {
                unset_config(visitor::SETTING, 'theme_dimensions');
            } else {
                set_config(visitor::SETTING, $value, 'theme_dimensions');
            }
            $this->become(null);
            $refusals[$case] = $this->refusal();
        }
        set_config(visitor::SETTING, '1', 'theme_dimensions');

        $first = reset($refusals);
        $this->assertObjectNotHasProperty('debuginfo', $first, 'Precondition: debugging is below DEVELOPER.');
        foreach ($refusals as $case => $exception) {
            $this->assertSame('calendarrangeunavailable', $exception->errorcode, $case);
            $this->assertSame(json_encode($first), json_encode($exception), $case . ': the same bytes.');
        }
        $this->assertSame([$this->ids['course'], $this->ids['event']], array_column($this->ask()['events'], 'id'), 'Control.');
    }

    /**
     * A site whose calendar type is not the Gregorian is refused, beside the same call answered on the Gregorian.
     *
     * @return void
     */
    public function test_another_calendar_type_is_refused(): void {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/tests/calendartype_test_example.php');
        $this->seed();
        $CFG->calendartype = 'test_example';
        $this->become(null);
        $this->assertSame('calendarrangeunavailable', $this->refusal()->errorcode);
        $CFG->calendartype = 'gregorian';
        $this->assertNotSame([], $this->ask()['events'], 'Control: the Gregorian type.');
    }

    /**
     * A logged-in session is refused before anything is read, the guest included: the session check is step 4.
     *
     * The control is the same call by a visitor, which costs statements.
     *
     * @return void
     */
    public function test_a_logged_in_session_is_refused_before_anything_is_read(): void {
        $this->seed();
        $sessions = ['a logged-in account' => self::getDataGenerator()->create_user(), 'the guest' => guest_user()];
        foreach ($sessions as $case => $user) {
            $this->setUser($user);
            self::forget_calendar();
            $GLOBALS['PAGE'] = new \moodle_page();
            get_public_calendar_range::reset();
            $errorcode = null;
            $args = self::args();
            $reads = $this->reads(static function () use ($args, &$errorcode): void {
                try {
                    get_public_calendar_range::execute(...$args);
                } catch (\moodle_exception $exception) {
                    $errorcode = $exception->errorcode;
                }
            });
            $this->assertSame('calendarrangeunavailable', $errorcode, $case);
            $this->assertSame(0, $reads, $case . ': refused before any statement.');
        }
        $this->assertGreaterThan(0, $this->reads_of(self::args(), $errorcode), 'Control: a visitor\'s answer reads.');
        $this->assertNull($errorcode);
    }

    /**
     * The switch is read before the sources, and the refusal costs at most the configuration's own read.
     *
     * The switch off is refused with no statement once the configuration is warm, beside the visitor answered with it on.
     *
     * @return void
     */
    public function test_the_switch_refuses_before_any_source_is_asked(): void {
        $this->seed();
        set_config(visitor::SETTING, '0', 'theme_dimensions');
        visitor::enabled();
        $this->assertSame(0, $this->reads_of(self::args(), $errorcode), 'Refused before any statement.');
        $this->assertSame('calendarrangeunavailable', $errorcode);
        set_config(visitor::SETTING, '1', 'theme_dimensions');
        visitor::enabled();
        $this->assertGreaterThan(0, $this->reads_of(self::args(), $errorcode));
        $this->assertNull($errorcode, 'Control: on, it answers.');
    }

    /**
     * The switch closes the calendar and never a public category's page: it is read beside publicaccess, not in it.
     *
     * @return void
     */
    public function test_the_switch_is_beside_publicaccess(): void {
        $this->seed();
        foreach ([null, '0', '1'] as $value) {
            if ($value === null) {
                unset_config(visitor::SETTING, 'theme_dimensions');
            } else {
                set_config(visitor::SETTING, $value, 'theme_dimensions');
            }
            $case = 'The category stays public: ' . var_export($value, true);
            $this->assertTrue(publicaccess::is_public($this->ids['public']), $case);
            $this->assertSame([$this->ids['public']], publicaccess::public_ids());
        }
        $this->assertTrue(visitor::enabled(), 'Control: a stored 1 opens the calendar.');
        unset_config(visitor::SETTING, 'theme_dimensions');
        $this->assertFalse(visitor::enabled(), 'Off by default.');
    }

    /**
     * A request carrying a cachekey is refused before any statement; the same call without it is answered.
     *
     * @return void
     */
    public function test_a_request_carrying_a_cachekey_is_refused(): void {
        $this->seed();
        $_GET['cachekey'] = time() - 1;
        $this->assertSame(0, $this->reads_of(self::args(), $errorcode));
        $this->assertSame('invalidparameter', $errorcode);
        unset($_GET['cachekey']);
        $this->assertNotSame([], $this->ask()['events'], 'Control: the same call without it.');
    }

    /**
     * One call per request: the second is refused, a refused first one counts too, a new request is answered.
     *
     * @return void
     */
    public function test_one_call_per_request(): void {
        $this->seed();
        $this->become(null);
        get_public_calendar_range::reset();
        $first = external_api::call_external_function(self::FUNCTION, self::args(), true);
        $second = external_api::call_external_function(self::FUNCTION, self::args(), true);
        $this->assertFalse($first['error'], 'The first call of a request is answered.');
        $this->assertTrue($second['error'], 'The second is not.');
        $this->assertSame('invalidparameter', $second['exception']->errorcode);

        get_public_calendar_range::reset();
        $refused = external_api::call_external_function(self::FUNCTION, self::args(['keys' => ['site']]), true);
        $after = external_api::call_external_function(self::FUNCTION, self::args(), true);
        $this->assertTrue($refused['error']);
        $this->assertSame('invalidparameter', $after['exception']->errorcode, 'A refused first call counts.');

        $this->assertNotSame([], $this->ask()['events'], 'Control: a new request is answered.');
    }

    /**
     * A malformed request is an invalid parameter, refused before anything is read; the switch off does not mask it.
     *
     * @return void
     */
    public function test_a_malformed_request_is_refused_before_anything_is_read(): void {
        $this->seed();
        $window = self::month_window();
        $cases = [
            'a day in another spelling' => ['from' => '01/11/2026', 'to' => $window->to],
            'a day the month does not have' => ['from' => '2026-11-31', 'to' => '2026-12-05'],
            'a pair given backwards' => ['from' => $window->to, 'to' => $window->from],
            'more than six weeks' => ['from' => '2026-01-01', 'to' => '2026-02-12'],
            'a key outside the two' => ['from' => $window->from, 'to' => $window->to, 'keys' => ['site']],
            'a key of the logged-in page' => ['from' => $window->from, 'to' => $window->to, 'keys' => ['category', 'user']],
        ];
        foreach ([true, false] as $on) {
            set_config(visitor::SETTING, $on ? '1' : '0', 'theme_dimensions');
            visitor::enabled();
            foreach ($cases as $case => $args) {
                $reads = $this->reads_of($args, $errorcode);
                $this->assertSame('invalidparameter', $errorcode, $case);
                $this->assertSame(0, $reads, $case . ': refused before any statement.');
            }
        }
        set_config(visitor::SETTING, '1', 'theme_dimensions');
        $this->assertNotSame([], $this->ask(['from' => '2026-01-01', 'to' => '2026-02-11'])['days'], 'Control: 42 days.');
        $this->assertSame(['course'], array_column($this->ask(['keys' => ['open']])['events'], 'source'), 'Control: one key.');
    }

    /**
     * The answer is publicrange::answer()'s for the same window, and nothing that is not public travels.
     *
     * Under forced login and on an open site alike. Every non-public name carries ZZ; the public ones are the control.
     *
     * @return void
     */
    public function test_the_answer_is_the_visitors_and_nothing_else_travels(): void {
        global $CFG;
        $this->seed();
        foreach ([true, false] as $forcelogin) {
            $CFG->forcelogin = $forcelogin ? 1 : 0;
            $answer = $this->ask();
            $this->setUser(null);
            self::forget_calendar();
            $window = self::month_window();
            $this->assertSame(
                json_encode(publicrange::answer(window::from_iso($window->from, $window->to), visitor::KEYS)),
                json_encode($answer),
                'The function answers the visitor\'s range, key for key.'
            );
            $strings = implode("\n", self::strings($answer));
            $this->assertStringNotContainsString('ZZ', $strings, 'Nothing that is not public travels.');
            $this->assertStringContainsString('Feira & Mostra', $strings, 'Control: the public event, plain.');
            $this->assertStringContainsString('Congresso Aberto', $strings, 'Control: the public event course.');
            $this->assertSame(
                [['id' => $this->ids['event'], 'text' => 'Entrada franca']],
                $answer['descriptions'],
                'The description as text.'
            );
            foreach (['/course/view.php', '/calendar/', 'pluginfile.php'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $strings, 'No address a visitor is refused.');
            }
        }
    }

    /**
     * The language the page names is spoken and given back, after an answer and after a refusal alike.
     *
     * @return void
     */
    public function test_the_language_the_page_names_is_spoken_and_given_back(): void {
        global $CFG;
        $this->seed();
        $folder = $CFG->dataroot . '/lang/xx';
        check_dir_exists($folder);
        $config = "<?php\n\$string['thislanguage'] = 'Xx';\n\$string['parentlanguage'] = '';\n";
        file_put_contents($folder . '/langconfig.php', $config);
        file_put_contents($folder . '/theme_dimensions.php', "<?php\n\$string['calendarrangeunavailable'] = 'XX fechado';\n");
        get_string_manager()->reset_caches();
        $this->assertTrue(get_string_manager()->translation_exists('xx', false), 'Precondition: the pack is installed.');
        $before = current_language();

        $spoken = null;
        $this->become(null);
        get_public_calendar_range::reset();
        $window = self::month_window();
        $answer = get_public_calendar_range::execute($window->from, $window->to, [], 'xx');
        $this->assertNotSame([], $answer['events']);
        $this->assertSame($before, current_language(), 'Given back after an answer.');

        set_config(visitor::SETTING, '0', 'theme_dimensions');
        get_public_calendar_range::reset();
        try {
            get_public_calendar_range::execute($window->from, $window->to, [], 'xx');
        } catch (\moodle_exception $exception) {
            $spoken = $exception->getMessage();
        }
        $this->assertSame('XX fechado', $spoken, 'The refusal is told in the page\'s language.');
        $this->assertSame($before, current_language(), 'Given back after a refusal.');
    }
}
