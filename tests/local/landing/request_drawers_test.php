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
 * Theme Dimensions - Tests for the drawers the landing page leaves out
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

use theme_dimensions\local\blockless;
use theme_dimensions\local\landingfields;
use theme_dimensions\output\landing_page;
use theme_dimensions\output\opengraph;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The landing page draws no block region and no course index, for a visitor and for a reader logged in.
 *
 * Each case builds its control first: a page as the landing page is (the same course, context, page type and
 * layout), left to core's own handling, which does show the block, the add-block button and the course index.
 * Then the request answers, and the same inputs of Boost Union's drawers.php are read from its page.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(request::class)]
#[CoversClass(blockless::class)]
final class request_drawers_test extends \advanced_testcase {
    /** @var string The text of the block the landing page must not show. */
    private const BLOCK_TEXT = 'A block the landing page must not show';

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
     * Leave no head tags or router behind.
     */
    protected function tearDown(): void {
        opengraph::set(null);
        \core\di::reset_container();
        parent::tearDown();
    }

    /**
     * A course with a landing page model, public or not, and an HTML block on every page of the site.
     *
     * @param bool $public Whether the course is in local_unlistedcourses' public state.
     * @return \stdClass The course.
     */
    private function make_course(bool $public): \stdClass {
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        \core_course\customfield\course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
        ]);
        if ($public) {
            \local_unlistedcourses\discoverability::set_state(
                (int) $course->id,
                \local_unlistedcourses\discoverability::STATE_PUBLIC,
                (int) get_admin()->id
            );
        }
        $this->getDataGenerator()->create_block('html', [
            'parentcontextid' => \core\context\system::instance()->id,
            'showinsubcontexts' => 1,
            'pagetypepattern' => '*',
            'defaultregion' => 'side-pre',
            'configdata' => base64_encode(serialize((object) ['text' => self::BLOCK_TEXT, 'format' => FORMAT_HTML])),
        ]);
        return $course;
    }

    /**
     * A page as the landing page is, but with core's own block and course index handling: the control.
     *
     * @param \stdClass $course The course.
     * @return \moodle_page
     */
    private static function control_page(\stdClass $course): \moodle_page {
        $page = new \moodle_page();
        $page->set_course($course);
        $page->set_context(\core\context\course::instance($course->id)->get_parent_context());
        $page->set_url(links::page((int) $course->id));
        $page->set_pagetype('theme-dimensions-landing');
        $page->set_pagelayout('standard');
        return $page;
    }

    /**
     * What Boost Union's drawers.php draws the two drawers from on a page, read as the layout reads them.
     *
     * The block drawer and its toggle are drawn for block markup in side-pre or a non-empty add-block button; the
     * course index drawer for a non-empty core_course_drawer(), which reads the global page.
     *
     * @param \moodle_page $page A page whose course, context, page type and layout are set.
     * @return string[] [the region's block markup, the add-block button, the course index].
     */
    private static function drawer_inputs(\moodle_page $page): array {
        $GLOBALS['PAGE'] = $page;
        // The header does this on a real request, before the layout runs.
        $page->blocks->load_blocks();
        $output = $page->get_renderer('core');
        return [$output->blocks('side-pre'), $output->addblockbutton(), core_course_drawer()];
    }

    /**
     * A reader logged in, an administrator in edit mode, gets no block, no add-block button and no course index.
     */
    public function test_a_logged_in_reader_gets_no_block_region_and_no_course_index(): void {
        global $USER;
        $this->resetAfterTest();
        $course = $this->make_course(false);
        $this->setAdminUser();
        $USER->editing = 1;

        [$markup, $button, $index] = self::drawer_inputs(self::control_page($course));
        $this->assertStringContainsString(self::BLOCK_TEXT, $markup, 'The control: the block is offered.');
        $this->assertNotSame('', $button, 'The control: edit mode offers the add-block button.');
        $this->assertNotSame('', $index, 'The control: a reader who may enter the course gets its index.');

        $GLOBALS['PAGE'] = new \moodle_page();
        $answer = request::handle((int) $course->id);
        $this->assertInstanceOf(landing_page::class, $answer->page);
        [$markup, $button, $index] = self::drawer_inputs($GLOBALS['PAGE']);
        $this->assertStringNotContainsString(self::BLOCK_TEXT, $markup);
        $this->assertStringNotContainsString('data-block=', $markup);
        $this->assertSame('', $button, 'No add-block button in edit mode.');
        $this->assertSame('', $index, 'No course index.');
    }

    /**
     * A visitor of a public course gets a page that loads no block and asks for no course index.
     *
     * Core shows a visitor neither the HTML block nor the index on a page left to it, so the markup is no control
     * here: the control is what the page decides, as the calendar's visitor test reads it (blocks load, an
     * administrator may edit them, the index is asked for).
     */
    public function test_a_visitor_of_a_public_course_gets_no_block_region_and_no_course_index(): void {
        $this->resetAfterTest();
        $course = $this->make_course(true);
        $fake = new \ReflectionProperty(\block_manager::class, 'fakeblocksonly');

        $control = self::control_page($course);
        $this->assertFalse($fake->getValue($control->blocks), 'The control: blocks load.');
        $this->assertTrue($control->user_can_edit_blocks(), 'The control: an administrator may edit blocks.');
        $this->assertTrue($control->get_show_course_index(), 'The control: core would ask for the course index.');
        $this->setUser(null);

        $GLOBALS['PAGE'] = new \moodle_page();
        $answer = request::handle((int) $course->id);
        $this->assertInstanceOf(landing_page::class, $answer->page);
        $page = $GLOBALS['PAGE'];
        [$markup, $button, $index] = self::drawer_inputs($page);
        $this->assertStringNotContainsString('data-block=', $markup);
        $this->assertSame('', $button);
        $this->assertSame('', $index);
        $this->assertTrue($fake->getValue($page->blocks), 'No block is loaded.');
        $this->assertFalse($page->get_show_course_index(), 'No course index, whoever is looking.');
        $this->setAdminUser();
        $this->assertFalse($page->user_can_edit_blocks(), 'Not even an administrator edits blocks on the page.');
    }
}
