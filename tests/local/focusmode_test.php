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
 * Theme Dimensions - Tests for the focus mode decision
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use core_course\customfield\course_handler;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the focus mode decision.
 *
 * Every clause of the predicate is tested by a page which fails that clause alone,
 * beside a companion which passes it: a clause tested only by its "no" case stays
 * green after the whole predicate is deleted.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(focusmode::class)]
final class focusmode_test extends \advanced_testcase {
    /**
     * Start every test with empty request caches and the master switch on.
     *
     * The switch ships off, so a test about any later clause turns it on first;
     * the tests about the switch itself set it back.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        focusmode::reset_caches();
        set_config('focusmode', yesno::YES, focusmode::COMPONENT);
    }

    /**
     * Undo what a test may have set outside the database.
     *
     * @return void
     */
    protected function tearDown(): void {
        focusmode::reset_caches();
        \core_useragent::instance(true);
        unset($_GET[focusmode::PARAM_OFF]);
        parent::tearDown();
    }

    /**
     * A course whose settings form has the focus mode box ticked.
     *
     * @param array $options Extra options for the course generator.
     * @return \stdClass The course record.
     */
    private function switched_on_course(array $options = []): \stdClass {
        $course = $this->getDataGenerator()->create_course($options);
        $this->set_course_switch($course, true);
        return $course;
    }

    /**
     * A course whose settings form has the focus mode box unticked.
     *
     * @param array $options Extra options for the course generator.
     * @return \stdClass The course record.
     */
    private function switched_off_course(array $options = []): \stdClass {
        $course = $this->getDataGenerator()->create_course($options);
        $this->set_course_switch($course, false);
        return $course;
    }

