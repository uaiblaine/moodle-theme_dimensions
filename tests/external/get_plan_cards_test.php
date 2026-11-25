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
 * Theme Dimensions - Tests for the learning plans page's card service
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use block_dimensions\plan_cards;
use core_competency\api;
use core_competency\plan;
use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use theme_dimensions\local\competency\plancards;

/**
 * Tests for theme_dimensions_get_plan_cards, called as core/ajax calls it.
 *
 * The door repeats core's own rule for the learning plans page ({@see \core_competency\api::list_user_plans()}):
 * a real account, competencies enabled, and a viewer who may read the user's plans or their drafts. Each refusal
 * sits beside a control that is answered, so a refusal that passes for the wrong reason (nothing to answer
 * with) cannot hold the test green.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_plan_cards::class)]
#[CoversClass(plancards::class)]
final class get_plan_cards_test extends \advanced_testcase {
    /** @var string The function's name. */
    private const NAME = 'theme_dimensions_get_plan_cards';

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
     * @param int $userid The user whose plans are asked for.
     * @return array call_external_function()'s answer.
     */
    private function ask(int $userid): array {
        $_POST['sesskey'] = sesskey();

        return external_api::call_external_function(self::NAME, ['userid' => $userid], true);
    }

    /**
     * A learner with one plan in each of core's five statuses, the names in an order unlike the creation order.
     * The owner is allowed to read their own drafts, which core's default user role is not.
     *
     * @return array [the owner, plan ids by status name].
     */
    private function owner_with_plans(): array {
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $owner = $generator->create_user();
        $admin = get_admin();
        // Core's default user role reads its own plans and not its own drafts: with this the owner sees every status.
        assign_capability(
            'moodle/competency:planviewowndraft',
            CAP_ALLOW,
            (int) get_config('core', 'defaultuserroleid'),
            \context_system::instance()->id,
            true
        );
        $plans = [
            'complete' => $lpg->create_plan([
                'userid' => $owner->id,
                'name' => 'A finished plan',
                'status' => plan::STATUS_COMPLETE,
            ]),
            'active' => $lpg->create_plan(['userid' => $owner->id, 'name' => 'E kitchen plan', 'status' => plan::STATUS_ACTIVE]),
            'draft' => $lpg->create_plan(['userid' => $owner->id, 'name' => 'C draft plan', 'status' => plan::STATUS_DRAFT]),
            'waiting' => $lpg->create_plan([
                'userid' => $owner->id,
                'name' => 'B waiting plan',
                'status' => plan::STATUS_WAITING_FOR_REVIEW,
            ]),
            'inreview' => $lpg->create_plan([
                'userid' => $owner->id,
                'name' => 'D plan in review',
                'status' => plan::STATUS_IN_REVIEW,
                'reviewerid' => $admin->id,
            ]),
        ];

        return [$owner, array_map(static fn(plan $plan): int => (int) $plan->get('id'), $plans)];
    }

    /**
     * A user holding the given competency capabilities in the owner's user context, through a role of their own.
     *
     * @param \stdClass $owner The plans' owner.
     * @param array $capabilities The capabilities allowed.
     * @return \stdClass The viewer.
     */
    private function viewer_with(\stdClass $owner, array $capabilities): \stdClass {
        $generator = self::getDataGenerator();
        $viewer = $generator->create_user();
        $roleid = $generator->create_role();
        $system = \context_system::instance();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $system->id, true);
        }
        role_assign($roleid, $viewer->id, \context_user::instance($owner->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        return $viewer;
    }

    /**
     * The ids of an answer's cards, as integers, in the answer's order.
     *
     * @param array $result call_external_function()'s answer.
     * @return array The plan ids.
     */
    private static function ids(array $result): array {
        return array_map('intval', array_column($result['data']['cards'], 'id'));
    }

    /**
     * The owner gets a card for each plan, in core's order, each card the block's for that plan.
     *
     * @return void
     */
    public function test_the_owner_gets_every_plan_in_cores_order(): void {
        $this->resetAfterTest();
        [$owner, $ids] = $this->owner_with_plans();
        $this->setUser($owner);

        $result = $this->ask((int) $owner->id);

        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $core = array_values(array_map(
            static fn(plan $plan): int => (int) $plan->get('id'),
            api::list_user_plans((int) $owner->id)
        ));
        $this->assertCount(5, $core, 'Control: core lists the five plans to their owner.');
        $this->assertSame($core, self::ids($result));
        $this->assertSame(['layout', 'itemclasses', 'cards'], array_keys($result['data']));

        $active = array_values(array_filter(
            $result['data']['cards'],
            static fn(array $card): bool => (int) $card['id'] === $ids['active']
        ));
        $this->assertCount(1, $active);
        $this->assertStringContainsString('E kitchen plan', $active[0]['html']);
        $this->assertStringContainsString('/local/dimensions/view-plan.php?id=' . $ids['active'], $active[0]['html']);
        $this->assertStringContainsString('plan-card', $active[0]['html']);
    }

    /**
     * A stranger is refused with core's own exception, before anything is built; the owner is the control.
     *
     * @return void
     */
    public function test_a_stranger_is_refused_with_cores_exception(): void {
        $this->resetAfterTest();
        [$owner] = $this->owner_with_plans();

        $this->setUser($owner);
        $this->assertCount(5, $this->ask((int) $owner->id)['data']['cards'], 'Control: the owner is answered.');

        $this->setUser(self::getDataGenerator()->create_user());
        $result = $this->ask((int) $owner->id);

        $this->assertTrue($result['error']);
        $this->assertSame('nopermissions', $result['exception']->errorcode);
        $this->assertStringContainsString(get_capability_string('moodle/competency:planview'), $result['exception']->message);
    }

    /**
     * Without the draft capability a viewer gets no card for a draft, waiting or in-review plan; with it, every card.
     *
     * Core counts the three as draft statuses ({@see \core_competency\plan::get_draft_statuses()}).
     *
     * @return void
     */
    public function test_drafts_follow_cores_draft_capability(): void {
        $this->resetAfterTest();
        [$owner, $ids] = $this->owner_with_plans();

        $reader = $this->viewer_with($owner, ['moodle/competency:planview']);
        $this->setUser($reader);
        $result = $this->ask((int) $owner->id);
        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $shown = self::ids($result);
        sort($shown);
        $expected = [$ids['complete'], $ids['active']];
        sort($expected);
        $this->assertSame($expected, $shown);

        $control = $this->viewer_with($owner, ['moodle/competency:planview', 'moodle/competency:planviewdraft']);
        $this->setUser($control);
        $all = self::ids($this->ask((int) $owner->id));
        sort($all);
        $every = array_values($ids);
        sort($every);
        $this->assertSame($every, $all, 'Control: with both capabilities every plan has its card.');
    }

    /**
     * A viewer holding the draft capability alone is answered, with the draft statuses' cards and no other.
     *
     * Core's rule is "may read the plans or their drafts": a door asking for the first half only would refuse
     * somebody core's own page lists the drafts to.
     *
     * @return void
     */
    public function test_the_draft_capability_alone_gets_the_drafts(): void {
        $this->resetAfterTest();
        [$owner, $ids] = $this->owner_with_plans();

        $reviewer = $this->viewer_with($owner, ['moodle/competency:planviewdraft']);
        $this->setUser($reviewer);
        $result = $this->ask((int) $owner->id);
        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $shown = self::ids($result);
        sort($shown);
        $expected = [$ids['draft'], $ids['waiting'], $ids['inreview']];
        sort($expected);
        $this->assertSame($expected, $shown);
    }

    /**
     * An id nobody holds is refused before anything is built, the administrator included.
     *
     * @param int $userid An id with no live account behind it.
     * @return void
     */
    #[DataProvider('ids_nobody_holds')]
    public function test_an_id_nobody_holds_is_refused(int $userid): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = $this->ask($userid);

        $this->assertTrue($result['error']);
        $this->assertArrayNotHasKey('data', $result);
    }

    /**
     * Ids with no live account behind them.
     *
     * @return array
     */
    public static function ids_nobody_holds(): array {
        return [
            'zero' => [0],
            'negative' => [-1],
            'nobody' => [987654321],
        ];
    }

    /**
     * With competencies disabled the door refuses with core's error; enabled, the same call is answered.
     *
     * @return void
     */
    public function test_disabled_competencies_are_refused(): void {
        $this->resetAfterTest();
        [$owner] = $this->owner_with_plans();
        $this->setUser($owner);

        set_config('enabled', 1, 'core_competency');
        $this->assertFalse($this->ask((int) $owner->id)['error'], 'Control: enabled, the owner is answered.');

        set_config('enabled', 0, 'core_competency');
        $result = $this->ask((int) $owner->id);
        $this->assertTrue($result['error']);
        $this->assertSame('competenciesarenotenabled', $result['exception']->errorcode);
    }

    /**
     * The guest account is refused, with a real account beside it as the control.
     *
     * @return void
     */
    public function test_the_guest_account_is_refused(): void {
        $this->resetAfterTest();
        [$owner] = $this->owner_with_plans();

        $this->setUser($owner);
        $this->assertFalse($this->ask((int) $owner->id)['error'], 'Control: a real account is answered.');

        $this->setGuestUser();
        $result = $this->ask((int) $owner->id);
        $this->assertTrue($result['error']);
        $this->assertSame('noguest', $result['exception']->errorcode);
    }

    /**
     * Nobody logged in is refused before anything is read: by core's ajax door, and by the function's own login check
     * when it is reached directly.
     *
     * @return void
     */
    public function test_a_session_with_nobody_is_refused(): void {
        $this->resetAfterTest();
        [$owner] = $this->owner_with_plans();
        $this->setUser(null);

        $result = $this->ask((int) $owner->id);
        $this->assertTrue($result['error']);
        $this->assertSame('servicerequireslogin', $result['exception']->errorcode);
        $this->expectException(\require_login_exception::class);
        get_plan_cards::execute((int) $owner->id);
    }

    /**
     * The layout and the item classes follow the theme's setting, both ways, and an unknown value reads as horizontal.
     *
     * @return void
     */
    public function test_the_layout_follows_the_theme_setting(): void {
        $this->resetAfterTest();
        [$owner] = $this->owner_with_plans();
        $this->setUser($owner);

        set_config(plancards::SETTING, plan_cards::LAYOUT_VERTICAL, 'theme_dimensions');
        $vertical = $this->ask((int) $owner->id)['data'];
        $this->assertSame(plan_cards::LAYOUT_VERTICAL, $vertical['layout']);
        $this->assertSame(plan_cards::item_classes(plan_cards::LAYOUT_VERTICAL), $vertical['itemclasses']);

        set_config(plancards::SETTING, plan_cards::LAYOUT_HORIZONTAL, 'theme_dimensions');
        $horizontal = $this->ask((int) $owner->id)['data'];
        $this->assertSame(plan_cards::LAYOUT_HORIZONTAL, $horizontal['layout']);
        $this->assertSame(plan_cards::item_classes(plan_cards::LAYOUT_HORIZONTAL), $horizontal['itemclasses']);
        $this->assertNotSame($vertical['itemclasses'], $horizontal['itemclasses']);
        $this->assertStringContainsString('plan-card-horizontal', $horizontal['cards'][0]['html']);
        $this->assertStringNotContainsString('plan-card-horizontal', $vertical['cards'][0]['html']);
    }

    /**
     * The function is declared for core/ajax, read-only, with a session, and answers an allowlist.
     *
     * @return void
     */
    public function test_the_declaration(): void {
        $info = external_api::external_function_info(self::NAME);

        $this->assertTrue((bool) $info->allowed_from_ajax);
        $this->assertTrue((bool) $info->loginrequired);
        $this->assertTrue((bool) $info->readonlysession);
        $this->assertSame('read', $info->type);

        $returns = get_plan_cards::execute_returns();
        $this->assertSame(['layout', 'itemclasses', 'cards'], array_keys($returns->keys));
        $this->assertSame(['id', 'html'], array_keys($returns->keys['cards']->content->keys));
    }
}
