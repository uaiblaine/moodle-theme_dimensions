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
 * Theme Dimensions - Tests for the navbar that follows the reading, as the page draws it
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\focusmode;
use theme_dimensions\local\guestnav;
use theme_dimensions\local\readingbar;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Renders the real page header - Boost Union's layout, its navbar and the theme's renderer - of an activity page and
 * a course page, because where the context layer lands, what the body carries and what the client module is handed
 * are only visible in the output.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(core_renderer::class)]
#[CoversClass(readingbar::class)]
final class readingbar_render_test extends \advanced_testcase {
    /** @var string The context layer's root, as the template writes it. */
    private const LAYER = 'data-region="dimensions-ctxbar"';

    /** @var string The client module's name in a queued AMD call. */
    private const MODULE = 'theme_dimensions/readingbar';

    /**
     * Completion on for the site, and nothing of a previous test's focus mode.
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
     * Drop the focus mode caches: they are process-wide.
     *
     * @return void
     */
    protected function tearDown(): void {
        focusmode::reset_caches();
        parent::tearDown();
    }

    /**
     * A topics course with completion and one tracked page activity.
     *
     * @return array The course record and the activity.
     */
    private function course(): array {
        $course = self::getDataGenerator()->create_course([
            'fullname' => 'Competency management',
            'format' => 'topics',
            'enablecompletion' => 1,
        ]);
        $module = self::getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Reading two',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        return [$course, get_fast_modinfo($course)->get_cm($module->cmid)];
    }

    /**
     * The real header of an activity page, as require_login() leaves the page.
     *
     * The page is a new global each time: header() may run once per moodle_page (core replaces the global the same
     * way in its own header tests). The course goes in before the theme is forced, and the render target is explicit
     * because PHPUnit runs as a CLI script (core_renderer_test::renderer() says why).
     *
     * @param \stdClass $course The course.
     * @param \cm_info $cm The activity.
     * @return string The header.
     */
    private function activity_header(\stdClass $course, \cm_info $cm): string {
        global $PAGE, $OUTPUT;

        $PAGE = new \moodle_page();
        $PAGE->set_context(\core\context\module::instance($cm->id));
        $PAGE->set_course($course);
        $PAGE->set_cm($cm, $course);
        $PAGE->set_url('/mod/page/view.php', ['id' => $cm->id]);
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title('Reading two');
        $PAGE->set_heading($course->fullname);
        $PAGE->force_theme('dimensions');
        $PAGE->initialise_theme_and_output();
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertInstanceOf(core_renderer::class, $OUTPUT);

        return $OUTPUT->header();
    }

    /**
     * The real header of a course's home page, as course/view.php sets it up.
     *
     * @param \stdClass $course The course.
     * @return string The header.
     */
    private function course_header(\stdClass $course): string {
        global $PAGE, $OUTPUT;

        $PAGE = new \moodle_page();
        $PAGE->set_context(\core\context\course::instance($course->id));
        $PAGE->set_course($course);
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_pagelayout('course');
        $PAGE->set_pagetype('course-view-' . $course->format);
        $PAGE->set_title($course->fullname);
        $PAGE->set_heading($course->fullname);
        $PAGE->force_theme('dimensions');
        $PAGE->initialise_theme_and_output();
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertInstanceOf(core_renderer::class, $OUTPUT);

        return $OUTPUT->header();
    }

    /**
     * The navbar's markup, from its opening tag to the mobile drawer Boost Union's navbar template includes after it.
     *
     * Not to the first closing nav: the primary navigation inside the bar is a nav of its own.
     *
     * @param string $html A header.
     * @return string
     */
    private function navbar(string $html): string {
        $start = strpos($html, '<nav class="navbar fixed-top');
        $this->assertNotFalse($start, 'The page draws Boost Union\'s navbar.');
        $end = strpos($html, 'id="theme_boost-drawers-primary"', $start);
        $this->assertNotFalse($end, 'The mobile drawer follows the navbar.');

        return substr($html, $start, $end - $start);
    }

    /**
     * The body tag.
     *
     * @param string $html A header.
     * @return string
     */
    private function body_tag(string $html): string {
        $this->assertSame(1, preg_match('~<body\b[^>]*>~', $html, $matches), 'The page has a body tag.');

        return $matches[0];
    }

    /**
     * The client module's arguments, as the page queued them; null when it was not armed.
     *
     * A header rendered under PHPUnit prints no footer, so the call is read from the requirements manager.
     *
     * @return array|null
     */
    private function client_config(): ?array {
        global $PAGE;

        $property = new \ReflectionProperty($PAGE->requires, 'amdjscode');
        $calls = implode("\n", (array) $property->getValue($PAGE->requires));
        $opening = "require(['" . self::MODULE . "'], function(amd) {amd.init(";
        $count = substr_count($calls, $opening);
        if ($count === 0) {
            return null;
        }
        $this->assertSame(1, $count, 'The module is armed once.');
        $at = strpos($calls, $opening) + strlen($opening);
        $found = preg_match('~^(\{.*?\})\); M\.util\.js_complete~s', substr($calls, $at), $matches);
        $this->assertSame(1, $found, 'The call carries its one argument.');
        $config = json_decode($matches[1], true);
        $this->assertIsArray($config);

        return $config;
    }

