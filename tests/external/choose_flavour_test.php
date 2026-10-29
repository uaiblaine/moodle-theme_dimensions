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
 * Theme Dimensions - Tests for the service that records the look chosen in the user menu
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\flavourchoice;

/**
 * Tests of the look service: what it records for the caller, and what it refuses.
 *
 * Called through call_external_function(), the way core/ajax reaches it, so the registration, the
 * parameter validation and the return cleaning are part of every test.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(choose_flavour::class)]
final class choose_flavour_test extends \advanced_testcase {
    /** @var string The registered name of the function under test. */
    private const FUNCTION = 'theme_dimensions_choose_flavour';

    /**
     * Call the function the way the client does.
     *
     * @param int $flavourid The chosen flavour.
     * @return array The framework's response.
     */
    private function call(int $flavourid): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::FUNCTION, ['flavourid' => $flavourid], false);
    }

    /**
     * Two flavours, the first offered.
     *
     * @return array The offered flavour and the other one.
     */
    private function flavours(): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('theme_boost_union');
        $offered = $generator->create_flavour(['title' => 'Harbour']);
        $other = $generator->create_flavour(['title' => 'Campus']);
        set_config(flavourchoice::SETTING, (string) $offered->id, 'theme_dimensions');

        return [$offered, $other];
    }

    /**
     * An offered flavour and the site default are recorded for the caller alone.
     *
     * @return void
     */
    public function test_an_offered_flavour_and_the_site_default_are_recorded(): void {
        $this->resetAfterTest();
        [$offered] = $this->flavours();
        $caller = $this->getDataGenerator()->create_user();
        $bystander = $this->getDataGenerator()->create_user();
        $this->setUser($caller);

        $response = $this->call((int) $offered->id);
        $this->assertFalse($response['error'], json_encode($response));
        $this->assertSame((int) $offered->id, $response['data']['chosen']);
        $this->assertSame((int) $offered->id, (int) get_user_preferences(flavourchoice::PREFERENCE, null, $caller));
        $this->assertNull(get_user_preferences(flavourchoice::PREFERENCE, null, $bystander));

        $response = $this->call(flavourchoice::SITE_DEFAULT);
        $this->assertFalse($response['error'], json_encode($response));
        $this->assertSame(flavourchoice::SITE_DEFAULT, $response['data']['chosen']);
    }

    /**
     * A flavour the setting does not offer is refused and nothing is written.
     *
     * @return void
     */
    public function test_a_flavour_not_offered_is_refused(): void {
        $this->resetAfterTest();
        [, $other] = $this->flavours();
        $caller = $this->getDataGenerator()->create_user();
        $this->setUser($caller);

        $response = $this->call((int) $other->id);

        $this->assertTrue($response['error']);
        $this->assertSame('flavourchoice_unavailable', $response['exception']->errorcode);
        $this->assertNull(get_user_preferences(flavourchoice::PREFERENCE, null, $caller));
    }

    /**
     * The guest account is refused, with an offered flavour as the control that the refusal is the guest's.
     *
     * @return void
     */
    public function test_the_guest_is_refused(): void {
        $this->resetAfterTest();
        [$offered] = $this->flavours();

        $this->setGuestUser();
        $response = $this->call((int) $offered->id);

        $this->assertTrue($response['error']);
        $this->assertSame('noguest', $response['exception']->errorcode);
    }
}
