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
 * Theme Dimensions - Tests for the calendar page's state
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landingfields;

/**
 * The state's vocabularies, its three spellings and the rule that an address naming it wins over the saved one.
 *
 * Every refusal-by-dropping is asserted beside a value of the same field that is kept, so a validation replaced by
 * "drop everything" fails as surely as one replaced by "keep everything".
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(state::class)]
final class state_test extends \advanced_testcase {
    /**
     * The course fields resolve, as on a site the theme was installed on.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
    }

    /**
     * Forget the request parameters a test set.
     *
     * @return void
     */
    protected function tearDown(): void {
        $_GET = [];
        $_POST = [];
        parent::tearDown();
    }

    /**
     * Every value outside its vocabulary is dropped, and the value beside it of the same field is kept.
     *
     * @return void
     */
    public function test_the_vocabularies_drop_and_keep(): void {
        $state = new state(
            ['group', 'nosuchkey', 'open', 7],
            -4,
            false,
            [landingfields::MODEL_EVENT, 99, 0, 'x'],
            [1, 1, 42],
            str_repeat('a', state::MAX_CITY + 1),
            true,
            'rainbow'
        );
        $this->assertSame(['group', 'open'], $state->off);
        $this->assertSame(0, $state->categoryid);
        $this->assertFalse($state->recursive);
        $this->assertSame([landingfields::MODEL_EVENT], $state->models);
        $this->assertSame([1], $state->modalities);
        $this->assertSame('', $state->city, 'An overlong city is dropped.');
        $this->assertTrue($state->actiononly);
        $this->assertSame(state::COLOUR_KEY, $state->colour);

        $kept = new state([], 5, true, [], [], '  Recife <b>Sul</b> ', false, state::COLOUR_CATEGORY);
        $this->assertSame(5, $kept->categoryid);
        $this->assertSame('Recife Sul', $kept->city, 'The city is plain text, trimmed.');
        $this->assertSame(state::COLOUR_CATEGORY, $kept->colour);
        $this->assertSame(['site', 'category', 'course', 'group', 'user', 'activity', 'other', 'open'], $kept->on());
    }

    /**
     * A stored value is read back through the same vocabularies; anything malformed is the default.
     *
     * @return void
     */
    public function test_a_stored_value_is_validated_on_the_way_out(): void {
        $this->assertTrue(state::decode('not json')->is_default());
        $this->assertTrue(state::decode('[]')->is_default());
        $this->assertTrue(state::decode('"string"')->is_default());
        $read = state::decode(json_encode(['off' => ['site', 'evil'], 'categoryid' => '12', 'model' => [99], 'colour' => 'x']));
        $this->assertSame(['site'], $read->off);
        $this->assertSame(12, $read->categoryid);
        $this->assertSame([], $read->models);
        $this->assertSame(state::COLOUR_KEY, $read->colour);
    }

    /**
     * A real account stores its state; the default is no row at all; the guest stores nothing.
     *
     * @return void
     */
    public function test_saving_writes_one_preference_and_the_default_none(): void {
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        (new state(['group'], 0, true, [], [], 'Natal'))->save();
        $this->assertSame(['group'], state::saved()->off);
        $this->assertSame('Natal', state::saved()->city);

        (new state())->save();
        $this->assertNull(get_user_preferences(state::PREFERENCE), 'The default state is no row.');

        $this->setGuestUser();
        (new state(['group']))->save();
        $this->assertNull(get_user_preferences(state::PREFERENCE), 'Not even in the guest\'s session.');
        $this->assertTrue(state::saved()->is_default());
        $this->assertNull(get_user_preferences(state::PREFERENCE, null, (int) guest_user()->id));
    }

    /**
     * An address naming any of the state wins whole over the saved state; a bare one, the month included, does not.
     *
     * @return void
     */
    public function test_an_address_naming_the_state_wins_over_the_saved_one(): void {
        $this->setUser(self::getDataGenerator()->create_user());
        (new state(['site'], 0, true, [], [], 'Natal'))->save();

        $_GET = ['month' => '2026-11'];
        $this->assertNull(state::from_request());
        $this->assertSame('Natal', state::for_request()->city, 'A bare address shows the saved state.');

        $_GET = ['colour' => 'key'];
        $named = state::for_request();
        $this->assertSame([], $named->off, 'Named, the address is read whole: every key on.');
        $this->assertSame('', $named->city);
    }

    /**
     * The address spells the keys that are on, with an empty member for none; a stray shape is dropped silently.
     *
     * @return void
     */
    public function test_the_address_round_trip(): void {
        $state = new state(['group', 'open'], 3, false, [landingfields::MODEL_COURSE], [2], 'Recife', true, state::COLOUR_CATEGORY);
        $_GET = $state->query();
        $this->assertSame($state->to_array(), state::from_request()->to_array());

        $none = new state(keys::ALL);
        $this->assertSame([''], $none->query()[state::PARAM_KEYS]);
        $_GET = $none->query();
        $this->assertSame(keys::ALL, state::from_request()->off);

        // A scalar where an array belongs reads as empty, with no debugging message.
        $_GET = ['keys' => 'site', 'model' => '1'];
        $stray = state::from_request();
        $this->assertSame(keys::ALL, $stray->off);
        $this->assertSame([], $stray->models);
        $this->assertDebuggingNotCalled();
    }
}