    /**
     * An activity page draws the context layer inside Boost Union's navbar, with the activity's name, the course
     * index control on core's attributes and the status core gives; the body carries the band's track and the
     * module is armed for the activity.
     *
     * @return void
     */
    public function test_an_activity_page_draws_the_layer_in_the_navbar(): void {
        [$course, $cm] = $this->course();
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->activity_header($course, $cm);
        $navbar = $this->navbar($html);

        $this->assertSame(1, substr_count($html, self::LAYER), 'One layer on the page.');
        $this->assertMatchesRegularExpression(
            '~id="usernavigation".*' . preg_quote(self::LAYER, '~') . '~s',
            $navbar,
            'Printed inside #usernavigation, where the plugin output goes.'
        );
        $this->assertMatchesRegularExpression(
            '~<span class="dimensions-ctxbar-title" data-region="dimensions-ctxbar-title">Reading two</span>~',
            $navbar
        );
        $this->assertStringContainsString('Competency management · ', $navbar);
        $this->assertMatchesRegularExpression(
            '~<button type="button" class="btn dimensions-ctxbar-index" data-region="dimensions-ctxbar-index"\s+'
                . 'data-toggler="drawers" data-action="toggle" data-target="theme_boost-drawers-courseindex"~',
            $navbar
        );
        $this->assertStringContainsString('id="theme_boost-drawers-courseindex"', $html, 'The drawer the control opens is drawn.');
        $this->assertMatchesRegularExpression(
            '~data-region="dimensions-ctxbar-status" data-cmid="' . $cm->id . '" data-state="pending"~',
            $navbar
        );
        $this->assertStringContainsString(readingbar::BODY_READING, $this->body_tag($html));
        $this->assertStringNotContainsString(readingbar::BODY_AUTOHIDE, $this->body_tag($html), 'Auto-hide is off by default.');

        $config = $this->client_config();
        $this->assertSame(
            ['kind' => 'activity', 'progress' => null, 'autohide' => false, 'behat' => false],
            $config
        );
    }

