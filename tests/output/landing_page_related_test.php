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
 * Theme Dimensions - Tests for the landing page's related courses band
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core_competency\course_competency;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\card\cover;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landing\resolution;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/category/fixture.php');

/**
 * The band at the end of a landing page: drawn with what the band lists, absent with nothing to list or when turned off.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(landing_page::class)]
final class landing_page_related_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var int The competency both courses of a test share. */
    private int $competencyid;

    /**
     * Competencies on, one competency, as a fresh account.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        self::forget();
        set_config('enabled', 1, 'core_competency');
        $generator = self::getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['idnumber' => 'FW1']);
        $this->competencyid = (int) $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'idnumber' => 'A',
        ])->get('id');
        $this->setUser(self::getDataGenerator()->create_user());
    }

    /**
     * A course with a landing page, public, optionally linked to the competency.
     *
     * @param string $fullname The course name.
     * @param bool $linked Whether it is linked to the test's competency.
     * @param int $related The related select's stored value, 0 for none.
     * @param array $more More course record fields, custom fields included.
     * @return \stdClass The course record.
     */
    private function course(string $fullname, bool $linked = true, int $related = 0, array $more = []): \stdClass {
        $record = $more + ['fullname' => $fullname, 'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE];
        if ($related) {
            $record['customfield_' . landingfields::SHORTNAME_RELATED] = $related;
        }
        $course = self::getDataGenerator()->create_course($record);
        if ($linked) {
            self::getDataGenerator()->get_plugin_generator('core_competency')->create_course_competency([
                'courseid' => $course->id,
                'competencyid' => $this->competencyid,
                'ruleoutcome' => course_competency::OUTCOME_EVIDENCE,
            ]);
        }
        $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
        return $course;
    }

    /**
     * The page's context and its markup, as a closed page draws them.
     *
     * @param \stdClass $course The course.
     * @return array [\stdClass context, string html]
     */
    private static function page(\stdClass $course): array {
        global $PAGE;

        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');
        $page = new landing_page($course, landingfields::values((int) $course->id), new resolution(resolution::STATE_CLOSED), []);
        return [$page->export_for_template($renderer), $renderer->render($page)];
    }

    /**
     * A related course is drawn as a card leading to its landing page, with the renderer's cover when it has no image.
     */
    public function test_the_band_lists_the_related_courses(): void {
        $parent = $this->course('Primeiros socorros');
        $related = $this->course('Suporte avançado & trauma', true, 0, [
            'category' => self::getDataGenerator()->create_category(['name' => 'Saúde & resgate'])->id,
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 12,
        ]);

        [$context, $html] = self::page($parent);

        $this->assertSame(1, $context->related['count']);
        $card = $context->related['cards'][0];
        $this->assertSame((int) $related->id, $card['courseid']);
        $this->assertSame('Suporte avançado & trauma', $card['name']);
        $this->assertSame(links::page((int) $related->id)->out(false), $card['url']);
        $this->assertStringStartsWith(cover::PREFIX, $card['imageurl']);
        $this->assertSame(get_string('landing_hours', 'theme_dimensions', '12'), $card['hours']);
        $this->assertSame('Saúde & resgate', $card['category']);
        $this->assertStringContainsString('data-region="dimensions-landing-related"', $html);
        $this->assertStringContainsString('Suporte avançado &amp; trauma', $html);
    }

    /**
     * Nothing to list, or the band turned off: no band and no heading.
     */
    public function test_no_band_without_a_related_course_or_when_turned_off(): void {
        $alone = $this->course('Sozinho', false);
        [$context, $html] = self::page($alone);
        $this->assertNull($context->related);
        $this->assertStringNotContainsString('dimensions-landing-related', $html);

        $off = $this->course('Desligado', true, landingfields::RELATED_OFF);
        $this->course('Relacionado');
        [$context, $html] = self::page($off);
        $this->assertNull($context->related);
        $this->assertStringNotContainsString(get_string('landing_related', 'theme_dimensions'), $html);

        // Control: the same pair draws the band once the select is left empty.
        [$context] = self::page($this->course('Automático'));
        $this->assertNotNull($context->related);
    }

    /**
     * A public page cleans its sections before the text filters: the editor's script goes, the shortcode's band stays.
     *
     * Cleaning the finished HTML stripped the band's markup with the script (no covers, no data or aria attributes);
     * the fixture holds both, saved trusted by an administrator with trusted text on, so only the cleaning removes the
     * script. The page behind login keeps the trusted rendering, and is the control. Skipped without filter_shortcodes.
     */
    public function test_a_public_pages_sections_are_cleaned_before_the_filters(): void {
        global $CFG, $PAGE;
        if (\core_component::get_component_directory('filter_shortcodes') === null) {
            $this->markTestSkipped('filter_shortcodes is not installed.');
        }
        require_once($CFG->libdir . '/filterlib.php');
        $PAGE->force_theme('dimensions');
        \theme_dimensions\local\related\shortcode::reset();
        filter_set_global_state('shortcodes', TEXTFILTER_ON);
        \cache::make_from_params(\cache_store::MODE_REQUEST, 'core_filter', 'global_filters')->purge();
        \filter_manager::reset_caches();
        $CFG->enabletrusttext = 1;

        $this->setAdminUser();
        $parent = $this->course('Primeiros socorros');
        $this->course('Suporte avançado');
        \core_course\customfield\course_handler::create()->instance_form_save((object) [
            'id' => $parent->id,
            'customfield_' . landingfields::SHORTNAME_PROGRAMME . '_editor' => [
                'text' => '<p>Módulo 1.</p><script>alert(1)</script><p>[relatedcourses]</p>',
                'format' => FORMAT_HTML,
            ],
        ]);
        $values = landingfields::values((int) $parent->id);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $parent->id]);
        $renderer = $PAGE->get_renderer('core');
        $closed = new resolution(resolution::STATE_CLOSED);

        $public = (new landing_page($parent, $values, $closed, [], true))->export_for_template($renderer);
        $html = $public->sections[0]['html'];
        $this->assertStringNotContainsString('<script', $html, 'The editor\'s script is cleaned.');
        $this->assertStringContainsString('data-region="dimensions-related-block"', $html, 'The filter\'s markup stays.');
        $this->assertStringContainsString('<section', $html);
        $this->assertStringContainsString('Suporte avançado', $html);
        $this->assertStringContainsString('Módulo 1.', $html);

        $internal = (new landing_page($parent, $values, $closed, [], false))->export_for_template($renderer);
        $this->assertStringContainsString('<script', $internal->sections[0]['html'], 'Control: trusted rendering behind login.');
    }
}
