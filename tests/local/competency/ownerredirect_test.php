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
 * Theme Dimensions - Tests for the owner's redirect from core's plan pages
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\yesno;

/**
 * Tests for {@see ownerredirect}: only the plan's owner, decided from the plan row, is sent to the plan overview.
 *
 * Every refusal stands beside a control that redirects, so a refusal is never proved by a fixture that could not
 * redirect anyway. The after_config branch that performs the redirect ends the request and has no unit test; its
 * guard is the Behat scenario "The owner of a plan is taken from core's plan page to the plan overview".
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(ownerredirect::class)]
final class ownerredirect_test extends \advanced_testcase {
    /** @var array The request's GET parameters before the test. */
    private array $get = [];

    /** @var array The request's POST parameters before the test. */
    private array $post = [];

    /** @var string|null The request method before the test. */
    private ?string $method = null;

    /**
     * Keep the request superglobals the tests write.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->get = $_GET;
        $this->post = $_POST;
        $this->method = $_SERVER['REQUEST_METHOD'] ?? null;
    }

    /**
     * Put the request superglobals back.
     *
     * @return void
     */
    protected function tearDown(): void {
        $_GET = $this->get;
        $_POST = $this->post;
        if ($this->method === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->method;
        }
        parent::tearDown();
    }

    /**
     * A learner with a plan, and a competency id to carry.
     *
     * @return array [owner, plan id, competency id].
     */
    private function owner_with_plan(): array {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $plan = $lpg->create_plan(['userid' => $owner->id]);
        $lpg->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $competency->get('id')]);
        return [$owner, (int) $plan->get('id'), (int) $competency->get('id')];
    }

    /**
     * The decision for the plan page, everything else as a redirect would have it.
     *
     * @param int $planid The plan id of the request.
     * @param int $userid The session user, 0 for nobody.
     * @param array $overrides Inputs to change: script, method, competencyid, stay, guest, enabled.
     * @return \core\url|null
     */
    private function decide(int $planid, int $userid, array $overrides = []): ?\core\url {
        $in = $overrides + [
            'script' => ownerredirect::PLAN_SCRIPT,
            'method' => 'GET',
            'competencyid' => 0,
            'stay' => false,
            'guest' => false,
            'enabled' => true,
        ];
        return ownerredirect::target(
            $in['script'],
            $in['method'],
            $planid,
            $in['competencyid'],
            $in['stay'],
            $userid,
            $in['guest'],
            $in['enabled']
        );
    }

    /**
     * The owner of a plan is sent to the overview of that plan, from either script; the competency is carried as an int.
     *
     * @return void
     */
    public function test_the_owner_is_sent_to_the_overview_from_both_scripts(): void {
        [$owner, $planid, $competencyid] = $this->owner_with_plan();
        $this->setUser($owner);

        $target = $this->decide($planid, (int) $owner->id);
        $this->assertNotNull($target);
        $this->assertSame(
            (new \core\url('/local/dimensions/view-plan.php'))->out_omit_querystring(),
            $target->out_omit_querystring()
        );
        $this->assertSame(['id' => (string) $planid], $target->params());

        $target = $this->decide($planid, (int) $owner->id, [
            'script' => ownerredirect::COMPETENCY_SCRIPT,
            'competencyid' => $competencyid,
        ]);
        $this->assertNotNull($target);
        $this->assertSame(['id' => (string) $planid, 'competencyid' => (string) $competencyid], $target->params());

        // A competency id of 0 is not carried: the overview opens on the plan.
        $target = $this->decide($planid, (int) $owner->id, ['script' => ownerredirect::COMPETENCY_SCRIPT]);
        $this->assertSame(['id' => (string) $planid], $target->params());
    }

    /**
     * Another learner, a manager and the administrator keep core's page over somebody else's plan.
     *
     * @return void
     */
    public function test_a_viewer_who_is_not_the_owner_keeps_cores_page(): void {
        global $DB;
        [$owner, $planid] = $this->owner_with_plan();
        $other = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();
        $managerrole = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerrole, $manager->id, \core\context\system::instance()->id);

        // The control: the owner is redirected.
        $this->assertNotNull($this->decide($planid, (int) $owner->id));

        $this->setUser($other);
        $this->assertNull($this->decide($planid, (int) $other->id));
        $this->setUser($manager);
        $this->assertNull($this->decide($planid, (int) $manager->id));
        $this->setAdminUser();
        $this->assertNull($this->decide($planid, (int) get_admin()->id));
    }

    /**
     * A plan id no row holds keeps core's page, without an exception: the answer a plan of somebody else gets.
     *
     * @return void
     */
    public function test_a_missing_plan_keeps_cores_page(): void {
        global $DB;
        [$owner, $planid] = $this->owner_with_plan();
        $this->setUser($owner);
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {competency_plan}') + 1000;

        $this->assertNotNull($this->decide($planid, (int) $owner->id));
        $this->assertNull($this->decide($missing, (int) $owner->id));
        $this->assertNull($this->decide(0, (int) $owner->id));
        $this->assertNull($this->decide(-1, (int) $owner->id));
    }

    /**
     * The guest flag and a session with nobody logged in redirect nobody, and read no table to say so.
     *
     * @return void
     */
    public function test_the_guest_and_a_visitor_are_not_redirected_and_cost_no_read(): void {
        global $DB;
        [$owner, $planid] = $this->owner_with_plan();
        $this->setUser($owner);

        // The control, which also warms the config caches: the owner's id, as a real account, is redirected.
        $this->assertNotNull($this->decide($planid, (int) $owner->id));
        $reads = $DB->perf_get_reads();
        $this->assertNotNull($this->decide($planid, (int) $owner->id));
        $this->assertGreaterThan($reads, $DB->perf_get_reads(), 'The owner\'s decision reads the plan row.');

        $reads = $DB->perf_get_reads();
        $this->assertNull($this->decide($planid, (int) $owner->id, ['guest' => true]));
        $this->assertNull($this->decide($planid, 0));
        $this->assertSame($reads, $DB->perf_get_reads(), 'The guest and a visitor read no table.');
    }

    /**
     * The request's noredirect keeps core's page for the owner.
     *
     * @return void
     */
    public function test_noredirect_keeps_cores_page_for_the_owner(): void {
        [$owner, $planid, $competencyid] = $this->owner_with_plan();
        $this->setUser($owner);

        $this->assertNotNull($this->decide($planid, (int) $owner->id));
        $this->assertNull($this->decide($planid, (int) $owner->id, ['stay' => true]));
        $this->assertNull($this->decide($planid, (int) $owner->id, [
            'script' => ownerredirect::COMPETENCY_SCRIPT,
            'competencyid' => $competencyid,
            'stay' => true,
        ]));
    }

    /**
     * The switch off keeps core's page; unset and yes read as on.
     *
     * @return void
     */
    public function test_the_switch_off_keeps_cores_page(): void {
        [$owner, $planid] = $this->owner_with_plan();
        $this->setUser($owner);

        $this->assertNotNull($this->decide($planid, (int) $owner->id));
        $this->assertNull($this->decide($planid, (int) $owner->id, ['enabled' => false]));

        unset_config(ownerredirect::SETTING, 'theme_dimensions');
        $this->assertTrue(ownerredirect::enabled(), 'An unset switch reads as on.');
        set_config(ownerredirect::SETTING, yesno::YES, 'theme_dimensions');
        $this->assertTrue(ownerredirect::enabled());
        set_config(ownerredirect::SETTING, yesno::NO, 'theme_dimensions');
        $this->assertFalse(ownerredirect::enabled());
    }

    /**
     * An owner the overview would refuse (no local/dimensions:view at the system context) keeps core's page.
     *
     * @return void
     */
    public function test_an_owner_without_the_overviews_capability_keeps_cores_page(): void {
        global $CFG;
        [$owner, $planid] = $this->owner_with_plan();
        $this->setUser($owner);

        $this->assertNotNull($this->decide($planid, (int) $owner->id));
        assign_capability(
            ownerredirect::CAPABILITY,
            CAP_PROHIBIT,
            (int) $CFG->defaultuserroleid,
            \core\context\system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertNull($this->decide($planid, (int) $owner->id));
    }

    /**
     * With competencies disabled the owner keeps core's page, which says so in core's words.
     *
     * @return void
     */
    public function test_disabled_competencies_keep_cores_page(): void {
        [$owner, $planid] = $this->owner_with_plan();
        $this->setUser($owner);

        $this->assertNotNull($this->decide($planid, (int) $owner->id));
        set_config('enabled', 0, 'core_competency');
        $this->assertNull($this->decide($planid, (int) $owner->id));
    }

    /**
     * A POST to either script is never redirected.
     *
     * @return void
     */
    public function test_a_post_is_not_redirected(): void {
        [$owner, $planid] = $this->owner_with_plan();
        $this->setUser($owner);

        $this->assertNotNull($this->decide($planid, (int) $owner->id, ['method' => 'get']));
        $this->assertNull($this->decide($planid, (int) $owner->id, ['method' => 'POST']));
        $this->assertNull($this->decide($planid, (int) $owner->id, [
            'script' => ownerredirect::COMPETENCY_SCRIPT,
            'method' => 'POST',
        ]));
    }

    /**
     * Any other script is left alone, even one of tool_lp's that names the owner's plan.
     *
     * @return void
     */
    public function test_another_script_is_left_alone(): void {
        [$owner, $planid] = $this->owner_with_plan();
        $this->setUser($owner);

        $this->assertNotNull($this->decide($planid, (int) $owner->id));
        $this->assertNull($this->decide($planid, (int) $owner->id, ['script' => '/admin/tool/lp/plans.php']));
        $this->assertNull($this->decide($planid, (int) $owner->id, ['script' => '/admin/tool/lp/editplan.php']));
        $this->assertNull($this->decide($planid, (int) $owner->id, ['script' => '']));
    }

    /**
     * Through the request: the competency page's userid decides nothing, the plan row does.
     *
     * A request naming another learner as userid over the viewer's own plan is redirected (the row says it is
     * the viewer's); a request naming the viewer as userid over another learner's plan is not.
     *
     * @return void
     */
    public function test_the_requests_userid_decides_nothing(): void {
        [$owner, $planid, $competencyid] = $this->owner_with_plan();
        $other = $this->getDataGenerator()->create_user();
        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $otherplanid = (int) $lpg->create_plan(['userid' => $other->id])->get('id');
        $this->setUser($owner);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];

        $_GET = ['userid' => (string) $other->id, 'competencyid' => (string) $competencyid, 'planid' => (string) $planid];
        $target = ownerredirect::for_request(ownerredirect::COMPETENCY_SCRIPT);
        $this->assertNotNull($target);
        $this->assertSame(['id' => (string) $planid, 'competencyid' => (string) $competencyid], $target->params());

        $_GET = ['userid' => (string) $owner->id, 'competencyid' => (string) $competencyid, 'planid' => (string) $otherplanid];
        $this->assertNull(ownerredirect::for_request(ownerredirect::COMPETENCY_SCRIPT));
    }

    /**
     * Through the request: each script reads its own plan parameter, noredirect and the method.
     *
     * @return void
     */
    public function test_the_request_is_read_as_each_script_spells_it(): void {
        [$owner, $planid] = $this->owner_with_plan();
        $this->setUser($owner);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];

        $_GET = ['id' => (string) $planid];
        $this->assertNotNull(ownerredirect::for_request(ownerredirect::PLAN_SCRIPT));
        // The plan page's parameter is id, the competency page's planid: neither reads the other's.
        $this->assertNull(ownerredirect::for_request(ownerredirect::COMPETENCY_SCRIPT));

        $_GET = ['id' => (string) $planid, ownerredirect::PARAM_STAY => '1'];
        $this->assertNull(ownerredirect::for_request(ownerredirect::PLAN_SCRIPT));

        $_GET = ['id' => (string) $planid];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertNull(ownerredirect::for_request(ownerredirect::PLAN_SCRIPT));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        set_config(ownerredirect::SETTING, yesno::NO, 'theme_dimensions');
        $this->assertNull(ownerredirect::for_request(ownerredirect::PLAN_SCRIPT));
    }

    /**
     * Through the request: the guest account and a visitor are not redirected.
     *
     * @return void
     */
    public function test_the_session_decides_who_is_logged_in(): void {
        [$owner, $planid] = $this->owner_with_plan();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET = ['id' => (string) $planid];

        $this->setUser($owner);
        $this->assertNotNull(ownerredirect::for_request(ownerredirect::PLAN_SCRIPT));
        $this->setGuestUser();
        $this->assertNull(ownerredirect::for_request(ownerredirect::PLAN_SCRIPT));
        $this->setUser(null);
        $this->assertNull(ownerredirect::for_request(ownerredirect::PLAN_SCRIPT));
    }
}
