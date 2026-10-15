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
 * Theme Dimensions - Tests for the focus mode shell as the renderer draws it
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core_course\customfield\course_handler;
use theme_dimensions\local\focusmode;
use theme_dimensions\local\focusmodefields;
use theme_dimensions\local\login;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the focus mode shell, rendered through the real layout.
 *
 * Every test here renders the whole page - Boost Union's layout file, this theme's renderer and
 * the focus template - because the properties that matter are only visible in the output: that
 * exactly one fixed bar reaches the browser, that the page still carries its JavaScript, that the
 * skip link still lands on an anchor which exists exactly once, and that the two drawers survive
 * a template written by removing things from Boost Union's. The predicate itself is covered by
 * {@see \theme_dimensions\local\focusmode_test}.
 *
 * Each test asserting the bar's absence carries the control that proves the page rendered at
 * all: the marker only Boost Union's navbar produces, {@see self::BU_NAVBAR_MARKER}. Without it
 * "focus mode is off" and "nothing was rendered" are the same assertion.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(core_renderer::class)]
#[CoversClass(focusmode_bar::class)]
#[CoversClass(focusmode::class)]
final class focusmode_renderer_test extends \advanced_testcase {
    /**
     * @var string A marker only Boost Union's own navbar puts on the page.
     *
     * The id belongs to the wrapper the navbar template draws around the user menu
     * (BU/templates/theme_boost/navbar.mustache:107, core Boost's own naming). The focus
     * template does not include that partial at all, so the id is present on an ordinary page
     * and absent on a focused one - which is what makes every "the bar is absent" assertion
     * below mean "the ordinary page was drawn" rather than "nothing came out".
     */
    private const BU_NAVBAR_MARKER = 'id="usernavigation"';

    /** @var string The bar's own root class, from the markup contract. */
    private const BAR = 'dimensions-focusbar';

    /** @var string The opening of every fixed bar on the site, ours and Boost Union's alike. */
    private const FIXED_BAR = 'class="navbar fixed-top';

    /** @var string The page title, deliberately free of any activity name. */
    private const PAGE_TITLE = 'Rendered by the focus mode test';

    /**
     * Start every test with empty request caches and completion enabled on the site.
     *
     * Nothing outside this theme flips usescourseindex any more: Boost Union's 5.2
     * 'activitynavigation' setting, which did, is gone from its 5.3 branch. What decides whether a
     * topics course shows previous and next on 5.3 is core's linear navigation, on by default; the
     * tests that are about this theme's own row switch it off for topics and say so.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        // The completion constants below are not loaded by the test bootstrap, only lazily.
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        focusmode::reset_caches();
        set_config('enablecompletion', 1);
    }

    /**
     * Switch core's linear navigation off for the topics format, the courses these tests create.
     *
     * With it off 5.3 behaves as 5.2 did: the manual completion button is a header action, and core
     * prints no previous and next anywhere on a format with a course index.
     *
     * @return void
     */
    private function without_linear_navigation(): void {
        set_config(\core_courseformat\local\linearnavigationsettings::SETTING_ENABLE_LINEAR_NAV, 0, 'format_topics');
    }

    /**
     * Undo what a test may have set outside the database.
     *
     * @return void
     */
    protected function tearDown(): void {
        focusmode::reset_caches();
        unset($_GET[focusmode::PARAM_OFF]);
        parent::tearDown();
    }

    /**
     * A course with completion available, whose focus mode switch is in the given state.
     *
     * @param bool $on Whether the course switched focus mode on.
     * @param array $options Extra options for the course generator.
     * @return \stdClass The course record.
     */
    private function course(bool $on, array $options = []): \stdClass {
        $course = $this->getDataGenerator()->create_course($options + ['enablecompletion' => 1]);
        $this->set_course_switch($course, $on);
        return $course;
    }

