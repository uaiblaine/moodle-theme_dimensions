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
 * Theme Dimensions - Tests for the shell of the class report's React view
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use report_competency\output\report;
use theme_dimensions\local\competency\classreport;

/**
 * Report/competency/index.php in class mode: one React mount whose props carry configuration and strings and no
 * record, core's table inside a noscript beside it; `view=table` is core's table whole and no mount; any other value
 * of `view` is the mount.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(class_report_view::class)]
#[CoversClass(report_competency_renderer::class)]
final class class_report_view_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass A learner. */
    private $learner;

    /** @var \stdClass A non-editing teacher. */
    private $teacher;

    /**
     * A course with one rated competency, a learner and a teacher.
     *
     * @return void
     */
    private function course_with_learner(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $this->course = $generator->create_course(['groupmode' => VISIBLEGROUPS]);
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id'), 'shortname' => 'Salads']);
        $lpg->create_course_competency(['courseid' => $this->course->id, 'competencyid' => $competency->get('id')]);
        $this->learner = $generator->create_and_enrol($this->course, 'student', [
            'firstname' => 'Zebedee',
            'lastname' => 'Quarantotto',
            'email' => 'zebedee@example.com',
        ]);
        $this->teacher = $generator->create_and_enrol($this->course, 'teacher');
    }

    /**
     * The class report as index.php renders it without `user`, under this theme, with the request's `view`.
     *
     * @param string|null $view The `view` parameter of the request, null for none.
     * @return string The report's HTML.
     */
    private function render_page(?string $view): string {
        global $PAGE;
        if ($view !== null) {
            $_GET[class_report_view::PARAM_VIEW] = $view;
        }
        // A page of its own per render: a theme is forced once per page.
        $PAGE = new \moodle_page();
        $PAGE->set_course($this->course);
        $PAGE->set_url('/report/competency/index.php', ['id' => $this->course->id]);
        $PAGE->force_theme('dimensions');
        $renderer = $PAGE->get_renderer('report_competency');

        return $renderer->render(new report($this->course->id, $this->learner->id, 0));
    }

    /**
     * The props the mount carries, decoded from core's data attribute.
     *
     * @param string $html The page's HTML.
     * @return array The props, or [] when there is no mount.
     */
    private function props(string $html): array {
        if (!preg_match("~data-react-props='([^']*)'~", $html, $match)) {
            return [];
        }

        return json_decode(html_entity_decode($match[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Class mode prints the mount whose props are configuration and strings only, and core's table in a noscript: the
     * learner's name, email and the competency's name are on the page, inside the noscript, and in no prop.
     *
     * @return void
     */
    public function test_the_mount_carries_no_record(): void {
        $this->course_with_learner();
        $this->setUser($this->teacher);

        $html = $this->render_page(null);
        $this->assertStringContainsString('data-react-component="' . class_report_view::COMPONENT . '"', $html);
        $props = $this->props($html);
        $this->assertSame(
            ['courseid', 'moduleid', 'groupid', 'reportid', 'perpage', 'perpages', 'searchmin', 'maxcolumns', 'tableurl',
                'downloadurl', 'formats', 'labels'],
            array_keys($props)
        );
        $this->assertSame((int) $this->course->id, $props['courseid']);
        $this->assertSame(classreport::MAX_COLUMNS, $props['maxcolumns']);
        $this->assertSame(classreport::SEARCH_MIN, $props['searchmin']);
        $encoded = json_encode($props);
        // The control: the records are on the page, in core's table of the noscript.
        $this->assertMatchesRegularExpression('~<noscript>.*Zebedee Quarantotto.*</noscript>~s', $html);
        $this->assertMatchesRegularExpression('~<noscript>.*Salads.*</noscript>~s', $html);
        foreach (['Zebedee', 'Quarantotto', 'zebedee@example.com', 'Salads'] as $record) {
            $this->assertStringNotContainsString($record, $encoded, "A prop carries {$record}.");
        }
        $this->assertStringNotContainsString('"userid"', $encoded);
        // Every prop but the labels and the formats is a number, a list of numbers or one of the page's addresses.
        foreach ($props as $name => $value) {
            if (in_array($name, ['labels', 'formats', 'tableurl', 'downloadurl'], true)) {
                continue;
            }
            $this->assertTrue(is_int($value) || (is_array($value) && array_filter($value, 'is_int') === $value), $name);
        }
        $table = '/report/competency/index.php?id=' . $this->course->id . '&view=table';
        $this->assertStringContainsString($table, $props['tableurl']);
        $this->assertStringContainsString('/reportbuilder/download.php?id=' . $props['reportid'], $props['downloadurl']);
        // The root part one's module listens in, and the module armed.
        $this->assertStringContainsString('data-region="dim-comp-classreport"', $html);
    }

    /**
     * The props name the group the page resolved; the control is the same page with no group chosen.
     *
     * @return void
     */
    public function test_the_props_name_the_pages_group(): void {
        $this->course_with_learner();
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $this->learner->id]);
        $this->setUser($this->teacher);

        $this->assertSame(0, $this->props($this->render_page(null))['groupid']);

        // As index.php does: the selector's choice is read into the session before the page renders.
        $_GET['group'] = $group->id;
        groups_get_course_group($this->course, true);
        unset($_GET['group']);
        $this->assertSame((int) $group->id, $this->props($this->render_page(null))['groupid']);
    }

    /**
     * `view=table` prints core's table of the report whole and no mount.
     *
     * @return void
     */
    public function test_the_table_view_is_cores_table(): void {
        $this->course_with_learner();
        $this->setUser($this->teacher);

        $html = $this->render_page(class_report_view::VIEW_TABLE);
        $this->assertStringNotContainsString('data-react-component', $html);
        $this->assertStringNotContainsString('<noscript>', $html);
        $this->assertStringContainsString('data-region="core_reportbuilder/report"', $html);
        $this->assertStringContainsString('Zebedee Quarantotto', $html);
        $this->assertStringContainsString('data-region="dim-comp-classreport"', $html);
    }

    /**
     * Values outside the vocabulary.
     *
     * @return array
     */
    public static function other_views(): array {
        return [
            'none' => [null],
            'empty' => [''],
            'cards' => ['cards'],
            'capitals' => ['TABLE'],
            'longer' => ['tables'],
        ];
    }

    /**
     * A `view` outside the vocabulary is the default, the mount.
     *
     * @param string|null $view The parameter.
     * @return void
     */
    #[DataProvider('other_views')]
    public function test_any_other_view_is_the_mount(?string $view): void {
        $this->course_with_learner();
        $this->setUser($this->teacher);

        $html = $this->render_page($view);
        $this->assertStringContainsString('data-react-component="' . class_report_view::COMPONENT . '"', $html);
        $this->assertMatchesRegularExpression('~<noscript>.*data-region="core_reportbuilder/report".*</noscript>~s', $html);
    }
}
