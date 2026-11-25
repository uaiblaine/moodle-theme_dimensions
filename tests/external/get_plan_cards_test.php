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
     * Select one option of a plan template's tag field, as the admin, the way local_dimensions' form saves it.
     *
     * instance_form_save() drops every field the current user cannot edit, so the save runs as the admin and the
     * current user is put back afterwards.
     *
     * @param int $templateid The plan template.
     * @param string $shortname The tag field's short name.
     * @param int $option The 1-based position of the option chosen.
     * @return void
     */
    private function tag_template(int $templateid, string $shortname, int $option): void {
        global $USER;

        $previous = $USER;
        $this->setAdminUser();
        \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_LP);
        \local_dimensions\customfield\lp_handler::create()->instance_form_save((object) [
            'id' => $templateid,
            'customfield_' . $shortname => $option,
        ], true);
        $this->setUser($previous);
    }

    /**
     * A learner with a plan on a template tagged with tag 1's second option and a plain plan, favourites on and
     * both of the block's plan tag filters offered; the tagged plan is one of the learner's favourites.
     *
     * @return array [the owner, the tagged plan's id, the plain plan's id].
     */
    private function owner_with_a_favourite(): array {
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $owner = $generator->create_user();
        $template = $lpg->create_template(['shortname' => 'Cookery']);
        $this->tag_template((int) $template->get('id'), \local_dimensions\constants::CFIELD_TAG1, 2);
        $tagged = $lpg->create_plan([
            'userid' => $owner->id,
            'name' => 'A tagged plan',
            'templateid' => $template->get('id'),
            'status' => plan::STATUS_ACTIVE,
        ]);
        $plain = $lpg->create_plan(['userid' => $owner->id, 'name' => 'B plain plan', 'status' => plan::STATUS_ACTIVE]);
        set_config('enable_favourites', 1, 'block_dimensions');
        set_config('enable_plan_tag1_filter', 1, 'block_dimensions');
        set_config('enable_plan_tag2_filter', 1, 'block_dimensions');
        $this->favourite((int) $owner->id, (int) $tagged->get('id'));

        return [$owner, (int) $tagged->get('id'), (int) $plain->get('id')];
    }

    /**
     * Make a plan one of a person's favourites, the row block_dimensions' star writes.
     *
     * @param int $userid The person.
     * @param int $planid The plan.
     * @return void
     */
    private function favourite(int $userid, int $planid): void {
        $usercontext = \context_user::instance($userid);
        \core_favourites\service_factory::get_service_for_user_context($usercontext)
            ->create_favourite('block_dimensions', 'plan', $planid, $usercontext);
    }

    /**
     * An answer's cards keyed by plan id.
     *
     * @param array $result call_external_function()'s answer.
     * @return array Plan id => card.
     */
    private static function by_id(array $result): array {
        $cards = [];
        foreach ($result['data']['cards'] as $card) {
            $cards[(int) $card['id']] = $card;
        }

        return $cards;
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
        $this->assertSame(['layout', 'itemclasses', 'favourites', 'tagfilters', 'cards'], array_keys($result['data']));

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
        $this->assertSame(['layout', 'itemclasses', 'favourites', 'tagfilters', 'cards'], array_keys($returns->keys));
        $this->assertSame(['key', 'label'], array_keys($returns->keys['tagfilters']->content->keys));
        $this->assertSame(['id', 'html', 'favourite', 'tags'], array_keys($returns->keys['cards']->content->keys));
        $this->assertSame(['key', 'value'], array_keys($returns->keys['cards']->content->keys['tags']->content->keys));
    }

    /**
     * Another person's list carries no favourite flag, even where its owner and the viewer both hold one on the plan.
     *
     * The rule is block_dimensions' (a person's favourites are read for their own list only); the theme passes the
     * flag through as the block hands it, and this pins the pass-through at the door. The owner's own call is the
     * control: the same plans, the flag on each card, the favourite pressed.
     *
     * @return void
     */
    public function test_another_persons_list_carries_no_favourite(): void {
        $this->resetAfterTest();
        [$owner, $taggedid, $plainid] = $this->owner_with_a_favourite();
        $viewer = $this->viewer_with($owner, ['moodle/competency:planview']);
        $this->favourite((int) $viewer->id, $taggedid);

        $this->setUser($owner);
        $own = $this->ask((int) $owner->id);
        $this->assertFalse($own['error'], json_encode($own['exception'] ?? null));
        $this->assertTrue($own['data']['favourites'], 'Control: the owner is offered the star.');
        $owncards = self::by_id($own);
        $this->assertSame(get_plan_cards::FAVOURITE_YES, (int) $owncards[$taggedid]['favourite']);
        $this->assertSame(get_plan_cards::FAVOURITE_NO, (int) $owncards[$plainid]['favourite']);

        $this->setUser($viewer);
        $other = $this->ask((int) $owner->id);
        $this->assertFalse($other['error'], json_encode($other['exception'] ?? null));
        $this->assertFalse($other['data']['favourites']);
        $cards = self::by_id($other);
        $this->assertSame([$taggedid, $plainid], array_keys($cards), 'The viewer must read both plans.');
        foreach ($cards as $planid => $card) {
            $this->assertSame(get_plan_cards::FAVOURITE_NONE, (int) $card['favourite'], "Plan {$planid} carries a flag.");
        }
    }

    /**
     * With the block's favourites off the owner's own list carries no flag; on again, the same call carries them.
     *
     * @return void
     */
    public function test_favourites_off_carry_no_flag(): void {
        $this->resetAfterTest();
        [$owner, $taggedid] = $this->owner_with_a_favourite();
        $this->setUser($owner);

        set_config('enable_favourites', 0, 'block_dimensions');
        $off = $this->ask((int) $owner->id);
        $this->assertFalse($off['data']['favourites']);
        foreach (self::by_id($off) as $card) {
            $this->assertSame(get_plan_cards::FAVOURITE_NONE, (int) $card['favourite']);
        }

        set_config('enable_favourites', 1, 'block_dimensions');
        $on = $this->ask((int) $owner->id);
        $this->assertTrue($on['data']['favourites'], 'Control: favourites on, the star is offered.');
        $this->assertSame(get_plan_cards::FAVOURITE_YES, (int) self::by_id($on)[$taggedid]['favourite']);
    }

    /**
     * Each card carries its template's tag values, and the tag filters are the block's that a listed plan has a
     * value for, with the block's labels.
     *
     * @return void
     */
    public function test_the_tags_and_their_filters(): void {
        $this->resetAfterTest();
        [$owner, $taggedid, $plainid] = $this->owner_with_a_favourite();
        $this->setUser($owner);

        $result = $this->ask((int) $owner->id);

        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $offered = plan_cards::tag_filters();
        $this->assertSame(['tag1', 'tag2'], array_column($offered, 'key'), 'Control: the block offers both filters.');
        $this->assertSame([['key' => 'tag1', 'label' => $offered[0]['label']]], $result['data']['tagfilters']);
        $cards = self::by_id($result);
        $this->assertSame([['key' => 'tag1', 'value' => 'Edit me 2']], $cards[$taggedid]['tags']);
        $this->assertSame([], $cards[$plainid]['tags']);

        set_config('enable_plan_tag1_filter', 0, 'block_dimensions');
        $this->assertSame([], $this->ask((int) $owner->id)['data']['tagfilters'], 'A filter the block drops is dropped.');
    }
}