    /**
     * Save the focus mode answer of one course through the course custom field handler.
     *
     * Saved as the administrator, because instance_form_save() silently drops the fields the
     * current user may not edit. Every caller sets the user it needs afterwards.
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
     * One activity of a course, as the page cache knows it.
     *
     * @param \stdClass $course The course.
     * @param array $options Extra options for the module generator.
     * @return \cm_info The course module.
     */
    private function activity(\stdClass $course, array $options = []): \cm_info {
        $module = $this->getDataGenerator()->create_module('page', $options + ['course' => $course->id]);
        return get_fast_modinfo($course)->get_cm($module->cmid);
    }

    /**
     * Build this theme's core renderer on the real activity page of one course module.
     *
     * The order is core_renderer_test::renderer()'s, adapted for an activity page: the course
     * goes in before the theme is forced, because initialise_theme_and_output() sets it to the
     * site otherwise and set_course() refuses to run once a theme is in place. The explicit
     * render target and the reassignment of the global $OUTPUT are both load-bearing: PHPUnit
     * runs as a CLI script, so the factory answers with core's CLI renderer without the target,
     * and Boost Union's layout file - which header() includes - renders through $OUTPUT.
     *
     * The page itself is replaced too. header() may be called once per moodle_page, and several
     * tests below render a control beside the case they are about; core replaces the global the
     * same way (CORE/lib/tests/hook/navigation/primary_extend_test.php:38).
     *
     * @param \stdClass $course The course being viewed.
     * @param \cm_info $cm The activity being viewed.
     * @param string $script The script serving the page, which decides the page type.
     * @return core_renderer The theme's renderer, bound to the new global page.
     */
    private function renderer(\stdClass $course, \cm_info $cm, string $script = '/mod/page/view.php'): core_renderer {
        global $PAGE, $OUTPUT;

        $PAGE = new \moodle_page();
        $PAGE->set_context(\core\context\module::instance($cm->id));
        $PAGE->set_course($course);
        $PAGE->set_cm($cm, $course);
        $PAGE->set_url($script, ['id' => $cm->id]);
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title(self::PAGE_TITLE);
        $PAGE->set_heading($course->fullname);
        $PAGE->force_theme('dimensions');
        $PAGE->initialise_theme_and_output();
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertInstanceOf(core_renderer::class, $OUTPUT);

        return $OUTPUT;
    }

    /**
     * The whole page, as a browser would receive it.
     *
     * @param \stdClass $course The course being viewed.
     * @param \cm_info $cm The activity being viewed.
     * @param string $script The script serving the page.
     * @return string The rendered header and footer, concatenated.
     */
    private function render(\stdClass $course, \cm_info $cm, string $script = '/mod/page/view.php'): string {
        [$header, $footer] = $this->render_halves($course, $cm, $script);
        return $header . $footer;
    }

    /**
     * The page in its two halves, split where the activity's own content would go.
     *
     * Several assertions below are about position rather than presence - the completion line
     * belongs under the content, not above it - and the split header() makes is exactly that
     * boundary, so a test can assert on the half rather than on a fragile offset comparison.
     *
     * @param \stdClass $course The course being viewed.
     * @param \cm_info $cm The activity being viewed.
     * @param string $script The script serving the page.
     * @return array The header half and the footer half.
     */
    private function render_halves(\stdClass $course, \cm_info $cm, string $script = '/mod/page/view.php'): array {
        $renderer = $this->renderer($course, $cm, $script);
        $header = $renderer->header();
        return [$header, $renderer->footer()];
    }

    /**
     * The focus bar alone, cut out of a rendered page.
     *
     * Every assertion about what the bar carries is made on this fragment and never on the whole
     * page: the manual completion button, the activity name and the overflow menu all have
     * legitimate second homes elsewhere in the document - in the browser the course index lists
     * every activity by name - so a page-wide assertion could pass while the bar itself was empty.
     *
     * @param string $html The rendered page.
     * @return string The bar's own markup, from its opening nav to the first closing one.
     */
    private function focus_bar(string $html): string {
        $found = preg_match('~<nav[^>]*' . self::BAR . '.*?</nav>~s', $html, $matches);
        $this->assertSame(1, $found, 'The focus bar is not in the rendered page.');
        return $matches[0];
    }

