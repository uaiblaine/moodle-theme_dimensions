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
 * Theme Dimensions - Tests for the landing page banner's format
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use theme_dimensions\local\landingfields;
use theme_dimensions\output\core_renderer;
use theme_dimensions\output\landing_hero;
use theme_dimensions\output\landing_page;
use theme_dimensions\output\opengraph;

/**
 * The banner of a landing page is drawn in the content column unless the site chose full width.
 *
 * The setting is read by heroformat alone. The page is served through request::handle(), as both entries serve it,
 * and drawn through the theme's real header and renderer; the column, which a site has before it chooses anything,
 * is the control of every full width case: there the page draws the banner and the header does not.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(heroformat::class)]
#[CoversClass(request::class)]
#[CoversClass(landing_hero::class)]
#[CoversClass(landing_page::class)]
final class heroformat_test extends \advanced_testcase {
    /** @var string The course's name: the banner's title. */
    private const FULLNAME = 'Generative AI in the Classroom';

    /**
     * Forget what local_unlistedcourses memoised for the last test, and start with the router off.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/course/lib.php');
        \local_unlistedcourses\discoverability::reset_caches();
        \local_unlistedcourses\access::reset_caches();
        \local_unlistedcourses\category_access::reset_caches();
        $CFG->routerconfigured = false;
        \core\di::reset_container();
    }

    /**
     * Leave no banner, head tags or router behind: the registries are static.
     */
    protected function tearDown(): void {
        landing_hero::reset();
        opengraph::set(null);
        \core\di::reset_container();
        parent::tearDown();
    }

    /**
     * Stored values and what each of them reads as.
     *
     * @return array Named cases of a stored value (null: nothing stored) and whether it reads as full width.
     */
    public static function stored_provider(): array {
        return [
            'nothing stored' => [null, false],
            'the column' => [heroformat::COLUMN, false],
            'full width' => [heroformat::FULLWIDTH, true],
            'a value outside the vocabulary' => ['banana', false],
            'full width in another spelling' => ['FullWidth', false],
        ];
    }

    /**
     * Only the stored 'fullwidth' draws the banner full width; anything else, nothing stored included, is the column.
     *
     * @param string|null $stored The stored value, or null for none.
     * @param bool $expected Whether it reads as full width.
     */
    #[DataProvider('stored_provider')]
    public function test_only_the_stored_full_width_moves_the_banner(?string $stored, bool $expected): void {
        $this->resetAfterTest();
        set_config(heroformat::SETTING, $stored, 'theme_dimensions');

        $this->assertSame($expected, heroformat::is_fullwidth());
    }

    /**
     * The settings page offers the two formats, the column first.
     */
    public function test_the_setting_offers_the_two_formats(): void {
        $this->assertSame([heroformat::COLUMN, heroformat::FULLWIDTH], array_keys(heroformat::choices()));
    }

    /**
     * A course with a landing page model.
     *
     * @return \stdClass The course.
     */
    private function make_course(): \stdClass {
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['fullname' => self::FULLNAME]);
        \core_course\customfield\course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
        ]);
        return $course;
    }

    /**
     * Serve the landing page of a course and draw it as a request does: the header through the layout, then the page.
     *
     * @param \stdClass $course The course.
     * @return array [the rendered header, the rendered page, the page's body classes].
     */
    private function serve(\stdClass $course): array {
        global $OUTPUT, $PAGE;

        set_config('theme', 'dimensions');
        $GLOBALS['PAGE'] = new \moodle_page();
        $answer = request::handle((int) $course->id);
        $this->assertInstanceOf(landing_page::class, $answer->page);

        $PAGE->initialise_theme_and_output();
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertInstanceOf(core_renderer::class, $OUTPUT);
        $header = $OUTPUT->header();
        $page = $OUTPUT->render($answer->page);

        return [$header, $page, $PAGE->bodyclasses];
    }

    /**
     * In the column, the page draws the banner and the header carries none: the control of the cases below.
     */
    public function test_in_the_column_the_page_draws_the_banner(): void {
        $this->resetAfterTest();
        $course = $this->make_course();

        [$header, $page, $classes] = $this->serve($course);

        $this->assertNull(landing_hero::current());
        $this->assertStringNotContainsString(heroformat::BODY_CLASS, $classes);
        $this->assertStringNotContainsString('dimensions-landing__hero--fullwidth', $header);
        $this->assertStringContainsString('<header class="dimensions-landing__hero">', $page);
        $this->assertSame(1, substr_count($page, '<h1 class="dimensions-landing__title">' . self::FULLNAME . '</h1>'));
    }

    /**
     * Full width, the banner is the page header, under the body class, and the page below it draws none.
     */
    public function test_full_width_the_banner_is_the_page_header(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        set_config(heroformat::SETTING, heroformat::FULLWIDTH, 'theme_dimensions');

        [$header, $page, $classes] = $this->serve($course);

        $this->assertNotNull(landing_hero::current());
        $this->assertStringContainsString(heroformat::BODY_CLASS, $classes);
        $found = preg_match('~<header id="page-header" class="[^"]*dimensions-landing__hero--fullwidth[^"]*"~', $header);
        $this->assertSame(1, $found, 'The banner is the page header.');
        $this->assertSame(1, substr_count($header, '<h1 class="dimensions-landing__title">' . self::FULLNAME . '</h1>'));
        $this->assertStringContainsString('data-region="header-actions-container"', $header);
        $this->assertStringNotContainsString('dimensions-landing__hero', $page, 'The page draws no banner of its own.');
        $this->assertStringContainsString('data-region="dimensions-landing"', $page, 'The page itself is drawn.');
    }

    /**
     * The banner and the page read one export: the header's banner never disagrees with the page under it.
     */
    public function test_the_banner_and_the_page_read_one_export(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->make_course();
        set_config(heroformat::SETTING, heroformat::FULLWIDTH, 'theme_dimensions');

        $GLOBALS['PAGE'] = new \moodle_page();
        $answer = request::handle((int) $course->id);
        $output = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);

        $exported = $answer->page->export_for_template($output);
        $this->assertSame($exported, $answer->page->export_for_template($output));
        $this->assertTrue($exported->herofullwidth);
        $this->assertSame($exported->fullname, landing_hero::current()->export_for_template($output)->fullname);
    }

    /**
     * A banner armed for an earlier landing page of the process is gone once the site draws the next one in the column.
     */
    public function test_a_banner_armed_earlier_is_not_the_next_pages(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        set_config(heroformat::SETTING, heroformat::FULLWIDTH, 'theme_dimensions');
        $GLOBALS['PAGE'] = new \moodle_page();
        request::handle((int) $course->id);
        $this->assertNotNull(landing_hero::current(), 'The control: the first page armed its banner.');

        set_config(heroformat::SETTING, heroformat::COLUMN, 'theme_dimensions');
        [$header, $page] = $this->serve($course);

        $this->assertNull(landing_hero::current());
        $this->assertStringNotContainsString('dimensions-landing__hero--fullwidth', $header);
        $this->assertStringContainsString('<header class="dimensions-landing__hero">', $page);
    }
}