    /**
     * With the context bar off the layer goes and the band stays: the module is still armed and the body still
     * carries the track.
     *
     * @return void
     */
    public function test_the_context_bar_switched_off_leaves_the_band(): void {
        [$course, $cm] = $this->course();
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'student'));
        set_config(readingbar::SETTING_CONTEXT, '0', readingbar::COMPONENT);

        $html = $this->activity_header($course, $cm);
        $this->assertStringContainsString('id="usernavigation"', $html, 'Control: the navbar is drawn.');
        $this->assertStringNotContainsString(self::LAYER, $html);
        $this->assertStringContainsString(readingbar::BODY_READING, $this->body_tag($html));
        $this->assertSame('activity', $this->client_config()['kind']);
    }

    /**
     * The course page draws the course's layer with the reader's completion, and hands the module the percentage.
     *
     * @return void
     */
    public function test_the_course_page_draws_the_course_and_hands_the_band_its_completion(): void {
        [$course, $cm] = $this->course();
        $student = self::getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        (new \completion_info($course))->update_state($cm, COMPLETION_COMPLETE, (int) $student->id);

        $html = $this->course_header($course);
        $navbar = $this->navbar($html);

        $this->assertMatchesRegularExpression(
            '~<div class="dimensions-ctxbar" data-region="dimensions-ctxbar" data-kind="course">~',
            $navbar
        );
        $this->assertMatchesRegularExpression(
            '~<span class="dimensions-ctxbar-title" data-region="dimensions-ctxbar-title">Competency management</span>~',
            $navbar
        );
        $this->assertStringContainsString(
            get_string('drawer_progress', 'theme_dimensions', (object) ['completed' => 1, 'total' => 1]),
            $navbar
        );
        $this->assertStringNotContainsString('dimensions-ctxbar-status', $navbar, 'The course page has no activity status.');
        $this->assertSame(
            ['kind' => 'course', 'progress' => 100, 'autohide' => false, 'behat' => false],
            $this->client_config()
        );
    }

    /**
     * Auto-hide reaches the body only on the kinds an administrator ticked.
     *
     * @return void
     */
    public function test_autohide_reaches_the_body_on_the_ticked_kind_only(): void {
        [$course, $cm] = $this->course();
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'student'));
        set_config(readingbar::SETTING_AUTOHIDE, readingbar::KIND_ACTIVITY, readingbar::COMPONENT);

        $html = $this->activity_header($course, $cm);
        $this->assertStringContainsString(readingbar::BODY_AUTOHIDE, $this->body_tag($html));
        $this->assertTrue($this->client_config()['autohide']);

        $home = $this->course_header($course);
        $this->assertStringContainsString(readingbar::BODY_READING, $this->body_tag($home), 'Control: a reading page.');
        $this->assertStringNotContainsString(readingbar::BODY_AUTOHIDE, $this->body_tag($home));
        $this->assertFalse($this->client_config()['autohide']);
    }

    /**
     * A visitor keeps the theme's "Log in" in the navbar and gets no layer over it; with those actions off, the layer.
     *
     * @return void
     */
    public function test_a_visitor_with_the_guest_actions_gets_no_layer(): void {
        [$course, $cm] = $this->course();
        $this->setUser(null);

        $html = $this->activity_header($course, $cm);
        $this->assertStringContainsString('data-region="dimensions-guestnav-login"', $html, 'Precondition: the actions are drawn.');
        $this->assertStringNotContainsString(self::LAYER, $html);
        $this->assertNotNull($this->client_config(), 'The band still follows the reading.');

        set_config(guestnav::SETTING, '0', guestnav::COMPONENT);
        $off = $this->activity_header($course, $cm);
        $this->assertStringContainsString(self::LAYER, $off);
        $this->assertStringNotContainsString('dimensions-ctxbar-status', $off, 'A visitor has no completion status.');
    }

    /**
     * In focus mode none of it is drawn or armed: the focus bar replaces the navbar.
     *
     * @return void
     */
    public function test_focus_mode_draws_none_of_it(): void {
        [$course, $cm] = $this->course();
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'student'));

        $plain = $this->activity_header($course, $cm);
        $this->assertStringContainsString(self::LAYER, $plain, 'Control: the ordinary activity page.');

        focusmode::enter_session((int) $course->id);
        $html = $this->activity_header($course, $cm);
        $this->assertStringContainsString('dimensions-focusbar', $html, 'Precondition: the focus bar is drawn.');
        $this->assertStringNotContainsString(self::LAYER, $html);
        $this->assertStringNotContainsString(readingbar::BODY_READING, $this->body_tag($html));
        $this->assertNull($this->client_config());
    }

    /**
     * An editing teacher finds core's edit mode switch again in the layer, a second one with its own id, after the
     * navbar's own; a student gets the layer and no switch.
     *
     * @return void
     */
    public function test_the_layer_carries_cores_edit_switch_for_a_reader_who_may_edit(): void {
        [$course, $cm] = $this->course();
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'student'));

        $plain = $this->navbar($this->activity_header($course, $cm));
        $this->assertStringContainsString(self::LAYER, $plain, 'Control: the student gets the layer.');
        $this->assertStringNotContainsString('dimensions-ctxbar-edit', $plain);
        $this->assertStringNotContainsString('editmode-switch-form', $plain, 'Nor core\'s switch: a student edits nothing.');

        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $navbar = $this->navbar($this->activity_header($course, $cm));

        $this->assertSame(2, substr_count($navbar, 'editmode-switch-form'), 'The navbar\'s switch and the layer\'s.');
        $this->assertMatchesRegularExpression(
            '~data-region="dimensions-ctxbar-edit"><form [^>]*class="[^"]*editmode-switch-form~',
            $navbar
        );
        $this->assertLessThan(
            strpos($navbar, self::LAYER),
            strpos($navbar, 'editmode-switch-form'),
            'The navbar\'s own switch comes first, so a lookup by its label finds it.'
        );
        preg_match_all('~id="(editingswitch[^"]+)"~', $navbar, $ids);
        $this->assertCount(2, array_unique($ids[1]), 'Two switches, two ids.');
    }

    /**
     * The template's own example renders the two words of the status and the control with core's toggler attributes.
     *
     * @return void
     */
    public function test_the_template_renders_its_example(): void {
        global $OUTPUT;
        $source = file_get_contents(dirname(__DIR__, 2) . '/templates/navbar_readingbar.mustache');
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(.*?)\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1], true);
        $this->assertIsArray($context);

        $html = $OUTPUT->render_from_template('theme_dimensions/navbar_readingbar', $context);
        $this->assertStringContainsString('data-toggler="drawers" data-action="toggle"', $html);
        $this->assertStringContainsString(get_string('readingbar_status_complete', 'theme_dimensions'), $html);
        $this->assertStringContainsString(get_string('readingbar_status_pending', 'theme_dimensions'), $html);
        $this->assertStringNotContainsString(
            'data-region="completion-status"',
            $html,
            'Never core\'s region: core replaces the first one of the page.'
        );
    }
}
