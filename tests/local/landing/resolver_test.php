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
 * Theme Dimensions - Tests for the enrolment eligibility resolver
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the enrolment eligibility resolver.
 *
 * The apply cases are skipped on sites without enrol_apply installed (the
 * theme has no dependency on it; the adapter is duck-typed), so this suite
 * stays green on the theme's own CI, which does not install enrol_apply.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(resolver::class)]
#[CoversClass(resolution::class)]
#[CoversClass(method::class)]
final class resolver_test extends \advanced_testcase {
    /**
     * Forget what the plugin remembered about a viewer: its memos are keyed by user and course id, which a reset database reuses.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        \local_unlistedcourses\access::reset_caches();
    }

    /**
     * Enable an enrol plugin site-wide.
     *
     * @param string $plugin The enrol plugin name.
     * @return void
     */
    private function enable_enrol(string $plugin): void {
        \core\plugininfo\enrol::enable_plugin($plugin, true);
    }

    /**
     * Enable a course's existing instance of one enrol method and return it fresh.
     *
     * @param int $courseid The course id.
     * @param string $enrol The enrol plugin name.
     * @return \stdClass The updated {enrol} record.
     */
    private function enabled_instance(int $courseid, string $enrol): \stdClass {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => $enrol], '*', MUST_EXIST);
        enrol_get_plugin($enrol)->update_status($instance, ENROL_INSTANCE_ENABLED);
        return $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);
    }

    /**
     * Skip the test when enrol_apply is not installed on this site.
     *
     * @return void
     */
    private function require_enrol_apply(): void {
        if (!\core_component::get_component_directory('enrol_apply')) {
            $this->markTestSkipped('enrol_apply is not installed on this site.');
        }
    }

    /**
     * Add an enabled enrol_apply instance to a course.
     *
     * @param \stdClass $course The course record.
     * @param array $fields Extra instance fields.
     * @return \stdClass The {enrol} record.
     */
    private function add_apply_instance(\stdClass $course, array $fields = []): \stdClass {
        global $DB;
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $plugin = enrol_get_plugin('apply');
        $id = $plugin->add_instance($course, $fields + [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
            'roleid' => $studentrole->id,
        ]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * A visitor gets the visitor state and is offered guest access only.
     */
    public function test_visitor_state_offers_only_guest_access(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $this->enable_enrol('guest');
        $course = self::getDataGenerator()->create_course();
        $this->enabled_instance($course->id, 'self');
        $this->enabled_instance($course->id, 'guest');

        $this->setUser(null);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_VISITOR, $resolution->state);
        $this->assertCount(1, $resolution->methods);
        $this->assertSame(method::TYPE_GUEST, $resolution->methods[0]->type);
        $this->assertFalse($resolution->methods[0]->requirespassword);

        // The Moodle guest account is a visitor too.
        $this->setGuestUser();
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_VISITOR, $resolution->state);
        $this->assertCount(1, $resolution->methods);
    }

    /**
     * A visitor has a login way in only where a login could lead to an enrolment.
     *
     * Guest access needs no login and manual enrolment offers nothing to press, so a course with only
     * those has none, for a visitor and for the guest account alike. An open self instance is the
     * control, and it counts with nothing to say about seats or a deadline - the offers list leaves such
     * an instance out, the login does not. A closed window and new enrolments switched off take it away
     * again, through the same predicate the offers read.
     */
    public function test_a_visitor_has_a_login_way_in_only_where_a_login_leads_somewhere(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $this->enable_enrol('guest');
        $course = self::getDataGenerator()->create_course();
        $this->enabled_instance($course->id, 'guest');
        $this->assertTrue($DB->record_exists('enrol', [
            'courseid' => $course->id,
            'enrol' => 'manual',
            'status' => ENROL_INSTANCE_ENABLED,
        ]), 'Precondition: the manual instance is enabled.');

        $this->setUser(null);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_VISITOR, $resolution->state);
        $this->assertFalse($resolution->loginwayin, 'Guest access and manual enrolment are no way in through a login.');
        $this->assertSame(method::TYPE_GUEST, $resolution->methods[0]->type);
        $this->setGuestUser();
        $this->assertFalse(resolver::resolve($course)->loginwayin, 'The guest account is a visitor too.');

        $self = $this->enabled_instance($course->id, 'self');
        $this->setUser(null);
        $resolution = resolver::resolve($course);
        $this->assertTrue($resolution->loginwayin, 'Control: an open self instance is a way in.');
        $this->assertSame([], $resolution->offers, 'Precondition: it declares neither seats nor a deadline.');

        $DB->set_field('enrol', 'enrolenddate', time() - DAYSECS, ['id' => $self->id]);
        $this->assertFalse(resolver::resolve($course)->loginwayin, 'Its window has closed.');

        $DB->set_field('enrol', 'enrolenddate', 0, ['id' => $self->id]);
        $DB->set_field('enrol', 'customint6', 0, ['id' => $self->id]);
        $this->assertFalse(resolver::resolve($course)->loginwayin, 'It takes no new enrolments.');
    }

    /**
     * An enabled enrol_coursecompleted instance is a login way in: after the login it names its prerequisite.
     *
     * It is carried by the fallback rule (the plugin overrides enrol_page_hook()), so this runs only where the
     * plugin is installed.
     */
    public function test_a_course_completed_instance_is_a_login_way_in(): void {
        global $DB;
        $this->resetAfterTest();
        if (!\core_component::get_component_directory('enrol_coursecompleted')) {
            $this->markTestSkipped('enrol_coursecompleted is not installed on this site.');
        }
        $this->enable_enrol('coursecompleted');
        $generator = self::getDataGenerator();
        $prerequisite = $generator->create_course();
        $course = $generator->create_course();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        $this->setUser(null);
        $this->assertFalse(resolver::resolve($course)->loginwayin, 'Control: manual enrolment alone.');

        $DB->insert_record('enrol', (object) [
            'enrol' => 'coursecompleted',
            'courseid' => (int) $course->id,
            'status' => ENROL_INSTANCE_ENABLED,
            'sortorder' => 9,
            'roleid' => (int) $studentrole->id,
            'customint1' => (int) $prerequisite->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $this->assertTrue(resolver::resolve($course)->loginwayin);
    }

    /**
     * An actively enrolled user is sent to the course, whatever methods exist.
     */
    public function test_enrolled_user_gets_the_enrolled_state(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $course = self::getDataGenerator()->create_course();
        $this->enabled_instance($course->id, 'self');
        $user = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($user->id, $course->id);

        $this->setUser($user);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_ENROLLED, $resolution->state);
        $this->assertSame([], $resolution->methods);
    }

    /**
     * A suspended enrolment is not "enrolled": the user is back to the method list.
     */
    public function test_suspended_enrolment_is_not_enrolled(): void {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $user = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($user->id, $course->id, null, 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $this->setUser($user);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $resolution->state);
    }

    /**
     * An eligible self enrolment is offered, and carries the enrolment-key flag.
     */
    public function test_eligible_self_enrolment_is_open(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $course = self::getDataGenerator()->create_course();
        $instance = $this->enabled_instance($course->id, 'self');
        $this->setUser(self::getDataGenerator()->create_user());

        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_OPEN, $resolution->state);
        $this->assertCount(1, $resolution->methods);
        $this->assertSame(method::TYPE_SELF, $resolution->methods[0]->type);
        $this->assertFalse($resolution->methods[0]->requirespassword);

        $DB->set_field('enrol', 'password', 'chave', ['id' => $instance->id]);
        $resolution = resolver::resolve($course);
        $this->assertTrue($resolution->methods[0]->requirespassword);
    }

    /**
     * An ineligible self enrolment is hidden entirely, never shown as a teaser.
     */
    public function test_ineligible_self_enrolment_is_hidden(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $course = self::getDataGenerator()->create_course();
        $instance = $this->enabled_instance($course->id, 'self');
        $this->setUser(self::getDataGenerator()->create_user());

        // Restricted to a cohort the user is not a member of.
        $cohort = self::getDataGenerator()->create_cohort();
        $DB->set_field('enrol', 'customint5', $cohort->id, ['id' => $instance->id]);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $resolution->state);
        $this->assertSame([], $resolution->methods);

        // Enrolment window already over.
        $DB->set_field('enrol', 'customint5', 0, ['id' => $instance->id]);
        $DB->set_field('enrol', 'enrolenddate', time() - DAYSECS, ['id' => $instance->id]);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $resolution->state);

        // Control: with the window restored, the method is back.
        $DB->set_field('enrol', 'enrolenddate', 0, ['id' => $instance->id]);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_OPEN, $resolution->state);
    }

    /**
     * A usable fee method carries the resolved cost and currency.
     */
    public function test_fee_method_carries_cost_and_currency(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_enrol('fee');
        $course = self::getDataGenerator()->create_course();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        enrol_get_plugin('fee')->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $studentrole->id,
            'cost' => '180.50',
            'currency' => 'BRL',
        ]);
        $this->setUser(self::getDataGenerator()->create_user());

        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_OPEN, $resolution->state);
        $this->assertCount(1, $resolution->methods);
        $this->assertSame(method::TYPE_FEE, $resolution->methods[0]->type);
        $this->assertSame(180.50, $resolution->methods[0]->cost);
        $this->assertSame('BRL', $resolution->methods[0]->currency);
    }

    /**
     * The fee method falls back to the plugin's default cost, and hides on the fee hook's own checks.
     */
    public function test_fee_hidden_cases_mirror_the_hook(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_enrol('fee');
        $course = self::getDataGenerator()->create_course();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $plugin = enrol_get_plugin('fee');
        $id = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $studentrole->id,
            'cost' => 0,
            'currency' => 'BRL',
        ]);
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        // No instance cost and no plugin default: misconfigured, hidden.
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $resolution->state);

        // The plugin-level default cost fills in.
        $plugin->set_config('cost', '99.00');
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_OPEN, $resolution->state);
        $this->assertSame(99.00, $resolution->methods[0]->cost);

        // Enrolment window not open yet.
        $DB->set_field('enrol', 'enrolstartdate', time() + DAYSECS, ['id' => $id]);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $resolution->state);
        $DB->set_field('enrol', 'enrolstartdate', 0, ['id' => $id]);

        // An existing enrolment row on this instance (whatever its status) hides it.
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $plugin->enrol_user($instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $resolution->state);
    }

    /**
     * Methods with no user-facing enrolment UI never surface, and the hook detector tells them apart.
     */
    public function test_plugins_without_a_hook_are_skipped(): void {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $this->setUser(self::getDataGenerator()->create_user());

        // A fresh course has manual enabled and self/guest disabled: nothing usable.
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $resolution->state);
        $this->assertSame([], $resolution->methods);

        $this->assertFalse(resolver::overrides_enrol_page_hook(enrol_get_plugin('manual')));
        $this->assertFalse(resolver::overrides_enrol_page_hook(enrol_get_plugin('cohort')));
        $this->assertTrue(resolver::overrides_enrol_page_hook(enrol_get_plugin('self')));
        $this->assertTrue(resolver::overrides_enrol_page_hook(enrol_get_plugin('guest')));
        $this->assertTrue(resolver::overrides_enrol_page_hook(enrol_get_plugin('fee')));
    }

    /**
     * Usable methods arrive in instance sortorder, guest access included whenever its instance is enabled.
     */
    public function test_methods_follow_instance_sortorder(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $this->enable_enrol('fee');
        $this->enable_enrol('guest');
        $course = self::getDataGenerator()->create_course();
        $this->enabled_instance($course->id, 'self');
        $this->enabled_instance($course->id, 'guest');
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        enrol_get_plugin('fee')->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $studentrole->id,
            'cost' => '50.00',
            'currency' => 'BRL',
        ]);
        $this->setUser(self::getDataGenerator()->create_user());

        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_OPEN, $resolution->state);

        $types = array_map(static fn($method) => $method->type, $resolution->methods);
        $this->assertSame([method::TYPE_GUEST, method::TYPE_SELF, method::TYPE_FEE], $types);

        $sortorders = array_map(static fn($method) => (int) $method->instance->sortorder, $resolution->methods);
        $sorted = $sortorders;
        sort($sorted);
        $this->assertSame($sorted, $sortorders);
    }

    /**
     * An eligible enrol_apply instance is offered; its own gates hide it entirely.
     */
    public function test_apply_eligibility(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_enrol_apply();
        $this->enable_enrol('apply');
        $course = self::getDataGenerator()->create_course();
        $instance = $this->add_apply_instance($course);
        $this->setUser(self::getDataGenerator()->create_user());

        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_OPEN, $resolution->state);
        $this->assertCount(1, $resolution->methods);
        $this->assertSame(method::TYPE_APPLY, $resolution->methods[0]->type);

        // Cohort restriction: not a member, hidden.
        $cohort = self::getDataGenerator()->create_cohort();
        $DB->set_field('enrol', 'customint5', $cohort->id, ['id' => $instance->id]);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $resolution->state);

        // The fail-closed sentinel a cross-site restore writes.
        $DB->set_field('enrol', 'customint5', -1, ['id' => $instance->id]);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $resolution->state);
        $DB->set_field('enrol', 'customint5', 0, ['id' => $instance->id]);

        // Applicant cap already reached by someone else's application.
        $DB->set_field('enrol', 'customint3', 1, ['id' => $instance->id]);
        $other = self::getDataGenerator()->create_user();
        $instance = $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);
        enrol_get_plugin('apply')->enrol_user($instance, $other->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $resolution->state);
    }

    /**
     * Enrolled from a later date: the state is scheduled, and an open self instance beside it is listed as a method.
     *
     * The viewer is told when the course opens to them,
     * and offered every method that would really take them now - the route the card's line points at. A course with
     * no other method is the control: scheduled, and nothing listed.
     */
    public function test_a_scheduled_enrolment_is_scheduled_and_lists_the_methods_beside_it(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $course = self::getDataGenerator()->create_course();
        $this->enabled_instance($course->id, 'self');
        $bare = self::getDataGenerator()->create_course();
        $start = time() + 4 * DAYSECS;
        $user = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual', $start, 0);
        self::getDataGenerator()->enrol_user($user->id, $bare->id, 'student', 'manual', $start, 0);

        $this->setUser($user);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_SCHEDULED, $resolution->state);
        $this->assertSame($start, $resolution->startsat);
        $this->assertSame(0, $resolution->endsat);
        $this->assertSame(\local_unlistedcourses\access::RELATIONSHIP_SCHEDULED, $resolution->relationship);
        $types = array_map(static fn($method) => $method->type, $resolution->methods);
        $this->assertSame([method::TYPE_SELF], $types, 'The route the card prints is on the page it opens.');

        $plain = resolver::resolve($bare);
        $this->assertSame(resolution::STATE_SCHEDULED, $plain->state, 'Control: scheduled with nothing beside it.');
        $this->assertSame([], $plain->methods);

        // Control: somebody with no row is offered the open self instance on the same course.
        $this->setUser(self::getDataGenerator()->create_user());
        $control = resolver::resolve($course);
        $this->assertSame(resolution::STATE_OPEN, $control->state);
        $this->assertNotEmpty($control->methods);
    }

    /**
     * A suspended or ended relationship rides beside the methods, never instead of them.
     *
     * The suspended viewer is offered the open self instance on the same course (core's default), and
     * told about the suspension; the ended one, on a course with no other method, is closed and told when the
     * enrolment ended. A viewer with no row is the control: the same open state, no relationship.
     */
    public function test_a_suspended_or_ended_relationship_rides_beside_the_methods(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $this->enabled_instance($course->id, 'self');
        $closed = $generator->create_course();
        $suspended = $generator->create_user();
        $generator->enrol_user($suspended->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $ended = $generator->create_user();
        $end = time() - 3 * DAYSECS;
        $generator->enrol_user($ended->id, $closed->id, 'student', 'manual', $end - 10 * DAYSECS, $end);

        $this->setUser($suspended);
        \local_unlistedcourses\access::reset_caches();
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_OPEN, $resolution->state);
        $this->assertSame(\local_unlistedcourses\access::RELATIONSHIP_SUSPENDED, $resolution->relationship);
        $this->assertSame([method::TYPE_SELF], array_map(static fn($method) => $method->type, $resolution->methods));

        $this->setUser($ended);
        \local_unlistedcourses\access::reset_caches();
        $resolution = resolver::resolve($closed);
        $this->assertSame(resolution::STATE_CLOSED, $resolution->state);
        $this->assertSame(\local_unlistedcourses\access::RELATIONSHIP_EXPIRED, $resolution->relationship);
        $this->assertSame($end, $resolution->endsat);

        $this->setUser($generator->create_user());
        \local_unlistedcourses\access::reset_caches();
        $control = resolver::resolve($course);
        $this->assertSame(resolution::STATE_OPEN, $control->state, 'Control: the same course, open.');
        $this->assertSame(\local_unlistedcourses\access::RELATIONSHIP_NONE, $control->relationship);
        $this->assertNull($control->endsat);
    }

    /**
     * A waiting-list application is pending, and its relationship says it is on the waiting list.
     */
    public function test_a_waiting_list_application_is_pending_on_the_waiting_list(): void {
        $this->resetAfterTest();
        $this->require_enrol_apply();
        $this->enable_enrol('apply');
        $course = self::getDataGenerator()->create_course();
        $instance = $this->add_apply_instance($course);
        $waiting = self::getDataGenerator()->create_user();
        $applicant = self::getDataGenerator()->create_user();
        enrol_get_plugin('apply')->enrol_user($instance, $waiting->id, null, 0, 0, 2);
        enrol_get_plugin('apply')->enrol_user($instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);

        $this->setUser($waiting);
        \local_unlistedcourses\access::reset_caches();
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_PENDING, $resolution->state);
        $this->assertSame(\local_unlistedcourses\access::RELATIONSHIP_WAITLISTED, $resolution->relationship);
        $this->assertGreaterThan(0, $resolution->appliedtime);

        $this->setUser($applicant);
        \local_unlistedcourses\access::reset_caches();
        $control = resolver::resolve($course);
        $this->assertSame(resolution::STATE_PENDING, $control->state);
        $this->assertSame(\local_unlistedcourses\access::RELATIONSHIP_PENDING, $control->relationship, 'Control.');
    }

    /**
     * enrol_coursecompleted is never a method: its prerequisite is named only to a viewer working towards it.
     *
     * Runs only where the plugin is installed, because the resolver asks local_unlistedcourses' per-course rule,
     * which asks the plugin. The stranger is the control: closed, and told no prerequisite.
     */
    public function test_a_course_completed_instance_names_its_prerequisite_to_its_learner_only(): void {
        global $DB;

        $this->resetAfterTest();
        if (!\core_component::get_component_directory('enrol_coursecompleted')) {
            $this->markTestSkipped('enrol_coursecompleted is not installed on this site.');
        }
        $this->enable_enrol('coursecompleted');
        $generator = self::getDataGenerator();
        $prerequisite = $generator->create_course();
        $course = $generator->create_course();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $DB->insert_record('enrol', (object) [
            'enrol' => 'coursecompleted',
            'courseid' => (int) $course->id,
            'status' => ENROL_INSTANCE_ENABLED,
            'sortorder' => 9,
            'roleid' => (int) $studentrole->id,
            'customint1' => (int) $prerequisite->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $learner = $generator->create_user();
        $generator->enrol_user($learner->id, $prerequisite->id);

        $this->setUser($learner);
        \local_unlistedcourses\access::reset_caches();
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $resolution->state, 'Nothing to press: no fallback widget.');
        $this->assertSame([], $resolution->methods);
        $this->assertSame((int) $prerequisite->id, $resolution->prerequisiteid);

        $this->setUser($generator->create_user());
        \local_unlistedcourses\access::reset_caches();
        $control = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $control->state);
        $this->assertNull($control->prerequisiteid, 'Control: nobody else hears of the prerequisite.');
    }

    /**
     * An existing application turns the state pending, while other methods stay listed.
     */
    public function test_apply_pending_state(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_enrol_apply();
        $this->enable_enrol('apply');
        $this->enable_enrol('fee');
        $course = self::getDataGenerator()->create_course();
        $instance = $this->add_apply_instance($course);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        enrol_get_plugin('fee')->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $studentrole->id,
            'cost' => '50.00',
            'currency' => 'BRL',
        ]);
        $user = self::getDataGenerator()->create_user();
        enrol_get_plugin('apply')->enrol_user($instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);

        $this->setUser($user);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_PENDING, $resolution->state);
        $this->assertGreaterThan(0, $resolution->appliedtime);

        // The apply instance itself is out; the fee alternative is still offered.
        $types = array_map(static fn($method) => $method->type, $resolution->methods);
        $this->assertNotContains(method::TYPE_APPLY, $types);
        $this->assertContains(method::TYPE_FEE, $types);
    }

    /**
     * An approved application whose period has run out is no pending application.
     *
     * With enrol_apply's keep-on-expiry action the approved row stays ENROL_USER_ACTIVE and only its
     * end date is past, so it must neither read as an application awaiting approval nor offer the
     * form again. The suspended row beside it is the control: the same resolver, the same course,
     * and that one IS pending - without it "no pending state" would hold for a resolver that never
     * reported one.
     */
    public function test_an_approved_expired_application_is_not_pending(): void {
        $this->resetAfterTest();
        $this->require_enrol_apply();
        $this->enable_enrol('apply');
        $course = self::getDataGenerator()->create_course();
        $instance = $this->add_apply_instance($course);
        $plugin = enrol_get_plugin('apply');
        $approved = self::getDataGenerator()->create_user();
        $waiting = self::getDataGenerator()->create_user();
        $plugin->enrol_user($instance, $approved->id, null, time() - 10 * DAYSECS, time() - DAYSECS, ENROL_USER_ACTIVE);
        $plugin->enrol_user($instance, $waiting->id, null, 0, 0, ENROL_USER_SUSPENDED);

        $this->setUser($approved);
        \local_unlistedcourses\access::reset_caches();
        $resolution = resolver::resolve($course);
        $this->assertNotSame(resolution::STATE_PENDING, $resolution->state);
        $this->assertNull($resolution->appliedtime);
        $types = array_map(static fn($method) => $method->type, $resolution->methods);
        $this->assertNotContains(method::TYPE_APPLY, $types, 'The approved applicant is not offered the form again.');

        $this->setUser($waiting);
        \local_unlistedcourses\access::reset_caches();
        $control = resolver::resolve($course);
        $this->assertSame(resolution::STATE_PENDING, $control->state, 'A suspended row is an application awaiting approval.');
        $this->assertGreaterThan(0, $control->appliedtime);
    }

    /**
     * A row that is not active but whose period ran out is no application awaiting a decision.
     *
     * This is what core's expiry sweep leaves of an approved application under enrol_apply's suspend action: the row
     * is re-suspended with its past end date, and enrol_apply's own queue does not list it. The controls are two
     * rows of the same instance that are not active either: one with no end date and one whose end date is ahead,
     * both pending, so the lapsed one is told apart by its end date alone.
     */
    public function test_a_lapsed_application_is_not_pending(): void {
        $this->resetAfterTest();
        $this->require_enrol_apply();
        $this->enable_enrol('apply');
        $course = self::getDataGenerator()->create_course();
        $instance = $this->add_apply_instance($course);
        $plugin = enrol_get_plugin('apply');
        $lapsed = self::getDataGenerator()->create_user();
        $waiting = self::getDataGenerator()->create_user();
        $dated = self::getDataGenerator()->create_user();
        $plugin->enrol_user($instance, $lapsed->id, null, time() - 10 * DAYSECS, time() - 5 * DAYSECS, ENROL_USER_SUSPENDED);
        $plugin->enrol_user($instance, $waiting->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $plugin->enrol_user($instance, $dated->id, null, 0, time() + 5 * DAYSECS, ENROL_USER_SUSPENDED);

        $this->setUser($lapsed);
        \local_unlistedcourses\access::reset_caches();
        $resolution = resolver::resolve($course);
        $this->assertNotSame(resolution::STATE_PENDING, $resolution->state);
        $this->assertNull($resolution->appliedtime);
        $types = array_map(static fn($method) => $method->type, $resolution->methods);
        $this->assertNotContains(method::TYPE_APPLY, $types, 'The row is still there: the form is not offered again.');

        foreach (['no end date' => $waiting, 'an end date ahead' => $dated] as $label => $user) {
            $this->setUser($user);
            \local_unlistedcourses\access::reset_caches();
            $control = resolver::resolve($course);
            $this->assertSame(resolution::STATE_PENDING, $control->state, "Control: a row with {$label} is awaiting a decision.");
            $this->assertGreaterThan(0, $control->appliedtime);
        }
    }

    /**
     * An application stays pending when enrol_apply or its instance is switched off, and nothing is offered for it.
     *
     * enrol_apply's queue reads neither the instance's status nor the plugin's enablement, so the applicant is told the
     * application waits. The approved row on the same disabled instance is active, and so no application at all:
     * the one shape that tells the status clause apart, because an active row on an enabled instance is enrolled
     * before the question is asked.
     */
    public function test_an_application_stays_pending_while_apply_is_switched_off(): void {
        $this->resetAfterTest();
        $this->require_enrol_apply();
        $this->enable_enrol('apply');
        $course = self::getDataGenerator()->create_course();
        $instance = $this->add_apply_instance($course);
        $plugin = enrol_get_plugin('apply');
        $applicant = self::getDataGenerator()->create_user();
        $approved = self::getDataGenerator()->create_user();
        $plugin->enrol_user($instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $plugin->enrol_user($instance, $approved->id, null, 0, 0, ENROL_USER_ACTIVE);

        \core\plugininfo\enrol::enable_plugin('apply', false);
        $this->assertArrayNotHasKey('apply', enrol_get_plugins(true), 'Precondition: enrol_apply is off site-wide.');
        $this->setUser($applicant);
        \local_unlistedcourses\access::reset_caches();
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_PENDING, $resolution->state, 'The plugin is off; the application still waits.');
        $this->assertGreaterThan(0, $resolution->appliedtime);
        $this->assertSame([], $resolution->methods, 'Nobody can decide it, so nothing is offered.');

        $this->enable_enrol('apply');
        $plugin->update_status($instance, ENROL_INSTANCE_DISABLED);
        $this->assertArrayHasKey('apply', enrol_get_plugins(true), 'Precondition: enrol_apply is back on.');
        $this->assertArrayNotHasKey($instance->id, enrol_get_instances($course->id, true), 'Precondition: the instance is off.');
        \local_unlistedcourses\access::reset_caches();
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_PENDING, $resolution->state, 'The instance is off; the application still waits.');
        $this->assertSame([], $resolution->methods);

        $this->setUser($approved);
        \local_unlistedcourses\access::reset_caches();
        $this->assertFalse(is_enrolled(\core\context\course::instance($course->id), $approved, '', true), 'Precondition.');
        $control = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $control->state, 'An active row is no application, whatever its instance.');
        $this->assertNull($control->appliedtime);
    }

    /**
     * enrol_paypal is listed, through its own hook, only where that hook would take a payment.
     *
     * The hook's three checks are enrol_fee's: no row of the user on the instance, the window, and a cost on the
     * instance or by default. A paypal instance with no cost would draw only core's error, and is hidden as a fee
     * instance is. The listed method is a fallback, so the page still renders the plugin's own button.
     */
    public function test_paypal_is_listed_only_where_its_hook_would_take_a_payment(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_enrol('paypal');
        $course = self::getDataGenerator()->create_course();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $plugin = enrol_get_plugin('paypal');
        $id = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $studentrole->id,
            'cost' => '25.00',
            'currency' => 'BRL',
        ]);
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_OPEN, $resolution->state);
        $this->assertCount(1, $resolution->methods);
        $this->assertSame(method::TYPE_FALLBACK, $resolution->methods[0]->type);
        $this->assertSame('paypal', $resolution->methods[0]->instance->enrol);

        // No cost on the instance and none by default: the hook would draw an error, not a button.
        $DB->set_field('enrol', 'cost', 0, ['id' => $id]);
        $plugin->set_config('cost', 0);
        $this->assertSame(resolution::STATE_CLOSED, resolver::resolve($course)->state);

        // The plugin's own default cost fills in.
        $plugin->set_config('cost', '30.00');
        $this->assertSame(resolution::STATE_OPEN, resolver::resolve($course)->state);

        // The enrolment window is not open yet.
        $DB->set_field('enrol', 'enrolstartdate', time() + DAYSECS, ['id' => $id]);
        $this->assertSame(resolution::STATE_CLOSED, resolver::resolve($course)->state);
        $DB->set_field('enrol', 'enrolstartdate', 0, ['id' => $id]);

        // A row of the user on the instance, whatever its status.
        $plugin->enrol_user($DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST), $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
        \local_unlistedcourses\access::reset_caches();
        $this->assertSame(resolution::STATE_CLOSED, resolver::resolve($course)->state);
    }

    /**
     * enrol_autoenrol is listed, through its own hook, only while the plugin's own check lets the user in.
     *
     * The check is enrol_allowed(), which the hook asks first; when it says no the hook draws nothing. Resolving
     * enrols nobody: the hook is the one that enrols, and the resolver never calls it.
     */
    public function test_autoenrol_is_listed_only_while_its_own_check_passes(): void {
        global $DB;
        $this->resetAfterTest();
        if (!\core_component::get_component_directory('enrol_autoenrol')) {
            $this->markTestSkipped('enrol_autoenrol is not installed on this site.');
        }
        $this->enable_enrol('autoenrol');
        $course = self::getDataGenerator()->create_course();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $id = enrol_get_plugin('autoenrol')->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $studentrole->id,
            'customint4' => 1,
        ]);
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_OPEN, $resolution->state);
        $this->assertCount(1, $resolution->methods);
        $this->assertSame(method::TYPE_FALLBACK, $resolution->methods[0]->type);
        $this->assertSame('autoenrol', $resolution->methods[0]->instance->enrol);
        $this->assertFalse(is_enrolled(\core\context\course::instance($course->id), $user), 'Resolving enrols nobody.');

        // New enrolments off: the plugin refuses, and its hook would draw nothing.
        $DB->set_field('enrol', 'customint4', 0, ['id' => $id]);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_CLOSED, $resolution->state);
        $this->assertSame([], $resolution->methods);
    }
    /**
     * A visitor is told the seats and deadline of every method open to anybody, cohort-restricted ones included.
     *
     * The controls are one method of each kind the visitor must not be told about: one whose cap is
     * reached, one whose window has closed, one that takes no new enrolments, and one with nothing
     * to say.
     */
    public function test_visitor_offers_are_the_methods_open_to_anybody(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $course = self::getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('self');
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $cohort = self::getDataGenerator()->create_cohort();
        $add = function (string $name, array $fields) use ($plugin, $course, $studentrole): int {
            return (int) $plugin->add_instance($course, $fields + [
                'name' => $name,
                'status' => ENROL_INSTANCE_ENABLED,
                'customint6' => 1,
                'roleid' => $studentrole->id,
            ]);
        };
        $open = $add('Open', ['customint3' => 40, 'enrolenddate' => time() + WEEKSECS]);
        $cohortonly = $add('Cohort only', ['customint3' => 40, 'customint5' => $cohort->id]);
        $full = $add('Full', ['customint3' => 1]);
        $add('Closed', ['customint3' => 40, 'enrolenddate' => time() - DAYSECS]);
        $add('No new enrolments', ['customint3' => 40, 'customint6' => 0]);
        $add('Silent', []);
        $student = self::getDataGenerator()->create_user();
        $plugin->enrol_user($DB->get_record('enrol', ['id' => $full]), $student->id, $studentrole->id);

        $this->setUser(null);
        $resolution = resolver::resolve($course);
        $this->assertSame(resolution::STATE_VISITOR, $resolution->state);
        $this->assertSame([$open, $cohortonly], array_map(fn($instance) => (int) $instance->id, $resolution->offers));

        // A logged-in user is told through the methods, never through the visitor's list.
        $this->setUser($student);
        $this->assertSame([], resolver::resolve($course)->offers);
    }
}
