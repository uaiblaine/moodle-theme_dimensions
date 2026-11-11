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
 * Theme Dimensions - Tests for the public landing page's head tags
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\category\branding;
use theme_dimensions\local\hook\output\before_standard_head_html_generation;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Open Graph tags and the hook that renders them into the head.
 *
 * The course name and the description both carry a bare ampersand: the one
 * fixture that tells "escaped exactly once" apart from "not escaped" and
 * from "escaped twice". The category half uses the same fixture; a category's
 * description is its public description field alone, while the root's is the
 * opening paragraph of the site summary.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(opengraph::class)]
#[CoversClass(before_standard_head_html_generation::class)]
final class opengraph_test extends \advanced_testcase {
    /**
     * Forget the page's tags between tests.
     *
     * @return void
     */
    protected function tearDown(): void {
        opengraph::set(null);
        parent::tearDown();
    }

    /**
     * The core renderer of this theme, on a page that has a URL.
     *
     * @return \renderer_base The renderer.
     */
    private function renderer(): \renderer_base {
        global $PAGE, $SITE;

        $PAGE->set_context(\core\context\system::instance());
        // The course before the theme: a text filter reached through format_text() asks the
        // bootstrap renderer to initialise the theme, which sets the course to the site and
        // refuses once a theme is in place. The same recipe as core_renderer_test's helper.
        $PAGE->set_course($SITE);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => 1]);
        $PAGE->force_theme('dimensions');
        return $PAGE->get_renderer('core');
    }

    /**
     * The tags carry the plain spellings and omit an empty description.
     *
     * @return void
     */
    public function test_for_course_keeps_the_plain_spelling_and_omits_an_empty_description(): void {
        global $SITE;

        $this->resetAfterTest();
        $SITE->fullname = 'Acme & Cia';
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Resgate & Salvamento']);
        $url = new \moodle_url('/theme/dimensions/landing.php', ['id' => $course->id]);

        $renderer = $this->renderer();
        $exported = opengraph::for_course($course, '   ', $url)->export_for_template($renderer);
        $this->assertSame('Resgate & Salvamento', $exported->title);
        $this->assertFalse($exported->hasdescription);
        $this->assertNull($exported->description);
        $this->assertSame($url->out(false), $exported->url);
        $this->assertSame('Acme & Cia', $exported->sitename);
        $this->assertMatchesRegularExpression('/^[a-z]{2,3}(_[A-Z]{2})?$/', $exported->locale);

        $exported = opengraph::for_course($course, ' Curso de 40 horas & prática ', $url)->export_for_template($renderer);
        $this->assertTrue($exported->hasdescription);
        $this->assertSame('Curso de 40 horas & prática', $exported->description);
    }

    /**
     * Moodle's pt_br becomes Open Graph's pt_BR; a code without a territory passes through.
     *
     * @return void
     */
    public function test_the_locale_upper_cases_the_territory(): void {
        $this->assertSame('pt_BR', opengraph::locale('pt_br'));
        $this->assertSame('en_US', opengraph::locale('en_us'));
        $this->assertSame('en', opengraph::locale('en'));
        $this->assertSame('de_du_kids', opengraph::locale('de_du_kids'));
    }

    /**
     * The robots directive follows the site's own rule: noindex unless the site is open to search engines.
     *
     * Both halves in one test, the closed site first as the control that the directive is
     * really written, so an open site passing "no noindex" cannot pass by rendering nothing.
     *
     * @return void
     */
    public function test_the_robots_directive_follows_the_sites_open_to_web_crawlers_rule(): void {
        global $CFG;
        $this->resetAfterTest();
        $renderer = $this->renderer();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Resgate & Salvamento']);
        $url = new \moodle_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        opengraph::set(opengraph::for_course($course, 'A & B', $url));

        $CFG->opentowebcrawlers = 0;
        $hook = new \core\hook\output\before_standard_head_html_generation($renderer);
        before_standard_head_html_generation::callback($hook);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $hook->get_output(), 'Control: closed.');

        $CFG->opentowebcrawlers = 1;
        $hook = new \core\hook\output\before_standard_head_html_generation($renderer);
        before_standard_head_html_generation::callback($hook);
        $html = $hook->get_output();
        $this->assertStringNotContainsString('name="robots"', $html, 'A site open to search engines lists its public pages.');
        $this->assertStringContainsString('<link rel="canonical" href="' . s($url->out(false)) . '">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Resgate &amp; Salvamento">', $html);
    }

    /**
     * The hook renders nothing until tags are set, then every tag escaped exactly once.
     *
     * @return void
     */
    public function test_the_hook_renders_the_tags_escaped_once_and_only_when_set(): void {
        $this->resetAfterTest();
        $renderer = $this->renderer();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Resgate & Salvamento']);
        $url = new \moodle_url('/theme/dimensions/landing.php', ['id' => $course->id]);

        $hook = new \core\hook\output\before_standard_head_html_generation($renderer);
        before_standard_head_html_generation::callback($hook);
        $this->assertSame('', $hook->get_output(), 'Nothing set, nothing rendered.');

        opengraph::set(opengraph::for_course($course, 'A & B', $url));
        $hook = new \core\hook\output\before_standard_head_html_generation($renderer);
        before_standard_head_html_generation::callback($hook);
        $html = $hook->get_output();
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="' . s($url->out(false)) . '">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Resgate &amp; Salvamento">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="A &amp; B">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="' . s($url->out(false)) . '">', $html);
        $this->assertStringContainsString('<meta property="og:site_name" content="', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html, 'Escaped once, not twice.');
        $this->assertStringNotContainsString('content="Resgate & Salvamento"', $html, 'Escaped, not raw.');

        // Without a description there is no description tag at all.
        opengraph::set(opengraph::for_course($course, null, $url));
        $hook = new \core\hook\output\before_standard_head_html_generation($renderer);
        before_standard_head_html_generation::callback($hook);
        $this->assertStringNotContainsString('og:description', $hook->get_output());
    }

    /**
     * The callback is registered in db/hooks.php: dispatching the hook through core's manager reaches it.
     *
     * Rendering the whole document head is not possible in PHPUnit (the
     * block setup re-initialises the theme), so the proof of wiring is the
     * hook manager, which is what standard_head_html() dispatches through.
     * The Behat feature asserts the tags on the live page.
     *
     * @return void
     */
    public function test_the_hook_is_wired_through_the_hook_manager(): void {
        $this->resetAfterTest();
        $renderer = $this->renderer();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Wired & Ready']);
        $url = new \moodle_url('/theme/dimensions/landing.php', ['id' => $course->id]);

        opengraph::set(opengraph::for_course($course, null, $url));
        $hook = new \core\hook\output\before_standard_head_html_generation($renderer);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        $head = $hook->get_output();
        $this->assertStringContainsString('<meta property="og:title" content="Wired &amp; Ready">', $head);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $head);

        // Control: with nothing registered for the page, the manager's dispatch adds nothing of ours.
        opengraph::set(null);
        $hook = new \core\hook\output\before_standard_head_html_generation($renderer);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        $this->assertStringNotContainsString('og:title', $hook->get_output());
    }

    /**
     * The image tags name the theme's route and the derivative's size, and are absent without an image.
     *
     * @return void
     */
    public function test_the_image_is_the_course_image_through_the_theme_route_or_nothing(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Resgate & Salvamento']);
        $url = new \moodle_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $this->renderer();

        $tags = opengraph::for_course($course, null, $url);
        $this->assertFalse($tags->export_for_template($renderer)->hasimage);
        $this->assertStringNotContainsString('og:image', $renderer->render($tags));

        $image = imagecreatetruecolor(1600, 900);
        ob_start();
        imagejpeg($image);
        imagedestroy($image);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \core\context\course::instance($course->id)->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'capa.jpg',
        ], (string) ob_get_clean());
        $hash = $file->get_contenthash();

        $tags = opengraph::for_course($course, null, $url);
        $exported = $tags->export_for_template($renderer);
        $this->assertTrue($exported->hasimage);
        $this->assertStringEndsWith("/theme_dimensions/ogimage/{$course->id}/{$hash}/og.jpg", $exported->imageurl);
        $this->assertSame(['image/jpeg', 1200, 630], [$exported->imagetype, $exported->imagewidth, $exported->imageheight]);

        $html = $renderer->render($tags);
        $this->assertMatchesRegularExpression(
            '~<meta property="og:image" content="[^"]*/ogimage/' . $course->id . '/' . $hash . '/og\.jpg">~',
            $html
        );
        $this->assertStringContainsString('<meta property="og:image:type" content="image/jpeg">', $html);
        $this->assertStringContainsString('<meta property="og:image:alt" content="Resgate &amp; Salvamento">', $html);
        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $html);
        $this->assertStringContainsString('<meta property="og:image:height" content="630">', $html);
    }

    /**
     * The fallback tag set - an original with a type and no size - renders without a size tag.
     *
     * @return void
     */
    public function test_an_unsized_image_renders_without_width_and_height(): void {
        $this->resetAfterTest();
        $renderer = $this->renderer();
        $tags = new opengraph('Curso & Cia', null, 'https://example.org/h?id=1', 'Site', 'pt_BR', [
            'url' => 'https://example.org/pluginfile.php/9/theme_dimensions/hero/1/abc/capa.png',
            'type' => 'image/png',
            'width' => null,
            'height' => null,
        ]);
        $exported = $tags->export_for_template($renderer);
        $this->assertTrue($exported->hasimage);
        $this->assertFalse($exported->hasimagesize);

        $html = $renderer->render($tags);
        $this->assertStringContainsString('/hero/1/abc/capa.png">', $html);
        $this->assertStringContainsString('<meta property="og:image:type" content="image/png">', $html);
        $this->assertStringContainsString('<meta property="og:image:alt" content="Curso &amp; Cia">', $html);
        $this->assertStringNotContainsString('og:image:width', $html);
        $this->assertStringNotContainsString('og:image:height', $html);
    }

    /**
     * A category, its branding and its page address, as the administrator who may brand it.
     *
     * @param string $name The category name.
     * @return array The category id, the category, and the page's own address.
     */
    private function category(string $name): array {
        $this->setAdminUser();
        $categoryid = (int) $this->getDataGenerator()->create_category(['name' => $name])->id;
        return [
            $categoryid,
            \core_course_category::get($categoryid, MUST_EXIST, true),
            new \core\url('/theme/dimensions/category.php', ['id' => $categoryid]),
        ];
    }

    /**
     * Store a JPEG GD can read in a category's hero area, as the editor's file manager would.
     *
     * @param int $categoryid The category.
     * @return \stored_file The file.
     */
    private function store_hero(int $categoryid): \stored_file {
        $image = imagecreatetruecolor(1600, 900);
        ob_start();
        imagejpeg($image);
        imagedestroy($image);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \core\context\coursecat::instance($categoryid)->id,
            'component' => branding::COMPONENT,
            'filearea' => branding::AREA_HERO,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'hero.jpg',
        ], (string) ob_get_clean());
        branding::forget_all();
        return $file;
    }

    /**
     * A category's tags carry its name plain, its public description as the description, and no image.
     *
     * @return void
     */
    public function test_for_category_takes_the_public_description_as_its_description(): void {
        $this->resetAfterTest();
        [$categoryid, $category, $url] = $this->category('Brigada & Resgate');
        branding::save($categoryid, (object) [
            'publicsummary' => '  Cursos e eventos abertos & contínuos  ',
        ]);

        $exported = opengraph::for_category($category, branding::for_category($categoryid), $url)
            ->export_for_template($this->renderer());

        $this->assertSame('Brigada & Resgate', $exported->title, 'Plain: the template escapes it once.');
        $this->assertTrue($exported->hasdescription);
        $this->assertSame('Cursos e eventos abertos & contínuos', $exported->description, 'Trimmed, plain.');
        $this->assertSame($url->out(false), $exported->url);
        $this->assertMatchesRegularExpression('/^[a-z]{2,3}(_[A-Z]{2})?$/', $exported->locale);
        $this->assertFalse($exported->hasimage, 'An empty hero area names no image.');
    }

    /**
     * The tagline and the public page text are not og:description; only the public description is.
     *
     * Neither was written for a share preview: a tagline addresses a reader already on the page,
     * and a paragraph of the public text cut at 200 characters is a sentence nobody reviewed for
     * one. A category carrying both, and no public description, gets no og:description at all.
     *
     * @return void
     */
    public function test_for_category_reads_neither_the_tagline_nor_the_public_page_text(): void {
        $this->resetAfterTest();
        [$categoryid, $category, $url] = $this->category('Brigada');
        branding::save($categoryid, (object) [
            'tagline' => 'Formação continuada e eventos',
            'publicdescription_editor' => [
                'text' => '<p>Primeiro parágrafo da página pública.</p><p>Segundo.</p>',
                'format' => FORMAT_HTML,
            ],
        ]);
        $branding = branding::for_category($categoryid);
        // The preconditions: both fields og:description must not read really do hold a value here.
        $this->assertSame('Formação continuada e eventos', $branding['tagline']);
        $this->assertStringContainsString('Primeiro parágrafo', $branding['publicdescription']);
        $this->assertSame('', $branding['publicsummary']);

        $renderer = $this->renderer();
        $exported = opengraph::for_category($category, $branding, $url)->export_for_template($renderer);
        $this->assertFalse(
            $exported->hasdescription,
            'A tagline or a public page text became an og:description again.'
        );
        $this->assertNull($exported->description);

        // The control: the field that IS the source turns the same tags into a description.
        $branding['publicsummary'] = 'Uma descrição revisada para o compartilhamento.';
        $with = opengraph::for_category($category, $branding, $url)->export_for_template($renderer);
        $this->assertTrue($with->hasdescription);
        $this->assertSame('Uma descrição revisada para o compartilhamento.', $with->description);
    }

    /**
     * A public description of nothing but spaces is none, and a category without one gets no tag.
     *
     * The branding is handed in untrimmed on purpose: branding::save() trims what an editor
     * types, so only a hand-built payload can tell a trimming for_category() from one that
     * passes the value straight through and puts an empty og:description on the page.
     *
     * @return void
     */
    public function test_for_category_omits_a_description_it_does_not_really_have(): void {
        $this->resetAfterTest();
        [$categoryid, $category, $url] = $this->category('Brigada');
        $renderer = $this->renderer();
        $branding = branding::for_category($categoryid);

        $bare = opengraph::for_category($category, $branding, $url)->export_for_template($renderer);
        $this->assertFalse($bare->hasdescription, 'Nothing branded, nothing to say.');
        $this->assertNull($bare->description);

        $branding['publicsummary'] = '   ';
        $blank = opengraph::for_category($category, $branding, $url)->export_for_template($renderer);
        $this->assertFalse($blank->hasdescription, 'A public description of spaces is none.');
        $this->assertNull($blank->description);

        $branding['publicsummary'] = '  Uma descrição  ';
        $real = opengraph::for_category($category, $branding, $url)->export_for_template($renderer);
        $this->assertTrue($real->hasdescription, 'Control: a real one does reach the tags.');
        $this->assertSame('Uma descrição', $real->description);
    }

    /**
     * The root's tags are the site's: its name twice, its summary, and never an image.
     *
     * for_root() is a sibling of for_category() and not a branch of it, so the assertions say
     * what it reads: the title and og:site_name are both the site's full name - there is no other
     * name the root could carry - the description is the first paragraph of the site summary,
     * cut at two hundred characters, and there is no image at all.
     *
     * The three cases keep each other from being vacuous: a site with no summary gets no
     * og:description, a summary of markup and spaces still gets none, and a real one does. The
     * empty case is the one that matters - no placeholder sentence under a shared link - and only
     * the third case tells it apart from a broken reader.
     *
     * @return void
     */
    public function test_for_root_reads_the_site_and_never_carries_an_image(): void {
        global $SITE, $DB;

        $this->resetAfterTest();
        $renderer = $this->renderer();
        $url = new \core\url('/theme/dimensions/category.php', ['id' => 0]);

        $SITE->fullname = 'Acme & Formação';
        $SITE->summary = '';
        $SITE->summaryformat = FORMAT_HTML;
        $DB->update_record('course', (object) [
            'id' => $SITE->id,
            'fullname' => $SITE->fullname,
            'summary' => '',
            'summaryformat' => FORMAT_HTML,
        ]);

        $bare = opengraph::for_root($url)->export_for_template($renderer);
        $this->assertSame('Acme & Formação', $bare->title, 'Plain: the template escapes it exactly once.');
        $this->assertSame('Acme & Formação', $bare->sitename, 'The root IS the site, so the two are one name.');
        $this->assertSame($url->out(false), $bare->url);
        $this->assertMatchesRegularExpression('/^[a-z]{2,3}(_[A-Z]{2})?$/', $bare->locale);
        $this->assertFalse($bare->hasdescription, 'No site summary, no og:description - never a placeholder.');
        $this->assertNull($bare->description);
        $this->assertFalse($bare->hasimage, 'And the root ships no og:image.');

        // A summary of markup and whitespace is no summary either.
        $SITE->summary = '<p>   </p>';
        $blank = opengraph::for_root($url)->export_for_template($renderer);
        $this->assertFalse($blank->hasdescription, 'A summary that renders to nothing is none.');

        /* The control, and the two reductions in one fixture: only the first paragraph is
           offered, and it is cut at two hundred characters. The first paragraph here is longer
           than the cut, so a reader taking the whole text would fail the length assertion and a
           reader taking the whole first paragraph would fail the ending one. */
        $first = str_repeat('Cursos e eventos abertos ao público. ', 8);
        $SITE->summary = '<p>' . $first . '</p><p>Um segundo parágrafo que nada convida.</p>';
        $real = opengraph::for_root($url)->export_for_template($renderer);
        $this->assertTrue($real->hasdescription, 'Control: a real summary does reach the tags.');
        $this->assertLessThanOrEqual(200, \core_text::strlen($real->description), 'Cut at two hundred characters.');
        $this->assertStringStartsWith('Cursos e eventos abertos ao público.', $real->description);
        $this->assertStringNotContainsString('segundo parágrafo', $real->description, 'Only the first paragraph.');
        $this->assertStringNotContainsString('<p>', $real->description, 'And it is plain text, not markup.');
        $this->assertFalse($real->hasimage, 'Still no image, whatever the site says.');
    }

    /**
     * With a hero image the tags name the 1200x630 derivative through the branding route.
     *
     * @return void
     */
    public function test_for_category_names_the_open_graph_derivative_of_the_hero_image(): void {
        $this->resetAfterTest();
        [$categoryid, $category, $url] = $this->category('Brigada');
        $renderer = $this->renderer();

        $bare = opengraph::for_category($category, branding::for_category($categoryid), $url)
            ->export_for_template($renderer);
        $this->assertFalse($bare->hasimage, 'Control: without a file there is no image tag at all.');

        $file = $this->store_hero($categoryid);

        $exported = opengraph::for_category($category, branding::for_category($categoryid), $url)
            ->export_for_template($renderer);
        $this->assertTrue($exported->hasimage);
        $this->assertStringEndsWith(
            '/categoryogimage/' . $categoryid . '/' . $file->get_contenthash() . '/og.jpg',
            $exported->imageurl
        );
        $this->assertSame(
            ['image/jpeg', 1200, 630],
            [$exported->imagetype, $exported->imagewidth, $exported->imageheight]
        );
    }
}
