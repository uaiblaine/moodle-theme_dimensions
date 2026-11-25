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
 * the hooks and the stated proficiency off the result. The theme's tool_lp renderer
 * ({@see tool_lp_renderer}) overrides render_plans_page(), which these pages never reach, and
 * render_user_competency_summary_in_course(), which adds the band's two counts to core's export; elsewhere core's
 * rendering answers and the template finder hands it the theme's copies. The learning plans page has its own tests
 * (tool_lp_renderer_test).
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

    /**
     * A learner with a rated competency in a course, three pieces of evidence and an active plan holding it.
     *
     * Two pieces of evidence are of the course; the third is of the system context, which core's list of the course
     * leaves out, so a table listing it would be listing more than core does. A teacher of the course may rate.
     *
     * @return array The course, the learner, the teacher, the competency, and the evidence of the course and elsewhere.
     */
    private function learner_in_course(): array {
        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $course = $generator->create_course();
        $learner = $generator->create_user();
        $teacher = $generator->create_user();
        $generator->enrol_user($learner->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $lpg->create_course_competency(['courseid' => $course->id, 'competencyid' => $competency->get('id')]);
        $lpg->create_user_competency_course([
            'userid' => $learner->id,
            'competencyid' => $competency->get('id'),
            'courseid' => $course->id,
            'grade' => 2,
            'proficiency' => 0,
        ]);
        $usercompetency = $lpg->create_user_competency(['userid' => $learner->id, 'competencyid' => $competency->get('id')]);
        $coursecontextid = \context_course::instance($course->id)->id;
        $incourse = [];
        foreach (['Presented the essay.', null] as $note) {
            $incourse[] = (int) $lpg->create_evidence([
                'usercompetencyid' => $usercompetency->get('id'),
                'contextid' => $coursecontextid,
                'note' => $note,
            ])->get('id');
        }
        $elsewhere = (int) $lpg->create_evidence(['usercompetencyid' => $usercompetency->get('id')])->get('id');
        $plan = $lpg->create_plan(['userid' => $learner->id, 'status' => \core_competency\plan::STATUS_ACTIVE]);
        $lpg->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $competency->get('id')]);

        return [$course, $learner, $teacher, $competency, $incourse, $elsewhere];
    }

    /**
     * The page of a learner's competency in a course, rendered by tool_lp's renderer for the session user.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $learner The learner.
     * @param \core_competency\competency $competency The competency.
     * @return array Core's own export for the same viewer, and the parsed render.
     */
    private function render_in_course(\stdClass $course, \stdClass $learner, \core_competency\competency $competency): array {
        global $PAGE;
        $this->under_theme('/admin/tool/lp/user_competency_in_course.php');
        $renderer = $PAGE->get_renderer('tool_lp');
        $renderable = new \tool_lp\output\user_competency_summary_in_course($learner->id, $competency->get('id'), $course->id);
        $export = $renderable->export_for_template($renderer);

        return [$export, self::parse($renderer->render($renderable))];
    }

    /**
     * The ids of the evidence rows, in the order drawn.
     *
     * @param \DOMXPath $xpath The parsed render.
     * @return array The ids.
     */
    private static function evidence_rows(\DOMXPath $xpath): array {
        $ids = [];
        foreach ($xpath->query("//tbody[@data-region='dim-comp-ucc-rows']/tr[@data-region='evidence']") as $row) {
            $ids[] = (int) $row->getAttribute('data-id');
        }
        return $ids;
    }

    /**
     * A teacher who may rate gets Rate as the one primary action, and the band says what core's export says.
     *
     * The four figures are read off the page and compared with core's export for the same viewer: the rating's and
     * proficiency's names, the number of pieces of evidence (two: the system context's is not the course's) with the
     * most recent one's date, and the number of plans core lists to this viewer. The rows are core's evidence, in
     * core's order.
     *
     * @return void
     */
    public function test_a_learners_competency_in_a_course_for_a_teacher_who_may_rate(): void {
        $this->resetAfterTest();
        [$course, $learner, $teacher, $competency, $incourse, $elsewhere] = $this->learner_in_course();
        $this->setUser($teacher);
        [$export, $xpath] = $this->render_in_course($course, $learner, $competency);
        $summary = $export->usercompetencysummary;

        $this->assertTrue($summary->cangrade, 'The teacher may not rate: the fixture proves nothing.');
        $root = "//div[@data-region='user-competency-full-info'][@data-region-id][contains(@class, 'dim-comp-ucc')]";
        $this->assertSame(1, $xpath->query($root)->length, 'The theme\'s copy did not answer.');
        $rate = "//*[contains(@class, 'dim-comp-ucc-primary')]/button[starts-with(@id, 'rate_')][contains(@class, 'btn-primary')]";
        $this->assertSame(1, $xpath->query($rate)->length);
        $this->assertSame(1, $xpath->query("//*[contains(concat(' ', @class, ' '), ' btn-primary ')]")->length);

        $rating = self::text_of($xpath, "//dt[contains(., 'Rating')]/following-sibling::dd[1]");
        $this->assertSame($summary->usercompetencycourse->gradename, $rating);
        $proficiency = self::text_of($xpath, "//dt[contains(., 'Proficient')]/following-sibling::dd[1]");
        $this->assertSame($summary->usercompetencycourse->proficiencyname, $proficiency);
        $this->assertCount(2, $summary->evidence);
        $this->assertSame((string) count($summary->evidence), self::text_of($xpath, "//dd[@data-dim-figure='evidence']"));
        $this->assertSame($summary->evidence[0]->userdate, self::text_of($xpath, "//span[@data-dim-figure='latest']"));
        $this->assertSame((string) count($export->plans), self::text_of($xpath, "//dd[@data-dim-figure='plans']"));

        $exported = array_map(static fn($evidence): int => (int) $evidence->id, $summary->evidence);
        $this->assertSame($exported, self::evidence_rows($xpath));
        sort($exported);
        $this->assertSame($incourse, $exported);
        $this->assertNotContains($elsewhere, self::evidence_rows($xpath));
    }

    /**
     * The learner reading their own competency gets the band and the table, and no Rate.
     *
     * The learner is the control of the teacher's Rate: same page, same record, core's cangrade false. Their own
     * active plan is listed to them, so the plans figure is one, as core's export says.
     *
     * @return void
     */
    public function test_a_learners_competency_in_a_course_for_the_learner(): void {
        $this->resetAfterTest();
        [$course, $learner, , $competency] = $this->learner_in_course();
        $this->setUser($learner);
        [$export, $xpath] = $this->render_in_course($course, $learner, $competency);

        $this->assertFalse($export->usercompetencysummary->cangrade);
        $this->assertSame(0, $xpath->query("//button[starts-with(@id, 'rate_')]")->length, 'The learner is offered Rate.');
        $this->assertSame(0, $xpath->query("//*[contains(concat(' ', @class, ' '), ' btn-primary ')]")->length);
        $this->assertSame(4, $xpath->query("//*[@data-region='dim-comp-ucc-band']//dt")->length);
        $this->assertCount(1, $export->plans);
        $this->assertSame('1', self::text_of($xpath, "//dd[@data-dim-figure='plans']"));
        $this->assertSame(1, $xpath->query("//li[@data-dim-plan]/a[contains(@href, '/admin/tool/lp/plan.php?id=')]")->length);
        $exported = array_map(static fn($evidence): int => (int) $evidence->id, $export->usercompetencysummary->evidence);
        $this->assertSame($exported, self::evidence_rows($xpath));
    }

    /**
     * Core's context alone, as the client re-render receives it, draws the band and the table.
     *
     * The export is cleaned through the return structure of tool_lp_data_for_user_competency_summary_in_course, as
     * the web service does before tool_lp/user_competency_info renders it, and rendered through the theme's template
     * with no decoration: every row is there and the two counts are left for the template's JavaScript.
     *
     * @return void
     */
    public function test_a_learners_competency_in_a_course_from_cores_context_alone(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$course, $learner, $teacher, $competency] = $this->learner_in_course();
        $this->setUser($teacher);
        $this->under_theme('/admin/tool/lp/user_competency_in_course.php');
        $renderer = $PAGE->get_renderer('tool_lp');
        $renderable = new \tool_lp\output\user_competency_summary_in_course($learner->id, $competency->get('id'), $course->id);
        $context = \core_external\external_api::clean_returnvalue(
            \tool_lp\external::data_for_user_competency_summary_in_course_returns(),
            $renderable->export_for_template($renderer)
        );
        $this->assertArrayNotHasKey('dimband', $context);
        $xpath = self::parse($renderer->render_from_template('tool_lp/user_competency_summary_in_course', $context));

        $this->assertSame(1, $xpath->query("//button[starts-with(@id, 'rate_')]")->length);
        $this->assertSame(4, $xpath->query("//*[@data-region='dim-comp-ucc-band']//dt")->length);
        $this->assertSame('', self::text_of($xpath, "//dd[@data-dim-figure='evidence']"));
        $evidence = $context['usercompetencysummary']['evidence'];
        $this->assertSame($evidence[0]['userdate'], self::text_of($xpath, "//span[@data-dim-figure='latest']"));
        $this->assertSame(array_map(static fn(array $row): int => (int) $row['id'], $evidence), self::evidence_rows($xpath));
    }

    /**
     * The evidence page says the status of a competency waiting for review and says nothing for an idle one.
     *
     * Core's exporter names an idle user competency "-"; the item's line and the detail's list print the status
     * only when it is a review state. One evidence is linked to a waiting competency (the control: its status is
     * in the line and in the pill) and to an idle one (no muted status and no pill).
     *
     * @return void
     */
    public function test_the_evidence_page_prints_no_status_for_an_idle_competency(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $user = $generator->create_user();
        $framework = $lpg->create_framework();
        $evidence = $lpg->create_user_evidence(['userid' => $user->id]);
        $statuses = [
            'Waiting competency' => \core_competency\user_competency::STATUS_WAITING_FOR_REVIEW,
            'Idle competency' => \core_competency\user_competency::STATUS_IDLE,
        ];
        foreach ($statuses as $shortname => $status) {
            $competency = $lpg->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'shortname' => $shortname,
            ]);
            $lpg->create_user_competency([
                'userid' => $user->id,
                'competencyid' => $competency->get('id'),
                'status' => $status,
            ]);
            $lpg->create_user_evidence_competency([
                'userevidenceid' => $evidence->get('id'),
                'competencyid' => $competency->get('id'),
            ]);
        }

        $this->under_theme('/admin/tool/lp/user_evidence_list.php');
        $xpath = self::parse($PAGE->get_renderer('tool_lp')->render(new \tool_lp\output\user_evidence_list_page($user->id)));
        $waiting = (string) \core_competency\user_competency::get_status_name(
            \core_competency\user_competency::STATUS_WAITING_FOR_REVIEW
        );

        $line = "//ul[contains(@class, 'user-evidence-competencies')]/li[contains(., '%s')]";
        $pill = "//ul[contains(@class, 'dim-comp-ev-detail-comps')]/li[contains(., '%s')]" .
            "//span[contains(@class, 'dim-comp-pill--status')]";
        $this->assertSame($waiting, self::text_of($xpath, sprintf($line, 'Waiting competency') . "/span[@class='dim-comp-muted']"));
        $this->assertSame($waiting, self::text_of($xpath, sprintf($pill, 'Waiting competency')));
        $this->assertSame(1, $xpath->query(sprintf($line, 'Idle competency'))->length, 'The idle competency is still listed.');
        $this->assertSame(0, $xpath->query(sprintf($line, 'Idle competency') . '/span')->length, 'A status is printed for it.');
        $this->assertSame(0, $xpath->query(sprintf($pill, 'Idle competency'))->length, 'A status pill is printed for it.');
    }
}
