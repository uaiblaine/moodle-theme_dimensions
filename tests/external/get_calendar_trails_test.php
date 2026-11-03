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
 * Theme Dimensions - Tests for the Trilhas view's service
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_course\customfield\course_handler;
use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/category/fixture.php');

/**
 * The service answers a logged-in account through core's own call path, and every miss with one refusal.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_calendar_trails::class)]
final class get_calendar_trails_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var string The service. */
    private const NAME = 'theme_dimensions_get_calendar_trails';

    /**
     * Skip without the module.
     */
    protected function setUp(): void {
        parent::setUp();
        if (\core_component::get_component_directory('mod_subcourse') === null) {
            $this->markTestSkipped('mod_subcourse is not installed.');
        }
        $this->resetAfterTest();
    }

    /**
     * resetAfterTest() does not restore superglobals: drop the sesskey the calls plant.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        parent::tearDown();
    }

    /**
     * Call the service as the client does.
     *
     * @param array $args The arguments.
     * @return array call_external_function()'s answer.
     */
    private static function call(array $args): array {
        self::forget();
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::NAME, $args, true);
    }

    /**
     * A trail of one step in March 2027 whose names carry markup.
     *
     * @return \stdClass The trail course.
     */
    private function seed(): \stdClass {
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $generator = self::getDataGenerator();
        $ref = $generator->create_course([
            'fullname' => 'Fire & <b>rescue</b>',
            'startdate' => make_timestamp(2027, 3, 2, 12),
            'enddate' => make_timestamp(2027, 3, 20, 12),
        ]);
        $trail = $generator->create_course(['fullname' => 'Trilha <i>um</i>']);
        course_handler::create()->instance_form_save((object) [
            'id' => $trail->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_TRAIL,
        ]);
        $generator->create_module('subcourse', ['course' => $trail->id, 'refcourse' => $ref->id, 'name' => 'Passo <b>1</b>']);
        return $trail;
    }

    /**
     * The answer comes through core's call path, in the plain spelling and with no markup.
     *
     * @return void
     */
    public function test_the_answer(): void {
        $trail = $this->seed();
        $this->setUser(self::getDataGenerator()->create_user());

        $result = self::call(['month' => '2027-03']);

        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $data = $result['data'];
        $this->assertSame('2027-03-01', $data['from']);
        $this->assertSame([(int) $trail->id], array_column($data['trails'], 'id'));
        $this->assertSame('Trilha um', $data['trails'][0]['name']);
        $this->assertSame('Passo 1', $data['trails'][0]['steps'][0]['name']);
        $this->assertSame('Fire & rescue', $data['trails'][0]['steps'][0]['coursename']);
        $json = json_encode($data);
        $this->assertStringNotContainsString('<', $json);

        // A trail id kept, and one that is no trail: the second names nothing.
        $this->assertCount(1, self::call(['month' => '2027-03', 'trail' => (int) $trail->id])['data']['trails']);
        $none = self::call(['month' => '2027-03', 'trail' => 987654]);
        $this->assertFalse($none['error']);
        $this->assertSame([], $none['data']['trails']);
    }

    /**
     * Every miss, of the request's shape or of the site, is the same refusal; the guest is refused, nobody is sent to
     * require_login().
     *
     * @return void
     */
    public function test_every_miss_is_the_same_refusal(): void {
        $this->seed();
        $hidden = self::getDataGenerator()->create_category(['visible' => 0]);
        $this->setUser(self::getDataGenerator()->create_user());

        $cases = [
            'malformed' => ['month' => 'march'],
            'month 13' => ['month' => '2027-13'],
            'year 1899' => ['month' => '1899-12'],
            'hidden category' => ['month' => '2027-03', 'categoryid' => (int) $hidden->id],
            'no such category' => ['month' => '2027-03', 'categoryid' => 987654],
        ];
        foreach ($cases as $name => $args) {
            $result = self::call($args);
            $this->assertTrue($result['error'], $name);
            $this->assertSame('calendarrangeunavailable', $result['exception']->errorcode, $name);
        }

        $this->setGuestUser();
        $guest = self::call(['month' => '2027-03']);
        $this->assertTrue($guest['error']);
        $this->assertSame('calendarrangeunavailable', $guest['exception']->errorcode);

        $this->setUser(null);
        $nobody = self::call(['month' => '2027-03']);
        $this->assertTrue($nobody['error']);
        $this->assertNotSame('calendarrangeunavailable', $nobody['exception']->errorcode);

        // The control: a real account is answered.
        $this->setUser(self::getDataGenerator()->create_user());
        $this->assertFalse(self::call(['month' => '2027-03'])['error']);
    }
}
