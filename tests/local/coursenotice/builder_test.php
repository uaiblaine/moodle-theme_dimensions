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
 * Theme Dimensions - Tests for the course notice's decisions
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\coursenotice;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\hotsite\links;
use theme_dimensions\local\hotsitefields;

/**
 * Tests for the course notice's decisions: every Boost Union hint, the promotion section and the page gate.
 *
 * Each hint is tested the way the fleet asks of a guard: the case that shows it, then the same case
 * with one condition broken (the setting off, the capability missing, the condition false), so that
 * an absence is never proved by a notice that was not built at all. Minting is asserted by the rows
 * in core's shortlink table, never by the notice alone.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(builder::class)]
#[CoversClass(notice::class)]
final class builder_test extends \advanced_testcase {
    /**
     * Start every test with Boost Union's hints off, a configured router and empty request memos.
     *
     * Boost Union ships every hint off, so a test switches on exactly the one it is about.
     * local_unlistedcourses memoises per course id in statics that PHPUnit does not reset, and the
     * sequences are restored between tests, so its three memos are reset here.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/theme/boost_union/lib.php');
        $this->resetAfterTest();
        $this->set_router(true);
        // The site admits the guest account, or the guest entry is withdrawn (guestaccess::available()).
        set_config('autologinguests', 1);
        $settings = [
            'showhintcoursehidden',
            'showhintforumnotifications',
            'showhintcourseselfenrol',
            'showhintcourseguestenrol',
            'showhintcourseguestaccess',
            'showswitchedroleincourse',
        ];
        foreach ($settings as $name) {
            set_config($name, THEME_BOOST_UNION_SETTING_SELECT_NO, builder::BU);
        }
        $this->reset_discoverability();
    }

    /**
     * Leave no router built under this test's setting behind.
     *
     * @return void
     */
    protected function tearDown(): void {
        \core\di::reset_container();
        parent::tearDown();
    }

    /**
     * Declare the site's router configured or not.
     *
     * @param bool $configured Whether it is.
     * @return void
     */
    private function set_router(bool $configured): void {
        global $CFG;
        $CFG->routerconfigured = $configured;
        \core\di::reset_container();
    }

    /**
     * Reset local_unlistedcourses' request memos.
     *
     * @return void
     */
    private function reset_discoverability(): void {
        \local_unlistedcourses\discoverability::reset_caches();
        \local_unlistedcourses\access::reset_caches();
        \local_unlistedcourses\category_access::reset_caches();
    }

    /**
     * A course, optionally opted into a hotsite model with a modality.
     *
     * @param int $modelo The page model (0 = none).
     * @param array $options Extra options for the course generator.
     * @param int $modalidade The modality (0 = none).
     * @return \stdClass The course record.
     */
    private function course(int $modelo = 0, array $options = [], int $modalidade = 0): \stdClass {
        \core_course\customfield\course_handler::reset_caches();
        hotsitefields::reset_field_cache();
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();

        // Saved as the administrator: instance_form_save() drops the fields the current user may not
        // edit. Every test sets the user it needs afterwards.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course($options);
        if ($modelo) {
            $data = (object) ['id' => $course->id, 'customfield_' . hotsitefields::SHORTNAME_MODELO => $modelo];
            if ($modalidade) {
                $data->{'customfield_' . hotsitefields::SHORTNAME_MODALIDADE} = $modalidade;
            }
            \core_course\customfield\course_handler::create()->instance_form_save($data);
        }
        return get_course($course->id);
    }

    /**
     * A user enrolled in the course with a role.
     *
     * @param \stdClass $course The course.
     * @param string $role The role's shortname.
     * @return \stdClass The user.
     */
    private function member(\stdClass $course, string $role): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role);
        return $user;
    }

    /**
     * A page of the course: its course page, or an activity's page with that activity's cm.
     *
     * @param \stdClass $course The course.
     * @param string $script The page's script.
     * @param \cm_info|null $cm The activity, for an activity page.
     * @return \moodle_page The page.
     */
    private function page(\stdClass $course, string $script = '/course/view.php', ?\cm_info $cm = null): \moodle_page {
        $page = new \moodle_page();
        if ($cm) {
            $page->set_context(\core\context\module::instance($cm->id));
            $page->set_course($course);
            $page->set_cm($cm, $course);
            $page->set_url($script, ['id' => $cm->id]);
        } else {
            $page->set_context(\core\context\course::instance($course->id));
            $page->set_course($course);
            $page->set_url($script, ['id' => $course->id]);
        }
        return $page;
    }

    /**
     * Build the notice of a page.
     *
     * @param \moodle_page $page The page.
     * @return notice|null The notice.
     */
    private function build(\moodle_page $page): ?notice {
        global $PAGE;
        return builder::build($page, $PAGE->get_renderer('core'));
    }

    /**
     * The hotsite's short codes of a course, both types.
     *
     * @param \stdClass $course The course.
     * @return int How many rows core's shortlink table holds for it.
     */
    private function codes(\stdClass $course): int {
        global $DB;
        return $DB->count_records('shortlink', ['component' => hotsitefields::COMPONENT, 'identifier' => (string) $course->id]);
    }

    /**
     * Switch the course's guest access instance on, with a password or without one.
     *
     * @param \stdClass $course The course.
     * @param string $password The password; '' for none.
     * @return void
     */
    private function enable_guest(\stdClass $course, string $password = ''): void {
        global $DB;
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $course->id, 'enrol' => 'guest']);
        $DB->set_field('enrol', 'password', $password, ['courseid' => $course->id, 'enrol' => 'guest']);
    }

    /**
     * Nothing is said outside a course, even where Boost Union would have spoken.
     *
     * The control is the same switched role inside a course, which does produce the hint.
     *
     * @return void
     */
    public function test_nothing_is_said_outside_a_course(): void {
        global $DB, $SITE;
        set_config('showswitchedroleincourse', THEME_BOOST_UNION_SETTING_SELECT_YES, builder::BU);
        $course = $this->course();
        $this->setAdminUser();
        $studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);

        role_switch($studentrole, \core\context\course::instance($course->id));
        $notice = $this->build($this->page($course));
        $this->assertNotNull($notice, 'Control: the switched role is said inside a course.');
        $this->assertArrayHasKey(builder::KEY_SWITCHEDROLE, $notice->notes);

        role_switch($studentrole, \core\context\course::instance(SITEID));
        $page = new \moodle_page();
        $page->set_context(\core\context\course::instance(SITEID));
        $page->set_course($SITE);
        $page->set_url('/index.php');
        $this->assertNull($this->build($page));

        $nourl = new \moodle_page();
        $nourl->set_context(\core\context\course::instance($course->id));
        $nourl->set_course($course);
        $this->assertNull($this->build($nourl), 'A page with no url says nothing.');
    }

    /**
     * The landing page is the course page, or the single activity's own view page, and nothing else.
     *
     * @return void
     */
    public function test_the_landing_page_follows_the_format(): void {
        $topics = $this->course(0, ['format' => 'topics']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $topics->id]);
        $pagecm = get_fast_modinfo($topics)->get_cm($page->cmid);
        $this->assertTrue(builder::is_landing($this->page($topics), $topics));
        $this->assertFalse(builder::is_landing($this->page($topics, '/mod/page/view.php', $pagecm), $topics));

        $single = $this->course(0, ['format' => 'singleactivity', 'activitytype' => 'page']);
        $main = $this->getDataGenerator()->create_module('page', ['course' => $single->id, 'section' => 0]);
        $other = $this->getDataGenerator()->create_module('forum', ['course' => $single->id, 'section' => 0]);
        $second = $this->getDataGenerator()->create_module('page', ['course' => $single->id, 'section' => 0]);
        $modinfo = get_fast_modinfo($single);
        $maincm = $modinfo->get_cm($main->cmid);
        $othercm = $modinfo->get_cm($other->cmid);
        $this->assertSame((int) $main->cmid, (int) course_get_format($single)->get_main_activity()->id, 'Precondition.');

        $this->assertTrue(builder::is_landing($this->page($single, '/mod/page/view.php', $maincm), $single));
        $this->assertFalse(builder::is_landing($this->page($single, '/mod/forum/view.php', $othercm), $single));
        $this->assertFalse(
            builder::is_landing($this->page($single, '/mod/page/view.php', $modinfo->get_cm($second->cmid)), $single),
            'A second activity of the main type, at the same script, is not the main one.'
        );
        $this->assertFalse(
            builder::is_landing($this->page($single, '/mod/page/other.php', $maincm), $single),
            'The main activity\'s other pages are not the landing page.'
        );
        $this->assertFalse(builder::is_landing($this->page($single), $single), 'A single activity course has no course page.');
    }

    /**
     * The promotion section is drawn on a single activity's page for its editor, and on no other activity's.
     *
     * @return void
     */
    public function test_a_single_activity_course_is_promoted_on_its_activity_page(): void {
        $single = $this->course(hotsitefields::MODELO_CURSO, ['format' => 'singleactivity', 'activitytype' => 'page']);
        $main = $this->getDataGenerator()->create_module('page', ['course' => $single->id, 'section' => 0]);
        $other = $this->getDataGenerator()->create_module('forum', ['course' => $single->id, 'section' => 0]);
        $modinfo = get_fast_modinfo($single);
        $this->setUser($this->member($single, 'editingteacher'));

        $notice = $this->build($this->page($single, '/mod/page/view.php', $modinfo->get_cm($main->cmid)));
        $this->assertNotNull($notice);
        $this->assertTrue($notice->promote);
        $this->assertArrayHasKey(builder::KEY_HOTSITE, $notice->links);

        $this->assertNull($this->build($this->page($single, '/mod/forum/view.php', $modinfo->get_cm($other->cmid))));
    }

    /**
     * Only a course editor gets the promotion section, and only on the landing page.
     *
     * @return void
     */
    public function test_the_promotion_section_is_for_course_editors_on_the_landing_page(): void {
        $course = $this->course(hotsitefields::MODELO_EVENTO, [], hotsitefields::MODALIDADE_PRESENCIAL);

        $this->setUser($this->member($course, 'editingteacher'));
        $notice = $this->build($this->page($course));
        $this->assertTrue($notice->promote);
        $this->assertSame(
            ['loginonly', 'hotsite'],
            array_keys($notice->chips),
            'A course that is not public says so first, then names its hotsite.'
        );
        $expected = get_string('coursenotice_chip_hotsitemodality', hotsitefields::COMPONENT, (object) [
            'model' => get_string('hotsitefield_modelo_evento', hotsitefields::COMPONENT),
            'modality' => \core_text::strtolower(get_string('hotsitefield_modalidade_presencial', hotsitefields::COMPONENT)),
        ]);
        $this->assertSame($expected, $notice->chips[builder::KEY_HOTSITE]['label']);
        $this->assertSame(links::share((int) $course->id)->out(false), $notice->links[builder::KEY_HOTSITE]['url']);
        $this->assertArrayHasKey(builder::KEY_LOGINONLY, $notice->notes);
        $this->assertNull($notice->notes[builder::KEY_LOGINONLY]['link'], 'An editing teacher may not publish.');

        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($module->cmid);
        $this->assertNull($this->build($this->page($course, '/mod/page/view.php', $cm)), 'Not away from the landing page.');

        foreach (['student', 'teacher'] as $role) {
            $this->setUser($this->member($course, $role));
            $this->assertNull($this->build($this->page($course)), "A $role gets no promotion section.");
        }
    }

    /**
     * The notice mints the codes for an editor, once, and never for anybody else.
     *
     * @return void
     */
    public function test_the_notice_mints_for_an_editor_and_never_for_anybody_else(): void {
        $course = $this->course(hotsitefields::MODELO_CURSO);
        $this->enable_guest($course);
        $this->assertSame(0, $this->codes($course), 'Precondition: nothing minted yet.');

        foreach (['student', 'teacher'] as $role) {
            $this->setUser($this->member($course, $role));
            $this->build($this->page($course));
            $this->assertSame(0, $this->codes($course), "A $role's visit mints nothing.");
        }
        $this->setGuestUser();
        $this->build($this->page($course));
        $this->assertSame(0, $this->codes($course), 'The guest account\'s visit mints nothing.');

        $this->setUser($this->member($course, 'editingteacher'));
        $notice = $this->build($this->page($course));
        $this->assertSame(2, $this->codes($course), 'The editor\'s visit minted the hotsite\'s code and the guest entry\'s.');
        $this->assertStringContainsString(
            '/p/' . links::existing_code((int) $course->id),
            $notice->links[builder::KEY_HOTSITE]['url']
        );
        $this->assertStringContainsString(
            '/p/' . links::existing_code((int) $course->id, links::LINKTYPE_GUEST),
            $notice->links[builder::KEY_GUEST]['url']
        );

        $this->build($this->page($course));
        $this->assertSame(2, $this->codes($course), 'A second visit reuses them.');
    }

    /**
     * A course without a hotsite model has no promotion section and mints nothing, even for its editor.
     *
     * @return void
     */
    public function test_a_course_without_a_model_is_not_promoted(): void {
        $course = $this->course();
        $this->setUser($this->member($course, 'editingteacher'));
        $this->assertNull($this->build($this->page($course)));
        $this->assertSame(0, $this->codes($course));
    }

    /**
     * The guest entry's row and chip appear only where the course offers passwordless guest access.
     *
     * @return void
     */
    public function test_the_guest_entry_is_offered_only_where_the_course_has_it(): void {
        $course = $this->course(hotsitefields::MODELO_CURSO);
        $this->setUser($this->member($course, 'editingteacher'));

        $notice = $this->build($this->page($course));
        $this->assertArrayHasKey(builder::KEY_HOTSITE, $notice->links, 'Control: the section is drawn.');
        $this->assertArrayNotHasKey(builder::KEY_GUEST, $notice->links, 'Guest access is off by default.');
        $this->assertArrayNotHasKey(builder::KEY_GUEST, $notice->chips);

        $this->enable_guest($course, 'secret');
        $notice = $this->build($this->page($course));
        $this->assertArrayNotHasKey(builder::KEY_GUEST, $notice->links, 'A password closes the entry.');

        $this->enable_guest($course);
        $notice = $this->build($this->page($course));
        $this->assertArrayHasKey(builder::KEY_GUEST, $notice->links);
        $this->assertArrayHasKey(builder::KEY_GUEST, $notice->chips);
        $this->assertArrayHasKey(builder::KEY_GUESTNOTPUBLIC, $notice->notes, 'Not public: logged-in readers only.');
    }

    /**
     * A public course is said to be public, with the link to change it for whoever may publish.
     *
     * @return void
     */
    public function test_a_public_course_is_said_to_be_public(): void {
        $course = $this->course(hotsitefields::MODELO_CURSO);
        $this->setAdminUser();

        $notice = $this->build($this->page($course));
        $this->assertArrayHasKey(builder::KEY_LOGINONLY, $notice->chips, 'Control: not public yet.');
        $this->assertArrayNotHasKey(builder::KEY_PUBLIC, $notice->chips);

        \local_unlistedcourses\discoverability::set_state((int) $course->id, \local_unlistedcourses\discoverability::STATE_PUBLIC);
        $this->reset_discoverability();
        $notice = $this->build($this->page($course));
        $this->assertArrayHasKey(builder::KEY_PUBLIC, $notice->chips);
        $this->assertSame(notice::TONE_OK, $notice->chips[builder::KEY_PUBLIC]['tone']);
        $this->assertArrayNotHasKey(builder::KEY_LOGINONLY, $notice->chips);
        $this->assertArrayHasKey(builder::KEY_PUBLIC, $notice->notes);
        $edit = (new \core\url('/course/edit.php', ['id' => $course->id]))->out(false);
        $this->assertSame($edit, $notice->notes[builder::KEY_PUBLIC]['link']['url'], 'The administrator may publish.');
    }

    /**
     * Without the router the addresses are the long ones, nothing is minted and a note says why.
     *
     * @return void
     */
    public function test_without_the_router_nothing_is_minted_and_a_note_says_why(): void {
        $this->set_router(false);
        $course = $this->course(hotsitefields::MODELO_CURSO);
        $this->setUser($this->member($course, 'editingteacher'));

        $notice = $this->build($this->page($course));
        $this->assertSame(links::page((int) $course->id)->out(false), $notice->links[builder::KEY_HOTSITE]['url']);
        $this->assertArrayHasKey(builder::KEY_ROUTEROFF, $notice->notes);
        $this->assertSame(0, $this->codes($course));
    }

    /**
     * Boost Union's hidden course hint: setting, capability, a hidden course; the settings link for an editor.
     *
     * @return void
     */
    public function test_the_hidden_course_hint_follows_boost_union(): void {
        $hidden = $this->course(0, ['visible' => 0]);
        $editor = $this->member($hidden, 'editingteacher');
        $teacher = $this->member($hidden, 'teacher');
        $student = $this->member($hidden, 'student');

        $this->setUser($editor);
        $this->assertNull($this->build($this->page($hidden)), 'The setting is off.');

        set_config('showhintcoursehidden', THEME_BOOST_UNION_SETTING_SELECT_YES, builder::BU);
        $notice = $this->build($this->page($hidden));
        $this->assertArrayHasKey(builder::KEY_HIDDEN, $notice->chips);
        $this->assertSame(notice::TONE_WARN, $notice->chips[builder::KEY_HIDDEN]['tone']);
        $lines = $notice->notes[builder::KEY_HIDDEN]['lines'];
        $this->assertSame(get_string('showhintcoursehiddengeneral', builder::BU), $lines[0]);
        $this->assertStringContainsString('/course/edit.php?id=' . $hidden->id, $lines[1]);

        $this->setUser($teacher);
        $notice = $this->build($this->page($hidden));
        $this->assertCount(1, $notice->notes[builder::KEY_HIDDEN]['lines'], 'No settings link without course:update.');

        $this->setUser($student);
        $this->assertNull($this->build($this->page($hidden)), 'A student lacks the capability.');

        $visible = $this->course();
        $this->setUser($this->member($visible, 'editingteacher'));
        $this->assertNull($this->build($this->page($visible)), 'A visible course has no hint.');
    }

    /**
     * Boost Union's forum special case: on a forum page of a hidden course, the forum text, only with its setting.
     *
     * @return void
     */
    public function test_the_forum_notifications_hint_replaces_the_hidden_one_on_a_forum_page(): void {
        set_config('showhintcoursehidden', THEME_BOOST_UNION_SETTING_SELECT_YES, builder::BU);
        $hidden = $this->course(0, ['visible' => 0]);
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $hidden->id]);
        $cm = get_fast_modinfo($hidden)->get_cm($forum->cmid);
        $this->setUser($this->member($hidden, 'editingteacher'));

        $this->assertNull($this->build($this->page($hidden, '/mod/forum/view.php', $cm)), 'The forum setting is off.');

        set_config('showhintforumnotifications', THEME_BOOST_UNION_SETTING_SELECT_YES, builder::BU);
        $notice = $this->build($this->page($hidden, '/mod/forum/view.php', $cm));
        $this->assertArrayHasKey(builder::KEY_FORUM, $notice->notes);
        $this->assertSame(get_string('showhintforumnotifications', builder::BU), $notice->notes[builder::KEY_FORUM]['lines'][0]);
        $this->assertArrayHasKey(builder::KEY_HIDDEN, $notice->chips);

        $page = $this->getDataGenerator()->create_module('page', ['course' => $hidden->id]);
        $pagecm = get_fast_modinfo($hidden)->get_cm($page->cmid);
        $this->assertNull(
            $this->build($this->page($hidden, '/mod/page/view.php', $pagecm)),
            'Another activity\'s page says nothing.'
        );
    }

    /**
     * Boost Union's guest access hint: a reader in the course as a guest, with the self enrolment link where there is one.
     *
     * @return void
     */
    public function test_the_guest_access_hint_follows_boost_union(): void {
        global $DB;
        $course = $this->course();
        $visitor = $this->getDataGenerator()->create_user();
        $this->setUser($visitor);

        $this->assertNull($this->build($this->page($course)), 'The setting is off.');

        set_config('showhintcourseguestaccess', THEME_BOOST_UNION_SETTING_SELECT_YES, builder::BU);
        $notice = $this->build($this->page($course));
        $this->assertArrayHasKey(builder::KEY_GUESTACCESS, $notice->notes);
        $this->assertCount(1, $notice->notes[builder::KEY_GUESTACCESS]['lines'], 'Self enrolment is off by default.');

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $course->id, 'enrol' => 'self']);
        $DB->set_field('enrol', 'customint6', 1, ['courseid' => $course->id, 'enrol' => 'self']);
        $notice = $this->build($this->page($course));
        $this->assertStringContainsString(
            '/enrol/index.php?id=' . $course->id,
            $notice->notes[builder::KEY_GUESTACCESS]['lines'][1]
        );

        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($module->cmid);
        $this->assertNull($this->build($this->page($course, '/mod/page/view.php', $cm)), 'Only on the landing page.');

        $studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        role_switch($studentrole, \core\context\course::instance($course->id));
        $this->assertTrue(is_guest(\core\context\course::instance($course->id)), 'Precondition: still a guest.');
        $this->assertNull($this->build($this->page($course)), 'Not after a role switch.');
        role_switch(0, \core\context\course::instance($course->id));

        $this->setUser($this->member($course, 'student'));
        $this->assertNull($this->build($this->page($course)), 'An enrolled student is not a guest.');
    }

    /**
     * Boost Union's guest enrolment hint: staff, a visible course, a guest instance without a password or "always".
     *
     * @return void
     */
    public function test_the_guest_enrolment_hint_follows_boost_union(): void {
        $course = $this->course();
        $this->enable_guest($course);
        $editor = $this->member($course, 'editingteacher');
        $student = $this->member($course, 'student');
        $this->setUser($editor);

        $this->assertNull($this->build($this->page($course)), 'The setting is off.');

        set_config('showhintcourseguestenrol', THEME_BOOST_UNION_SETTING_GUESTACCESSHINT_WITHOUTPASSWORD, builder::BU);
        $notice = $this->build($this->page($course));
        $this->assertArrayHasKey(builder::KEY_GUESTENROL, $notice->chips);
        $lines = $notice->notes[builder::KEY_GUESTENROL]['lines'];
        $this->assertStringStartsWith(
            get_string('showhintcourseguestenrolhint', builder::BU, ['courseid' => $course->id]),
            $lines[0]
        );
        $this->assertStringContainsString('/enrol/instances.php?id=' . $course->id, $lines[1]);

        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($module->cmid);
        $this->assertNull($this->build($this->page($course, '/mod/page/view.php', $cm)), 'Only on the landing page.');

        $this->setUser($student);
        $this->assertNull($this->build($this->page($course)), 'A student lacks the capability.');

        $this->setUser($editor);
        $this->enable_guest($course, 'secret');
        $this->assertNull($this->build($this->page($course)), 'A password hides it under "without password".');
        set_config('showhintcourseguestenrol', THEME_BOOST_UNION_SETTING_GUESTACCESSHINT_ALWAYS, builder::BU);
        $notice = $this->build($this->page($course));
        $this->assertStringStartsWith(
            get_string('showhintcourseguestenrolhintalways', builder::BU, ['courseid' => $course->id]),
            $notice->notes[builder::KEY_GUESTENROL]['lines'][0]
        );

        $hidden = $this->course(0, ['visible' => 0]);
        $this->enable_guest($hidden);
        $this->setUser($this->member($hidden, 'editingteacher'));
        $this->assertNull($this->build($this->page($hidden)), 'A hidden course has no guest enrolment hint.');
    }

    /**
     * Where the promotion section shows the guest entry, the guest enrolment hint keeps its note and drops its chip.
     *
     * @return void
     */
    public function test_the_guest_enrolment_chip_is_not_repeated_beside_the_guest_entry(): void {
        set_config('showhintcourseguestenrol', THEME_BOOST_UNION_SETTING_GUESTACCESSHINT_WITHOUTPASSWORD, builder::BU);
        $course = $this->course(hotsitefields::MODELO_CURSO);
        $this->enable_guest($course);
        $this->setUser($this->member($course, 'editingteacher'));

        $notice = $this->build($this->page($course));
        $this->assertArrayHasKey(builder::KEY_GUEST, $notice->chips);
        $this->assertArrayNotHasKey(builder::KEY_GUESTENROL, $notice->chips);
        $this->assertArrayHasKey(builder::KEY_GUESTENROL, $notice->notes);
    }

    /**
     * Boost Union's self enrolment hint: staff, a visible course, a keyless instance taking new enrolments.
     *
     * @return void
     */
    public function test_the_self_enrolment_hint_follows_boost_union(): void {
        global $DB;
        $course = $this->course();
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $course->id, 'enrol' => 'self']);
        $DB->set_field('enrol', 'customint6', 1, ['courseid' => $course->id, 'enrol' => 'self']);
        $instanceid = (int) $DB->get_field('enrol', 'id', ['courseid' => $course->id, 'enrol' => 'self']);
        $editor = $this->member($course, 'editingteacher');
        $teacher = $this->member($course, 'teacher');
        $this->setUser($editor);

        $this->assertNull($this->build($this->page($course)), 'The setting is off.');

        set_config('showhintcourseselfenrol', THEME_BOOST_UNION_SETTING_SELECT_YES, builder::BU);
        $notice = $this->build($this->page($course));
        $this->assertArrayHasKey(builder::KEY_SELFENROL, $notice->chips);
        $lines = $notice->notes[builder::KEY_SELFENROL]['lines'];
        $this->assertSame(get_string('showhintcourseselfenrolstartcurrently', builder::BU), $lines[0]);
        $this->assertMatchesRegularExpression(
            '~/enrol/editinstance\.php\?courseid=' . $course->id . '&(amp;)?id=' . $instanceid . '\b~',
            $lines[1]
        );
        $this->assertSame(get_string('showhintcourseselfenrolinstancecallforaction', builder::BU), end($lines));

        $this->setUser($teacher);
        $notice = $this->build($this->page($course));
        $this->assertStringNotContainsString('editinstance.php', $notice->notes[builder::KEY_SELFENROL]['lines'][1]);
        $this->assertCount(2, $notice->notes[builder::KEY_SELFENROL]['lines'], 'No call to action without enrol/self:config.');

        $this->setUser($this->member($course, 'student'));
        $this->assertNull($this->build($this->page($course)), 'A student lacks the capability.');

        $this->setUser($editor);
        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($module->cmid);
        $this->assertNull($this->build($this->page($course, '/mod/page/view.php', $cm)), 'Only on the landing page.');
        $DB->set_field('course', 'visible', 0, ['id' => $course->id]);
        $this->assertNull($this->build($this->page(get_course($course->id))), 'A hidden course has no self enrolment hint.');
        $DB->set_field('course', 'visible', 1, ['id' => $course->id]);

        $DB->set_field('enrol', 'password', 'key', ['id' => $instanceid]);
        $this->assertNull($this->build($this->page($course)), 'A key closes it.');
        $DB->set_field('enrol', 'password', '', ['id' => $instanceid]);
        $DB->set_field('enrol', 'customint6', 0, ['id' => $instanceid]);
        $this->assertNull($this->build($this->page($course)), 'No new enrolments, no hint.');
        $DB->set_field('enrol', 'customint6', 1, ['id' => $instanceid]);
        $DB->set_field('enrol', 'enrolenddate', time() - DAYSECS, ['id' => $instanceid]);
        $this->assertNull($this->build($this->page($course)), 'An ended window, no hint.');
        $DB->set_field('enrol', 'enrolenddate', 0, ['id' => $instanceid]);
        $DB->set_field('enrol', 'enrolstartdate', time() + DAYSECS, ['id' => $instanceid]);
        $notice = $this->build($this->page($course));
        $this->assertSame(
            get_string('showhintcourseselfenrolstartfuture', builder::BU),
            $notice->notes[builder::KEY_SELFENROL]['lines'][0],
            'A window opening later is announced as planned.'
        );
    }

    /**
     * Boost Union's switched role hint: on any page of the course, with the way back in the bar.
     *
     * @return void
     */
    public function test_the_switched_role_hint_follows_boost_union(): void {
        global $DB;
        $course = $this->course();
        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($module->cmid);
        $this->setUser($this->member($course, 'editingteacher'));
        $studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);

        role_switch($studentrole, \core\context\course::instance($course->id));
        $this->assertNull($this->build($this->page($course)), 'The setting is off.');

        set_config('showswitchedroleincourse', THEME_BOOST_UNION_SETTING_SELECT_YES, builder::BU);
        $notice = $this->build($this->page($course, '/mod/page/view.php', $cm));
        $this->assertArrayHasKey(builder::KEY_SWITCHEDROLE, $notice->chips);
        $this->assertArrayHasKey(builder::KEY_SWITCHEDROLE, $notice->notes, 'An activity page carries it too.');
        // The role is named as core's user navigation names it (\core\user::get_user_navigation_info()).
        $rolename = role_get_name($DB->get_record('role', ['id' => $studentrole]), \core\context\course::instance($course->id));
        $this->assertSame(
            [get_string('switchedroleto', builder::BU, ['role' => $rolename])],
            $notice->notes[builder::KEY_SWITCHEDROLE]['lines']
        );
        $action = $notice->actions[builder::KEY_SWITCHEDROLE];
        $this->assertSame(get_string('switchrolereturn'), $action['label']);
        $this->assertStringContainsString('/course/switchrole.php?id=' . $course->id, $action['url']);
        $this->assertStringContainsString('switchrole=0', $action['url']);
        $this->assertFalse($notice->promote);

        role_switch(0, \core\context\course::instance($course->id));
        $this->assertNull($this->build($this->page($course)), 'Not switched, no hint.');
    }
}
