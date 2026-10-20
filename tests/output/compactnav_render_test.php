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
 * Theme Dimensions - Tests for the navbar's compact mode on tablets, as the page draws it
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\compactnav;
use theme_dimensions\local\focusmode;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Renders the real page header - Boost Union's layout, its navbar and the theme's renderer - because the splice into
 * the body attributes and the module's arming happen inside the one render_from_template() call of the layout, and
 * are only visible in its output.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(core_renderer::class)]
#[CoversClass(compactnav::class)]
final class compactnav_render_test extends \advanced_testcase {
    /** @var string The client module's name in a queued AMD call. */
    private const MODULE = 'theme_dimensions/compactnav';

    /**
     * Nothing of a previous test's focus mode.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        focusmode::reset_caches();
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
     * A course with one page activity, and a student of it logged in.
     *
     * @return array The course record and the activity.
     */
    private function course(): array {
        $course = self::getDataGenerator()->create_course(['format' => 'topics']);
        $module = self::getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Reading two']);
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'student'));

        return [$course, get_fast_modinfo($course)->get_cm($module->cmid)];
    }

    /**
     * Give the site's primary navigation the given number of custom menu items.
     *
     * @param int $count How many.
     * @return void
     */
    private function custom_items(int $count): void {
        $lines = [];
        for ($i = 1; $i <= $count; $i++) {
            $lines[] = "Custom item {$i}|/course/index.php?item={$i}";
        }
        set_config('custommenuitems', implode("\n", $lines));
    }

    /**
     * The real header of an activity page, as require_login() leaves the page (readingbar_render_test says why so).
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
     * How many times the page queued the module.
     *
     * A header rendered under PHPUnit prints no footer, so the call is read from the requirements manager.
     *
     * @return int
     */
    private function armed(): int {
        global $PAGE;

        $property = new \ReflectionProperty($PAGE->requires, 'amdjscode');
        $calls = implode("\n", (array) $property->getValue($PAGE->requires));

        return substr_count($calls, "require(['" . self::MODULE . "'], function(amd) {amd.init(");
    }

    /**
     * A navigation that can overflow arms the mode: the body carries the class and the module is queued once.
     *
     * @return void
     */
    public function test_a_navigation_that_can_overflow_arms_the_mode(): void {
        [$course, $cm] = $this->course();
        $this->custom_items(4);

        $html = $this->activity_header($course, $cm);

        $this->assertStringContainsString('Custom item 4', $html, 'Precondition: the items are in the navigation.');
        $this->assertMatchesRegularExpression(
            '~<body\b[^>]*\bclass="[^"]*\b' . compactnav::BODY_ARMED . '\b~',
            $this->body_tag($html)
        );
        $this->assertSame(1, substr_count($this->body_tag($html), compactnav::BODY_ARMED), 'Spliced once.');
        $this->assertSame(1, $this->armed());
        $this->assertStringContainsString('"maxhidden":' . compactnav::MAXHIDDEN, $this->queued());
    }

    /**
     * A navigation that can never send more than MAXHIDDEN items into "More" is decided on the server: nothing arms.
     *
     * @return void
     */
    public function test_a_short_navigation_is_decided_on_the_server(): void {
        [$course, $cm] = $this->course();

        $this->custom_items(4);
        $this->activity_header($course, $cm);
        $this->assertSame(1, $this->armed(), 'Control: four more items arm it.');

        set_config('custommenuitems', '');
        $html = $this->activity_header($course, $cm);
        $this->assertStringContainsString('id="usernavigation"', $html, 'Control: the navbar is drawn.');
        $this->assertStringNotContainsString(compactnav::BODY_ARMED, $this->body_tag($html));
        $this->assertSame(0, $this->armed());
    }

    /**
     * Switched off, nothing of it reaches the page.
     *
     * @return void
     */
    public function test_the_setting_switched_off_leaves_core_navbar(): void {
        [$course, $cm] = $this->course();
        $this->custom_items(4);
        $this->activity_header($course, $cm);
        $this->assertSame(1, $this->armed(), 'Control: on by default.');

        set_config(compactnav::SETTING, '0', compactnav::COMPONENT);
        $html = $this->activity_header($course, $cm);
        $this->assertStringContainsString('Custom item 4', $html, 'Control: the navigation is there.');
        $this->assertStringNotContainsString(compactnav::BODY_ARMED, $this->body_tag($html));
        $this->assertSame(0, $this->armed());
    }

    /**
     * The Moodle app gets nothing of it.
     *
     * @return void
     */
    public function test_the_moodle_app_gets_nothing(): void {
        [$course, $cm] = $this->course();
        $this->custom_items(4);
        $this->activity_header($course, $cm);
        $this->assertSame(1, $this->armed(), 'Control: a browser.');

        \core_useragent::instance(true, 'Mozilla/5.0 (Linux; Android 10) MoodleMobile 4.5.0');
        $this->assertTrue(\core_useragent::is_moodle_app(), 'Precondition: the user agent is the app.');

        $html = $this->activity_header($course, $cm);
        $this->assertStringContainsString('id="usernavigation"', $html, 'Control: the navbar is drawn.');
        $this->assertStringNotContainsString(compactnav::BODY_ARMED, $this->body_tag($html));
        $this->assertSame(0, $this->armed());
    }

    /**
     * Focus mode keeps its own bar, and nothing of the mode is armed under it.
     *
     * @return void
     */
    public function test_focus_mode_keeps_its_own_bar(): void {
        [$course, $cm] = $this->course();
        $this->custom_items(4);
        $this->activity_header($course, $cm);
        $this->assertSame(1, $this->armed(), 'Control: the ordinary activity page.');

        focusmode::enter_session((int) $course->id);
        $html = $this->activity_header($course, $cm);
        $this->assertStringContainsString('dimensions-focusbar', $html, 'Precondition: the focus bar is drawn.');
        $this->assertStringNotContainsString(compactnav::BODY_ARMED, $this->body_tag($html));
        $this->assertSame(0, $this->armed());
    }

    /**
     * The queued calls of the page, as one string.
     *
     * @return string
     */
    private function queued(): string {
        global $PAGE;

        $property = new \ReflectionProperty($PAGE->requires, 'amdjscode');

        return implode("\n", (array) $property->getValue($PAGE->requires));
    }
}
