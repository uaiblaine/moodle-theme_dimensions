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
 * Theme Dimensions - Tests for the class report's module, read from its source and from core's
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins amd/src/classratings.js against core's report_competency/grading_popup, which it mirrors: the same web
 * services, template, title string and dialogue, read from core's module so a change there reddens here; and the one
 * difference, the close reloading Report Builder's table instead of core's single-learner refresh. The behaviour
 * itself is the Behat scenario's (theme_dimensions_competency.feature).
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class classratings_source_test extends \basic_testcase {
    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = dirname(__DIR__, 2) . '/amd/src/classratings.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * Core's grading popup's source.
     *
     * @return string
     */
    private function core(): string {
        global $CFG;
        $path = $CFG->dirroot . '/report/competency/amd/src/grading_popup.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * Every name the module mirrors is in core's module: the two services, the template, the title and the dialogue.
     *
     * @return void
     */
    public function test_the_names_are_cores(): void {
        $source = $this->source();
        $core = $this->core();
        foreach (
            [
                "'tool_lp_data_for_user_competency_summary_in_course'",
                "'core_competency_user_competency_viewed_in_course'",
                "'tool_lp/user_competency_summary_in_course'",
                "'tool_lp/dialogue'",
            ] as $name
        ) {
            $this->assertStringContainsString($name, $core, "Core's module no longer names {$name}.");
            $this->assertStringContainsString($name, $source, "The module does not name {$name}.");
        }
        $this->assertStringContainsString("str.get_string('usercompetencysummary', 'report_competency')", $core);
        $this->assertStringContainsString("getString('usercompetencysummary', 'report_competency')", $source);
        $this->assertStringContainsString('context.displayuser = true;', $core);
        $this->assertStringContainsString('context.displayuser = true;', $source);
        foreach (["data('competencyid')", "data('courseid')", "data('userid')"] as $hook) {
            $this->assertStringContainsString($hook, $core, "Core's cell hook {$hook} is gone.");
        }
        $this->assertStringContainsString("'[data-user-competency=true]'", $this->core_template());
        $this->assertStringContainsString("const CELL = '[data-user-competency=\"true\"]';", $source);
    }

    /**
     * Core's breakdown template arms its popup on the same cell selector the module listens for.
     *
     * @return string
     */
    private function core_template(): string {
        global $CFG;
        return (string) file_get_contents($CFG->dirroot . '/report/competency/templates/report.mustache');
    }

    /**
     * The close reloads Report Builder's rows on the report, keeping the page, under a Pending token; core's
     * single-learner refresh is never called.
     *
     * @return void
     */
    public function test_the_close_reloads_the_report(): void {
        global $CFG;
        $source = $this->source();
        $this->assertStringContainsString(
            'dispatchEvent(reportEvents.tableReload, {preservePagination: true}, report);',
            $source
        );
        $this->assertStringContainsString('() => reload(report),', $source);
        $this->assertStringContainsString(
            "report.addEventListener(TableEvents.tableContentRefreshed, () => pending.resolve(), {once: true});",
            $source
        );
        $this->assertStringNotContainsString('report_competency_data_for_report', $source);
        $this->assertStringNotContainsString('jquery', strtolower($source));

        $events = (string) file_get_contents($CFG->dirroot . '/reportbuilder/amd/src/local/events.js');
        $this->assertStringContainsString("tableReload: 'core_reportbuilder_table_reload'", $events);
        $report = (string) file_get_contents($CFG->dirroot . '/reportbuilder/amd/src/report.js');
        $this->assertStringContainsString('document.addEventListener(reportEvents.tableReload', $report);
    }

    /**
     * The opening holds a Pending token from the press to the dialogue shown, released on a failure too.
     *
     * @return void
     */
    public function test_the_opening_is_pending(): void {
        $source = $this->source();
        $pending = strpos($source, "const pending = new Pending('theme_dimensions/classratings:open');");
        $request = strpos($source, 'await Promise.all(Ajax.call([');
        $this->assertNotFalse($pending);
        $this->assertNotFalse($request);
        $this->assertGreaterThan($pending, $request);
        $this->assertMatchesRegularExpression('~Templates\.runTemplateJS\(js\);\s*pending\.resolve\(\);~', $source);
        $this->assertMatchesRegularExpression('~\} catch \(error\) \{\s*pending\.resolve\(\);~', $source);
    }
}