    /**
     * The body tag alone, cut out of a rendered page.
     *
     * @param string $html The rendered page.
     * @return string The opening body tag with its attributes.
     */
    private function body_tag(string $html): string {
        $found = preg_match('~<body[^>]*>~', $html, $matches);
        $this->assertSame(1, $found, 'The rendered page has no body tag.');
        return $matches[0];
    }

    /**
     * The bar replaces Boost Union's navbar; it does not join it.
     *
     * The count is the whole test. A presence-only assertion passes on a page carrying two fixed
     * bars stacked on top of each other, which is what an interception that renders the focus
     * template without removing the navbar partial produces - and the second one is 60px of
     * chrome the design exists to remove.
     *
     * @return void
     */
    public function test_the_focus_bar_replaces_the_navbar(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $this->assertStringContainsString(self::BAR, $html);
        $this->assertSame(1, substr_count($html, self::FIXED_BAR), 'Exactly one fixed bar, and it is ours.');
        $this->assertStringNotContainsString(self::BU_NAVBAR_MARKER, $html);
    }

    /**
     * A course which did not switch focus mode on gets Boost Union's page, whole.
     *
     * @return void
     */
    public function test_the_ordinary_page_is_drawn_when_the_course_switch_is_off(): void {
        $course = $this->course(false);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $this->assertStringNotContainsString(self::BAR, $html);
        $this->assertStringContainsString(self::BU_NAVBAR_MARKER, $html);
        $this->assertSame(1, substr_count($html, self::FIXED_BAR));
    }

    /**
     * The site-wide master switch off leaves every course alone, whatever the course says.
     *
     * @return void
     */
    public function test_the_ordinary_page_is_drawn_when_the_master_switch_is_off(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        set_config('focusmode', login::SELECT_NO, focusmode::COMPONENT);

        $html = $this->render($course, $cm);

        $this->assertStringNotContainsString(self::BAR, $html);
        $this->assertStringContainsString(self::BU_NAVBAR_MARKER, $html);
    }

    /**
     * The support parameter gives one page load of the ordinary theme.
     *
     * @return void
     */
    public function test_the_ordinary_page_is_drawn_with_focusoff(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $_GET[focusmode::PARAM_OFF] = '1';

        $html = $this->render($course, $cm);

        $this->assertStringNotContainsString(self::BAR, $html);
        $this->assertStringContainsString(self::BU_NAVBAR_MARKER, $html);
    }

    /**
     * A teacher who turned editing on gets the whole theme back.
     *
     * The edit switch lives in the navbar focus mode replaces, so a focus mode which survived
     * editing would leave the teacher unable to turn it off again.
     *
     * @return void
     */
    public function test_editing_teachers_get_the_full_theme(): void {
        global $USER;

        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $USER->editing = 1;

        $html = $this->render($course, $cm);

        $this->assertStringNotContainsString(self::BAR, $html);
        $this->assertStringContainsString(self::BU_NAVBAR_MARKER, $html);
    }

    /**
     * The focused page still carries its JavaScript.
     *
     * The focus template is Boost Union's drawers template with things removed, and it must keep
     * the footer partial: that partial emits the end-of-body token which
     * {@see \core\output\core_renderer::footer()} replaces with the page's JavaScript. Without it
     * the page looks right and has no drawers, no completion toggle and no width control, with
     * nothing in any log.
     *
     * @return void
     */
    public function test_the_page_still_carries_its_javascript(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $this->assertStringContainsString(self::BAR, $html);
        $this->assertStringContainsString("require(['theme_boost/loader'", $html);
    }

    /**
     * The anchor the skip link points at is on the page exactly once.
     *
     * Once, not "at least once": the anchor is core's, it comes from the activity header partial
     * the focus template keeps, and a template which also emitted one of its own would give the
     * browser two elements with the same id and the skip link a coin toss.
     *
     * @return void
     */
    public function test_the_maincontent_anchor_survives(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $this->assertSame(1, substr_count($html, 'id="maincontent"'));
    }

