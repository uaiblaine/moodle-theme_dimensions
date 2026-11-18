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
 * Theme Dimensions - Tests for the mobile drawer's web service
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for theme_dimensions_get_drawer_summary, called as the client calls it.
 *
 * The decisions are drawersummary_test's; these hold the door: a real account only, the system
 * context, the answer validated against its own allowlist.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_drawer_summary::class)]
final class get_drawer_summary_test extends \advanced_testcase {
    /** @var string The function's name. */
    private const NAME = 'theme_dimensions_get_drawer_summary';

    /**
     * resetAfterTest() does not restore superglobals: drop the sesskey the calls plant in $_POST.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        parent::tearDown();
    }

    /**
     * Call the function as core/ajax does.
     *
     * @return array call_external_function()'s answer.
     */
    private function ask(): array {
        $_POST['sesskey'] = sesskey();

        return external_api::call_external_function(self::NAME, [], true);
    }

    /**
     * A real account gets its own summary, through the allowlisted returns.
     *
     * @return void
     */
    public function test_a_real_account_gets_its_summary(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Algebra <b>I</b>', 'enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $DB->insert_record('user_lastaccess', ['userid' => $student->id, 'courseid' => $course->id, 'timeaccess' => time()]);
        $this->setUser($student);

        $result = $this->ask();

        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $resume = $result['data']['resume'];
        $this->assertTrue($resume['hasitem']);
        $this->assertSame('Algebra I', $resume['name'], 'format_string() without escaping, so PARAM_TEXT keeps the answer.');
        $this->assertSame((new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false), $resume['url']);
        $this->assertSame(['resume'], array_keys($result['data']), 'The card alone: no count of courses in progress (D77).');
    }

    /**
     * The returns are an allowlist of the card alone: nothing about the person's other courses goes back.
     *
     * @return void
     */
    public function test_the_returns_are_the_card_alone(): void {
        $returns = get_drawer_summary::execute_returns();

        $this->assertSame(['resume'], array_keys($returns->keys));
        $this->assertSame(
            ['hasitem', 'name', 'url', 'detail', 'kind', 'hascompletion', 'completed', 'total', 'percent'],
            array_keys($returns->keys['resume']->keys)
        );
    }

    /**
     * The guest account is refused, with a real account beside it as the control.
     *
     * @return void
     */
    public function test_the_guest_account_is_refused(): void {
        $this->resetAfterTest();

        $this->setUser(self::getDataGenerator()->create_user());
        $this->assertFalse($this->ask()['error'], 'Control: a real account is answered.');

        $this->setGuestUser();
        $result = $this->ask();
        $this->assertTrue($result['error']);
        $this->assertSame('noguest', $result['exception']->errorcode);
    }

    /**
     * Nobody logged in is refused before anything is read.
     *
     * @return void
     */
    public function test_a_session_with_nobody_is_refused(): void {
        $this->resetAfterTest();
        $this->setUser(null);

        $result = $this->ask();
        $this->assertTrue($result['error']);
    }

    /**
     * The function is declared for core/ajax, read-only, with a session.
     *
     * @return void
     */
    public function test_the_declaration(): void {
        $info = external_api::external_function_info(self::NAME);

        $this->assertTrue((bool) $info->allowed_from_ajax);
        $this->assertTrue((bool) $info->loginrequired);
        $this->assertTrue((bool) $info->readonlysession);
        $this->assertSame('read', $info->type);
    }
}
