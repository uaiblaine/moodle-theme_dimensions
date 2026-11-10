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
 * Theme Dimensions - Tests for the [relatedcourses] shortcode
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\related;

use core_competency\course_competency;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\output\related_band;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../category/fixture.php');

/**
 * What the shortcode draws for its attributes and its place, and when it draws nothing.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(shortcode::class)]
#[CoversClass(related_band::class)]
final class shortcode_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var int The category every course of a test lives in. */
    private int $categoryid;

    /** @var int[] Competency ids by idnumber, in framework FW1. */
    private array $comps = [];

    /**
     * Competencies on, the page in this theme, as an administrator, with one framework of three competencies.
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        self::forget();
        shortcode::reset();
        set_config('enabled', 1, 'core_competency');
        $PAGE->force_theme('dimensions');

        $this->categoryid = (int) self::getDataGenerator()->create_category()->id;
        $framework = self::competencies()->create_framework(['idnumber' => 'FW1']);
        foreach (['A', 'B', 'C'] as $idnumber) {
            $this->comps[$idnumber] = (int) self::competencies()->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'idnumber' => $idnumber,
            ])->get('id');
        }
    }

    /**
     * Forget the instances drawn, as the next request would.
     */
    protected function tearDown(): void {
        shortcode::reset();
        parent::tearDown();
    }

    /**
     * Core's competency generator.
     *
     * @return \core_competency_generator
     */
    private static function competencies(): \core_competency_generator {
        return self::getDataGenerator()->get_plugin_generator('core_competency');
    }

    /**
     * A public course in the test's category, linked to competencies.
     *
     * @param string[] $idnumbers The competencies.
     * @param string $shortname The course's short name.
     * @return \stdClass The course record.
     */
    private function course(array $idnumbers, string $shortname): \stdClass {
        $course = self::getDataGenerator()->create_course([
            'category' => $this->categoryid,
            'shortname' => $shortname,
            'fullname' => "Curso {$shortname}",
        ]);
        foreach ($idnumbers as $idnumber) {
            self::competencies()->create_course_competency([
                'courseid' => $course->id,
                'competencyid' => $this->comps[$idnumber],
                'ruleoutcome' => course_competency::OUTCOME_EVIDENCE,
            ]);
        }
        $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
        return $course;
    }

    /**
     * What the shortcode prints in a context, as the first instance of a request.
     *
     * @param array $args The attributes.
     * @param \core\context|null $context The text's context; the system's when null.
     * @param bool $fresh Whether the request starts here; false counts this instance with the ones before it.
     * @return string
     */
    private static function draw(array $args, ?\core\context $context = null, bool $fresh = true): string {
        if ($fresh) {
            shortcode::reset();
        }
        $env = (object) ['context' => $context ?? \core\context\system::instance()];
        return shortcode::handle(shortcode::NAME, $args, null, $env, static fn($text) => $text);
    }

    /**
     * The course ids a drawn block lists, in order.
     *
     * @param string $html The block.
     * @return int[]
     */
    private static function listed(string $html): array {
        preg_match_all('/<li class="dimensions-landing__relateditem" data-courseid="(\d+)"/', $html, $matches);
        return array_map('intval', $matches[1]);
    }

    /**
     * By default it relates to the course the text belongs to, never listing that course, in the theme's root.
     */
    public function test_it_relates_to_the_texts_course(): void {
        $parent = $this->course(['A'], 'PAI');
        $related = (int) $this->course(['A'], 'REL')->id;
        $this->course(['B'], 'OUTRO');
        $label = self::getDataGenerator()->create_module('label', ['course' => $parent->id]);

        $html = self::draw([], \core\context\module::instance($label->cmid));

        $this->assertSame([$related], self::listed($html));
        $this->assertStringContainsString('class="theme_dimensions-related"', $html);
        $this->assertStringContainsString('data-region="' . shortcode::REGION . '"', $html);
        $this->assertStringContainsString('Curso REL', $html);
    }

    /**
     * `course` names the parent wherever the text is; a course the site does not have draws nothing.
     */
    public function test_the_course_attribute_names_the_parent(): void {
        $this->course(['B'], 'PAI');
        $related = (int) $this->course(['B'], 'REL')->id;

        $this->assertSame([$related], self::listed(self::draw(['course' => 'PAI'])));
        $this->assertSame([], self::listed(self::draw(['course' => 'NENHUM'])));
    }

    /**
     * Manual mode reads the competencies inside their framework; with a parent only the ones linked to it count, and
     * `match="all"` keeps the courses linked to every one.
     */
    public function test_manual_mode_reads_competencies_in_their_framework(): void {
        $bya = (int) $this->course(['A'], 'POR-A')->id;
        $byab = (int) $this->course(['A', 'B'], 'POR-AB')->id;
        $byc = (int) $this->course(['C'], 'POR-C')->id;

        $this->assertSame([$byc], self::listed(self::draw(['framework' => 'FW1', 'competencies' => 'C'])));
        $this->assertSame(
            [$byab, $bya],
            self::listed(self::draw(['framework' => 'FW1', 'competencies' => 'A, B'])),
            'Covering more of them first.'
        );
        $this->assertSame([$byab], self::listed(self::draw(['framework' => 'FW1', 'competencies' => 'A,B', 'match' => 'all'])));

        $parent = $this->course(['A'], 'PAI');
        $inparent = \core\context\course::instance($parent->id);
        $this->assertSame([], self::listed(self::draw(['framework' => 'FW1', 'competencies' => 'C'], $inparent)));
        $this->assertEqualsCanonicalizing(
            [$bya, $byab],
            self::listed(self::draw(['framework' => 'FW1', 'competencies' => 'A,C'], $inparent)),
            'Only the parent\'s A counts.'
        );
    }

    /**
     * `limit` caps the band; a value that is not a number reads as the site's setting, one past the cap as the cap.
     */
    public function test_the_limit_caps_the_band(): void {
        $this->course(['A'], 'PAI');
        for ($i = 0; $i < 6; $i++) {
            $this->course(['A'], "REL{$i}");
        }

        $this->assertCount(2, self::listed(self::draw(['course' => 'PAI', 'limit' => '2'])));
        $this->assertCount(band::DEFAULT_COUNT, self::listed(self::draw(['course' => 'PAI', 'limit' => 'x'])));
        set_config(band::SETTING_COUNT, '5', 'theme_dimensions');
        $this->assertCount(5, self::listed(self::draw(['course' => 'PAI'])));
        $this->assertCount(1, self::listed(self::draw(['course' => 'PAI', 'limit' => '0'])), 'Clamped to one.');
    }

    /**
     * A reference the site does not have draws nothing, and only an editor of the text's place is told what.
     */
    public function test_only_an_editor_is_told_what_was_not_found(): void {
        $this->course(['A'], 'PAI');
        $this->course(['A'], 'REL');
        $problems = [
            [['course' => 'NENHUM'], 'NENHUM'],
            [['framework' => 'FW9', 'competencies' => 'A'], 'FW9/A'],
            [['framework' => 'FW1', 'competencies' => 'A,Z'], 'FW1/Z'],
            [['competencies' => 'A'], get_string('related_shortcode_noframework', 'theme_dimensions')],
            [['mode' => 'manual'], get_string('related_shortcode_nocompetencies', 'theme_dimensions')],
            [[], get_string('related_shortcode_nocourse', 'theme_dimensions')],
        ];
        foreach ($problems as [$args, $named]) {
            $html = self::draw($args);
            $this->assertSame([], self::listed($html), json_encode($args));
            $this->assertStringContainsString('alert-warning', $html, json_encode($args));
            $this->assertStringContainsString(s($named), $html, json_encode($args));
            shortcode::reset();
        }

        $front = self::draw([], \core\context\course::instance(SITEID));
        $nocourse = s(get_string('related_shortcode_nocourse', 'theme_dimensions'));
        $this->assertStringContainsString($nocourse, $front, 'The site is no course.');

        $this->setUser(self::getDataGenerator()->create_user());
        foreach ($problems as [$args]) {
            $this->assertSame('', self::draw($args), 'A reader who may not edit is told nothing: ' . json_encode($args));
            shortcode::reset();
        }
        $this->assertNotSame('', self::draw(['course' => 'PAI']), 'Control: the same reader sees the band.');
    }

    /**
     * Nothing on a page another theme draws, while competencies are off, inside another instance, or past the cap.
     */
    public function test_the_guards_draw_nothing(): void {
        global $DB, $PAGE;
        $this->course(['A'], 'PAI');
        $this->course(['A'], 'REL');
        $args = ['course' => 'PAI'];

        for ($i = 0; $i < shortcode::MAX_PER_REQUEST; $i++) {
            $this->assertNotSame('', self::draw($args, null, $i === 0), "Instance {$i}.");
        }
        $this->assertSame('', self::draw($args, null, false), 'Past the cap.');
        shortcode::reset();

        $depth = new \ReflectionProperty(shortcode::class, 'depth');
        $depth->setValue(null, 1);
        $this->assertSame('', self::draw($args, null, false), 'Inside another instance.');
        $depth->setValue(null, 0);
        $this->assertNotSame('', self::draw($args), 'Control: outside any.');

        set_config('enabled', 0, 'core_competency');
        $this->assertFalse(finder::available(), 'Read once, so the config cache is warm for the count below.');
        $reads = $DB->perf_get_reads();
        $this->assertSame('', self::draw($args), 'Competencies off.');
        $this->assertSame($reads, $DB->perf_get_reads(), 'Competencies off, nothing is read.');
        set_config('enabled', 1, 'core_competency');

        $PAGE = new \moodle_page();
        $PAGE->set_context(\core\context\system::instance());
        $PAGE->force_theme('boost');
        $this->assertSame('', self::draw($args), 'Another theme.');
    }

    /**
     * On a course's landing page, an instance in a text of that course's own context draws nothing in place and is
     * kept for the page's band, the first one alone; a text of any other context still draws in place.
     */
    public function test_on_its_landing_page_the_courses_texts_keep_it_for_the_band(): void {
        $parent = $this->course(['A'], 'PAI');
        $related = (int) $this->course(['A'], 'REL')->id;
        $other = $this->course(['B'], 'OUTRO');
        $byb = (int) $this->course(['B'], 'POR-B')->id;
        $label = self::getDataGenerator()->create_module('label', ['course' => $parent->id]);
        $incourse = \core\context\course::instance($parent->id);

        $this->assertNull(shortcode::lifted(), 'Nothing kept before any page asked.');
        shortcode::lift((int) $parent->id);
        $this->assertSame('', self::draw(['course' => 'OUTRO'], $incourse, false), 'Kept, not drawn in the text.');
        $this->assertSame('', self::draw([], $incourse, false), 'A second one draws nothing either.');
        $this->assertSame([$byb], array_map(
            static fn(array $entry): int => (int) $entry['row']->id,
            shortcode::lifted()['found']
        ), 'The first one is the band.');
        $this->assertNull(shortcode::lifted()['notice']);

        $this->assertSame([$related], self::listed(self::draw([], \core\context\module::instance($label->cmid), false)));
        $inother = \core\context\course::instance($other->id);
        $this->assertSame([$byb], self::listed(self::draw([], $inother, false)), 'Another course\'s text.');

        set_config('enabled', 0, 'core_competency');
        $this->assertNull(shortcode::lifted(), 'Competencies off, the page draws nothing.');
        set_config('enabled', 1, 'core_competency');

        shortcode::lift((int) $parent->id);
        $this->assertNull(shortcode::lifted(), 'A new page keeps nothing of the last one.');
        self::draw(['course' => 'NENHUM'], $incourse, false);
        $this->assertSame([], shortcode::lifted()['found']);
        $this->assertStringContainsString('NENHUM', shortcode::lifted()['notice']['message'], 'The editor is told on the page.');

        shortcode::reset();
        $this->assertNotSame('', self::draw([], $incourse), 'Control: on any other page it draws in place.');
    }

    /**
     * Through the filter itself, the text's shortcode becomes the band; skipped without filter_shortcodes.
     */
    public function test_the_filter_draws_the_band(): void {
        global $CFG;
        if (\core_component::get_component_directory('filter_shortcodes') === null) {
            $this->markTestSkipped('filter_shortcodes is not installed.');
        }
        require_once($CFG->libdir . '/filterlib.php');
        $this->course(['A'], 'PAI');
        $related = (int) $this->course(['A'], 'REL')->id;
        filter_set_global_state('shortcodes', TEXTFILTER_ON);
        \cache::make_from_params(\cache_store::MODE_REQUEST, 'core_filter', 'global_filters')->purge();
        \filter_manager::reset_caches();

        $html = format_text('<p>[relatedcourses course="PAI"]</p>', FORMAT_HTML, ['context' => \core\context\system::instance()]);

        $this->assertSame([$related], self::listed($html));
        $this->assertStringNotContainsString('[relatedcourses', $html);
    }
}