    /**
     * Core's skip link is still the first thing in the body, ahead of the bar.
     *
     * It reaches the page through output.standard_top_of_body_html, which the focus template
     * keeps at the top of the page wrapper. A template which dropped it, or placed it after the
     * bar, would leave a keyboard user tabbing through the bar's controls on every activity.
     *
     * @return void
     */
    public function test_the_skip_link_precedes_the_bar(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $skip = strpos($html, 'href="#maincontent"');
        $bar = strpos($html, self::BAR);
        $this->assertNotFalse($skip, 'Core\'s skip link is missing from the focused page.');
        $this->assertNotFalse($bar);
        $this->assertLessThan($bar, $skip);
    }

    /**
     * The activity name is in the bar and nowhere else in the page's own content.
     *
     * Not a whole-page count: in the browser the course index drawer lists every activity by name,
     * so the name legitimately occurs more than once. What must not happen is a second copy in the
     * reading column, in the heading the activity header partial draws. Boost and Boost Union
     * export that title empty (activityheaderconfig notitle), so no code in this theme prevents
     * it; this test is the sentinel for a parent theme that stops doing so. The third assertion
     * catches it: the h2 is inside the page container, and the bar and the drawers are outside it.
     *
     * @return void
     */
    public function test_the_activity_name_is_shown_exactly_once(): void {
        $course = $this->course(true);
        $name = 'Zebra reading task';
        $cm = $this->activity($course, ['name' => $name]);
        $this->activity($course, ['name' => 'Neighbouring task']);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $this->assertSame(1, substr_count($this->focus_bar($html), $name));
        $this->assertDoesNotMatchRegularExpression(
            '~<h2[^>]*>\s*' . preg_quote($name, '~') . '\s*</h2>~',
            $html
        );
        $container = strpos($html, '<div id="page"');
        $this->assertNotFalse($container);
        $this->assertStringNotContainsString($name, substr($html, $container));
    }

    /**
     * An activity name holding an ampersand is drawn once-escaped in the bar, never twice.
     *
     * The title leaves the exporter in the plain spelling because the template's double stash
     * escapes it itself; core's default escaping would hand the stash "A &amp; B" and the browser
     * would paint the entity text. A bare ampersand is the fixture because a tag-shaped name is
     * stripped identically in both spellings and would prove nothing.
     *
     * @return void
     */
    public function test_an_activity_name_with_an_ampersand_is_escaped_once_in_the_bar(): void {
        global $PAGE, $OUTPUT;

        $course = $this->course(true);
        $cm = $this->activity($course, ['name' => 'A & B']);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $found = preg_match('~<h1 class="dimensions-focusbar-title">(.*?)</h1>~s', $this->focus_bar($html), $title);
        $this->assertSame(1, $found, 'The bar has its title.');
        $this->assertSame('A &amp; B', $title[1]);
        $this->assertStringNotContainsString('&amp;amp;', $this->focus_bar($html));

        $exported = (new focusmode_bar($PAGE, []))->export_for_template($OUTPUT);
        $this->assertSame('A & B', $exported['title'], 'The exporter hands the double stash the plain spelling.');
    }

