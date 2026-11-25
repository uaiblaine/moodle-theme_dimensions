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
 * Theme Dimensions - Tests for the course competencies page's first paint
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core_competency\course_module_competency;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\competency\coursecompetencies;
use tool_lp\output\course_competencies_page;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/competency/course_fixture.php');

/**
 * The course competencies page's layers: the theme's renderer decorates the first paint, the template renders from
 * core's export alone (what tool_lp/competencies gives it on the client), and both keep every hook of core's.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(tool_lp_renderer::class)]
#[CoversClass(coursecompetencies::class)]
final class course_competencies_page_test extends \advanced_testcase {
    use \theme_dimensions\local\competency\course_fixture;

    /**
     * Put the page under this theme, in the course, before any renderer exists.
     *
     * @param int $courseid The course.
     * @return void
     */
    private function under_theme(int $courseid): void {
        global $PAGE;
        $PAGE->set_url('/admin/tool/lp/coursecompetencies.php', ['courseid' => $courseid]);
        $PAGE->set_context(\core\context\course::instance($courseid));
        $PAGE->force_theme('dimensions');
    }

    /**
     * Parse a render.
     *
     * @param string $html The markup.
     * @return \DOMXPath
     */
    private static function parse(string $html): \DOMXPath {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return new \DOMXPath($document);
    }

    /**
     * A course with one competency, an activity linked to it with an outcome, and one learner rated proficient.
     *
     * @return array The fixture, plus 'cmid'.
     */
    private function course(): array {
        $fixture = $this->course_with_competencies(2, 1);
        $courseid = (int) $fixture['course']->id;
        $generator = self::getDataGenerator();
        $page = $generator->create_module('page', ['course' => $courseid, 'name' => 'Rubrics workshop']);
        $generator->get_plugin_generator('core_competency')->create_course_module_competency([
            'cmid' => $page->cmid,
            'competencyid' => $fixture['competencies'][0],
            'ruleoutcome' => course_module_competency::OUTCOME_COMPLETE,
        ]);
        $this->rate((int) $fixture['learners'][0]->id, $courseid, $fixture['competencies'][0], 2, true);
        $fixture['cmid'] = (int) $page->cmid;

        return $fixture;
    }

    /**
     * The teacher's first paint carries the distribution and the activity's outcome, beside every hook of core's.
     *
     * @return void
     */
    public function test_the_teachers_first_paint_carries_the_extras(): void {
        global $PAGE;
        $this->resetAfterTest();
        $fixture = $this->course();
        $courseid = (int) $fixture['course']->id;
        $competencyid = $fixture['competencies'][0];
        $this->setUser($fixture['teacher']);
        $this->under_theme($courseid);

        $html = $PAGE->get_renderer('tool_lp')->render(new course_competencies_page($courseid, 0));
        $xpath = self::parse($html);

        $root = "//div[@data-region='coursecompetenciespage'][@data-dim-extras='1']";
        $this->assertSame(1, $xpath->query($root)->length, 'The first paint lost its mark.');
        $row = "//tbody[contains(@class, 'drag-parentnode')]/tr[contains(@class, 'drag-samenode')][@data-id='{$competencyid}']";
        $this->assertSame(1, $xpath->query($row)->length);
        $delete = $row . "//a[@data-action='delete-competency-link'][@data-id='{$competencyid}']";
        $this->assertSame(1, $xpath->query($delete)->length);
        $this->assertSame(1, $xpath->query($row . "//select[@data-field='ruleoutcome'][@data-id]")->length);
        $slot = $row . "//*[@data-region='dim-comp-extra'][@data-competencyid='{$competencyid}']";
        $this->assertSame(1, $xpath->query($slot . "//progress[@value='50'][@max='100']")->length, 'No distribution.');
        $this->assertStringContainsString('1 of 2 proficient', $html);
        $cm = $row . "//*[@data-region='dim-comp-cm'][@data-cmid='{$fixture['cmid']}']";
        $this->assertSame(1, $xpath->query($cm . "//span[contains(@class, 'dim-comp-pill--proficient')]")->length);
        // No extra carries data-id, which core reads through closest('[data-id]').
        $this->assertSame(0, $xpath->query("//*[@data-region='dim-comp-extra' or @data-region='dim-comp-cm'][@data-id]")->length);
    }

    /**
     * The learner's first paint carries their rating as core's badge with its words, and no distribution.
     *
     * @return void
     */
    public function test_the_learners_first_paint_has_their_rating_and_no_distribution(): void {
        global $PAGE;
        $this->resetAfterTest();
        $fixture = $this->course();
        $courseid = (int) $fixture['course']->id;
        $this->setUser($fixture['learners'][0]);
        $this->under_theme($courseid);

        $html = $PAGE->get_renderer('tool_lp')->render(new course_competencies_page($courseid, 0));
        $xpath = self::parse($html);

        $this->assertSame(1, $xpath->query("//span[contains(concat(' ', @class, ' '), ' badge ')]" .
            "[contains(@class, 'dim-comp-pill--proficient')]")->length, 'The rating is not core\'s badge.');
        $extra = "//*[@data-region='dim-comp-extra']";
        $this->assertSame(0, $xpath->query($extra . "//progress")->length, 'A learner got the distribution.');
        $this->assertSame(1, $xpath->query($extra . "//p[contains(@class, 'dim-comp-course-mine')]")->length);
    }

    /**
     * The template renders from core's export alone (what the client re-render hands it): every hook, no extra and no
     * first-paint mark, so the module knows to put the extras back.
     *
     * @return void
     */
    public function test_the_template_renders_from_cores_context_alone(): void {
        global $PAGE;
        $this->resetAfterTest();
        $fixture = $this->course();
        $courseid = (int) $fixture['course']->id;
        $competencyid = $fixture['competencies'][0];
        $this->setUser($fixture['teacher']);
        $this->under_theme($courseid);
        $output = $PAGE->get_renderer('tool_lp');

        $data = (new course_competencies_page($courseid, 0))->export_for_template($output);
        $xpath = self::parse($output->render_from_template('tool_lp/course_competencies_page', $data));

        $this->assertSame(0, $xpath->query("//*[@data-dim-extras]")->length);
        $this->assertSame(1, $xpath->query("//tr[@data-id='{$competencyid}']//*[@data-region='dim-comp-extra']")->length);
        $this->assertSame(0, $xpath->query("//*[@data-region='dim-comp-extra']/*")->length);
        $this->assertSame(1, $xpath->query("//*[@data-region='dim-comp-cm'][@data-cmid='{$fixture['cmid']}']")->length);
        $this->assertSame(0, $xpath->query("//*[@data-region='dim-comp-cm']/*")->length);
        $this->assertSame(1, $xpath->query("//a[@title='View details'][@id='competency-info-link-{$competencyid}']")->length);
    }

    /**
     * The site course gets no extras and no first-paint mark: the page stays core's there. A course is the control.
     *
     * @return void
     */
    public function test_the_site_course_gets_no_extras(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->course();
        $lpg = self::getDataGenerator()->get_plugin_generator('core_competency');
        $lpg->create_course_competency(['courseid' => SITEID, 'competencyid' => $fixture['competencies'][0]]);
        $this->under_theme(SITEID);

        $html = $PAGE->get_renderer('tool_lp')->render(new course_competencies_page(SITEID, 0));
        $xpath = self::parse($html);

        $this->assertSame(1, $xpath->query("//div[@data-region='coursecompetenciespage']")->length);
        $this->assertSame(0, $xpath->query("//*[@data-dim-extras]")->length);
        $this->assertSame(0, $xpath->query("//*[@data-region='dim-comp-extra']/*")->length);
    }
}
