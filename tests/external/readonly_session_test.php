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
 * Tests that the showcase's reads declared read-only leave the session as they found it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/category/fixture.php');

/**
 * A service declared 'readonlysession' runs without the session lock, so nothing it writes to
 * $SESSION survives the request.
 *
 * Core's contract for a read-only session is the one write_close() checks
 * (lib/classes/session/manager.php, the array_session_diff() of $SESSION taken at session start
 * and at close): no $SESSION key added, removed or changed. That is what is asserted here around
 * the showcase's two logged-in reads, the way the client makes them. $USER is not compared, as core
 * does not compare it. The page's own preference writes (category_listing::view(),
 * category_hero::fold(), remembered::record()) are page code and the remember_category_state write
 * service; no read reaches them.
 *
 * get_public_category_rows is the one read left undeclared: it is served by service-nologin.php
 * under NO_MOODLE_COOKIES, so there is no session to lock, and it changes $SESSION->forcelang around
 * its own work, which a read-only declaration would misdescribe.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_category_rows::class)]
#[CoversClass(get_card_details::class)]
final class readonly_session_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var string The read that stays undeclared, and why is in the class docblock. */
    private const SESSIONLESS_READ = 'theme_dimensions_get_public_category_rows';

    /**
     * resetAfterTest() does not restore superglobals: drop the sesskey the calls plant in $_POST, and
     * forget the memos as the fixture trait's own tearDown(), which this one replaces, does.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        self::forget();
        parent::tearDown();
    }

    /**
     * The services that must hold no session lock, each with the arguments the client sends
     * against the fixture below.
     *
     * @param int $categoryid The fixture's category.
     * @param int $courseid The fixture's course.
     * @return array Service name => arguments.
     */
    private static function read_only_calls(int $categoryid, int $courseid): array {
        return [
            'theme_dimensions_get_category_rows' => ['categoryid' => $categoryid],
            'theme_dimensions_get_card_details' => ['courseids' => [$courseid]],
        ];
    }

    /**
     * Read the theme's db/services.php as core does.
     *
     * @return array The $functions array it declares.
     */
    private static function declared_functions(): array {
        $functions = null;
        include(\core_component::get_component_directory('theme_dimensions') . '/db/services.php');

        return $functions;
    }

    /**
     * $SESSION as core compares it: one entry per key, the value serialised so a change inside a
     * nested object is seen too.
     *
     * @return array Key => serialised value.
     */
    private static function session_snapshot(): array {
        global $SESSION;

        return array_map('serialize', (array) $SESSION);
    }

    /**
     * The $SESSION keys added, removed or changed between two snapshots.
     *
     * @param array $before Snapshot taken before.
     * @param array $after Snapshot taken after.
     * @return array The keys, sorted.
     */
    private static function changed_keys(array $before, array $after): array {
        $keys = array_keys(array_diff_assoc($before, $after) + array_diff_assoc($after, $before));
        sort($keys);

        return $keys;
    }

    /**
     * Every logged-in read and no write is declared read-only, the exercised set is the declared
     * one, and core's own reader of db/services.php sees the flag.
     *
     * Reading through external_function_info() is the point: it is the value
     * external_api::call_external_function() decides the session lock from.
     *
     * @return void
     */
    public function test_every_logged_in_read_and_no_write_is_declared_read_only(): void {
        $functions = self::declared_functions();
        $declared = [];
        foreach ($functions as $name => $function) {
            $readonly = !empty($function['readonlysession']);
            $this->assertSame($function['type'] === 'read' && $name !== self::SESSIONLESS_READ, $readonly, $name);
            $this->assertSame($readonly, (bool) external_api::external_function_info($name)->readonlysession, $name);
            if ($readonly) {
                $declared[] = $name;
            }
        }
        sort($declared);
        $exercised = array_keys(self::read_only_calls(1, 1));
        sort($exercised);

        $this->assertFalse($functions[self::SESSIONLESS_READ]['loginrequired']);
        $this->assertSame($exercised, $declared);
    }

    /**
     * The comparison sees a write, so an empty result below is not an empty comparison.
     *
     * @return void
     */
    public function test_the_snapshot_sees_a_write(): void {
        global $SESSION;

        $this->resetAfterTest();
        $before = self::session_snapshot();
        $SESSION->theme_dimensions_probe = (object) ['seen' => 1];
        $added = self::session_snapshot();
        $SESSION->theme_dimensions_probe->seen = 2;

        $this->assertSame(['theme_dimensions_probe'], self::changed_keys($before, $added));
        $this->assertSame(['theme_dimensions_probe'], self::changed_keys($added, self::session_snapshot()));
    }

    /**
     * Every read-only service leaves $SESSION as it found it, on a fixture that gives it work.
     *
     * A logged-in reader, not enrolled anywhere, and one category holding one course: the listing
     * counts it and the details answer for it. Each call starts from an empty session and a fresh
     * page, as a new request would, with the unlisted-courses memos forgotten, so nothing the
     * fixture ran can already hold a key a call would write. The read-only branch of
     * external_api::call_external_function() is taken, as on a site with
     * $CFG->enable_read_only_sessions on.
     *
     * One require_login() runs before each snapshot. Core's after_require_login callbacks run
     * inside every service of every component, and under PHPUnit tool_mfa's writes a marker
     * ($SESSION->mfa_login_hook_test, admin/tool/mfa/lib.php); paying it first keeps that core
     * write out of the comparison without exempting any key from it.
     *
     * @return void
     */
    public function test_read_only_services_leave_the_session_untouched(): void {
        global $CFG, $PAGE;

        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $viewer = $generator->create_user();
        $CFG->enable_read_only_sessions = true;

        foreach (self::read_only_calls((int) $category->id, (int) $course->id) as $name => $args) {
            \core\session\manager::init_empty_session();
            $this->become($viewer);
            $PAGE = new \moodle_page();
            require_login();
            $_POST['sesskey'] = sesskey();

            $before = self::session_snapshot();
            $result = external_api::call_external_function($name, $args, false);
            $after = self::session_snapshot();

            $this->assertFalse($result['error'], "$name: " . json_encode($result['exception'] ?? null));
            // The control: the call found the fixture's course, so its whole path ran.
            if ($name === 'theme_dimensions_get_category_rows') {
                $this->assertSame(1, (int) $result['data']['total'], $name);
            } else {
                $this->assertSame([(int) $course->id], array_map('intval', array_column($result['data']['details'], 'id')), $name);
            }
            $this->assertSame([], self::changed_keys($before, $after), "$name changed \$SESSION");
        }
    }
}
