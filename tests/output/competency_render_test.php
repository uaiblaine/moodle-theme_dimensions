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
 * Theme Dimensions - Tests for the competency templates rendered from core's own exporters
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Renders the theme's copies of tool_lp and report_competency templates from what core really exports.
 *
 * competency_templates_test renders each copy from its own example context, which the theme wrote; an
 * example can carry a key the exporter never sends, or miss one it always does. These tests build a
 * framework, competencies, a user competency, a piece of evidence and a course with the
 * core_competency generator, hand core's renderables to core's renderers under this theme, and read
 * the hooks and the stated proficiency off the result. No renderer of the theme's is involved: the
 * theme overrides templates only, so core's tool_lp and report_competency renderers answer, and the
 * template finder hands them the theme's copies.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class competency_render_test extends \advanced_testcase {
    /**
     * Put the page under this theme, before any renderer exists.
     *
     * @param string $url The page's address.
     * @return void
     */
    private function under_theme(string $url): void {
        global $PAGE;
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url($url);
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
     * The normalised text of the first element an expression matches.
     *
     * @param \DOMXPath $xpath The parsed render.
     * @param string $expression The expression.
     * @return string The text, whitespace collapsed and trimmed.
     */
    private static function text_of(\DOMXPath $xpath, string $expression): string {
        $node = $xpath->query($expression)->item(0);
        return $node ? trim((string) preg_replace('/\s+/u', ' ', $node->textContent)) : '';
    }

    /**
     * A user competency rendered by tool_lp's renderer carries the theme's markup and every hook.
     *
     * The rating is set on the record, the scale being the generator's "A,B,C,D", so "A" is the
     * rating's name the summary has to say; proficiency is set beside it, so the summary has to say
     * "Yes" with the proficient pill's icon.
     *
     * @return void
     */
    public function test_a_user_competency_summary_from_the_exporter(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $user = $generator->create_user();
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $usercompetency = $lpg->create_user_competency([
            'userid' => $user->id,
            'competencyid' => $competency->get('id'),
            'grade' => 1,
            'proficiency' => 1,
        ]);
        $evidence = $lpg->create_evidence(['usercompetencyid' => $usercompetency->get('id')]);

        $this->under_theme('/admin/tool/lp/user_competency.php');
        $renderable = new \tool_lp\output\user_competency_summary($usercompetency);
        $xpath = self::parse($PAGE->get_renderer('tool_lp')->render($renderable));

        $competencyid = (int) $competency->get('id');
        $root = "//div[@data-region='user-competency-full-info'][@data-node='user-competency']" .
            "[@data-competencyid='{$competencyid}'][@data-userid='{$user->id}'][contains(@class, 'dim-comp-ucsummary')]";
        $this->assertSame(1, $xpath->query($root)->length, 'The theme\'s copy did not answer, or lost its root hooks.');
        $this->assertSame(1, $xpath->query("//h4[@id='competency_link_{$competencyid}']")->length);
        $this->assertSame(1, $xpath->query("//*[@data-region='user-competency-status']")->length);
        $this->assertSame(1, $xpath->query("//button[starts-with(@id, 'rate_')]")->length, 'The admin may rate: no rate button.');

        $proficiency = "//dt[contains(., 'Proficient')]/following-sibling::dd[1]";
        $this->assertSame('Yes', self::text_of($xpath, $proficiency));
        $this->assertSame(1, $xpath->query($proficiency . "//span[contains(@class, 'dim-comp-pill--proficient')]" .
            "/i[@aria-hidden='true'][contains(@class, 'fa-circle-check')]")->length);
        $rating = "//dt[contains(., 'Rating')]/following-sibling::dd[1]//span[contains(@class, 'dim-comp-pill--rated')]";
        $this->assertSame('A', self::text_of($xpath, $rating));

        $evidenceid = (int) $evidence->get('id');
        $this->assertSame(1, $xpath->query("//dl[@data-region='evidence-listing']" .
            "//div[@data-region='evidence'][@data-id='{$evidenceid}'][contains(@class, 'dim-comp-evidence')]")->length);
    }

    /**
     * The breakdown report rendered by report_competency's renderer says each rating's proficiency in words.
     *
     * Two competencies in the course: one rated and proficient, one never rated. Core painted the first
     * cell green and left the second plain; the copy must say "Proficient" in the first and only "Not
     * rated" in the second, inside the link core's Behat and the grading popup read.
     *
     * @return void
     */
    public function test_the_breakdown_report_from_the_exporter(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        $framework = $lpg->create_framework();
        $rated = $lpg->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Rated one']);
        $unrated = $lpg->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Unrated one']);
        foreach ([$rated, $unrated] as $competency) {
            $lpg->create_course_competency(['courseid' => $course->id, 'competencyid' => $competency->get('id')]);
        }
        $lpg->create_user_competency_course([
            'userid' => $user->id,
            'competencyid' => $rated->get('id'),
            'courseid' => $course->id,
            'grade' => 4,
            'proficiency' => 1,
        ]);

        $this->under_theme('/report/competency/index.php');
        $renderable = new \report_competency\output\report($course->id, $user->id, 0);
        $xpath = self::parse($PAGE->get_renderer('report_competency')->render($renderable));

        $root = "//div[@data-region='competency-breakdown-report'][@data-courseid='{$course->id}']" .
            "[@data-userid='{$user->id}'][@data-moduleid='0'][contains(@class, 'dim-comp-report')]";
        $this->assertSame(1, $xpath->query($root)->length, 'The theme\'s copy did not answer, or lost its root hooks.');

        $courseid = (int) $course->id;
        $cell = static fn(int $competencyid): string =>
            "//tr[td[1]//a[@data-action='competency-dialogue'][@data-id='{$competencyid}']]" .
            "/td[2][@data-user-competency='true'][@data-competencyid='{$competencyid}'][@data-courseid='{$courseid}']" .
            "//a[@title='User competency summary']";
        $this->assertSame('D Proficient', self::text_of($xpath, $cell((int) $rated->get('id'))));
        $this->assertSame('Not rated', self::text_of($xpath, $cell((int) $unrated->get('id'))));
        $painted = $xpath->query("//td[contains(@class, 'alert-success')]")->length;
        $this->assertSame(0, $painted, 'A proficient rating is painted, not said: core\'s green cell came back.');
    }
}
