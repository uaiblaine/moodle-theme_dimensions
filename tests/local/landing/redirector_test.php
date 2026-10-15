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
 * Theme Dimensions - Tests for the enrolment page forwarding decision
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

use theme_dimensions\local\landingfields;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the enrolment page forwarding decision.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(redirector::class)]
final class redirector_test extends \advanced_testcase {
    /**
     * Create a course, optionally opted into a landing page model.
     *
     * @param int $model The page model select value (0 = none).
     * @return \stdClass The course record.
     */
    private function create_course_with_model(int $model): \stdClass {
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        if ($model) {
            $handler = \core_course\customfield\course_handler::create();
            $handler->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => $model,
            ]);
        }
        return $course;
    }

    /**
     * A model course forwards non-enrolled users (and visitors) to the landing page.
     */
    public function test_model_course_forwards(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_model(landingfields::MODEL_COURSE);

        $this->setUser(self::getDataGenerator()->create_user());
        $target = redirector::enrol_page_target((int) $course->id, '', false);
        $this->assertNotNull($target);
        $this->assertSame(links::page((int) $course->id)->out(false), $target->out(false));

        // A visitor forwards too: the landing page owns the login round-trip.
        $this->setUser(null);
        $this->assertNotNull(redirector::enrol_page_target((int) $course->id, '', false));
    }

    /**
     * The requests the standard page must keep processing are left alone.
     */
    public function test_requests_the_standard_page_owns_are_left_alone(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_model(landingfields::MODEL_COURSE);
        $this->setUser(self::getDataGenerator()->create_user());

        // The action=enrol request is enrol_self's own enrolment flow.
        $this->assertNull(redirector::enrol_page_target((int) $course->id, 'enrol', false));
        // The explicit bypass (the landing page's guest-with-password link).
        $this->assertNull(redirector::enrol_page_target((int) $course->id, '', true));
        // The frontpage never forwards.
        $this->assertNull(redirector::enrol_page_target(SITEID, '', false));
        // Nor does a course id that does not exist - the standard page raises its own error.
        $this->assertNull(redirector::enrol_page_target(999999, '', false));
    }

    /**
     * A course without a page model is untouched.
     */
    public function test_course_without_model_is_untouched(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_model(0);
        $this->setUser(self::getDataGenerator()->create_user());
        $this->assertNull(redirector::enrol_page_target((int) $course->id, '', false));
    }

    /**
     * Enrolled users are not forwarded: core sends them into the course.
     */
    public function test_enrolled_users_are_not_forwarded(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_model(landingfields::MODEL_COURSE);
        $user = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($user->id, $course->id);

        $this->setUser($user);
        $this->assertNull(redirector::enrol_page_target((int) $course->id, '', false));

        // A suspended enrolment is not active: back to the landing page.
        $suspended = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($suspended->id, $course->id, null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->setUser($suspended);
        $this->assertNotNull(redirector::enrol_page_target((int) $course->id, '', false));
    }

    /**
     * A course marked unlisted ghosts a viewer who cannot reach it, and only them.
     *
     * The two halves are one test on purpose: the eligible viewer is the control
     * that proves the mark was applied and the predicate consulted, rather than
     * the ghost being an artefact of the fixture.
     *
     * @return void
     */
    public function test_an_unlisted_course_ghosts_only_the_ineligible(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $this->setAdminUser();
        \local_unlistedcourses\discoverability::set_state(
            (int) $course->id,
            \local_unlistedcourses\discoverability::STATE_UNLISTED
        );

        $outsider = $generator->create_user();
        $this->setUser($outsider);
        \local_unlistedcourses\access::reset_caches();
        $this->assertTrue(
            redirector::is_ghosted((int) $course->id),
            'Somebody who cannot reach the course must be ghosted.'
        );

        $insider = $generator->create_user();
        $generator->enrol_user($insider->id, $course->id);
        $this->setUser($insider);
        \local_unlistedcourses\access::reset_caches();
        $this->assertFalse(
            redirector::is_ghosted((int) $course->id),
            'Control: an enrolled user must not be ghosted.'
        );
    }

    /**
     * Without the predicate class nobody is ghosted: the redirector fails open.
     *
     * The opposite of publicaccess::is_public(), on purpose. The control is
     * the same viewer through the real predicate, who is ghosted.
     *
     * @return void
     */
    public function test_a_missing_predicate_fails_open(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->setAdminUser();
        \local_unlistedcourses\discoverability::set_state(
            (int) $course->id,
            \local_unlistedcourses\discoverability::STATE_UNLISTED
        );
        $this->setUser($generator->create_user());
        \local_unlistedcourses\access::reset_caches();
        $this->assertTrue(redirector::is_ghosted((int) $course->id), 'Control: the real predicate ghosts an outsider.');

        $this->assertFalse(
            redirector::is_ghosted((int) $course->id, 'theme_dimensions\\local\\landing\\no_such_predicate'),
            'A missing predicate must ghost nobody.'
        );
    }

    /**
     * The guest account, like a visitor, is never ghosted, because ghosting would strand somebody eligible.
     *
     * For an account with no identity of its own the predicate can only answer "no",
     * so a ghost here would send a user who would be admitted the moment they signed
     * in to a dead end instead of to the login page.
     *
     * The course really is marked unlisted, and a logged-in outsider is ghosted off it in the
     * same run: against a listed course the predicate answers "discoverable" for everybody, so
     * the assertions below would keep passing after the exemption was deleted.
     *
     * @return void
     */
    public function test_a_visitor_is_never_ghosted(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $this->setAdminUser();
        \local_unlistedcourses\discoverability::set_state(
            (int) $course->id,
            \local_unlistedcourses\discoverability::STATE_UNLISTED
        );

        // Control: the mark took, so the ghosting this test denies really is switched on.
        $this->setUser($generator->create_user());
        \local_unlistedcourses\access::reset_caches();
        $this->assertTrue(redirector::is_ghosted((int) $course->id));

        $this->setGuestUser();
        \local_unlistedcourses\access::reset_caches();
        $this->assertFalse(redirector::is_ghosted((int) $course->id), 'A guest must not be ghosted.');

        $this->setUser(null);
        \local_unlistedcourses\access::reset_caches();
        $this->assertFalse(
            redirector::is_ghosted((int) $course->id),
            'Nor must somebody who is not logged in at all.'
        );
    }

    /**
     * Add a user to a cohort defined at a category's own context.
     *
     * @param int $categoryid The category the cohort is defined at.
     * @param int $userid The user to add.
     * @return void
     */
    private function add_category_cohort_member(int $categoryid, int $userid): void {
        global $CFG;

        require_once($CFG->dirroot . '/cohort/lib.php');
        $cohort = $this->getDataGenerator()->create_cohort([
            'contextid' => \core\context\coursecat::instance($categoryid)->id,
        ]);
        cohort_add_member($cohort->id, $userid);
    }

    /**
     * A category marked unlisted ghosts a viewer who cannot reach it, and only them.
     *
     * The cohort member and the admin are the controls that the mark was really
     * applied and the predicate really consulted, rather than the ghost being
     * an artefact of the fixture; the top-level pseudo-category (id 0) has no
     * discoverability state at all and must never be ghosted either.
     *
     * @return void
     */
    public function test_an_unlisted_category_ghosts_only_the_ineligible(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $category = $generator->create_category();
        $this->setAdminUser();
        \local_unlistedcourses\category_discoverability::set_state(
            (int) $category->id,
            \local_unlistedcourses\category_discoverability::STATE_UNLISTED
        );

        $outsider = $generator->create_user();
        $this->setUser($outsider);
        \local_unlistedcourses\category_access::reset_caches();
        $this->assertTrue(
            redirector::is_category_ghosted((int) $category->id),
            'Somebody who cannot reach the category must be ghosted.'
        );

        $member = $generator->create_user();
        $this->add_category_cohort_member((int) $category->id, (int) $member->id);
        $this->setUser($member);
        \local_unlistedcourses\category_access::reset_caches();
        $this->assertFalse(
            redirector::is_category_ghosted((int) $category->id),
            'Control: a cohort member must not be ghosted.'
        );

        $this->setAdminUser();
        \local_unlistedcourses\category_access::reset_caches();
        $this->assertFalse(
            redirector::is_category_ghosted((int) $category->id),
            'Control: an admin must not be ghosted.'
        );

        $this->assertFalse(
            redirector::is_category_ghosted(0),
            'The top-level pseudo-category has no state to hold and must never be ghosted.'
        );
    }

    /**
     * Without the predicate class nobody is ghosted for a category either: the redirector fails open.
     *
     * The control is the same viewer through the real predicate, who is ghosted -
     * exactly as {@see test_a_missing_predicate_fails_open()} does for courses.
     *
     * @return void
     */
    public function test_a_missing_predicate_fails_open_for_a_category(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $this->setAdminUser();
        \local_unlistedcourses\category_discoverability::set_state(
            (int) $category->id,
            \local_unlistedcourses\category_discoverability::STATE_UNLISTED
        );
        $this->setUser($generator->create_user());
        \local_unlistedcourses\category_access::reset_caches();
        $this->assertTrue(
            redirector::is_category_ghosted((int) $category->id),
            'Control: the real predicate ghosts an outsider.'
        );

        $this->assertFalse(
            redirector::is_category_ghosted(
                (int) $category->id,
                'theme_dimensions\\local\\landing\\no_such_predicate'
            ),
            'A missing predicate must ghost nobody.'
        );
    }

    /**
     * A visitor is never ghosted off an unlisted category either.
     *
     * Not logged in means the predicate can only answer "no", so a ghost here
     * would strand somebody who would be admitted the moment they signed in.
     *
     * The category really is marked unlisted, and a logged-in outsider is
     * ghosted off it in the same run: without that control the assertions
     * below would be made against a category nobody was ever going to be
     * ghosted off, and would keep passing after the exemption was deleted.
     *
     * @return void
     */
    public function test_a_visitor_is_never_ghosted_off_a_category(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $category = $generator->create_category();
        $this->setAdminUser();
        \local_unlistedcourses\category_discoverability::set_state(
            (int) $category->id,
            \local_unlistedcourses\category_discoverability::STATE_UNLISTED
        );

        // Control: the mark took, so the ghosting this test denies really is switched on.
        $this->setUser($generator->create_user());
        \local_unlistedcourses\category_access::reset_caches();
        $this->assertTrue(redirector::is_category_ghosted((int) $category->id));

        $this->setGuestUser();
        \local_unlistedcourses\category_access::reset_caches();
        $this->assertFalse(
            redirector::is_category_ghosted((int) $category->id),
            'A guest must not be ghosted.'
        );

        $this->setUser(null);
        \local_unlistedcourses\category_access::reset_caches();
        $this->assertFalse(
            redirector::is_category_ghosted((int) $category->id),
            'Nor must somebody who is not logged in at all.'
        );
    }

    /**
     * Put a category into a discoverability state, as the administrator, whoever is logged in.
     *
     * @param int $categoryid The category.
     * @param int $state A category_discoverability::STATE_* constant.
     * @return void
     */
    private function set_category_state(int $categoryid, int $state): void {
        $current = $GLOBALS['USER'];
        $this->setAdminUser();
        \local_unlistedcourses\category_discoverability::set_state($categoryid, $state, (int) get_admin()->id);
        $this->setUser($current);
        \local_unlistedcourses\category_discoverability::reset_caches();
        \local_unlistedcourses\category_access::reset_caches();
    }

    /**
     * A visitor who asks for the showcase of a public category is forwarded to its public page.
     *
     * The listed and the unlisted category beside it are the controls that the forward is a
     * decision about the state and not about anonymity: all three are asked in one run, by one
     * visitor, and only the public one is forwarded. A missing id and the top-level
     * pseudo-category answer null too, without raising - the parameter came from a visitor.
     *
     * @return void
     */
    public function test_a_visitor_is_forwarded_only_for_a_public_category(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $public = (int) $generator->create_category()->id;
        $listed = (int) $generator->create_category()->id;
        $unlisted = (int) $generator->create_category()->id;
        $this->set_category_state($public, \local_unlistedcourses\category_discoverability::STATE_PUBLIC);
        $this->set_category_state($unlisted, \local_unlistedcourses\category_discoverability::STATE_UNLISTED);
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000;

        $this->setUser(null);
        \local_unlistedcourses\category_discoverability::reset_caches();

        $target = redirector::category_page_target($public);
        $this->assertNotNull($target, 'Control: the public category is forwarded.');
        $this->assertSame(links::category_page($public)->out(false), $target->out(false));

        $this->assertNull(redirector::category_page_target($listed), 'A listed category keeps core\'s wall.');
        $this->assertNull(redirector::category_page_target($unlisted), 'So does an unlisted one.');
        $this->assertNull(redirector::category_page_target($missing), 'And an id that is no category\'s.');
        $this->assertNull(redirector::category_page_target(0), 'The top level has no public page.');
    }

    /**
     * The forward carries the showcase parameters of the request, so a shared address keeps them.
     *
     * @return void
     */
    public function test_the_forward_carries_the_showcase_parameters(): void {
        $this->resetAfterTest();
        $public = (int) self::getDataGenerator()->create_category()->id;
        $this->set_category_state($public, \local_unlistedcourses\category_discoverability::STATE_PUBLIC);

        $this->setUser(null);
        \local_unlistedcourses\category_discoverability::reset_caches();

        $bare = redirector::category_page_target($public);
        $this->assertNotNull($bare, 'Control: the category is forwarded at all.');
        $this->assertStringNotContainsString('q=x', $bare->out(false));

        $carried = redirector::category_page_target($public, ['q' => 'x']);
        $this->assertNotNull($carried);
        $this->assertStringContainsString('q=x', $carried->out(false));
        $this->assertSame(links::category_page($public, ['q' => 'x'])->out(false), $carried->out(false));
    }

    /**
     * Nobody who is logged in is forwarded - the guest account included: it lands on the showcase.
     *
     * The same visitor, the same category, in the same run is the control: without it these
     * assertions would pass against a category that was never public in the first place.
     *
     * @return void
     */
    public function test_nobody_logged_in_is_forwarded(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $public = (int) $generator->create_category()->id;
        $this->set_category_state($public, \local_unlistedcourses\category_discoverability::STATE_PUBLIC);

        $this->setUser(null);
        \local_unlistedcourses\category_discoverability::reset_caches();
        $this->assertNotNull(redirector::category_page_target($public), 'Control: a visitor IS forwarded.');

        $this->setUser($generator->create_user());
        $this->assertNull(redirector::category_page_target($public), 'A logged-in person keeps the showcase.');

        $this->setGuestUser();
        $this->assertNull(redirector::category_page_target($public), 'And the guest account is logged in to core.');
    }

    /**
     * With the public surface switched off nobody is forwarded, whatever a category's state says.
     *
     * @return void
     */
    public function test_nobody_is_forwarded_while_the_public_surface_is_switched_off(): void {
        $this->resetAfterTest();
        $public = (int) self::getDataGenerator()->create_category()->id;
        $this->set_category_state($public, \local_unlistedcourses\category_discoverability::STATE_PUBLIC);

        $this->setUser(null);
        \local_unlistedcourses\category_discoverability::reset_caches();
        $this->assertNotNull(redirector::category_page_target($public), 'Control: the surface is open.');

        set_config(
            \theme_dimensions\local\category\publicaccess::SETTING_ENABLED,
            '0',
            'theme_dimensions'
        );
        $this->assertNull(redirector::category_page_target($public));
    }
}
