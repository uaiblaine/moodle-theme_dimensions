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
 * Theme Dimensions - Tests for the decisions of the navbar that follows the reading
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Every clause of {@see readingbar}, each refusal beside the control that shows the same page accepted.
 *
 * The pages are built as the scripts build them (course/view.php sets its page type, require_login() the incourse
 * layout with the course module) and never rendered: what the page draws is tests/output/readingbar_render_test.php's.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(readingbar::class)]
final class readingbar_test extends \advanced_testcase {
    /**
     * Completion on for the site, and nothing of a previous test's focus mode or user agent.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        focusmode::reset_caches();
        set_config('enablecompletion', 1);
    }

    /**
     * Put the user agent and the focus mode caches back: both are process-wide.
     *
     * @return void
     */
    protected function tearDown(): void {
        \core_useragent::instance(true);
        focusmode::reset_caches();
        parent::tearDown();
    }

    /**
     * A course with completion, in the given format.
     *
     * @param string $format The course format.
     * @return \stdClass
     */
    private function course(string $format = 'topics'): \stdClass {
        return self::getDataGenerator()->create_course([
            'fullname' => 'Competency management',
            'format' => $format,
            'enablecompletion' => 1,
        ]);
    }

    /**
     * A page activity of a course.
     *
     * @param \stdClass $course The course.
     * @param int $completion The completion tracking of the activity.
     * @param string $name The activity's name.
     * @return \cm_info
     */
    private function activity(
        \stdClass $course,
        int $completion = COMPLETION_TRACKING_MANUAL,
        string $name = 'Reading 2'
    ): \cm_info {
        $module = self::getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => $name,
            'completion' => $completion,
        ]);

        return get_fast_modinfo($course)->get_cm($module->cmid);
    }

    /**
     * An activity's page, as require_login() leaves it.
     *
     * @param \stdClass $course The course.
     * @param \cm_info $cm The activity.
     * @return \moodle_page
     */
    private function activity_page(\stdClass $course, \cm_info $cm): \moodle_page {
        $page = new \moodle_page();
        $page->set_context(\core\context\module::instance($cm->id));
        $page->set_course($course);
        $page->set_cm($cm, $course);
        $page->set_url('/mod/page/view.php', ['id' => $cm->id]);
        $page->set_pagelayout('incourse');

        return $page;
    }

    /**
     * A course's home page, as course/view.php sets it up.
     *
     * @param \stdClass $course The course.
     * @param string|null $pagetype The page type; course/view.php's own when null.
     * @return \moodle_page
     */
    private function course_page(\stdClass $course, ?string $pagetype = null): \moodle_page {
        $page = new \moodle_page();
        $page->set_context(\core\context\course::instance($course->id));
        $page->set_course($course);
        $page->set_url('/course/view.php', ['id' => $course->id]);
        $page->set_pagelayout('course');
        $page->set_pagetype($pagetype ?? 'course-view-' . $course->format);

        return $page;
    }

    /**
     * A student of the course, logged in.
     *
     * @param \stdClass $course The course.
     * @return \stdClass
     */
    private function student(\stdClass $course): \stdClass {
        $user = self::getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);

        return $user;
    }

    /**
     * An activity page and the course page are the two kinds; the site course, the category page and the course
     * page under another page type are none.
     *
     * @return void
     */
    public function test_the_page_kinds(): void {
        global $SITE;
        $course = $this->course();
        $cm = $this->activity($course);

        $this->assertSame(readingbar::KIND_ACTIVITY, readingbar::kind($this->activity_page($course, $cm)));
        $this->assertSame(readingbar::KIND_COURSE, readingbar::kind($this->course_page($course)));
        $this->assertNull(readingbar::kind($this->course_page($course, 'course-section')), 'course/section.php is not the home.');

        $sitecm = $this->activity($SITE, COMPLETION_TRACKING_NONE);
        $this->assertNull(readingbar::kind($this->activity_page($SITE, $sitecm)), 'An activity of the front page.');

        $category = new \moodle_page();
        $category->set_context(\core\context\coursecat::instance((int) $course->category));
        $category->set_url('/course/index.php', ['categoryid' => $course->category]);
        $category->set_pagelayout('coursecategory');
        $this->assertNull(readingbar::kind($category), 'The category showcase.');

        // The incourse layout without a course module is no activity page.
        $plain = new \moodle_page();
        $plain->set_context(\core\context\course::instance($course->id));
        $plain->set_course($course);
        $plain->set_url('/course/view.php', ['id' => $course->id]);
        $plain->set_pagelayout('incourse');
        $this->assertNull(readingbar::kind($plain));
    }

    /**
     * The context bar ships on, and only an explicit '0' switches it off.
     *
     * @return void
     */
    public function test_only_an_explicit_zero_switches_the_context_bar_off(): void {
        $course = $this->course();
        $page = $this->activity_page($course, $this->activity($course));
        $this->student($course);

        unset_config(readingbar::SETTING_CONTEXT, readingbar::COMPONENT);
        $this->assertTrue(readingbar::context_enabled(), 'Never saved: on.');
        $this->assertTrue(readingbar::context_applies($page));

        set_config(readingbar::SETTING_CONTEXT, '', readingbar::COMPONENT);
        $this->assertTrue(readingbar::context_applies($page), 'An empty value is not an explicit zero.');

        set_config(readingbar::SETTING_CONTEXT, '0', readingbar::COMPONENT);
        $this->assertFalse(readingbar::context_enabled());
        $this->assertFalse(readingbar::context_applies($page));
        $this->assertTrue(readingbar::applies($page), 'The band follows the reading whatever the context bar says.');

        set_config(readingbar::SETTING_CONTEXT, '1', readingbar::COMPONENT);
        $this->assertTrue(readingbar::context_applies($page));
    }

    /**
     * Focus mode takes the page: its bar replaces the navbar, so none of the three applies there.
     *
     * @return void
     */
    public function test_focus_mode_takes_the_page(): void {
        $course = $this->course();
        $page = $this->activity_page($course, $this->activity($course));
        $this->student($course);
        set_config(readingbar::SETTING_AUTOHIDE, readingbar::KIND_ACTIVITY, readingbar::COMPONENT);

        $this->assertTrue(readingbar::applies($page), 'Control: the ordinary activity page.');
        $this->assertTrue(readingbar::context_applies($page));
        $this->assertTrue(readingbar::autohide_applies($page, false));

        focusmode::enter_session((int) $course->id);
        $this->assertTrue(focusmode::applies($page), 'Precondition: focus mode applies.');
        $this->assertFalse(readingbar::applies($page));
        $this->assertFalse(readingbar::context_applies($page));
        $this->assertFalse(readingbar::autohide_applies($page, false));
        $this->assertSame([], readingbar::body_classes($page, false));
    }

    /**
     * The Moodle app is left alone.
     *
     * @return void
     */
    public function test_the_moodle_app_is_left_alone(): void {
        $course = $this->course();
        $page = $this->activity_page($course, $this->activity($course));
        $this->student($course);
        $this->assertTrue(readingbar::applies($page), 'Control: a browser.');
        $this->assertTrue(readingbar::context_applies($page));

        \core_useragent::instance(true, 'Mozilla/5.0 (Linux; Android 10) MoodleMobile 4.5.0');

        $this->assertTrue(\core_useragent::is_moodle_app(), 'Precondition: the user agent is the app.');
        $this->assertFalse(readingbar::applies($page));
        $this->assertFalse(readingbar::context_applies($page));
        $this->assertSame([], readingbar::body_classes($page, false));
    }

    /**
     * The front page's own activities and home get nothing: the site course is no reading course.
     *
     * @return void
     */
    public function test_the_site_course_gets_nothing(): void {
        global $SITE;
        $this->setAdminUser();
        $course = $this->course();
        $this->assertTrue(readingbar::applies($this->activity_page($course, $this->activity($course))), 'Control.');

        $page = $this->activity_page($SITE, $this->activity($SITE, COMPLETION_TRACKING_NONE));
        $this->assertFalse(readingbar::applies($page));
        $this->assertFalse(readingbar::context_applies($page));
        $this->assertSame([], readingbar::body_classes($page, false));
    }

    /**
     * A visitor whose navbar carries the theme's "Log in" and "Create account" gets no context bar; with those off, it
     * does: the decision is guestnav's own.
     *
     * @return void
     */
    public function test_a_visitor_with_the_guest_actions_gets_no_context_bar(): void {
        $course = $this->course();
        $page = $this->activity_page($course, $this->activity($course));

        $this->student($course);
        $this->assertTrue(readingbar::context_applies($page), 'Control: a logged-in account.');

        $this->setUser(null);
        $this->assertTrue(guestnav::applies($page), 'Precondition: the visitor\'s actions are drawn.');
        $this->assertTrue(readingbar::applies($page), 'The band follows a visitor\'s reading too.');
        $this->assertFalse(readingbar::context_applies($page));

        $this->setGuestUser();
        $this->assertFalse(readingbar::context_applies($page), 'The guest account too.');

        set_config(guestnav::SETTING, '0', guestnav::COMPONENT);
        $this->assertFalse(guestnav::applies($page));
        $this->assertTrue(readingbar::context_applies($page), 'Without the actions, nothing is hidden under the layer.');
    }

    /**
     * Auto-hide ships with no page kind, reads the kinds an administrator ticked, and drops a key it does not know.
     *
     * @return void
     */
    public function test_autohide_follows_the_ticked_kinds(): void {
        $course = $this->course();
        $activity = $this->activity_page($course, $this->activity($course));
        $home = $this->course_page($course);
        $this->student($course);

        unset_config(readingbar::SETTING_AUTOHIDE, readingbar::COMPONENT);
        $this->assertSame([], readingbar::autohide_kinds(), 'Off by default.');
        $this->assertFalse(readingbar::autohide_applies($activity, false));
        $this->assertFalse(readingbar::autohide_applies($home, false));

        set_config(readingbar::SETTING_AUTOHIDE, readingbar::KIND_ACTIVITY, readingbar::COMPONENT);
        $this->assertTrue(readingbar::autohide_applies($activity, false));
        $this->assertFalse(readingbar::autohide_applies($home, false), 'The course page was not ticked.');

        set_config(readingbar::SETTING_AUTOHIDE, 'activity,course,stale', readingbar::COMPONENT);
        $this->assertSame([readingbar::KIND_ACTIVITY, readingbar::KIND_COURSE], readingbar::autohide_kinds());
        $this->assertTrue(readingbar::autohide_applies($home, false));
    }

    /**
     * Never on the Behat site, where a navbar moved off the screen breaks Selenium's clicks.
     *
     * @return void
     */
    public function test_autohide_is_off_on_the_behat_site(): void {
        $course = $this->course();
        $page = $this->activity_page($course, $this->activity($course));
        $this->student($course);
        set_config(readingbar::SETTING_AUTOHIDE, readingbar::KIND_ACTIVITY, readingbar::COMPONENT);

        $this->assertTrue(readingbar::autohide_applies($page, false), 'Control: another site.');
        $this->assertFalse(readingbar::autohide_applies($page, true));
        $this->assertSame([readingbar::BODY_READING], readingbar::body_classes($page, true));
        $this->assertFalse(readingbar::client_config($page, true)['autohide']);
        $this->assertTrue(readingbar::client_config($page, true)['behat']);
        $this->assertFalse(readingbar::behat_site(), 'Precondition: PHPUnit is not the Behat site.');
        $this->assertTrue(readingbar::autohide_applies($page), 'Null asks the request, which is not the Behat site here.');
    }

    /**
     * The body classes: the band's track on every reading page, auto-hide's offsets where it is on.
     *
     * @return void
     */
    public function test_the_body_classes(): void {
        $course = $this->course();
        $activity = $this->activity_page($course, $this->activity($course));
        $home = $this->course_page($course);
        $this->student($course);

        $this->assertSame([readingbar::BODY_READING], readingbar::body_classes($activity, false));
        $this->assertSame([readingbar::BODY_READING], readingbar::body_classes($home, false));

        set_config(readingbar::SETTING_AUTOHIDE, readingbar::KIND_COURSE, readingbar::COMPONENT);
        $this->assertSame([readingbar::BODY_READING], readingbar::body_classes($activity, false));
        $this->assertSame([readingbar::BODY_READING, readingbar::BODY_AUTOHIDE], readingbar::body_classes($home, false));
    }

    /**
     * The course index control exists where core draws the drawer: a format that uses one, on a page that did not hide it.
     *
     * @return void
     */
    public function test_the_index_control_follows_the_course_index(): void {
        $topics = $this->course('topics');
        $page = $this->activity_page($topics, $this->activity($topics));
        $this->student($topics);
        $this->assertTrue(course_get_format($topics)->uses_course_index(), 'Precondition.');
        $this->assertTrue(readingbar::has_course_index($page));
        $this->assertTrue(readingbar::context($page)['hasindex']);

        $page->set_show_course_index(false);
        $this->assertFalse(readingbar::has_course_index($page), 'The page hid the index.');

        $social = $this->course('social');
        $this->assertFalse(course_get_format($social)->uses_course_index(), 'Precondition: social uses no course index.');
        $socialpage = $this->course_page($social);
        $this->assertFalse(readingbar::has_course_index($socialpage));
        $this->assertFalse(readingbar::context($socialpage)['hasindex']);
    }

    /**
     * The status is core's answer for the reader: pending, then complete once core records it; none untracked.
     *
     * @return void
     */
    public function test_the_status_is_read_from_core(): void {
        $course = $this->course();
        $cm = $this->activity($course);
        $student = $this->student($course);

        $this->assertSame(readingbar::STATE_PENDING, readingbar::activity_status($cm));
        $context = readingbar::context($this->activity_page($course, $cm));
        $this->assertTrue($context['hasstatus']);
        $this->assertSame(readingbar::STATE_PENDING, $context['state']);
        $this->assertSame((int) $cm->id, $context['cmid']);

        (new \completion_info($course))->update_state($cm, COMPLETION_COMPLETE, (int) $student->id);
        $cm = get_fast_modinfo($course)->get_cm($cm->id);
        $this->assertSame(readingbar::STATE_COMPLETE, readingbar::activity_status($cm));
        $this->assertTrue(readingbar::context($this->activity_page($course, $cm))['complete']);

        // An activity without completion has no status.
        $untracked = $this->activity($course, COMPLETION_TRACKING_NONE, 'Untracked');
        $this->assertNull(readingbar::activity_status($untracked));
        $this->assertFalse(readingbar::context($this->activity_page($course, $untracked))['hasstatus']);

        // Nor does a person completion does not track: a teacher.
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $this->assertNull(readingbar::activity_status($cm));

        // Nor the guest account.
        $this->setGuestUser();
        $this->assertNull(readingbar::activity_status($cm));
    }

    /**
     * The title and the sub line: the activity's name, then the course's and "X of Y completed" for a tracked reader.
     *
     * @return void
     */
    public function test_the_title_and_the_sub_line(): void {
        $course = $this->course();
        $cm = $this->activity($course, COMPLETION_TRACKING_MANUAL, 'Maps & scales');
        $this->activity($course, COMPLETION_TRACKING_MANUAL, 'Second');
        $student = $this->student($course);
        (new \completion_info($course))->update_state($cm, COMPLETION_COMPLETE, (int) $student->id);

        $progress = get_string('drawer_progress', 'theme_dimensions', (object) ['completed' => 1, 'total' => 2]);
        $context = readingbar::context($this->activity_page($course, $cm));
        $this->assertSame(readingbar::KIND_ACTIVITY, $context['kind']);
        $this->assertTrue($context['isactivity']);
        $this->assertSame('Maps & scales', $context['title'], 'The plain spelling, for the double stash.');
        $this->assertSame('Competency management · ' . $progress, $context['subline']);

        $home = readingbar::context($this->course_page($course));
        $this->assertSame('Competency management', $home['title']);
        $this->assertFalse($home['isactivity']);
        $this->assertFalse($home['hasstatus']);
        $this->assertSame($progress, $home['subline']);

        // A reader completion does not track reads the course's name alone.
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $this->assertSame('Competency management', readingbar::context($this->activity_page($course, $cm))['subline']);
    }

    /**
     * The course page's band is the reader's completion, floored; an activity page's is the module's to measure.
     *
     * @return void
     */
    public function test_the_course_page_band_is_the_readers_completion(): void {
        $course = $this->course();
        $first = $this->activity($course);
        $this->activity($course, COMPLETION_TRACKING_MANUAL, 'Second');
        $this->activity($course, COMPLETION_TRACKING_MANUAL, 'Third');
        $student = $this->student($course);
        $home = $this->course_page($course);

        $this->assertSame(0, readingbar::client_config($home, false)['progress']);
        (new \completion_info($course))->update_state($first, COMPLETION_COMPLETE, (int) $student->id);
        $config = readingbar::client_config($home, false);
        $this->assertSame(33, $config['progress'], 'One of three, floored.');
        $this->assertSame(readingbar::KIND_COURSE, $config['kind']);
        $this->assertFalse($config['autohide']);
        $this->assertFalse($config['behat']);

        $this->assertNull(readingbar::client_config($this->activity_page($course, $first), false)['progress']);

        // No number for a reader completion does not track: the band stays full.
        $this->setGuestUser();
        $this->assertNull(readingbar::client_config($home, false)['progress']);
    }
}