    /**
     * Under core's linear navigation the bar carries the completion status and core's footer the button.
     *
     * Moodle 5.3 moves the manual completion button out of the page header whenever it adds its
     * linear navigation footer (topics and weeks, on by default): the header gets the read-only
     * status core_course/completion_status, which core updates in place when the footer's button is
     * pressed, and the button is in the sticky footer core prints from before_footer_html_generation
     * (lib/classes/output/activity_header.php, core_courseformat\hook_listener). The bar reads the
     * header actions as before, so it carries that status; the footer reaches the focus page through
     * footer() like any other, so the button is still on the page, once, under the text.
     *
     * The control is the same activity without completion tracking: no button anywhere and no
     * status in the bar.
     *
     * @return void
     */
    public function test_the_bar_carries_the_status_and_cores_footer_the_button(): void {
        $course = $this->course(true);
        $cm = $this->activity($course, [
            'name' => 'Tracked task',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $this->assertTrue(
            \core_courseformat\local\linearnavigationsettings::is_linear_navigation_enabled($course),
            'Precondition: core\'s linear navigation is on for this course.'
        );

        [$header, $footer] = $this->render_halves($course, $cm);
        $bar = $this->focus_bar($header);
        $this->assertMatchesRegularExpression(
            '~<span class="activity-completion-status" data-region="completion-status" data-cmid="' . $cm->id . '"~',
            $bar
        );
        $this->assertStringNotContainsString('data-action="toggle-manual-completion"', $bar);
        $this->assertStringContainsString('course-linear-navigation-footer-content', $footer);
        $this->assertSame(1, substr_count($header . $footer, 'data-action="toggle-manual-completion"'));

        // The control: the same activity without completion tracking has neither.
        $plain = $this->activity($course, [
            'name' => 'Untracked task',
            'completion' => COMPLETION_TRACKING_NONE,
        ]);
        $html = $this->render($course, $plain);
        $this->assertStringNotContainsString('data-action="toggle-manual-completion"', $html);
        $this->assertStringNotContainsString('data-region="completion-status"', $this->focus_bar($html));
    }

    /**
     * Without linear navigation the manual completion button is in the bar, and it is the real one.
     *
     * The button is not built by this theme: it arrives as one of the page's header actions,
     * which core fills while the activity header exports itself. So this pins the whole route -
     * the header actions being read at all, and being read late enough to have been filled.
     *
     * @return void
     */
    public function test_the_manual_completion_button_is_in_the_bar(): void {
        $this->without_linear_navigation();
        $course = $this->course(true);
        $cm = $this->activity($course, [
            'name' => 'Tracked task',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);
        $this->assertStringContainsString('data-action="toggle-manual-completion"', $this->focus_bar($html));
        $this->assertStringNotContainsString('course-linear-navigation', $html, 'Precondition: core printed no footer.');

        // The control: the same activity without completion tracking has no button anywhere.
        $plain = $this->activity($course, [
            'name' => 'Untracked task',
            'completion' => COMPLETION_TRACKING_NONE,
        ]);
        $html = $this->render($course, $plain);
        $this->assertStringNotContainsString('data-action="toggle-manual-completion"', $html);
        $this->assertStringContainsString(self::BAR, $html);
    }

    /**
     * The course index drawer survives the reduced shell.
     *
     * It is the learner's way from one activity to the next without leaving the mode. The control
     * is a course format which uses no course index (social): the drawer's absence there is a
     * course-format fact and not a theme one, so a test asserting only the presence could not tell
     * the two apart.
     *
     * @return void
     */
    public function test_the_course_index_drawer_is_kept(): void {
        $course = $this->course(true, ['format' => 'topics']);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);
        $this->assertStringContainsString('theme_boost-drawers-courseindex', $html);
        $this->assertStringContainsString(self::BAR, $html);
        // Its controls too, in the block 5.3's theme_boost/drawer names drawercontrols.
        $this->assertStringContainsString('id="courseindexdrawercontrols"', $html);

        $social = $this->course(true, ['format' => 'social']);
        $socialcm = $this->activity($social);
        $this->setUser($this->getDataGenerator()->create_and_enrol($social, 'student'));

        $html = $this->render($social, $socialcm);
        $this->assertStringNotContainsString('theme_boost-drawers-courseindex', $html);
        $this->assertStringContainsString(self::BAR, $html);
    }

    /**
     * The block drawer survives the reduced shell.
     *
     * mod_book's table of contents, mod_quiz's question navigation and mod_lesson's menu are fake
     * blocks, so they reach the page only through the block drawer. The drawer is empty on a
     * mod_page, which is why this test adds a block rather than trusting the shell.
     *
     * @return void
     */
    public function test_the_block_drawer_is_kept(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->getDataGenerator()->create_block('online_users', [
            'parentcontextid' => \core\context\module::instance($cm->id)->id,
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $this->assertStringContainsString('theme_boost-drawers-blocks', $html);
        $this->assertStringContainsString(self::BAR, $html);
    }

    /**
     * The previous and next controls are rendered, which core alone would not do.
     *
     * Core returns an empty string for activity_navigation() whenever the theme and the format
     * both use a course index, which on a topics course is exactly this page. The controls exist
     * here only because the renderer flips that flag around core's own call, so their presence
     * is the flip. Core's linear navigation is switched off for topics, as a site may: with it on,
     * core's own footer is the route forward and this row is not drawn (the test below).
     *
     * @return void
     */
    public function test_prev_next_is_rendered_on_a_topics_course(): void {
        $this->without_linear_navigation();
        $course = $this->course(true);
        $first = $this->activity($course, ['name' => 'First task']);
        $this->activity($course, ['name' => 'Second task']);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        [, $footer] = $this->render_halves($course, $first);

        $this->assertStringContainsString('dimensions-focus-activitynav', $footer);
        $this->assertStringContainsString('activity-navigation', $footer);
        $this->assertStringContainsString('Second task', $footer);
    }

    /**
     * Where core draws its linear navigation, the page carries that one row forward and not this theme's too.
     *
     * Core 5.3 prints previous and next in the sticky footer it adds to an activity page of topics
     * and weeks; the focus page prints that footer as every page does, so the theme's own row would
     * be a second navigation under the text. The control is the same page with linear navigation
     * off, where the theme's row is the only one.
     *
     * @return void
     */
    public function test_cores_linear_navigation_stands_alone_under_the_text(): void {
        $course = $this->course(true);
        $first = $this->activity($course, ['name' => 'First task']);
        $this->activity($course, ['name' => 'Second task']);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        [, $footer] = $this->render_halves($course, $first);
        $this->assertSame(1, substr_count($footer, 'course-linear-navigation-footer-content'), 'Core\'s row, once.');
        $this->assertMatchesRegularExpression('~<div class="dimensions-focus-activitynav">\s*</div>~', $footer);
        $this->assertStringNotContainsString('class="mt-5 mb-1 activity-navigation', $footer);

        $this->without_linear_navigation();
        [, $footer] = $this->render_halves($course, $first);
        $this->assertStringNotContainsString('course-linear-navigation', $footer, 'Control: core printed no row.');
        $this->assertStringContainsString('class="mt-5 mb-1 activity-navigation', $footer, 'Control: the theme\'s row is drawn.');
    }

    /**
     * The widened reading column follows the person's own choice, then the administrator's.
     *
     * Read on the server so the class is right at first paint: a class the AMD module added
     * after load would show every learner the wrong measure for the length of one page load.
     *
     * @return void
     */
    public function test_the_expanded_class_follows_the_preference(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        set_config('focusmodedefaultwidth', focusmode::WIDTH_CONTRACTED, focusmode::COMPONENT);
        set_user_preference(focusmode::PREFERENCE_EXPANDED, 1);

        $body = $this->body_tag($this->render($course, $cm));
        $this->assertStringContainsString('dimensions-focusmode-expanded', $body);

        /* The control: with the preference gone the administrator's default decides, and the
           mode class stays - the two classes are separate, and only the second one moves. */
        unset_user_preference(focusmode::PREFERENCE_EXPANDED);

        $body = $this->body_tag($this->render($course, $cm));
        $this->assertStringNotContainsString('dimensions-focusmode-expanded', $body);
        $this->assertStringContainsString('dimensions-focusmode', $body);
    }

    /**
     * The width button keeps one accessible name in both states, and only aria-pressed changes.
     *
     * A toggle whose label also flips announces two contradictory states. The control is the
     * pressed state itself: the button is extracted from both renders and compared.
     *
     * @return void
     */
    public function test_the_width_button_keeps_one_label_and_aria_pressed_carries_the_state(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $label = get_string('focusmode_width', focusmode::COMPONENT);
        $button = function (string $html): string {
            $this->assertSame(
                1,
                preg_match('~<button[^>]*data-action="dimensions-focus-width".*?</button>~s', $this->focus_bar($html), $found),
                'The bar has its width button.'
            );
            return $found[0];
        };

        set_config('focusmodedefaultwidth', focusmode::WIDTH_CONTRACTED, focusmode::COMPONENT);
        unset_user_preference(focusmode::PREFERENCE_EXPANDED);
        $contracted = $button($this->render($course, $cm));
        set_user_preference(focusmode::PREFERENCE_EXPANDED, 1);
        $expanded = $button($this->render($course, $cm));

        // Precondition: the two renders really are the two states.
        $this->assertStringContainsString('aria-pressed="false"', $contracted);
        $this->assertStringContainsString('aria-pressed="true"', $expanded);
        foreach ([$contracted, $expanded] as $html) {
            $this->assertStringContainsString('title="' . $label . '"', $html);
            $this->assertStringContainsString('>' . $label . '</span>', $html);
        }
    }

    /**
     * The bar offers the overflow menu to somebody who has something to put in it.
     *
     * A teacher with editing off has at least the edit switch, which is the control that gets
     * them back to the ordinary theme; a learner on a plain activity page has one secondary
     * navigation node and nothing else, and an empty dropdown is worse than no dropdown.
     *
     * @return void
     */
    public function test_the_bar_carries_the_overflow_menu_for_a_teacher(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $bar = $this->focus_bar($this->render($course, $cm));
        $this->assertStringContainsString('dimensions-focusbar-more', $bar);

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $bar = $this->focus_bar($this->render($course, $cm));
        $this->assertStringNotContainsString('dimensions-focusbar-more', $bar);
    }

    /**
     * The completion conditions are one line under the content, and never the box again.
     *
     * Two controls, because the risk is not that the line is missing but that it grows back into
     * the activity information box the design removed: the box's own region marker must be gone,
     * and so must the activity dates it carries. The third control is an activity with no
     * completion at all, where the whole line has to disappear rather than render empty.
     *
     * The line's position is asserted through the split rather than through offsets: header()
     * cuts the page exactly where the activity's own content goes, so "in the footer half" is
     * "below the content", stated in terms core itself defines.
     *
     * @return void
     */
    public function test_the_completion_conditions_are_one_line_at_the_card_foot(): void {
        $course = $this->course(true);
        $cm = $this->activity($course, [
            'name' => 'Automatic task',
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        [$header, $footer] = $this->render_halves($course, $cm);
        $whole = $header . $footer;

        $this->assertSame(1, substr_count($footer, 'dimensions-focus-completion-list'));
        $this->assertStringContainsString('automatic-completion-is-incomplete', $footer);
        $kicker = get_string('focusmode_completionkicker', focusmode::COMPONENT);
        $this->assertStringContainsString($kicker, $footer);
        $this->assertStringNotContainsString('dimensions-focus-completion', $header);

        /* The two controls, scoped to the reading column: the bar may legitimately carry a
           date among its header actions on an activity which has one, and it is the box coming
           back into the content that this test exists to refuse. */
        $column = substr($whole, strpos($whole, 'id="region-main"'));
        $this->assertStringNotContainsString('data-region="activity-information"', $column);
        $this->assertStringNotContainsString('data-region="activity-dates"', $column);

        // The third control: no completion, no line - not an empty one.
        $plain = $this->activity($course, [
            'name' => 'Plain task',
            'completion' => COMPLETION_TRACKING_NONE,
        ]);
        $html = $this->render($course, $plain);
        $this->assertStringNotContainsString('dimensions-focus-completion', $html);
        $this->assertStringContainsString(self::BAR, $html);
    }

    /**
     * The activity description is still in the reading column, and the box around it is not.
     *
     * Since Moodle 4.0 a module's intro is printed by the activity header, inside
     * core_course/activity_info - the same template that draws the activity information box. So
     * the key focus mode rewrites carries both, and blanking it would drop the intro of every page,
     * forum and assignment along with the box, while the page still looked correct.
     *
     * The fixture is a mod_page with printintro on, which is the shape a reader actually meets:
     * the intro is what mod_page prints above its content. The two assertions are each other's
     * control - the description proves the header content was exported and reached the column, so
     * the absent region marker means the box was removed rather than nothing having rendered.
     *
     * @return void
     */
    public function test_the_activity_description_is_kept_in_the_reading_column(): void {
        global $DB;

        $course = $this->course(true);
        $intro = 'Read the vital signs protocol before the practical session.';
        $cm = $this->activity($course, [
            'name' => 'Described task',
            'intro' => '<p>' . $intro . '</p>',
            'introformat' => FORMAT_HTML,
            'printintro' => 1,
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $this->assertStringContainsString(self::BAR, $html);
        $column = substr($html, strpos($html, 'id="region-main"'));
        $this->assertStringContainsString($intro, $column);
        $this->assertStringContainsString('dimensions-focus-description', $column);
        $this->assertStringNotContainsString('data-region="activity-information"', $column);

        /* The control: an activity with no intro renders no wrapper rather than an empty one.
           The empty intro is written afterwards because the module generator refuses to make
           one - `empty($record->intro)` is true of the empty string, so it substitutes its own
           "Test page N" and the control would have tested nothing. The header reads the record
           straight from the database (moodle_page::load_activity_record()), so no cache rebuild
           is involved. */
        $bare = $this->activity($course, ['name' => 'Bare task']);
        $DB->set_field('page', 'intro', '', ['id' => $bare->instance]);

        $html = $this->render($course, $bare);
        $this->assertStringNotContainsString('dimensions-focus-description', $html);
        $this->assertStringContainsString(self::BAR, $html);
    }

    /**
     * On an ordinary activity page the way into focus mode sits left of the notifications bell.
     *
     * The assertion is on the order and not on the presence: core_renderer::navbar_plugin_output()
     * appends plugin output in plugin-type order, and message_popup (type message) draws the bell
     * before any theme's callback runs, so a theme callback in lib.php would land the button on
     * the right of it, the one position the design rules out. Only the splice can put it here,
     * and only an ordered assertion can tell the two apart.
     *
     * @return void
     */
    public function test_the_navbar_button_precedes_the_notifications_container(): void {
        global $USER;

        $course = $this->course(false);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $entry = strpos($html, 'dimensions-focus-entry');
        $bell = strpos($html, 'nav-notification-popover-container');
        $this->assertNotFalse($entry, 'The way into focus mode is missing from the navbar.');
        $this->assertNotFalse($bell, 'The notifications container is missing, so the order proves nothing.');
        $this->assertLessThan($bell, $entry);

        /* The control, in this configuration: a page the predicate refuses to offer entry on
           carries no button. Editing is the clause used here; the already-focused course is the
           control of the test below, so between them both refusals are covered with the
           notifications container present and absent. */
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $USER->editing = 1;

        $html = $this->render($course, $cm);
        $this->assertStringNotContainsString('dimensions-focus-entry', $html);
        $this->assertStringContainsString(self::BU_NAVBAR_MARKER, $html);
    }

    /**
     * With notifications switched off the button is prepended, and is still the first thing.
     *
     * The id the splice looks for is gone in this configuration, and the fallback must not be
     * "append": appending would put the button back on the right of the messages drawer toggle.
     * So the assertion is again an order, against whatever plugin output survives.
     *
     * @return void
     */
    public function test_the_navbar_button_is_prepended_when_message_popup_is_disabled(): void {
        $course = $this->course(false);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        set_config('messaging', 1);
        \core\plugininfo\message::enable_plugin('popup', 0);

        $html = $this->render($course, $cm);

        $this->assertStringNotContainsString('nav-notification-popover-container', $html);
        $entry = strpos($html, 'dimensions-focus-entry');
        $messages = strpos($html, 'data-region="popover-region-messages"');
        $this->assertNotFalse($entry, 'The way into focus mode is missing from the navbar.');
        $this->assertNotFalse($messages, 'The messages toggle is missing, so the order proves nothing.');
        $this->assertLessThan($messages, $entry);

        // The control, in this configuration: a course which is already focused offers no way in.
        $focused = $this->course(true);
        $focusedcm = $this->activity($focused);
        $this->setUser($this->getDataGenerator()->create_and_enrol($focused, 'student'));

        $html = $this->render($focused, $focusedcm);
        $this->assertStringNotContainsString('dimensions-focus-entry', $html);
        $this->assertStringContainsString(self::BAR, $html);
    }
}
