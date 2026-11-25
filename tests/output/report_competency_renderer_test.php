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
 * Theme Dimensions - Tests for the report_competency renderer: the class report and core's breakdown
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use context_course;
use PHPUnit\Framework\Attributes\CoversClass;
use report_competency\output\report;
use report_competency\output\user_course_navigation;
use theme_dimensions\local\competency\classreport;

/**
 * Report/competency/index.php under the theme: the class report without `user`, core's breakdown with it, and core's
 * breakdown for a viewer who reads learners only through per-user grants.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_competency_renderer::class)]
#[CoversClass(classreport::class)]
final class report_competency_renderer_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass A learner. */
    private $learner;

    /** @var \stdClass A non-editing teacher. */
    private $teacher;

    /**
     * A course with one competency, a learner and a teacher.
     *
     * @return void
     */
    private function course_with_learner(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $this->course = $generator->create_course();
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Salads']);
        $lpg->create_course_competency(['courseid' => $this->course->id, 'competencyid' => $competency->get('id')]);
        $this->learner = $generator->create_and_enrol($this->course, 'student');
        $this->teacher = $generator->create_and_enrol($this->course, 'teacher');
    }

    /**
     * Put report/competency/index.php of the course under this theme, before any renderer exists.
     *
     * @param int|null $user The `user` parameter of the request, null for none.
     * @return report_competency_renderer
     */
    private function page(?int $user): report_competency_renderer {
        global $PAGE;
        if ($user !== null) {
            $_GET[classreport::PARAM_USER] = $user;
        }
        $PAGE->set_course($this->course);
        $PAGE->set_url('/report/competency/index.php', ['id' => $this->course->id]);
        $PAGE->force_theme('dimensions');

        return $PAGE->get_renderer('report_competency');
    }

    /**
     * The factory really does hand over this theme's renderer.
     *
     * The class name a theme override must carry is derived, not declared, so a file in the wrong directory or a
     * namespace off by one level fails by silently doing nothing at all: core's renderer answers, and the page is
     * core's one learner again.
     *
     * @return void
     */
    public function test_the_theme_renderer_is_the_one_the_factory_returns(): void {
        $this->course_with_learner();
        $this->assertInstanceOf(report_competency_renderer::class, $this->page(null));
    }

    /**
     * Without `user`, the report is the class report, core's export is not called (no row inserted for the learner
     * index.php picked), and the learner's heading is not drawn.
     *
     * @return void
     */
    public function test_without_a_learner_the_page_is_the_class_report(): void {
        global $DB;
        $this->course_with_learner();
        $this->setUser($this->teacher);
        $renderer = $this->page(null);
        $before = $DB->count_records('competency_usercompcourse');

        $html = $renderer->render(new report($this->course->id, $this->learner->id, 0));
        $this->assertStringContainsString('data-region="dim-comp-classreport"', $html);
        $this->assertStringNotContainsString('competency-breakdown-report', $html);
        $this->assertSame($before, $DB->count_records('competency_usercompcourse'), 'Core\'s export was not called.');
        $this->assertSame('', $renderer->context_header(['heading' => 'First learner'], 3));
    }

    /**
     * With `user`, the report is core's breakdown through the theme's copy, and the learner's heading is core's.
     *
     * @return void
     */
    public function test_with_a_learner_the_page_is_cores_breakdown(): void {
        $this->course_with_learner();
        $this->setUser($this->teacher);
        $renderer = $this->page((int) $this->learner->id);

        $html = $renderer->render(new report($this->course->id, $this->learner->id, 0));
        $this->assertStringContainsString('data-region="competency-breakdown-report"', $html);
        $this->assertStringContainsString('dim-comp-report', $html);
        $this->assertStringNotContainsString('dim-comp-classreport', $html);
        $this->assertStringContainsString('First learner', $renderer->context_header(['heading' => 'First learner'], 3));
    }

    /**
     * A viewer who reads the learner only through a grant in the learner's user context keeps core's one-learner page
     * without `user`: the class needs the course-level capability.
     *
     * @return void
     */
    public function test_a_per_user_grant_keeps_cores_page(): void {
        $this->course_with_learner();
        $mentor = $this->getDataGenerator()->create_user();
        $roleid = create_role('Mentor', 'compmentor', '');
        $usercontext = \context_user::instance($this->learner->id);
        assign_capability(classreport::USERVIEW, CAP_ALLOW, $roleid, $usercontext);
        role_assign($roleid, $mentor->id, $usercontext);
        $this->setUser($mentor);
        $this->assertFalse(classreport::may_view(context_course::instance($this->course->id)), 'Precondition.');

        $html = $this->page(null)->render(new report($this->course->id, $this->learner->id, 0));
        $this->assertStringContainsString('data-region="competency-breakdown-report"', $html);
        $this->assertStringNotContainsString('dim-comp-classreport', $html);
    }

    /**
     * The navigation: in class mode "All learners" is chosen and the hidden user is 0; on a breakdown it is offered
     * first and the learner stays chosen.
     *
     * @return void
     */
    public function test_the_navigation_offers_all_learners(): void {
        $this->course_with_learner();
        $this->setUser($this->teacher);
        $baseurl = new \moodle_url('/report/competency/index.php');

        $html = $this->page(null)->render(new user_course_navigation($this->learner->id, $this->course->id, $baseurl, 0));
        $this->assertMatchesRegularExpression('~<option value="0"\s+selected="selected">All learners</option>~', $html);
        $this->assertStringContainsString('<input type="hidden" name="user" value="0">', $html);
        $this->assertStringNotContainsString('<option value="' . $this->learner->id . '" selected', $html);
    }

    /**
     * On a breakdown the navigation offers "All learners" without choosing it.
     *
     * @return void
     */
    public function test_a_breakdowns_navigation_keeps_its_learner(): void {
        $this->course_with_learner();
        $this->setUser($this->teacher);
        $baseurl = new \moodle_url('/report/competency/index.php');

        $renderer = $this->page((int) $this->learner->id);
        $html = $renderer->render(new user_course_navigation($this->learner->id, $this->course->id, $baseurl, 0));
        $this->assertMatchesRegularExpression('~<option value="0"\s*>All learners</option>~', $html);
        $this->assertMatchesRegularExpression('~<option value="' . $this->learner->id . '"\s+selected="selected">~', $html);
        $this->assertStringContainsString('<input type="hidden" name="user" value="' . $this->learner->id . '">', $html);
    }
}
