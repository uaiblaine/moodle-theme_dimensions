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
 * Theme Dimensions - Tests for the cover of a card whose course has no image, and Boost Union's flags on the card
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\card\boost_union;
use theme_dimensions\local\card\cover;
use theme_dimensions\output\category_card;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Tests for cards::images() asking the renderer for a course with no image, and for the card's Boost Union flags.
 *
 * The global page is drawn in this theme in every test, so the renderer images() reaches is the theme's and the
 * settings of Boost Union apply; one test draws it in Boost instead, the control for the theme's own renderer.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(cards::class)]
#[CoversClass(category_card::class)]
final class cards_cover_test extends \advanced_testcase {
    use fixture;

    /** @var string A one-pixel PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Draw the global page in a theme.
     *
     * @param string $theme The theme.
     * @return void
     */
    private static function theme(string $theme = 'dimensions'): void {
        global $PAGE;

        $PAGE->set_context(\context_system::instance());
        $PAGE->force_theme($theme);
    }

    /**
     * A category with two courses: one with an image of its own, one without, and a branding pair.
     *
     * @return array [category id, course with an image, course without, the image's file]
     */
    private function seed(): array {
        global $CFG, $DB;

        $CFG->courseoverviewfileslimit = 1;
        $CFG->courseoverviewfilesext = '.jpg,.gif,.png';
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $with = $generator->create_course(['category' => $category->id]);
        $without = $generator->create_course(['category' => $category->id]);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \core\context\course::instance($with->id)->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'capa.png',
        ], base64_decode(self::PNG));
        $DB->insert_record(branding::TABLE, (object) [
            'categoryid' => (int) $category->id,
            'layout' => branding::DEFAULT_LAYOUT,
            'bgcolour' => '#b3261e',
            'inkcolour' => '#ffffff',
            'owncolours' => 1,
            'overlay' => branding::DEFAULT_OVERLAY,
            'publicdescriptionformat' => FORMAT_HTML,
        ]);
        branding::forget_all();
        $this->become($generator->create_user());
        return [(int) $category->id, (int) $with->id, (int) $without->id, $file];
    }

    /**
     * The population rows of one category, for the current user.
     *
     * @param int $categoryid The category.
     * @return \stdClass[]
     */
    private function rows(int $categoryid): array {
        self::forget();
        $criteria = new criteria($categoryid);
        return population::records(population::scope($criteria), $criteria);
    }

    /**
     * An uploaded image wins; a course without one gets the renderer's cover, and only when asked for it.
     */
    public function test_a_course_without_an_image_gets_the_renderers_cover(): void {
        $this->resetAfterTest();
        [$categoryid, $with, $without] = $this->seed();
        self::theme();
        $rows = $this->rows($categoryid);

        $images = cards::images($rows, false, true);

        $this->assertInstanceOf(\moodle_url::class, $images[$with], 'The course\'s own image wins.');
        $this->assertStringEndsWith('/course/overviewfiles/capa.png', $images[$with]->out(false));
        $this->assertSame(cover::data_uri('#b3261e'), $images[$without]);

        // The control: without the flag the course without an image keeps no image, as the calendar asks.
        $this->assertNull(cards::images($rows)[$without]);

        // Core's course_image cache keeps core's answer, "no image" (null, as its data source writes it), never the cover.
        $this->assertNull(\cache::make('core', 'course_image')->get($without));
    }

    /**
     * The public shell keeps the theme's route for an uploaded image and takes the same cover for the rest.
     */
    public function test_the_public_shell_takes_the_cover_as_it_is(): void {
        $this->resetAfterTest();
        [$categoryid, $with, $without, $file] = $this->seed();
        self::theme();
        $rows = $this->rows($categoryid);

        $images = cards::images($rows, true, true);

        $contextid = \core\context\course::instance($with)->id;
        $this->assertStringEndsWith(
            "/pluginfile.php/{$contextid}/theme_dimensions/cover/{$with}/{$file->get_contenthash()}/capa.png",
            $images[$with]->out(false)
        );
        $this->assertSame(cover::data_uri('#b3261e'), $images[$without], 'A data URI needs no route.');
    }

    /**
     * The cover of a page of cards costs no statement per course once the branding is cached.
     */
    public function test_the_covers_of_a_page_cost_no_statement_per_course(): void {
        global $DB;
        $this->resetAfterTest();
        [$categoryid] = $this->seed();
        $generator = self::getDataGenerator();
        for ($i = 0; $i < 6; $i++) {
            $generator->create_course(['category' => $categoryid]);
        }
        self::theme();
        $rows = $this->rows($categoryid);
        cards::images($rows, false, true);

        $reads = $DB->perf_get_reads();
        cards::images($rows, false, false);
        $plain = $DB->perf_get_reads() - $reads;
        $reads = $DB->perf_get_reads();
        $images = cards::images($rows, false, true);
        $generated = $DB->perf_get_reads() - $reads;

        $this->assertCount(7, array_filter($images, 'is_string'), 'Seven courses without an image.');
        $this->assertSame($plain, $generated, "The covers added " . ($generated - $plain) . " statements.");
    }

    /**
     * Under another theme the renderer images() reaches is that theme's, asked course by course.
     */
    public function test_another_themes_renderer_is_asked_by_id(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$categoryid, , $without] = $this->seed();
        self::theme('boost');
        $rows = $this->rows($categoryid);

        $images = cards::images($rows, false, true);

        $core = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertNotInstanceOf(\theme_dimensions\output\core_renderer::class, $core);
        $this->assertSame($core->get_generated_image_for_id($without), $images[$without]);
        $this->assertNotSame(cover::data_uri('#b3261e'), $images[$without]);
    }

    /**
     * The card exports Boost Union's flags, and the template drops the media band when neither view shows it.
     */
    public function test_the_card_exports_boost_unions_flags(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$categoryid, , $without] = $this->seed();
        self::theme();
        $output = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $rows = $this->rows($categoryid);
        $images = cards::images($rows, false, true);
        $card = new category_card(
            cards::records([$without])[$without],
            $rows[$without],
            cards::values($rows)[$without],
            $images[$without],
            null,
            true
        );

        $context = $card->export_for_template($output);
        $this->assertSame(cover::data_uri('#b3261e'), $context->imageurl);
        $this->assertTrue($context->hasimage);
        $this->assertSame([true, true, true, true], [
            $context->showcover, $context->showthumb, $context->showmedia, $context->showprogress,
        ]);
        $html = $output->render_from_template('theme_dimensions/category_card', $context);
        $this->assertStringContainsString('dim-card__media', $html);

        set_config(boost_union::SETTING_IMAGES, 'list,summary', boost_union::COMPONENT);
        set_config(boost_union::SETTING_PROGRESS, 'no', boost_union::COMPONENT);
        $context = $card->export_for_template($output);
        $this->assertSame([false, true, true, false], [
            $context->showcover, $context->showthumb, $context->showmedia, $context->showprogress,
        ]);
        $html = $output->render_from_template('theme_dimensions/category_card', $context);
        $this->assertStringContainsString('dim-card__media', $html, 'The list view still shows its thumbnail.');
        $this->assertStringContainsString('dim-card--nocover', $html);
        $this->assertStringNotContainsString('dim-card--nothumb', $html);

        set_config(boost_union::SETTING_IMAGES, '', boost_union::COMPONENT);
        $context = $card->export_for_template($output);
        $this->assertFalse($context->showmedia);
        $html = $output->render_from_template('theme_dimensions/category_card', $context);
        $this->assertStringNotContainsString('dim-card__media', $html, 'Neither view shows an image.');
        $this->assertStringContainsString('dim-card--nocover dim-card--nothumb', $html);
    }
}
