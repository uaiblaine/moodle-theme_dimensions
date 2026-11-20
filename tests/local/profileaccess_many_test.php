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
 * Theme Dimensions - Tests for the batch form of the profile access rule
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use context_course;
use context_system;
use context_user;
use core_user;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

/**
 * Tests for profileaccess::control_view_profile_many().
 *
 * The batch is held against the per-user callback, never against a list written by hand alone: the
 * fixture holds every branch of the decision, each with the answer it gives, and the two answers
 * are compared for every viewer and every course. The explicit expectations beside the comparison
 * are what keep a change that broke both sides the same way from passing.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(profileaccess::class)]
final class profileaccess_many_test extends \advanced_testcase {
    /**
     * Reset the request memo, since one request runs every test.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        profileaccess::reset_caches();
    }

    /**
     * The batch answers what the callback answers, for every subject, viewer and course, in both directions.
     *
     * @return void
     */
    public function test_the_batch_agrees_with_the_callback(): void {
        $f = $this->fixture();

        // The learner, with no course, in the course where one subject teaches, and in the shared course.
        $this->setUser($f->viewer);
        foreach ([null, $f->contactcourse, $f->shared] as $course) {
            $this->assert_agrees($f->subjects, $course);
        }

        // Both answers occur, so the agreement above is about the rule and not about a constant.
        $this->setUser($f->viewer);
        $site = profileaccess::control_view_profile_many(array_values($f->subjects));
        $this->assertSame($this->expected($f, false), $site);
        $incourse = profileaccess::control_view_profile_many(array_values($f->subjects), $f->contactcourse);
        $this->assertSame($this->expected($f, true), $incourse);

        // Staff, the guest and nobody: the callback prevents nothing for them, and nor does the batch.
        $manager = $this->getDataGenerator()->create_user();
        role_assign($this->role_id('manager'), $manager->id, context_system::instance()->id);
        $this->setUser($manager);
        $this->assert_agrees($f->subjects, null);
        $this->setAdminUser();
        $this->assert_agrees($f->subjects, $f->contactcourse);
        $this->setGuestUser();
        $this->assert_agrees($f->subjects, null);
        $this->setUser(null);
        $this->assert_agrees($f->subjects, null);
        $this->assertNotContains(
            core_user::VIEWPROFILE_PREVENT,
            profileaccess::control_view_profile_many(array_values($f->subjects))
        );
    }

    /**
     * Every id given comes back, once, numeric strings and duplicates included.
     *
     * @return void
     */
    public function test_every_id_given_is_answered(): void {
        $f = $this->fixture();
        $this->setUser($f->viewer);

        $answers = profileaccess::control_view_profile_many([
            (string) $f->subjects['stranger'],
            $f->subjects['stranger'],
            $f->subjects['classmate'],
        ]);

        $this->assertSame([
            $f->subjects['stranger'] => core_user::VIEWPROFILE_PREVENT,
            $f->subjects['classmate'] => core_user::VIEWPROFILE_DO_NOT_PREVENT,
        ], $answers);
        $this->assertSame([], profileaccess::control_view_profile_many([]));
    }

    /**
     * An id no account has has no user context, where the callback throws; the batch fails closed.
     *
     * @return void
     */
    public function test_an_id_without_a_user_context_is_prevented(): void {
        global $DB;

        $f = $this->fixture();
        $nobody = (int) $DB->get_field_sql('SELECT MAX(id) FROM {user}') + 1000;
        $this->setUser($f->viewer);

        // Control: the callback really has no answer for it.
        try {
            profileaccess::control_view_profile((object) ['id' => $nobody]);
            $this->fail('The callback answered for an id with no user context.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $this->assertSame(
            [$nobody => core_user::VIEWPROFILE_PREVENT, $f->subjects['classmate'] => core_user::VIEWPROFILE_DO_NOT_PREVENT],
            profileaccess::control_view_profile_many([$nobody, $f->subjects['classmate']], $f->contactcourse)
        );
    }

    /**
     * The own profile stays open for somebody who shares no course with anyone, themself included.
     *
     * @return void
     */
    public function test_the_own_profile_is_open_without_any_course(): void {
        $loner = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();
        $this->setUser($loner);

        $this->assertSame([
            $loner->id => core_user::VIEWPROFILE_DO_NOT_PREVENT,
            $stranger->id => core_user::VIEWPROFILE_PREVENT,
        ], profileaccess::control_view_profile_many([$loner->id, $stranger->id]));
    }

    /**
     * A site with no course contact roles set opens nobody's profile through a course.
     *
     * @return void
     */
    public function test_no_course_contact_setting_opens_nothing(): void {
        global $CFG;

        $f = $this->fixture();
        // Control: with the setting, the teacher of the course is open there.
        $this->setUser($f->viewer);
        $this->assertSame(
            core_user::VIEWPROFILE_DO_NOT_PREVENT,
            profileaccess::control_view_profile_many([$f->subjects['contact']], $f->contactcourse)[$f->subjects['contact']]
        );

        unset($CFG->coursecontact);
        $this->assert_agrees($f->subjects, $f->contactcourse);
        $this->assertSame(
            core_user::VIEWPROFILE_PREVENT,
            profileaccess::control_view_profile_many([$f->subjects['contact']], $f->contactcourse)[$f->subjects['contact']]
        );
    }

    /**
     * The batch neither reads nor fills the per-request memo of the callback.
     *
     * @return void
     */
    public function test_the_batch_leaves_the_memo_alone(): void {
        $f = $this->fixture();
        $this->setUser($f->viewer);

        // Fill the memo with the site answer, then ask the batch the in-course question.
        $this->assertSame(
            core_user::VIEWPROFILE_PREVENT,
            profileaccess::control_view_profile((object) ['id' => $f->subjects['contact']])
        );
        $this->assertSame(
            [$f->subjects['contact'] => core_user::VIEWPROFILE_DO_NOT_PREVENT],
            profileaccess::control_view_profile_many([$f->subjects['contact']], $f->contactcourse)
        );

        $memo = new \ReflectionProperty(profileaccess::class, 'memo');
        $this->assertCount(1, $memo->getValue());
    }

    /**
     * The statements read do not grow with the number of people asked about.
     *
     * Three statements once the viewer's access data is loaded: the user contexts, the course
     * contacts and the courses with their contexts. perf_get_reads() counts a recordset as three
     * reads on PostgreSQL and one elsewhere, hence the bound of five.
     *
     * @return void
     */
    public function test_the_statements_do_not_grow_with_the_list(): void {
        global $DB;

        $f = $this->fixture();
        $few = [];
        $many = [];
        for ($i = 0; $i < 12; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $own = $this->getDataGenerator()->create_course();
            $this->getDataGenerator()->enrol_user($user->id, $own->id, 'student');
            if ($i % 2 === 0) {
                $this->getDataGenerator()->enrol_user($user->id, $f->shared->id, 'student');
            }
            if ($i < 2) {
                $few[] = (int) $user->id;
            }
            $many[] = (int) $user->id;
        }
        $this->setUser($f->viewer);

        // Warm the viewer's access data and the course context of the request.
        profileaccess::control_view_profile_many(array_values($f->subjects), $f->contactcourse);

        $before = $DB->perf_get_reads();
        $fewanswers = profileaccess::control_view_profile_many($few, $f->contactcourse);
        $forfew = $DB->perf_get_reads() - $before;

        $before = $DB->perf_get_reads();
        $manyanswers = profileaccess::control_view_profile_many($many, $f->contactcourse);
        $formany = $DB->perf_get_reads() - $before;

        // Control: the long list holds both answers, so every branch of the batch ran.
        $this->assertContains(core_user::VIEWPROFILE_PREVENT, $manyanswers);
        $this->assertContains(core_user::VIEWPROFILE_DO_NOT_PREVENT, $manyanswers);
        $this->assertCount(2, $fewanswers);
        $this->assertSame($forfew, $formany);
        $this->assertLessThanOrEqual(5, $formany);
    }

    /**
     * Compare the batch with the callback, one subject at a time, for the current user.
     *
     * @param array $subjects Label => user id.
     * @param stdClass|null $course The course asked about.
     * @return void
     */
    private function assert_agrees(array $subjects, ?stdClass $course): void {
        $batch = profileaccess::control_view_profile_many(array_values($subjects), $course);
        profileaccess::reset_caches();
        foreach ($subjects as $label => $id) {
            $this->assertSame(
                profileaccess::control_view_profile((object) ['id' => $id], $course),
                $batch[$id],
                "Subject '$label' with " . ($course === null ? 'no course' : "course {$course->shortname}")
            );
        }
        $this->assertCount(count(array_unique($subjects)), $batch);
    }

    /**
     * The answers a learner gets, by subject, with no course or in the course one subject teaches.
     *
     * @param stdClass $f The fixture.
     * @param bool $incourse Whether the question is asked in the contact's course.
     * @return array User id => answer.
     */
    private function expected(stdClass $f, bool $incourse): array {
        $open = core_user::VIEWPROFILE_DO_NOT_PREVENT;
        $shut = core_user::VIEWPROFILE_PREVENT;
        $answers = [
            'self' => $open,
            'nobody' => $open,
            'classmate' => $open,
            'suspended' => $open,
            'mentee' => $open,
            'contact' => $incourse ? $open : $shut,
            'coordinator' => $incourse ? $open : $shut,
            'elsewhere' => $shut,
            'pupil' => $shut,
            'hidden' => $shut,
            'guest' => $shut,
            'deleted' => $shut,
            'stranger' => $shut,
        ];
        $expected = [];
        foreach ($f->subjects as $label => $id) {
            $expected[$id] = $answers[$label];
        }
        return $expected;
    }

    /**
     * Build a learner and one subject per branch of the decision.
     *
     * - classmate and suspended: in the learner's course (the second suspended), open;
     * - hidden: shares only a course where learners may not see the participants, prevented;
     * - contact: teaches a course the learner is not in, open only when asked in that course;
     * - coordinator: a contact role at the taught course's category, open only in that course;
     * - elsewhere: teaches another course, prevented even in the contact's course;
     * - pupil: a learner of the taught course, which the learner is not in: prevented there too;
     * - mentee: the learner holds a role with moodle/user:viewdetails in their user context, open;
     * - guest: the guest account, enrolled in the learner's course, still prevented (core lists no
     *   course for it);
     * - deleted: was in the learner's course, deleted (unenrolled, user context kept), prevented;
     * - stranger: prevented; self and nobody (id 0): open.
     *
     * @return stdClass viewer, shared, contactcourse and subjects (label => id).
     */
    private function fixture(): stdClass {
        global $CFG;

        $gen = $this->getDataGenerator();
        $editingteacher = $this->role_id('editingteacher');
        set_config('coursecontact', $editingteacher);
        $CFG->coursecontact = $editingteacher;

        $shared = $gen->create_course(['shortname' => 'shared']);
        $closed = $gen->create_course(['shortname' => 'closed']);
        $contactcourse = $gen->create_course(['shortname' => 'taught']);
        $othercourse = $gen->create_course(['shortname' => 'other']);

        $viewer = $gen->create_user();
        $gen->enrol_user($viewer->id, $shared->id, 'student');
        $gen->enrol_user($viewer->id, $closed->id, 'student');

        $classmate = $gen->create_user();
        $gen->enrol_user($classmate->id, $shared->id, 'student');
        $suspended = $gen->create_user();
        $gen->enrol_user($suspended->id, $shared->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $hidden = $gen->create_user();
        $gen->enrol_user($hidden->id, $closed->id, 'student');
        assign_capability(
            'moodle/course:viewparticipants',
            CAP_PREVENT,
            $this->role_id('student'),
            context_course::instance($closed->id)->id,
            true
        );

        $contact = $gen->create_user();
        $gen->enrol_user($contact->id, $contactcourse->id, 'editingteacher');
        $elsewhere = $gen->create_user();
        $gen->enrol_user($elsewhere->id, $othercourse->id, 'editingteacher');
        $pupil = $gen->create_user();
        $gen->enrol_user($pupil->id, $contactcourse->id, 'student');
        $coordinator = $gen->create_user();
        role_assign($editingteacher, $coordinator->id, \context_coursecat::instance($contactcourse->category)->id);

        $mentee = $gen->create_user();
        $parent = create_role('Parent', 'dimensionsparent', '');
        set_role_contextlevels($parent, [CONTEXT_USER]);
        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $parent, context_system::instance()->id, true);
        role_assign($parent, $viewer->id, context_user::instance($mentee->id)->id);

        $guest = guest_user();
        $gen->enrol_user($guest->id, $shared->id, 'student');

        $deleted = $gen->create_user();
        $gen->enrol_user($deleted->id, $shared->id, 'student');
        delete_user($deleted);

        $stranger = $gen->create_user();
        accesslib_clear_all_caches_for_unit_testing();

        return (object) [
            'viewer' => $viewer,
            'shared' => $shared,
            'contactcourse' => $contactcourse,
            'subjects' => [
                'self' => (int) $viewer->id,
                'nobody' => 0,
                'classmate' => (int) $classmate->id,
                'suspended' => (int) $suspended->id,
                'mentee' => (int) $mentee->id,
                'contact' => (int) $contact->id,
                'elsewhere' => (int) $elsewhere->id,
                'pupil' => (int) $pupil->id,
                'coordinator' => (int) $coordinator->id,
                'hidden' => (int) $hidden->id,
                'guest' => (int) $guest->id,
                'deleted' => (int) $deleted->id,
                'stranger' => (int) $stranger->id,
            ],
        ];
    }

    /**
     * Look up a role id by shortname.
     *
     * @param string $shortname The role shortname.
     * @return int The role id.
     */
    private function role_id(string $shortname): int {
        global $DB;

        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }
}
