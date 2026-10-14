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
 * Theme Dimensions - Tests for the pages strip of the category showcase
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The pages strip: the theme's own rules over local_page's answer, and the markup that draws it.
 *
 * The plugin is optional and coupled by name, so its answer is played by a stand-in here, on a site
 * with the plugin and on one without it alike; what the real plugin answers is its own test's, and
 * course_renderer_test and the route test read the two page entries with the plugin itself where it
 * is installed.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(category_pages::class)]
final class category_pages_test extends \advanced_testcase {
    /** @var string The stand-in catalogue's class. */
    private const STUB = \theme_dimensions_catalogue_stub::class;

    /**
     * Load the stand-in and give it an empty answer.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        require_once(__DIR__ . '/../fixtures/catalogue_stub.php');
        \theme_dimensions_catalogue_stub::$pages = [];
        \theme_dimensions_catalogue_stub::$asked = [];
    }

    /**
     * One page of the stand-in's answer, on this site.
     *
     * @param string $name The plain name.
     * @param string $slug The last segment of its address.
     * @param string $description The plain description.
     * @return array
     */
    private static function page(string $name, string $slug, string $description = ''): array {
        global $CFG;
        return [
            'id' => crc32($slug),
            'name' => $name,
            'url' => $CFG->wwwroot . '/r.php/local_page/category/7/' . $slug,
            'description' => $description,
        ];
    }

    /**
     * The strip names the catalogue's pages, in the catalogue's order, for the category asked about.
     *
     * @return void
     */
    public function test_the_strip_names_the_catalogues_pages_in_its_order(): void {
        \theme_dimensions_catalogue_stub::$pages = [
            self::page('Agenda', 'agenda', 'Datas das turmas.'),
            self::page('Edital 2026', 'edital'),
        ];

        $strip = category_pages::export(7, self::STUB);

        $this->assertSame([7], \theme_dimensions_catalogue_stub::$asked, 'Asked about the listed category.');
        $this->assertSame(get_string('category_pages', 'theme_dimensions'), $strip['label']);
        $this->assertSame(['Agenda', 'Edital 2026'], array_column($strip['pages'], 'name'));
        $this->assertSame([true, false], array_column($strip['pages'], 'hasdescription'));
        $this->assertSame('Datas das turmas.', $strip['pages'][0]['description']);
    }

    /**
     * No strip at the root, where the catalogue is not even asked, and none without a page to name.
     *
     * @return void
     */
    public function test_nothing_at_the_root_and_nothing_without_a_page(): void {
        \theme_dimensions_catalogue_stub::$pages = [self::page('Agenda', 'agenda')];
        $this->assertNull(category_pages::export(0, self::STUB), 'The course index has no category.');
        $this->assertSame([], \theme_dimensions_catalogue_stub::$asked, 'Nor is the plugin asked about one.');

        \theme_dimensions_catalogue_stub::$pages = [];
        $this->assertNull(category_pages::export(7, self::STUB), 'Nothing to name, no strip.');
    }

    /**
     * Without the plugin there is no strip, and no error: the class is asked about by name first.
     *
     * @return void
     */
    public function test_without_the_plugin_there_is_no_strip_and_no_error(): void {
        \theme_dimensions_catalogue_stub::$pages = [self::page('Agenda', 'agenda')];
        $this->assertNull(category_pages::export(7, '\\theme_dimensions\\no_such_catalogue'));
        $this->assertNull(category_pages::export(7, \stdClass::class), 'A class without the read method is no catalogue.');
        $this->assertNotNull(category_pages::export(7, self::STUB), 'Control: the same call with a class that exists.');
    }

    /**
     * An address that is not this site's is dropped, and so is a page with no name.
     *
     * The "wwwrootevil" spelling is the one a prefix test without its slash would admit.
     *
     * @return void
     */
    public function test_an_address_outside_this_site_is_dropped(): void {
        global $CFG;

        \theme_dimensions_catalogue_stub::$pages = [
            ['name' => 'Elsewhere', 'url' => 'https://evil.example/page', 'description' => ''],
            ['name' => 'Lookalike', 'url' => $CFG->wwwroot . 'evil/page', 'description' => ''],
            ['name' => 'Script', 'url' => 'javascript:alert(1)', 'description' => ''],
            ['name' => 'Relative', 'url' => '/local/page/index.php?id=3', 'description' => ''],
            ['name' => '   ', 'url' => $CFG->wwwroot . '/local/page/index.php?id=4', 'description' => ''],
            self::page('Edital 2026', 'edital'),
        ];

        $strip = category_pages::export(7, self::STUB);

        $this->assertSame(['Edital 2026'], array_column($strip['pages'], 'name'), 'This site\'s own address alone.');
    }

    /**
     * The template escapes the plain spelling once, titles a link with its description, and names the list.
     *
     * @return void
     */
    public function test_the_template_escapes_once_and_titles_a_link_with_its_description(): void {
        global $OUTPUT;

        \theme_dimensions_catalogue_stub::$pages = [
            self::page('Edital & regras', 'edital', 'Prazos "e" documentos'),
            self::page('Agenda', 'agenda'),
        ];
        $html = $OUTPUT->render_from_template('theme_dimensions/category_pages', category_pages::export(7, self::STUB));

        $this->assertStringContainsString('>Edital &amp; regras<', $html, 'Escaped exactly once.');
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringContainsString('title="Prazos &quot;e&quot; documentos"', $html);
        $this->assertSame(1, substr_count($html, 'title='), 'A page with no description has no title.');
        $this->assertStringContainsString('aria-labelledby="dim-pages-label"', $html);
        $this->assertStringContainsString('id="dim-pages-label"', $html);
        $this->assertSame(2, substr_count($html, 'data-region="dim-pages-link"'));
        $this->assertMatchesRegularExpression('~<i [^>]*aria-hidden="true"~', $html, 'The glyph is decoration.');
    }

    /**
     * The listing draws the strip above the toolbar, and draws nothing without one.
     *
     * @return void
     */
    public function test_the_listing_draws_the_strip_above_the_toolbar(): void {
        global $OUTPUT;

        $context = self::listing_context();
        $html = $OUTPUT->render_from_template('theme_dimensions/category_listing', $context);
        $strip = strpos($html, 'data-region="dim-pages"');
        $toolbar = strpos($html, 'data-region="dim-vitrine-toolbar"');
        $this->assertNotFalse($strip, 'The strip is drawn.');
        $this->assertNotFalse($toolbar);
        $this->assertLessThan($toolbar, $strip, 'Under the hero, above the toolbar.');

        unset($context->pages);
        $html = $OUTPUT->render_from_template('theme_dimensions/category_listing', $context);
        $this->assertStringNotContainsString('dim-pages', $html, 'Control: no strip, no trace of one.');
    }

    /**
     * The listing template's own example context, which carries a strip.
     *
     * @return \stdClass
     */
    private static function listing_context(): \stdClass {
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/category_listing.mustache');
        // To the doc comment's own close, a "}}" at the start of a line: the body holds "}}}" too.
        preg_match('/Example context \(json\):\s*(.*?)\n\}\}/s', $template, $match);
        $context = json_decode($match[1] ?? '');
        self::assertNotNull($context, 'Precondition: the example context parses.');
        self::assertNotEmpty($context->pages ?? null, 'Precondition: the example carries a strip.');
        return $context;
    }
}
