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
 * Theme Dimensions - Tests for the evidence page's service of the platform's records
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_competency\evidence;
use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\competency\evidencelist;

/**
 * Tests for theme_dimensions_get_user_evidence_feed, called as core/ajax calls it.
 *
 * The door repeats core's rule for a user competency's evidence ({@see \core_competency\api::list_evidence()}): a real
 * account, competencies enabled, and a viewer who may read the user's competencies. Each refusal sits beside a control
 * that is answered with a row, so a refusal that passes for the wrong reason cannot hold the test green.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_user_evidence_feed::class)]
#[CoversClass(evidencelist::class)]
final class get_user_evidence_feed_test extends \advanced_testcase {
    /** @var string The function's name. */
    private const NAME = 'theme_dimensions_get_user_evidence_feed';

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
     * @param int $userid The user whose records are asked for.
     * @param array $args The other arguments.
     * @return array call_external_function()'s answer.
     */
    private function ask(int $userid, array $args = []): array {
        $_POST['sesskey'] = sesskey();

        return external_api::call_external_function(self::NAME, ['userid' => $userid] + $args, true);
    }

    /**
     * A learner with one rated record carrying a note, a link row and a folded row of another component.
     *
     * @return array [the learner, the rated record's id].
     */
    private function learner_with_records(): array {
        global $DB;
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $learner = $generator->create_user();
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Kitchen']);
        $uc = $lpg->create_user_competency(['userid' => $learner->id, 'competencyid' => $competency->get('id')]);
        $rated = $lpg->create_evidence([
            'usercompetencyid' => $uc->get('id'),
            'action' => evidence::ACTION_OVERRIDE,
            'grade' => 1,
            'descidentifier' => 'evidence_manualoverride',
            'note' => 'Solid & tidy',
            'actionuserid' => get_admin()->id,
        ]);
        $lpg->create_evidence([
            'usercompetencyid' => $uc->get('id'),
            'descidentifier' => evidencelist::LINKED,
            'desca' => json_encode('Certificate'),
        ]);
        $DB->insert_record(evidence::TABLE, (object) [
            'usercompetencyid' => $uc->get('id'),
            'contextid' => \context_system::instance()->id,
            'action' => evidence::ACTION_LOG,
            'descidentifier' => 'evidence_status_valid',
            'desccomponent' => 'local_evidencehub',
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 2,
        ]);

        return [$learner, (int) $rated->get('id')];
    }

    /**
     * The owner gets the rated record, the link row left out, the folded line, the total and the facets.
     *
     * @return void
     */
    public function test_the_owner_gets_the_first_page(): void {
        $this->resetAfterTest();
        [$learner, $rated] = $this->learner_with_records();
        $this->setUser($learner);

        $result = $this->ask((int) $learner->id);

        $this->assertFalse($result['error'], json_encode($result['exception'] ?? null));
        $data = $result['data'];
        $this->assertSame([$rated], array_map('intval', array_column($data['records'], 'id')));
        $this->assertSame(1, $data['total']);
        $this->assertFalse($data['hasmore']);
        $this->assertCount(1, $data['folds']);
        $this->assertSame(1, $data['folds'][0]['count']);
        $this->assertSame('rating', $data['records'][0]['kind']);
        $this->assertSame('Solid & tidy', $data['records'][0]['note'], 'The note is plain text, not escaped.');
        $this->assertSame(['Kitchen'], array_column($data['facets']['competencies'], 'name'));
    }

    /**
     * A stranger is refused with core's exception, before anything is built; the owner is the control.
     *
     * @return void
     */
    public function test_a_stranger_is_refused_with_cores_exception(): void {
        $this->resetAfterTest();
        [$learner] = $this->learner_with_records();

        $this->setUser($learner);
        $this->assertCount(1, $this->ask((int) $learner->id)['data']['records'], 'Control: the owner is answered.');

        $this->setUser(self::getDataGenerator()->create_user());
        $result = $this->ask((int) $learner->id);

        $this->assertTrue($result['error']);
        $this->assertSame('nopermissions', $result['exception']->errorcode);
        $this->assertArrayNotHasKey('data', $result);
    }

    /**
     * The access rule comes before the period is read: a stranger asking with a malformed day is refused for who they
     * are, as a well-formed request is; the owner's malformed day is the control that the day is checked at all.
     *
     * @return void
     */
    public function test_the_rule_comes_before_the_arguments_are_read(): void {
        $this->resetAfterTest();
        [$learner] = $this->learner_with_records();

        $this->setUser($learner);
        $control = $this->ask((int) $learner->id, ['datefrom' => 'someday']);
        $this->assertSame('invalidparameter', $control['exception']->errorcode, 'Control: the owner\'s bad day is refused.');

        $this->setUser(self::getDataGenerator()->create_user());
        $result = $this->ask((int) $learner->id, ['datefrom' => 'someday']);
        $this->assertTrue($result['error']);
        $this->assertSame('nopermissions', $result['exception']->errorcode);
    }

    /**
     * A manager who may read the user's competencies is answered; one who may only read the prior-learning evidence is
     * refused, although the evidence page itself opens for them.
     *
     * @return void
     */
    public function test_the_rule_is_the_user_competencies_not_the_prior_learning(): void {
        $this->resetAfterTest();
        [$learner] = $this->learner_with_records();
        $generator = self::getDataGenerator();
        $usercontext = \context_user::instance($learner->id);

        $make = function (string $capability) use ($generator, $usercontext): \stdClass {
            $viewer = $generator->create_user();
            $roleid = $generator->create_role();
            assign_capability($capability, CAP_ALLOW, $roleid, \context_system::instance()->id, true);
            role_assign($roleid, $viewer->id, $usercontext->id);
            accesslib_clear_all_caches_for_unit_testing();
            return $viewer;
        };

        $this->setUser($make('moodle/competency:usercompetencyview'));
        $this->assertFalse($this->ask((int) $learner->id)['error'], 'Control: the user competencies capability.');

        $this->setUser($make('moodle/competency:userevidenceview'));
        $result = $this->ask((int) $learner->id);
        $this->assertTrue($result['error']);
        $this->assertSame('nopermissions', $result['exception']->errorcode);
    }

    /**
     * With competencies disabled the door refuses with core's error; enabled, the same call is answered.
     *
     * @return void
     */
    public function test_disabled_competencies_are_refused(): void {
        $this->resetAfterTest();
        [$learner] = $this->learner_with_records();
        $this->setUser($learner);

        set_config('enabled', 1, 'core_competency');
        $this->assertFalse($this->ask((int) $learner->id)['error'], 'Control: enabled, the owner is answered.');

        set_config('enabled', 0, 'core_competency');
        $result = $this->ask((int) $learner->id);
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
        [$learner] = $this->learner_with_records();

        $this->setUser($learner);
        $this->assertFalse($this->ask((int) $learner->id)['error'], 'Control: a real account is answered.');

        $this->setGuestUser();
        $result = $this->ask((int) $learner->id);
        $this->assertTrue($result['error']);
        $this->assertSame('noguest', $result['exception']->errorcode);
    }

    /**
     * A malformed cursor or day is refused; the next page from a real cursor is answered without facets or total.
     *
     * @return void
     */
    public function test_the_cursor_and_the_period_are_validated(): void {
        $this->resetAfterTest();
        [$learner, $rated] = $this->learner_with_records();
        $this->setUser($learner);
        $time = (int) evidence::get_record(['id' => $rated])->get('timecreated');

        $next = $this->ask((int) $learner->id, ['beforetime' => $time, 'beforeid' => $rated + 1]);
        $this->assertFalse($next['error'], 'Control: a real cursor is answered.');
        $this->assertSame([$rated], array_map('intval', array_column($next['data']['records'], 'id')));
        $this->assertSame(-1, $next['data']['total']);
        $this->assertSame([], $next['data']['facets']['competencies'], 'Facets come with the first page only.');

        foreach ([['beforetime' => $time], ['beforeid' => $rated], ['datefrom' => '2026-13-01'], ['dateto' => 'soon']] as $args) {
            $result = $this->ask((int) $learner->id, $args);
            $this->assertTrue($result['error'], json_encode($args));
            $this->assertSame('invalidparameter', $result['exception']->errorcode);
        }
    }

    /**
     * The function is declared for core/ajax, read-only, with a session, and answers an allowlist with no file, no
     * raw description and no context.
     *
     * @return void
     */
    public function test_the_declaration(): void {
        $info = external_api::external_function_info(self::NAME);

        $this->assertTrue((bool) $info->allowed_from_ajax);
        $this->assertTrue((bool) $info->loginrequired);
        $this->assertTrue((bool) $info->readonlysession);
        $this->assertSame('read', $info->type);

        $returns = get_user_evidence_feed::execute_returns();
        $top = ['total', 'hasmore', 'nexttime', 'nextid', 'periodfrom', 'periodto', 'records', 'folds', 'facets'];
        $record = ['id', 'time', 'month', 'monthlabel', 'date', 'kind', 'title', 'competencyid', 'competency',
            'competencyurl', 'gradename', 'actor', 'note', 'url'];
        $fold = ['key', 'component', 'label', 'month', 'monthlabel', 'count', 'time'];
        $this->assertSame($top, array_keys($returns->keys));
        $this->assertSame($record, array_keys($returns->keys['records']->content->keys));
        $this->assertSame($fold, array_keys($returns->keys['folds']->content->keys));
    }
}