    /**
     * Save the focus mode answer of one course through the course custom field handler.
     *
     * Saved as the administrator, because instance_form_save() silently drops the fields
     * the current user may not edit.
     *
     * @param \stdClass $course The course.
     * @param bool $on The answer to store.
     * @return void
     */
    private function set_course_switch(\stdClass $course, bool $on): void {
        $this->setAdminUser();
        course_handler::reset_caches();
        focusmode::reset_caches();
        focusmodefields::ensure_fields();
        focusmodefields::reset_field_cache();

        course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . focusmodefields::SHORTNAME => $on ? 1 : 0,
        ]);

        focusmode::reset_caches();
    }

    /**
     * An activity of one course (mod_page by default), as the course's modinfo knows it.
     *
     * @param \stdClass $course The course.
     * @param string $modname The activity module to create.
     * @return \cm_info The course module.
     */
    private function activity(\stdClass $course, string $modname = 'page'): \cm_info {
        $module = $this->getDataGenerator()->create_module($modname, ['course' => $course->id]);
        return get_fast_modinfo($course)->get_cm($module->cmid);
    }

    /**
     * A page in the state an activity view builds: incourse, module context, cm set.
     *
     * @param \stdClass $course The course.
     * @param \cm_info $cm The activity.
     * @param string $script The script the page is served by, which decides the page type.
     * @return \moodle_page The page.
     */
    private function activity_page(\stdClass $course, \cm_info $cm, string $script = '/mod/page/view.php'): \moodle_page {
        $page = new \moodle_page();
        $page->set_course($course);
        $page->set_cm($cm, $course);
        $page->set_url($script, ['id' => $cm->id]);
        $page->set_pagelayout('incourse');
        return $page;
    }

    /**
     * The baseline: an activity of a course which switched focus mode on.
     *
     * Every other test in this file perturbs exactly one thing about this page, so this
     * one is what makes the rest of them non-vacuous.
     *
     * @return void
     */
    public function test_it_applies_on_an_activity_of_a_switched_on_course(): void {
        $this->resetAfterTest();
        $course = $this->switched_on_course();
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $page = $this->activity_page($course, $cm);

        $this->assertTrue(focusmode::applies($page));
        $this->assertSame('applies', focusmode::why($page));
        $this->assertTrue(focusmode::available($page));
    }

    /**
     * Clause 1: the master switch off leaves every page alone.
     *
     * @return void
     */
    public function test_the_master_switch_off_stops_it(): void {
        $this->resetAfterTest();
        $course = $this->switched_on_course();
        $page = $this->activity_page($course, $this->activity($course));

        set_config('focusmode', yesno::NO, focusmode::COMPONENT);

        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('master', focusmode::why($page));
        $this->assertFalse(focusmode::available($page));
    }

    /**
     * A site which never wrote the master switch gets the documented default, which is off.
     *
     * The companion of the test above, and the one which pins the reading: the switch ships
     * off, so a fresh install must agree with its own settings page, and only an
     * explicit Yes turns the feature on. The control is that Yes.
     *
     * @return void
     */
    public function test_the_master_switch_is_off_when_it_was_never_written(): void {
        $course = $this->switched_on_course();
        $page = $this->activity_page($course, $this->activity($course));
        $this->assertTrue(focusmode::applies($page), 'Control: an explicit Yes.');

        unset_config('focusmode', focusmode::COMPONENT);

        $this->assertFalse(get_config(focusmode::COMPONENT, 'focusmode'));
        $this->assertFalse(focusmode::master_enabled());
        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('master', focusmode::why($page));
    }

    /**
     * Clause 2: the support parameter turns focus mode off for one page load.
     *
     * @return void
     */
    public function test_the_focusoff_parameter_stops_it(): void {
        $this->resetAfterTest();
        $course = $this->switched_on_course();
        $page = $this->activity_page($course, $this->activity($course));
        $this->assertTrue(focusmode::applies($page));

        $_GET[focusmode::PARAM_OFF] = '1';

        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('focusoff', focusmode::why($page));
    }

    /**
     * Clause 3: any layout but incourse is left alone.
     *
     * @return void
     */
    public function test_another_pagelayout_stops_it(): void {
        $this->resetAfterTest();
        $course = $this->switched_on_course();
        $page = $this->activity_page($course, $this->activity($course));

        $page->set_pagelayout('standard');

        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('pagelayout', focusmode::why($page));
    }

    /**
     * The activity settings form keeps the whole theme, and the layout is what says so.
     *
     * course/modedit.php calls require_login() with the course module - so the module
     * context and the cm are both set there - and then forces the layout back to 'admin'.
     * A cm-based or page-type-prefix predicate would fire on that page.
     *
     * @return void
     */
    public function test_the_activity_settings_form_is_not_focus_mode(): void {
        $this->resetAfterTest();
        $course = $this->switched_on_course();
        $cm = $this->activity($course);
        $page = $this->activity_page($course, $cm, '/course/modedit.php');

        $page->set_pagelayout('admin');

        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('pagelayout', focusmode::why($page));
        $this->assertFalse(focusmode::available($page));
    }

    /**
     * Clause 4: an incourse page which is not in a module context is left alone.
     *
     * @return void
     */
    public function test_a_context_which_is_not_a_module_stops_it(): void {
        $this->resetAfterTest();
        $course = $this->switched_on_course();
        $page = $this->activity_page($course, $this->activity($course));

        $page->set_context(\core\context\course::instance($course->id));
        /* Core warns when a page's context level changes after the fact; the state is what
           this test is about, and the warning is core's own, not a finding about the theme. */
        $this->assertDebuggingCalled();

        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('context', focusmode::why($page));
    }

    /**
     * Clause 5: a module context with no cm on the page is left alone.
     *
     * @return void
     */
    public function test_a_page_without_a_cm_stops_it(): void {
        $this->resetAfterTest();
        $course = $this->switched_on_course();
        $cm = $this->activity($course);

        $page = new \moodle_page();
        $page->set_course($course);
        $page->set_context(\core\context\module::instance($cm->id));
        $page->set_url('/mod/page/view.php', ['id' => $cm->id]);
        $page->set_pagelayout('incourse');

        $this->assertNull($page->cm);
        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('cm', focusmode::why($page));
    }

    /**
     * Clause 6: an activity of the site course is left alone.
     *
     * The default answer is switched on for the whole site first, so that the site course
     * really would be focused if this clause were not there - otherwise the test would
     * pass on the course clause refusing a few lines later, and prove nothing.
     *
     * @return void
     */
    public function test_it_does_not_apply_on_the_site_course(): void {
        global $SITE;
        $this->resetAfterTest();

        focusmodefields::ensure_fields();
        focusmodefields::reset_field_cache();
        focusmodefields::set_default(true);
        focusmode::reset_caches();

        // The companion: an ordinary course in exactly this state is focused.
        $ordinary = $this->getDataGenerator()->create_course();
        $this->assertTrue(focusmode::applies($this->activity_page($ordinary, $this->activity($ordinary))));

        $cm = $this->activity($SITE);
        $page = $this->activity_page($SITE, $cm);

        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('siteid', focusmode::why($page));
        $this->assertFalse(focusmode::available($page));
    }

    /**
     * Clause 7: a teacher with editing on gets the whole theme back.
     *
     * @return void
     */
    public function test_an_editing_teacher_stops_it(): void {
        global $USER;
        $this->resetAfterTest();
        $course = $this->switched_on_course();
        $page = $this->activity_page($course, $this->activity($course));

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        // The companion: the same teacher, editing off, is focused.
        $this->assertTrue(focusmode::applies($page));

        $USER->editing = 1;

        $this->assertTrue($page->user_is_editing());
        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('editing', focusmode::why($page));
        $this->assertFalse(focusmode::available($page));
    }

    /**
     * Clause 8: the Moodle app draws its own header, so focus mode stays out of it.
     *
     * @return void
     */
    public function test_the_moodle_app_stops_it(): void {
        $this->resetAfterTest();
        $course = $this->switched_on_course();
        $page = $this->activity_page($course, $this->activity($course));
        $this->assertTrue(focusmode::applies($page));

        \core_useragent::instance(true, 'Mozilla/5.0 (Linux; Android 10) MoodleMobile 4.5.0');

        $this->assertTrue(\core_useragent::is_moodle_app());
        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('app', focusmode::why($page));
        $this->assertFalse(focusmode::available($page));
    }

    /**
     * Clause 9: a page type on the excluded list always opens with the ordinary theme.
     *
     * @return void
     */
    public function test_an_excluded_pagetype_stops_it(): void {
        $this->resetAfterTest();
        $course = $this->switched_on_course();
        $page = $this->activity_page($course, $this->activity($course, 'quiz'), '/mod/quiz/view.php');

        // The companion: the quiz view page itself is not on the list.
        $this->assertSame('mod-quiz-view', $page->pagetype);
        $this->assertTrue(focusmode::applies($page));

        $page->set_pagetype('mod-quiz-attempt');

        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('pagetype', focusmode::why($page));
    }

    /**
     * The assign grading table is excluded by listing its page type, not by guessing.
     *
     * @return void
     */
    public function test_the_grading_table_is_excluded(): void {
        $this->resetAfterTest();
        set_config('focusmodeexcludedpagetypes', "mod-assign-grading\n", focusmode::COMPONENT);
        $course = $this->switched_on_course();
        $page = $this->activity_page($course, $this->activity($course, 'assign'), '/mod/assign/view.php');

        $page->set_pagetype('mod-assign-grading');

        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('pagetype', focusmode::why($page));
    }

    /**
     * The plain assign view is not excluded by the entry above.
     *
     * @return void
     */
    public function test_the_plain_assign_view_is_not(): void {
        $this->resetAfterTest();
        set_config('focusmodeexcludedpagetypes', "mod-assign-grading\n", focusmode::COMPONENT);
        $course = $this->switched_on_course();
        $page = $this->activity_page($course, $this->activity($course, 'assign'), '/mod/assign/view.php');

        $this->assertSame('mod-assign-view', $page->pagetype);
        $this->assertTrue(focusmode::applies($page));
    }

    /**
     * Clause 10: a course which did not switch focus mode on is left alone.
     *
     * @return void
     */
    public function test_a_course_which_did_not_switch_it_on_stops_it(): void {
        $this->resetAfterTest();
        $course = $this->switched_off_course();
        $page = $this->activity_page($course, $this->activity($course));

        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('course', focusmode::why($page));
    }

    /**
     * A course with no stored answer follows the field's default for new courses.
     *
     * @return void
     */
    public function test_a_course_with_no_answer_follows_the_default(): void {
        $this->resetAfterTest();
        focusmodefields::ensure_fields();
        focusmodefields::reset_field_cache();
        $course = $this->getDataGenerator()->create_course();

        focusmode::reset_caches();
        $this->assertFalse(focusmode::enabled_for_course((int) $course->id));

        focusmodefields::set_default(true);
        focusmode::reset_caches();

        $this->assertTrue(focusmode::enabled_for_course((int) $course->id));
    }

    /**
     * why() names the first clause which refused, not the last one which would have.
     *
     * @return void
     */
    public function test_why_names_the_clause(): void {
        global $USER;
        $this->resetAfterTest();
        $course = $this->switched_off_course();
        $page = $this->activity_page($course, $this->activity($course));

        // The course clause alone refuses.
        $this->assertSame('course', focusmode::why($page));

        // With editing on, the earlier clause is the one named.
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $USER->editing = 1;
        $this->assertSame('editing', focusmode::why($page));

        // And the master switch is named before either of them.
        set_config('focusmode', yesno::NO, focusmode::COMPONENT);
        $this->assertSame('master', focusmode::why($page));
    }

    /**
     * The per course answer is read from the database once per course per request, and cached afterwards.
     *
     * @return void
     */
    public function test_the_course_answer_is_cached_per_request(): void {
        global $DB;
        $this->resetAfterTest();
        $first = $this->switched_on_course();
        $second = $this->switched_off_course();
        focusmode::reset_caches();

        $before = $DB->perf_get_reads();
        $this->assertTrue(focusmode::enabled_for_course((int) $first->id));
        $this->assertGreaterThan($before, $DB->perf_get_reads(), 'The first answer of a request is read from the database.');

        $before = $DB->perf_get_reads();
        $this->assertTrue(focusmode::enabled_for_course((int) $first->id));
        $this->assertSame($before, $DB->perf_get_reads(), 'The second answer for the same course is cached.');

        /* The control: the cache is keyed by course, so another course is another read.
           A single memoised answer for the whole request would pass the two assertions
           above and hand every course the first one's answer. */
        $before = $DB->perf_get_reads();
        $this->assertFalse(focusmode::enabled_for_course((int) $second->id));
        $this->assertGreaterThan($before, $DB->perf_get_reads());
    }

    /**
     * An activity of a course which did not switch focus mode on can switch in place, and opens ordinary.
     *
     * @return void
     */
    public function test_an_unswitched_course_is_available_and_opens_ordinary(): void {
        $course = $this->switched_off_course();
        $page = $this->activity_page($course, $this->activity($course));

        $this->assertTrue(focusmode::available($page));
        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('course', focusmode::why($page));
    }

    /**
     * A course which switched focus mode on opens focused, and can be left in place for the session.
     *
     * Leaving lasts the rest of the session in that course. The page stays available,
     * which is what puts the way back in on it, and entering again wins over the exit.
     *
     * @return void
     */
    public function test_leaving_a_switched_on_course_lasts_the_session(): void {
        $course = $this->switched_on_course();
        $page = $this->activity_page($course, $this->activity($course));
        $this->assertTrue(focusmode::applies($page));

        focusmode::choose((int) $course->id, false);

        $this->assertTrue(focusmode::opted_out((int) $course->id));
        $this->assertFalse(focusmode::applies($page));
        $this->assertSame('optout', focusmode::why($page));
        $this->assertTrue(focusmode::available($page));
        $this->assertFalse(focusmode::chosen((int) $course->id));

        focusmode::choose((int) $course->id, true);

        $this->assertFalse(focusmode::opted_out((int) $course->id));
        $this->assertTrue(focusmode::applies($page));
        $this->assertTrue(focusmode::chosen((int) $course->id));
    }

    /**
     * Leaving one switched-on course leaves another switched-on course focused.
     *
     * @return void
     */
    public function test_leaving_is_held_per_course(): void {
        $first = $this->switched_on_course();
        $second = $this->switched_on_course();
        $secondpage = $this->activity_page($second, $this->activity($second));

        focusmode::choose((int) $first->id, false);

        $this->assertTrue(focusmode::opted_out((int) $first->id));
        $this->assertFalse(focusmode::opted_out((int) $second->id));
        $this->assertTrue(focusmode::applies($secondpage));
    }

    /**
     * The two flags are exclusive: the last choice is the one the predicate reads.
     *
     * @return void
     */
    public function test_a_choice_replaces_the_previous_one(): void {
        $course = $this->switched_off_course();

        focusmode::choose((int) $course->id, true);
        $this->assertTrue(focusmode::in_session((int) $course->id));
        $this->assertFalse(focusmode::opted_out((int) $course->id));
        $this->assertTrue(focusmode::chosen((int) $course->id));

        focusmode::choose((int) $course->id, false);
        $this->assertFalse(focusmode::in_session((int) $course->id));
        $this->assertTrue(focusmode::opted_out((int) $course->id));
        $this->assertFalse(focusmode::chosen((int) $course->id));
    }

    /**
     * Entering a session focuses the activities of that course, and of no other.
     *
     * An implementation keeping one boolean for the whole session passes every other
     * test in this file and follows a learner into every other course they open.
     *
     * @return void
     */
    public function test_a_session_is_held_per_course(): void {
        $this->resetAfterTest();
        $first = $this->switched_off_course();
        $second = $this->switched_off_course();
        $firstpage = $this->activity_page($first, $this->activity($first));
        $secondpage = $this->activity_page($second, $this->activity($second));

        focusmode::choose((int) $first->id, true);

        $this->assertTrue(focusmode::in_session((int) $first->id));
        $this->assertTrue(focusmode::applies($firstpage));
        $this->assertFalse(focusmode::in_session((int) $second->id));
        $this->assertFalse(focusmode::applies($secondpage));
        $this->assertSame('course', focusmode::why($secondpage));
    }

    /**
     * Leaving the session gives the ordinary page back, with the way in on it.
     *
     * @return void
     */
    public function test_leaving_a_session_ends_it(): void {
        $this->resetAfterTest();
        $course = $this->switched_off_course();
        $page = $this->activity_page($course, $this->activity($course));
        focusmode::choose((int) $course->id, true);
        $this->assertTrue(focusmode::applies($page));

        focusmode::choose((int) $course->id, false);

        $this->assertFalse(focusmode::in_session((int) $course->id));
        $this->assertFalse(focusmode::applies($page));
        $this->assertTrue(focusmode::available($page));
    }

    /**
     * Leaving one course's session leaves the other courses' sessions alone.
     *
     * @return void
     */
    public function test_leaving_one_session_keeps_the_others(): void {
        $this->resetAfterTest();

        focusmode::choose(11, true);
        focusmode::choose(12, true);
        focusmode::choose(11, false);

        $this->assertFalse(focusmode::in_session(11));
        $this->assertTrue(focusmode::in_session(12));
    }

    /**
     * The close control returns to the activity's own place, as each format defines it.
     *
     * The single activity format is {@see test_a_single_activity_course_leaves_for_the_site_home_page()}.
     *
     * @return void
     */
    public function test_close_url_per_format(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $topics = $this->getDataGenerator()->create_course(['format' => 'topics']);
        $cm = $this->activity($topics);
        $url = focusmode::close_url($cm);
        $this->assertStringContainsString('/course/view.php', $url->out(false));
        $this->assertStringEndsWith('#module-' . $cm->id, $url->out(false));

        $weeks = $this->getDataGenerator()->create_course(['format' => 'weeks']);
        $cm = $this->activity($weeks);
        $url = focusmode::close_url($cm);
        $this->assertStringContainsString('/course/view.php', $url->out(false));
        $this->assertStringEndsWith('#module-' . $cm->id, $url->out(false));

        $social = $this->getDataGenerator()->create_course(['format' => 'social']);
        $cm = $this->activity($social);
        $url = focusmode::close_url($cm);
        $this->assertStringContainsString('/course/view.php', $url->out(false));
        $this->assertStringContainsString('id=' . $social->id, $url->out(false));
    }

    /**
     * A single activity course's way out is the site home page, kept there, for everybody.
     *
     * The course page would bounce the reader straight back into the activity, so the exit leaves
     * the course. redirect=0 keeps a logged-in reader whose home page is the Dashboard on the site
     * home; a visitor or the guest account under forcelogin is sent on to the login page by core.
     * A topics course is the control: its exit stays in the course.
     *
     * @return void
     */
    public function test_a_single_activity_course_leaves_for_the_site_home_page(): void {
        $single = $this->getDataGenerator()->create_course([
            'format' => 'singleactivity',
            'activitytype' => 'page',
        ]);
        $cm = $this->activity($single);
        $topics = $this->getDataGenerator()->create_course(['format' => 'topics']);
        $topicscm = $this->activity($topics);
        $home = (new \moodle_url('/', ['redirect' => 0]))->out(false);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame($home, focusmode::close_url($cm)->out(false));
        $this->assertStringContainsString('/course/view.php', focusmode::close_url($topicscm)->out(false), 'Control.');

        $this->setGuestUser();
        $this->assertSame($home, focusmode::close_url($cm)->out(false));

        $this->setUser(null);
        $this->assertSame($home, focusmode::close_url($cm)->out(false));
    }

    /**
     * Only a single activity course that switched focus mode on leaves the course to leave focus mode.
     *
     * A single activity course that did not switch it on toggles in place like any
     * other, and so does every other format that did.
     *
     * @return void
     */
    public function test_only_a_forced_single_activity_course_leaves_the_course(): void {
        $single = $this->switched_on_course(['format' => 'singleactivity', 'activitytype' => 'page']);
        $this->assertTrue(focusmode::leaves_course($this->activity($single)));

        $plain = $this->switched_off_course(['format' => 'singleactivity', 'activitytype' => 'page']);
        $this->assertFalse(focusmode::leaves_course($this->activity($plain)), 'Not forced: in place.');

        $topics = $this->switched_on_course(['format' => 'topics']);
        $this->assertFalse(focusmode::leaves_course($this->activity($topics)), 'Another format: in place.');
    }

    /**
     * The excluded page types fall back to the shipped list while the setting is unwritten.
     *
     * @return void
     */
    public function test_the_excluded_pagetypes_fall_back_to_the_shipped_list(): void {
        $this->resetAfterTest();
        unset_config('focusmodeexcludedpagetypes', focusmode::COMPONENT);

        $this->assertSame(
            ['mod-quiz-attempt', 'mod-quiz-summary', 'mod-quiz-review'],
            focusmode::excluded_pagetypes()
        );
    }

    /**
     * The setting is read one page type per line, trimmed, lower-cased, blanks dropped.
     *
     * @return void
     */
    public function test_the_excluded_pagetypes_are_normalised(): void {
        $this->resetAfterTest();
        set_config('focusmodeexcludedpagetypes', "  MOD-Book-View \n\n mod-data-edit\n\n", focusmode::COMPONENT);

        $this->assertSame(['mod-book-view', 'mod-data-edit'], focusmode::excluded_pagetypes());
    }

    /**
     * An administrator who empties the textarea gets no exclusions, not the shipped list.
     *
     * @return void
     */
    public function test_an_emptied_setting_excludes_nothing(): void {
        $this->resetAfterTest();
        set_config('focusmodeexcludedpagetypes', '', focusmode::COMPONENT);

        $this->assertSame([], focusmode::excluded_pagetypes());
    }

    /**
     * The width choice can be stored for a real user, and never for a guest or a visitor.
     *
     * @return void
     */
    public function test_only_a_real_user_can_persist_the_width(): void {
        $this->resetAfterTest();

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue(focusmode::can_persist_width());

        $this->setGuestUser();
        $this->assertFalse(focusmode::can_persist_width());

        $this->setUser(null);
        $this->assertFalse(focusmode::can_persist_width());
    }

    /**
     * The width follows the administrator's default until the person has chosen.
     *
     * @return void
     */
    public function test_the_width_follows_the_default_until_it_is_chosen(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        set_config('focusmodedefaultwidth', focusmode::WIDTH_CONTRACTED, focusmode::COMPONENT);
        $this->assertFalse(focusmode::wants_expanded());

        set_config('focusmodedefaultwidth', focusmode::WIDTH_EXPANDED, focusmode::COMPONENT);
        $this->assertTrue(focusmode::wants_expanded());

        // A stored choice wins over the default, in both directions.
        set_user_preference(focusmode::PREFERENCE_EXPANDED, 0, $user);
        $this->assertFalse(focusmode::wants_expanded());

        set_config('focusmodedefaultwidth', focusmode::WIDTH_CONTRACTED, focusmode::COMPONENT);
        set_user_preference(focusmode::PREFERENCE_EXPANDED, 1, $user);
        $this->assertTrue(focusmode::wants_expanded());
    }

    /**
     * A guest, whose choice cannot be stored, gets the administrator's default.
     *
     * @return void
     */
    public function test_a_guest_gets_the_default_width(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        set_config('focusmodedefaultwidth', focusmode::WIDTH_EXPANDED, focusmode::COMPONENT);
        $this->assertTrue(focusmode::wants_expanded());

        set_config('focusmodedefaultwidth', focusmode::WIDTH_CONTRACTED, focusmode::COMPONENT);
        $this->assertFalse(focusmode::wants_expanded());
    }

    /**
     * The focus mode stylesheet is compiled into the theme only while the master switch is on.
     *
     * Off, nothing of the feature is loaded. Read from the main SCSS callback itself,
     * which the theme's stylesheet is compiled from; the token is one only focusmode.scss declares.
     * The control is the switch on.
     *
     * @return void
     */
    public function test_the_stylesheet_is_compiled_only_while_the_switch_is_on(): void {
        global $CFG;
        require_once($CFG->dirroot . '/theme/dimensions/lib.php');
        $theme = \core\output\theme_config::load('dimensions');
        $token = '$dimensions-focusbar-height';

        $this->assertStringContainsString($token, theme_dimensions_get_main_scss_content($theme), 'Control: on.');

        set_config('focusmode', yesno::NO, focusmode::COMPONENT);
        $this->assertStringNotContainsString($token, theme_dimensions_get_main_scss_content($theme));
    }
}
