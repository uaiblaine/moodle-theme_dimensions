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
 * Theme Dimensions - Tests for the image of a course with none, as the theme's renderer answers it
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\card\boost_union;
use theme_dimensions\local\card\cover;
use theme_dimensions\local\category\branding;

/**
 * Tests for core_renderer::get_generated_image_for_id() and the cover it draws.
 *
 * The renderer is this theme's, built on a page drawn in this theme; the flavour Boost Union would apply is handed in
 * through the renderer's seam, because Boost Union memoises its lookup for the whole process.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(core_renderer::class)]
#[CoversClass(cover::class)]
final class core_renderer_cover_test extends \advanced_testcase {
    /** @var string A one-pixel PNG, Boost Union's fallback image. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Put a page in this theme: the global one by default, which can be set up once per test.
     *
     * @param \moodle_page|null $page The page, or null for the global one.
     * @return \moodle_page
     */
    private static function page(?\moodle_page $page = null): \moodle_page {
        global $PAGE, $SITE;

        $page ??= $PAGE;
        $page->set_context(\context_system::instance());
        $page->set_course($SITE);
        $page->set_url('/course/index.php');
        $page->force_theme('dimensions');
        $page->initialise_theme_and_output();
        return $page;
    }

    /**
     * This theme's renderer, with the flavour that applies handed in.
     *
     * @param \stdClass|null $flavour The flavour, or null for none.
     * @return core_renderer
     */
    private static function renderer(?\stdClass $flavour = null): core_renderer {
        // A page of its own, so that a test can build several renderers.
        $page = self::page(new \moodle_page());
        return new class ($page, RENDERER_TARGET_GENERAL, $flavour) extends core_renderer {
            /** @var \stdClass|null The flavour the test hands in. */
            private ?\stdClass $testflavour;

            /**
             * Constructor.
             *
             * @param \moodle_page $page The page.
             * @param string $target The target.
             * @param \stdClass|null $flavour The flavour.
             */
            public function __construct(\moodle_page $page, string $target, ?\stdClass $flavour) {
                parent::__construct($page, $target);
                $this->testflavour = $flavour;
            }

            /**
             * The flavour the test handed in.
             *
             * @return \stdClass|null
             */
            protected function cover_flavour(): ?\stdClass {
                return $this->testflavour;
            }
        };
    }

    /**
     * A branding pair of a category's own.
     *
     * @param int $categoryid The category.
     * @param string $bg The band colour.
     * @return void
     */
    private static function pair(int $categoryid, string $bg): void {
        global $DB;

        $DB->insert_record(branding::TABLE, (object) [
            'categoryid' => $categoryid,
            'layout' => branding::DEFAULT_LAYOUT,
            'bgcolour' => $bg,
            'inkcolour' => '#ffffff',
            'owncolours' => 1,
            'overlay' => branding::DEFAULT_OVERLAY,
            'publicdescriptionformat' => FORMAT_HTML,
        ]);
        branding::forget_all();
    }

    /**
     * The SVG document inside a cover's data URI, parsed.
     *
     * @param string $uri The data URI.
     * @return \SimpleXMLElement
     */
    private function svg(string $uri): \SimpleXMLElement {
        $this->assertStringStartsWith(cover::PREFIX, $uri);
        $document = base64_decode(substr($uri, strlen(cover::PREFIX)), true);
        $this->assertIsString($document, 'The payload is base64.');
        $svg = simplexml_load_string($document);
        $this->assertInstanceOf(\SimpleXMLElement::class, $svg, 'The payload is well-formed XML.');
        $this->assertSame('svg', $svg->getName());
        return $svg;
    }

    /**
     * The band colour of a cover: the fill of its first rectangle.
     *
     * @param string $uri The data URI.
     * @return string
     */
    private function band(string $uri): string {
        $svg = $this->svg($uri);
        $svg->registerXPathNamespace('s', 'http://www.w3.org/2000/svg');
        $rects = $svg->xpath('//s:rect');
        $this->assertCount(2, $rects, 'The band and the overlay.');
        $this->assertSame('url(#o)', (string) $rects[1]['fill'], 'The overlay lies over the band.');
        return (string) $rects[0]['fill'];
    }

    /**
     * Upload Boost Union's fallback image, as its setting's file manager stores it.
     *
     * @return string The URL Boost Union answers for it.
     */
    private static function upload_fallback(): string {
        global $CFG;

        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => boost_union::COMPONENT,
            'filearea' => 'courseoverviewimagefallback',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'fallback.png',
        ], base64_decode(self::PNG));
        set_config('courseoverviewimagefallback', '/fallback.png', boost_union::COMPONENT);
        require_once($CFG->dirroot . '/theme/boost_union/locallib.php');
        return theme_boost_union_get_course_overview_fallback_image_url()->out();
    }

    /**
     * A category's own pair paints the cover, under the contract's overlay, as a valid SVG.
     */
    public function test_a_category_with_its_own_pair_paints_the_cover(): void {
        $this->resetAfterTest();
        $category = self::getDataGenerator()->create_category();
        $course = self::getDataGenerator()->create_course(['category' => $category->id]);
        self::pair((int) $category->id, '#B3261E');

        $uri = self::renderer()->get_generated_image_for_id($course->id);

        $this->assertSame('#b3261e', $this->band($uri));
        $svg = $this->svg($uri);
        $svg->registerXPathNamespace('s', 'http://www.w3.org/2000/svg');
        $stops = $svg->xpath('//s:linearGradient[@id="o"]/s:stop');
        $this->assertSame(['#ffffff', '0.16'], [(string) $stops[0]['stop-color'], (string) $stops[0]['stop-opacity']]);
        $this->assertSame(['#000000', '0.34'], [(string) $stops[1]['stop-color'], (string) $stops[1]['stop-opacity']]);
        $gradient = $svg->xpath('//s:linearGradient')[0];
        $this->assertSame(['0', '0', '1', '1'], [
            (string) $gradient['x1'], (string) $gradient['y1'], (string) $gradient['x2'], (string) $gradient['y2'],
        ], 'Top left to bottom right: the 135 degrees of the CSS overlay.');
        $this->assertMatchesRegularExpression('~^data:image/svg\+xml;base64,[A-Za-z0-9+/=]+$~', $uri);
    }

    /**
     * A category without a pair takes its nearest ancestor's, and a nearer pair wins over a farther one.
     */
    public function test_a_category_without_a_pair_takes_its_nearest_ancestors(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $top = $generator->create_category();
        $middle = $generator->create_category(['parent' => $top->id]);
        $leaf = $generator->create_category(['parent' => $middle->id]);
        $sibling = $generator->create_category(['parent' => $top->id]);
        $inleaf = $generator->create_course(['category' => $leaf->id]);
        $insibling = $generator->create_course(['category' => $sibling->id]);
        self::pair((int) $top->id, '#204060');
        self::pair((int) $middle->id, '#7a2e8c');

        $renderer = self::renderer();

        $this->assertSame('#7a2e8c', $this->band($renderer->get_generated_image_for_id($inleaf->id)), 'The nearer pair.');
        $this->assertSame('#204060', $this->band($renderer->get_generated_image_for_id($insibling->id)), 'The top\'s pair.');
    }

    /**
     * With no pair on the path the cover is the primary: the flavour's, else Boost Union's brand colour, else Boost's.
     */
    public function test_no_pair_anywhere_paints_the_primary(): void {
        $this->resetAfterTest();
        $category = self::getDataGenerator()->create_category();
        $course = self::getDataGenerator()->create_course(['category' => $category->id]);

        $this->assertSame(cover::BOOST_PRIMARY, $this->band(self::renderer()->get_generated_image_for_id($course->id)));
        $this->assertNotSame(branding::DEFAULT_BG, cover::BOOST_PRIMARY, 'Never the band of an unbranded hero.');

        set_config('brandcolor', '#123ABC', boost_union::COMPONENT);
        $this->assertSame('#123abc', $this->band(self::renderer()->get_generated_image_for_id($course->id)));

        $flavour = (object) ['id' => 7, 'look_brandcolor' => '#aa0000'];
        $this->assertSame('#aa0000', $this->band(self::renderer($flavour)->get_generated_image_for_id($course->id)));

        // A brand colour the picker took but no SVG may hold is passed over.
        set_config('brandcolor', 'red', boost_union::COMPONENT);
        $this->assertSame(cover::BOOST_PRIMARY, $this->band(self::renderer()->get_generated_image_for_id($course->id)));
    }

    /**
     * The fallback image, chosen and uploaded, is Boost Union's answer for every course; the pattern source keeps the cover.
     */
    public function test_the_fallback_image_wins_when_chosen_and_uploaded(): void {
        $this->resetAfterTest();
        $category = self::getDataGenerator()->create_category();
        $one = self::getDataGenerator()->create_course(['category' => $category->id]);
        $two = self::getDataGenerator()->create_course(['category' => $category->id]);
        self::pair((int) $category->id, '#b3261e');
        $fallback = self::upload_fallback();

        // The control: the default source draws the cover although an image is uploaded.
        $this->assertSame('#b3261e', $this->band(self::renderer()->get_generated_image_for_id($one->id)));

        set_config(boost_union::SETTING_IMAGESOURCE, boost_union::SOURCE_FALLBACK, boost_union::COMPONENT);
        $renderer = self::renderer();
        $this->assertSame($fallback, $renderer->get_generated_image_for_id($one->id));
        $this->assertSame($fallback, $renderer->get_generated_image_for_id($two->id), 'Asked once, the same for each course.');
        $this->assertSame(
            [(int) $one->id => $fallback, (int) $two->id => $fallback],
            $renderer->course_covers([(int) $one->id => (int) $category->id, (int) $two->id => (int) $category->id])
        );
    }

    /**
     * The fallback image chosen with none uploaded draws the category's cover, not Boost Union's pattern.
     */
    public function test_the_fallback_chosen_without_an_image_paints_the_cover(): void {
        $this->resetAfterTest();
        $category = self::getDataGenerator()->create_category();
        $course = self::getDataGenerator()->create_course(['category' => $category->id]);
        self::pair((int) $category->id, '#b3261e');
        set_config(boost_union::SETTING_IMAGESOURCE, boost_union::SOURCE_FALLBACK, boost_union::COMPONENT);

        $this->assertSame('#b3261e', $this->band(self::renderer()->get_generated_image_for_id($course->id)));
    }

    /**
     * An id that names no course, and the site course, keep the parent's pattern.
     */
    public function test_an_id_without_a_category_keeps_the_parents_pattern(): void {
        global $PAGE;
        $this->resetAfterTest();

        $renderer = self::renderer();
        $boost = new \theme_boost_union\output\core_renderer($PAGE, RENDERER_TARGET_GENERAL);
        $this->assertSame($boost->get_generated_image_for_id(SITEID), $renderer->get_generated_image_for_id(SITEID));
        $this->assertSame($boost->get_generated_image_for_id(987654), $renderer->get_generated_image_for_id(987654));
    }

    /**
     * The renderer core and the plugins reach is this one, and it answers the cover.
     */
    public function test_the_factory_answers_the_theme_renderer(): void {
        global $PAGE;
        $this->resetAfterTest();
        $category = self::getDataGenerator()->create_category();
        $course = self::getDataGenerator()->create_course(['category' => $category->id]);
        self::pair((int) $category->id, '#b3261e');

        self::page();
        $renderer = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);

        $this->assertInstanceOf(core_renderer::class, $renderer);
        $this->assertSame('#b3261e', $this->band($renderer->get_generated_image_for_id($course->id)));
    }

    /**
     * One call costs the course's category statement once the branding is cached, and nothing the second time.
     */
    public function test_a_warm_call_reads_the_category_once_and_a_grid_reads_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $category = self::getDataGenerator()->create_category();
        $one = self::getDataGenerator()->create_course(['category' => $category->id]);
        $two = self::getDataGenerator()->create_course(['category' => $category->id]);
        self::pair((int) $category->id, '#b3261e');
        branding::for_category((int) $category->id);

        $renderer = self::renderer();
        $renderer->get_generated_image_for_id(SITEID);
        $reads = $DB->perf_get_reads();
        $renderer->get_generated_image_for_id($one->id);
        $this->assertSame(1, $DB->perf_get_reads() - $reads, 'The course\'s category.');

        $reads = $DB->perf_get_reads();
        $renderer->get_generated_image_for_id($one->id);
        $renderer->course_covers([(int) $two->id => (int) $category->id]);
        $this->assertSame(0, $DB->perf_get_reads() - $reads, 'Remembered, and a grid hands the category in.');
    }
}
