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
 * Theme Dimensions - Tests for the two skins of an activity page as the renderer draws them
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
use theme_dimensions\local\yesno;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the two skins of an activity page, rendered through the real layout.
 *
 * Every test here renders the whole page - Boost Union's layout file and template, untouched, and
 * this theme's renderer adding to them - because the properties that matter are only visible in the
 * output: which body classes the first paint carries, that the bar is there exactly once on every
 * page that can switch in place and nowhere else, that the header actions are drawn once and in the
 * skin the page opens in, that the skip link still precedes the bar, and that the foot of the content
 * is drawn. The predicate itself is covered by {@see \theme_dimensions\local\focusmode_test}; what
 * is shown or hidden under the body class is {@see \theme_dimensions\local\focusmode_scss_test}'s.
 *
 * Boost Union's navbar is on every one of these pages, focused or not, so it is the control that
 * the page rendered at all: {@see self::BU_NAVBAR_MARKER}. Without it "the bar is absent" and
 * "nothing was rendered" are the same assertion.
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
     * The id of the wrapper Boost Union's theme_boost/navbar template draws around the user menu, in
     * both skins.
     */
    private const BU_NAVBAR_MARKER = 'id="usernavigation"';

    /** @var string The bar's own root class, from the markup contract. */
    private const BAR = 'dimensions-focusbar';

    /** @var string The opening of every fixed bar on the site, ours and Boost Union's alike. */
    private const FIXED_BAR = 'class="navbar fixed-top';

    /** @var string The body class of the focused skin. */
    private const FOCUSED = 'dimensions-focusmode';

    /** @var string The body class of every page that can switch in place. */
    private const FOCUSABLE = 'dimensions-focusable';

    /** @var string The hook of the manual completion button, core's own. */
    private const MANUAL_TOGGLE = 'data-action="toggle-manual-completion"';

    /** @var string The page title, deliberately free of any activity name. */
    private const PAGE_TITLE = 'Rendered by the focus mode test';

    /**
     * Start every test with empty request caches and completion enabled on the site.
     *
     * Whether a topics course shows previous and next is decided by core's linear navigation, on by
     * default; the tests about this theme's own row switch it off for topics and say so.
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
        // The switch ships off; the tests about it set it back.
        set_config('focusmode', yesno::YES, focusmode::COMPONENT);
    }

    /**
     * Switch core's linear navigation off for the topics format, the courses these tests create.
     *
     * With it off the manual completion button is a header action, and core prints no previous and
     * next anywhere on a format with a course index.
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
     * The order, the explicit render target and the global $OUTPUT are core_renderer_test::renderer()'s
     * (see there), adapted for an activity page.
     *
     * The page itself is replaced too: header() may be called once per moodle_page, and several
     * tests below render a control beside the case they are about. Core's own
     * primary_extend_test replaces the global page the same way.
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
     * The classes of the body tag, as a list.
     *
     * Read as a list and never by substring: dimensions-focusmode is a prefix of
     * dimensions-focusmode-expanded, so a substring test passes on the wrong class.
     *
     * @param string $html The rendered page.
     * @return array The class names.
     */
    private function body_classes(string $html): array {
        $found = preg_match('~\bclass="([^"]*)"~', $this->body_tag($html), $matches);
        $this->assertSame(1, $found, 'The body tag has no class attribute.');
        return preg_split('/\s+/', trim($matches[1]));
    }

    /**
     * Core's page header's header actions container alone, cut out of a rendered page.
     *
     * @param string $html The rendered page.
     * @return string The container's markup, up to the header extras container that follows it.
     */
    private function page_header_actions(string $html): string {
        $start = strpos($html, 'data-region="header-actions-container"');
        $this->assertNotFalse($start, 'Core\'s page header has no header actions container.');
        $end = strpos($html, 'data-region="header-extras-container"', $start);
        $this->assertNotFalse($end);
        return substr($html, $start, $end - $start);
    }

    /**
     * A course which switched focus mode on opens focused, with the bar beside Boost Union's navbar.
     *
     * Both skins are in the page: Boost Union's layout renders untouched and the bar is added to it,
     * once. The body classes are what the stylesheet shows one or the other by; the count of fixed
     * bars is the bar's and the navbar's, and nothing more.
     *
     * @return void
     */
    public function test_a_switched_on_course_opens_focused_beside_boost_unions_navbar(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $classes = $this->body_classes($html);
        $this->assertContains(self::FOCUSED, $classes);
        $this->assertContains(self::FOCUSABLE, $classes);
        $this->assertSame(1, substr_count($html, '<nav class="navbar fixed-top ' . self::BAR), 'The bar, once.');
        $this->assertStringContainsString(self::BU_NAVBAR_MARKER, $html, 'Boost Union\'s navbar renders too.');
        $this->assertSame(2, substr_count($html, self::FIXED_BAR), 'The bar and Boost Union\'s navbar.');
        $this->assertStringContainsString('dimensions-focus-only', $this->focus_bar($html));
    }

    /**
     * A course which did not switch focus mode on opens ordinary, with the bar and the way in ready.
     *
     * @return void
     */
    public function test_an_unswitched_course_opens_ordinary_with_the_bar_ready(): void {
        $course = $this->course(false);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $classes = $this->body_classes($html);
        $this->assertNotContains(self::FOCUSED, $classes);
        $this->assertContains(self::FOCUSABLE, $classes);
        $this->assertStringContainsString(self::BAR, $html);
        $this->assertStringContainsString('dimensions-focus-entry', $html);
        $this->assertStringContainsString(self::BU_NAVBAR_MARKER, $html);
    }

    /**
     * Leaving a switched-on course in place makes its next page open ordinary, for the session.
     *
     * The control is the same page before the choice, focused.
     *
     * @return void
     */
    public function test_a_course_left_in_place_opens_ordinary_for_the_session(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $this->assertContains(self::FOCUSED, $this->body_classes($this->render($course, $cm)), 'Control.');

        focusmode::choose((int) $course->id, false);

        $html = $this->render($course, $cm);
        $this->assertNotContains(self::FOCUSED, $this->body_classes($html));
        $this->assertContains(self::FOCUSABLE, $this->body_classes($html));
        $this->assertStringContainsString(self::BAR, $html);
    }

    /**
     * The site-wide master switch off loads nothing of the feature, whatever the course says.
     *
     * No bar, no body class, no way in and no module.
     *
     * @return void
     */
    public function test_the_master_switch_off_loads_nothing_of_the_feature(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        set_config('focusmode', yesno::NO, focusmode::COMPONENT);

        $html = $this->render($course, $cm);

        $this->assertStringNotContainsString(self::BAR, $html);
        $this->assertStringNotContainsString('dimensions-focus', $this->body_tag($html));
        $this->assertStringNotContainsString('dimensions-focus-entry', $html);
        $this->assertStringNotContainsString('theme_dimensions/focusmode', $html);
        $this->assertStringContainsString(self::BU_NAVBAR_MARKER, $html);
    }

    /**
     * The support parameter gives one page load of the ordinary theme, with nothing of focus mode in it.
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
        $this->assertStringNotContainsString('dimensions-focus', $this->body_tag($html));
        $this->assertStringContainsString(self::BU_NAVBAR_MARKER, $html);
    }

    /**
     * A teacher who turned editing on gets the whole theme back, with nothing of focus mode in it.
     *
     * The edit switch lives in the navbar focus mode hides, so a focus mode which survived
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
        $this->assertStringNotContainsString('dimensions-focus', $this->body_tag($html));
        $this->assertStringContainsString(self::BU_NAVBAR_MARKER, $html);
    }

    /**
     * The module is armed with the skin the page opened in, on both skins.
     *
     * The module is what turns the two forms into a switch in place; armed with the wrong first
     * state, the first press would switch to the skin already shown.
     *
     * @return void
     */
    public function test_the_module_is_armed_with_the_first_paint(): void {
        $focused = $this->course(true);
        $cm = $this->activity($focused);
        $this->setUser($this->getDataGenerator()->create_and_enrol($focused, 'student'));
        $html = $this->render($focused, $cm);
        $this->assertMatchesRegularExpression('~theme_dimensions/focusmode.*?"active":true~s', $html);

        $ordinary = $this->course(false);
        $cm = $this->activity($ordinary);
        $this->setUser($this->getDataGenerator()->create_and_enrol($ordinary, 'student'));
        $html = $this->render($ordinary, $cm);
        $this->assertMatchesRegularExpression('~theme_dimensions/focusmode.*?"active":false~s', $html);
    }

    /**
     * The anchor the skip link points at is on the page exactly once.
     *
     * Once, not "at least once": the anchor is core's, it comes from the activity header partial,
     * and anything of focus mode's that also emitted one would give the browser two elements with
     * the same id and the skip link a coin toss.
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
     * Both reach the page through output.standard_top_of_body_html, the bar appended to core's
     * output. Printed before it, a keyboard user would tab through the bar's controls on every
     * activity before the skip link.
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
        $navbar = strpos($html, self::BU_NAVBAR_MARKER);
        $this->assertNotFalse($skip, 'Core\'s skip link is missing from the focused page.');
        $this->assertNotFalse($bar);
        $this->assertLessThan($bar, $skip);
        $this->assertLessThan($navbar, $bar, 'The bar comes before Boost Union\'s navbar.');
    }

    /**
     * The status line that announces a switch is outside the bar, so it speaks in both skins.
     *
     * A live region inside an element that is not displayed announces nothing, and the bar is not
     * displayed in the ordinary skin.
     *
     * @return void
     */
    public function test_the_status_line_is_outside_the_bar(): void {
        $course = $this->course(false);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $this->assertStringContainsString(
            '<div class="visually-hidden" aria-live="polite" data-region="dimensions-focus-status"></div>',
            $html
        );
        $this->assertStringNotContainsString('dimensions-focus-status', $this->focus_bar($html));
    }

    /**
     * The activity name is in the bar once, and never as a second heading in the reading column.
     *
     * Not a whole-page count: the course index and Boost Union's breadcrumb name the activity too.
     * What must not happen is a second copy in the reading column, in the heading the activity
     * header partial draws. Boost and Boost Union export that title empty (activityheaderconfig
     * notitle), so no code in this theme prevents it; this test is the sentinel for a parent theme
     * that stops doing so.
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
     * linear navigation footer (topics and weeks, on by default): the header actions get the
     * read-only status core_course/completion_status, which core updates in place when the footer's
     * button is pressed, and the button is in the sticky footer core prints from
     * before_footer_html_generation. On a page that opens focused the status is in the bar and not
     * in the page header too; the button is on the page once, under the text.
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
        $status = '~<span class="activity-completion-status" data-region="completion-status" data-cmid="' . $cm->id . '"~';
        $this->assertMatchesRegularExpression($status, $this->focus_bar($header));
        $this->assertDoesNotMatchRegularExpression($status, $this->page_header_actions($header), 'Not drawn twice.');
        $this->assertStringNotContainsString(self::MANUAL_TOGGLE, $this->focus_bar($header));
        $this->assertStringContainsString('course-linear-navigation-footer-content', $footer);
        $this->assertSame(1, substr_count($header . $footer, self::MANUAL_TOGGLE));

        // The control: the same activity without completion tracking has neither.
        $plain = $this->activity($course, [
            'name' => 'Untracked task',
            'completion' => COMPLETION_TRACKING_NONE,
        ]);
        $html = $this->render($course, $plain);
        $this->assertStringNotContainsString(self::MANUAL_TOGGLE, $html);
        $this->assertStringNotContainsString('data-region="completion-status"', $this->focus_bar($html));
    }

    /**
     * The manual completion button is drawn once, in the skin the page opens in.
     *
     * The button is not built by this theme: it arrives as one of the page's header actions, which
     * core fills while the activity header exports itself. Focused, the bar takes it and the page
     * header leaves it out; ordinary, the page header draws it and the bar's slot is empty. The
     * module then moves the one node; a hidden second copy would go stale when one was pressed.
     *
     * @return void
     */
    public function test_the_manual_completion_button_is_drawn_once_in_the_first_skin(): void {
        $this->without_linear_navigation();
        $course = $this->course(true);
        $cm = $this->activity($course, [
            'name' => 'Tracked task',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);
        $this->assertStringNotContainsString('course-linear-navigation', $html, 'Precondition: core printed no footer.');
        $this->assertStringContainsString(self::MANUAL_TOGGLE, $this->focus_bar($html));
        $this->assertStringNotContainsString(self::MANUAL_TOGGLE, $this->page_header_actions($html));
        $this->assertSame(1, substr_count($html, self::MANUAL_TOGGLE));

        // Ordinary first paint: the page header draws it, the bar's slot is empty.
        focusmode::choose((int) $course->id, false);
        $html = $this->render($course, $cm);
        $this->assertStringContainsString(self::MANUAL_TOGGLE, $this->page_header_actions($html));
        $this->assertStringNotContainsString(self::MANUAL_TOGGLE, $this->focus_bar($html));
        $this->assertStringContainsString('data-region="dimensions-focus-headeractions"></div>', $this->focus_bar($html));
        $this->assertSame(1, substr_count($html, self::MANUAL_TOGGLE));

        // The control: the same activity without completion tracking has no button anywhere.
        $plain = $this->activity($course, [
            'name' => 'Untracked task',
            'completion' => COMPLETION_TRACKING_NONE,
        ]);
        $html = $this->render($course, $plain);
        $this->assertStringNotContainsString(self::MANUAL_TOGGLE, $html);
        $this->assertStringContainsString(self::BAR, $html);
    }

    /**
     * A header action the page header adds itself stays there, and the page keeps every action.
     *
     * The page header is drawn with the bar's actions withdrawn around the parent's call; what the
     * parent appends - the region main settings menu - must survive the call, and the actions the
     * bar took must be back on the page afterwards, for anything that reads them later.
     *
     * @return void
     */
    public function test_the_header_actions_are_put_back_after_the_page_header(): void {
        global $PAGE;

        $this->without_linear_navigation();
        $course = $this->course(true);
        $cm = $this->activity($course, ['completion' => COMPLETION_TRACKING_MANUAL]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $renderer = $this->renderer($course, $cm);
        $renderer->header();

        $actions = $PAGE->get_header_actions();
        $this->assertNotEmpty($actions);
        $this->assertStringContainsString(self::MANUAL_TOGGLE, $actions[0], 'The bar\'s action is back, first.');
    }

    /**
     * The previous and next controls are drawn in the foot, which core alone would not do.
     *
     * Core returns an empty string for activity_navigation() whenever the theme and the format
     * both use a course index, which on a topics course is exactly this page. The controls exist
     * here only because the renderer flips that flag around core's own call, so their presence
     * is the flip. They are in the focus-only foot: the ordinary skin keeps core's own answer.
     * Core's linear navigation is switched off for topics, as a site may: with it on, core's own
     * footer is the route forward and this row is not drawn (the test below).
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

        $this->assertMatchesRegularExpression(
            '~<div class="dimensions-focus-only dimensions-focus-foot">.*?dimensions-focus-activitynav.*?Second task~s',
            $footer
        );
    }

    /**
     * Where core draws its linear navigation, the page carries that one row forward and not this theme's too.
     *
     * Core 5.3 prints previous and next in the sticky footer it adds to an activity page of topics
     * and weeks, in either skin, so the theme's own row would be a second navigation under the text.
     * The control is the same page with linear navigation off, where the theme's row is the only one.
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
        $this->assertStringNotContainsString('dimensions-focus-activitynav', $footer);
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

        $this->assertContains('dimensions-focusmode-expanded', $this->body_classes($this->render($course, $cm)));

        /* The control: with the preference gone the administrator's default decides, and the
           mode class stays - the two classes are separate, and only the second one moves. */
        unset_user_preference(focusmode::PREFERENCE_EXPANDED);

        $classes = $this->body_classes($this->render($course, $cm));
        $this->assertNotContains('dimensions-focusmode-expanded', $classes);
        $this->assertContains(self::FOCUSED, $classes);
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
     * The completion conditions are one line in the foot of the content, and core's box keeps them too.
     *
     * The foot draws them as one discreet line, through core's own partial, in a
     * container only the focused skin shows, while core's activity information box keeps its own
     * statement of them for the ordinary skin (the stylesheet hides that half under the body class).
     * The control is an activity with no completion at all, where the whole line has to disappear
     * rather than render empty.
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

        $this->assertSame(1, substr_count($footer, 'dimensions-focus-completion-list'));
        $this->assertMatchesRegularExpression(
            '~<div class="dimensions-focus-only dimensions-focus-foot">\s*<div class="dimensions-focus-completion">~',
            $footer
        );
        $this->assertStringContainsString('automatic-completion-is-incomplete', $footer);
        $this->assertStringContainsString(get_string('focusmode_completionkicker', focusmode::COMPONENT), $footer);
        $this->assertStringNotContainsString('dimensions-focus-completion', $header);
        $this->assertStringContainsString('data-region="completion-info"', $header, 'Core\'s box is kept.');

        // The control: no completion, no line - not an empty one.
        $plain = $this->activity($course, [
            'name' => 'Plain task',
            'completion' => COMPLETION_TRACKING_NONE,
        ]);
        $html = $this->render($course, $plain);
        $this->assertStringNotContainsString('dimensions-focus-completion', $html);
        $this->assertStringContainsString(self::BAR, $html);
    }

    /**
     * The activity description is printed once, by core's own box, in either skin.
     *
     * Since Moodle 4.0 a module's intro is printed by the activity header, inside
     * core_course/activity_info, so the box is kept and the stylesheet reduces it to the
     * description under the body class. Nothing of focus mode prints a second copy.
     *
     * @return void
     */
    public function test_the_activity_description_is_printed_once_by_cores_box(): void {
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

        $this->assertContains(self::FOCUSED, $this->body_classes($html));
        $column = substr($html, strpos($html, 'id="region-main"'));
        $this->assertSame(1, substr_count($column, $intro));
        $this->assertMatchesRegularExpression('~data-region="activity-information".*?id="intro"~s', $column);
    }

    /**
     * Every exit leaves in place, but a single activity course's that switched focus mode on.
     *
     * In place, the exit is the leave form the module intercepts (data-action dimensions-focus-leave),
     * on a topics course that switched focus mode on as on a manual session. A single activity course
     * that switched it on has no ordinary page to go back to: its exit is the plain link
     * to the site home page. A single activity course that did not switch it on is the control.
     *
     * @return void
     */
    public function test_the_exit_leaves_in_place_but_on_a_forced_single_activity_course(): void {
        $topics = $this->course(true);
        $cm = $this->activity($topics);
        $this->setUser($this->getDataGenerator()->create_and_enrol($topics, 'student'));
        $bar = $this->focus_bar($this->render($topics, $cm));
        $this->assertStringContainsString('data-action="dimensions-focus-leave"', $bar);
        $this->assertStringNotContainsString('data-action="dimensions-focus-exit"', $bar);

        $single = $this->course(true, ['format' => 'singleactivity', 'activitytype' => 'page']);
        $cm = $this->activity($single);
        $this->setUser($this->getDataGenerator()->create_and_enrol($single, 'student'));
        $bar = $this->focus_bar($this->render($single, $cm, '/mod/page/view.php'));
        $home = (new \moodle_url('/', ['redirect' => 0]))->out(false);
        $link = '<a class="btn dimensions-focusbar-close" href="' . s($home) . '" data-action="dimensions-focus-exit"';
        $this->assertStringContainsString($link, $bar);
        $this->assertStringNotContainsString('data-action="dimensions-focus-leave"', $bar);

        $plain = $this->course(false, ['format' => 'singleactivity', 'activitytype' => 'page']);
        $cm = $this->activity($plain);
        $this->setUser($this->getDataGenerator()->create_and_enrol($plain, 'student'));
        focusmode::choose((int) $plain->id, true);
        $bar = $this->focus_bar($this->render($plain, $cm));
        $this->assertStringContainsString('data-action="dimensions-focus-leave"', $bar, 'Control: not forced, in place.');
    }

    /**
     * The way into focus mode is in the navbar of a page that opens focused too, for the way back.
     *
     * The navbar is hidden in the focused skin, so the button is unseen until the reader leaves
     * in place; it is what the focus moves to then.
     *
     * @return void
     */
    public function test_a_focused_page_carries_the_way_back_in(): void {
        $course = $this->course(true);
        $cm = $this->activity($course);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->render($course, $cm);

        $this->assertContains(self::FOCUSED, $this->body_classes($html));
        $this->assertStringContainsString('data-action="dimensions-focus-enter"', $html);
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

        /* The control, in this configuration: a page that cannot switch in place carries no
           button. Editing is the clause used here, the master switch the control of the test below,
           so between them both refusals are covered with the notifications container present and
           absent. */
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

        // The control, in this configuration: with the master switch off there is no way in.
        set_config('focusmode', yesno::NO, focusmode::COMPONENT);

        $html = $this->render($course, $cm);
        $this->assertStringNotContainsString('dimensions-focus-entry', $html);
        $this->assertStringContainsString('data-region="popover-region-messages"', $html);
    }
}
